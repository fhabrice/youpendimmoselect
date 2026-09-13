<?php
$nav = $nav ?? '';
$wa = whatsapp_link('Bonjour YOUPENDI IMMO SELECT, je souhaite des informations.');
// Logo de la vitrine : chaîne de repli pour ne jamais afficher une image cassée,
// même si un fichier du paquet manque sur le serveur.
$logo = logo_url();
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(($title ?? 'Accueil') . ' — YOUPENDI IMMO SELECT') ?></title>
  <meta name="description" content="YOUPENDI IMMO SELECT — achetez ou louez votre propriété partout en RDC. Vente, location mensuelle et location journalière.">
  <link rel="icon" href="<?= e($logo) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
  <?php if (!empty($tailwind)): ?><link rel="stylesheet" href="<?= e(asset('css/tailwind-youpendi.css')) ?>"><?php endif; ?>
</head>
<body>
<a class="skip" href="#main">Aller au contenu</a>
<header class="pub-header">
  <div class="container bar">
    <a class="brand" href="<?= e(base_url()) ?>">
      <img class="logo" src="<?= e($logo) ?>" alt="YOUPENDI IMMO SELECT">
    </a>
    <nav class="nav" id="pubnav">
      <a class="<?= $nav===''?'active':'' ?>" href="<?= e(base_url()) ?>">Accueil</a>
      <a class="<?= $nav==='ventes'?'active':'' ?>" href="<?= e(base_url('ventes')) ?>">Vente</a>
      <a class="<?= $nav==='biens'?'active':'' ?>" href="<?= e(base_url('biens')) ?>">Location mensuelle</a>
      <a class="<?= $nav==='stay'?'active':'' ?>" href="<?= e(base_url('immo-stay')) ?>">Location journalière</a>
      <a class="<?= $nav==='services'?'active':'' ?>" href="<?= e(base_url('services')) ?>">Services</a>
      <?php if (user() && (user()['role'] ?? '') === 'owner'): ?>
        <a class="<?= str_starts_with(request_path(), '/espace/proprietaire')?'active':'' ?>" href="<?= e(base_url('espace/proprietaire')) ?>">Espace bailleur</a>
      <?php else: ?>
        <a href="<?= e(base_url('inscription-bailleur')) ?>">Espace propriétaire</a>
      <?php endif; ?>
    </nav>
    <div class="nav-cta">
      <?php if (user()): ?>
        <span class="user-mini">Bonjour <?= e(user()['first_name'] ?? user()['prenom'] ?? 'vous') ?></span>
        <a class="btn btn-sm" href="<?= e(base_url(home_for(user()))) ?>">Mon espace</a>
        <a class="btn btn-sm btn-logout" href="<?= e(base_url('deconnexion')) ?>">Déconnexion</a>
      <?php else: ?>
        <a class="btn btn-outline btn-sm" href="<?= e(base_url('connexion')) ?>">Se Connecter</a>
        <a class="btn btn-green btn-sm" href="<?= e(base_url('connexion')) ?>">Administrateur</a>
      <?php endif; ?>
      <button class="burger" id="burger" aria-label="Menu">☰</button>
    </div>
  </div>
</header>
<main id="main"><?= $content ?></main>
<footer class="pub">
  <div class="container">
    <div class="foot-grid">
      <div>
        <img class="footer-logo" src="<?= e($logo) ?>" alt="" style="margin-bottom:12px;background:#fff;border-radius:8px">
        <p>Votre partenaire de confiance pour la location et la gestion de propriétés partout en République Démocratique du Congo.</p>
      </div>
      <div>
        <h4>Liens Rapides</h4>
        <a href="<?= e(base_url()) ?>">Accueil</a>
        <a href="<?= e(base_url('ventes')) ?>">Vente</a>
        <a href="<?= e(base_url('biens')) ?>">Location mensuelle</a>
        <a href="<?= e(base_url('immo-stay')) ?>">Location journalière</a>
        <a href="<?= e(base_url('services')) ?>">À Propos</a>
        <a href="<?= e(base_url('contact')) ?>">Contact</a>
      </div>
      <div>
        <h4>Contact</h4>
        <p>Avenue de la Libération, Gombe<br>Kinshasa, RDC</p>
        <p>+243 994 052 587</p>
        <p>contact@youpendimmoselect.com</p>
      </div>
      <div>
        <h4>Newsletter</h4>
        <p>Restez informé des derniers logements et actualités.</p>
        <form method="post" action="<?= e(base_url('contact')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="name" value="Newsletter">
          <input type="hidden" name="subject" value="Newsletter">
          <input type="hidden" name="body" value="Inscription newsletter">
          <input type="email" name="email" placeholder="Votre email" required style="background:#1f2937;border-color:#374151;color:#fff;margin-bottom:8px">
          <button class="btn btn-block">S'abonner</button>
        </form>
      </div>
    </div>
    <div class="copy">© <?= date('Y') ?> YOUPENDI IMMO SELECT. Tous droits réservés.</div>
  </div>
</footer>

<div class="wa-box" id="wabox">
  <div class="wa-head">
    <div>
      <strong>YOUPENDI Support</strong>
      <div style="font-size:12px;opacity:.9">En ligne maintenant</div>
    </div>
    <button type="button" id="waclose" style="background:none;border:0;color:#fff;font-size:18px;cursor:pointer">✕</button>
  </div>
  <div class="wa-body">
    <div class="wa-msg">
      👋 Bonjour ! Bienvenue chez YOUPENDI IMMO SELECT<br>
      <span style="color:#4b5563">Comment pouvons-nous vous aider avec vos projets immobiliers aujourd’hui ?</span>
    </div>
    <a class="btn btn-block" style="margin-top:12px;background:#22c55e" href="<?= e($wa) ?>" target="_blank" rel="noopener">Continuer sur WhatsApp</a>
  </div>
</div>
<a class="wa" href="<?= e($wa) ?>" id="wabtn" target="_blank" rel="noopener" title="WhatsApp">✆</a>
<script>
window.YP_CITIES_BY_PROVINCE = <?= json_encode(cities_by_province(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.YP_COMMUNES = <?= json_encode(communes_map(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.YP_QUARTIERS = <?= json_encode(quartiers_map(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
