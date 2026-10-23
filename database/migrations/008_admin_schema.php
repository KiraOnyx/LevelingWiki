<?php
declare(strict_types=1);
// Chaque étape est introspectée : relance possible après interruption du DDL.
return static function (PDO $pdo): void {
    $column = static function (string $table, string $name) use ($pdo): ?string {
        $query = $pdo->prepare('SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $query->execute([$table, $name]);
        return $query->fetchColumn() ?: null;
    };
    if (!$column('users', 'is_active')) $pdo->exec('ALTER TABLE users ADD is_active BOOLEAN NOT NULL DEFAULT TRUE');
    if (!$column('users', 'session_version')) $pdo->exec('ALTER TABLE users ADD session_version INT UNSIGNED NOT NULL DEFAULT 1');
    if (!$column('users', 'must_change_password')) $pdo->exec('ALTER TABLE users ADD must_change_password BOOLEAN NOT NULL DEFAULT FALSE');
    if ($column('users', 'role') !== "enum('admin','editor')") {
        $pdo->exec("ALTER TABLE users MODIFY role ENUM('member','moderator','admin','editor') NOT NULL DEFAULT 'editor'");
        $pdo->exec("UPDATE users SET is_active = FALSE WHERE role = 'member'");
        $pdo->exec("UPDATE users SET role = 'editor' WHERE role IN ('member','moderator')");
        $pdo->exec("ALTER TABLE users MODIFY role ENUM('admin','editor') NOT NULL DEFAULT 'editor'");
    }
    if (!$column('achievements', 'publication_status')) {
        $pdo->exec("ALTER TABLE achievements ADD publication_status ENUM('draft','published') NOT NULL DEFAULT 'draft', ADD INDEX idx_publication (publication_status)");
    }
    if (!$column('achievements', 'archived_at')) $pdo->exec('ALTER TABLE achievements ADD archived_at TIMESTAMP NULL');
    if (!$column('categories', 'color')) $pdo->exec("ALTER TABLE categories ADD color CHAR(7) NOT NULL DEFAULT '#65C66B'");
    if (!$column('categories', 'archived_at')) $pdo->exec('ALTER TABLE categories ADD archived_at TIMESTAMP NULL');
};
