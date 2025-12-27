Router — Declarative routes and component rendering
=================================================

This folder contains the Router used by the framework to map incoming HTTP
requests to either UI Components (rendered with Twig) or to plain PHP callbacks
(API endpoints). It supports route parameters, named routes with URL generation,
route-scoped data, nested routes, redirects, and simple guards/middleware.

Core pieces:
- `Sophia\Router\Router` — singleton router and matching engine
- `Sophia\Router\Models\MiddlewareInterface` — interface for guards (`canActivate`)
- `Sophia\Component\Renderer` — used by the router to render component routes
- `Sophia\Component\ComponentRegistry` — used for lazy component registration


Quick navigation
----------------
- [What is the Router](#what-is-the-router)
- [Quick start](#quick-start)
- [Defining routes](#defining-routes)
- [Route parameters](#route-parameters)
- [Named routes and URL generation](#named-routes-and-url-generation)
- [Route data in templates](#route-data-in-templates)
- [Nested routes](#nested-routes)
- [Redirects](#redirects)
- [API routes (callbacks)](#api-routes-callbacks)
- [Guards/Middleware (`canActivate`)](#guardsmiddleware-canactivate)
- [Base path](#base-path)
- [404 handling](#404-handling)
- [Integration with components](#integration-with-components)
- [Troubleshooting](#troubleshooting)
- [Full example](#full-example)
- [API reference (brief)](#api-reference-brief)
- [See also](#see-also)


What is the Router
------------------
The Router maps an HTTP request path to a target action. Targets can be:
- a Component class: the component is lazily registered and rendered as the root view
- a PHP `callback`: useful for lightweight API endpoints

The router is a singleton (`Router::getInstance()`), integrated with the component
system so you can render pages as components, and use Twig helpers like `route_data()`
and `url()` inside templates.


Quick start
-----------
`index.php` wiring:
```php
use Sophia\Component\ComponentRegistry;
use Sophia\Component\Renderer;
use Sophia\Router\Router;

$registry = ComponentRegistry::getInstance();
$renderer = new Renderer($registry, __DIR__ . '/pages', cachePath: __DIR__ . '/cache/twig', debug: true);

$router = Router::getInstance();
$router->setComponentRegistry($registry);
$router->setRenderer($renderer);
$router->setBasePath('/test-route'); // optional, if app lives in a subfolder

require __DIR__ . '/routes.php';
$router->dispatch();
```

`routes.php` (declarative array configuration):
```php
use App\Pages\Home\HomeComponent;
use App\Pages\About\AboutComponent;
use Sophia\Router\Router;

$router = Router::getInstance();

$router->configure([
  [
    'path' => 'home/:id',
    'component' => HomeComponent::class,
    'name' => 'home',
    'data' => [ 'title' => 'Home Page' ],
  ],
  [
    'path' => 'about',
    'children' => [[
      'path' => 'us',
      'component' => AboutComponent::class,
      'data' => [ 'title' => 'About Us' ],
    ]]
  ],
]);
```


Defining routes
---------------
Routes are provided as an array of associative arrays via `Router::configure(array $routes)`.
Each route entry accepts the following keys:
- `path`: string path pattern (e.g., `"about"`, `"home/:id"`). Use `":"` to declare params
- `component`: fully-qualified Component class to render (optional if using `callback`)
- `callback`: callable for API routes (optional if using `component`)
- `name`: route name used for URL generation
- `data`: arbitrary route-scoped data available in templates via `route_data()`
- `children`: array of child route configs (nested routes)
- `redirectTo`: path to redirect to
- `canActivate`: array of guard classes/instances implementing `MiddlewareInterface`

There are also convenience methods to add component routes individually:
```php
$router->get('blog', BlogComponent::class, options: [ 'name' => 'blog.index' ]);
$router->post('contact', ContactComponent::class);
```
Internally these call `addRoute('GET'|'POST', path, component, options)`.


Route parameters
----------------
Declare parameters in the path with a leading colon, e.g. `"post/:id"`.
- When matched, parameters are exposed to the root component as initial data and
  can also be read anywhere via `Router::getInstance()->getCurrentParams()`.
- In templates you can access them as normal variables if your root component
  exposes them as public properties (the router injects them when rendering the component).

Example route:
```php
[ 'path' => 'post/:id', 'component' => PostComponent::class, 'name' => 'post.show' ]
```
Inside `PostComponent`:
```php
class PostComponent {
    public string $id;    // receives the ":id" param value
    public array $post = [];
    public function onInit(): void { /* load post by $this->id */ }
}
```


Named routes and URL generation
-------------------------------
Give a route a `name`, then generate URLs from anywhere:
- In PHP: `Router::getInstance()->url('post.show', ['id' => 123])`
- In Twig: `{{ url('post.show', { id: 123 }) }}`

If a parameter is missing, URL generation throws an exception in PHP. In Twig, the
exception will bubble up as a template error (useful during development).


Route data in templates
-----------------------
Each route can carry a `data` bag. Access it in Twig with the `route_data()` helper:
```twig
<title>{{ route_data('title') }}</title>
<p>{{ route_data().description }}</p>
```
From PHP you can read it via `Router::getInstance()->getCurrentRouteData($key = null)`.

When the router renders a component for a route, it passes a `routeData` array into the
root component as an initial property (if present). You can receive it like any other
public property on the component:
```php
class HomeComponent {
    public array $routeData = [];
}
```


Nested routes
-------------
Define `children` under a parent route to nest paths and share guard behavior:
```php
[
  'path' => 'account',
  'canActivate' => [AuthGuard::class],
  'children' => [
    [ 'path' => 'profile', 'component' => ProfileComponent::class ],
    [ 'path' => 'orders/:id', 'component' => OrderDetailComponent::class ],
  ]
]
```
- The matching engine merges `canActivate` from parent and child.
- Child paths are joined to parent (`account/profile`, `account/orders/:id`).


Redirects
---------
A route can redirect to another path using `redirectTo`:
```php
[ 'path' => '/old-page', 'redirectTo' => '/new-page' ]
```
The router will send a `Location:` header and stop execution.


API routes (callbacks)
----------------------
For lightweight endpoints, use `callback` instead of `component`:
```php
[
  'path' => '/api/ping',
  'callback' => function () {
      header('Content-Type: application/json');
      echo json_encode(['ok' => true]);
  },
  'name' => 'api.ping',
]
```


Guards/Middleware (`canActivate`)
---------------------------------
Guards are simple classes that implement `MiddlewareInterface` with a `handle(): bool` method.
Provide them via `canActivate`. All must return `true` to proceed; otherwise routing stops.
```php
use Sophia\Router\Models\MiddlewareInterface;

class AuthGuard implements MiddlewareInterface {
    public function handle(): bool {
        return isset($_SESSION['user']);
    }
}

[
  'path' => 'dashboard',
  'component' => DashboardComponent::class,
  'canActivate' => [AuthGuard::class],
]
```
You may also pass guard instances (useful when they require constructor args).


Base path
---------
If your app is served under a subfolder (e.g., `/test-route`), set a base path:
```php
$router->setBasePath('/test-route');
```
- Matching will ignore the base path prefix.
- `url(name, params)` returns app-relative URLs (starting with `/`).


404 handling
------------
If no route matches, the router sends HTTP 404 and echoes `"404 - Page not found"`.
You can override this behavior by catching exceptions and short-circuiting output in `index.php`,
or by adding a catch-all route with `path: '*'` at the end of your routes list.


Integration with components
---------------------------
When a route targets a component class:
- The router lazily registers the component with `ComponentRegistry::lazyRegister()`.
- It merges route params (e.g., `:id`) into the initial data passed to the root component.
- It also includes `routeData` into that initial data bag.
- Rendering is performed via `Renderer::renderRoot($selector, $data)`.

In templates, you automatically get two helpers registered by the renderer:
- `route_data(key?: string)` → returns current route data (or the whole `data` bag)
- `url(name, params = {})` → generate URLs for named routes


Troubleshooting
---------------
- Unknown component class: make sure the `component` FQCN exists and is autoloadable.
- URL generation error: you called `url('name')` without providing required params.
- Route not matching: remember that `path` is normalized without leading/trailing slashes and parameters use `:name`.
- Guards not working: ensure classes implement `MiddlewareInterface` and return `true` to allow access.
- Templates missing route data: use `{{ route_data() }}` or bind `public array $routeData = [];` on the root component.


Full example
------------
`routes.php`:
```php
use App\Pages\Blog\BlogComponent;
use App\Pages\Post\PostComponent;
use Sophia\Router\Router;
use Sophia\Router\Models\MiddlewareInterface;

class AuthGuard implements MiddlewareInterface {
    public function handle(): bool { return isset($_SESSION['user']); }
}

$router = Router::getInstance();
$router->configure([
  [ 'path' => '', 'redirectTo' => 'blog' ],
  [ 'path' => 'blog', 'component' => BlogComponent::class, 'name' => 'blog.index', 'data' => [ 'title' => 'Blog' ] ],
  [ 'path' => 'post/:id', 'component' => PostComponent::class, 'name' => 'post.show' ],
  [
    'path' => 'account',
    'canActivate' => [AuthGuard::class],
    'children' => [
      [ 'path' => 'profile', 'component' => App\Pages\Account\ProfileComponent::class ],
    ]
  ],
  [ 'path' => '/api/ping', 'callback' => fn () => print json_encode(['ok' => true]), 'name' => 'api.ping' ],
]);
```
Root template usage:
```twig
<h1>{{ route_data('title') }}</h1>
<a href="{{ url('post.show', { id: 42 }) }}">Read #42</a>
```


API reference (brief)
---------------------
- `Router::getInstance(): Router`
- `Router::configure(array $routes): void`
- `Router::get(string $path, string $component, array $options = []): void`
- `Router::post(string $path, string $component, array $options = []): void`
- `Router::setBasePath(string $basePath): void`
- `Router::url(string $name, array $params = []): string`
- `Router::getCurrentRouteData(?string $key = null): mixed`
- `Router::getCurrentParams(): array`

Route config keys supported by `configure()`:
- `path`, `component`, `callback`, `name`, `data`, `children`, `redirectTo`, `canActivate`


See also
--------
- Components: `core/component/README.md`
- Injector/DI: `core/injector/README.md`
