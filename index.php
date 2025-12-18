<?php

// Abilita tutti gli errori per debug
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Normalizza il REQUEST_URI per ambienti subdirectory
$basePath = '/test-route';  // Modifica con il nome della tua cartella

if (isset($_SERVER['REQUEST_URI'])) {
    $uri = $_SERVER['REQUEST_URI'];

    // Rimuovi il base path se presente
    if (str_starts_with($uri, $basePath)) {
        $uri = substr($uri, strlen($basePath));
    }

    // Normalizza
    if ($uri === '') {
        $uri = '/';
    }

    // Sostituisci in $_SERVER
    $_SERVER['REQUEST_URI'] = $uri;
}

require_once __DIR__ . '/vendor/autoload.php';


// Inizializzazione
use App\Router\Router;

$router = Router::getInstance();
//$router->setBasePath($basePath);

require_once __DIR__ . '/routes.php';

$router->dispatch();