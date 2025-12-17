<?php
namespace App\Router\Models;
/**
 * Interfaccia per i Middleware/Guards
 */
interface MiddlewareInterface
{
    public function handle(): bool;
}