<?php
namespace Sophia\Router\Models;

/**
 * Route module (similar to Angular)
 */
final class RouteModule
{
    private string $name;
    private string $prefix;
    private array $middleware;
    private array $routes;

    public function __construct(string $name, array $config = [])
    {
        $this->name = $name;
        $this->prefix = $config['prefix'] ?? '';
        $this->middleware = $config['canActivate'] ?? [];
        $this->routes = $config['routes'] ?? [];
    }

    /**
     * Gets the module name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Gets the module prefix
     *
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Gets the module middleware
     *
     * @return array
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Gets the module routes
     *
     * @return array
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Adds a route to the module
     *
     * @param array $route Route configuration
     * @return self
     */
    public function addRoute(array $route): self
    {
        $this->routes[] = $route;
        return $this;
    }
}