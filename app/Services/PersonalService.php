<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
final class PersonalService {
    public function __construct(private PDO $pdo) {}
    public function act(int $userId,int $achievementId,string $action): void {
        if (!in_array($action,['not_started','obtained'],true)) throw new ValidationException('État personnel invalide.');
        $owns=!$this->pdo->inTransaction();if($owns)$this->pdo->beginTransaction();
        try {
            $q=$this->pdo->prepare("SELECT id FROM achievements WHERE id=? AND publication_status='published' AND archived_at IS NULL FOR UPDATE");$q->execute([$achievementId]);
            if(!$q->fetchColumn())throw new ValidationException('Ce succès n’est plus disponible.');
            $q=$this->pdo->prepare('SELECT progress,obtained_at FROM personal_achievements WHERE user_id=? AND achievement_id=? FOR UPDATE');$q->execute([$userId,$achievementId]);$before=$q->fetch()?:null;
            $date=$action==='obtained'?($before['obtained_at']??gmdate('Y-m-d H:i:s')):null;
            $this->pdo->prepare('INSERT INTO personal_achievements(user_id,achievement_id,progress,obtained_at) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE progress=VALUES(progress),obtained_at=VALUES(obtained_at)')->execute([$userId,$achievementId,$action,$date]);
            if(($before['progress']??'not_started')!==$action)Audit::record($this->pdo,$userId,'personal.'.$action,'achievement',$achievementId,$before,['progress'=>$action]);
            if($owns)$this->pdo->commit();
        }catch(\Throwable $e){if($owns&&$this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    public function importLocal(int $userId,array $slugs): void {
        if (count($slugs)>1000) throw new ValidationException('Trop de succès.');
        $this->pdo->beginTransaction();
        try {
            foreach (array_unique($slugs,SORT_REGULAR) as $slug) {
                if (!is_string($slug)) throw new ValidationException('Liste invalide.');
                Input::slug($slug);
                $q=$this->pdo->prepare("SELECT id FROM achievements WHERE slug=? AND publication_status='published' AND archived_at IS NULL");$q->execute([$slug]);$id=$q->fetchColumn();
                if ($id) $this->act($userId,(int)$id,'obtained');
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {$this->pdo->rollBack();throw $e;}
    }
    public function rows(int $userId): array {
        $q=$this->pdo->prepare('SELECT p.*,a.slug,a.name,a.description,a.publication_status,a.archived_at,c.name AS category FROM personal_achievements p
            LEFT JOIN achievements a ON a.id=p.achievement_id LEFT JOIN categories c ON c.id=a.category_id WHERE p.user_id=? ORDER BY p.accepted_at DESC,p.id DESC');
        $q->execute([$userId]);$rows=$q->fetchAll();
        foreach ($rows as &$row) {
            $row['available']=$row['publication_status']==='published' && $row['archived_at']===null;
            if ($row['available'] && is_string($row['description'])) {
                preg_match('/^.{0,180}/us',$row['description'],$excerpt);
                if (strlen($excerpt[0])<strlen($row['description'])) $row['description']=$excerpt[0].'…';
            }
            if (!$row['available']) {$row['name']='Succès indisponible';$row['description']='';$row['slug']=null;$row['category']=null;}
        }
        return $rows;
    }
    public function overview(int $userId): array {
        $repository=new \App\Models\AchievementRepository($this->pdo);
        $items=$repository->all();$rows=$this->rows($userId);$byId=array_column($rows,null,'achievement_id');
        $categories=[];foreach($repository->categories() as $category)$categories[(string)$category['id']]=['name'=>$category['name'],'color'=>$category['color'],'total'=>0,'obtained'=>0];
        $done=[];
        foreach($items as $item){
            $key=(string)($item['category_id']??'');
            $categories[$key]??=['name'=>'Non classés','color'=>'#A8B0B8','total'=>0,'obtained'=>0];
            $categories[$key]['total']++;
            if(($byId[$item['id']]['progress']??'not_started')==='obtained'){
                $categories[$key]['obtained']++;$done[]=$item+['obtained_at'=>$byId[$item['id']]['obtained_at']];
            }
        }
        usort($done,fn($a,$b)=>strcmp($b['obtained_at']??'',$a['obtained_at']??'') ?: ((int)$b['id']<=>(int)$a['id']));
        $rewards=[];
        foreach(['xp','gold','gems','keys'] as $key){
            // DECIMAL SUM preserves unsigned BIGINT precision; never coalesce individual NULLs into zero.
            $q=$this->pdo->prepare("SELECT COALESCE(SUM(a.reward_$key),0) AS known,COALESCE(SUM(a.reward_$key IS NULL),0) AS unknown FROM personal_achievements p JOIN achievements a ON a.id=p.achievement_id WHERE p.user_id=? AND p.progress='obtained' AND a.publication_status='published' AND a.archived_at IS NULL");
            $q->execute([$userId]);$rewards[$key]=$q->fetch();
        }
        $titles=[];$unknownTitles=0;foreach($done as $item){$titles=array_merge($titles,$item['titles']);$unknownTitles+=(int)!$item['title_rewards_known'];}
        $total=count($items);$obtained=count($done);
        return ['items'=>$items,'rows'=>$rows,'personalById'=>$byId,'available'=>array_column($items,null,'id'),'categoriesProgress'=>$categories,'recent'=>array_slice($done,0,5),'rewardsEstimate'=>$rewards,'earnedTitles'=>array_values(array_unique($titles)),'unknownTitles'=>$unknownTitles,'summary'=>['total'=>$total,'obtained'=>$obtained,'remaining'=>$total-$obtained,'percent'=>$total?round($obtained/$total*100,1):0]];
    }
}
