<?php
namespace App\Form\Results;

class RedirectResult
{
    public function __construct(public string $location, public int $status = 302)
    {
    }
}
