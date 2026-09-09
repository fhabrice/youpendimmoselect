<?php
/**
 * Pont public_html → YOUPENDI IMMO SELECT
 */
$app = __DIR__ . '/youpendimmoselect/public/index.php';
if (!is_file($app)) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>YOUPENDI</title>';
    echo '<p style="font-family:sans-serif;max-width:640px;margin:40px auto">';
    echo 'Dossier <code>youpendimmoselect</code> introuvable dans public_html.<br>';
    echo 'Déplacez le contenu de <strong>A-METTRE-DANS-public_html</strong> ici.</p>';
    exit;
}
require $app;
