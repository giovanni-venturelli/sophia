<?php

namespace App\Pages\About;

use Sophia\Component\Component;
use Sophia\Component\Slot;
use Sophia\Component\SlotContent;
use Shared\Header\HeaderComponent;
use Shared\Footer\FooterComponent;

#[Component(
    selector: 'app-about-layout',
    template: 'about-layout.php',
    imports: [
        HeaderComponent::class,
        FooterComponent::class
    ], styles: ['about-layout.css']
)]
class AboutLayoutComponent
{
    public string $title = 'About';

    // Outlet per proiettare il child
    #[Slot('outlet')]
    public ?SlotContent $outlet = null;
}
