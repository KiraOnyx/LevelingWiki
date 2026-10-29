<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';require __DIR__.'/discord-support.php';
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='test'||getenv('DB_DATABASE')!=='levelingwiki_test')exit(1);
function sessionCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "OK : $label\n";}
function apiCalls():int{return json_decode(file_get_contents('http://discord:8082/test/count'),true)['calls'];}
$p=App\Services\Database::connect();seedDiscordRoles($p);$m='200000000000000001';$a='200000000000000003';$e='200000000000000002';$uid='100000000000001201';
$admin=discordLogin($uid,[$a]);$before=apiCalls();
foreach(['/','/succes','/profil','/tour-de-guilde','/admin','/admin/achievements','/admin/settings/discord','/admin/tour-de-guilde/paliers','/admin'] as $path)sessionCheck($admin->request($path)['status']===200,'session locale : '.$path);
sessionCheck(apiCalls()===$before,'zéro appel Discord pendant navigation et administration');
mockDiscord(['outage'=>true]);try{sessionCheck($admin->request('/admin/settings/discord')['status']===200,'panne Discord sans perte des droits frais');sessionCheck(apiCalls()===$before,'panne sans tentative API pendant validité');}finally{mockDiscord(['outage'=>false]);}
// Policy changes recalculate from cached role IDs, even while Discord is unreachable.
$creds=json_decode(file_get_contents('/tmp/levelingwiki-test-admin.json'),true);$owner=new HttpTestClient();$owner->login($creds['username'],$creds['password']);
$rid='200000000000001299';$owner->post('/admin/settings/discord',['action'=>'save','level'=>'editor','discord_role_id'=>$rid,'label'=>'Session isolée']);$editor=discordLogin('100000000000001202',[$rid]);$before=apiCalls();
mockDiscord(['outage'=>true]);try{$owner->post('/admin/settings/discord',['action'=>'delete','level'=>'editor','discord_role_id'=>$rid]);sessionCheck($editor->request('/admin')['status']===403,'retrait local révoque immédiatement le cache');sessionCheck(apiCalls()===$before,'invalidation de politique sans Discord');}finally{mockDiscord(['outage'=>false]);}
mockDiscord(['user'=>['id'=>$uid,'roles'=>[$m]]]);sessionCheck($admin->request('/admin')['status']===200,'rôle Discord retiré non interrogé avant échéance');expireDiscordCache($admin);$before=apiCalls();sessionCheck($admin->request('/admin')['status']===403&&$admin->request('/profil')['status']===200,'renouvellement quotidien applique ADMIN vers MEMBER');sessionCheck(apiCalls()===$before+1,'une seule vérification membre au renouvellement');
mockDiscord(['user'=>['id'=>$uid,'roles'=>[]]]);expireDiscordCache($admin);sessionCheck($admin->request('/profil')['status']===403,'perte du dernier rôle au renouvellement');
$admin=discordLogin($uid,[$a]);expireDiscordCache($admin);mockDiscord(['outage'=>true]);try{$r=$admin->request('/admin');sessionCheck($r['status']===403&&str_contains($r['body'],'momentanément injoignable'),'cache expiré et panne : accès bloqué avec explication');sessionCheck($admin->request('/profil')['status']===403,'aucune prolongation des droits expirés');}finally{mockDiscord(['outage'=>false]);}
sessionCheck($admin->request('/admin')['status']===200,'renouvellement possible au retour de Discord');
sessionCheck($admin->request('/admin/settings/discord',['csrf'=>'invalid','action'=>'save','level'=>'member','discord_role_id'=>$rid,'label'=>'refus'])['status']===403,'CSRF maintenu avec permissions locales');
// Expired access token is refreshed only when roles expire.
$code='session_id(trim(stream_get_contents(STDIN)));session_start();$_SESSION["discord"]["tokens"]["expires"]=0;$_SESSION["discord"]["checked"]=time()-86400;session_write_close();';$proc=proc_open([PHP_BINARY,'-r',$code],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],$admin->cookies['lw_session']);fclose($pipes[0]);fclose($pipes[1]);fclose($pipes[2]);proc_close($proc);$before=apiCalls();sessionCheck($admin->request('/admin')['status']===200&&apiCalls()===$before+2,'refresh sécurisé puis rôles sans relancer OAuth');
$csrf=$admin->token('/profil');sessionCheck($admin->request('/deconnexion',['csrf'=>$csrf])['status']===303&&$admin->request('/profil')['status']===303,'déconnexion révoque la session locale');
$config=require dirname(__DIR__).'/config/discord.php';sessionCheck($config['role_ttl']===86400,'permissions plafonnées à 24 heures');
// Production limits cannot be extended through historical environment settings.
$environment=getenv('APP_ENV');putenv('APP_ENV=production');$security=require dirname(__DIR__).'/config/security.php';putenv('APP_ENV='.$environment);
sessionCheck($security['absolute_seconds']===86400&&$security['idle_seconds']===86400,'session production : plafond absolu et inactivité de 24 heures');
$admin=discordLogin($uid,[$a]);expireDiscordCache($admin);mockDiscord(['user'=>['id'=>$uid,'invalid_token'=>true]]);
try{$r=$admin->request('/admin');sessionCheck($r['status']===303&&str_contains($r['location']??'','expired=1'),'jeton révoqué : nouvelle authentification demandée');}finally{mockDiscord(['user'=>['id'=>$uid,'invalid_token'=>false]]);}
