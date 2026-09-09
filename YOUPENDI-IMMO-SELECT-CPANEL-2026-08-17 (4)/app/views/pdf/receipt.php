<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Quittance <?= e($q['number']) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500&family=Source+Sans+3:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
  <style>
    body{background:#fff;padding:32px}
    .sheet{max-width:720px;margin:auto;border:1px solid #e4ddd0;padding:36px;border-radius:8px}
    .gold-line{height:3px;background:linear-gradient(90deg,#c4a35a,#0b1c33);margin:16px 0 24px}
  </style>
</head>
<body>
<div class="sheet">
  <div class="brand">
    <span class="brand-mark">Y</span>
    <span><span class="brand-name" style="color:#0b1c33">YOUPENDI</span><span class="brand-sub">IMMO SELECT</span></span>
  </div>
  <div class="gold-line"></div>
  <p class="kicker gold">Quittance de loyer</p>
  <h1 class="page-title"><?= e($q['number']) ?></h1>
  <p>Je soussigné, YOUPENDI IMMO SELECT, reconnais avoir reçu de <strong><?= e(full_name($q)) ?></strong>
    la somme de <strong><?= e(money($q['amount'])) ?></strong> au titre du loyer de
    <strong><?= e($q['title']) ?></strong> (<?= e($q['city'] ?? '') ?>) pour la période <strong><?= e($q['period_label']) ?></strong>.</p>
  <p>Mode de paiement : <?= e($q['method']) ?><br>Date : <?= e(dfr($q['issued_at'])) ?></p>
  <p class="muted">Document généré électroniquement — cachet numérique YOUPENDI IMMO SELECT.</p>
  <p style="margin-top:48px">Pour YOUPENDI IMMO SELECT<br><em>Service financier</em></p>
  <button class="btn btn-gold no-print" onclick="window.print()">Imprimer / PDF</button>
</div>
</body>
</html>
