<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';require __DIR__.'/discord-support.php';
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='test'||getenv('DB_DATABASE')!=='levelingwiki_test')exit(1);
function catalogCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "OK catalogue : $label\n";}
$p=App\Services\Database::connect();$repo=new App\Models\GuildCatalogRepository($p);seedDiscordRoles($p);
$divisionSnapshot=$p->query('SELECT * FROM guild_divisions ORDER BY id')->fetchAll();
catalogCheck(count($repo->milestones())===12,'douze paliers importés');catalogCheck(count($repo->types())===5,'types distincts importés');
$levels=$repo->publicLevels();catalogCheck(count(array_filter($levels,fn($l)=>$l['rewards'][0]['label']==='???'))===5,'cinq récompenses inconnues');
catalogCheck($p->query('SELECT COUNT(*) FROM guild_milestone_rewards WHERE type_id IS NULL AND visibility=\'known\'')->fetchColumn()==2,'deux clés sans classification inventée');
$member=discordLogin('100000000000000911',['200000000000000001']);$editor=discordLogin('100000000000000912',['200000000000000001','200000000000000002']);$admin=discordLogin('100000000000000913',['200000000000000001','200000000000000003']);
$post=static fn($c,$section,$d)=>$c->request('/admin/tour-de-guilde/'.$section.'/save',['csrf'=>$c->token('/profil'),...$d]);
$draft=['level'=>'120','title'=>'Sommet <script>','description'=>'Un nouveau palier','archived'=>'0'];
foreach([$member] as $c){catalogCheck($c->request('/admin/tour-de-guilde/paliers')['status']===403,'lecture admin interdite');catalogCheck($post($c,'paliers',$draft)['status']===403,'écriture non admin interdite');}
catalogCheck($admin->request('/admin/tour-de-guilde/paliers/save',['csrf'=>'invalid',...$draft])['status']===403,'CSRF requis');
foreach(['0','-1','1.5','4294967296'] as $bad)catalogCheck($post($admin,'paliers',array_replace($draft,['level'=>$bad]))['status']===422,'niveau invalide '.$bad);
$r=$post($admin,'paliers',$draft);catalogCheck($r['status']===303,'création palier 120');parse_str(parse_url($r['location'],PHP_URL_QUERY),$params);$id=(int)$params['id'];
catalogCheck($post($admin,'paliers',$draft)['status']===422,'unicité niveau actif');
$edit=$draft+['id'=>$id,'version'=>'1'];$edit['title']='Nouveau sommet';catalogCheck($post($admin,'paliers',$edit)['status']===303,'modification palier');catalogCheck($post($admin,'paliers',$edit)['status']===409,'conflit palier');
$type=['slug'=>'custom-test','name'=>'Ressource SECRET_TYPE','short_name'=>'CT','description'=>'Type personnalisé','icon'=>'book','unit'=>'unités','category'=>'personal','display_order'=>'8','archived'=>'0'];
$r=$post($admin,'types',$type);catalogCheck($r['status']===303,'création type personnalisé');parse_str(parse_url($r['location'],PHP_URL_QUERY),$params);$typeId=(int)$params['id'];
$type+=['id'=>$typeId,'version'=>'1'];$type['unit']='jetons';catalogCheck($post($admin,'types',$type)['status']===303,'modification type');catalogCheck($post($admin,'types',$type)['status']===409,'conflit type');
$reward=['milestone_id'=>$id,'type_id'=>$typeId,'quantity'=>'987654321','distribution'=>'individual','description'=>'SECRET_DESCRIPTION','visibility'=>'unknown','display_order'=>'1','archived'=>'0'];
$r=$post($admin,'recompenses',$reward);catalogCheck($r['status']===303,'ajout inconnue renseignée');$rid=(int)$p->query('SELECT MAX(id) FROM guild_milestone_rewards')->fetchColumn();
$r=$post($admin,'recompenses',array_replace($reward,['type_id'=>'','quantity'=>'','description'=>'','display_order'=>'2']));catalogCheck($r['status']===303,'plusieurs inconnues indépendantes');
$body=$member->request('/tour-de-guilde')['body'];catalogCheck(!str_contains($body,'SECRET_TYPE')&&!str_contains($body,'SECRET_DESCRIPTION')&&!str_contains($body,'987654321'),'aucune fuite HTML/JSON inconnue');catalogCheck(str_contains($body,'id="level-120"')&&str_contains($body,'Nouveau sommet')&&str_contains($body,'tower-floor-relief'),'niveau 120 architecture 2,5D');
$reward+=['id'=>$rid,'version'=>'1'];$reward['visibility']='known';$reward['quantity']='500';catalogCheck($post($admin,'recompenses',$reward)['status']===303,'révélation sans recréation');catalogCheck($post($admin,'recompenses',$reward)['status']===409,'conflit récompense');
$body=$member->request('/tour-de-guilde')['body'];catalogCheck(str_contains($body,'500 Ressource SECRET_TYPE')&&str_contains($body,'???'),'connue et inconnue sur même palier');
$reward['version']='2';$reward['milestone_id']=1;catalogCheck($post($admin,'recompenses',$reward)['status']===422,'relation forgée refusée');$reward['milestone_id']=$id;
$reward['quantity']='-1';catalogCheck($post($admin,'recompenses',$reward)['status']===422,'quantité négative refusée');$reward['quantity']='700';catalogCheck($post($admin,'recompenses',$reward)['status']===303,'modification quantité');
$type['version']='2';$type['archived']='1';catalogCheck($post($admin,'types',$type)['status']===303,'archivage type');catalogCheck(str_contains($member->request('/tour-de-guilde')['body'],'700 Ressource SECRET_TYPE'),'référence type archivé préservée');
$new=$reward;unset($new['id'],$new['version']);catalogCheck($post($admin,'recompenses',$new)['status']===422,'nouvelle association type archivé interdite');
$reward['version']='3';$reward['archived']='1';catalogCheck($post($admin,'recompenses',$reward)['status']===303,'archivage récompense');catalogCheck(!str_contains($member->request('/tour-de-guilde')['body'],'SECRET_TYPE'),'récompense archivée absente');
$edit['version']='2';$edit['archived']='1';catalogCheck($post($admin,'paliers',$edit)['status']===303,'archivage palier');catalogCheck(!str_contains($member->request('/tour-de-guilde')['body'],'id="level-120"'),'palier archivé absent');$edit['version']='3';$edit['archived']='0';catalogCheck($post($admin,'paliers',$edit)['status']===303,'restauration palier');
$before=$p->query('SELECT * FROM guild_milestone_rewards ORDER BY id')->fetchAll();$migration=require dirname(__DIR__).'/database/migrations/016_guild_catalog.php';$migration($p);catalogCheck($before===$p->query('SELECT * FROM guild_milestone_rewards ORDER BY id')->fetchAll(),'réexécution sans écrasement ni résurrection');
catalogCheck($repo->find('milestone',$id)['title']==='Nouveau sommet','édition conservée');catalogCheck($divisionSnapshot===$p->query('SELECT * FROM guild_divisions ORDER BY id')->fetchAll(),'divisions inchangées');
catalogCheck($p->query("SELECT COUNT(*) FROM audit_logs WHERE action LIKE 'guild.catalog.%'")->fetchColumn()>=10,'historisation catalogue');
// Empty catalogue and archived level 1 must retain a structural foot, without a fictional reward.
$p->beginTransaction();$p->exec('UPDATE guild_milestones SET archived_at=NOW()');catalogCheck($repo->publicLevels()===[],'catalogue vide sans repli statique');$p->rollBack();
echo "OK catalogue : tests terminés\n";
// Restore both archived entities, preserving their persistent identifiers.
$type['version']='3';$type['archived']='0';catalogCheck($post($admin,'types',$type)['status']===303,'restauration type');
$reward['version']='4';$reward['archived']='0';catalogCheck($post($admin,'recompenses',$reward)['status']===303,'restauration récompense');
catalogCheck($post($admin,'paliers',array_replace($draft,['id'=>['forged']]))['status']===422,'identifiant composé rejeté');
$p->exec('DELETE FROM guild_milestone_rewards WHERE id=1');$migration($p);catalogCheck(!$repo->find('reward',1),'import ne ressuscite pas une entrée supprimée');
// Restore a seeded milestone whose level conflicts with another active one: must fail atomically.
$p->exec('UPDATE guild_milestones SET archived_at=NOW() WHERE id=1');
$r=$post($admin,'paliers',['level'=>'1','title'=>'Autre palier','description'=>'','archived'=>'0']);catalogCheck($r['status']===303,'niveau réutilisable après archivage');
catalogCheck($post($admin,'paliers',['id'=>'1','version'=>'1','level'=>'1','title'=>'Palier 1','description'=>'','archived'=>'0'])['status']===422,'restauration niveau occupé rejetée');
