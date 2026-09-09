<div class="section-head">
  <div>
    <p class="kicker gold">Pilotage</p>
    <h1 class="app-title"><?= e($heading ?? 'Tableau de bord') ?></h1>
  </div>
  <div class="muted"><?= e(dfr(now(), 'd/m/Y H:i')) ?></div>
</div>
<div class="kpi">
  <?php foreach ($kpis as $k): ?>
    <div class="stat"><b><?= e($k['v']) ?></b><?= e($k['l']) ?></div>
  <?php endforeach; ?>
</div>

<div class="prop-grid" style="margin-top:22px">
  <div class="panel">
    <h3>Activité récente</h3>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Quand</th><th>Action</th><th>Entité</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $l): ?>
          <tr><td><?= e(dfr($l['created_at'], 'd/m H:i')) ?></td><td><?= e($l['action']) ?></td><td><?= e($l['entity']) ?> #<?= e($l['entity_id']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$logs): ?><tr><td colspan="3" class="muted">Aucune activité.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="panel">
    <h3>À traiter</h3>
    <ul>
      <?php foreach ($todo as $t): ?>
        <li style="margin:8px 0"><a href="<?= e(base_url($t[0])) ?>"><?= e($t[1]) ?></a></li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>
