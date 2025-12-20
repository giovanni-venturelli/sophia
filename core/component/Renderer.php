<?php
namespace App\Component;

use App\Router\Router;
use ReflectionClass;
use ReflectionMethod;
use ReflectionObject;
use ReflectionProperty;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;


class Renderer
{
    private Environment $twig;
    private ComponentRegistry $registry;
    private array $templatePaths = [];

    public function __construct(ComponentRegistry $registry, string $templatesPath, string $cachePath, bool $debug = true)
    {
        $this->registry = $registry;

        // PRIMA crea loader vuoto
        $loader = new FilesystemLoader();
        $this->twig = new Environment($loader, [
            'cache' => $cachePath,
            'auto_reload' => $debug,
            'debug' => $debug,
            'strict_variables' => true,
        ]);

        // POI aggiungi paths (AGGIORNA loader dinamicamente)
        $this->addTemplatePath($templatesPath);

        $this->registerCustomFunctions();
    }

    /**
     * 🔥 PATH RELATIVI → NOME RELATIVO per Twig!
     */
    private function resolveTemplatePath(string $componentClass, string $template): string
    {
        $reflection = new ReflectionClass($componentClass);
        $componentDir = dirname($reflection->getFileName());

        // Prova path relativo dal componente
        $fullPath = realpath($componentDir . '/' . $template);
        if ($fullPath && file_exists($fullPath)) {
            $this->addTemplatePath(dirname($fullPath)); // Registra directory
            return basename($fullPath); // ← SOLO NOME!
        }

        // Fallback globali
        $templateName = basename($template);
        foreach ($this->templatePaths as $basePath) {
            $fullPath = realpath($basePath . '/' . $templateName);
            if ($fullPath && file_exists($fullPath)) {
                return $templateName; // ← SOLO NOME!
            }
        }

        throw new \RuntimeException("Template '{$template}' not found for {$componentClass}");
    }

    public function renderRoot(string $selector, array $data = []): string
    {
        $entry = $this->registry->get($selector);
        if (!$entry) {
            throw new \RuntimeException("Component {$selector} not found");
        }

        $instance = new $entry['class']();
        $this->injectData($instance, $data);
        return $this->renderInstance($instance, $entry['config']);
    }

    private function renderInstance(object $component, Component $config): string
    {
        if (!$config->template) {
            throw new \RuntimeException("Component {$config->selector} has no template");
        }

        // 🔥 OTTIENI NOME RELATIVO e registra path
        $templateName = $this->resolveTemplatePath(get_class($component), $config->template);

        $templateData = $this->extractComponentData($component);
        $templateData['__component'] = [
            'selector' => $config->selector,
            'meta' => $config->meta ?? []
        ];

        return $this->twig->render($templateName, $templateData); // ← NOME RELATIVO!
    }

    // ... RESTO IDENTICO (renderComponent, applyInputBindings, etc.)
    public function renderComponent(string $selector, array $bindings = []): string
    {
        $entry = $this->registry->get($selector);
        if (!$entry) {
            return "<!-- Component {$selector} not found -->";
        }

        $instance = new $entry['class']();
        $this->applyInputBindings($instance, $bindings);
        return $this->renderInstance($instance, $entry['config']);
    }

    private function registerCustomFunctions(): void
    {
        $this->twig->addFunction(new TwigFunction('component', [$this, 'renderComponent'], ['is_safe' => ['html']]));
        $this->twig->addFunction(new TwigFunction('route_data', [$this, 'getRouteData']));
        $this->twig->addFunction(new TwigFunction('url', [$this, 'generateUrl']));
    }

    private function applyInputBindings(object $component, array $bindings): void
    {
        $ref = new ReflectionObject($component);
        foreach ($ref->getProperties() as $prop) {
            $inputAttr = $prop->getAttributes(Input::class)[0] ?? null;
            if (!$inputAttr) continue;

            $input = $inputAttr->newInstance();
            $name = $input->alias ?? $prop->getName();

            if (!array_key_exists($name, $bindings)) continue;

            $prop->setAccessible(true);
            $prop->setValue($component, $bindings[$name]);
        }
    }

    private function injectData(object $component, array $data): void
    {
        foreach ($data as $key => $value) {
            if (property_exists($component, $key)) {
                $component->$key = $value;
            }
        }
    }

    private function extractComponentData(object $component): array
    {
        $data = [];
        $reflection = new ReflectionObject($component);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $data[$prop->getName()] = $prop->getValue($component);
        }

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (str_starts_with($method->getName(), 'get') && $method->getNumberOfParameters() === 0) {
                $propertyName = lcfirst(substr($method->getName(), 3));
                $data[$propertyName] = $method->invoke($component);
            }
        }

        return $data;
    }

    public function getRouteData(?string $key = null): mixed
    {
        $router = Router::getInstance();
        return $router->getCurrentRouteData($key);
    }

    public function generateUrl(string $name, array $params = []): string
    {
        $router = Router::getInstance();
        return $router->url($name, $params);
    }

    public function getTwig(): Environment
    {
        return $this->twig;
    }

    public function addTemplatePath(string $path, ?string $namespace = null): void
    {
        $realPath = realpath($path);
        if (!$realPath || in_array($realPath, $this->templatePaths)) {
            return;
        }

        $this->templatePaths[] = $realPath;

        // 🔥 AGGIORNA LOADER DYNAMICAMENTE!
        $loader = $this->twig->getLoader();
        if ($loader instanceof FilesystemLoader) {
            if ($namespace) {
                $loader->addPath($realPath, $namespace);
            } else {
                $loader->addPath($realPath);
            }
        }
    }
}