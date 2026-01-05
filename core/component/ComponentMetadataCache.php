<?php
namespace Sophia\Component;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class ComponentMetadataCache
{
    private static array $cache = [];

    public static function warmup(string $className): array
    {
        if (isset(self::$cache[$className])) {
            return self::$cache[$className];
        }

        $reflection = new ReflectionClass($className);
        $meta = [
            'inputs' => [],
            'slots' => [],
            'getters' => [],
            'public_props' => [],
            'inject_props' => [],
            'hasOnInit' => false,
            'providers' => [],
            'componentAttr' => null
        ];

        // 1. Attributo Component
        $componentAttrs = $reflection->getAttributes(Component::class);
        if (!empty($componentAttrs)) {
            $meta['componentAttr'] = $componentAttrs[0]->newInstance();
            $meta['providers'] = $meta['componentAttr']->providers ?? [];
        }

        // 2. Proprietà
        foreach ($reflection->getProperties() as $prop) {
            $propName = $prop->getName();

            // Input
            $inputAttrs = $prop->getAttributes(Input::class);
            if (!empty($inputAttrs)) {
                $inputInstance = $inputAttrs[0]->newInstance();
                $alias = $inputInstance->alias ?? $propName;
                $meta['inputs'][$alias] = $propName;
            }

            // Inject
            if (!empty($prop->getAttributes(\Sophia\Injector\Inject::class))) {
                $meta['inject_props'][] = [
                    'name' => $propName,
                    'type' => $prop->getType()?->getName()
                ];
            }

            // Slot
            if (!empty($prop->getAttributes(Slot::class))) {
                $meta['slots'][] = $propName;
            }

            if ($prop->isPublic()) {
                $meta['public_props'][] = $propName;
            }
        }

        // 3. Metodi
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $mName = $method->getName();

            if ($mName === 'onInit') {
                $meta['hasOnInit'] = true;
            }

            if (str_starts_with($mName, 'get') &&
                $method->getNumberOfRequiredParameters() === 0) {
                $key = lcfirst(substr($mName, 3));
                $meta['getters'][$key] = $mName;
            }
        }

        self::$cache[$className] = $meta;
        return $meta;
    }

    public static function clear(): void
    {
        self::$cache = [];
    }
}