<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
require __DIR__.'/discord-support.php';
use App\Services\{Database,OwnerService,ValidationException};
if (PHP_SAPI!=='cli' || getenv('APP_ENV')!=='test' || getenv('DB_DATABASE')!=='levelingwiki_test') exit(1);
function verify4(bool $ok,string $label): void {if (!$ok) throw new RuntimeException('ÉCHEC : '.$label);echo "OK : $label\n";}
function memberPost(HttpTestClient $client,string $path,array $data): array {return $client->request($path,['csrf'=>$client->token('/profil'),...$data]);}
function age4(HttpTestClient $client,bool $token=false): void {
    $code='session_id(trim(stream_get_contents(STDIN)));session_start();$_SESSION["discord"]["checked"]=0;'.($token?'$_SESSION["discord"]["tokens"]["expires"]=0;':'').'session_write_close();';
    $p=proc_open([PHP_BINARY,'-r',$code],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    fwrite($pipes[0],$client->cookies['lw_session']);fclose($pipes[0]);stream_get_contents($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($p)!==0)throw new RuntimeException('Horloge de test');
}
$pdo=Database::connect();seedDiscordRoles($pdo);$credentials=json_decode(file_get_contents('/tmp/levelingwiki-test-admin.json'),true);
$owner=new HttpTestClient();verify4($owner->login($credentials['username'],$credentials['password'])['status']===303,'SUPER_ADMIN local opérationnel');
$ownerId=(int)$pdo->query("SELECT id FROM users WHERE role='super_admin'")->fetchColumn();
try {(new OwnerService($pdo))->designate('admin_test','admin_test',$credentials['password']);verify4(false,'unique owner');} catch(ValidationException) {verify4(true,'second propriétaire refusé');}
$anonymous=new HttpTestClient();
foreach (['/','/succes','/succes/elan-du-matin-test','/profil'] as $path) {
    $r=$anonymous->request($path);verify4($r['status']===303 && !str_contains($r['body'],'Élan'),'contenu privé anonyme : '.$path);
}
verify4($anonymous->request('/assets/images/capture.webp')['status']===403,'captures statiques protégées');
verify4($anonymous->request('/auth/discord',['csrf'=>'bad'])['status']===403,'CSRF au départ OAuth');
verify4($anonymous->request('/auth/discord/callback?state=invalid&code=invalid')['status']===403,'state invalide refusé');
foreach ([
    ['100000000000000010',[],[], 'rôle MEMBER absent'],
    ['100000000000000013',['200000000000000001'],['absent'=>true], 'absent du serveur'],
    ['100000000000000014',['200000000000000001'],['invalid_token'=>true], 'token invalide'],
    ['100000000000000015',['200000000000000001'],['pending'=>true], 'adhésion en attente'],
] as [$id,$roles,$extra,$label]) {
    $client=new HttpTestClient();verify4(discordFlow($client,$id,$roles,$extra)['status']===403,'OAuth refusé : '.$label);
    $q=$pdo->prepare('SELECT COUNT(*) FROM discord_identities WHERE discord_user_id=?');$q->execute([$id]);verify4((int)$q->fetchColumn()===0,'aucun compte créé sans autorisation');
}
foreach(['editor'=>'200000000000000002','admin'=>'200000000000000003'] as $role=>$discordRole){$client=new HttpTestClient();verify4(discordFlow($client,$role==='editor'?'100000000000000011':'100000000000000012',[$discordRole])['status']===303,$role.' seul autorisé');verify4($client->request('/admin')['status']===200,'compte créé avec rôle autorisé');}
$replayClient=new HttpTestClient();
$start=$replayClient->request('/auth/discord',['csrf'=>$replayClient->token('/connexion')]);
parse_str(parse_url($start['location'],PHP_URL_QUERY),$oauthParams);
$badCallback='/auth/discord/callback?'.http_build_query(['state'=>$oauthParams['state'],'code'=>'unregistered-code']);
verify4($replayClient->request($badCallback)['status']===403,'code OAuth inconnu refusé');
verify4(str_contains($replayClient->request($badCallback)['body'],'state invalide'),'state consommé même après un échec de token');
$member=discordLogin('100000000000000020',['200000000000000001']);
$other=discordLogin('100000000000000021',['200000000000000001']);
$editor=discordLogin('100000000000000022',['200000000000000001','200000000000000002']);
$admin=discordLogin('100000000000000023',['200000000000000001','200000000000000003']);
verify4($member->request('/succes')['status']===200 && str_contains($member->request('/succes')['body'],'Secrets</option>'),'catalogue et filtres accessibles au membre');
verify4($member->request('/admin/achievements/new')['status']===403,'MEMBER ne peut pas éditer');
verify4($editor->request('/admin/achievements/new')['status']===200 && $editor->request('/admin/settings/discord')['status']===200,'EDITOR peut éditer et gérer MEMBER');
verify4($admin->request('/admin/achievements/new')['status']===200,'ADMIN implique EDITOR sans rôle EDITOR');
verify4($admin->request('/admin/users')['status']===403,'ADMIN ne peut gérer ni le propriétaire ni les comptes');
verify4($admin->post('/admin/achievements/1/delete',['confirm'=>'1'])['status']===403,'ADMIN ne peut supprimer définitivement');
foreach (['member','editor'] as $level) verify4($admin->post('/admin/settings/discord',['action'=>'save','level'=>$level,'discord_role_id'=>'200000000000000009','label'=>'Libellé '.$level])['status']===303,'ADMIN gère les rôles '.$level);
verify4($admin->post('/admin/settings/discord',['action'=>'save','level'=>'admin','discord_role_id'=>'200000000000000009','label'=>'Escalade'])['status']===403,'ADMIN ne peut ajouter un rôle ADMIN');
verify4($admin->post('/admin/settings/discord',['action'=>'delete','level'=>'admin','discord_role_id'=>'200000000000000003'])['status']===403,'ADMIN ne peut retirer un rôle ADMIN');
verify4($owner->post('/admin/settings/discord',['action'=>'save','level'=>'admin','discord_role_id'=>'200000000000000009','label'=>'Super ? Non, ADMIN'])['status']===303,'SUPER_ADMIN gère les rôles ADMIN');
$multi=discordLogin('100000000000000024',['200000000000000009']);
verify4($multi->request('/admin')['status']===200,'plusieurs rôles autorisés : autre ID MEMBER et ADMIN accepté');
verify4((int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='super_admin' AND auth_provider='discord'")->fetchColumn()===0,'Discord n’attribue jamais SUPER_ADMIN');
verify4($owner->post('/admin/settings/discord',['action'=>'save','level'=>'member','discord_role_id'=>'200000000000000009','label'=>'Nouveau libellé sans changement du rôle'])['status']===303 && $multi->request('/succes')['status']===200,'renommage de libellé indépendant de l’autorisation');
// Première connexion avec le pseudo exact du propriétaire : aucun rapprochement de compte.
$same=new HttpTestClient();verify4(discordFlow($same,'100000000000000025',['200000000000000001'],['name'=>'admin_test'])['status']===303,'pseudo Discord identique au compte local accepté séparément');
verify4((int)$pdo->query("SELECT user_id FROM discord_identities WHERE discord_user_id='100000000000000025'")->fetchColumn()!==$ownerId,'aucun lien automatique par pseudo');
verify4(discordFlow($same,'100000000000000025',['200000000000000001'],['name'=>'Pseudo actualisé'])['status']===303 && $pdo->query("SELECT display_name FROM discord_identities WHERE discord_user_id='100000000000000025'")->fetchColumn()==='Pseudo actualisé','pseudo actualisé à la reconnexion');
$memberId=(int)$pdo->query("SELECT user_id FROM discord_identities WHERE discord_user_id='100000000000000020'")->fetchColumn();
$otherId=(int)$pdo->query("SELECT user_id FROM discord_identities WHERE discord_user_id='100000000000000021'")->fetchColumn();
$id=(int)$pdo->query("SELECT id FROM achievements WHERE slug='elan-du-matin-test'")->fetchColumn();
$state=static function() use($pdo,$memberId,$id){return $pdo->query("SELECT * FROM personal_achievements WHERE user_id=$memberId AND achievement_id=$id")->fetch();};
verify4(memberPost($member,'/progression',['achievement_id'=>$id,'action'=>'obtained'])['status']===303 && $state()['progress']==='obtained','obtention directe sans tâche');
verify4(memberPost($other,'/progression',['user_id'=>$memberId,'achievement_id'=>$id,'action'=>'obtained'])['status']===422,'isolation des déclarations');
verify4(memberPost($member,'/progression/import-local',['confirm'=>'1','slugs'=>json_encode(['archive-inconnue-test'])])['status']===303,'migration volontaire du suivi local');
verify4(memberPost($member,'/progression/import-local',['slugs'=>'[]'])['status']===422,'migration locale sans consentement refusée');
$member->request('/deconnexion',['csrf'=>$member->token('/profil')]);$member=discordLogin('100000000000000020',['200000000000000001']);
verify4($state()['progress']==='obtained','progression conservée à la reconnexion');
$pdo->exec("UPDATE achievements SET publication_status='draft' WHERE id=$id");
verify4(!str_contains($member->request('/succes')['body'],'Élan du matin'),'brouillon masqué dans le suivi personnel');
verify4(memberPost($other,'/progression',['achievement_id'=>$id,'action'=>'obtained'])['status']===422,'déclaration de brouillon refusée');
$pdo->exec("UPDATE achievements SET publication_status='published',archived_at=CURRENT_TIMESTAMP WHERE id=$id");
verify4(memberPost($other,'/progression',['achievement_id'=>$id,'action'=>'obtained'])['status']===422,'archive non déclarable');
$pdo->exec("UPDATE achievements SET archived_at=NULL WHERE id=$id");
// Révocation après expiration quotidienne du cache.
mockDiscord(['user'=>['id'=>'100000000000000022','roles'=>['200000000000000001']]]);
expireDiscordCache($editor);verify4($editor->request('/admin/achievements/new')['status']===403,'révocation EDITOR vérifiée pour accès sensible');
mockDiscord(['user'=>['id'=>'100000000000000023','roles'=>['200000000000000001']]]);
expireDiscordCache($admin);verify4($admin->request('/admin/settings/discord')['status']===403,'révocation ADMIN');
mockDiscord(['user'=>['id'=>'100000000000000020','roles'=>[]]]);
age4($member);
verify4($member->request('/succes')['status']===403 && $state()['progress']==='obtained','expiration du cache retire MEMBER sans perdre le suivi');
verify4($owner->post('/admin/settings/discord',['action'=>'delete','level'=>'member','discord_role_id'=>'200000000000000009'])['status']===303 && $multi->request('/succes')['status']===200,'retrait MEMBER conserve le rôle ADMIN configuré');
expireDiscordCache($multi);mockDiscord(['outage'=>true]);
verify4($owner->request('/admin')['status']===200 && $owner->request('/succes')['status']===200,'propriétaire indépendant d’une panne Discord');
verify4($multi->request('/admin/settings/discord')['status']===403,'échec API refuse les permissions sensibles');
mockDiscord(['outage'=>false]);
$member=discordLogin('100000000000000020',['200000000000000001']);age4($member,true);
verify4($member->request('/succes')['status']===200,'rafraîchissement des tokens côté serveur');
verify4(!str_contains($owner->request('/admin/settings/discord')['body'],'isolated-test-only'),'secret OAuth jamais rendu dans l’interface');
$logs=json_encode($pdo->query('SELECT old_values,new_values FROM audit_logs')->fetchAll());
verify4(!str_contains($logs,'isolated-test-only') && !str_contains($logs,'access_token') && !str_contains($logs,'refresh_token'),'audit sans secrets OAuth');
verify4((int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action LIKE 'discord.role_%'")->fetchColumn()>0,'changements de rôles journalisés');
echo "Phase 4 : OAuth simulé, permissions et suivi vérifiés.\n";
