<footer class="main-footer">
    <div class="container">
        <div class="footer-content">
            <div class="footer-section">
                <?= $e($serviceCount) ?>
                <h3><?= $e($companyName) ?></h3>
                <p>Building amazing things with PHP and Twig</p>
            </div>

            <div class="footer-section">
                <h4>Quick Links</h4>
                <ul>
                    <?php foreach ($quickLinks as $link): ?>
                        <li>
                            <a href="<?= $e($link['url']) ?>"><?= $e($link['label']) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="footer-section">
                <h4>Follow Us</h4>
                <div class="social-links">
                    <?php foreach ($socialLinks as $social): ?>
                        <a href="<?= $e($social['url']) ?>"
                           title="<?= $e($social['name']) ?>"
                           class="social-link">
                            <?= $e($social['icon']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <p><?= $e($copyrightText) ?></p>
        </div>
    </div>
</footer>

