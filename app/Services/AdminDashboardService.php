<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
final class AdminDashboardService
{
    public function __construct(private PDO $pdo){}
    // Eligibility is the last persisted authorization state, not an online Discord census.
    private const CTE="WITH members AS (SELECT u.id,d.display_name,d.discord_user_id,d.avatar_hash,d.created_at FROM users u JOIN discord_identities d ON d.user_id=u.id WHERE u.auth_provider='discord' AND u.is_active=1 AND u.must_change_password=0 AND u.role IN ('member','editor','admin')), available AS (SELECT id,name,slug,category_id FROM achievements WHERE publication_status='published' AND archived_at IS NULL), wins AS (SELECT p.user_id,p.achievement_id,p.obtained_at FROM personal_achievements p JOIN members m ON m.id=p.user_id JOIN available a ON a.id=p.achievement_id WHERE p.progress='obtained') ";
    private function rows(string $sql):array{return $this->pdo->query(self::CTE.$sql)->fetchAll();}
    public static function percent(int $count,int $total):float{return $total>0?round(100*$count/$total,1):0.0;}
    public function overview(array $actor):array{
        if(!PermissionService::member($actor)||!PermissionService::edit($actor))throw new \RuntimeException('Accès refusé.',403);
        $owns=!$this->pdo->inTransaction();if($owns)$this->pdo->beginTransaction();
        try{
            $summary=$this->rows("SELECT (SELECT COUNT(*) FROM members) members,(SELECT COUNT(*) FROM available) achievements,(SELECT COUNT(*) FROM wins) declarations,(SELECT COUNT(*) FROM guild_divisions WHERE archived_at IS NULL) divisions,(SELECT COALESCE(MAX(level),0) FROM guild_divisions WHERE archived_at IS NULL) max_level")[0];
            $summary=array_map('intval',$summary);$summary['average']=self::percent($summary['declarations'],$summary['members']*$summary['achievements']);
            $counts="SELECT a.id,a.name,a.slug,COUNT(w.user_id) total FROM available a LEFT JOIN wins w ON w.achievement_id=a.id GROUP BY a.id,a.name,a.slug";
            $most=$this->rows($counts.' ORDER BY total DESC,a.name,a.id LIMIT 10');$least=$this->rows($counts.' ORDER BY total ASC,a.name,a.id LIMIT 10');
            $categories=$this->rows("SELECT c.id,c.name,COUNT(DISTINCT a.id) achievements,COUNT(w.user_id) declarations FROM categories c LEFT JOIN available a ON a.category_id=c.id LEFT JOIN wins w ON w.achievement_id=a.id WHERE c.archived_at IS NULL GROUP BY c.id,c.name ORDER BY c.name,c.id");
            $unclassified=$this->rows("SELECT COUNT(DISTINCT a.id) achievements,COUNT(w.user_id) declarations FROM available a LEFT JOIN wins w ON w.achievement_id=a.id WHERE a.category_id IS NULL")[0];
            if((int)$unclassified['achievements']>0)$categories[]=['id'=>null,'name'=>'Non classés',...$unclassified];
            foreach($categories as &$category)$category['percent']=self::percent((int)$category['declarations'],$summary['members']*(int)$category['achievements']);unset($category);
            $attention=$this->pdo->query("SELECT
                (SELECT COUNT(*) FROM achievements WHERE archived_at IS NULL AND publication_status='draft') drafts,
                (SELECT COUNT(*) FROM achievements WHERE archived_at IS NULL AND (reward_xp IS NULL OR reward_gold IS NULL OR reward_gems IS NULL OR reward_keys IS NULL OR title_rewards_known=0)) unknown_rewards,
                (SELECT COUNT(*) FROM guild_milestones m WHERE m.archived_at IS NULL AND (NOT EXISTS(SELECT 1 FROM guild_milestone_rewards r WHERE r.milestone_id=m.id AND r.archived_at IS NULL) OR EXISTS(SELECT 1 FROM guild_milestone_rewards r WHERE r.milestone_id=m.id AND r.archived_at IS NULL AND (r.visibility='unknown' OR r.type_id IS NULL OR r.quantity IS NULL OR r.distribution='unconfirmed')))) incomplete_milestones,
                (SELECT COUNT(*) FROM guild_divisions g WHERE g.archived_at IS NULL AND NOT EXISTS(SELECT 1 FROM guild_division_masters gm JOIN users u ON u.id=gm.user_id JOIN discord_identities d ON d.user_id=u.id WHERE gm.division_id=g.id AND u.is_active=1 AND u.auth_provider='discord' AND u.role IN ('editor','admin') AND u.must_change_password=0)) unassigned_divisions")->fetch();
            $ranking=[];$activity=[];$identifiable=PermissionService::admin($actor);
            if($identifiable){
                $ranking=$this->rows("SELECT m.id,m.display_name,m.discord_user_id,m.avatar_hash,COUNT(w.achievement_id) obtained,RANK() OVER(ORDER BY COUNT(w.achievement_id) DESC) position FROM members m LEFT JOIN wins w ON w.user_id=m.id GROUP BY m.id,m.display_name,m.discord_user_id,m.avatar_hash ORDER BY obtained DESC,m.display_name,m.id LIMIT 20");
                foreach($ranking as &$member){$member['percent']=self::percent((int)$member['obtained'],$summary['achievements']);$member['avatar']=preg_match('/^(?:a_)?[a-f0-9]{32}$/D',$member['avatar_hash']??'')&&preg_match('/^[0-9]{17,20}$/D',$member['discord_user_id'])?'https://cdn.discordapp.com/avatars/'.$member['discord_user_id'].'/'.$member['avatar_hash'].'.png?size=64':null;unset($member['id'],$member['discord_user_id'],$member['avatar_hash']);}unset($member);
                $activity=$this->rows("SELECT l.created_at date,CONCAT(m.display_name,IF(l.action='personal.obtained',' a déclaré : ',' a annulé : '),a.name) label FROM audit_logs l JOIN members m ON m.id=l.actor_id JOIN available a ON a.id=l.entity_id WHERE l.entity_type='achievement' AND l.action IN ('personal.obtained','personal.not_started') ORDER BY l.created_at DESC,l.id DESC LIMIT 12");
                foreach($this->rows("SELECT created_at date,CONCAT('Première connexion · ',display_name) label FROM members ORDER BY created_at DESC,id DESC LIMIT 12") as $event)$activity[]=$event;
            }
            foreach($this->pdo->query("SELECT h.created_at date,CONCAT(g.name,' · niveau ',h.new_level) label FROM guild_division_levels h JOIN guild_divisions g ON g.id=h.division_id WHERE g.archived_at IS NULL ORDER BY h.created_at DESC,h.id DESC LIMIT 12")->fetchAll() as $event)$activity[]=$event;
            // Never expose account, permission or credential audit payloads on this dashboard.
            $editorial=$this->pdo->query("SELECT created_at date,CONCAT('Mise à jour administrative · ',entity_type,' · ',action) label FROM audit_logs WHERE entity_type IN ('achievement','category','title','guild_milestone','guild_reward','guild_type') AND action NOT LIKE 'personal.%' ORDER BY created_at DESC,id DESC LIMIT 12")->fetchAll();
            $activity=array_merge($activity,$editorial);usort($activity,static fn($a,$b)=>strcmp($b['date'],$a['date'])?:strcmp($a['label'],$b['label']));$activity=array_slice($activity,0,20);
            if($owns)$this->pdo->commit();return compact('summary','most','least','categories','ranking','activity','attention','identifiable');
        }catch(\Throwable $e){if($owns&&$this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
}
