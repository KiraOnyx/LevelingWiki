<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';require __DIR__.'/discord-support.php';
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='test'||getenv('DB_DATABASE')!=='levelingwiki_test')exit(1);
function formsCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "OK formulaires : $label\n";}
$p=App\Services\Database::connect();seedDiscordRoles($p);$admin=discordLogin('100000000000000971',['200000000000000001','200000000000000003']);
$post=fn($d)=>$admin->request('/admin/tour-de-guilde/paliers/save',['csrf'=>$admin->token('/profil'),...$d]);
$ids=[];foreach([[501,'Harmonie Alpha','0'],[502,'Harmonie Beta','0'],[503,'Harmonie Gamma','1']] as [$level,$title,$archive]){$r=$post(['level'=>$level,'title'=>$title,'description'=>'Essai compact','archived'=>$archive]);formsCheck($r['status']===303,'création fixture '.$title);parse_str(parse_url($r['location'],PHP_URL_QUERY),$args);$ids[]=$args['id'];}
$read=fn($params)=>$admin->request('/admin/tour-de-guilde/paliers?'.http_build_query($params));
$r=$read(['q'=>'Harmonie','status'=>'active','sort'=>'desc']);$body=$r['body'];formsCheck($r['status']===200&&str_contains($body,'2 palier(s) correspondant(s)'),'recherche et statut combinés');formsCheck(strpos($body,'Niv. 502')<strpos($body,'Niv. 501')&&!str_contains($body,'Niv. 503'),'tri décroissant et exclusion archivé');formsCheck(str_contains($body,'value="Harmonie"')&&str_contains($body,'value="desc" selected'),'sélections conservées');formsCheck(str_contains($body,'action="/admin/tour-de-guilde/paliers"')&&!str_contains($body,'id="catalog-editor"'),'cible canonique et absence de formulaire permanent');
$body=$read(['q'=>'Harmonie','status'=>'all','sort'=>'asc'])['body'];formsCheck(strpos($body,'Niv. 501')<strpos($body,'Niv. 503')&&str_contains($body,'3 palier(s) correspondant(s)'),'tous statuts et tri croissant');
$body=$read(['q'=>'Harmonie','status'=>'archived'])['body'];formsCheck(str_contains($body,'Niv. 503')&&!str_contains($body,'Niv. 501')&&str_contains($body,'Restaurer'),'filtre archivé et action explicite');
foreach(['Absent-934834','%','_'] as $term){$body=$read(['q'=>$term])['body'];formsCheck(str_contains($body,'Aucun palier correspondant.')&&str_contains($body,'0 palier(s)'),'recherche vide ou littérale '.$term);}
$body=$read(['new'=>'1','q'=>'Harmonie','status'=>'all','sort'=>'desc'])['body'];formsCheck(str_contains($body,'Créer un palier')&&str_contains($body,'value="Harmonie"')&&!str_contains($body,'class="guild-catalog-cards"'),'création dédiée et contexte conservé');
$r=$post(['id'=>$ids[0],'version'=>'1','level'=>'0','title'=>'Valeur conservée','description'=>'Essai','archived'=>'0','q'=>'Harmonie','status'=>'all','sort'=>'desc']);formsCheck($r['status']===422&&str_contains($r['body'],'value="Valeur conservée"')&&str_contains($r['body'],'Retour à la liste'),'erreur conserve les valeurs et retour sûr');
$r=$post(['id'=>$ids[0],'version'=>'1','level'=>'501','title'=>'Harmonie Alpha','description'=>'Essai','archived'=>'1','q'=>'Harmonie','status'=>'all','sort'=>'desc']);formsCheck($r['status']===303&&str_contains($r['location'],'q=Harmonie&status=all&sort=desc'),'archivage conserve les filtres');
$body=$admin->request('/admin/tour-de-guilde/types')['body'];formsCheck(!str_contains($body,'id="catalog-editor"')&&str_contains($body,'+ Ajouter un type'),'types sans formulaire permanent');
$body=$admin->request('/admin/tour-de-guilde')['body'];formsCheck(!str_contains($body,'action="/admin/tour-de-guilde/save"')&&str_contains($body,'+ Ajouter une division'),'divisions sans formulaire permanent');
$body=$admin->request('/admin/tour-de-guilde?new=1')['body'];formsCheck(str_contains($body,'Créer une division')&&str_contains($body,'Annuler'),'création division et annulation');
foreach(['/admin/categories','/admin/titles','/admin/settings/discord'] as $path)formsCheck(str_contains($admin->request($path)['body'],'data-admin-disclosure'),'formulaires à la demande '.$path);
$type=$p->query("SELECT * FROM guild_reward_types WHERE slug='gold'")->fetch();
$payload=array_intersect_key($type,array_flip(['id','version','slug','name','short_name','description','icon','unit','category','display_order']));$payload['archived']='1';
$r=$admin->request('/admin/tour-de-guilde/types/save',['csrf'=>$admin->token('/profil'),...$payload]);formsCheck($r['status']===303,'archivage type depuis ses propriétés conservées');
$body=$admin->request('/admin/tour-de-guilde/types?q=gold&status=active')['body'];formsCheck(str_contains($body,'Aucun type correspondant.'),'type archivé absent des résultats actifs');
$body=$admin->request('/admin/tour-de-guilde/types?q=gold&status=archived')['body'];formsCheck(str_contains($body,'Restaurer')&&!str_contains($body,'Aucun type correspondant.'),'recherche des types archivés');
$payload['archived']='0';$payload['version']=(string)((int)$payload['version']+1);formsCheck($admin->request('/admin/tour-de-guilde/types/save',['csrf'=>$admin->token('/profil'),...$payload])['status']===303,'restauration type sans perte');
