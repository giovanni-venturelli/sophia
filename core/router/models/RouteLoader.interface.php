<?php
namespace App\Router\Models;
/**
 * Interfaccia per i loader di moduli lazy
 */
interface RouteLoaderInterface
{
    public function load(): array;
}
