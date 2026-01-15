Components — View + Logic building blocks
=======================================

Component system: PHP classes annotated with `#[Component]` and rendered with native PHP templates.

Features:
- Expose public properties/getters to templates
- Receive inputs from parent components
- Inject services via DI
- Declare local providers for scoped services
- Include CSS/JS files at render time

The renderer handles lifecycle, data extraction, template resolution, child rendering, and template helpers.


Quick navigation
----------------
- [What is a Component](#what-is-a-component)
- [Quick start](#quick-start-minimal-example)
- [Anatomy of #[Component]](#anatomy-of-component)
- [Lifecycle and data flow](#lifecycle-and-data-flow)
- [Templates and data binding](#templates-and-data-binding)
- [Inputs (parent → child)](#inputs-parent--child-bindings)
- [Nesting components](#nesting-components-in-twig)
- [Dependency Injection](#dependency-injection-in-components-inject-and-providers)
- [Styles and Scripts](#styles)
- [Registration and rendering](#registration-and-rendering-root-rendering)
- [Template helpers](#routertwig-helpers-route_data-url)
- [Troubleshooting](#troubleshooting)
- [Full example](#full-example-parent--child--di)


What is a Component
-------------------
A Component is a class with a `#[Component]` attribute that binds a PHP class to a PHP template
and optional metadata (`imports`, `styles`, `scripts`, `providers`). Components are instantiated
via `ComponentProxy`, which applies property injection (`#[Inject]`), calls `onInit()` if present,
and renders the PHP template with the component's public state.


Quick start (minimal example)
-----------------------------
PHP class:
```php
<?php
namespace App\Pages\Hello;

use Sophia\Component\Component;

#[Component(selector: 'app-hello', template: 'hello.php')]
class HelloComponent
{
    public string $name = 'World';
}
```
Template `hello.php` (next to `HelloComponent.php` or in configured templates path):
```php
<h1>Hello, <?= $e($name) ?>!</h1>
```
Render via `Renderer`:
```php
$registry = \Sophia\Component\ComponentRegistry::getInstance();
$registry->register(App\Pages\Hello\HelloComponent::class);

$renderer = \Sophia\Injector\Injector::inject(\Sophia\Component\Renderer::class);
$renderer->setRegistry($registry);
echo $renderer->renderRoot('app-hello');
```


Anatomy of `#[Component]`
-------------------------
```php
#[Attribute(Attribute::TARGET_CLASS)]
class Component {
    public function __construct(
        public string $selector,
        public string $template,
        public array $imports = [],
        public array $styles = [],
        public array $scripts = [],
        public array $providers = [],
        public array $meta = []
    ) {}
}
```
- `selector`: unique component name (e.g., `'app-hello'`)
- `template`: PHP file name (basename or relative path next to component's PHP file)
- `imports`: other component classes to auto-register
- `styles`: CSS file paths (relative to component's PHP file), injected as `<style>` tag
- `scripts`: JS file paths (relative to component's PHP file), injected as `<script>` tag
- `providers`: DI providers (service classes) for this component and subtree
- `meta`: arbitrary metadata (optional)


Lifecycle and data flow
-----------------------
Component creation via `ComponentProxy`:
1) Enter DI scope for this component
2) Pre-warm providers (resolve each class in `providers`)
3) Create component instance
4) Perform property injection (`#[Inject]`)
5) Call `onInit()` if present
6) Render PHP template with component's public state

Data exposed to templates:
- All public properties
- All public zero-arg getters: `getXyz()` → `$xyz`

Pass initial data via `Renderer::renderRoot($selector, $data)` to assign matching public properties.


Templates and data binding
--------------------------
- Templates resolved relative to component's file or from configured template paths
- Public properties and zero-arg getters become template variables

PHP template example:
```php
<section>
  <h2><?= $e($title) ?></h2>
  <p>Total: <?= count($items) ?></p>

  <?php if (!empty($items)): ?>
    <ul>
      <?php foreach ($items as $item): ?>
        <li><?= $e($item['name']) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <em>No items.</em>
  <?php endif; ?>
</section>
```


Inputs (parent → child bindings)
--------------------------------
Use `#[Input]` on child properties to declare bindable inputs. Parents supply values via `$component()`.

Child component:
```php
use Sophia\Component\Component;
use Sophia\Component\Input;

#[Component(selector: 'app-child', template: 'child.php')]
class ChildComponent
{
    #[Input] public string $title;          // bound by "title"
    #[Input(alias: 'count')] public int $n; // bound by "count"
}
```
Parent template:
```php
<?= $component('app-child', ['title' => 'Hello', 'count' => 3]) ?>
```
Binding rules:
- Only `#[Input]` properties participate
- Use `alias` if provided, otherwise property name
- Bindings applied before child render and `onInit()`


Nesting components
------------------
The `$component()` helper renders child components:
```php
<?= $component('app-card', ['title' => 'Card', 'items' => $items]) ?>
```
Creates child under current DI scope, applies input bindings, renders, returns HTML. Provider resolution walks up parent scopes to root singletons.


Dependency Injection in components
----------------------------------
- Use `#[Inject]` on typed component properties to receive services
- List non-root services in `providers` array for this subtree
- Root singletons (`#[Injectable(providedIn: 'root')]`) globally available

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
    template: 'users.php',
    providers: [UserService::class]
)]
class UsersComponent
{
    #[Inject] private UserService $users;
    public array $active = [];

    public function onInit(): void
    {
        $this->active = $this->users->getActive();
    }
}
```
More: `core/injector/README.md`.


Styles and Scripts
------------------
Provide CSS/JS files (relative to component's PHP file) in `styles`/`scripts`. Renderer injects them as `<style>`/`<script>` tags.

```php
#[Component(
  selector: 'app-panel',
  template: 'panel.php',
  styles: ['panel.css', 'theme.css'],
  scripts: ['panel.js']
)]
```


Registration and rendering
--------------------------
Register components with `ComponentRegistry` and render with `Renderer`:

```php
use Sophia\Component\ComponentRegistry;
use Sophia\Component\Renderer;
use Sophia\Injector\Injector;

$registry = ComponentRegistry::getInstance();
$registry->register(App\Pages\Home\HomeComponent::class);

$renderer = Injector::inject(Renderer::class);
$renderer->setRegistry($registry);
$renderer->configure(__DIR__ . '/pages', '', 'en', true);

$html = $renderer->renderRoot('app-home', ['title' => 'Welcome']);
```
Notes:
- `Renderer` is root injectable via `Injector::inject()`
- `register()` throws if class lacks `#[Component]`
- `imports` auto-registered recursively


Template helpers
----------------
Available in all templates:
- `$component(selector, bindings, slotContent?)` → render child component
- `$slot(name, context?)` → render named slot content
- `$set_title(title)` → set page title
- `$add_meta(name, content)` → add `<meta>` tag
- `$route_data(key?)` → get route data
- `$url(name, params?)` → generate URL by route name
- `$form_action(name)` → build form POST action URL
- `$csrf_field()` → CSRF hidden input
- `$flash(key)`, `$peek_flash(key)`, `$has_flash(key)` → flash messages
- `$form_errors(field?)` → validation errors
- `$old(field, default?)` → sticky old input
- `$e(value)` → HTML escape

Examples:
```php
<a href="<?= $e($url('post.show', ['id' => $post['id']])) ?>">Read more</a>
<p>Category: <?= $e($route_data('category')) ?></p>

<form method="post" action="<?= $e($form_action('send')) ?>">
  <?= $csrf_field() ?>
  <?php if ($has_flash('error')): ?>
    <div class="alert"><?= $e($flash('error')) ?></div>
  <?php endif; ?>
</form>
```


Troubleshooting
---------------
- Template not found: ensure file exists next to component class or in renderer's paths
- Undefined variable: expose data via public properties or getters
- Input not applied: check `#[Input]` decoration and correct key/alias in parent
- Service not injected: use `#[Inject]` on typed property; ensure service is root-provided or in `providers`
- Styles/scripts error: files must exist relative to component's PHP file


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

#[Component(selector: 'app-post-list', template: 'post-list.php')]
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
  template: 'blog.php',
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
Parent template `blog.php`:
```php
<h2>Latest posts</h2>
<?= $component('app-post-list', ['items' => $latest]) ?>
```
Result:
- `ConnectionService` is root singleton (shared globally)
- `PostRepository` scoped to `app-blog` and its subtree
- Data flows: `BlogComponent::$latest` → `PostListComponent::$items` via `#[Input]`


Enhancements (Layouts, Slots, Scripts)
--------------------------------------

**Layouts + outlet**
- Create parent layout component with common shell (header/footer) and `outlet` placeholder for child route content
- See Router → Nested routes for layout attachment

**Slots & content projection**
- Use `$component()` to render children and `$slot()` to project named content
- Example:
  ```php
  <!-- Parent template -->
  <?= $component('app-card', ['title' => 'Hello'], $slotContent) ?>

  <!-- Child (app-card) template -->
  <article>
    <h3><?= $e($title) ?></h3>
    <?= $slot('content') ?>
  </article>
  ```

**Per-component JavaScript**
- Declare `scripts` array (relative to component PHP file)
- Renderer injects JS at end of `<body>`
- Example:
  ```php
  #[Component(
    selector: 'app-widget',
    template: 'widget.php',
    styles: ['widget.css'],
    scripts: ['widget.js']
  )]
  class WidgetComponent {}
  ```

**Page skeleton**
- Renderer generates `<html lang="...">` and `<body>` around root component
- Configure language via `Renderer::configure(..., string $language = 'en', ...)`
- Set title/meta from templates: `$set_title()`, `$add_meta()`
- Global meta: `Renderer::addGlobalMetaTags([...])`
