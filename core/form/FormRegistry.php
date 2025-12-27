<?php
namespace App\Form;

use App\Injector\Injectable;
use App\Injector\Injector;

#[Injectable(providedIn: 'root')]
class FormRegistry
{
    /**
     * token => [class, method, route, expires, singleUse]
     */
    private array $tokens = [];

    /**
     * Index for resolving action(name) → token: [class => [handlerName => token]]
     */
    private array $index = [];

    public function __construct(private SessionService $session)
    {
        $this->tokens = $_SESSION['__form_tokens'] ?? [];
        $this->index = $_SESSION['__form_tokens_index'] ?? [];
    }

    public static function getInstance(): self
    {
        return Injector::inject(self::class);
    }

    private function persist(): void
    {
        $_SESSION['__form_tokens'] = $this->tokens;
        $_SESSION['__form_tokens_index'] = $this->index;
    }

    public function issueToken(string $class, string $method, string $routePath, int $ttlSeconds = 1200, bool $singleUse = true): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        $this->tokens[$token] = [
            'class' => $class,
            'method' => $method,
            'route' => $routePath,
            'expires' => time() + $ttlSeconds,
            'singleUse' => $singleUse,
        ];
        $this->persist();
        return $token;
    }

    public function resolve(string $token): ?array
    {
        $entry = $this->tokens[$token] ?? null;
        if (!$entry) return null;
        if (($entry['expires'] ?? 0) < time()) {
            unset($this->tokens[$token]);
            $this->persist();
            return null;
        }
        return $entry;
    }

    public function invalidate(string $token): void
    {
        unset($this->tokens[$token]);
        // also cleanup index entries that pointed to this token
        foreach ($this->index as $cls => $handlers) {
            foreach ($handlers as $name => $tok) {
                if ($tok === $token) {
                    unset($this->index[$cls][$name]);
                }
            }
        }
        $this->persist();
    }

    public function findOrCreateToken(string $class, string $method, string $routePath): string
    {
        // Try to find an existing valid token for this combo to avoid duplicates on the same render
        foreach ($this->tokens as $tok => $meta) {
            if ($meta['class'] === $class && $meta['method'] === $method && $meta['route'] === $routePath && ($meta['expires'] ?? 0) >= time()) {
                return $tok;
            }
        }
        return $this->issueToken($class, $method, $routePath);
    }

    public function registerHandler(string $class, string $handlerName, string $method, string $routePath): string
    {
        $token = $this->findOrCreateToken($class, $method, $routePath);
        $this->index[$class][$handlerName] = $token;
        $this->persist();
        return $token;
    }

    public function getTokenFor(string $class, string $handlerName): ?string
    {
        return $this->index[$class][$handlerName] ?? null;
    }
}
