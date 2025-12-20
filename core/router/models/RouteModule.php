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

    /**
     * Ottiene il nome del modulo
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Ottiene il prefisso del modulo
     *
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Ottiene i middleware del modulo
     *
     * @return array
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Ottiene le routes del modulo
     *
     * @return array
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Aggiunge una route al modulo
     *
     * @param array $route Configurazione della route
     * @return self
     */
    public function addRoute(array $route): self
    {
        $this->routes[] = $route;
        return $this;
    }
}