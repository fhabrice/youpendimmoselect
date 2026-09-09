<?php

function user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function auth_refresh(): void
{
    if (empty($_SESSION['user']['id'])) {
        return;
    }
    $u = db()->one('SELECT * FROM users WHERE id = ? AND status = ?', [$_SESSION['user']['id'], 'actif']);
    if (!$u) {
        unset($_SESSION['user']);
        return;
    }
    unset($u['password']);
    $_SESSION['user'] = $u;
}

function require_auth(): array
{
    $u = user();
    if (!$u) {
        $_SESSION['_intended'] = request_path();
        flash('error', 'Veuillez vous connecter pour continuer.');
        redirect('connexion');
    }
    return $u;
}

function guest_only(): void
{
    if (user()) {
        redirect(home_for(user()));
    }
}

function home_for(?array $u): string
{
    if (!$u) {
        return '';
    }
    return match ($u['role']) {
        'owner' => 'espace/proprietaire',
        'tenant' => 'espace/locataire',
        'traveler' => 'espace/voyageur',
        default => 'app',
    };
}

function can(string $perm, ?array $u = null): bool
{
    $u = $u ?? user();
    if (!$u) {
        return false;
    }
    if ($u['role'] === 'admin') {
        return true;
    }
    $map = role_permissions();
    $list = $map[$u['role']] ?? [];
    if (in_array('*', $list, true) || in_array($perm, $list, true)) {
        return true;
    }
    $parts = explode('.', $perm);
    while (count($parts) > 1) {
        array_pop($parts);
        if (in_array(implode('.', $parts) . '.*', $list, true)) {
            return true;
        }
    }
    return false;
}

function require_can(string $perm): void
{
    require_auth();
    if (!can($perm)) {
        abort(403, 'Vous n’avez pas l’autorisation d’accéder à cette ressource.');
    }
}

function require_role(array $roles): array
{
    $u = require_auth();
    if (!in_array($u['role'], $roles, true)) {
        abort(403, 'Accès réservé.');
    }
    return $u;
}

function role_permissions(): array
{
    return [
        'admin' => ['*'],
        'finance' => [
            'dashboard.view', 'rents.*', 'payments.*', 'receipts.*', 'commissions.*',
            'payouts.*', 'expenses.*', 'reports.finance', 'contracts.view', 'properties.view',
            'owners.view', 'tenants.view', 'logs.view',
        ],
        'manager' => [
            'dashboard.view', 'properties.view', 'properties.edit', 'tenants.*', 'contracts.*',
            'rents.view', 'maintenance.*', 'expenses.create', 'expenses.view', 'owners.view',
            'reports.owner', 'visits.view', 'documents.*', 'stay.*',
        ],
        'supervisor' => [
            'dashboard.view', 'prospects.*', 'properties.create', 'properties.view', 'properties.edit',
            'properties.publish_limited', 'visits.*', 'agents.team', 'commissions.team',
            'contracts.view', 'tasks.*',
        ],
        'agent' => [
            'dashboard.view', 'prospects.own', 'properties.create', 'properties.own',
            'visits.own', 'clients.own', 'commissions.own', 'tasks.own', 'agenda.own',
        ],
        'owner' => ['portal.owner'],
        'tenant' => ['portal.tenant'],
        'traveler' => ['portal.traveler'],
    ];
}

function login(string $email, string $password): array
{
    $email = mb_strtolower(trim($email));
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0';
    $lockMin = (int) config('security.login_lock_minutes', 15);
    $max = (int) config('security.login_max_attempts', 6);

    $fails = (int) db()->val(
        'SELECT COUNT(*) FROM login_attempts WHERE email = ? AND ip = ? AND success = 0 AND created_at > ?',
        [$email, $ip, date('Y-m-d H:i:s', time() - $lockMin * 60)]
    );
    if ($fails >= $max) {
        return ['ok' => false, 'error' => "Trop de tentatives. Réessayez dans $lockMin minutes."];
    }

    $u = db()->one('SELECT * FROM users WHERE email = ?', [$email]);
    if (!$u) {
        try {
            $u = db()->one('SELECT * FROM utilisateurs WHERE email = ? OR mail = ?', [$email, $email]);
        } catch (Throwable $e) {
            $u = null;
        }
    }
    $ok = false;
    $rehash = false;
    if ($u) {
        $hash = (string) ($u['password'] ?? $u['mot_de_passe'] ?? $u['mdp'] ?? '');
        if (password_verify($password, $hash)) {
            $ok = true;
        } elseif (strlen($hash) === 32 && hash_equals($hash, md5($password))) {
            $ok = $rehash = true;
        } elseif (strlen($hash) === 40 && hash_equals(strtolower($hash), sha1($password))) {
            $ok = $rehash = true;
        }
    }
    db()->insert('login_attempts', [
        'email' => $email,
        'ip' => $ip,
        'success' => $ok ? 1 : 0,
        'created_at' => now(),
    ]);
    if (!$ok) {
        return ['ok' => false, 'error' => 'Identifiants incorrects.'];
    }
    $st = strtolower((string) ($u['status'] ?? $u['statut'] ?? 'actif'));
    if (!in_array($st, ['actif', 'active', '1', 'oui', ''], true)) {
        return ['ok' => false, 'error' => 'Compte suspendu. Contactez YOUPENDI.'];
    }
    if ($rehash) {
        db()->update('users', ['password' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [$u['id']]);
    }
    session_regenerate_id(true);
    unset($u['password']);
    $_SESSION['user'] = $u;
    db()->update('users', ['last_login' => now()], 'id = ?', [$u['id']]);
    log_activity('connexion', 'users', $u['id']);
    return ['ok' => true, 'user' => $u];
}

function logout(): void
{
    if (user()) {
        log_activity('deconnexion', 'users', user()['id']);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function agent_of(?array $u = null): ?array
{
    $u = $u ?? user();
    if (!$u) {
        return null;
    }
    return db()->one('SELECT * FROM immo_agents WHERE user_id = ?', [$u['id']]);
}

function owner_of(?array $u = null): ?array
{
    $u = $u ?? user();
    if (!$u) {
        return null;
    }
    return db()->one('SELECT * FROM owners WHERE user_id = ?', [$u['id']]);
}

function tenant_of(?array $u = null): ?array
{
    $u = $u ?? user();
    if (!$u) {
        return null;
    }
    return db()->one('SELECT * FROM tenants WHERE user_id = ?', [$u['id']]);
}

function can_see_property(array $p, ?array $u = null): bool
{
    $u = $u ?? user();
    if (!$u) {
        return in_array($p['status'], ['publie', 'disponible', 'visite_en_cours'], true);
    }
    if (in_array($u['role'], ['admin', 'finance', 'manager'], true)) {
        return true;
    }
    if ($u['role'] === 'supervisor') {
        $a = agent_of($u);
        if (!$a) {
            return true;
        }
        $teamIds = array_column(db()->all('SELECT id FROM immo_agents WHERE team_id = ? OR supervisor_id = ? OR id = ?', [$a['team_id'], $a['id'], $a['id']]), 'id');
        return in_array((int) $p['agent_apporteur_id'], $teamIds, true)
            || in_array((int) $p['agent_commercial_id'], $teamIds, true)
            || in_array((int) $p['manager_id'], $teamIds, true);
    }
    if ($u['role'] === 'agent') {
        $a = agent_of($u);
        if (!$a) {
            return false;
        }
        return (int) $p['agent_apporteur_id'] === (int) $a['id']
            || (int) $p['agent_commercial_id'] === (int) $a['id']
            || (int) $p['manager_id'] === (int) $a['id'];
    }
    if ($u['role'] === 'owner') {
        $o = owner_of($u);
        return $o && (int) $p['owner_id'] === (int) $o['id'];
    }
    if ($u['role'] === 'tenant') {
        $t = tenant_of($u);
        return $t && (int) ($t['property_id'] ?? 0) === (int) $p['id'];
    }
    return in_array($p['status'], ['publie', 'disponible', 'visite_en_cours'], true);
}

function property_scope_sql(string $alias = 'p'): array
{
    $u = user();
    if (!$u || in_array($u['role'], ['admin', 'finance', 'manager'], true)) {
        return ['1=1', []];
    }
    if ($u['role'] === 'agent') {
        $a = agent_of($u);
        $id = $a['id'] ?? 0;
        return ["($alias.agent_apporteur_id = ? OR $alias.agent_commercial_id = ? OR $alias.manager_id = ?)", [$id, $id, $id]];
    }
    if ($u['role'] === 'supervisor') {
        $a = agent_of($u);
        if (!$a) {
            return ['1=1', []];
        }
        $ids = array_map('intval', array_column(db()->all(
            'SELECT id FROM immo_agents WHERE team_id = ? OR supervisor_id = ? OR id = ?',
            [$a['team_id'], $a['id'], $a['id']]
        ), 'id'));
        if (!$ids) {
            $ids = [(int) $a['id']];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        return ["($alias.agent_apporteur_id IN ($in) OR $alias.agent_commercial_id IN ($in) OR $alias.manager_id IN ($in))", array_merge($ids, $ids, $ids)];
    }
    if ($u['role'] === 'owner') {
        $o = owner_of($u);
        return ["$alias.owner_id = ?", [$o['id'] ?? 0]];
    }
    return ['0=1', []];
}
