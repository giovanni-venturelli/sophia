<?php

namespace Sophia\Component;

use Sophia\Injector\Injector;
use Sophia\Router\Router;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionObject;
use ReflectionProperty;
use RuntimeException;
use Twig\Environment;
use Sophia\Form\FormRegistry;
use Sophia\Form\CsrfService;
use Sophia\Form\FlashService;
use Sophia\Form\Attributes\FormHandler;
use Twig\Error\LoaderError;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

class Renderer
{
    private Environment $twig;
    private ComponentRegistry $registry;
    private array $templatePaths = [];

    // 🔥 NUOVO: Accumulatori per risorse globali
    private array $globalStyles = [];
    private array $globalScripts = [];
    private array $globalMetaTags = [];
    private array $componentStyles = [];
    private array $componentScripts = [];
    private array $metaTags = [];
    private string $pageTitle = '';
    private string $language = 'en';

    public function __construct(
        ComponentRegistry $registry,
        string            $templatesPath,
        string            $cachePath = '',
        string            $language = 'en',
        bool              $debug = false
    )
    {
        $this->registry = $registry;
        $this->language = $language;
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


    public function addGlobalStyle(string $css): void
    {
        $styleId = 'globalStyle-' . uniqid();
        $this->globalStyles[$styleId] = $css;;
    }
    public function addGlobalScripts(string $js): void
    {
        $scriptId = 'globalScript-' . uniqid();
        $this->globalScripts[$scriptId] = $js;;
    }
    public function addGlobalMetaTags(array $tags): void
    {
        foreach($tags as $tag) {

            $tagId = 'globalTag-' . uniqid();
            $this->globalMetaTags[$tagId] = $tag;
        }
    }
    /**
     * 🔥 NUOVO: Renderizza con layout HTML completo
     */
    public function renderRoot(string $selector, array $data = [], ?string $slotContent = null): string
    {
        $entry = $this->registry->get($selector);
        if (!$entry) {
            throw new RuntimeException("Component {$selector} not found");
        }

        // Reset accumulatori SOLO se non stiamo componendo una catena (nessuno slot passato)
        if ($slotContent === null) {
            $this->componentStyles = [];
            $this->componentScripts = [];
            $this->metaTags = [];
            $this->pageTitle = '';
        }

        $proxy = new ComponentProxy($entry['class'], $entry['config']);
        $this->injectData($proxy, $data);

        // Inietta eventuale contenuto negli slot del root (per layout routing)
        if ($slotContent !== null) {
            $this->injectSlotContent($proxy->instance, $slotContent);
        }

        // Renderizza il componente (questo raccoglie styles/scripts)
        $bodyContent = $this->renderInstance($proxy);

        Injector::exitScope();

        // 🔥 Costruisci HTML completo
        return $this->buildFullHtml($bodyContent);
    }

    /**
     * 🔥 NUOVO: Costruisce la struttura HTML completa
     */
    private function buildFullHtml(string $bodyContent): string
    {
        $html = '<!DOCTYPE html>' . "\n";
        $html .= '<html lang="' . $this->language . '">' . "\n";
        $html .= '<head>' . "\n";
        $html .= '    <meta charset="UTF-8">' . "\n";
        $html .= '    <meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";

        // Title
        if ($this->pageTitle) {
            $html .= '    <title>' . htmlspecialchars($this->pageTitle) . '</title>' . "\n";
        }

        // Meta tags
        foreach ($this->metaTags as $meta) {
            $html .= '    ' . $meta . "\n";
        }
        foreach ($this->globalMetaTags as $tag) {
            $html .= '   <meta name="' . htmlspecialchars($tag->name) . '" content="' . htmlspecialchars($tag->content) . '"> \n' ;
        }

        // 🔥 Global Styles
        foreach ($this->globalStyles as $styleId => $css) {
            $html .= '    <link id="' . $styleId . '" rel="stylesheet" href="'.$css.'"></style>' . "\n";
        }
        foreach ($this->componentStyles as $styleId => $css) {
            $html .= '    <style id="' . $styleId . '">' . $css . '</style>' . "\n";
        }

        $html .= '</head>' . "\n";
        $html .= '<body>' . "\n";

        // 🔥 Body content (componenti renderizzati)
        $html .= $bodyContent . "\n";

        // 🔥 Global Scripts
        foreach ($this->globalScripts as $scriptId => $js) {
            $html .= '    <script id="' . $scriptId . '" type="text/javascript" src="'.$js.'"></script>' . "\n";
        }
        // 🔥 Global Scripts (alla fine del body)
        foreach ($this->componentScripts as $scriptId => $js) {
            $html .= '    <script id="' . $scriptId . '">' . $js . '</script>' . "\n";
        }

        $html .= '</body>' . "\n";
        $html .= '</html>';

        return $html;
    }

    /**
     * 🔥 AGGIORNATO: supporta slot content
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

        // 🔥 SLOT INJECTION: inietta il contenuto degli slot nel componente
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
     * 🔥 NUOVO: Inietta il contenuto degli slot nel componente
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

            $content = $slots[$slotName] ?? null;

            if ($content) {
                $prop->setAccessible(true);
                $prop->setValue($component, $content);
            }
        }
    }

    /**
     * 🔥 NUOVO: Parse del contenuto per estrarre gli slot
     */
    private function parseSlotContent(string $content): array
    {
        $slots = [];
        // Supporto storico: <slot name="..."> ... </slot>
        if (preg_match_all('/<slot\s+name=["\']([^"\']+)["\']\s*>(.*?)<\/slot>/s', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $slotName = trim($match[1]);
                $slotHtml = trim($match[2]);
                $slots[$slotName] = new SlotContent($slotHtml, $slotName);
            }
        }
        // Nuovo per router layouts: <router-outlet name="..."> ... </router-outlet>
        if (preg_match_all('/<router-outlet\s+name=["\']([^"\']+)["\']\s*>(.*?)<\/router-outlet>/s', $content, $matches2, PREG_SET_ORDER)) {
            foreach ($matches2 as $match) {
                $slotName = trim($match[1]);
                $slotHtml = trim($match[2]);
                $slots[$slotName] = new SlotContent($slotHtml, $slotName);
            }
        }
        return $slots;
    }

    /**
     * 🔥 AGGIORNATO: Renderizza senza iniettare stili inline
     */
    private function renderInstance(ComponentProxy $proxy): string
    {
        Injector::enterScope($proxy);

        $config = $proxy->getConfig();
        $templateName = $this->resolveTemplatePath($proxy->instance::class, $config->template);
        $templateData = $this->extractComponentData($proxy);

        $html = $this->twig->render($templateName, $templateData);

        // 🔥 Raccogli styles globalmente invece di iniettarli inline
        if (!empty($config->styles)) {
            $cssContent = $this->loadStyles($proxy->instance::class, $config->styles);
            $styleId = 'style-' . uniqid();
            $this->componentStyles[$styleId] = $cssContent;
        }

        // 🔥 Raccogli scripts globalmente
        if (!empty($config->scripts)) {
            $jsContent = $this->loadScripts($proxy->instance::class, $config->scripts);
            $scriptId = 'script-' . uniqid();
            $this->componentScripts[$scriptId] = $jsContent;
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
            $jsContent .= file_get_contents($jsPath) . "\n";
        }

        return $jsContent;
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
     * 🔥 AGGIORNATO: Estrae i dati e gestisce automaticamente gli slot
     */
    private function extractComponentData(ComponentProxy $proxy): array
    {
        $data = [];
        $instance = $proxy->instance;
        $reflection = new ReflectionObject($instance);

        // 🔥 AUTO-GENERATE: slot helpers automatici (has* e slot functions)
        $slotHelpers = $this->generateSlotHelpers($reflection, $instance);
        $data = array_merge($data, $slotHelpers);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $value = $prop->getValue($instance);

            // 🔥 Se è SlotContent, estrai l'HTML o stringa vuota
            if ($value instanceof SlotContent) {
                $data[$prop->getName()] = $value->html;
            } else {
                $data[$prop->getName()] = $value;
            }
        }

        // Metodi getter → dati
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (str_starts_with($method->getName(), 'get') &&
                $method->getNumberOfRequiredParameters() === 0) {
                $propertyName = lcfirst(substr($method->getName(), 3));
                $data[$propertyName] = $method->invoke($instance);
            }
        }

        // 🔥 Form handlers: registra i metodi marcati con #[FormHandler('name')]
        $formTokens = [];
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attrs = $method->getAttributes(FormHandler::class);
            if (!$attrs) continue;
            foreach ($attrs as $attr) {
                /** @var FormHandler $meta */
                $meta = $attr->newInstance();
                $name = $meta->name;
                $methodName = $method->getName();
                // Compute current route path similar to Router::getCurrentPath()
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

        return $data;
    }

    /**
     * 🔥 NUOVO: Genera automaticamente gli helper per gli slot
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

            $helpers['has' . ucfirst($baseName)] = $slotContent instanceof SlotContent && !$slotContent->isEmpty();

            if ($slotContent instanceof SlotContent) {
                $slotObjects[$baseName] = $slotContent;
            }
        }

        $helpers['slot'] = function (string $name, array $context = []) use ($slotObjects) {
            $slotContent = $slotObjects[$name] ?? null;
            if (!$slotContent || $slotContent->isEmpty()) return '';
            return $slotContent->render($context);
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

        // 🔥 NUOVO: Funzione per settare il title della pagina
        $this->twig->addFunction(new TwigFunction('set_title', function (string $title) {
            $this->pageTitle = $title;
        }));

        // 🔥 NUOVO: Funzione per aggiungere meta tags
        $this->twig->addFunction(new TwigFunction('add_meta', function (string $name, string $content) {
            $this->metaTags[] = '<meta name="' . htmlspecialchars($name) . '" content="' . htmlspecialchars($content) . '">';
        }));

        $this->twig->addFunction(new TwigFunction('route_data', [$this, 'getRouteData']));
        $this->twig->addFunction(new TwigFunction('url', [$this, 'generateUrl']));

        // Forms: action URL helper
        $this->twig->addFunction(new TwigFunction('form_action', function (array $context, string $name) {
            $class = $context['__component_class'] ?? null;
            if (!$class) return '#';
            $token = FormRegistry::getInstance()->getTokenFor($class, $name);
            if (!$token) return '#';
            $router = Router::getInstance();
            $path = $router->url('forms.submit', ['token' => $token]); // named route
            $base = rtrim($router->getBasePath() ?: '', '/');
            return ($base !== '' ? $base : '') . $path;
        }, ['needs_context' => true]));

        // CSRF hidden input field
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

        // Flash helpers (injectable services)
        // flash(): consume-on-read (pull)
        $this->twig->addFunction(new TwigFunction('flash', function (string $key, $default = null) {
            $flash = Injector::inject(FlashService::class);
            return $flash->pullValue($key, $default);
        }));
        // peek_flash(): read without consuming
        $this->twig->addFunction(new TwigFunction('peek_flash', function (string $key, $default = null) {
            $flash = Injector::inject(FlashService::class);
            return $flash->getValue($key, $default);
        }));
        $this->twig->addFunction(new TwigFunction('has_flash', function (string $key) {
            $flash = Injector::inject(FlashService::class);
            return $flash->hasKey($key);
        }));

        // Validation errors helper
        $this->twig->addFunction(new TwigFunction('form_errors', function (?string $field = null) {
            $flash = Injector::inject(FlashService::class);
            $errors = $flash->getValue('__errors', []);
            if ($field === null) return $errors;
            return $errors[$field] ?? [];
        }));

        // Old input helper
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
}