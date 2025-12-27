<?php

use App\Pages\About\AboutComponent;
use App\Pages\About\AboutLayoutComponent;
use App\Pages\Contact\ThankYouComponent;
use App\Pages\Home\HomeComponent;
use App\Pages\Contact\ContactComponent;
use Sophia\Form\FormController;
use Sophia\Router\Router;

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

    // Contact page with POST form example
    [
        'path' => 'contact',
        'component' => ContactComponent::class,
        'name' => 'contact'
    ],
    [
        'path' => 'contact/thank-you',
        'component' => ThankYouComponent::class,
        'name' => 'contact.thankyou'
    ],

    // Forms submit endpoint (POST)
    [
        'path' => 'forms/submit/:token',
        'callback' => [FormController::class, 'handle'],
        'name' => 'forms.submit'
    ],

    // About section with nested routes
    [
        'path' => 'about',
        'component' => AboutLayoutComponent::class,
        'children' => include __DIR__ . '/pages/About/routes.php'
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
