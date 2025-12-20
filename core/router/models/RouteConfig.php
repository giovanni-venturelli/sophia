<?php
declare(strict_types=1);

namespace App\Router\Models;

use InvalidArgumentException;

/**
 * DTO per rappresentare una route configurata (Angular-like)
 */
final class RouteConfig
{
    public string $path;
    public string $method;

    /** Controller / closure */
    public $callback;

    /** Component selector o FQCN */
    public ?string $component;

    /** canActivate */
    public array $middleware;

    public ?string $name;
    public ?string $redirectTo;

    /** route data */
    public array $data;

    /** lazy loading */
    public $loadChildren;

    /** children routes */
    public array $children;

    /** module imports */
    public array $imports;

    public function __construct(array $config)
    {
        $this->path   = $config['path'] ?? '';
        $this->method = strtoupper($config['method'] ?? 'ANY');

        // Separazione netta tra component e callback
        $this->component = isset($config['component'])
            ? (is_string($config['component']) ? $config['component'] : null)
            : null;

        $this->callback = $config['callback'] ?? null;

        $this->middleware = $config['canActivate'] ?? [];
        $this->name       = $config['name'] ?? null;
        $this->redirectTo = $config['redirectTo'] ?? null;
        $this->data       = $config['data'] ?? [];

        $this->loadChildren = $config['loadChildren'] ?? null;
        $this->children     = $config['children'] ?? [];
        $this->imports      = $config['imports'] ?? [];

        $this->validate();
    }

    /**
     * Valida la configurazione della route
     *
     * @throws InvalidArgumentException
     */
    private function validate(): void
    {
        if (
            $this->component === null &&
            $this->callback === null &&
            $this->redirectTo === null &&
            empty($this->children) &&
            empty($this->imports) &&
            $this->loadChildren === null
        ) {
            throw new InvalidArgumentException(
                "Route deve avere almeno uno tra: 'component', 'callback', 'redirectTo', 'children', 'imports', 'loadChildren'"
            );
        }
    }

    /**
     * Verifica se la route è un gruppo (ha children/imports)
     *
     * @return bool
     */
    public function isGroup(): bool
    {
        return !empty($this->children)
            || !empty($this->imports)
            || $this->loadChildren !== null;
    }
}