<div class="feature-card <?= $e($borderClass) ?> <?= $isFirst ? 'first' : '' ?> <?= $isLast ? 'last' : '' ?>"
     data-index="<?= $e($index) ?>">
    <div class="icon" style="color: <?= $e($iconColor) ?>">
        <?= $e($icon) ?>
    </div>
    <h3><?= $e($title) ?></h3>
    <p><?= $e($description) ?></p>
    <?php if ($isFirst): ?>
        <div class="badge">✨ Most Popular</div>
    <?php endif; ?>

    <div>We have exactly <?= $e($serviceCount) ?> items</div>
    <?php if ($isLast): ?>
        <div class="badge">🆕 New!</div>
    <?php endif; ?>
    <?= $component('app-footer', [
        'year' => 3000,
        'companyName' => 'My Awesome Company'
    ]) ?>
</div>
