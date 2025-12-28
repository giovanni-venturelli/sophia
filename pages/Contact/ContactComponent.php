<?php
namespace App\Pages\Contact;

use Sophia\Component\Component;
use Sophia\Form\Attributes\FormHandler;
use Sophia\Form\FlashService;
use Sophia\Injector\Inject;
use Sophia\Form\FormRequest;
use Sophia\Form\Results\RedirectResult;
use Sophia\Router\Router;

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
    #[Inject]
    private Router $router;

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

        $router = $this->router;
        $contactUrl = $router->url('contact');
        $thankYouUrl = $router->url('contact.thankyou');

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
