Injector — Lightweight DI for Components and Services
====================================================

This folder contains the minimal dependency injection (DI) system used by the framework. 
It is inspired by Angular: components create hierarchical scopes, services can be provided at
component level or globally (root), and injections happen automatically.

Core pieces:
- `Sophia\Injector\Injector` — the DI container and resolver
- `Sophia\Injector\Injectable` — class attribute to declare a service as injectable
- `Sophia\Injector\Inject` — property attribute to request a dependency on components
- `Sophia\Component\Component` — component attribute that declares template and local `providers`
- `Sophia\Component\ComponentProxy` — builds component instances and performs property injection


Quick navigation
----------------
- [What you can inject](#what-you-can-inject)
- [Scopes and providers](#scopes-and-providers)
- [Root singletons](#root-singletons)
- [Component-local providers (hierarchical resolution)](#component-local-providers-hierarchical-resolution)
- [Property injection in components](#property-injection-in-components)
- [Constructor injection in services](#constructor-injection-in-services)
- [Manual injection with Injector::inject(...)](#manual-injection-with-injectorinject)
- [Lifecycle: when do injections run?](#lifecycle-when-do-injections-run)
- [A complete, concrete example](#a-complete-concrete-example)
- [Troubleshooting](#troubleshooting)
- [Best practices](#best-practices)
- [API reference (brief)](#api-reference-brief)
- [See also](#see-also)


What you can inject
-------------------
- Any class marked with `#[Injectable]` can be constructed by the DI system.
- You can consume injections in two ways:
  1) Properties on components annotated with `#[Inject]` (property injection)
  2) Constructor parameters of services (constructor injection)

Note: Components themselves are created by the framework and receive property injections automatically.
Services are created either as root singletons or inside a component scope when listed in `providers`.


Scopes and providers (how resolution works)
------------------------------------------
- Each rendered component creates a DI scope (`ComponentProxy`).
- Resolution rules for `Injector::inject(Foo::class, $scope)`:
  1) If `Foo` is a root singleton (`#[Injectable(providedIn: 'root')]`) → return the unique global instance.
  2) If the current scope or any ancestor component declares `Foo` in its `providers` → create/cache per that scope.
  3) Otherwise → throw `RuntimeException`: no provider found.
- The framework keeps a per-scope cache so each provider is instantiated once per scope (a scoped singleton).


Root singletons
---------------
If a service should be globally shared, mark it as `providedIn: 'root'`.
```php
<?php
namespace App\Services;

use Sophia\Injector\Injectable;

#[Injectable(providedIn: 'root')]
class ConnectionService
{
    // ... public API
}
```
Usage anywhere (no component scope required):
```php
use Sophia\Injector\Injector;
use App\Services\ConnectionService;

$db = Injector::inject(ConnectionService::class); // the unique root instance
```


Component-local providers (hierarchical resolution)
--------------------------------------------------
Declare providers on a component with the `#[Component]` attribute. These services are resolved
first from the component itself, then by walking up the parent component chain.

```php
<?php
namespace App\Pages\Home;

use Sophia\Component\Component;
use App\Services\UserService;

#[Component(
    selector: 'app-home',
    template: 'home.html.twig',
    providers: [UserService::class]   // provide the service for this component subtree
)]
class HomeComponent
{
    // ...
}
```
Each provider is instantiated once per scope. Child components share the same instance from the nearest provider.


Property injection in components
--------------------------------
Components request dependencies with the `#[Inject]` attribute on typed properties.
`ComponentProxy` scans component properties, resolves each dependency and sets it before `onInit()` is called.

```php
<?php
namespace App\Pages\Home;

use Sophia\Component\Component;
use Sophia\Injector\Inject;
use App\Services\UserService;

#[Component(selector: 'app-home', template: 'home.html.twig', providers: [UserService::class])]
class HomeComponent
{
    #[Inject] private UserService $users;  // resolved from the component scope

    public array $activeUsers = [];

    public function onInit(): void
    {
        $this->activeUsers = $this->users->getActive();
    }
}
```
Requirements for property injection:
- The property MUST be typed with a class name.
- The property MUST have the `#[Inject]` attribute.
- The type MUST be either root-provided or present in the current/ancestor components' `providers`.


Constructor injection in services
---------------------------------
Services can declare dependencies in their constructors. When the `Injector` creates a service,
it resolves each constructor parameter by type using the same rules (root first, then scope chain).

```php
<?php
namespace App\Services;

use Sophia\Injector\Injectable;

#[Injectable] // not root; must be listed in some component providers
class UserService
{
    public function __construct(private Logger $logger) {}

    public function getActive(): array
    {
        $this->logger->info('Loading users...');
        // ...
        return [];
    }
}

#[Injectable(providedIn: 'root')]
class Logger
{
    public function info(string $msg): void { /* ... */ }
}
```
Notes:
- Optional constructor params are supported. If a param has a default value and cannot be resolved as a class, the default is used.
- Non-optional params without resolvable class type will cause an error: `Cannot resolve 'paramName' for Class`.


Manual injection with Injector::inject
-------------------------------------
In non-component code (e.g., `index.php`) or static contexts you can always resolve a dependency manually.

```php
use Sophia\Injector\Injector;
use App\Services\ConnectionService;

$db = Injector::inject(ConnectionService::class); // works because it's root-provided
```
When calling `Injector::inject()` manually for a non-root service, you may pass the current component scope
(the framework does this automatically during rendering):
```php
use Sophia\Injector\Injector;
use Sophia\Component\ComponentProxy;
use App\Services\UserService;

/** @var ComponentProxy $scope */
$service = Injector::inject(UserService::class, $scope);
```


Lifecycle: when do injections run?
---------------------------------
- Components are instantiated by `ComponentProxy`.
- Immediately after creation, `ComponentProxy` performs property injection on all properties with `#[Inject]`.
- If the component defines `onInit()`, it is invoked AFTER property injection, so injected services are ready.
- During nesting, the renderer maintains the current scope so child components resolve against the correct providers.


A complete, concrete example
----------------------------
Service definitions:
```php
<?php
namespace App\Services;

use Sophia\Injector\Injectable;

#[Injectable(providedIn: 'root')]
class ConnectionService { /* ... */ }

#[Injectable]
class PostRepository
{
    public function __construct(private ConnectionService $db) {}
    public function latest(int $limit = 5): array { /* query ... */ return []; }
}
```
Component with providers and property injection:
```php
<?php
namespace App\Pages\Blog;

use Sophia\Component\Component;
use Sophia\Injector\Inject;
use App\Services\PostRepository;

#[Component(
    selector: 'app-blog',
    template: 'blog.html.twig',
    providers: [PostRepository::class]   // scoped, because it lacks providedIn: 'root'
)]
class BlogComponent
{
    #[Inject] private PostRepository $posts; // resolved from the component scope

    public array $latest = [];

    public function onInit(): void
    {
        $this->latest = $this->posts->latest(10);
    }
}
```
This yields:
- `ConnectionService` is global (root) and reused across the whole app.
- `PostRepository` is created once per `BlogComponent` scope and shared with its child components.


Troubleshooting
---------------
- Error: `No provider found for 'Foo'` → Add `#[Injectable(providedIn: 'root')]` to `Foo` or list `Foo::class` in a component's `providers`.
- Error: `Cannot resolve 'param' for Foo` → The parameter type is not a class or is missing; make it a class type or provide a default.
- A property with `#[Inject]` remains null → Ensure it is typed (e.g., `private Foo $foo;`) and the type is provided (root or providers chain).
- Using `#[Inject]` on services has no effect → Property injection is performed on components. Services rely on constructor injection.


Best practices
--------------
- Prefer constructor injection for services. Keep them stateless where possible.
- Use property injection only on components — it keeps component code concise.
- Mark widely used cross-cutting services as `providedIn: 'root'` to avoid duplicate instances.
- Keep component `providers` minimal and close to where variations are needed (e.g., testing, overrides).
- Avoid magic strings: always type-hint classes so the resolver can work.


API reference (brief)
---------------------
- `Injector::inject(string $class, ?ComponentProxy $scope = null): object`
  - Resolves a dependency using current scope (if any), walking up providers, falling back to root singletons.
- `#[Injectable(providedIn?: 'root')]`
  - Marks a class as injectable. With `providedIn: 'root'` it becomes a global singleton.
- `#[Inject]` (property attribute)
  - Use on component properties to receive injected instances by type.
- `#[Component(providers: [...])]`
  - Declare services available to this component and its subtree.


See also
--------
- Database integration shows real-world usage of root services: `core/database/README.md`
