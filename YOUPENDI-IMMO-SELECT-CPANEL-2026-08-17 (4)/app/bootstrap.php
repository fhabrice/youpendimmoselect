<?php

declare(strict_types=1);

date_default_timezone_set('Africa/Lubumbashi');

require __DIR__ . '/helpers.php';
require __DIR__ . '/Database.php';
require __DIR__ . '/Auth.php';
require __DIR__ . '/migrate.php';
require __DIR__ . '/agent_compat.php';
require __DIR__ . '/seed.php';
require __DIR__ . '/legacy_import.php';
require __DIR__ . '/hosting.php';

$config = config();
date_default_timezone_set($config['timezone'] ?? 'Africa/Lubumbashi');

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

session_name($config['security']['session_name'] ?? 'YPSESSID');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!empty($config['debug'])) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE);
    ini_set('display_errors', '0');
}

$path = request_path();

try {
    migrate(db());
    if (db()->driver === 'mysql') {
        ensure_hosting_schema(db());
        if ((int) db()->val('SELECT COUNT(*) FROM users') === 0) {
            import_legacy();
        }
    } else {
        seed_if_empty();
    }
    $lock = dirname(__DIR__) . '/storage/installed.lock';
    if (!is_file($lock)) {
        file_put_contents($lock, now());
    }
    auth_refresh();
} catch (Throwable $e) {
    if (!str_starts_with($path, '/install') && $path !== '/health' && $path !== '/sync-base') {
        $_SESSION['install_error'] = $e->getMessage();
        redirect('install');
    }
}
