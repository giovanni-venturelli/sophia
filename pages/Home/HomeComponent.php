<?php
/**
 * Home Component - Pagina principale dell'applicazione
 */

namespace App\Pages\Home;

use App\Component\Component;
use App\Pages\Shared\HeaderComponent;
use App\Pages\Shared\FooterComponent;
use App\Pages\Shared\FeatureCardComponent;

#[Component(
    selector: 'app-home',
    template: 'Home/home.html.twig',
    imports: [
        HeaderComponent::class,
        FooterComponent::class,
        FeatureCardComponent::class
    ]
)]
class HomeComponent
{
    public string $pageTitle = 'Welcome to Our App';
    public string $subtitle = 'Build amazing things with PHP and Twig';
    public array $features = [];
    public array $stats = [];

    public function __construct()
    {;
        $this->features = $this->loadFeatures();
        $this->stats = $this->loadStats();
    }

    private function loadFeatures(): array
    {
        return [
            [
                'id' => 1,
                'icon' => '⚡',
                'title' => 'Super Fast',
                'description' => 'Lightning fast performance with Twig compilation',
                'color' => 'blue'
            ],
            [
                'id' => 2,
                'icon' => '🎨',
                'title' => 'Beautiful Design',
                'description' => 'Clean and modern UI components',
                'color' => 'purple'
            ],
            [
                'id' => 3,
                'icon' => '🔒',
                'title' => 'Secure',
                'description' => 'Built with security best practices',
                'color' => 'green'
            ],
            [
                'id' => 4,
                'icon' => '📱',
                'title' => 'Responsive',
                'description' => 'Works perfectly on all devices',
                'color' => 'orange'
            ]
        ];
    }

    private function loadStats(): array
    {
        return [
            ['label' => 'Users', 'value' => '10K+'],
            ['label' => 'Projects', 'value' => '500+'],
            ['label' => 'Countries', 'value' => '50+'],
            ['label' => 'Satisfaction', 'value' => '99%']
        ];
    }

    public function getFeaturesCount(): int
    {
        return count($this->features);
    }

    public function getCurrentYear(): int
    {
        return date('Y');
    }

    public function getWelcomeMessage(): string
    {
        $hour = (int)date('H');

        if ($hour < 12) {
            return 'Good morning! ☀️';
        } elseif ($hour < 18) {
            return 'Good afternoon! 🌤️';
        } else {
            return 'Good evening! 🌙';
        }
    }
}