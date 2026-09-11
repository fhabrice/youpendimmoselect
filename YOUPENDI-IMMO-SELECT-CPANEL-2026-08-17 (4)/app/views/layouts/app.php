<?php
$u = user();
$role = $u['role'] ?? '';
$ucount = unread_count();
$menu = [];
if (in_array($role, ['admin','finance','manager','supervisor','agent'], true)) {
    $menu[] = ['app', 'Dashboard', '▣'];
    if (can('properties.view') || can('properties.own') || can('properties.create')) {
        $menu[] = ['group', 'Immobilier', [
            ['app/biens', 'Tous les biens'],
            ['app/biens/nouveau', 'Ajouter un bien'],
            ['app/biens?status=disponible', 'Disponibles'],
            ['app/biens?status=occupe', 'Occupés'],
            ['app/biens?status=maintenance', 'Maintenance'],
        ]];
    }
    if (can('prospects.*') || can('prospects.own')) {
        $menu[] = ['group', 'CRM', [
            ['app/crm', 'Tableau de bord CRM'],
            ['app/prospects', 'Tous les prospects'],
            ['app/prospects?type=proprietaire', 'Propriétaires potentiels'],
            ['app/prospects?type=locataire', 'Locataires potentiels'],
            ['app/prospects/nouveau', 'Nouveau prospect'],
        ]];
    }
    $locationMenu = [
        ['app/visites', 'Visites'],
        ['app/locataires', 'Locataires'],
    ];
    if (can('contracts.view')) {
        $locationMenu[] = ['app/contrats', 'Contrats'];
    }
    if (can('contracts.*')) {
        $locationMenu[] = ['app/contrats/nouveau', 'Nouveau contrat'];
    }
    $locationMenu[] = ['app/loyers', 'Loyers'];
    $locationMenu[] = ['app/quittances', 'Quittances'];
    $locationMenu[] = ['app/loyers?status=en_retard', 'Impayés'];
    $menu[] = ['group', 'Location', $locationMenu];
    $menu[] = ['group', 'Propriétaires', [
        ['app/proprietaires', 'Liste'],
        ['app/reversements', 'Reversements'],
            ['app/rapports', 'Rapports du portefeuille'],
    ]];
    $menu[] = ['group', 'Immo Stay', [
        ['app/stay', 'Logements'],
        ['app/reservations', 'Réservations'],
        ['app/housekeeping', 'Housekeeping'],
    ]];
    if (in_array($role, ['admin','supervisor','agent'], true)) {
        $menu[] = ['group', 'Agents', [
            ['app/agents', 'Agents'],
            ['app/commissions', 'Commissions'],
            ['app/taches', 'Tâches / agenda'],
        ]];
    }
    $menu[] = ['group', 'Gestion', [
        ['app/maintenance', 'Maintenance'],
        ['app/prestataires', 'Prestataires'],
        ['app/depenses', 'Dépenses'],
        ['app/documents', 'Documents'],
    ]];
    if (can('payments.*') || $role === 'admin') {
        $menu[] = ['group', 'Finance', [
            ['app/paiements', 'Encaissements'],
            ['app/commissions', 'Commissions'],
            ['app/reversements', 'Reversements'],
            ['app/rapports', 'Rapports financiers'],
        ]];
    }
    if ($role === 'admin') {
        $menu[] = ['group', 'Administration', [
            ['app/utilisateurs', 'Utilisateurs'],
            ['app/parametres', 'Paramètres'],
            ['app/remise-a-zero', 'Remise à zéro'],
            ['app/logs', 'Journal d’activité'],
        ]];
    }
    $menu[] = ['app/messages', 'Messages', '✉'];
    $menu[] = ['app/notifications', 'Notifications' . ($ucount ? " ($ucount)" : ''), '●'];
} elseif ($role === 'owner') {
    $menu = [
        ['espace/proprietaire', 'Dashboard', '▣'],
        ['espace/proprietaire/ajouter', 'Publier un bien', '+'],
        ['espace/proprietaire/biens', 'Mes biens', '⌂'],
        ['espace/proprietaire/locataires', 'Mes locataires', '☺'],
        ['espace/proprietaire/revenus', 'Revenus & loyers', '$'],
        ['espace/proprietaire/depenses', 'Dépenses', '–'],
        ['espace/proprietaire/reversements', 'Reversements', '↓'],
        ['espace/proprietaire/reservations', 'Réservations', '▣'],
        ['espace/proprietaire/incidents', 'Incidents', '!'],
        ['espace/proprietaire/documents', 'Documents', '▤'],
        ['app/rapports', 'Rapport de portefeuille', '▦'],
        ['app/messages', 'Messages', '✉'],
    ];
} elseif ($role === 'tenant') {
    $menu = [
        ['espace/locataire', 'Dashboard', '▣'],
        ['espace/locataire/logement', 'Mon logement', '⌂'],
        ['espace/locataire/contrat', 'Mon contrat', '▤'],
        ['espace/locataire/paiements', 'Mes paiements', '$'],
        ['espace/locataire/quittances', 'Quittances', '▣'],
        ['espace/locataire/demandes', 'Mes demandes', '✎'],
        ['espace/locataire/maintenance', 'Maintenance', '!'],
        ['espace/locataire/documents', 'Documents', '▤'],
        ['app/notifications', 'Notifications', '●'],
        ['app/messages', 'Messages', '✉'],
    ];
} else {
    $menu = [
        ['espace/voyageur', 'Mes réservations', '▣'],
        ['immo-stay', 'Rechercher', '⌕'],
        ['app/messages', 'Messages', '✉'],
    ];
}
$path = request_path();
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(($title ?? 'Espace') . ' — YOUPENDI') ?></title>
  <link rel="icon" href="<?= e(asset('img/logo.svg')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
  <?php if (!empty($tailwind)): ?><link rel="stylesheet" href="<?= e(asset('css/tailwind-youpendi.css')) ?>"><?php endif; ?>
</head>
<body>
<div class="app-shell">
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="<?= e(base_url()) ?>" style="margin:8px 8px 22px;color:#fff">
      <span class="brand-mark">Y</span>
      <span><span class="brand-name">YOUPENDI</span><span class="brand-sub">IMMO SELECT</span></span>
    </a>
    <nav class="side-nav">
      <?php foreach ($menu as $item): ?>
        <?php if (($item[0] ?? '') === 'group'): ?>
          <details open>
            <summary><?= e($item[1]) ?></summary>
            <?php foreach ($item[2] as $sub):
              $href = base_url($sub[0]);
              $active = rtrim($path,'/') === '/'.trim(explode('?', $sub[0])[0], '/');
            ?>
              <a class="<?= $active?'active':'' ?>" href="<?= e($href) ?>"><?= e($sub[1]) ?></a>
            <?php endforeach; ?>
          </details>
        <?php else:
          $href = base_url($item[0]);
          $active = rtrim($path,'/') === '/'.trim($item[0], '/');
        ?>
          <a class="<?= $active?'active':'' ?>" href="<?= e($href) ?>"><?= e($item[2] ?? '•') ?> <?= e($item[1]) ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
      <a href="<?= e(base_url()) ?>">← Site public</a>
      <a href="<?= e(base_url('deconnexion')) ?>" style="color:#fecaca;font-weight:700">⏻ Déconnexion</a>
    </nav>
  </aside>
  <div>
    <div class="top">
      <div style="display:flex;align-items:center;gap:10px">
        <button class="burger icon-btn" id="sideburger" aria-label="Menu" style="color:var(--navy);border:1px solid var(--line)">☰</button>
        <strong><?= e(role_label($role)) ?></strong>
      </div>
      <div class="userchip">
        <a href="<?= e(base_url('app/notifications')) ?>"><?php if ($ucount): ?><span class="notice-dot"></span><?php endif; ?> Notifications</a>
        <div class="avatar"><?= e(initials($u['first_name']??'', $u['last_name']??'')) ?></div>
        <div>
          <strong><?= e(full_name($u)) ?></strong><br>
          <span class="muted" style="font-size:12px"><?= e($u['email'] ?? '') ?></span>
        </div>
      </div>
    </div>
    <div class="app-main">
      <?php foreach (flashes() as $type => $msgs): foreach ($msgs as $m): ?>
        <div class="flash flash-<?= e($type === 'error' ? 'error' : $type) ?>"><?= e($m) ?></div>
      <?php endforeach; endforeach; ?>
      <?= $content ?>
    </div>
  </div>
</div>
<script>
window.YP_CITIES_BY_PROVINCE = <?= json_encode(cities_by_province(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.YP_COMMUNES = <?= json_encode(communes_map(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.YP_QUARTIERS = <?= json_encode(quartiers_map(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
