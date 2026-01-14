# Migrazione da Twig a PHP Nativo

## Panoramica
Il framework Sophia è stato migrato da Twig a template PHP nativi, mantenendo la struttura a componenti e tutte le funzionalità esistenti.

## Modifiche Principali

### 1. Renderer.php
- **Rimosso**: Dipendenze Twig (`Twig\Environment`, `Twig\Loader\FilesystemLoader`, `Twig\TwigFunction`)
- **Rimosso**: Metodi `initTwig()`, `registerCustomFunctions()`, `getTwig()`
- **Aggiunto**: Metodo `renderPhpTemplate()` che usa output buffering e `include` PHP nativo
- **Modificato**: `resolveTemplatePath()` ora restituisce il path completo del file invece del basename

### 2. Funzioni Helper nei Template
Tutte le funzioni Twig sono state convertite in funzioni PHP disponibili nei template:

| Twig | PHP Nativo |
|------|------------|
| `{{ variabile }}` | `<?= $e($variabile) ?>` |
| `{{ variabile\|raw }}` | `<?= $variabile ?>` |
| `{% for item in items %}` | `<?php foreach ($items as $item): ?>` |
| `{% endfor %}` | `<?php endforeach; ?>` |
| `{% if condition %}` | `<?php if ($condition): ?>` |
| `{% endif %}` | `<?php endif; ?>` |
| `{% set var %}...{% endset %}` | `<?php ob_start(); ?>...<?php $var = ob_get_clean(); ?>` |
| `{{ component('selector', {...}) }}` | `<?= $component('selector', [...]) ?>` |
| `{{ slot('name') }}` | `<?= $slot('name') ?>` |
| `{{ form_action('name') }}` | `<?= $e($form_action('name')) ?>` |
| `{{ csrf_field()\|raw }}` | `<?= $csrf_field() ?>` |
| `{{ flash('key') }}` | `<?= $e($flash('key')) ?>` |
| `{{ has_flash('key') }}` | `<?php if ($has_flash('key')): ?>` |
| `{{ old('field') }}` | `<?= $e($old('field')) ?>` |
| `{{ form_errors('field') }}` | `<?php $errs = $form_errors('field'); ?>` |
| `{{ url('route') }}` | `<?= $e($url('route')) ?>` |
| `{{ route_data('key') }}` | `<?= $e($route_data('key')) ?>` |
| `loop.first` | `$__loop_first` |
| `loop.last` | `$__loop_last` |
| `loop.index` | `$__loop_index + 1` |

### 3. Funzione $e()
La funzione `$e()` è l'equivalente di `{{ }}` in Twig e fornisce l'escape HTML automatico:
```php
$e = function($value) {
    if ($value === null) return '';
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
```

### 4. Template Convertiti
Tutti i template `.html.twig` sono stati convertiti in `.php`:
- `home.html.twig` → `home.php`
- `about.html.twig` → `about.php`
- `contact.html.twig` → `contact.php`
- `header.html.twig` → `header.php`
- `footer.html.twig` → `footer.php`
- `feature-card.html.twig` → `feature-card.php`
- `thank-you.html.twig` → `thank-you.php`
- `about-layout.html.twig` → `about-layout.php`

### 5. Component Aggiornati
Tutti i Component sono stati aggiornati per usare l'estensione `.php`:
```php
// Prima
#[Component(
    selector: 'app-home',
    template: 'home.html.twig',
    ...
)]

// Dopo
#[Component(
    selector: 'app-home',
    template: 'home.php',
    ...
)]
```

### 6. Composer.json
- **Rimosso**: `"twig/twig": "^3.22"`
- **Aggiornato**: Descrizione e keywords per riflettere l'uso di PHP nativo

## Esempio di Conversione

### Prima (Twig)
```twig
<h1>{{ pageTitle }}</h1>
{% for feature in features %}
    <div class="{{ loop.first ? 'first' : '' }}">
        {{ component('app-feature-card', {
            title: feature.title,
            icon: feature.icon
        }) }}
    </div>
{% endfor %}
```

### Dopo (PHP Nativo)
```php
<h1><?= $e($pageTitle) ?></h1>
<?php foreach ($features as $__loop_index => $feature): ?>
    <?php $__loop_first = ($__loop_index === 0); ?>
    <div class="<?= $__loop_first ? 'first' : '' ?>">
        <?= $component('app-feature-card', [
            'title' => $feature['title'],
            'icon' => $feature['icon']
        ]) ?>
    </div>
<?php endforeach; ?>
```

## Vantaggi della Migrazione

1. **Nessuna dipendenza esterna**: Eliminata la dipendenza da Twig
2. **Performance**: PHP nativo è più veloce (no parsing/compilazione Twig)
3. **Semplicità**: Meno astrazione, codice più diretto
4. **Debug**: Errori più chiari e stack trace più leggibili
5. **IDE Support**: Migliore supporto degli IDE per PHP nativo
6. **Dimensione**: Riduzione delle dimensioni del progetto

## Compatibilità

Tutte le funzionalità esistenti sono state mantenute:
- ✅ Componenti
- ✅ Slot e proiezione di contenuto
- ✅ Input binding
- ✅ Dependency injection
- ✅ Form handling
- ✅ Flash messages
- ✅ CSRF protection
- ✅ Routing
- ✅ Stili e script per componente

## Note per gli Sviluppatori

1. **Escape HTML**: Usare sempre `$e()` per l'output di variabili, tranne quando si vuole output HTML raw
2. **Array vs Object**: In PHP nativo si usano array associativi `$item['key']` invece di `$item.key` di Twig
3. **Loop Variables**: Le variabili loop devono essere create manualmente con prefisso `__loop_`
4. **Funzioni Helper**: Tutte le funzioni helper sono disponibili come closure nel template

## Conclusione

La migrazione è stata completata con successo mantenendo la struttura a componenti e tutte le funzionalità del framework Sophia.
