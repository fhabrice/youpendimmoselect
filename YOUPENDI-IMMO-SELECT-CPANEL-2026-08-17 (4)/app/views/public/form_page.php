<section style="padding-top:36px">
  <div class="container" style="max-width:820px">
    <p class="kicker gold"><?= e($kicker ?? 'YOUPENDI') ?></p>
    <h1 class="page-title"><?= e($title) ?></h1>
    <p class="muted"><?= e($intro ?? '') ?></p>
    <?php foreach (flashes() as $type => $msgs): foreach ($msgs as $m): ?>
      <div class="flash flash-<?= e($type==='error'?'error':$type) ?>"><?= e($m) ?></div>
    <?php endforeach; endforeach; ?>
    <form class="panel" method="post" enctype="multipart/form-data" style="margin-top:18px">
      <?= csrf_field() ?>
      <?= $form ?>
      <button class="btn btn-gold" style="margin-top:8px"><?= e($submit ?? 'Envoyer') ?></button>
    </form>
  </div>
</section>
