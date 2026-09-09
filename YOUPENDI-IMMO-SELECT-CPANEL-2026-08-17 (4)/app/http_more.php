<?php

function app_stay(): void
{
    require_auth();
    $items = db()->all('SELECT s.*, p.title, p.city, p.quartier FROM stay_listings s JOIN properties p ON p.id = s.property_id');
    $rows = [];
    foreach ($items as $s) {
        $rows[] = [e($s['name'] ?: $s['title']), e($s['city'] . ' · ' . $s['quartier']), (int) $s['guests'] . ' pers.', e(money($s['price_night']) . '/n'), status_badge($s['status']), a_link('app/reservations?stay=' . $s['id'], 'Réservations')];
    }
    list_page('Logements Immo Stay', ['Nom', 'Lieu', 'Capacité', 'Prix', 'Statut', ''], $rows);
}

function app_reservations(): void
{
    require_auth();
    $w = ['1=1'];
    $params = [];
    if ($s = str_input('status')) { $w[] = 'status = ?'; $params[] = $s; }
    if ($sid = int_input('stay')) { $w[] = 'stay_id = ?'; $params[] = $sid; }
    $items = db()->all('SELECT r.*, p.title FROM reservations r LEFT JOIN properties p ON p.id=r.property_id WHERE ' . implode(' AND ', $w) . ' ORDER BY r.checkin DESC', $params);
    $rows = [];
    foreach ($items as $r) {
        $act = a_link('app/reservations/' . $r['id'], 'Dossier');
        $rows[] = [e($r['number']), e($r['title']), e($r['guest_name']), e(dfr($r['checkin']) . ' → ' . dfr($r['checkout'])), (int) $r['nights'] . ' n', e(money($r['total'])), status_badge($r['status']), $act];
    }
    list_page('Réservations', ['N°', 'Logement', 'Voyageur', 'Dates', 'Nuits', 'Total', 'Statut', ''], $rows, [
        'filters' => ['status' => ['' => 'Statut', 'demande' => 'Demande', 'confirmee' => 'Confirmée', 'sejour' => 'Séjour', 'checkout' => 'Check-out', 'cloturee' => 'Clôturée', 'annulee' => 'Annulée']],
    ]);
}

function app_reservation_show(int $id): void
{
    require_auth();
    $r = db()->one('SELECT r.*, p.title FROM reservations r LEFT JOIN properties p ON p.id=r.property_id WHERE r.id = ?', [$id]);
    if (!$r) {
        abort(404, 'Réservation introuvable');
    }
    if (is_post()) {
        csrf_verify();
        $act = str_input('act');
        if ($act === 'confirmer') {
            update_existing('reservations', ['status' => 'confirmee'], 'id = ?', [$id]);
        } elseif ($act === 'annuler') {
            update_existing('reservations', ['status' => 'annulee'], 'id = ?', [$id]);
        } elseif ($act === 'checkin') {
            update_existing('reservations', ['status' => 'sejour', 'checkin_notes' => str_input('notes')], 'id = ?', [$id]);
        } elseif ($act === 'checkout') {
            update_existing('reservations', ['status' => 'checkout', 'checkout_notes' => str_input('notes')], 'id = ?', [$id]);
            insert_existing('housekeeping', [
                'reservation_id' => $id, 'stay_id' => $r['stay_id'], 'property_id' => $r['property_id'],
                'status' => 'a_faire', 'created_at' => now(),
            ]);
        } elseif ($act === 'payer') {
            update_existing('reservations', ['paid_amount' => $r['total']], 'id = ?', [$id]);
        }
        log_activity('reservation_' . $act, 'reservations', $id);
        flash('success', 'Réservation mise à jour.');
        redirect('app/reservations/' . $id);
    }
    ob_start(); ?>
    <div class="section-head"><div><p class="kicker gold"><?= e($r['number']) ?></p><h1 class="app-title"><?= e($r['title']) ?></h1></div><?= status_badge($r['status']) ?></div>
    <div class="panel">
      <p><?= e($r['guest_name']) ?> · <?= e($r['guest_phone']) ?> · <?= e($r['guest_email']) ?></p>
      <p><?= e(dfr($r['checkin'])) ?> → <?= e(dfr($r['checkout'])) ?> · <?= (int)$r['nights'] ?> nuits · <?= (int)$r['guests'] ?> pers.</p>
      <p>Total <?= e(money($r['total'])) ?> · Payé <?= e(money($r['paid_amount'])) ?></p>
      <p>Check-in : <?= e($r['checkin_notes'] ?: '—') ?><br>Check-out : <?= e($r['checkout_notes'] ?: '—') ?></p>
      <form method="post" class="row-actions"><?= csrf_field() ?>
        <input name="notes" placeholder="Observations" style="min-width:220px">
        <?php if ($r['status']==='demande'): ?><button class="btn btn-gold btn-sm" name="act" value="confirmer">Confirmer</button><?php endif; ?>
        <?php if (in_array($r['status'], ['demande','confirmee'], true)): ?><button class="btn btn-sm" name="act" value="checkin">Check-in</button><?php endif; ?>
        <?php if ($r['status']==='sejour'): ?><button class="btn btn-sm" name="act" value="checkout">Check-out + ménage</button><?php endif; ?>
        <?php if ((float)$r['paid_amount'] < (float)$r['total']): ?><button class="btn btn-sm" name="act" value="payer">Marquer payé</button><?php endif; ?>
        <?php if (!in_array($r['status'], ['annulee','cloturee'], true)): ?><button class="btn btn-danger btn-sm" name="act" value="annuler">Annuler</button><?php endif; ?>
      </form>
    </div>
    <?php
    view('app/raw', ['title' => $r['number'], 'html' => ob_get_clean()], 'app');
}

function app_housekeeping(): void
{
    require_auth();
    $items = db()->all('SELECT h.*, p.title FROM housekeeping h LEFT JOIN properties p ON p.id=h.property_id ORDER BY h.id DESC');
    if (is_post()) {
        csrf_verify();
        update_existing('housekeeping', ['status' => str_input('status'), 'assigned_to' => str_input('assigned_to'), 'done_at' => str_input('status') === 'termine' ? now() : null], 'id = ?', [int_input('id')]);
        if (str_input('status') === 'termine') {
            $h = db()->one('SELECT * FROM housekeeping WHERE id = ?', [int_input('id')]);
            update_existing('reservations', ['status' => 'cloturee'], 'id = ?', [$h['reservation_id']]);
        }
        flash('success', 'Housekeeping mis à jour.');
        redirect('app/housekeeping');
    }
    $rows = [];
    foreach ($items as $h) {
        $form = '<form method="post" class="row-actions">' . csrf_field() . '<input type="hidden" name="id" value="' . (int) $h['id'] . '"><input name="assigned_to" value="' . e($h['assigned_to']) . '" placeholder="Prestataire"><select name="status"><option value="a_faire">À faire</option><option value="en_cours">En cours</option><option value="termine">Terminé / inspecté</option></select><button class="btn btn-sm">OK</button></form>';
        $rows[] = [e($h['title']), e($h['assigned_to'] ?: '—'), status_badge($h['status']), e(dfr($h['created_at'])), $form];
    }
    list_page('Housekeeping', ['Logement', 'Affecté à', 'Statut', 'Créé', 'Maj'], $rows);
}

function app_maintenance(): void
{
    require_auth();
    $w = ['1=1'];
    $params = [];
    if ($s = str_input('status')) { $w[] = 'status = ?'; $params[] = $s; }
    $items = db()->all('SELECT m.*, p.title FROM maintenances m LEFT JOIN properties p ON p.id=m.property_id WHERE ' . implode(' AND ', $w) . ' ORDER BY m.id DESC', $params);
    $rows = [];
    foreach ($items as $m) {
        $rows[] = [e($m['title']), e($m['category']), e($m['urgency']), e(mb_strimwidth($m['description'], 0, 60, '…')), status_badge($m['status']), a_link('app/maintenance/' . $m['id'], 'Suivi')];
    }
    list_page('Maintenance', ['Bien', 'Catégorie', 'Urgence', 'Description', 'Statut', ''], $rows, [
        'create' => 'app/maintenance/nouveau',
        'filters' => ['status' => ['' => 'Statut', 'nouveau_inc' => 'Nouveau', 'a_analyser' => 'À analyser', 'intervention' => 'Intervention', 'resolu' => 'Résolu']],
    ]);
}

function app_maint_form(?int $id = null): void
{
    require_auth();
    $m = $id ? db()->one('SELECT * FROM maintenances WHERE id = ?', [$id]) : null;
    if (is_post()) {
        csrf_verify();
        $data = [
            'property_id' => int_input('property_id'),
            'category' => str_input('category'),
            'urgency' => str_input('urgency'),
            'description' => str_input('description'),
            'vendor_id' => int_input('vendor_id') ?: null,
            'cost' => float_input('cost'),
            'status' => str_input('status') ?: 'nouveau_inc',
            'updated_at' => now(),
        ];
        try {
            if ($p = handle_upload('photo', 'maintenance')) {
                $data['photo_path'] = $p;
            }
        } catch (Throwable $e) {
        }
        if ($id) {
            update_existing('maintenances', $data, 'id = ?', [$id]);
        } else {
            $data['created_by'] = user()['id'];
            $data['created_at'] = now();
            if (user()['role'] === 'tenant') {
                $tn = tenant_of();
                $data['tenant_id'] = $tn['id'] ?? null;
                $data['property_id'] = $tn['property_id'] ?? $data['property_id'];
            }
            $id = insert_existing('maintenances', $data);
        }
        flash('success', 'Incident enregistré.');
        redirect('app/maintenance');
    }
    $props = [];
    foreach (db()->all('SELECT id, title FROM properties') as $p) {
        $props[$p['id']] = $p['title'];
    }
    $vends = ['' => '—'];
    foreach (db()->all('SELECT id, name FROM vendors') as $v) {
        $vends[$v['id']] = $v['name'];
    }
    $st = ['nouveau_inc' => 'Nouveau', 'a_analyser' => 'À analyser', 'approuve' => 'Approuvé', 'affecte' => 'Prestataire affecté', 'intervention' => 'Intervention', 'resolu' => 'Résolu', 'cloture' => 'Clôturé'];
    ob_start();
    echo sel('property_id', $props, $m['property_id'] ?? '', 'Bien');
    echo sel('category', ['plomberie' => 'Plomberie', 'electricite' => 'Électricité', 'serrurerie' => 'Serrurerie', 'climatisation' => 'Climatisation', 'toiture' => 'Toiture', 'peinture' => 'Peinture', 'equipement' => 'Équipement', 'autre' => 'Autre'], $m['category'] ?? 'autre', 'Catégorie');
    echo sel('urgency', ['basse' => 'Basse', 'normale' => 'Normale', 'haute' => 'Haute', 'critique' => 'Critique'], $m['urgency'] ?? 'normale', 'Urgence');
    echo sel('status', $st, $m['status'] ?? 'nouveau_inc', 'Statut');
    echo sel('vendor_id', $vends, $m['vendor_id'] ?? '', 'Prestataire');
    echo fld('cost', 'Coût', 'number', $m['cost'] ?? 0);
    echo fld('description', 'Description', 'textarea', $m['description'] ?? '', 'full req');
    echo fld('photo', 'Photo', 'file', '', 'full');
    view('app/form', ['title' => $m ? 'Incident #' . $id : 'Nouvel incident', 'fields' => ob_get_clean(), 'back' => 'app/maintenance'], 'app');
}

function app_vendors(): void
{
    require_auth();
    if (is_post()) {
        csrf_verify();
        insert_existing('vendors', [
            'name' => str_input('name'), 'specialty' => str_input('specialty'),
            'phone' => str_input('phone'), 'email' => str_input('email'), 'city' => str_input('city'),
        ]);
        flash('success', 'Prestataire ajouté.');
        redirect('app/prestataires');
    }
    $items = db()->all('SELECT * FROM vendors ORDER BY name');
    $rows = [];
    foreach ($items as $v) {
        $rows[] = [e($v['name']), e($v['specialty']), e($v['phone']), e($v['city'])];
    }
    ob_start();
    echo fld('name', 'Nom', 'text', '', 'req');
    echo fld('specialty', 'Spécialité', 'text');
    echo fld('phone', 'Téléphone', 'tel');
    echo fld('email', 'E-mail', 'email');
    echo fld('city', 'Ville', 'text');
    view('app/form', [
        'title' => 'Prestataires',
        'fields' => ob_get_clean(),
        'submit' => 'Ajouter',
        'extra' => '<div class="table-wrap" style="margin-top:18px"><table><thead><tr><th>Nom</th><th>Spécialité</th><th>Tél</th><th>Ville</th></tr></thead><tbody>' .
            implode('', array_map(fn($r) => '<tr><td>' . implode('</td><td>', $r) . '</td></tr>', $rows)) . '</tbody></table></div>',
    ], 'app');
}

function app_expenses(): void
{
    require_auth();
    $items = db()->all('SELECT e.*, p.title FROM expenses e LEFT JOIN properties p ON p.id=e.property_id ORDER BY e.id DESC');
    $rows = [];
    foreach ($items as $e) {
        $act = ($e['status'] !== 'validee' && can('expenses.create')) ? post_btn('app/depenses/' . $e['id'] . '/valider', 'Valider') : '';
        $rows[] = [e($e['title']), e($e['category']), e(money($e['amount'])), e(dfr($e['expense_date'])), e($e['beneficiary']), status_badge($e['status']), $act];
    }
    list_page('Dépenses', ['Bien', 'Catégorie', 'Montant', 'Date', 'Bénéficiaire', 'Statut', ''], $rows, ['create' => 'app/depenses/nouveau']);
}

function app_expense_new(): void
{
    require_auth();
    if (is_post()) {
        csrf_verify();
        $path = null;
        try {
            $path = handle_upload('invoice', 'documents');
        } catch (Throwable $e) {
        }
        insert_existing('expenses', [
            'property_id' => int_input('property_id'), 'category' => str_input('category'),
            'amount' => float_input('amount'), 'expense_date' => str_input('expense_date') ?: today(),
            'description' => str_input('description'), 'beneficiary' => str_input('beneficiary'),
            'invoice_path' => $path, 'created_by' => user()['id'],
            'status' => user()['role'] === 'finance' || user()['role'] === 'admin' ? 'validee' : 'en_attente',
            'validated_by' => (user()['role'] === 'finance' || user()['role'] === 'admin') ? user()['id'] : null,
            'created_at' => now(),
        ]);
        flash('success', 'Dépense enregistrée.');
        redirect('app/depenses');
    }
    $props = [];
    foreach (db()->all('SELECT id, title FROM properties') as $p) {
        $props[$p['id']] = $p['title'];
    }
    ob_start();
    echo sel('property_id', $props, '', 'Bien');
    echo fld('category', 'Catégorie', 'text', '', 'req');
    echo fld('amount', 'Montant', 'number', '', 'req');
    echo fld('expense_date', 'Date', 'date', today());
    echo fld('beneficiary', 'Bénéficiaire', 'text');
    echo fld('description', 'Description', 'textarea', '', 'full');
    echo fld('invoice', 'Facture', 'file', '', 'full');
    view('app/form', ['title' => 'Nouvelle dépense', 'fields' => ob_get_clean(), 'back' => 'app/depenses'], 'app');
}

function app_expense_ok(int $id): void
{
    require_auth();
    csrf_verify();
    update_existing('expenses', ['status' => 'validee', 'validated_by' => user()['id']], 'id = ?', [$id]);
    flash('success', 'Dépense validée.');
    redirect('app/depenses');
}

/** Charge une fiche agent sans dépendre des colonnes ajoutées récemment. */
function admin_agent_record(int $id): ?array
{
    $row = compatibility_row_by_id(db(), 'immo_agents', $id, ['id', 'agent_id', 'id_agent']);
    if (!$row) {
        // Tolère les anciens liens qui envoyaient l'identifiant du compte au lieu de celui de l'agent.
        $columns = schema_columns(db(), 'immo_agents');
        foreach (['user_id', 'utilisateur_id', 'id_utilisateur'] as $linkColumn) {
            if (in_array($linkColumn, $columns, true)) {
                $row = db()->one("SELECT * FROM immo_agents WHERE `$linkColumn` = ? ORDER BY id DESC LIMIT 1", [$id]);
                if ($row) break;
            }
        }
    }
    if (!$row) {
        return null;
    }
    $row = array_change_key_case($row, CASE_LOWER);
    $recordId = (int) public_agent_value([$row], ['id', 'agent_id', 'id_agent'], $id);
    $userId = (int) public_agent_value([$row], ['user_id', 'utilisateur_id', 'id_utilisateur'], 0);
    $profile = compatibility_row_by_id(db(), 'users', $userId, ['id']);
    $legacyProfile = compatibility_row_by_id(db(), 'utilisateurs', $userId, ['id', 'id_utilisateur', 'user_id']);
    $normalized = normalize_public_agent([$row, $profile ?: [], $legacyProfile ?: []], 'immo_agents', $recordId);
    $teamId = (int) public_agent_value([$row], ['team_id', 'equipe_id', 'id_equipe'], 0);
    $team = compatibility_row_by_id(db(), 'teams', $teamId, ['id']);

    return array_merge($row, $normalized, [
        'id' => $recordId,
        'account_id' => (int) ($profile['id'] ?? 0),
        'legacy_account_id' => $legacyProfile ? $userId : 0,
        'role' => public_agent_text(public_agent_value([$profile ?: [], $legacyProfile ?: []], ['role', 'profil', 'type', 'fonction'], 'agent')),
        'status' => public_agent_text(public_agent_value([$row, $profile ?: [], $legacyProfile ?: []], ['status', 'statut', 'etat'], 'actif')),
        'type' => public_agent_text(public_agent_value([$row], ['type', 'type_agent'], 'immobilier')),
        'team' => public_agent_text(public_agent_value([$team ?: []], ['name', 'nom'], '')),
        'deleted_at' => public_agent_text(public_agent_value([$row], ['deleted_at', 'supprime_le'], '')),
    ]);
}

function app_agents(): void
{
    require_auth();
    $agentRows = qtry_all('SELECT * FROM immo_agents ORDER BY id DESC');
    $canonical = (bool) $agentRows;
    $items = [];
    foreach ($agentRows as $row) {
        $agent = admin_agent_record((int) ($row['id'] ?? 0));
        if (!$agent || $agent['deleted_at'] !== '' || public_agent_token($agent['status']) === 'supprime') {
            continue;
        }
        $items[] = $agent;
    }

    if (!$canonical) {
        $items = qtry_all("SELECT * FROM users WHERE role IN ('agent','supervisor','manager') ORDER BY id DESC");
    }
    if (!$items && !$canonical) {
        $items = qtry_all('SELECT * FROM agents ORDER BY id DESC');
    }

    $rows = [];
    foreach ($items as $agent) {
        $agent = array_change_key_case($agent, CASE_LOWER);
        $agentId = (int) ($agent['id'] ?? 0);
        $name = trim((string) ($agent['name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($agent['first_name'] ?? $agent['prenom'] ?? '') . ' ' . (string) ($agent['last_name'] ?? $agent['nom'] ?? '')) ?: '—';
        }
        $phone = public_agent_text(public_agent_value([$agent], ['phone', 'telephone', 'tel', 'mobile', 'whatsapp'], ''));
        $photo = agent_photo_url(public_agent_value([$agent], ['avatar', 'photo_path', 'photo', 'image'], null));
        $status = public_agent_text(public_agent_value([$agent], ['status', 'statut', 'etat'], 'actif')) ?: 'actif';
        $prospects = $canonical ? (int) qtry_val('SELECT COUNT(*) FROM prospects WHERE agent_id = ?', [$agentId]) : 0;
        $commissions = $canonical ? qtry_val('SELECT COALESCE(SUM(amount),0) FROM commissions WHERE agent_id = ?', [$agentId]) : 0;
        $nameCell = $canonical
            ? '<a href="' . e(base_url('app/agents/' . $agentId)) . '"><strong>' . e($name) . '</strong></a>'
            : '<strong>' . e($name) . '</strong>';
        $action = '';
        if ($canonical && user()['role'] === 'admin') {
            $action = '<div class="row-actions">'
                . '<a class="btn btn-outline btn-sm" href="' . e(base_url('app/agents/' . $agentId)) . '">Modifier / photo</a>'
                . post_btn_confirm(
                    'app/agents/' . $agentId . '/supprimer',
                    'Supprimer',
                    'Supprimer cet agent de l’équipe ? Son historique commercial sera conservé.',
                    'btn btn-danger btn-sm'
                )
                . '</div>';
        } elseif (!$canonical && user()['role'] === 'admin' && $agentId > 0 && table_exists(db(), 'users')) {
            $action = '<a class="btn btn-outline btn-sm" href="' . e(base_url('app/utilisateurs/' . $agentId)) . '">Modifier le compte</a>';
        }
        $rows[] = [
            '<img src="' . e($photo) . '" alt="Photo de ' . e($name) . '" style="width:58px;height:68px;object-fit:cover;border-radius:9px">',
            $nameCell,
            e($phone ?: '—'),
            e($agent['type'] ?? $agent['role'] ?? 'agent'),
            e(trim(($agent['province'] ?? '') . ' · ' . ($agent['city'] ?? $agent['ville'] ?? $agent['team'] ?? '—'), ' ·')),
            $prospects . ' prospect(s)',
            e(money($commissions)),
            status_badge($status),
            $action,
        ];
    }
    list_page('Agents', ['Photo', 'Nom complet', 'Téléphone', 'Type', 'Province / ville', 'CRM', 'Commissions', 'Statut', 'Actions'], $rows, [
        'create' => user()['role'] === 'admin' ? 'app/agents/nouveau' : null,
        'createLabel' => 'Ajouter un agent',
    ]);
}

function app_agent_form(?int $id = null): void
{
    require_role(['admin']);
    $agent = $id ? admin_agent_record($id) : null;
    if ($id && !$agent) {
        abort(404, 'Agent introuvable.');
    }
    if ($agent) {
        $id = (int) $agent['id'];
    }

    $message = '';
    if (is_post()) {
        csrf_verify();
        try {
            $email = mb_strtolower(str_input('email'));
            $firstName = str_input('first_name');
            $lastName = str_input('last_name');
            $phone = str_input('phone');
            $location = rdc_location(str_input('province'), str_input('city'));
            $city = $location['city'];
            $type = str_input('type') ?: 'immobilier';
            $specialties = str_input('specialties');
            $status = str_input('status') === 'inactif' ? 'inactif' : 'actif';
            if ($firstName === '' || $lastName === '' || $phone === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Le nom complet, le téléphone et un e-mail valide sont obligatoires.');
            }
            $accountId = (int) ($agent['account_id'] ?? 0);
            $duplicate = db()->one('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $accountId]);
            if ($duplicate) {
                throw new RuntimeException('Un autre compte utilise déjà cet e-mail.');
            }
            $password = (string) input('password', '');
            if (!$id && strlen($password) < 8) {
                throw new RuntimeException('Mot de passe : 8 caractères minimum.');
            }
            if ($id && $password !== '' && strlen($password) < 8) {
                throw new RuntimeException('Le nouveau mot de passe doit contenir au moins 8 caractères.');
            }

            $avatar = (string) ($agent['avatar'] ?? '');
            if ($uploadedPhoto = handle_upload('photo', 'agents')) {
                $avatar = $uploadedPhoto;
            }

            db()->begin();
            $userData = [
                'email' => $email, 'mail' => $email,
                'first_name' => $firstName, 'prenom' => $firstName,
                'last_name' => $lastName, 'nom' => $lastName,
                'phone' => $phone, 'telephone' => $phone, 'whatsapp' => $phone,
                'province' => $location['province'], 'city' => $city, 'ville' => $city,
                'avatar' => $avatar ?: null,
                'role' => $id ? ($agent['role'] ?? 'agent') : 'agent',
                'status' => $status, 'statut' => $status,
                'updated_at' => now(),
            ];
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $userData['password'] = $hash;
                $userData['mot_de_passe'] = $hash;
            }

            if ($id) {
                update_existing('users', $userData, 'id = ?', [$accountId]);
                update_existing('immo_agents', [
                    'type' => $type,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'phone' => $phone,
                    'email' => $email,
                    'province' => $location['province'],
                    'city' => $city,
                    'specialties' => $specialties,
                    'photo_path' => $avatar ?: null,
                    'status' => $status,
                    'notes' => $specialties,
                    'updated_at' => now(),
                ], 'id = ?', [$id]);
                $agentId = $id;
                log_activity('agent_modifie', 'immo_agents', $agentId);
            } else {
                $userData['password'] = $userData['password'] ?? password_hash($password, PASSWORD_DEFAULT);
                $userData['created_at'] = now();
                $accountId = insert_existing('users', $userData);
                $agentId = insert_existing('immo_agents', [
                    'user_id' => $accountId,
                    'type' => $type,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'phone' => $phone,
                    'email' => $email,
                    'province' => $location['province'],
                    'city' => $city,
                    'specialties' => $specialties,
                    'photo_path' => $avatar ?: null,
                    'status' => $status,
                    'notes' => $specialties,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                log_activity('agent_cree', 'immo_agents', $agentId);
            }
            db()->commit();
            flash('success', $id ? 'Agent et photo mis à jour.' : 'Agent enregistré avec sa photo.');
            redirect('app/agents/' . $agentId);
        } catch (Throwable $e) {
            if (db()->pdo()->inTransaction()) {
                db()->rollBack();
            }
            $message = $e->getMessage();
        }
    }

    ob_start();
    if ($message) {
        echo '<div class="flash flash-error" style="grid-column:1/-1">' . e($message) . '</div>';
    }
    if ($agent) {
        echo '<div class="full" style="display:flex;align-items:center;gap:14px;margin-bottom:8px">';
        echo '<img src="' . e(agent_photo_url($agent['avatar'] ?? null)) . '" alt="Photo actuelle" style="width:96px;height:112px;object-fit:cover;border-radius:12px">';
        echo '<div><strong>Photo publique actuelle</strong><br><span class="muted">Téléversez une nouvelle image pour la remplacer.</span></div></div>';
    }
    echo fld('first_name', 'Prénom', 'text', $agent['first_name'] ?? '', 'req');
    echo fld('last_name', 'Nom', 'text', $agent['last_name'] ?? '', 'req');
    echo fld('email', 'E-mail', 'email', $agent['email'] ?? '', 'req');
    echo fld('phone', 'Numéro de téléphone / WhatsApp', 'tel', $agent['phone'] ?? '', 'req');
    $agentCity = old('city', $agent['city'] ?? '');
    $agentProvince = old('province', $agent['province'] ?? province_for_city($agentCity));
    echo sel('province', ['' => 'Sélectionner une province'] + provinces_rdc(), $agentProvince, 'Province');
    echo sel('city', ['' => 'Sélectionner une ville'] + array_combine(cities(), cities()), $agentCity, 'Ville');
    echo sel('type', [
        'immobilier' => 'Agent immobilier',
        'principal' => 'Agent principal',
        'regional' => 'Agent régional',
        'senior' => 'Superviseur / senior',
    ], $agent['type'] ?? 'immobilier', 'Type');
    echo sel('status', ['actif' => 'Actif et visible', 'inactif' => 'Inactif / masqué'], $agent['status'] ?? 'actif', 'Affichage public');
    echo fld('specialties', 'Spécialités', 'text', $agent['specialties'] ?? $agent['notes'] ?? '');
    echo fld('photo', 'Photo de l’agent (JPG, PNG ou WebP)', 'file', '', 'full');
    echo fld('password', $agent ? 'Nouveau mot de passe (laisser vide pour conserver)' : 'Mot de passe (8 caractères minimum)', 'password', '', $agent ? '' : 'req');
    $fields = ob_get_clean();
    $extra = '<script>const photo=document.querySelector("[name=photo]");if(photo)photo.setAttribute("accept","image/jpeg,image/png,image/webp");</script>';
    if ($agent) {
        $extra .= '<div class="panel" style="margin-top:18px;max-width:920px;border-color:#ef4444">'
            . '<h3>Supprimer cet agent</h3>'
            . '<p class="muted">L’agent sera retiré de l’équipe et du site public. Ses prospects, contrats, commissions et activités historiques seront conservés.</p>'
            . post_btn_confirm(
                'app/agents/' . $id . '/supprimer',
                'Supprimer l’agent',
                'Confirmer la suppression de cet agent ? Son historique sera conservé.',
                'btn btn-danger'
            )
            . '</div>';
    }
    view('app/form', [
        'title' => $agent ? 'Modifier l’agent · ' . full_name($agent) : 'Nouvel agent',
        'kicker' => 'Équipe commerciale',
        'fields' => $fields,
        'submit' => $agent ? 'Mettre à jour l’agent' : 'Enregistrer l’agent',
        'back' => 'app/agents',
        'extra' => $extra,
    ], 'app');
}

/**
 * Suppression sécurisée : l'agent disparaît des écrans actifs, mais ses liens CRM restent lisibles.
 */
function app_agent_delete(int $id): void
{
    require_role(['admin']);
    csrf_verify();
    $agent = admin_agent_record($id);
    if (!$agent) {
        abort(404, 'Agent introuvable.');
    }
    $id = (int) $agent['id'];

    $accountId = (int) ($agent['account_id'] ?? 0);
    if ($accountId > 0 && $accountId === (int) (user()['id'] ?? 0)) {
        flash('error', 'Vous ne pouvez pas supprimer votre propre compte agent pendant que vous êtes connecté.');
        redirect('app/agents/' . $id);
    }

    $linked = 0;
    foreach (['prospects', 'visits', 'contracts', 'commissions', 'tasks'] as $table) {
        $linked += (int) qtry_val("SELECT COUNT(*) FROM $table WHERE agent_id = ?", [$id], 0);
    }
    foreach (['agent_apporteur_id', 'agent_commercial_id', 'manager_id'] as $column) {
        $linked += (int) qtry_val("SELECT COUNT(*) FROM properties WHERE $column = ?", [$id], 0);
    }

    db()->begin();
    try {
        update_existing('immo_agents', [
            'status' => 'supprime',
            'deleted_at' => now(),
            'updated_at' => now(),
        ], 'id = ?', [$id]);

        // Un compte purement agent est désactivé. Les comptes manager/superviseur restent utilisables.
        if ($accountId > 0 && public_agent_token($agent['role'] ?? '') === 'agent') {
            update_existing('users', ['status' => 'inactif', 'updated_at' => now()], 'id = ?', [$accountId]);
        }
        log_activity('agent_supprime', 'immo_agents', $id, [
            'status' => $agent['status'] ?? null,
        ], [
            'status' => 'supprime',
            'liens_historiques' => $linked,
        ]);
        db()->commit();
    } catch (Throwable $e) {
        if (db()->pdo()->inTransaction()) {
            db()->rollBack();
        }
        flash('error', 'Suppression impossible : ' . $e->getMessage());
        redirect('app/agents/' . $id);
    }

    flash('success', $linked > 0
        ? 'Agent supprimé de l’équipe. Ses éléments historiques ont été conservés.'
        : 'Agent supprimé de l’équipe et du site public.');
    redirect('app/agents');
}


function app_tasks(): void
{
    require_auth();
    $w = ['1=1'];
    $params = [];
    if (user()['role'] === 'agent') {
        $a = agent_of();
        $w[] = 'agent_id = ?';
        $params[] = $a['id'] ?? 0;
    }
    if (is_post()) {
        csrf_verify();
        if (int_input('id')) {
            update_existing('tasks', ['status' => 'faite'], 'id = ?', [int_input('id')]);
        } else {
            $ag = agent_of();
            insert_existing('tasks', [
                'agent_id' => $ag['id'] ?? int_input('agent_id') ?: null,
                'title' => str_input('title'),
                'due_at' => str_replace('T', ' ', str_input('due_at')),
                'status' => 'ouverte',
                'created_at' => now(),
            ]);
        }
        redirect('app/taches');
    }
    $items = db()->all('SELECT * FROM tasks WHERE ' . implode(' AND ', $w) . ' ORDER BY due_at', $params);
    $rows = [];
    foreach ($items as $t) {
        $rows[] = [e($t['title']), e(dfr($t['due_at'], 'd/m/Y H:i')), status_badge($t['status'] === 'ouverte' ? 'a_payer' : 'paye'), $t['status'] === 'ouverte' ? post_btn('app/taches', 'Terminer') . '<input type="hidden" name="id" value="' . $t['id'] . '">' : ''];
    }
    // fix terminate button - simpler
    $rows = [];
    foreach ($items as $t) {
        $btn = $t['status'] === 'ouverte'
            ? '<form method="post">' . csrf_field() . '<input type="hidden" name="id" value="' . (int) $t['id'] . '"><button class="btn btn-sm">Terminer</button></form>'
            : '—';
        $rows[] = [e($t['title']), e(dfr($t['due_at'], 'd/m/Y H:i')), e($t['status']), $btn];
    }
    ob_start(); ?>
    <form method="post" class="panel" style="margin-bottom:16px"><?= csrf_field() ?>
      <div class="form-grid">
        <?= fld('title','Nouvelle tâche','text','','req') ?>
        <?= fld('due_at','Échéance','datetime-local') ?>
      </div>
      <button class="btn btn-gold btn-sm">Ajouter</button>
    </form>
    <?php
    $extra = ob_get_clean();
    view('app/list', ['title' => 'Tâches & agenda', 'cols' => ['Tâche', 'Échéance', 'Statut', ''], 'rows' => $rows, 'pg' => ['pages' => 1], 'extraTop' => $extra], 'app');
}

function app_documents(): void
{
    require_auth();
    if (is_post()) {
        csrf_verify();
        try {
            $path = handle_upload('file', 'documents');
            insert_existing('documents', [
                'entity' => str_input('entity'), 'entity_id' => int_input('entity_id'),
                'title' => str_input('title'), 'path' => $path, 'uploaded_by' => user()['id'], 'created_at' => now(),
            ]);
            flash('success', 'Document enregistré.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('app/documents');
    }
    $items = db()->all('SELECT * FROM documents ORDER BY id DESC');
    $rows = [];
    foreach ($items as $d) {
        $url = str_starts_with((string) $d['path'], '../') ? photo_url($d['path']) : upload_url($d['path']);
        $rows[] = [e($d['title']), e($d['entity'] . ' #' . $d['entity_id']), e(dfr($d['created_at'])), '<a href="' . e($url) . '" target="_blank">Ouvrir</a>'];
    }
    ob_start();
    echo fld('title', 'Titre', 'text', '', 'req');
    echo sel('entity', ['properties' => 'Bien', 'owners' => 'Propriétaire', 'tenants' => 'Locataire', 'contracts' => 'Contrat'], 'properties', 'Entité');
    echo fld('entity_id', 'ID entité', 'number', '1');
    echo fld('file', 'Fichier', 'file', '', 'req');
    view('app/form', ['title' => 'Documents', 'fields' => ob_get_clean(), 'submit' => 'Téléverser',
        'extra' => '<div class="table-wrap" style="margin-top:16px"><table><thead><tr><th>Titre</th><th>Lié à</th><th>Date</th><th></th></tr></thead><tbody>' .
        implode('', array_map(fn($r) => '<tr><td>' . implode('</td><td>', $r) . '</td></tr>', $rows)) . '</tbody></table></div>'], 'app');
}

function app_users(): void
{
    require_role(['admin']);
    $items = db()->all('SELECT id, email, first_name, last_name, role, status, last_login FROM users ORDER BY id');
    $rows = [];
    foreach ($items as $u) {
        $rows[] = [e(full_name($u)), e($u['email']), e(role_label($u['role'])), status_badge($u['status']), e(dfr($u['last_login'], 'd/m/Y H:i')), a_link('app/utilisateurs/' . $u['id'], 'Modifier')];
    }
    list_page('Utilisateurs', ['Nom', 'E-mail', 'Rôle', 'Statut', 'Dernière connexion', ''], $rows, ['create' => 'app/utilisateurs/nouveau']);
}

function app_user_form(?int $id = null): void
{
    require_role(['admin']);
    $u = $id ? db()->one('SELECT * FROM users WHERE id = ?', [$id]) : null;
    if (is_post()) {
        csrf_verify();
        $data = [
            'first_name' => str_input('first_name'), 'last_name' => str_input('last_name'),
            'email' => mb_strtolower(str_input('email')),
            'phone' => str_input('phone'), 'whatsapp' => str_input('whatsapp'),
            'role' => str_input('role'), 'status' => str_input('status'),
            'updated_at' => now(),
        ];
        if (str_input('password')) {
            $data['password'] = password_hash(str_input('password'), PASSWORD_DEFAULT);
        }
        if ($id) {
            if (empty($data['password'])) {
                unset($data['password']);
            }
            update_existing('users', $data, 'id = ?', [$id]);
        } else {
            $data['password'] = password_hash(str_input('password') ?: 'ChangeMe@2026', PASSWORD_DEFAULT);
            $data['created_at'] = now();
            $id = insert_existing('users', $data);
            if (in_array($data['role'], ['agent', 'supervisor', 'manager'], true)) {
                insert_existing('immo_agents', ['user_id' => $id, 'type' => $data['role'] === 'supervisor' ? 'senior' : 'immobilier', 'created_at' => now()]);
            }
            if ($data['role'] === 'owner') {
                insert_existing('owners', ['user_id' => $id, 'first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'email' => $data['email'], 'phone' => $data['phone'], 'created_at' => now()]);
            }
            if ($data['role'] === 'tenant') {
                insert_existing('tenants', ['user_id' => $id, 'first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'email' => $data['email'], 'phone' => $data['phone'], 'created_at' => now()]);
            }
        }
        flash('success', 'Utilisateur enregistré.');
        redirect('app/utilisateurs');
    }
    ob_start();
    echo fld('first_name', 'Prénom', 'text', $u['first_name'] ?? '', 'req');
    echo fld('last_name', 'Nom', 'text', $u['last_name'] ?? '', 'req');
    echo fld('email', 'E-mail', 'email', $u['email'] ?? '', 'req');
    echo fld('phone', 'Téléphone', 'tel', $u['phone'] ?? '');
    echo fld('whatsapp', 'WhatsApp', 'tel', $u['whatsapp'] ?? '');
    echo sel('role', roles_catalog(), $u['role'] ?? 'agent', 'Rôle');
    echo sel('status', ['actif' => 'Actif', 'suspendu' => 'Suspendu', 'inactif' => 'Inactif'], $u['status'] ?? 'actif', 'Statut');
    echo fld('password', $u ? 'Nouveau mot de passe (vide = inchangé)' : 'Mot de passe', 'password', '');
    view('app/form', ['title' => $u ? 'Modifier l’utilisateur' : 'Nouvel utilisateur', 'fields' => ob_get_clean(), 'back' => 'app/utilisateurs'], 'app');
}

function app_settings(): void
{
    require_role(['admin']);
    if (is_post()) {
        csrf_verify();
        foreach (['company', 'email', 'phone', 'whatsapp', 'address', 'about', 'commission_mgmt', 'commission_apporteur', 'commission_commercial', 'commission_supervisor', 'currency'] as $k) {
            set_setting($k, str_input($k));
        }
        flash('success', 'Paramètres enregistrés. Les taux ne sont jamais figés dans le code.');
        redirect('app/parametres');
    }
    ob_start();
    echo fld('company', 'Raison sociale', 'text', setting('company'), 'full');
    echo fld('email', 'E-mail', 'email', setting('email'));
    echo fld('phone', 'Téléphone', 'text', setting('phone'));
    echo fld('whatsapp', 'WhatsApp (indicatif sans +)', 'text', setting('whatsapp'));
    echo fld('address', 'Adresse', 'text', setting('address'), 'full');
    echo fld('about', 'Présentation', 'textarea', setting('about'), 'full');
    echo fld('currency', 'Devise', 'text', setting('currency', 'USD'));
    echo fld('commission_mgmt', 'Commission gestion YOUPENDI %', 'number', setting('commission_mgmt', 10));
    echo fld('commission_apporteur', 'Part agent apporteur %', 'number', setting('commission_apporteur', 30));
    echo fld('commission_commercial', 'Part agent commercial %', 'number', setting('commission_commercial', 40));
    echo fld('commission_supervisor', 'Part superviseur %', 'number', setting('commission_supervisor', 10));
    view('app/form', ['title' => 'Paramètres', 'fields' => ob_get_clean()], 'app');
}

function clear_uploaded_business_files(): void
{
    $root = rtrim((string) config('upload.dir'), '/');
    if (!is_dir($root)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isFile()) {
            if ($item->getFilename() !== '.htaccess') {
                @unlink($item->getPathname());
            }
        } elseif ($item->isDir()) {
            @rmdir($item->getPathname());
        }
    }
}

function app_reset_platform(): void
{
    $admin = require_role(['admin']);
    if (is_post()) {
        csrf_verify();
        $confirmation = mb_strtoupper(trim(str_input('confirmation')));
        if ($confirmation !== 'REMISE A ZERO' && $confirmation !== 'REMISE À ZÉRO') {
            flash('error', 'Saisissez exactement REMISE A ZERO pour confirmer.');
            redirect('app/remise-a-zero');
        }
        $current = db()->one('SELECT * FROM users WHERE id = ?', [$admin['id']]);
        if (!$current || !password_verify((string) input('password', ''), (string) ($current['password'] ?? ''))) {
            flash('error', 'Mot de passe administrateur incorrect.');
            redirect('app/remise-a-zero');
        }

        $tables = [
            'property_photos', 'prospect_notes', 'prospect_status_history', 'payments', 'receipts',
            'rents', 'commissions', 'contracts', 'visits', 'tenants', 'properties', 'owners',
            'prospects', 'expenses', 'maintenances', 'payouts', 'reservations', 'housekeeping',
            'stay_listings', 'documents', 'tasks', 'teams', 'immo_agents', 'vendors', 'favorites',
            'contact_messages', 'messages', 'app_notifications', 'activity_logs', 'login_attempts',
            'password_resets',
        ];
        $legacyTables = [
            'agents', 'proprietes', 'propriétés', 'propriete_images', 'propriete_photos',
            'visites', 'depenses', 'paiements', 'demandes', 'commentaires', 'utilisateurs',
        ];

        db()->begin();
        try {
            foreach (array_merge($tables, $legacyTables) as $table) {
                if (table_exists(db(), $table)) {
                    $safe = str_replace('`', '', $table);
                    db()->pdo()->exec("DELETE FROM `$safe`");
                }
            }
            db()->q('DELETE FROM users WHERE id <> ?', [(int) $admin['id']]);
            update_existing('users', ['status' => 'actif', 'updated_at' => now()], 'id = ?', [(int) $admin['id']]);
            db()->commit();
        } catch (Throwable $e) {
            if (db()->pdo()->inTransaction()) {
                db()->rollBack();
            }
            flash('error', 'Remise à zéro annulée : ' . $e->getMessage());
            redirect('app/remise-a-zero');
        }

        clear_uploaded_business_files();
        @file_put_contents(
            dirname(__DIR__) . '/storage/reset-audit.log',
            date('c') . ' Remise à zéro métier par administrateur #' . (int) $admin['id'] . "\n",
            FILE_APPEND
        );
        $_SESSION['user'] = db()->one('SELECT * FROM users WHERE id = ?', [(int) $admin['id']]);
        unset($_SESSION['user']['password']);
        flash('success', 'Plateforme remise à zéro. Seul votre compte administrateur et la configuration ont été conservés.');
        redirect('app');
    }

    ob_start();
    echo '<div class="flash flash-error full"><strong>Action irréversible.</strong><br>Cette opération efface les prospects, agents, propriétaires, biens, locataires, visites, contrats, loyers, paiements, commissions, rapports, messages et fichiers téléversés. Seuls ce compte administrateur, les paramètres et la configuration MySQL seront conservés.</div>';
    echo fld('confirmation', 'Saisissez REMISE A ZERO', 'text', '', 'req full');
    echo fld('password', 'Mot de passe administrateur', 'password', '', 'req full');
    view('app/form', [
        'title' => 'Remise à zéro de la plateforme',
        'kicker' => 'Administration · Zone dangereuse',
        'fields' => ob_get_clean(),
        'submit' => 'Effacer toutes les données métier',
        'back' => 'app/parametres',
    ], 'app');
}

function app_logs(): void
{
    require_role(['admin', 'finance']);
    $items = db()->all('SELECT l.*, u.first_name, u.last_name FROM activity_logs l LEFT JOIN users u ON u.id=l.user_id ORDER BY l.id DESC LIMIT 200');
    $rows = [];
    foreach ($items as $l) {
        $rows[] = [e(dfr($l['created_at'], 'd/m/Y H:i')), e(full_name($l) ?: 'Système'), e($l['action']), e($l['entity'] . ' #' . $l['entity_id']), e($l['ip'])];
    }
    list_page('Journal d’activité', ['Date', 'Utilisateur', 'Action', 'Cible', 'IP'], $rows);
}

function app_notifications(): void
{
    $u = require_auth();
    if (is_post()) {
        csrf_verify();
        db()->exec('UPDATE app_notifications SET is_read = 1 WHERE user_id = ?', [$u['id']]);
        redirect('app/notifications');
    }
    $items = db()->all('SELECT * FROM app_notifications WHERE user_id = ? ORDER BY id DESC LIMIT 50', [$u['id']]);
    $rows = [];
    foreach ($items as $n) {
        $rows[] = [e(dfr($n['created_at'], 'd/m H:i')), ($n['is_read'] ? '' : '● ') . e($n['title']), e($n['body'])];
    }
    list_page('Notifications', ['Quand', 'Titre', 'Détail'], $rows);
}

function app_messages(): void
{
    $u = require_auth();
    $peer = int_input('avec');
    if (!$peer) {
        $admin = db()->one("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1");
        $peer = (int) ($admin['id'] ?? 0);
    }
    if (is_post()) {
        csrf_verify();
        insert_existing('messages', [
            'thread' => min($u['id'], $peer) . '-' . max($u['id'], $peer),
            'sender_id' => $u['id'], 'recipient_id' => $peer,
            'body' => str_input('body'), 'created_at' => now(),
        ]);
        redirect('app/messages?avec=' . $peer);
    }
    $msgs = db()->all('SELECT * FROM messages WHERE (sender_id = ? AND recipient_id = ?) OR (sender_id = ? AND recipient_id = ?) ORDER BY id', [$u['id'], $peer, $peer, $u['id']]);
    $people = db()->all('SELECT id, first_name, last_name, role FROM users WHERE id != ? ORDER BY first_name', [$u['id']]);
    ob_start(); ?>
    <div class="section-head"><h1 class="app-title">Messagerie</h1></div>
    <div class="prop-grid">
      <div class="panel">
        <?php foreach ($people as $p): ?>
          <div><a href="?avec=<?= (int)$p['id'] ?>"><?= e(full_name($p)) ?></a> <span class="muted"><?= e(role_label($p['role'])) ?></span></div>
        <?php endforeach; ?>
      </div>
      <div>
        <div class="msg-list">
          <?php foreach ($msgs as $m): ?>
            <div class="msg <?= $m['sender_id']==$u['id']?'me':'' ?>"><strong><?= $m['sender_id']==$u['id']?'Moi':'Eux' ?></strong><br><?= nl2br(e($m['body'])) ?><div class="muted"><?= e(dfr($m['created_at'], 'd/m H:i')) ?></div></div>
          <?php endforeach; ?>
        </div>
        <form method="post" style="margin-top:12px"><?= csrf_field() ?><textarea name="body" required placeholder="Votre message"></textarea><button class="btn btn-gold" style="margin-top:8px">Envoyer</button></form>
      </div>
    </div>
    <?php
    view('app/raw', ['title' => 'Messages', 'html' => ob_get_clean()], 'app');
}

function app_reports(): void
{
    require_auth();
    $rev = (float) db()->val("SELECT COALESCE(SUM(paid_amount),0) FROM rents WHERE status IN ('paye','partiel')");
    $imp = (float) db()->val("SELECT COALESCE(SUM(amount-paid_amount),0) FROM rents WHERE status IN ('en_retard','a_payer','partiel')");
    $com = (float) db()->val("SELECT COALESCE(SUM(amount),0) FROM commissions WHERE type = 'gestion_youpendi'");
    $occ = (int) db()->val("SELECT COUNT(*) FROM properties WHERE status = 'occupe' AND is_short_stay = 0");
    $tot = max(1, (int) db()->val('SELECT COUNT(*) FROM properties WHERE is_short_stay = 0'));
    $agents = db()->all("SELECT a.id, u.first_name, u.last_name,
        (SELECT COUNT(*) FROM prospects WHERE agent_id=a.id) prospects,
        (SELECT COUNT(*) FROM visits WHERE agent_id=a.id AND status='realisee') visites,
        (SELECT COUNT(*) FROM properties WHERE agent_apporteur_id=a.id) biens,
        (SELECT COALESCE(SUM(amount),0) FROM commissions WHERE agent_id=a.id) comms
        FROM immo_agents a JOIN users u ON u.id=a.user_id");
    if (str_input('export') === 'csv') {
        csv_download('performance-agents.csv', ['Agent', 'Prospects', 'Visites', 'Biens', 'Commissions'], array_map(fn($a) => [full_name($a), $a['prospects'], $a['visites'], $a['biens'], $a['comms']], $agents));
    }
    ob_start(); ?>
    <div class="section-head"><h1 class="app-title">Rapports</h1><a class="btn btn-outline btn-sm" href="?export=csv">Export agents CSV</a></div>
    <div class="kpi">
      <div class="stat"><b><?= e(money($rev)) ?></b>Loyers encaissés</div>
      <div class="stat"><b><?= e(money($imp)) ?></b>Encours / impayés</div>
      <div class="stat"><b><?= e(money($com)) ?></b>Commissions YOUPENDI</div>
      <div class="stat"><b><?= e(round(100*$occ/$tot)) ?>%</b>Taux d’occupation</div>
    </div>
    <div class="table-wrap" style="margin-top:18px">
      <table>
        <thead><tr><th>Agent</th><th>Prospects</th><th>Visites réussies</th><th>Biens apportés</th><th>Commissions</th><th>Conversion</th></tr></thead>
        <tbody>
        <?php foreach ($agents as $a): $conv = $a['prospects'] ? round(100 * $a['biens'] / $a['prospects']) : 0; ?>
          <tr><td><?= e(full_name($a)) ?></td><td><?= (int)$a['prospects'] ?></td><td><?= (int)$a['visites'] ?></td><td><?= (int)$a['biens'] ?></td><td><?= e(money($a['comms'])) ?></td><td><?= $conv ?>%</td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
    view('app/raw', ['title' => 'Rapports', 'html' => ob_get_clean()], 'app');
}

function portal_safe_all(string $sql, array $params = []): array
{
    try {
        return db()->all($sql, $params);
    } catch (Throwable $e) {
        return [];
    }
}

function portal_safe_val(string $sql, array $params = [])
{
    try {
        return db()->val($sql, $params);
    } catch (Throwable $e) {
        return 0;
    }
}

function portal_owner(): void
{
    try {
    $u = require_role(['owner', 'admin']);
    $o = null;
    try {
        $o = owner_of($u) ?: db()->one('SELECT * FROM owners ORDER BY id LIMIT 1');
    } catch (Throwable $e) {
        $o = null;
    }
    if (!$o) {
        view('app/raw', ['title' => 'Espace propriétaire', 'html' => '<h1 class="app-title">Espace propriétaire</h1><p>Aucun dossier propriétaire n’est encore lié à ce compte. Un administrateur peut vous associer des biens.</p>'], 'app');
        return;
    }
    $oid = (int) ($o['id'] ?? 0);
    $biens = portal_safe_all('SELECT * FROM properties WHERE owner_id = ?', [$oid]);
    $ids = array_values(array_filter(array_column($biens, 'id'))) ?: [0];
    $in = implode(',', array_fill(0, count($ids), '?'));
    $nVente = 0;
    $nLoc = 0;
    foreach ($biens as $bp) {
        if (property_offre($bp) === 'vente') {
            $nVente++;
        } else {
            $nLoc++;
        }
    }
    $kpis = [
        ['l' => 'Biens', 'v' => count($biens)],
        ['l' => 'En vente', 'v' => $nVente],
        ['l' => 'En location', 'v' => $nLoc],
        ['l' => 'Occupés', 'v' => portal_safe_val("SELECT COUNT(*) FROM properties WHERE owner_id=? AND status='occupe'", [$oid])],
        ['l' => 'Loyers encaissés', 'v' => money(portal_safe_val('SELECT COALESCE(SUM(paid_amount),0) FROM rents WHERE owner_id=?', [$oid]))],
        ['l' => 'Impayés', 'v' => money(portal_safe_val("SELECT COALESCE(SUM(amount-paid_amount),0) FROM rents WHERE owner_id=? AND status IN ('en_retard','a_payer')", [$oid]))],
        ['l' => 'Commission YOUPENDI', 'v' => money(portal_safe_val("SELECT COALESCE(SUM(amount),0) FROM commissions WHERE type='gestion_youpendi' AND property_id IN ($in)", $ids))],
        ['l' => 'Dépenses', 'v' => money(portal_safe_val("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE property_id IN ($in)", $ids))],
        ['l' => 'Net reversé', 'v' => money(portal_safe_val("SELECT COALESCE(SUM(net_amount),0) FROM payouts WHERE owner_id=? AND status='paye'", [$oid]))],
        ['l' => 'Incidents ouverts', 'v' => portal_safe_val("SELECT COUNT(*) FROM maintenances WHERE property_id IN ($in) AND status NOT IN ('resolu','cloture')", $ids)],
    ];
    $logs = portal_safe_all('SELECT * FROM activity_logs ORDER BY id DESC LIMIT 6');
    $todo = [
        ['espace/proprietaire/biens', 'Voir mes biens'],
        ['espace/proprietaire/reversements', 'Reversements'],
        ['espace/proprietaire/rapports', 'Rapport mensuel'],
    ];
    $prenom = $o['first_name'] ?? $o['prenom'] ?? $u['first_name'] ?? '';
    view('owner/dashboard', [
        'title' => 'Espace bailleur',
        'nav' => '',
        'name' => $prenom,
        'kpis' => $kpis,
        'biens' => $biens,
        'offres' => owner_offres(),
    ], 'public');
    } catch (Throwable $e) {
        if (!headers_sent()) {
            http_response_code(200);
        }
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Propriétaire</title></head><body style="font-family:sans-serif;padding:24px">';
        echo '<h1>Espace propriétaire</h1><p>Le tableau de bord se prépare. Détail : <code>' . htmlspecialchars($e->getMessage()) . '</code></p>';
        echo '<p><a href="/app">Retour admin</a> · <a href="/">Accueil</a></p></body></html>';
    }
}

function portal_owner_list(string $what): void
{
    $u = require_role(['owner', 'admin']);
    $o = owner_of($u);
    $oid = $o['id'] ?? 0;
    if ($what === 'biens') {
        $items = db()->all('SELECT * FROM properties WHERE owner_id = ?', [$oid]);
        $rows = [];
        foreach ($items as $p) {
            $rows[] = [e($p['reference'] ?? ''), e($p['title'] ?? $p['titre'] ?? ''), e(offre_label(property_offre($p))), status_badge($p['status'] ?? $p['statut'] ?? ''), e(money($p['rent'] ?? $p['loyer'] ?? $p['prix'] ?? 0))];
        }
        list_page('Mes biens', ['Réf', 'Titre', 'Offre', 'Statut', 'Prix'], $rows);
        return;
    }
    if ($what === 'locataires') {
        $items = db()->all('SELECT t.*, p.title FROM tenants t JOIN properties p ON p.id=t.property_id WHERE p.owner_id=?', [$oid]);
        $rows = [];
        foreach ($items as $t) {
            $rows[] = [e(full_name($t)), e($t['title']), e(dfr($t['entry_date']))];
        }
        list_page('Mes locataires', ['Nom', 'Bien', 'Entrée'], $rows);
        return;
    }
    if ($what === 'revenus') {
        $items = db()->all('SELECT r.*, p.title FROM rents r JOIN properties p ON p.id=r.property_id WHERE r.owner_id=? ORDER BY due_date DESC', [$oid]);
        $rows = [];
        foreach ($items as $r) {
            $rows[] = [e($r['title']), e(dfr($r['period_start'], 'm/Y')), e(money($r['amount'])), e(money($r['paid_amount'])), status_badge($r['status'])];
        }
        list_page('Revenus & loyers', ['Bien', 'Période', 'Dû', 'Encaissé', 'Statut'], $rows, ['export' => true]);
        return;
    }
    if ($what === 'depenses') {
        $items = db()->all('SELECT e.*, p.title FROM expenses e JOIN properties p ON p.id=e.property_id WHERE p.owner_id=?', [$oid]);
        $rows = [];
        foreach ($items as $e) {
            $rows[] = [e($e['title']), e($e['category']), e(money($e['amount'])), e(dfr($e['expense_date'])), status_badge($e['status'])];
        }
        list_page('Dépenses', ['Bien', 'Catégorie', 'Montant', 'Date', 'Statut'], $rows);
        return;
    }
    if ($what === 'reversements') {
        $items = db()->all('SELECT * FROM payouts WHERE owner_id=? ORDER BY id DESC', [$oid]);
        $rows = [];
        foreach ($items as $x) {
            $rows[] = [e(dfr($x['period_start'], 'm/Y')), e(money($x['collections'])), e(money($x['commission'])), e(money($x['expenses'])), e(money($x['net_amount'])), status_badge($x['status'])];
        }
        list_page('Reversements', ['Période', 'Encaissé', 'Commission', 'Dépenses', 'Net', 'Statut'], $rows);
        return;
    }
    if ($what === 'reservations') {
        $items = db()->all('SELECT r.*, p.title FROM reservations r JOIN properties p ON p.id=r.property_id WHERE p.owner_id=?', [$oid]);
        $rows = [];
        foreach ($items as $r) {
            $rows[] = [e($r['number']), e($r['title']), e($r['guest_name']), e(dfr($r['checkin'])), e(money($r['total'])), status_badge($r['status'])];
        }
        list_page('Réservations Stay', ['N°', 'Logement', 'Voyageur', 'Arrivée', 'Total', 'Statut'], $rows);
        return;
    }
    if ($what === 'incidents') {
        $items = db()->all('SELECT m.*, p.title FROM maintenances m JOIN properties p ON p.id=m.property_id WHERE p.owner_id=?', [$oid]);
        $rows = [];
        foreach ($items as $m) {
            $rows[] = [e($m['title']), e($m['category']), e($m['description']), status_badge($m['status'])];
        }
        list_page('Incidents', ['Bien', 'Catégorie', 'Détail', 'Statut'], $rows);
        return;
    }
    if ($what === 'documents') {
        $items = db()->all("SELECT * FROM documents WHERE (entity='owners' AND entity_id=?) OR (entity='properties' AND entity_id IN (SELECT id FROM properties WHERE owner_id=?))", [$oid, $oid]);
        $rows = [];
        foreach ($items as $d) {
            $rows[] = [e($d['title']), e($d['entity']), e(dfr($d['created_at']))];
        }
        list_page('Documents', ['Titre', 'Type', 'Date'], $rows);
        return;
    }
    if ($what === 'rapports') {
        $col = (float) db()->val('SELECT COALESCE(SUM(paid_amount),0) FROM rents WHERE owner_id=?', [$oid]);
        $exp = (float) db()->val('SELECT COALESCE(SUM(e.amount),0) FROM expenses e JOIN properties p ON p.id=e.property_id WHERE p.owner_id=?', [$oid]);
        $com = (float) db()->val("SELECT COALESCE(SUM(c.amount),0) FROM commissions c JOIN properties p ON p.id=c.property_id WHERE p.owner_id=? AND c.type='gestion_youpendi'", [$oid]);
        ob_start(); ?>
        <h1 class="app-title">Rapport propriétaire</h1>
        <div class="kpi">
          <div class="stat"><b><?= e(money($col)) ?></b>Encaissements</div>
          <div class="stat"><b><?= e(money($com)) ?></b>Commission YOUPENDI</div>
          <div class="stat"><b><?= e(money($exp)) ?></b>Dépenses</div>
          <div class="stat"><b><?= e(money($col-$com-$exp)) ?></b>Net</div>
        </div>
        <p class="muted">Formule : Encaissements – Commission YOUPENDI – Dépenses autorisées = Net propriétaire.</p>
        <?php
        view('app/raw', ['title' => 'Rapport', 'html' => ob_get_clean()], 'app');
        return;
    }
    abort(404, 'Section inconnue');
}

function portal_tenant(): void
{
    $u = require_role(['tenant', 'admin']);
    $t = tenant_of($u);
    if (!$t && $u['role'] === 'admin') {
        $t = db()->one('SELECT * FROM tenants ORDER BY id LIMIT 1');
    }
    if (!$t) {
        view('app/raw', ['title' => 'Espace locataire', 'html' => '<h1 class="app-title">Aucun logement associé pour le moment.</h1>'], 'app');
        return;
    }
    $p = $t['property_id'] ? db()->one('SELECT * FROM properties WHERE id=?', [$t['property_id']]) : null;
    $c = $t['contract_id'] ? db()->one('SELECT * FROM contracts WHERE id=?', [$t['contract_id']]) : null;
    $due = db()->one("SELECT * FROM rents WHERE tenant_id=? AND status IN ('a_payer','en_retard','partiel') ORDER BY due_date LIMIT 1", [$t['id']]);
    $kpis = [
        ['l' => 'Mon loyer', 'v' => money($c['rent'] ?? 0)],
        ['l' => 'Prochaine échéance', 'v' => $due ? dfr($due['due_date']) : '—'],
        ['l' => 'Reste à payer', 'v' => $due ? money($due['amount'] - $due['paid_amount']) : money(0)],
        ['l' => 'Contrat jusqu’au', 'v' => dfr($c['end_date'] ?? null)],
    ];
    $logs = db()->all('SELECT * FROM rents WHERE tenant_id=? ORDER BY due_date DESC LIMIT 6', [$t['id']]);
    $logRows = array_map(fn($r) => ['created_at' => $r['due_date'], 'action' => $r['status'] . ' ' . money($r['amount']), 'entity' => 'loyer', 'entity_id' => $r['id']], $logs);
    view('app/dashboard', ['title' => 'Espace locataire', 'heading' => $p['title'] ?? 'Mon espace', 'kpis' => $kpis, 'logs' => $logRows, 'todo' => [
        ['espace/locataire/paiements', 'Voir mes paiements'],
        ['espace/locataire/maintenance', 'Signaler un incident'],
        ['espace/locataire/quittances', 'Télécharger une quittance'],
    ]], 'app');
}

function portal_tenant_page(string $what): void
{
    $u = require_role(['tenant', 'admin']);
    $t = tenant_of($u) ?: db()->one('SELECT * FROM tenants ORDER BY id LIMIT 1');
    if (!$t) {
        abort(404, 'Dossier introuvable');
    }
    if ($what === 'logement') {
        $p = db()->one('SELECT * FROM properties WHERE id=?', [$t['property_id']]);
        ob_start(); ?>
        <h1 class="app-title"><?= e($p['title'] ?? 'Logement') ?></h1>
        <div class="panel"><p><?= nl2br(e($p['description'] ?? '')) ?></p><p><?= e($p['address_public'] ?? '') ?></p></div>
        <?php
        view('app/raw', ['title' => 'Mon logement', 'html' => ob_get_clean()], 'app');
        return;
    }
    if ($what === 'contrat') {
        $c = db()->one('SELECT * FROM contracts WHERE id=?', [$t['contract_id']]);
        ob_start(); ?>
        <h1 class="app-title">Contrat <?= e($c['reference'] ?? '') ?></h1>
        <div class="panel">
          <p>Du <?= e(dfr($c['start_date']??null)) ?> au <?= e(dfr($c['end_date']??null)) ?></p>
          <p>Loyer <?= e(money($c['rent']??0)) ?> · Caution <?= e(money($c['deposit']??0)) ?></p>
          <p><?= nl2br(e($c['conditions']??'')) ?></p>
        </div>
        <?php
        view('app/raw', ['title' => 'Contrat', 'html' => ob_get_clean()], 'app');
        return;
    }
    if ($what === 'paiements') {
        $items = db()->all('SELECT * FROM rents WHERE tenant_id=? ORDER BY due_date DESC', [$t['id']]);
        $rows = [];
        foreach ($items as $r) {
            $rows[] = [e(dfr($r['period_start'], 'm/Y')), e(money($r['amount'])), e(money($r['paid_amount'])), status_badge($r['status']), e(dfr($r['due_date']))];
        }
        list_page('Mes paiements', ['Période', 'Dû', 'Payé', 'Statut', 'Échéance'], $rows);
        return;
    }
    if ($what === 'quittances') {
        $items = db()->all('SELECT * FROM receipts WHERE tenant_id=? ORDER BY id DESC', [$t['id']]);
        $rows = [];
        foreach ($items as $q) {
            $rows[] = [e($q['number']), e($q['period_label']), e(money($q['amount'])), a_link('quittance/' . $q['id'], 'PDF / Imprimer')];
        }
        list_page('Quittances', ['N°', 'Période', 'Montant', ''], $rows);
        return;
    }
    if ($what === 'demandes' || $what === 'maintenance') {
        if (is_post()) {
            csrf_verify();
            insert_existing('maintenances', [
                'property_id' => $t['property_id'], 'tenant_id' => $t['id'],
                'category' => str_input('category'), 'urgency' => str_input('urgency'),
                'description' => str_input('description'), 'status' => 'nouveau_inc',
                'created_by' => $u['id'], 'created_at' => now(), 'updated_at' => now(),
            ]);
            flash('success', 'Incident transmis au gestionnaire.');
            redirect('espace/locataire/maintenance');
        }
        $items = db()->all('SELECT * FROM maintenances WHERE tenant_id=? ORDER BY id DESC', [$t['id']]);
        $rows = [];
        foreach ($items as $m) {
            $rows[] = [e(dfr($m['created_at'])), e($m['category']), e($m['description']), status_badge($m['status'])];
        }
        ob_start();
        echo sel('category', ['plomberie' => 'Plomberie', 'electricite' => 'Électricité', 'serrurerie' => 'Serrurerie', 'climatisation' => 'Climatisation', 'autre' => 'Autre'], 'autre', 'Catégorie');
        echo sel('urgency', ['normale' => 'Normale', 'haute' => 'Haute', 'critique' => 'Critique'], 'normale', 'Urgence');
        echo fld('description', 'Description', 'textarea', '', 'full req');
        view('app/form', ['title' => 'Signaler un problème', 'fields' => ob_get_clean(), 'submit' => 'Envoyer',
            'extra' => '<div class="table-wrap" style="margin-top:16px"><table><thead><tr><th>Date</th><th>Cat.</th><th>Détail</th><th>Statut</th></tr></thead><tbody>' .
            implode('', array_map(fn($r) => '<tr><td>' . implode('</td><td>', $r) . '</td></tr>', $rows)) . '</tbody></table></div>'], 'app');
        return;
    }
    if ($what === 'documents') {
        $items = db()->all("SELECT * FROM documents WHERE entity='tenants' AND entity_id=?", [$t['id']]);
        $rows = [];
        foreach ($items as $d) {
            $rows[] = [e($d['title']), e(dfr($d['created_at']))];
        }
        list_page('Mes documents', ['Titre', 'Date'], $rows);
        return;
    }
    abort(404, 'Section inconnue');
}

function portal_traveler(): void
{
    $u = require_role(['traveler', 'admin']);
    $items = db()->all('SELECT r.*, p.title FROM reservations r LEFT JOIN properties p ON p.id=r.property_id WHERE r.user_id=? OR r.guest_email=? ORDER BY r.id DESC', [$u['id'], $u['email']]);
    $rows = [];
    foreach ($items as $r) {
        $rows[] = [e($r['number']), e($r['title']), e(dfr($r['checkin']) . ' → ' . dfr($r['checkout'])), e(money($r['total'])), status_badge($r['status'])];
    }
    list_page('Mes réservations', ['N°', 'Logement', 'Dates', 'Total', 'Statut'], $rows);
}
