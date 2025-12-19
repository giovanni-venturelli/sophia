<?php

namespace App\Router;

use App\Component\ComponentRegistry;
use App\Component\Renderer;

class Router
{
    private static ?Router $instance = null;

    /** @var array */
    private array $routes = [];

    /** @var array|null */
    private ?array $currentRoute = null;

    /** Parametri estratti dalla route corrente (es. id, slug, ecc.) */
    private array $currentParams = [];

    /** Dati extra definiti nella route corrente (chiave 'data') */
    private array $currentRouteData = [];

    /**
     * Base path dell'applicazione, es. "/test-route".
     * Verrà tolto dall'URI in ingresso e prefissato agli URL generati.
     */
    private string $basePath = '';

    /** @var Renderer|null */
    private ?Renderer $renderer = null;

    /** @var ComponentRegistry|null */
    private ?ComponentRegistry $componentRegistry = null;

    private function __construct()
    {
    }

    public static function getInstance(): Router
    {
        if (!self::$instance) {
            self::$instance = new Router();
        }

        return self::$instance;
    }

    public function setBasePath(string $basePath): void
    {
        $basePath = trim($basePath);

        if ($basePath === '' || $basePath === '/') {
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

    /**
     * Configura le routes con sintassi Angular-like.
     *
     * Esempio:
     * [
     *   ['path' => 'home', 'component' => HomeComponent::class, 'name' => 'home'],
     * ]
     */
    public function configure(array $routes): void
    {
        $this->routes = $routes;
    }

    /**
     * Gestisce la richiesta corrente: match della route + esecuzione.
     */
    public function dispatch(): void
    {
        $uri = $this->getCurrentPath();
        $match = $this->matchRoute($uri);

        if (!$match) {
            $this->handleNotFound();
            return;
        }

        [$route, $params] = $match;

        $this->currentRoute      = $route;
        $this->currentParams     = $params;
        $this->currentRouteData  = $route['data'] ?? [];

        // Redirect
        if (isset($route['redirectTo'])) {
            $target = $this->normalizePath($route['redirectTo']);
            $url    = $this->basePath . '/' . $target;
            header('Location: ' . $url);
            exit;
        }

        // Callback (API ecc.)
        if (isset($route['callback']) && is_callable($route['callback'])) {
            \call_user_func($route['callback'], $params, $route);
            return;
        }

        // Component
        if (isset($route['component'])) {
            $componentClass = $route['component'];

            if (!class_exists($componentClass)) {
                http_response_code(500);
                echo "Component class '{$componentClass}' not found";
                return;
            }

            if (!$this->renderer || !$this->componentRegistry) {
                http_response_code(500);
                echo "Renderer or ComponentRegistry not configured in Router";
                return;
            }

            /**
             * ASSUNZIONE:
             * - per ogni route con 'component' hai registrato un selector nel ComponentRegistry.
             * - per la Home: selector 'app-home' → HomeComponent::class.
             */
            $selector = $this->resolveSelectorFromRoute($route, $componentClass);

            // Dati da passare al root component: params + data
            $data = array_merge($this->currentParams, [
                'routeData' => $this->currentRouteData,
            ]);

            echo $this->renderer->renderRoot($selector, $data);
            return;
        }

        // Nessun handler valido
        $this->handleNotFound();
    }

    /**
     * Per semplicità: mappa component class → selector.
     * In un sistema più avanzato potresti tenere questa mappa altrove.
     */
    private function resolveSelectorFromRoute(array $route, string $componentClass): string
    {
        // Se nella route hai messo un 'selector', usalo
        if (!empty($route['selector']) && is_string($route['selector'])) {
            return $route['selector'];
        }

        // Fallback semplice: nome in base al class basename
        // Es: App\Pages\Home\HomeComponent -> app-home
        $short = strtolower(preg_replace('/Component$/', '', basename(str_replace('\\', '/', $componentClass))));
        return 'app-' . $short;
    }

    /**
     * Restituisce solo il path della richiesta, senza query string,
     * senza basePath, e senza slash iniziale (es. "home", "blog/php/router").
     */
    private function getCurrentPath(): string
    {
        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        // Rimuovi il basePath dall'inizio, se presente
        if ($this->basePath && str_starts_with($path, $this->basePath)) {
            $path = substr($path, strlen($this->basePath));
            if ($path === '') {
                $path = '/';
            }
        }

        // Normalizza stile Angular: niente slash iniziale
        $path = ltrim($path, '/'); // "/" -> "", "/home" -> "home"

        return $path;
    }

    /**
     * Trova la prima route che matcha il path richiesto.
     *
     * @return array{0:array,1:array}|null
     */
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
     * Match di una singola route (con eventuali children).
     *
     * @return array{0:array,1:array}|null
     */
    private function matchSingleRoute(array $route, string $path): ?array
    {
        $routePath = $route['path'] ?? '';

        // Catch-all tipo 404: path == '' o '*'
        if ($routePath === '*' || $routePath === '') {
            return [$route, []];
        }

        // Se ha children, prima prova a matchare il padre e poi i figli
        if (!empty($route['children']) && is_array($route['children'])) {
            $parentPath = $this->normalizePath($routePath);

            if ($path === $parentPath || str_starts_with($path, $parentPath . '/')) {
                $rest = trim(substr($path, strlen($parentPath)), '/'); // "" oppure "users", ecc.

                foreach ($route['children'] as $child) {
                    $childPath     = $this->normalizePath($child['path'] ?? '');
                    $fullChildPath = $parentPath;

                    if ($childPath !== '') {
                        $fullChildPath .= '/' . $childPath;
                    }

                    $match = $this->matchPathWithParams($fullChildPath, $path);
                    if ($match) {
                        [$params] = $match;

                        $mergedRoute = array_merge($route, $child);
                        unset($mergedRoute['children']);

                        return [$mergedRoute, $params];
                    }
                }
            }
        }

        // Route "semplice"
        $routePath = $this->normalizePath($routePath);
        $match     = $this->matchPathWithParams($routePath, $path);

        if ($match) {
            [$params] = $match;
            return [$route, $params];
        }

        return null;
    }

    /**
     * Normalizza un path route (no slash iniziale/finale).
     */
    private function normalizePath(string $path): string
    {
        $path = trim($path);
        $path = trim($path, '/');

        return $path;
    }

    /**
     * Match path con parametri stile Angular.
     *
     * @return array{0:array}|null
     */
    private function matchPathWithParams(string $routePath, string $requestPath): ?array
    {
        $routeSegments   = $routePath === '' ? [] : explode('/', $routePath);
        $requestSegments = $requestPath === '' ? [] : explode('/', $requestPath);

        if (count($routeSegments) !== count($requestSegments)) {
            return null;
        }

        $params = [];

        foreach ($routeSegments as $index => $segment) {
            $value = $requestSegments[$index];

            if (str_starts_with($segment, ':')) {
                $paramName          = substr($segment, 1);
                $params[$paramName] = $value;
                continue;
            }

            if ($segment !== $value) {
                return null;
            }
        }

        return [$params];
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

    /**
     * Genera l'URL di una route a partire dal suo name e dai params.
     * Ritorna un path relativo CON slash iniziale, SENZA basePath.
     */
    public function url(string $name, array $params = []): string
    {
        $route = $this->findRouteByName($name);

        if (!$route) {
            return '';
        }

        $path = $this->normalizePath($route['path'] ?? '');

        if ($path !== '') {
            $segments = explode('/', $path);

            foreach ($segments as $i => $segment) {
                if (str_starts_with($segment, ':')) {
                    $paramName = substr($segment, 1);

                    if (!array_key_exists($paramName, $params)) {
                        throw new \InvalidArgumentException(
                            "Missing parameter '{$paramName}' for route '{$name}'"
                        );
                    }

                    $segments[$i] = $params[$paramName];
                }
            }

            $path = implode('/', $segments);
        }

        $path = '/' . ltrim($path, '/');

        return $path;
    }

    private function findRouteByName(string $name): ?array
    {
        foreach ($this->routes as $route) {
            if (($route['name'] ?? null) === $name) {
                return $route;
            }

            if (!empty($route['children']) && is_array($route['children'])) {
                foreach ($route['children'] as $child) {
                    if (($child['name'] ?? null) === $name) {
                        return array_merge($route, $child);
                    }
                }
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
