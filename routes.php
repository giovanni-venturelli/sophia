<?php

use App\Router\Models\RouteModule;
use App\Router\Router;

$router = Router::getInstance();

// Registrazione moduli
$adminModule = new RouteModule('admin', [
    'prefix' => '/admin'
]);

$adminModule->addRoute([
    'path' => '/dashboard',
    'component' => function () {
        echo "Admin Dashboard";
    },
    'name' => 'admin.dashboard'
]);
$adminModule->addRoute([
    'path' => '/users',
    'component' => function () {
        echo "User Management";
    },
    'name' => 'admin.users'
]);
$adminModule->addRoute([
    'path' => '/settings',
    'component' => function () {
        echo "Settings";
    },
    'name' => 'admin.settings'
]);

$router->registerModule($adminModule);

// API Module
$apiModule = new RouteModule('api', [
    'prefix' => '/api/v1',
]);

$apiModule->addRoute([
    'path' => '/users',
    'method' => 'GET',
    'component' => function () {
        return json_encode(['users' => []]);
    },
    'name' => 'api.users.index'
]);

$apiModule->addRoute([
    'path' => '/users/:id',
    'method' => 'GET',
    'component' => function ($id) {
        return json_encode(['id' => $id]);
    },
    'name' => 'api.users.show'
]);

$router->registerModule($apiModule);

// Configurazione route principale
$router->configure([
// Homepage
    [
        'path' => '/',
        'component' => function () {
            echo "<h1>Benvenuto</h1>";
            echo "<p><a href='" . Router::getInstance()->url('user.profile') . "'>Profilo</a></p>";
            echo "<p><a href='" . Router::getInstance()->url('news') . "'>News</a></p>";
        },
        'name' => 'home'
    ],
// news
    [
        'path' => '/news',
        'component' => function () {
            require_once __DIR__ . '/pages/news.php';
        },
        'name' => 'news'
    ],

// Area utente con nesting
    [
        'path' => '/user',
        'children' => [
            [
                'path' => '/profile',
                'component' => function () {
                    require_once __DIR__ . '/pages/archive.php';
                }, // Punta direttamente al componente
                'data' => [
                    'pageTitle' => 'Home Page - Benvenuto'
                ],
                'name' => 'user.profile'
            ],
            [
                'path' => '/settings',
                'children' => [
                    [
                        'path' => '/general',
                        'component' => function () {
                            echo "General Settings";
                        },
                        'name' => 'user.settings.general'
                    ],
                    [
                        'path' => '/security',
                        'component' => function () {
                            echo "Security Settings";
                        },
                        'name' => 'user.settings.security'
                    ]
                ]
            ]
        ]
    ],

//    // Importa modulo registrato
//    [
//        'path' => '',
//        'imports' => ['admin', 'api']
//    ],

// Lazy loading da file
//    [
//        'path' => '/blog',
//        'loadChildren' => new FileRouteLoader(__DIR__ . '/blog_routes.php'),
//        'name' => 'blog'
//    ],

// Redirect
    [
        'path' => '/old-home',
        'redirectTo' => '/',
        'name' => 'old.home'
    ],

// 404 custom
    [
        'path' => '*',
        'component' => function () {
            http_response_code(404);
            echo "<h1>Pagina non trovata</h1>";
        },
        'name' => '404'
    ]
]);