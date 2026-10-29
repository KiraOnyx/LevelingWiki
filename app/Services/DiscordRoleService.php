<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
final class DiscordRoleService {
    public function __construct(private PDO $pdo) {}
    public function save(array $data,array $actor): void {
        $level=Input::choice($data,'level',['member','editor','admin']);
        if (!PermissionService::manageDiscordLevel($actor,$level)) throw new ValidationException('Vous pouvez uniquement gérer les associations des niveaux inférieurs au vôtre.',403);
        $action=Input::choice($data,'action',['save','delete']);
        $role=Input::text($data,'discord_role_id',20,true);
        if (!preg_match('/^[0-9]{17,20}$/D',$role)) throw new ValidationException('Discord Role ID invalide.');
        $label=$action==='save'?Input::text($data,'label',100,true):null;
        $expected=Input::id($data['policy_version']??null);
        $this->pdo->beginTransaction();
        try {
            $version=(int)$this->pdo->query('SELECT version FROM authorization_policy WHERE id=1 FOR UPDATE')->fetchColumn();
            if($expected!==$version)throw new ValidationException('Les associations ont changé. Rechargez la page avant de réessayer.',409);
            $before=null;
            if(isset($data['association_id'])) {
                $q=$this->pdo->prepare('SELECT * FROM discord_roles WHERE id=? FOR UPDATE');$q->execute([Input::id($data['association_id'])]);$before=$q->fetch();
                if(!$before)throw new ValidationException('Association introuvable. Rechargez la page.',409);
                if(!PermissionService::manageDiscordLevel($actor,$before['level']))throw new ValidationException('Cette association ne relève pas de vos permissions.',403);
                if($before['level']!==$level||$before['discord_role_id']!==$role)throw new ValidationException('Le niveau et le Discord Role ID de cette association sont immuables.',403);
            } else {
                $q=$this->pdo->prepare('SELECT * FROM discord_roles WHERE level=? AND discord_role_id=? FOR UPDATE');$q->execute([$level,$role]);$before=$q->fetch()?:null;
            }
            // Composite key is an association identity, not a request to move a role between levels.
            if($action==='delete') {
                if(!$before)throw new ValidationException('Association introuvable. Rechargez la page.',409);
                $this->pdo->prepare('DELETE FROM discord_roles WHERE id=?')->execute([$before['id']]);
            } elseif($before) $this->pdo->prepare('UPDATE discord_roles SET label=? WHERE id=?')->execute([$label,$before['id']]);
            else $this->pdo->prepare('INSERT INTO discord_roles(level,discord_role_id,label) VALUES(?,?,?)')->execute([$level,$role,$label]);
            $associationId=$before?(int)$before['id']:(int)$this->pdo->lastInsertId();
            $this->pdo->exec('UPDATE authorization_policy SET version=version+1 WHERE id=1');
            Audit::record($this->pdo,(int)$actor['id'],'discord.role_'.$action,'discord_role',$associationId,$before,['level'=>$level,'discord_role_id'=>$role,'label'=>$label]);
            $this->pdo->commit();
        } catch (\Throwable $e) { $this->pdo->rollBack();throw $e; }
    }
}
