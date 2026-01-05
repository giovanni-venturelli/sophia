<?php

namespace Sophia\Component;

use Sophia\Cache\FileCache;
use Sophia\Injector\Inject;
use Sophia\Injector\Injectable;
use Sophia\Injector\Injector;
use Sophia\Router\Router;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use RuntimeException;
use Twig\Environment;
use Sophia\Form\FormRegistry;
use Sophia\Form\CsrfService;
use Sophia\Form\FlashService;
use Sophia\Form\Attributes\FormHandler;
use Twig\Error\LoaderError;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

#[Injectable(providedIn: 'root')]
class Renderer
{
    private Environment $twig;
    private ComponentRegistry $registry;
    private array $templatePaths = [];

    // Accumulatori per risorse globali
    private array $globalStyles = [];
    private array $globalScripts = [];
    private array $globalMetaTags = [];
    private array $componentStyles = [];
    private array $componentScripts = [];
    private array $metaTags = [];
    private string $pageTitle = '';
    private string $language = 'en';

    private array $profilingData = [];
    private ?FileCache $cache = null;
    private bool $enableCache = true;
    private string $currentUserRole = 'guest';

    // 🔥 NUOVO: Cache ottimizzata per metadati
    private static array $metadataCache = [];
    private static array $templatePathCache = [];
    private static array $componentInstanceCache = [];

    public function setRegistry(ComponentRegistry $registry): void
    {
        $this->registry = $registry;
    }

    public function setUserRole(string $role): void
    {
        $this->currentUserRole = $role;
    }

    public function resetUserRole(): void
    {
        $this->currentUserRole = 'guest';
    }

    public function configure(string $templatesPath, string $cachePath = '', string $language = 'en', bool $debug = false): void
    {
        $this->language = $language;
        $this->templatePaths = [];
        $this->initTwig($cachePath, $debug);

        if ($cachePath) {
            $componentCachePath = $cachePath . '/components';
            $this->cache = new FileCache($componentCachePath);

            // Pulisci cache scaduta occasionalmente
            if (rand(1, 100) === 1) {
                $this->cache->cleanExpired();
            }
        }

        if ($templatesPath !== '') {
            $this->addTemplatePath($templatesPath);
        }

        $this->registerCustomFunctions();
    }

    private function initTwig(string $cachePath, bool $debug): void
    {
        $loader = new FilesystemLoader();
        $cacheConfig = false;

        if ($cachePath !== '') {
            if (!is_dir($cachePath)) {
                if (!mkdir($cachePath, 0755, true) && !is_dir($cachePath)) {
                    throw new RuntimeException("Cannot create cache directory: {$cachePath}");
                }
            }

            if (!is_writable($cachePath)) {
                throw new RuntimeException("Cache directory is not writable: {$cachePath}");
            }

            $cacheConfig = realpath($cachePath);
        }

        $this->twig = new Environment($loader, [
            'cache' => $cacheConfig,
            'auto_reload' => $debug,
            'debug' => $debug,
            'strict_variables' => true,
        ]);
    }

    /**
     * 🔥 NUOVO: Cache per i metadati del componente
     */
    private function warmupMetadata(string $className): array
    {
        if (isset(self::$metadataCache[$className])) {
            return self::$metadataCache[$className];
        }

        try {
            $reflection = new ReflectionClass($className);
        } catch (ReflectionException $e) {
            throw new RuntimeException("Cannot reflect class {$className}: " . $e->getMessage());
        }

        $meta = [
            'inputs' => [],        // Mappa alias -> nome proprietà
            'slots' => [],         // Nomi proprietà slot
            'getters' => [],       // Mappa nomeProperty -> nomeMetodo
            'public_props' => [],  // Nomi proprietà pubbliche
            'inject_props' => [],  // Proprietà con #[Inject]
            'hasOnInit' => false,
            'form_handlers' => [], // Metodi con #[FormHandler]
            'component_attr' => null
        ];

        // 1. Attributo Component principale
        $componentAttrs = $reflection->getAttributes(Component::class);
        if (!empty($componentAttrs)) {
            $meta['component_attr'] = $componentAttrs[0]->newInstance();
        }

        // 2. Analisi proprietà
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
            $injectAttrs = $prop->getAttributes(Inject::class);
            if (!empty($injectAttrs)) {
                $meta['inject_props'][] = [
                    'name' => $propName,
                    'type' => $prop->getType()?->getName()
                ];
            }

            // Slot
            $slotAttrs = $prop->getAttributes(Slot::class);
            if (!empty($slotAttrs)) {
                $meta['slots'][] = $propName;
            }

            // Proprietà pubbliche
            if ($prop->isPublic()) {
                $meta['public_props'][] = $propName;
            }
        }

        // 3. Analisi metodi
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $methodName = $method->getName();

            // onInit
            if ($methodName === 'onInit') {
                $meta['hasOnInit'] = true;
            }

            // Getter methods (getSomething())
            if (str_starts_with($methodName, 'get') &&
                $method->getNumberOfRequiredParameters() === 0) {
                $propertyName = lcfirst(substr($methodName, 3));
                $meta['getters'][$propertyName] = $methodName;
            }

            // Form handlers
            $formHandlerAttrs = $method->getAttributes(FormHandler::class);
            if (!empty($formHandlerAttrs)) {
                foreach ($formHandlerAttrs as $attr) {
                    $meta['form_handlers'][] = [
                        'method' => $methodName,
                        'meta' => $attr->newInstance()
                    ];
                }
            }
        }

        self::$metadataCache[$className] = $meta;
        return $meta;
    }

    /**
     * 🔥 NUOVO: Crea istanza componente ottimizzata
     */
    private function createComponentInstance(string $className, Component $config, ?ComponentProxy $parentScope = null): object
    {
        $cacheKey = $className . ($parentScope ? '_' . $parentScope->getId() : '');

        // Usa cache per istanze root
        if ($parentScope === null && isset(self::$componentInstanceCache[$cacheKey])) {
            return self::$componentInstanceCache[$cacheKey];
        }

        $meta = $this->warmupMetadata($className);

        // 1. Gestione providers nello scope
        if ($parentScope) {
            Injector::enterScope($parentScope);
        }

        // 2. Registra providers se presenti
        if (!empty($config->providers)) {
            foreach ($config->providers as $providerClass) {
                Injector::inject($providerClass, $parentScope);
            }
        }

        // 3. Crea istanza con DI ottimizzata
        $instance = $this->createInstanceWithCachedDI($className, $meta, $parentScope);

        // 4. Chiama onInit se presente
        if ($meta['hasOnInit'] && method_exists($instance, 'onInit')) {
            $instance->onInit();
        }

        // 5. Cache per istanze root
        if ($parentScope === null) {
            self::$componentInstanceCache[$cacheKey] = $instance;
        }

        return $instance;
    }

    /**
     * 🔥 NUOVO: Crea istanza con DI usando cache metadati
     */
    private function createInstanceWithCachedDI(string $className, array $meta, ?ComponentProxy $scope): object
    {
        // Se non ci sono dipendenze da injectare, crea direttamente
        if (empty($meta['inject_props'])) {
            $instance = new $className();

            // Se c'è un costruttore con dipendenze, usa Injector
            $reflection = new ReflectionClass($className);
            $constructor = $reflection->getConstructor();
            if ($constructor && $constructor->getNumberOfRequiredParameters() > 0) {
                $instance = Injector::inject($className, $scope);
            }

            return $instance;
        }

        // Altrimenti usa Reflection ottimizzata
        $instance = new $className();

        foreach ($meta['inject_props'] as $injectProp) {
            $type = $injectProp['type'];
            if ($type && class_exists($type)) {
                $service = Injector::inject($type, $scope);
                $instance->{$injectProp['name']} = $service;
            }
        }

        return $instance;
    }

    /**
     * 🔥 NUOVO: Applica binding inputs ottimizzato
     */
    private function applyInputBindingsOptimized(object $instance, array $bindings, array $meta): void
    {
        foreach ($bindings as $alias => $value) {
            if (isset($meta['inputs'][$alias])) {
                $propName = $meta['inputs'][$alias];
                $instance->$propName = $value;
            }
        }
    }

    /**
     * 🔥 NUOVO: Inietta slot ottimizzato
     */
    private function injectSlotContentOptimized(object $instance, string $slotContent, array $slotNames): void
    {
        if (empty($slotNames) || trim($slotContent) === '') {
            return;
        }

        // Se non ci sono tag <slot, assegna tutto allo slot 'content' se esiste
        if (!str_contains($slotContent, '<slot') && !str_contains($slotContent, '<router-outlet')) {
            if (in_array('content', $slotNames)) {
                $instance->content = new SlotContent(trim($slotContent), 'content');
            }
            return;
        }

        // Parsing ottimizzato per slot
        $slots = $this->parseSlotContentOptimized($slotContent);

        foreach ($slotNames as $slotName) {
            if (isset($slots[$slotName])) {
                $instance->$slotName = new SlotContent($slots[$slotName], $slotName);
            }
        }
    }

    /**
     * 🔥 NUOVO: Parse slot ottimizzato
     */
    private function parseSlotContentOptimized(string $content): array
    {
        $slots = [];

        // Pattern unificato per slot e router-outlet
        $pattern = '/<(?:slot|router-outlet)\s+name=["\']([^"\']+)["\']\s*>(.*?)<\/(?:slot|router-outlet)>/s';

        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $slotName = trim($match[1]);
                $slotHtml = trim($match[2]);
                $slots[$slotName] = $slotHtml;
            }
        }

        return $slots;
    }

    /**
     * 🔥 NUOVO: Extract context ottimizzato
     */
    private function extractComponentContext(object $instance, array $meta): array
    {
        $context = [];

        // 1. Proprietà pubbliche
        foreach ($meta['public_props'] as $propName) {
            $context[$propName] = $instance->$propName;
        }

        // 2. Getters
        foreach ($meta['getters'] as $propName => $methodName) {
            $context[$propName] = $instance->$methodName();
        }

        // 3. Gestione form handlers
        if (!empty($meta['form_handlers'])) {
            $formTokens = [];
            $className = get_class($instance);
            $routePath = $this->getCurrentRoutePath();

            foreach ($meta['form_handlers'] as $handler) {
                $metaObj = $handler['meta'];
                $methodName = $handler['method'];

                $token = FormRegistry::getInstance()->registerHandler(
                    $className,
                    $metaObj->name,
                    $methodName,
                    $routePath
                );
                $formTokens[$metaObj->name] = $token;
            }

            $context['__form_tokens'] = $formTokens;
            $context['__component_class'] = $className;
        }

        // 4. Slot helpers
        $slotHelpers = $this->generateSlotHelpers($instance, $meta['slots']);
        $context = array_merge($context, $slotHelpers);

        // 5. Riferimento all'istanza (per compatibilità)
        $context['component'] = $instance;

        return $context;
    }

    private function generateSlotHelpers(object $instance, array $slotNames): array
    {
        $helpers = [];
        $slotObjects = [];

        foreach ($slotNames as $slotName) {
            if (property_exists($instance, $slotName)) {
                $slotContent = $instance->$slotName;
                $baseName = str_ends_with($slotName, 'Slot') ?
                    substr($slotName, 0, -4) : $slotName;

                $helpers['has' . ucfirst($baseName)] =
                    $slotContent instanceof SlotContent && !$slotContent->isEmpty();

                if ($slotContent instanceof SlotContent) {
                    $slotObjects[$baseName] = $slotContent;
                }
            }
        }

        $helpers['slot'] = function(string $name, array $context = []) use ($slotObjects) {
            $slotContent = $slotObjects[$name] ?? null;
            if (!$slotContent || $slotContent->isEmpty()) {
                return '';
            }
            return $slotContent->render($context);
        };

        return $helpers;
    }

    public function renderRoot(string $selector, array $data = [], ?string $slotContent = null): string
    {
        $entry = $this->registry->get($selector);
        if (!$entry) {
            throw new RuntimeException("Component {$selector} not found");
        }

        // Reset accumulatori
        if ($slotContent === null) {
            $this->componentStyles = [];
            $this->componentScripts = [];
            $this->metaTags = [];
            $this->pageTitle = '';
        }

        // 🔥 NUOVO: Crea istanza ottimizzata
        $meta = $this->warmupMetadata($entry['class']);
        $instance = $this->createComponentInstance($entry['class'], $entry['config']);

        // Applica data
        foreach ($data as $key => $value) {
            if (property_exists($instance, $key)) {
                $instance->$key = $value;
            }
        }

        // Inietta slot
        if ($slotContent !== null) {
            $this->injectSlotContentOptimized($instance, $slotContent, $meta['slots']);
        }

        // Renderizza
        $bodyContent = $this->renderInstanceOptimized($instance, $entry['config']);

        // 🔥 Costruisci HTML completo
        $html = $this->buildFullHtml($bodyContent);

        $csrfService = Injector::inject(CsrfService::class);
        $html = str_replace('%%SOPHIA_CSRF_TOKEN%%', $csrfService->getToken(), $html);

        if ($_ENV['DEBUG'] ?? false) {
            $html .= "\n<!-- PROFILING:\n";
            foreach ($this->profilingData as $item) {
                $html .= sprintf("%s: %.4fs\n", $item['template'], $item['time']);
            }
            $html .= "-->";
        }
        return $html;
    }

    public function renderComponent(string $selector, array $bindings = [], ?string $slotContent = null): string
    {
        $entry = $this->registry->get($selector);
        if (!$entry) {
            return "<!-- Component {$selector} not found -->";
        }

        // 🔥 NUOVO: Ottimizzazione con cache metadata
        $className = $entry['class'];
        $meta = $this->warmupMetadata($className);

        // Traccia styles/scripts prima del rendering
        $stylesBefore = array_keys($this->componentStyles);
        $scriptsBefore = array_keys($this->componentScripts);

        // Crea istanza ottimizzata
        $parentScope = Injector::getCurrentScope();
        $instance = $this->createComponentInstance($className, $entry['config'], $parentScope);

        // Applica bindings ottimizzato
        $this->applyInputBindingsOptimized($instance, $bindings, $meta);

        // Inietta slot
        if ($slotContent !== null) {
            $this->injectSlotContentOptimized($instance, $slotContent, $meta['slots']);
        }

        // Renderizza
        $html = $this->renderInstanceOptimized($instance, $entry['config']);

        // Identifica nuovi styles/scripts
        $stylesAfter = array_keys($this->componentStyles);
        $scriptsAfter = array_keys($this->componentScripts);

        $newStyleKeys = array_diff($stylesAfter, $stylesBefore);
        $newScriptKeys = array_diff($scriptsAfter, $scriptsBefore);

        // Salva per eventuale caching
        $componentStyles = [];
        foreach ($newStyleKeys as $key) {
            $componentStyles[$key] = $this->componentStyles[$key];
        }

        $componentScripts = [];
        foreach ($newScriptKeys as $key) {
            $componentScripts[$key] = $this->componentScripts[$key];
        }

        return $html;
    }

    /**
     * 🔥 NUOVO: Render instance ottimizzata
     */
    private function renderInstanceOptimized(object $instance, Component $config): string
    {
        $start = microtime(true);
        $className = get_class($instance);

        // Risolvi template con cache
        $templateKey = $className . '::' . $config->template;
        if (!isset(self::$templatePathCache[$templateKey])) {
            self::$templatePathCache[$templateKey] = $this->resolveTemplatePath($className, $config->template);
        }

        $templateName = self::$templatePathCache[$templateKey];

        // Estrai context ottimizzato
        $meta = $this->warmupMetadata($className);
        $templateData = $this->extractComponentContext($instance, $meta);

        // Renderizza con Twig
        $html = $this->twig->render($templateName, $templateData);

        // Carica styles e scripts
        $this->loadComponentAssets($className, $config);

        $this->profilingData[] = [
            'template' => $templateName,
            'time' => microtime(true) - $start
        ];

        return $html;
    }

    private function loadComponentAssets(string $className, Component $config): void
    {
        // Styles
        if (!empty($config->styles)) {
            $cssContent = $this->loadStyles($className, $config->styles);
            $styleId = 'style-' . md5($className);
            if (!isset($this->componentStyles[$styleId])) {
                $this->componentStyles[$styleId] = $cssContent;
            }
        }

        // Scripts
        if (!empty($config->scripts)) {
            $jsContent = $this->loadScripts($className, $config->scripts);
            $scriptId = 'script-' . md5($className . implode(',', $config->scripts));
            $this->componentScripts[$scriptId] = $jsContent;
        }
    }

    // 🔥 METODI ESISTENTI MANTENUTI (con piccole ottimizzazioni)

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
        if (str_starts_with($js, 'http')) {
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
        foreach ($tags as $tag) {
            $tagId = 'globalTag-' . uniqid();
            $this->globalMetaTags[$tagId] = $tag;
        }
    }



    private function buildFullHtml(string $bodyContent): string
    {
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
            $html .= '   <meta name="' . htmlspecialchars($tag->name) . '" content="' . htmlspecialchars($tag->content) . '"> \n';
        }

        foreach ($this->globalStyles as $styleId => $css) {
            $html .= '    <link id="' . $styleId . '" rel="stylesheet" href="' . $css . '"></style>' . "\n";
        }

        foreach ($this->componentStyles as $styleId => $css) {
            $html .= '    <style id="' . $styleId . '">' . $css . '</style>' . "\n";
        }

        $html .= '</head>' . "\n";
        $html .= '<body>' . "\n";
        $html .= $bodyContent . "\n";

        foreach ($this->globalScripts as $scriptId => $js) {
            $html .= '    <script id="' . $scriptId . '" type="text/javascript" src="' . $js . '"></script>' . "\n";
        }

        foreach ($this->componentScripts as $scriptId => $js) {
            $html .= '    <script id="' . $scriptId . '">' . $js . '</script>' . "\n";
        }

        $html .= '</body>' . "\n";
        $html .= '</html>';

        return $html;
    }

    public function setCacheEnabled(bool $enabled): void
    {
        $this->enableCache = $enabled;
    }

    public function clearComponentCache(): void
    {
        $this->cache?->clear();
    }

    public function clearComponentCacheFor(string $selector): void
    {
        $this->cache?->clear();
    }

    public function getCacheStats(): array
    {
        if ($this->cache) {
            return $this->cache->getStats();
        }
        return ['enabled' => false];
    }

    private function resolveTemplatePath(string $componentClass, string $template): string
    {
        // Cache per percorsi template
        $cacheKey = "template_path_{$componentClass}_{$template}";
        if (isset(self::$templatePathCache[$cacheKey])) {
            return self::$templatePathCache[$cacheKey];
        }

        try {
            $reflection = new ReflectionClass($componentClass);
            $componentDir = dirname($reflection->getFileName());

            $fullPath = realpath($componentDir . '/' . $template);
            if ($fullPath && file_exists($fullPath)) {
                $this->addTemplatePath(dirname($fullPath));
                $result = basename($fullPath);
                self::$templatePathCache[$cacheKey] = $result;
                return $result;
            }

            $templateName = basename($template);
            foreach ($this->templatePaths as $basePath) {
                $fullPath = realpath($basePath . '/' . $templateName);
                if ($fullPath && file_exists($fullPath)) {
                    $result = $templateName;
                    self::$templatePathCache[$cacheKey] = $result;
                    return $result;
                }
            }

            throw new RuntimeException("Template '{$template}' not found for {$componentClass}");
        } catch (ReflectionException $e) {
            throw new RuntimeException("Cannot resolve template path for {$componentClass}: " . $e->getMessage());
        }
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
            $jsContent .= file_get_contents($jsPath) . "\n";
        }

        return $jsContent;
    }

    private function getCurrentRoutePath(): string
    {
        static $cachedRoutePath = null;

        if ($cachedRoutePath !== null) {
            return $cachedRoutePath;
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $base = Router::getInstance()->getBasePath();

        if ($base && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
            if ($path === '') {
                $path = '/';
            }
        }

        $cachedRoutePath = ltrim($path, '/');
        return $cachedRoutePath;
    }

    private function registerCustomFunctions(): void
    {
        $this->twig->addFunction(new TwigFunction('component',
            fn(string $selector, array $bindings = [], ?string $slotContent = null) =>
            $this->renderComponent($selector, $bindings, $slotContent),
            ['is_safe' => ['html']]
        ));

        $this->twig->addFunction(new TwigFunction('slot',
            function (array $twigContext, string $name, array $slotContext = []) {
                if (isset($twigContext['slot']) && is_callable($twigContext['slot'])) {
                    return $twigContext['slot']($name, $slotContext);
                }
                return '';
            },
            ['is_safe' => ['html'], 'needs_context' => true]
        ));

        $this->twig->addFunction(new TwigFunction('set_title',
            fn(string $title) => $this->pageTitle = $title
        ));

        $this->twig->addFunction(new TwigFunction('add_meta',
            function (string $name, string $content) {
                $this->metaTags[] = '<meta name="' . htmlspecialchars($name) . '" content="' . htmlspecialchars($content) . '">';
            }
        ));

        $this->twig->addFunction(new TwigFunction('route_data', [$this, 'getRouteData']));
        $this->twig->addFunction(new TwigFunction('url', [$this, 'generateUrl']));

        $this->twig->addFunction(new TwigFunction('form_action',
            function (array $context, string $name) {
                $class = $context['__component_class'] ?? null;
                if (!$class) return '#';
                $token = FormRegistry::getInstance()->getTokenFor($class, $name);
                if (!$token) return '#';
                $router = Router::getInstance();
                return $router->url('forms.submit', ['token' => $token]);
            },
            ['needs_context' => true]
        ));

        $this->twig->addFunction(new TwigFunction('csrf_field',
            fn() => '<input type="hidden" name="_csrf" value="%%SOPHIA_CSRF_TOKEN%%">',
            ['is_safe' => ['html']]
        ));

        $this->twig->addFunction(new TwigFunction('flash',
            fn(string $key, $default = null) =>
            Injector::inject(FlashService::class)->pullValue($key, $default)
        ));

        $this->twig->addFunction(new TwigFunction('peek_flash',
            fn(string $key, $default = null) =>
            Injector::inject(FlashService::class)->getValue($key, $default)
        ));

        $this->twig->addFunction(new TwigFunction('has_flash',
            fn(string $key) => Injector::inject(FlashService::class)->hasKey($key)
        ));

        $this->twig->addFunction(new TwigFunction('form_errors',
            function (?string $field = null) {
                $flash = Injector::inject(FlashService::class);
                $errors = $flash->getValue('__errors', []);
                if ($field === null) return $errors;
                return $errors[$field] ?? [];
            }
        ));

        $this->twig->addFunction(new TwigFunction('old',
            fn(string $field, $default = '') =>
                Injector::inject(FlashService::class)->getValue('__old', [])[$field] ?? $default
        ));
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
     * 🔥 NUOVO: Pulisce tutte le cache interne
     */
    public static function clearAllCaches(): void
    {
        self::$metadataCache = [];
        self::$templatePathCache = [];
        self::$componentInstanceCache = [];
    }
}