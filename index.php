<?php

use Sophia\Component\ComponentRegistry;
use Sophia\Component\Renderer;
use Sophia\Controller\ControllerRegistry;
use Sophia\Database\ConnectionService;
use Sophia\Injector\Injector;
use Sophia\Router\Router;

// ⚡ PERFORMANCE: Start timing
$_performance_start = microtime(true);
$_performance_memory_start = memory_get_usage();

require __DIR__ . '/vendor/autoload.php';

$basePath = '/sophia';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// ⚡ PERFORMANCE: Enable OPcache in production
if (!($_ENV['DEBUG'] ?? false)) {
    if (function_exists('opcache_get_status')) {
        $status = opcache_get_status();
        if (!$status['opcache_enabled']) {
            error_log('⚠️  OPcache is disabled! Enable it for 2-3x performance boost');
        }
    }
}

$dbConfig = file_exists('config/database.php')
    ? require 'config/database.php'
    : ['driver' => 'sqlite', 'credentials' => ['database' => 'database/app.db']];

$dbService = Injector::inject(ConnectionService::class);
$dbService->configure($dbConfig);

$registry = ComponentRegistry::getInstance();
$templatesPath = __DIR__ . '/pages';

// ⚡ PERFORMANCE: Use realpath cache
$cachePath = realpath(__DIR__ . '/cache/twig') ?: __DIR__ . '/cache/twig';

/** @var Renderer $renderer */
$renderer = Injector::inject(Renderer::class);
$renderer->setRegistry($registry);

// ⚡ PERFORMANCE: Disable cache in debug, enable in production
$isDebug = $_ENV['DEBUG'] ?? false;
$renderer->configure($templatesPath, $isDebug ? '' : $cachePath, 'it', $isDebug);

$renderer->addGlobalStyle('/sophia/css/style.css');
$renderer->addGlobalScripts('/sophia/js/scripts.js');

/** @var Router $router */
$router = Injector::inject(Router::class);
$router->setComponentRegistry($registry);
$router->setControllerRegistry(new ControllerRegistry());
$router->setRenderer($renderer);
$router->setBasePath($basePath);

require __DIR__ . '/routes.php';

try {
    // ⚡ PERFORMANCE: Measure routing
    $_routing_start = microtime(true);

    $router->dispatch();

    $_routing_time = microtime(true) - $_routing_start;
    $_total_time = microtime(true) - $_performance_start;
    $_memory_used = memory_get_usage() - $_performance_memory_start;
    $_memory_peak = memory_get_peak_usage();

    // ⚡ PERFORMANCE: Output metrics in debug mode
    if ($isDebug) {
        echo "\n<!-- PERFORMANCE METRICS:\n";
        echo sprintf("Total Time: %.4f seconds\n", $_total_time);
        echo sprintf("Routing Time: %.4f seconds\n", $_routing_time);
        echo sprintf("Memory Used: %.2f MB\n", $_memory_used / 1024 / 1024);
        echo sprintf("Memory Peak: %.2f MB\n", $_memory_peak / 1024 / 1024);

        if (function_exists('opcache_get_status')) {
            $opcache = opcache_get_status();
            echo sprintf("OPcache: %s\n", $opcache['opcache_enabled'] ? 'ENABLED ✓' : 'DISABLED ✗');
            if ($opcache['opcache_enabled']) {
                echo sprintf("OPcache Hit Rate: %.2f%%\n", $opcache['opcache_statistics']['opcache_hit_rate']);
            }
        }

        echo "-->\n";
    }

} catch (\Throwable $e) {
    http_response_code(500);

    if ($isDebug) {
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "\n\n" .
            htmlspecialchars($e->getTraceAsString()) . "</pre>";
    } else {
        error_log("Application Error: " . $e->getMessage());
        echo "500 - Internal Server Error";
    }
}

// ⚡ PERFORMANCE TIP: Add this to your php.ini for maximum performance:
/*
; OPcache (2-3x faster)
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
opcache.fast_shutdown=1

; Realpath cache (reduces file system calls)
realpath_cache_size=4096K
realpath_cache_ttl=600

; JIT (PHP 8.0+, additional 10-30% boost)
opcache.jit_buffer_size=100M
opcache.jit=tracing
*/