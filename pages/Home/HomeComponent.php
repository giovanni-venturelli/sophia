<?php
/**
 * Home Component - Pagina principale dell'applicazione
 */

namespace App\Pages\Home;

use Sophia\Component\Component;
use Sophia\Component\Input;
use Sophia\Database\ConnectionService;
use Sophia\Injector\Inject;
use App\Pages\Home\Models\Post;
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
    scripts: ['home.js'],
    providers: [AppService::class]
)]
class HomeComponent
{
    #[Input] public string $id = '';
    #[Inject] private AppService $appService;
    #[Inject] private ConnectionService $connectionService;
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

    public function getPosts(){
        return Post::all();
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
            ],
            [
                'id' => 5,
                'icon' => '🚀',
                'title' => 'Scalable',
                'description' => 'Grows with your business needs',
                'color' => 'red'
            ],
            [
                'id' => 6,
                'icon' => '💎',
                'title' => 'Premium Quality',
                'description' => 'Enterprise-grade code standards',
                'color' => 'indigo'
            ],
            [
                'id' => 7,
                'icon' => '🌐',
                'title' => 'Global Ready',
                'description' => 'Multi-language and timezone support',
                'color' => 'cyan'
            ],
            [
                'id' => 8,
                'icon' => '⚙️',
                'title' => 'Customizable',
                'description' => 'Flexible configuration options',
                'color' => 'gray'
            ],
            [
                'id' => 9,
                'icon' => '📊',
                'title' => 'Analytics',
                'description' => 'Comprehensive data insights',
                'color' => 'pink'
            ],
            [
                'id' => 10,
                'icon' => '🎯',
                'title' => 'Precise',
                'description' => 'Accurate and reliable results',
                'color' => 'yellow'
            ],
            [
                'id' => 11,
                'icon' => '🛠️',
                'title' => 'Easy Setup',
                'description' => 'Get started in minutes',
                'color' => 'blue'
            ],
            [
                'id' => 12,
                'icon' => '💡',
                'title' => 'Innovative',
                'description' => 'Cutting-edge technology stack',
                'color' => 'amber'
            ],
            [
                'id' => 13,
                'icon' => '🔄',
                'title' => 'Auto Updates',
                'description' => 'Always up-to-date automatically',
                'color' => 'teal'
            ],
            [
                'id' => 14,
                'icon' => '📚',
                'title' => 'Well Documented',
                'description' => 'Extensive documentation available',
                'color' => 'brown'
            ],
            [
                'id' => 15,
                'icon' => '🤝',
                'title' => 'Great Support',
                'description' => '24/7 customer assistance',
                'color' => 'lime'
            ],
            [
                'id' => 16,
                'icon' => '🎭',
                'title' => 'Themeable',
                'description' => 'Multiple theme options included',
                'color' => 'violet'
            ],
            [
                'id' => 17,
                'icon' => '🔔',
                'title' => 'Notifications',
                'description' => 'Real-time alert system',
                'color' => 'rose'
            ],
            [
                'id' => 18,
                'icon' => '💾',
                'title' => 'Cloud Backup',
                'description' => 'Automatic data backup',
                'color' => 'sky'
            ],
            [
                'id' => 19,
                'icon' => '🎮',
                'title' => 'Interactive',
                'description' => 'Engaging user experience',
                'color' => 'fuchsia'
            ],
            [
                'id' => 20,
                'icon' => '🌟',
                'title' => 'Premium Features',
                'description' => 'Advanced functionality included',
                'color' => 'emerald'
            ],
            [
                'id' => 21,
                'icon' => '🔍',
                'title' => 'Smart Search',
                'description' => 'Powerful search capabilities',
                'color' => 'slate'
            ],
            [
                'id' => 22,
                'icon' => '📈',
                'title' => 'Growth Tools',
                'description' => 'Built-in marketing features',
                'color' => 'red'
            ],
            [
                'id' => 23,
                'icon' => '🎪',
                'title' => 'Fun to Use',
                'description' => 'Delightful user interface',
                'color' => 'orange'
            ],
            [
                'id' => 24,
                'icon' => '🏆',
                'title' => 'Award Winning',
                'description' => 'Recognized for excellence',
                'color' => 'yellow'
            ],
            [
                'id' => 25,
                'icon' => '🌈',
                'title' => 'Colorful',
                'description' => 'Vibrant color schemes',
                'color' => 'pink'
            ],
            [
                'id' => 26,
                'icon' => '⏱️',
                'title' => 'Time Saving',
                'description' => 'Automated workflows',
                'color' => 'indigo'
            ],
            [
                'id' => 27,
                'icon' => '🎁',
                'title' => 'Bonus Content',
                'description' => 'Extra templates and resources',
                'color' => 'purple'
            ],
            [
                'id' => 28,
                'icon' => '🔐',
                'title' => 'Privacy First',
                'description' => 'GDPR compliant by default',
                'color' => 'green'
            ],
            [
                'id' => 29,
                'icon' => '🌙',
                'title' => 'Dark Mode',
                'description' => 'Eye-friendly dark theme',
                'color' => 'zinc'
            ],
            [
                'id' => 30,
                'icon' => '✨',
                'title' => 'Magic Touch',
                'description' => 'Smooth animations and transitions',
                'color' => 'cyan'
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

    public function dump($var) {
        var_dump($var);
    }
}