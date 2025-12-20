<?php
/**
 * ComponentRegistry - Singleton for centralized component management
 */
namespace App\Component;

use ReflectionClass;
use ReflectionException;
use Throwable;

class ComponentRegistry
{
    /** @var self|null */
    private static ?self $instance = null;

    /** @var array<string, array> */
    private array $components = [];

    /**
     *
     */
    public static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     *
     * @throws ReflectionException
     */
    public function register(string $class): void
    {
        if ($this->isRegistered($class)) {
            return;
        }

        $ref = new ReflectionClass($class);
        $attr = $ref->getAttributes(Component::class)[0] ?? null;

        if (!$attr) {
            throw new \RuntimeException("Class $class must have #[Component] attribute");
        }

        /** @var Component $config */
        $config = $attr->newInstance();

        // Salva il componente
        $this->components[$config->selector] = [
            'class' => $class,
            'config' => $config,
            'reflection' => $ref
        ];

        // 🔥 auto-registration
        foreach ($config->imports ?? [] as $importClass) {
            $this->register($importClass);
        }
    }

    /**
     * Lazy Registration
     */
    public function lazyRegister(string $class): string
    {
        $selector = $this->getSelectorFromClass($class);
        if (!$this->has($selector)) {
            $this->registerRecursive($class); // ← NO options!
        }
        return $selector;
    }


    /**
     *
     * @throws ReflectionException
     */
    private function registerRecursive(string $class, array $options = []): void
    {
        $selector = $this->getSelectorFromClass($class);
        if ($this->has($selector)) {
            return;
        }

        $this->registerClass($class, $options);

        // Auto-registra imports RICORSIVAMENTE
        $imports = $this->getImportsFromComponentAttribute($class);
        foreach ($imports as $importClass) {
            $this->registerRecursive($importClass, $options);
        }
    }

    /**
     *
     * @throws ReflectionException
     */
    private function registerClass(string $class, array $options = []): void
    {
        $ref = new ReflectionClass($class);
        $attr = $ref->getAttributes(Component::class)[0] ?? null;

        if (!$attr) {
            throw new \RuntimeException("Class $class must have #[Component] attribute");
        }

        /** @var Component $config */
        $config = $attr->newInstance();

        $this->components[$config->selector] = [
            'class' => $class,
            'config' => $config,
            'reflection' => $ref,
            'options' => $options
        ];
    }

    /**
     *
     */
    private function getSelectorFromClass(string $class): string
    {
        try {
            $ref = new ReflectionClass($class);
            $attr = $ref->getAttributes(Component::class)[0] ?? null;
            if ($attr) {
                /** @var Component $config */
                $config = $attr->newInstance();
                return $config->selector;
            }
        } catch (Throwable) {}
        return $class;
    }

    /**
     *
     */
    private function getImportsFromComponentAttribute(string $class): array
    {
        try {
            $reflection = new ReflectionClass($class);
            $componentAttr = $reflection->getAttributes(Component::class);

            if (empty($componentAttr)) {
                return [];
            }

            /** @var Component $component */
            $component = $componentAttr[0]->newInstance();
            return $component->imports ?? [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Check if a component is already registered
     */
    public function has(string $selector): bool
    {
        return isset($this->components[$selector]);
    }

    /**
     * Check if a class or a selector is registered
     */
    public function isRegistered(string $classOrSelector): bool
    {
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
     * Gets component's data from a selector
     */
    public function get(string $selector): ?array
    {
        return $this->components[$selector] ?? null;
    }

    /**
     * Gets all registered selectors (debug purposes)
     */
    public function getSelectors(): array
    {
        return array_keys($this->components);
    }

    /**
     * Gets all registered classes (debug purposes)
     */
    public function getClasses(): array
    {
        return array_column($this->components, 'class');
    }

    /**
     *Gets all registered components (debug purposes)
     */
    public function getAll(): array
    {
        return $this->components;
    }
}
