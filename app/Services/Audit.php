<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
final class Audit
{
    public static function record(PDO $pdo, ?int $actor, string $action, string $entity, ?int $id, ?array $before = null, ?array $after = null): void
    {
        $allowed = ['short_name','master_ids','version','name','slug','description','unlock_conditions','category_id','rarity','secret_type','reward_xp','reward_gold',
            'reward_gems','reward_keys','title_rewards_known','title_ids','verification_status','publication_status','archived_at',
            'discord_user_id','discord_role_id','level','label','progress','task_status','import_actor','source_number','source_title','source_edition','source_status','source_note','verification_note','source_sha256','source_category','source_url','evidence_path','username','email','role','is_active','color'];
        $clean = static fn(?array $values) => $values === null ? null : json_encode(array_intersect_key($values, array_flip($allowed)), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $pdo->prepare('INSERT INTO audit_logs (actor_id, action, entity_type, entity_id, old_values, new_values) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$actor, $action, $entity, $id, $clean($before), $clean($after)]);
    }
}
