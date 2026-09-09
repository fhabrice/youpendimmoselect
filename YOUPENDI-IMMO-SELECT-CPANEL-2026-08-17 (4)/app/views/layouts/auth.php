<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(($title ?? 'Connexion') . ' — YOUPENDI IMMO SELECT') ?></title>
  <link rel="icon" href="<?= e(asset('img/logo.svg')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-visual">
    <div class="brand" style="margin-bottom:auto">
      <img src="<?= e(asset('img/logo-youpendi.png')) ?>" alt="YOUPENDI" style="height:80px;background:#fff;border-radius:10px;padding:4px">
    </div>
    <p class="kicker">YOUPENDI IMMO SELECT</p>
    <h1>Trouvez votre maison partout en RDC.</h1>
    <p>Location, gestion locative et Immo Stay — un seul espace pour propriétaires, locataires et agents.</p>
  </div>
  <div class="auth-form">
    <div class="auth-card">
      <?php foreach (flashes() as $type => $msgs): foreach ($msgs as $m): ?>
        <div class="flash flash-<?= e($type === 'error' ? 'error' : $type) ?>"><?= e($m) ?></div>
      <?php endforeach; endforeach; ?>
      <?= $content ?>
    </div>
  </div>
</div>
<script>
window.YP_CITIES_BY_PROVINCE = <?= json_encode(cities_by_province(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.YP_COMMUNES = <?= json_encode(communes_map(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.YP_QUARTIERS = <?= json_encode(quartiers_map(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
