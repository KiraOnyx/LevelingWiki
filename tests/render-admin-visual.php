<?php
declare(strict_types=1);
// Inert, fixture-only HTML for owner-only screens. No database or authentication writes.
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='test'||getenv('DB_DATABASE')!=='levelingwiki_test')exit(1);
require dirname(__DIR__).'/app/bootstrap.php';require dirname(__DIR__).'/app/Views/helpers.php';
$view=$argv[1]??'';if(!in_array($view,['users','password'],true))exit(1);
$user=['id'=>0,'role'=>'super_admin','auth_provider'=>'local','username'=>'Propriétaire de test','is_active'=>1,'must_change_password'=>false];$csrf='inert-visual-fixture';$flash=$error=null;$_SERVER['REQUEST_URI']='/admin/'.$view;
$records=[['id'=>1,'display_name'=>'Pseudonyme de démonstration particulièrement long pour contrôler les retours à la ligne','username'=>'visual-owner','email'=>null,'role'=>'super_admin','auth_provider'=>'local','is_active'=>1,'discord_user_id'=>null],['id'=>2,'display_name'=>'Compte désactivé de démonstration','username'=>'visual-inactive','email'=>null,'role'=>'editor','auth_provider'=>'local','is_active'=>0,'discord_user_id'=>null]];
require dirname(__DIR__).'/app/Views/admin/layout.php';
