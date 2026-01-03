# 🔥 Controllers in Sophia Framework

NestJS-style Controller system for handling API and AJAX requests.

## Table of Contents

1. [Introduction](#introduction)
2. [Core Concepts](#core-concepts)
3. [Controller Decorator](#controller-decorator)
4. [HTTP Decorators](#http-decorators)
5. [Route Configuration](#route-configuration)
6. [Route Parameters](#route-parameters)
7. [Response Handling](#response-handling)
8. [Guards and Middleware](#guards-and-middleware)
9. [Practical Examples](#practical-examples)

---

## Introduction

**Controllers** in Sophia allow you to handle HTTP requests declaratively using PHP 8 attributes, similar to NestJS.

### Main Features:

- ✅ Mandatory `#[Controller]` decorator to identify controllers
- ✅ HTTP method decorators (`#[Get]`, `#[Post]`, `#[Put]`, `#[Delete]`, `#[Patch]`)
- ✅ Automatic routing based on decorators
- ✅ Dynamic route parameters (`:id`, `:slug`, etc.)
- ✅ No manual registration required
- ✅ Compatible with existing Guards/Middleware
- ✅ Automatic JSON response for arrays/objects
- ✅ Coexists with Components and callbacks

---

## Core Concepts

### Basic Controller

```php
<?php

namespace App\Controllers;

use Sophia\Controller\Controller;
use Sophia\Controller\Get;
use Sophia\Controller\Post;

#[Controller('posts')]  // 🔥 REQUIRED
class PostController
{
    #[Get()]
    public function findAll(): array
    {
        return [
            'success' => true,
            'data' => [/* ... */]
        ];
    }

    #[Get(':id')]
    public function findOne(string $id): array
    {
        return [
            'success' => true,
            'data' => ['id' => $id, /* ... */]
        ];
    }

    #[Post()]
    public function create(): array
    {
        $data = json_decode(file_get_contents('php://input'), true);
        return ['success' => true, 'data' => $data];
    }
}
```

### Route Configuration

```php
// routes.php
return [
    [
        'path' => 'posts',
        'controller' => PostController::class,
    ],
];
```

**This automatically generates:**

- `GET /posts` → `PostController::findAll()`
- `GET /posts/12` → `PostController::findOne($id)`
- `POST /posts` → `PostController::create()`

---

## Controller Decorator

### Basic Syntax

```php
use Sophia\Controller\Controller;

#[Controller]
class MyController { }
```

### With Prefix (Optional)

```php
#[Controller('api/v1')]
class ApiController { }
```

⚠️ **IMPORTANT**: The prefix in the decorator is **optional** and informative. The actual path is determined by route configuration.

### Examples

```php
// Controller without prefix
#[Controller]
class HomeController { }

// Controller with descriptive prefix
#[Controller('posts')]
class PostController { }

// API Controller
#[Controller('api/users')]
class UserController { }
```

### Validation

The Router **requires** all classes used as controllers to have the `#[Controller]` decorator:

```php
// ✅ CORRECT
#[Controller]
class PostController { }

// ❌ ERROR: RuntimeException
class PostController { }  // Missing #[Controller]
```

**Generated Error:**
```
RuntimeException: Class 'App\Controllers\PostController' must have #[Controller] attribute
```

---

## HTTP Decorators

### Available Decorators

```php
use Sophia\Controller\Get;
use Sophia\Controller\Post;
use Sophia\Controller\Put;
use Sophia\Controller\Delete;
use Sophia\Controller\Patch;
```

### Syntax

```php
// Controller root route
#[Get()]
public function index() { }

// Route with single parameter
#[Get(':id')]
public function show(string $id) { }

// Route with complex path
#[Get(':id/comments')]
public function getComments(string $id) { }

// Route with multiple parameters
#[Get(':userId/posts/:postId')]
public function getUserPost(string $userId, string $postId) { }

// Static route (takes precedence over parameters)
#[Get('featured')]
public function featured() { }
```

### Priority Order

⚠️ **IMPORTANT**: Static routes must be defined BEFORE dynamic parameters:

```php
class PostController
{
    // ✅ CORRECT: static route first
    #[Get('featured')]
    public function featured() { }

    #[Get(':id')]
    public function findOne(string $id) { }

    // ❌ WRONG: this will never be called
    // because ':id' also captures 'search'
    #[Get('search')]
    public function search() { }
}
```

---

## Route Configuration

### Basic Route

```php
[
    'path' => 'posts',
    'controller' => PostController::class,
]
```

### With Named Route

```php
[
    'path' => 'posts',
    'controller' => PostController::class,
    'name' => 'posts',
]
```

### With Guards

```php
[
    'path' => 'admin/posts',
    'controller' => PostController::class,
    'canActivate' => [
        AuthGuard::class,
        AdminGuard::class,
    ],
]
```

### With Route Data

```php
[
    'path' => 'posts',
    'controller' => PostController::class,
    'data' => [
        'resource' => 'posts',
        'permission' => 'read',
    ],
]
```

### Nested Controllers

```php
[
    'path' => 'api/posts/:postId/comments',
    'controller' => CommentController::class,
]
```

This allows:
- `GET /api/posts/123/comments` → `CommentController::findAll()`
- The `:postId` parameter is available in `$_GET['postId']` or route data

---

## Route Parameters

### Single Parameters

```php
#[Get(':id')]
public function show(string $id): array
{
    return ['id' => $id];
}
```

**Call:** `GET /posts/42`
**Parameters:** `$id = "42"`

### Multiple Parameters

```php
#[Get(':category/:slug')]
public function showByCategory(string $category, string $slug): array
{
    return [
        'category' => $category,
        'slug' => $slug
    ];
}
```

**Call:** `GET /posts/tech/my-article`
**Parameters:** 
- `$category = "tech"`
- `$slug = "my-article"`

### Parameters with Nested Paths

```php
#[Get(':id/comments/:commentId')]
public function getComment(string $id, string $commentId): array
{
    return [
        'postId' => $id,
        'commentId' => $commentId
    ];
}
```

**Call:** `GET /posts/10/comments/5`
**Parameters:**
- `$id = "10"`
- `$commentId = "5"`

---

## Response Handling

The Router automatically handles different response types:

### JSON Response (Automatic)

```php
#[Get()]
public function index(): array
{
    return ['success' => true, 'data' => [...]];
}
```

**Output:**
```json
{
  "success": true,
  "data": [...]
}
```

**Automatic headers:**
```
Content-Type: application/json
```

### String Response

```php
#[Get('health')]
public function health(): string
{
    return 'OK';
}
```

**Output:** `OK`

### Null Response

```php
#[Post('webhook')]
public function webhook(): void
{
    // Process webhook
    // No output
}
```

### Custom Response

```php
#[Get('download')]
public function download(): void
{
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="file.pdf"');
    readfile('/path/to/file.pdf');
}
```

---

## Guards and Middleware

Guards work exactly like with Components:

### Guard Definition

```php
namespace App\Guards;

use Sophia\Router\Models\MiddlewareInterface;

class AuthGuard implements MiddlewareInterface
{
    public function handle(): bool
    {
        // Check authentication
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return false;
        }
        return true;
    }
}
```

### Applying Guard

```php
// At route level
[
    'path' => 'admin/posts',
    'controller' => PostController::class,
    'canActivate' => [AuthGuard::class],
]
```

Guards are executed **before** the controller method.

---

## Practical Examples

### Complete CRUD

```php
<?php

namespace App\Controllers;

use Sophia\Controller\Controller;
use Sophia\Controller\Get;
use Sophia\Controller\Post;
use Sophia\Controller\Put;
use Sophia\Controller\Delete;

#[Controller('posts')]  // 🔥 REQUIRED
class PostController
{
    // List all posts
    #[Get()]
    public function index(): array
    {
        $posts = /* fetch from DB */;
        return ['success' => true, 'data' => $posts];
    }

    // Show a post
    #[Get(':id')]
    public function show(string $id): array
    {
        $post = /* fetch from DB */;
        if (!$post) {
            http_response_code(404);
            return ['success' => false, 'error' => 'Not found'];
        }
        return ['success' => true, 'data' => $post];
    }

    // Create a post
    #[Post()]
    public function store(): array
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = /* save to DB */;
        return ['success' => true, 'data' => ['id' => $id]];
    }

    // Update a post
    #[Put(':id')]
    public function update(string $id): array
    {
        $data = json_decode(file_get_contents('php://input'), true);
        /* update in DB */;
        return ['success' => true, 'message' => 'Updated'];
    }

    // Delete a post
    #[Delete(':id')]
    public function destroy(string $id): array
    {
        /* delete from DB */;
        return ['success' => true, 'message' => 'Deleted'];
    }
}
```

### API with Relations

```php
use Sophia\Controller\Controller;
use Sophia\Controller\Get;
use Sophia\Controller\Post;

#[Controller('comments')]
class CommentController
{
    // GET /posts/:postId/comments
    #[Get()]
    public function index(): array
    {
        $postId = $_GET['postId'] ?? null;
        $comments = /* fetch comments for postId */;
        return ['success' => true, 'data' => $comments];
    }

    // POST /posts/:postId/comments
    #[Post()]
    public function store(): array
    {
        $postId = $_GET['postId'] ?? null;
        $data = json_decode(file_get_contents('php://input'), true);
        /* save comment for postId */;
        return ['success' => true];
    }
}
```

### Mix Controller and Component

```php
return [
    // Homepage SSR
    [
        'path' => '',
        'component' => HomeComponent::class,
    ],

    // API Posts
    [
        'path' => 'api/posts',
        'controller' => PostController::class,
    ],

    // Dashboard SSR
    [
        'path' => 'dashboard',
        'component' => DashboardComponent::class,
        'canActivate' => [AuthGuard::class],
    ],
];
```

### AJAX Call

```javascript
// Fetch all posts
const response = await fetch('/posts');
const data = await response.json();
console.log(data); // { success: true, data: [...] }

// Fetch single post
const response = await fetch('/posts/12');
const data = await response.json();
console.log(data); // { success: true, data: { id: 12, ... } }

// Create post
const response = await fetch('/posts', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ title: 'New Post', body: '...' })
});
const data = await response.json();
```

---

## Best Practices

### 1. Naming Convention

```php
// ✅ Use descriptive names
#[Get()]
public function index() { }

#[Get(':id')]
public function show(string $id) { }

#[Post()]
public function store() { }

#[Put(':id')]
public function update(string $id) { }

#[Delete(':id')]
public function destroy(string $id) { }
```

### 2. Input Validation

```php
#[Post()]
public function store(): array
{
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['title'])) {
        http_response_code(400);
        return ['success' => false, 'error' => 'Title required'];
    }
    
    // Process...
}
```

### 3. HTTP Status Codes

```php
#[Get(':id')]
public function show(string $id): array
{
    $post = /* ... */;
    
    if (!$post) {
        http_response_code(404);
        return ['success' => false, 'error' => 'Not found'];
    }
    
    return ['success' => true, 'data' => $post];
}
```

### 4. Consistent Response Format

```php
// ✅ Consistent format
return [
    'success' => true,
    'data' => [...],
    'meta' => [
        'page' => 1,
        'total' => 100
    ]
];

// On error
return [
    'success' => false,
    'error' => 'Error message',
    'code' => 'ERROR_CODE'
];
```

---

## Differences from NestJS

| Feature | NestJS | Sophia |
|---------|--------|--------|
| Decorators | ✅ `@Get()` | ✅ `#[Get()]` |
| Injection | ✅ Constructor | ⚠️ To implement |
| DTO | ✅ With validation | ⚠️ Manual |
| Exception Filters | ✅ Built-in | ⚠️ Manual |
| Pipes | ✅ Built-in | ⚠️ Manual |
| Interceptors | ✅ Built-in | ⚠️ Manual |

---

## Troubleshooting

### Route not found

**Problem:** `404 - Page not found`

**Solutions:**
1. Verify controller is in route: `'controller' => YourController::class`
2. Verify method has correct decorator: `#[Get()]`
3. Check route base path
4. Check route order (static before dynamic)

### Parameters not received

**Problem:** Parameters are `null`

**Solutions:**
1. Verify parameter names in decorator match method names
2. Verify URL contains correct parameters
3. Debug: `var_dump($id);` at method start

### JSON not decoded

**Problem:** `$data` is `null` in POST

**Solutions:**
```php
// ✅ Correct
$data = json_decode(file_get_contents('php://input'), true);

// ❌ Doesn't work with JSON
$data = $_POST; // This is for form-data
```

---

## Future Developments

- [ ] Dependency Injection in controllers
- [ ] DTO with automatic validation
- [ ] Exception filters
- [ ] Response interceptors
- [ ] Controller-specific middleware
- [ ] Automatic API documentation (OpenAPI/Swagger)

---

## Conclusion

Controllers in Sophia offer a modern, declarative way to handle APIs and AJAX requests, while maintaining compatibility with the existing system of Components and Routes.

For questions or contributions, consult the complete framework documentation.
