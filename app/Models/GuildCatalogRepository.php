<?php
declare(strict_types=1);
namespace App\Models;
use PDO;
final class GuildCatalogRepository
{
    public const TABLES=['milestone'=>'guild_milestones','type'=>'guild_reward_types','reward'=>'guild_milestone_rewards'];
    public function __construct(private PDO $pdo){}
    public function find(string $entity,int $id):?array{$q=$this->pdo->prepare('SELECT * FROM '.self::TABLES[$entity].' WHERE id=?');$q->execute([$id]);return $q->fetch()?:null;}
    public function types(string $search='',string $status='all',string $sort='asc'):array{
        $where=$status==='all'?'1=1':('archived_at IS '.($status==='archived'?'NOT ':'').'NULL');
        $literal='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';
        $q=$this->pdo->prepare("SELECT * FROM guild_reward_types WHERE ".$where." AND (name LIKE ? ESCAPE '!' OR slug LIKE ? ESCAPE '!' OR description LIKE ? ESCAPE '!') ORDER BY display_order ".($sort==='desc'?'DESC':'ASC').',name,id');$q->execute([$literal,$literal,$literal]);return $q->fetchAll();
    }
    public function milestones(string $search='',string $status='active',string $sort='asc'):array{
        $where=$status==='all'?'1=1':('archived_at IS '.($status==='archived'?'NOT ':'').'NULL');
        $literal=str_replace(['!','%','_'],['!!','!%','!_'],$search);
        $q=$this->pdo->prepare('SELECT * FROM guild_milestones WHERE '.$where.' AND (title LIKE ? ESCAPE \'!\' OR description LIKE ? ESCAPE \'!\' OR CAST(level AS CHAR)=?) ORDER BY level '.($sort==='desc'?'DESC':'ASC').',id');$q->execute(['%'.$literal.'%','%'.$literal.'%',$search]);return $q->fetchAll();
    }
    public function rewards(int $milestone):array{$q=$this->pdo->prepare('SELECT r.*,t.name AS type_name,t.icon,t.unit FROM guild_milestone_rewards r LEFT JOIN guild_reward_types t ON t.id=r.type_id WHERE r.milestone_id=? ORDER BY r.display_order,r.id');$q->execute([$milestone]);return $q->fetchAll();}
    // Only this projection crosses the member HTML/JSON boundary. Unknown payloads are discarded.
    public static function present(array $r):array{
        if($r['visibility']==='unknown')return ['id'=>(int)$r['id'],'label'=>'???','kind'=>'unknown','icon'=>'lock','description'=>'','distribution'=>''];
        $kind=['individual'=>'personal','collective'=>'collective','unconfirmed'=>'unconfirmed'][$r['distribution']];
        return ['id'=>(int)$r['id'],'label'=>$r['quantity'].' '.($r['type_name']??$r['description']),'kind'=>$kind,'icon'=>$r['icon']??'medal','description'=>$r['type_name']?$r['description']:'Classification administrative nécessaire.','distribution'=>$r['distribution']];
    }
    public function publicLevels():array{
        $result=[];
        foreach($this->milestones() as $m){
            $rewards=array_map([self::class,'present'],array_values(array_filter($this->rewards((int)$m['id']),static fn($r)=>$r['archived_at']===null)));
            $result[]=['level'=>(int)$m['level'],'label'=>$m['title'],'description'=>$m['description'],'kind'=>'catalog','icon'=>'medal','rewards'=>$rewards];
        }return $result;
    }
}
