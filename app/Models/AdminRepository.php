<?php
declare(strict_types=1);
namespace App\Models;
use PDO;
final class AdminRepository
{
    public function __construct(private PDO $pdo) {}
    public function statistics(): array
    {
        $items = $this->achievements();
        $active = array_filter($items,fn($a)=>$a['archived_at'] === null);
        return [count($items), count(array_filter($active,fn($a)=>$a['publication_status']==='published')),
            count(array_filter($active,fn($a)=>$a['publication_status']==='draft')),
            count(array_filter($active,fn($a)=>$a['secret_type']==='secret')),
            count(array_filter($active,fn($a)=>!\App\Services\CatalogService::rewardsKnown($a))),
            (int)$this->pdo->query('SELECT COUNT(*) FROM categories WHERE archived_at IS NULL')->fetchColumn(),
            (int)$this->pdo->query('SELECT COUNT(*) FROM titles')->fetchColumn()];
    }
    public function achievements(): array
    {
        return $this->pdo->query('SELECT a.*, c.name AS category FROM achievements a LEFT JOIN categories c ON c.id = a.category_id ORDER BY a.updated_at DESC, a.id DESC')->fetchAll();
    }
    public function achievement(int $id): ?array
    {
        $query = $this->pdo->prepare('SELECT a.*, d.unlock_conditions, d.evidence_path FROM achievements a LEFT JOIN achievement_details d ON d.achievement_id = a.id WHERE a.id = ?');
        $query->execute([$id]); $item = $query->fetch();
        if (!$item) return null;
        $query = $this->pdo->prepare('SELECT title_id FROM achievement_titles WHERE achievement_id = ?');
        $query->execute([$id]); $item['title_ids'] = array_map('intval', $query->fetchAll(PDO::FETCH_COLUMN));
        return $item;
    }
    public function categories(): array { return $this->pdo->query('SELECT c.*, (SELECT COUNT(*) FROM achievements a WHERE a.category_id = c.id) AS uses_count FROM categories c ORDER BY c.archived_at IS NOT NULL, c.name')->fetchAll(); }
    public function titles(): array { return $this->pdo->query('SELECT t.*, (SELECT COUNT(*) FROM achievement_titles a WHERE a.title_id = t.id) AS uses_count FROM titles t ORDER BY t.name')->fetchAll(); }
    public function users(): array { return $this->pdo->query('SELECT u.id, u.username, u.email, u.role, u.auth_provider, u.is_active, u.must_change_password, u.updated_at, d.display_name, d.discord_user_id FROM users u LEFT JOIN discord_identities d ON d.user_id=u.id ORDER BY u.id')->fetchAll(); }
    public function history(int $page): array
    {
        $query = $this->pdo->prepare('SELECT l.*, u.username FROM audit_logs l LEFT JOIN users u ON u.id = l.actor_id ORDER BY l.id DESC LIMIT 50 OFFSET ?');
        $query->bindValue(1, ($page - 1) * 50, PDO::PARAM_INT); $query->execute(); return $query->fetchAll();
    }
}
