<?php
namespace App\Pages\Shared\FeatureCard;

use App\Component\Component;
use App\Component\Input;

#[Component(
    selector: 'app-feature-card',
    template: 'feature-card.html.twig',
    styles: ['feature-card.css']
)]
class FeatureCardComponent
{
    #[Input]
    public string $icon = '📦';

    #[Input]
    public string $title = '';

    #[Input]
    public string $description = '';

    #[Input]
    public string $color = 'blue';

    #[Input]
    public bool $isFirst = false;

    #[Input]
    public bool $isLast = false;

    #[Input]
    public int $index = 0;

    public function getBorderClass(): string
    {
        return match($this->color) {
            'blue' => 'border-blue',
            'purple' => 'border-purple',
            'green' => 'border-green',
            'orange' => 'border-orange',
            default => 'border-blue'
        };
    }

    public function getIconColor(): string
    {
        return match($this->color) {
            'blue' => '#3b82f6',
            'purple' => '#8b5cf6',
            'green' => '#10b981',
            'orange' => '#f59e0b',
            default => '#3b82f6'
        };
    }
}