<h1 class="page-title">Créer un compte</h1>
<p class="muted">Locataire, propriétaire ou voyageur — un compte pour suivre vos dossiers.</p>
<form method="post" style="margin-top:18px">
  <?= csrf_field() ?>
  <div class="form-grid">
    <label class="fld">Prénom<input name="first_name" required value="<?= e(old('first_name')) ?>"></label>
    <label class="fld">Nom<input name="last_name" required value="<?= e(old('last_name')) ?>"></label>
  </div>
  <label class="fld">E-mail<input type="email" name="email" required value="<?= e(old('email')) ?>"></label>
  <label class="fld">Téléphone<input name="phone" required value="<?= e(old('phone')) ?>"></label>
  <label class="fld">WhatsApp<input name="whatsapp" value="<?= e(old('whatsapp')) ?>"></label>
  <div class="form-grid">
    <label class="fld">Province *
      <select name="province" required><option value="">Sélectionner une province</option><?php foreach (provinces_rdc() as $value => $label): ?><option value="<?= e($value) ?>" <?= old('province') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
    </label>
    <label class="fld">Ville *
      <select name="city" required data-selected-city="<?= e(old('city')) ?>"><option value="">Sélectionner une ville</option><?php foreach (cities() as $city): ?><option value="<?= e($city) ?>" <?= old('city') === $city ? 'selected' : '' ?>><?= e($city) ?></option><?php endforeach; ?></select>
    </label>
  </div>
  <label class="fld">Vous êtes
    <select name="role">
      <option value="tenant">Locataire</option>
      <option value="owner">Propriétaire</option>
      <option value="traveler">Voyageur / courte durée</option>
    </select>
  </label>
  <label class="fld">Mot de passe<input type="password" name="password" required minlength="8"></label>
  <button class="btn btn-gold btn-block">Créer mon compte</button>
</form>
<p><a href="<?= e(base_url('connexion')) ?>">Déjà inscrit ? Connexion</a></p>
