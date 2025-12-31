Components — View + Logic building blocks
=======================================

This folder contains the component system of the framework. Components are simple PHP classes
annotated with the `#[Component]` attribute and rendered with Twig templates. They can:
- expose public properties (or zero-arg getters) to the view
- receive inputs from parent components
- inject services via the DI system
- declare local providers to create scoped services for their subtree
- include CSS files at render time

The renderer handles lifecycle, data extraction, template resolution, child component rendering,
and a small set of Twig helpers.


Quick navigation
----------------
- [What is a Component](#what-is-a-component)
- [Quick start (minimal example)](#quick-start-minimal-example)
- [Anatomy of #[Component]](#anatomy-of-component)
- [Lifecycle and data flow](#lifecycle-and-data-flow)
- [Templates and data binding](#templates-and-data-binding)
- [Inputs (parent → child bindings)](#inputs-parent--child-bindings)
- [Nesting components in Twig](#nesting-components-in-twig)
- [Dependency Injection in components (`#[Inject]` and `providers`)](#dependency-injection-in-components-inject-and-providers)
- [Styles](#styles)
- [Registration and rendering (root rendering)](#registration-and-rendering-root-rendering)
- [Router/Twig helpers: `route_data()`, `url()`](#routertwig-helpers-route_data-url)
- [Troubleshooting](#troubleshooting)
- [Full example (parent + child + DI)](#full-example-parent--child--di)


What is a Component
-------------------
A Component is a class with a `#[Component]` attribute. It binds a PHP class to a Twig template
and optional metadata like `imports`, `styles`, and DI `providers`. Components are instantiated
by the framework through a `ComponentProxy`, which applies property injection (for `#[Inject]`),
then calls `onInit()` if present, and finally renders the Twig template with the component's
public state.


Quick start (minimal example)
-----------------------------
PHP class:
```php
<?php
namespace App\Pages\Hello;

use Sophia\Component\Component;

#[Component(
    selector: 'app-hello',
    template: 'hello.html.twig'
)]
class HelloComponent
{
    public string $name = 'World';
}
```
Twig template `hello.html.twig` (placed next to `HelloComponent.php` or in a configured templates path):
```twig
<h1>Hello, {{ name }}!</h1>
```
Rendered (root) via the framework's `Renderer`:
```php
$registry = \Sophia\Component\ComponentRegistry::getInstance();
$registry->register(App\Pages\Hello\HelloComponent::class);

$renderer = new \Sophia\Component\Renderer($registry, __DIR__ . '/pages');
echo $renderer->renderRoot('app-hello');
```


Anatomy of `#[Component]`
-------------------------
Attribute is defined in `core/component/Component.php`:
```php
#[Attribute(Attribute::TARGET_CLASS)]
class Component {
    public function __construct(
        public string $selector,
        public string $template,
        public array $imports = [],
        public array $styles = [],
        public array $providers = [],  // Angular-style providers
        public array $meta = []
    ) {}
}
```
- `selector`: unique name used to reference the component (e.g., `'app-hello'`).
- `template`: Twig file name. Can be a basename found in configured template paths, or a relative path
  next to the component's PHP file (the renderer auto-resolves both cases).
- `imports`: array of other component classes to be auto-registered with this component.
- `styles`: array of CSS file paths relative to the component's PHP file; they will be concatenated and
  injected into the page inside a `<style>` tag on render.
- `providers`: DI providers (service classes) available to this component and its subtree (scoped singletons).
- `meta`: arbitrary metadata bag (optional, free-form).


Lifecycle and data flow
-----------------------
Component creation is orchestrated by `ComponentProxy`:
1) The proxy enters the DI scope for this component.
2) It pre-warms providers by resolving each class listed in `providers` once.
3) It creates the component instance.
4) It performs property injection for fields annotated with `#[Inject]`.
5) If the instance has `onInit()`, it is called now (so injected services are ready).
6) The `Renderer` gathers template data from the instance and renders the Twig template.

Data exposed to templates comes from:
- All public properties of the component
- All public zero-argument getters: methods named `getXyz()` become variables `xyz` in Twig

You can also pass initial data to a root component via `Renderer::renderRoot($selector, $data)`;
any keys that match public properties will be assigned before rendering.


Templates and data binding
--------------------------
- Templates are resolved either relative to the component's file directory or from the renderer's
  configured template paths.
- The `Renderer` sets Twig to strict variables mode, so referencing a missing variable will raise an error.
- Public properties and zero-arg getters become the template context.

Twig example:
```twig
<section>
  <h2>{{ title }}</h2>
  <p>Total: {{ items|length }}</p>

  {% if items is not empty %}
    <ul>
      {% for item in items %}
        <li>{{ item.name }}</li>
      {% endfor %}
    </ul>
  {% else %}
    <em>No items.</em>
  {% endif %}
</section>
```


Inputs (parent → child bindings)
--------------------------------
Use the `#[Input]` attribute on child component properties to declare bindable inputs. Parents can
supply values through the `component()` Twig function when rendering a child.

Child component:
```php
use Sophia\Component\Component;
use Sophia\Component\Input;

#[Component(selector: 'app-child', template: 'child.html.twig')]
class ChildComponent
{
    #[Input] public string $title;          // bound by name "title"
    #[Input(alias: 'count')] public int $n; // bound by alias "count"
}
```
Parent template:
```twig
{{ component('app-child', { title: 'Hello', count: 3 }) }}
```
Binding rules:
- Only properties with `#[Input]` participate in bindings.
- If `alias` is provided, the parent must use the alias key; otherwise the property name.
- Bindings are applied before the child is rendered and before its `onInit()` runs.


Nesting components in Twig
--------------------------
The renderer registers a Twig function `component(selector, bindings = {})`:
```twig
{{ component('app-card', { title: 'Card', items: items }) }}
```
This creates a child component under the current component's DI scope, applies input bindings,
renders it, and returns its HTML. If a provider is not found in the child, resolution walks up
through parent scopes and then to root singletons (see Injector docs).


Dependency Injection in components
----------------------------------
- Use `#[Inject]` on typed component properties to receive services.
- List non-root services in your component's `providers` array to make them available in this subtree.
- Root singletons (`#[Injectable(providedIn: 'root')]`) are globally available.

Example:
```php
use Sophia\Component\Component;
use Sophia\Injector\Inject;
use Sophia\Injector\Injectable;

#[Injectable(providedIn: 'root')]
class Logger { public function info(string $m): void {} }

#[Injectable]
class UserService { public function __construct(private Logger $log) {} }

#[Component(
    selector: 'app-users',
    template: 'users.html.twig',
    providers: [UserService::class]
)]
class UsersComponent
{
    #[Inject] private UserService $users; // resolved from providers
    public array $active = [];

    public function onInit(): void
    {
        $this->active = $this->users->getActive();
    }
}
```
More details in `core/injector/README.md`.


Styles
------
Provide CSS files (relative to the component's PHP file) in `styles: [...]`. The renderer concatenates
these files and injects them as a single `<style>` tag into the output. If a `<head>` tag exists,
styles are inserted before it closes; otherwise they are prepended to the HTML.

```php
#[Component(
  selector: 'app-panel',
  template: 'panel.html.twig',
  styles: ['panel.css', 'theme.css']
)]
```


Registration and rendering (root rendering)
------------------------------------------
Register components with the `ComponentRegistry` and render with `Renderer`.

```php
use Sophia\Component\ComponentRegistry;
use Sophia\Component\Renderer;
use Sophia\Injector\Injector;

$registry = ComponentRegistry::getInstance();
$registry->register(App\Pages\Home\HomeComponent::class); // auto-registers imports

/** @var Renderer $renderer */
$renderer = Injector::inject(Renderer::class);
$renderer->setRegistry($registry);
$renderer->configure(__DIR__ . '/pages', __DIR__ . '/cache/twig', 'en', true);

// Pass initial data to public properties of the root component
$html = $renderer->renderRoot('app-home', [ 'title' => 'Welcome' ]);
```
Notes:
- `Renderer` is a root injectable (`#[Injectable(providedIn: 'root')]`). Use `Injector::inject()` to obtain it.
- `register()` throws if the class lacks `#[Component]`.
- `imports` listed in a component are auto-registered recursively.
- You can call `lazyRegister(Class::class)` to register by class and receive its selector.


Router/Twig helpers
-------------------
The renderer exposes a set of Twig helpers out of the box:
- `component(selector, bindings = {}, slotContent?)` → render a child component
- `slot(_context, name, slotContext = {})` → render named slot content provided by a parent
- `set_title(title)` → set the page title for the current render
- `add_meta(name, content)` → add a `<meta>` tag
- `route_data(key?: string)` → get current route's data or a specific value
- `url(name, params = {})` → generate a URL by route name
- `form_action(name)` → build POST action URL for a named form in the current component
- `csrf_field()` → hidden input with CSRF token
- `flash(key)`, `peek_flash(key)`, `has_flash(key)` → flash message helpers
- `form_errors(field?)` → validation errors (all or per field)
- `old(field, default?)` → sticky old input values

Examples:
```twig
<a href="{{ url('post.show', { id: post.id }) }}">Read more</a>
<p>Category: {{ route_data('category') }}</p>

<form method="post" action="{{ form_action('send') }}">
  {{ csrf_field()|raw }}
  {% if has_flash('error') %}<div class="alert">{{ flash('error') }}</div>{% endif %}
</form>
```


Troubleshooting
---------------
- Template not found: ensure the file exists next to the component class or in a path added to the renderer.
- Undefined Twig variable: templates run with strict variables; expose data via public properties or getters.
- Input not applied: check you decorated the property with `#[Input]` and used the right key/alias in the parent.
- Service not injected: for components use `#[Inject]` on a typed property; for services ensure they are either
  root-provided or present in the nearest `providers`.
- Styles error: a file listed in `styles` must exist relative to the component's PHP file.


Full example (parent + child + DI)
----------------------------------
Services:
```php
use Sophia\Injector\Injectable;

#[Injectable(providedIn: 'root')]
class ConnectionService {}

#[Injectable]
class PostRepository {
    public function __construct(private ConnectionService $db) {}
    public function latest(int $limit = 5): array { return []; }
}
```
Child component:
```php
use Sophia\Component\Component;
use Sophia\Component\Input;

#[Component(selector: 'app-post-list', template: 'post-list.html.twig')]
class PostListComponent
{
    #[Input] public array $items = [];
}
```
Parent component:
```php
use Sophia\Component\Component;
use Sophia\Injector\Inject;

#[Component(
  selector: 'app-blog',
  template: 'blog.html.twig',
  providers: [PostRepository::class]
)]
class BlogComponent
{
    #[Inject] private PostRepository $repo;
    public array $latest = [];

    public function onInit(): void
    {
        $this->latest = $this->repo->latest(10);
    }
}
```
Parent template `blog.html.twig`:
```twig
<h2>Latest posts</h2>
{{ component('app-post-list', { items: latest }) }}
```
This setup yields:
- `ConnectionService` is a root singleton (shared globally).
- `PostRepository` is scoped to `app-blog` and shared by its subtree.
- Data flows: `BlogComponent::$latest` → bound to child `PostListComponent::$items` via `#[Input]`.


Enhancements (Layouts, Slots, Scripts, Page skeleton)
----------------------------------------------------
The component system now includes several UX/productivity additions:

- Layouts + outlet
  - Create a parent layout component that renders the common shell (header/footer) and provides an `outlet` (placeholder) where the active child route content is projected.
  - See Router → Nested routes for how the layout is attached to a route; the renderer composes the final HTML by nesting the child into the layout.

- Slots & content projection
  - Use the `component()` helper to render children and the `slot()` helper to project named content from a parent into a child.
  - Example:
    ```twig
    {# Parent template #}
    {{ component('app-card', { title: 'Hello' }, slot('content', { body: 'Lorem ipsum' })) }}

    {# Child (app-card) template #}
    <article>
      <h3>{{ title }}</h3>
      {{ slot(_context, 'content') }}
    </article>
    ```

- Per‑component JavaScript
  - Alongside `styles`, each component can declare a `scripts` array (relative to the component PHP file). The renderer loads the JS content and injects it at the end of the `<body>` of the final page.
  - Declaration:
    ```php
    #[Component(
      selector: 'app-widget',
      template: 'widget.html.twig',
      styles: ['widget.css'],
      // NEW
      scripts: ['widget.js']
    )]
    class WidgetComponent {}
    ```

- Page skeleton and language
  - The `Renderer` now generates `<html lang="...">` and `<body>` around the root component output. Configure language via `Renderer::configure(..., string $language = 'en', ...)`.
  - You can set the document title and additional meta tags from templates using `set_title()` and `add_meta()` helpers, and attach global meta via `Renderer::addGlobalMetaTags([...])`.
