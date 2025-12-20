<?php

use App\Component\ComponentRegistry;
use App\Component\Renderer;
use App\Router\Router;

require __DIR__ . '/vendor/autoload.php';

$registry = ComponentRegistry::getInstance();

$templatesPath = __DIR__ . '/pages';
$cachePath     = __DIR__ . '/cache/twig';

$renderer = new Renderer(
    $registry,
    $templatesPath,
    $cachePath,
    true
);

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
