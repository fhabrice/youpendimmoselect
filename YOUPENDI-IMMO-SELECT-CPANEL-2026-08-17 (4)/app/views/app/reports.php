<?php
$labels = [
  'dashboard' => 'Vue d’ensemble',
  'portfolio' => 'Portefeuille immobilier',
  'occupation' => 'Occupation',
  'vacant' => 'Biens vacants',
  'owners' => 'Par propriétaire',
  'agents' => 'Par agent immobilier',
  'tenants' => 'Locataires',
  'contracts' => 'Contrats',
  'movements' => 'Mouvements',
];
$reportCodes = [
  'dashboard' => 'DASH',
  'portfolio' => 'A',
  'occupation' => 'B',
  'vacant' => 'C',
  'owners' => 'D',
  'agents' => 'E',
  'tenants' => 'F',
  'contracts' => 'G',
  'movements' => 'H',
];
$reportDescriptions = [
  'dashboard' => 'Indicateurs clés et priorités du portefeuille',
  'portfolio' => 'Biens, localisation, propriétaires et responsables',
  'occupation' => 'Occupation, vacance et évolution dans le temps',
  'vacant' => 'Biens vacants à relancer commercialement',
  'owners' => 'Synthèse des biens confiés par propriétaire',
  'agents' => 'Portefeuille et performance par agent',
  'tenants' => 'Locataires et périodes de leurs contrats',
  'contracts' => 'État des contrats et alertes d’échéance',
  'movements' => 'Entrées, sorties et changements du portefeuille',
];
$reportTitle = $labels[$report] ?? 'Rapports du portefeuille';
$reportCode = $reportCodes[$report] ?? 'R';
$reportReference = 'RPT-' . $reportCode . '-' . date('Ymd') . '-U' . (int) (user()['id'] ?? 0);
$reportViewer = (user()['role'] ?? '') === 'owner' ? 'Rapport du bailleur' : 'Rapport administratif';
$link = static function (string $name, array $extra = []): string {
    return base_url('app/rapports?' . qs(array_merge($_GET, ['report' => $name], $extra), ['export', 'page']));
};
$pdfPreviewLink = base_url('app/rapports/pdf?' . qs(['report' => $report], ['export', 'page', 'download']));
$pdfDownloadLink = base_url('app/rapports/pdf?' . qs(['report' => $report, 'download' => '1'], ['export', 'page']));
$activeRows = $rows[$report] ?? [];
?>
<div class="report-page tw-mx-auto tw-max-w-[1600px] tw-space-y-5">
  <header class="report-document-header tw-overflow-hidden tw-rounded-2xl tw-border tw-border-sky-200 tw-bg-gradient-to-r tw-from-white tw-to-sky-50 tw-shadow-sm">
    <div class="report-document-brand">
      <img src="<?= e(asset('img/logo-youpendi.png')) ?>" alt="YOUPENDI IMMO SELECT">
      <div>
        <strong>YOUPENDI</strong>
        <span>IMMO SELECT</span>
      </div>
    </div>
    <div class="report-document-title">
      <p class="kicker">Document de pilotage immobilier</p>
      <h2>Rapport <?= e($reportCode) ?> — <?= e($reportTitle) ?></h2>
      <p><?= e($reportViewer) ?> · Portefeuille immobilier</p>
    </div>
    <dl class="report-document-meta">
      <div><dt>Référence</dt><dd><?= e($reportReference) ?></dd></div>
      <div><dt>Édité le</dt><dd><?= e(date('d/m/Y à H:i')) ?></dd></div>
    </dl>
  </header>
  <div class="report-head tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-white tw-p-5 tw-shadow-sm md:tw-p-6">
    <div>
      <p class="kicker">Pilotage immobilier</p>
      <h1 class="app-title">Rapport <?= e($reportCode) ?> — <?= e($reportTitle) ?></h1>
      <p class="muted">Données visibles selon le périmètre autorisé de votre compte.</p>
    </div>
    <div class="report-actions no-print">
      <a class="btn btn-outline btn-sm" href="<?= e($pdfPreviewLink) ?>" target="_blank" rel="noopener">Visualiser le PDF</a>
      <a class="btn btn-sm" href="<?= e($pdfDownloadLink) ?>">Télécharger le PDF</a>
      <a class="btn btn-outline btn-sm" href="<?= e($link($report, ['export' => 'csv'])) ?>">Télécharger Excel/CSV</a>
    </div>
  </div>

  <section class="no-print tw-flex tw-flex-col tw-gap-4 tw-rounded-2xl tw-border tw-border-emerald-200 tw-bg-gradient-to-r tw-from-emerald-50 tw-to-sky-50 tw-p-5 sm:tw-flex-row sm:tw-items-center sm:tw-justify-between" aria-label="État de l’aperçu">
    <div class="tw-flex tw-items-start tw-gap-4">
      <span class="tw-grid tw-h-11 tw-w-11 tw-shrink-0 tw-place-items-center tw-rounded-xl tw-bg-emerald-600 tw-text-xl tw-font-black tw-text-white tw-shadow-md">✓</span>
      <div>
        <p class="tw-m-0 tw-text-xs tw-font-extrabold tw-uppercase tw-tracking-[0.16em] tw-text-emerald-700">Aperçu Tailwind prêt</p>
        <h2 class="tw-mb-1 tw-mt-1 tw-text-lg tw-font-extrabold tw-text-slate-900"><?= e($reportTitle) ?></h2>
        <p class="tw-m-0 tw-text-sm tw-text-slate-600"><?= e($reportDescriptions[$report] ?? '') ?>. L’aperçu utilise exactement le périmètre filtré ci-dessous.</p>
      </div>
    </div>
    <span class="tw-inline-flex tw-w-fit tw-items-center tw-gap-2 tw-rounded-full tw-bg-white tw-px-4 tw-py-2 tw-text-xs tw-font-bold tw-text-slate-700 tw-shadow-sm"><i class="tw-h-2 tw-w-2 tw-rounded-full tw-bg-emerald-500"></i><?= (int) $summary['rows'] ?> ligne(s)</span>
  </section>

  <form class="report-filters no-print tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-white tw-p-5 tw-shadow-sm md:tw-p-6" method="get" action="<?= e(base_url('app/rapports')) ?>">
    <div class="report-selector">
      <div class="report-selector-heading">
        <div>
          <p class="kicker">Sélection du document</p>
          <h2>Quel rapport souhaitez-vous consulter ou imprimer&nbsp;?</h2>
          <p class="muted">Choisissez un type de rapport, appliquez vos filtres, puis validez.</p>
        </div>
        <span class="report-selector-count">1 rapport à la fois</span>
      </div>
      <label class="report-select-field">Type de rapport
        <select name="report" aria-label="Type de rapport">
          <?php foreach ($labels as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $report === $key ? 'selected' : '' ?>>
              <?= e($reportCodes[$key] . ' — ' . $label) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <small class="muted"><?= e($reportDescriptions[$report] ?? '') ?></small>
      </label>
    </div>
    <div class="report-filter-heading">
      <div><p class="kicker">Périmètre des données</p><strong>Affiner le rapport</strong></div>
      <span class="muted">Les filtres s’appliquent à l’écran et à l’impression.</span>
    </div>
    <div class="report-filter-fields">
      <label>Période début<input type="date" name="from" value="<?= e($filters['from']) ?>"></label>
      <label>Période fin<input type="date" name="to" value="<?= e($filters['to']) ?>"></label>
      <label>Province
        <select name="province"><option value="">Toutes les provinces</option><?php foreach ($options['provinces'] as $v => $label): ?><option value="<?= e($v) ?>" <?= $filters['province']===$v?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select>
      </label>
      <label>Ville
        <select name="ville" data-selected-city="<?= e($filters['ville']) ?>"><option value="">Toutes les villes</option><?php foreach ($options['cities'] as $v): ?><option value="<?= e($v) ?>" <?= $filters['ville']===$v?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select>
      </label>
      <label>Commune
        <select name="commune"><option value="">Toutes les communes</option><?php foreach ($options['communes'] as $v): ?><option value="<?= e($v) ?>" <?= $filters['commune']===$v?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select>
      </label>
      <label>Quartier
        <select name="quartier"><option value="">Tous les quartiers</option><?php foreach ($options['quartiers'] as $v): ?><option value="<?= e($v) ?>" <?= $filters['quartier']===$v?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select>
      </label>
      <label>Propriétaire
        <select name="owner_id"><option value="">Tous les propriétaires</option><?php foreach ($options['owners'] as $o): ?><option value="<?= (int)$o['id'] ?>" <?= (int)$filters['owner_id']===(int)$o['id']?'selected':'' ?>><?= e(trim($o['first_name'].' '.$o['last_name'])) ?></option><?php endforeach; ?></select>
      </label>
      <label>Agent immobilier
        <select name="agent_id"><option value="">Tous les agents</option><?php foreach ($options['agents'] as $a): ?><option value="<?= (int)$a['id'] ?>" <?= (int)$filters['agent_id']===(int)$a['id']?'selected':'' ?>><?= e(trim(($a['first_name']??'').' '.($a['last_name']??''))) ?></option><?php endforeach; ?></select>
      </label>
      <label>Catégorie
        <select name="category"><option value="">Toutes</option><?php foreach ($options['categories'] as $v => $label): ?><option value="<?= e($v) ?>" <?= $filters['category']===$v?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select>
      </label>
      <label>Type de bien
        <select name="type"><option value="">Tous les types</option><?php foreach ($options['types'] as $v => $label): ?><option value="<?= e($v) ?>" <?= $filters['type']===$v?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select>
      </label>
      <label>Statut du bien
        <select name="status"><option value="">Tous les statuts</option><?php foreach ($options['statuses'] as $v => $label): ?><option value="<?= e($v) ?>" <?= $filters['status']===$v?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select>
      </label>
      <label>Statut du contrat
        <select name="contract_status"><option value="">Tous les statuts</option><?php foreach ($options['contractStatuses'] as $v => $label): ?><option value="<?= e($v) ?>" <?= $filters['contract_status']===$v?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select>
      </label>
    </div>
    <div class="report-filter-actions">
      <button class="btn btn-sm" type="submit">Afficher le rapport sélectionné</button>
      <a class="btn btn-outline btn-sm" href="<?= e(base_url('app/rapports')) ?>">Réinitialiser</a>
    </div>
  </form>

  <section class="report-summary tw-rounded-2xl tw-border tw-border-sky-200 tw-bg-sky-50/70 tw-p-5 tw-shadow-sm" aria-label="Synthèse du rapport">
    <div class="report-summary-heading">
      <div><p class="kicker">Synthèse</p><strong>Totaux du périmètre sélectionné</strong></div>
      <span class="muted"><?= (int) $summary['rows'] ?> ligne(s) dans ce rapport</span>
    </div>
    <div class="report-summary-grid">
      <div><span>Biens</span><strong><?= (int) $summary['properties'] ?></strong></div>
      <div><span>Contrats actifs</span><strong><?= (int) $summary['contracts'] ?></strong></div>
      <div><span>Occupés</span><strong><?= (int) $summary['occupied'] ?></strong></div>
      <div><span>Vacants</span><strong><?= (int) $summary['vacant'] ?></strong></div>
      <div><span>Disponibles</span><strong><?= (int) $summary['available'] ?></strong></div>
      <div><span>Commissions</span><strong><?= e(money($summary['commission_total'])) ?></strong></div>
      <div><span>Commissions validées</span><strong><?= e(money($summary['commission_validated'])) ?></strong></div>
      <div><span>Dépenses</span><strong><?= e(money($summary['expenses'])) ?></strong></div>
    </div>
  </section>

  <?php if ($report === 'dashboard'): ?>
    <div class="report-kpis">
      <?php foreach ($kpis as $i => $kpi): ?><article class="report-kpi tone-<?= $i % 4 ?>"><span><?= e($kpi['label']) ?></span><strong><?= e((string)$kpi['value']) ?></strong><small><?= e($kpi['hint']) ?></small></article><?php endforeach; ?>
    </div>
    <div class="report-grid">
      <section class="panel report-panel">
        <div class="section-head"><div><p class="kicker">Occupation</p><h2>État du parc</h2></div><a href="<?= e($link('occupation')) ?>">Voir le rapport</a></div>
        <?php
          $totalActive = max(1, (int)$kpis[0]['value']);
          $occupiedValue = (int)$kpis[2]['value'];
          $availableValue = (int)$kpis[1]['value'];
          $vacantValue = (int)$kpis[3]['value'];
        ?>
        <div class="occupancy-bars">
          <div><span><b>Occupés</b><em><?= $occupiedValue ?></em></span><i><b style="width:<?= min(100, $occupiedValue*100/$totalActive) ?>%"></b></i></div>
          <div><span><b>Disponibles</b><em><?= $availableValue ?></em></span><i><b style="width:<?= min(100, $availableValue*100/$totalActive) ?>%"></b></i></div>
          <div><span><b>Vacants</b><em><?= $vacantValue ?></em></span><i><b style="width:<?= min(100, $vacantValue*100/$totalActive) ?>%"></b></i></div>
        </div>
        <div class="history-strip"><?php foreach ($history as $month): ?><div><span style="height:<?= min(100, (int)$month['value']*12) ?>%"></span><small><?= e($month['label']) ?></small></div><?php endforeach; ?></div>
      </section>
      <section class="panel report-panel">
        <div class="section-head"><div><p class="kicker">À traiter</p><h2>Priorités opérationnelles</h2></div><a href="<?= e($link('vacant')) ?>">Biens vacants</a></div>
        <div class="priority-list">
          <a href="<?= e($link('vacant')) ?>"><strong><?= (int)$kpis[3]['value'] ?></strong><span>biens vacants à commercialiser</span><b>→</b></a>
          <a href="<?= e($link('contracts')) ?>"><strong><?= (int)$kpis[10]['value'] ?></strong><span>contrats à renouveler sous 90 jours</span><b>→</b></a>
          <a href="<?= e($link('movements')) ?>"><strong><?= count($rows['movements']) ?></strong><span>mouvements enregistrés</span><b>→</b></a>
        </div>
      </section>
    </div>
  <?php elseif ($report === 'occupation'): ?>
    <div class="report-kpis compact"><?php foreach ([$kpis[1], $kpis[2], $kpis[3], $kpis[4], $kpis[11]] as $kpi): ?><article class="report-kpi tone-1"><span><?= e($kpi['label']) ?></span><strong><?= e((string)$kpi['value']) ?></strong><small><?= e($kpi['hint']) ?></small></article><?php endforeach; ?></div>
    <section class="panel report-panel"><div class="section-head"><div><p class="kicker">Évolution</p><h2>Occupation dans le temps</h2></div></div><div class="history-large"><?php foreach ($history as $month): ?><div><strong><?= (int)$month['value'] ?></strong><span style="height:<?= min(100, (int)$month['value']*12) ?>%"></span><small><?= e($month['label']) ?></small></div><?php endforeach; ?></div></section>
  <?php else: ?>
    <?php if ($report === 'portfolio'): ?>
      <section class="panel report-panel"><div class="section-head"><div><p class="kicker">Rapport A</p><h2>Portefeuille immobilier</h2></div><span class="muted"><?= count($activeRows) ?> résultat(s)</span></div>
        <div class="table-scroll"><table class="report-table"><thead><tr><th>Référence</th><th>Nom du bien</th><th>Catégorie</th><th>Type</th><th>Province</th><th>Ville</th><th>Commune</th><th>Quartier</th><th>Propriétaire</th><th>Agent responsable</th><th>Statut</th><th>Date d’enregistrement</th></tr></thead><tbody><?php foreach ($activeRows as $r): ?><tr><td><strong><?= e($r['reference']) ?></strong></td><td><?= e($r['title']) ?></td><td><?= e($r['category']) ?></td><td><?= e($r['type']) ?></td><td><?= e($r['province']) ?></td><td><?= e($r['city']) ?></td><td><?= e($r['commune']) ?></td><td><?= e($r['quartier']) ?></td><td><?= e($r['owner']) ?></td><td><?= e($r['agent']) ?></td><td><?= status_badge($r['status']) ?></td><td><?= e(dfr($r['created_at'])) ?></td></tr><?php endforeach; ?></tbody></table></div>
      </section>
    <?php elseif ($report === 'vacant'): ?>
      <section class="panel report-panel"><div class="section-head"><div><p class="kicker">Rapport C</p><h2>Biens vacants</h2></div><span class="muted"><?= count($activeRows) ?> bien(s)</span></div>
        <div class="table-scroll"><table class="report-table"><thead><tr><th>Référence</th><th>Localisation</th><th>Propriétaire</th><th>Agent responsable</th><th>Date de libération</th><th>Durée</th><th>Commercialisation</th></tr></thead><tbody><?php foreach ($activeRows as $r): ?><tr><td><strong><?= e($r['reference']) ?></strong></td><td><?= e($r['location']) ?></td><td><?= e($r['owner']) ?></td><td><?= e($r['agent']) ?></td><td><?= e(dfr($r['released_at'])) ?></td><td><strong><?= (int)$r['vacancy_days'] ?> j</strong></td><td><?= status_badge($r['status']) ?></td></tr><?php endforeach; ?></tbody></table></div>
      </section>
    <?php elseif ($report === 'owners' || $report === 'agents'): ?>
      <section class="panel report-panel"><div class="section-head"><div><p class="kicker">Rapport <?= $report === 'owners' ? 'D' : 'E' ?></p><h2><?= e($labels[$report]) ?></h2></div></div>
        <div class="table-scroll"><table class="report-table"><thead><tr><?php if ($report==='owners'): ?><th>Propriétaire</th><th>Total biens</th><th>Occupés</th><th>Vacants</th><th>Disponibles</th><th>Agents affectés</th><th>Contrats en cours</th><?php else: ?><th>Agent</th><th>Biens affectés</th><th>Disponibles</th><th>Occupés</th><th>Vacants</th><th>Visites</th><th>Locataires</th><th>Contrats</th><th>Taux occupation</th><?php endif; ?></tr></thead><tbody><?php foreach ($activeRows as $r): ?><tr><?php if ($report==='owners'): ?><td><strong><?= e($r['owner']) ?></strong></td><td><?= (int)$r['total'] ?></td><td><?= (int)$r['occupied'] ?></td><td><?= (int)$r['vacant'] ?></td><td><?= (int)$r['available'] ?></td><td><?= e($r['agents'] ?: '—') ?></td><td><?= (int)$r['contracts'] ?></td><?php else: ?><td><strong><?= e($r['agent']) ?></strong></td><td><?= (int)$r['assigned'] ?></td><td><?= (int)$r['available'] ?></td><td><?= (int)$r['occupied'] ?></td><td><?= (int)$r['vacant'] ?></td><td><?= (int)$r['visits'] ?></td><td><?= (int)$r['tenants'] ?></td><td><?= (int)$r['contracts'] ?></td><td><?= e($r['rate']) ?></td><?php endif; ?></tr><?php endforeach; ?></tbody></table></div>
      </section>
    <?php elseif ($report === 'tenants'): ?>
      <section class="panel report-panel"><div class="section-head"><div><p class="kicker">Rapport F</p><h2>Locataires</h2></div></div>
        <div class="table-scroll"><table class="report-table"><thead><tr><th>Nom du locataire</th><th>Bien occupé</th><th>Propriétaire</th><th>Agent responsable</th><th>Date d’entrée</th><th>Début du contrat</th><th>Fin du contrat</th><th>Statut du contrat</th></tr></thead><tbody><?php foreach ($activeRows as $r): ?><tr><td><strong><?= e($r['tenant']) ?></strong></td><td><?= e($r['property']) ?></td><td><?= e($r['owner']) ?></td><td><?= e($r['agent']) ?></td><td><?= e(dfr($r['entry'])) ?></td><td><?= e(dfr($r['start'])) ?></td><td><?= e(dfr($r['end'])) ?></td><td><?= status_badge($r['status']) ?></td></tr><?php endforeach; ?></tbody></table></div>
      </section>
    <?php elseif ($report === 'contracts'): ?>
      <section class="panel report-panel"><div class="section-head"><div><p class="kicker">Rapport G</p><h2>Contrats de location</h2></div></div>
        <?php
          $contractSummary = ['actif' => 0, 'renouvele' => 0, 'expire' => 0, 'resilie' => 0, '30' => 0, '60' => 0, '90' => 0];
          foreach ($activeRows as $contractRow) {
              $statusKey = (string) ($contractRow['status'] ?? '');
              if (isset($contractSummary[$statusKey])) {
                  $contractSummary[$statusKey]++;
              }
              if (str_contains((string) ($contractRow['alert'] ?? ''), '30')) {
                  $contractSummary['30']++;
              } elseif (str_contains((string) ($contractRow['alert'] ?? ''), '60')) {
                  $contractSummary['60']++;
              } elseif (str_contains((string) ($contractRow['alert'] ?? ''), '90')) {
                  $contractSummary['90']++;
              }
          }
        ?>
        <div class="report-kpis compact">
          <article class="report-kpi tone-1"><span>Actifs</span><strong><?= $contractSummary['actif'] ?></strong><small>Contrats en cours</small></article>
          <article class="report-kpi tone-2"><span>Renouvelés</span><strong><?= $contractSummary['renouvele'] ?></strong><small>Contrats renouvelés</small></article>
          <article class="report-kpi tone-3"><span>Expirés</span><strong><?= $contractSummary['expire'] ?></strong><small>Contrats arrivés à terme</small></article>
          <article class="report-kpi tone-0"><span>Résiliés</span><strong><?= $contractSummary['resilie'] ?></strong><small>Contrats résiliés</small></article>
          <article class="report-kpi tone-1"><span>Échéance 30 j</span><strong><?= $contractSummary['30'] ?></strong><small>Alerte prioritaire</small></article>
          <article class="report-kpi tone-2"><span>Échéance 60 j</span><strong><?= $contractSummary['60'] ?></strong><small>À anticiper</small></article>
          <article class="report-kpi tone-3"><span>Échéance 90 j</span><strong><?= $contractSummary['90'] ?></strong><small>À planifier</small></article>
        </div>
        <div class="table-scroll"><table class="report-table"><thead><tr><th>Référence</th><th>Bien</th><th>Locataire</th><th>Propriétaire</th><th>Agent</th><th>Période</th><th>Statut</th><th>Alerte échéance</th></tr></thead><tbody><?php foreach ($activeRows as $r): ?><tr><td><strong><?= e($r['reference']) ?></strong></td><td><?= e($r['property']) ?></td><td><?= e($r['tenant']) ?></td><td><?= e($r['owner']) ?></td><td><?= e($r['agent']) ?></td><td><?= e($r['period']) ?></td><td><?= status_badge($r['status']) ?></td><td><?= $r['alert'] !== '—' ? '<span class="badge badge-warn">'.e($r['alert']).'</span>' : '<span class="muted">—</span>' ?></td></tr><?php endforeach; ?></tbody></table></div>
      </section>
    <?php else: ?>
      <section class="panel report-panel"><div class="section-head"><div><p class="kicker">Rapport H</p><h2>Mouvements du portefeuille</h2></div></div>
        <div class="table-scroll"><table class="report-table"><thead><tr><th>Date</th><th>Mouvement</th><th>Élément</th><th>Localisation</th></tr></thead><tbody><?php foreach ($activeRows as $r): ?><tr><td><strong><?= e(dfr($r['date'])) ?></strong></td><td><?= e($r['type']) ?></td><td><?= e($r['subject']) ?></td><td><?= e($r['location']) ?></td></tr><?php endforeach; ?></tbody></table></div>
      </section>
    <?php endif; ?>
  <?php endif; ?>
  <footer class="report-document-footer">
    <span><strong>YOUPENDI IMMO SELECT</strong> · L’immobilier de confiance</span>
    <span><?= e($reportReference) ?> · Document imprimé le <?= e(date('d/m/Y à H:i')) ?></span>
  </footer>
</div>