<?php
namespace App\Component;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Slot
{
    public function __construct(
        public ?string $name = null
    ) {}
}