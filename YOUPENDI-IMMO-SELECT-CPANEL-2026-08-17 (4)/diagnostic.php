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
row(is_file(__DIR__ . '/index.php'), 'index.php à la racine de public_html');
row(is_dir(__DIR__ . '/youpendimmoselect/app'), 'dossier youpendimmoselect/app');
row(is_file(__DIR__ . '/youpendimmoselect/config.php'), 'config.php');
row(is_file(__DIR__ . '/youpendimmoselect/app/http_app.php'), 'http_app.php');
row(is_file(__DIR__ . '/youpendimmoselect/public/assets/css/app.css'), 'CSS');
row(is_file(__DIR__ . '/youpendimmoselect/app/views/auth/login.php'), 'page connexion');
$login = (string) @file_get_contents(__DIR__ . '/youpendimmoselect/app/views/auth/login.php');
row(strpos($login, 'démonstration') === false && strpos($login, 'youpendi.cd') === false, 'aucun compte démo affiché');
$app = (string) @file_get_contents(__DIR__ . '/youpendimmoselect/app/http_app.php');
row(substr_count($app, '{') > 0, 'http_app.php lisible');
row(extension_loaded('pdo'), 'Extension PDO disponible');
row(extension_loaded('pdo_mysql'), 'Pilote PDO MySQL disponible');
row(is_writable(__DIR__ . '/youpendimmoselect') || is_writable(__DIR__ . '/youpendimmoselect/storage'), 'Dossier applicatif accessible en écriture');
echo '<p>Pour des raisons de sécurité, les identifiants et les tables MySQL ne sont jamais affichés sur cette page publique.</p>';
echo '<p>PHP ' . PHP_VERSION . '</p>';
echo '<p><a href="/">← Site</a> · <a href="/connexion">Connexion</a></p></body></html>';
