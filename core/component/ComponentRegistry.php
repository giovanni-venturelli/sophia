<?php
namespace App\Component;

use ReflectionClass;
use ReflectionException;

class ComponentRegistry
{
    private array $components = [];
    private static ?ComponentRegistry $instance = null;

    private function __construct() {}

    public static function getInstance(): ComponentRegistry
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }
    /**
     * Registra un componente e tutti i suoi imports ricorsivamente
     *
     * @throws ReflectionException
     */
    public function register(string $class): void
    {

        // Evita registrazioni duplicate
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

        // Registra ricorsivamente tutti gli imports
        foreach ($config->imports as $importClass) {
            $this->register($importClass);
        }
    }

    /**
     * Registra multipli componenti
     *
     * @param array $classes
     * @throws ReflectionException
     */
    public function registerMany(array $classes): void
    {
        foreach ($classes as $class) {
            $this->register($class);
        }
    }

    /**
     * Verifica se un componente è già registrato
     *
     * @param string $classOrSelector Nome della classe o selector del componente
     * @return bool
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
     *
     * @param string $selector
     * @return array|null
     */
    public function get(string $selector): ?array
    {
        return $this->components[$selector] ?? null;
    }

    /**
     * Ottiene tutti i selettori registrati
     *
     * @return array
     */
    public function getSelectors(): array
    {
        return array_keys($this->components);
    }

    /**
     * Ottiene tutte le classi registrate
     *
     * @return array
     */
    public function getClasses(): array
    {
        return array_column($this->components, 'class');
    }

    /**
     * Ottiene tutti i componenti
     *
     * @return array
     */
    public function getAll(): array
    {
        return $this->components;
    }
}