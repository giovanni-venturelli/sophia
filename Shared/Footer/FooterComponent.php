<?php
namespace Shared\Footer;

use App\Component\Component;
use App\Component\Input;
use App\Injector\Inject;
use App\Services\AppService;

#[Component(
    selector: 'app-footer',
    template: 'footer.html.twig',
    styles: ['footer.css']
)]
class FooterComponent
{
    #[Input]
    public int $year = 0;

    #[Input]
    public string $companyName = 'My Company';

    public array $socialLinks = [];
    public array $quickLinks = [];

    #[Inject] private AppService $appService;
    public function __construct()
    {
        if ($this->year === 0) {
            $this->year = (int)date('Y');
        }

        $this->socialLinks = [
            ['name' => 'Facebook', 'icon' => '📘', 'url' => '#'],
            ['name' => 'Twitter', 'icon' => '🐦', 'url' => '#'],
            ['name' => 'Instagram', 'icon' => '📷', 'url' => '#'],
            ['name' => 'LinkedIn', 'icon' => '💼', 'url' => '#'],
        ];

        $this->quickLinks = [
            ['label' => 'Privacy Policy', 'url' => '/privacy'],
            ['label' => 'Terms of Service', 'url' => '/terms'],
            ['label' => 'Contact Us', 'url' => '/contact'],
        ];
    }

    public function getCopyrightText(): string
    {
        return "© {$this->year} {$this->companyName}. All rights reserved.";
    }
    public function getServiceCount(): int
    {
        return count($this->appService->getItems());
    }

}