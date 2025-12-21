<?php

namespace App\Component;

use App\Injector\Injector;
use App\Router\Router;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionObject;
use ReflectionProperty;
use RuntimeException;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;
class Renderer
{
    private Environment $twig;
    private ComponentRegistry $registry;
    private array $templatePaths = [];

    /**
     * @throws LoaderError
     */
    public function __construct(
        ComponentRegistry $registry,
        string $templatesPath,
        string $cachePath = '',
        bool $debug = false
    ) {
        $this->registry = $registry;

        $loader = new FilesystemLoader();
        $this->twig = new Environment($loader, [
            'cache' => $cachePath,
            'auto_reload' => $debug,
            'debug' => $debug,
            'strict_variables' => true,
        ]);

        $this->addTemplatePath($templatesPath);
        $this->registerCustomFunctions();
    }

    private function resolveTemplatePath(string $componentClass, string $template): string
    {
        $reflection = new ReflectionClass($componentClass);
        $componentDir = dirname($reflection->getFileName());

        $fullPath = realpath($componentDir . '/' . $template);
        if ($fullPath && file_exists($fullPath)) {
            $this->addTemplatePath(dirname($fullPath));
            return basename($fullPath);
        }

        $templateName = basename($template);
        foreach ($this->templatePaths as $basePath) {
            $fullPath = realpath($basePath . '/' . $templateName);
            if ($fullPath && file_exists($fullPath)) {
                return $templateName;
            }
        }

        throw new RuntimeException("Template '{$template}' not found for {$componentClass}");
    }

    public function renderRoot(string $selector, array $data = []): string
    {
        $entry = $this->registry->get($selector);
        if (!$entry) {
            throw new RuntimeException("Component {$selector} not found");
        }

        $proxy = new ComponentProxy($entry['class'], $entry['config']);
        $this->injectData($proxy, $data);

        $html = $this->renderInstance($proxy);

        Injector::exitScope();

        return $html;
    }

    /**
     * @throws ReflectionException
     */
    public function renderComponent(string $selector, array $bindings = []): string
    {
        $entry = $this->registry->get($selector);
        if (!$entry) {
            return "<!-- Component {$selector} not found -->";
        }

        $parentScope = Injector::getCurrentScope();
        $parentId = $parentScope ? $parentScope->getId() : 'null';
        $parentName = $parentScope && isset($parentScope->instance) ? basename(str_replace('\\', '/', get_class($parentScope->instance))) : 'null';


        $proxy = new ComponentProxy($entry['class'], $entry['config'], $parentScope);

        $this->applyInputBindings($proxy->instance, $bindings);

        $html = $this->renderInstance($proxy);

        if ($parentScope) {
            Injector::enterScope($parentScope);
        } else {
            Injector::exitScope();
        }

        return $html;
    }

    private function renderInstance(ComponentProxy $proxy): string
    {
        Injector::enterScope($proxy);

        $config = $proxy->getConfig();
        $templateName = $this->resolveTemplatePath($proxy->instance::class, $config->template);
        $templateData = $this->extractComponentData($proxy);

        $html = $this->twig->render($templateName, $templateData);

        if (!empty($config->styles)) {
            $cssContent = $this->loadStyles($proxy->instance::class, $config->styles);
            $html = $this->injectGlobalStyles($html, $cssContent);
        }

        return $html;
    }

    private function loadStyles(string $componentClass, array $styles): string
    {
        $reflection = new ReflectionClass($componentClass);
        $componentDir = dirname($reflection->getFileName());
        $cssContent = '';

        foreach ($styles as $styleFile) {
            $cssPath = realpath($componentDir . '/' . $styleFile);
            if (!$cssPath || !file_exists($cssPath)) {
                throw new RuntimeException("Style '{$styleFile}' not found for {$componentClass}");
            }
            $cssContent .= file_get_contents($cssPath) . "\n\n";
        }

        return $cssContent;
    }

    private function injectGlobalStyles(string $html, string $css): string
    {
        $styleId = 'global-styles-' . uniqid();
        $styleTag = "<style id=\"{$styleId}\">{$css}</style>";

        if (stripos($html, '<head>') !== false) {
            return preg_replace('/<\\/head>/i', $styleTag . '</head>', $html, 1);
        }

        return $styleTag . $html;
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

    private function injectData(ComponentProxy $proxy, array $data): void
    {
        $component = $proxy->instance;
        foreach ($data as $key => $value) {
            if (property_exists($component, $key)) {
                $component->$key = $value;
            }
        }
    }

    private function extractComponentData(ComponentProxy $proxy): array
    {
        $data = [];
        $instance = $proxy->instance;
        $reflection = new ReflectionObject($instance);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $data[$prop->getName()] = $prop->getValue($instance);
        }

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (str_starts_with($method->getName(), 'get') &&
                $method->getNumberOfRequiredParameters() === 0) {
                $propertyName = lcfirst(substr($method->getName(), 3));
                $data[$propertyName] = $method->invoke($instance);
            }
        }

        return $data;
    }

    private function registerCustomFunctions(): void
    {
        $this->twig->addFunction(new TwigFunction('component', [$this, 'renderComponent'], ['is_safe' => ['html']]));
        $this->twig->addFunction(new TwigFunction('route_data', [$this, 'getRouteData']));
        $this->twig->addFunction(new TwigFunction('url', [$this, 'generateUrl']));
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