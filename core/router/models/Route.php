<?php
namespace Sophia\Router\Models;

/**
 * Rappresentazione di una route finale (dopo elaborazione)
 */

class Route
{
    private string $method;
    private string $path;
    private ?string $componentClass = null;
    private ?\Closure $callback = null;
    private array $middleware = [];
    private ?string $name = null;
    private ?string $pathMatch = null;
    private array $data = [];
    private string $pattern;

    /**
     * Costruttore
     */
    public function __construct(array $config)
    {
        $this->method = $config['method'];
        $this->path = $config['path'];

        // 🔥 SUPPORTA componentClass per lazy loading!
        $this->componentClass = $config['component'] ?? null;

        $this->callback = $config['callback'] ?? null;
        $this->pathMatch = $config['pathMatch'] ?? 'prefix'; // Default: 'prefix'
        $this->middleware = $config['middleware'] ?? [];
        $this->name = $config['name'] ?? null;
        $this->data = $config['data'] ?? [];

        $this->pattern = $this->compilePattern();
    }

    /**
     * 🔥 PER ROUTER LAZY: restituisce la classe del componente
     */
    public function getComponentClass(): string
    {
        if (!$this->componentClass) {
            throw new \RuntimeException('No component class defined for this route');
        }
        return $this->componentClass;
    }

    /**
     * 🔥 PER BACKWARDS COMPATIBILITY: selector legacy
     */
    public function getSelector(): ?string
    {
        return $config['selector'] ?? null;
    }

    /**
     * Compila il path in una regex per il matching
     */
    private function compilePattern(): string
    {
        // Sostituisce :param con regex
        $pattern = preg_replace('/:([a-zA-Z0-9_]+)/', '(?P<$1>[a-zA-Z0-9_-]+)', $this->path);

        // Sostituisce * con regex per wildcard
        $pattern = str_replace('*', '(.*)', $pattern);

        return '#^' . $pattern . '$#';
    }

    /**
     * Verifica se la route matcha il path dato
     */
    public function matches(string $path): ?array
    {
        if (preg_match($this->pattern, $path, $matches)) {
            // Filtra solo i named parameters
            return array_filter($matches, function ($key) {
                return !is_numeric($key);
            }, ARRAY_FILTER_USE_KEY);
        }
        return null;
    }

    /**
     * Genera URL dalla route sostituendo i parametri
     */
    public function generateUrl(array $params = []): string
    {
        $url = $this->path;
        foreach ($params as $key => $value) {
            $url = str_replace(":$key", (string)$value, $url);
        }
        return $url;
    }

    /**
     * Getter per proprietà (usato dal Router)
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getCallback(): ?\Closure
    {
        return $this->callback;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getPathMatch(): string
    {
        return $this->pathMatch ?? 'prefix';
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getPattern(): string
    {
        return $this->pattern;
    }

    /**
     * Verifica se è una route con componente
     */
    public function hasComponent(): bool
    {
        return $this->componentClass !== null;
    }

    /**
     * Verifica se è una route con callback
     */
    public function hasCallback(): bool
    {
        return $this->callback !== null;
    }
}