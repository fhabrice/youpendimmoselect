<?php

/**
 * Relie le nouveau code aux tables déjà présentes sur la base de l’hébergeur.
 * Aucune table existante n’est drop / rename.
 */
function purge_demo_accounts(?Database $db = null): void
{
    $db = $db ?? db();
    $emails = [
        'admin@youpendi.cd', 'finance@youpendi.cd', 'gestion@youpendi.cd',
        'superviseur@youpendi.cd', 'agent@youpendi.cd', 'agent2@youpendi.cd',
        'proprio@youpendi.cd', 'proprio2@youpendi.cd', 'locataire@youpendi.cd',
        'voyageur@youpendi.cd',
    ];
    $in = implode(',', array_fill(0, count($emails), '?'));
    foreach (['users', 'utilisateurs'] as $t) {
        try {
            $db->exec("DELETE FROM `$t` WHERE email IN ($in)", $emails);
        } catch (Throwable $e) {
        }
        try {
            $db->exec("DELETE FROM `$t` WHERE mail IN ($in)", $emails);
        } catch (Throwable $e) {
        }
    }
}

function hosting_alias_plan(Database $db): array
{
    if ($db->driver !== 'mysql') {
        return [];
    }
    $have = [];
    foreach ($db->pdo()->query('SHOW TABLES') as $r) {
        $have[strtolower((string) array_values($r)[0])] = true;
    }
    // Ne plus fusionner proprietes/utilisateurs dans les requêtes :
    // trop de colonnes différentes → erreurs 500. Les tables YOUPENDI restent séparées.
    return [];
}

function hosting_needed_columns(): array
{
    return [
        'users' => [
            'email' => 'VARCHAR(190) NULL',
            'password' => 'VARCHAR(255) NULL',
            'first_name' => 'VARCHAR(80) NULL',
            'last_name' => 'VARCHAR(80) NULL',
            'phone' => 'VARCHAR(40) NULL',
            'whatsapp' => 'VARCHAR(40) NULL',
            'province' => 'VARCHAR(80) NULL',
            'city' => 'VARCHAR(80) NULL',
            'address' => 'TEXT NULL',
            'role' => "VARCHAR(30) NOT NULL DEFAULT 'agent'",
            'status' => "VARCHAR(20) NOT NULL DEFAULT 'actif'",
            'avatar' => 'VARCHAR(255) NULL',
            'last_login' => 'DATETIME NULL',
            'created_at' => 'DATETIME NULL',
            'updated_at' => 'DATETIME NULL',
        ],
        'properties' => [
            'reference' => 'VARCHAR(40) NULL',
            'title' => 'VARCHAR(200) NULL',
            'slug' => 'VARCHAR(220) NULL',
            'type' => "VARCHAR(40) NULL DEFAULT 'appartement'",
            'usage_type' => "VARCHAR(20) DEFAULT 'residentiel'",
            'description' => 'TEXT NULL',
            'province' => 'VARCHAR(80) NULL',
            'city' => 'VARCHAR(80) NULL',
            'commune' => 'VARCHAR(80) NULL',
            'quartier' => 'VARCHAR(80) NULL',
            'address' => 'TEXT NULL',
            'address_public' => 'VARCHAR(200) NULL',
            'rent' => 'DOUBLE DEFAULT 0',
            'currency' => "VARCHAR(8) DEFAULT 'USD'",
            'deposit' => 'DOUBLE DEFAULT 0',
            'bedrooms' => 'INT DEFAULT 0',
            'bathrooms' => 'INT DEFAULT 0',
            'area' => 'DOUBLE NULL',
            'furnished' => 'TINYINT DEFAULT 0',
            'amenities' => 'TEXT NULL',
            'status' => "VARCHAR(30) DEFAULT 'disponible'",
            'available_from' => 'DATE NULL',
            'owner_id' => 'INT NULL',
            'agent_apporteur_id' => 'INT NULL',
            'agent_commercial_id' => 'INT NULL',
            'manager_id' => 'INT NULL',
            'commission_rate' => 'DOUBLE DEFAULT 10',
            'managed_from' => 'DATE NULL',
            'visit_modalities' => 'TEXT NULL',
            'is_short_stay' => 'TINYINT DEFAULT 0',
            'offre' => "VARCHAR(40) DEFAULT 'location_mensuelle'",
            'created_by' => 'INT NULL',
            'created_at' => 'DATETIME NULL',
            'updated_at' => 'DATETIME NULL',
        ],
        'property_photos' => [
            'property_id' => 'INT NULL',
            'path' => 'VARCHAR(255) NULL',
            'is_cover' => 'TINYINT DEFAULT 0',
            'sort_order' => 'INT DEFAULT 0',
        ],
        'visits' => [
            'property_id' => 'INT NULL',
            'prospect_id' => 'INT NULL',
            'visitor_name' => 'VARCHAR(120) NULL',
            'visitor_phone' => 'VARCHAR(40) NULL',
            'visitor_email' => 'VARCHAR(190) NULL',
            'agent_id' => 'INT NULL',
            'scheduled_at' => 'DATETIME NULL',
            'status' => "VARCHAR(20) DEFAULT 'planifiee'",
            'report' => 'TEXT NULL',
            'created_at' => 'DATETIME NULL',
        ],
        'expenses' => [
            'property_id' => 'INT NULL',
            'category' => 'VARCHAR(60) NULL',
            'amount' => 'DOUBLE DEFAULT 0',
            'expense_date' => 'DATE NULL',
            'description' => 'TEXT NULL',
            'beneficiary' => 'VARCHAR(120) NULL',
            'invoice_path' => 'VARCHAR(255) NULL',
            'created_by' => 'INT NULL',
            'validated_by' => 'INT NULL',
            'status' => "VARCHAR(20) DEFAULT 'validee'",
            'created_at' => 'DATETIME NULL',
        ],
        'payments' => [
            'rent_id' => 'INT NULL',
            'amount' => 'DOUBLE DEFAULT 0',
            'method' => 'VARCHAR(40) NULL',
            'reference' => 'VARCHAR(80) NULL',
            'paid_at' => 'DATE NULL',
            'status' => "VARCHAR(20) DEFAULT 'confirme'",
            'confirmed_by' => 'INT NULL',
            'confirmed_at' => 'DATETIME NULL',
            'notes' => 'TEXT NULL',
            'created_by' => 'INT NULL',
            'created_at' => 'DATETIME NULL',
        ],
        'prospects' => [
            'type' => "VARCHAR(20) DEFAULT 'locataire'",
            'first_name' => 'VARCHAR(80) NULL',
            'last_name' => 'VARCHAR(80) NULL',
            'phone' => 'VARCHAR(40) NULL',
            'whatsapp' => 'VARCHAR(40) NULL',
            'email' => 'VARCHAR(190) NULL',
            'province' => 'VARCHAR(80) NULL',
            'city' => 'VARCHAR(80) NULL',
            'commune' => 'VARCHAR(80) NULL',
            'quartier' => 'VARCHAR(80) NULL',
            'property_type' => 'VARCHAR(40) NULL',
            'bedrooms' => 'INT NULL',
            'budget_min' => 'DOUBLE NULL',
            'budget_max' => 'DOUBLE NULL',
            'furnished' => 'TINYINT NULL',
            'desired_date' => 'DATE NULL',
            'duration' => 'VARCHAR(80) NULL',
            'management_type' => 'VARCHAR(40) NULL',
            'source' => 'VARCHAR(40) NULL',
            'agent_id' => 'INT NULL',
            'status' => "VARCHAR(20) DEFAULT 'nouveau'",
            'notes' => 'TEXT NULL',
            'converted_to' => 'VARCHAR(20) NULL',
            'converted_id' => 'INT NULL',
            'created_at' => 'DATETIME NULL',
            'updated_at' => 'DATETIME NULL',
        ],
        'prospect_notes' => [
            'prospect_id' => 'INT NULL',
            'user_id' => 'INT NULL',
            'body' => 'TEXT NULL',
            'created_at' => 'DATETIME NULL',
        ],
    ];
}

function hosting_copy_map(): array
{
    return [
        'users' => [
            'first_name' => ['prenom', 'prénom', 'name'],
            'last_name' => ['nom', 'name'],
            'password' => ['mot_de_passe', 'mdp', 'pass', 'passwd'],
            'phone' => ['telephone', 'tel', 'mobile'],
            'status' => ['statut', 'etat'],
            'created_at' => ['date_creation', 'created', 'date_ajout'],
            'email' => ['mail', 'courriel', 'e_mail'],
            'role' => ['type', 'profil', 'fonction'],
        ],
        'properties' => [
            'title' => ['titre', 'nom', 'libelle', 'name'],
            'city' => ['ville'],
            'address' => ['adresse', 'adresse_complete'],
            'rent' => ['loyer', 'prix', 'prix_loyer', 'montant', 'price'],
            'deposit' => ['caution', 'garantie'],
            'bedrooms' => ['chambres', 'nb_chambres', 'nbre_chambres'],
            'bathrooms' => ['sdb', 'salles_de_bain', 'douches'],
            'area' => ['superficie', 'surface', 'm2'],
            'status' => ['statut', 'etat'],
            'owner_id' => ['proprietaire_id', 'id_proprietaire'],
            'description' => ['desc', 'details'],
            'quartier' => ['district', 'zone'],
            'currency' => ['devise'],
            'furnished' => ['meuble', 'meublé'],
            'created_at' => ['date_ajout', 'date_creation', 'created'],
            'type' => ['type_bien', 'categorie'],
        ],
        'property_photos' => [
            'property_id' => ['propriete_id', 'id_propriete', 'bien_id'],
            'path' => ['chemin', 'image', 'url', 'fichier', 'photo', 'nom_fichier'],
            'is_cover' => ['principale', 'cover'],
            'sort_order' => ['ordre', 'position'],
        ],
        'visits' => [
            'property_id' => ['propriete_id', 'id_propriete'],
            'visitor_name' => ['nom', 'visiteur', 'client', 'nom_visiteur'],
            'visitor_phone' => ['telephone', 'tel'],
            'visitor_email' => ['email', 'mail'],
            'scheduled_at' => ['date_visite', 'date'],
            'status' => ['statut', 'etat'],
            'report' => ['rapport', 'compte_rendu', 'commentaire', 'notes'],
        ],
        'expenses' => [
            'property_id' => ['propriete_id', 'id_propriete'],
            'category' => ['categorie', 'type'],
            'amount' => ['montant', 'prix'],
            'expense_date' => ['date'],
            'description' => ['libelle', 'motif'],
            'beneficiary' => ['beneficiaire', 'prestataire'],
            'status' => ['statut', 'etat'],
        ],
        'payments' => [
            'amount' => ['montant'],
            'method' => ['mode', 'methode'],
            'reference' => ['ref', 'transaction'],
            'paid_at' => ['date_paiement', 'date'],
            'status' => ['statut', 'etat'],
            'notes' => ['commentaire'],
        ],
        'prospects' => [
            'first_name' => ['nom', 'name', 'client'],
            'phone' => ['telephone', 'tel'],
            'email' => ['mail'],
            'city' => ['ville'],
            'notes' => ['message', 'commentaire', 'details', 'description'],
            'status' => ['statut', 'etat'],
            'created_at' => ['date'],
        ],
        'prospect_notes' => [
            'prospect_id' => ['demande_id', 'id_demande'],
            'body' => ['commentaire', 'message', 'contenu'],
            'created_at' => ['date'],
        ],
    ];
}

function ensure_hosting_schema(Database $db): array
{
    $log = [];
    if ($db->driver !== 'mysql') {
        return $log;
    }
    $aliases = hosting_alias_plan($db);
    $db->setAliases($aliases);
    $log['aliases'] = $aliases;

    foreach (['properties', 'proprietes'] as $t) {
        try {
            $cols = mysql_columns($db, $t);
            if ($cols && !in_array('offre', $cols, true)) {
                $db->pdo()->exec("ALTER TABLE `$t` ADD COLUMN `offre` VARCHAR(40) DEFAULT 'location_mensuelle'");
                $log['added'][$t][] = 'offre';
            }
        } catch (Throwable $e) {
            $log['warn'][] = $e->getMessage();
        }
    }

    $needed = hosting_needed_columns();
    $copies = hosting_copy_map();

    foreach ($aliases as $logical => $physical) {
        $cols = mysql_columns($db, $physical);
        foreach ($needed[$logical] ?? [] as $col => $ddl) {
            if (!in_array(strtolower($col), $cols, true)) {
                $db->pdo()->exec('ALTER TABLE `' . $physical . '` ADD COLUMN `' . $col . '` ' . $ddl);
                $log['added'][$physical][] = $col;
                $cols[] = strtolower($col);
            }
        }
        foreach ($copies[$logical] ?? [] as $dest => $sources) {
            if (!in_array(strtolower($dest), $cols, true)) {
                continue;
            }
            foreach ($sources as $src) {
                if (in_array(strtolower($src), $cols, true) && strtolower($src) !== strtolower($dest)) {
                    $db->pdo()->exec(
                        "UPDATE `{$physical}` SET `{$dest}` = `{$src}` WHERE (`{$dest}` IS NULL OR `{$dest}` = '') AND `{$src}` IS NOT NULL AND `{$src}` != ''"
                    );
                    $log['copied'][$physical][] = "$src → $dest";
                    break;
                }
            }
        }
    }

    // Normalise quelques statuts / rôles fréquents
    if (isset($aliases['users'])) {
        $t = $aliases['users'];
        try {
            $db->pdo()->exec("UPDATE `$t` SET role = 'admin' WHERE role IN ('administrateur','Administrateur','ADMIN')");
            $db->pdo()->exec("UPDATE `$t` SET role = 'owner' WHERE role IN ('proprietaire','propriétaire','Proprietaire')");
            $db->pdo()->exec("UPDATE `$t` SET role = 'tenant' WHERE role IN ('locataire','Locataire')");
            $db->pdo()->exec("UPDATE `$t` SET role = 'agent' WHERE role IN ('Agent','agent_immobilier')");
            $db->pdo()->exec("UPDATE `$t` SET status = 'actif' WHERE status IN ('1','active','Active','oui') OR status IS NULL OR status = ''");
        } catch (Throwable $e) {
            $log['warn'][] = $e->getMessage();
        }
    }
    if (isset($aliases['properties'])) {
        $t = $aliases['properties'];
        try {
            $db->pdo()->exec("UPDATE `$t` SET status = 'disponible' WHERE status IN ('libre','Libre','dispo','1','publie','publié') OR status IS NULL OR status = ''");
            $db->pdo()->exec("UPDATE `$t` SET status = 'occupe' WHERE status IN ('occupé','loue','loué','Loué')");
            $db->pdo()->exec("UPDATE `$t` SET title = CONCAT('Bien #', id) WHERE title IS NULL OR title = ''");
        } catch (Throwable $e) {
            $log['warn'][] = $e->getMessage();
        }
    }

    try {
        purge_demo_accounts($db);
        $log['demo_purged'] = true;
    } catch (Throwable $e) {
        $log['warn'][] = $e->getMessage();
    }

    $nUsers = (int) $db->val('SELECT COUNT(*) FROM users');
    if ($nUsers === 0) {
        $db->insert('users', [
            'email' => 'admin@youpendimmoselect.com',
            'password' => password_hash('Admin@2026', PASSWORD_DEFAULT),
            'first_name' => 'Admin',
            'last_name' => 'Youpendi',
            'role' => 'admin',
            'status' => 'actif',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $log['admin_created'] = 'admin@youpendimmoselect.com';
    }

    if (!(int) $db->val('SELECT COUNT(*) FROM settings')) {
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

    file_put_contents(dirname(__DIR__) . '/storage/hosting_map.json', json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return $log;
}
