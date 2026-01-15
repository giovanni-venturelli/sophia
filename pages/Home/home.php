<?php ob_start(); ?>
    <slot name="easyProjection">
        <div style="color: red">this</div>
        <div>is</div>
        <div>a</div>
        <div>test</div>
    </slot>
<?php $slotContent = ob_get_clean(); ?>
<?= $component('app-header', [
    'title' => $pageTitle
], $slotContent) ?>

<section class="hero">
    <div class="container">
        <div class="welcome"><?= $e($welcomeMessage) ?></div>
        <h1><?= $e($pageTitle) ?> id: <?= $e($id) ?></h1>
        <p><?= $e($subtitle) ?></p>
        <p>Service count: <?= $e($serviceCount) ?></p>
    </div>
</section>


<section class="stats-section">
    <div class="container">
        <div class="stats">
            <?php foreach ($stats as $stat): ?>
                <div class="stat-card">
                    <div class="value"><?= $e($stat['value']) ?></div>
                    <div class="label"><?= $e($stat['label']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="features">
    <div class="container">
        <h2>Why Choose Us?</h2>
        <p class="subtitle">
            Abbiamo <?= $e($featuresCount) ?> incredibili features per te
        </p>

        <div class="features-grid">
            <?php if (!empty($features)): ?>
                <?php foreach ($features as $__loop_index => $feature): ?>
                    <?php $__loop_first = ($__loop_index === 0); ?>
                    <?php $__loop_last = ($__loop_index === count($features) - 1); ?>
                    <?= $component('app-feature-card', [
                        'icon' => $feature['icon'],
                        'title' => $feature['title'],
                        'description' => $feature['description'],
                        'color' => $feature['color'],
                        'isFirst' => $__loop_first,
                        'isLast' => $__loop_last,
                        'index' => $__loop_index + 1
                    ]) ?>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Nessuna feature disponibile</p>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php if ($featuresCount > 3): ?>
    <section class="promo">
        <div class="container">
            <h3>🎉 Wow! Abbiamo più di 3 features!</h3>
            <p>Scopri tutte le nostre <?= $e($featuresCount) ?> incredibili funzionalità</p>
        </div>
    </section>
<?php endif; ?>

<?= $component('app-footer', [
    'year' => $currentYear,
    'companyName' => 'My Awesome Company'
]) ?>
