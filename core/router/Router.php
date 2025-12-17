<?php
/**
 * Router PHP Avanzato con sintassi Angular-like
 *
 * Caratteristiche:
 * - Singleton pattern
 * - Named routes con generazione URL
 * - N-livelli di nesting infinito
 * - Middleware/Guards ereditari
 * - Lazy loading con loadChildren
 * - Moduli importabili
 * - Data ereditario
 * - Supporto route con nome
 */

declare(strict_types=1);

namespace App\Router;

use App\Router\Models\MiddlewareInterface;
use App\Router\Models\Route;
use App\Router\Models\RouteConfig;
use App\Router\Models\RouteLoaderInterface;
use App\Router\Models\RouteModule;
use Exception;

// ============================================================
// INTERFACCE E CLASSI BASE
// ============================================================



// ============================================================
// ROUTER PRINCIPALE
// ============================================================

final class Router {
    // Singleton instance
    private static ?self $instance = null;

    // Registry
    private array $routes = [];
    private array $namedRoutes = [];
    private array $middlewareInstances = [];
    private array $registeredModules = [];
    private array $globalMiddleware = [];

    // Stato corrente
    private ?Route $currentRoute = null;
    private array $currentParams = [];

    // Costruttore privato
    private function __construct() {}

    // Prevenire clonazione
    private function __clone() {}

    // Base path per fornire il path completo delle url
    private string $basePath = '';
    // Prevenire unserialize

    /**
     * @throws Exception
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }

    /**
     * Ottiene l'istanza singleton del router
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ============================================================
    // REGISTRAZIONE MODULI E MIDDLEWARE
    // ============================================================

    /**
     * Registra un modulo di route
     */
    public function registerModule(RouteModule $module): self {
        $this->registeredModules[$module->getName()] = $module;
        return $this;
    }

    /**
     * Registra un middleware globale
     */
    public function useGlobalMiddleware(string $middlewareClass): self {
        if (!in_array($middlewareClass, $this->globalMiddleware, true)) {
            $this->globalMiddleware[] = $middlewareClass;
        }
        return $this;
    }

    /**
     * Configura le route principali
     * @throws Exception
     */
    public function configure(array $routesConfig): void {
        foreach ($routesConfig as $config) {
            $this->processRouteConfig(new RouteConfig($config));
        }
    }

    // ============================================================
    // ELABORAZIONE ROUTE (RICORSIVA)
    // ============================================================

    /**
     * Processa una configurazione route (gestisce nesting infinito)
     * @throws Exception
     */
    private function processRouteConfig(
        RouteConfig $config,
        string $parentPath = '',
        array $parentMiddleware = [],
        array $parentData = []
    ): void {
        $currentPath = rtrim($parentPath . $config->path, '/');
        $currentMiddleware = array_merge($parentMiddleware, $config->middleware);
        $currentData = array_merge($parentData, $config->data);

        // 1. Gestione imports (moduli registrati)
        foreach ($config->imports as $moduleName) {
            $this->importModule($moduleName, $currentPath, $currentMiddleware, $currentData);
        }

        // 2. Gestione loadChildren (lazy loading)
        if ($config->loadChildren !== null) {
            $this->loadChildren($config->loadChildren, $currentPath, $currentMiddleware, $currentData);
        }

        // 3. Gestione children (nesting ricorsivo)
        foreach ($config->children as $childConfig) {
            $childRouteConfig = new RouteConfig($childConfig);
            $this->processRouteConfig($childRouteConfig, $currentPath, $currentMiddleware, $currentData);
        }

        // 4. Se non è solo un gruppo, registra come route finale
        if (!$config->isGroup()) {
            $this->registerFinalRoute($config, $currentPath, $currentMiddleware, $currentData);
        }
    }

    /**
     * Importa un modulo registrato
     * @throws Exception
     */
    private function importModule(
        string $moduleName,
        string $parentPath,
        array $parentMiddleware,
        array $parentData
    ): void {
        if (!isset($this->registeredModules[$moduleName])) {
            throw new Exception("Modulo '$moduleName' non registrato");
        }

        $module = $this->registeredModules[$moduleName];
        $modulePath = $parentPath . $module->getPrefix();
        $moduleMiddleware = array_merge($parentMiddleware, $module->getMiddleware());

        foreach ($module->getRoutes() as $routeConfig) {
            $config = new RouteConfig($routeConfig);
            $this->processRouteConfig($config, $modulePath, $moduleMiddleware, $parentData);
        }
    }

    /**
     * Carica route lazy (da callback, file o modulo)
     * @throws Exception
     */
    private function loadChildren(
        $loader,
        string $parentPath,
        array $parentMiddleware,
        array $parentData
    ): void {
        $routes = [];

        if (is_callable($loader)) {
            // Callback che restituisce array di route
            $routes = $loader();
        } elseif (is_string($loader) && file_exists($loader)) {
            // File PHP che restituisce array
            $routes = require $loader;
        } elseif (is_string($loader) && isset($this->registeredModules[$loader])) {
            // Nome di modulo registrato
            $module = $this->registeredModules[$loader];
            $routes = $module->getRoutes();
            $parentPath .= $module->getPrefix();
            $parentMiddleware = array_merge($parentMiddleware, $module->getMiddleware());
        } elseif ($loader instanceof RouteLoaderInterface) {
            // Loader che implementa l'interfaccia
            $routes = $loader->load();
        } else {
            throw new Exception("loadChildren non valido: deve essere callable, file path, nome modulo o RouteLoaderInterface");
        }

        if (!is_array($routes)) {
            throw new Exception("loadChildren deve restituire un array di route");
        }

        foreach ($routes as $routeConfig) {
            $config = new RouteConfig($routeConfig);
            $this->processRouteConfig($config, $parentPath, $parentMiddleware, $parentData);
        }
    }

    /**
     * Registra una route finale (senza children)
     * @throws Exception
     */
    private function registerFinalRoute(
        RouteConfig $config,
        string $fullPath,
        array $middleware,
        array $data
    ): void {
        // Gestione redirect
        if ($config->redirectTo !== null) {
            $callback = function() use ($config) {
                header("Location: {$config->redirectTo}");
                exit;
            };
        } else {
            $callback = $config->callback;
        }

        $route = new Route([
            'method' => $config->method,
            'path' => $fullPath ?: '/',
            'callback' => $callback,
            'middleware' => $middleware,
            'name' => $config->name,
            'data' => array_merge($data, $config->data)
        ]);

        $this->routes[] = $route;

        if ($config->name !== null) {
            if (isset($this->namedRoutes[$config->name])) {
                throw new Exception("Route name '{$config->name}' già registrato");
            }
            $this->namedRoutes[$config->name] = $route;
        }
    }

    // ============================================================
    // GENERAZIONE URL (NAMED ROUTES)
    // ============================================================


    /**
     * Genera URL per una route con nome
     * @throws Exception
     */
    public function url(string $name, array $params = [], array $query = []): string {
        if (!isset($this->namedRoutes[$name])) {
            throw new Exception("Route '$name' non trovata");
        }

        $route = $this->namedRoutes[$name];
        $url = $this->replaceRouteParameters($route->path, $params);

        // ============================================
        // AGGIUNGI IL BASE PATH SE CONFIGURATO
        // ============================================
        if ($this->basePath && $this->basePath !== '/') {
            $url = rtrim($this->basePath, '/') . $url;
        }
        // ============================================

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }

    /**
     * Genera URL assoluto (con dominio)
     * @throws Exception
     */
    public function fullUrl(string $name, array $params = [], array $query = []): string {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        // Usa url() che ora include il base path
        $path = $this->url($name, $params, $query);

        return $protocol . '://' . $host . $path;
    }

    /**
     * Sostituisce i parametri nel path della route
     * @throws Exception
     */
    private function replaceRouteParameters(string $path, array $params): string {
        foreach ($params as $key => $value) {
            $path = str_replace(':' . $key, (string) $value, $path);
        }

        // Verifica che tutti i parametri richiesti siano sostituiti
        if (preg_match('/:[a-zA-Z_]+/', $path)) {
            throw new Exception("Parametri mancanti per la route");
        }

        return $path;
    }

    /**
     * Verifica se una route con nome esiste
     */
    public function has(string $name): bool {
        return isset($this->namedRoutes[$name]);
    }

    // ============================================================
    // DISPATCH E GESTIONE RICHIESTE
    // ============================================================

    /**
     * Esegue il routing della richiesta corrente
     * @throws Exception
     */
    public function dispatch(?string $requestUri = null, ?string $requestMethod = null): void {
        $requestUri = $requestUri ?? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
        $requestMethod = $requestMethod ?? $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Rimuovi il base path dall'URI della richiesta
        if ($this->basePath && str_starts_with($requestUri, $this->basePath)) {
            $requestUri = substr($requestUri, strlen($this->basePath));
        }

        if ($requestUri === '') {
            $requestUri = '/';
        }

        foreach ($this->routes as $route) {
            // Controlla metodo HTTP
            if ($route->method !== 'ANY' && $route->method !== $requestMethod) {
                continue;
            }

            // Controlla match del path
            $params = $route->matches($requestUri);
            if ($params === null) {
                continue;
            }

            // Route trovata - salva stato corrente
            $this->currentRoute = $route;
            $this->currentParams = $params;

            // Esegui middleware (globali + specifici della route)
            if (!$this->runMiddleware($route)) {
                return;
            }

            // Esegui il callback
            $this->executeCallback($route, $params);
            return;
        }

        // Nessuna route trovata
        $this->handleNotFound();
    }

    /**
     * Esegui i middleware in sequenza
     * @throws Exception
     */
    private function runMiddleware(Route $route): bool {
        $allMiddleware = array_merge($this->globalMiddleware, $route->middleware);

        foreach ($allMiddleware as $middlewareClass) {
            $middleware = $this->getMiddlewareInstance($middlewareClass);

            try {
                $result = $middleware->handle();
                if ($result === false) {
                    return false;
                }
            } catch (Exception $e) {
                error_log("Middleware error: " . $e->getMessage());
                return false;
            }
        }

        return true;
    }

    /**
     * Ottiene o crea un'istanza di middleware
     * @throws Exception
     */
    private function getMiddlewareInstance(string $middlewareClass): MiddlewareInterface {
        if (!isset($this->middlewareInstances[$middlewareClass])) {
            if (!class_exists($middlewareClass)) {
                throw new Exception("Middleware class '$middlewareClass' non trovata");
            }

            $instance = new $middlewareClass();
            if (!$instance instanceof MiddlewareInterface) {
                throw new Exception("Middleware '$middlewareClass' deve implementare MiddlewareInterface");
            }

            $this->middlewareInstances[$middlewareClass] = $instance;
        }

        return $this->middlewareInstances[$middlewareClass];
    }


    /**
     * Esegue il callback della route
     * @throws Exception
     */
    private function executeCallback(Route $route, array $params): void
    {
        $callback = $route->callback;

        if (is_callable($callback)) {
            call_user_func_array($callback, array_merge(array_values($params), [$route->data]));
        } elseif (is_string($callback) && strpos($callback, '@') !== false) {
            // Controller@method syntax (opzionale, per retrocompatibilità)
            [$controllerClass, $method] = explode('@', $callback, 2);

            if (!class_exists($controllerClass)) {
                throw new Exception("Controller '$controllerClass' non trovato");
            }

            $controller = new $controllerClass();
            if (!method_exists($controller, $method)) {
                throw new Exception("Method '$method' non trovato in controller '$controllerClass'");
            }

            call_user_func_array([$controller, $method], array_merge(array_values($params), [$route->data]));
        }  else {
            throw new Exception("Callback non valido per la route");
        }
    }

    /**
     * Gestisce 404 Not Found
     * @throws Exception
     */
    private function handleNotFound(): void {
        http_response_code(404);

        // Cerca una route con nome '404' o wildcard
        foreach ($this->routes as $route) {
            if ($route->name === '404' || str_contains($route->path, '*')) {
                $this->executeCallback($route, []);
                return;
            }
        }

        // Default 404 response
        if (php_sapi_name() === 'cli') {
            echo "404 - Route not found\n";
        } else {
            header('Content-Type: text/html; charset=utf-8');
            echo <<<HTML
            <!DOCTYPE html>
            <html lang="it">
            <head>
                <title>404 - Pagina non trovata</title>
                <style>
                    body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                    h1 { color: #333; }
                    p { color: #666; }
                </style>
            </head>
            <body>
                <h1>404 - Pagina non trovata</h1>
                <p>La pagina che stai cercando non esiste.</p>
            </body>
            </html>
            HTML;
        }
    }

    // ============================================================
    // UTILITIES E GETTERS
    // ============================================================
    public function setBasePath(string $basePath): self {
        $this->basePath = rtrim($basePath, '/');
        return $this;
    }
    /**
     * Ottiene la route corrente
     */
    public function getCurrentRoute(): ?Route {
        return $this->currentRoute;
    }

    /**
     * Ottiene il nome della route corrente
     */
    public function getCurrentRouteName(): ?string {
        return $this->currentRoute->name ?? null;
    }

    /**
     * Ottiene i parametri della route corrente
     */
    public function getCurrentParams(): array {
        return $this->currentParams;
    }

    /**
     * Ottiene tutte le route registrate
     */
    public function getRoutes(): array {
        return $this->routes;
    }

    /**
     * Ottiene tutte le named routes
     */
    public function getNamedRoutes(): array {
        return $this->namedRoutes;
    }

    /**
     * Reimposta il router (per testing)
     */
    public function reset(): void {
        $this->routes = [];
        $this->namedRoutes = [];
        $this->middlewareInstances = [];
        $this->globalMiddleware = [];
        $this->currentRoute = null;
        $this->currentParams = [];
    }

    /**
     * Debug: mostra tutte le route registrate
     */
    public function dumpRoutes(): void {
        echo "<pre>=== REGISTERED ROUTES ===\n";
        foreach ($this->routes as $route) {
            echo sprintf(
                "%-8s %-40s %-30s\n",
                $route->method,
                $route->path,
                $route->name ?? '(no name)'
            );
        }
        echo "==========================\n</pre>";
    }
}
