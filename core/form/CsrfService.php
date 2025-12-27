<?php
namespace Sophia\Form;

use Sophia\Injector\Injectable;
use Sophia\Injector\Injector;

#[Injectable(providedIn: 'root')]
class CsrfService
{
    private string $sessionKey = '__csrf_token';

    public function __construct(private SessionService $session)
    {
        // SessionService ensures session is started
    }

    // Backward-compatible accessor for static-style usage
    public static function getInstance(): self
    {
        return Injector::inject(self::class);
    }

    public function getToken(): string
    {
        if (empty($_SESSION[$this->sessionKey])) {
            $_SESSION[$this->sessionKey] = bin2hex(random_bytes(16));
        }
        return $_SESSION[$this->sessionKey];
    }

    public function validate(?string $token): bool
    {
        $expected = $_SESSION[$this->sessionKey] ?? null;
        if (!$expected || !$token) return false;
        return hash_equals($expected, $token);
    }
}
