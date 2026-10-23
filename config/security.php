<?php
declare(strict_types=1);
// Peut être adapté sur un mutualisé qui ne fournit pas de variables d’environnement.
return [
    'idle_seconds' => max(1, (int) (getenv('SESSION_IDLE_SECONDS') ?: 1800)),
    'absolute_seconds' => max(1, (int) (getenv('SESSION_ABSOLUTE_SECONDS') ?: 28800)),
    'secure_cookie' => getenv('SESSION_SECURE_COOKIE') === '1' || getenv('APP_ENV') === 'production'
        || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'upload_max_bytes' => max(1, (int) (getenv('UPLOAD_MAX_BYTES') ?: 5242880)),
    'upload_directory' => getenv('UPLOAD_DIRECTORY') ?: dirname(__DIR__) . '/storage/uploads',
    'login_window' => 900,
    'login_account_limit' => 5,
    'login_ip_limit' => 30,
];
