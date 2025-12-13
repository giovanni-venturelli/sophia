<?php

/**
 * Interfaccia per i Middleware/Guards
 */
interface MiddlewareInterface
{
    public function handle(): bool;
}