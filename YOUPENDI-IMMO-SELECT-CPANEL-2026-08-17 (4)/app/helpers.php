<?php

function config(?string $key = null, $default = null)
{
    static $cfg;
    if ($cfg === null) {
        $cfg = require dirname(__DIR__) . '/config.php';
        $override = dirname(__DIR__) . '/storage/config.local.php';
        if (is_file($override)) {
            $local = require $override;
            $cfg = array_replace_recursive($cfg, $local);
        }
    }
    if ($key === null) {
        return $cfg;
    }
    $cur = $cfg;
    foreach (explode('.', $key) as $p) {
        if (!is_array($cur) || !array_key_exists($p, $cur)) {
            return $default;
        }
        $cur = $cur[$p];
    }
    return $cur;
}

function base_url(string $path = ''): string
{
    $configured = rtrim((string) (defined('YP_BASE_URL') ? YP_BASE_URL : config('base_url')), '/');
    if ($configured === '') {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $dir = rtrim(str_replace('\\', '/', dirname($script)), '/.');
        $configured = $scheme . '://' . $host . ($dir === '' ? '' : $dir);
    }
    $path = ltrim($path, '/');
    return $configured . ($path !== '' ? '/' . $path : '');
}

function asset(string $path): string
{
    return base_url('assets/' . ltrim($path, '/'));
}

function upload_url(string $path): string
{
    return base_url('uploads/' . ltrim($path, '/'));
}

function redirect(string $to, int $code = 302): never
{
    if (!preg_match('#^https?://#', $to)) {
        $to = base_url(ltrim($to, '/'));
    }
    header('Location: ' . $to, true, $code);
    exit;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function today(): string
{
    return date('Y-m-d');
}

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function slugify(string $s): string
{
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
    $s = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $s) ?? '');
    return trim($s, '-');
}

function money($amount, ?string $currency = null): string
{
    $currency = $currency ?: config('currency', 'USD');
    $n = number_format((float) $amount, 2, ',', ' ');
    return $n . ' ' . $currency;
}

function dfr(?string $date, string $fmt = 'd/m/Y'): string
{
    if (!$date) {
        return '—';
    }
    $t = strtotime($date);
    return $t ? date($fmt, $t) : $date;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $t = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!$t || !hash_equals($_SESSION['_csrf'] ?? '', $t)) {
        http_response_code(419);
        flash('error', 'Session expirée. Veuillez réessayer.');
        redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }
}

function flash(string $type, ?string $msg = null)
{
    if ($msg === null) {
        $all = $_SESSION['_flash'][$type] ?? [];
        unset($_SESSION['_flash'][$type]);
        return $all;
    }
    $_SESSION['_flash'][$type][] = $msg;
}

function flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

function old(string $key, $default = '')
{
    $bag = $_SESSION['_old'] ?? [];
    return $bag[$key] ?? $default;
}

function remember_old(array $data): void
{
    $_SESSION['_old'] = $data;
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

function request_method(): string
{
    $m = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($m === 'POST' && !empty($_POST['_method'])) {
        return strtoupper($_POST['_method']);
    }
    return $m;
}

function request_path(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $base = rtrim(str_replace('\\', '/', dirname($script)), '/.');
    if ($base && str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base)) ?: '/';
    }
    $uri = '/' . trim($uri, '/');
    return $uri === '/' ? '/' : rtrim($uri, '/');
}

function is_post(): bool
{
    return request_method() === 'POST';
}

function input(string $key, $default = null)
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function str_input(string $key, $default = ''): string
{
    return trim((string) input($key, $default));
}

function int_input(string $key, int $default = 0): int
{
    return (int) input($key, $default);
}

function float_input(string $key, float $default = 0.0): float
{
    $v = str_replace([' ', ','], ['', '.'], (string) input($key, $default));
    return (float) $v;
}

function paginate(int $total, int $per = 12, string $param = 'page'): array
{
    $page = max(1, (int) ($_GET[$param] ?? 1));
    $pages = max(1, (int) ceil($total / $per));
    $page = min($page, $pages);
    return [
        'page' => $page,
        'per' => $per,
        'total' => $total,
        'pages' => $pages,
        'offset' => ($page - 1) * $per,
    ];
}

function qs(array $extra = [], array $drop = []): string
{
    $q = $_GET;
    foreach ($drop as $d) {
        unset($q[$d]);
    }
    $q = array_merge($q, $extra);
    return http_build_query(array_filter($q, fn($v) => $v !== '' && $v !== null));
}

function view(string $name, array $data = [], ?string $layout = 'public'): void
{
    extract($data, EXTR_SKIP);
    $contentView = dirname(__DIR__) . '/app/views/' . $name . '.php';
    if (!is_file($contentView)) {
        http_response_code(500);
        echo 'Vue introuvable : ' . e($name);
        return;
    }
    ob_start();
    include $contentView;
    $content = ob_get_clean();
    if ($layout) {
        $layoutFile = dirname(__DIR__) . '/app/views/layouts/' . $layout . '.php';
        include $layoutFile;
    } else {
        echo $content;
    }
}

function abort(int $code, string $msg = 'Erreur'): never
{
    http_response_code($code);
    if ($code === 404) {
        view('errors/404', ['message' => $msg], user() ? 'app' : 'public');
    } else {
        view('errors/error', ['code' => $code, 'message' => $msg], user() ? 'app' : 'public');
    }
    exit;
}

function json_out($data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function setting(string $key, $default = null)
{
    static $cache;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (db()->all('SELECT k, v FROM settings') as $row) {
                $cache[$row['k']] = $row['v'];
            }
        } catch (Throwable $e) {
            return $default;
        }
    }
    return $cache[$key] ?? $default;
}

function set_setting(string $key, $value): void
{
    db()->exec(
        'INSERT INTO settings (k, v, updated_at) VALUES (?, ?, ?)
         ON CONFLICT(k) DO UPDATE SET v = excluded.v, updated_at = excluded.updated_at',
        [$key, (string) $value, now()]
    );
}

function property_types(): array
{
    return [
        'appartement' => 'Appartement',
        'maison' => 'Maison',
        'villa' => 'Villa',
        'studio' => 'Studio',
        'bureau' => 'Bureau',
        'boutique' => 'Boutique',
        'local_commercial' => 'Local commercial',
        'entrepot' => 'Entrepôt',
        'residence_meublee' => 'Résidence meublée',
    ];
}

function property_statuses(): array
{
    return [
        'brouillon' => 'Brouillon',
        'en_attente' => 'En attente de validation',
        'valide' => 'Validé',
        'publie' => 'Publié',
        'disponible' => 'Disponible',
        'visite_en_cours' => 'Visite en cours',
        'reserve' => 'Réservé',
        'occupe' => 'Occupé',
        'preavis' => 'En préavis',
        'maintenance' => 'Maintenance',
        'indisponible' => 'Indisponible',
        'archive' => 'Archivé',
    ];
}

function prospect_statuses(string $type): array
{
    if ($type === 'proprietaire') {
        return [
            'nouveau' => 'Nouveau',
            'contacte' => 'Contacté',
            'rendez_vous_fixe' => 'Rendez-vous fixé',
            'bien_a_visiter' => 'Bien à visiter',
            'proposition_envoyee' => 'Proposition envoyée',
            'converti' => 'Converti',
            'non_abouti' => 'Non abouti',
        ];
    }
    return [
        'nouveau' => 'Nouveau',
        'contacte' => 'Contacté',
        'recherche_en_cours' => 'Recherche en cours',
        'biens_proposes' => 'Biens proposés',
        'visite_programmee' => 'Visite programmée',
        'visite_effectuee' => 'Visite effectuée',
        'converti' => 'Converti',
        'non_abouti' => 'Non abouti',
    ];
}

function visit_statuses(): array
{
    return [
        'planifiee' => 'Planifiée',
        'confirmee' => 'Confirmée',
        'reportee' => 'Reportée',
        'realisee' => 'Réalisée',
        'annulee' => 'Annulée',
        'no_show' => 'Absent / non présenté',
    ];
}

function prospect_type_label(string $type): string
{
    return $type === 'proprietaire' ? 'Propriétaire potentiel' : 'Locataire potentiel';
}

function prospect_source_label(?string $source): string
{
    return match ((string) $source) {
        'site_je_suis_proprietaire' => 'Site web – Je suis propriétaire',
        'site_je_cherche_logement' => 'Site web – Je cherche un logement',
        'site' => 'Site web',
        'agent' => 'Saisie par un agent',
        default => $source ?: '—',
    };
}

function contract_statuses(): array
{
    return [
        'actif' => 'Actif',
        'termine' => 'Terminé',
        'resilie' => 'Résilié',
    ];
}

function contract_periodicities(): array
{
    return [
        'mensuel' => 'Mensuel',
        'trimestriel' => 'Trimestriel',
        'semestriel' => 'Semestriel',
        'annuel' => 'Annuel',
    ];
}

function rent_statuses(): array
{
    return [
        'a_venir' => 'À venir',
        'a_payer' => 'À payer',
        'paye' => 'Payé',
        'partiel' => 'Partiellement payé',
        'en_retard' => 'En retard',
        'annule' => 'Annulé',
    ];
}

function cities_by_province(): array
{
    return [
        'Bas-Uele' => ['Buta'],
        'Équateur' => ['Mbandaka'],
        'Haut-Katanga' => ['Lubumbashi', 'Likasi', 'Kasumbalesa'],
        'Haut-Lomami' => ['Kamina'],
        'Haut-Uele' => ['Isiro', 'Watsa'],
        'Ituri' => ['Bunia'],
        'Kasaï' => ['Tshikapa'],
        'Kasaï-Central' => ['Kananga'],
        'Kasaï-Oriental' => ['Mbuji-Mayi'],
        'Kinshasa' => ['Kinshasa'],
        'Kongo-Central' => ['Matadi', 'Boma', 'Muanda'],
        'Kwango' => ['Kenge'],
        'Kwilu' => ['Bandundu', 'Kikwit'],
        'Lomami' => ['Kabinda', 'Mwene-Ditu'],
        'Lualaba' => ['Kolwezi'],
        'Mai-Ndombe' => ['Inongo'],
        'Maniema' => ['Kindu'],
        'Mongala' => ['Lisala'],
        'Nord-Kivu' => ['Goma', 'Beni', 'Butembo'],
        'Nord-Ubangi' => ['Gbadolite'],
        'Sankuru' => ['Lusambo'],
        'Sud-Kivu' => ['Bukavu', 'Uvira'],
        'Sud-Ubangi' => ['Gemena'],
        'Tanganyika' => ['Kalemie'],
        'Tshopo' => ['Kisangani'],
        'Tshuapa' => ['Boende'],
    ];
}

function provinces_rdc(): array
{
    $provinces = array_keys(cities_by_province());
    return array_combine($provinces, $provinces);
}

function province_for_city(string $city): string
{
    foreach (cities_by_province() as $province => $provinceCities) {
        if (in_array($city, $provinceCities, true)) {
            return $province;
        }
    }
    return '';
}

/**
 * Normalise et contrôle une localisation congolaise avant sa persistance.
 * Les champs peuvent rester vides dans les anciens formulaires optionnels,
 * mais une ville choisie doit toujours appartenir à la province choisie.
 */
function rdc_location(string $province, string $city): array
{
    $province = trim($province);
    $city = trim($city);
    if ($province === '' && $city !== '') {
        $province = province_for_city($city);
    }
    if ($province !== '' && !array_key_exists($province, cities_by_province())) {
        throw new InvalidArgumentException('Veuillez sélectionner une province de la RDC.');
    }
    if ($city !== '' && ($province === '' || !in_array($city, cities_by_province()[$province], true))) {
        throw new InvalidArgumentException('Veuillez sélectionner une ville correspondant à la province choisie.');
    }
    return ['province' => $province, 'city' => $city];
}

function cities(): array
{
    $cities = [];
    foreach (cities_by_province() as $provinceCities) {
        array_push($cities, ...$provinceCities);
    }
    $cities = array_values(array_unique($cities));
    sort($cities, SORT_NATURAL | SORT_FLAG_CASE);
    return $cities;
}

function communes_map(): array
{
    return [
        'Goma' => ['Goma', 'Karisimbi'],
        'Bukavu' => ['Ibanda', 'Kadutu', 'Bagira'],
        'Kinshasa' => ['Gombe', 'Ngaliema', 'Limete', 'Kintambo', 'Bandalungwa'],
        'Lubumbashi' => ['Lubumbashi', 'Kampemba', 'Kenya', 'Annexe'],
        'Kisangani' => ['Makiso', 'Tshopo', 'Mangobo'],
    ];
}

function quartiers_map(): array
{
    return [
        'Goma' => ['Himbi', 'Keshero', 'Katindo', 'Majengo', 'Mikeno', 'Mabanga', 'Ndosho', 'Murara', 'Les Volcans'],
        'Bukavu' => ['Nguba', 'Panzi', 'Ndendere', 'Essence', 'Nyawera'],
        'Kinshasa' => ['Gombe', 'Binza', 'Utex', 'Ma Campagne', 'Kinsuka', 'Kingabwa'],
        'Lubumbashi' => ['Golf', 'Bel Air', 'Kenya', 'Makutano'],
        'Kisangani' => ['Centre-ville', 'Tshopo'],
    ];
}

function amenities_catalog(): array
{
    return [
        'climatisation' => 'Climatisation',
        'groupe' => 'Groupe électrogène',
        'eau' => 'Eau courante 24/24',
        'internet' => 'Internet / Fibre',
        'parking' => 'Parking',
        'securite' => 'Sécurité / Gardien',
        'cuisine' => 'Cuisine équipée',
        'meuble' => 'Meublé',
        'balcon' => 'Balcon / Terrasse',
        'jardin' => 'Jardin',
        'piscine' => 'Piscine',
        'ascenseur' => 'Ascenseur',
        'eau_chaude' => 'Eau chaude',
        'tv' => 'TV',
        'lave_linge' => 'Lave-linge',
        'proche_ecole' => 'Proche écoles',
        'vue_lac' => 'Vue lac',
    ];
}

function roles_catalog(): array
{
    return [
        'admin' => 'Administrateur',
        'finance' => 'Service financier',
        'manager' => 'Gestionnaire immobilier',
        'supervisor' => 'Superviseur',
        'agent' => 'Agent immobilier',
        'owner' => 'Propriétaire',
        'tenant' => 'Locataire',
        'traveler' => 'Voyageur',
    ];
}

function role_label(string $role): string
{
    return roles_catalog()[$role] ?? $role;
}

function status_badge(string $status): string
{
    $map = [
        'brouillon' => 'muted', 'en_attente' => 'warn', 'valide' => 'info',
        'publie' => 'ok', 'disponible' => 'ok', 'visite_en_cours' => 'info',
        'reserve' => 'warn', 'occupe' => 'navy', 'preavis' => 'warn',
        'maintenance' => 'warn', 'indisponible' => 'muted', 'archive' => 'muted',
        'a_venir' => 'muted', 'a_payer' => 'info', 'paye' => 'ok',
        'partiel' => 'warn', 'en_retard' => 'danger', 'annule' => 'muted',
        'actif' => 'ok', 'suspendu' => 'danger', 'inactif' => 'muted',
        'nouveau' => 'info', 'en_cours' => 'info', 'contacte' => 'info',
        'rendez_vous_fixe' => 'warn', 'bien_a_visiter' => 'warn', 'proposition_envoyee' => 'navy',
        'recherche_en_cours' => 'info', 'biens_proposes' => 'navy', 'visite_programmee' => 'warn',
        'visite_effectuee' => 'ok', 'converti' => 'ok', 'non_abouti' => 'muted', 'perdu' => 'muted',
        'termine' => 'muted', 'resilie' => 'danger',
        'planifiee' => 'info', 'confirmee' => 'navy', 'reportee' => 'warn', 'realisee' => 'ok', 'annulee' => 'muted', 'no_show' => 'warn',
        'generee' => 'info', 'validee' => 'ok', 'payable' => 'warn', 'payee' => 'ok',
        'a_preparer' => 'info', 'demande' => 'info', 'confirmee' => 'ok',
        'checkin' => 'info', 'sejour' => 'navy', 'checkout' => 'warn', 'cloturee' => 'ok',
        'nouveau_inc' => 'danger', 'a_analyser' => 'warn', 'approuve' => 'info',
        'affecte' => 'info', 'intervention' => 'warn', 'resolu' => 'ok', 'cloture' => 'muted',
        'confirme' => 'ok', 'en_attente_paiement' => 'warn',
    ];
    $labels = array_merge(
        property_statuses(),
        rent_statuses(),
        [
            'actif' => 'Actif', 'suspendu' => 'Suspendu', 'inactif' => 'Inactif',
            'nouveau' => 'Nouveau', 'en_cours' => 'En cours', 'contacte' => 'Contacté',
            'rendez_vous_fixe' => 'Rendez-vous fixé', 'bien_a_visiter' => 'Bien à visiter',
            'proposition_envoyee' => 'Proposition envoyée', 'recherche_en_cours' => 'Recherche en cours',
            'biens_proposes' => 'Biens proposés', 'visite_programmee' => 'Visite programmée',
            'visite_effectuee' => 'Visite effectuée', 'converti' => 'Converti',
            'non_abouti' => 'Non abouti', 'perdu' => 'Perdu', 'termine' => 'Terminé', 'resilie' => 'Résilié',
            'planifiee' => 'Planifiée', 'confirmee' => 'Confirmée', 'reportee' => 'Reportée', 'realisee' => 'Réalisée', 'annulee' => 'Annulée', 'no_show' => 'Absent',
            'generee' => 'Générée', 'validee' => 'Validée', 'payable' => 'Payable', 'payee' => 'Payée',
            'a_preparer' => 'À préparer', 'demande' => 'Demande', 'confirmee' => 'Confirmée',
            'checkin' => 'Check-in', 'sejour' => 'En séjour', 'checkout' => 'Check-out', 'cloturee' => 'Clôturée',
            'nouveau_inc' => 'Nouveau', 'a_analyser' => 'À analyser', 'approuve' => 'Approuvé',
            'affecte' => 'Prestataire affecté', 'intervention' => 'Intervention', 'resolu' => 'Résolu', 'cloture' => 'Clôturé',
            'confirme' => 'Confirmé', 'en_attente_paiement' => 'En attente',
        ]
    );
    $tone = $map[$status] ?? 'muted';
    $label = $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
    return '<span class="badge badge-' . $tone . '">' . e($label) . '</span>';
}

function initials(string $first, string $last = ''): string
{
    $a = mb_substr(trim($first), 0, 1);
    $b = mb_substr(trim($last), 0, 1);
    return mb_strtoupper($a . $b);
}

function full_name(array $u): string
{
    return trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
}

function split_full_name(string $name): array
{
    $parts = preg_split('/\s+/', trim($name), 2) ?: [];
    return [$parts[0] ?? '', $parts[1] ?? ''];
}

function numbered_reference(string $table, string $prefix): string
{
    $next = (int) qtry_val("SELECT COALESCE(MAX(id), 0) + 1 FROM $table", [], 1);
    return $prefix . '-' . str_pad((string) max(1, $next), 4, '0', STR_PAD_LEFT);
}

function entity_reference(array $row, string $prefix): string
{
    return (string) (($row['reference'] ?? '') ?: $prefix . '-' . str_pad((string) ($row['id'] ?? 0), 4, '0', STR_PAD_LEFT));
}

function record_prospect_status(int $prospectId, ?string $oldStatus, string $newStatus, string $note = ''): void
{
    if ($oldStatus === $newStatus && $note === '') {
        return;
    }
    insert_existing('prospect_status_history', [
        'prospect_id' => $prospectId,
        'old_status' => $oldStatus,
        'new_status' => $newStatus,
        'user_id' => user()['id'] ?? null,
        'note' => $note,
        'created_at' => now(),
    ]);
}

/** Affecte les demandes publiques à l'agent actif ayant le moins de prospects ouverts. */
function next_public_prospect_agent_id(): ?int
{
    $bestId = null;
    $bestCount = PHP_INT_MAX;
    foreach (load_public_agents(db(), 500) as $agent) {
        if (($agent['source'] ?? '') !== 'immo_agents' || empty($agent['id'])) {
            continue;
        }
        $count = (int) qtry_val(
            "SELECT COUNT(*) FROM prospects WHERE agent_id = ? AND status NOT IN ('converti','non_abouti')",
            [(int) $agent['id']],
            0
        );
        if ($count < $bestCount) {
            $bestId = (int) $agent['id'];
            $bestCount = $count;
        }
    }
    return $bestId;
}

function notify_staff_new_prospect(int $prospectId, string $type, string $name): void
{
    $title = $type === 'proprietaire' ? 'Nouveau propriétaire potentiel' : 'Nouveau locataire potentiel';
    foreach (qtry_all("SELECT id FROM users WHERE role IN ('admin','manager','supervisor') AND status = 'actif'") as $staff) {
        try {
            notify((int) $staff['id'], $title, $name . ' a envoyé un formulaire depuis le site public.', 'app/prospects/' . $prospectId, 'info');
        } catch (Throwable $e) {
            // Une notification ne doit jamais bloquer la soumission publique.
        }
    }
}

/**
 * Clôture un contrat sans effacer l'historique et libère le bien si nécessaire.
 */
function close_contract(int $contractId, string $status = 'termine', string $reason = ''): bool
{
    if (!in_array($status, ['termine', 'resilie'], true)) {
        throw new InvalidArgumentException('Statut de clôture invalide.');
    }
    $contract = qtry_one('SELECT * FROM contracts WHERE id = ?', [$contractId]);
    if (!$contract || ($contract['status'] ?? '') !== 'actif') {
        return false;
    }

    $pdo = db()->pdo();
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        db()->begin();
    }
    try {
        update_existing('contracts', [
            'status' => $status,
            'ended_at' => now(),
            'termination_reason' => $reason,
            'updated_at' => now(),
        ], 'id = ?', [$contractId]);
        update_existing('tenants', [
            'property_id' => null,
            'contract_id' => null,
        ], 'id = ? AND contract_id = ?', [(int) $contract['tenant_id'], $contractId]);
        db()->exec(
            "UPDATE rents SET status = 'annule' WHERE contract_id = ? AND period_start > ? AND paid_amount = 0 AND status IN ('a_venir','a_payer')",
            [$contractId, today()]
        );
        $otherActive = (int) qtry_val(
            "SELECT COUNT(*) FROM contracts WHERE property_id = ? AND status = 'actif' AND id <> ?",
            [(int) $contract['property_id'], $contractId],
            0
        );
        $property = qtry_one('SELECT status FROM properties WHERE id = ?', [(int) $contract['property_id']]);
        if ($otherActive === 0 && ($property['status'] ?? '') !== 'maintenance') {
            update_existing('properties', ['status' => 'disponible', 'updated_at' => now()], 'id = ?', [(int) $contract['property_id']]);
        }
        log_activity('contrat_' . $status, 'contracts', $contractId, ['status' => 'actif'], ['status' => $status, 'motif' => $reason]);
        if ($ownsTransaction) {
            db()->commit();
        }
        return true;
    } catch (Throwable $e) {
        if ($ownsTransaction) {
            db()->rollBack();
        }
        throw $e;
    }
}

function sync_expired_contracts(): void
{
    foreach (qtry_all("SELECT id FROM contracts WHERE status = 'actif' AND end_date IS NOT NULL AND end_date < ?", [today()]) as $contract) {
        close_contract((int) $contract['id'], 'termine', 'Échéance contractuelle atteinte');
    }
}

function log_activity(string $action, string $entity = '', $entityId = null, $old = null, $new = null): void
{
    try {
        db()->insert('activity_logs', [
            'user_id' => user()['id'] ?? null,
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'old_value' => $old !== null ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
            'new_value' => $new !== null ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'created_at' => now(),
        ]);
    } catch (Throwable $e) {
        // never break the app
    }
}

function notify(int $userId, string $title, string $body = '', string $link = '', string $type = 'info'): void
{
    db()->insert('app_notifications', [
        'user_id' => $userId,
        'title' => $title,
        'body' => $body,
        'link' => $link,
        'type' => $type,
        'is_read' => 0,
        'created_at' => now(),
    ]);
}

function unread_count(): int
{
    if (!user()) {
        return 0;
    }
    return (int) db()->val('SELECT COUNT(*) FROM app_notifications WHERE user_id = ? AND is_read = 0', [user()['id']]);
}

function handle_upload(string $field, string $subdir): ?string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Échec du téléversement.');
    }
    $max = ((int) config('upload.max_mb', 8)) * 1024 * 1024;
    if ($f['size'] > $max) {
        throw new RuntimeException('Fichier trop volumineux.');
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $f['tmp_name']);
    finfo_close($finfo);
    $allowed = config('upload.mimes');
    if (!in_array($mime, $allowed, true)) {
        throw new RuntimeException('Type de fichier non autorisé.');
    }
    $ext = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf',
    ][$mime] ?? 'bin';
    $name = date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dir = rtrim(config('upload.dir'), '/') . '/' . trim($subdir, '/');
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($f['tmp_name'], $dest)) {
        throw new RuntimeException('Impossible d’enregistrer le fichier.');
    }
    return trim($subdir, '/') . '/' . $name;
}

function handle_uploads(string $field, string $subdir): array
{
    if (empty($_FILES[$field])) {
        return [];
    }
    $files = $_FILES[$field];
    if (!is_array($files['name'])) {
        $one = handle_upload($field, $subdir);
        return $one ? [$one] : [];
    }
    $out = [];
    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $_FILES['_tmp_one'] = [
            'name' => $files['name'][$i],
            'type' => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error' => $files['error'][$i],
            'size' => $files['size'][$i],
        ];
        $p = handle_upload('_tmp_one', $subdir);
        if ($p) {
            $out[] = $p;
        }
    }
    unset($_FILES['_tmp_one']);
    return $out;
}

function csv_download(string $filename, array $headers, array $rows): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, $headers, ';');
    foreach ($rows as $r) {
        fputcsv($out, $r, ';');
    }
    fclose($out);
    exit;
}

function ref_code(string $prefix): string
{
    return $prefix . '-' . date('y') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
}

function qtry_all(string $sql, array $params = []): array
{
    try {
        return db()->all($sql, $params);
    } catch (Throwable $e) {
        return [];
    }
}

function qtry_val(string $sql, array $params = [], $default = 0)
{
    try {
        $v = db()->val($sql, $params);
        return $v === null ? $default : $v;
    } catch (Throwable $e) {
        return $default;
    }
}

function qtry_one(string $sql, array $params = []): ?array
{
    try {
        return db()->one($sql, $params);
    } catch (Throwable $e) {
        return null;
    }
}

function owner_offres(): array
{
    return [
        'location_mensuelle' => 'Location mensuelle',
        'location_journaliere' => 'Location journalière',
        'vente' => 'Vente',
    ];
}

function property_offre(array $p): string
{
    $o = strtolower(trim((string) ($p['offre'] ?? $p['usage_type'] ?? '')));
    if (in_array($o, ['vente', 'sale', 'a_vendre', 'vendre', 'a vendre'], true)) {
        return 'vente';
    }
    if (in_array($o, ['location_journaliere', 'journalier', 'journaliere', 'stay', 'immo_stay', 'courte_duree'], true)
        || !empty($p['is_short_stay']) || !empty($p['_stay'])) {
        return 'location_journaliere';
    }
    return 'location_mensuelle';
}

function offre_label(string $offre): string
{
    return owner_offres()[$offre] ?? $offre;
}

function published_status_sql(string $alias = 'p'): string
{
    return "$alias.status IN ('publie','disponible','visite_en_cours')";
}

function photo_url(?string $path): string
{
    if (!$path) {
        return asset('img/p1.jpg');
    }
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return $path;
    }
    if (str_starts_with($path, '../assets/')) {
        return asset(ltrim(str_replace('../assets/', '', $path), '/'));
    }
    if (str_starts_with($path, 'assets/')) {
        return asset(substr($path, 7));
    }
    return upload_url($path);
}

function agent_photo_url(?string $path): string
{
    $path = trim((string) $path);
    if ($path === '') {
        return asset('img/agent-placeholder.svg');
    }
    return photo_url($path);
}

function cover_of(int $propertyId): string
{
    $p = qtry_one('SELECT path FROM property_photos WHERE property_id = ? ORDER BY is_cover DESC, sort_order ASC', [$propertyId]);
    if (!$p) {
        $p = qtry_one('SELECT path FROM propriete_images WHERE propriete_id = ? OR property_id = ? LIMIT 1', [$propertyId, $propertyId]);
    }
    return photo_url($p['path'] ?? $p['image'] ?? $p['chemin'] ?? null);
}

function whatsapp_link(string $text = ''): string
{
    $n = preg_replace('/\D+/', '', (string) setting('whatsapp', config('contact.whatsapp')));
    $q = $text !== '' ? '?text=' . rawurlencode($text) : '';
    return 'https://wa.me/' . $n . $q;
}
