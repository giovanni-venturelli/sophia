<?php

/**
 * Angular-style Injectable attribute
 * #[Injectable(providedIn: 'root')] = global singleton
 */

namespace App\Injector;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Injectable
{
    public function __construct(
        public ?string $providedIn = null  // 'root' = global singleton
    )
    {
    }
}