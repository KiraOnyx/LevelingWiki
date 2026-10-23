<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';require __DIR__.'/discord-support.php';
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='test'||getenv('DB_DATABASE')!=='levelingwiki_test')exit(1);
function check5(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "OK : $label\n";}
$p=App\Services\Database::connect();seedDiscordRoles($p);
$id='100000000000000051';$roles=['200000000000000001'];$member=discordLogin($id,$roles);$other=discordLogin('100000000000000052',$roles);
foreach(['/','/succes','/mes-succes','/profil'] as $path){$r=$member->request($path);check5($r['status']===200,'page Valhalla '.$path);check5(substr_count($r['body'],'type="search"')===1,'recherche unique '.$path);check5(!str_contains($r['body'],'href="/admin"'),'administration masquée aux membres '.$path);}
$achievement=(int)$p->query("SELECT id FROM achievements WHERE publication_status='published' AND archived_at IS NULL LIMIT 1")->fetchColumn();
check5($achievement>0,'fixture publiée disponible');
$csrf=$member->token('/profil');$r=$member->request('/progression',['csrf'=>$csrf,'achievement_id'=>$achievement,'action'=>'obtained'],null,['Accept: application/json']);
check5($r['status']===200 && json_decode($r['body'],true)['progress']==='obtained','progression sans navigation');
$q=$p->prepare('SELECT p.progress FROM personal_achievements p JOIN discord_identities d ON p.user_id=d.user_id WHERE d.discord_user_id=? AND p.achievement_id=?');$q->execute([$id,$achievement]);check5($q->fetchColumn()==='obtained','état enregistré en base');
$r=$other->request('/progression',['csrf'=>$other->token('/profil'),'achievement_id'=>$achievement,'action'=>'obtained','user_id'=>1],null,['Accept: application/json']);check5($r['status']===422,'identité arbitraire refusée');
check5($member->request('/progression',['csrf'=>'bad','achievement_id'=>$achievement,'action'=>'obtained'],null,['Accept: application/json'])['status']===403,'CSRF progression JSON');
$member->request('/deconnexion',['csrf'=>$member->token('/profil')]);
$client=new HttpTestClient();check5(discordFlow($client,$id,[])['status']===403,'perte du rôle refuse la connexion');
$member=discordLogin($id,$roles);$q->execute([$id,$achievement]);check5($q->fetchColumn()==='obtained','récupération du rôle conserve la progression');
$q=$p->prepare('SELECT COUNT(*) FROM discord_identities WHERE discord_user_id=?');$q->execute([$id]);check5((int)$q->fetchColumn()===1,'reconnexion sans duplication');
check5(str_contains($member->request('/mes-succes')['body'],'Marquer comme obtenu'),'état restauré dans le formulaire');
check5($member->request('/admin')['status']===403,'administration protégée côté serveur');
