<h1 class="page-title">Mot de passe oublié</h1>
<p class="muted">Saisissez votre e-mail. Un lien de réinitialisation sera généré (affiché ici en démonstration).</p>
<form method="post">
  <?= csrf_field() ?>
  <label class="fld">E-mail<input type="email" name="email" required></label>
  <button class="btn btn-gold btn-block">Continuer</button>
</form>
<?php if (!empty($reset_link)): ?>
  <div class="flash flash-info" style="margin-top:14px">Lien de réinitialisation :<br><a href="<?= e($reset_link) ?>"><?= e($reset_link) ?></a></div>
<?php endif; ?>
