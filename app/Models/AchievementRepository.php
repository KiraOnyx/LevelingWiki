<?php
declare(strict_types=1);
namespace App\Models;
use PDO;

final class AchievementRepository
{
    public function __construct(private PDO $pdo) {}

    public function all(): array
    {
        $rows = $this->pdo->query("SELECT a.id, a.slug, a.name, a.description, a.category_id,
            a.rarity, a.secret_type, a.reward_xp, a.reward_gold, a.reward_gems, a.reward_keys,
            a.title_rewards_known, a.verification_status, a.created_at,
            GREATEST(a.updated_at, COALESCE(d.updated_at, a.updated_at)) AS updated_at,
            c.name AS category,c.color AS category_color,a.source_url, d.unlock_conditions, d.icon_path, d.evidence_path
            FROM achievements a LEFT JOIN categories c ON c.id = a.category_id
            LEFT JOIN achievement_details d ON d.achievement_id = a.id
            WHERE a.publication_status = 'published' AND a.archived_at IS NULL
            ORDER BY a.name, a.id")->fetchAll();
        $titles = [];
        foreach ($this->pdo->query('SELECT at.achievement_id, t.name FROM achievement_titles at
            JOIN titles t ON t.id = at.title_id ORDER BY t.name') as $row) {
            $titles[(int) $row['achievement_id']][] = $row['name'];
        }
        foreach ($rows as &$row) {
            foreach (['reward_xp', 'reward_gold', 'reward_gems', 'reward_keys', 'category_id'] as $field) {
                if ($row[$field] !== null) $row[$field] = (string) $row[$field];
            }
            $row['titles'] = $titles[(int) $row['id']] ?? [];
            $row['title_rewards_known'] = (bool) $row['title_rewards_known'];
        }
        return $rows;
    }

    public function categories(): array
    {
        return $this->pdo->query("SELECT id, name, color FROM categories WHERE archived_at IS NULL ORDER BY name")->fetchAll();
    }
}
