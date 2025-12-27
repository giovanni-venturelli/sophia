<?php
namespace Sophia\Component;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Input
{
    public function __construct(
        public ?string $alias = null
    ) {}
}