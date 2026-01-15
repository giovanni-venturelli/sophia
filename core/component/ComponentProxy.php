<?php

namespace Sophia\Component;

use Sophia\Injector\Inject;
use Sophia\Injector\Injector;
use ReflectionClass;
use ReflectionException;

class ComponentProxy
{
    private static int $idCounter = 0;
    private int $id;

    public Component $config;
    public object $instance;
    public ?ComponentProxy $parentScope = null;

    private static array $injectionCache = [];

    /**
     * @throws ReflectionException
     */
    public function __construct(string $className, Component $config, ?ComponentProxy $parent = null)
    {
        $this->id = ++self::$idCounter;
        $this->config = $config;
        $this->parentScope = $parent;

        Injector::enterScope($this);

        foreach ($config->providers as $providerClass) {
            Injector::inject($providerClass, $this);
        }

        $this->instance = $this->createAndInject($className);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getConfig(): Component
    {
        return $this->config;
    }

    /**
     * @throws ReflectionException
     */
    private function createAndInject(string $className): object
    {
        if (!isset(self::$injectionCache[$className])) {
            $reflection = new ReflectionClass($className);
            $propsToInject = [];
            foreach ($reflection->getProperties() as $prop) {
                $injectAttr = $prop->getAttributes(Inject::class)[0] ?? null;
                if ($injectAttr) {
                    $type = $prop->getType()?->getName();
                    if ($type && class_exists($type)) {
                        $prop->setAccessible(true);
                        $propsToInject[] = ['prop' => $prop, 'type' => $type];
                    }
                }
            }
            self::$injectionCache[$className] = [
                'reflection' => $reflection,
                'inject' => $propsToInject,
                'hasOnInit' => method_exists($className, 'onInit')
            ];
        }

        $cache = self::$injectionCache[$className];
        $instance = $cache['reflection']->newInstance();

        foreach ($cache['inject'] as $injection) {
            $service = Injector::inject($injection['type'], $this);
            $injection['prop']->setValue($instance, $service);
        }

        // ⚠️ NON chiamare onInit() qui - sarà chiamato dal Renderer dopo applyInputBindings
        // if ($cache['hasOnInit']) {
        //     $instance->onInit();
        // }

        return $instance;
    }

    public function callOnInit(): void
    {
        $className = get_class($this->instance);
        if (method_exists($this->instance, 'onInit')) {
            $this->instance->onInit();
        }
    }

    public function __call(string $method, array $args): mixed
    {
        return $this->instance->$method(...$args);
    }

    public function __get(string $name): mixed
    {
        return $this->instance->$name;
    }
}