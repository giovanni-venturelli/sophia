ConnectionService
=================

**Multi-Driver PDO Database Layer with Fluent QueryBuilder**

#\[Injectable(providedIn: "root")\] • Zero Config • Production Ready

Overview
-----------

**ConnectionService** is a lightweight, multi-driver database service with full QueryBuilder support. Auto-registered as singleton via DI.

*   MySQL SQLite PostgreSQL SQL Server
*   ~150 LOC total • Fluent API • Prepared statements

Installation
---------------

```json
    {
      "require": {
        "twig/twig": "^3.0",
        "ext-pdo": "*",
        "ext-pdo_mysql": "*",
        "ext-pdo_sqlite": "*"
      }
    }

```
Setup (index.php)
--------------------

```php
    // 1. Get injectable instance (auto-created)
    $db = Injector::inject(ConnectionService::class);
    
    // 2. Configure (one time only, this is a pure example, you can provide the configuration from any source you like)
    $dbConfig = file_exists('config/database.php') 
        ? require 'config/database.php'
        : ['driver' => 'sqlite', 'credentials' => ['database' => 'database/app.db']];
    
    $db->configure($dbConfig);
```

config/database.php
----------------------

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

Usage in Components
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
QueryBuilder API
-------------------

### SELECT

```php
    // Basic
    $users = $db->table('users')->get();
    
    // WHERE
    $active = $db->table('users')->where('active', 1)->get();
    
    // JOIN
    $usersWithOrders = $db->table('users')
                         ->leftJoin('orders', 'users.id', '=', 'orders.user_id')
                         ->get();
    
    // Advanced
    $results = $db->table('users')
                 ->join('orders', 'users.id', '=', 'orders.user_id')
                 ->where('orders.total', '>', 100)
                 ->orderBy('users.name', 'DESC')
                 ->limit(5)
                 ->get();

```
### CRUD

```php
    // CREATE
    $id = $db->table('users')->create([
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ]);
    
    // UPDATE
    $db->table('users')->where('id', $id)->update(['active' => 0]);
    
    // DELETE
    $db->table('users')->where('id', $id)->delete();

```
### Raw Queries

```php
    $results = $db->raw("SELECT * FROM users WHERE id = ?", [1]);

```

Features
----------

Zero config

SQLite default

Multi-driver

MySQL/SQLite/Postgres/SQLServer

Fluent QueryBuilder

JOIN, WHERE IN, LIMIT/OFFSET

Dependency Injection

#\[Inject\] ready

Production ready

Prepared statements everywhere

🛡️ Error Handling
------------------

```php
    try {
        $users = $db->table('users')->get();
    } catch (RuntimeException $e) {
        echo "DB Error: " . $e->getMessage();
    }

```
**Lightweight. Powerful. Injectable.** 