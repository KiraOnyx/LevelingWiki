<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';require __DIR__.'/discord-support.php';
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='test'||getenv('DB_DATABASE')!=='levelingwiki_test')exit(1);
use App\Services\{Database,OwnerAccess,PersonalService};
function vcheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "OK : $label\n";}
$p=Database::connect();$path=OwnerAccess::path();$anonymous=new HttpTestClient();
foreach(['/connexion','/admin/login','/owner','/super-admin','/login-admin','/mes-taches'] as $route){$r=$anonymous->request($route);vcheck(!str_contains($r['body'].$r['location'],$path),'chemin propriétaire absent des réponses publiques');if($route!=='/connexion')vcheck($r['status']===404,'ancienne route désactivée : '.$route);}
$r=$anonymous->request($path);vcheck($r['status']===200&&str_contains(implode(' ',$r['headers']),'noindex, nofollow, noarchive'),'formulaire privé non indexable');vcheck(!str_contains($r['body'],$path)&&!str_contains($r['body'],'Se connecter avec Discord'),'formulaire privé sans chemin ni connexion Discord');
seedDiscordRoles($p);$member=discordLogin('100000000000000061',['200000000000000001']);$other=discordLogin('100000000000000062',['200000000000000001']);
vcheck($member->request($path)['status']===404,'session Discord exclue de la connexion propriétaire');
foreach(['/','/succes','/mes-succes','/profil'] as $route){$r=$member->request($route);vcheck($r['status']===200&&!str_contains($r['body'],'Mes tâches')&&!str_contains($r['body'],$path),'navigation binaire et confidentielle : '.$route);}
$emptyCollection=$member->request('/mes-succes?progress=obtained')['body'];
vcheck(str_contains($emptyCollection,'data-count>0</span>') && str_contains($emptyCollection,'data-empty >') && str_contains($emptyCollection,'Votre légende commence ici.'),'collection vide cohérente sans JavaScript');
vcheck(str_contains($emptyCollection,'data-collection-tab="obtained" aria-current="page"'),'onglet actif accessible dès le rendu serveur');
$remainingCollection=$member->request('/mes-succes?progress=remaining')['body'];
vcheck(str_contains($remainingCollection,'data-count>4</span>') && str_contains($remainingCollection,'data-empty hidden'),'collection restante cohérente sans JavaScript');
$u=(int)$p->query("SELECT user_id FROM discord_identities WHERE discord_user_id='100000000000000061'")->fetchColumn();$id=(int)$p->query("SELECT id FROM achievements WHERE slug='elan-du-matin-test'")->fetchColumn();
$post=fn(array $data)=>$member->request('/progression',['csrf'=>$member->token('/profil'),'achievement_id'=>$id,...$data],null,['Accept: application/json']);
vcheck($post(['action'=>'obtained'])['status']===200,'déclaration directe obtenue');$service=new PersonalService($p);$view=$service->overview($u);
vcheck($view['summary']['obtained']===1&&$view['summary']['remaining']===3&&$view['summary']['percent']===25.0,'statistiques personnelles cohérentes');
vcheck((string)$view['rewardsEstimate']['xp']['known']==='2500'&&(int)$view['rewardsEstimate']['xp']['unknown']===0,'récompenses cumulées exactes');
vcheck(array_sum(array_column($view['categoriesProgress'],'obtained'))===1,'progression réelle par catégorie');
vcheck(str_contains($member->request('/mes-succes?progress=obtained')['body'],'Déclaré obtenu le'),'date de déclaration présentée');
vcheck($post(['action'=>'not_started'])['status']===422,'annulation exige confirmation serveur');
vcheck($post(['action'=>'not_started','confirm'=>'1'])['status']===200&&$service->overview($u)['summary']['obtained']===0,'annulation confirmée met à jour les statistiques');
foreach(['accept','complete','abandon','resume','in_progress'] as $action)vcheck($post(['action'=>$action])['status']===422,'ancienne action refusée : '.$action);
$unknown=(int)$p->query("SELECT id FROM achievements WHERE slug='archive-inconnue-test'")->fetchColumn();$service->act($u,$unknown,'obtained');$view=$service->overview($u);vcheck((int)$view['rewardsEstimate']['xp']['unknown']===1&&(string)$view['rewardsEstimate']['xp']['known']==='0','NULL conservé comme inconnu dans les estimations');
$otherId=(int)$p->query("SELECT user_id FROM discord_identities WHERE discord_user_id='100000000000000062'")->fetchColumn();vcheck($service->overview($otherId)['summary']['obtained']===0,'estimations isolées par membre');
$generated=OwnerAccess::generate();vcheck((bool)preg_match('~[a-f0-9]{64}$~',$generated),'générateur cryptographique 256 bits');putenv('OWNER_LOGIN_PATH='.$generated);vcheck(OwnerAccess::matches($generated)&&!OwnerAccess::matches($path),'rotation de configuration sans ancien alias');putenv('OWNER_LOGIN_PATH='.$path);
$credentials=json_decode(file_get_contents('/tmp/levelingwiki-test-admin.json'),true);$owner=new HttpTestClient();$owner->login($credentials['username'],$credentials['password']);
$newPassword=bin2hex(random_bytes(20));$proc=proc_open([PHP_BINARY,dirname(__DIR__).'/bin/owner-access.php','--reset-password','--username=admin_test','--confirm-owner=admin_test','--password-stdin'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],$newPassword);fclose($pipes[0]);$output=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);vcheck(proc_close($proc)===0&&!str_contains($output,$newPassword),'récupération CLI réelle sans secret en sortie');
vcheck($owner->request('/admin')['status']===303,'récupération invalide les sessions');$p->exec('DELETE FROM login_limits');vcheck($owner->login('admin_test',$credentials['password'])['status']===401&&$owner->login('admin_test',$newPassword)['status']===303,'mot de passe récupéré utilisable, ancien refusé');
file_put_contents('/tmp/levelingwiki-test-admin.json',json_encode(['username'=>'admin_test','password'=>$newPassword]));
$logs=json_encode($p->query('SELECT * FROM audit_logs')->fetchAll());vcheck(!str_contains($logs,$path)&&!str_contains($logs,$newPassword),'audit sans chemin privé ni mot de passe');
