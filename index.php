<?php

use Sophia\Component\ComponentRegistry;
use Sophia\Component\Renderer;
use Sophia\Database\ConnectionService;
use Sophia\Injector\Injector;
use Sophia\Router\Router;

require __DIR__ . '/vendor/autoload.php';

$basePath = '/test-route';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$dbConfig = file_exists('config/database.php')
    ? require 'config/database.php'
    : ['driver' => 'sqlite', 'credentials' => ['database' => 'database/app.db']];

$dbService = Injector::inject(ConnectionService::class);  // Auto-creato da #[Injectable]
$dbService->configure($dbConfig);

$registry = ComponentRegistry::getInstance();
$templatesPath = __DIR__ . '/pages';
$cachePath     = __DIR__ . '/cache/twig';

$renderer = new Renderer(
    $registry,
    $templatesPath,
    $cachePath,
    'it',
    true
);
$renderer->addGlobalStyle('/test-route/css/style.css');
$renderer->addGlobalScripts('/test-route/js/scripts.js');
$router = Router::getInstance();

$router->setComponentRegistry($registry);
$router->setRenderer($renderer);
$router->setBasePath('/test-route');

require __DIR__ . '/routes.php';

try {
    $router->dispatch();
} catch (\Throwable $e) {
    http_response_code(500);
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "\n\n" .
        htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
