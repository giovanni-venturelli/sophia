<section class="thank-you">
  <h1><?= $e($title) ?></h1>
  <p><?= $e($message) ?></p>

  <?php $submitted = $peek_flash('__submitted'); ?>
  <?php if ($submitted): ?>
    <div class="submitted-summary">
      <h2>Submitted data</h2>
      <ul>
        <li><strong>Name</strong> <?= $e($submitted['name'] ?? '') ?></li>
        <li><strong>Email</strong> <?= $e($submitted['email'] ?? '') ?></li>
        <li><strong>Message</strong> <?= $e($submitted['message'] ?? '') ?></li>
      </ul>
    </div>
  <?php endif; ?>

  <p><a href="<?= $e($contactUrl) ?>">Back to contact</a></p>
</section>
