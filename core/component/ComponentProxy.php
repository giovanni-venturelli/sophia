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

    /**
     * @throws ReflectionException
     */
    public function __construct(string $className, Component $config, ?ComponentProxy $parent = null)
    {
        $this->id = ++self::$idCounter;
        $this->config = $config;
        $this->parentScope = $parent;

        $componentName = basename(str_replace('\\', '/', $className));
        $parentId = $parent ? $parent->getId() : 'null';


        Injector::enterScope($this);

        foreach ($config->providers as $providerClass) {
            $serviceName = basename(str_replace('\\', '/', $providerClass));
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
        $reflection = new ReflectionClass($className);
        $instance = $reflection->newInstance();

        $componentName = basename(str_replace('\\', '/', $className));

        foreach ($reflection->getProperties() as $prop) {
            $injectAttr = $prop->getAttributes(Inject::class)[0] ?? null;
            if (!$injectAttr) continue;

            $type = $prop->getType()?->getName();

            if (!$type || !class_exists($type)) {
                continue;
            }

            $prop->setAccessible(true);
            $service = Injector::inject($type, $this);
            $prop->setValue($instance, $service);

        }

        if (method_exists($instance, 'onInit')) {
            $instance->onInit();

            if (method_exists($instance, 'getServiceCount')) {
                $count = $instance->getServiceCount();
            }
        }

        return $instance;
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