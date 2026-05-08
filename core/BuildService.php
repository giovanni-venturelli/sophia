<?php

namespace Sophia\Core;

use Sophia\Component\Component;
use Sophia\Component\ComponentRegistry;
use Sophia\Injector\Injectable;
use Sophia\Router\Router;
use ReflectionClass;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class BuildService
{
    private string $rootDir;
    private string $cacheDir;

    public function __construct(string $rootDir)
    {
        $this->rootDir = rtrim($rootDir, '/\\');
        $this->cacheDir = $this->rootDir . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'build';
        
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
    }

    /**
     * Executes the complete build process
     */
    public function build(array $options = []): array
    {
        $results = [];
        $results['components'] = $this->buildComponentMap($options['components_dirs'] ?? null);
        $results['routes'] = $this->buildRouteCache($options['routes_path'] ?? null);
        $results['di'] = $this->buildDIMap($options['services_dirs'] ?? null);
        
        $this->generateBuildManifest($results);
        
        return $results;
    }

    /**
     * Maps all components to avoid directory scanning and reflection at runtime
     */
    private function buildComponentMap(?array $dirs = null): int
    {
        if ($dirs === null) {
            $dirs = [
                $this->rootDir . DIRECTORY_SEPARATOR . 'Shared',
                $this->rootDir . DIRECTORY_SEPARATOR . 'pages'
            ];
        }
        
        $classes = $this->scanForClasses($dirs);
        $map = [];

        foreach ($classes as $class) {
            try {
                $reflection = new ReflectionClass($class);
                $attr = $reflection->getAttributes(Component::class)[0] ?? null;
                
                if ($attr) {
                    $config = $attr->newInstance();
                    $map[$config->selector] = [
                        'class' => $class,
                        'template' => $config->template,
                        'styles' => $config->styles,
                        'scripts' => $config->scripts,
                        'providers' => $config->providers,
                        'imports' => $config->imports,
                        'config' => $config // Added for the Renderer
                    ];
                }
            } catch (\Throwable) {
                continue;
            }
        }

        $this->saveCache('components_map.php', $map);
        return count($map);
    }

    /**
     * Pre-compiles routes (including external files) into a single array
     */
    private function buildRouteCache(?string $routesPath = null): int
    {
        // Note: This requires routes to be definable statically
        // If routes.php uses external variables, it might be complex.
        // We assume routes.php configures the Router singleton.
        
        $router = Router::getInstance();
        // Load original routes (this might vary depending on how the user defines them)
        if ($routesPath === null) {
            $routesPath = $this->rootDir . DIRECTORY_SEPARATOR . 'routes.php';
        }
        
        if (file_exists($routesPath)) {
            // We isolate the inclusion to not pollute the state
            (static function($router, $path) {
                require $path;
            })($router, $routesPath);
        }

        $reflection = new ReflectionClass($router);
        $prop = $reflection->getProperty('routes');
        $prop->setAccessible(true);
        $routes = $prop->getValue($router);

        $this->saveCache('routes_compiled.php', $routes);
        return count($routes);
    }

    /**
     * Maps root-provided Injectable services
     */
    private function buildDIMap(?array $dirs = null): int
    {
        if ($dirs === null) {
            $servicesDir = $this->rootDir . DIRECTORY_SEPARATOR . 'services';
            $dirs = [$servicesDir];
            
            // Try to find Sophia core classes
            $sophiaCore = __DIR__; // This is current core dir
            if (is_dir($sophiaCore)) {
                $dirs[] = $sophiaCore;
            }
        }

        $classes = $this->scanForClasses($dirs);
        $rootServices = [];

        foreach ($classes as $class) {
            try {
                if (!class_exists($class)) continue;
                $reflection = new ReflectionClass($class);
                $attr = $reflection->getAttributes(Injectable::class)[0] ?? null;
                
                if ($attr && $attr->newInstance()->providedIn === 'root') {
                    $rootServices[] = $class;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        $this->saveCache('di_root_services.php', $rootServices);
        return count($rootServices);
    }

    private function scanForClasses(array $dirs): array
    {
        $classes = [];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) continue;
            
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($files as $file) {
                if ($file->getExtension() === 'php') {
                    $content = file_get_contents($file->getPathname());
                    $namespace = '';
                    if (preg_match('/namespace\s+([^;]+);/i', $content, $m)) {
                        $namespace = trim($m[1]);
                    }
                    
                    if (preg_match('/(?:class|trait|interface)\s+(\w+)/i', $content, $m2)) {
                        $className = $m2[1];
                        $fullClassName = $namespace ? $namespace . '\\' . $className : $className;
                        
                        // Try to trigger autoloader if not already loaded
                        if (!class_exists($fullClassName, true)) {
                            // If it still doesn't exist, we might need to require it manually
                            // because scanForClasses is used to find classes to be cached
                            try {
                                @include_once $file->getPathname();
                            } catch (\Throwable) {
                                // Ignore include errors
                            }
                        }
                        
                        if (class_exists($fullClassName, false) || class_exists($fullClassName, true)) {
                            $classes[] = $fullClassName;
                        }
                    }
                }
            }
        }
        return array_unique($classes);
    }

    private function saveCache(string $filename, mixed $data): void
    {
        // Basic handling for Closures which var_export does not support
        $export = $this->serializeData($data);
        $content = "<?php\n// Generated by Sophia BuildService - " . date('Y-m-d H:i:s') . "\nreturn " . $export . ";\n";
        file_put_contents($this->cacheDir . DIRECTORY_SEPARATOR . $filename, $content);
    }

    private function serializeData(mixed $data): string
    {
        if (is_array($data)) {
            $parts = [];
            foreach ($data as $key => $value) {
                $parts[] = var_export($key, true) . ' => ' . $this->serializeData($value);
            }
            return '[' . implode(', ', $parts) . ']';
        }
        
        if ($data instanceof \Closure) {
            return 'function() { /* Closure not supported in build cache */ }';
        }

        if (is_object($data)) {
            $class = get_class($data);
            $vars = get_object_vars($data);
            $parts = [];
            foreach ($vars as $key => $value) {
                $parts[] = $this->serializeData($value);
            }
            return 'new \\' . $class . '(' . implode(', ', $parts) . ')';
        }
        
        return var_export($data, true);
    }

    private function generateBuildManifest(array $results): void
    {
        $manifest = [
            'timestamp' => time(),
            'version' => '1.0.0',
            'stats' => $results
        ];
        $this->saveCache('manifest.php', $manifest);
    }
}
