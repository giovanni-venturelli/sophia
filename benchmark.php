<?php

require __DIR__ . '/vendor/autoload.php';

use Sophia\Core\BuildService;
use Sophia\Component\ComponentRegistry;
use Sophia\Router\Router;
use Sophia\Injector\Injector;
use Sophia\Component\Renderer;
use Sophia\Controller\ControllerRegistry;
use Sophia\Database\ConnectionService;

// Mock environment
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/sophia/home/1';

/**
 * Function to reset singletons and simulate a new clean request
 */
function reset_framework() {
    // Reset Injector
    $reflection = new ReflectionClass(Injector::class);
    $prop = $reflection->getProperty('rootInstances');
    $prop->setAccessible(true);
    $prop->setValue([]);
    
    $propScopes = $reflection->getProperty('treeScopes');
    $propScopes->setAccessible(true);
    $propScopes->setValue([]);

    // Reset ComponentRegistry
    $reflectionReg = new ReflectionClass(ComponentRegistry::class);
    $instReg = $reflectionReg->getProperty('instance');
    $instReg->setAccessible(true);
    $instReg->setValue(null, null);

    // Reset Router
    $reflectionRouter = new ReflectionClass(Router::class);
    $instRouter = $reflectionRouter->getProperty('instance');
    $instRouter->setAccessible(true);
    $instRouter->setValue(null, null);
}

/**
 * Executes the framework bootstrap
 */
function run_app($isDebug, $rootDir) {
    $buildCacheDir = $rootDir . '/cache/build';
    $isProduction = !$isDebug;

    // 1. Database Service (Minimum required)
    $dbService = Injector::inject(ConnectionService::class);
    // Note: configuration omitted for benchmark speed
    
    // 2. Component Registry
    $registry = ComponentRegistry::getInstance();
    if ($isProduction && file_exists($buildCacheDir . '/manifest.php')) {
        $registry->loadFromCache($buildCacheDir . '/components_map.php');
    } else {
        $registry->boot($rootDir . '/Shared');
    }
    
    // Warmup registry for each iteration by accessing home component
    $homeEntry = $registry->get('app-home') ?? $registry->get('home');
    
    // 3. Renderer
    $renderer = Injector::inject(Renderer::class);
    $renderer->setRegistry($registry);
    $renderer->configure($rootDir . '/pages', '', 'it', $isDebug);
    
    // 4. Router
    $router = Router::getInstance();
    $router->setComponentRegistry($registry);
    $router->setControllerRegistry(new ControllerRegistry());
    $router->setRenderer($renderer);
    $router->setBasePath('sophia');
    
    if ($isProduction && file_exists($buildCacheDir . '/routes_compiled.php')) {
        $router->loadFromCache($buildCacheDir . '/routes_compiled.php');
    } else {
        // Mocking routes.php loading to avoid actual include for each iteration 
        // in a real scenario this would be: require $rootDir . '/routes.php';
        // Per il benchmark carichiamo le rotte una volta e le riutilizziamo
        $router->configure([
            ['path' => 'home/:id', 'component' => 'App\Pages\Home\HomeComponent']
            // ... altre rotte mockate per velocità
        ]);
    }
    
    // 5. Dispatch (without output)
    ob_start();
    try {
        $router->dispatch();
    } catch (\Throwable $e) {}
    ob_end_clean();
}

function benchmark($isDebug, $iterations = 200) {
    $rootDir = __DIR__;
    
    // Warmup
    reset_framework();
    run_app($isDebug, $rootDir);
    
    $start = microtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        reset_framework();
        run_app($isDebug, $rootDir);
    }
    $end = microtime(true);
    
    return ($end - $start) / $iterations;
}

echo "Sophia Framework Benchmark\n";
echo "==========================\n";
echo "Running 200 iterations per mode...\n\n";

$timeDebug = benchmark(true);
$timeProd = benchmark(false);

echo "Results (average bootstrap + routing time):\n";
echo "DEBUG (Runtime scanning): " . number_format($timeDebug * 1000, 4) . " ms\n";
echo "PROD  (Compiled cache):   " . number_format($timeProd * 1000, 4) . " ms\n";

$improvement = (($timeDebug - $timeProd) / $timeDebug) * 100;
echo "\nPerformance improvement: " . number_format($improvement, 2) . "%\n";

if ($timeProd < $timeDebug) {
    echo "✓ PROD mode is " . number_format($timeDebug / $timeProd, 2) . "x faster\n";
}
