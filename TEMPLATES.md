PHP Templates Guide
===================

Sophia uses native PHP templates with helper functions for safe, efficient rendering.

Quick navigation
----------------
- [Basic syntax](#basic-syntax)
- [Template helpers](#template-helpers)
- [Control structures](#control-structures)
- [Component nesting](#component-nesting)
- [Slots](#slots)
- [Best practices](#best-practices)


Basic syntax
------------

### Output with escape
Always use `$e()` for safe HTML output:
```php
<h1><?= $e($title) ?></h1>
<p><?= $e($description) ?></p>
```

### Raw output
Only for trusted HTML (components, CSRF fields):
```php
<?= $component('app-header') ?>
<?= $csrf_field() ?>
```


Template helpers
----------------

### Core helpers
- `$e($value)` → HTML escape (use for all user data)
- `$component($selector, $bindings, $slotContent?)` → render child component
- `$slot($name, $context?)` → render named slot

### Page helpers
- `$set_title($title)` → set page title
- `$add_meta($name, $content)` → add meta tag

### Routing helpers
- `$url($name, $params?)` → generate URL by route name
- `$route_data($key?)` → get route data

### Form helpers
- `$form_action($name)` → form POST action URL
- `$csrf_field()` → CSRF hidden input
- `$old($field, $default?)` → sticky form values
- `$form_errors($field?)` → validation errors
- `$flash($key)` → flash message (pull)
- `$peek_flash($key)` → flash message (peek)
- `$has_flash($key)` → check flash message


Control structures
------------------

### Conditionals
```php
<?php if ($condition): ?>
  <p>True</p>
<?php elseif ($other): ?>
  <p>Other</p>
<?php else: ?>
  <p>False</p>
<?php endif; ?>
```

### Loops
```php
<?php foreach ($items as $item): ?>
  <div><?= $e($item['name']) ?></div>
<?php endforeach; ?>
```

### Loop variables
```php
<?php foreach ($items as $index => $item): ?>
  <?php $isFirst = ($index === 0); ?>
  <?php $isLast = ($index === count($items) - 1); ?>
  <div class="<?= $isFirst ? 'first' : '' ?>">
    <?= $e($item['name']) ?>
  </div>
<?php endforeach; ?>
```


Component nesting
-----------------

### Basic nesting
```php
<?= $component('app-card', [
  'title' => 'Hello',
  'items' => $items
]) ?>
```

### With slot content
```php
<?php ob_start(); ?>
  <slot name="header">
    <h2>Custom Header</h2>
  </slot>
<?php $slotContent = ob_get_clean(); ?>

<?= $component('app-layout', ['title' => 'Page'], $slotContent) ?>
```


Slots
-----

### Check if slot has content
```php
<?php if ($hasHeader): ?>
  <header><?= $slot('header') ?></header>
<?php endif; ?>
```

### Slot with default content
```php
<div class="content">
  <?= $hasContent ? $slot('content') : '<p>No content</p>' ?>
</div>
```


Best practices
--------------

### Security
- **Always** use `$e()` for user data, database values, URL params
- **Never** use `$e()` for HTML from trusted sources (components, helpers)
- Use `$csrf_field()` in all forms

### Arrays vs Objects
- Use array access: `$item['key']` not `$item->key`
- Components expose arrays to templates

### Performance
- Avoid complex logic in templates
- Use component methods (getters) for computed values
- Keep templates focused on presentation

### Readability
- Use alternative syntax: `<?php if (): ?>...<?php endif; ?>`
- One statement per PHP tag when possible
- Indent nested structures consistently

### Common patterns
```php
<!-- Conditional class -->
<div class="card <?= $isActive ? 'active' : '' ?>">

<!-- Conditional attribute -->
<input type="text" <?= $isDisabled ? 'disabled' : '' ?>>

<!-- Array access -->
<p><?= $e($user['name']) ?></p>

<!-- Nested component with data -->
<?= $component('app-item', [
  'id' => $item['id'],
  'title' => $item['title']
]) ?>

<!-- Form with validation -->
<form method="post" action="<?= $e($form_action('submit')) ?>">
  <?= $csrf_field() ?>
  
  <input type="text" name="email" value="<?= $e($old('email')) ?>">
  <?php if ($form_errors('email')): ?>
    <div class="error">
      <?php foreach ($form_errors('email') as $error): ?>
        <p><?= $e($error) ?></p>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  
  <button type="submit">Submit</button>
</form>
```
