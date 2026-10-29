<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\GuildCatalogRepository;
use PDO;
final class GuildCatalogService
{
    public const ICONS=['medal','spark','lock','shield','book','check','filter','arrow','search'];
    public function __construct(private PDO $pdo){}
    public function save(string $entity,array $d,array $actor):int{
        if(!PermissionService::member($actor)||!PermissionService::edit($actor))throw new \RuntimeException('Gestion du catalogue réservée aux éditeurs et administrateurs.',403);
        if(!isset(GuildCatalogRepository::TABLES[$entity]))throw new ValidationException('Entité inconnue.');
        $fields=match($entity){'milestone'=>['level','title','description'],'type'=>['slug','name','short_name','description','icon','unit','category','display_order'],'reward'=>['milestone_id','type_id','quantity','distribution','description','visibility','display_order']};
        if(array_diff(array_keys($d),[...$fields,'csrf','id','version','archived','q','status','sort']))throw new ValidationException('Champs non autorisés.');
        $id=($d['id']??'')===''?null:Input::id($d['id']);$v=[];
        foreach($fields as $field){
            $v[$field]=match($field){
                'level'=>GuildDivisionService::number($d[$field]??null),
                'milestone_id'=>Input::id($d[$field]??null),
                'type_id'=>($d[$field]??'')===''?null:Input::id($d[$field]),
                'quantity'=>Input::amount($d,$field),
                'distribution'=>Input::choice($d,$field,['individual','collective','unconfirmed']),
                'visibility'=>Input::choice($d,$field,['known','unknown']),
                'category'=>Input::choice($d,$field,['personal','collective']),
                'icon'=>Input::choice($d,$field,self::ICONS),
                'display_order'=>$this->order($d[$field]??'0'),
                'description'=>Input::text($d,$field,4000),
                'title'=>Input::text($d,$field,160,true),
                'name','slug'=>Input::text($d,$field,100,true),
                'short_name'=>Input::text($d,$field,30),
                'unit'=>Input::text($d,$field,60),
            };
        }
        if($entity==='type')Input::slug($v['slug']);
        if($entity==='reward'&&$v['visibility']==='known'&&($v['quantity']===null||($v['type_id']===null&&$v['description']==='')))throw new ValidationException('Récompense connue : quantity requise et type_id ou description à préciser.');
        $archived=Input::choice($d,'archived',['0','1']);
        $table=GuildCatalogRepository::TABLES[$entity];$this->pdo->beginTransaction();
        try{
            if($entity==='reward'){
                $q=$this->pdo->prepare('SELECT * FROM guild_milestones WHERE id=? FOR UPDATE');$q->execute([$v['milestone_id']]);$parent=$q->fetch();
                if(!$parent)throw new \RuntimeException('Palier introuvable.',404);
                if($parent['archived_at']!==null)throw new \RuntimeException('Restaurez le palier avant de modifier ses récompenses.',409);
            }
            $before=null;
            if($id!==null){
                $q=$this->pdo->prepare("SELECT * FROM $table WHERE id=? FOR UPDATE");$q->execute([$id]);$before=$q->fetch();
                if(!$before)throw new \RuntimeException('Entrée introuvable.',404);
                if((string)$before['version']!==(string)($d['version']??''))throw new \RuntimeException('Modification concurrente : rechargez la page avant de réessayer.',409);
                if($entity==='reward'&&(int)$before['milestone_id']!==$v['milestone_id'])throw new ValidationException('La récompense n’appartient pas à ce palier.');
                if($entity==='type'&&$before['slug']!==$v['slug'])throw new ValidationException('L’identifiant slug est permanent.');
            }
            if($entity==='reward'&&$v['type_id']!==null){
                $q=$this->pdo->prepare('SELECT archived_at FROM guild_reward_types WHERE id=? LOCK IN SHARE MODE');$q->execute([$v['type_id']]);$type=$q->fetch();
                if(!$type||($type['archived_at']!==null&&(int)($before['type_id']??0)!==$v['type_id']))throw new ValidationException('type_id indisponible ou archivé.');
            }
            $v['archived_at']=$archived==='1'?($before['archived_at']??gmdate('Y-m-d H:i:s')):null;
            if($id===null){$this->pdo->prepare("INSERT INTO $table (".implode(',',array_keys($v)).') VALUES ('.implode(',',array_fill(0,count($v),'?')).')')->execute(array_values($v));$id=(int)$this->pdo->lastInsertId();}
            else $this->pdo->prepare("UPDATE $table SET ".implode(',',array_map(static fn($k)=>"$k=?",array_keys($v))).',version=version+1 WHERE id=?')->execute([...array_values($v),$id]);
            $action=$before===null?'create':($before['archived_at']!==$v['archived_at']?($archived==='1'?'archive':'restore'):'update');
            Audit::record($this->pdo,(int)$actor['id'],'guild.catalog.'.$action,'guild_'.$entity,$id,$before,$v);
            $this->pdo->commit();return $id;
        }catch(\Throwable $e){$this->pdo->rollBack();if($e instanceof \PDOException&&$e->getCode()==='23000')throw new ValidationException('Conflit : level actif ou slug déjà utilisé, ou référence invalide.');throw $e;}
    }
    public function deleteMilestone(array $data,array $actor):void {
        if(!PermissionService::member($actor)||!PermissionService::admin($actor))throw new \RuntimeException('Suppression réservée aux administrateurs.',403);
        if(($data['confirm']??'')!=='1')throw new ValidationException('Confirmez explicitement la suppression définitive.');
        $id=Input::id($data['id']??null);$version=Input::id($data['version']??null);
        $this->pdo->beginTransaction();
        try {
            $q=$this->pdo->prepare('SELECT * FROM guild_milestones WHERE id=? FOR UPDATE');$q->execute([$id]);$before=$q->fetch();
            if(!$before)throw new \RuntimeException('Palier introuvable.',404);
            if((int)$before['version']!==$version)throw new \RuntimeException('Modification concurrente : rechargez le palier avant de le supprimer.',409);
            $q=$this->pdo->prepare('SELECT id FROM guild_milestone_rewards WHERE milestone_id=? FOR UPDATE');$q->execute([$id]);$rewardIds=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
            $this->pdo->prepare('DELETE FROM guild_milestone_rewards WHERE milestone_id=?')->execute([$id]);
            $this->pdo->prepare('DELETE FROM guild_milestones WHERE id=?')->execute([$id]);
            Audit::record($this->pdo,(int)$actor['id'],'guild.catalog.delete','guild_milestone',$id,['title'=>$before['title'],'level'=>$before['level'],'deleted_rewards'=>count($rewardIds),'deleted_reward_ids'=>$rewardIds]);
            $this->pdo->commit();
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();if($e instanceof \PDOException && $e->getCode()==='23000')throw new \RuntimeException('Suppression impossible : des données dépendent encore de ce palier. Aucune suppression effectuée.',409);throw $e;}
    }
    private function order(mixed $v):int{if(!is_scalar($v)||!preg_match('/^[0-9]{1,9}$/D',(string)$v))throw new ValidationException('display_order doit être un entier entre 0 et 999999999.');return (int)$v;}
}
