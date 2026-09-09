<div class="section-head">
  <div>
    <p class="kicker gold"><?= e($kicker ?? 'Formulaire') ?></p>
    <h1 class="app-title"><?= e($title) ?></h1>
  </div>
  <?php if (!empty($back)): ?><a class="btn btn-outline btn-sm" href="<?= e(base_url($back)) ?>">Retour</a><?php endif; ?>
</div>
<form class="panel" method="post" enctype="multipart/form-data" style="max-width:920px">
  <?= csrf_field() ?>
  <div class="form-grid">
    <?= $fields ?>
  </div>
  <button class="btn btn-gold" style="margin-top:12px"><?= e($submit ?? 'Enregistrer') ?></button>
</form>
<?php if (!empty($extra)) echo $extra; ?>
