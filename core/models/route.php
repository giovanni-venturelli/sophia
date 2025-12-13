<?php

/**
 * Rappresentazione di una route finale (dopo elaborazione)
 */
final class Route {
    public string $method;
    public string $path;
    public $callback;
    public array $middleware;
    public ?string $name;
    public array $data;
    public string $pattern;

    public function __construct(array $config) {
        $this->method = $config['method'];
        $this->path = $config['path'];
        $this->callback = $config['callback'];
        $this->middleware = $config['middleware'] ?? [];
        $this->name = $config['name'] ?? null;
        $this->data = $config['data'] ?? [];
        $this->pattern = $this->compilePattern();
    }

    private function compilePattern(): string {
        // Sostituisce :param con regex
        $pattern = preg_replace('/:([a-zA-Z0-9_]+)/', '(?P<$1>[a-zA-Z0-9_-]+)', $this->path);
        // Sostituisce * con regex per wildcard
        $pattern = str_replace('*', '(.*)', $pattern);
        return '#^' . $pattern . '$#';
    }

    public function matches(string $path): ?array {
        if (preg_match($this->pattern, $path, $matches)) {
            // Filtra solo i named parameters
            $params = [];
            foreach ($matches as $key => $value) {
                if (!is_numeric($key)) {
                    $params[$key] = $value;
                }
            }
            return $params;
        }
        return null;
    }
}