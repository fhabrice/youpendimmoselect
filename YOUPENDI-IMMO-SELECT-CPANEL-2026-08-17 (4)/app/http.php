<?php

function fld(string $name, string $label, string $type = 'text', $value = '', string $cls = ''): string
{
    $req = str_contains($cls, 'req') ? ' required' : '';
    $full = str_contains($cls, 'full') ? ' full' : '';
    $val = e((string) $value);
    $html = '<label class="fld' . $full . '">' . e($label);
    if ($type === 'textarea') {
        $html .= '<textarea name="' . e($name) . '"' . $req . '>' . $val . '</textarea>';
    } elseif ($type === 'select') {
        $html .= $value; // prebuilt select
    } else {
        $html .= '<input type="' . e($type) . '" name="' . e($name) . '" value="' . $val . '"' . $req . '>';
    }
    return $html . '</label>';
}

function sel(string $name, array $opts, $cur = '', string $label = '', bool $full = false, bool $required = false): string
{
    $h = $label ? '<label class="fld' . ($full ? ' full' : '') . '">' . e($label) : '';
    $h .= '<select name="' . e($name) . '"' . ($required ? ' required' : '') . '>';
    foreach ($opts as $k => $v) {
        $s = ((string) $cur === (string) $k) ? ' selected' : '';
        $h .= '<option value="' . e((string) $k) . '"' . $s . '>' . e($v) . '</option>';
    }
    $h .= '</select>';
    return $label ? $h . '</label>' : $h;
}

function table_cols(string $table): array
{
    try {
        return schema_columns(db(), $table);
    } catch (Throwable $e) {
        return [];
    }
}

function insert_existing(string $table, array $data): int
{
    $cols = table_cols($table);
    if (!$cols) {
        return (int) db()->insert($table, $data);
    }
    $filtered = [];
    foreach ($data as $k => $v) {
        if (in_array(strtolower($k), $cols, true)) {
            $filtered[$k] = $v;
        }
    }
    if (!$filtered) {
        throw new RuntimeException('Aucune colonne compatible pour ' . $table);
    }
    return db()->insert($table, $filtered);
}

function update_existing(string $table, array $data, string $where, array $params = []): int
{
    $cols = table_cols($table);
    $filtered = [];
    foreach ($data as $k => $v) {
        if (!$cols || in_array(strtolower($k), $cols, true)) {
            $filtered[$k] = $v;
        }
    }
    if (!$filtered) {
        return 0;
    }
    return db()->update($table, $filtered, $where, $params);
}

function user_row_norm(?array $u): array
{
    if (!$u) {
        return [];
    }
    $u = array_change_key_case($u, CASE_LOWER);
    return [
        'id' => (int) ($u['id'] ?? 0),
        'email' => $u['email'] ?? $u['mail'] ?? '',
        'first_name' => $u['first_name'] ?? $u['prenom'] ?? '',
        'last_name' => $u['last_name'] ?? $u['nom'] ?? '',
        'phone' => $u['phone'] ?? $u['telephone'] ?? '',
        'whatsapp' => $u['whatsapp'] ?? $u['phone'] ?? $u['telephone'] ?? '',
        'province' => $u['province'] ?? '',
        'city' => $u['city'] ?? $u['ville'] ?? '',
        'address' => $u['address'] ?? $u['adresse'] ?? '',
        'role' => $u['role'] ?? $u['type'] ?? 'agent',
        'status' => $u['status'] ?? $u['statut'] ?? 'actif',
        'last_login' => $u['last_login'] ?? null,
    ];
}



function owner_register(): void
{
    try {
        if (user() && (user()['role'] ?? '') === 'owner') {
            redirect('espace/proprietaire');
        }
        if (is_post()) {
            csrf_verify();
            $location = rdc_location(str_input('province'), str_input('city'));
            if ($location['province'] === '' || $location['city'] === '') {
                throw new InvalidArgumentException('La province et la ville sont obligatoires.');
            }
            $email = mb_strtolower(str_input('email'));
            $exists = db()->one('SELECT id FROM users WHERE email = ?', [$email]);
            if (!$exists) {
                try {
                    $exists = db()->one('SELECT id FROM utilisateurs WHERE email = ? OR mail = ?', [$email, $email]);
                } catch (Throwable $e) {
                }
            }
            if ($exists) {
                flash('error', 'Un compte existe déjà avec cet e-mail. Connectez-vous.');
                remember_old($_POST);
                redirect('inscription-bailleur');
            }
            if (strlen((string) input('password')) < 8 || input('password') !== input('password2')) {
                flash('error', 'Mot de passe trop court ou confirmation différente.');
                remember_old($_POST);
                redirect('inscription-bailleur');
            }
            $hash = password_hash((string) input('password'), PASSWORD_DEFAULT);
            $uid = insert_existing('users', [
                'email' => $email,
                'password' => $hash,
                'mot_de_passe' => $hash,
                'first_name' => str_input('first_name'),
                'prenom' => str_input('first_name'),
                'last_name' => str_input('last_name'),
                'nom' => str_input('last_name'),
                'phone' => str_input('phone'),
                'telephone' => str_input('phone'),
                'whatsapp' => str_input('phone'),
                'province' => $location['province'],
                'city' => $location['city'],
                'ville' => $location['city'],
                'address' => str_input('address'),
                'adresse' => str_input('address'),
                'role' => 'owner',
                'status' => 'actif',
                'statut' => 'actif',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            try {
                insert_existing('utilisateurs', [
                    'email' => $email,
                    'mail' => $email,
                    'password' => $hash,
                    'mot_de_passe' => $hash,
                    'first_name' => str_input('first_name'),
                    'prenom' => str_input('first_name'),
                    'last_name' => str_input('last_name'),
                    'nom' => str_input('last_name'),
                    'phone' => str_input('phone'),
                    'telephone' => str_input('phone'),
                    'province' => $location['province'],
                    'city' => $location['city'],
                    'ville' => $location['city'],
                    'address' => str_input('address'),
                    'adresse' => str_input('address'),
                    'role' => 'owner',
                    'status' => 'actif',
                    'statut' => 'actif',
                ]);
            } catch (Throwable $e) {
            }
            try {
                insert_existing('owners', [
                    'reference' => numbered_reference('owners', 'PROP'),
                    'user_id' => $uid,
                    'first_name' => str_input('first_name'),
                    'last_name' => str_input('last_name'),
                    'phone' => str_input('phone'),
                    'email' => $email,
                    'province' => $location['province'],
                    'city' => $location['city'],
                    'address' => str_input('address'),
                    'created_at' => now(),
                ]);
            } catch (Throwable $e) {
            }
            $ok = login($email, (string) input('password'));
            if (empty($ok['ok'])) {
                $_SESSION['user'] = [
                    'id' => $uid,
                    'email' => $email,
                    'first_name' => str_input('first_name'),
                    'last_name' => str_input('last_name'),
                    'role' => 'owner',
                    'status' => 'actif',
                    'phone' => str_input('phone'),
                    'province' => $location['province'],
                    'city' => $location['city'],
                    'address' => str_input('address'),
                ];
            }
            flash('success', 'Compte bailleur créé. Publiez vos biens : un admin les validera.');
            redirect('espace/proprietaire');
        }
        view('owner/register', ['title' => 'Inscription bailleur', 'nav' => '', 'tailwind' => true], 'public');
    } catch (Throwable $e) {
        flash('error', 'Inscription impossible : ' . $e->getMessage());
        remember_old($_POST);
        if (is_post()) {
            redirect('inscription-bailleur');
        }
        view('owner/register', ['title' => 'Inscription bailleur', 'nav' => '', 'tailwind' => true], 'public');
    }
}

function owner_add_property(): void
{
    $u = require_role(['owner', 'admin']);
    if (is_post()) {
        csrf_verify();
        $oid = owner_of($u)['id'] ?? null;
        if (!$oid) {
            $oid = db()->insert('owners', [
                'reference' => numbered_reference('owners', 'PROP'),
                'user_id' => $u['id'], 'first_name' => $u['first_name'], 'last_name' => $u['last_name'],
                'email' => $u['email'], 'phone' => $u['phone'] ?? '', 'created_at' => now(),
            ]);
        }
        $offre = str_input('offre') ?: 'location_mensuelle';
        if (!in_array($offre, ['location_mensuelle', 'location_journaliere', 'vente'], true)) {
            $offre = 'location_mensuelle';
        }
        try {
            $location = rdc_location(str_input('province'), str_input('city'));
            if ($location['province'] === '' || $location['city'] === '') {
                throw new InvalidArgumentException('La province et la ville du bien sont obligatoires.');
            }
        } catch (InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            remember_old($_POST);
            redirect('espace/proprietaire/ajouter');
        }
        $row = [
            'reference' => numbered_reference('properties', 'BIE'),
            'title' => str_input('title'),
            'titre' => str_input('title'),
            'slug' => slugify(str_input('title')),
            'type' => str_input('type') ?: 'maison',
            'description' => str_input('description'),
            'province' => $location['province'],
            'city' => $location['city'],
            'ville' => $location['city'],
            'quartier' => str_input('quartier'),
            'address' => str_input('address'),
            'adresse' => str_input('address'),
            'address_public' => str_input('quartier') . ', ' . str_input('city'),
            'rent' => float_input('prix'),
            'loyer' => float_input('prix'),
            'prix' => float_input('prix'),
            'currency' => 'USD',
            'bedrooms' => int_input('bedrooms'),
            'chambres' => int_input('bedrooms'),
            'bathrooms' => int_input('bathrooms'),
            'sdb' => int_input('bathrooms'),
            'area' => float_input('area'),
            'superficie' => float_input('area'),
            'status' => 'en_attente',
            'statut' => 'en_attente',
            'offre' => $offre,
            'usage_type' => $offre === 'vente' ? 'vente' : 'residentiel',
            'is_short_stay' => $offre === 'location_journaliere' ? 1 : 0,
            'owner_id' => $oid,
            'proprietaire_id' => $oid,
            'created_by' => $u['id'],
            'created_at' => now(),
            'updated_at' => now(),
        ];
        $pid = 0;
        try {
            $pid = insert_existing('properties', $row);
        } catch (Throwable $e) {
            $pid = insert_existing('proprietes', $row);
        }
        try {
            foreach (handle_uploads('photos', 'properties') as $i => $path) {
                db()->insert('property_photos', [
                    'property_id' => $pid, 'path' => $path, 'is_cover' => $i === 0 ? 1 : 0, 'sort_order' => $i,
                ]);
            }
        } catch (Throwable $e) {
        }
        if ($offre === 'location_journaliere' && !db()->one('SELECT id FROM stay_listings WHERE property_id=?', [$pid])) {
            db()->insert('stay_listings', [
                'property_id' => $pid, 'name' => str_input('title'),
                'guests' => 2, 'beds' => max(1, int_input('bedrooms')),
                'price_night' => float_input('prix'), 'status' => 'disponible',
            ]);
        }
        log_activity('bien_propose_bailleur', 'properties', $pid);
        flash('success', 'Bien envoyé. Il sera visible après approbation de l’administrateur.');
        redirect('espace/proprietaire');
    }
    view('owner/add', ['title' => 'Publier un bien', 'nav' => ''], 'public');
}

function owner_report(): void
{
    $u = require_role(['owner', 'admin']);
    $o = owner_of($u);
    $oid = $o['id'] ?? 0;
    $mois = str_input('mois') ?: date('Y-m');
    $biens = qtry_all('SELECT * FROM properties WHERE owner_id = ?', [$oid]);
    $nLoc = $nVente = 0;
    foreach ($biens as $p) {
        if (($p['offre'] ?? '') === 'vente') {
            $nVente++;
        } else {
            $nLoc++;
        }
    }
    $encaisse = (float) qtry_val("SELECT COALESCE(SUM(paid_amount),0) FROM rents WHERE owner_id=? AND period_start LIKE ?", [$oid, $mois . '%']);
    $taux = (float) setting('commission_mgmt', 10);
    $commission = $encaisse * $taux / 100;
    view('owner/report', [
        'title' => 'Rapport mensuel', 'nav' => '',
        'mois' => $mois, 'nLoc' => $nLoc, 'nVente' => $nVente,
        'encaisse' => $encaisse, 'commission' => $commission, 'net' => $encaisse - $commission, 'taux' => $taux,
    ], 'public');
}

function public_home(): void
{
    $pub = published_status_sql('p');
    $sales = qtry_all("SELECT * FROM properties p WHERE p.offre = 'vente' AND $pub ORDER BY p.id DESC LIMIT 6");
    $listings = qtry_all("SELECT * FROM properties p WHERE (p.is_short_stay = 0 OR p.is_short_stay IS NULL) AND (p.offre IS NULL OR p.offre = '' OR p.offre = 'location_mensuelle') AND $pub ORDER BY p.id DESC LIMIT 6");
    $stays = qtry_all("SELECT p.*, s.price_night FROM properties p LEFT JOIN stay_listings s ON s.property_id = p.id WHERE (p.is_short_stay = 1 OR p.offre = 'location_journaliere') AND $pub ORDER BY p.id DESC LIMIT 3");
    // Normalise les noms, téléphones et photos des schémas actuels comme historiques.
    $agentsHome = load_public_agents(db(), 8);
    $stats = [
        'biens' => qtry_val('SELECT COUNT(*) FROM properties WHERE is_short_stay = 0'),
        'dispo' => qtry_val("SELECT COUNT(*) FROM properties WHERE status = 'disponible' AND is_short_stay = 0"),
        'owners' => qtry_val('SELECT COUNT(*) FROM owners'),
        'stays' => qtry_val('SELECT COUNT(*) FROM stay_listings'),
        'ventes' => qtry_val("SELECT COUNT(*) FROM properties WHERE offre = 'vente'"),
    ];
    view('public/home', compact('listings', 'sales', 'stays', 'stats', 'agentsHome') + ['title' => 'Accueil', 'nav' => '']);
}

function public_search(bool $stay = false): void
{
    $offre = str_input('offre');
    $path = request_path();
    if ($path === '/ventes') {
        $offre = 'vente';
        $stay = false;
    }
    if (!$stay && ($offre === 'location_journaliere' || str_input('duree') === 'journalier')) {
        $qs = $_GET;
        unset($qs['duree'], $qs['offre']);
        redirect('immo-stay' . ($qs ? '?' . http_build_query($qs) : ''));
    }
    if ($stay && str_input('duree') === 'mensuel') {
        $qs = $_GET;
        unset($qs['duree']);
        redirect('biens' . ($qs ? '?' . http_build_query($qs) : ''));
    }
    if (!$stay && $offre === 'vente' && $path !== '/ventes') {
        $qs = $_GET;
        $qs['offre'] = 'vente';
        redirect('ventes?' . http_build_query($qs));
    }
    $mode = $stay ? 'stay' : ($offre === 'vente' ? 'vente' : 'location');
    $f = [
        'province' => str_input('province'), 'ville' => str_input('ville'), 'quartier' => str_input('quartier'), 'type' => str_input('type'),
        'q' => str_input('q'),
        'min' => str_input('min'), 'max' => str_input('max'), 'chambres' => str_input('chambres'),
        'sdb' => str_input('sdb'), 'meuble' => str_input('meuble'), 'usage' => str_input('usage'),
    ];
    if ($mode === 'stay') {
        $w = ["(p.is_short_stay = 1 OR p.offre = 'location_journaliere')"];
    } elseif ($mode === 'vente') {
        $w = ["p.offre = 'vente'"];
    } else {
        $w = ["(p.is_short_stay = 0 OR p.is_short_stay IS NULL)", "(p.offre IS NULL OR p.offre = '' OR p.offre = 'location_mensuelle')"];
    }
    $w[] = published_status_sql('p');
    $params = [];
    if ($f['q']) { $w[] = '(p.title LIKE ? OR p.description LIKE ? OR p.quartier LIKE ?)'; $params[] = '%'.$f['q'].'%'; $params[] = '%'.$f['q'].'%'; $params[] = '%'.$f['q'].'%'; }
    if ($f['province']) { $w[] = 'p.province = ?'; $params[] = $f['province']; }
    if ($f['ville']) { $w[] = 'p.city = ?'; $params[] = $f['ville']; }
    if ($f['quartier']) { $w[] = 'p.quartier LIKE ?'; $params[] = '%' . $f['quartier'] . '%'; }
    if ($f['type']) { $w[] = 'p.type = ?'; $params[] = $f['type']; }
    if ($f['min'] !== '') { $w[] = 'p.rent >= ?'; $params[] = (float) $f['min']; }
    if ($f['max'] !== '') { $w[] = 'p.rent <= ?'; $params[] = (float) $f['max']; }
    if ($f['chambres'] !== '') { $w[] = 'p.bedrooms >= ?'; $params[] = (int) $f['chambres']; }
    if ($f['sdb'] !== '') { $w[] = 'p.bathrooms >= ?'; $params[] = (int) $f['sdb']; }
    if ($f['meuble'] !== '') { $w[] = 'p.furnished = ?'; $params[] = (int) $f['meuble']; }
    if ($f['usage']) { $w[] = 'p.usage_type = ?'; $params[] = $f['usage']; }
    if ($stay && str_input('arrivee') && str_input('depart')) {
        $w[] = 'NOT EXISTS (SELECT 1 FROM reservations r WHERE r.property_id = p.id AND r.status NOT IN (\'annulee\') AND r.checkin < ? AND r.checkout > ?)';
        $params[] = str_input('depart');
        $params[] = str_input('arrivee');
    }
    $where = implode(' AND ', $w);
    $total = (int) qtry_val("SELECT COUNT(*) FROM properties p WHERE $where", $params);
    $pg = paginate($total, 9);
    $sql = "SELECT p.* " . ($stay ? ', s.price_night, s.guests as stay_guests' : '') . " FROM properties p ";
    if ($stay) {
        $sql .= 'LEFT JOIN stay_listings s ON s.property_id = p.id ';
    }
    $sql .= "WHERE $where ORDER BY p.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}";
    $items = qtry_all($sql, $params);
    $titles = [
        'stay' => ['Location journalière', 'stay'],
        'vente' => ['Propriétés en vente', 'ventes'],
        'location' => ['Location mensuelle', 'biens'],
    ];
    view('public/listings', [
        'title' => $titles[$mode][0],
        'heading' => $titles[$mode][0],
        'nav' => $titles[$mode][1],
        'items' => $items, 'f' => $f, 'pg' => $pg, 'stay' => $stay,
        'mode' => $mode, 'offre' => $offre,
    ]);
}

function public_property(int $id): void
{
    $p = db()->one('SELECT * FROM properties WHERE id = ?', [$id]);
    if (!$p || $p['is_short_stay']) {
        abort(404, 'Bien introuvable.');
    }
    if (!in_array($p['status'], ['publie', 'disponible', 'visite_en_cours', 'reserve'], true) && !can('properties.view')) {
        abort(404, 'Bien introuvable.');
    }
    $photos = db()->all('SELECT * FROM property_photos WHERE property_id = ? ORDER BY is_cover DESC, sort_order', [$id]);
    $agent = $p['agent_commercial_id'] ? db()->one('SELECT u.first_name, u.last_name FROM immo_agents a JOIN users u ON u.id = a.user_id WHERE a.id = ?', [$p['agent_commercial_id']]) : null;
    $agentName = $agent ? full_name($agent) : 'Équipe YOUPENDI';
    $nav = property_offre($p) === 'vente' ? 'ventes' : 'biens';
    view('public/property', ['title' => $p['title'], 'nav' => $nav, 'p' => $p, 'photos' => $photos, 'agentName' => $agentName]);
}

function public_visit(int $id): void
{
    csrf_verify();
    $p = db()->one('SELECT * FROM properties WHERE id = ?', [$id]);
    if (!$p) {
        abort(404, 'Bien introuvable');
    }
    db()->insert('visits', [
        'property_id' => $id,
        'visitor_name' => str_input('visitor_name'),
        'visitor_phone' => str_input('visitor_phone'),
        'visitor_email' => str_input('visitor_email'),
        'agent_id' => $p['agent_commercial_id'] ?: $p['agent_apporteur_id'],
        'scheduled_at' => str_replace('T', ' ', str_input('scheduled_at')),
        'status' => 'planifiee',
        'created_at' => now(),
    ]);
    if ($p['status'] === 'disponible') {
        db()->update('properties', ['status' => 'visite_en_cours', 'updated_at' => now()], 'id = ?', [$id]);
    }
    log_activity('visite_demandee', 'properties', $id);
    flash('success', 'Votre demande de visite a été enregistrée. Un agent vous contactera.');
    redirect('biens/' . $id);
}

function public_fav(int $id): void
{
    csrf_verify();
    $u = require_auth();
    $ex = db()->one('SELECT id FROM favorites WHERE user_id = ? AND property_id = ?', [$u['id'], $id]);
    if (!$ex) {
        db()->insert('favorites', ['user_id' => $u['id'], 'property_id' => $id, 'created_at' => now()]);
    }
    flash('success', 'Ajouté à vos favoris.');
    redirect('biens/' . $id);
}

function public_stay_show(int $id): void
{
    $p = db()->one('SELECT * FROM properties WHERE id = ? AND is_short_stay = 1', [$id]);
    $stay = $p ? db()->one('SELECT * FROM stay_listings WHERE property_id = ?', [$id]) : null;
    if (!$p || !$stay) {
        abort(404, 'Logement introuvable.');
    }
    $photos = db()->all('SELECT * FROM property_photos WHERE property_id = ? ORDER BY is_cover DESC, sort_order', [$id]);
    $busy = db()->all("SELECT checkin, checkout FROM reservations WHERE property_id = ? AND status NOT IN ('annulee')", [$id]);
    $calendar = [];
    $start = new DateTime('monday this week');
    for ($i = 0; $i < 35; $i++) {
        $d = (clone $start)->modify("+$i day")->format('Y-m-d');
        $isBusy = false;
        foreach ($busy as $b) {
            if ($d >= $b['checkin'] && $d < $b['checkout']) {
                $isBusy = true;
            }
        }
        $calendar[] = ['date' => $d, 'busy' => $isBusy];
    }
    view('public/stay_show', ['title' => $stay['name'], 'nav' => 'stay', 'p' => $p, 'stay' => $stay, 'photos' => $photos, 'calendar' => $calendar]);
}

function public_reserve(int $id): void
{
    csrf_verify();
    $stay = db()->one('SELECT * FROM stay_listings WHERE property_id = ?', [$id]);
    if (!$stay) {
        abort(404, 'Logement introuvable');
    }
    $in = str_input('checkin');
    $out = str_input('checkout');
    if (!$in || !$out || $out <= $in) {
        flash('error', 'Dates invalides.');
        redirect('immo-stay/' . $id);
    }
    $overlap = db()->val(
        "SELECT COUNT(*) FROM reservations WHERE property_id = ? AND status NOT IN ('annulee') AND checkin < ? AND checkout > ?",
        [$id, $out, $in]
    );
    if ((int) $overlap > 0) {
        flash('error', 'Ces dates ne sont plus disponibles.');
        redirect('immo-stay/' . $id);
    }
    $nights = (int) ((strtotime($out) - strtotime($in)) / 86400);
    $total = $nights * (float) $stay['price_night'] + (float) $stay['extra_fees'];
    $uid = user()['id'] ?? null;
    db()->insert('reservations', [
        'number' => ref_code('RS'),
        'stay_id' => $stay['id'],
        'property_id' => $id,
        'user_id' => $uid,
        'guest_name' => str_input('guest_name'),
        'guest_phone' => str_input('guest_phone'),
        'guest_email' => str_input('guest_email'),
        'checkin' => $in,
        'checkout' => $out,
        'nights' => $nights,
        'guests' => int_input('guests', 1),
        'price_night' => $stay['price_night'],
        'fees' => $stay['extra_fees'],
        'total' => $total,
        'paid_amount' => 0,
        'status' => 'demande',
        'created_at' => now(),
    ]);
    log_activity('reservation_demandee', 'properties', $id);
    flash('success', "Demande envoyée ($nights nuits — " . money($total) . '). Notre équipe confirme sous 24h.');
    redirect('immo-stay/' . $id);
}

function public_confier_bien(): void
{
    if (is_post()) {
        csrf_verify();
        $name = str_input('nom');
        $phone = str_input('phone');
        if ($name === '' || $phone === '') {
            remember_old($_POST);
            flash('error', 'Le nom complet et le téléphone sont obligatoires.');
            redirect('confier-mon-bien');
        }
        try {
            $location = rdc_location(str_input('province'), str_input('ville'));
        } catch (InvalidArgumentException $e) {
            remember_old($_POST);
            flash('error', $e->getMessage());
            redirect('confier-mon-bien');
        }
        [$firstName, $lastName] = split_full_name($name);
        $dup = db()->one(
            "SELECT * FROM prospects WHERE type = 'proprietaire' AND phone = ? AND (converted_to IS NULL OR converted_to = '') ORDER BY id DESC LIMIT 1",
            [$phone]
        );
        $status = ($dup['status'] ?? '') === 'non_abouti' ? 'nouveau' : ($dup['status'] ?? 'nouveau');
        $data = [
            'type' => 'proprietaire',
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $phone,
            'whatsapp' => str_input('whatsapp'),
            'email' => str_input('email'),
            'province' => $location['province'],
            'city' => $location['city'],
            'quartier' => str_input('quartier'),
            'property_type' => str_input('type'),
            'bedrooms' => int_input('chambres'),
            'budget_max' => float_input('loyer'),
            'management_type' => str_input('gestion'),
            'property_description' => str_input('description_bien'),
            'availability' => str_input('dispo'),
            'desired_date' => str_input('dispo') ?: null,
            'message' => str_input('message'),
            'source' => 'site_je_suis_proprietaire',
            'agent_id' => !empty($dup['agent_id']) ? (int) $dup['agent_id'] : next_public_prospect_agent_id(),
            'status' => $status,
            'updated_at' => now(),
        ];
        if ($dup) {
            $pid = (int) $dup['id'];
            update_existing('prospects', $data, 'id = ?', [$pid]);
            insert_existing('prospect_notes', [
                'prospect_id' => $pid,
                'user_id' => null,
                'body' => 'Nouvelle soumission du formulaire « Je suis propriétaire ».' . (str_input('message') ? "\n" . str_input('message') : ''),
                'created_at' => now(),
            ]);
            if (($dup['status'] ?? '') !== $status) {
                record_prospect_status($pid, $dup['status'] ?? null, $status, 'Nouvelle demande depuis le site public.');
            }
        } else {
            $data['created_at'] = now();
            $pid = insert_existing('prospects', $data);
            record_prospect_status($pid, null, 'nouveau', 'Créé automatiquement depuis le formulaire public.');
        }
        try {
            foreach (handle_uploads('photos', 'properties') as $path) {
                insert_existing('documents', [
                    'entity' => 'prospects', 'entity_id' => $pid, 'title' => 'Photo du bien',
                    'path' => $path, 'created_at' => now(),
                ]);
            }
        } catch (Throwable $e) {
            flash('error', 'La demande est enregistrée, mais certaines photos n’ont pas pu être ajoutées.');
        }
        notify_staff_new_prospect($pid, 'proprietaire', $name);
        log_activity('prospect_proprietaire_site', 'prospects', $pid);
        clear_old();
        flash('success', 'Merci. Votre demande est enregistrée dans notre CRM et un agent YOUPENDI vous contactera.');
        redirect('confier-mon-bien');
    }
    ob_start(); ?>
    <div class="form-grid">
      <?= fld('nom','Nom complet','text',old('nom'),'req') ?>
      <?= fld('phone','Téléphone','tel',old('phone'),'req') ?>
      <?= fld('whatsapp','WhatsApp','tel',old('whatsapp')) ?>
      <?= fld('email','E-mail','email',old('email')) ?>
      <?= sel('province', [''=>'Sélectionner une province'] + provinces_rdc(), old('province'), 'Province') ?>
      <?= sel('ville', [''=>'Sélectionner une ville'] + array_combine(cities(), cities()), old('ville'), 'Ville') ?>
      <?= fld('quartier','Quartier','text',old('quartier')) ?>
      <?= sel('type', property_types(), old('type'), 'Type de bien') ?>
      <?= fld('chambres','Nombre de chambres','number',old('chambres')) ?>
      <?= fld('loyer','Loyer souhaité (USD)','number',old('loyer')) ?>
      <?= fld('dispo','Disponibilité du bien','date',old('dispo')) ?>
      <?= sel('gestion', ['mise_en_location'=>'Mise en location','gestion_locative'=>'Gestion locative','courte_duree'=>'Location courte durée'], old('gestion'), 'Besoin de gestion') ?>
      <?= fld('description_bien','Description du bien','textarea',old('description_bien'),'full') ?>
      <?= fld('photos','Photos','file','','full') ?>
      <?= fld('message','Message ou besoin exprimé','textarea',old('message'),'full') ?>
    </div>
    <script>document.querySelector('[name=photos]').setAttribute('multiple','multiple');</script>
    <?php
    $form = ob_get_clean();
    view('public/form_page', [
        'title' => 'Confier mon bien', 'nav' => 'confier', 'kicker' => 'Je suis propriétaire',
        'intro' => 'Votre demande est créée automatiquement dans notre CRM afin que notre équipe puisse la suivre jusqu’à la mise en gestion du bien.',
        'form' => $form, 'submit' => 'Envoyer mon bien',
    ]);
}

function public_confier_recherche(): void
{
    if (is_post()) {
        csrf_verify();
        $name = str_input('nom');
        $phone = str_input('phone');
        if ($name === '' || $phone === '') {
            remember_old($_POST);
            flash('error', 'Le nom complet et le téléphone sont obligatoires.');
            redirect('confier-ma-recherche');
        }
        try {
            $location = rdc_location(str_input('province'), str_input('ville'));
        } catch (InvalidArgumentException $e) {
            remember_old($_POST);
            flash('error', $e->getMessage());
            redirect('confier-ma-recherche');
        }
        [$firstName, $lastName] = split_full_name($name);
        $dup = db()->one(
            "SELECT * FROM prospects WHERE type = 'locataire' AND phone = ? AND (converted_to IS NULL OR converted_to = '') ORDER BY id DESC LIMIT 1",
            [$phone]
        );
        $status = ($dup['status'] ?? '') === 'non_abouti' ? 'nouveau' : ($dup['status'] ?? 'nouveau');
        $data = [
            'type' => 'locataire',
            'first_name' => $firstName ?: 'Prospect',
            'last_name' => $lastName,
            'phone' => $phone,
            'whatsapp' => str_input('whatsapp'),
            'email' => str_input('email'),
            'province' => $location['province'],
            'city' => $location['city'],
            'quartier' => str_input('quartiers'),
            'property_type' => str_input('type'),
            'bedrooms' => int_input('chambres'),
            'budget_min' => float_input('budget_min'),
            'budget_max' => float_input('budget_max'),
            'furnished' => int_input('meuble', 0),
            'desired_date' => str_input('date') ?: null,
            'duration' => str_input('duree'),
            'criteria' => str_input('criteres'),
            'message' => str_input('message'),
            'source' => 'site_je_cherche_logement',
            'agent_id' => !empty($dup['agent_id']) ? (int) $dup['agent_id'] : next_public_prospect_agent_id(),
            'status' => $status,
            'updated_at' => now(),
        ];
        if ($dup) {
            $pid = (int) $dup['id'];
            update_existing('prospects', $data, 'id = ?', [$pid]);
            insert_existing('prospect_notes', [
                'prospect_id' => $pid,
                'user_id' => null,
                'body' => 'Nouvelle soumission du formulaire « Je cherche un logement ».' . (str_input('message') ? "\n" . str_input('message') : ''),
                'created_at' => now(),
            ]);
            if (($dup['status'] ?? '') !== $status) {
                record_prospect_status($pid, $dup['status'] ?? null, $status, 'Nouvelle recherche depuis le site public.');
            }
        } else {
            $data['created_at'] = now();
            $pid = insert_existing('prospects', $data);
            record_prospect_status($pid, null, 'nouveau', 'Créé automatiquement depuis le formulaire public.');
        }
        notify_staff_new_prospect($pid, 'locataire', $name);
        log_activity('prospect_locataire_site', 'prospects', $pid);
        clear_old();
        flash('success', 'Votre recherche est enregistrée dans notre CRM. Un agent vous proposera des biens adaptés.');
        redirect('confier-ma-recherche');
    }
    ob_start(); ?>
    <div class="form-grid">
      <?= fld('nom','Nom complet','text',old('nom'),'req') ?>
      <?= fld('phone','Téléphone','tel',old('phone'),'req') ?>
      <?= fld('whatsapp','WhatsApp','tel',old('whatsapp')) ?>
      <?= fld('email','E-mail','email',old('email')) ?>
      <?= sel('province', [''=>'Sélectionner une province'] + provinces_rdc(), old('province'), 'Province recherchée') ?>
      <?= sel('ville', [''=>'Sélectionner une ville'] + array_combine(cities(), cities()), old('ville'), 'Ville recherchée') ?>
      <?= fld('quartiers','Quartier(s) recherché(s)','text',old('quartiers')) ?>
      <?= sel('type', property_types(), old('type'), 'Type de logement') ?>
      <?= fld('chambres','Nombre de chambres','number',old('chambres')) ?>
      <?= fld('budget_min','Budget minimum (USD)','number',old('budget_min')) ?>
      <?= fld('budget_max','Budget maximum (USD)','number',old('budget_max')) ?>
      <?= sel('meuble', ['0'=>'Non meublé','1'=>'Meublé'], old('meuble'), 'Ameublement') ?>
      <?= fld('date','Date souhaitée d’emménagement','date',old('date')) ?>
      <?= fld('duree','Durée souhaitée','text',old('duree')) ?>
      <?= fld('criteres','Critères particuliers','textarea',old('criteres'),'full') ?>
      <?= fld('message','Message','textarea',old('message'),'full') ?>
    </div>
    <?php
    view('public/form_page', [
        'title' => 'Confier ma recherche', 'nav' => 'recherche', 'kicker' => 'Je cherche un logement',
        'intro' => 'Décrivez vos critères. Votre demande devient automatiquement un prospect suivi dans notre CRM.',
        'form' => ob_get_clean(), 'submit' => 'Envoyer ma recherche',
    ]);
}

function public_contact(): void
{
    if (is_post()) {
        csrf_verify();
        db()->insert('contact_messages', [
            'name' => str_input('name'), 'email' => str_input('email'), 'phone' => str_input('phone'),
            'subject' => str_input('subject'), 'body' => str_input('body'), 'created_at' => now(),
        ]);
        flash('success', 'Message reçu. Nous vous répondons rapidement.');
        redirect('contact');
    }
    ob_start(); ?>
    <div class="form-grid">
      <?= fld('name','Nom','text','','req') ?>
      <?= fld('email','E-mail','email','','req') ?>
      <?= fld('phone','Téléphone','tel') ?>
      <?= fld('subject','Sujet','text','','req full') ?>
      <?= fld('body','Message','textarea','','req full') ?>
    </div>
    <p class="muted"><?= e(setting('address', config('contact.address'))) ?> · <?= e(setting('phone', config('contact.phone'))) ?></p>
    <?php
    view('public/form_page', [
        'title' => 'Contact', 'nav' => 'contact', 'kicker' => 'Parlons-en',
        'intro' => 'Une question, un mandat, une recherche urgente ?',
        'form' => ob_get_clean(), 'submit' => 'Envoyer',
    ]);
}

function public_services(): void
{
    view('public/services', ['title' => 'Services', 'nav' => 'services']);
}

function auth_login(): void
{
    guest_only();
    if (is_post()) {
        csrf_verify();
        $r = login(str_input('email'), (string) input('password', ''));
        if (!$r['ok']) {
            remember_old(['email' => str_input('email')]);
            flash('error', $r['error']);
            redirect('connexion');
        }
        clear_old();
        $to = $_SESSION['_intended'] ?? home_for($r['user']);
        unset($_SESSION['_intended']);
        redirect($to);
    }
    view('auth/login', ['title' => 'Connexion'], 'auth');
}

function auth_register(): void
{
    guest_only();
    if (is_post()) {
        csrf_verify();
        try {
            $location = rdc_location(str_input('province'), str_input('city'));
            if ($location['province'] === '' || $location['city'] === '') {
                throw new InvalidArgumentException('La province et la ville sont obligatoires.');
            }
        } catch (InvalidArgumentException $e) {
            remember_old($_POST);
            flash('error', $e->getMessage());
            redirect('inscription');
        }
        $email = mb_strtolower(str_input('email'));
        if (db()->one('SELECT id FROM users WHERE email = ?', [$email])) {
            flash('error', 'Un compte existe déjà avec cet e-mail.');
            remember_old($_POST);
            redirect('inscription');
        }
        $role = str_input('role');
        if (!in_array($role, ['tenant', 'owner', 'traveler'], true)) {
            $role = 'traveler';
        }
        $uid = db()->insert('users', [
            'email' => $email,
            'password' => password_hash((string) input('password'), PASSWORD_DEFAULT),
            'first_name' => str_input('first_name'),
            'last_name' => str_input('last_name'),
            'phone' => str_input('phone'),
            'whatsapp' => str_input('whatsapp'),
            'province' => $location['province'],
            'city' => $location['city'],
            'role' => $role,
            'status' => 'actif',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($role === 'owner') {
            db()->insert('owners', [
                'user_id' => $uid, 'first_name' => str_input('first_name'), 'last_name' => str_input('last_name'),
                'phone' => str_input('phone'), 'whatsapp' => str_input('whatsapp'), 'email' => $email,
                'province' => $location['province'], 'city' => $location['city'], 'created_at' => now(),
            ]);
        } elseif ($role === 'tenant') {
            db()->insert('tenants', [
                'user_id' => $uid, 'first_name' => str_input('first_name'), 'last_name' => str_input('last_name'),
                'phone' => str_input('phone'), 'whatsapp' => str_input('whatsapp'), 'email' => $email, 'created_at' => now(),
            ]);
        }
        login($email, (string) input('password'));
        flash('success', 'Compte créé. Bienvenue.');
        redirect(home_for(user()));
    }
    view('auth/register', ['title' => 'Inscription'], 'auth');
}

function auth_logout(): void
{
    logout();
    redirect('');
}

function auth_forgot(): void
{
    $reset_link = null;
    if (is_post()) {
        csrf_verify();
        $email = mb_strtolower(str_input('email'));
        $u = db()->one('SELECT id FROM users WHERE email = ?', [$email]);
        if ($u) {
            $token = bin2hex(random_bytes(24));
            db()->insert('password_resets', [
                'email' => $email, 'token' => $token,
                'expires_at' => date('Y-m-d H:i:s', time() + 3600), 'used' => 0,
            ]);
            $reset_link = base_url('reset/' . $token);
        }
        flash('info', 'Si un compte existe, un lien a été généré.');
    }
    view('auth/forgot', ['title' => 'Mot de passe oublié', 'reset_link' => $reset_link], 'auth');
}

function auth_reset(string $token): void
{
    $row = db()->one('SELECT * FROM password_resets WHERE token = ? AND used = 0 AND expires_at > ?', [$token, now()]);
    if (!$row) {
        flash('error', 'Lien invalide ou expiré.');
        redirect('mot-de-passe-oublie');
    }
    if (is_post()) {
        csrf_verify();
        if (input('password') !== input('password2') || strlen((string) input('password')) < 8) {
            flash('error', 'Mots de passe différents ou trop courts.');
            redirect('reset/' . $token);
        }
        db()->update('users', ['password' => password_hash((string) input('password'), PASSWORD_DEFAULT), 'updated_at' => now()], 'email = ?', [$row['email']]);
        db()->update('password_resets', ['used' => 1], 'id = ?', [$row['id']]);
        flash('success', 'Mot de passe mis à jour.');
        redirect('connexion');
    }
    view('auth/reset', ['title' => 'Réinitialiser'], 'auth');
}

function install_page(): void
{
    if (is_file(dirname(__DIR__) . '/storage/installed.lock') && !isset($_GET['force'])) {
        flash('info', 'La plateforme est déjà installée.');
        redirect('');
    }
    $error = $_SESSION['install_error'] ?? '';
    unset($_SESSION['install_error']);
    if (is_post()) {
        csrf_verify();
        $driver = str_input('driver') === 'mysql' ? 'mysql' : 'sqlite';
        $local = [
            'db' => [
                'driver' => $driver,
                'host' => str_input('host', 'localhost'),
                'port' => int_input('port', 3306),
                'name' => str_input('name', 'youpendi'),
                'user' => str_input('user'),
                'pass' => (string) input('pass', ''),
            ],
        ];
        $file = dirname(__DIR__) . '/storage/config.local.php';
        file_put_contents($file, "<?php\nreturn " . var_export($local, true) . ";\n");
        try {
            // reset db singleton by reconnecting
            $db = new Database(array_merge(config('db'), $local['db']));
            migrate($db);
            // hijack global by writing then using new instance via function — re-include not possible
            $GLOBALS['__db_override'] = $db;
            if (!function_exists('db_installed_override')) {
                // use the new connection directly
            }
            if ((int) $db->val('SELECT COUNT(*) FROM users') === 0) {
                if (input('seed')) {
                    // seed uses db() — we need config.local loaded
                    // Force recreate: write lock after seed via db()
                }
            }
            // Re-read config with local file
            // Create admin if no users using this $db
            if ((int) $db->val('SELECT COUNT(*) FROM users') === 0) {
                $hash = password_hash((string) input('password', 'Admin@2026'), PASSWORD_DEFAULT);
                $db->insert('users', [
                    'email' => str_input('email', 'admin@youpendi.cd'),
                    'password' => $hash,
                    'first_name' => str_input('first_name', 'Admin'),
                    'last_name' => str_input('last_name', 'Youpendi'),
                    'role' => 'admin', 'status' => 'actif',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            file_put_contents(dirname(__DIR__) . '/storage/installed.lock', now());
            flash('success', 'Installation réussie. Connectez-vous.');
            redirect('connexion');
        } catch (Throwable $e) {
            @unlink($file);
            $error = 'Connexion impossible : ' . $e->getMessage();
        }
    }
    view('install', ['title' => 'Installation', 'error' => $error], null);
}

function app_dashboard(): void
{
    try {
    $u = require_auth();
    sync_expired_contracts();
    if (in_array($u['role'], ['owner', 'tenant', 'traveler'], true)) {
        redirect(home_for($u));
    }
    $kpis = [
        ['l' => 'Biens sous gestion', 'v' => db()->val('SELECT COUNT(*) FROM properties WHERE is_short_stay = 0')],
        ['l' => 'Disponibles', 'v' => db()->val("SELECT COUNT(*) FROM properties WHERE status = 'disponible'")],
        ['l' => 'Occupés', 'v' => db()->val("SELECT COUNT(*) FROM properties WHERE status = 'occupe'")],
        ['l' => 'Prospects actifs', 'v' => db()->val("SELECT COUNT(*) FROM prospects WHERE status NOT IN ('converti','non_abouti','perdu')")],
        ['l' => 'Loyers du mois', 'v' => money(db()->val("SELECT COALESCE(SUM(amount),0) FROM rents WHERE period_start >= ?", [date('Y-m-01')]))],
        ['l' => 'Encaissés (mois)', 'v' => money(db()->val("SELECT COALESCE(SUM(paid_amount),0) FROM rents WHERE period_start >= ? AND status = 'paye'", [date('Y-m-01')]))],
        ['l' => 'Impayés', 'v' => db()->val("SELECT COUNT(*) FROM rents WHERE status IN ('en_retard','a_payer') AND due_date < ?", [today()])],
        ['l' => 'Réservations Stay', 'v' => db()->val("SELECT COUNT(*) FROM reservations WHERE status NOT IN ('annulee','cloturee')")],
    ];
    if ($u['role'] === 'agent') {
        $a = agent_of($u);
        $aid = $a['id'] ?? 0;
        $kpis = [
            ['l' => 'Mes prospects', 'v' => db()->val('SELECT COUNT(*) FROM prospects WHERE agent_id = ?', [$aid])],
            ['l' => 'Biens apportés', 'v' => db()->val('SELECT COUNT(*) FROM properties WHERE agent_apporteur_id = ?', [$aid])],
            ['l' => 'Visites prévues', 'v' => db()->val("SELECT COUNT(*) FROM visits WHERE agent_id = ? AND status = 'planifiee'", [$aid])],
            ['l' => 'Commissions', 'v' => money(db()->val('SELECT COALESCE(SUM(amount),0) FROM commissions WHERE agent_id = ?', [$aid]))],
        ];
    }
    $logs = db()->all('SELECT * FROM activity_logs ORDER BY id DESC LIMIT 8');
    $todo = [
        ['app/prospects?status=nouveau', db()->val("SELECT COUNT(*) FROM prospects WHERE status = 'nouveau'") . ' nouveaux prospects'],
        ['app/visites', db()->val("SELECT COUNT(*) FROM visits WHERE status = 'planifiee'") . ' visites planifiées'],
        ['app/loyers?status=en_retard', db()->val("SELECT COUNT(*) FROM rents WHERE status = 'en_retard'") . ' loyers en retard'],
        ['app/maintenance?status=nouveau_inc', db()->val("SELECT COUNT(*) FROM maintenances WHERE status IN ('nouveau_inc','a_analyser')") . ' incidents ouverts'],
        ['app/reservations?status=demande', db()->val("SELECT COUNT(*) FROM reservations WHERE status = 'demande'") . ' réservations à confirmer'],
        ['app/paiements', db()->val("SELECT COUNT(*) FROM payments WHERE status = 'en_attente_paiement'") . ' paiements à valider'],
    ];
    view('app/dashboard', ['title' => 'Dashboard', 'heading' => 'Tableau de bord', 'kpis' => $kpis, 'logs' => $logs, 'todo' => $todo], 'app');
    } catch (Throwable $e) {
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Dashboard</title></head><body style="font-family:sans-serif;padding:24px">';
        echo '<h1>Tableau de bord</h1><p><code>' . htmlspecialchars($e->getMessage()) . '</code></p>';
        echo '<p><a href="/">Accueil</a></p></body></html>';
    }
}

function list_page(string $title, array $cols, array $rows, array $opt = []): void
{
    view('app/list', array_merge([
        'title' => $title, 'cols' => $cols, 'rows' => $rows, 'pg' => ['pages' => 1],
    ], $opt), 'app');
}

function a_link(string $href, string $label): string
{
    return '<a href="' . e(base_url($href)) . '">' . e($label) . '</a>';
}

function post_btn(string $href, string $label, string $cls = 'btn btn-sm'): string
{
    return '<form method="post" action="' . e(base_url($href)) . '" style="display:inline">' . csrf_field() . '<button class="' . $cls . '">' . e($label) . '</button></form>';
}

function post_btn_confirm(string $href, string $label, string $message, string $cls = 'btn btn-sm'): string
{
    return '<form method="post" action="' . e(base_url($href)) . '" style="display:inline" onsubmit="return confirm(' . e(json_encode($message, JSON_UNESCAPED_UNICODE)) . ')">' . csrf_field() . '<button class="' . e($cls) . '">' . e($label) . '</button></form>';
}
