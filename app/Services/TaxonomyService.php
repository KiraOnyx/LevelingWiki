<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
final class TaxonomyService
{
    public function __construct(private PDO $pdo) {}
    public function save(string $kind, array $data, ?int $actor): void
    {
        if (!in_array($kind, ['categories','titles'], true)) throw new ValidationException('Type invalide.');
        $id = empty($data['id']) ? null : Input::id($data['id']);
        $action = Input::choice($data,'action',['save','archive','restore','delete']);
        if (!$id && $action !== 'save') throw new ValidationException('Identifiant manquant.');
        if ($kind === 'titles' && in_array($action,['archive','restore'],true)) throw new ValidationException('Action invalide.');
        $values = [];
        if ($action === 'save') {
            $values = ['name'=>Input::text($data,'name',$kind === 'categories' ? 100 : 191,true), 'description'=>Input::text($data,'description',12000)];
            if ($kind === 'categories') {
                $values['color'] = Input::text($data,'color',7,true);
                if (!preg_match('/^#[0-9A-Fa-f]{6}$/D',$values['color'])) throw new ValidationException('Couleur invalide.');
            }
            if (!$id) $values['slug'] = Input::slug(Input::text($data,'slug',191,true));
        }
        $ownsTransaction = !$this->pdo->inTransaction();
        if (!$ownsTransaction && ($id !== null || $action !== 'save')) throw new ValidationException('Transaction externe réservée aux créations.');
        if ($actor === null && PHP_SAPI !== 'cli') throw new ValidationException('Acteur système réservé à la CLI.');
        if ($ownsTransaction) $this->pdo->beginTransaction();
        try {
            $before = null;
            if ($id) {
                $query = $this->pdo->prepare("SELECT * FROM $kind WHERE id = ? FOR UPDATE"); $query->execute([$id]); $before = $query->fetch();
                if (!$before) throw new ValidationException('Élément introuvable.');
            }
            if (in_array($action,['delete','archive'],true)) {
                $sql = $kind === 'categories' ? 'SELECT id FROM achievements WHERE category_id = ? LIMIT 1 FOR UPDATE' : 'SELECT achievement_id FROM achievement_titles WHERE title_id = ? LIMIT 1 FOR UPDATE';
                $query = $this->pdo->prepare($sql); $query->execute([$id]);
                if ($query->fetchColumn()) throw new ValidationException('Cet élément est encore utilisé par un succès.');
            }
            if ($action === 'delete') $this->pdo->prepare("DELETE FROM $kind WHERE id = ?")->execute([$id]);
            elseif (in_array($action,['archive','restore'],true)) $this->pdo->prepare('UPDATE categories SET archived_at = ? WHERE id = ?')->execute([$action === 'archive' ? gmdate('Y-m-d H:i:s') : null, $id]);
            elseif ($id) $this->pdo->prepare("UPDATE $kind SET " . implode(', ',array_map(fn($k)=>"$k = ?",array_keys($values))) . ' WHERE id = ?')->execute([...array_values($values),$id]);
            else {
                $this->pdo->prepare("INSERT INTO $kind (".implode(',',array_keys($values)).') VALUES ('.implode(',',array_fill(0,count($values),'?')).')')->execute(array_values($values));
                $id = (int) $this->pdo->lastInsertId();
            }
            Audit::record($this->pdo,$actor,$kind.'.'.$action,$kind,$id,$before,$values ?: null);
            if ($ownsTransaction) $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) $this->pdo->rollBack();
            if ($e instanceof \PDOException && ($e->errorInfo[1] ?? null) === 1062) throw new ValidationException('Ce nom ou ce slug est déjà utilisé.');
            throw $e;
        }
    }
}
