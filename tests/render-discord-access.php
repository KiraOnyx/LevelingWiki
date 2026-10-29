<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';require __DIR__.'/discord-support.php';
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='test'||getenv('DB_DATABASE')!=='levelingwiki_test')exit(1);
$p=App\Services\Database::connect();seedDiscordRoles($p);
$admin=discordLogin('100000000000000991',['200000000000000001','200000000000000003']);
$editor=discordLogin('100000000000000993',['200000000000000002']);
$member=discordLogin('100000000000000992',['200000000000000001']);
$dir='/var/www/storage/discord-visual';@mkdir($dir,0700,true);
foreach(['editor-roles'=>[$editor,'/admin/settings/discord'],'roles'=>[$admin,'/admin/settings/discord'],'denied'=>[$member,'/admin'],'member'=>[$member,'/profil'],'expired'=>[new HttpTestClient(),'/connexion?expired=1'],'callback'=>[new HttpTestClient(),'/auth/discord/callback?state=invalid']] as $name=>[$client,$url]){file_put_contents($dir.'/'.$name.'.html',str_replace('<head>','<head><base href="/">',$client->request($url)['body']));}
echo "Rendus isolés créés.\n";
