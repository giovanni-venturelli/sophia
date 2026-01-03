<?php

declare(strict_types=1);

namespace Sophia\Controller;

use Attribute;

/**
 * 🔥 Controller decorator (NestJS-style)
 * Marca una classe come Controller
 *
 * Esempio:
 * #[Controller('posts')]
 * class PostController { ... }
 *
 * Il prefisso 'posts' è opzionale e viene usato come base path
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Controller
{
    public function __construct(
        public ?string $prefix = null
    ) {}
}