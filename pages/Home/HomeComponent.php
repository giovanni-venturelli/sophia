<?php
/**
 * Home Component - Pagina principale dell'applicazione
 */

namespace App\Pages\Home;

use App\Component\Component;
use App\Component\Input;
use App\Injector\Inject;
use App\Services\AppService;
use Shared\FeatureCard\FeatureCardComponent;
use Shared\Footer\FooterComponent;
use Shared\Header\HeaderComponent;

#[Component(
    selector: 'app-home',
    template: 'home.html.twig',
    imports: [
        HeaderComponent::class,
        FooterComponent::class,
        FeatureCardComponent::class
    ],
    styles: ['home.css'],
    providers: [AppService::class]
)]
class HomeComponent
{
    #[Input] public string $id = '';
    #[Inject] private AppService $appService;
    public string $pageTitle = 'Welcome to Our App';
    public string $subtitle = 'Build amazing things with PHP and Twig';
    public array $features = [];
    public array $stats = [];

    public function __construct()
    {
        $this->features = $this->loadFeatures();
        $this->stats = $this->loadStats();
    }


    public function onInit(): void
    {
        $this->appService->addItems(['item1', 'item2', 'item3']);
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

    public function getServiceCount(): int
    {
        return count($this->appService->getItems());
    }
}