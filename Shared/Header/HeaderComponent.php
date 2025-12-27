<?php
namespace Shared\Header;

use Sophia\Component\Component;
use Sophia\Component\Input;
use Sophia\Component\Slot;
use Sophia\Component\SlotContent;

#[Component(
    selector: 'app-header',
    template: 'header.html.twig',
    styles: ['header.css']
)]
class HeaderComponent
{
    #[Input]
    public string $title = 'My App';
    #[Slot(name: 'easyProjection')]
    public ?SlotContent $easyProjection = null;

    public array $navigation = [];

    public function __construct()
    {
        $this->navigation = [
            ['label' => 'Home', 'url' => '/', 'active' => true],
            ['label' => 'Dashboard', 'url' => '/dashboard', 'active' => false],
            ['label' => 'About', 'url' => '/about', 'active' => false],
            ['label' => 'Contact', 'url' => '/contact', 'active' => false],
        ];
    }

    public function getNavigationCount(): int
    {
        return count($this->navigation);
    }
}