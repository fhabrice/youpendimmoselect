<h1 class="page-title">Connexion</h1>
<p class="muted">Accédez à votre espace YOUPENDI IMMO SELECT.</p>
<form method="post" style="margin-top:18px">
  <?= csrf_field() ?>
  <label class="fld">E-mail<input type="email" name="email" required value="<?= e(old('email')) ?>" autocomplete="username"></label>
  <label class="fld">Mot de passe<input type="password" name="password" required autocomplete="current-password"></label>
  <button class="btn btn-gold btn-block">Se connecter</button>
</form>
<p style="margin-top:14px"><a href="<?= e(base_url('mot-de-passe-oublie')) ?>">Mot de passe oublié</a> · <a href="<?= e(base_url('inscription')) ?>">Créer un compte bailleur</a></p>
