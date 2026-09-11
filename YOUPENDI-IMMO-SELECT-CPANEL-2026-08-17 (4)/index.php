<?php
/**
 * Pont public_html → YOUPENDI IMMO SELECT
 *
 * Accepte les deux implantations du paquet :
 *   public_html/youpendimmoselect/public/index.php  (recommandé)
 *   public_html/public/index.php                    (paquet extrait à plat)
 */
$candidates = [
    __DIR__ . '/youpendimmoselect/public/index.php',
    __DIR__ . '/public/index.php',
];
foreach ($candidates as $app) {
    if (is_file($app)) {
        require $app;
        return;
    }
}
http_response_code(500);
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><meta charset="utf-8"><title>YOUPENDI</title>';
echo '<p style="font-family:sans-serif;max-width:640px;margin:40px auto">';
echo 'Application introuvable dans <code>public_html</code>.<br>';
echo 'Le dossier <code>youpendimmoselect</code> (ou <code>public</code>) doit contenir '
    . '<code>public/index.php</code>, <code>app/</code> et <code>config.php</code>.</p>';
