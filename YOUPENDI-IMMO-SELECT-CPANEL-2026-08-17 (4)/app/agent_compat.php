<?php

/**
 * Compatibilité des agents entre les schémas actuels et historiques.
 * Toutes les lectures sont non destructives et se font sans supposer les noms de colonnes legacy.
 */
function public_agent_value(array $sources, array $aliases, $default = '')
{
    $aliases = array_map(static fn($key) => mb_strtolower((string) $key), $aliases);
    foreach ($sources as $source) {
        if (!is_array($source)) {
            continue;
        }
        $source = array_change_key_case($source, CASE_LOWER);
        foreach ($aliases as $alias) {
            if (!array_key_exists($alias, $source)) {
                continue;
            }
            $value = $source[$alias];
            if ($value === null || (is_string($value) && trim($value) === '')) {
                continue;
            }
            return $value;
        }
    }
    return $default;
}

function public_agent_text($value): string
{
    if (is_array($value)) {
        $value = implode(', ', array_filter(array_map('trim', array_map('strval', $value))));
    }
    return trim((string) $value);
}

function public_agent_token($value): string
{
    $token = mb_strtolower(public_agent_text($value));
    return strtr($token, [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a',
        'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ÿ' => 'y',
    ]);
}

function public_agent_is_active($status): bool
{
    if ($status === null || public_agent_text($status) === '') {
        return true;
    }
    $status = preg_replace('/[^a-z0-9]+/', '', public_agent_token($status));
    return !in_array($status, [
        '0', 'non', 'false', 'inactif', 'inactive', 'desactive', 'suspendu',
        'suspended', 'bloque', 'archive', 'supprime',
    ], true);
}

function public_agent_has_role(array $row): bool
{
    $role = public_agent_token(public_agent_value(
        [$row],
        ['role', 'profil', 'profile', 'type', 'type_utilisateur', 'fonction']
    ));
    $role = trim((string) preg_replace('/[_-]+/', ' ', $role));
    return in_array($role, [
        'agent', 'agent immobilier', 'commercial', 'superviseur',
        'supervisor', 'manager', 'gestionnaire', 'gestionnaire immobilier',
    ], true);
}

/** Recherche une ligne historique sans imposer que sa clé primaire s'appelle id. */
function compatibility_row_by_id(Database $db, string $table, int $id, array $idAliases = ['id']): ?array
{
    if ($id <= 0 || !in_array($table, ['immo_agents', 'users', 'utilisateurs', 'agents', 'teams'], true)) {
        return null;
    }
    $columns = schema_columns($db, $table);
    if (!$columns) {
        return null;
    }
    foreach ($idAliases as $alias) {
        $alias = mb_strtolower($alias);
        if (in_array($alias, $columns, true)) {
            return $db->one("SELECT * FROM `$table` WHERE `$alias` = ? LIMIT 1", [$id]);
        }
    }
    return null;
}

function normalize_public_agent(array $sources, string $source, int $sourceId = 0): array
{
    $firstName = public_agent_text(public_agent_value(
        $sources,
        ['first_name', 'firstname', 'prenom', 'prénom']
    ));
    $lastName = public_agent_text(public_agent_value(
        $sources,
        ['last_name', 'lastname', 'nom', 'nom_agent']
    ));
    $fullName = public_agent_text(public_agent_value(
        $sources,
        ['full_name', 'fullname', 'nom_complet', 'noms', 'name', 'display_name', 'agent_name']
    ));
    if ($fullName === '') {
        $fullName = trim($firstName . ' ' . $lastName);
    }
    if ($firstName === '' && $lastName === '' && $fullName !== '') {
        $lastName = $fullName;
    }

    $photo = public_agent_text(public_agent_value($sources, [
        'photo_path', 'avatar', 'photo', 'image', 'photo_profil',
        'photo_profile', 'profile_photo', 'picture',
    ]));

    return [
        'id' => (int) public_agent_value($sources, ['id', 'agent_id', 'id_agent'], $sourceId),
        'user_id' => (int) public_agent_value($sources, ['user_id', 'utilisateur_id', 'id_utilisateur'], 0),
        'first_name' => $firstName,
        'last_name' => $lastName,
        'name' => $fullName,
        'phone' => public_agent_text(public_agent_value($sources, [
            'phone', 'telephone', 'téléphone', 'tel', 'mobile', 'whatsapp', 'wathsapp', 'numero_telephone',
        ])),
        'email' => public_agent_text(public_agent_value($sources, ['email', 'mail', 'courriel', 'e_mail'])),
        'province' => public_agent_text(public_agent_value($sources, ['province', 'region', 'région'])),
        'city' => public_agent_text(public_agent_value($sources, ['city', 'ville', 'localite', 'localité'])),
        'address' => public_agent_text(public_agent_value($sources, ['address', 'adresse'])),
        'specialties' => public_agent_text(public_agent_value($sources, [
            'specialties', 'specialites', 'spécialités', 'specialite', 'spécialité',
        ])),
        'photo_path' => $photo,
        'avatar' => $photo,
        'source' => $source,
        'source_id' => $sourceId,
    ];
}

/**
 * Charge les agents visibles sur l'accueil dans cet ordre :
 * immo_agents + users/utilisateurs, profils agents orphelins, puis table agents historique.
 */
function load_public_agents(Database $db, int $limit = 3): array
{
    $limit = max(1, $limit);
    $result = [];
    $seen = [];
    $userProfiles = [];
    $legacyProfiles = [];

    if (table_exists($db, 'users')) {
        foreach ($db->all('SELECT * FROM users') as $row) {
            $userProfiles[(int) ($row['id'] ?? 0)] = $row;
        }
    }
    if (table_exists($db, 'utilisateurs')) {
        foreach ($db->all('SELECT * FROM utilisateurs') as $row) {
            $legacyId = (int) public_agent_value([$row], ['id', 'id_utilisateur', 'user_id'], 0);
            if ($legacyId > 0) {
                $legacyProfiles[$legacyId] = $row;
            }
        }
    }

    $append = static function (array $agent) use (&$result, &$seen, $limit): void {
        if (count($result) >= $limit || trim((string) ($agent['name'] ?? '')) === '') {
            return;
        }

        $keys = [];
        if (!empty($agent['user_id'])) {
            $keys[] = 'user:' . (int) $agent['user_id'];
        }
        $phone = preg_replace('/\D+/', '', (string) ($agent['phone'] ?? ''));
        if ($phone !== '') {
            $keys[] = 'phone:' . $phone;
        }
        $email = mb_strtolower(trim((string) ($agent['email'] ?? '')));
        if ($email !== '') {
            $keys[] = 'email:' . $email;
        }
        $name = preg_replace('/[^a-z0-9]+/', '', public_agent_token($agent['name'] ?? ''));
        if ($name !== '') {
            $keys[] = 'name:' . $name;
        }
        foreach ($keys as $key) {
            if (isset($seen[$key])) {
                return;
            }
        }
        foreach ($keys as $key) {
            $seen[$key] = true;
        }
        $result[] = $agent;
    };

    if (table_exists($db, 'immo_agents')) {
        foreach ($db->all('SELECT * FROM immo_agents ORDER BY id DESC') as $row) {
            if (count($result) >= $limit) {
                break;
            }
            if (!public_agent_is_active(public_agent_value(
                [$row],
                ['status', 'statut', 'etat', 'actif'],
                'actif'
            ))) {
                continue;
            }

            $userId = (int) public_agent_value(
                [$row],
                ['user_id', 'utilisateur_id', 'id_utilisateur'],
                0
            );
            $profile = $userProfiles[$userId] ?? [];
            $legacyProfile = $legacyProfiles[$userId] ?? [];
            $activeProfile = $profile ?: $legacyProfile;
            if ($activeProfile && !public_agent_is_active(public_agent_value(
                [$activeProfile],
                ['status', 'statut', 'etat', 'actif'],
                'actif'
            ))) {
                continue;
            }

            $agent = normalize_public_agent(
                [$row, $profile, $legacyProfile],
                'immo_agents',
                (int) ($row['id'] ?? 0)
            );
            $agent['user_id'] = $userId;
            $append($agent);
        }
    }

    // Anciennes installations : le lien user_id peut être absent ou ne plus correspondre.
    foreach ([['users', $userProfiles], ['utilisateurs', $legacyProfiles]] as [$source, $profiles]) {
        foreach ($profiles as $id => $profile) {
            if (count($result) >= $limit) {
                break 2;
            }
            if (!public_agent_has_role($profile)) {
                continue;
            }
            if (!public_agent_is_active(public_agent_value(
                [$profile],
                ['status', 'statut', 'etat', 'actif'],
                'actif'
            ))) {
                continue;
            }
            $agent = normalize_public_agent([$profile], $source, (int) $id);
            $agent['user_id'] = (int) $id;
            $append($agent);
        }
    }

    if (count($result) < $limit && table_exists($db, 'agents')) {
        foreach ($db->all('SELECT * FROM agents') as $row) {
            if (count($result) >= $limit) {
                break;
            }
            if (!public_agent_is_active(public_agent_value(
                [$row],
                ['status', 'statut', 'etat', 'actif'],
                'actif'
            ))) {
                continue;
            }
            $legacyId = (int) public_agent_value([$row], ['id', 'agent_id', 'id_agent'], 0);
            $append(normalize_public_agent([$row], 'agents', $legacyId));
        }
    }

    return $result;
}
