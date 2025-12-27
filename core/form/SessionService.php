<?php
namespace App\Form;

use App\Injector\Injectable;

#[Injectable(providedIn: 'root')]
class SessionService
{
    public function __construct()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            // Safe to start at bootstrap of DI root services
            session_start();
        }
    }
}
