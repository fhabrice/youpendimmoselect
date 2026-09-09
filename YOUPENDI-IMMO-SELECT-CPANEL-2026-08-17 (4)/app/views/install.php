<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Installation — YOUPENDI IMMO SELECT</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500&family=Source+Sans+3:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body style="background:var(--navy);color:#fff">
<div class="container" style="max-width:640px;padding:48px 16px">
  <p class="kicker">Installation</p>
  <h1 class="page-title" style="color:#fff">Brancher votre base de données</h1>
  <p>Renseignez les identifiants MySQL de votre hébergeur (cPanel, phpMyAdmin, Plesk…). Les tables seront créées automatiquement.</p>
  <?php if (!empty($error)): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="panel" style="color:var(--ink);margin-top:18px">
    <?= csrf_field() ?>
    <label class="fld">Moteur
      <select name="driver">
        <option value="mysql" selected>MySQL / MariaDB (hébergeur)</option>
        <option value="sqlite">SQLite (local / démo)</option>
      </select>
    </label>
    <div class="form-grid">
      <label class="fld">Hôte<input name="host" value="localhost"></label>
      <label class="fld">Port<input name="port" value="3306"></label>
      <label class="fld">Nom de la base<input name="name" value="" placeholder="Nom fourni par l’hébergeur" required></label>
      <label class="fld">Utilisateur<input name="user" value="" placeholder="Utilisateur MySQL"></label>
      <label class="fld full">Mot de passe<input type="password" name="pass" value="" autocomplete="new-password"></label>
    </div>
    <hr>
    <h3>Compte administrateur</h3>
    <div class="form-grid">
      <label class="fld">Prénom<input name="first_name" value="Admin"></label>
      <label class="fld">Nom<input name="last_name" value="Youpendi"></label>
      <label class="fld">E-mail<input type="email" name="email" value="admin@youpendimmoselect.com"></label>
      <label class="fld">Mot de passe<input type="password" name="password" required></label>
    </div>
    <label class="fld"><input type="checkbox" name="seed" value="1" checked> Charger des données de démonstration</label>
    <button class="btn btn-gold">Installer la plateforme</button>
  </form>
</div>
</body>
</html>
