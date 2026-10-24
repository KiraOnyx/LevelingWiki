<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';require __DIR__.'/discord-support.php';
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='test'||getenv('DB_DATABASE')!=='levelingwiki_test')exit(1);
function quickCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "OK : $label\n";}
$p=App\Services\Database::connect();seedDiscordRoles($p);
$editor=discordLogin('100000000000000081',['200000000000000001','200000000000000002']);
$member=discordLogin('100000000000000082',['200000000000000001']);
$admin=discordLogin('100000000000000083',['200000000000000001','200000000000000003']);
$repo=new App\Models\AdminRepository($p);$row=$p->query('SELECT * FROM achievements WHERE archived_at IS NULL LIMIT 1')->fetch();
$id=(int)$row['id'];$before=$repo->achievement($id);$csrf=$editor->token();
$data=['csrf'=>$csrf,'action'=>'quick','id'=>$id,'field'=>'publication_status','previous'=>$row['publication_status'],'value'=>$row['publication_status']==='draft'?'published':'draft'];
$send=fn($client,$payload)=>$client->request('/admin/achievements/save',$payload,null,['Accept: application/json']);
quickCheck($send(new HttpTestClient(),$data)['status']===303,'édition rapide anonyme refusée');
quickCheck($send($member,[...$data,'csrf'=>$member->token('/profil')])['status']===403,'édition rapide MEMBER refusée');
quickCheck($send($editor,[...$data,'csrf'=>'invalid'])['status']===403,'CSRF édition rapide');
quickCheck($send($editor,[...$data,'value'=>'invalid'])['status']===422,'validation statut');
quickCheck($send($editor,[...$data,'field'=>'xp_reward'])['status']===422,'champ arbitraire refusé');
quickCheck($send($editor,[...$data,'gold_reward'=>'999'])['status']===422,'champs supplémentaires refusés');
$r=$send($editor,$data);$json=json_decode($r['body'],true);
quickCheck($r['status']===200&&$json['ok']&&count($json['stats'])===7,'édition EDITOR et sept compteurs réels');
quickCheck($json['stats']===$repo->statistics(),'compteurs actualisés');
quickCheck($send($editor,$data)['status']===422,'modification concurrente détectée');
$after=$repo->achievement($id);foreach($before as $key=>$value)if(!in_array($key,['publication_status','updated_at'],true))quickCheck($value===$after[$key],'préservation '.$key);
$verify=['csrf'=>$admin->token(),'action'=>'quick','id'=>$id,'field'=>'verification_status','previous'=>$after['verification_status'],'value'=>'verified'];
if ($verify['previous']==='verified') { quickCheck($send($admin,[...$verify,'value'=>'pending'])['status']===200,'préparation vérification'); $verify['previous']='pending'; }
quickCheck($send($admin,$verify)['status']===200,'vérification rapide ADMIN');
$verified=$repo->achievement($id);quickCheck($verified['verification_status']==='verified'&&$verified['verified_by']!==null&&$verified['verified_at']!==null,'auteur et date de vérification');
quickCheck($send($admin,[...$verify,'previous'=>'verified','value'=>'pending'])['status']===200,'statut pending existant');
quickCheck($repo->achievement($id)['verified_by']===null,'retrait de la vérification');
$q=$p->prepare("SELECT COUNT(*) FROM audit_logs WHERE entity_id=? AND action IN ('achievement.update','achievement.publish','achievement.unpublish')");$q->execute([$id]);quickCheck((int)$q->fetchColumn()>=3,'journal existant alimenté');
quickCheck(str_contains($admin->request('/admin/achievements')['body'],'data-quick-status'),'contrôles accessibles rendus');
quickCheck(str_contains($admin->request('/admin')['body'],'Catégories actives'),'compteurs du tableau de bord');
// Restore fixture using the same narrow service, never touch the development database.
$service=new App\Services\AchievementService($p);$actor=(int)$verified['verified_by'];
$service->quickUpdate(['id'=>$id,'field'=>'publication_status','previous'=>$after['publication_status'],'value'=>$before['publication_status']],$actor);
$service->quickUpdate(['id'=>$id,'field'=>'verification_status','previous'=>'pending','value'=>$before['verification_status']],$actor);
$current=$repo->achievement($id);
$native=['csrf'=>$csrf,'action'=>'quick','id'=>$id,'field'=>'publication_status','previous'=>$current['publication_status'],'value'=>$current['publication_status']];
quickCheck($editor->request('/admin/achievements/save',$native)['status']===303,'enregistrement sans JavaScript');
$invalid=$editor->request('/admin/achievements/save',[...$native,'value'=>'invalid']);
quickCheck($invalid['status']===422&&str_contains($invalid['body'],'Gérer les succès'),'erreur sans JavaScript conserve la liste');
quickCheck($send($editor,[...$native,'id'=>999999])['status']===422,'succès inexistant refusé');
$service->removeOrArchive($id,'archive',$actor);
quickCheck($send($editor,$native)['status']===422,'archive non modifiable rapidement');
$service->removeOrArchive($id,'restore',$actor);
echo "OK : édition rapide terminée dans la base isolée\n";
