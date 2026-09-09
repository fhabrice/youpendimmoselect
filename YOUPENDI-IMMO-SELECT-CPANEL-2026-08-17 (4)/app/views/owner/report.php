<section style="padding:32px 0 64px;background:#f9fafb">
  <div class="container">
    <?php include dirname(__DIR__) . '/partials/owner_bar.php'; ?>
    <p class="kicker gold">Reporting</p>
    <h1 class="page-title">Rapport mensuel</h1>
    <form method="get" class="filters">
      <input type="month" name="mois" value="<?= e($mois) ?>">
      <button class="btn btn-sm">Afficher</button>
    </form>
    <div class="kpi">
      <div class="stat"><b><?= (int)$nLoc ?></b>en location</div>
      <div class="stat"><b><?= (int)$nVente ?></b>en vente</div>
      <div class="stat"><b><?= e(money($encaisse)) ?></b>loyers encaissés</div>
      <div class="stat"><b><?= e(money($commission)) ?></b>commission YOUPENDI</div>
      <div class="stat"><b><?= e(money($net)) ?></b>net bailleur</div>
    </div>
    <div class="panel">
      <p>Période <strong><?= e($mois) ?></strong>. Encaissements − commission (<?= e($taux) ?> %) = net à recevoir.</p>
      <p class="muted">Les biens « en attente » ne sont pas encore visibles du public tant que l’admin ne les a pas approuvés.</p>
    </div>
    <p><a class="btn" href="<?= e(base_url('espace/proprietaire')) ?>">Mon espace</a></p>
  </div>
</section>
