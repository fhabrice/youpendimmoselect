<?php

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/http.php';
require dirname(__DIR__) . '/app/http_app.php';
require dirname(__DIR__) . '/app/http_more.php';
require dirname(__DIR__) . '/app/http_reports.php';

$path = request_path();
$method = request_method();

try {
    dispatch($path, $method);
} catch (Throwable $e) {
    @file_put_contents(dirname(__DIR__) . '/storage/last-error.txt', date('c') . ' ' . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine());
    if (config('debug') || isset($_GET['debug'])) {
        http_response_code(500);
        echo '<pre>' . e($e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine()) . '</pre>';
        exit;
    }
    http_response_code(500);
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Erreur</title>';
    echo '<link rel="stylesheet" href="' . e(asset('css/app.css')) . '"></head><body class="app-main">';
    echo '<div class="container" style="padding:40px 16px"><h1>Erreur d’enregistrement</h1>';
    echo '<p class="flash flash-error">' . e($e->getMessage()) . '</p>';
    echo '<p><a class="btn" href="' . e(base_url('app/agents')) . '">Retour aux agents</a> ';
    echo '<a class="btn btn-outline" href="' . e(base_url('app')) . '">Tableau de bord</a></p></div></body></html>';
    exit;
}

function dispatch(string $path, string $method): void
{
    if ($path === '/health') {
        json_out(['ok' => true, 'app' => 'YOUPENDI IMMO SELECT']);
    }

    $routes = [
        'GET /' => 'public_home',
        'GET /biens' => fn() => public_search(false),
        'GET /ventes' => fn() => public_search(false),
        'GET /immo-stay' => fn() => public_search(true),
        'GET /services' => 'public_services',
        'GET /confier-mon-bien' => 'public_confier_bien',
        'POST /confier-mon-bien' => 'public_confier_bien',
        'GET /confier-ma-recherche' => 'public_confier_recherche',
        'POST /confier-ma-recherche' => 'public_confier_recherche',
        'GET /contact' => 'public_contact',
        'POST /contact' => 'public_contact',
        'GET /connexion' => 'auth_login',
        'POST /connexion' => 'auth_login',
        'GET /inscription' => 'auth_register',
        'POST /inscription' => 'auth_register',
        'GET /inscription-bailleur' => 'owner_register',
        'POST /inscription-bailleur' => 'owner_register',
        'GET /espace/proprietaire/ajouter' => 'owner_add_property',
        'POST /espace/proprietaire/ajouter' => 'owner_add_property',
        'GET /espace/proprietaire/rapport' => 'owner_report',
        'GET /deconnexion' => 'auth_logout',
        'GET /mot-de-passe-oublie' => 'auth_forgot',
        'POST /mot-de-passe-oublie' => 'auth_forgot',
        'GET /install' => 'install_page',
        'POST /install' => 'install_page',
        'GET /app' => 'app_dashboard',
        'GET /app/biens' => 'app_properties',
        'GET /app/biens/nouveau' => fn() => app_property_form(null),
        'POST /app/biens/nouveau' => fn() => app_property_form(null),
        'GET /app/prospects' => 'app_prospects',
        'GET /app/prospects/nouveau' => fn() => app_prospect_form(null),
        'POST /app/prospects/nouveau' => fn() => app_prospect_form(null),
        'GET /app/visites' => 'app_visits',
        'GET /app/visites/nouveau' => 'app_visit_new',
        'POST /app/visites/nouveau' => 'app_visit_new',
        'GET /app/proprietaires' => 'app_owners',
        'GET /app/proprietaires/nouveau' => fn() => app_owner_form(null),
        'POST /app/proprietaires/nouveau' => fn() => app_owner_form(null),
        'GET /app/locataires' => 'app_tenants',
        'GET /app/locataires/nouveau' => fn() => app_tenant_form(null),
        'POST /app/locataires/nouveau' => fn() => app_tenant_form(null),
        'GET /app/contrats' => 'app_contracts',
        'GET /app/contrats/nouveau' => 'app_contract_new',
        'POST /app/contrats/nouveau' => 'app_contract_new',
        'GET /app/loyers' => 'app_rents',
        'GET /app/quittances' => 'app_receipts',
        'GET /app/paiements' => 'app_payments',
        'GET /app/commissions' => 'app_commissions',
        'GET /app/reversements' => 'app_payouts',
        'GET /app/reversements/nouveau' => 'app_payout_new',
        'POST /app/reversements/nouveau' => 'app_payout_new',
        'GET /app/stay' => 'app_stay',
        'GET /app/reservations' => 'app_reservations',
        'GET /app/housekeeping' => 'app_housekeeping',
        'POST /app/housekeeping' => 'app_housekeeping',
        'GET /app/maintenance' => 'app_maintenance',
        'GET /app/maintenance/nouveau' => fn() => app_maint_form(null),
        'POST /app/maintenance/nouveau' => fn() => app_maint_form(null),
        'GET /app/prestataires' => 'app_vendors',
        'POST /app/prestataires' => 'app_vendors',
        'GET /app/depenses' => 'app_expenses',
        'GET /app/depenses/nouveau' => 'app_expense_new',
        'POST /app/depenses/nouveau' => 'app_expense_new',
        'GET /app/agents' => 'app_agents',
        'GET /app/agents/nouveau' => fn() => app_agent_form(null),
        'POST /app/agents/nouveau' => fn() => app_agent_form(null),
        'GET /app/taches' => 'app_tasks',
        'POST /app/taches' => 'app_tasks',
        'GET /app/documents' => 'app_documents',
        'POST /app/documents' => 'app_documents',
        'GET /app/utilisateurs' => 'app_users',
        'GET /app/utilisateurs/nouveau' => fn() => app_user_form(null),
        'POST /app/utilisateurs/nouveau' => fn() => app_user_form(null),
        'GET /app/parametres' => 'app_settings',
        'POST /app/parametres' => 'app_settings',
        'GET /app/remise-a-zero' => 'app_reset_platform',
        'POST /app/remise-a-zero' => 'app_reset_platform',
        'GET /app/logs' => 'app_logs',
        'GET /app/notifications' => 'app_notifications',
        'POST /app/notifications' => 'app_notifications',
        'GET /app/messages' => 'app_messages',
        'POST /app/messages' => 'app_messages',
        'GET /app/rapports/pdf' => 'app_portfolio_report_pdf',
        'GET /app/rapports' => 'app_portfolio_reports',
        'GET /espace/proprietaire' => 'portal_owner',
        'GET /espace/locataire' => 'portal_tenant',
        'GET /espace/voyageur' => 'portal_traveler',
    ];

    $key = $method . ' ' . $path;
    if (isset($routes[$key])) {
        $fn = $routes[$key];
        is_string($fn) ? $fn() : $fn();
        return;
    }

    if (preg_match('#^/biens/(\d+)$#', $path, $m) && $method === 'GET') {
        public_property((int) $m[1]);
        return;
    }
    if (preg_match('#^/biens/(\d+)/visite$#', $path, $m) && $method === 'POST') {
        public_visit((int) $m[1]);
        return;
    }
    if (preg_match('#^/biens/(\d+)/favori$#', $path, $m) && $method === 'POST') {
        public_fav((int) $m[1]);
        return;
    }
    if (preg_match('#^/immo-stay/(\d+)$#', $path, $m) && $method === 'GET') {
        public_stay_show((int) $m[1]);
        return;
    }
    if (preg_match('#^/immo-stay/(\d+)/reserver$#', $path, $m) && $method === 'POST') {
        public_reserve((int) $m[1]);
        return;
    }
    if (preg_match('#^/reset/([a-f0-9]+)$#', $path, $m)) {
        auth_reset($m[1]);
        return;
    }
    if (preg_match('#^/quittance/(\d+)$#', $path, $m)) {
        print_receipt((int) $m[1]);
        return;
    }
    if (preg_match('#^/app/biens/(\d+)$#', $path, $m) && $method === 'GET') {
        app_property_show((int) $m[1]);
        return;
    }
    if (preg_match('#^/app/biens/(\d+)/edit$#', $path, $m)) {
        app_property_form((int) $m[1]);
        return;
    }
    if (preg_match('#^/app/biens/(\d+)/publier$#', $path, $m) && $method === 'POST') {
        app_publish((int) $m[1]);
        return;
    }
    if (preg_match('#^/app/biens/(\d+)/supprimer$#', $path, $m) && $method === 'POST') {
        app_property_delete((int) $m[1]);
        return;
    }
    if (preg_match('#^/app/prospects/(\d+)/statut$#', $path, $m) && $method === 'POST') {
        app_prospect_status_update((int) $m[1]);
        return;
    }
    if (preg_match('#^/app/prospects/(\d+)/convertir-proprietaire$#', $path, $m) && $method === 'POST') {
        app_prospect_convert((int) $m[1], 'owner');
        return;
    }
    if (preg_match('#^/app/prospects/(\d+)/convertir-locataire$#', $path, $m) && $method === 'POST') {
        app_prospect_convert((int) $m[1], 'tenant');
        return;
    }
    if (preg_match('#^/app/prospects/(\d+)$#', $path, $m)) {
        app_prospect_form((int) $m[1]);
        return;
    }
    if (preg_match('#^/app/visites/(\d+)/statut$#', $path, $m) && $method === 'POST') {
        app_visit_status_update((int) $m[1]);
        return;
    }
    if (preg_match('#^/app/visites/(\d+)/ok$#', $path, $m) && $method === 'POST') {
        app_visit_ok((int) $m[1]);
        return;
    }
    if (preg_match('#^/app/proprietaires/(\d+)/supprimer$#', $path, $m) && $method === 'POST') {
        app_owner_delete((int) $m[1]);
        return;
    }
    if (preg_match('#^/app/proprietaires/(\d+)$#', $path, $m)) {
        app_owner_form((int) $m[1]);
        return;
    }
    if (preg_match('#^/app/locataires/(\d+)/supprimer$#', $path, $m) && $method === 'POST') {
        app_tenant_delete((int) $m[1]);
        return;
    }
    if (preg_match('#^/app/locataires/(\d+)$#', $path, $m)) {
        app_tenant_form((int) $m[1]);
        return;
    }
    if (preg_match('#^/app/contrats/(\d+)/cloturer$#', $path, $m) && $method === 'POST') {
        app_contract_close((int) $m[1]);
        return;
    }
    if (preg_match('#^/app/contrats/(\d+)$#', $path, $m) && $method === 'GET') {
        app_contract_show((int) $m[1]);
        return;
    }
    if (preg_match('#^/app/loyers/(\d+)/payer$#', $path, $m)) {
        app_rent_pay((int) $m[1]);
        return;
    }
    if (preg_match('#^/app/paiements/(\d+)/confirmer$#', $path, $m) && $method === 'POST') {
        app_payment_confirm((int) $m[1]);
        return;
    }
    if (preg_match('#^/app/commissions/(\d+)/valider$#', $path, $m) && $method === 'POST') {
        app_commission_ok((int) $m[1]);
        return;
    }
    if (preg_match('#^/app/reversements/(\d+)/payer$#', $path, $m) && $method === 'POST') {
        app_payout_pay((int) $m[1]);
        return;
    }
    if (preg_match('#^/app/reservations/(\d+)$#', $path, $m)) {
        app_reservation_show((int) $m[1]);
        return;
    }
    if (preg_match('#^/app/maintenance/(\d+)$#', $path, $m)) {
        app_maint_form((int) $m[1]);
        return;
    }
    if (preg_match('#^/app/depenses/(\d+)/valider$#', $path, $m) && $method === 'POST') {
        app_expense_ok((int) $m[1]);
        return;
    }
    if (preg_match('#^/app/agents/(\d+)/supprimer$#', $path, $m) && $method === 'POST') {
        app_agent_delete((int) $m[1]);
        return;
    }
    if (preg_match('#^/app/agents/(\d+)$#', $path, $m) && in_array($method, ['GET', 'POST'], true)) {
        app_agent_form((int) $m[1]);
        return;
    }
    if (preg_match('#^/app/utilisateurs/(\d+)$#', $path, $m)) {
        app_user_form((int) $m[1]);
        return;
    }
    if (preg_match('#^/espace/proprietaire/([a-z]+)$#', $path, $m)) {
        portal_owner_list($m[1]);
        return;
    }
    if (preg_match('#^/espace/locataire/([a-z]+)$#', $path, $m)) {
        portal_tenant_page($m[1]);
        return;
    }

    abort(404, 'Cette page n’existe pas.');
}
