<?php
namespace Sophia\Router\Models;

/**
 * Interfaccia per i Middleware/Guards
 */
interface MiddlewareInterface
{
    /**
     * Gestisce la richiesta
     *
     * @return bool True per continuare, false per bloccare
     */
    public function handle(): bool;
}