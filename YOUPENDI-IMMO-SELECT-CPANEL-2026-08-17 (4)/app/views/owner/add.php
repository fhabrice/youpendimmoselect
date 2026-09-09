<section style="padding:32px 0 64px;background:#f9fafb">
  <div class="container" style="max-width:800px">
    <?php include dirname(__DIR__) . '/partials/owner_bar.php'; ?>
    <p class="kicker gold">Publication</p>
    <h1 class="page-title">Mettre un bien en location ou en vente</h1>
    <p class="muted">Remplissez tous les détails et ajoutez des photos. Un administrateur YOUPENDI doit approuver l’annonce avant publication.</p>
    <?php foreach (flashes() as $type => $msgs): foreach ($msgs as $m): ?>
      <div class="flash flash-<?= e($type==='error'?'error':$type) ?>"><?= e($m) ?></div>
    <?php endforeach; endforeach; ?>
    <form class="panel" method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <label class="fld">Titre *<input name="title" required placeholder="Villa 4 chambres — Himbi"></label>
      <div class="form-grid">
        <label class="fld">Type d’offre *
          <select name="offre" required>
            <option value="vente">Vente (à vendre)</option>
            <option value="location_mensuelle">Location mensuelle</option>
            <option value="location_journaliere">Location journalière</option>
          </select>
        </label>
        <label class="fld">Type de bien
          <select name="type"><?php foreach (property_types() as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select>
        </label>
        <label class="fld">Province *
          <select name="province" required>
            <option value="">Sélectionner une province</option>
            <?php foreach (provinces_rdc() as $value => $label): ?><option value="<?= e($value) ?>" <?= old('province') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label class="fld">Ville *
          <select name="city" required data-selected-city="<?= e(old('city')) ?>">
            <option value="">Sélectionner une ville</option>
            <?php foreach (cities() as $city): ?><option value="<?= e($city) ?>" <?= old('city') === $city ? 'selected' : '' ?>><?= e($city) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label class="fld">Quartier<input name="quartier" value="<?= e(old('quartier')) ?>"></label>
        <label class="fld">Prix (USD) *<input type="number" step="0.01" name="prix" required placeholder="Loyer/mois, /nuit ou prix de vente"></label>
        <label class="fld">Superficie m²<input type="number" name="area"></label>
        <label class="fld">Chambres<input type="number" name="bedrooms"></label>
        <label class="fld">Salles de bain<input type="number" name="bathrooms"></label>
      </div>
      <label class="fld">Adresse<input name="address"></label>
      <label class="fld">Description complète<textarea name="description" placeholder="État du bien, équipements, documents, voisinage…"></textarea></label>
      <label class="fld">Photos (plusieurs)<input type="file" name="photos[]" accept="image/*" multiple></label>
      <p><button class="btn">Envoyer pour validation admin</button>
         <a class="btn btn-outline" href="<?= e(base_url('espace/proprietaire')) ?>">Retour</a></p>
    </form>
  </div>
</section>
