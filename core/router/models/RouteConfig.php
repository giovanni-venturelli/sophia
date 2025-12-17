<?php
namespace App\Router\Models;
use InvalidArgumentException;
/**
 * DTO per rappresentare una route configurata
 */
final class RouteConfig
{
    public string $path;
    public string $method;
    public $callback;
    public array $middleware;
    public ?string $name;
    public ?string $redirectTo;
    public array $data;
    public ?string $loadChildren;
    public array $children;
    public array $imports;

    public function __construct(array $config)
    {
        $this->path = $config['path'] ?? '';
        $this->method = strtoupper($config['method'] ?? 'GET');
        $this->callback = $config['component'] ?? $config['callback'] ?? null;
        $this->middleware = $config['canActivate'] ?? [];
        $this->name = $config['name'] ?? null;
        $this->redirectTo = $config['redirectTo'] ?? null;
        $this->data = $config['data'] ?? [];
        $this->loadChildren = $config['loadChildren'] ?? null;
        $this->children = $config['children'] ?? [];
        $this->imports = $config['imports'] ?? [];

        $this->validate();
    }

    private function validate(): void
    {
        if ($this->callback === null && $this->redirectTo === null && empty($this->children) && $this->loadChildren === null) {
            throw new InvalidArgumentException(
                "Route deve avere 'component', 'callback', 'redirectTo', 'children' o 'loadChildren'"
            );
        }
    }

    public function isGroup(): bool
    {
        return !empty($this->children) || !empty($this->imports) || $this->loadChildren !== null;
    }
}