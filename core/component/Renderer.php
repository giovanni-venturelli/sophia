<?php
namespace App\Component;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;
use ReflectionObject;

class Renderer
{
    private Environment $twig;
    private ComponentRegistry $registry;

    public function __construct(
        ComponentRegistry $registry,
        string $templatesPath = __DIR__ . '/../../pages',
        string $cachePath = __DIR__ . '/../../cache/twig',
        bool $debug = true
    ) {
        $this->registry = $registry;

        // Configura il loader per cercare i template in più directory
        $loader = new FilesystemLoader($templatesPath);

        $this->twig = new Environment($loader, [
            'cache' => $cachePath,
            'auto_reload' => true,
            'debug' => $debug,
            'strict_variables' => true,
        ]);

        // Aggiungi funzioni custom per supportare i componenti
        $this->registerCustomFunctions();
    }

    /**
     * Registra funzioni Twig custom per gestire i componenti
     */
    private function registerCustomFunctions(): void
    {
        // Funzione per renderizzare un componente
        // Uso: {{ component('app-user-card', {name: 'Mario'}) }}
        $this->twig->addFunction(new TwigFunction(
            'component',
            [$this, 'renderComponent'],
            ['is_safe' => ['html']]
        ));

        // Funzione per ottenere dati di route
        // Uso: {{ route_data('title') }}
        $this->twig->addFunction(new TwigFunction(
            'route_data',
            [$this, 'getRouteData']
        ));

        // Funzione per generare URL
        // Uso: {{ url('home', {id: 123}) }}
        $this->twig->addFunction(new TwigFunction(
            'url',
            [$this, 'generateUrl']
        ));
    }

    /**
     * Renderizza un componente root
     *
     * @param string $selector Selector del componente
     * @param array $data Dati da passare al componente (route params, etc.)
     * @return string HTML renderizzato
     */
    public function renderRoot(string $selector, array $data = []): string
    {
        $entry = $this->registry->get($selector);

        if (!$entry) {
            throw new \RuntimeException("Component '$selector' not found");
        }

        // Crea un'istanza del componente
        $instance = new ($entry['class'])();

        // Passa i dati al componente (per route params, query string, etc.)
        $this->injectData($instance, $data);

        return $this->renderInstance($instance, $entry['config']);
    }

    /**
     * Renderizza un'istanza di componente
     *
     * @param object $component Istanza del componente
     * @param Component $config Configurazione del componente
     * @return string HTML renderizzato
     */
    private function renderInstance(object $component, Component $config): string
    {
        if (!$config->template) {
            throw new \RuntimeException("Component '{$config->selector}' has no template");
        }

        // Estrai tutti i dati pubblici dal componente
        $templateData = $this->extractComponentData($component);

        // Aggiungi metadati utili
        $templateData['_component'] = [
            'selector' => $config->selector,
            'meta' => $config->meta
        ];

        // Renderizza con Twig
        return $this->twig->render($config->template, $templateData);
    }

    /**
     * Renderizza un componente child (chiamato da template Twig)
     *
     * @param string $selector Selector del componente
     * @param array $bindings Dati da passare al componente
     * @return string HTML renderizzato
     */
    public function renderComponent(string $selector, array $bindings = []): string
    {
        $entry = $this->registry->get($selector);

        if (!$entry) {
            return "<!-- Component '$selector' not found -->";
        }

        // Crea istanza del componente
        $instance = new ($entry['class'])();

        // Applica i bindings alle proprietà @Input del componente
        $this->applyInputBindings($instance, $bindings);

        return $this->renderInstance($instance, $entry['config']);
    }

    /**
     * Applica i binding alle proprietà @Input del componente
     *
     * @param object $component Istanza del componente
     * @param array $bindings Array associativo nome => valore
     */
    private function applyInputBindings(object $component, array $bindings): void
    {
        $ref = new ReflectionObject($component);

        foreach ($ref->getProperties() as $prop) {
            $inputAttr = $prop->getAttributes(Input::class)[0] ?? null;

            if (!$inputAttr) {
                continue;
            }

            /** @var Input $input */
            $input = $inputAttr->newInstance();
            $name = $input->alias ?? $prop->getName();

            if (!array_key_exists($name, $bindings)) {
                continue;
            }

            $prop->setAccessible(true);
            $prop->setValue($component, $bindings[$name]);
        }
    }

    /**
     * Inietta dati nel componente (per route params, etc.)
     *
     * @param object $component Istanza del componente
     * @param array $data Dati da iniettare
     */
    private function injectData(object $component, array $data): void
    {
        foreach ($data as $key => $value) {
            if (property_exists($component, $key)) {
                $component->$key = $value;
            }
        }
    }

    /**
     * Estrae tutti i dati pubblici da un componente per passarli a Twig
     *
     * @param object $component Istanza del componente
     * @return array Array associativo di dati
     */
    private function extractComponentData(object $component): array
    {
        $data = [];
        $reflection = new ReflectionObject($component);

        // Estrai proprietà pubbliche
        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $name = $prop->getName();
            $data[$name] = $prop->getValue($component);
        }

        // Estrai metodi pubblici getter (getNomeMetodo)
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (str_starts_with($method->getName(), 'get') && $method->getNumberOfParameters() === 0) {
                $propertyName = lcfirst(substr($method->getName(), 3));
                $data[$propertyName] = $method->invoke($component);
            }
        }

        return $data;
    }

    /**
     * Helper per ottenere dati di route (usato nei template)
     *
     * @param string|null $key Chiave specifica o null per tutti i dati
     * @return mixed
     */
    public function getRouteData(?string $key = null): mixed
    {
        $router = \App\Router\Router::getInstance();
        return $router->getCurrentRouteData($key);
    }

    /**
     * Helper per generare URL (usato nei template)
     *
     * @param string $name Nome della route
     * @param array $params Parametri per la route
     * @return string URL generato
     */
    public function generateUrl(string $name, array $params = []): string
    {
        try {
            $router = \App\Router\Router::getInstance();
            return $router->url($name, $params);
        } catch (\Exception $e) {
            return '#';
        }
    }

    /**
     * Ottiene l'ambiente Twig (per estensioni custom)
     *
     * @return Environment
     */
    public function getTwig(): Environment
    {
        return $this->twig;
    }

    /**
     * Aggiunge un path per i template
     *
     * @param string $path Path da aggiungere
     * @param string|null $namespace Namespace opzionale
     */
    public function addTemplatePath(string $path, ?string $namespace = null): void
    {
        $loader = $this->twig->getLoader();

        if ($loader instanceof FilesystemLoader) {
            if ($namespace) {
                $loader->addPath($path, $namespace);
            } else {
                $loader->addPath($path);
            }
        }
    }
}