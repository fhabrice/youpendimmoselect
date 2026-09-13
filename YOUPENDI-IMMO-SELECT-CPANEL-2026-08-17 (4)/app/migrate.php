<?php

function yp_id_sql(Database $db): string
{
    return $db->driver === 'mysql'
        ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY'
        : 'INTEGER PRIMARY KEY AUTOINCREMENT';
}

function yp_text(Database $db): string
{
    return $db->driver === 'mysql' ? 'TEXT' : 'TEXT';
}

function table_exists(Database $db, string $name): bool
{
    if ($db->driver === 'mysql') {
        $n = $db->val('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$name]);
        return (int) $n > 0;
    }
    return (bool) $db->val("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?", [$name]);
}

function mysql_columns(Database $db, string $table): array
{
    if ($db->driver !== 'mysql' || !table_exists($db, $table)) {
        return [];
    }
    return array_map('strtolower', array_column($db->all('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`'), 'Field'));
}

/** Retourne les colonnes d'une table, aussi bien avec MySQL qu'avec SQLite. */
function schema_columns(Database $db, string $table): array
{
    if (!table_exists($db, $table)) {
        return [];
    }
    if ($db->driver === 'mysql') {
        return mysql_columns($db, $table);
    }
    $safe = str_replace(['`', '"', "'"], '', $table);
    return array_map('strtolower', array_column($db->all("PRAGMA table_info(`$safe`)"), 'name'));
}

/** Ajoute une colonne sans casser les installations qui l'ont déjà. */
function ensure_column(Database $db, string $table, string $column, string $definition): void
{
    if (!table_exists($db, $table) || in_array(strtolower($column), schema_columns($db, $table), true)) {
        return;
    }
    $safeTable = str_replace('`', '', $table);
    $safeColumn = str_replace('`', '', $column);
    $db->pdo()->exec("ALTER TABLE `$safeTable` ADD COLUMN `$safeColumn` $definition");
}

function migrate(Database $db): void
{
    // Local SQLite only: rename our old demo tables. Never touch MySQL "agents"/"notifications" (données hébergeur).
    if ($db->driver === 'sqlite') {
        try {
            if ($db->val("SELECT name FROM sqlite_master WHERE type='table' AND name='agents'")
                && !$db->val("SELECT name FROM sqlite_master WHERE type='table' AND name='immo_agents'")) {
                $db->pdo()->exec('ALTER TABLE agents RENAME TO immo_agents');
            }
            if ($db->val("SELECT name FROM sqlite_master WHERE type='table' AND name='notifications'")
                && !$db->val("SELECT name FROM sqlite_master WHERE type='table' AND name='app_notifications'")) {
                $db->pdo()->exec('ALTER TABLE notifications RENAME TO app_notifications');
            }
        } catch (Throwable $e) {
        }
    }

    $id = yp_id_sql($db);
    $engine = $db->driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';

    $stmts = [];

    $stmts[] = "CREATE TABLE IF NOT EXISTS settings (
        k VARCHAR(120) PRIMARY KEY,
        v TEXT,
        updated_at DATETIME
    )$engine";

    $stmts[] = "CREATE TABLE IF NOT EXISTS users (
        id $id,
        email VARCHAR(190) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        first_name VARCHAR(80) NOT NULL,
        last_name VARCHAR(80) NOT NULL,
        phone VARCHAR(40),
        whatsapp VARCHAR(40),
        province VARCHAR(80),
        city VARCHAR(80),
        address TEXT,
        role VARCHAR(30) NOT NULL DEFAULT 'traveler',
        status VARCHAR(20) NOT NULL DEFAULT 'actif',
        avatar VARCHAR(255),
        last_login DATETIME,
        created_at DATETIME,
        updated_at DATETIME
    )$engine";

    $stmts[] = "CREATE TABLE IF NOT EXISTS login_attempts (
        id $id,
        email VARCHAR(190),
        ip VARCHAR(64),
        success INTEGER DEFAULT 0,
        created_at DATETIME
    )$engine";

    $stmts[] = "CREATE TABLE IF NOT EXISTS password_resets (
        id $id,
        email VARCHAR(190),
        token VARCHAR(80),
        expires_at DATETIME,
        used INTEGER DEFAULT 0
    )$engine";

    $stmts[] = "CREATE TABLE IF NOT EXISTS teams (
        id $id,
        name VARCHAR(120) NOT NULL,
        city VARCHAR(80),
        supervisor_id INTEGER,
        created_at DATETIME
    )$engine";

    $stmts[] = "CREATE TABLE IF NOT EXISTS immo_agents (
        id $id,
        user_id INTEGER NOT NULL,
        team_id INTEGER,
        supervisor_id INTEGER,
        type VARCHAR(30) DEFAULT 'immobilier',
        commission_apporteur REAL DEFAULT 30,
        commission_commercial REAL DEFAULT 40,
        first_name VARCHAR(80),
        last_name VARCHAR(80),
        phone VARCHAR(40),
        email VARCHAR(190),
        province VARCHAR(80),
        city VARCHAR(80),
        specialties TEXT,
        photo_path VARCHAR(255),
        status VARCHAR(20) DEFAULT 'actif',
        notes TEXT,
        deleted_at DATETIME,
        created_at DATETIME,
        updated_at DATETIME
    )$engine";

    $stmts[] = "CREATE TABLE IF NOT EXISTS owners (
        id $id,
        reference VARCHAR(40) UNIQUE,
        user_id INTEGER,
        first_name VARCHAR(80),
        last_name VARCHAR(80),
        phone VARCHAR(40),
        whatsapp VARCHAR(40),
        email VARCHAR(190),
        province VARCHAR(80),
        city VARCHAR(80),
        address TEXT,
        id_number VARCHAR(80),
        notes TEXT,
        status VARCHAR(20) DEFAULT 'actif',
        deleted_at DATETIME,
        created_at DATETIME
    )$engine";

    $stmts[] = "CREATE TABLE IF NOT EXISTS prospects (
        id $id,
        type VARCHAR(20) NOT NULL,
        first_name VARCHAR(80),
        last_name VARCHAR(80),
        phone VARCHAR(40),
        whatsapp VARCHAR(40),
        email VARCHAR(190),
        province VARCHAR(80),
        city VARCHAR(80),
        commune VARCHAR(80),
        quartier VARCHAR(80),
        property_type VARCHAR(40),
        bedrooms INTEGER,
        budget_min REAL,
        budget_max REAL,
        furnished INTEGER,
        desired_date DATE,
        duration VARCHAR(80),
        management_type VARCHAR(40),
        property_description TEXT,
        availability VARCHAR(120),
        criteria TEXT,
        message TEXT,
        source VARCHAR(80),
        agent_id INTEGER,
        status VARCHAR(30) DEFAULT 'nouveau',
        notes TEXT,
        converted_to VARCHAR(20),
        converted_id INTEGER,
        created_at DATETIME,
        updated_at DATETIME
    )$engine";

    $stmts[] = "CREATE TABLE IF NOT EXISTS prospect_notes (
        id $id,
        prospect_id INTEGER NOT NULL,
        user_id INTEGER,
        body TEXT,
        created_at DATETIME
    )$engine";

    $stmts[] = "CREATE TABLE IF NOT EXISTS prospect_status_history (
        id $id,
        prospect_id INTEGER NOT NULL,
        old_status VARCHAR(30),
        new_status VARCHAR(30) NOT NULL,
        user_id INTEGER,
        note TEXT,
        created_at DATETIME
    )$engine";

    // Cahier des charges §21 — biens proposés à un prospect et réponse du client.
    $stmts[] = "CREATE TABLE IF NOT EXISTS prospect_proposals (
        id $id,
        prospect_id INTEGER NOT NULL,
        property_id INTEGER NOT NULL,
        agent_id INTEGER,
        status VARCHAR(20) DEFAULT 'envoye',
        note TEXT,
        created_at DATETIME,
        responded_at DATETIME
    )$engine";

    $stmts[] = "CREATE TABLE IF NOT EXISTS properties (
        id $id,
        reference VARCHAR(40) UNIQUE,
        title VARCHAR(200) NOT NULL,
        slug VARCHAR(220),
        type VARCHAR(40) NOT NULL,
        usage_type VARCHAR(20) DEFAULT 'residentiel',
        description TEXT,
        province VARCHAR(80),
        city VARCHAR(80),
        commune VARCHAR(80),
        quartier VARCHAR(80),
        address TEXT,
        address_public VARCHAR(200),
        lat REAL,
        lng REAL,
        rent REAL DEFAULT 0,
        currency VARCHAR(8) DEFAULT 'USD',
        deposit REAL DEFAULT 0,
        bedrooms INTEGER DEFAULT 0,
        bathrooms INTEGER DEFAULT 0,
        area REAL,
        furnished INTEGER DEFAULT 0,
        amenities TEXT,
        status VARCHAR(30) DEFAULT 'brouillon',
        offre VARCHAR(40) DEFAULT 'location_mensuelle',
        available_from DATE,
        owner_id INTEGER,
        agent_apporteur_id INTEGER,
        agent_commercial_id INTEGER,
        manager_id INTEGER,
        commission_rate REAL DEFAULT 10,
        managed_from DATE,
        visit_modalities TEXT,
        is_short_stay INTEGER DEFAULT 0,
        created_by INTEGER,
        created_at DATETIME,
        updated_at DATETIME
    )$engine";

    $stmts[] = "CREATE TABLE IF NOT EXISTS property_photos (
        id $id,
        property_id INTEGER NOT NULL,
        path VARCHAR(255) NOT NULL,
        is_cover INTEGER DEFAULT 0,
        sort_order INTEGER DEFAULT 0
    )$engine";

    $stmts[] = "CREATE TABLE IF NOT EXISTS documents (
        id $id,
        entity VARCHAR(40),
        entity_id INTEGER,
        title VARCHAR(160),
        path VARCHAR(255),
        uploaded_by INTEGER,
        created_at DATETIME
    )$engine";

    $stmts[] = "CREATE TABLE IF NOT EXISTS tenants (
        id $id,
        reference VARCHAR(40) UNIQUE,
        user_id INTEGER,
        first_name VARCHAR(80),
        last_name VARCHAR(80),
        phone VARCHAR(40),
        whatsapp VARCHAR(40),
        email VARCHAR(190),
        id_number VARCHAR(80),
        property_id INTEGER,
        contract_id INTEGER,
        entry_date DATE,
        notes TEXT,
        status VARCHAR(20) DEFAULT 'actif',
        deleted_at DATETIME,
        created_at DATETIME
    )$engine";

    $stmts[] = "CREATE TABLE IF NOT EXISTS visits (
        id $id,
        property_id INTEGER,
        prospect_id INTEGER,
        visitor_name VARCHAR(120),
        visitor_phone VARCHAR(40),
        visitor_email VARCHAR(190),
        agent_id INTEGER,
        scheduled_at DATETIME,
        status VARCHAR(20) DEFAULT 'planifiee',
        report TEXT,
        created_at DATETIME
    )$engine";

    $stmts[] = "CREATE TABLE IF NOT EXISTS contracts (
        id $id,
        reference VARCHAR(40),
        property_id INTEGER,
        owner_id INTEGER,
        tenant_id INTEGER,
        agent_id INTEGER,
        start_date DATE,
        end_date DATE,
        rent REAL,
        deposit REAL,
        currency VARCHAR(8) DEFAULT 'USD',
        periodicity VARCHAR(20) DEFAULT 'mensuel',
        commission_rate REAL DEFAULT 10,
        conditions TEXT,
        file_path VARCHAR(255),
        status VARCHAR(20) DEFAULT 'actif',
        ended_at DATETIME,
        termination_reason TEXT,
        created_at DATETIME,
        updated_at DATETIME
    )$engine";

    $stmts[] = "CREATE TABLE IF NOT EXISTS rents (
        id $id,
        contract_id INTEGER,
        property_id INTEGER,
        owner_id INTEGER,
        tenant_id INTEGER,
        period_start DATE,
        period_end DATE,
        due_date DATE,
        amount REAL,
        paid_amount REAL DEFAULT 0,
        currency VARCHAR(8) DEFAULT 'USD',
        status VARCHAR(20) DEFAULT 'a_payer',
        created_at DATETIME
    )$engine";

    $stmts[] = "CREATE TABLE IF NOT EXISTS payments (
        id $id,
        rent_id INTEGER,
        amount REAL,
        method VARCHAR(40),
        reference VARCHAR(80),
        paid_at DATE,
        status VARCHAR(20) DEFAULT 'en_attente_paiement',
        confirmed_by INTEGER,
        confirmed_at DATETIME,
        notes TEXT,
        created_by INTEGER,
        created_at DATETIME
    )$engine";

    $stmts[] = "CREATE TABLE IF NOT EXISTS receipts (
        id $id,
        number VARCHAR(40) UNIQUE,
        payment_id INTEGER,
        rent_id INTEGER,
        tenant_id INTEGER,
        property_id INTEGER,
        period_label VARCHAR(80),
        amount REAL,
        method VARCHAR(40),
        issued_at DATE,
        created_at DATETIME
    )$engine";

    $stmts[] = "CREATE TABLE IF NOT EXISTS commissions (
        id $id,
        type VARCHAR(30),
        agent_id INTEGER,
        property_id INTEGER,
        contract_id INTEGER,
        payment_id INTEGER,
        base_amount REAL,
        rate REAL,
        amount REAL,
        status VARCHAR(20) DEFAULT 'generee',
        validated_by INTEGER,
        validated_at DATETIME,
        paid_at DATE,
        notes TEXT,
        created_at DATETIME
    )$engine";

    $stmts[] = "CREATE TABLE IF NOT EXISTS expenses (
        id $id,
        property_id INTEGER,
        category VARCHAR(60),
        amount REAL,
        expense_date DATE,
        description TEXT,
        beneficiary VARCHAR(120),
        invoice_path VARCHAR(255),
        created_by INTEGER,
        validated_by INTEGER,
        status VARCHAR(20) DEFAULT 'en_attente',
        created_at DATETIME
    )$engine";

    $stmts[] = "CREATE TABLE IF NOT EXISTS vendors (
        id $id,
        name VARCHAR(120),
        specialty VARCHAR(80),
        phone VARCHAR(40),
        email VARCHAR(190),
        city VARCHAR(80),
        notes TEXT
    )$engine";

    $stmts[] = "CREATE TABLE IF NOT EXISTS maintenances (
        id $id,
        property_id INTEGER,
        tenant_id INTEGER,
        category VARCHAR(40),
        urgency VARCHAR(20) DEFAULT 'normale',
        description TEXT,
        photo_path VARCHAR(255),
        vendor_id INTEGER,
        cost REAL DEFAULT 0,
        invoice_path VARCHAR(255),
        status VARCHAR(20) DEFAULT 'nouveau_inc',
        created_by INTEGER,
        created_at DATETIME,
        updated_at DATETIME
    )$engine";

    $stmts[] = "CREATE TABLE IF NOT EXISTS payouts (
        id $id,
        owner_id INTEGER,
        period_start DATE,
        period_end DATE,
        collections REAL DEFAULT 0,
        commission REAL DEFAULT 0,
        expenses REAL DEFAULT 0,
        net_amount REAL DEFAULT 0,
        status VARCHAR(20) DEFAULT 'a_preparer',
        paid_at DATE,
        notes TEXT,
        created_at DATETIME
    )$engine";

    $stmts[] = "CREATE TABLE IF NOT EXISTS stay_listings (
        id $id,
        property_id INTEGER,
        name VARCHAR(160),
        guests INTEGER DEFAULT 2,
        beds INTEGER DEFAULT 1,
        price_night REAL,
        extra_fees REAL DEFAULT 0,
        rules TEXT,
        checkin_from VARCHAR(10) DEFAULT '14:00',
        checkout_before VARCHAR(10) DEFAULT '11:00',
        status VARCHAR(20) DEFAULT 'disponible'
    )$engine";

    $stmts[] = "CREATE TABLE IF NOT EXISTS reservations (
        id $id,
        number VARCHAR(40) UNIQUE,
        stay_id INTEGER,
        property_id INTEGER,
        user_id INTEGER,
        guest_name VARCHAR(120),
        guest_phone VARCHAR(40),
        guest_email VARCHAR(190),
        checkin DATE,
        checkout DATE,
        nights INTEGER,
        guests INTEGER,
        price_night REAL,
        fees REAL DEFAULT 0,
        total REAL,
        paid_amount REAL DEFAULT 0,
        status VARCHAR(20) DEFAULT 'demande',
        checkin_notes TEXT,
        checkout_notes TEXT,
        created_at DATETIME
    )$engine";

    $stmts[] = "CREATE TABLE IF NOT EXISTS housekeeping (
        id $id,
        reservation_id INTEGER,
        stay_id INTEGER,
        property_id INTEGER,
        assigned_to VARCHAR(120),
        status VARCHAR(20) DEFAULT 'a_faire',
        notes TEXT,
        done_at DATETIME,
        created_at DATETIME
    )$engine";

    $stmts[] = "CREATE TABLE IF NOT EXISTS app_notifications (
        id $id,
        user_id INTEGER,
        title VARCHAR(180),
        body TEXT,
        link VARCHAR(255),
        type VARCHAR(30) DEFAULT 'info',
        is_read INTEGER DEFAULT 0,
        created_at DATETIME
    )$engine";

    $stmts[] = "CREATE TABLE IF NOT EXISTS messages (
        id $id,
        thread VARCHAR(80),
        sender_id INTEGER,
        recipient_id INTEGER,
        body TEXT,
        is_read INTEGER DEFAULT 0,
        created_at DATETIME
    )$engine";

    $stmts[] = "CREATE TABLE IF NOT EXISTS tasks (
        id $id,
        agent_id INTEGER,
        title VARCHAR(180),
        due_at DATETIME,
        status VARCHAR(20) DEFAULT 'ouverte',
        related_type VARCHAR(40),
        related_id INTEGER,
        created_at DATETIME
    )$engine";

    $stmts[] = "CREATE TABLE IF NOT EXISTS activity_logs (
        id $id,
        user_id INTEGER,
        action VARCHAR(80),
        entity VARCHAR(40),
        entity_id INTEGER,
        old_value TEXT,
        new_value TEXT,
        ip VARCHAR(64),
        created_at DATETIME
    )$engine";

    $stmts[] = "CREATE TABLE IF NOT EXISTS favorites (
        id $id,
        user_id INTEGER,
        property_id INTEGER,
        created_at DATETIME
    )$engine";

    $stmts[] = "CREATE TABLE IF NOT EXISTS contact_messages (
        id $id,
        name VARCHAR(120),
        email VARCHAR(190),
        phone VARCHAR(40),
        subject VARCHAR(180),
        body TEXT,
        created_at DATETIME
    )$engine";

    $skip = [];
    // Toujours créer les tables YOUPENDI (users, properties…).
    // Les anciennes (utilisateurs, proprietes) restent intactes.

    foreach ($stmts as $sql) {
        if (preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/', $sql, $m) && in_array($m[1], $skip, true)) {
            continue;
        }
        $db->pdo()->exec($sql);
    }

    // Mises à niveau non destructives des bases déjà installées.
    $text = $db->driver === 'mysql' ? 'TEXT NULL' : 'TEXT';
    $dateTime = $db->driver === 'mysql' ? 'DATETIME NULL' : 'DATETIME';
    ensure_column($db, 'users', 'avatar', 'VARCHAR(255) NULL');
    ensure_column($db, 'users', 'province', 'VARCHAR(80) NULL');
    ensure_column($db, 'users', 'city', 'VARCHAR(80) NULL');
    ensure_column($db, 'users', 'address', $text);
    ensure_column($db, 'immo_agents', 'first_name', 'VARCHAR(80) NULL');
    ensure_column($db, 'immo_agents', 'last_name', 'VARCHAR(80) NULL');
    ensure_column($db, 'immo_agents', 'phone', 'VARCHAR(40) NULL');
    ensure_column($db, 'immo_agents', 'email', 'VARCHAR(190) NULL');
    ensure_column($db, 'immo_agents', 'province', 'VARCHAR(80) NULL');
    ensure_column($db, 'immo_agents', 'city', 'VARCHAR(80) NULL');
    ensure_column($db, 'immo_agents', 'specialties', $text);
    ensure_column($db, 'immo_agents', 'photo_path', 'VARCHAR(255) NULL');
    ensure_column($db, 'immo_agents', 'status', "VARCHAR(20) DEFAULT 'actif'");
    ensure_column($db, 'immo_agents', 'deleted_at', $dateTime);
    ensure_column($db, 'immo_agents', 'updated_at', $dateTime);
    ensure_column($db, 'owners', 'reference', 'VARCHAR(40) NULL');
    ensure_column($db, 'owners', 'province', 'VARCHAR(80) NULL');
    ensure_column($db, 'owners', 'status', "VARCHAR(20) DEFAULT 'actif'");
    ensure_column($db, 'owners', 'deleted_at', $dateTime);
    ensure_column($db, 'tenants', 'reference', 'VARCHAR(40) NULL');
    ensure_column($db, 'tenants', 'status', "VARCHAR(20) DEFAULT 'actif'");
    ensure_column($db, 'tenants', 'deleted_at', $dateTime);
    ensure_column($db, 'prospects', 'province', 'VARCHAR(80) NULL');
    ensure_column($db, 'prospects', 'property_description', $text);
    ensure_column($db, 'prospects', 'availability', 'VARCHAR(120) NULL');
    ensure_column($db, 'prospects', 'criteria', $text);
    ensure_column($db, 'prospects', 'message', $text);
    ensure_column($db, 'properties', 'province', 'VARCHAR(80) NULL');
    ensure_column($db, 'contracts', 'ended_at', $dateTime);
    ensure_column($db, 'contracts', 'termination_reason', $text);
    ensure_column($db, 'contracts', 'updated_at', $dateTime);

    // Complète la province des enregistrements historiques à partir de leur ville RDC.
    foreach (cities_by_province() as $province => $provinceCities) {
        foreach (['users', 'immo_agents', 'owners', 'prospects', 'properties'] as $table) {
            foreach ($provinceCities as $city) {
                try {
                    $db->exec(
                        "UPDATE $table SET province = ? WHERE city = ? AND (province IS NULL OR province = '')",
                        [$province, $city]
                    );
                } catch (Throwable $e) {
                    // Une table historique peut ne pas exposer ces colonnes : la migration reste additive.
                }
            }
        }
    }

    // Recopie non destructive des coordonnées d'agents déjà présentes dans users/utilisateurs.
    $pickAgentValue = static function (array $row, array $keys, $default = null) {
        $row = array_change_key_case($row, CASE_LOWER);
        foreach ($keys as $key) {
            $key = mb_strtolower($key);
            if (array_key_exists($key, $row) && $row[$key] !== null && trim((string) $row[$key]) !== '') {
                return $row[$key];
            }
        }
        return $default;
    };
    $hasLegacyUsers = table_exists($db, 'utilisateurs');
    foreach ($db->all('SELECT * FROM immo_agents') as $agentRow) {
        $profile = $db->one('SELECT * FROM users WHERE id = ?', [$agentRow['user_id']]);
        if (!$profile && $hasLegacyUsers) {
            $profile = $db->one('SELECT * FROM utilisateurs WHERE id = ?', [$agentRow['user_id']]);
        }
        if (!$profile) {
            continue;
        }
        $fill = [];
        $mapping = [
            'first_name' => ['first_name', 'prenom', 'prénom'],
            'last_name' => ['last_name', 'nom'],
            'phone' => ['phone', 'telephone', 'téléphone', 'tel', 'mobile', 'whatsapp'],
            'email' => ['email', 'mail', 'courriel'],
            'photo_path' => ['avatar', 'photo_path', 'photo', 'image', 'photo_profil'],
            'status' => ['status', 'statut', 'etat'],
        ];
        foreach ($mapping as $column => $keys) {
            if (empty($agentRow[$column])) {
                $value = $pickAgentValue($profile, $keys);
                if ($value !== null && $value !== '') {
                    $fill[$column] = $value;
                }
            }
        }
        if ($fill) {
            $fill['updated_at'] = now();
            $db->update('immo_agents', $fill, 'id = ?', [$agentRow['id']]);
        }
    }

    // Les anciennes installations utilisaient quatre statuts CRM génériques.
    $db->exec("UPDATE prospects SET status = 'contacte' WHERE status = 'en_cours'");
    $db->exec("UPDATE prospects SET status = 'non_abouti' WHERE status = 'perdu'");
    foreach ($db->all('SELECT p.id, p.status, p.created_at FROM prospects p WHERE NOT EXISTS (SELECT 1 FROM prospect_status_history h WHERE h.prospect_id = p.id)') as $prospect) {
        $db->insert('prospect_status_history', [
            'prospect_id' => $prospect['id'], 'old_status' => null,
            'new_status' => $prospect['status'] ?: 'nouveau', 'user_id' => null,
            'note' => 'Historique initial importé.', 'created_at' => $prospect['created_at'] ?: now(),
        ]);
    }
    foreach ($db->all("SELECT id, reference FROM owners WHERE reference IS NULL OR reference = ''") as $row) {
        $db->update('owners', ['reference' => 'PROP-' . str_pad((string) $row['id'], 4, '0', STR_PAD_LEFT)], 'id = ?', [$row['id']]);
    }
    foreach ($db->all("SELECT id, reference FROM tenants WHERE reference IS NULL OR reference = ''") as $row) {
        $db->update('tenants', ['reference' => 'LOC-' . str_pad((string) $row['id'], 4, '0', STR_PAD_LEFT)], 'id = ?', [$row['id']]);
    }

    // ---- Cahier des charges CRM : colonnes additives (§9, §11) ----
    ensure_column($db, 'prospects', 'reference', 'VARCHAR(20)');
    ensure_column($db, 'prospect_notes', 'kind', "VARCHAR(20) DEFAULT 'note'");

    // §11 — identifiant PROP-00001 / LOC-00001 pour les dossiers déjà présents.
    foreach ($db->all("SELECT id, type FROM prospects WHERE reference IS NULL OR reference = ''") as $row) {
        $prefix = ($row['type'] ?? 'locataire') === 'proprietaire' ? 'PROP' : 'LOC';
        $db->update('prospects', ['reference' => $prefix . '-' . str_pad((string) $row['id'], 5, '0', STR_PAD_LEFT)], 'id = ?', [$row['id']]);
    }

    // §6 — unification des statuts historiques, tracée dans l'historique.
    foreach ([
        'rendez_vous_fixe' => 'rdv_prevu',
        'bien_a_visiter' => 'en_traitement',
        'proposition_envoyee' => 'negociation',
        'recherche_en_cours' => 'en_traitement',
        'biens_proposes' => 'negociation',
        'visite_programmee' => 'visite_prevue',
        'visite_effectuee' => 'negociation',
        'non_abouti' => 'perdu',
    ] as $oldStatus => $newStatus) {
        foreach ($db->all('SELECT id, created_at FROM prospects WHERE status = ?', [$oldStatus]) as $row) {
            $db->insert('prospect_status_history', [
                'prospect_id' => $row['id'], 'old_status' => $oldStatus, 'new_status' => $newStatus,
                'user_id' => null, 'note' => 'Statut unifié lors de la mise à niveau CRM.',
                'created_at' => $row['created_at'] ?: now(),
            ]);
        }
        $db->exec('UPDATE prospects SET status = ? WHERE status = ?', [$newStatus, $oldStatus]);
    }

    // §18 — normalisation des sources historiques.
    foreach ([
        'site_je_suis_proprietaire' => 'site_confier_mon_bien',
        'site_je_cherche_logement' => 'site_confier_ma_recherche',
        'agent' => 'bureau',
    ] as $oldSource => $newSource) {
        $db->exec('UPDATE prospects SET source = ? WHERE source = ?', [$newSource, $oldSource]);
    }

    $indexes = [
        'CREATE INDEX IF NOT EXISTS idx_prop_status ON properties(status)',
        'CREATE INDEX IF NOT EXISTS idx_prop_city ON properties(city)',
        'CREATE INDEX IF NOT EXISTS idx_prop_owner ON properties(owner_id)',
        'CREATE INDEX IF NOT EXISTS idx_prospect_phone ON prospects(phone)',
        'CREATE INDEX IF NOT EXISTS idx_prospect_agent ON prospects(agent_id)',
        'CREATE INDEX IF NOT EXISTS idx_prospect_type_status ON prospects(type, status)',
        'CREATE INDEX IF NOT EXISTS idx_prospect_history ON prospect_status_history(prospect_id, created_at)',
        'CREATE INDEX IF NOT EXISTS idx_prospect_ref ON prospects(reference)',
        'CREATE INDEX IF NOT EXISTS idx_proposal_prospect ON prospect_proposals(prospect_id)',
        'CREATE INDEX IF NOT EXISTS idx_proposal_property ON prospect_proposals(property_id)',
        'CREATE INDEX IF NOT EXISTS idx_contract_property_status ON contracts(property_id, status)',
        'CREATE INDEX IF NOT EXISTS idx_contract_tenant_status ON contracts(tenant_id, status)',
        'CREATE INDEX IF NOT EXISTS idx_rent_status ON rents(status)',
        'CREATE INDEX IF NOT EXISTS idx_rent_due ON rents(due_date)',
        'CREATE INDEX IF NOT EXISTS idx_notif_user ON app_notifications(user_id, is_read)',
        'CREATE INDEX IF NOT EXISTS idx_res_dates ON reservations(checkin, checkout)',
    ];
    // La colonne « offre » (vente / location_mensuelle / location_journaliere) est
    // indispensable à la vitrine : toutes les requêtes publiques la filtrent. Sans
    // elle (anciennes bases SQLite de démonstration), les requêtes échouent et
    // aucune propriété ni photo ne s'affiche sur le site. On la garantit donc sur
    // TOUS les pilotes, pas seulement MySQL.
    try {
        ensure_column($db, 'properties', 'offre', "VARCHAR(40) DEFAULT 'location_mensuelle'");
    } catch (Throwable $e) {
    }
    try {
        ensure_column($db, 'proprietes', 'offre', "VARCHAR(40) DEFAULT 'location_mensuelle'");
    } catch (Throwable $e) {
    }

    foreach ($indexes as $sql) {
        try {
            $db->pdo()->exec($sql);
        } catch (Throwable $e) {
            // index déjà là ou table aliasée
        }
    }
}

function db_ready(): bool
{
    try {
        $n = db()->val("SELECT COUNT(*) FROM users");
        return $n !== null;
    } catch (Throwable $e) {
        return false;
    }
}
