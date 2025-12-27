Framework Overview — Lightweight PHP Components, DI, and Router
=============================================================

This repository contains a minimal, production‑oriented PHP framework that blends:
- Component-based rendering with Twig
- A tiny, Angular‑inspired Dependency Injection system
- A simple but capable Router (components, API callbacks, parameters, guards)
- Optional database layer with a fluent QueryBuilder and Active Record‑style ORM

It is designed to be clear, explicit, and easy to extend. Components are plain PHP classes (annotated), services are auto‑wired, and routes map requests to either components (views) or callbacks (APIs).


Quick navigation
----------------
- [What you get (features)](#what-you-get-features)
- [Architecture at a glance](#architecture-at-a-glance)
- [Installation](#installation)
- [Project structure](#project-structure)
- [Quick start (index.php + routes.php)](#quick-start-indexphp--routesphp)
- [Create your first component](#create-your-first-component)
- [Dependency Injection (services)](#dependency-injection-services)
- [Routing basics (components, params, urls)](#routing-basics-components-params-urls)
- [Database integration (optional)](#database-integration-optional)
- [Troubleshooting](#troubleshooting)
- [Deep dives (module READMEs)](#deep-dives-module-readmes)
- [Including other READMEs by reference](#including-other-readmes-by-reference)


What you get (features)
-----------------------
- Components rendered with Twig, with strict templates and a small set of helpers
- Property injection in components and constructor injection in services
- Root singletons via `#[Injectable(providedIn: 'root')]`
- Route configuration with parameters, named routes, redirects, nested routes, and simple guards
- Optional database service with fluent QueryBuilder and Active Record‑style entities


Architecture at a glance
------------------------
- Components: `core/component` (attributes, registry, renderer)
- DI (Injector): `core/injector` (root singletons + per‑component scoped providers)
- Router: `core/router` (maps requests to components or callbacks; integrates with renderer)
- Database (optional): `core/database` (connection service + ORM)

Flow for a component route:
1) `Router` matches the incoming path → selects a component class.
2) `ComponentRegistry` lazily registers it and `Renderer` creates a `ComponentProxy`.
3) `ComponentProxy` opens a DI scope, warms providers, creates the component, runs property injection, then `onInit()`.
4) `Renderer` collects public properties/zero‑arg getters and renders the Twig template.


Installation
------------
- PHP 8.1+
- Composer

```bash
composer install
```

If you use environment variables, add a `.env` file (Dotenv is included in composer.json) and configure the database as needed (see [Database integration](#database-integration-optional)).


Project structure
-----------------
```
.
├─ core/
│  ├─ component/     # Component system (attributes, registry, renderer)
│  ├─ injector/      # DI container + attributes
│  ├─ router/        # Router + middleware interface
│  └─ database/      # Optional DB service + ORM
├─ pages/            # Your component classes and Twig templates
├─ routes.php        # Route table
├─ index.php         # App bootstrap (renderer + router wiring)
└─ vendor/           # Composer dependencies
```


Quick start (index.php + routes.php)
------------------------------------
Minimal bootstrap in `index.php`:
```php
<?php
use Sophia\Component\ComponentRegistry;
use Sophia\Component\Renderer;
use Sophia\Database\ConnectionService;
use Sophia\Injector\Injector;
use Sophia\Router\Router;

require __DIR__ . '/vendor/autoload.php';

// Optional env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Optional DB (root singleton)
$dbConfig = file_exists('config/database.php')
    ? require 'config/database.php'
    : ['driver' => 'sqlite', 'credentials' => ['database' => 'database/app.db']];
$db = Injector::inject(ConnectionService::class);
$db->configure($dbConfig);

$registry = ComponentRegistry::getInstance();
$renderer = new Renderer($registry, __DIR__ . '/pages', cachePath: __DIR__ . '/cache/twig', debug: true);

$router = Router::getInstance();
$router->setComponentRegistry($registry);
$router->setRenderer($renderer);
$router->setBasePath('/test-route'); // optional, if app is in a subfolder

require __DIR__ . '/routes.php';
$router->dispatch();
```

Define routes in `routes.php`:
```php
<?php
use App\Pages\Home\HomeComponent;
use Sophia\Router\Router;

$router = Router::getInstance();

$router->configure([
    [
        'path' => 'home/:id',                  // URL with param
        'component' => HomeComponent::class,  // Component class
        'name' => 'home',                     // Named route
        'data' => [ 'title' => 'Home Page' ], // Route-scoped data
    ],
]);
```


Create your first component
---------------------------
Component class under `pages/...` and a Twig template next to it (or inside the configured pages path):
```php
<?php
namespace App\Pages\Home;

use Sophia\Component\Component;

#[Component(selector: 'app-home', template: 'home.html.twig')]
class HomeComponent
{
    public string $title = 'Welcome';
    public string $id;

    public function onInit(): void
    {
        // Optionally compute public state for the template
    }
}
```
Template `home.html.twig`:
```twig
<h1>{{ title }}</h1>
<p>User ID: {{ id }}</p>
```
The renderer will pass route params (`:id`) as initial data to the root component, so `id` will be available.


Dependency Injection (services)
-------------------------------
- Mark root singletons with `#[Injectable(providedIn: 'root')]`.
- Use property injection in components: `#[Inject] private Service $service;`
- Use constructor injection in services; dependencies are resolved by the Injector.

Example service + usage in a component:
```php
use Sophia\Injector\Injectable;
use Sophia\Injector\Inject;
use Sophia\Component\Component;

#[Injectable(providedIn: 'root')]
class Logger { public function info(string $m): void {} }

#[Injectable]
class UserService { public function __construct(private Logger $log) {} }

#[Component(selector: 'app-users', template: 'users.html.twig', providers: [UserService::class])]
class UsersComponent
{
    #[Inject] private UserService $users; // resolved from providers
    public array $active = [];

    public function onInit(): void { $this->active = $this->users->getActive(); }
}
```
See the full DI reference: [Injector (DI)](core/injector/README.md).


Routing basics (components, params, urls)
----------------------------------------
- Define paths like `post/:id` to capture params; they are available to components and templates.
- Name routes with `name` and generate URLs from PHP or Twig using the `url()` helper.
- Provide `data` on routes; read them in Twig with `route_data()`.

Twig helpers from the renderer:
```twig
<a href="{{ url('home', { id: 123 }) }}">Go home</a>
<p>Title: {{ route_data('title') }}</p>
```
More details: [Router](core/router/README.md).


Database integration (optional)
-------------------------------
The `ConnectionService` is a root‑provided service with a fluent QueryBuilder and an Active Record‑style ORM via `Entity`.

Example entity:
```php
use Sophia\Database\Entity;

class Post extends Entity
{
    protected static string $table = 'posts';
    protected static array $fillable = ['title', 'content', 'status'];
}
```
Query examples:
```php
$posts = Post::where('status', 'published')->orderBy('created_at', 'DESC')->limit(10)->get();
$one   = Post::find(1);
```
Full guide: [Database](core/database/README.md).


Troubleshooting
---------------
- Template not found: ensure the file exists next to the component class or in a path added to the renderer.
- Undefined Twig variable: templates run with strict variables; expose data via public properties or zero‑arg getters.
- Injection error: add `#[Inject]` to typed component properties; mark services as `#[Injectable]` (root when needed) and/or list them in `providers`.
- Routing mismatch: check `basePath`, path normalization, and that names/params match when calling `url()`.


Deep dives (module READMEs)
---------------------------
- Components: [Components](core/component/README.md)
- Injector (DI): [Injector (DI)](core/injector/README.md)
- Router: [Router](core/router/README.md)
- Database: [Database](core/database/README.md)




---

Using this repository as a package + demo
----------------------------------------
This repo is organized so that the core framework (package) is published to Packagist, while the demo app stays in-repo only.

- Package (library): `giovanni-venturelli/sophia` (root of this repo)
  - Namespaces exported: `Sophia\\*` (component, injector, router, form, database)
  - Packagist dist excludes the demo and app assets via `.gitattributes`
- Demo app: `/demo` (not included in the Packagist dist)
  - Depends on the package via Composer repository of type `path` to the repo root
  - Autoloads the demo namespaces from the project folders (`../pages`, `../Shared`, `../services`)

Install the package (as a dependency) in another project
-------------------------------------------------------
```bash
composer require giovanni-venturelli/sophia
```

Run the demo locally from this repo
-----------------------------------
1) Install dependencies for the demo (uses a path repo to the root library):
```bash
cd demo
composer install
```

2) Start a PHP dev server pointing to the demo folder (or your web server root to `demo/`):
```bash
php -S localhost:8080 -t demo
```

Then open:
- http://localhost:8080/index.php/home/123 (adjust paths if needed)

Notes
-----
- The demo reuses the project folders `pages/`, `Shared/`, `services/`, `config/`, `css/`, `js/`, `cache/` from the repo root.
- The router base path in the demo is set to `/test-route/demo`. If you serve it at a different path, update `$basePath` in `demo/index.php` and the global asset paths.
- The package requires PHP >= 8.1; the demo also requires `vlucas/phpdotenv` for `.env` loading.

Publishing to Packagist
-----------------------
1) Push this repository to GitHub under `giovanni-venturelli/sophia`.
2) Create a version tag, e.g.:
```bash
git tag v0.1.0
git push origin v0.1.0
```
3) Submit the repository URL to Packagist and set up the GitHub Service Hook so Packagist auto-updates on new tags.

After publish, consumers can `composer require giovanni-venturelli/sophia`.
