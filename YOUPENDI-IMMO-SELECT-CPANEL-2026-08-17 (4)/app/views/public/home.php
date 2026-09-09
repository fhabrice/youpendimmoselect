<?php $hero = asset('img/hero-house.jpg'); ?>
<section class="hero" style="background-image:url('<?= e($hero) ?>')">
  <div class="hero-inner">
    <h1>ACHETEZ OU LOUEZ VOTRE PROPRIÉTÉ<br><span>DANS TOUTE LA RDC</span></h1>
    <p class="hero-sub">Vente et location de propriétés dans les provinces de la République Démocratique du Congo</p>
    <form class="search-card" method="get" action="<?= e(base_url('biens')) ?>" id="hero-search">
      <div class="search-grid">
        <div>
          <label>Mot-clé</label>
          <input name="q" placeholder="Tapez un mot-clé...">
        </div>
        <div>
          <label>Province</label>
          <select name="province">
            <option value="">Toutes les provinces</option>
            <?php foreach (provinces_rdc() as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Ville</label>
          <select name="ville">
            <option value="">Choisir une ville...</option>
            <?php foreach (cities() as $c): ?><option value="<?= e($c) ?>"><?= e($c) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Transaction</label>
          <select name="offre">
            <option value="">Vente ou Location...</option>
            <option value="vente">Vente</option>
            <option value="location_mensuelle">Location mensuelle</option>
            <option value="location_journaliere">Location journalière</option>
          </select>
        </div>
        <div>
          <label>Type</label>
          <select name="type">
            <option value="">Type de bien...</option>
            <?php foreach (property_types() as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="go"><button class="btn btn-block" type="submit">Rechercher</button></div>
      </div>
    </form>
  </div>
</section>

<section style="background:#f9fafb">
  <div class="container">
    <div class="section-head">
      <h2>PROPRIÉTÉS EN VENTE</h2>
      <p>Découvrez notre sélection de biens disponibles à l’achat dans les meilleurs quartiers de la RDC.</p>
    </div>
    <?php if (!empty($sales)): ?>
      <div class="cards">
        <?php foreach ($sales as $p): include dirname(__DIR__) . '/partials/property_card.php'; endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty panel">Les prochaines ventes seront publiées ici après validation. <a href="<?= e(base_url('inscription-bailleur')) ?>">Mettre mon bien en vente</a></div>
    <?php endif; ?>
    <p style="text-align:center;margin-top:28px">
      <a class="btn" href="<?= e(base_url('ventes')) ?>">Voir toutes les ventes</a>
    </p>
  </div>
</section>

<section>
  <div class="container">
    <div class="section-head">
      <h2>PROPRIÉTÉS EN LOCATION</h2>
      <p>Locations mensuelles : appartements, maisons et villas prêts à habiter.</p>
    </div>
    <?php if (!empty($listings)): ?>
      <div class="cards">
        <?php foreach ($listings as $p): include dirname(__DIR__) . '/partials/property_card.php'; endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty panel">Aucune location publiée pour le moment. <a href="<?= e(base_url('biens')) ?>">Voir le catalogue</a></div>
    <?php endif; ?>
    <p style="text-align:center;margin-top:28px">
      <a class="btn btn-outline" href="<?= e(base_url('biens')) ?>">Voir toutes les locations</a>
    </p>
  </div>
</section>
<script>
(function(){
  var f=document.getElementById('hero-search');
  var s=f&&f.querySelector('[name=offre]');
  if(!f||!s) return;
  function sync(){
    if(s.value==='vente') f.action=<?= json_encode(base_url('ventes')) ?>;
    else if(s.value==='location_journaliere') f.action=<?= json_encode(base_url('immo-stay')) ?>;
    else f.action=<?= json_encode(base_url('biens')) ?>;
  }
  s.addEventListener('change', sync);
  sync();
})();
</script>

<section>
  <div class="container">
    <div class="section-head">
      <h2>ESPACE PROPRIÉTAIRE</h2>
      <p>Vendez ou louez vos propriétés et gérez vos biens immobiliers</p>
    </div>
    <div class="services">
      <article class="service"><div class="ico">⌂</div><h3>Gestion Facilitée</h3><p>Gérez vos ventes et locations depuis votre tableau de bord personnalisé.</p></article>
      <article class="service"><div class="ico green">★</div><h3>Support Expert</h3><p>Accompagnement de nos experts tout au long du processus.</p></article>
      <article class="service"><div class="ico">◉</div><h3>Suivi en Temps Réel</h3><p>Rapports détaillés sur les performances de vos biens en vente et en location.</p></article>
    </div>
    <p style="text-align:center;margin-top:18px">
      <a class="btn" href="<?= e(base_url('inscription-bailleur')) ?>">S’inscrire comme Propriétaire</a>
      <a class="btn btn-outline" href="<?= e(base_url('connexion')) ?>">Accéder au tableau de bord</a>
    </p>
    <p class="muted" style="text-align:center">Nouveau propriétaire ? Inscrivez-vous. Déjà membre ? Connectez-vous.</p>
  </div>
</section>

<?php if (!empty($stays)): ?>
<section style="background:#f9fafb">
  <div class="container">
    <div class="section-head">
      <h2>LOCATION JOURNALIÈRE</h2>
      <p>Meublés à la nuit — calendrier et réservation.</p>
    </div>
    <div class="cards">
      <?php foreach ($stays as $p): $p['_stay']=true; include dirname(__DIR__).'/partials/property_card.php'; endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section>
  <div class="container">
    <div class="section-head">
      <h2>APPELEZ NOS AGENTS</h2>
      <p>Les agents publiés par l’administration vous accompagnent dans vos projets immobiliers.</p>
    </div>
    <?php if (!empty($agentsHome)): ?>
    <div class="agents">
      <?php foreach ($agentsHome as $ag):
        $firstName = trim((string) ($ag['first_name'] ?? $ag['prenom'] ?? ''));
        $lastName = trim((string) ($ag['last_name'] ?? $ag['nom'] ?? ''));
        $agentName = trim($firstName . ' ' . $lastName) ?: ($ag['name'] ?? $ag['nom'] ?? 'Agent immobilier');
        $agentType = (string) ($ag['type'] ?? 'immobilier');
        $agentTypeLabels = ['immobilier' => 'Agent immobilier', 'principal' => 'Agent principal', 'regional' => 'Agent régional', 'senior' => 'Superviseur / senior'];
        $agentType = $agentTypeLabels[$agentType] ?? ucwords(str_replace('_', ' ', $agentType));
        $agentPhone = (string) ($ag['phone'] ?? $ag['telephone'] ?? $ag['whatsapp'] ?? '');
        $agentProvince = trim((string) ($ag['province'] ?? ''));
        $agentCity = trim($agentProvince . ($agentProvince && !empty($ag['city']) ? ' · ' : '') . (string) ($ag['city'] ?? $ag['ville'] ?? ''));
        $agentSpecialties = trim((string) ($ag['specialties'] ?? $ag['specialites'] ?? $ag['notes'] ?? ''));
        $agentPhoto = agent_photo_url($ag['avatar'] ?? $ag['photo_path'] ?? $ag['photo'] ?? null);
        ?>
        <article class="card agent-card">
          <img class="agent-photo" src="<?= e($agentPhoto) ?>" alt="Photo de <?= e($agentName) ?>" loading="lazy">
          <div class="body">
            <div class="card-type"><?= e($agentType) ?></div>
            <h3><?= e($agentName) ?></h3>
            <?php if ($agentCity): ?><p class="muted"><?= e($agentCity) ?></p><?php endif; ?>
            <?php if ($agentSpecialties): ?><div class="chips"><span class="chip"><?= e($agentSpecialties) ?></span></div><?php endif; ?>
            <?php if ($agentPhone): ?>
              <p class="agent-phone"><?= e($agentPhone) ?></p>
              <a class="btn btn-sm btn-block" href="tel:<?= e(preg_replace('/\s+/', '', $agentPhone)) ?>">Appeler l’agent</a>
            <?php else: ?>
              <span class="muted">Téléphone non renseigné</span>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
      <div class="empty panel">Les agents seront affichés ici dès qu’ils seront ajoutés et activés depuis l’administration.</div>
    <?php endif; ?>
  </div>
</section>
