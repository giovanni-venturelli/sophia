<?php
/**
 * ComponentRegistry - Singleton for centralized component management
 */
namespace Sophia\Component;

use ReflectionClass;
use ReflectionException;
use Sophia\Debug\Profiler;
use Throwable;

class ComponentRegistry {
    private static ?self $instance = null;
    private array $components = [];
    private static array $reflectionCache = [];  // ← PERFORMANCE CACHE!

    public static function getInstance(): self {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register a component class explicitly
     * @throws ReflectionException
     */
    public function register(string $className): void {
        $reflection = new ReflectionClass($className);
        $componentAttr = $reflection->getAttributes(Component::class)[0] ?? null;

        if (!$componentAttr) return;

        $config = $componentAttr->newInstance();
        $this->components[$config->selector] = [
            'class' => $className,
            'template' => $config->template,
            'styles' => $config->styles,     // ← Array
            'scripts' => $config->scripts,   // ← Array NUOVO
            'providers' => $config->providers,
            'imports' => $config->imports
        ];
    }

    /**
     * Lazy Registration - NO options!
     */
    public function lazyRegister(string $class): string {
        Profiler::start('ComponentRegistry::lazyRegister');
        Profiler::count('lazyRegister calls');

        $selector = $this->getSelectorFromClass($class);
        if ($this->has($selector)) {
            Profiler::count('lazyRegister cache hits');
            Profiler::end('ComponentRegistry::lazyRegister');
            return $selector;
        }

        Profiler::start('lazyRegister::registerRecursive');
        $this->registerRecursive($class, []);
        Profiler::end('lazyRegister::registerRecursive');

        Profiler::end('ComponentRegistry::lazyRegister');
        return $selector;
    }

    /**
     * Private recursive registration
     * @throws ReflectionException
     */

    private function registerRecursive(string $class, array $options): void {
        Profiler::count('registerRecursive calls');

        $selector = $this->getSelectorFromClass($class);
        if ($this->has($selector)) {
            return; // ⚡ Already registered
        }

        Profiler::start('registerRecursive::registerClass');
        $this->registerClass($class, $options);
        Profiler::end('registerRecursive::registerClass');

        // Auto-registra imports RICORSIVAMENTE
        Profiler::start('registerRecursive::getImports');
        $imports = $this->getImportsFromComponentAttribute($class);
        Profiler::end('registerRecursive::getImports');

        foreach ($imports as $importClass) {
            $this->registerRecursive($importClass, $options); // ⚡ Potenzialmente lento!
        }
    }

    /**
     * Register single class with cache
     * @throws ReflectionException
     */
    private function registerClass(string $class, array $options): void {
        Profiler::count('registerClass calls');

        // Cache check
        $cacheKey = $class;
        if (isset(static::$reflectionCache[$cacheKey])) {
            $ref = static::$reflectionCache[$cacheKey];
        } else {
            Profiler::start('registerClass::reflection');
            $ref = new ReflectionClass($class);
            static::$reflectionCache[$cacheKey] = $ref;
            Profiler::end('registerClass::reflection');
        }

        $attr = $ref->getAttributes(Component::class)[0] ?? null;
        if (!$attr) {
            throw new ReflectionException("Class {$class} must have #[Component] attribute");
        }

        Profiler::start('registerClass::newInstance');
        $config = $attr->newInstance();
        Profiler::end('registerClass::newInstance');

        $this->components[$config->selector] = [
            'class' => $class,
            'config' => $config,
            'reflection' => $ref,
            'options' => $options
        ];
    }

    /**
     * Get selector from class (with cache)
     */
    private function getSelectorFromClass(string $class): string {
        Profiler::count('getSelectorFromClass calls');

        try {
            $cacheKey = "selector:{$class}";
            if (isset(static::$reflectionCache[$cacheKey])) {
                Profiler::count('getSelectorFromClass cache hits');
                return static::$reflectionCache[$cacheKey];
            }

            Profiler::start('getSelectorFromClass::reflection');
            $ref = new ReflectionClass($class);
            $attr = $ref->getAttributes(Component::class)[0] ?? null;

            if ($attr) {
                $config = $attr->newInstance();
                $selector = $config->selector;
                static::$reflectionCache[$cacheKey] = $selector;
                Profiler::end('getSelectorFromClass::reflection');
                return $selector;
            }

            $selector = $class;
            static::$reflectionCache[$cacheKey] = $selector;
            Profiler::end('getSelectorFromClass::reflection');
            return $selector;
        } catch (Throwable) {
            return $class;
        }
    }

    /**
     * Get imports from component attribute
     */
    private function getImportsFromComponentAttribute(string $class): array {
        Profiler::count('getImportsFromComponentAttribute calls');

        try {
            $reflection = new ReflectionClass($class);
            $componentAttr = $reflection->getAttributes(Component::class);

            if (empty($componentAttr)) return [];

            $component = $componentAttr[0]->newInstance();
            return $component->imports ?? [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Check if a component selector is registered
     */
    public function has(string $selector): bool {
        return isset($this->components[$selector]);
    }

    /**
     * Check if a class or selector is registered
     */
    public function isRegistered(string $classOrSelector): bool {
        // Cerca per selector
        if (isset($this->components[$classOrSelector])) {
            return true;
        }

        // Cerca per classe
        foreach ($this->components as $data) {
            if ($data['class'] === $classOrSelector) {
                return true;
            }
        }
        return false;
    }

    /**
     * Gets component data from selector
     */
    public function get(string $selector): ?array {
        return $this->components[$selector] ?? null;
    }

    /**
     * Gets all registered selectors (debug)
     */
    public function getSelectors(): array {
        return array_keys($this->components);
    }

    /**
     * Gets all registered classes (debug)
     */
    public function getClasses(): array {
        return array_column($this->components, 'class');
    }

    /**
     * Gets all registered components (debug)
     */
    public function getAll(): array {
        return $this->components;
    }

    /**
     * Clear reflection cache (debug/prod)
     */
    public static function clearReflectionCache(): void {
        static::$reflectionCache = [];
    }
}