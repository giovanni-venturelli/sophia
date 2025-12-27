<?php
namespace App\Form;

class FormRequest
{
    private array $post;
    private array $files;

    public function __construct(?array $post = null, ?array $files = null)
    {
        $this->post = $post ?? $_POST;
        $this->files = $files ?? $_FILES;
    }

    public function input(string $key, $default = null)
    {
        return $this->post[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->post;
    }

    public function file(string $key)
    {
        return $this->files[$key] ?? null;
    }

    public function header(string $key, $default = null)
    {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return $_SERVER[$serverKey] ?? $default;
    }

    public function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
