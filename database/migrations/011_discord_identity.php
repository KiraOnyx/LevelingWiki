<?php
declare(strict_types=1);
return static function(PDO $pdo): void {
    $column=static function(string $name) use($pdo): bool {
        $q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=\'users\' AND COLUMN_NAME=?');$q->execute([$name]);return (bool)$q->fetchColumn();
    };
    $pdo->exec("ALTER TABLE users MODIFY role ENUM('admin','editor','member','super_admin') NOT NULL DEFAULT 'member'");
    if (!$column('auth_provider')) $pdo->exec("ALTER TABLE users ADD auth_provider ENUM('local','discord') NOT NULL DEFAULT 'local'");
    if (!$column('owner_slot')) $pdo->exec("ALTER TABLE users ADD owner_slot TINYINT GENERATED ALWAYS AS (CASE WHEN role='super_admin' THEN 1 ELSE NULL END) STORED, ADD UNIQUE KEY uq_single_owner (owner_slot), ADD CONSTRAINT chk_local_owner CHECK (role <> 'super_admin' OR auth_provider='local')");
    $pdo->exec("CREATE TABLE IF NOT EXISTS discord_identities (
        user_id BIGINT UNSIGNED PRIMARY KEY, discord_user_id VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
        display_name VARCHAR(100) NOT NULL, avatar_hash VARCHAR(100) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_discord_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS discord_roles (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        level ENUM('member','editor','admin') NOT NULL,
        discord_role_id VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        label VARCHAR(100) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_discord_level_role(level,discord_role_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS authorization_policy (
        id TINYINT PRIMARY KEY, version BIGINT UNSIGNED NOT NULL DEFAULT 1,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT chk_policy_singleton CHECK(id=1)
    ) ENGINE=InnoDB");
    $pdo->exec('INSERT IGNORE INTO authorization_policy(id) VALUES(1)');
    // Aucun compte n’est promu. La CLI de désignation exige une confirmation et le mot de passe existant.
};
