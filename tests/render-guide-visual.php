<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';require __DIR__.'/discord-support.php';
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='test'||getenv('DB_DATABASE')!=='levelingwiki_test')exit(1);
$p=App\Services\Database::connect();seedDiscordRoles($p);
$editor=discordLogin('100000000000000991',['200000000000000001','200000000000000002']);
$base=['description'=>'Condition courte','unlock_conditions'=>'Valider une quête entre minuit et 4 h du matin.','secret_type'=>'secret','verification_status'=>'unverified','publication_status'=>'published','rarity'=>'Rare','reward_xp'=>'150','reward_gold'=>'300','reward_gems'=>'25','reward_keys'=>'2','title_rewards_known'=>'1'];
$ids=[];
foreach(['Le gardien des heures silencieuses','Un très long nom de succès pour vérifier la hiérarchie et les retours à la ligne sur smartphone','Un mystère à éclaircir'] as $i=>$name){$data=[...$base,'name'=>$name,'slug'=>'visuel-guide-'.$i];if($i===2){$data['reward_gold']='';$data['secret_type']='normal';}$r=$editor->post('/admin/achievements/save',$data);preg_match('~/([0-9]+)/edit~',$r['location'],$m);$ids[]=(int)$m[1];}
$file=['field'=>'image','name'=>'illustration.webp','mime'=>'image/webp','body'=>file_get_contents(dirname(__DIR__).'/public/assets/illustrations/valhalla/valhalla-gates.webp')];
$r=$editor->request('/admin/achievements/guide-image',['csrf'=>$editor->token(),'achievement_id'=>$ids[0],'alt'=>'Illustration de démonstration : les portes de Valhalla'],$file);$url=json_decode($r['body'],true)['path'];
$md="### Préparation\nChoisissez une quête adaptée à votre niveau.\n\n### Réalisation\n1. Préparez votre séance.\n2. Validez votre quête entre **00 h 00 et 04 h 00**.\n3. Vérifiez que le succès a bien été attribué.\n\n> Conseil : vérifiez les conditions exactes dans l’application.\n\n![Les portes de Valhalla — illustration de démonstration]($url)\n\n### Pour aller plus loin\n- Prenez le temps de vérifier chaque étape.\n- Gardez une capture de votre résultat.";
$editor->post('/admin/achievements/save',[...$base,'name'=>'Le gardien des heures silencieuses','slug'=>'visuel-guide-0','id'=>$ids[0],'guide_markdown'=>$md]);
$dir='/var/www/storage/guide-visual';@mkdir($dir,0700,true);@mkdir($dir.'/guide-media',0700,true);copy((new App\Services\UploadService(true))->resolve($url),$dir.$url);
$save=function($name,$body)use($dir){$body=str_replace('<head>','<head><base href="/">',$body);file_put_contents($dir.'/'.$name.'.html',$body);};
$save('cards',$editor->request('/succes')['body']);$save('list',$editor->request('/succes?view=list')['body']);$save('detail',$editor->request('/succes/visuel-guide-0')['body']);$save('no-guide',$editor->request('/succes/visuel-guide-2')['body']);
$editor->request('/progression',['csrf'=>$editor->token('/profil'),'achievement_id'=>$ids[0],'action'=>'obtained']);$save('obtained',$editor->request('/succes?progress=obtained')['body']);
$body=$editor->request('/admin/achievements/'.$ids[0].'/edit')['body'];$body=str_replace('<p>L’aperçu utilise le même rendu que la fiche membre.</p>',App\Services\GuideMarkdown::render($md,[$url]),$body);$save('editor',$body);
echo "Rendus inertes dans $dir\n";
