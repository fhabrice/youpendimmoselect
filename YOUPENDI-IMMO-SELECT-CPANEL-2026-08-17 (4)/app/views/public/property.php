<section style="padding-top:28px">
  <div class="container">
    <p class="kicker gold"><?= e($p['reference']) ?> · <?= e(trim(($p['province'] ?? '') . ' · ' . $p['city'], ' ·')) ?></p>
    <h1 class="page-title"><?= e($p['title']) ?></h1>
    <p class="muted"><?= e($p['address_public'] ?: ($p['quartier'].', '.$p['city'])) ?> — adresse exacte communiquée après visite.</p>

    <div class="gallery" style="margin:18px 0 26px">
      <img class="gallery-main" src="<?= e(photo_url($photos[0]['path'] ?? null)) ?>" alt="<?= e($p['title']) ?>" style="min-height:360px;object-fit:cover"
           onerror="this.onerror=null;this.src='<?= e(photo_placeholder()) ?>'">
      <div class="stack">
        <?php foreach (array_slice($photos,1,3) as $ph): ?>
          <img src="<?= e(photo_url($ph['path'])) ?>" alt="" style="height:116px;object-fit:cover;cursor:pointer"
               onerror="this.onerror=null;this.src='<?= e(photo_placeholder()) ?>'">
        <?php endforeach; ?>
      </div>
    </div>

    <div class="prop-grid">
      <div>
        <div class="panel">
          <div class="meta" style="margin-bottom:12px">
            <?= status_badge($p['status']) ?>
            <span><?= e(property_types()[$p['type']] ?? $p['type']) ?></span>
            <span><?= e($p['usage_type'] === 'professionnel' ? 'Professionnel' : 'Résidentiel') ?></span>
            <span><?= !empty($p['furnished']) ? 'Meublé' : 'Non meublé' ?></span>
          </div>
          <p><?= nl2br(e($p['description'])) ?></p>
          <h3>Équipements</h3>
          <div>
            <?php foreach (array_filter(explode(',', (string)$p['amenities'])) as $a): ?>
              <span class="amenity"><?= e(amenities_catalog()[$a] ?? $a) ?></span>
            <?php endforeach; ?>
          </div>
          <h3>Modalités de visite</h3>
          <p><?= e($p['visit_modalities'] ?: 'Sur rendez-vous avec un agent YOUPENDI.') ?></p>
        </div>
      </div>
      <aside>
        <div class="panel">
          <?php $offreP = property_offre($p); $isSale = $offreP === 'vente'; ?>
          <div class="card-type" style="<?= $isSale ? 'color:var(--blue)' : '' ?>"><?= e(offre_label($offreP)) ?></div>
          <div class="price" style="font-size:28px"><?= e(money($p['rent'], $p['currency'])) ?>
            <?php if (!$isSale): ?><span class="muted" style="font-size:16px">/ mois</span><?php endif; ?>
          </div>
          <?php if ($isSale): ?>
            <p>Prix de vente — visite et offre d’achat avec un agent YOUPENDI.</p>
          <?php else: ?>
            <p>Caution : <?= e(money($p['deposit'] ?? 0, $p['currency'])) ?></p>
          <?php endif; ?>
          <p class="muted"><?= (int)$p['bedrooms'] ?> chambres · <?= (int)$p['bathrooms'] ?> sdb · <?= e($p['area']) ?> m²</p>
          <p>Agent : <strong><?= e($agentName ?? 'YOUPENDI') ?></strong></p>
          <form method="post" action="<?= e(base_url('biens/'.$p['id'].'/visite')) ?>">
            <?= csrf_field() ?>
            <label class="fld">Votre nom<input name="visitor_name" required value="<?= e(user()['first_name'] ?? '') ?>"></label>
            <label class="fld">Téléphone<input name="visitor_phone" required value="<?= e(user()['phone'] ?? '') ?>"></label>
            <label class="fld">E-mail<input type="email" name="visitor_email" value="<?= e(user()['email'] ?? '') ?>"></label>
            <label class="fld">Date souhaitée<input type="datetime-local" name="scheduled_at" required></label>
            <button class="btn btn-gold btn-block"><?= $isSale ? 'Demander une visite / offre' : 'Demander une visite' ?></button>
          </form>
          <div style="display:grid;gap:8px;margin-top:10px">
            <?php if (user()): ?>
              <form method="post" action="<?= e(base_url('biens/'.$p['id'].'/favori')) ?>"><?= csrf_field() ?><button class="btn btn-outline btn-block btn-sm">Ajouter aux favoris</button></form>
            <?php endif; ?>
            <a class="btn btn-outline btn-sm" href="<?= e(whatsapp_link('Bonjour, je m’intéresse au bien '.$p['reference'].' — '.$p['title'])) ?>" target="_blank">Contacter YOUPENDI</a>
            <button class="btn btn-outline btn-sm" type="button" onclick="navigator.share?navigator.share({title:document.title,url:location.href}):navigator.clipboard.writeText(location.href)">Partager</button>
          </div>
        </div>
      </aside>
    </div>
  </div>
</section>
