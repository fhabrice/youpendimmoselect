<?php

/**
 * Importe les données des anciennes tables françaises
 * (utilisateurs, proprietes, visites, …) vers le nouveau schéma.
 * Ne supprime rien. Ne renomme jamais agents / notifications existants.
 */
function pick_col(array $cols, array $candidates): ?string
{
    $lower = array_map('strtolower', $cols);
    foreach ($candidates as $c) {
        $i = array_search(strtolower($c), $lower, true);
        if ($i !== false) {
            return $cols[$i];
        }
    }
    return null;
}

function row_val(array $row, array $cols, array $candidates, $default = null)
{
    $c = pick_col($cols, $candidates);
    if (!$c || !array_key_exists($c, $row)) {
        return $default;
    }
    $v = $row[$c];
    return $v === '' ? $default : $v;
}

function map_role($raw): string
{
    $r = mb_strtolower(trim((string) $raw));
    return match (true) {
        str_contains($r, 'admin') => 'admin',
        str_contains($r, 'financ') => 'finance',
        str_contains($r, 'gest') || str_contains($r, 'manager') => 'manager',
        str_contains($r, 'superv') => 'supervisor',
        str_contains($r, 'agent') => 'agent',
        str_contains($r, 'propr') || str_contains($r, 'owner') => 'owner',
        str_contains($r, 'locat') || str_contains($r, 'tenant') => 'tenant',
        str_contains($r, 'voyag') || str_contains($r, 'client') => 'traveler',
        default => 'agent',
    };
}

function map_prop_status($raw): string
{
    $s = mb_strtolower(trim((string) $raw));
    return match (true) {
        str_contains($s, 'occup') || str_contains($s, 'lou') => 'occupe',
        str_contains($s, 'reserv') => 'reserve',
        str_contains($s, 'maint') => 'maintenance',
        str_contains($s, 'arch') => 'archive',
        str_contains($s, 'indispo') => 'indisponible',
        str_contains($s, 'visite') => 'visite_en_cours',
        str_contains($s, 'publ') => 'publie',
        str_contains($s, 'valid') => 'valide',
        str_contains($s, 'attente') || str_contains($s, 'brouillon') => 'en_attente',
        default => 'disponible',
    };
}

function map_prop_type($raw): string
{
    $s = mb_strtolower((string) $raw);
    foreach (array_keys(property_types()) as $k) {
        if (str_contains($s, str_replace('_', ' ', $k)) || str_contains($s, $k)) {
            return $k;
        }
    }
    if (str_contains($s, 'appart')) {
        return 'appartement';
    }
    if (str_contains($s, 'villa')) {
        return 'villa';
    }
    if (str_contains($s, 'studio')) {
        return 'studio';
    }
    if (str_contains($s, 'bureau')) {
        return 'bureau';
    }
    if (str_contains($s, 'boutique')) {
        return 'boutique';
    }
    return 'maison';
}

function import_legacy(): array
{
    $db = db();
    $report = ['ok' => true, 'imported' => [], 'skipped' => [], 'notes' => []];
    if ($db->driver !== 'mysql') {
        $report['notes'][] = 'Import légacy uniquement en MySQL.';
        return $report;
    }

    $tables = array_map('strtolower', array_column($db->all('SHOW TABLES'), array_keys($db->all('SHOW TABLES')[0] ?? ['Tables_in_' => 0])[0] ?? 'Tables_in_db'));
    // more reliable:
    $tables = [];
    foreach ($db->pdo()->query('SHOW TABLES') as $r) {
        $tables[] = strtolower((string) array_values($r)[0]);
    }

    $has = fn(string $t) => in_array(strtolower($t), $tables, true);

    if ((int) $db->val('SELECT COUNT(*) FROM users') > 0) {
        $report['skipped'][] = 'users déjà peuplée — import utilisateurs ignoré';
    } elseif ($has('utilisateurs')) {
        $n = import_legacy_users();
        $report['imported']['utilisateurs → users'] = $n;
    } else {
        $report['notes'][] = 'Table utilisateurs absente';
    }

    if ((int) $db->val('SELECT COUNT(*) FROM owners') === 0 && $has('utilisateurs')) {
        $report['imported']['propriétaires'] = import_legacy_owners();
    }

    if ((int) $db->val('SELECT COUNT(*) FROM properties') === 0 && ($has('proprietes') || $has('propriétés'))) {
        $src = $has('proprietes') ? 'proprietes' : 'propriétés';
        $report['imported']["$src → properties"] = import_legacy_properties($src);
    }

    if ((int) $db->val('SELECT COUNT(*) FROM property_photos') === 0 && ($has('propriete_images') || $has('propriete_photos'))) {
        $src = $has('propriete_images') ? 'propriete_images' : 'propriete_photos';
        $report['imported']["$src → photos"] = import_legacy_photos($src);
    }

    if ((int) $db->val('SELECT COUNT(*) FROM visits') === 0 && $has('visites')) {
        $report['imported']['visites → visits'] = import_legacy_visits();
    }

    if ((int) $db->val('SELECT COUNT(*) FROM expenses') === 0 && $has('depenses')) {
        $report['imported']['depenses → expenses'] = import_legacy_expenses();
    }

    if ((int) $db->val('SELECT COUNT(*) FROM payments') === 0 && $has('paiements')) {
        $report['imported']['paiements → payments'] = import_legacy_payments();
    }

    if ((int) $db->val('SELECT COUNT(*) FROM prospects') === 0 && $has('demandes')) {
        $report['imported']['demandes → prospects'] = import_legacy_demandes();
    }

    if ($has('commentaires') && (int) $db->val('SELECT COUNT(*) FROM prospect_notes') === 0) {
        $report['imported']['commentaires'] = import_legacy_comments();
    }

    if ((int) $db->val('SELECT COUNT(*) FROM users') === 0) {
        $db->insert('users', [
            'email' => 'admin@youpendi.cd',
            'password' => password_hash('Admin@2026', PASSWORD_DEFAULT),
            'first_name' => 'Admin',
            'last_name' => 'Youpendi',
            'role' => 'admin',
            'status' => 'actif',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $report['notes'][] = 'Aucun utilisateur importé : compte admin@youpendi.cd / Admin@2026 créé.';
    }

    // settings defaults
    if (!(int) $db->val("SELECT COUNT(*) FROM settings")) {
        foreach ([
            'company' => 'YOUPENDI IMMO SELECT',
            'whatsapp' => '243970000000',
            'commission_mgmt' => '10',
            'commission_apporteur' => '30',
            'commission_commercial' => '40',
            'currency' => 'USD',
        ] as $k => $v) {
            $db->insert('settings', ['k' => $k, 'v' => $v, 'updated_at' => now()]);
        }
    }

    return $report;
}

function import_legacy_users(): int
{
    $db = db();
    $cols = array_column($db->all('SHOW COLUMNS FROM utilisateurs'), 'Field');
    $rows = $db->all('SELECT * FROM utilisateurs');
    $n = 0;
    $idMap = [];
    foreach ($rows as $r) {
        $email = mb_strtolower(trim((string) row_val($r, $cols, ['email', 'mail', 'courriel', 'e_mail'], '')));
        if ($email === '' || db()->one('SELECT id FROM users WHERE email = ?', [$email])) {
            continue;
        }
        $nom = (string) row_val($r, $cols, ['nom', 'last_name', 'name'], '');
        $prenom = (string) row_val($r, $cols, ['prenom', 'prénom', 'first_name'], '');
        if ($prenom === '' && $nom !== '') {
            $parts = preg_split('/\s+/', $nom, 2);
            $prenom = $parts[0] ?? 'User';
            $nom = $parts[1] ?? $nom;
        }
        $hash = (string) row_val($r, $cols, ['password', 'mot_de_passe', 'mdp', 'pass', 'passwd'], '');
        if ($hash === '' || (strlen($hash) < 20 && !preg_match('/^[a-f0-9]{32,40}$/i', $hash))) {
            $hash = password_hash('ChangeMe@2026', PASSWORD_DEFAULT);
        }
        $oldId = (int) row_val($r, $cols, ['id', 'id_utilisateur', 'user_id'], 0);
        $newId = $db->insert('users', [
            'email' => $email,
            'password' => $hash,
            'first_name' => $prenom ?: 'User',
            'last_name' => $nom ?: '',
            'phone' => (string) row_val($r, $cols, ['telephone', 'tel', 'phone', 'mobile'], ''),
            'whatsapp' => (string) row_val($r, $cols, ['whatsapp', 'wathsapp'], ''),
            'role' => map_role(row_val($r, $cols, ['role', 'type', 'profil', 'fonction'], 'agent')),
            'status' => in_array(mb_strtolower((string) row_val($r, $cols, ['statut', 'status', 'etat'], 'actif')), ['0', 'inactif', 'suspendu', 'bloque', 'bloqué'], true) ? 'suspendu' : 'actif',
            'created_at' => row_val($r, $cols, ['created_at', 'date_creation', 'created'], now()),
            'updated_at' => now(),
        ]);
        $idMap[$oldId] = $newId;
        if (map_role(row_val($r, $cols, ['role', 'type', 'profil'], '')) === 'agent'
            || map_role(row_val($r, $cols, ['role', 'type', 'profil'], '')) === 'supervisor'
            || map_role(row_val($r, $cols, ['role', 'type', 'profil'], '')) === 'manager') {
            $db->insert('immo_agents', ['user_id' => $newId, 'type' => 'immobilier', 'created_at' => now()]);
        }
        $n++;
    }
    file_put_contents(dirname(__DIR__) . '/storage/legacy_user_map.json', json_encode($idMap));
    return $n;
}

function import_legacy_owners(): int
{
    $n = 0;
    foreach (db()->all("SELECT * FROM users WHERE role = 'owner'") as $u) {
        if (db()->one('SELECT id FROM owners WHERE user_id = ? OR email = ?', [$u['id'], $u['email']])) {
            continue;
        }
        db()->insert('owners', [
            'user_id' => $u['id'],
            'first_name' => $u['first_name'],
            'last_name' => $u['last_name'],
            'phone' => $u['phone'],
            'email' => $u['email'],
            'created_at' => now(),
        ]);
        $n++;
    }
    return $n;
}

function import_legacy_properties(string $src): int
{
    $db = db();
    $cols = array_column($db->all('SHOW COLUMNS FROM `' . $src . '`'), 'Field');
    $n = 0;
    foreach ($db->all('SELECT * FROM `' . $src . '`') as $r) {
        $title = (string) row_val($r, $cols, ['titre', 'title', 'nom', 'libelle', 'name'], 'Bien importé');
        $oldId = (int) row_val($r, $cols, ['id', 'id_propriete', 'propriete_id'], 0);
        $ownerOld = row_val($r, $cols, ['proprietaire_id', 'owner_id', 'id_proprietaire', 'user_id']);
        $ownerId = null;
        if ($ownerOld) {
            $ownerId = db()->val('SELECT id FROM owners WHERE user_id = ? OR id = ?', [$ownerOld, $ownerOld]);
        }
        $newId = $db->insert('properties', [
            'reference' => (string) (row_val($r, $cols, ['reference', 'ref', 'code'], null) ?: ref_code('YP')),
            'title' => $title,
            'slug' => slugify($title),
            'type' => map_prop_type(row_val($r, $cols, ['type', 'type_bien', 'categorie'], 'maison')),
            'usage_type' => str_contains(mb_strtolower((string) row_val($r, $cols, ['usage', 'usage_type'], '')), 'pro') ? 'professionnel' : 'residentiel',
            'description' => (string) row_val($r, $cols, ['description', 'desc', 'details'], ''),
            'city' => (string) row_val($r, $cols, ['ville', 'city'], 'Goma'),
            'commune' => (string) row_val($r, $cols, ['commune'], ''),
            'quartier' => (string) row_val($r, $cols, ['quartier', 'district', 'zone'], ''),
            'address' => (string) row_val($r, $cols, ['adresse', 'address', 'adresse_complete'], ''),
            'address_public' => (string) row_val($r, $cols, ['adresse_publique', 'quartier'], ''),
            'rent' => (float) row_val($r, $cols, ['loyer', 'prix', 'prix_loyer', 'montant', 'rent', 'price'], 0),
            'currency' => (string) row_val($r, $cols, ['devise', 'currency'], 'USD'),
            'deposit' => (float) row_val($r, $cols, ['caution', 'deposit', 'garantie'], 0),
            'bedrooms' => (int) row_val($r, $cols, ['chambres', 'nb_chambres', 'bedrooms', 'nbre_chambres'], 0),
            'bathrooms' => (int) row_val($r, $cols, ['sdb', 'salles_de_bain', 'bathrooms', 'douches'], 0),
            'area' => (float) row_val($r, $cols, ['superficie', 'surface', 'area', 'm2'], 0),
            'furnished' => (int) (bool) row_val($r, $cols, ['meuble', 'meublé', 'furnished'], 0),
            'status' => map_prop_status(row_val($r, $cols, ['statut', 'status', 'etat'], 'disponible')),
            'owner_id' => $ownerId ?: null,
            'commission_rate' => (float) setting('commission_mgmt', 10),
            'created_at' => row_val($r, $cols, ['created_at', 'date_ajout', 'created'], now()),
            'updated_at' => now(),
        ]);
        $mapFile = dirname(__DIR__) . '/storage/legacy_prop_map.json';
        $map = is_file($mapFile) ? json_decode(file_get_contents($mapFile), true) : [];
        $map[$oldId] = $newId;
        file_put_contents($mapFile, json_encode($map));
        $n++;
    }
    return $n;
}

function import_legacy_photos(string $src): int
{
    $db = db();
    $cols = array_column($db->all('SHOW COLUMNS FROM `' . $src . '`'), 'Field');
    $map = is_file(dirname(__DIR__) . '/storage/legacy_prop_map.json')
        ? json_decode(file_get_contents(dirname(__DIR__) . '/storage/legacy_prop_map.json'), true)
        : [];
    $n = 0;
    foreach ($db->all('SELECT * FROM `' . $src . '`') as $r) {
        $oldPid = (int) row_val($r, $cols, ['propriete_id', 'id_propriete', 'property_id', 'bien_id'], 0);
        $pid = $map[$oldPid] ?? $oldPid;
        if (!$pid || !db()->one('SELECT id FROM properties WHERE id = ?', [$pid])) {
            continue;
        }
        $path = (string) row_val($r, $cols, ['chemin', 'path', 'image', 'url', 'fichier', 'photo', 'nom_fichier'], '');
        if ($path === '') {
            continue;
        }
        $db->insert('property_photos', [
            'property_id' => $pid,
            'path' => $path,
            'is_cover' => (int) row_val($r, $cols, ['is_cover', 'principale', 'cover'], 0),
            'sort_order' => (int) row_val($r, $cols, ['ordre', 'sort', 'position'], 0),
        ]);
        $n++;
    }
    return $n;
}

function import_legacy_visits(): int
{
    $db = db();
    $cols = array_column($db->all('SHOW COLUMNS FROM visites'), 'Field');
    $map = is_file(dirname(__DIR__) . '/storage/legacy_prop_map.json')
        ? json_decode(file_get_contents(dirname(__DIR__) . '/storage/legacy_prop_map.json'), true)
        : [];
    $n = 0;
    foreach ($db->all('SELECT * FROM visites') as $r) {
        $oldPid = (int) row_val($r, $cols, ['propriete_id', 'id_propriete', 'property_id'], 0);
        $st = mb_strtolower((string) row_val($r, $cols, ['statut', 'status'], 'planifiee'));
        $status = str_contains($st, 'realis') || str_contains($st, 'fait') ? 'realisee' : (str_contains($st, 'annul') ? 'annulee' : 'planifiee');
        $db->insert('visits', [
            'property_id' => $map[$oldPid] ?? ($oldPid ?: null),
            'visitor_name' => (string) row_val($r, $cols, ['nom', 'visiteur', 'client', 'visitor_name', 'nom_visiteur'], 'Visiteur'),
            'visitor_phone' => (string) row_val($r, $cols, ['telephone', 'tel', 'phone'], ''),
            'visitor_email' => (string) row_val($r, $cols, ['email', 'mail'], ''),
            'scheduled_at' => row_val($r, $cols, ['date_visite', 'scheduled_at', 'date', 'created_at'], now()),
            'status' => $status,
            'report' => (string) row_val($r, $cols, ['rapport', 'compte_rendu', 'commentaire', 'notes'], ''),
            'created_at' => now(),
        ]);
        $n++;
    }
    return $n;
}

function import_legacy_expenses(): int
{
    $db = db();
    $cols = array_column($db->all('SHOW COLUMNS FROM depenses'), 'Field');
    $map = is_file(dirname(__DIR__) . '/storage/legacy_prop_map.json')
        ? json_decode(file_get_contents(dirname(__DIR__) . '/storage/legacy_prop_map.json'), true)
        : [];
    $n = 0;
    foreach ($db->all('SELECT * FROM depenses') as $r) {
        $oldPid = (int) row_val($r, $cols, ['propriete_id', 'id_propriete', 'property_id'], 0);
        $db->insert('expenses', [
            'property_id' => $map[$oldPid] ?? ($oldPid ?: null),
            'category' => (string) row_val($r, $cols, ['categorie', 'category', 'type'], 'autre'),
            'amount' => (float) row_val($r, $cols, ['montant', 'amount', 'prix'], 0),
            'expense_date' => row_val($r, $cols, ['date', 'expense_date', 'created_at'], today()),
            'description' => (string) row_val($r, $cols, ['description', 'libelle', 'motif'], ''),
            'beneficiary' => (string) row_val($r, $cols, ['beneficiaire', 'prestataire'], ''),
            'status' => 'validee',
            'created_at' => now(),
        ]);
        $n++;
    }
    return $n;
}

function import_legacy_payments(): int
{
    $db = db();
    $cols = array_column($db->all('SHOW COLUMNS FROM paiements'), 'Field');
    $n = 0;
    foreach ($db->all('SELECT * FROM paiements') as $r) {
        $db->insert('payments', [
            'amount' => (float) row_val($r, $cols, ['montant', 'amount'], 0),
            'method' => (string) row_val($r, $cols, ['mode', 'methode', 'method'], 'autre'),
            'reference' => (string) row_val($r, $cols, ['reference', 'ref', 'transaction'], ''),
            'paid_at' => row_val($r, $cols, ['date_paiement', 'paid_at', 'date'], today()),
            'status' => str_contains(mb_strtolower((string) row_val($r, $cols, ['statut', 'status'], '')), 'attente') ? 'en_attente_paiement' : 'confirme',
            'notes' => (string) row_val($r, $cols, ['notes', 'commentaire'], 'Importé'),
            'created_at' => now(),
        ]);
        $n++;
    }
    return $n;
}

function import_legacy_demandes(): int
{
    $db = db();
    $cols = array_column($db->all('SHOW COLUMNS FROM demandes'), 'Field');
    $n = 0;
    foreach ($db->all('SELECT * FROM demandes') as $r) {
        $phone = (string) row_val($r, $cols, ['telephone', 'tel', 'phone'], '');
        if ($phone && db()->one('SELECT id FROM prospects WHERE phone = ?', [$phone])) {
            continue;
        }
        $nom = (string) row_val($r, $cols, ['nom', 'name', 'client'], 'Prospect');
        $db->insert('prospects', [
            'type' => str_contains(mb_strtolower((string) row_val($r, $cols, ['type'], '')), 'propr') ? 'proprietaire' : 'locataire',
            'first_name' => $nom,
            'last_name' => '',
            'phone' => $phone,
            'email' => (string) row_val($r, $cols, ['email', 'mail'], ''),
            'city' => (string) row_val($r, $cols, ['ville', 'city'], 'Goma'),
            'quartier' => (string) row_val($r, $cols, ['quartier'], ''),
            'notes' => (string) row_val($r, $cols, ['message', 'commentaire', 'details', 'description'], ''),
            'source' => 'import',
            'status' => 'nouveau',
            'created_at' => row_val($r, $cols, ['created_at', 'date'], now()),
            'updated_at' => now(),
        ]);
        $n++;
    }
    return $n;
}

function import_legacy_comments(): int
{
    $db = db();
    $cols = array_column($db->all('SHOW COLUMNS FROM commentaires'), 'Field');
    $n = 0;
    foreach ($db->all('SELECT * FROM commentaires') as $r) {
        $db->insert('prospect_notes', [
            'prospect_id' => (int) row_val($r, $cols, ['demande_id', 'prospect_id', 'id_demande'], 0) ?: 0,
            'body' => (string) row_val($r, $cols, ['commentaire', 'message', 'contenu', 'body'], ''),
            'created_at' => row_val($r, $cols, ['created_at', 'date'], now()),
        ]);
        $n++;
    }
    return $n;
}
