<?php
namespace Sophia\Form;

use Sophia\Injector\Injectable;
use Sophia\Injector\Injector;

#[Injectable(providedIn: 'root')]
class FlashService
{
    private const FLASH_KEY = '__flash';

    public function __construct(private SessionService $session)
    {
        if (!isset($_SESSION[self::FLASH_KEY])) {
            $_SESSION[self::FLASH_KEY] = [];
        }
    }

    // Instance API (internal)
    public function setValue(string $key, $value): void
    {
        $_SESSION[self::FLASH_KEY][$key] = $value;
    }

    public function getValue(string $key, $default = null)
    {
        return $_SESSION[self::FLASH_KEY][$key] ?? $default;
    }

    public function pullValue(string $key, $default = null)
    {
        $value = $_SESSION[self::FLASH_KEY][$key] ?? $default;
        unset($_SESSION[self::FLASH_KEY][$key]);
        return $value;
    }

    public function hasKey(string $key): bool
    {
        return array_key_exists($key, $_SESSION[self::FLASH_KEY]);
    }

    // Static shims for backward compatibility
    public static function set(string $key, $value): void { Injector::inject(self::class)->setValue($key, $value); }
    public static function get(string $key, $default = null) { return Injector::inject(self::class)->getValue($key, $default); }
    public static function pull(string $key, $default = null) { return Injector::inject(self::class)->pullValue($key, $default); }
    public static function has(string $key): bool { return Injector::inject(self::class)->hasKey($key); }
}
