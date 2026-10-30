<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';require __DIR__.'/discord-support.php';
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='test'||getenv('DB_DATABASE')!=='levelingwiki_test')exit(1);
function uxCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "OK UX : $label\n";}
$p=App\Services\Database::connect();seedDiscordRoles($p);
$admin=discordLogin('100000000000000981',['200000000000000001','200000000000000003']);
$member=discordLogin('100000000000000982',['200000000000000001']);
$editor=discordLogin('100000000000000983',['200000000000000001','200000000000000002']);
$post=fn($section,$data)=>$admin->request('/admin/tour-de-guilde/'.$section.'/save',['csrf'=>$admin->token('/profil'),...$data]);
$r=$post('paliers',['level'=>'620','title'=>'Palier de démonstration','description'=>'Validation UX','archived'=>'0']);uxCheck($r['status']===303,'création palier isolé');parse_str(parse_url($r['location'],PHP_URL_QUERY),$params);$id=$params['id'];
$type=$p->query("SELECT id FROM guild_reward_types WHERE slug='gold'")->fetchColumn();
$payload=['milestone_id'=>$id,'type_id'=>$type,'quantity'=>'3','distribution'=>'individual','visibility'=>'known','description'=>'','display_order'=>'0','archived'=>'0'];
uxCheck($post('recompenses',$payload)['status']===303,'ajout confirmé');$rid=$p->query('SELECT MAX(id) FROM guild_milestone_rewards')->fetchColumn();
$payload+=['id'=>$rid,'version'=>'1'];$payload['quantity']='7';$r=$post('recompenses',$payload);uxCheck($r['status']===303,'modification confirmée');
$body=$admin->request($r['location'])['body'];uxCheck(str_contains($body,'7 Pièces d’or')&&str_contains($body,'Distribution : Par membre'),'carte actualisée');
uxCheck(str_contains($body,'>Modifier</summary>')&&str_contains($body,'+ Ajouter une récompense')&&!str_contains($body,'Modifier la récompense #'),'actions explicites sans titre technique');
uxCheck(!preg_match('/<details data-admin-disclosure\s+open/',$body),'formulaires refermés après succès');
uxCheck(str_contains($body,'data-disclosure-cancel')&&!str_contains($body,'/recompenses/delete'),'annulation et absence de suppression inventée');
$payload['version']='2';$payload['quantity']='-1';$r=$post('recompenses',$payload);uxCheck($r['status']===422&&str_contains($r['body'],'value="-1"')&&str_contains($r['body'],'data-admin-disclosure open'),'erreur conserve formulaire ouvert et saisie');
$unknown=['milestone_id'=>$id,'type_id'=>'','quantity'=>'','distribution'=>'unconfirmed','visibility'=>'unknown','description'=>'','display_order'=>'1','archived'=>'0'];uxCheck($post('recompenses',$unknown)['status']===303,'inconnue vide acceptée');
$row=$p->query('SELECT * FROM guild_milestone_rewards ORDER BY id DESC LIMIT 1')->fetch();uxCheck($row['quantity']===null&&$row['type_id']===null,'NULL préservé');
uxCheck($member->request('/admin/tour-de-guilde/recompenses/save',['csrf'=>$member->token('/profil'),...$unknown])['status']===403,'membre refusé');
uxCheck($admin->request('/admin/tour-de-guilde/recompenses/save',['csrf'=>'invalid',...$unknown])['status']===403,'CSRF récompense refusé');
$memberTower=$member->request('/tour-de-guilde')['body'];uxCheck(!str_contains($memberTower,'Gérer les divisions'),'gestion masquée au membre');
foreach([$admin,$editor] as $actor){$tower=$actor->request('/tour-de-guilde')['body'];uxCheck((bool)preg_match('/<aside class="tower-sidebar">\s*<div class="tower-management">/',$tower),'gestion dans le conteneur sticky autorisé');}
if(in_array('--visual',$argv,true)){
 file_put_contents('/tmp/reward-ux.html',$admin->request('/admin/tour-de-guilde/paliers?id='.$id)['body']);
 file_put_contents('/tmp/reward-error.html',$r['body']);
 file_put_contents('/tmp/tower-ux.html',$admin->request('/tour-de-guilde')['body']);
}
