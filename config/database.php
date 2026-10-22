<?php
declare(strict_types=1);

// Option pour les hébergements sans variables d’environnement, hors de public/.
$localFile = __DIR__ . '/local.php';
$local = is_file($localFile) ? require $localFile : [];
$config = [];
foreach (['host' => 'DB_HOST', 'port' => 'DB_PORT', 'database' => 'DB_DATABASE',
    'username' => 'DB_USERNAME', 'password' => 'DB_PASSWORD'] as $key => $env) {
    $value = getenv($env);
    $config[$key] = $value !== false ? $value : ($local[$key] ?? null);
    if ($config[$key] === null || $config[$key] === '') {
        throw new RuntimeException('Configuration de base de données incomplète.');
    }
}
if (!ctype_digit((string) $config['port']) || (int) $config['port'] < 1 || (int) $config['port'] > 65535) {
    throw new RuntimeException('Port de base de données invalide.');
}
foreach (['host', 'database'] as $key) {
    if (preg_match('/[;\x00-\x1f]/', (string) $config[$key])) {
        throw new RuntimeException('Configuration de base de données invalide.');
    }
}
return $config;
