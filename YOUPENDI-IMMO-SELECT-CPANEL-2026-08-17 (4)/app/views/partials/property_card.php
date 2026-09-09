<?php
$offre = property_offre($p);
$isSale = $offre === 'vente';
$isStay = $offre === 'location_journaliere';
$href = $isStay ? base_url('immo-stay/' . $p['id']) : base_url('biens/' . $p['id']);
$price = $isStay
    ? money($p['price_night'] ?? $p['rent'] ?? $p['loyer'] ?? $p['prix'] ?? 0) . ' / nuit'
    : ($isSale
        ? money($p['rent'] ?? $p['loyer'] ?? $p['prix'] ?? 0, $p['currency'] ?? 'USD')
        : money($p['rent'] ?? $p['loyer'] ?? $p['prix'] ?? 0, $p['currency'] ?? 'USD') . '/mois');
$badge = $isSale ? 'À vendre' : ($isStay ? 'À louer / jour' : 'À louer');
$pillClass = $isSale ? 'pill sale' : ($isStay ? 'pill stay' : 'pill');
$loc = trim(($p['quartier'] ?? '') . ', ' . ($p['city'] ?? $p['ville'] ?? '') . (($p['province'] ?? '') ? ' · ' . $p['province'] : ''), ' ,·');
?>
<a class="card" href="<?= e($href) ?>">
  <div class="card-media">
    <img src="<?= e(cover_of((int)$p['id'])) ?>" alt="">
    <span class="<?= e($pillClass) ?>"><?= e($badge) ?></span>
  </div>
  <div class="card-body">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px">
      <h3><?= e($p['title'] ?? $p['titre'] ?? '') ?></h3>
      <div class="price"><?= e($price) ?></div>
    </div>
    <p class="muted" style="margin:6px 0 0;font-size:14px"><?= e($loc) ?></p>
    <div class="meta">
      <?php if ((int)($p['bedrooms'] ?? $p['chambres'] ?? 0)): ?><span><?= (int)($p['bedrooms'] ?? $p['chambres']) ?> chambres</span><?php endif; ?>
      <?php if ((int)($p['bathrooms'] ?? $p['sdb'] ?? 0)): ?><span><?= (int)($p['bathrooms'] ?? $p['sdb']) ?> salles de bain</span><?php endif; ?>
      <?php if (!empty($p['area']) || !empty($p['superficie'])): ?><span><?= e($p['area'] ?? $p['superficie']) ?> m²</span><?php endif; ?>
    </div>
  </div>
</a>
