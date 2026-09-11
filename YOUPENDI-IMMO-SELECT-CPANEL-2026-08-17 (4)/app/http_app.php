<?php

function app_properties(): void
{
    require_auth();
    sync_expired_contracts();
    [$scope, $sp] = property_scope_sql('p');
    $w = [$scope];
    $params = $sp;
    if ($s = str_input('status')) { $w[] = 'p.status = ?'; $params[] = $s; }
    if ($t = str_input('type')) { $w[] = 'p.type = ?'; $params[] = $t; }
    if ($province = str_input('province')) { $w[] = 'p.province = ?'; $params[] = $province; }
    if ($c = str_input('ville')) { $w[] = 'p.city = ?'; $params[] = $c; }
    if ($q = str_input('q')) { $w[] = '(p.title LIKE ? OR p.reference LIKE ? OR p.quartier LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
    $where = implode(' AND ', $w);
    $total = (int) db()->val("SELECT COUNT(*) FROM properties p WHERE $where", $params);
    $pg = paginate($total, 20);
    $items = qtry_all("SELECT p.*, o.reference owner_reference, o.first_name ofn, o.last_name oln FROM properties p LEFT JOIN owners o ON o.id = p.owner_id WHERE $where ORDER BY p.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
    if (!$items) {
        $items = qtry_all("SELECT p.* FROM properties p WHERE $where ORDER BY p.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
    }
    if (str_input('export') === 'csv') {
        csv_download('biens.csv', ['Réf', 'Titre', 'Province', 'Ville', 'Loyer', 'Statut', 'Propriétaire'], array_map(fn($p) => [
            $p['reference'], $p['title'], $p['province'] ?? '', $p['city'], $p['rent'], $p['status'], trim(($p['ofn'] ?? '') . ' ' . ($p['oln'] ?? '')),
        ], $items));
    }
    $rows = [];
    foreach ($items as $p) {
        $ownerName = trim(($p['ofn'] ?? '') . ' ' . ($p['oln'] ?? ''));
        $actions = '<div class="row-actions">'
            . '<a class="btn btn-sm" href="' . e(base_url('app/biens/' . $p['id'])) . '">Visualiser</a>'
            . '<a class="btn btn-outline btn-sm" href="' . e(base_url('app/biens/' . $p['id'] . '/edit')) . '">Modifier</a>';
        if (can('properties.edit') || user()['role'] === 'admin') {
            $actions .= post_btn_confirm(
                'app/biens/' . $p['id'] . '/supprimer',
                'Supprimer',
                'Supprimer ce bien ? S’il possède un historique, il sera archivé afin de conserver les contrats.',
                'btn btn-danger btn-sm'
            );
        }
        $actions .= '</div>';
        $rows[] = [
            e($p['reference']),
            e($p['title']),
            e(trim(($p['province'] ?? '') . ' · ' . $p['city'] . ' · ' . $p['quartier'], ' ·')),
            e(property_types()[$p['type']] ?? $p['type']),
            e(money($p['rent'], $p['currency'])),
            status_badge($p['status']),
            $ownerName !== '' ? e((($p['owner_reference'] ?? '') ? $p['owner_reference'] . ' – ' : '') . $ownerName) : '<span class="badge badge-danger">Non attribué</span>',
            $actions,
        ];
    }
    list_page('Tous les biens', ['Réf', 'Titre', 'Localisation', 'Type', 'Loyer', 'Statut', 'Propriétaire', 'Actions'], $rows, [
        'create' => 'app/biens/nouveau', 'createLabel' => 'Ajouter un bien', 'export' => true, 'pg' => $pg,
        'filters' => [
            'q' => ['type' => 'text', 'label' => 'Recherche'],
            'province' => ['' => 'Province'] + provinces_rdc(),
            'ville' => ['' => 'Ville'] + array_combine(cities(), cities()),
            'type' => ['' => 'Type'] + property_types(),
            'status' => ['' => 'Statut'] + property_statuses(),
        ],
    ]);
}

function app_property_form(?int $id = null): void
{
    require_auth();
    if (!can('properties.create') && !can('properties.edit') && !can('properties.own')) {
        abort(403, 'Non autorisé');
    }
    $p = $id ? db()->one('SELECT * FROM properties WHERE id = ?', [$id]) : null;
    if ($id && !$p) {
        abort(404, 'Bien introuvable');
    }
    if ($p && !can_see_property($p)) {
        abort(403, 'Non autorisé');
    }
    if (is_post()) {
        csrf_verify();
        try {
        $ownerId = int_input('owner_id');
        if ($ownerId <= 0 || !db()->one('SELECT id FROM owners WHERE id = ?', [$ownerId])) {
            throw new InvalidArgumentException('Le propriétaire du bien est obligatoire. Enregistrez d’abord le propriétaire, puis sélectionnez-le.');
        }
        $activeContract = $id ? db()->one("SELECT id FROM contracts WHERE property_id = ? AND status = 'actif' LIMIT 1", [$id]) : null;
        if ($activeContract && (int) ($p['owner_id'] ?? 0) !== $ownerId) {
            throw new InvalidArgumentException('Le propriétaire ne peut pas être changé pendant qu’un contrat est actif.');
        }
        $location = rdc_location(str_input('province'), str_input('city'));
        if ($location['province'] === '' || $location['city'] === '') {
            throw new InvalidArgumentException('La province et la ville du bien sont obligatoires.');
        }
        $data = [
            'title' => str_input('title'),
            'titre' => str_input('title'),
            'slug' => slugify(str_input('title')),
            'type' => str_input('type'),
            'offre' => str_input('offre') ?: 'location_mensuelle',
            'usage_type' => str_input('usage_type') ?: 'residentiel',
            'description' => str_input('description'),
            'province' => $location['province'],
            'city' => $location['city'],
            'commune' => str_input('commune'),
            'quartier' => str_input('quartier'),
            'address' => str_input('address'),
            'address_public' => str_input('address_public'),
            'rent' => float_input('rent'),
            'currency' => str_input('currency') ?: 'USD',
            'deposit' => float_input('deposit'),
            'bedrooms' => int_input('bedrooms'),
            'bathrooms' => int_input('bathrooms'),
            'area' => float_input('area'),
            'furnished' => int_input('furnished'),
            'amenities' => implode(',', (array) ($_POST['amenities'] ?? [])),
            'available_from' => str_input('available_from') ?: null,
            'owner_id' => $ownerId,
            'agent_apporteur_id' => int_input('agent_apporteur_id') ?: null,
            'agent_commercial_id' => int_input('agent_commercial_id') ?: null,
            'manager_id' => int_input('manager_id') ?: null,
            'commission_rate' => float_input('commission_rate', (float) setting('commission_mgmt', 10)),
            'visit_modalities' => str_input('visit_modalities'),
            'is_short_stay' => str_input('offre') === 'location_journaliere' ? 1 : int_input('is_short_stay'),
            'updated_at' => now(),
        ];
        $newStatus = str_input('status') ?: 'brouillon';
        if ($newStatus === 'publie' && !can('properties.publish_limited') && user()['role'] !== 'admin') {
            $newStatus = $p['status'] ?? 'en_attente';
            flash('error', 'Un bien ne peut être publié sans validation administrateur.');
        }
        if (user()['role'] === 'agent' && !$id) {
            $newStatus = 'en_attente';
            $ag = agent_of();
            $data['agent_apporteur_id'] = $ag['id'] ?? null;
        }
        if ($activeContract && $newStatus !== 'maintenance') {
            $newStatus = 'occupe';
        }
        $data['status'] = $newStatus;
        if ($id) {
            $old = $p['status'];
            update_existing('properties', $data, 'id = ?', [$id]);
            log_activity('bien_modifie', 'properties', $id, ['status' => $old], ['status' => $newStatus]);
        } else {
            $data['reference'] = numbered_reference('properties', 'BIE');
            $data['created_by'] = user()['id'];
            $data['created_at'] = now();
            $id = insert_existing('properties', $data);
            log_activity('bien_cree', 'properties', $id);
        }
        try {
            foreach (handle_uploads('photos', 'properties') as $i => $path) {
                insert_existing('property_photos', [
                    'property_id' => $id, 'path' => $path, 'is_cover' => $i === 0 ? 1 : 0, 'sort_order' => $i,
                ]);
            }
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        if (int_input('is_short_stay') && !db()->one('SELECT id FROM stay_listings WHERE property_id = ?', [$id])) {
            insert_existing('stay_listings', [
                'property_id' => $id, 'name' => str_input('title'),
                'guests' => int_input('guests', 2), 'beds' => int_input('beds', 1),
                'price_night' => float_input('rent'), 'extra_fees' => 0, 'status' => 'disponible',
            ]);
        }
        flash('success', 'Bien enregistré.');
        redirect('app/biens/' . $id);
        } catch (Throwable $e) {
            flash('error', 'Enregistrement impossible : ' . $e->getMessage());
            remember_old($_POST);
            redirect($id ? 'app/biens/' . $id . '/edit' : 'app/biens/nouveau');
        }
    }
    $owners = qtry_all("SELECT id, reference, first_name, last_name FROM owners WHERE deleted_at IS NULL AND COALESCE(status, 'actif') <> 'supprime' ORDER BY first_name, last_name");
    $agents = qtry_all('SELECT a.id, u.first_name, u.last_name FROM immo_agents a LEFT JOIN users u ON u.id = a.user_id');
    if (!$agents) {
        $agents = qtry_all("SELECT id, first_name, last_name FROM users WHERE role IN ('agent','supervisor','manager')");
    }
    $ownOpts = ['' => $owners ? 'Sélectionner un propriétaire' : 'Aucun propriétaire enregistré'];
    foreach ($owners as $owner) {
        $ownOpts[$owner['id']] = entity_reference($owner, 'PROP') . ' – ' . full_name($owner);
    }
    $agOpts = ['' => '—'] + array_column(array_map(fn($o) => [$o['id'], full_name($o)], $agents), 1, 0);
    $am = array_filter(explode(',', (string) ($p['amenities'] ?? '')));
    ob_start();
    echo fld('title', 'Titre', 'text', $p['title'] ?? '', 'req full');
    echo sel('offre', owner_offres(), $p['offre'] ?? 'location_mensuelle', 'Type d’offre (vente / location)');
    echo sel('type', property_types(), $p['type'] ?? 'appartement', 'Type');
    echo sel('usage_type', ['residentiel' => 'Résidentiel', 'professionnel' => 'Professionnel'], $p['usage_type'] ?? 'residentiel', 'Usage');
    echo sel('status', property_statuses(), $p['status'] ?? 'brouillon', 'Statut');
    $selectedCity = old('city', $p['city'] ?? 'Goma');
    $selectedProvince = old('province', $p['province'] ?? province_for_city($selectedCity));
    echo sel('province', ['' => 'Sélectionner une province'] + provinces_rdc(), $selectedProvince, 'Province *', false, true);
    echo sel('city', ['' => 'Sélectionner une ville'] + array_combine(cities(), cities()), $selectedCity, 'Ville *', false, true);
    echo fld('commune', 'Commune', 'text', $p['commune'] ?? '');
    echo fld('quartier', 'Quartier', 'text', $p['quartier'] ?? '');
    echo fld('address', 'Adresse privée', 'text', $p['address'] ?? '', 'full');
    echo fld('address_public', 'Adresse publique (masquée)', 'text', $p['address_public'] ?? '', 'full');
    echo fld('rent', 'Loyer / nuitée', 'number', $p['rent'] ?? '');
    echo fld('deposit', 'Caution', 'number', $p['deposit'] ?? '');
    echo fld('currency', 'Devise', 'text', $p['currency'] ?? 'USD');
    echo fld('bedrooms', 'Chambres', 'number', $p['bedrooms'] ?? 0);
    echo fld('bathrooms', 'Salles de bain', 'number', $p['bathrooms'] ?? 0);
    echo fld('area', 'Superficie m²', 'number', $p['area'] ?? '');
    echo sel('furnished', ['0' => 'Non meublé', '1' => 'Meublé'], $p['furnished'] ?? 0, 'Ameublement');
    echo sel('is_short_stay', ['0' => 'Location classique', '1' => 'Immo Stay'], $p['is_short_stay'] ?? 0, 'Catégorie');
    echo sel('owner_id', $ownOpts, $p['owner_id'] ?? int_input('owner_id'), 'Propriétaire du bien *', false, true);
    echo sel('agent_apporteur_id', $agOpts, $p['agent_apporteur_id'] ?? '', 'Agent apporteur');
    echo sel('agent_commercial_id', $agOpts, $p['agent_commercial_id'] ?? '', 'Agent commercial');
    echo sel('manager_id', $agOpts, $p['manager_id'] ?? '', 'Gestionnaire');
    echo fld('commission_rate', 'Commission YOUPENDI %', 'number', $p['commission_rate'] ?? setting('commission_mgmt', 10));
    echo fld('available_from', 'Disponible le', 'date', $p['available_from'] ?? '');
    echo fld('visit_modalities', 'Modalités de visite', 'text', $p['visit_modalities'] ?? '', 'full');
    echo fld('description', 'Description', 'textarea', $p['description'] ?? '', 'full');
    echo '<div class="full"><span class="muted">Équipements</span><br>';
    foreach (amenities_catalog() as $k => $lab) {
        $ck = in_array($k, $am, true) ? ' checked' : '';
        echo '<label style="margin-right:10px"><input type="checkbox" name="amenities[]" value="' . e($k) . '"' . $ck . '> ' . e($lab) . '</label>';
    }
    echo '</div>';
    echo fld('photos', 'Photos', 'file', '', 'full');
    $fields = ob_get_clean();
    $extra = !$owners
        ? '<div class="flash flash-error" style="max-width:920px">Vous devez d’abord <a href="' . e(base_url('app/proprietaires/nouveau')) . '">enregistrer un propriétaire</a>.</div>'
        : '';
    $extra .= '<script>document.querySelector(\'[name=photos]\').setAttribute(\'multiple\',\'multiple\')</script>';
    view('app/form', [
        'title' => $p ? 'Modifier le bien' : 'Nouveau bien',
        'fields' => $fields, 'back' => 'app/biens', 'extra' => $extra,
    ], 'app');
}

function app_property_show(int $id): void
{
    require_auth();
    sync_expired_contracts();
    $p = db()->one('SELECT * FROM properties WHERE id = ?', [$id]);
    if (!$p || !can_see_property($p)) {
        abort(404, 'Bien introuvable');
    }
    $photos = db()->all('SELECT * FROM property_photos WHERE property_id = ?', [$id]);
    $owner = $p['owner_id'] ? db()->one('SELECT * FROM owners WHERE id = ?', [$p['owner_id']]) : null;
    $currentContract = db()->one(
        "SELECT c.*, t.reference tenant_reference, t.first_name tenant_first_name, t.last_name tenant_last_name
         FROM contracts c LEFT JOIN tenants t ON t.id = c.tenant_id
         WHERE c.property_id = ? AND c.status = 'actif' ORDER BY c.start_date DESC, c.id DESC LIMIT 1",
        [$id]
    );
    $contracts = db()->all(
        'SELECT c.*, t.reference tenant_reference, t.first_name tenant_first_name, t.last_name tenant_last_name FROM contracts c LEFT JOIN tenants t ON t.id = c.tenant_id WHERE c.property_id = ? ORDER BY c.start_date DESC, c.id DESC',
        [$id]
    );
    $rents = db()->all('SELECT * FROM rents WHERE property_id = ? ORDER BY due_date DESC LIMIT 8', [$id]);
    $docs = db()->all("SELECT * FROM documents WHERE entity = 'properties' AND entity_id = ?", [$id]);
    ob_start(); ?>
    <div class="section-head">
      <div><p class="kicker gold"><?= e($p['reference']) ?></p><h1 class="app-title"><?= e($p['title']) ?></h1></div>
      <div class="row-actions">
        <?= status_badge($p['status']) ?>
        <a class="btn btn-sm" href="<?= e(base_url('app/biens/'.$id.'/edit')) ?>">Modifier</a>
        <?php if (!$currentContract && !in_array($p['status'], ['maintenance','archive'], true)): ?>
          <a class="btn btn-gold btn-sm" href="<?= e(base_url('app/contrats/nouveau?property_id='.$id)) ?>">Créer un contrat</a>
        <?php endif; ?>
        <?php if (user()['role']==='admin' && $p['status']!=='publie'): ?>
          <?= post_btn('app/biens/'.$id.'/publier', 'Valider & publier', 'btn btn-gold btn-sm') ?>
        <?php endif; ?>
      </div>
    </div>
    <div class="prop-grid">
      <div class="panel">
        <h3>Informations du bien</h3>
        <p><?= nl2br(e($p['description'])) ?></p>
        <p><strong>Localisation :</strong> <?= e(trim(($p['province'] ?? '') . ' · ' . $p['city'] . ' · ' . $p['quartier'], ' ·')) ?></p>
        <p><strong>Loyer :</strong> <?= e(money($p['rent'], $p['currency'])) ?></p>
        <p><strong>Propriétaire :</strong>
          <?php if ($owner): ?><a href="<?= e(base_url('app/proprietaires/'.$owner['id'])) ?>"><?= e(entity_reference($owner, 'PROP').' – '.full_name($owner)) ?></a><?php else: ?><span class="badge badge-danger">Non attribué</span><?php endif; ?>
        </p>
        <p><strong>Locataire actuel :</strong>
          <?php if ($currentContract): ?>
            <a href="<?= e(base_url('app/locataires/'.$currentContract['tenant_id'])) ?>"><?= e(($currentContract['tenant_reference'] ?: 'LOC-'.str_pad((string)$currentContract['tenant_id'],4,'0',STR_PAD_LEFT)).' – '.trim($currentContract['tenant_first_name'].' '.$currentContract['tenant_last_name'])) ?></a>
            via <a href="<?= e(base_url('app/contrats/'.$currentContract['id'])) ?>"><?= e($currentContract['reference']) ?></a>
          <?php else: ?>—<?php endif; ?>
        </p>
        <p><strong>Commission gestion :</strong> <?= e($p['commission_rate']) ?> %</p>
        <div><?php foreach ($photos as $ph): ?><img src="<?= e(photo_url($ph['path'])) ?>" style="height:90px;display:inline-block;margin:4px;border-radius:8px" alt=""><?php endforeach; ?></div>
      </div>
      <div class="panel">
        <h3>Loyers récents</h3>
        <?php if (!$rents): ?><p class="muted">Aucune échéance.</p><?php endif; ?>
        <?php foreach ($rents as $r): ?><div><?= e(dfr($r['period_start'])) ?> — <?= e(money($r['amount'], $r['currency'] ?? 'USD')) ?> <?= status_badge($r['status']) ?></div><?php endforeach; ?>
        <h3>Documents</h3>
        <?php if (!$docs): ?><p class="muted">Aucun document.</p><?php endif; ?>
        <?php foreach ($docs as $d): ?>
          <div>
            <?php if (photo_exists((string) $d['path'])): ?>
              <a href="<?= e(upload_url((string) $d['path'])) ?>"><?= e($d['title']) ?></a>
            <?php else: ?>
              <span class="muted"><?= e($d['title']) ?> — fichier introuvable</span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="panel" style="margin-top:18px">
      <h3>Contrats et historique des occupants</h3>
      <div class="table-wrap"><table>
        <thead><tr><th>Contrat</th><th>Locataire</th><th>Période</th><th>Statut</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($contracts as $contract): ?>
          <tr>
            <td><?= e($contract['reference']) ?></td>
            <td><?= e(trim($contract['tenant_first_name'].' '.$contract['tenant_last_name'])) ?></td>
            <td><?= e(dfr($contract['start_date']).' → '.dfr($contract['end_date'])) ?></td>
            <td><?= status_badge($contract['status']) ?></td>
            <td><a href="<?= e(base_url('app/contrats/'.$contract['id'])) ?>">Visualiser</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$contracts): ?><tr><td colspan="5" class="empty">Aucun contrat.</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>
    <?php // §19 — rapprochement : le bien face aux locataires potentiels compatibles. ?>
    <div class="panel" style="margin-top:18px">
      <h3>Locataires intéressés (compatibilité)</h3>
      <p class="muted">Demandes compatibles avec ce bien, classées par score.</p>
      <a class="btn btn-sm" href="<?= e(base_url('app/biens/' . $id . '/matching')) ?>">Voir tous les locataires potentiels</a>
      <?php $rows = crm_matches_for_property($id, 6, 20); ?>
      <?php if (!$rows): ?>
        <p class="muted">Aucun locataire potentiel compatible pour le moment.</p>
      <?php else: ?>
        <ul class="crm-matches" style="margin-top:12px">
          <?php foreach ($rows as $m): ?>
            <li>
              <a href="<?= e(base_url('app/prospects/' . $m['id'])) ?>"><?= e(prospect_reference($m)) ?> — <?= e(full_name($m)) ?></a>
              <span class="crm-match-score"><?= (int) $m['score'] ?>%</span>
              <span class="crm-match-meta"><?= e($m['city']) ?> — <?= e(property_types()[$m['property_type']] ?? $m['property_type']) ?> — <?= e(money($m['budget_max'])) ?></span>
              <?= status_badge(prospect_status_normalize($m['status'] ?? null)) ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
    <?php
    view('app/raw', ['title' => $p['title'], 'html' => ob_get_clean()], 'app');
}

function app_publish(int $id): void
{
    require_role(['admin']);
    csrf_verify();
    $p = db()->one('SELECT * FROM properties WHERE id = ?', [$id]);
    if (!$p) {
        abort(404, 'Introuvable');
    }
    update_existing('properties', ['status' => 'disponible', 'updated_at' => now()], 'id = ?', [$id]);
    log_activity('bien_publie', 'properties', $id, $p['status'], 'disponible');
    flash('success', 'Bien validé et publié (disponible).');
    redirect('app/biens/' . $id);
}

function app_property_delete(int $id): void
{
    require_role(['admin', 'manager', 'supervisor']);
    csrf_verify();
    $property = db()->one('SELECT * FROM properties WHERE id = ?', [$id]);
    if (!$property) {
        abort(404, 'Bien introuvable');
    }
    $linked = 0;
    foreach (['contracts', 'rents', 'reservations', 'maintenances', 'expenses'] as $table) {
        $linked += (int) qtry_val("SELECT COUNT(*) FROM $table WHERE property_id = ?", [$id], 0);
    }
    if ($linked > 0) {
        update_existing('properties', ['status' => 'archive', 'updated_at' => now()], 'id = ?', [$id]);
        log_activity('bien_archive', 'properties', $id, ['status' => $property['status']], ['status' => 'archive']);
        flash('success', 'Ce bien possède un historique : il a été archivé plutôt que supprimé.');
    } else {
        db()->exec("DELETE FROM documents WHERE entity = 'properties' AND entity_id = ?", [$id]);
        db()->exec('DELETE FROM property_photos WHERE property_id = ?', [$id]);
        db()->exec('DELETE FROM stay_listings WHERE property_id = ?', [$id]);
        db()->exec('DELETE FROM properties WHERE id = ?', [$id]);
        log_activity('bien_supprime', 'properties', $id, $property, null);
        flash('success', 'Bien supprimé.');
    }
    redirect('app/biens');
}

function app_prospects(): void
{
    require_auth();
    if (!can('prospects.*') && !can('prospects.own')) {
        abort(403, 'Accès CRM non autorisé.');
    }
    $w = ['1=1'];
    $params = [];
    $u = user();
    if ($u['role'] === 'agent') {
        $a = agent_of($u);
        // Les anciennes demandes publiques non attribuées restent visibles afin de ne jamais disparaître du CRM.
        $w[] = '(p.agent_id = ? OR p.agent_id IS NULL OR p.agent_id = 0)';
        $params[] = $a['id'] ?? 0;
    }
    $type = str_input('type');
    if ($type) { $w[] = 'p.type = ?'; $params[] = $type; }
    if ($province = str_input('province')) { $w[] = 'p.province = ?'; $params[] = $province; }
    if ($city = str_input('city')) { $w[] = 'p.city = ?'; $params[] = $city; }
    if ($s = str_input('status')) { $w[] = 'p.status = ?'; $params[] = $s; }
    if ($pt = str_input('property_type')) { $w[] = 'p.property_type = ?'; $params[] = $pt; }
    if ($src = str_input('source')) { $w[] = 'p.source = ?'; $params[] = $src; }
    if (($agentFilter = str_input('agent_id')) !== '') {
        if ($agentFilter === '-1') {
            $w[] = '(p.agent_id IS NULL OR p.agent_id = 0)';
        } else {
            $w[] = 'p.agent_id = ?';
            $params[] = (int) $agentFilter;
        }
    }
    if ($budget = float_input('budget_max')) {
        $w[] = '(p.budget_max >= ? OR (p.budget_max IS NULL AND p.budget_min >= ?))';
        $params[] = $budget;
        $params[] = $budget;
    }
    if ($from = str_input('date_from')) { $w[] = 'DATE(p.created_at) >= ?'; $params[] = $from; }
    if ($to = str_input('date_to')) { $w[] = 'DATE(p.created_at) <= ?'; $params[] = $to; }
    if ($q = str_input('q')) {
        $w[] = '(p.first_name LIKE ? OR p.last_name LIKE ? OR p.phone LIKE ? OR p.email LIKE ? OR p.whatsapp LIKE ? OR p.reference LIKE ?)';
        array_push($params, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%", "%$q%");
    }
    $where = implode(' AND ', $w);
    $total = (int) db()->val("SELECT COUNT(*) FROM prospects p WHERE $where", $params);
    $pg = paginate($total, 20);
    $items = db()->all(
        "SELECT p.*, u.first_name agent_first_name, u.last_name agent_last_name
         FROM prospects p
         LEFT JOIN immo_agents a ON a.id = p.agent_id
         LEFT JOIN users u ON u.id = a.user_id
         WHERE $where ORDER BY p.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}",
        $params
    );
    if (str_input('export') === 'csv') {
        csv_download('prospects.csv', ['Identifiant', 'Nom', 'Téléphone', 'WhatsApp', 'E-mail', 'Type', 'Province', 'Ville', 'Quartier', 'Bien', 'Chambres', 'Budget min', 'Budget max', 'Source', 'Responsable', 'Statut', 'Soumis le'], array_map(fn($p) => [
            prospect_reference($p), full_name($p), $p['phone'], $p['whatsapp'], $p['email'], prospect_type_label($p['type']),
            $p['province'], $p['city'], $p['quartier'],
            property_types()[$p['property_type']] ?? $p['property_type'], $p['bedrooms'], $p['budget_min'], $p['budget_max'],
            prospect_source_label($p['source']), crm_responsible_name($p), prospect_status_label($p['status']), $p['created_at'],
        ], $items));
    }
    $statusOptions = prospect_statuses();
    $rows = [];
    foreach ($items as $p) {
        $responsible = crm_responsible_name($p);
        $statusForm = '<form method="post" action="' . e(base_url('app/prospects/' . $p['id'] . '/statut')) . '" class="row-actions">'
            . csrf_field()
            . '<select name="status" aria-label="Nouveau statut">';
        foreach ($statusOptions as $statusKey => $statusLabel) {
            $statusForm .= '<option value="' . e($statusKey) . '"' . (prospect_status_normalize($p['status']) === $statusKey ? ' selected' : '') . '>' . e($statusLabel) . '</option>';
        }
        $statusForm .= '</select><button class="btn btn-sm">Mettre à jour</button></form>';

        if ($type === 'proprietaire') {
            // §15 — colonnes Propriétaires potentiels
            $rows[] = [
                a_link('app/prospects/' . $p['id'], prospect_reference($p) . ' · ' . (full_name($p) ?: 'Sans nom')),
                crm_contact_actions($p),
                e(trim(($p['city'] ?? '') . ' · ' . ($p['quartier'] ?? ''), ' ·')),
                e(property_types()[$p['property_type']] ?? $p['property_type'] ?: '—'),
                e(!empty($p['budget_max']) ? money($p['budget_max'], 'USD') . '/mois' : '—'),
                e(management_type_label($p['management_type'] ?? null)),
                e($p['availability'] ?: ($p['desired_date'] ? dfr($p['desired_date']) : '—')),
                status_badge($p['status']),
                e($responsible ?: 'Non attribué'),
                $statusForm,
            ];
            continue;
        }
        if ($type === 'locataire') {
            // §16 — colonnes Locataires potentiels
            $rows[] = [
                a_link('app/prospects/' . $p['id'], prospect_reference($p) . ' · ' . (full_name($p) ?: 'Sans nom')),
                crm_contact_actions($p),
                e(trim(($p['city'] ?? '') . ' · ' . ($p['quartier'] ?? ''), ' ·')),
                e(property_types()[$p['property_type']] ?? $p['property_type'] ?: 'Indifférent'),
                e(!empty($p['budget_max']) ? money($p['budget_max'], 'USD') : '—'),
                e((int) ($p['bedrooms'] ?? 0) ?: '—'),
                e(!empty($p['furnished']) ? 'Meublé' : 'Non meublé'),
                e($p['desired_date'] ? dfr($p['desired_date']) : '—'),
                status_badge($p['status']),
                e($responsible ?: 'Non attribué'),
                $statusForm,
            ];
            continue;
        }
        $rows[] = [
            a_link('app/prospects/' . $p['id'], prospect_reference($p) . ' · ' . (full_name($p) ?: 'Sans nom')),
            crm_contact_actions($p),
            e(prospect_type_label($p['type'])),
            e(trim(($p['province'] ?? '') . ' · ' . $p['city'] . ' · ' . $p['quartier'], ' ·')),
            e(property_types()[$p['property_type']] ?? $p['property_type']),
            e(prospect_source_label($p['source'])),
            e($responsible ?: 'Non attribué'),
            status_badge($p['status']),
            e(dfr($p['created_at'], 'd/m/Y H:i')),
            $statusForm,
        ];
    }
    $title = $type === 'proprietaire' ? 'Propriétaires potentiels' : ($type === 'locataire' ? 'Locataires potentiels' : 'Tous les prospects CRM');
    $cols = $type === 'proprietaire'
        ? ['Prospect', 'Téléphone', 'Ville', 'Type de bien', 'Loyer souhaité', 'Besoin', 'Disponibilité', 'Statut', 'Responsable', 'Changer le statut']
        : ($type === 'locataire'
            ? ['Prospect', 'Téléphone', 'Ville', 'Recherche', 'Budget max.', 'Chambres', 'Meublé', 'Date souhaitée', 'Statut', 'Responsable', 'Changer le statut']
            : ['Prospect', 'Téléphone', 'Type', 'Zone', 'Bien', 'Source', 'Responsable', 'Statut', 'Soumis le', 'Changer le statut']);
    list_page($title, $cols, $rows, [
        'kicker' => 'CRM · Site public → suivi → conversion',
        'create' => 'app/prospects/nouveau' . ($type ? '?type=' . rawurlencode($type) : ''),
        'createLabel' => 'Nouveau prospect', 'export' => true, 'pg' => $pg,
        'extraTop' => crm_kpis_html() . crm_alerts_html(),
        'filters' => [
            'q' => ['type' => 'text', 'label' => 'Nom, identifiant, téléphone ou e-mail'],
            'type' => ['' => 'Type', 'locataire' => 'Locataire potentiel', 'proprietaire' => 'Propriétaire potentiel'],
            'status' => ['' => 'Statut'] + $statusOptions,
            'province' => ['' => 'Province'] + provinces_rdc(),
            'city' => ['' => 'Ville'] + array_combine(cities(), cities()),
            'property_type' => ['' => 'Type de bien'] + property_types(),
            'source' => ['' => 'Source'] + prospect_sources(),
            'agent_id' => ['' => 'Responsable', '-1' => 'Sans responsable'] + crm_responsible_options_named(),
            'budget_max' => ['type' => 'text', 'label' => 'Budget au moins (USD)'],
            'date_from' => ['type' => 'text', 'label' => 'Créé depuis (AAAA-MM-JJ)'],
            'date_to' => ['type' => 'text', 'label' => 'Créé jusqu’au (AAAA-MM-JJ)'],
        ],
    ]);
}

function app_prospect_status_update(int $id): void
{
    require_auth();
    if (!can('prospects.*') && !can('prospects.own')) {
        abort(403, 'Mise à jour CRM non autorisée.');
    }
    csrf_verify();
    $prospect = db()->one('SELECT * FROM prospects WHERE id = ?', [$id]);
    if (!$prospect) {
        abort(404, 'Prospect introuvable.');
    }
    if (user()['role'] === 'agent') {
        $agent = agent_of();
        $assigned = (int) ($prospect['agent_id'] ?? 0);
        if ($assigned > 0 && $assigned !== (int) ($agent['id'] ?? 0)) {
            abort(403, 'Ce prospect est attribué à un autre agent.');
        }
    }
    $status = str_input('status');
    if (!array_key_exists($status, prospect_statuses($prospect['type']))) {
        flash('error', 'Statut CRM invalide.');
        redirect('app/prospects?type=' . rawurlencode($prospect['type']));
    }
    $oldStatus = (string) ($prospect['status'] ?? 'nouveau');
    $data = ['status' => $status, 'updated_at' => now()];
    if (user()['role'] === 'agent' && empty($prospect['agent_id'])) {
        $data['agent_id'] = agent_of()['id'] ?? null;
    }
    update_existing('prospects', $data, 'id = ?', [$id]);
    if ($oldStatus !== $status) {
        record_prospect_status($id, $oldStatus, $status, 'Statut mis à jour depuis la liste CRM.');
        insert_existing('prospect_notes', [
            'prospect_id' => $id,
            'user_id' => user()['id'],
            'body' => 'Statut : ' . (prospect_statuses($prospect['type'])[$status] ?? $status) . '.',
            'created_at' => now(),
        ]);
    }
    flash('success', 'Le statut du prospect a été mis à jour.');
    redirect('app/prospects?type=' . rawurlencode($prospect['type']));
}

function app_prospect_form(?int $id = null): void
{
    require_auth();
    if (!can('prospects.*') && !can('prospects.own')) {
        abort(403, 'Accès CRM non autorisé.');
    }
    $p = $id ? db()->one('SELECT * FROM prospects WHERE id = ?', [$id]) : null;
    if ($id && !$p) {
        abort(404, 'Prospect introuvable.');
    }
    if ($p && user()['role'] === 'agent') {
        $agent = agent_of();
        $assigned = (int) ($p['agent_id'] ?? 0);
        if ($assigned > 0 && $assigned !== (int) ($agent['id'] ?? 0)) {
            abort(403, 'Ce prospect est attribué à un autre agent.');
        }
    }
    if (is_post()) {
        csrf_verify();
        try {
            $phone = str_input('phone');
            if (!$id) {
                // §10 — anti-doublon sur téléphone, WhatsApp et e-mail.
                $dup = crm_find_contact(str_input('type') === 'proprietaire' ? 'proprietaire' : 'locataire', $phone, str_input('whatsapp'), str_input('email'));
                if ($dup) {
                    flash('error', 'Prospect déjà existant — dossier ' . prospect_reference($dup) . ' (' . full_name($dup) . '). Aucune fiche en double n’a été créée.');
                    redirect('app/prospects/' . $dup['id']);
                }
            }
            $type = str_input('type') === 'proprietaire' ? 'proprietaire' : 'locataire';
            $status = str_input('status') ?: 'nouveau';
            if (!array_key_exists($status, prospect_statuses($type))) {
                $status = 'nouveau';
            }
            $location = rdc_location(str_input('province'), str_input('city'));
            $data = [
                'type' => $type,
                'first_name' => str_input('first_name'),
                'last_name' => str_input('last_name'),
                'phone' => $phone,
                'whatsapp' => str_input('whatsapp'),
                'email' => str_input('email'),
                'province' => $location['province'],
                'city' => $location['city'],
                'quartier' => str_input('quartier'),
                'property_type' => str_input('property_type'),
                'bedrooms' => int_input('bedrooms'),
                'budget_min' => float_input('budget_min'),
                'budget_max' => float_input('budget_max'),
                'furnished' => int_input('furnished'),
                'desired_date' => str_input('desired_date') ?: null,
                'duration' => str_input('duration'),
                'management_type' => str_input('management_type'),
                'property_description' => str_input('property_description'),
                'availability' => str_input('availability'),
                'criteria' => str_input('criteria'),
                'message' => str_input('message'),
                'status' => $status,
                'notes' => str_input('notes'),
                'updated_at' => now(),
            ];
            $agent = agent_of();
            if (user()['role'] === 'agent') {
                $data['agent_id'] = $agent['id'] ?? null;
            } else {
                $data['agent_id'] = int_input('agent_id') ?: null;
            }
            if (!$id) {
                // §18 — source standardisée (téléphone, WhatsApp, bureau, Facebook…).
                $source = str_input('source');
                $data['source'] = array_key_exists($source, prospect_sources()) ? $source : 'bureau';
                $data['created_at'] = now();
                $id = insert_existing('prospects', $data);
                // §11 — identifiant PROP-00001 / LOC-00001.
                prospect_assign_reference($id, $type);
                record_prospect_status($id, null, $status, 'Prospect créé dans le CRM.');
                log_activity('prospect_cree', 'prospects', $id);
            } else {
                $oldStatus = $p['status'] ?? null;
                update_existing('prospects', $data, 'id = ?', [$id]);
                if ($oldStatus !== $status) {
                    record_prospect_status($id, $oldStatus, $status, str_input('new_note'));
                }
                log_activity('prospect_modifie', 'prospects', $id, ['status' => $oldStatus], ['status' => $status]);
            }
            if ($note = str_input('new_note')) {
                insert_existing('prospect_notes', ['prospect_id' => $id, 'user_id' => user()['id'], 'body' => $note, 'created_at' => now()]);
            }
            flash('success', 'Prospect enregistré.');
            redirect('app/prospects/' . $id);
        } catch (Throwable $e) {
            flash('error', 'Prospect : ' . $e->getMessage());
            redirect($id ? 'app/prospects/' . $id : 'app/prospects/nouveau');
        }
    }

    $type = $p['type'] ?? (str_input('type') === 'proprietaire' ? 'proprietaire' : 'locataire');
    $notes = $id ? db()->all('SELECT n.*, u.first_name, u.last_name FROM prospect_notes n LEFT JOIN users u ON u.id = n.user_id WHERE prospect_id = ? ORDER BY n.id DESC', [$id]) : [];
    $history = $id ? db()->all('SELECT h.*, u.first_name, u.last_name FROM prospect_status_history h LEFT JOIN users u ON u.id = h.user_id WHERE prospect_id = ? ORDER BY h.id DESC', [$id]) : [];
    $agents = qtry_all('SELECT a.id, u.first_name, u.last_name FROM immo_agents a LEFT JOIN users u ON u.id = a.user_id ORDER BY u.first_name, u.last_name');
    $agentOptions = ['' => 'Non attribué'];
    foreach ($agents as $agent) {
        $agentOptions[$agent['id']] = full_name($agent);
    }

    ob_start();
    echo sel('type', ['locataire' => 'Locataire potentiel', 'proprietaire' => 'Propriétaire potentiel'], $type, 'Type de prospect', false, true);
    echo sel('status', prospect_statuses($type), $p['status'] ?? 'nouveau', 'Étape commerciale', false, true);
    echo fld('first_name', 'Prénom', 'text', $p['first_name'] ?? '', 'req');
    echo fld('last_name', 'Nom', 'text', $p['last_name'] ?? '');
    echo fld('phone', 'Téléphone', 'tel', $p['phone'] ?? '', 'req');
    echo fld('whatsapp', 'WhatsApp', 'tel', $p['whatsapp'] ?? '');
    echo fld('email', 'E-mail', 'email', $p['email'] ?? '');
    $prospectCity = old('city', $p['city'] ?? '');
    $prospectProvince = old('province', $p['province'] ?? province_for_city($prospectCity));
    echo sel('province', ['' => 'Sélectionner une province'] + provinces_rdc(), $prospectProvince, 'Province');
    echo sel('city', ['' => 'Sélectionner une ville'] + array_combine(cities(), cities()), $prospectCity, 'Ville');
    echo fld('quartier', 'Quartier recherché ou du bien', 'text', $p['quartier'] ?? '');
    echo sel('property_type', ['' => '—'] + property_types(), $p['property_type'] ?? '', 'Type de bien');
    echo fld('bedrooms', 'Nombre de chambres', 'number', $p['bedrooms'] ?? '');
    echo fld('budget_min', 'Budget minimum', 'number', $p['budget_min'] ?? '');
    echo fld('budget_max', 'Budget maximum / loyer souhaité', 'number', $p['budget_max'] ?? '');
    echo sel('furnished', ['0' => 'Non meublé', '1' => 'Meublé'], $p['furnished'] ?? 0, 'Ameublement');
    echo fld('desired_date', 'Date souhaitée / disponibilité', 'date', $p['desired_date'] ?? '');
    echo fld('duration', 'Durée souhaitée', 'text', $p['duration'] ?? '');
    echo sel('management_type', ['' => '—', 'mise_en_location'=>'Mise en location', 'gestion_locative'=>'Gestion locative', 'courte_duree'=>'Location courte durée'], $p['management_type'] ?? '', 'Besoin propriétaire');
    echo sel('source', ['' => '—'] + prospect_sources(), prospect_source_normalize($p['source'] ?? null), 'Source du prospect');
    echo fld('availability', 'Disponibilité du bien', 'text', $p['availability'] ?? '');
    echo fld('property_description', 'Description du bien', 'textarea', $p['property_description'] ?? '', 'full');
    echo fld('criteria', 'Critères particuliers', 'textarea', $p['criteria'] ?? '', 'full');
    echo fld('message', 'Message / besoin exprimé', 'textarea', $p['message'] ?? '', 'full');
    if (user()['role'] !== 'agent') {
        echo sel('agent_id', $agentOptions, $p['agent_id'] ?? '', 'Responsable du suivi');
    }
    echo fld('notes', 'Notes internes', 'textarea', $p['notes'] ?? '', 'full');
    echo fld('new_note', 'Ajouter un suivi', 'textarea', '', 'full');
    // §17 — les champs affichés changent selon le type de prospect sélectionné.
    echo '<script>
    (function () {
      var form = document.currentScript.closest("form") || document;
      var typeSel = form.querySelector("[name=type]");
      if (!typeSel) { return; }
      var ownerOnly = ["management_type", "availability", "property_description"];
      var tenantOnly = ["budget_min", "furnished", "duration", "criteria"];
      function apply() {
        var t = typeSel.value;
        ownerOnly.concat(tenantOnly).forEach(function (name) {
          var input = form.querySelector("[name=" + name + "]");
          if (!input) { return; }
          var wrap = input.closest("label.fld") || input;
          var show = (t === "proprietaire") ? ownerOnly.indexOf(name) >= 0 : tenantOnly.indexOf(name) >= 0;
          wrap.style.display = show ? "" : "none";
          if (!show) { input.removeAttribute("required"); }
        });
      }
      typeSel.addEventListener("change", apply);
      apply();
    })();
    </script>';
    $fields = ob_get_clean();

    $extra = '';
    if ($p) {
        $extra .= '<div class="panel" style="margin-top:16px;max-width:920px"><h3>Origine et conversion</h3>';
        $extra .= '<p><strong>Identifiant :</strong> ' . e(prospect_reference($p))
            . '<br><strong>Source :</strong> ' . e(prospect_source_label($p['source'] ?? ''))
            . '<br><strong>Responsable du dossier :</strong> ' . e(crm_responsible_name($p) ?: 'Non attribué')
            . '<br><strong>Soumis le :</strong> ' . e(dfr($p['created_at'] ?? null, 'd/m/Y H:i')) . '</p>';
        // §8 — actions rapides
        $extra .= '<div class="row-actions" style="margin:10px 0">' . crm_contact_actions($p)
            . '<a class="btn btn-sm btn-gold" href="' . e(base_url('app/prospects/' . $p['id'] . '/matching')) . '">Biens compatibles</a>'
            . '</div>';
        // §8 — affectation du responsable
        $extra .= crm_assign_form((int) $p['id'], (int) ($p['agent_id'] ?? 0));

        // §5 — photos et documents joints au dossier (photos du bien confié).
        $docs = qtry_all("SELECT * FROM documents WHERE entity = 'prospects' AND entity_id = ? ORDER BY id", [(int) $p['id']]);
        if ($docs) {
            $extra .= '<div class="panel" style="margin-top:16px;max-width:920px"><h3>Photos et pièces du dossier</h3><div class="crm-docs">';
            foreach ($docs as $doc) {
                $path = (string) ($doc['path'] ?? '');
                if (photo_exists($path)) {
                    $extra .= '<figure class="crm-doc"><a href="' . e(upload_url($path)) . '" target="_blank" rel="noopener">'
                        . '<img src="' . e(photo_url($path)) . '" alt="' . e($doc['title'] ?: 'Pièce jointe') . '" loading="lazy"></a>'
                        . '<figcaption>' . e($doc['title'] ?: 'Pièce jointe') . '</figcaption></figure>';
                } else {
                    $extra .= '<p class="muted">' . e($doc['title'] ?: 'Pièce jointe') . ' — fichier introuvable sur le serveur</p>';
                }
            }
            $extra .= '</div></div>';
        }
        if (!empty($p['converted_to']) && !empty($p['converted_id'])) {
            $url = $p['converted_to'] === 'owner' ? 'app/proprietaires/' : 'app/locataires/';
            $extra .= '<p><span class="badge badge-ok">Déjà converti</span> <a href="' . e(base_url($url . $p['converted_id'])) . '">Ouvrir la fiche créée</a></p>';
        } elseif ($p['type'] === 'proprietaire') {
            $extra .= post_btn_confirm('app/prospects/' . $p['id'] . '/convertir-proprietaire', 'Convertir en propriétaire', 'Créer la fiche propriétaire à partir de ce prospect ?', 'btn btn-gold');
        } else {
            $extra .= post_btn_confirm('app/prospects/' . $p['id'] . '/convertir-locataire', 'Convertir en locataire', 'Créer la fiche locataire à partir de ce prospect ?', 'btn btn-gold');
        }
        $extra .= '</div>';

        // §8 / §9 — nouvelle interaction tracée
        $extra .= '<div class="panel" style="margin-top:16px;max-width:920px"><h3>Ajouter une interaction</h3>'
            . crm_interaction_form((int) $p['id'])
            . '<p class="muted">Appels, WhatsApp, e-mails, notes, rendez-vous et visites sont horodatés et signés.</p></div>';

        // §19 / §20 — rapprochement automatique
        $extra .= crm_matching_panel($p);

        // §21 — biens déjà proposés
        $extra .= crm_proposals_panel((int) $p['id']);
    }

    // §9 — historique unifié des interactions
    $timeline = $id ? crm_timeline((int) $id) : [];
    if ($timeline) {
        $extra .= '<div class="panel" style="margin-top:16px;max-width:920px"><h3>Historique des interactions</h3><ul class="crm-timeline">';
        foreach ($timeline as $event) {
            $extra .= '<li><span class="crm-kind">' . e(crm_interaction_label($event['kind'])) . '</span>'
                . '<span class="crm-at">' . e(dfr($event['at'], 'd/m/Y H:i')) . '</span>'
                . '<span class="crm-by">' . e($event['user']) . '</span>'
                . '<div>' . nl2br(e($event['body'])) . '</div></li>';
        }
        $extra .= '</ul></div>';
    }
    view('app/form', [
        'title' => $p ? 'Dossier prospect · ' . prospect_reference($p) . ' · ' . full_name($p) : 'Nouveau prospect',
        'fields' => $fields, 'back' => 'app/prospects', 'extra' => $extra,
    ], 'app');
}

function app_prospect_convert(int $id, string $target): void
{
    require_auth();
    if (!can('prospects.*') && !can('prospects.own')) {
        abort(403, 'Conversion non autorisée.');
    }
    csrf_verify();
    $prospect = db()->one('SELECT * FROM prospects WHERE id = ?', [$id]);
    if (!$prospect) {
        abort(404, 'Prospect introuvable.');
    }
    if (user()['role'] === 'agent') {
        $agent = agent_of();
        $assigned = (int) ($prospect['agent_id'] ?? 0);
        if ($assigned > 0 && $assigned !== (int) ($agent['id'] ?? 0)) {
            abort(403, 'Ce prospect est attribué à un autre agent.');
        }
    }
    $expected = $target === 'owner' ? 'proprietaire' : 'locataire';
    if ($prospect['type'] !== $expected) {
        abort(422, 'Le type du prospect ne correspond pas à cette conversion.');
    }
    if (!empty($prospect['converted_to']) && !empty($prospect['converted_id'])) {
        flash('info', 'Ce prospect a déjà été converti.');
        redirect(($target === 'owner' ? 'app/proprietaires/' : 'app/locataires/') . $prospect['converted_id']);
    }

    db()->begin();
    try {
        $existing = null;
        $table = $target === 'owner' ? 'owners' : 'tenants';
        if (!empty($prospect['phone'])) {
            $existing = db()->one("SELECT * FROM $table WHERE phone = ? AND deleted_at IS NULL AND COALESCE(status, 'actif') <> 'supprime' ORDER BY id DESC LIMIT 1", [$prospect['phone']]);
        }
        if (!$existing && !empty($prospect['email'])) {
            $existing = db()->one("SELECT * FROM $table WHERE email = ? AND deleted_at IS NULL AND COALESCE(status, 'actif') <> 'supprime' ORDER BY id DESC LIMIT 1", [$prospect['email']]);
        }
        if ($existing) {
            $entityId = (int) $existing['id'];
        } elseif ($target === 'owner') {
            $entityId = insert_existing('owners', [
                'reference' => numbered_reference('owners', 'PROP'),
                'first_name' => $prospect['first_name'], 'last_name' => $prospect['last_name'],
                'phone' => $prospect['phone'], 'whatsapp' => $prospect['whatsapp'], 'email' => $prospect['email'],
                'province' => $prospect['province'] ?? province_for_city($prospect['city'] ?? ''),
                'city' => $prospect['city'], 'address' => trim(($prospect['quartier'] ?? '') . ' ' . ($prospect['commune'] ?? '')),
                'notes' => 'Converti depuis le prospect CRM #' . $id . '.', 'created_at' => now(),
            ]);
        } else {
            $entityId = insert_existing('tenants', [
                'reference' => numbered_reference('tenants', 'LOC'),
                'first_name' => $prospect['first_name'], 'last_name' => $prospect['last_name'],
                'phone' => $prospect['phone'], 'whatsapp' => $prospect['whatsapp'], 'email' => $prospect['email'],
                'property_id' => null, 'contract_id' => null,
                'notes' => 'Converti depuis le prospect CRM #' . $id . '.', 'created_at' => now(),
            ]);
        }
        update_existing('prospects', [
            'status' => 'converti', 'converted_to' => $target, 'converted_id' => $entityId, 'updated_at' => now(),
        ], 'id = ?', [$id]);
        record_prospect_status($id, $prospect['status'] ?? null, 'converti', 'Conversion en ' . ($target === 'owner' ? 'propriétaire' : 'locataire') . '.');
        insert_existing('prospect_notes', [
            'prospect_id' => $id, 'user_id' => user()['id'],
            'body' => 'Prospect converti en ' . ($target === 'owner' ? 'propriétaire' : 'locataire') . ' (fiche #' . $entityId . ').',
            'created_at' => now(),
        ]);
        log_activity('prospect_converti_' . $target, 'prospects', $id, null, ['id' => $entityId]);
        db()->commit();
        flash('success', $target === 'owner'
            ? 'Prospect converti. Vous pouvez maintenant rattacher ses biens.'
            : 'Prospect converti. Le locataire sera rattaché à un bien uniquement lors de la création d’un contrat.');
        redirect(($target === 'owner' ? 'app/proprietaires/' : 'app/locataires/') . $entityId);
    } catch (Throwable $e) {
        db()->rollBack();
        flash('error', 'Conversion impossible : ' . $e->getMessage());
        redirect('app/prospects/' . $id);
    }
}


function app_visits(): void
{
    require_auth();
    $w = ['1=1'];
    $params = [];
    if (user()['role'] === 'agent') {
        $a = agent_of();
        $w[] = 'v.agent_id = ?';
        $params[] = $a['id'] ?? 0;
    }
    if ($s = str_input('status')) { $w[] = 'v.status = ?'; $params[] = $s; }
    $items = db()->all('SELECT v.*, p.title, pr.id prospect_record_id FROM visits v LEFT JOIN properties p ON p.id = v.property_id LEFT JOIN prospects pr ON pr.id = v.prospect_id WHERE ' . implode(' AND ', $w) . ' ORDER BY scheduled_at DESC', $params);
    $rows = [];
    foreach ($items as $v) {
        $act = '<form method="post" action="' . e(base_url('app/visites/' . $v['id'] . '/statut')) . '" class="visit-status-form">'
            . csrf_field()
            . '<select name="status" aria-label="Statut de la visite">';
        foreach (visit_statuses() as $statusKey => $statusLabel) {
            $act .= '<option value="' . e($statusKey) . '"' . ($v['status'] === $statusKey ? ' selected' : '') . '>' . e($statusLabel) . '</option>';
        }
        $act .= '</select>'
            . '<input name="report" value="' . e($v['report'] ?? '') . '" placeholder="Compte rendu / motif">'
            . '<button class="btn btn-sm">Mettre à jour</button></form>';
        $rows[] = [
            e(dfr($v['scheduled_at'], 'd/m/Y H:i')),
            e($v['title']),
            !empty($v['prospect_record_id'])
                ? a_link('app/prospects/' . $v['prospect_record_id'], $v['visitor_name']) . '<br><span class="muted">' . e($v['visitor_phone']) . '</span>'
                : e($v['visitor_name'] . ' · ' . $v['visitor_phone']),
            status_badge($v['status']),
            e($v['report']),
            $act,
        ];
    }
    list_page('Visites', ['Quand', 'Bien', 'Visiteur', 'Statut actuel', 'Compte rendu', 'Changer le statut'], $rows, [
        'create' => 'app/visites/nouveau',
        'filters' => ['status' => ['' => 'Tous les statuts'] + visit_statuses()],
    ]);
}

function app_visit_new(): void
{
    require_auth();
    if (is_post()) {
        csrf_verify();
        try {
            $agent = agent_of();
            $prospectId = int_input('prospect_id') ?: null;
            $visitId = insert_existing('visits', [
                'property_id' => int_input('property_id'),
                'prospect_id' => $prospectId,
                'visitor_name' => str_input('visitor_name'),
                'visitor_phone' => str_input('visitor_phone'),
                'visitor_email' => str_input('visitor_email'),
                'agent_id' => $agent['id'] ?? int_input('agent_id') ?: null,
                'scheduled_at' => str_replace('T', ' ', str_input('scheduled_at')),
                'status' => 'planifiee',
                'created_at' => now(),
            ]);
            $propertyId = int_input('property_id');
            if ($propertyId > 0) {
                db()->exec("UPDATE properties SET status = 'visite_en_cours', updated_at = ? WHERE id = ? AND status IN ('disponible','publie')", [now(), $propertyId]);
            }
            if ($prospectId) {
                $prospect = db()->one('SELECT status FROM prospects WHERE id = ?', [$prospectId]);
                if ($prospect) {
                    update_existing('prospects', ['status' => 'visite_programmee', 'updated_at' => now()], 'id = ?', [$prospectId]);
                    record_prospect_status($prospectId, $prospect['status'] ?? null, 'visite_programmee', 'Visite #' . $visitId . ' planifiée.');
                }
            }
            flash('success', 'Visite planifiée et dossier prospect mis à jour.');
            redirect('app/visites');
        } catch (Throwable $e) {
            flash('error', 'Visite : ' . $e->getMessage());
            redirect('app/visites/nouveau');
        }
    }
    $properties = db()->all("SELECT id, reference, title FROM properties WHERE status IN ('disponible','publie','visite_en_cours') ORDER BY title");
    $propertyOptions = [];
    foreach ($properties as $property) {
        $propertyOptions[$property['id']] = $property['reference'] . ' – ' . $property['title'];
    }
    $prospects = db()->all("SELECT id, first_name, last_name, phone, email FROM prospects WHERE type = 'locataire' AND status NOT IN ('converti','non_abouti') ORDER BY id DESC");
    $prospectOptions = ['' => 'Visiteur sans dossier CRM'];
    $prospectMap = [];
    foreach ($prospects as $prospect) {
        $prospectOptions[$prospect['id']] = full_name($prospect) . ' · ' . $prospect['phone'];
        $prospectMap[$prospect['id']] = ['name' => full_name($prospect), 'phone' => $prospect['phone'], 'email' => $prospect['email']];
    }
    $selectedProspectId = int_input('prospect_id');
    $selectedProspect = $prospectMap[$selectedProspectId] ?? null;
    ob_start();
    echo sel('property_id', $propertyOptions, int_input('property_id'), 'Bien *', false, true);
    echo sel('prospect_id', $prospectOptions, $selectedProspectId, 'Locataire potentiel (CRM)');
    echo fld('visitor_name', 'Visiteur', 'text', $selectedProspect['name'] ?? '', 'req');
    echo fld('visitor_phone', 'Téléphone', 'tel', $selectedProspect['phone'] ?? '', 'req');
    echo fld('visitor_email', 'E-mail', 'email', $selectedProspect['email'] ?? '');
    echo fld('scheduled_at', 'Date & heure', 'datetime-local', '', 'req');
    $map = json_encode($prospectMap, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $extra = '<script>(function(){const map=' . $map . ',s=document.querySelector("[name=prospect_id]");if(!s)return;s.addEventListener("change",function(){const p=map[this.value];if(!p)return;document.querySelector("[name=visitor_name]").value=p.name;document.querySelector("[name=visitor_phone]").value=p.phone;document.querySelector("[name=visitor_email]").value=p.email||"";});})();</script>';
    view('app/form', ['title' => 'Planifier une visite', 'fields' => ob_get_clean(), 'back' => 'app/visites', 'extra' => $extra], 'app');
}

function app_visit_status_update(int $id): void
{
    require_auth();
    csrf_verify();
    $visit = db()->one('SELECT * FROM visits WHERE id = ?', [$id]);
    if (!$visit) {
        abort(404, 'Visite introuvable.');
    }
    if (user()['role'] === 'agent') {
        $agent = agent_of();
        if ((int) ($visit['agent_id'] ?? 0) !== (int) ($agent['id'] ?? 0)) {
            abort(403, 'Cette visite est attribuée à un autre agent.');
        }
    }
    $status = str_input('status');
    if (!array_key_exists($status, visit_statuses())) {
        flash('error', 'Statut de visite invalide.');
        redirect('app/visites');
    }
    $report = str_input('report');
    if ($report === '') {
        $report = match ($status) {
            'realisee' => 'Visite effectuée.',
            'annulee' => 'Visite annulée.',
            'no_show' => 'Le visiteur ne s’est pas présenté.',
            'reportee' => 'Visite reportée.',
            'confirmee' => 'Visite confirmée.',
            default => 'Visite planifiée.',
        };
    }
    update_existing('visits', ['status' => $status, 'report' => $report], 'id = ?', [$id]);

    if (!empty($visit['prospect_id'])) {
        $prospect = db()->one('SELECT * FROM prospects WHERE id = ?', [$visit['prospect_id']]);
        if ($prospect) {
            $newProspectStatus = null;
            if ($status === 'realisee') {
                $newProspectStatus = 'visite_effectuee';
            } elseif (in_array($status, ['planifiee', 'confirmee', 'reportee'], true)
                && !in_array($prospect['status'] ?? '', ['converti', 'non_abouti'], true)) {
                $newProspectStatus = 'visite_programmee';
            }
            if ($newProspectStatus && ($prospect['status'] ?? '') !== $newProspectStatus) {
                update_existing('prospects', ['status' => $newProspectStatus, 'updated_at' => now()], 'id = ?', [$visit['prospect_id']]);
                record_prospect_status((int) $visit['prospect_id'], $prospect['status'] ?? null, $newProspectStatus, $report);
            }
            insert_existing('prospect_notes', [
                'prospect_id' => $visit['prospect_id'],
                'user_id' => user()['id'],
                'body' => 'Visite #' . $id . ' — ' . (visit_statuses()[$status] ?? $status) . ' : ' . $report,
                'created_at' => now(),
            ]);
        }
    }

    $propertyId = (int) ($visit['property_id'] ?? 0);
    if ($propertyId > 0) {
        if (in_array($status, ['planifiee', 'confirmee', 'reportee'], true)) {
            db()->exec("UPDATE properties SET status = 'visite_en_cours', updated_at = ? WHERE id = ? AND status IN ('disponible','publie')", [now(), $propertyId]);
        } else {
            $otherVisits = (int) qtry_val(
                "SELECT COUNT(*) FROM visits WHERE property_id = ? AND id <> ? AND status IN ('planifiee','confirmee','reportee')",
                [$propertyId, $id],
                0
            );
            if ($otherVisits === 0) {
                db()->exec("UPDATE properties SET status = 'disponible', updated_at = ? WHERE id = ? AND status = 'visite_en_cours'", [now(), $propertyId]);
            }
        }
    }
    log_activity('visite_statut_modifie', 'visits', $id, ['status' => $visit['status']], ['status' => $status, 'report' => $report]);
    flash('success', 'Le statut de la visite a été mis à jour.');
    redirect('app/visites');
}

function app_visit_ok(int $id): void
{
    require_auth();
    csrf_verify();
    $visit = db()->one('SELECT * FROM visits WHERE id = ?', [$id]);
    if (!$visit) {
        abort(404, 'Visite introuvable.');
    }
    $report = str_input('report') ?: 'Visite effectuée.';
    update_existing('visits', ['status' => 'realisee', 'report' => $report], 'id = ?', [$id]);
    if (!empty($visit['prospect_id'])) {
        $prospect = db()->one('SELECT status FROM prospects WHERE id = ?', [$visit['prospect_id']]);
        if ($prospect) {
            update_existing('prospects', ['status' => 'visite_effectuee', 'updated_at' => now()], 'id = ?', [$visit['prospect_id']]);
            record_prospect_status((int) $visit['prospect_id'], $prospect['status'] ?? null, 'visite_effectuee', $report);
            insert_existing('prospect_notes', [
                'prospect_id' => $visit['prospect_id'], 'user_id' => user()['id'], 'body' => 'Compte rendu de visite #' . $id . ' : ' . $report, 'created_at' => now(),
            ]);
        }
    }
    flash('success', 'Compte rendu enregistré et dossier prospect mis à jour.');
    redirect('app/visites');
}

function app_owners(): void
{
    require_auth();
    sync_expired_contracts();
    $items = db()->all("SELECT o.*, (SELECT COUNT(*) FROM properties WHERE owner_id = o.id) n FROM owners o WHERE o.deleted_at IS NULL AND COALESCE(o.status, 'actif') <> 'supprime' ORDER BY o.id DESC");
    $rows = [];
    foreach ($items as $owner) {
        $rows[] = [
            e(entity_reference($owner, 'PROP')),
            a_link('app/proprietaires/' . $owner['id'], full_name($owner)),
            e($owner['phone']), e($owner['email']), e(trim(($owner['province'] ?? '') . ' · ' . $owner['city'], ' ·')), (int) $owner['n'] . ' bien(s)',
            user()['role'] === 'admin' ? post_btn_confirm(
                'app/proprietaires/' . $owner['id'] . '/supprimer',
                'Supprimer',
                'Supprimer ce propriétaire de la liste ? Ses contrats et biens historiques seront conservés.',
                'btn btn-danger btn-sm'
            ) : '',
        ];
    }
    list_page('Propriétaires', ['Référence', 'Nom', 'Téléphone', 'E-mail', 'Province / ville', 'Portefeuille', 'Actions'], $rows, ['create' => 'app/proprietaires/nouveau']);
}

function app_owner_form(?int $id = null): void
{
    require_auth();
    $owner = $id ? db()->one('SELECT * FROM owners WHERE id = ?', [$id]) : null;
    if ($id && !$owner) {
        abort(404, 'Propriétaire introuvable.');
    }
    if (is_post()) {
        csrf_verify();
        try {
            $location = rdc_location(str_input('province'), str_input('city'));
            $data = [
                'first_name' => str_input('first_name'), 'prenom' => str_input('first_name'),
                'last_name' => str_input('last_name'), 'nom' => str_input('last_name'),
                'phone' => str_input('phone'), 'telephone' => str_input('phone'), 'whatsapp' => str_input('whatsapp'),
                'email' => str_input('email'), 'province' => $location['province'],
                'city' => $location['city'], 'ville' => $location['city'],
                'address' => str_input('address'), 'adresse' => str_input('address'), 'notes' => str_input('notes'),
            ];
            if ($id) {
                update_existing('owners', $data, 'id = ?', [$id]);
                log_activity('proprietaire_modifie', 'owners', $id);
            } else {
                $data['reference'] = numbered_reference('owners', 'PROP');
                $data['created_at'] = now();
                $id = insert_existing('owners', $data);
                log_activity('proprietaire_cree', 'owners', $id);
            }
            flash('success', 'Propriétaire enregistré.');
            redirect('app/proprietaires/' . $id);
        } catch (Throwable $e) {
            flash('error', 'Propriétaire : ' . $e->getMessage());
            redirect($id ? 'app/proprietaires/' . $id : 'app/proprietaires/nouveau');
        }
    }

    sync_expired_contracts();
    $properties = $id ? db()->all(
        "SELECT p.*, c.id current_contract_id, c.reference current_contract_reference,
                t.id current_tenant_id, t.first_name tenant_first_name, t.last_name tenant_last_name
         FROM properties p
         LEFT JOIN contracts c ON c.property_id = p.id AND c.status = 'actif'
         LEFT JOIN tenants t ON t.id = c.tenant_id
         WHERE p.owner_id = ? ORDER BY p.id DESC",
        [$id]
    ) : [];
    $contracts = $id ? db()->all(
        'SELECT c.*, p.title, t.first_name tenant_first_name, t.last_name tenant_last_name FROM contracts c LEFT JOIN properties p ON p.id=c.property_id LEFT JOIN tenants t ON t.id=c.tenant_id WHERE c.owner_id=? ORDER BY c.start_date DESC, c.id DESC',
        [$id]
    ) : [];

    ob_start();
    if ($owner) {
        echo '<div class="full"><span class="badge badge-navy">' . e(entity_reference($owner, 'PROP')) . '</span></div>';
    }
    echo fld('first_name', 'Prénom', 'text', $owner['first_name'] ?? '', 'req');
    echo fld('last_name', 'Nom', 'text', $owner['last_name'] ?? '', 'req');
    echo fld('phone', 'Téléphone', 'tel', $owner['phone'] ?? '');
    echo fld('whatsapp', 'WhatsApp', 'tel', $owner['whatsapp'] ?? '');
    echo fld('email', 'E-mail', 'email', $owner['email'] ?? '');
    $ownerCity = old('city', $owner['city'] ?? '');
    $ownerProvince = old('province', $owner['province'] ?? province_for_city($ownerCity));
    echo sel('province', ['' => 'Sélectionner une province'] + provinces_rdc(), $ownerProvince, 'Province');
    echo sel('city', ['' => 'Sélectionner une ville'] + array_combine(cities(), cities()), $ownerCity, 'Ville');
    echo fld('address', 'Adresse', 'text', $owner['address'] ?? '', 'full');
    echo fld('notes', 'Notes', 'textarea', $owner['notes'] ?? '', 'full');
    $fields = ob_get_clean();

    $extra = '';
    if ($owner) {
        $extra .= '<div class="section-head" style="margin-top:24px;max-width:1100px"><div><h2>Biens associés</h2><p class="muted">Un propriétaire peut posséder plusieurs biens.</p></div><a class="btn btn-gold btn-sm" href="' . e(base_url('app/biens/nouveau?owner_id=' . $id)) . '">Ajouter un bien à ce propriétaire</a></div>';
        $extra .= '<div class="table-wrap" style="max-width:1100px"><table><thead><tr><th>Bien</th><th>Localisation</th><th>Statut</th><th>Locataire actuel</th><th></th></tr></thead><tbody>';
        foreach ($properties as $property) {
            $tenant = trim(($property['tenant_first_name'] ?? '') . ' ' . ($property['tenant_last_name'] ?? ''));
            $extra .= '<tr><td><strong>' . e($property['reference']) . '</strong><br>' . e($property['title']) . '</td><td>' . e(trim(($property['province'] ?? '') . ' · ' . $property['city'] . ' · ' . $property['quartier'], ' ·')) . '</td><td>' . status_badge($property['status']) . '</td><td>' . e($tenant ?: '—') . '</td><td><a href="' . e(base_url('app/biens/' . $property['id'])) . '">Visualiser</a></td></tr>';
        }
        if (!$properties) { $extra .= '<tr><td class="empty" colspan="5">Aucun bien associé.</td></tr>'; }
        $extra .= '</tbody></table></div>';

        $extra .= '<div class="panel" style="margin-top:18px;max-width:1100px"><h3>Contrats et locataires liés</h3><div class="table-wrap"><table><thead><tr><th>Contrat</th><th>Bien</th><th>Locataire</th><th>Période</th><th>Statut</th></tr></thead><tbody>';
        foreach ($contracts as $contract) {
            $extra .= '<tr><td><a href="' . e(base_url('app/contrats/' . $contract['id'])) . '">' . e($contract['reference']) . '</a></td><td>' . e($contract['title']) . '</td><td>' . e(trim($contract['tenant_first_name'] . ' ' . $contract['tenant_last_name'])) . '</td><td>' . e(dfr($contract['start_date']) . ' → ' . dfr($contract['end_date'])) . '</td><td>' . status_badge($contract['status']) . '</td></tr>';
        }
        if (!$contracts) { $extra .= '<tr><td class="empty" colspan="5">Aucun contrat.</td></tr>'; }
        $extra .= '</tbody></table></div></div>';
        if (user()['role'] === 'admin') {
            $extra .= '<div class="panel" style="margin-top:18px;max-width:1100px;border-color:#ef4444"><h3>Supprimer ce propriétaire</h3><p class="muted">Possible après la clôture de ses contrats actifs. L’historique restera conservé.</p>'
                . post_btn_confirm('app/proprietaires/' . $id . '/supprimer', 'Supprimer le propriétaire', 'Confirmer la suppression de ce propriétaire ?', 'btn btn-danger')
                . '</div>';
        }
    }
    view('app/form', ['title' => $owner ? 'Propriétaire · ' . full_name($owner) : 'Nouveau propriétaire', 'fields' => $fields, 'back' => 'app/proprietaires', 'extra' => $extra], 'app');
}

function app_owner_delete(int $id): void
{
    require_role(['admin']);
    csrf_verify();
    $owner = db()->one('SELECT * FROM owners WHERE id = ?', [$id]);
    if (!$owner) {
        abort(404, 'Propriétaire introuvable.');
    }
    $activeContracts = (int) qtry_val("SELECT COUNT(*) FROM contracts WHERE owner_id = ? AND status = 'actif'", [$id], 0);
    if ($activeContracts > 0) {
        flash('error', 'Ce propriétaire possède encore un contrat actif. Clôturez ou résiliez d’abord le contrat.');
        redirect('app/proprietaires/' . $id);
    }
    update_existing('owners', ['status' => 'supprime', 'deleted_at' => now()], 'id = ?', [$id]);
    if (!empty($owner['user_id'])) {
        $account = db()->one('SELECT role FROM users WHERE id = ?', [$owner['user_id']]);
        if (($account['role'] ?? '') === 'owner') {
            update_existing('users', ['status' => 'inactif', 'updated_at' => now()], 'id = ?', [$owner['user_id']]);
        }
    }
    log_activity('proprietaire_supprime', 'owners', $id, $owner, ['status' => 'supprime']);
    flash('success', 'Propriétaire supprimé de la liste. Ses biens et contrats historiques sont conservés.');
    redirect('app/proprietaires');
}

function app_tenants(): void
{
    require_auth();
    sync_expired_contracts();
    $items = db()->all(
        "SELECT t.*, p.title, c.reference contract_reference, c.start_date
         FROM tenants t
         LEFT JOIN contracts c ON c.tenant_id = t.id AND c.status = 'actif'
         LEFT JOIN properties p ON p.id = c.property_id
         WHERE t.deleted_at IS NULL AND COALESCE(t.status, 'actif') <> 'supprime'
         ORDER BY t.id DESC"
    );
    $rows = [];
    foreach ($items as $tenant) {
        $rows[] = [
            e(entity_reference($tenant, 'LOC')),
            a_link('app/locataires/' . $tenant['id'], full_name($tenant)),
            e($tenant['phone']), e($tenant['title'] ?: '—'), e($tenant['contract_reference'] ?: '—'), e(dfr($tenant['start_date'])),
            user()['role'] === 'admin' ? post_btn_confirm(
                'app/locataires/' . $tenant['id'] . '/supprimer',
                'Supprimer',
                'Supprimer ce locataire de la liste ? Son historique contractuel sera conservé.',
                'btn btn-danger btn-sm'
            ) : '',
        ];
    }
    list_page('Locataires', ['Référence', 'Nom', 'Téléphone', 'Bien occupé', 'Contrat actif', 'Entrée', 'Actions'], $rows, ['create' => 'app/locataires/nouveau']);
}

function app_tenant_form(?int $id = null): void
{
    require_auth();
    $tenant = $id ? db()->one('SELECT * FROM tenants WHERE id = ?', [$id]) : null;
    if ($id && !$tenant) {
        abort(404, 'Locataire introuvable.');
    }
    if (is_post()) {
        csrf_verify();
        try {
            // Le bien et le contrat ne sont jamais saisis ici : ils sont attribués par le module Contrats.
            $data = [
                'first_name' => str_input('first_name'), 'prenom' => str_input('first_name'),
                'last_name' => str_input('last_name'), 'nom' => str_input('last_name'),
                'phone' => str_input('phone'), 'telephone' => str_input('phone'), 'whatsapp' => str_input('whatsapp'),
                'email' => str_input('email'), 'id_number' => str_input('id_number'),
                'notes' => str_input('notes'),
            ];
            if ($id) {
                update_existing('tenants', $data, 'id = ?', [$id]);
                log_activity('locataire_modifie', 'tenants', $id);
            } else {
                $data['reference'] = numbered_reference('tenants', 'LOC');
                $data['property_id'] = null;
                $data['contract_id'] = null;
                $data['entry_date'] = null;
                $data['created_at'] = now();
                $id = insert_existing('tenants', $data);
                log_activity('locataire_cree', 'tenants', $id);
            }
            flash('success', 'Locataire enregistré. Le rattachement à un bien se fera par un contrat.');
            redirect('app/locataires/' . $id);
        } catch (Throwable $e) {
            flash('error', 'Locataire : ' . $e->getMessage());
            redirect($id ? 'app/locataires/' . $id : 'app/locataires/nouveau');
        }
    }

    sync_expired_contracts();
    $contracts = $id ? db()->all(
        'SELECT c.*, p.title, p.reference property_reference, o.id owner_record_id, o.reference owner_reference, o.first_name owner_first_name, o.last_name owner_last_name FROM contracts c LEFT JOIN properties p ON p.id=c.property_id LEFT JOIN owners o ON o.id=c.owner_id WHERE c.tenant_id=? ORDER BY c.start_date DESC, c.id DESC',
        [$id]
    ) : [];
    $active = null;
    foreach ($contracts as $contract) {
        if ($contract['status'] === 'actif') { $active = $contract; break; }
    }

    ob_start();
    if ($tenant) {
        echo '<div class="full"><span class="badge badge-navy">' . e(entity_reference($tenant, 'LOC')) . '</span></div>';
    }
    echo fld('first_name', 'Prénom', 'text', $tenant['first_name'] ?? '', 'req');
    echo fld('last_name', 'Nom', 'text', $tenant['last_name'] ?? '', 'req');
    echo fld('phone', 'Téléphone', 'tel', $tenant['phone'] ?? '');
    echo fld('whatsapp', 'WhatsApp', 'tel', $tenant['whatsapp'] ?? '');
    echo fld('email', 'E-mail', 'email', $tenant['email'] ?? '');
    echo fld('id_number', 'Pièce d’identité', 'text', $tenant['id_number'] ?? '');
    echo fld('notes', 'Notes', 'textarea', $tenant['notes'] ?? '', 'full');
    $fields = ob_get_clean();

    $extra = '';
    if ($tenant) {
        $extra .= '<div class="panel" style="margin-top:18px;max-width:1050px"><div class="section-head"><div><h3>Occupation actuelle</h3><p class="muted">Locataire → Contrat → Bien</p></div>';
        if (!$active) {
            $extra .= '<a class="btn btn-gold btn-sm" href="' . e(base_url('app/contrats/nouveau?tenant_id=' . $id)) . '">Créer un contrat</a>';
        }
        $extra .= '</div>';
        if ($active) {
            $extra .= '<p><strong>Bien :</strong> <a href="' . e(base_url('app/biens/' . $active['property_id'])) . '">' . e($active['property_reference'] . ' – ' . $active['title']) . '</a><br>';
            $extra .= '<strong>Contrat :</strong> <a href="' . e(base_url('app/contrats/' . $active['id'])) . '">' . e($active['reference']) . '</a><br>';
            $extra .= '<strong>Propriétaire :</strong> <a href="' . e(base_url('app/proprietaires/' . $active['owner_record_id'])) . '">' . e(($active['owner_reference'] ?: 'PROP-' . str_pad((string)$active['owner_record_id'],4,'0',STR_PAD_LEFT)) . ' – ' . trim($active['owner_first_name'] . ' ' . $active['owner_last_name'])) . '</a></p>';
        } else {
            $extra .= '<p class="muted">Aucun contrat actif. Ce locataire n’est rattaché à aucun bien.</p>';
        }
        $extra .= '</div>';

        $extra .= '<div class="panel" style="margin-top:18px;max-width:1050px"><h3>Historique des contrats</h3><div class="table-wrap"><table><thead><tr><th>Contrat</th><th>Bien</th><th>Propriétaire</th><th>Période</th><th>Statut</th></tr></thead><tbody>';
        foreach ($contracts as $contract) {
            $extra .= '<tr><td><a href="' . e(base_url('app/contrats/' . $contract['id'])) . '">' . e($contract['reference']) . '</a></td><td>' . e($contract['property_reference'] . ' – ' . $contract['title']) . '</td><td>' . e(trim($contract['owner_first_name'] . ' ' . $contract['owner_last_name'])) . '</td><td>' . e(dfr($contract['start_date']) . ' → ' . dfr($contract['end_date'])) . '</td><td>' . status_badge($contract['status']) . '</td></tr>';
        }
        if (!$contracts) { $extra .= '<tr><td class="empty" colspan="5">Aucun contrat.</td></tr>'; }
        $extra .= '</tbody></table></div></div>';
        if (user()['role'] === 'admin') {
            $extra .= '<div class="panel" style="margin-top:18px;max-width:1050px;border-color:#ef4444"><h3>Supprimer ce locataire</h3><p class="muted">Possible lorsqu’il ne possède plus de contrat actif. Son historique restera conservé.</p>'
                . post_btn_confirm('app/locataires/' . $id . '/supprimer', 'Supprimer le locataire', 'Confirmer la suppression de ce locataire ?', 'btn btn-danger')
                . '</div>';
        }
    }
    view('app/form', ['title' => $tenant ? 'Locataire · ' . full_name($tenant) : 'Nouveau locataire', 'fields' => $fields, 'back' => 'app/locataires', 'extra' => $extra], 'app');
}


function app_tenant_delete(int $id): void
{
    require_role(['admin']);
    csrf_verify();
    $tenant = db()->one('SELECT * FROM tenants WHERE id = ?', [$id]);
    if (!$tenant) {
        abort(404, 'Locataire introuvable.');
    }
    $activeContracts = (int) qtry_val("SELECT COUNT(*) FROM contracts WHERE tenant_id = ? AND status = 'actif'", [$id], 0);
    if ($activeContracts > 0) {
        flash('error', 'Ce locataire possède encore un contrat actif. Clôturez ou résiliez d’abord le contrat.');
        redirect('app/locataires/' . $id);
    }
    update_existing('tenants', [
        'status' => 'supprime',
        'deleted_at' => now(),
        'property_id' => null,
        'contract_id' => null,
    ], 'id = ?', [$id]);
    if (!empty($tenant['user_id'])) {
        $account = db()->one('SELECT role FROM users WHERE id = ?', [$tenant['user_id']]);
        if (($account['role'] ?? '') === 'tenant') {
            update_existing('users', ['status' => 'inactif', 'updated_at' => now()], 'id = ?', [$tenant['user_id']]);
        }
    }
    log_activity('locataire_supprime', 'tenants', $id, $tenant, ['status' => 'supprime']);
    flash('success', 'Locataire supprimé de la liste. Son historique contractuel est conservé.');
    redirect('app/locataires');
}

function generate_rents(int $contractId): void
{
    $contract = db()->one('SELECT * FROM contracts WHERE id = ?', [$contractId]);
    if (!$contract || (int) qtry_val('SELECT COUNT(*) FROM rents WHERE contract_id = ?', [$contractId], 0) > 0) {
        return;
    }
    $start = new DateTimeImmutable($contract['start_date']);
    $end = new DateTimeImmutable($contract['end_date']);
    if ($end < $start) {
        throw new InvalidArgumentException('La date de fin doit être postérieure à la date de début.');
    }
    $months = match ($contract['periodicity'] ?? 'mensuel') {
        'trimestriel' => 3,
        'semestriel' => 6,
        'annuel' => 12,
        default => 1,
    };
    $cursor = $start;
    while ($cursor <= $end) {
        $next = $cursor->modify('+' . $months . ' months');
        $periodEnd = $next->modify('-1 day');
        if ($periodEnd > $end) {
            $periodEnd = $end;
        }
        $due = $cursor->modify('+4 days');
        $status = $cursor->format('Y-m-d') > today() ? 'a_venir' : 'a_payer';
        insert_existing('rents', [
            'contract_id' => $contract['id'], 'property_id' => $contract['property_id'], 'owner_id' => $contract['owner_id'],
            'tenant_id' => $contract['tenant_id'], 'period_start' => $cursor->format('Y-m-d'), 'period_end' => $periodEnd->format('Y-m-d'),
            'due_date' => $due->format('Y-m-d'), 'amount' => $contract['rent'], 'paid_amount' => 0,
            'currency' => $contract['currency'], 'status' => $status, 'created_at' => now(),
        ]);
        $cursor = $next;
    }
}

function app_contracts(): void
{
    require_can('contracts.view');
    sync_expired_contracts();
    $items = db()->all(
        'SELECT c.*, p.title, p.reference property_reference, t.reference tenant_reference, t.first_name, t.last_name, o.reference owner_reference, o.first_name owner_first_name, o.last_name owner_last_name FROM contracts c LEFT JOIN properties p ON p.id=c.property_id LEFT JOIN tenants t ON t.id=c.tenant_id LEFT JOIN owners o ON o.id=c.owner_id ORDER BY c.id DESC'
    );
    $rows = [];
    foreach ($items as $contract) {
        $rows[] = [
            a_link('app/contrats/' . $contract['id'], $contract['reference']),
            e($contract['property_reference'] . ' – ' . $contract['title']),
            e(($contract['tenant_reference'] ?: 'LOC-' . str_pad((string)$contract['tenant_id'], 4, '0', STR_PAD_LEFT)) . ' – ' . full_name($contract)),
            e(trim($contract['owner_first_name'] . ' ' . $contract['owner_last_name'])),
            e(dfr($contract['start_date']) . ' → ' . dfr($contract['end_date'])),
            e(money($contract['rent'], $contract['currency'])),
            e(contract_periodicities()[$contract['periodicity']] ?? $contract['periodicity']),
            status_badge($contract['status']),
            a_link('app/contrats/' . $contract['id'], 'Visualiser'),
        ];
    }
    list_page('Contrats', ['Réf', 'Bien', 'Locataire', 'Propriétaire', 'Période', 'Loyer', 'Fréquence', 'Statut', ''], $rows, ['create' => 'app/contrats/nouveau']);
}

function app_contract_new(): void
{
    require_can('contracts.*');
    if (user()['role'] === 'agent') {
        abort(403, 'Lecture seule sur les contrats.');
    }
    sync_expired_contracts();
    if (is_post()) {
        csrf_verify();
        try {
            $propertyId = int_input('property_id');
            $tenantId = int_input('tenant_id');
            $property = db()->one('SELECT * FROM properties WHERE id = ?', [$propertyId]);
            $tenant = db()->one('SELECT * FROM tenants WHERE id = ?', [$tenantId]);
            if (!$property || !$tenant) {
                throw new InvalidArgumentException('Le bien et le locataire sont obligatoires.');
            }
            if (empty($property['owner_id']) || !db()->one('SELECT id FROM owners WHERE id = ?', [$property['owner_id']])) {
                throw new InvalidArgumentException('Ce bien ne possède pas de propriétaire valide. Modifiez d’abord la fiche du bien.');
            }
            if (in_array($property['status'], ['maintenance', 'archive', 'indisponible'], true)) {
                throw new InvalidArgumentException('Ce bien ne peut pas recevoir de contrat dans son statut actuel.');
            }
            if ((int) db()->val("SELECT COUNT(*) FROM contracts WHERE property_id = ? AND status = 'actif'", [$propertyId]) > 0) {
                throw new InvalidArgumentException('Ce bien possède déjà un contrat actif.');
            }
            if ((int) db()->val("SELECT COUNT(*) FROM contracts WHERE tenant_id = ? AND status = 'actif'", [$tenantId]) > 0) {
                throw new InvalidArgumentException('Ce locataire possède déjà un contrat actif.');
            }
            $startDate = str_input('start_date');
            $endDate = str_input('end_date');
            if (!$startDate || !$endDate || strtotime($endDate) < strtotime($startDate)) {
                throw new InvalidArgumentException('La période du contrat est invalide.');
            }
            $periodicity = str_input('periodicity') ?: 'mensuel';
            if (!array_key_exists($periodicity, contract_periodicities())) {
                throw new InvalidArgumentException('La fréquence du loyer est invalide.');
            }
            $rent = float_input('rent');
            if ($rent <= 0) {
                throw new InvalidArgumentException('Le montant du loyer doit être supérieur à zéro.');
            }

            db()->begin();
            $id = insert_existing('contracts', [
                'reference' => numbered_reference('contracts', 'CTR'),
                'property_id' => $propertyId,
                'owner_id' => $property['owner_id'],
                'tenant_id' => $tenantId,
                'agent_id' => $property['agent_commercial_id'] ?? null,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'rent' => $rent,
                'deposit' => float_input('deposit'),
                'currency' => str_input('currency') ?: 'USD',
                'periodicity' => $periodicity,
                'commission_rate' => float_input('commission_rate', (float) ($property['commission_rate'] ?? 10)),
                'conditions' => str_input('conditions'),
                'status' => 'actif',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            update_existing('tenants', ['property_id' => $propertyId, 'contract_id' => $id, 'entry_date' => $startDate], 'id = ?', [$tenantId]);
            update_existing('properties', ['status' => 'occupe', 'updated_at' => now()], 'id = ?', [$propertyId]);
            generate_rents($id);

            $commercialAgent = $property['agent_commercial_id'] ?? null;
            if ($commercialAgent) {
                $rate = (float) setting('commission_commercial', 40);
                insert_existing('commissions', [
                    'type' => 'commercial', 'agent_id' => $commercialAgent, 'property_id' => $propertyId, 'contract_id' => $id,
                    'base_amount' => $rent, 'rate' => $rate, 'amount' => $rent * $rate / 100,
                    'status' => 'generee', 'created_at' => now(),
                ]);
            }
            if (!empty($property['agent_apporteur_id'])) {
                $rate = (float) setting('commission_apporteur', 30);
                insert_existing('commissions', [
                    'type' => 'apporteur', 'agent_id' => $property['agent_apporteur_id'], 'property_id' => $propertyId, 'contract_id' => $id,
                    'base_amount' => $rent, 'rate' => $rate, 'amount' => $rent * $rate / 100,
                    'status' => 'generee', 'created_at' => now(),
                ]);
            }
            log_activity('contrat_cree', 'contracts', $id, null, ['property_id' => $propertyId, 'tenant_id' => $tenantId, 'owner_id' => $property['owner_id']]);
            db()->commit();
            flash('success', 'Contrat activé : le locataire et le bien sont liés, le bien est occupé et les échéances sont générées.');
            redirect('app/contrats/' . $id);
        } catch (Throwable $e) {
            if (db()->pdo()->inTransaction()) {
                db()->rollBack();
            }
            remember_old($_POST);
            flash('error', 'Contrat : ' . $e->getMessage());
            redirect('app/contrats/nouveau');
        }
    }

    $properties = db()->all(
        "SELECT p.id, p.reference, p.title, p.rent, p.deposit, p.currency, p.owner_id, o.reference owner_reference, o.first_name owner_first_name, o.last_name owner_last_name
         FROM properties p JOIN owners o ON o.id = p.owner_id
         WHERE o.deleted_at IS NULL AND COALESCE(o.status, 'actif') <> 'supprime'
           AND p.status IN ('disponible','reserve','visite_en_cours','publie')
           AND NOT EXISTS (SELECT 1 FROM contracts c WHERE c.property_id=p.id AND c.status='actif')
         ORDER BY p.title"
    );
    $tenants = db()->all(
        "SELECT t.id, t.reference, t.first_name, t.last_name FROM tenants t
         WHERE t.deleted_at IS NULL AND COALESCE(t.status, 'actif') <> 'supprime'
           AND NOT EXISTS (SELECT 1 FROM contracts c WHERE c.tenant_id=t.id AND c.status='actif')
         ORDER BY t.first_name, t.last_name"
    );
    $propertyOptions = ['' => $properties ? 'Sélectionner un bien' : 'Aucun bien disponible avec propriétaire'];
    $propertyMap = [];
    foreach ($properties as $property) {
        $ownerName = trim($property['owner_first_name'] . ' ' . $property['owner_last_name']);
        $propertyOptions[$property['id']] = $property['reference'] . ' – ' . $property['title'] . ' · Propriétaire : ' . $ownerName;
        $propertyMap[$property['id']] = [
            'owner' => ($property['owner_reference'] ?: 'PROP-' . str_pad((string)$property['owner_id'], 4, '0', STR_PAD_LEFT)) . ' – ' . $ownerName,
            'rent' => $property['rent'], 'deposit' => $property['deposit'], 'currency' => $property['currency'],
        ];
    }
    $tenantOptions = ['' => $tenants ? 'Sélectionner un locataire' : 'Aucun locataire disponible'];
    foreach ($tenants as $tenant) {
        $tenantOptions[$tenant['id']] = entity_reference($tenant, 'LOC') . ' – ' . full_name($tenant);
    }
    $selectedPropertyId = int_input('property_id');
    $selectedTenantId = int_input('tenant_id');
    $selectedProperty = $propertyMap[$selectedPropertyId] ?? null;

    ob_start();
    echo sel('property_id', $propertyOptions, old('property_id', $selectedPropertyId), 'Bien concerné *', false, true);
    echo sel('tenant_id', $tenantOptions, old('tenant_id', $selectedTenantId), 'Locataire *', false, true);
    echo '<label class="fld full">Propriétaire du bien (automatique)<input id="contract-owner" value="' . e($selectedProperty['owner'] ?? 'Sélectionnez un bien') . '" readonly></label>';
    echo fld('start_date', 'Date de début', 'date', old('start_date', today()), 'req');
    echo fld('end_date', 'Date de fin', 'date', old('end_date', date('Y-m-d', strtotime('+1 year'))), 'req');
    echo fld('rent', 'Montant du loyer par échéance', 'number', old('rent', $selectedProperty['rent'] ?? ''), 'req');
    echo sel('periodicity', contract_periodicities(), old('periodicity', 'mensuel'), 'Fréquence du loyer *', false, true);
    echo fld('deposit', 'Caution', 'number', old('deposit', $selectedProperty['deposit'] ?? ''));
    echo fld('currency', 'Devise', 'text', old('currency', $selectedProperty['currency'] ?? 'USD'));
    echo fld('commission_rate', 'Commission YOUPENDI %', 'number', old('commission_rate', setting('commission_mgmt', 10)));
    echo fld('conditions', 'Autres conditions', 'textarea', old('conditions'), 'full');
    $fields = ob_get_clean();
    $mapJson = json_encode($propertyMap, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $extra = '<script>(function(){const map=' . $mapJson . ', select=document.querySelector("[name=property_id]"), owner=document.getElementById("contract-owner");if(!select)return;select.addEventListener("change",function(){const p=map[this.value];owner.value=p?p.owner:"Sélectionnez un bien";if(p){const rent=document.querySelector("[name=rent]"),deposit=document.querySelector("[name=deposit]"),currency=document.querySelector("[name=currency]");if(!rent.value)rent.value=p.rent;if(!deposit.value)deposit.value=p.deposit;if(!currency.value)currency.value=p.currency;}});})();</script>';
    view('app/form', ['title' => 'Nouveau contrat', 'fields' => $fields, 'back' => 'app/contrats', 'extra' => $extra], 'app');
}

function app_contract_show(int $id): void
{
    require_can('contracts.view');
    sync_expired_contracts();
    $contract = db()->one(
        'SELECT c.*, p.reference property_reference, p.title, p.status property_status, t.reference tenant_reference, t.first_name tenant_first_name, t.last_name tenant_last_name, o.reference owner_reference, o.first_name owner_first_name, o.last_name owner_last_name FROM contracts c LEFT JOIN properties p ON p.id=c.property_id LEFT JOIN tenants t ON t.id=c.tenant_id LEFT JOIN owners o ON o.id=c.owner_id WHERE c.id=?',
        [$id]
    );
    if (!$contract) {
        abort(404, 'Contrat introuvable.');
    }
    $rents = db()->all('SELECT * FROM rents WHERE contract_id=? ORDER BY period_start', [$id]);
    ob_start(); ?>
    <div class="section-head">
      <div><p class="kicker gold">Contrat</p><h1 class="app-title"><?= e($contract['reference']) ?></h1></div>
      <div class="row-actions"><?= status_badge($contract['status']) ?><a class="btn btn-outline btn-sm" href="<?= e(base_url('app/contrats')) ?>">Retour</a></div>
    </div>
    <div class="prop-grid">
      <div class="panel">
        <h3>Relations contractuelles</h3>
        <p><strong>Locataire :</strong> <a href="<?= e(base_url('app/locataires/'.$contract['tenant_id'])) ?>"><?= e(($contract['tenant_reference'] ?: 'LOC-'.str_pad((string)$contract['tenant_id'],4,'0',STR_PAD_LEFT)).' – '.trim($contract['tenant_first_name'].' '.$contract['tenant_last_name'])) ?></a></p>
        <p><strong>Bien :</strong> <a href="<?= e(base_url('app/biens/'.$contract['property_id'])) ?>"><?= e($contract['property_reference'].' – '.$contract['title']) ?></a></p>
        <p><strong>Propriétaire :</strong> <a href="<?= e(base_url('app/proprietaires/'.$contract['owner_id'])) ?>"><?= e(($contract['owner_reference'] ?: 'PROP-'.str_pad((string)$contract['owner_id'],4,'0',STR_PAD_LEFT)).' – '.trim($contract['owner_first_name'].' '.$contract['owner_last_name'])) ?></a></p>
      </div>
      <div class="panel">
        <h3>Conditions</h3>
        <p><strong>Période :</strong> <?= e(dfr($contract['start_date']).' → '.dfr($contract['end_date'])) ?><br>
        <strong>Loyer :</strong> <?= e(money($contract['rent'], $contract['currency'])) ?> · <?= e(contract_periodicities()[$contract['periodicity']] ?? $contract['periodicity']) ?><br>
        <strong>Caution :</strong> <?= e(money($contract['deposit'], $contract['currency'])) ?></p>
        <p><?= nl2br(e($contract['conditions'])) ?></p>
        <?php if (!empty($contract['termination_reason'])): ?><p><strong>Motif de clôture :</strong> <?= e($contract['termination_reason']) ?></p><?php endif; ?>
      </div>
    </div>
    <?php if ($contract['status']==='actif' && (can('contracts.*') || user()['role']==='admin')): ?>
      <div class="panel" style="margin-top:18px;max-width:720px">
        <h3>Fin ou résiliation du contrat</h3>
        <p class="muted">L’historique sera conservé, le locataire sera retiré comme occupant actuel et le bien redeviendra disponible, sauf s’il est en maintenance.</p>
        <form method="post" action="<?= e(base_url('app/contrats/'.$id.'/cloturer')) ?>">
          <?= csrf_field() ?>
          <div class="form-grid">
            <?= sel('close_status', ['termine'=>'Fin normale','resilie'=>'Résiliation'], 'termine', 'Type de clôture', false, true) ?>
            <?= fld('reason', 'Motif', 'text', '', 'req') ?>
          </div>
          <button class="btn btn-danger" onclick="return confirm('Clôturer ce contrat ?')">Clôturer le contrat</button>
        </form>
      </div>
    <?php endif; ?>
    <div class="panel" style="margin-top:18px">
      <h3>Échéances de loyer</h3>
      <div class="table-wrap"><table><thead><tr><th>Période</th><th>Échéance</th><th>Montant</th><th>Payé</th><th>Statut</th></tr></thead><tbody>
      <?php foreach ($rents as $rent): ?><tr><td><?= e(dfr($rent['period_start']).' → '.dfr($rent['period_end'])) ?></td><td><?= e(dfr($rent['due_date'])) ?></td><td><?= e(money($rent['amount'],$rent['currency'])) ?></td><td><?= e(money($rent['paid_amount'],$rent['currency'])) ?></td><td><?= status_badge($rent['status']) ?></td></tr><?php endforeach; ?>
      <?php if (!$rents): ?><tr><td colspan="5" class="empty">Aucune échéance.</td></tr><?php endif; ?>
      </tbody></table></div>
    </div>
    <?php
    view('app/raw', ['title' => $contract['reference'], 'html' => ob_get_clean()], 'app');
}

function app_contract_close(int $id): void
{
    require_can('contracts.*');
    csrf_verify();
    $status = str_input('close_status') === 'resilie' ? 'resilie' : 'termine';
    $reason = str_input('reason');
    if ($reason === '') {
        flash('error', 'Le motif de clôture est obligatoire.');
        redirect('app/contrats/' . $id);
    }
    if (close_contract($id, $status, $reason)) {
        flash('success', 'Contrat clôturé. Le bien et le locataire ont été mis à jour sans supprimer l’historique.');
    } else {
        flash('info', 'Ce contrat était déjà clôturé.');
    }
    redirect('app/contrats/' . $id);
}


function refresh_rent_statuses(): void
{
    db()->exec("UPDATE rents SET status = 'en_retard' WHERE status IN ('a_payer','partiel') AND due_date < ? AND paid_amount < amount", [today()]);
    db()->exec("UPDATE rents SET status = 'a_payer' WHERE status = 'a_venir' AND period_start <= ?", [today()]);
}

function app_rents(): void
{
    require_auth();
    refresh_rent_statuses();
    $w = ['1=1'];
    $params = [];
    if ($s = str_input('status')) {
        if ($s === 'en_retard') {
            $w[] = "r.status IN ('en_retard')";
        } else {
            $w[] = 'r.status = ?';
            $params[] = $s;
        }
    }
    $items = db()->all('SELECT r.*, p.title, t.first_name, t.last_name FROM rents r LEFT JOIN properties p ON p.id=r.property_id LEFT JOIN tenants t ON t.id=r.tenant_id WHERE ' . implode(' AND ', $w) . ' ORDER BY r.due_date DESC', $params);
    if (str_input('export') === 'csv') {
        csv_download('loyers.csv', ['Bien', 'Locataire', 'Période', 'Montant', 'Payé', 'Statut'], array_map(fn($r) => [$r['title'], full_name($r), $r['period_start'], $r['amount'], $r['paid_amount'], $r['status']], $items));
    }
    $rows = [];
    foreach ($items as $r) {
        $pay = (can('payments.*') || user()['role'] === 'admin') && $r['status'] !== 'paye'
            ? a_link('app/loyers/' . $r['id'] . '/payer', 'Encaisser') : '';
        $rows[] = [
            e($r['title']), e(full_name($r)), e(dfr($r['period_start'], 'm/Y')),
            e(money($r['amount'])), e(money($r['paid_amount'])),
            status_badge($r['status']), e(dfr($r['due_date'])), $pay,
        ];
    }
    list_page('Loyers', ['Bien', 'Locataire', 'Période', 'Dû', 'Payé', 'Statut', 'Échéance', ''], $rows, [
        'export' => true,
        'filters' => ['status' => ['' => 'Statut'] + rent_statuses()],
    ]);
}

function app_rent_pay(int $id): void
{
    require_can('payments.*');
    $r = db()->one('SELECT * FROM rents WHERE id = ?', [$id]);
    if (!$r) {
        abort(404, 'Échéance introuvable');
    }
    if (is_post()) {
        csrf_verify();
        $amount = float_input('amount');
        $auto = user()['role'] === 'admin' || user()['role'] === 'finance';
        $pid = insert_existing('payments', [
            'rent_id' => $id, 'amount' => $amount, 'method' => str_input('method'),
            'reference' => str_input('reference'), 'paid_at' => str_input('paid_at') ?: today(),
            'status' => $auto ? 'confirme' : 'en_attente_paiement',
            'confirmed_by' => $auto ? user()['id'] : null,
            'confirmed_at' => $auto ? now() : null,
            'notes' => str_input('notes'),
            'created_by' => user()['id'], 'created_at' => now(),
        ]);
        if ($auto) {
            confirm_payment($pid);
        }
        flash('success', $auto ? 'Paiement confirmé, quittance générée.' : 'Paiement enregistré — en attente de validation finance.');
        redirect('app/loyers');
    }
    $reste = max(0, (float) $r['amount'] - (float) $r['paid_amount']);
    ob_start();
    echo fld('amount', 'Montant', 'number', $reste, 'req');
    echo sel('method', ['cash' => 'Espèces', 'virement' => 'Virement', 'mobile_money' => 'Mobile Money', 'cheque' => 'Chèque'], 'mobile_money', 'Mode');
    echo fld('reference', 'Référence', 'text');
    echo fld('paid_at', 'Date', 'date', today());
    echo fld('notes', 'Notes', 'textarea', '', 'full');
    view('app/form', ['title' => 'Encaisser un loyer', 'fields' => ob_get_clean(), 'back' => 'app/loyers'], 'app');
}

function confirm_payment(int $paymentId): void
{
    $pay = db()->one('SELECT * FROM payments WHERE id = ?', [$paymentId]);
    $r = db()->one('SELECT * FROM rents WHERE id = ?', [$pay['rent_id']]);
    $paid = (float) $r['paid_amount'] + (float) $pay['amount'];
    $st = $paid >= (float) $r['amount'] - 0.01 ? 'paye' : 'partiel';
    update_existing('rents', ['paid_amount' => $paid, 'status' => $st], 'id = ?', [$r['id']]);
    update_existing('payments', ['status' => 'confirme', 'confirmed_by' => user()['id'] ?? $pay['confirmed_by'], 'confirmed_at' => now()], 'id = ?', [$paymentId]);
    $c = db()->one('SELECT * FROM contracts WHERE id = ?', [$r['contract_id']]);
    insert_existing('receipts', [
        'number' => ref_code('Q'),
        'payment_id' => $paymentId, 'rent_id' => $r['id'], 'tenant_id' => $r['tenant_id'],
        'property_id' => $r['property_id'], 'period_label' => date('m/Y', strtotime($r['period_start'])),
        'amount' => $pay['amount'], 'method' => $pay['method'], 'issued_at' => $pay['paid_at'], 'created_at' => now(),
    ]);
    $rate = (float) ($c['commission_rate'] ?? setting('commission_mgmt', 10));
    insert_existing('commissions', [
        'type' => 'gestion_youpendi', 'property_id' => $r['property_id'], 'contract_id' => $r['contract_id'],
        'payment_id' => $paymentId, 'base_amount' => $pay['amount'], 'rate' => $rate,
        'amount' => $pay['amount'] * $rate / 100, 'status' => 'validee', 'created_at' => now(),
    ]);
    log_activity('paiement_confirme', 'payments', $paymentId, null, $pay['amount']);
}

function app_payments(): void
{
    require_auth();
    $items = db()->all('SELECT p.*, r.period_start FROM payments p LEFT JOIN rents r ON r.id = p.rent_id ORDER BY p.id DESC');
    $rows = [];
    foreach ($items as $p) {
        $act = $p['status'] !== 'confirme' && (can('payments.*')) ? post_btn('app/paiements/' . $p['id'] . '/confirmer', 'Valider') : '';
        $rows[] = [e(dfr($p['paid_at'])), e(money($p['amount'])), e($p['method']), e($p['reference']), status_badge($p['status']), $act];
    }
    list_page('Encaissements', ['Date', 'Montant', 'Mode', 'Réf', 'Statut', ''], $rows);
}

function app_payment_confirm(int $id): void
{
    require_can('payments.*');
    csrf_verify();
    confirm_payment($id);
    flash('success', 'Paiement validé.');
    redirect('app/paiements');
}

function app_receipts(): void
{
    require_auth();
    $items = db()->all('SELECT q.*, t.first_name, t.last_name, p.title FROM receipts q LEFT JOIN tenants t ON t.id=q.tenant_id LEFT JOIN properties p ON p.id=q.property_id ORDER BY q.id DESC');
    $rows = [];
    foreach ($items as $q) {
        $rows[] = [e($q['number']), e(full_name($q)), e($q['title']), e($q['period_label']), e(money($q['amount'])), a_link('quittance/' . $q['id'], 'Imprimer')];
    }
    list_page('Quittances', ['N°', 'Locataire', 'Bien', 'Période', 'Montant', ''], $rows);
}

function print_receipt(int $id): void
{
    require_auth();
    $q = db()->one('SELECT q.*, t.first_name, t.last_name, p.title, p.address_public, p.city FROM receipts q LEFT JOIN tenants t ON t.id=q.tenant_id LEFT JOIN properties p ON p.id=q.property_id WHERE q.id = ?', [$id]);
    if (!$q) {
        abort(404, 'Quittance introuvable');
    }
    if (user()['role'] === 'tenant') {
        $tn = tenant_of();
        if (!$tn || (int) $q['tenant_id'] !== (int) $tn['id']) {
            abort(403, 'Interdit');
        }
    }
    view('pdf/receipt', ['q' => $q, 'title' => 'Quittance ' . $q['number']], null);
}

function app_commissions(): void
{
    require_auth();
    $w = ['1=1'];
    $params = [];
    if (user()['role'] === 'agent') {
        $a = agent_of();
        $w[] = 'c.agent_id = ?';
        $params[] = $a['id'] ?? 0;
    }
    $items = db()->all('SELECT c.*, u.first_name, u.last_name, p.title FROM commissions c LEFT JOIN immo_agents a ON a.id=c.agent_id LEFT JOIN users u ON u.id=a.user_id LEFT JOIN properties p ON p.id=c.property_id WHERE ' . implode(' AND ', $w) . ' ORDER BY c.id DESC', $params);
    if (str_input('export') === 'csv') {
        csv_download('commissions.csv', ['Type', 'Agent', 'Montant', 'Statut'], array_map(fn($c) => [$c['type'], full_name($c), $c['amount'], $c['status']], $items));
    }
    $rows = [];
    foreach ($items as $c) {
        $act = '';
        if (can('commissions.*') && $c['status'] === 'generee') {
            $act = post_btn('app/commissions/' . $c['id'] . '/valider', 'Valider');
        }
        $rows[] = [e($c['type']), e(full_name($c) ?: 'YOUPENDI'), e($c['title']), e($c['rate'] . ' %'), e(money($c['amount'])), status_badge($c['status']), $act];
    }
    list_page('Commissions', ['Type', 'Bénéficiaire', 'Bien', 'Taux', 'Montant', 'Statut', ''], $rows, ['export' => true]);
}

function app_commission_ok(int $id): void
{
    require_can('commissions.*');
    csrf_verify();
    update_existing('commissions', ['status' => 'validee', 'validated_by' => user()['id'], 'validated_at' => now()], 'id = ?', [$id]);
    log_activity('commission_validee', 'commissions', $id);
    flash('success', 'Commission validée.');
    redirect('app/commissions');
}

function app_payouts(): void
{
    require_auth();
    $items = db()->all('SELECT x.*, o.first_name, o.last_name FROM payouts x LEFT JOIN owners o ON o.id=x.owner_id ORDER BY x.id DESC');
    $rows = [];
    foreach ($items as $x) {
        $act = (can('payouts.*') && $x['status'] === 'a_preparer') ? post_btn('app/reversements/' . $x['id'] . '/payer', 'Marquer payé') : '';
        $rows[] = [e(full_name($x)), e(dfr($x['period_start'], 'm/Y')), e(money($x['collections'])), e(money($x['commission'])), e(money($x['expenses'])), e(money($x['net_amount'])), status_badge($x['status']), $act];
    }
    list_page('Reversements propriétaires', ['Propriétaire', 'Période', 'Encaissé', 'Commission', 'Dépenses', 'Net', 'Statut', ''], $rows, ['create' => 'app/reversements/nouveau', 'createLabel' => 'Préparer']);
}

function app_payout_new(): void
{
    require_can('payouts.*');
    if (is_post()) {
        csrf_verify();
        $oid = int_input('owner_id');
        $from = str_input('period_start');
        $to = str_input('period_end');
        $col = (float) db()->val("SELECT COALESCE(SUM(paid_amount),0) FROM rents WHERE owner_id = ? AND period_start >= ? AND period_end <= ? AND status IN ('paye','partiel')", [$oid, $from, $to]);
        $com = (float) db()->val("SELECT COALESCE(SUM(amount),0) FROM commissions WHERE type = 'gestion_youpendi' AND property_id IN (SELECT id FROM properties WHERE owner_id = ?) AND created_at >= ? AND created_at <= ?", [$oid, $from, $to . ' 23:59:59']);
        $exp = (float) db()->val("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE status = 'validee' AND property_id IN (SELECT id FROM properties WHERE owner_id = ?) AND expense_date >= ? AND expense_date <= ?", [$oid, $from, $to]);
        $net = $col - $com - $exp;
        insert_existing('payouts', [
            'owner_id' => $oid, 'period_start' => $from, 'period_end' => $to,
            'collections' => $col, 'commission' => $com, 'expenses' => $exp, 'net_amount' => $net,
            'status' => 'a_preparer', 'created_at' => now(),
        ]);
        flash('success', 'Reversement préparé. Net : ' . money($net));
        redirect('app/reversements');
    }
    $owners = [];
    foreach (db()->all('SELECT id, first_name, last_name FROM owners') as $o) {
        $owners[$o['id']] = full_name($o);
    }
    ob_start();
    echo sel('owner_id', $owners, '', 'Propriétaire');
    echo fld('period_start', 'Du', 'date', date('Y-m-01'));
    echo fld('period_end', 'Au', 'date', date('Y-m-t'));
    view('app/form', ['title' => 'Préparer un reversement', 'fields' => ob_get_clean(), 'back' => 'app/reversements'], 'app');
}

function app_payout_pay(int $id): void
{
    require_can('payouts.*');
    csrf_verify();
    update_existing('payouts', ['status' => 'paye', 'paid_at' => today()], 'id = ?', [$id]);
    flash('success', 'Reversement marqué payé.');
    redirect('app/reversements');
}
