<?php

/**
 * YOUPENDI IMMO SELECT — routes CRM
 * Tableau de bord (§22), alertes (§23), actions rapides (§8),
 * historique (§9), matching (§19/§20) et propositions (§21).
 */

/** Garde d'accès CRM commune ; vérifie aussi la propriété du dossier pour un agent. */
function crm_guard(?array $prospect = null): array
{
    $u = require_auth();
    if (!can('prospects.*') && !can('prospects.own')) {
        abort(403, 'Accès CRM non autorisé.');
    }
    if ($prospect && $u['role'] === 'agent') {
        $assigned = (int) ($prospect['agent_id'] ?? 0);
        if ($assigned > 0 && $assigned !== (int) (agent_of()['id'] ?? 0)) {
            abort(403, 'Ce prospect est attribué à un autre agent.');
        }
    }
    return $u;
}

/** §22 / §23 — Tableau de bord CRM avec indicateurs et alertes. */
function app_crm_dashboard(): void
{
    crm_guard();

    $recent = qtry_all(
        'SELECT p.* FROM prospects p ORDER BY p.id DESC LIMIT 10'
    );
    $rows = [];
    foreach ($recent as $p) {
        $rows[] = [
            a_link('app/prospects/' . $p['id'], prospect_reference($p)),
            e(full_name($p) ?: 'Sans nom'),
            e(prospect_type_label($p['type'])),
            e(trim(($p['province'] ?? '') . ' · ' . $p['city'], ' ·')),
            e(prospect_source_label($p['source'])),
            status_badge($p['status']),
            e(crm_responsible_name($p) ?: 'Non attribué'),
            e(dfr($p['created_at'], 'd/m/Y H:i')),
        ];
    }

    ob_start(); ?>
    <div class="section-head">
      <div>
        <p class="kicker gold">CRM · Pilotage</p>
        <h1 class="app-title">Tableau de bord CRM</h1>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn btn-outline btn-sm" href="<?= e(base_url('app/prospects')) ?>">Tous les prospects</a>
        <a class="btn btn-gold btn-sm" href="<?= e(base_url('app/prospects/nouveau')) ?>">Nouveau prospect</a>
      </div>
    </div>
    <?= crm_kpis_html() ?>
    <?= crm_alerts_html() ?>
    <div class="table-wrap" style="margin-top:18px">
      <table>
        <thead><tr><th>Identifiant</th><th>Prospect</th><th>Type</th><th>Zone</th><th>Source</th><th>Statut</th><th>Responsable</th><th>Soumis le</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
          <tr><?php foreach ($row as $cell): ?><td><?= $cell ?></td><?php endforeach; ?></tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="8" class="empty">Aucun prospect pour l’instant.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php
    view('app/raw', ['html' => ob_get_clean()], 'app');
}

/** §8 — Affecter le prospect à un responsable. */
function app_prospect_assign(int $id): void
{
    $prospect = qtry_one('SELECT * FROM prospects WHERE id = ?', [$id]);
    if (!$prospect) {
        abort(404, 'Prospect introuvable.');
    }
    crm_guard($prospect);
    csrf_verify();

    $oldId = (int) ($prospect['agent_id'] ?? 0);
    $newId = int_input('agent_id');
    if ($oldId === $newId) {
        flash('info', 'Le responsable du dossier est inchangé.');
        redirect('app/prospects/' . $id);
    }
    update_existing('prospects', ['agent_id' => $newId ?: null, 'updated_at' => now()], 'id = ?', [$id]);

    $oldName = crm_responsible_name(['agent_id' => $oldId]) ?: 'Non attribué';
    $newName = crm_responsible_name(['agent_id' => $newId]) ?: 'Non attribué';
    crm_log_interaction($id, 'responsable', 'Responsable du dossier : ' . $oldName . ' → ' . $newName);
    log_activity('prospect_affecte', 'prospects', $id, ['agent_id' => $oldId], ['agent_id' => $newId]);

    flash('success', 'Prospect affecté à ' . $newName . '.');
    redirect('app/prospects/' . $id);
}

/** §8 / §9 — Enregistrer une interaction (note, appel, WhatsApp, e-mail, RDV, visite). */
function app_prospect_interaction(int $id): void
{
    $prospect = qtry_one('SELECT * FROM prospects WHERE id = ?', [$id]);
    if (!$prospect) {
        abort(404, 'Prospect introuvable.');
    }
    crm_guard($prospect);
    csrf_verify();

    $kind = str_input('kind', 'note');
    if (!array_key_exists($kind, crm_interaction_kinds())) {
        $kind = 'note';
    }
    $body = str_input('body');
    if ($body === '') {
        flash('error', 'Précisez le contenu de l’interaction.');
        redirect('app/prospects/' . $id);
    }
    $at = str_input('at');
    if ($at !== '') {
        $at = str_replace('T', ' ', $at);
        if (strlen($at) === 16) {
            $at .= ':00';
        }
    }
    insert_existing('prospect_notes', [
        'prospect_id' => $id,
        'user_id' => user()['id'] ?? null,
        'kind' => $kind,
        'body' => $body,
        'created_at' => $at ?: now(),
    ]);

    if (in_array($kind, ['rdv', 'visite'], true)) {
        $status = $kind === 'visite' ? 'rdv_prevu' : 'rdv_prevu';
        if (($prospect['status'] ?? '') === 'nouveau' || ($prospect['status'] ?? '') === 'a_contacter') {
            update_existing('prospects', ['status' => $status, 'updated_at' => now()], 'id = ?', [$id]);
            record_prospect_status($id, $prospect['status'] ?? null, $status, 'Rendez-vous programmé depuis la fiche prospect.');
        }
    }

    log_activity('prospect_interaction_' . $kind, 'prospects', $id);
    flash('success', crm_interaction_label($kind) . ' enregistré dans l’historique.');
    redirect('app/prospects/' . $id);
}

/** §19 / §20 — Biens compatibles avec la recherche d'un prospect. */
function app_prospect_matching(int $id): void
{
    $prospect = qtry_one('SELECT * FROM prospects WHERE id = ?', [$id]);
    if (!$prospect) {
        abort(404, 'Prospect introuvable.');
    }
    crm_guard($prospect);

    $matches = crm_matches_for_prospect($id, 30, 30);
    $rows = [];
    foreach ($matches as $match) {
        $property = $match['property'];
        $already = qtry_one(
            'SELECT id, status FROM prospect_proposals WHERE prospect_id = ? AND property_id = ? ORDER BY id DESC LIMIT 1',
            [$id, (int) $property['id']]
        );
        $action = $already
            ? '<span class="badge">Déjà proposé — ' . e(crm_proposal_status_label($already['status'])) . '</span>'
            : '<form method="post" action="' . e(base_url('app/prospects/' . $id . '/proposer')) . '" style="display:inline">'
                . csrf_field() . '<input type="hidden" name="property_id" value="' . (int) $property['id'] . '">'
                . '<button class="btn btn-sm btn-gold">Proposer ce bien</button></form>';
        $rows[] = [
            '<span class="badge ' . e(crm_score_class($match['score'])) . '">' . (int) $match['score'] . ' %</span>',
            a_link('app/biens/' . $property['id'], $property['title'] ?: 'Bien #' . $property['id']),
            e(trim(($property['province'] ?? '') . ' · ' . ($property['city'] ?? '') . ' · ' . ($property['quartier'] ?? ''), ' ·')),
            e(property_types()[$property['type']] ?? $property['type']),
            e((int) ($property['bedrooms'] ?? 0) . ' ch.'),
            e(money($property['rent'] ?? 0, $property['currency'] ?? 'USD')),
            '<details><summary>Détail du score</summary><ul>'
                . implode('', array_map(fn($k, $v) => '<li>' . e($k) . ' : ' . e($v) . '</li>', array_keys($match['detail']), $match['detail']))
                . '</ul></details>',
            $action,
        ];
    }

    list_page('Biens compatibles — ' . prospect_reference($prospect) . ' · ' . (full_name($prospect) ?: 'sans nom'),
        ['Score', 'Bien', 'Localisation', 'Type', 'Chambres', 'Loyer', 'Critères', 'Action'], $rows, [
            'kicker' => 'Matching CRM · ' . count($matches) . ' correspondance(s)',
            'back' => 'app/prospects/' . $id,
        ]);
}

/** §19 — Depuis un bien : locataires potentiellement intéressés. */
function app_property_matching(int $propertyId): void
{
    require_auth();
    if (!can('prospects.*') && !can('prospects.own')) {
        abort(403, 'Accès CRM non autorisé.');
    }
    $property = qtry_one('SELECT * FROM properties WHERE id = ?', [$propertyId]);
    if (!$property) {
        abort(404, 'Bien introuvable.');
    }

    $matches = crm_matches_for_property($propertyId, 30, 30);
    $rows = [];
    foreach ($matches as $match) {
        $prospect = $match['prospect'];
        $already = qtry_one(
            'SELECT id, status FROM prospect_proposals WHERE prospect_id = ? AND property_id = ? ORDER BY id DESC LIMIT 1',
            [(int) $prospect['id'], $propertyId]
        );
        $action = $already
            ? '<span class="badge">Déjà proposé — ' . e(crm_proposal_status_label($already['status'])) . '</span>'
            : '<form method="post" action="' . e(base_url('app/prospects/' . $prospect['id'] . '/proposer')) . '" style="display:inline">'
                . csrf_field() . '<input type="hidden" name="property_id" value="' . $propertyId . '">'
                . '<button class="btn btn-sm btn-gold">Proposer ce bien</button></form>';
        $rows[] = [
            '<span class="badge ' . e(crm_score_class($match['score'])) . '">' . (int) $match['score'] . ' %</span>',
            a_link('app/prospects/' . $prospect['id'], prospect_reference($prospect) . ' · ' . (full_name($prospect) ?: 'sans nom')),
            crm_contact_actions($prospect),
            e(trim(($prospect['province'] ?? '') . ' · ' . ($prospect['city'] ?? '') . ' · ' . ($prospect['quartier'] ?? ''), ' ·')),
            e(money($prospect['budget_max'] ?? 0)),
            e((int) ($prospect['bedrooms'] ?? 0) . ' ch.'),
            '<details><summary>Détail du score</summary><ul>'
                . implode('', array_map(fn($k, $v) => '<li>' . e($k) . ' : ' . e($v) . '</li>', array_keys($match['detail']), $match['detail']))
                . '</ul></details>',
            $action,
        ];
    }

    list_page('Locataires potentiellement intéressés — ' . ($property['title'] ?: 'Bien #' . $propertyId),
        ['Score', 'Prospect', 'Contact', 'Zone recherchée', 'Budget max.', 'Chambres', 'Critères', 'Action'], $rows, [
            'kicker' => 'Matching CRM · ' . count($matches) . ' correspondance(s)',
            'back' => 'app/biens/' . $propertyId,
        ]);
}

/** §21 — Proposer un bien à un prospect. */
function app_prospect_propose(int $id): void
{
    $prospect = qtry_one('SELECT * FROM prospects WHERE id = ?', [$id]);
    if (!$prospect) {
        abort(404, 'Prospect introuvable.');
    }
    crm_guard($prospect);
    csrf_verify();

    $propertyId = int_input('property_id');
    $property = qtry_one('SELECT id, title FROM properties WHERE id = ?', [$propertyId]);
    if (!$property) {
        flash('error', 'Bien introuvable.');
        redirect('app/prospects/' . $id);
    }

    crm_propose($id, $propertyId, str_input('note'));
    if (in_array($prospect['status'] ?? '', ['nouveau', 'a_contacter', 'contacte', 'qualifie'], true)) {
        update_existing('prospects', ['status' => 'negociation', 'updated_at' => now()], 'id = ?', [$id]);
        record_prospect_status($id, $prospect['status'] ?? null, 'negociation', 'Bien proposé : ' . $property['title']);
    }
    log_activity('prospect_proposition', 'prospects', $id, null, ['property_id' => $propertyId]);

    flash('success', 'Bien « ' . $property['title'] . ' » proposé au prospect.');
    redirect($_SERVER['HTTP_REFERER'] ?? base_url('app/prospects/' . $id));
}

/** §21 — Enregistrer la réponse du client à une proposition. */
function app_proposal_update(int $id): void
{
    require_auth();
    if (!can('prospects.*') && !can('prospects.own')) {
        abort(403, 'Accès CRM non autorisé.');
    }
    csrf_verify();

    $proposal = qtry_one('SELECT * FROM prospect_proposals WHERE id = ?', [$id]);
    if (!$proposal) {
        abort(404, 'Proposition introuvable.');
    }
    $status = str_input('status', 'envoye');
    if (!array_key_exists($status, crm_proposal_statuses())) {
        flash('error', 'Réponse invalide.');
        redirect('app/prospects/' . $proposal['prospect_id']);
    }
    update_existing('prospect_proposals', [
        'status' => $status,
        'responded_at' => now(),
        'note' => str_input('note') ?: (string) ($proposal['note'] ?? ''),
    ], 'id = ?', [$id]);

    $property = qtry_one('SELECT title FROM properties WHERE id = ?', [(int) $proposal['property_id']]);
    crm_log_interaction(
        (int) $proposal['prospect_id'],
        'proposition',
        'Réponse du client pour « ' . ($property['title'] ?? 'bien #' . (int) $proposal['property_id']) . ' » : '
            . crm_proposal_status_label($status)
    );

    if ($status === 'visite_demandee') {
        update_existing('prospects', ['status' => 'visite_prevue', 'updated_at' => now()], 'id = ?', [(int) $proposal['prospect_id']]);
    } elseif ($status === 'loue') {
        update_existing('prospects', ['status' => 'converti', 'updated_at' => now()], 'id = ?', [(int) $proposal['prospect_id']]);
    } elseif (in_array($status, ['refuse'], true)) {
        update_existing('prospects', ['status' => 'non_interesse', 'updated_at' => now()], 'id = ?', [(int) $proposal['prospect_id']]);
    }

    log_activity('proposition_reponse', 'prospect_proposals', $id, ['status' => $proposal['status']], ['status' => $status]);
    flash('success', 'Réponse enregistrée : ' . crm_proposal_status_label($status) . '.');
    redirect('app/prospects/' . $proposal['prospect_id']);
}
