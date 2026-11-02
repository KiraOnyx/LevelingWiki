<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';require __DIR__.'/discord-support.php';
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='test'||getenv('DB_DATABASE')!=='levelingwiki_test')exit(1);
function coherenceCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "OK cohérence : $label\n";}
$p=App\Services\Database::connect();seedDiscordRoles($p);
$admin=discordLogin('100000000000000991',['200000000000000001','200000000000000003']);
$editor=discordLogin('100000000000000992',['200000000000000001','200000000000000002']);
$member=discordLogin('100000000000000993',['200000000000000001']);
$masterId=(int)$p->query("SELECT user_id FROM discord_identities WHERE discord_user_id='100000000000000992'")->fetchColumn();
$post=fn($client,$data)=>$client->request('/admin/tour-de-guilde/save',['csrf'=>$client->token('/profil'),...$data]);
$draft=['name'=>'Division Cohérence','short_name'=>'UX','description'=>'Fixture uniquement','color'=>'#AA77CC','level'=>'43','archived'=>'0'];
$r=$post($admin,$draft);coherenceCheck($r['status']===303,'création sans sélection de responsables');parse_str(parse_url($r['location'],PHP_URL_QUERY),$params);$id=(int)$params['division'];$repo=new App\Models\GuildDivisionRepository($p);
coherenceCheck($repo->masterIds($id)===[],'nouvelle division sans attribution implicite');
// Existing guarded API can still seed an assignment; the HTML no longer offers it.
$edit=$draft+['id'=>$id,'version'=>'1','master_ids'=>[$masterId]];coherenceCheck($post($admin,$edit)['status']===303,'association de fixture via contrôle existant');
$snapshot=$p->query('SELECT * FROM guild_division_masters WHERE division_id='.$id)->fetchAll();unset($edit['master_ids']);$edit['version']='2';$edit['name']='Division Cohérence modifiée';
coherenceCheck($post($admin,$edit)['status']===303,'modification sans champ master_ids');
coherenceCheck($snapshot===$p->query('SELECT * FROM guild_division_masters WHERE division_id='.$id)->fetchAll(),'associations et métadonnées strictement conservées');
$body=$admin->request('/admin/tour-de-guilde?division='.$id)['body'];coherenceCheck(!str_contains($body,'name="master_ids')&&!str_contains($body,'<legend>Guild Masters'),'sélection retirée du formulaire');
$body=$admin->request('/admin/tour-de-guilde')['body'];coherenceCheck(str_contains($body,'class="secondary-button" href="/admin/tour-de-guilde?division='.$id.'#division-history">Niveau et historique</a>')&&str_contains($body,'#division-editor">Modifier</a>'),'deux actions et destinations explicites pour admin');
$body=$editor->request('/admin/tour-de-guilde')['body'];coherenceCheck(str_contains($body,'#division-history">Niveau et historique</a>')&&!str_contains($body,'#division-editor">Modifier</a>'),'éditeur assigné sans bouton de modification administrative');
coherenceCheck($editor->request('/admin/tour-de-guilde?division='.$id)['status']===200,'historique accessible au Guild Master');
coherenceCheck($post($editor,$edit)['status']===403,'éditeur interdit de modifier les métadonnées');
coherenceCheck($post($member,$edit)['status']===403,'membre interdit');
coherenceCheck($admin->request('/admin/tour-de-guilde/save',['csrf'=>'invalid',...$edit])['status']===403,'CSRF conservé');
coherenceCheck($editor->request('/admin/tour-de-guilde/level',['csrf'=>$editor->token('/profil'),'id'=>$id,'version'=>'3','level'=>'44'])['status']===303,'Guild Master conserve la progression');
$p->prepare('UPDATE users SET is_active=0 WHERE id=?')->execute([$masterId]);$edit['version']='4';$edit['level']='44';
coherenceCheck($post($admin,$edit)['status']===303,'édition conserve aussi une association inactive sans réattribution');
coherenceCheck($snapshot===$p->query('SELECT * FROM guild_division_masters WHERE division_id='.$id)->fetchAll(),'association inactive inchangée');$p->prepare('UPDATE users SET is_active=1 WHERE id=?')->execute([$masterId]);
$credentials=json_decode(file_get_contents('/tmp/levelingwiki-test-admin.json'),true);$owner=new HttpTestClient();coherenceCheck($owner->login($credentials['username'],$credentials['password'])['status']===303,'connexion propriétaire fictif');$edit['version']='5';coherenceCheck($post($owner,$edit)['status']===303,'SUPER_ADMIN conserve la modification');coherenceCheck($repo->masterIds($id)===[$masterId],'association conservée après modification propriétaire');
$r=$admin->request('/admin/tour-de-guilde/paliers/save',['csrf'=>$admin->token('/profil'),'level'=>'630','title'=>'Épreuve Cohérence','description'=>'Fixture','archived'=>'0']);coherenceCheck($r['status']===303,'création palier de recherche');parse_str(parse_url($r['location'],PHP_URL_QUERY),$milestone);$mid=(int)$milestone['id'];
$body=$admin->request('/admin/tour-de-guilde/paliers?q=epreuve%20coherence&status=active')['body'];coherenceCheck(str_contains($body,'Épreuve Cohérence')&&str_contains($body,'data-server-results'),'recherche serveur insensible aux accents');
$body=$admin->request('/admin/tour-de-guilde/paliers?q=epreuve%20coherence&status=archived')['body'];coherenceCheck(str_contains($body,'Aucun palier correspondant.'),'recherche et statut combinés');
if(in_array('--visual',$argv,true)){
 foreach(['divisions'=>'/admin/tour-de-guilde','division'=>'/admin/tour-de-guilde?division='.$id,'division-new'=>'/admin/tour-de-guilde?new=1','paliers'=>'/admin/tour-de-guilde/paliers','palier'=>'/admin/tour-de-guilde/paliers?id='.$mid,'types'=>'/admin/tour-de-guilde/types','type'=>'/admin/tour-de-guilde/types?id=1','categories'=>'/admin/categories','titres'=>'/admin/titles','succes'=>'/admin/achievements','discord'=>'/admin/settings/discord','codex'=>'/succes'] as $name=>$url)file_put_contents('/tmp/coherence-'.$name.'.html',$admin->request($url)['body']);
 file_put_contents('/tmp/coherence-cookies.json',json_encode($admin->cookies));
}
