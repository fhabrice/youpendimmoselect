<section style="padding:32px 0 64px;background:#f9fafb">
  <div class="container">
    <?php include dirname(__DIR__) . '/partials/owner_bar.php'; ?>
    <p class="kicker gold">Espace bailleur</p>
    <h1 class="page-title">Bonjour <?= e($name) ?></h1>
    <p class="muted">Suivez vos biens en <strong>vente</strong> et en <strong>location</strong>. Les nouvelles annonces restent en attente jusqu’à validation de l’administrateur.</p>
    <p>
      <a class="btn" href="<?= e(base_url('espace/proprietaire/ajouter')) ?>">+ Publier un bien (vente ou location)</a>
      <a class="btn btn-outline" href="<?= e(base_url('espace/proprietaire/rapport')) ?>">Rapport mensuel</a>
      <a class="btn btn-logout" href="<?= e(base_url('deconnexion')) ?>">Se déconnecter</a>
    </p>
    <div class="kpi" style="margin:20px 0">
      <?php foreach ($kpis as $k): ?>
        <div class="stat"><b><?= e($k['v']) ?></b><?= e($k['l']) ?></div>
      <?php endforeach; ?>
    </div>
    <?php
      $ventes = [];
      $locations = [];
      foreach ($biens as $p) {
          if (property_offre($p) === 'vente') {
              $ventes[] = $p;
          } else {
              $locations[] = $p;
          }
      }
    ?>
    <h2>Mes biens en vente</h2>
    <div class="table-wrap" style="margin-bottom:28px">
      <table>
        <thead><tr><th>Titre</th><th>Ville</th><th>Prix de vente</th><th>Statut</th></tr></thead>
        <tbody>
        <?php foreach ($ventes as $p): ?>
          <tr>
            <td><?= e($p['title'] ?? $p['titre'] ?? '') ?></td>
            <td><?= e(trim(($p['province'] ?? '') . ' · ' . ($p['city'] ?? $p['ville'] ?? ''), ' ·')) ?></td>
            <td><?= e(money($p['rent'] ?? $p['loyer'] ?? $p['prix'] ?? 0)) ?></td>
            <td><?= status_badge($p['status'] ?? $p['statut'] ?? 'en_attente') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$ventes): ?><tr><td colspan="4" class="empty">Aucune vente. <a href="<?= e(base_url('espace/proprietaire/ajouter')) ?>">Mettre un bien en vente</a></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <h2>Mes biens en location</h2>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Titre</th><th>Offre</th><th>Ville</th><th>Prix</th><th>Statut</th></tr></thead>
        <tbody>
        <?php foreach ($locations as $p): ?>
          <tr>
            <td><?= e($p['title'] ?? $p['titre'] ?? '') ?></td>
            <td><?= e($offres[property_offre($p)] ?? offre_label(property_offre($p))) ?></td>
            <td><?= e(trim(($p['province'] ?? '') . ' · ' . ($p['city'] ?? $p['ville'] ?? ''), ' ·')) ?></td>
            <td><?= e(money($p['rent'] ?? $p['loyer'] ?? $p['prix'] ?? 0)) ?></td>
            <td><?= status_badge($p['status'] ?? $p['statut'] ?? 'en_attente') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$locations): ?><tr><td colspan="5" class="empty">Aucune location. <a href="<?= e(base_url('espace/proprietaire/ajouter')) ?>">Publier une location</a></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
