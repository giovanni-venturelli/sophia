<?php
/**
 * ComponentRegistry - Singleton per la gestione centralizzata dei componenti
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
     * Ottiene l'istanza singleton
     */
    public static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Registra un singolo componente (con auto-imports)
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

        // 🔥 AUTO-REGISTRAZIONE RICORSIVA imports
        foreach ($config->imports ?? [] as $importClass) {
            $this->register($importClass);
        }
    }

    /**
     * 🔥 NUOVO: Registrazione LAZY per Router (solo quando serve!)
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
     * 🔥 NUOVO: Registrazione ricorsiva con options
     */
    public function registerWithImports(array $classes, array $options = []): void
    {
        foreach ($classes as $class) {
            $this->registerRecursive($class, $options);
        }
    }

    /**
     * 🔥 METODO PRIVATO: Registrazione ricorsiva completa
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
     * Registra una classe specifica (senza ricorsione)
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
     * 🔥 OTTIMIZZATO: Ottiene selector da classe
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
     * 🔥 NUOVO: Estrae imports dall'attributo Component esistente
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
     * Verifica se un componente è già registrato
     */
    public function has(string $selector): bool
    {
        return isset($this->components[$selector]);
    }

    /**
     * Verifica se una classe o selector è registrato (legacy)
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
     * Ottiene i dati di un componente dal selector
     */
    public function get(string $selector): ?array
    {
        return $this->components[$selector] ?? null;
    }

    /**
     * Ottiene tutti i selettori registrati
     */
    public function getSelectors(): array
    {
        return array_keys($this->components);
    }

    /**
     * Ottiene tutte le classi registrate
     */
    public function getClasses(): array
    {
        return array_column($this->components, 'class');
    }

    /**
     * Ottiene tutti i componenti
     */
    public function getAll(): array
    {
        return $this->components;
    }
}
