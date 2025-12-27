<?php
namespace App\Form\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class FormHandler
{
    public function __construct(public string $name)
    {
    }
}
