<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';require __DIR__.'/discord-support.php';
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='test'||getenv('DB_DATABASE')!=='levelingwiki_test')exit(1);
function dynamicCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "OK : $label\n";}
$p=App\Services\Database::connect();seedDiscordRoles($p);
$member=discordLogin('100000000000000091',['200000000000000001']);
$other=discordLogin('100000000000000092',['200000000000000001']);
$id=(int)$p->query("SELECT id FROM achievements WHERE publication_status='published' AND archived_at IS NULL LIMIT 1")->fetchColumn();
$q=$p->prepare('SELECT user_id FROM discord_identities WHERE discord_user_id=?');$q->execute(['100000000000000091']);$userId=(int)$q->fetchColumn();
$csrf=$member->token('/profil');$send=fn($data)=>$member->request('/progression',['csrf'=>$csrf,'achievement_id'=>$id,...$data],null,['Accept: application/json']);
$r=$send(['action'=>'obtained']);$result=json_decode($r['body'],true);$overview=(new App\Services\PersonalService($p))->overview($userId);
dynamicCheck($r['status']===200&&$result['summary']==json_decode(json_encode($overview['summary']),true),'totaux dynamiques calculés par le serveur');
dynamicCheck($result['rewards']===$overview['rewardsEstimate'],'récompenses exactes, valeurs inconnues préservées');
dynamicCheck($result['categories']===$overview['categoriesProgress'],'répartition dynamique par catégorie');
dynamicCheck(count($result['obtained'])===1&&$result['obtained_at']!==null,'état et date confirmés');
$again=json_decode($send(['action'=>'obtained'])['body'],true);dynamicCheck($again['summary']['obtained']===1&&$again['obtained_at']===$result['obtained_at'],'double déclaration idempotente');
dynamicCheck($send(['action'=>'not_started'])['status']===422,'annulation exige confirmation');
$undo=json_decode($send(['action'=>'not_started','confirm'=>'1'])['body'],true);dynamicCheck($undo['summary']['obtained']===0&&$undo['obtained_at']===null,'annulation recalcule les totaux');
dynamicCheck(str_contains($other->request('/succes')['body'],'data-summary="obtained">0'),'isolation des comptes');
foreach(['/','/profil'] as $path)dynamicCheck(!str_contains($member->request($path)['body'],'type="search"'),'absence de recherche hors collection '.$path);
$profile=$member->request('/profil')['body'];dynamicCheck(!str_contains($profile,'data-local-import'),'import local retiré de l’interface');
$catalog=$member->request('/succes')['body'];dynamicCheck(str_contains($catalog,'data-inline-progress')&&!str_contains($catalog,'>Consulter'),'actions directes du Codex');
$collection=$member->request('/succes')['body'];dynamicCheck(!str_contains($collection,'estimate-panel')&&!str_contains($collection,'category-progress-grid'),'collection sans duplication du tableau de bord');
