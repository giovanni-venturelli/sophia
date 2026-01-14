<div class="layout-sentence">Questo è un layout</div>

<main class="about-layout">
  <h1><?= $e($title) ?></h1>
  <section class="content">
    <?= $slot('outlet') ?>
  </section>
</main>
<div class="layout-sentence">
qui finisce il layout
</div>
