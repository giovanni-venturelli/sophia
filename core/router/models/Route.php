<?php
namespace App\Router\Models;

/**
 * Rappresentazione di una route finale (dopo elaborazione)
 */
final class Route
{
    public string $method;
    public string $path;
    public $callback;
    public array $middleware;
    public ?string $name;
    public array $data;
    public string $pattern;

    public function __construct(array $config)
    {
        $this->method = $config['method'];
        $this->path = $config['path'];
        $this->callback = $config['callback'];
        $this->middleware = $config['middleware'] ?? [];
        $this->name = $config['name'] ?? null;
        $this->data = $config['data'] ?? [];
        $this->pattern = $this->compilePattern();
    }

    /**
     * Compila il path in una regex per il matching
     *
     * @return string Pattern regex
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
     *
     * @param string $path Path da verificare
     * @return array|null Array di parametri se match, null altrimenti
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
     *
     * @param array $params Parametri da sostituire
     * @return string URL generato
     */
    public function generateUrl(array $params = []): string
    {
        $url = $this->path;

        foreach ($params as $key => $value) {
            $url = str_replace(":$key", (string)$value, $url);
        }

        return $url;
    }
}