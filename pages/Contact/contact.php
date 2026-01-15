<section class="contact-page">
  <h1><?= $e($title) ?></h1>
  <?php if ($has_flash('error')): ?>
    <div class="alert alert-danger"><?= $e($flash('error')) ?></div>
  <?php endif; ?>
  <?php if ($has_flash('success')): ?>
    <div class="alert alert-success"><?= $e($flash('success')) ?></div>
  <?php endif; ?>
  <form method="post" action="<?= $e($form_action('send')) ?>">
    <?= $csrf_field() ?>

    <div class="form-group">
      <label for="name">Name</label>
      <input type="text" id="name" name="name" value="<?= $e($old('name')) ?>" />
      <?php $errs = $form_errors('name'); ?>
      <?php if ($errs): ?>
        <ul class="errors"><?php foreach ($errs as $err): ?><li><?= $e($err) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </div>

    <div class="form-group">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" value="<?= $e($old('email')) ?>" />
      <?php $errs = $form_errors('email'); ?>
      <?php if ($errs): ?>
        <ul class="errors"><?php foreach ($errs as $err): ?><li><?= $e($err) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </div>

    <div class="form-group">
      <label for="message">Message</label>
      <textarea id="message" name="message" rows="5"><?= $e($old('message')) ?></textarea>
      <?php $errs = $form_errors('message'); ?>
      <?php if ($errs): ?>
        <ul class="errors"><?php foreach ($errs as $err): ?><li><?= $e($err) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </div>

    <button type="submit">Send</button>
  </form>
</section>
