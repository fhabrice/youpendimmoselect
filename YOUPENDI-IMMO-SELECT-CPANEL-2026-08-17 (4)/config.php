<?php
/**
 * YOUPENDI IMMO SELECT — configuration
 * Renseignez vos identifiants MySQL d'hébergeur ici,
 * ou utilisez l'assistant /install.
 */
return [
    'app_name'     => 'YOUPENDI IMMO SELECT',
    'app_tagline'  => 'L’immobilier de confiance, de la prospection au reversement',
    'env'          => 'production',
    'debug'        => false,
    'timezone'     => 'Africa/Lubumbashi',
    'locale'       => 'fr_FR',
    'currency'     => 'USD',
    'currency_alt' => 'CDF',
    'base_url'     => getenv('YP_BASE_URL') ?: 'https://www.youpendimmoselect.com',

    'db' => [
        'driver'          => getenv('YP_DB_DRIVER') ?: 'mysql',
        'host'            => getenv('YP_DB_HOST') ?: 'localhost',
        'port'            => (int) (getenv('YP_DB_PORT') ?: 3306),
        // Coordonnées historiques conservées pour rester compatible avec la base en production.
        // Les variables d’environnement permettent toujours de les remplacer sans modifier ce fichier.
        'name'            => getenv('YP_DB_NAME') ?: 'wqmetrvw_immo',
        'user'            => getenv('YP_DB_USER') ?: 'wqmetrvw_youpendi',
        'pass'            => getenv('YP_DB_PASS') ?: 'Goma@2019',
        'charset'         => 'utf8mb4',
        'sqlite_path'     => __DIR__ . '/storage/youpendi.sqlite',
        'fallback_sqlite' => false,
    ],

    'security' => [
        'session_name'        => 'YPSESSID',
        'login_max_attempts'  => 6,
        'login_lock_minutes'  => 15,
        'csrf_key'            => 'yp_csrf',
        'password_min'        => 8,
    ],

    'contact' => [
        'email'    => 'contact@youpendimmoselect.com',
        'phone'    => '+243 994 052 587',
        'whatsapp' => '243994052587',
        'address'  => 'Avenue de la Libération, Gombe — Kinshasa, RDC',
        'hours'    => 'Lun–Sam, 8h00 – 18h00',
    ],

    'upload' => [
        'max_mb'   => 8,
        'mimes'    => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
        'dir'      => __DIR__ . '/public/uploads',
    ],

    'commission_defaults' => [
        'youpendi_mgmt'   => 10.0,
        'agent_apporteur' => 30.0, // % de la commission YOUPENDI
        'agent_commercial'=> 40.0,
        'supervisor'      => 10.0,
    ],
];
