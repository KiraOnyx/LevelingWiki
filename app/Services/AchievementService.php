<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
use App\Models\AdminRepository;
final class AchievementService
{
    public function __construct(private PDO $pdo) {}
    public function save(array $data, ?array $file, ?int $actor): int
    {
        $id = empty($data['id']) ? null : Input::id($data['id']);
        ['values'=>$values, 'conditions'=>$conditions, 'titles'=>$titles] = $this->validate($data);
        $ownsTransaction = !$this->pdo->inTransaction();
        if (!$ownsTransaction && ($file !== null || $id !== null)) throw new ValidationException('Transaction externe réservée aux créations sans fichier.');
        if ($actor === null && PHP_SAPI !== 'cli') throw new ValidationException('Acteur système réservé à la CLI.');
        $uploads = new UploadService(); $newFile = $uploads->store($file); $oldFile = null;
        try {
            if ($ownsTransaction) $this->pdo->beginTransaction();
        if ($values['category_id']) {
            $query = $this->pdo->prepare('SELECT id FROM categories WHERE id = ? AND archived_at IS NULL FOR UPDATE'); $query->execute([$values['category_id']]);
            if (!$query->fetchColumn()) throw new ValidationException('Catégorie indisponible.');
        }
        foreach ($titles as $title) {
            $query = $this->pdo->prepare('SELECT id FROM titles WHERE id = ? FOR UPDATE'); $query->execute([$title]);
            if (!$query->fetchColumn()) throw new ValidationException('Titre indisponible.');
        }
            $before = null;
            if ($id) {
                $lock = $this->pdo->prepare('SELECT id FROM achievements WHERE id = ? FOR UPDATE'); $lock->execute([$id]);
                if (!$lock->fetchColumn()) throw new ValidationException('Succès introuvable.');
                $before = (new AdminRepository($this->pdo))->achievement($id);
            }
            $oldFile = $before['evidence_path'] ?? null;
            $path = $newFile ?? (isset($data['remove_evidence']) ? null : $oldFile);
            $values['verified_by'] = $values['verification_status'] === 'verified' ? $actor : null;
            $values['verified_at'] = $values['verification_status'] === 'verified' ? gmdate('Y-m-d H:i:s') : null;
            if ($id) {
                $sql = 'UPDATE achievements SET ' . implode(', ', array_map(fn($key)=>"$key = ?", array_keys($values))) . ', updated_at = CURRENT_TIMESTAMP WHERE id = ?';
                $this->pdo->prepare($sql)->execute([...array_values($values), $id]);
            } else {
                $values['created_by'] = $actor;
                $sql = 'INSERT INTO achievements (' . implode(',', array_keys($values)) . ') VALUES (' . implode(',', array_fill(0,count($values),'?')) . ')';
                $this->pdo->prepare($sql)->execute(array_values($values)); $id = (int) $this->pdo->lastInsertId();
            }
            $this->pdo->prepare('INSERT INTO achievement_details (achievement_id, unlock_conditions, evidence_path) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE unlock_conditions = VALUES(unlock_conditions), evidence_path = VALUES(evidence_path), updated_at = CURRENT_TIMESTAMP')->execute([$id, $conditions ?: null, $path]);
            $this->pdo->prepare('DELETE FROM achievement_titles WHERE achievement_id = ?')->execute([$id]);
            foreach ($titles as $title) $this->pdo->prepare('INSERT INTO achievement_titles (achievement_id, title_id) VALUES (?, ?)')->execute([$id, $title]);
            $after = [...$values, 'unlock_conditions'=>$conditions, 'title_ids'=>$titles, 'evidence_path'=>$path];
            Audit::record($this->pdo, $actor, $before ? 'achievement.update' : 'achievement.create', 'achievement', $id, $before, $after);
            if (($before['publication_status'] ?? 'draft') !== $values['publication_status']) Audit::record($this->pdo, $actor, $values['publication_status'] === 'published' ? 'achievement.publish' : 'achievement.unpublish', 'achievement', $id, ['publication_status'=>$before['publication_status'] ?? 'draft'], ['publication_status'=>$values['publication_status']]);
            $rewardKeys = [...CatalogService::REWARDS, 'title_ids'=>'Titres', 'title_rewards_known'=>'Titres connus'];
            if ($before && (array_filter(array_keys(CatalogService::REWARDS), fn($field)=>($before[$field] === null ? null : (string) $before[$field]) !== $values[$field])
                || $before['title_ids'] !== $titles || (bool)$before['title_rewards_known'] !== (bool)$values['title_rewards_known'])) {
                Audit::record($this->pdo, $actor, 'achievement.rewards', 'achievement', $id, array_intersect_key($before, $rewardKeys), array_intersect_key($after, $rewardKeys));
            }
            if ($ownsTransaction) $this->pdo->commit();
            if ($oldFile !== $path) $uploads->remove($oldFile);
            return $id;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) $this->pdo->rollBack();
            $uploads->remove($newFile);
            if ($e instanceof \PDOException && ($e->errorInfo[1] ?? null) === 1062) throw new ValidationException('Ce slug est déjà utilisé.');
            throw $e;
        }
    }
    public function validate(array $data): array
    {
        $values = [
            'name'=>Input::text($data,'name',191,true), 'slug'=>Input::slug(Input::text($data,'slug',191,true)),
            'description'=>Input::text($data,'description',12000),
            'category_id'=>empty($data['category_id']) ? null : Input::id($data['category_id']),
            'rarity'=>Input::text($data,'rarity',50) ?: null,
            'secret_type'=>Input::choice($data,'secret_type',['normal','secret']),
            'verification_status'=>Input::choice($data,'verification_status',['unverified','pending','verified','rejected']),
            'publication_status'=>Input::choice($data,'publication_status',['draft','published']),
            'source_url'=>Input::text($data,'source_url',2048) ?: null,
            'title_rewards_known'=>isset($data['title_rewards_known']) ? 1 : 0,
        ];
        if ($values['rarity'] !== null && !in_array($values['rarity'], ['Commun','Rare','Épique','Légendaire'], true)) throw new ValidationException('Rareté invalide.');
        if ($values['source_url'] !== null && (!filter_var($values['source_url'], FILTER_VALIDATE_URL) || !in_array(strtolower(parse_url($values['source_url'], PHP_URL_SCHEME) ?: ''), ['http','https'], true)
            || parse_url($values['source_url'], PHP_URL_USER) !== null || parse_url($values['source_url'], PHP_URL_PASS) !== null)) throw new ValidationException('La source doit être une URL HTTP(S), sans identifiants.');
        foreach (CatalogService::REWARDS as $field => $label) $values[$field] = Input::amount($data, $field);
        $conditions = Input::text($data,'unlock_conditions',12000);
        $titles = $data['title_ids'] ?? [];
        if (!is_array($titles) || count($titles) > 100) throw new ValidationException('Titres invalides.');
        $titles = array_values(array_unique(array_map(Input::id(...), $titles)));
        return ['values'=>$values, 'conditions'=>$conditions, 'titles'=>$titles];
    }
    public function removeOrArchive(int $id, string $action, int $actor): void
    {
        $this->pdo->beginTransaction();
        try {
            $lock = $this->pdo->prepare('SELECT id FROM achievements WHERE id = ? FOR UPDATE'); $lock->execute([$id]);
            if (!$lock->fetchColumn()) throw new ValidationException('Succès introuvable.');
            $before = (new AdminRepository($this->pdo))->achievement($id);
            if ($action === 'delete') $this->pdo->prepare('DELETE FROM achievements WHERE id = ?')->execute([$id]);
            else $this->pdo->prepare('UPDATE achievements SET archived_at = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$action === 'archive' ? gmdate('Y-m-d H:i:s') : null, $id]);
            Audit::record($this->pdo, $actor, 'achievement.' . $action, 'achievement', $id, $before);
            $this->pdo->commit();
            if ($action === 'delete') (new UploadService())->remove($before['evidence_path']);
        } catch (\Throwable $e) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }
}
