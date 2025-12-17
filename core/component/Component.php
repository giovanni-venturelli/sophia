<?php

#[Attribute(Attribute::TARGET_CLASS)]
class Component
{
    public readonly string $template;
    public readonly array $styles;

    public function __construct(
        string $template,
        array $styles = []
    ) {
        $this->template = $template;
        $this->styles = $styles;
    }
}
