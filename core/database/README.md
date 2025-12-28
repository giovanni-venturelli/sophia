ConnectionService + Entity ORM
==============================

Multi-Driver PDO + Fluent QueryBuilder + Active Record ORM  
#\[Injectable(providedIn: "root")\] • Zero Config • Production Ready

### Quick Navigation

* [Installation](#installation)
* [Setup](#setup-indexphp)
* [ConnectionService](#connectionservice)
* [Entity ORM](#entity-orm)
* [Query Examples](#query-examples)
* [Component Usage](#component-integration)


Installation
------------
Add PDO extensions (and drivers you need) and Twig to your project. Dotenv is optional.
```json
{
  "require": {
    "php": ">=8.1",
    "twig/twig": "^3.22",
    "ext-pdo": "*",
    "ext-pdo_mysql": "*",
    "ext-pdo_sqlite": "*"
  }
}
```
Setup (index.php)
-----------------
```php
    // 1. Auto-load env (optional)
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    
    // 2. Configure DB (auto-injected)
    $dbConfig = file_exists('config/database.php') 
        ? require 'config/database.php'
        : ['driver' => 'sqlite', 'credentials' => ['database' => 'database/app.db']];
    
    $dbService = Injector::inject(ConnectionService::class);
    $dbService->configure($dbConfig);
```
### config/database.php
```php
    <?php
    // MySQL
    return [
        'driver' => 'mysql',
        'credentials' => [
            'host' => 'localhost',
            'database' => 'myapp',
            'username' => 'root',
            'password' => ''
        ]
    ];
    
    // SQLite (default)
    return [
        'driver' => 'sqlite', 
        'credentials' => ['database' => 'database/app.db']
    ];
```
ConnectionService
-----------------

#\[Inject\] ready. Fluent QueryBuilder integrato.

#### Component Usage
```php
    class UserListComponent {
        #[Inject] private ConnectionService $db;
        
        public function getActiveUsers(): array {
            return $this->db->table('users')
                           ->where('active', 1)
                           ->orderBy('name')
                           ->limit(10)
                           ->get();
        }
    }
```
### QueryBuilder Methods

| Method        | Example                             | Returns        |
|---------------|-------------------------------------|----------------|
| `table('name')` | `$db->table('users')`              | QueryBuilder  |
| `where()`     | `->where('age', '>', 18)`            | QueryBuilder  |
| `whereIn()`   | `->whereIn('id', [1,2,3])`           | QueryBuilder  |
| `join()`      | `->leftJoin('orders', ...)`          | QueryBuilder  |
| `get()`       | `->get()`                            | array          |
| `create()`    | `->create(['name' => 'John'])`       | ID             |

Entity ORM
----------

Active Record pattern. Estendi Entity per ogni modello.

#### Model Definition
```php
    <?php
    namespace Sophia\Models;
    
    use Sophia\Database\Entity;
    
    class Post extends Entity
    {
        protected static string $table = 'posts';
        
        protected static array $fillable = [
            'title', 'content', 'status'
        ];
    }
```
### Core Methods

| Method            | Example               | Returns    |
|-------------------|-----------------------|------------|
| `::all()`         | `Post::all()`         | `Post[]`   |
| `::find()`        | `Post::find(1)`       | `Post\|null` |
| `::create()`      | `Post::create([...])` | `Post`     |
| `$model->save()`  | `$post->save()`       | `bool`     |


Query Examples
--------------

### Entity Queries (Laravel-style)
```php
    // Published posts
    $posts = Post::where('status', 'published')->get();
    
    // Featured post
    $featured = Post::where('featured', 1)->first();
    
    // Complex chain
    $recent = Post::where('status', 'published')
                 ->where('created_at', '>=', '2025-01-01')
                 ->orderBy('created_at', 'DESC')
                 ->limit(10)
                 ->get();
```
### CRUD Operations
```php
    // CREATE
    $post = Post::create([
        'title' => 'New Post',
        'content' => 'Lorem ipsum...',
        'status' => 'published'
    ]);
    
    // UPDATE
    $post->title = 'Updated Title';
    $post->save();
    
    // DELETE
    $post->delete();
```
Component Integration
---------------------

#### HomeComponent.php
```php
    class HomeComponent {
        public array $posts = [];
        
        public function onInit(): void {
            $this->posts = Post::where('status', 'published')
                              ->orderBy('created_at', 'DESC')
                              ->limit(5)
                              ->get();
        }
    }
```
#### home.html.twig
```html
    {% for post in posts %}
    <article>
        <h3>{{ post.title }}</h3>
        <p>{{ post.content|slice(0,150) }}...</p>
    </article>
    {% endfor %}
```
