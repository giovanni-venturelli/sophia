<?php
namespace App\Pages\Contact;

use App\Component\Component;
use App\Form\Attributes\FormHandler;
use App\Form\FlashService;
use App\Injector\Inject;
use App\Form\FormRequest;
use App\Form\Results\RedirectResult;
use App\Router\Router;

#[Component(
    selector: 'app-contact',
    template: 'contact.html.twig',
    styles: ['contact.css']
)]
class ContactComponent
{
    public string $title = 'Contact Us';

    #[Inject]
    private FlashService $flash;

    #[FormHandler('send')]
    public function onSend(FormRequest $request): RedirectResult
    {
        $data = $request->all();
        $errors = [];

        $name = trim((string)($data['name'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));
        $message = trim((string)($data['message'] ?? ''));

        if ($name === '') { $errors['name'][] = 'Name is required'; }
        if ($email === '') { $errors['email'][] = 'Email is required'; }
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors['email'][] = 'Email is not valid'; }
        if ($message === '') { $errors['message'][] = 'Message is required'; }

        $router = Router::getInstance();
        $base = rtrim($router->getBasePath() ?: '', '/');
        $contactUrl = ($base !== '' ? $base : '') . $router->url('contact');
        $thankYouUrl = ($base !== '' ? $base : '') . $router->url('contact.thankyou');

        if (!empty($errors)) {
            // Persist errors and old input
            $this->flash->setValue('__errors', $errors);
            $this->flash->setValue('__old', [
                'name' => $name,
                'email' => $email,
                'message' => $message,
            ]);
            $this->flash->setValue('error', 'Please correct the errors below');
            return new RedirectResult($contactUrl);
        }

        // Success: clear old form data and errors, set success message and pass submitted data to thank-you
        $this->flash->setValue('__old', []);
        $this->flash->setValue('__errors', []);
        // Store submitted data for the thank-you page
        $this->flash->setValue('__submitted', [
            'name' => $name,
            'email' => $email,
            'message' => $message,
        ]);
        $this->flash->setValue('success', 'Message sent successfully!');
        return new RedirectResult($thankYouUrl);
    }
}
