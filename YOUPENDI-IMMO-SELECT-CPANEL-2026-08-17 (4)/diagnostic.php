<?php
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>Diagnostic YOUPENDI</title>';
echo '<style>body{font-family:sans-serif;max-width:820px;margin:32px auto;padding:0 16px;line-height:1.5}.ok{color:#166534}.ko{color:#b91c1c}code{background:#f3f4f6;padding:2px 6px;border-radius:4px}</style></head><body>';
echo '<h1>Diagnostic YOUPENDI IMMO SELECT</h1>';
function row(bool $ok, string $label, string $detail = ''): void
{
    echo '<p><strong class="' . ($ok ? 'ok' : 'ko') . '">' . ($ok ? 'OK' : 'KO') . '</strong> — '
        . htmlspecialchars($label) . ($detail !== '' ? ' <code>' . htmlspecialchars($detail) . '</code>' : '') . '</p>';
}

// Le paquet peut être extrait dans public_html/youpendimmoselect/ ou directement
// dans public_html : on détecte l'implantation réelle.
$appDir = is_dir(__DIR__ . '/youpendimmoselect/app') ? __DIR__ . '/youpendimmoselect' : __DIR__;

row(is_file(__DIR__ . '/index.php'), 'index.php à la racine de public_html');
row(is_dir($appDir . '/app'), 'dossier applicatif', basename($appDir) . '/app');
row(is_file($appDir . '/config.php'), 'config.php');
row(is_file($appDir . '/app/http_app.php'), 'http_app.php');
row(is_file($appDir . '/public/assets/css/app.css'), 'CSS');
row(is_file($appDir . '/app/views/auth/login.php'), 'page connexion');
$login = (string) @file_get_contents($appDir . '/app/views/auth/login.php');
row(strpos($login, 'démonstration') === false && strpos($login, 'youpendi.cd') === false, 'aucun compte démo affiché');
$app = (string) @file_get_contents($appDir . '/app/http_app.php');
row(substr_count($app, '{') > 0, 'http_app.php lisible');
row(extension_loaded('pdo'), 'Extension PDO disponible');
row(extension_loaded('pdo_mysql'), 'Pilote PDO MySQL disponible');
row(is_writable($appDir) || is_writable($appDir . '/storage'), 'Dossier applicatif accessible en écriture');

echo '<h2>Images de la vitrine</h2>';
$uploads = $appDir . '/public/uploads';
row(is_dir($uploads), 'Dossier des téléversements présent', $uploads);
row(is_dir($uploads) && is_writable($uploads), 'Dossier des téléversements inscriptible (755 ou 775)');
row(is_file($uploads . '/.htaccess'), 'Protection du dossier des téléversements (.htaccess)');
row(is_file($appDir . '/public/assets/img/photo-missing.svg'), 'Image de substitution des biens');
row(is_file($appDir . '/public/assets/img/agent-placeholder.svg'), 'Image de substitution des agents');
row(is_file(__DIR__ . '/.htaccess'), '.htaccess à la racine de public_html (réécriture des URL)');

if (is_file($appDir . '/app/helpers.php') && is_file($appDir . '/app/Database.php')) {
    try {
        require_once $appDir . '/app/helpers.php';
        require_once $appDir . '/app/Database.php';
        $audit = photo_audit();
        row(
            $audit['missing'] === 0,
            'Photos référencées présentes sur le serveur',
            $audit['total'] . ' en base, ' . $audit['missing'] . ' introuvable(s)'
        );
        foreach ($audit['examples'] as $example) {
            echo '<p style="margin-left:18px">Introuvable : <code>' . htmlspecialchars((string) $example) . '</code></p>';
        }
        if ($audit['missing'] > 0) {
            echo '<p style="margin-left:18px">Ces fichiers ont été perdus (mise à jour du paquet ou remise à zéro). '
                . 'La vitrine affiche l’image de substitution en attendant : ouvrez le bien dans '
                . '<strong>Administration &gt; Biens</strong> et téléversez à nouveau ses photos.</p>';
        }
        row(is_writable($uploads), 'Nouveau téléversement possible', upload_root());
    } catch (Throwable $e) {
        row(false, 'Contrôle des photos impossible', $e->getMessage());
    }
}

echo '<p>Pour des raisons de sécurité, les identifiants et les tables MySQL ne sont jamais affichés sur cette page publique.</p>';
echo '<p>PHP ' . PHP_VERSION . '</p>';
echo '<p><a href="/">← Site</a> · <a href="/connexion">Connexion</a></p></body></html>';
