<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';require __DIR__.'/discord-support.php';
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='test'||getenv('DB_DATABASE')!=='levelingwiki_test')exit(1);
function uxCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "OK UX : $label\n";}
$p=App\Services\Database::connect();seedDiscordRoles($p);
$member=discordLogin('100000000000000951',['200000000000000001']);$editor=discordLogin('100000000000000952',['200000000000000001','200000000000000002']);$admin=discordLogin('100000000000000953',['200000000000000001','200000000000000003']);
$body=$member->request('/succes')['body'];uxCheck(str_contains($body,'data-collection-tab="remaining" aria-current="page"'),'À débloquer sélectionné par défaut');uxCheck(strpos($body,'data-collection-tab="remaining"')<strpos($body,'data-collection-tab="obtained"'),'ordre des onglets');uxCheck(!str_contains($body,'href="/mes-succes"'),'ancienne navigation retirée');
$r=$member->request('/mes-succes?q=dragon&progress=remaining&view=list');uxCheck($r['status']===302&&$r['location']==='/succes?q=dragon&progress=remaining&view=list','redirection avec filtres');
$r=$member->request('/mes-succes');uxCheck($r['status']===302&&$r['location']==='/succes?progress=obtained','ancienne collection conserve Obtenus');
$body=$member->request('/succes?progress=obtained&view=list')['body'];uxCheck(str_contains($body,'data-display="list"')&&str_contains($body,'data-collection-tab="obtained" aria-current="page"'),'lien direct Obtenus/Liste');
uxCheck(str_contains($member->request('/')['body'],'/succes?progress=obtained&amp;view=list&amp;sort=obtained-desc'),'Tout voir ouvre l’historique unifié');
foreach([$editor,$admin] as $c){$body=$c->request('/admin/achievements')['body'];uxCheck(str_contains($body,'aria-label="Administration des succès"')&&str_contains($body,'Gestion des succès')&&str_contains($body,'Voir le Codex'),'sous-menu succès');}
$body=$admin->request('/admin/categories')['body'];uxCheck(substr_count($body,'href="/admin/categories"')===1&&str_contains($body,'Administration des succès'),'catégories au second niveau uniquement');
$draft=['level'=>'997','title'=>'Palier UX','description'=>'Prérempli','archived'=>'0'];
$post=fn($c,$data)=>$c->request('/admin/tour-de-guilde/paliers/save',['csrf'=>$c->token('/profil'),...$data]);
$r=$post($editor,$draft);uxCheck($r['status']===303,'EDITOR crée un palier');parse_str(parse_url($r['location'],PHP_URL_QUERY),$params);$id=(int)$params['id'];
$body=$editor->request('/admin/tour-de-guilde/paliers?id='.$id)['body'];uxCheck(str_contains($body,'value="Palier UX"')&&str_contains($body,'Prérempli')&&str_contains($body,'Ajouter une récompense'),'formulaire et récompenses préremplis');uxCheck(str_contains($body,'#catalog-editor">Modifier</a>')&&str_contains($body,'id="catalog-editor"'),'bouton explicite et destination du formulaire');
uxCheck($post($member,$draft)['status']===403,'MEMBER refusé');uxCheck($editor->request('/admin/tour-de-guilde/paliers/save',['csrf'=>'invalid',...$draft])['status']===403,'CSRF EDITOR');
$draft+=['id'=>$id,'version'=>'1'];$draft['title']='Palier édité';uxCheck($post($admin,$draft)['status']===303,'ADMIN modifie');uxCheck($post($editor,$draft)['status']===409,'version périmée EDITOR refusée');
$owner=$p->query("SELECT * FROM users WHERE role='super_admin' AND is_active=1 LIMIT 1")->fetch();uxCheck((bool)$owner,'propriétaire existant de la suite');
$draft['version']='2';$draft['archived']='1';(new App\Services\GuildCatalogService($p))->save('milestone',$draft,$owner);uxCheck((new App\Models\GuildCatalogRepository($p))->find('milestone',$id)['archived_at']!==null,'SUPER_ADMIN archive via service sécurisé');
$draft['version']='3';$draft['archived']='0';uxCheck($post($editor,$draft)['status']===303,'EDITOR restaure');
$q=$p->prepare("SELECT COUNT(*) FROM audit_logs WHERE entity_type='guild_milestone' AND entity_id=?");$q->execute([$id]);uxCheck($q->fetchColumn()==4,'historique des quatre changements');
