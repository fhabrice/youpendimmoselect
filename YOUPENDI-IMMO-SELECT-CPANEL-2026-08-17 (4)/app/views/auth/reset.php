<h1 class="page-title">Nouveau mot de passe</h1>
<form method="post">
  <?= csrf_field() ?>
  <label class="fld">Mot de passe<input type="password" name="password" required minlength="8"></label>
  <label class="fld">Confirmation<input type="password" name="password2" required></label>
  <button class="btn btn-gold btn-block">Enregistrer</button>
</form>
