<?php

declare(strict_types=1);

namespace Sophia\Controller;

use ReflectionClass;
use ReflectionMethod;
use ReflectionAttribute;
use RuntimeException;

/**
 * 🔥 Registry per analizzare controller e cache i metodi HTTP decorati
 */
class ControllerRegistry
{
    private array $cache = [];

    /**
     * 🔥 Analizza un controller e ritorna i metodi HTTP decorati
     *
     * @param string $controllerClass Nome della classe controller
     * @return array Array di metodi con path, method, reflection
     */
    public function getControllerMethods(string $controllerClass): array
    {
        // Cache hit
        if (isset($this->cache[$controllerClass])) {
            return $this->cache[$controllerClass];
        }

        if (!class_exists($controllerClass)) {
            throw new RuntimeException("Controller class '{$controllerClass}' not found");
        }

        $reflection = new ReflectionClass($controllerClass);
        $methods = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // 🔥 FIX: Usa ReflectionAttribute::IS_INSTANCEOF invece di ReflectionMethod::IS_PUBLIC
            $attributes = $method->getAttributes(HttpMethod::class, ReflectionAttribute::IS_INSTANCEOF);

            foreach ($attributes as $attribute) {
                /** @var HttpMethod $httpMethod */
                $httpMethod = $attribute->newInstance();

                $methods[] = [
                    'httpMethod' => $httpMethod->method,
                    'path' => $httpMethod->path,
                    'methodName' => $method->getName(),
                    'reflection' => $method,
                ];
            }
        }

        // Cache
        $this->cache[$controllerClass] = $methods;
        return $methods;
    }

    /**
     * 🔥 Trova il metodo appropriato in un controller per una richiesta
     *
     * @param string $controllerClass
     * @param string $requestMethod GET, POST, PUT, DELETE, etc.
     * @param string $requestPath Path della richiesta (relativo alla route)
     * @return array|null ['methodName' => string, 'params' => array] o null
     */
    public function matchControllerMethod(
        string $controllerClass,
        string $requestMethod,
        string $requestPath
    ): ?array {
        $methods = $this->getControllerMethods($controllerClass);
        $requestPath = trim($requestPath, '/');

        foreach ($methods as $method) {
            // Controlla il metodo HTTP
            if (strtoupper($method['httpMethod']) !== strtoupper($requestMethod)) {
                continue;
            }

            // Match del path
            $match = $this->matchPath($method['path'] ?? '', $requestPath);
            if ($match !== null) {
                return [
                    'methodName' => $method['methodName'],
                    'params' => $match,
                    'reflection' => $method['reflection'],
                ];
            }
        }

        return null;
    }

    /**
     * 🔥 Matcha il pattern path con i parametri dinamici
     *
     * Pattern: '' o null → matcha root
     * Pattern: ':id' → matcha qualsiasi valore e lo cattura come 'id'
     * Pattern: ':id/detail' → matcha ':id/detail' e cattura 'id'
     *
     * @return array|null Parametri catturati o null se no match
     */
    private function matchPath(?string $pattern, string $request): ?array
    {
        // Nessun pattern = matcha solo root
        if (empty($pattern)) {
            return ($request === '' || $request === '/') ? [] : null;
        }

        $pattern = trim($pattern, '/');
        $request = trim($request, '/');

        if ($pattern === $request) {
            // Match esatto, nessun parametro
            return [];
        }

        // Split per segmenti
        $patternSegments = explode('/', $pattern);
        $requestSegments = explode('/', $request);

        // Se il numero di segmenti non corrisponde, no match
        if (count($patternSegments) !== count($requestSegments)) {
            return null;
        }

        $params = [];

        foreach ($patternSegments as $i => $segment) {
            $requestSegment = $requestSegments[$i] ?? null;

            if ($requestSegment === null) {
                return null;
            }

            // Parametro dinamico
            if (str_starts_with($segment, ':')) {
                $paramName = substr($segment, 1);
                $params[$paramName] = $requestSegment;
                continue;
            }

            // Segmento statico - deve matchare
            if ($segment !== $requestSegment) {
                return null;
            }
        }

        return $params;
    }

    /**
     * 🔥 Crea un'istanza del controller e chiama il metodo con injection
     */
    public function invokeControllerMethod(
        string $controllerClass,
        string $methodName,
        array $params = []
    ): mixed {
        if (!class_exists($controllerClass)) {
            throw new RuntimeException("Controller '{$controllerClass}' not found");
        }

        // Istanzia controller
        $controller = new $controllerClass();

        if (!method_exists($controller, $methodName)) {
            throw new RuntimeException("Method '{$methodName}' not found in '{$controllerClass}'");
        }

        $reflection = new ReflectionMethod($controller, $methodName);

        // Prepara gli argomenti basati sui parametri della route
        $args = [];
        foreach ($reflection->getParameters() as $param) {
            $paramName = $param->getName();
            if (isset($params[$paramName])) {
                $args[] = $params[$paramName];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            }
        }

        return $controller->$methodName(...$args);
    }
}