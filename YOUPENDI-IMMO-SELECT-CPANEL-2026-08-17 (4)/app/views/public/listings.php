<?php
$mode = $mode ?? (!empty($stay) ? 'stay' : (($offre ?? '') === 'vente' ? 'vente' : 'location'));
$intros = [
    'vente' => 'Maisons, villas, appartements et locaux à vendre partout en RDC. Prix net vendeur, visite avec un agent YOUPENDI.',
    'stay' => 'Location journalière (Immo Stay) : prix à la nuit, dates d’arrivée et de départ.',
    'location' => 'Location mensuelle : loyer au mois, contrat, caution et quittances.',
];
?>
<section style="padding-top:36px;background:#f9fafb">
  <div class="container">
    <div class="section-head" style="text-align:left">
      <h1 class="page-title"><?= e($heading ?? 'Location mensuelle') ?></h1>
      <p><?= e($intros[$mode] ?? $intros['location']) ?></p>
      <div class="rent-switch" style="justify-content:flex-start;margin:16px 0 0">
        <a class="<?= $mode==='vente'?'on':'' ?>" href="<?= e(base_url('ventes')) ?>">Vente</a>
        <a class="<?= $mode==='location'?'on':'' ?>" href="<?= e(base_url('biens')) ?>">Location mensuelle</a>
        <a class="<?= $mode==='stay'?'on':'' ?>" href="<?= e(base_url('immo-stay')) ?>">Location journalière</a>
      </div>
    </div>
    <form class="search-card" method="get" style="margin:0 0 28px;box-shadow:var(--shadow)">
      <div class="search-grid listing-search-grid">
        <div><label>Province</label>
          <select name="province"><option value="">Toutes</option><?php foreach (provinces_rdc() as $value => $label): ?><option value="<?= e($value) ?>" <?= ($f['province']??'')===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select>
        </div>
        <div><label>Ville</label>
          <select name="ville" data-selected-city="<?= e($f['ville'] ?? '') ?>"><option value="">Toutes</option><?php foreach (cities() as $c): ?><option value="<?= e($c) ?>" <?= ($f['ville']??'')===$c?'selected':'' ?>><?= e($c) ?></option><?php endforeach; ?></select>
        </div>
        <div><label>Type</label>
          <select name="type"><option value="">Tous</option><?php foreach (property_types() as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($f['type']??'')===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select>
        </div>
        <div><label><?= $mode==='vente' ? 'Prix min' : 'Prix min' ?></label><input type="number" name="min" value="<?= e($f['min']??'') ?>"></div>
        <div><label>Prix max</label><input type="number" name="max" value="<?= e($f['max']??'') ?>"></div>
        <div><label>Chambres</label><input type="number" name="chambres" value="<?= e($f['chambres']??'') ?>"></div>
        <?php if ($mode !== 'vente'): ?>
        <div><label>Meublé</label>
          <select name="meuble"><option value="">Indifférent</option><option value="1" <?= ($f['meuble']??'')==='1'?'selected':'' ?>>Meublé</option><option value="0" <?= ($f['meuble']??'')==='0'?'selected':'' ?>>Non meublé</option></select>
        </div>
        <?php else: ?>
        <div><label>Mot-clé</label><input name="q" value="<?= e($f['q']??'') ?>" placeholder="Quartier, villa…"></div>
        <?php endif; ?>
        <div class="go" style="grid-column:span 2"><button class="btn btn-block">Rechercher</button></div>
      </div>
    </form>
    <?php if (!$items): ?>
      <div class="empty panel">
        <?php if ($mode === 'vente'): ?>
          Aucun bien en vente ne correspond à ces critères.
          <a href="<?= e(base_url('inscription-bailleur')) ?>">Vous êtes propriétaire ? Mettez votre bien en vente</a>
          ou <a href="<?= e(base_url('contact')) ?>">contactez un agent</a>.
        <?php else: ?>
          Aucun bien ne correspond à ces critères. <a href="<?= e(base_url('confier-ma-recherche')) ?>">Confiez-nous votre recherche</a>.
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="cards">
        <?php foreach ($items as $p): if ($mode==='stay') $p['_stay']=true; include dirname(__DIR__).'/partials/property_card.php'; endforeach; ?>
      </div>
      <?php if (($pg['pages'] ?? 1) > 1): ?>
        <div class="pager">
          <?php for ($i=1;$i<=$pg['pages'];$i++): ?>
            <a href="?<?= e(qs(['page'=>$i])) ?>"><?= $i ?></a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>
