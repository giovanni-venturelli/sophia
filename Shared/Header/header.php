<header class="main-header">
    <div class="container">
        <div class="header-content">
            <div class="logo">
                <span class="logo-icon">🚀</span>
                <span class="logo-text"><?= $e($title) ?></span>
                <?= $slot('easyProjection') ?>
            </div>

            <nav class="navigation">
                <ul>
                    <?php foreach ($navigation as $item): ?>
                        <li class="<?= $item['active'] ? 'active' : '' ?>">
                            <a href="<?= $e($url($item['url'])) ?>"><?= $e($item['label']) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <div class="header-actions">
                <button class="btn-secondary">Login</button>
                <button class="btn-primary">Sign Up</button>
            </div>
        </div>
    </div>
</header>
