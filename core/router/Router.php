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

        // 🔥 PRIORITÀ 1: Controller Routes (API/AJAX)
        if ($this->tryDispatchController($uri, $method)) {
            return;
        }

        // 🔥 PRIORITÀ 2: POST callbacks (form submissions)
        if (strtoupper($method) === 'POST') {
            foreach ($this->routes as $route) {
                $routePath = $this->normalizePath($route['path'] ?? '');
                $match = $this->matchPathWithParams($routePath, $uri, $route);
                if ($match && isset($route['callback']) && is_callable($route['callback'])) {
                    [$params] = $match;
                    call_user_func($route['callback'], $params, $route);
                    return;
                }
            }
        }

        // 🔥 PRIORITÀ 3: Nested route chain (layout + children)
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
                    return;
                }
            }

            if ($redirectTo) {
                $target = $this->normalizePath($redirectTo);
                $url = $this->basePath . '/' . $target;
                header('Location: ' . $url);
                exit;
            }

            if ($callback) {
                call_user_func($callback, $params, end($chain));
                return;
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
                        return;
                    }
                    $selector = $this->componentRegistry->lazyRegister($componentClass);
                    $data = array_merge($this->currentParams, ['routeData' => $this->currentRouteData]);

                    $slotContent = $rendered !== null ? '<router-outlet name="outlet">' . $rendered . '</router-outlet>' : null;

                    if ($i === $topIndex) {
                        echo $this->renderer->renderRoot($selector, $data, $slotContent);
                        return;
                    } else {
                        $rendered = $this->renderer->renderComponent($selector, $data, $slotContent);
                    }
                }
            }
        }

        // 🔥 PRIORITÀ 4: Fallback matching singolo
        $match = $this->matchRoute($uri);

        if (!$match) {
            $this->handleNotFound();
            return;
        }

        [$route, $params] = $match;
        $this->currentRoute = $route;
        $this->currentParams = $params;
        $this->currentRouteData = $route['data'] ?? [];

        if (!empty($route['canActivate'])) {
            if (!$this->executeGuards($route['canActivate'])) {
                return;
            }
        }

        if (isset($route['redirectTo'])) {
            $target = $this->normalizePath($route['redirectTo']);
            $url = $this->basePath . '/' . $target;
            header('Location: ' . $url);
            exit;
        }

        if (isset($route['callback']) && is_callable($route['callback'])) {
            call_user_func($route['callback'], $params, $route);
            return;
        }

        if (isset($route['component'])) {
            $componentClass = $route['component'];

            if (!class_exists($componentClass)) {
                http_response_code(500);
                echo "Component '{$componentClass}' not found";
                return;
            }

            if (!$this->renderer || !$this->componentRegistry) {
                http_response_code(500);
                echo "Renderer or ComponentRegistry not configured";
                return;
            }

            $selector = $this->componentRegistry->lazyRegister($componentClass);
            $data = array_merge($this->currentParams, [
                'routeData' => $this->currentRouteData,
            ]);

            echo $this->renderer->renderRoot($selector, $data);
            return;
        }

        $this->handleNotFound();
    }

    /**
     * 🔥 NUOVO: Prova a dispatchare una route con controller
     */
    private function tryDispatchController(string $uri, string $method): bool
    {
        foreach ($this->routes as $route) {
            // Solo route con controller
            if (!isset($route['controller'])) {
                continue;
            }

            $controllerClass = $route['controller'];
            $routeBasePath = $this->normalizePath($route['path'] ?? '');

            // Verifica che l'URI inizi con il base path della route
            if ($routeBasePath !== '' && !str_starts_with($uri, $routeBasePath)) {
                continue;
            }

            // Calcola il path relativo al controller
            $relativePath = $routeBasePath !== ''
                ? substr($uri, strlen($routeBasePath))
                : $uri;
            $relativePath = ltrim($relativePath, '/');

            // Ottieni il registry dei controller (lazy init)
            if (!$this->controllerRegistry) {
                $this->controllerRegistry = new ControllerRegistry();
            }

            // Cerca un metodo nel controller che matcha
            $match = $this->controllerRegistry->matchControllerMethod(
                $controllerClass,
                $method,
                $relativePath
            );

            if ($match) {
                // Esegui guards della route se presenti
                if (!empty($route['canActivate'])) {
                    if (!$this->executeGuards($route['canActivate'])) {
                        return true; // Route gestita ma bloccata da guard
                    }
                }

                // Salva route corrente
                $this->currentRoute = $route;
                $this->currentParams = $match['params'];
                $this->currentRouteData = $route['data'] ?? [];

                // Invoca il metodo del controller
                $result = $this->controllerRegistry->invokeControllerMethod(
                    $controllerClass,
                    $match['methodName'],
                    $match['params']
                );

                // Gestisci il risultato
                $this->handleControllerResponse($result);
                return true;
            }
        }

        return false;
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
        $result = $this->findRouteAndFullPath($name);
        if (!$result) {
            return '#';
        }

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

        return '/' . ltrim($path, '/');
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
        foreach ($this->routes as $route) {
            $res = $this->matchNodeChain($route, $path, '');
            if ($res) return $res;
        }
        return null;
    }

    private function matchNodeChain(array $node, string $requestPath, string $accumulated): ?array
    {
        $nodePath = $this->normalizePath($node['path'] ?? '');
        $fullPath = trim(($accumulated !== '' ? ($accumulated . '/') : '') . $nodePath, '/');

        if (!empty($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $child) {
                $res = $this->matchNodeChain($child, $requestPath, $fullPath);
                if ($res) {
                    [$chain, $params] = $res;
                    array_unshift($chain, $node);
                    return [$chain, $params];
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