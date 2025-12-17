<?php
namespace App\Router\Models;
/**
 * Modulo di route (come in Angular)
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

    public function getName(): string
    {
        return $this->name;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function addRoute(array $route): self
    {
        $this->routes[] = $route;
        return $this;
    }
}

