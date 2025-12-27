<?php
namespace Sophia\Component;

use Sophia\Injector\Injector;
use Attribute;


#[Attribute(Attribute::TARGET_CLASS)]
class Component
{
    public function __construct(
        public string $selector,
        public string $template,
        public array $imports = [],
        public array $styles = [],
        public array $scripts = [],
        public array $providers = [],  // Angular-style providers
        public array $meta = []
    ) {}
}

