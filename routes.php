<?php

use App\Pages\About\AboutComponent;
use App\Pages\Home\HomeComponent;
use App\Router\Router;

$router = Router::getInstance();

$router->setBasePath('/test-route');

$router->configure([
    // Home page
    [
        'path' => 'home/:id',
        'component' => HomeComponent::class,
        'name' => 'home',
        'data' => [
            'title' => 'Home Page',
            'description' => 'Welcome to our application',
        ],
    ],
    [
        'path' => 'about',
        'children' => [[
            'path' => 'us',
            'component' => AboutComponent::class,
            'data' => [
                'title' => 'Home Page',
                'description' => 'Welcome to our application',
            ],]
        ]
    ],

    // Redirect
    [
        'path' => '/old-page',
        'redirectTo' => '/new-page',
    ],

    // API test
    [
        'path' => '/api/test',
        'callback' => function () {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'ok', 'message' => 'API Working']);
        },
        'name' => 'api.test',
    ],
]);
