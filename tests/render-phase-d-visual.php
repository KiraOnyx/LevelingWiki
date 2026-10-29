<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';require __DIR__.'/discord-support.php';
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='test'||getenv('DB_DATABASE')!=='levelingwiki_test')exit(1);
$p=App\Services\Database::connect();seedDiscordRoles($p);$admin=discordLogin('100000000000000951',['200000000000000001','200000000000000003']);
$p->exec("INSERT INTO categories(slug,name,color) VALUES('visual-d','Régularité et aventures collectives','#4E9B78')");$cat=(int)$p->lastInsertId();
$data=['description'=>'Condition courte','unlock_conditions'=>'Terminer une quête simultanément avec un autre participant.','secret_type'=>'secret','verification_status'=>'unverified','publication_status'=>'published','rarity'=>'Rare','reward_xp'=>'150','reward_gold'=>'300','reward_gems'=>'25','reward_keys'=>'2','title_rewards_known'=>'1','category_id'=>$cat,'participants_required'=>'2'];
foreach(['Ensemble au sommet','Un exploit collectif au nom particulièrement long pour vérifier les retours à la ligne','Le prochain mystère'] as $i=>$name){$r=$admin->post('/admin/achievements/save',[...$data,'name'=>$name,'slug'=>'visual-d-'.$i,'reward_gems'=>$i===2?'':'25']);preg_match('~/([0-9]+)/edit~',$r['location'],$m);$ids[]=(int)$m[1];}
$admin->request('/progression',['csrf'=>$admin->token('/profil'),'achievement_id'=>$ids[0],'action'=>'obtained']);
$dir='/var/www/storage/phase-d-visual';@mkdir($dir,0700,true);
foreach(['profile'=>'/profil','home'=>'/','codex'=>'/succes','list'=>'/succes?view=list','detail'=>'/succes/visual-d-1','editor'=>'/admin/achievements/'.$ids[1].'/edit','milestones'=>'/admin/tour-de-guilde/paliers'] as $name=>$url){$body=$admin->request($url)['body'];file_put_contents($dir.'/'.$name.'.html',str_replace('<head>','<head><base href="/">',$body));}
echo "Rendus de test inertes créés.\n";
