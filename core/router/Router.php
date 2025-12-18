<?php
/**
 * Router PHP Avanzato con sintassi Angular-like + Component SSR
 */

declare(strict_types=1);

namespace App\Router;

use App\Component\Component;
use App\Component\ComponentRegistry;
use App\Component\Renderer;
use App\Router\Models\MiddlewareInterface;
use App\Router\Models\Route;
use App\Router\Models\RouteConfig;
use App\Router\Models\RouteLoaderInterface;
use App\Router\Models\RouteModule;

use ReflectionClass;
use Exception;

final class Router
{
    private static ?self $instance = null;

    private array $routes = [];
    private array $namedRoutes = [];
    private array $middlewareInstances = [];
    private array $registeredModules = [];
    private array $globalMiddleware = [];

    private ?Route $currentRoute = null;
    private array $currentParams = [];

    private string $basePath = '';

    private ?ComponentRegistry $componentRegistry = null;
    private ?Renderer $renderer = null;

    private function __construct() {}
    private function __clone() {}

    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /* ==========================================================
     * MODULES & MIDDLEWARE
     * ========================================================== */

    public function registerModule(RouteModule $module): self
    {
        $this->registeredModules[$module->getName()] = $module;
        return $this;
    }

    public function useGlobalMiddleware(string $middlewareClass): self
    {
        if (!in_array($middlewareClass, $this->globalMiddleware, true)) {
            $this->globalMiddleware[] = $middlewareClass;
        }
        return $this;
    }

    public function configure(array $routesConfig): void
    {
        foreach ($routesConfig as $config) {
            $this->processRouteConfig(new RouteConfig($config));
        }
    }

    /* ==========================================================
     * ROUTE PROCESSING (RECURSIVE)
     * ========================================================== */

    /**
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

        // Processa imports se presenti
        foreach ($config->imports as $moduleName) {
            $this->importModule($moduleName, $currentPath, $currentMiddleware, $currentData);
        }

        // Processa loadChildren se presente
        if ($config->loadChildren !== null) {
            $this->loadChildren($config->loadChildren, $currentPath, $currentMiddleware, $currentData);
        }

        // Registra questa route come route finale se ha component, callback o redirectTo
        // Anche se ha children, può essere una route valida (es. /user può avere un componente)
        if ($config->component !== null || $config->callback !== null || $config->redirectTo !== null) {
            $this->registerFinalRoute($config, $currentPath ?: '/', $currentMiddleware, $currentData);
        }

        // Processa children ricorsivamente
        foreach ($config->children as $childConfig) {
            $this->processRouteConfig(
                new RouteConfig($childConfig),
                $currentPath,
                $currentMiddleware,
                $currentData
            );
        }
    }

    /**
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

        foreach ($module->getRoutes() as $routeConfig) {
            $this->processRouteConfig(
                new RouteConfig($routeConfig),
                $parentPath . $module->getPrefix(),
                array_merge($parentMiddleware, $module->getMiddleware()),
                $parentData
            );
        }
    }

    /**
     * @throws Exception
     */
    private function loadChildren(
        $loader,
        string $parentPath,
        array $parentMiddleware,
        array $parentData
    ): void {
        if ($loader instanceof RouteLoaderInterface) {
            $routes = $loader->load();
        } elseif (is_callable($loader)) {
            $routes = $loader();
        } elseif (is_string($loader) && file_exists($loader)) {
            $routes = require $loader;
        } else {
            throw new Exception("loadChildren non valido");
        }

        foreach ($routes as $routeConfig) {
            $this->processRouteConfig(
                new RouteConfig($routeConfig),
                $parentPath,
                $parentMiddleware,
                $parentData
            );
        }
    }

    /* ==========================================================
     * FINAL ROUTE REGISTRATION
     * ========================================================== */

    private function registerFinalRoute(
        RouteConfig $config,
        string $fullPath,
        array $middleware,
        array $data
    ): void {
        if ($config->component !== null) {
            $callback = $this->createComponentCallback($config->component);
        } elseif ($config->redirectTo !== null) {
            $callback = function () use ($config) {
                header("Location: {$config->redirectTo}");
                exit;
            };
        } else {
            $callback = $config->callback;
        }

        $route = new Route([
            'method'     => $config->method,
            'path'       => $fullPath,
            'callback'   => $callback,
            'middleware' => $middleware,
            'name'       => $config->name,
            'data'       => array_merge($data, $config->data),
        ]);

        $this->routes[] = $route;

        if ($config->name !== null) {
            $this->namedRoutes[$config->name] = $route;
        }
    }

    /* ==========================================================
     * COMPONENT CALLBACK
     * ========================================================== */

    private function createComponentCallback(string $componentClass): callable
    {
        return function () use ($componentClass) {
            if ($this->componentRegistry === null) {
                $this->componentRegistry = new ComponentRegistry();
                $this->renderer = new Renderer($this->componentRegistry);
            }

            // Ottieni il selettore dalla classe del componente
            $ref = new ReflectionClass($componentClass);
            $attr = $ref->getAttributes(Component::class)[0] ?? null;

            if (!$attr) {
                throw new \RuntimeException("Class $componentClass is not a Component");
            }

            $componentAttr = $attr->newInstance();
            $selector = $componentAttr->selector;

            // Assicurati che il componente sia registrato
            if (!$this->componentRegistry->get($selector)) {
                $this->componentRegistry->register($componentClass);
            }

            echo $this->renderer->renderRoot($selector);
        };
    }

    /* ==========================================================
     * DISPATCH
     * ========================================================== */

    public function dispatch(?string $uri = null, ?string $method = null): void
    {
        $uri = $uri ?? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
        $method = $method ?? $_SERVER['REQUEST_METHOD'] ?? 'GET';

        foreach ($this->routes as $route) {
            if ($route->method !== 'ANY' && $route->method !== $method) {
                continue;
            }

            $params = $route->matches($uri);
            if ($params === null) {
                continue;
            }

            $this->currentRoute = $route;
            $this->currentParams = $params;

            if (!$this->runMiddleware($route)) {
                return;
            }

            $this->executeCallback($route, $params);
            return;
        }

        $this->handleNotFound();
    }

    private function runMiddleware(Route $route): bool
    {
        foreach (array_merge($this->globalMiddleware, $route->middleware) as $mw) {
            $instance = $this->getMiddlewareInstance($mw);
            if ($instance->handle() === false) {
                return false;
            }
        }
        return true;
    }

    private function getMiddlewareInstance(string $class): MiddlewareInterface
    {
        return $this->middlewareInstances[$class]
            ??= new $class();
    }

    private function executeCallback(Route $route, array $params): void
    {
        call_user_func_array($route->callback, array_values($params));
    }

    private function handleNotFound(): void
    {
        // Cerca una route 404 personalizzata
        foreach ($this->routes as $route) {
            if ($route->path === '*') {
                $this->executeCallback($route, []);
                return;
            }
        }

        // Fallback a 404 di default
        http_response_code(404);
        echo "404 - Route not found";
    }

    /* ==========================================================
     * UTILITY METHODS
     * ========================================================== */

    /**
     * @throws Exception
     */
    public function url(string $name, array $params = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new Exception("Route named '$name' not found");
        }

        return $this->namedRoutes[$name]->generateUrl($params);
    }

    public function getCurrentRoute(): ?Route
    {
        return $this->currentRoute;
    }

    public function getCurrentParams(): array
    {
        return $this->currentParams;
    }

    public function setBasePath(string $basePath): self
    {
        $this->basePath = rtrim($basePath, '/');
        return $this;
    }
}