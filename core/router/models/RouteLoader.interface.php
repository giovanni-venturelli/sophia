<?php
namespace App\Router\Models;

/**
 * Interfaccia per i loader di moduli lazy
 */
interface RouteLoaderInterface
{
    /**
     * Carica e restituisce un array di route configurations
     *
     * @return array Array di configurazioni route
     */
    public function load(): array;
}