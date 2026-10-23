<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';require __DIR__.'/http-client.php';
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='test'||getenv('DB_DATABASE')!=='levelingwiki_test')exit(1);
$client=new HttpTestClient();$old=getenv('OWNER_PREVIOUS_PATH');$new=App\Services\OwnerAccess::path();
if(!$old||!$new||$old===$new||$client->request($old)['status']!==404||$client->request($new)['status']!==200)throw new RuntimeException('Rotation HTTP propriétaire échouée.');
echo "OK : rotation HTTP réelle, ancien chemin 404 et nouveau formulaire accessible\n";
