<?php

declare(strict_types=1);

namespace Sophia\Controller;


use Attribute;

/**
 * 🔥 Base decorator per i metodi HTTP
 * Memorizza il metodo HTTP e il path opzionale
 */
#[Attribute(Attribute::TARGET_METHOD)]
class HttpMethod
{
    public function __construct(
        public string $method,
        public ?string $path = null
    ) {}
}

/**
 * 🔥 GET decorator
 * #[Get()] o #[Get(':id')]
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Get extends HttpMethod
{
    public function __construct(?string $path = null)
    {
        parent::__construct('GET', $path);
    }
}

/**
 * 🔥 POST decorator
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Post extends HttpMethod
{
    public function __construct(?string $path = null)
    {
        parent::__construct('POST', $path);
    }
}

/**
 * 🔥 PUT decorator
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Put extends HttpMethod
{
    public function __construct(?string $path = null)
    {
        parent::__construct('PUT', $path);
    }
}

/**
 * 🔥 DELETE decorator
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Delete extends HttpMethod
{
    public function __construct(?string $path = null)
    {
        parent::__construct('DELETE', $path);
    }
}

/**
 * 🔥 PATCH decorator
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Patch extends HttpMethod
{
    public function __construct(?string $path = null)
    {
        parent::__construct('PATCH', $path);
    }
}