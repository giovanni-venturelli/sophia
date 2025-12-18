<?php
namespace App\Component;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Component
{
    public function __construct(
        public string  $selector,
        public ?string $template = null,
        public array   $styles = [],
        public array   $meta = []
    ) {}
}