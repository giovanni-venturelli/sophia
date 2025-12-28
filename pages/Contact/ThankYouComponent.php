<?php
namespace App\Pages\Contact;

use Sophia\Component\Component;
use Sophia\Component\Renderer;
use Sophia\Router\Router;

#[Component(
    selector: 'app-contact-thank-you',
    template: 'thank-you.html.twig',
    styles: ['thank-you.css']
)]
class ThankYouComponent
{
    public string $title = 'Thank you!';
    public string $message = 'Your message has been sent successfully. We will get back to you soon.';

    public function getContactUrl() {
        $router = Router::getInstance();
        return  $router->url('contact');
    }
}
