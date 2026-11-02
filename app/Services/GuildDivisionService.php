<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\GuildDivisionRepository;
use PDO;
final class GuildDivisionService
{
    public function __construct(private PDO $pdo) {}
    public static function number(mixed $value): int {
        if (!is_scalar($value)||!preg_match('/^[1-9][0-9]{0,9}$/D',(string)$value)||(float)$value>4294967295) throw new ValidationException('Le niveau doit être un entier entre 1 et 4 294 967 295.');
        return (int)$value;
    }
    public static function admin(array $actor): bool {return PermissionService::member($actor)&&PermissionService::admin($actor);}
    public function canManage(array $actor,int $divisionId): bool {
        if(!PermissionService::member($actor))return false;
        if(self::admin($actor))return true;
        if($actor['role']!=='editor'||$actor['auth_provider']!=='discord')return false;
        $q=$this->pdo->prepare('SELECT 1 FROM guild_division_masters WHERE division_id=? AND user_id=?');$q->execute([$divisionId,$actor['id']]);return (bool)$q->fetchColumn();
    }
    public function save(array $data,array $actor,bool $levelOnly=false): int {
        if(!PermissionService::member($actor)||!PermissionService::edit($actor))throw new \RuntimeException('Accès refusé.',403);
        if(!$levelOnly&&!self::admin($actor))throw new \RuntimeException('Gestion réservée aux administrateurs.',403);
        $allowed=$levelOnly?['csrf','id','version','level']:['csrf','id','version','name','short_name','description','color','level','archived','master_ids'];
        if(array_diff(array_keys($data),$allowed))throw new ValidationException('Champs non autorisés.');
        $id=($data['id']??'')===''?null:Input::id($data['id']);
        if($levelOnly&&$id===null)throw new ValidationException('Division requise.');
        $level=self::number($data['level']??null);
        if(!$levelOnly){
            $values=['name'=>Input::text($data,'name',100,true),'short_name'=>Input::text($data,'short_name',20,true),'description'=>Input::text($data,'description',4000),'color'=>strtoupper(Input::text($data,'color',7,true)),'level'=>$level];
            if(!preg_match('/^#[0-9A-F]{6}$/D',$values['color']))throw new ValidationException('Couleur hexadécimale invalide.');
            if(!in_array($data['archived']??'0',['0','1'],true))throw new ValidationException('Archivage invalide.');
            $updateMasters=array_key_exists('master_ids',$data);
            $masterIds=$data['master_ids']??[];
            if(!is_array($masterIds)||!array_is_list($masterIds))throw new ValidationException('Responsables invalides.');
            $masterIds=array_values(array_unique(array_map([Input::class,'id'],$masterIds)));sort($masterIds);
        }
        $this->pdo->beginTransaction();
        try {
            $before=null;$repository=new GuildDivisionRepository($this->pdo);
            if($id!==null){
                $q=$this->pdo->prepare('SELECT * FROM guild_divisions WHERE id=? FOR UPDATE');$q->execute([$id]);$before=$q->fetch();
                if(!$before)throw new \RuntimeException('Division introuvable.',404);
                // Lock division first for both level edits and assignment changes: revocations serialize.
                if(!$this->canManage($actor,$id))throw new \RuntimeException('Vous ne gérez pas cette division.',403);
                if((string)$before['version']!==(string)($data['version']??''))throw new \RuntimeException('Cette division a été modifiée entre-temps. Rechargez la page avant de réessayer.',409);
                if($levelOnly&&$before['archived_at']!==null)throw new \RuntimeException('Cette division est archivée.',409);
                $before['master_ids']=$repository->masterIds($id);
            }
            if(!$levelOnly){
                // An omitted assignment field preserves the locked associations, including inactive masters.
                if(!$updateMasters)$masterIds=$before['master_ids']??[];
                foreach($updateMasters?$masterIds:[] as $masterId){
                    $q=$this->pdo->prepare("SELECT id FROM users WHERE id=? AND is_active=1 AND auth_provider='discord' AND role IN ('editor','admin') FOR UPDATE");$q->execute([$masterId]);
                    if(!$q->fetchColumn())throw new ValidationException('Un responsable sélectionné n’est plus un éditeur ou administrateur Discord actif.');
                }
                $q=$this->pdo->prepare('SELECT id FROM guild_divisions WHERE name=? AND id<>?');$q->execute([$values['name'],$id??0]);
                if($q->fetchColumn())throw new ValidationException('Une division porte déjà ce nom.');
                $values['archived_at']=($data['archived']??'0')==='1'?($before['archived_at']??gmdate('Y-m-d H:i:s')):null;
                if($id===null){
                    $this->pdo->prepare('INSERT INTO guild_divisions(name,short_name,description,color,level,archived_at) VALUES(?,?,?,?,?,?)')->execute(array_values($values));$id=(int)$this->pdo->lastInsertId();
                }else{
                    $this->pdo->prepare('UPDATE guild_divisions SET name=?,short_name=?,description=?,color=?,level=?,archived_at=?,version=version+1 WHERE id=?')->execute([...array_values($values),$id]);
                }
                foreach(array_diff($before['master_ids']??[],$masterIds) as $removed)$this->pdo->prepare('DELETE FROM guild_division_masters WHERE division_id=? AND user_id=?')->execute([$id,$removed]);
                foreach(array_diff($masterIds,$before['master_ids']??[]) as $masterId)$this->pdo->prepare('INSERT INTO guild_division_masters(division_id,user_id,assigned_by) VALUES(?,?,?)')->execute([$id,$masterId,$actor['id']]);
                $values['master_ids']=$masterIds;
            }else{
                $values=['level'=>$level];
                $this->pdo->prepare('UPDATE guild_divisions SET level=?,version=version+1 WHERE id=?')->execute([$level,$id]);
            }
            if($before===null||(int)$before['level']!==$level)$this->pdo->prepare('INSERT INTO guild_division_levels(division_id,actor_id,old_level,new_level) VALUES(?,?,?,?)')->execute([$id,$actor['id'],$before['level']??null,$level]);
            Audit::record($this->pdo,(int)$actor['id'],$before===null?'guild.create':($levelOnly?'guild.level':'guild.update'),'guild_division',$id,$before,$values);
            $this->pdo->commit();return $id;
        }catch(\Throwable $e){
            $this->pdo->rollBack();
            if($e instanceof \PDOException && $e->getCode()==='23000')throw new ValidationException('Données en conflit : vérifiez le nom de la division et les responsables.');
            throw $e;
        }
    }
}
