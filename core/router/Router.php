<?php

namespace App\Router;

use App\Component\ComponentRegistry;
use App\Component\Renderer;
use App\Router\Models\MiddlewareInterface;
use function call_user_func;

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

    /**
     * Singleton
     */
    public static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 🔥 DISPATCH con supporto Guards/Middleware
     */
    public function dispatch(): void
    {
        $uri = $this->getCurrentPath();

        // 1) Prova nested route chain (layout + children)
        $chainMatch = $this->matchRouteChain($uri);
        if ($chainMatch) {
            [$chain, $params] = $chainMatch;

            // Unisci i dati e i guards lungo la catena
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

            // Guards
            if (!empty($mergedGuards)) {
                if (!$this->executeGuards($mergedGuards)) {
                    return;
                }
            }

            // Redirect
            if ($redirectTo) {
                $target = $this->normalizePath($redirectTo);
                $url = $this->basePath . '/' . $target;
                header('Location: ' . $url);
                exit;
            }

            // Callback
            if ($callback) {
                call_user_func($callback, $params, end($chain));
                return;
            }

            // Rendering bottom-up: leaf -> root, usando <router-outlet>
            if ($this->renderer && $this->componentRegistry) {
                $rendered = null;
                // Trova l'indice del componente più alto (top) nella catena
                $topIndex = null;
                for ($i = 0; $i < count($chain); $i++) {
                    if (isset($chain[$i]['component'])) { $topIndex = $i; break; }
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
                    $data = array_merge($this->currentParams, [ 'routeData' => $this->currentRouteData ]);

                    $slotContent = $rendered !== null ? '<router-outlet name="outlet">' . $rendered . '</router-outlet>' : null;

                    if ($i === $topIndex) {
                        echo $this->renderer->renderRoot($selector, $data, $slotContent);
                        return;
                    } else {
                        $rendered = $this->renderer->renderComponent($selector, $data, $slotContent);
                    }
                }
                // Se non è stato trovato alcun componente, 404
            }
            // Se non renderizzato per qualche motivo, cade al fallback
        }

        // 2) Fallback: vecchio matching singolo
        $match = $this->matchRoute($uri);

        if (!$match) {
            $this->handleNotFound();
            return;
        }

        [$route, $params] = $match;
        $this->currentRoute = $route;
        $this->currentParams = $params;
        $this->currentRouteData = $route['data'] ?? [];

        // 🔥 GUARDS/MIDDLEWARE (canActivate)
        if (!empty($route['canActivate'])) {
            if (!$this->executeGuards($route['canActivate'])) {
                // Guard ha bloccato l'accesso
//                $this->handleUnauthorized($route);
                return;
            }
        }

        // Redirect
        if (isset($route['redirectTo'])) {
            $target = $this->normalizePath($route['redirectTo']);
            $url = $this->basePath . '/' . $target;
            header('Location: ' . $url);
            exit;
        }

        // Callback (API)
        if (isset($route['callback']) && is_callable($route['callback'])) {
            call_user_func($route['callback'], $params, $route);
            return;
        }

        // 🔥 COMPONENT RENDERING
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

            // 🔥 LAZY REGISTRATION
            $selector = $this->componentRegistry->lazyRegister($componentClass);

            // Dati route
            $data = array_merge($this->currentParams, [
                'routeData' => $this->currentRouteData,
            ]);

            echo $this->renderer->renderRoot($selector, $data);
            return;
        }

        $this->handleNotFound();
    }

    /**
     * 🔥 Esegue i guards della route
     *
     * @param array $guards Array di classi guard (devono implementare MiddlewareInterface)
     * @return bool True se tutti i guards passano, false altrimenti
     */
    private function executeGuards(array $guards): bool
    {
        foreach ($guards as $guardClass) {
            // Se è una stringa, istanzia la classe
            if (is_string($guardClass)) {
                if (!class_exists($guardClass)) {
                    throw new \RuntimeException("Guard class '{$guardClass}' not found");
                }

                $guard = new $guardClass();

                // Verifica che implementi l'interfaccia
                if (!$guard instanceof MiddlewareInterface) {
                    throw new \RuntimeException(
                        "Guard '{$guardClass}' must implement " . MiddlewareInterface::class
                    );
                }
            }
            // Se è già un'istanza, usala direttamente
            elseif ($guardClass instanceof MiddlewareInterface) {
                $guard = $guardClass;
            }
            else {
                throw new \RuntimeException("Invalid guard type");
            }

            // Esegui il guard
            if (!$guard->handle()) {
                // Guard ha fallito - blocca l'accesso
                return false;
            }
        }

        // Tutti i guards sono passati
        return true;
    }

    /**
     * Configura routes (routes.php)
     */
    public function configure(array $routes): void
    {
        $this->routes = $routes;
    }

    /**
     * Route methods (per routes.php)
     */
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

    // 🔗 HELPERS PER TEMPLATES
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
        $route = $this->findRouteByName($name);
        if (!$route) {
            return '#';
        }

        $path = $this->normalizePath($route['path'] ?? '');
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

    // 🔍 MATCHING ENGINE
    private function getCurrentPath(): string
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

    /**
     * 🔥 NUOVO: restituisce la catena completa (root→leaf) di una rotta annidata che matcha il path
     * Ritorna [array $chain, array $params] oppure null se nessuna match
     */
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

        // Prima cerca nei figli per ottenere il match più profondo
        if (!empty($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $child) {
                $res = $this->matchNodeChain($child, $requestPath, $fullPath);
                if ($res) {
                    [$chain, $params] = $res;
                    array_unshift($chain, $node);
                    return [$chain, $params];
                }
            }
        }

        // Altrimenti prova a matchare questo nodo come leaf
        $match = $this->matchPathWithParams($fullPath, $requestPath);
        if ($match) {
            [$params] = $match;
            return [[ $node ], $params];
        }

        return null;
    }

    private function matchSingleRoute(array $route, string $path): ?array
    {
        $routePath = $route['path'] ?? '';

        if ($routePath === '*' || $routePath === '') {
            return [$route, []];
        }

        if (!empty($route['children']) && is_array($route['children'])) {
            $parentPath = $this->normalizePath($routePath);
            if ($path === $parentPath || str_starts_with($path, $parentPath . '/')) {
                $rest = trim(substr($path, strlen($parentPath)), '/');
                foreach ($route['children'] as $child) {
                    $childPath = $this->normalizePath($child['path'] ?? '');
                    $fullChildPath = $parentPath;
                    if ($childPath !== '') {
                        $fullChildPath .= '/' . $childPath;
                    }
                    $match = $this->matchPathWithParams($fullChildPath, $path);
                    if ($match) {
                        [$params] = $match;

                        // 🔥 MERGE canActivate: parent + child
                        $parentGuards = $route['canActivate'] ?? [];
                        $childGuards = $child['canActivate'] ?? [];

                        $mergedRoute = array_merge($route, $child);
                        $mergedRoute['canActivate'] = array_merge($parentGuards, $childGuards);

                        unset($mergedRoute['children']);
                        return [$mergedRoute, $params];
                    }
                }
            }
        }

        $routePath = $this->normalizePath($routePath);
        $match = $this->matchPathWithParams($routePath, $path);
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

    private function matchPathWithParams(string $routePath, string $requestPath): ?array
    {
        $routeSegments = $routePath === '' ? [] : explode('/', $routePath);
        $requestSegments = $requestPath === '' ? [] : explode('/', $requestPath);

        if (count($routeSegments) !== count($requestSegments)) {
            return null;
        }

        $params = [];
        foreach ($routeSegments as $index => $segment) {
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
        return [$params];
    }

    private function findRouteByName(string $name): ?array
    {
        foreach ($this->routes as $route) {
            if (($route['name'] ?? null) === $name) {
                return $route;
            }
        }
        return null;
    }

    private function handleNotFound(): void
    {
        http_response_code(404);
        echo '404 - Page not found';
    }
}