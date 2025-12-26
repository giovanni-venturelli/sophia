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
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

class Renderer
{
    private Environment $twig;
    private ComponentRegistry $registry;
    private array $templatePaths = [];

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
     * ðŸ”¥ AGGIORNATO: supporta slot content
     */
    public function renderComponent(string $selector, array $bindings = [], ?string $slotContent = null): string
    {
        $entry = $this->registry->get($selector);
        if (!$entry) {
            return "<!-- Component {$selector} not found -->";
        }

        $parentScope = Injector::getCurrentScope();
        $proxy = new ComponentProxy($entry['class'], $entry['config'], $parentScope);

        $this->applyInputBindings($proxy->instance, $bindings);

        // ðŸ”¥ SLOT INJECTION: inietta il contenuto degli slot nel componente
        if ($slotContent !== null) {
            $this->injectSlotContent($proxy->instance, $slotContent);
        }

        $html = $this->renderInstance($proxy);

        if ($parentScope) {
            Injector::enterScope($parentScope);
        } else {
            Injector::exitScope();
        }

        return $html;
    }

    /**
     * ðŸ”¥ NUOVO: Inietta il contenuto degli slot nel componente
     */
    private function injectSlotContent(object $component, string $slotContent): void
    {
        $slots = $this->parseSlotContent($slotContent);
        $ref = new ReflectionObject($component);

        foreach ($ref->getProperties() as $prop) {
            $slotAttr = $prop->getAttributes(Slot::class)[0] ?? null;
            if (!$slotAttr) continue;

            $slotConfig = $slotAttr->newInstance();
            $slotName = $slotConfig->name;

            // Cerca il contenuto corrispondente
            $content = $slots[$slotName] ?? null;

            if ($content) {
                $prop->setAccessible(true);
                $prop->setValue($component, $content);
            }
        }
    }

    /**
     * ðŸ”¥ NUOVO: Parse del contenuto per estrarre gli slot
     */
    private function parseSlotContent(string $content): array {
        $slots = [];
        if (preg_match_all('/<slot\\s+name=[\"|\']([^\"|\']+)[\"|\']\\s*>(.*?)<\/slot>/s', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $slotName = trim($match[1]);
                $slotHtml = trim($match[2]);
                $slots[$slotName] = new SlotContent($slotHtml, $slotName);
            }
        }
        return $slots;
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
        if (!empty($config->scripts)) {
            $jsContent = $this->loadScripts($proxy->instance::class, $config->scripts);
            $html = $this->injectGlobalScripts($html, $jsContent);
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
    private function loadScripts(string $componentClass, array $scripts): string
    {
        $reflection = new ReflectionClass($componentClass);
        $componentDir = dirname($reflection->getFileName());
        $jsContent = '';

        foreach ($scripts as $scriptFile) {
            $jsPath = realpath($componentDir . '/' . $scriptFile);
            if (!$jsPath || !file_exists($jsPath)) {
                throw new RuntimeException("Script '{$scriptFile}' not found for {$componentClass}");
            }
            $jsContent .= file_get_contents($jsPath);
        }

        return $jsContent;
    }

    private function injectGlobalStyles(string $html, string $css): string
    {
        $styleId = 'global-styles-' . uniqid();
        $styleTag = "<style id=\"{$styleId}\">{$css}</style>";

        if (stripos($html, '<head>') !== false) {
            return preg_replace('/<\/head>/i', $styleTag . '</head>', $html, 1);
        }

        return $styleTag . $html;
    }

    private function injectGlobalScripts(string $html, string $js): string
    {
        $scriptId = 'script-' . uniqid();
        $scriptTag = "<script id=\"{$scriptId}\">{$js}</script>";

        if (stripos($html, '</body>') !== false) {
            return preg_replace(
                '/<\/body>/i',
                $scriptTag . '</body>',
                $html,
                1
            );
        }

        return $scriptTag . $html;
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

    /**
     * ðŸ”¥ AGGIORNATO: Estrae i dati e gestisce automaticamente gli slot
     */
    private function extractComponentData(ComponentProxy $proxy): array
    {
        $data = [];
        $instance = $proxy->instance;
        $reflection = new ReflectionObject($instance);

        // ðŸ”¥ AUTO-GENERATE: slot helpers automatici (has* e slot functions)
        $slotHelpers = $this->generateSlotHelpers($reflection, $instance);
        $data = array_merge($data, $slotHelpers);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $value = $prop->getValue($instance);

            // ðŸ”¥ Se Ã¨ SlotContent, estrai l'HTML o stringa vuota
            if ($value instanceof SlotContent) {
                $data[$prop->getName()] = $value->html;
            } else {
                $data[$prop->getName()] = $value;
            }
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

    /**
     * ðŸ”¥ NUOVO: Genera automaticamente gli helper per gli slot
     * Crea:
     * - hasHeader, hasFooter, hasContent, etc. (boolean)
     * - Funzioni slot() per rendering con contesto
     */
    private function generateSlotHelpers(ReflectionObject $reflection, object $instance): array
    {
        $helpers = [];
        $slotObjects = [];

        foreach ($reflection->getProperties() as $prop) {
            $slotAttr = $prop->getAttributes(Slot::class)[0] ?? null;
            if (!$slotAttr) continue;

            $prop->setAccessible(true);
            $slotContent = $prop->getValue($instance);

            $propName = $prop->getName();
            $baseName = str_ends_with($propName, 'Slot') ? substr($propName, 0, -4) : $propName;

            // ðŸ”¥ FIX: Helper has + slot reference
            $helpers['has' . ucfirst($baseName)] = $slotContent instanceof SlotContent && !$slotContent->isEmpty();

            if ($slotContent instanceof SlotContent) {
                $slotObjects[$baseName] = $slotContent;
            }
        }

        // ðŸ”¥ FIX CRITICO: Contesto corretto per slot()
        $helpers['slot'] = function(string $name, array $context = []) use ($slotObjects) {
            $slotContent = $slotObjects[$name] ?? null;
            if (!$slotContent || $slotContent->isEmpty()) return '';
            return $slotContent->render($context);  // â† PASSA CONTESTO DEL TEMPLATE
        };

        return $helpers;
    }


    private function registerCustomFunctions(): void
    {
        $this->twig->addFunction(new TwigFunction('component', function(string $selector, array $bindings = [], ?string $slotContent = null) {
            return $this->renderComponent($selector, $bindings, $slotContent);
        }, ['is_safe' => ['html']]));
        $this->twig->addFunction(new TwigFunction('slot', function(array $twigContext, string $name, array $slotContext = []) {
            // Recupera la closure slot dal context del template
            if (isset($twigContext['slot']) && is_callable($twigContext['slot'])) {
                return $twigContext['slot']($name, $slotContext);
            }
            return '';
        }, ['is_safe' => ['html'], 'needs_context' => true]));
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