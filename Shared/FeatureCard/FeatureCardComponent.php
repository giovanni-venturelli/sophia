<?php

namespace Shared\FeatureCard;

use Sophia\Component\Component;
use Sophia\Component\Input;
use Sophia\Injector\Inject;
use App\Services\AppService;

#[Component(
    selector: 'app-feature-card',
    template: 'feature-card.php',
    styles: ['feature-card.css'],
    providers: [AppService::class]
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

    #[Inject] private AppService $appService;

    public function getServiceCount(): int
    {
        return count($this->appService->getItems());
    }

    public function getBorderClass(): string
    {
        return match ($this->color) {
            'blue' => 'border-blue',
            'purple' => 'border-purple',
            'green' => 'border-green',
            'orange' => 'border-orange',
            default => 'border-blue'
        };
    }

    public function getIconColor(): string
    {
        return match ($this->color) {
            'blue' => '#3b82f6',
            'purple' => '#8b5cf6',
            'green' => '#10b981',
            'orange' => '#f59e0b',
            default => '#3b82f6'
        };
    }
}