<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
final class DiscordRoleService {
    public function __construct(private PDO $pdo) {}
    public function save(array $data,array $actor): void {
        if (!PermissionService::admin($actor)) throw new ValidationException('Permission insuffisante.');
        $level=Input::choice($data,'level',['member','editor','admin']);
        if ($level==='admin' && !PermissionService::owner($actor)) throw new ValidationException('Seul le propriétaire peut gérer les rôles ADMIN.');
        $action=Input::choice($data,'action',['save','delete']);
        $role=Input::text($data,'discord_role_id',20,true);
        if (!preg_match('/^[0-9]{17,20}$/D',$role)) throw new ValidationException('Discord Role ID invalide.');
        $label=$action==='save'?Input::text($data,'label',100,true):null;
        $this->pdo->beginTransaction();
        try {
            $this->pdo->query('SELECT version FROM authorization_policy WHERE id=1 FOR UPDATE');
            if ($action==='save') $this->pdo->prepare('INSERT INTO discord_roles(level,discord_role_id,label) VALUES(?,?,?) ON DUPLICATE KEY UPDATE label=VALUES(label)')->execute([$level,$role,$label]);
            else $this->pdo->prepare('DELETE FROM discord_roles WHERE level=? AND discord_role_id=?')->execute([$level,$role]);
            $this->pdo->exec('UPDATE authorization_policy SET version=version+1 WHERE id=1');
            Audit::record($this->pdo,(int)$actor['id'],'discord.role_'.$action,'discord_role',null,null,['level'=>$level,'discord_role_id'=>$role,'label'=>$label]);
            $this->pdo->commit();
        } catch (\Throwable $e) { $this->pdo->rollBack();throw $e; }
    }
}
