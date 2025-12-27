<?php
namespace Sophia\Injector;

use Sophia\Component\ComponentProxy;
use ReflectionClass;
use ReflectionException;
use Throwable;

class Injector
{
    private static array $rootInstances = [];
    private static array $treeScopes = [];
    private static ?ComponentProxy $currentScope = null;

    /**
     * @throws ReflectionException
     */
    public static function inject(string $className, ?ComponentProxy $scope = null): object
    {
        if (static::isRootProvided($className)) {
            return static::$rootInstances[$className] ??= static::createInstance($className, null);
        }

        if (!$scope) {
            $scope = static::$currentScope;
        }

        $scopeKey = $scope ? $scope->getId() : 0;

        $serviceName = basename(str_replace('\\', '/', $className));
        $componentName = $scope && isset($scope->instance) ? basename(str_replace('\\', '/', get_class($scope->instance))) : 'Proxy';

        if (isset(static::$treeScopes[$scopeKey][$className])) {
            $instance = static::$treeScopes[$scopeKey][$className];
            $itemCount = method_exists($instance, 'getItems') ? count($instance->getItems()) : '?';
            return $instance;
        }

        $isProvidedHere = $scope && in_array($className, $scope->getConfig()->providers, true);

        if ($isProvidedHere) {
            static::$treeScopes[$scopeKey][$className] = static::createInstance($className, $scope);
            return static::$treeScopes[$scopeKey][$className];
        }

        if ($scope && $scope->parentScope) {
            $parentId = $scope->parentScope->getId();
            $parentName = isset($scope->parentScope->instance) ? basename(str_replace('\\', '/', get_class($scope->parentScope->instance))) : 'Proxy';
            return static::inject($className, $scope->parentScope);
        }

        throw new \RuntimeException(
            "No provider found for '{$className}'. " .
            "Add it to a component's providers array or mark it with #[Injectable(providedIn: 'root')]"
        );
    }

    public static function enterScope(ComponentProxy $proxy): void
    {
        $id = $proxy->getId();
        $componentName = isset($proxy->instance) ? basename(str_replace('\\', '/', get_class($proxy->instance))) : 'Proxy';

        static::$currentScope = $proxy;
    }

    public static function exitScope(): void
    {
        static::$currentScope = null;
    }

    public static function getCurrentScope(): ?ComponentProxy
    {
        return static::$currentScope;
    }

    private static function isRootProvided(string $className): bool
    {
        try {
            $reflection = new ReflectionClass($className);
            $attr = $reflection->getAttributes(Injectable::class)[0] ?? null;
            return $attr && $attr->newInstance()->providedIn === 'root';
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @throws ReflectionException
     */
    private static function createInstance(string $className, ?ComponentProxy $scopeContext = null): object
    {
        $reflection = new ReflectionClass($className);
        $constructor = $reflection->getConstructor();

        if (!$constructor || $constructor->getNumberOfRequiredParameters() === 0) {
            return new $className();
        }

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType()?->getName();
            if ($type && class_exists($type)) {
                $scope = $scopeContext ?? static::getCurrentScope();
                $args[] = static::inject($type, $scope);
            } elseif ($param->isOptional()) {
                $args[] = $param->getDefaultValue();
            } else {
                throw new \RuntimeException("Cannot resolve '{$param->getName()}' for {$className}");
            }
        }

        return $reflection->newInstanceArgs($args);
    }
}