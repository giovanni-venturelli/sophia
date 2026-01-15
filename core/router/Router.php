<?php

namespace Sophia\Router;

use Sophia\Component\ComponentRegistry;
use Sophia\Component\Renderer;
use Sophia\Controller\ControllerRegistry;
use Sophia\Injector\Injectable;
use Sophia\Injector\Injector;
use Sophia\Router\Models\MiddlewareInterface;
use function call_user_func;

#[Injectable(providedIn: 'root')]
class Router
{
    private static array $dispatchCache = [];
    /** @var self|null */
    private static ?self $instance = null;

    /** @var array */
    private array $routes = [];

    /** @var string */
    private string $basePath = '';

    /** @var array|null */
    private ?array $currentRoute = null;

    /** @var array */
    private array $currentParams = [];

    /** @var array */
    private array $currentRouteData = [];

    private ?Renderer $renderer = null;
    private ?ComponentRegistry $componentRegistry = null;
    private ?ControllerRegistry $controllerRegistry = null;
    private static array $routeCache = [];
    private static array $urlCache = [];

    /**
     * Singleton
     */
    public static function getInstance(): self
    {
        try {
            /** @var self $instance */
            $instance = Injector::inject(self::class);
            return $instance;
        } catch (\Throwable) {
            if (!self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }
    }

    /**
     * 🔥 DISPATCH con supporto Guards/Middleware e Controllers
     */
    public function dispatch(): void
    {
        $uri = $this->getCurrentPath();
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // ⚡ CACHE: Stesso URI + stesso method = stessa route
        $cacheKey = $method . ':' . $uri;

        if (isset(self::$dispatchCache[$cacheKey])) {
            $cached = self::$dispatchCache[$cacheKey];

            // Ripristina stato da cache
            $this->currentRoute = $cached['route'];
            $this->currentParams = $cached['params'];
            $this->currentRouteData = $cached['data'];

            // Se è una callback, eseguila
            if (isset($cached['callback'])) {
                call_user_func($cached['callback'], $cached['params'], $cached['route']);
                return;
            }

            // Se è un componente, renderizzalo
            if (isset($cached['component'])) {
                $selector = $this->componentRegistry->lazyRegister($cached['component']);
                $data = array_merge($cached['params'], ['routeData' => $cached['data']]);
                echo $this->renderer->renderRoot($selector, $data, $cached['slotContent'] ?? null);
                return;
            }

            // Se è un redirect
            if (isset($cached['redirect'])) {
                header('Location: ' . $cached['redirect']);
                exit;
            }

            return;
        }

        // ⚡ DISPATCH ORIGINALE (solo se non in cache)
        $result = $this->doDispatch($uri, $method);

        // ⚡ SALVA in cache
        if ($result) {
            self::$dispatchCache[$cacheKey] = $result;
        }
    }

    private function doDispatch(string $uri, string $method): ?array
    {
        // Priority 1: Controller Routes
        if ($this->tryDispatchController($uri, $method)) {
            // Controllers non vanno in cache perché potrebbero avere side effects
            return null;
        }

        // Priority 2: POST callbacks
        if (strtoupper($method) === 'POST') {
            foreach ($this->routes as $route) {
                $routePath = $this->normalizePath($route['path'] ?? '');
                $match = $this->matchPathWithParams($routePath, $uri, $route);
                if ($match && isset($route['callback']) && is_callable($route['callback'])) {
                    [$params] = $match;

                    $result = [
                        'route' => $route,
                        'params' => $params,
                        'data' => $route['data'] ?? [],
                        'callback' => $route['callback'],
                    ];

                    call_user_func($route['callback'], $params, $route);
                    return $result;
                }
            }
        }

        // Priority 3: Nested route chain
        $chainMatch = $this->matchRouteChain($uri);

        if ($chainMatch) {
            [$chain, $params] = $chainMatch;

            $mergedData = [];
            $mergedGuards = [];
            $redirectTo = null;
            $callback = null;

            foreach ($chain as $node) {
                if (isset($node['data']) && is_array($node['data'])) {
                    $mergedData = array_merge($mergedData, $node['data']);
                }
                if (!empty($node['canActivate'])) {
                    $mergedGuards = array_merge($mergedGuards, $node['canActivate']);
                }
                if (isset($node['redirectTo'])) {
                    $redirectTo = $node['redirectTo'];
                }
                if (isset($node['callback']) && is_callable($node['callback'])) {
                    $callback = $node['callback'];
                }
            }

            $this->currentRoute = end($chain) ?: null;
            $this->currentParams = $params;
            $this->currentRouteData = $mergedData;

            if (!empty($mergedGuards)) {
                if (!$this->executeGuards($mergedGuards)) {
                    return null;
                }
            }

            if ($redirectTo) {
                $target = $this->normalizePath($redirectTo);
                $url = $this->basePath . '/' . $target;

                $result = [
                    'route' => end($chain),
                    'params' => $params,
                    'data' => $mergedData,
                    'redirect' => $url,
                ];

                header('Location: ' . $url);
                exit;
            }

            if ($callback) {
                $result = [
                    'route' => end($chain),
                    'params' => $params,
                    'data' => $mergedData,
                    'callback' => $callback,
                ];

                call_user_func($callback, $params, end($chain));
                return $result;
            }

            if ($this->renderer && $this->componentRegistry) {
                $rendered = null;
                $topIndex = null;

                for ($i = 0; $i < count($chain); $i++) {
                    if (isset($chain[$i]['component'])) {
                        $topIndex = $i;
                        break;
                    }
                }

                for ($i = count($chain) - 1; $i >= 0; $i--) {
                    $node = $chain[$i];
                    if (!isset($node['component'])) {
                        continue;
                    }

                    $componentClass = $node['component'];
                    if (!class_exists($componentClass)) {
                        http_response_code(500);
                        echo "Component '{$componentClass}' not found";
                        return null;
                    }

                    $selector = $this->componentRegistry->lazyRegister($componentClass);
                    $data = array_merge($this->currentParams, ['routeData' => $this->currentRouteData]);

                    $slotContent = $rendered !== null ? '<router-outlet name="outlet">' . $rendered . '</router-outlet>' : null;

                    if ($i === $topIndex) {
                        $result = [
                            'route' => end($chain),
                            'params' => $params,
                            'data' => $mergedData,
                            'component' => $componentClass,
                            'slotContent' => $slotContent,
                        ];

                        echo $this->renderer->renderRoot($selector, $data, $slotContent);
                        return $result;
                    } else {
                        $rendered = $this->renderer->renderComponent($selector, $data, $slotContent);
                    }
                }
            }
        }

        // Priority 4: Fallback matching
        $match = $this->matchRoute($uri);

        if (!$match) {
            $this->handleNotFound();
            return null;
        }

        [$route, $params] = $match;
        $this->currentRoute = $route;
        $this->currentParams = $params;
        $this->currentRouteData = $route['data'] ?? [];

        if (!empty($route['canActivate'])) {
            if (!$this->executeGuards($route['canActivate'])) {
                return null;
            }
        }

        if (isset($route['redirectTo'])) {
            $target = $this->normalizePath($route['redirectTo']);
            $url = $this->basePath . '/' . $target;

            $result = [
                'route' => $route,
                'params' => $params,
                'data' => $route['data'] ?? [],
                'redirect' => $url,
            ];

            header('Location: ' . $url);
            exit;
        }

        if (isset($route['callback']) && is_callable($route['callback'])) {
            $result = [
                'route' => $route,
                'params' => $params,
                'data' => $route['data'] ?? [],
                'callback' => $route['callback'],
            ];

            call_user_func($route['callback'], $params, $route);
            return $result;
        }

        if (isset($route['component'])) {
            $componentClass = $route['component'];

            if (!class_exists($componentClass)) {
                http_response_code(500);
                echo "Component '{$componentClass}' not found";
                return null;
            }

            if (!$this->renderer || !$this->componentRegistry) {
                http_response_code(500);
                echo "Renderer or ComponentRegistry not configured";
                return null;
            }

            $selector = $this->componentRegistry->lazyRegister($componentClass);
            $data = array_merge($this->currentParams, [
                'routeData' => $this->currentRouteData,
            ]);

            $result = [
                'route' => $route,
                'params' => $params,
                'data' => $route['data'] ?? [],
                'component' => $componentClass,
            ];

            echo $this->renderer->renderRoot($selector, $data);
            return $result;
        }

        $this->handleNotFound();
        return null;
    }

    /**
     * ⚡ Pulisci cache (sviluppo)
     */
    public static function clearDispatchCache(): void
    {
        self::$dispatchCache = [];
    }

    private function walkControllerRoutes(
        array $routes,
        string $uri,
        string $method,
        string $parentPath = '',
        array $parentGuards = [],
        array $parentData = []
    ): bool {
        foreach ($routes as $route) {
            $routePath = $this->normalizePath($route['path'] ?? '');
            $fullPath = trim(
                ($parentPath !== '' ? $parentPath . '/' : '') . $routePath,
                '/'
            );

            $guards = array_merge($parentGuards, $route['canActivate'] ?? []);
            $data   = array_merge($parentData, $route['data'] ?? []);

            // ⚡ CONTROLLER MATCH - con early exit
            if (isset($route['controller'])) {
                // ⚡ SKIP se path non matcha prefix
                if ($fullPath !== '' && !str_starts_with($uri, $fullPath)) {
                    goto check_children; // ⚡ Evita nesting pesante
                }

                $relativePath = $fullPath !== ''
                    ? substr($uri, strlen($fullPath))
                    : $uri;

                $relativePath = ltrim($relativePath, '/');

                if (!$this->controllerRegistry) {
                    $this->controllerRegistry = new ControllerRegistry();
                }

                $match = $this->controllerRegistry->matchControllerMethod(
                    $route['controller'],
                    $method,
                    $relativePath
                );

                if ($match) {
                    if (!empty($guards)) {
                        if (!$this->executeGuards($guards)) {
                            return true; // ⚡ Guard blocked, ma match trovato
                        }
                    }

                    $this->currentRoute = $route;
                    $this->currentParams = $match['params'];
                    $this->currentRouteData = $data;

                    $result = $this->controllerRegistry->invokeControllerMethod(
                        $route['controller'],
                        $match['methodName'],
                        $match['params']
                    );

                    $this->handleControllerResponse($result);
                    return true; // ⚡ FOUND! Exit immediatamente
                }
            }

            check_children:

            // ⚡ CHILDREN - solo se necessario
            if (!empty($route['children'])) {
                if ($this->walkControllerRoutes(
                    $route['children'],
                    $uri,
                    $method,
                    $fullPath,
                    $guards,
                    $data
                )) {
                    return true; // ⚡ Found in children, exit
                }
            }

            // ⚡ IMPORTS - solo se necessario
            if (!empty($route['imports'])) {
                foreach ($route['imports'] as $import) {
                    if (!empty($import['children'])) {
                        if ($this->walkControllerRoutes(
                            $import['children'],
                            $uri,
                            $method,
                            $fullPath,
                            $guards,
                            $data
                        )) {
                            return true; // ⚡ Found in imports, exit
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * 🔥 NUOVO: Prova a dispatchare una route con controller
     */
    private function tryDispatchController(string $uri, string $method): bool
    {
        // ⚡ SKIP IMMEDIATO se non ci sono controller
        $hasControllers = false;
        foreach ($this->routes as $route) {
            if (isset($route['controller'])) {
                $hasControllers = true;
                break;
            }
        }

        if (!$hasControllers) {
            return false; // ⚡ Risparmio: non scansionare se non necessario
        }

        return $this->walkControllerRoutes(
            $this->routes,
            $uri,
            strtoupper($method)
        );
    }



    /**
     * 🔥 NUOVO: Gestisce la risposta di un controller
     */
    private function handleControllerResponse(mixed $result): void
    {
        if ($result === null) {
            return;
        }

        // Se è già una stringa, output diretto
        if (is_string($result)) {
            echo $result;
            return;
        }

        // Se è un array o oggetto, serializza come JSON
        if (is_array($result) || is_object($result)) {
            header('Content-Type: application/json');
            echo json_encode($result);
            return;
        }

        // Altri tipi: converti a stringa
        echo (string)$result;
    }

    /**
     * 🔥 Esegue i guards della route
     */
    private function executeGuards(array $guards): bool
    {
        foreach ($guards as $guardClass) {
            if (is_string($guardClass)) {
                if (!class_exists($guardClass)) {
                    throw new \RuntimeException("Guard class '{$guardClass}' not found");
                }

                $guard = new $guardClass();

                if (!$guard instanceof MiddlewareInterface) {
                    throw new \RuntimeException(
                        "Guard '{$guardClass}' must implement " . MiddlewareInterface::class
                    );
                }
            } elseif ($guardClass instanceof MiddlewareInterface) {
                $guard = $guardClass;
            } else {
                throw new \RuntimeException("Invalid guard type");
            }

            if (!$guard->handle()) {
                return false;
            }
        }

        return true;
    }

    public function configure(array $routes): void
    {
        $this->routes = $routes;
    }

    public function get(string $path, string $component, array $options = []): void
    {
        $this->addRoute('GET', $path, $component, $options);
    }

    public function post(string $path, string $component, array $options = []): void
    {
        $this->addRoute('POST', $path, $component, $options);
    }

    private function addRoute(string $method, string $path, string $component, array $options = []): void
    {
        $this->routes[] = array_merge($options, [
            'path' => $path,
            'component' => $component,
            'method' => $method
        ]);
    }

    public function setBasePath(string $basePath): void
    {
        if (empty($basePath)) {
            $this->basePath = '';
            return;
        }
        if ($basePath[0] !== '/') {
            $basePath = '/' . $basePath;
        }
        if (strlen($basePath) > 1) {
            $basePath = rtrim($basePath, '/');
        }
        $this->basePath = $basePath;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function setRenderer(Renderer $renderer): void
    {
        $this->renderer = $renderer;
    }

    public function setComponentRegistry(ComponentRegistry $registry): void
    {
        $this->componentRegistry = $registry;
    }

    public function setControllerRegistry(ControllerRegistry $registry): void
    {
        $this->controllerRegistry = $registry;
    }

    public function getCurrentRouteData(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->currentRouteData;
        }
        return $this->currentRouteData[$key] ?? null;
    }

    public function getCurrentParams(): array
    {
        return $this->currentParams;
    }

    public function url(string $name, array $params = []): string
    {
        // ⚡ Cache del risultato finale
        $cacheKey = $name . ':' . json_encode($params);
        if (isset(self::$urlCache[$cacheKey])) {
            return self::$urlCache[$cacheKey];
        }

        // ⚡ Cache del lookup della route
        if (!isset(self::$routeCache[$name])) {
            $result = $this->findRouteAndFullPath($name);
            if (!$result) {
                return '#';
            }
            self::$routeCache[$name] = $result;
        }

        $result = self::$routeCache[$name];
        $route = $result['route'];
        $path = $result['fullPath'];

        $path = $this->normalizePath($path);

        if (trim($this->basePath) !== '') {
            $path = implode('/', [$this->basePath, $path]);
        }

        if ($path !== '') {
            $segments = explode('/', $path);
            foreach ($segments as $i => $segment) {
                if (str_starts_with($segment, ':')) {
                    $paramName = substr($segment, 1);
                    if (!array_key_exists($paramName, $params)) {
                        throw new \InvalidArgumentException("Missing param '{$paramName}' for '{$name}'");
                    }
                    $segments[$i] = $params[$paramName];
                }
            }
            $path = implode('/', $segments);
        }

        $finalUrl = '/' . ltrim($path, '/');

        // ⚡ Salva in cache il risultato
        self::$urlCache[$cacheKey] = $finalUrl;

        return $finalUrl;
    }

    /**
     * ⚡ NUOVO: Pulisce la cache (per sviluppo)
     */
    public static function clearUrlCache(): void
    {
        self::$routeCache = [];
        self::$urlCache = [];
    }

    private function findRouteAndFullPath(string $name, array $routes = null, string $parentPath = ''): ?array
    {
        $routes = $routes ?? $this->routes;
        foreach ($routes as $route) {
            $currentPath = $route['path'] ?? '';
            $fullPath = $parentPath;
            if ($currentPath !== '') {
                if ($fullPath !== '') {
                    $fullPath .= '/' . $currentPath;
                } else {
                    $fullPath = $currentPath;
                }
            }

            if (($route['name'] ?? null) === $name) {
                return [
                    'route' => $route,
                    'fullPath' => $fullPath
                ];
            }

            if (!empty($route['children'])) {
                $found = $this->findRouteAndFullPath($name, $route['children'], $fullPath);
                if ($found) {
                    return $found;
                }
            }

            if (!empty($route['imports'])) {
                foreach ($route['imports'] as $importedRoute) {
                    if (is_array($importedRoute) && !empty($importedRoute['children'])) {
                        $found = $this->findRouteAndFullPath($name, $importedRoute['children'], $fullPath);
                        if ($found) {
                            return $found;
                        }
                    }
                }
            }
        }

        return null;
    }

    private function findRouteByName(string $name): ?array
    {
        $result = $this->findRouteAndFullPath($name);
        return $result ? $result['route'] : null;
    }

    public function getCurrentPath(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        if ($this->basePath && str_starts_with($path, $this->basePath)) {
            $path = substr($path, strlen($this->basePath));
            if ($path === '') {
                $path = '/';
            }
        }

        return ltrim($path, '/');
    }

    private function matchRoute(string $path): ?array
    {
        foreach ($this->routes as $route) {
            $match = $this->matchSingleRoute($route, $path);
            if ($match) {
                return $match;
            }
        }
        return null;
    }

    private function matchRouteChain(string $path): ?array
    {
        // ⚡ Cache dei pattern compilati
        static $patternCache = [];

        foreach ($this->routes as $route) {
            $res = $this->matchNodeChain($route, $path, '', $patternCache);
            if ($res) return $res; // ⚡ Exit appena trova
        }
        return null;
    }

    private function matchNodeChain(array $node, string $requestPath, string $accumulated, array &$patternCache): ?array
    {
        $nodePath = $this->normalizePath($node['path'] ?? '');
        $fullPath = trim(($accumulated !== '' ? ($accumulated . '/') : '') . $nodePath, '/');

        // ⚡ EARLY EXIT: Se path non matcha, skip children
        // Ma solo se non ci sono parametri dinamici (es. :id, :token)
        if ($fullPath !== '' && !str_contains($fullPath, ':')) {
            if (!str_starts_with($requestPath, $fullPath) && $requestPath !== $fullPath) {
                return null; // ⚡ Non può matchare, skip subito
            }
        }

        if (!empty($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $child) {
                $res = $this->matchNodeChain($child, $requestPath, $fullPath, $patternCache);
                if ($res) {
                    [$chain, $params] = $res;
                    array_unshift($chain, $node);
                    return [$chain, $params]; // ⚡ Exit appena trova
                }
            }
            return null;
        }

        $match = $this->matchPathWithParams($fullPath, $requestPath, $node);
        if ($match) {
            [$params] = $match;
            return [[$node], $params];
        }

        return null;
    }

    private function matchSingleRoute(array $route, string $path): ?array
    {
        $routePath = $route['path'] ?? '';

        if (!empty($route['children']) && is_array($route['children'])) {
            $parentPath = $this->normalizePath($routePath);
            $parentPathMatch = $route['pathMatch'] ?? 'prefix';

            if ($parentPathMatch === 'full') {
                if ($path !== $parentPath && !str_starts_with($path, $parentPath . '/')) {
                    return null;
                }
            } else {
                if ($path !== $parentPath && !str_starts_with($path, $parentPath . '/')) {
                    return null;
                }
            }

            foreach ($route['children'] as $child) {
                $childPath = $this->normalizePath($child['path'] ?? '');
                $fullChildPath = $parentPath;
                if ($childPath !== '') {
                    if ($fullChildPath !== '') {
                        $fullChildPath .= '/' . $childPath;
                    } else {
                        $fullChildPath = $childPath;
                    }
                }
                $match = $this->matchPathWithParams($fullChildPath, $path, $child);
                if ($match) {
                    [$params] = $match;

                    $parentGuards = $route['canActivate'] ?? [];
                    $childGuards = $child['canActivate'] ?? [];

                    $mergedRoute = array_merge($route, $child);
                    $mergedRoute['canActivate'] = array_merge($parentGuards, $childGuards);

                    unset($mergedRoute['children']);
                    return [$mergedRoute, $params];
                }
            }

            return null;
        }

        if (empty($route['children'])) {
            if ($routePath === '*' || $routePath === '') {
                return [$route, []];
            }
        }

        $routePath = $this->normalizePath($routePath);
        $match = $this->matchPathWithParams($routePath, $path, $route);
        if ($match) {
            [$params] = $match;
            return [$route, $params];
        }

        return null;
    }

    private function normalizePath(string $path): string
    {
        return trim(trim($path), '/');
    }

    private function matchPathWithParams(string $routePath, string $requestPath, array $route = []): ?array
    {
        $pathMatch = $route['pathMatch'] ?? 'prefix';

        $routeSegments = $routePath === '' ? [] : explode('/', $routePath);
        $requestSegments = $requestPath === '' ? [] : explode('/', $requestPath);

        if ($pathMatch === 'full') {
            if (count($routeSegments) !== count($requestSegments)) {
                return null;
            }
        } else {
            if (count($routeSegments) > count($requestSegments)) {
                return null;
            }
        }

        $params = [];
        foreach ($routeSegments as $index => $segment) {
            if (!isset($requestSegments[$index])) {
                return null;
            }

            $value = $requestSegments[$index];

            if (str_starts_with($segment, ':')) {
                $paramName = substr($segment, 1);
                $params[$paramName] = $value;
                continue;
            }

            if ($segment !== $value) {
                return null;
            }
        }

        if ($pathMatch === 'full' && count($requestSegments) > count($routeSegments)) {
            return null;
        }

        return [$params];
    }

    private function handleNotFound(): void
    {
        http_response_code(404);
        echo '404 - Page not found';
    }
}