<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';

try {
    $pdo = App\Services\Database::connect();
    if ((int) $pdo->query("SELECT GET_LOCK('levelingwiki_migrations', 30)")->fetchColumn() !== 1) {
        throw new RuntimeException('Verrou de migration indisponible.');
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
            version VARCHAR(191) PRIMARY KEY,
            checksum CHAR(64) NOT NULL,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $files = array_merge(glob(dirname(__DIR__) . '/database/migrations/*.sql'), glob(dirname(__DIR__) . '/database/migrations/*.php'));
        sort($files, SORT_STRING);
        foreach ($files as $file) {
            $version = basename($file);
            $sql = file_get_contents($file);
            $checksum = hash('sha256', $sql);
            $query = $pdo->prepare('SELECT checksum FROM schema_migrations WHERE version = ?');
            $query->execute([$version]);
            $existing = $query->fetchColumn();
            if ($existing !== false) {
                if (!hash_equals($existing, $checksum)) {
                    throw new RuntimeException('Migration déjà appliquée modifiée.');
                }
                echo "Déjà appliquée : $version\n";
                continue;
            }
            // Le DDL ne rollback pas. Les migrations PHP inspectent chaque étape avant application.
            if (str_ends_with($file, '.php')) {
                $migration = require $file;
                $migration($pdo);
            } else {
                $pdo->exec($sql);
            }
            $pdo->prepare('INSERT INTO schema_migrations (version, checksum) VALUES (?, ?)')
                ->execute([$version, $checksum]);
            echo "Appliquée : $version\n";
        }
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('levelingwiki_migrations')");
    }
} catch (Throwable) {
    fwrite(STDERR, "Échec des migrations. Vérifier la connexion, le schéma et les empreintes des fichiers appliqués.\n");
    exit(1);
}
