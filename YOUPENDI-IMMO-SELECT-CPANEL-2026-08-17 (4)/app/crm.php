<?php

/**
 * YOUPENDI IMMO SELECT — module CRM
 *
 * Implémente le cahier des charges « Connexion des formulaires Confier mon bien /
 * Confier ma recherche au CRM » :
 *   §7  responsable du dossier (affectation automatique)
 *   §8  actions rapides
 *   §9  historique des interactions
 *   §10 anti-doublon (téléphone, WhatsApp, e-mail)
 *   §11 identifiant prospect (PROP-00001 / LOC-00001)
 *   §12 notification équipe
 *   §19 matching propriétaire ↔ locataire
 *   §20 score de compatibilité
 *   §21 proposer le bien
 *   §22 tableau de bord CRM
 *   §23 alertes
 */

/* ------------------------------------------------------------------ *
 * §11 — Identifiant prospect
 * ------------------------------------------------------------------ */

function prospect_reference_prefix(string $type): string
{
    return $type === 'proprietaire' ? 'PROP' : 'LOC';
}

/** Identifiant affiché : valeur stockée, sinon PROP-00001 / LOC-00001 dérivé de l'id. */
function prospect_reference(array $p): string
{
    $ref = trim((string) ($p['reference'] ?? ''));
    if ($ref !== '') {
        return $ref;
    }
    return prospect_reference_prefix((string) ($p['type'] ?? 'locataire'))
        . '-' . str_pad((string) (int) ($p['id'] ?? 0), 5, '0', STR_PAD_LEFT);
}

/** Écrit l'identifiant définitif d'un prospect fraîchement créé. */
function prospect_assign_reference(int $prospectId, string $type): string
{
    $ref = prospect_reference_prefix($type) . '-' . str_pad((string) $prospectId, 5, '0', STR_PAD_LEFT);
    update_existing('prospects', ['reference' => $ref, 'updated_at' => now()], 'id = ?', [$prospectId]);
    return $ref;
}

/* ------------------------------------------------------------------ *
 * §10 — Anti-doublon
 * ------------------------------------------------------------------ */

/** Normalise un numéro (format RDC) pour comparer 0994…, +243994…, 243994… */
function crm_normalize_phone(?string $phone): string
{
    $d = preg_replace('/\D+/', '', (string) $phone) ?? '';
    if ($d === '') {
        return '';
    }
    if (str_starts_with($d, '00243')) {
        $d = '243' . substr($d, 5);
    } elseif (str_starts_with($d, '0') && strlen($d) >= 9) {
        $d = '243' . substr($d, 1);
    }
    return $d;
}

/** Derniers chiffres significatifs d'un numéro, pour une recherche SQL tolérante. */
function crm_phone_tail(?string $phone): string
{
    $d = crm_normalize_phone($phone);
    return $d === '' ? '' : substr($d, -9);
}

/**
 * Recherche un contact existant (même type de prospect) par téléphone, WhatsApp
 * ou e-mail. Retourne le dossier le plus récent, ou null.
 */
function crm_find_contact(string $type, ?string $phone, ?string $whatsapp, ?string $email): ?array
{
    $clauses = [];
    $params = [];

    $tail = crm_phone_tail($phone);
    if ($tail !== '') {
        $clauses[] = "(REPLACE(REPLACE(REPLACE(REPLACE(p.phone, ' ', ''), '-', ''), '.', ''), '(', '') LIKE ?
                       OR REPLACE(REPLACE(REPLACE(REPLACE(p.whatsapp, ' ', ''), '-', ''), '.', ''), '(', '') LIKE ?)";
        $params[] = '%' . $tail;
        $params[] = '%' . $tail;
    }
    $waTail = crm_phone_tail($whatsapp);
    if ($waTail !== '' && $waTail !== $tail) {
        $clauses[] = "(REPLACE(REPLACE(REPLACE(REPLACE(p.whatsapp, ' ', ''), '-', ''), '.', ''), '(', '') LIKE ?
                       OR REPLACE(REPLACE(REPLACE(REPLACE(p.phone, ' ', ''), '-', ''), '.', ''), '(', '') LIKE ?)";
        $params[] = '%' . $waTail;
        $params[] = '%' . $waTail;
    }
    $email = strtolower(trim((string) $email));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $clauses[] = 'LOWER(p.email) = ?';
        $params[] = $email;
    }
    if (!$clauses) {
        return null;
    }

    $where = implode(' OR ', $clauses);
    $candidates = qtry_all(
        "SELECT p.* FROM prospects p
         WHERE p.type = ? AND ($where)
         ORDER BY p.id DESC LIMIT 20",
        array_merge([$type], $params)
    );

    $phoneNorm = crm_normalize_phone($phone);
    $waNorm = crm_normalize_phone($whatsapp);
    foreach ($candidates as $row) {
        $rowPhone = crm_normalize_phone($row['phone'] ?? null);
        $rowWa = crm_normalize_phone($row['whatsapp'] ?? null);
        $rowEmail = strtolower(trim((string) ($row['email'] ?? '')));
        if (($phoneNorm !== '' && ($rowPhone === $phoneNorm || $rowWa === $phoneNorm))
            || ($waNorm !== '' && ($rowWa === $waNorm || $rowPhone === $waNorm))
            || ($email !== '' && $rowEmail === $email)) {
            return $row;
        }
    }
    return null;
}

/* ------------------------------------------------------------------ *
 * §7 — Responsable du dossier
 * ------------------------------------------------------------------ */

function crm_responsible_options(): array
{
    $options = ['' => 'Non attribué'];
    foreach (qtry_all('SELECT a.id, u.first_name, u.last_name FROM immo_agents a LEFT JOIN users u ON u.id = a.user_id ORDER BY u.first_name, u.last_name') as $agent) {
        $options[(int) $agent['id']] = full_name($agent);
    }
    return $options;
}

/** Options de responsable sans l'entrée « Non attribué » (pour les filtres). */
function crm_responsible_options_named(): array
{
    $options = crm_responsible_options();
    unset($options['']);
    $named = [];
    foreach ($options as $id => $label) {
        $named[(string) $id] = $label;
    }
    return $named;
}

/** Besoin exprimé par un propriétaire potentiel (§15). */
function management_type_label(?string $type): string
{
    return [
        'mise_en_location' => 'Mise en location',
        'gestion_locative' => 'Gestion locative',
        'courte_duree' => 'Location courte durée',
    ][(string) $type] ?? '—';
}

/** Nombre de dossiers ouverts portés par un agent. */
function crm_agent_open_load(int $agentId, string $type = ''): int
{
    $open = "'" . implode("','", prospect_open_statuses()) . "'";
    if ($type !== '') {
        return (int) qtry_val(
            "SELECT COUNT(*) FROM prospects WHERE agent_id = ? AND type = ? AND status IN ($open)",
            [$agentId, $type],
            0
        );
    }
    return (int) qtry_val("SELECT COUNT(*) FROM prospects WHERE agent_id = ? AND status IN ($open)", [$agentId], 0);
}

/**
 * Affectation automatique (§7) :
 *  1. agent actif publié sur la même ville ;
 *  2. à défaut, agent actif publié sur la même province ;
 *  3. à défaut, agent actif le moins chargé pour ce type de prospect.
 */
function crm_auto_assign(string $type, ?string $city, ?string $province): ?int
{
    $agents = qtry_all(
        "SELECT a.id, a.city, a.province FROM immo_agents a
         LEFT JOIN users u ON u.id = a.user_id
         WHERE a.status = 'actif' AND (a.deleted_at IS NULL OR a.deleted_at = '')
           AND (u.status IS NULL OR u.status = 'actif')"
    );
    if (!$agents) {
        return null;
    }

    $city = mb_strtolower(trim((string) $city));
    $province = mb_strtolower(trim((string) $province));

    foreach (['city' => $city, 'province' => $province] as $field => $wanted) {
        if ($wanted === '') {
            continue;
        }
        $matching = array_values(array_filter(
            $agents,
            fn($a) => mb_strtolower(trim((string) ($a[$field] ?? ''))) === $wanted
        ));
        if ($matching) {
            return crm_least_loaded($matching, $type);
        }
    }
    return crm_least_loaded($agents, $type);
}

function crm_least_loaded(array $agents, string $type): int
{
    $bestId = (int) $agents[0]['id'];
    $bestLoad = PHP_INT_MAX;
    foreach ($agents as $agent) {
        $load = crm_agent_open_load((int) $agent['id'], $type);
        if ($load < $bestLoad) {
            $bestLoad = $load;
            $bestId = (int) $agent['id'];
        }
    }
    return $bestId;
}

/** Nom du responsable (agent) d'un prospect. */
function crm_responsible_name(array $prospect): string
{
    $id = (int) ($prospect['agent_id'] ?? 0);
    if ($id <= 0) {
        return '';
    }
    $row = qtry_one('SELECT a.first_name, a.last_name, u.first_name u_first, u.last_name u_last
                     FROM immo_agents a LEFT JOIN users u ON u.id = a.user_id WHERE a.id = ?', [$id]);
    if (!$row) {
        return '';
    }
    return full_name(['first_name' => $row['u_first'] ?? $row['first_name'], 'last_name' => $row['u_last'] ?? $row['last_name']]);
}

/* ------------------------------------------------------------------ *
 * §9 — Historique des interactions
 * ------------------------------------------------------------------ */

function crm_interaction_kinds(): array
{
    return [
        'note' => 'Note',
        'appel' => 'Appel',
        'whatsapp' => 'Message WhatsApp',
        'email' => 'E-mail',
        'rdv' => 'Rendez-vous',
        'visite' => 'Visite',
        'statut' => 'Changement de statut',
        'responsable' => 'Changement de responsable',
        'proposition' => 'Bien proposé',
        'formulaire' => 'Soumission du site',
    ];
}

function crm_interaction_label(?string $kind): string
{
    return crm_interaction_kinds()[(string) $kind] ?? 'Note';
}

/** Enregistre une interaction dans l'historique du prospect. */
function crm_log_interaction(int $prospectId, string $kind, string $body, ?int $userId = null): int
{
    return insert_existing('prospect_notes', [
        'prospect_id' => $prospectId,
        'user_id' => $userId ?? (user()['id'] ?? null),
        'kind' => $kind,
        'body' => $body,
        'created_at' => now(),
    ]);
}

/**
 * Historique unifié (§9) : notes, appels, WhatsApp, e-mails, rendez-vous,
 * visites, changements de statut, changements de responsable et propositions.
 * Chaque ligne porte la date, l'heure et l'utilisateur.
 */
function crm_timeline(int $prospectId, int $limit = 100): array
{
    $events = [];

    foreach (qtry_all('SELECT n.*, u.first_name, u.last_name FROM prospect_notes n
                       LEFT JOIN users u ON u.id = n.user_id
                       WHERE n.prospect_id = ? ORDER BY n.id DESC', [$prospectId]) as $note) {
        $events[] = [
            'at' => (string) ($note['created_at'] ?? ''),
            'kind' => (string) ($note['kind'] ?? 'note'),
            'label' => crm_interaction_label($note['kind'] ?? 'note'),
            'body' => (string) ($note['body'] ?? ''),
            'user' => full_name($note) ?: 'Site public',
        ];
    }

    foreach (qtry_all('SELECT h.*, u.first_name, u.last_name FROM prospect_status_history h
                       LEFT JOIN users u ON u.id = h.user_id
                       WHERE h.prospect_id = ? ORDER BY h.id DESC', [$prospectId]) as $entry) {
        $events[] = [
            'at' => (string) ($entry['created_at'] ?? ''),
            'kind' => 'statut',
            'label' => 'Changement de statut',
            'body' => trim(prospect_status_label($entry['old_status'] ?? null) . ' → '
                . prospect_status_label($entry['new_status'] ?? null)
                . (($entry['note'] ?? '') !== '' ? ' · ' . $entry['note'] : '')),
            'user' => full_name($entry) ?: 'Système',
        ];
    }

    foreach (qtry_all('SELECT v.*, pr.reference prop_ref, pr.title prop_title
                       FROM visits v LEFT JOIN properties pr ON pr.id = v.property_id
                       WHERE v.prospect_id = ? ORDER BY v.id DESC', [$prospectId]) as $visit) {
        $events[] = [
            'at' => (string) ($visit['scheduled_at'] ?? $visit['created_at'] ?? ''),
            'kind' => 'visite',
            'label' => 'Visite',
            'body' => trim('Visite ' . (visit_statuses()[$visit['status']] ?? $visit['status'])
                . ' — ' . ($visit['prop_title'] ?: 'bien #' . (int) $visit['property_id'])
                . (($visit['report'] ?? '') !== '' ? ' · ' . $visit['report'] : '')),
            'user' => crm_responsible_name(['agent_id' => $visit['agent_id']]) ?: 'Agent',
        ];
    }

    foreach (qtry_all('SELECT pp.*, pr.title prop_title, pr.reference prop_ref
                       FROM prospect_proposals pp LEFT JOIN properties pr ON pr.id = pp.property_id
                       WHERE pp.prospect_id = ? ORDER BY pp.id DESC', [$prospectId]) as $proposal) {
        $events[] = [
            'at' => (string) ($proposal['responded_at'] ?? $proposal['created_at'] ?? ''),
            'kind' => 'proposition',
            'label' => 'Bien proposé',
            'body' => trim(($proposal['prop_title'] ?: 'bien #' . (int) $proposal['property_id'])
                . ' — réponse : ' . crm_proposal_status_label($proposal['status'] ?? 'envoye')
                . (($proposal['note'] ?? '') !== '' ? ' · ' . $proposal['note'] : '')),
            'user' => crm_responsible_name(['agent_id' => $proposal['agent_id']]) ?: 'Agent',
        ];
    }

    usort($events, fn($a, $b) => strcmp((string) $b['at'], (string) $a['at']));
    return array_slice($events, 0, $limit);
}

/* ------------------------------------------------------------------ *
 * §20 — Score de compatibilité
 * ------------------------------------------------------------------ */

function crm_norm_text(?string $value): string
{
    $value = mb_strtolower(trim((string) $value));
    $value = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    return preg_replace('/\s+/', ' ', $value) ?? '';
}

/**
 * Score de compatibilité entre un bien et une demande locataire (0 à 100).
 * Critères pondérés (§20) : ville 25, quartier 15, type 20, budget 20,
 * chambres 10, ameublement 5, disponibilité 5.
 *
 * @return array{score:int, detail:array<string,string>}
 */
function crm_match_score(array $property, array $prospect): array
{
    $detail = [];
    $score = 0;

    // Ville (25)
    $pCity = crm_norm_text($property['city'] ?? null);
    $rCity = crm_norm_text($prospect['city'] ?? null);
    $pProv = crm_norm_text($property['province'] ?? null);
    $rProv = crm_norm_text($prospect['province'] ?? null);
    if ($rCity === '') {
        $score += 10;
        $detail['Ville'] = 'non précisée (10/25)';
    } elseif ($rCity === $pCity) {
        $score += 25;
        $detail['Ville'] = $property['city'] . ' (25/25)';
    } elseif ($rProv !== '' && $rProv === $pProv) {
        $score += 10;
        $detail['Ville'] = 'même province, ville différente (10/25)';
    } else {
        $detail['Ville'] = ($prospect['city'] ?: '—') . ' ≠ ' . ($property['city'] ?: '—') . ' (0/25)';
    }

    // Quartier (15)
    $pQ = crm_norm_text($property['quartier'] ?? null);
    $rQ = crm_norm_text($prospect['quartier'] ?? null);
    if ($rQ === '') {
        $score += 8;
        $detail['Quartier'] = 'non précisé (8/15)';
    } elseif ($pQ !== '' && (str_contains($rQ, $pQ) || str_contains($pQ, $rQ))) {
        $score += 15;
        $detail['Quartier'] = $property['quartier'] . ' (15/15)';
    } else {
        $score += 3;
        $detail['Quartier'] = ($prospect['quartier'] ?: '—') . ' ≠ ' . ($property['quartier'] ?: '—') . ' (3/15)';
    }

    // Type de bien (20)
    $pType = crm_norm_text($property['type'] ?? null);
    $rType = crm_norm_text($prospect['property_type'] ?? null);
    if ($rType === '') {
        $score += 10;
        $detail['Type de bien'] = 'indifférent (10/20)';
    } elseif ($rType === $pType) {
        $score += 20;
        $detail['Type de bien'] = (property_types()[$property['type']] ?? $property['type']) . ' (20/20)';
    } else {
        $detail['Type de bien'] = (property_types()[$prospect['property_type']] ?? $prospect['property_type'])
            . ' ≠ ' . (property_types()[$property['type']] ?? $property['type']) . ' (0/20)';
    }

    // Budget (20)
    $rent = (float) ($property['rent'] ?? $property['loyer'] ?? 0);
    $max = (float) ($prospect['budget_max'] ?? 0);
    $min = (float) ($prospect['budget_min'] ?? 0);
    if ($max <= 0) {
        $score += 10;
        $detail['Budget'] = 'non précisé (10/20)';
    } elseif ($rent <= $max && ($min <= 0 || $rent >= $min * 0.5)) {
        $score += 20;
        $detail['Budget'] = money($rent) . ' ≤ budget max ' . money($max) . ' (20/20)';
    } elseif ($rent <= $max * 1.1) {
        $score += 12;
        $detail['Budget'] = money($rent) . ' légèrement au-dessus du budget (12/20)';
    } else {
        $detail['Budget'] = money($rent) . ' > budget max ' . money($max) . ' (0/20)';
    }

    // Chambres (10)
    $pBeds = (int) ($property['bedrooms'] ?? $property['chambres'] ?? 0);
    $rBeds = (int) ($prospect['bedrooms'] ?? 0);
    if ($rBeds <= 0) {
        $score += 5;
        $detail['Chambres'] = 'indifférent (5/10)';
    } elseif ($pBeds === $rBeds) {
        $score += 10;
        $detail['Chambres'] = $pBeds . ' chambres (10/10)';
    } elseif ($pBeds > $rBeds) {
        $score += 7;
        $detail['Chambres'] = $pBeds . ' chambres pour ' . $rBeds . ' demandées (7/10)';
    } else {
        $score += 3;
        $detail['Chambres'] = $pBeds . ' chambres < ' . $rBeds . ' demandées (3/10)';
    }

    // Ameublement (5)
    $pFurn = (int) ($property['furnished'] ?? 0);
    $rFurnRaw = $prospect['furnished'] ?? null;
    if ($rFurnRaw === null || $rFurnRaw === '') {
        $score += 3;
        $detail['Ameublement'] = 'indifférent (3/5)';
    } elseif ((int) $rFurnRaw === $pFurn) {
        $score += 5;
        $detail['Ameublement'] = ((int) $rFurnRaw === 1 ? 'meublé' : 'non meublé') . ' (5/5)';
    } else {
        $detail['Ameublement'] = 'préférence différente (0/5)';
    }

    // Disponibilité (5)
    $wanted = (string) ($prospect['desired_date'] ?? '');
    $available = (string) ($property['available_from'] ?? '');
    if ($wanted === '') {
        $score += 3;
        $detail['Disponibilité'] = 'date non précisée (3/5)';
    } elseif ($available === '' || $available <= $wanted) {
        $score += 5;
        $detail['Disponibilité'] = 'disponible pour le ' . dfr($wanted) . ' (5/5)';
    } else {
        $score += 1;
        $detail['Disponibilité'] = 'bien libre le ' . dfr($available) . ' (1/5)';
    }

    return ['score' => max(0, min(100, $score)), 'detail' => $detail];
}

function crm_score_class(int $score): string
{
    if ($score >= 85) {
        return 'badge-ok';
    }
    if ($score >= 65) {
        return 'badge-warn';
    }
    return 'badge-muted';
}

/**
 * §19 — Locataires potentiellement intéressés par un bien.
 *
 * @return array<int, array{prospect:array, score:int, detail:array}>
 */
function crm_matches_for_property(int $propertyId, int $limit = 50, int $minScore = 40): array
{
    $property = qtry_one('SELECT * FROM properties WHERE id = ?', [$propertyId]);
    if (!$property) {
        return [];
    }
    return crm_matches_for_property_row($property, $limit, $minScore);
}

function crm_matches_for_property_row(array $property, int $limit = 50, int $minScore = 40): array
{
    // Les locations journalières (IMMO STAY) ne se proposent pas à des locataires longue durée.
    if (!empty($property['is_short_stay'])) {
        return [];
    }
    $open = "'" . implode("','", prospect_open_statuses()) . "'";
    $candidates = qtry_all(
        "SELECT * FROM prospects
         WHERE type = 'locataire'
           AND status IN ($open)
           AND (converted_to IS NULL OR converted_to = '')
         ORDER BY id DESC LIMIT 500"
    );
    $out = [];
    foreach ($candidates as $prospect) {
        $result = crm_match_score($property, $prospect);
        if ($result['score'] < $minScore) {
            continue;
        }
        $out[] = ['prospect' => $prospect, 'score' => $result['score'], 'detail' => $result['detail']];
    }
    usort($out, fn($a, $b) => $b['score'] <=> $a['score'] ?: (int) $b['prospect']['id'] <=> (int) $a['prospect']['id']);
    return array_slice($out, 0, $limit);
}

/** §19 — Biens compatibles avec la recherche d'un prospect locataire. */
function crm_matches_for_prospect(int $prospectId, int $limit = 20, int $minScore = 40): array
{
    $prospect = qtry_one('SELECT * FROM prospects WHERE id = ?', [$prospectId]);
    if (!$prospect) {
        return [];
    }
    // On écarte les locations journalières : elles ne correspondent pas à une recherche longue durée.
    $properties = qtry_all(
        'SELECT * FROM properties WHERE ' . published_status_sql('properties')
            . ' AND (is_short_stay = 0 OR is_short_stay IS NULL) ORDER BY id DESC LIMIT 300'
    );
    $out = [];
    foreach ($properties as $property) {
        $result = crm_match_score($property, $prospect);
        if ($result['score'] < $minScore) {
            continue;
        }
        $out[] = ['property' => $property, 'score' => $result['score'], 'detail' => $result['detail']];
    }
    usort($out, fn($a, $b) => $b['score'] <=> $a['score']);
    return array_slice($out, 0, $limit);
}

/* ------------------------------------------------------------------ *
 * §21 — Proposer le bien
 * ------------------------------------------------------------------ */

function crm_proposal_statuses(): array
{
    return [
        'envoye' => 'Envoyé',
        'interesse' => 'Intéressé',
        'visite_demandee' => 'Visite demandée',
        'refuse' => 'Refusé',
        'loue' => 'Loué',
    ];
}

function crm_proposal_status_label(?string $status): string
{
    return crm_proposal_statuses()[(string) $status] ?? 'Envoyé';
}

/** Enregistre la proposition d'un bien à un prospect (évite les doublons). */
function crm_propose(int $prospectId, int $propertyId, string $note = ''): int
{
    $existing = qtry_one(
        'SELECT * FROM prospect_proposals WHERE prospect_id = ? AND property_id = ? ORDER BY id DESC LIMIT 1',
        [$prospectId, $propertyId]
    );
    if ($existing) {
        update_existing('prospect_proposals', [
            'status' => 'envoye',
            'note' => $note !== '' ? $note : (string) ($existing['note'] ?? ''),
            'agent_id' => user()['id'] ?? null,
            'responded_at' => null,
            'created_at' => now(),
        ], 'id = ?', [(int) $existing['id']]);
        $id = (int) $existing['id'];
    } else {
        $id = insert_existing('prospect_proposals', [
            'prospect_id' => $prospectId,
            'property_id' => $propertyId,
            'agent_id' => user()['id'] ?? null,
            'status' => 'envoye',
            'note' => $note,
            'created_at' => now(),
        ]);
    }
    $property = qtry_one('SELECT title FROM properties WHERE id = ?', [$propertyId]);
    crm_log_interaction(
        $prospectId,
        'proposition',
        'Bien proposé : ' . ($property['title'] ?? 'bien #' . $propertyId) . ($note !== '' ? ' — ' . $note : '')
    );
    return $id;
}

/* ------------------------------------------------------------------ *
 * §22 — Tableau de bord CRM
 * ------------------------------------------------------------------ */

function crm_kpis(): array
{
    $open = "'" . implode("','", prospect_open_statuses()) . "'";
    $total = (int) qtry_val('SELECT COUNT(*) FROM prospects');
    $converted = (int) qtry_val("SELECT COUNT(*) FROM prospects WHERE status = 'converti'");
    // §22 — taux de conversion = prospects convertis / total des prospects.
    $rate = $total > 0 ? round($converted * 100 / $total, 1) : 0.0;
    // §22 — temps de réponse moyen (création → première interaction tracée), en heures.
    // Expression portable : julianday() n'existe que sur SQLite, TIMESTAMPDIFF() que sur MySQL.
    $firstContact = '(SELECT prospect_id, MIN(created_at) AS first_at FROM prospect_notes GROUP BY prospect_id) f';
    $avgSql = (db()->driver ?? 'sqlite') === 'mysql'
        ? "SELECT AVG(TIMESTAMPDIFF(MINUTE, p.created_at, f.first_at)) / 60
             FROM prospects p JOIN $firstContact ON f.prospect_id = p.id
            WHERE p.created_at IS NOT NULL AND f.first_at IS NOT NULL"
        : "SELECT AVG((julianday(f.first_at) - julianday(p.created_at)) * 24)
             FROM prospects p JOIN $firstContact ON f.prospect_id = p.id
            WHERE p.created_at IS NOT NULL AND f.first_at IS NOT NULL";
    $avgHours = qtry_val($avgSql);
    return [
        ['l' => 'Tous les prospects', 'v' => $total],
        ['l' => 'Nouveaux aujourd’hui', 'v' => qtry_val('SELECT COUNT(*) FROM prospects WHERE DATE(created_at) = ?', [today()])],
        ['l' => 'Propriétaires potentiels', 'v' => qtry_val("SELECT COUNT(*) FROM prospects WHERE type = 'proprietaire'")],
        ['l' => 'Locataires potentiels', 'v' => qtry_val("SELECT COUNT(*) FROM prospects WHERE type = 'locataire'")],
        ['l' => 'Prospects à contacter', 'v' => qtry_val("SELECT COUNT(*) FROM prospects WHERE status IN ('nouveau','a_contacter')")],
        ['l' => 'Visites prévues', 'v' => qtry_val("SELECT COUNT(*) FROM visits WHERE status IN ('planifiee','confirmee') AND DATE(scheduled_at) >= ?", [today()])],
        ['l' => 'Prospects convertis', 'v' => $converted],
        ['l' => 'Taux de conversion', 'v' => $rate . ' %'],
        ['l' => 'Réponse moyenne', 'v' => $avgHours === null ? '—' : round((float) $avgHours, 1) . ' h'],
        ['l' => 'Sans responsable', 'v' => qtry_val("SELECT COUNT(*) FROM prospects WHERE status IN ($open) AND (agent_id IS NULL OR agent_id = 0)")],
    ];
}

/* ------------------------------------------------------------------ *
 * §23 — Alertes
 * ------------------------------------------------------------------ */

/**
 * Alertes CRM. Chaque alerte porte une couleur (rouge, orange, vert, bleu, violet),
 * un libellé, un compteur et un lien.
 */
function crm_alerts(): array
{
    $alerts = [];

    $late = (int) qtry_val(
        "SELECT COUNT(*) FROM prospects
         WHERE status IN ('nouveau','a_contacter')
           AND created_at IS NOT NULL
           AND created_at <= ?",
        [date('Y-m-d H:i:s', strtotime('-24 hours'))]
    );
    $alerts[] = [
        'color' => '🔴', 'tone' => 'alert-red',
        'label' => 'Prospect non contacté après 24 heures',
        'count' => $late, 'link' => 'app/prospects?status=nouveau',
    ];

    $todayVisits = (int) qtry_val(
        "SELECT COUNT(*) FROM visits WHERE DATE(scheduled_at) = ? AND status IN ('planifiee','confirmee')",
        [today()]
    );
    $alerts[] = [
        'color' => '🟠', 'tone' => 'alert-orange',
        'label' => 'Visite prévue aujourd’hui',
        'count' => $todayVisits, 'link' => 'app/visites',
    ];

    $matching = (int) qtry_val(
        "SELECT COUNT(*) FROM properties WHERE " . published_status_sql('properties') . " AND id NOT IN (SELECT DISTINCT property_id FROM prospect_proposals WHERE property_id IS NOT NULL)"
    );
    $alerts[] = [
        'color' => '🟢', 'tone' => 'alert-green',
        'label' => 'Nouveau matching disponible',
        'count' => $matching, 'link' => 'app/prospects?type=locataire',
    ];

    $newOwners = (int) qtry_val(
        "SELECT COUNT(*) FROM prospects WHERE type = 'proprietaire' AND status IN ('nouveau','a_contacter')"
    );
    $alerts[] = [
        'color' => '🔵', 'tone' => 'alert-blue',
        'label' => 'Nouveau propriétaire',
        'count' => $newOwners, 'link' => 'app/prospects?type=proprietaire',
    ];

    $newTenants = (int) qtry_val(
        "SELECT COUNT(*) FROM prospects WHERE type = 'locataire' AND status IN ('nouveau','a_contacter')"
    );
    $alerts[] = [
        'color' => '🟣', 'tone' => 'alert-purple',
        'label' => 'Nouveau locataire potentiel',
        'count' => $newTenants, 'link' => 'app/prospects?type=locataire',
    ];

    return $alerts;
}

function crm_alerts_html(): string
{
    $html = '<h3 class="crm-alerts-title" style="margin:18px 0 8px">Alertes du jour</h3><div class="crm-alerts">';
    foreach (crm_alerts() as $alert) {
        $html .= '<a class="crm-alert ' . e($alert['tone']) . '" href="' . e(base_url($alert['link'])) . '">'
            . '<b>' . (int) $alert['count'] . '</b>'
            . '<span>' . e($alert['color'] . ' ' . $alert['label']) . '</span></a>';
    }
    return $html . '</div>';
}

function crm_kpis_html(): string
{
    $html = '<div class="stats" style="margin-bottom:16px">';
    foreach (crm_kpis() as $kpi) {
        $html .= '<div class="stat"><b>' . e((string) $kpi['v']) . '</b><span>' . e($kpi['l']) . '</span></div>';
    }
    return $html . '</div>';
}

/* ------------------------------------------------------------------ *
 * §8 — Actions rapides
 * ------------------------------------------------------------------ */

/** Boutons Appeler / WhatsApp / E-mail (§8). */
function crm_contact_actions(array $p): string
{
    // §15/§16 — la colonne « Téléphone » affiche le numéro, puis les actions rapides (§8).
    $html = '<div class="crm-contact">';
    $phone = preg_replace('/\s+/', '', (string) ($p['phone'] ?? ''));
    if ($phone !== '') {
        $html .= '<a class="crm-contact-tel" href="tel:' . e($phone) . '">' . e($phone) . '</a>';
    } else {
        $html .= '<span class="muted">—</span>';
    }
    if (!empty($p['email'])) {
        $html .= '<br><span class="muted">' . e($p['email']) . '</span>';
    }
    $html .= '<div class="row-actions">';
    if ($phone !== '') {
        $html .= '<a class="btn btn-sm" href="tel:' . e($phone) . '">Appeler</a>';
    }
    $wa = preg_replace('/\D+/', '', (string) ($p['whatsapp'] ?? $p['phone'] ?? ''));
    if ($wa !== '') {
        $html .= '<a class="btn btn-sm btn-green" href="https://wa.me/' . e($wa)
            . '?text=' . rawurlencode('Bonjour ' . full_name($p) . ', ici YOUPENDI IMMO SELECT.')
            . '" target="_blank" rel="noopener">WhatsApp</a>';
    }
    if (!empty($p['email'])) {
        $html .= '<a class="btn btn-sm btn-outline" href="mailto:' . e($p['email']) . '">E-mail</a>';
    }
    $html .= '<a class="btn btn-sm btn-outline" href="' . e(base_url('app/prospects/' . (int) $p['id'])) . '">Fiche</a>';
    return $html . '</div></div>';
}

/**
 * Formulaire d'interaction rapide (§8) : note, appel, WhatsApp, e-mail,
 * rendez-vous, visite — tout est tracé dans l'historique (§9).
 */
function crm_interaction_form(int $prospectId): string
{
    $kinds = ['note' => 'Note', 'appel' => 'Appel', 'whatsapp' => 'WhatsApp', 'email' => 'E-mail', 'rdv' => 'Rendez-vous', 'visite' => 'Visite'];
    $html = '<form class="crm-interaction" method="post" action="' . e(base_url('app/prospects/' . $prospectId . '/interaction')) . '">'
        . csrf_field()
        . '<div class="crm-interaction-row">'
        . '<select name="kind" aria-label="Type d’interaction">';
    foreach ($kinds as $key => $label) {
        $html .= '<option value="' . e($key) . '">' . e($label) . '</option>';
    }
    $html .= '</select>'
        . '<input type="text" name="body" placeholder="Compte rendu, objet, décision…" required>'
        . '<input type="datetime-local" name="at" aria-label="Date et heure">'
        . '<button class="btn btn-sm">Enregistrer</button>'
        . '</div></form>';
    return $html;
}

/**
 * §19 / §20 — Panneau de rapprochement affiché sur la fiche prospect.
 * Pour un locataire : les biens compatibles.
 * Pour un propriétaire : les locataires potentiellement intéressés par son bien.
 */
function crm_matching_panel(array $prospect): string
{
    $id = (int) ($prospect['id'] ?? 0);
    if ($id <= 0) {
        return '';
    }

    if (($prospect['type'] ?? '') === 'proprietaire') {
        $matches = crm_matches_for_prospect($id, 5, 30);
        $html = '<div class="panel" style="margin-top:16px;max-width:920px"><h3>Locataires potentiellement intéressés</h3>';
        if (!$matches) {
            return $html . '<p class="muted">Aucune demande compatible pour l’instant. Le CRM relance le rapprochement à chaque nouvelle recherche.</p></div>';
        }
        $html .= '<p><strong>' . count($matches) . ' correspondance(s)</strong></p><ul class="crm-matches">';
        foreach ($matches as $match) {
            $row = $match['property'];
            $html .= '<li><span class="badge ' . e(crm_score_class($match['score'])) . '">' . (int) $match['score'] . ' %</span>'
                . '<a href="' . e(base_url('app/biens/' . $row['id'])) . '">' . e($row['title'] ?: 'Bien #' . $row['id']) . '</a>'
                . '<span class="muted">' . e(trim(($row['city'] ?? '') . ' · ' . ($row['quartier'] ?? ''), ' ·')) . '</span></li>';
        }
        return $html . '</ul><p><a class="btn btn-sm btn-outline" href="' . e(base_url('app/prospects/' . $id . '/matching')) . '">Voir tout le matching</a></p></div>';
    }

    $matches = crm_matches_for_prospect($id, 5, 30);
    $html = '<div class="panel" style="margin-top:16px;max-width:920px"><h3>Biens compatibles avec cette recherche</h3>';
    if (!$matches) {
        return $html . '<p class="muted">Aucun bien publié ne correspond encore à ces critères.</p></div>';
    }
    $html .= '<p><strong>' . count($matches) . ' correspondance(s)</strong></p><ul class="crm-matches">';
    foreach ($matches as $match) {
        $row = $match['property'];
        $html .= '<li><span class="badge ' . e(crm_score_class($match['score'])) . '">' . (int) $match['score'] . ' %</span>'
            . '<a href="' . e(base_url('app/biens/' . $row['id'])) . '">' . e($row['title'] ?: 'Bien #' . $row['id']) . '</a>'
            . '<span class="muted">' . e(money($row['rent'] ?? 0, $row['currency'] ?? 'USD')) . '</span>'
            . '<form method="post" action="' . e(base_url('app/prospects/' . $id . '/proposer')) . '" style="display:inline">'
            . csrf_field() . '<input type="hidden" name="property_id" value="' . (int) $row['id'] . '">'
            . '<button class="btn btn-sm btn-gold">Proposer ce bien</button></form></li>';
    }
    return $html . '</ul><p><a class="btn btn-sm btn-outline" href="' . e(base_url('app/prospects/' . $id . '/matching')) . '">Voir tout le matching</a></p></div>';
}

/** §21 — Biens déjà proposés à ce prospect et réponse du client. */
function crm_proposals_panel(int $prospectId): string
{
    $proposals = qtry_all('SELECT pp.*, pr.title FROM prospect_proposals pp
                           LEFT JOIN properties pr ON pr.id = pp.property_id
                           WHERE pp.prospect_id = ? ORDER BY pp.id DESC', [$prospectId]);
    if (!$proposals) {
        return '';
    }
    $html = '<div class="panel" style="margin-top:16px;max-width:920px"><h3>Biens proposés</h3><div class="table-wrap"><table>'
        . '<thead><tr><th>Bien</th><th>Proposé le</th><th>Réponse du client</th><th>Mettre à jour</th></tr></thead><tbody>';
    foreach ($proposals as $proposal) {
        $form = '<form class="row-actions" method="post" action="' . e(base_url('app/propositions/' . $proposal['id'] . '/reponse')) . '">'
            . csrf_field() . '<select name="status" aria-label="Réponse du client">';
        foreach (crm_proposal_statuses() as $key => $label) {
            $form .= '<option value="' . e($key) . '"' . (($proposal['status'] ?? '') === $key ? ' selected' : '') . '>' . e($label) . '</option>';
        }
        $form .= '</select><button class="btn btn-sm">Enregistrer</button></form>';
        $html .= '<tr><td><a href="' . e(base_url('app/biens/' . (int) $proposal['property_id'])) . '">'
            . e($proposal['title'] ?: 'Bien #' . (int) $proposal['property_id']) . '</a></td>'
            . '<td>' . e(dfr($proposal['created_at'], 'd/m/Y H:i')) . '</td>'
            . '<td>' . e(crm_proposal_status_label($proposal['status'] ?? 'envoye'))
            . ($proposal['responded_at'] ? '<br><span class="muted">' . e(dfr($proposal['responded_at'], 'd/m/Y H:i')) . '</span>' : '') . '</td>'
            . '<td>' . $form . '</td></tr>';
    }
    return $html . '</tbody></table></div></div>';
}

/** Formulaire d'affectation du responsable (§8). */
function crm_assign_form(int $prospectId, ?int $current): string
{
    $html = '<form class="row-actions" method="post" action="' . e(base_url('app/prospects/' . $prospectId . '/affecter')) . '">'
        . csrf_field()
        . '<select name="agent_id" aria-label="Responsable du dossier">';
    foreach (crm_responsible_options() as $value => $label) {
        $selected = ((string) $value !== '' && (int) $value === (int) $current) ? ' selected' : '';
        $html .= '<option value="' . e((string) $value) . '"' . $selected . '>' . e($label) . '</option>';
    }
    return $html . '</select><button class="btn btn-sm">Affecter</button></form>';
}
