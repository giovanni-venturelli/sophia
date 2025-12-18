<?php
namespace App\Component;


use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionException;

class ComponentRegistry
{
    private array $components = [];

    /**
     * @throws ReflectionException
     */
    public function __construct()
    {
        // Auto-scansione delle directory
        $this->autoRegisterFromDirectory(__DIR__ . '/../../pages/');
    }

    /**
     * @throws ReflectionException
     */
    private function autoRegisterFromDirectory(string $directory): void
    {

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $filePath = $file->getPathname();

                // Estrai il namespace e il nome della classe dal file
                $content = file_get_contents($filePath);

                if (preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatch) &&
                    preg_match('/class\s+(\w+)/', $content, $classMatch)) {

                    $fullClassName = $namespaceMatch[1] . '\\' . $classMatch[1];


                    if (class_exists($fullClassName)) {
                        $ref = new ReflectionClass($fullClassName);
                        if ($ref->getAttributes(Component::class)) {
                            $this->register($fullClassName);
                        }
                    }
                }
            }
        }

    }

    /**
     * @throws ReflectionException
     */
    public function register(string $class): void
    {
        $ref = new ReflectionClass($class);
        $attr = $ref->getAttributes(Component::class)[0] ?? null;

        if (!$attr) {
            return;
        }

        /** @var Component $config */
        $config = $attr->newInstance();

        $this->components[$config->selector] = [
            'class' => $class,
            'config' => $config
        ];
    }

    public function get(string $selector): ?array
    {
        return $this->components[$selector] ?? null;
    }

    public function getAll(): array
    {
        return $this->components;
    }
}