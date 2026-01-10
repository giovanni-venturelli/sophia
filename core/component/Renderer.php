<?php

namespace Sophia\Component;

use Sophia\Debug\Profiler;
use Sophia\Injector\Injectable;
use Sophia\Injector\Injector;
use Sophia\Router\Router;
use ReflectionClass;
use ReflectionMethod;
use ReflectionObject;
use ReflectionProperty;
use RuntimeException;
use Twig\Environment;
use Sophia\Form\FormRegistry;
use Sophia\Form\CsrfService;
use Sophia\Form\FlashService;
use Sophia\Form\Attributes\FormHandler;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

#[Injectable(providedIn: 'root')]
class Renderer
{
    private Environment $twig;
    private ComponentRegistry $registry;
    private array $templatePaths = [];

    private array $globalStyles = [];
    private array $globalScripts = [];
    private array $globalMetaTags = [];
    private array $componentStyles = [];
    private array $componentScripts = [];
    private array $metaTags = [];
    private string $pageTitle = '';
    private string $language = 'en';

    private array $profilingData = [];
    private static array $reflectionCache = [];
    private static array $inputBindingsCache = [];
    private static array $slotPropertiesCache = [];

    public function __construct(
        ?ComponentRegistry $registry = null,
        string            $templatesPath = '',
        string            $cachePath = '',
        string            $language = 'en',
        bool              $debug = false
    )
    {
        Profiler::start('Renderer::__construct');

        $this->registry = $registry ?? ComponentRegistry::getInstance();
        $this->language = $language;
        $this->initTwig($cachePath, $debug);
        if ($templatesPath !== '') {
            $this->addTemplatePath($templatesPath);
        }
        $this->registerCustomFunctions();

        Profiler::end('Renderer::__construct');
    }

    public function setRegistry(ComponentRegistry $registry): void
    {
        $this->registry = $registry;
    }

    public function configure(string $templatesPath, string $cachePath = '', string $language = 'en', bool $debug = false): void
    {
        $this->language = $language;
        $this->templatePaths = [];
        $this->initTwig($cachePath, $debug);
        $this->addTemplatePath($templatesPath);
        $this->registerCustomFunctions();
    }

    private function initTwig(string $cachePath, bool $debug): void
    {
        Profiler::start('initTwig');

        $loader = new FilesystemLoader();
        $this->twig = new Environment($loader, [
            'cache' => $cachePath,
            'auto_reload' => $debug,
            'debug' => $debug,
            'strict_variables' => true,
        ]);

        Profiler::end('initTwig');
    }

    private function resolveTemplatePath(string $componentClass, string $template): string
    {
        Profiler::start('resolveTemplatePath');
        Profiler::count('resolveTemplatePath calls');

        $reflection = new ReflectionClass($componentClass);
        $componentDir = dirname($reflection->getFileName());

        $fullPath = realpath($componentDir . '/' . $template);
        if ($fullPath && file_exists($fullPath)) {
            $this->addTemplatePath(dirname($fullPath));
            Profiler::end('resolveTemplatePath');
            return basename($fullPath);
        }

        $templateName = basename($template);
        foreach ($this->templatePaths as $basePath) {
            $fullPath = realpath($basePath . '/' . $templateName);
            if ($fullPath && file_exists($fullPath)) {
                Profiler::end('resolveTemplatePath');
                return $templateName;
            }
        }

        throw new RuntimeException("Template '{$template}' not found for {$componentClass}");
    }

    public function addGlobalStyle(string $css): void
    {
        $styleId = 'globalStyle-' . uniqid();
        $path = $css;
        $base = Router::getInstance()->getBasePath();
        if (trim($base) !== '') {
            $path = $base . '/' . $path;
        }
        $this->globalStyles[$styleId] = $path;
    }

    public function addGlobalScripts(string $js): void
    {
        $scriptId = 'globalScript-' . uniqid();

        if(str_starts_with($js, 'http')){
            $this->globalScripts[$scriptId] = $js;
            return;
        }
        $path = $js;
        $base = Router::getInstance()->getBasePath();
        if (trim($base) !== '') {
            $path = $base . '/' . $path;
        }
        $this->globalScripts[$scriptId] = $path;
    }

    public function addGlobalMetaTags(array $tags): void
    {
        foreach($tags as $tag) {
            $tagId = 'globalTag-' . uniqid();
            $this->globalMetaTags[$tagId] = $tag;
        }
    }

    public function renderRoot(string $selector, array $data = [], ?string $slotContent = null): string
    {
        Profiler::start('renderRoot');

        $entry = $this->registry->get($selector);
        if (!$entry) {
            throw new RuntimeException("Component {$selector} not found");
        }

        if ($slotContent === null) {
            $this->componentStyles = [];
            $this->componentScripts = [];
            $this->metaTags = [];
            $this->pageTitle = '';
        }

        $proxy = new ComponentProxy($entry['class'], $entry['config']);
        $this->injectData($proxy, $data);

        if ($slotContent !== null) {
            $this->injectSlotContent($proxy->instance, $slotContent);
        }

        $bodyContent = $this->renderInstance($proxy);

        Injector::exitScope();

        $html = $this->buildFullHtml($bodyContent);

        // Aggiungi report profiler
        $html .= Profiler::getReport();

        Profiler::end('renderRoot');

        return $html;
    }

    private function buildFullHtml(string $bodyContent): string
    {
        Profiler::start('buildFullHtml');

        $html = '<!DOCTYPE html>' . "\n";
        $html .= '<html lang="' . $this->language . '">' . "\n";
        $html .= '<head>' . "\n";
        $html .= '    <meta charset="UTF-8">' . "\n";
        $html .= '    <meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";

        if ($this->pageTitle) {
            $html .= '    <title>' . htmlspecialchars($this->pageTitle) . '</title>' . "\n";
        }

        foreach ($this->metaTags as $meta) {
            $html .= '    ' . $meta . "\n";
        }
        foreach ($this->globalMetaTags as $tag) {
            $html .= '   <meta name="' . htmlspecialchars($tag->name) . '" content="' . htmlspecialchars($tag->content) . '"> \n' ;
        }

        foreach ($this->globalStyles as $styleId => $css) {
            $html .= '    <link id="' . $styleId . '" rel="stylesheet" href="'.$css.'"></style>' . "\n";
        }
        foreach ($this->componentStyles as $styleId => $css) {
            $html .= '    <style id="' . $styleId . '">' . $css . '</style>' . "\n";
        }

        $html .= '</head>' . "\n";
        $html .= '<body>' . "\n";

        $html .= $bodyContent . "\n";

        foreach ($this->globalScripts as $scriptId => $js) {
            $html .= '    <script id="' . $scriptId . '" type="text/javascript" src="'.$js.'"></script>' . "\n";
        }
        foreach ($this->componentScripts as $scriptId => $js) {
            $html .= '    <script id="' . $scriptId . '">' . $js . '</script>' . "\n";
        }

        $html .= '</body>' . "\n";
        $html .= '</html>';

        Profiler::end('buildFullHtml');

        return $html;
    }

    public function renderComponent(string $selector, array $bindings = [], ?string $slotContent = null): string
    {
        Profiler::start("renderComponent::{$selector}");
        Profiler::count('renderComponent calls');

        $entry = $this->registry->get($selector);
        if (!$entry) {
            return "<!-- Component {$selector} not found -->";
        }

        $parentScope = Injector::getCurrentScope();
        $proxy = new ComponentProxy($entry['class'], $entry['config'], $parentScope);

        $this->applyInputBindings($proxy->instance, $bindings);

        if ($slotContent !== null) {
            $this->injectSlotContent($proxy->instance, $slotContent);
        }

        $html = $this->renderInstance($proxy);

        if ($parentScope) {
            Injector::enterScope($parentScope);
        } else {
            Injector::exitScope();
        }

        Profiler::end("renderComponent::{$selector}");

        return $html;
    }

    private function injectSlotContent(object $component, string $slotContent): void
    {
        Profiler::start('injectSlotContent');

        $slots = $this->parseSlotContent($slotContent);
        $className = get_class($component);

        // ⚡ CACHE: Proprietà slot una volta sola per classe
        if (!isset(self::$slotPropertiesCache[$className])) {
            $ref = new ReflectionObject($component);
            $slotProps = [];

            foreach ($ref->getProperties() as $prop) {
                $slotAttr = $prop->getAttributes(Slot::class)[0] ?? null;
                if ($slotAttr) {
                    $slotConfig = $slotAttr->newInstance();
                    $slotProps[] = [
                        'name' => $prop->getName(),
                        'slotName' => $slotConfig->name,
                    ];
                }
            }

            self::$slotPropertiesCache[$className] = $slotProps;
        }

        $slotProps = self::$slotPropertiesCache[$className];

        // ⚡ Applica slot velocemente
        foreach ($slotProps as $slotInfo) {
            $content = $slots[$slotInfo['slotName']] ?? null;
            if ($content) {
                $component->{$slotInfo['name']} = $content;
            }
        }

        Profiler::end('injectSlotContent');
    }

    private function parseSlotContent(string $content): array
    {
        Profiler::start('parseSlotContent');
        Profiler::count('parseSlotContent calls');

        $slots = [];
        if (preg_match_all('/<slot\s+name=["\']([^"\']+)["\']\s*>(.*?)<\/slot>/s', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $slotName = trim($match[1]);
                $slotHtml = trim($match[2]);
                $slots[$slotName] = new SlotContent($slotHtml, $slotName);
            }
        }
        if (preg_match_all('/<router-outlet\s+name=["\']([^"\']+)["\']\s*>(.*?)<\/router-outlet>/s', $content, $matches2, PREG_SET_ORDER)) {
            foreach ($matches2 as $match) {
                $slotName = trim($match[1]);
                $slotHtml = trim($match[2]);
                $slots[$slotName] = new SlotContent($slotHtml, $slotName);
            }
        }

        Profiler::end('parseSlotContent');

        return $slots;
    }

    private function renderInstance(ComponentProxy $proxy): string
    {
        Profiler::start('renderInstance');

        Injector::enterScope($proxy);
        $start = microtime(true);
        $config = $proxy->getConfig();

        Profiler::start('resolveTemplate');
        $templateName = $this->resolveTemplatePath($proxy->instance::class, $config->template);
        Profiler::end('resolveTemplate');

        Profiler::start('extractComponentData');
        $templateData = $this->extractComponentData($proxy);
        Profiler::end('extractComponentData');

        Profiler::start('twig->render');
        $html = $this->twig->render($templateName, $templateData);
        Profiler::end('twig->render');

        if (!empty($config->styles)) {
            Profiler::start('loadStyles');
            $cssContent = $this->loadStyles($proxy->instance::class, $config->styles);
            $styleId = 'style-' . MD5($proxy->instance::class);
            if(!isset($this->componentStyles[$styleId])) {
                $this->componentStyles[$styleId] = $cssContent;
            }
            Profiler::end('loadStyles');
        }

        if (!empty($config->scripts)) {
            Profiler::start('loadScripts');
            $jsContent = $this->loadScripts($proxy->instance::class, $config->scripts);
            $scriptId = 'script-' . uniqid();
            $this->componentScripts[$scriptId] = $jsContent;
            Profiler::end('loadScripts');
        }

        $this->profilingData[] = [
            'template' => $templateName,
            'time' => microtime(true) - $start
        ];

        Profiler::end('renderInstance');

        return $html;
    }
    private static array $stylesContentCache = [];

    private function loadStyles(string $componentClass, array $styles): string
    {
        Profiler::count('loadStyles calls');

        // ⚡ CACHE: Contenuto CSS una volta sola per classe
        $cacheKey = $componentClass;
        if (isset(self::$stylesContentCache[$cacheKey])) {
            return self::$stylesContentCache[$cacheKey];
        }

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

        self::$stylesContentCache[$cacheKey] = $cssContent;
        return $cssContent;
    }
    private static array $scriptsContentCache = [];

    private function loadScripts(string $componentClass, array $scripts): string
    {
        Profiler::count('loadScripts calls');

        // ⚡ CACHE: Contenuto JS una volta sola per classe
        $cacheKey = $componentClass;
        if (isset(self::$scriptsContentCache[$cacheKey])) {
            return self::$scriptsContentCache[$cacheKey];
        }

        $reflection = new ReflectionClass($componentClass);
        $componentDir = dirname($reflection->getFileName());
        $jsContent = '';

        foreach ($scripts as $scriptFile) {
            $jsPath = realpath($componentDir . '/' . $scriptFile);
            if (!$jsPath || !file_exists($jsPath)) {
                throw new RuntimeException("Script '{$scriptFile}' not found for {$componentClass}");
            }
            $jsContent .= file_get_contents($jsPath) . "\n";
        }

        self::$scriptsContentCache[$cacheKey] = $jsContent;
        return $jsContent;
    }


    private function applyInputBindings(object $component, array $bindings): void
    {
        if (empty($bindings)) return;

        Profiler::start('applyInputBindings');
        Profiler::count('applyInputBindings calls');

        $className = get_class($component);

        // ⚡ CACHE: Mappa input una volta sola per classe
        if (!isset(self::$inputBindingsCache[$className])) {
            $ref = new ReflectionObject($component);
            $inputMap = [];

            foreach ($ref->getProperties() as $prop) {
                $inputAttr = $prop->getAttributes(Input::class)[0] ?? null;
                if ($inputAttr) {
                    $input = $inputAttr->newInstance();
                    $name = $input->alias ?? $prop->getName();
                    $inputMap[$name] = $prop->getName();
                }
            }

            self::$inputBindingsCache[$className] = $inputMap;
        }

        $inputMap = self::$inputBindingsCache[$className];

        // ⚡ Applica bindings velocemente
        foreach ($bindings as $name => $value) {
            if (isset($inputMap[$name])) {
                $component->{$inputMap[$name]} = $value;
            }
        }

        Profiler::end('applyInputBindings');
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
        Profiler::start('extractComponentData::slots');
        $data = [];
        $instance = $proxy->instance;
        $reflection = new ReflectionObject($instance);

        $slotHelpers = $this->generateSlotHelpers($reflection, $instance);
        $data = array_merge($data, $slotHelpers);
        Profiler::end('extractComponentData::slots');

        Profiler::start('extractComponentData::component');
        $componentContext = new class($instance) {
            public function __construct(private $instance) {}
            public function __call(string $name, array $arguments) {
                $reflection = new ReflectionObject($this->instance);
                if ($method = $reflection->getMethod($name)) {
                    return $method->invoke($this->instance, ...$arguments);
                }
                throw new \BadMethodCallException("Method $name not found");
            }
        };
        $data['component'] = $componentContext;
        Profiler::end('extractComponentData::component');

        Profiler::start('extractComponentData::properties');
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $value = $prop->getValue($instance);
            if ($value instanceof SlotContent) {
                $data[$prop->getName()] = $value->html;
            } else {
                $data[$prop->getName()] = $value;
            }
        }
        Profiler::end('extractComponentData::properties');

        Profiler::start('extractComponentData::getters');
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (str_starts_with($method->getName(), 'get') && $method->getNumberOfRequiredParameters() === 0) {
                $propertyName = lcfirst(substr($method->getName(), 3));
                $data[$propertyName] = $method->invoke($instance);
            }
        }
        Profiler::end('extractComponentData::getters');

        Profiler::start('extractComponentData::forms');
        $formTokens = [];
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attrs = $method->getAttributes(FormHandler::class);
            if (!$attrs) continue;
            foreach ($attrs as $attr) {
                $meta = $attr->newInstance();
                $name = $meta->name;
                $methodName = $method->getName();
                $uri = $_SERVER['REQUEST_URI'] ?? '/';
                $path = parse_url($uri, PHP_URL_PATH) ?: '/';
                $base = Router::getInstance()->getBasePath();
                if ($base && str_starts_with($path, $base)) {
                    $path = substr($path, strlen($base));
                    if ($path === '') { $path = '/'; }
                }
                $routePath = ltrim($path, '/');
                $token = FormRegistry::getInstance()->registerHandler($reflection->getName(), $name, $methodName, $routePath);
                $formTokens[$name] = $token;
            }
        }
        if (!empty($formTokens)) {
            $data['__form_tokens'] = $formTokens;
            $data['__component_class'] = $reflection->getName();
        }
        Profiler::end('extractComponentData::forms');

        return $data;
    }

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

            $helpers['has' . ucfirst($baseName)] = $slotContent instanceof SlotContent && !$slotContent->isEmpty();

            if ($slotContent instanceof SlotContent) {
                $slotObjects[$baseName] = $slotContent;
            }
        }

        $helpers['slot'] = function (string $name, array $context = []) use ($slotObjects) {
            Profiler::start("slot::render::{$name}");
            Profiler::count('slot renders');

            $slotContent = $slotObjects[$name] ?? null;
            if (!$slotContent || $slotContent->isEmpty()) {
                Profiler::end("slot::render::{$name}");
                return '';
            }

            $result = $slotContent->render($context);
            Profiler::end("slot::render::{$name}");

            return $result;
        };

        return $helpers;
    }

    private function registerCustomFunctions(): void
    {
        $this->twig->addFunction(new TwigFunction('component', function (string $selector, array $bindings = [], ?string $slotContent = null) {
            return $this->renderComponent($selector, $bindings, $slotContent);
        }, ['is_safe' => ['html']]));

        $this->twig->addFunction(new TwigFunction('slot', function (array $twigContext, string $name, array $slotContext = []) {
            if (isset($twigContext['slot']) && is_callable($twigContext['slot'])) {
                return $twigContext['slot']($name, $slotContext);
            }
            return '';
        }, ['is_safe' => ['html'], 'needs_context' => true]));

        $this->twig->addFunction(new TwigFunction('set_title', function (string $title) {
            $this->pageTitle = $title;
        }));

        $this->twig->addFunction(new TwigFunction('add_meta', function (string $name, string $content) {
            $this->metaTags[] = '<meta name="' . htmlspecialchars($name) . '" content="' . htmlspecialchars($content) . '">';
        }));

        $this->twig->addFunction(new TwigFunction('route_data', [$this, 'getRouteData']));
        $this->twig->addFunction(new TwigFunction('url', [$this, 'generateUrl']));

        $this->twig->addFunction(new TwigFunction('form_action', function (array $context, string $name) {
            $class = $context['__component_class'] ?? null;
            if (!$class) return '#';
            $token = FormRegistry::getInstance()->getTokenFor($class, $name);
            if (!$token) return '#';
            $router = Router::getInstance();
            return $router->url('forms.submit', ['token' => $token]);
        }, ['needs_context' => true]));

        $this->twig->addFunction(
            new TwigFunction(
                'csrf_field',
                function () {
                    $csrf = Injector::inject(CsrfService::class);
                    $token = $csrf->getToken();
                    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($token) . '">';
                },
                ['is_safe' => ['html']]
            )
        );

        $this->twig->addFunction(new TwigFunction('flash', function (string $key, $default = null) {
            $flash = Injector::inject(FlashService::class);
            return $flash->pullValue($key, $default);
        }));
        $this->twig->addFunction(new TwigFunction('peek_flash', function (string $key, $default = null) {
            $flash = Injector::inject(FlashService::class);
            return $flash->getValue($key, $default);
        }));
        $this->twig->addFunction(new TwigFunction('has_flash', function (string $key) {
            $flash = Injector::inject(FlashService::class);
            return $flash->hasKey($key);
        }));

        $this->twig->addFunction(new TwigFunction('form_errors', function (?string $field = null) {
            $flash = Injector::inject(FlashService::class);
            $errors = $flash->getValue('__errors', []);
            if ($field === null) return $errors;
            return $errors[$field] ?? [];
        }));

        $this->twig->addFunction(new TwigFunction('old', function (string $field, $default = '') {
            $flash = Injector::inject(FlashService::class);
            $old = $flash->getValue('__old', []);
            return $old[$field] ?? $default;
        }));
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

    /**
     * Aggiungi questo metodo per pulire le cache in sviluppo:
     */
    public static function clearCaches(): void
    {
        self::$reflectionCache = [];
        self::$inputBindingsCache = [];
        self::$slotPropertiesCache = [];
        self::$stylesContentCache = [];
        self::$scriptsContentCache = [];
    }
}