<?php
declare(strict_types=1);
namespace App\Models;
use PDO;
final class GuildDivisionRepository
{
    public function __construct(private PDO $pdo) {}
    public function all(bool $includeArchived=false): array {
        return $this->pdo->query('SELECT * FROM guild_divisions'.($includeArchived?'':' WHERE archived_at IS NULL').' ORDER BY level DESC,name,id')->fetchAll();
    }
    public function find(int $id): ?array {
        $q=$this->pdo->prepare('SELECT * FROM guild_divisions WHERE id=?');$q->execute([$id]);return $q->fetch()?:null;
    }
    public function masterIds(int $divisionId): array {
        $q=$this->pdo->prepare('SELECT user_id FROM guild_division_masters WHERE division_id=? ORDER BY user_id');$q->execute([$divisionId]);return array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
    }
    public function assignedIds(int $userId): array {
        $q=$this->pdo->prepare('SELECT division_id FROM guild_division_masters WHERE user_id=?');$q->execute([$userId]);return array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
    }
    public function candidates(): array {
        return $this->pdo->query("SELECT u.id,COALESCE(d.display_name,u.username) AS name FROM users u LEFT JOIN discord_identities d ON d.user_id=u.id WHERE u.is_active=1 AND u.auth_provider='discord' AND u.role IN ('editor','admin') ORDER BY name,u.id")->fetchAll();
    }
    public function history(int $divisionId,int $before=0): array {
        $q=$this->pdo->prepare('SELECT h.*,COALESCE(d.display_name,u.username,\'Compte supprimé\') AS actor_name FROM guild_division_levels h LEFT JOIN users u ON u.id=h.actor_id LEFT JOIN discord_identities d ON d.user_id=u.id WHERE h.division_id=? AND (?=0 OR h.id<?) ORDER BY h.id DESC LIMIT 50');
        $q->execute([$divisionId,$before,$before]);return $q->fetchAll();
    }
}
