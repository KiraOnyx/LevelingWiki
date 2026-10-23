<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'test' || getenv('DB_DATABASE') !== 'levelingwiki_test') {
    fwrite(STDERR, "Fixtures interdites en dehors de la base isolée levelingwiki_test et APP_ENV=test.\n");
    exit(1);
}
$pdo = App\Services\Database::connect();
if ((int) $pdo->query('SELECT COUNT(*) FROM achievements')->fetchColumn() !== 0) {
    fwrite(STDERR, "La base de test doit être vide.\n"); exit(1);
}
$pdo->beginTransaction();
try {
    $pdo->exec("INSERT INTO categories (id, slug, name) VALUES (1, 'entrainement-test', 'Entraînement'), (2, 'exploration-test', 'Exploration')");
    $fixtures = json_decode(file_get_contents(__DIR__ . '/fixtures.json'), true, flags: JSON_THROW_ON_ERROR);
    foreach ($fixtures as $item) {
        $statement = $pdo->prepare('INSERT INTO achievements
            (slug, name, description, category_id, rarity, secret_type, reward_xp, reward_gold, reward_gems, reward_keys,
             title_rewards_known, verification_status, verified_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $statement->execute([$item['slug'], $item['name'], $item['description'], $item['category_id'], $item['rarity'],
            $item['secret_type'], $item['reward_xp'], $item['reward_gold'], $item['reward_gems'], $item['reward_keys'],
            (int) $item['title_rewards_known'], $item['verification_status'],
            $item['verification_status'] === 'verified' ? $item['created_at'] : null, $item['created_at']]);
        $id = $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO achievement_details (achievement_id, unlock_conditions) VALUES (?, ?)')->execute([$id, $item['unlock_conditions']]);
        foreach ($item['titles'] as $name) {
            $pdo->prepare('INSERT INTO titles (slug, name) VALUES (?, ?)')->execute(['titre-test-' . $id, $name]);
            $title = $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO achievement_titles (achievement_id, title_id) VALUES (?, ?)')->execute([$id, $title]);
        }
    }
    $pdo->exec("UPDATE achievements SET publication_status = 'published'");
    $pdo->commit();
    echo "Quatre fixtures créées dans levelingwiki_test uniquement.\n";
} catch (Throwable $error) { $pdo->rollBack(); throw $error; }
