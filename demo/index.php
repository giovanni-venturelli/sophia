<?php
use Sophia\Component\ComponentRegistry;
use Sophia\Component\Renderer;
use Sophia\Database\ConnectionService;
use Sophia\Injector\Injector;
use Sophia\Router\Router;

require __DIR__ . '/vendor/autoload.php';

// Determine project root (one level up from /demo)
$projectRoot = dirname(__DIR__);

// Base path for the demo when served under a subfolder, adjust if needed
$basePath = '/test-route/demo';

// Load environment from project root (so you can reuse existing .env)
if (class_exists(Dotenv\Dotenv::class)) {
    $dotenv = Dotenv\Dotenv::createImmutable($projectRoot);
    if (file_exists($projectRoot . '/.env')) {
        $dotenv->load();
    }
}

// Database config from project root config directory (fallback to sqlite in ./database)
$dbConfig = file_exists($projectRoot . '/config/database.php')
    ? require $projectRoot . '/config/database.php'
    : ['driver' => 'sqlite', 'credentials' => ['database' => $projectRoot . '/database/app.db']];

// Configure DB service (root singleton)
$dbService = Injector::inject(ConnectionService::class);
$dbService->configure($dbConfig);

$registry = ComponentRegistry::getInstance();
$templatesPath = $projectRoot . '/pages';
$cachePath     = $projectRoot . '/cache/twig';

$renderer = new Renderer(
    $registry,
    $templatesPath,
    $cachePath,
    'it',
    true
);

// Reuse global assets from project root
$renderer->addGlobalStyle('/test-route/css/style.css');
$renderer->addGlobalScripts('/test-route/js/scripts.js');

$router = Router::getInstance();
$router->setComponentRegistry($registry);
$router->setRenderer($renderer);
$router->setBasePath($basePath);

// Load routes from project root, then override base path for demo
require $projectRoot . '/routes.php';
$router->setBasePath($basePath);

try {
    $router->dispatch();
} catch (\Throwable $e) {
    http_response_code(500);
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "\n\n" .
        htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
