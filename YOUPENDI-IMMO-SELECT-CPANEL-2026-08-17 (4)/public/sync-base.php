<?php

require dirname(__DIR__) . '/app/bootstrap.php';

header('Content-Type: text/html; charset=utf-8');
$user = user();
if (!$user || ($user['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo '<!doctype html><meta charset="utf-8"><h1>Accès refusé</h1><p>Connectez-vous comme administrateur pour vérifier la base.</p>';
    exit;
}

try {
    migrate(db());
    $tables = db()->driver === 'mysql'
        ? array_map(fn($row) => (string) array_values($row)[0], db()->all('SHOW TABLES'))
        : array_column(db()->all("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name"), 'name');
    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>Diagnostic base de données</title></head>';
    echo '<body style="font-family:sans-serif;max-width:800px;margin:32px auto">';
    echo '<h1>Base de données connectée</h1><p>Moteur <code>' . e(db()->driver) . '</code> — ' . count($tables) . ' tables. Les migrations sont à jour.</p>';
    echo '<p><a href="' . e(base_url('app')) . '">Tableau de bord</a></p></body></html>';
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h1>Base de données injoignable</h1><p>Vérifiez la configuration dans <code>storage/config.local.php</code> ou les variables d’environnement.</p>';
}
