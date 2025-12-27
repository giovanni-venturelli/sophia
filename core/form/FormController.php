<?php
namespace Sophia\Form;

use Sophia\Form\Results\JsonResult;
use Sophia\Form\Results\NoContentResult;
use Sophia\Form\Results\RedirectResult;
use Sophia\Injector\Injector;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

class FormController
{
    /**
     * @throws ReflectionException
     */
    public static function handle(array $params): void
    {
        $token = $params['token'] ?? null;
        if (!$token) {
            http_response_code(400);
            echo 'Missing form token';
            return;
        }

        $registry = FormRegistry::getInstance();
        $entry = $registry->resolve($token);
        if (!$entry) {
            http_response_code(400);
            echo 'Invalid or expired form token';
            return;
        }

        // CSRF check
        $csrf = Injector::inject(CsrfService::class);
        $csrfToken = $_POST['_csrf'] ?? null;
        if (!$csrf->validate($csrfToken)) {
            http_response_code(400);
            echo 'CSRF validation failed';
            return;
        }

        $class = $entry['class'];
        $methodName = $entry['method'];

        if (!class_exists($class)) {
            http_response_code(500);
            echo "Handler class '{$class}' not found";
            return;
        }
        
        // Instantiate handler with DI (constructor) and inject #[Inject] properties
        $instance = self::makeHandlerInstance($class);
        self::injectProperties($instance);

        if (!method_exists($instance, $methodName)) {
            http_response_code(500);
            echo "Handler method '{$methodName}' not found on '{$class}'";
            return;
        }

        $method = new ReflectionMethod($instance, $methodName);
        $args = [];
        foreach ($method->getParameters() as $p) {
            $type = $p->getType()?->getName();
            if ($type === FormRequest::class) {
                $args[] = new FormRequest();
            } else {
                // Try to resolve class-typed parameters via Injector
                if ($type && class_exists($type)) {
                    $args[] = Injector::inject($type);
                } else {
                    $args[] = $p->isDefaultValueAvailable() ? $p->getDefaultValue() : null;
                }
            }
        }

        // Call handler
        $result = $method->invokeArgs($instance, $args);

        // Single-use: invalidate token
        if (($entry['singleUse'] ?? true) === true) {
            $registry->invalidate($token);
        }

        // Normalize and emit response
        if ($result instanceof RedirectResult) {
            http_response_code($result->status);
            header('Location: ' . $result->location);
            return;
        }
        if ($result instanceof JsonResult) {
            http_response_code($result->status);
            header('Content-Type: application/json');
            echo json_encode($result->data);
            return;
        }
        if ($result instanceof NoContentResult) {
            http_response_code($result->status);
            return;
        }
        if (is_array($result)) {
            header('Content-Type: application/json');
            echo json_encode($result);
            return;
        }
        if (is_string($result)) {
            // Treat as redirect URL
            header('Location: ' . $result);
            return;
        }

        // Default: 204
        http_response_code(204);
    }

    /**
     * Create an instance of the handler class resolving constructor dependencies via Injector.
     */
    private static function makeHandlerInstance(string $class): object
    {
        $ref = new ReflectionClass($class);
        $ctor = $ref->getConstructor();
        if (!$ctor || $ctor->getNumberOfRequiredParameters() === 0) {
            return new $class();
        }
        $args = [];
        foreach ($ctor->getParameters() as $param) {
            $type = $param->getType()?->getName();
            if ($type && class_exists($type)) {
                $args[] = Injector::inject($type);
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                throw new \RuntimeException("Cannot resolve constructor param '{$param->getName()}' for {$class}");
            }
        }
        return $ref->newInstanceArgs($args);
    }

    /**
     * Inject properties marked with #[Inject] on the given instance using the root Injector.
     */
    private static function injectProperties(object $instance): void
    {
        $ref = new \ReflectionObject($instance);
        foreach ($ref->getProperties() as $prop) {
            $attrs = $prop->getAttributes(\Sophia\Injector\Inject::class);
            if (!$attrs) { continue; }
            $type = $prop->getType()?->getName();
            if ($type && class_exists($type)) {
                $service = Injector::inject($type);
                $prop->setAccessible(true);
                $prop->setValue($instance, $service);
            }
        }
    }
}
