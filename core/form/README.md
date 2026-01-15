Forms Guide
===========

Sophia provides built-in form handling with CSRF protection, validation, flash messages, and sticky inputs.

Quick navigation
----------------
- [Basic form setup](#basic-form-setup)
- [Form handlers](#form-handlers)
- [Validation](#validation)
- [Flash messages](#flash-messages)
- [Sticky inputs](#sticky-inputs)
- [Complete example](#complete-example)


Basic form setup
----------------

### Route configuration
Add the forms route to `routes.php`:
```php
[
  'path' => 'forms/submit/:token',
  'callback' => [\Sophia\Form\FormController::class, 'handle'],
  'name' => 'forms.submit'
]
```

### Template form
```php
<form method="post" action="<?= $e($form_action('send')) ?>">
  <?= $csrf_field() ?>
  
  <input type="text" name="email" value="<?= $e($old('email')) ?>">
  <button type="submit">Submit</button>
</form>
```


Form handlers
-------------

### Define handler in component
Use `#[FormHandler]` attribute on component methods:
```php
use Sophia\Component\Component;
use Sophia\Form\Attributes\FormHandler;
use Sophia\Form\FormRequest;
use Sophia\Form\Results\RedirectResult;

#[Component(selector: 'app-contact', template: 'contact.php')]
class ContactComponent
{
    #[FormHandler('send')]
    public function onSend(FormRequest $request): RedirectResult
    {
        $data = $request->all();
        $email = $data['email'] ?? '';
        
        // Process form...
        
        return new RedirectResult('/success');
    }
}
```

### FormRequest methods
- `$request->all()` → all form data
- `$request->get($key, $default?)` → single field value
- `$request->has($key)` → check if field exists
- `$request->only($keys)` → subset of fields
- `$request->except($keys)` → all except specified


Validation
----------

### Manual validation
```php
#[FormHandler('send')]
public function onSend(FormRequest $request): RedirectResult
{
    $data = $request->all();
    $errors = [];
    
    $email = trim($data['email'] ?? '');
    if ($email === '') {
        $errors['email'][] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'][] = 'Email is not valid';
    }
    
    if (!empty($errors)) {
        $this->flash->setValue('__errors', $errors);
        $this->flash->setValue('__old', $data);
        return new RedirectResult('/contact');
    }
    
    // Process valid data...
    return new RedirectResult('/success');
}
```

### Display errors in template
```php
<?php if ($form_errors('email')): ?>
  <div class="error">
    <?php foreach ($form_errors('email') as $error): ?>
      <p><?= $e($error) ?></p>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
```


Flash messages
--------------

### Set flash messages
```php
use Sophia\Form\FlashService;
use Sophia\Injector\Inject;

#[Inject] private FlashService $flash;

public function onSend(FormRequest $request): RedirectResult
{
    // Success message
    $this->flash->setValue('success', 'Form submitted successfully!');
    
    // Error message
    $this->flash->setValue('error', 'Something went wrong');
    
    return new RedirectResult('/contact');
}
```

### Display flash messages
```php
<!-- Pull (removes after display) -->
<?php if ($has_flash('success')): ?>
  <div class="alert alert-success">
    <?= $e($flash('success')) ?>
  </div>
<?php endif; ?>

<!-- Peek (keeps for next request) -->
<?php if ($has_flash('error')): ?>
  <div class="alert alert-error">
    <?= $e($peek_flash('error')) ?>
  </div>
<?php endif; ?>
```


Sticky inputs
-------------

### Preserve form values on error
```php
// In handler
if (!empty($errors)) {
    $this->flash->setValue('__old', $data);
    return new RedirectResult('/contact');
}
```

### Display old values in template
```php
<input type="text" name="name" value="<?= $e($old('name')) ?>">
<input type="email" name="email" value="<?= $e($old('email', 'default@example.com')) ?>">
<textarea name="message"><?= $e($old('message')) ?></textarea>
```


Complete example
----------------

### Component
```php
<?php
namespace App\Pages\Contact;

use Sophia\Component\Component;
use Sophia\Form\Attributes\FormHandler;
use Sophia\Form\FlashService;
use Sophia\Form\FormRequest;
use Sophia\Form\Results\RedirectResult;
use Sophia\Injector\Inject;
use Sophia\Router\Router;

#[Component(selector: 'app-contact', template: 'contact.php')]
class ContactComponent
{
    public string $title = 'Contact Us';
    
    #[Inject] private FlashService $flash;
    #[Inject] private Router $router;
    
    #[FormHandler('send')]
    public function onSend(FormRequest $request): RedirectResult
    {
        $data = $request->all();
        $errors = [];
        
        // Validate
        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $message = trim($data['message'] ?? '');
        
        if ($name === '') {
            $errors['name'][] = 'Name is required';
        }
        if ($email === '') {
            $errors['email'][] = 'Email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Email is not valid';
        }
        if ($message === '') {
            $errors['message'][] = 'Message is required';
        }
        
        $contactUrl = $this->router->url('contact');
        
        if (!empty($errors)) {
            $this->flash->setValue('__errors', $errors);
            $this->flash->setValue('__old', $data);
            $this->flash->setValue('error', 'Please correct the errors below');
            return new RedirectResult($contactUrl);
        }
        
        // Process form (send email, save to DB, etc.)
        // ...
        
        // Success
        $this->flash->setValue('__old', []);
        $this->flash->setValue('__errors', []);
        $this->flash->setValue('success', 'Message sent successfully!');
        
        return new RedirectResult($contactUrl);
    }
}
```

### Template
```php
<section class="contact-page">
  <h1><?= $e($title) ?></h1>
  
  <?php if ($has_flash('error')): ?>
    <div class="alert alert-danger">
      <?= $e($flash('error')) ?>
    </div>
  <?php endif; ?>
  
  <?php if ($has_flash('success')): ?>
    <div class="alert alert-success">
      <?= $e($flash('success')) ?>
    </div>
  <?php endif; ?>
  
  <form method="post" action="<?= $e($form_action('send')) ?>">
    <?= $csrf_field() ?>
    
    <div class="form-group">
      <label for="name">Name</label>
      <input type="text" id="name" name="name" value="<?= $e($old('name')) ?>">
      <?php if ($form_errors('name')): ?>
        <ul class="errors">
          <?php foreach ($form_errors('name') as $error): ?>
            <li><?= $e($error) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
    
    <div class="form-group">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" value="<?= $e($old('email')) ?>">
      <?php if ($form_errors('email')): ?>
        <ul class="errors">
          <?php foreach ($form_errors('email') as $error): ?>
            <li><?= $e($error) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
    
    <div class="form-group">
      <label for="message">Message</label>
      <textarea id="message" name="message" rows="5"><?= $e($old('message')) ?></textarea>
      <?php if ($form_errors('message')): ?>
        <ul class="errors">
          <?php foreach ($form_errors('message') as $error): ?>
            <li><?= $e($error) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
    
    <button type="submit">Send</button>
  </form>
</section>
```

### Route
```php
[
  'path' => 'contact',
  'component' => App\Pages\Contact\ContactComponent::class,
  'name' => 'contact'
]
```


Best practices
--------------

- Always use `$csrf_field()` in forms
- Validate all user input server-side
- Use `$e()` for all output in templates
- Clear old data and errors on success
- Provide clear, specific error messages
- Use flash messages for user feedback
- Redirect after POST (Post-Redirect-Get pattern)
