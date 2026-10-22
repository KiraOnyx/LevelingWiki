<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "OK : $message\n";
}
function rejects(PDO $pdo, string $sql, string $message): void
{
    try {
        $pdo->exec($sql);
    } catch (PDOException $error) {
        if (in_array($error->errorInfo[1] ?? null, [1062, 1452, 1264, 4025, 3819], true)) {
            echo "OK : $message\n";
            return;
        }
        throw $error;
    }
    throw new RuntimeException($message);
}

$pdo = null;
try {
    $pdo = App\Services\Database::connect();
    check((int) $pdo->query('SELECT 1')->fetchColumn() === 1, 'connexion PDO MariaDB');
    foreach (['users', 'categories', 'titles', 'achievements', 'achievement_titles', 'audit_logs'] as $table) {
        $pdo->query("SELECT 1 FROM `$table` LIMIT 1");
        echo "OK : table $table\n";
    }
    check((int) $pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn() === 6, 'six migrations enregistrées');
    $pdo->beginTransaction();
    $token = bin2hex(random_bytes(8));
    $pdo->exec("INSERT INTO users (username, email, password_hash) VALUES ('$token', '$token@example.invalid', 'test-only')");
    $user = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO categories (slug, name) VALUES ('$token', '$token')");
    $category = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO achievements (slug, name, category_id, created_by, reward_gold) VALUES ('$token', '$token', $category, $user, 0)");
    $achievement = (int) $pdo->lastInsertId();
    $row = $pdo->query("SELECT * FROM achievements WHERE id = $achievement")->fetch();
    check($row['reward_xp'] === null && (int) $row['reward_gold'] === 0, 'récompense inconnue distincte de zéro');
    check($row['rarity'] === null && $row['secret_type'] === 'unknown' && $row['verification_status'] === 'unverified', 'valeurs métier initiales');
    rejects($pdo, "INSERT INTO achievements (slug, name) VALUES ('$token', 'duplicate')", 'unicité du slug');
    rejects($pdo, "UPDATE achievements SET category_id = 0 WHERE id = $achievement", 'clé étrangère catégorie');
    rejects($pdo, "UPDATE achievements SET reward_xp = -1 WHERE id = $achievement", 'récompense négative refusée');
    rejects($pdo, "UPDATE achievements SET verification_status = 'verified' WHERE id = $achievement", 'vérification sans date refusée');
    $pdo->exec("UPDATE achievements SET verification_status = 'verified', verified_at = CURRENT_TIMESTAMP, verified_by = $user WHERE id = $achievement");
    $pdo->exec("INSERT INTO titles (slug, name) VALUES ('$token', '$token')");
    $title = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO achievement_titles (achievement_id, title_id) VALUES ($achievement, $title)");
    rejects($pdo, "INSERT INTO achievement_titles (achievement_id, title_id) VALUES ($achievement, $title)", 'association de titre unique');
    $pdo->exec("INSERT INTO audit_logs (actor_id, action, entity_type, entity_id, new_values) VALUES ($user, 'test', 'achievement', $achievement, '{}')");
    $audit = (int) $pdo->lastInsertId();
    $pdo->exec("DELETE FROM users WHERE id = $user");
    check($pdo->query("SELECT actor_id FROM audit_logs WHERE id = $audit")->fetchColumn() === null, 'audit conservé après suppression de son auteur');
    $pdo->exec("DELETE FROM categories WHERE id = $category");
    check($pdo->query("SELECT category_id FROM achievements WHERE id = $achievement")->fetchColumn() === null, 'succès conservé après suppression de sa catégorie');
    $pdo->exec("DELETE FROM achievements WHERE id = $achievement");
    check((int) $pdo->query("SELECT COUNT(*) FROM achievement_titles WHERE achievement_id = $achievement")->fetchColumn() === 0, 'suppression en cascade des associations');
    $pdo->rollBack();
    echo "Tests terminés ; données de test annulées.\n";
} catch (Throwable) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Échec du test suivant le dernier contrôle OK (détails de connexion masqués).\n");
    exit(1);
}
