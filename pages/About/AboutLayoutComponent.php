<?php

namespace App\Pages\About;

use App\Component\Component;
use App\Component\Slot;
use App\Component\SlotContent;
use Shared\Header\HeaderComponent;
use Shared\Footer\FooterComponent;

#[Component(
    selector: 'app-about-layout',
    template: 'about-layout.html.twig',
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
