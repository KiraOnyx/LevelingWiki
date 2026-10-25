<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$controller = new App\Controllers\TowerPrototypeController();
function check(bool $condition,string $label): void {if(!$condition)throw new RuntimeException($label);echo "OK $label\n";}
// Exercise only the prototype controller. This test does not open a database connection.
function response(string $environment,string $method): array {
    global $controller;
    putenv('APP_ENV='.$environment);$_SERVER['REQUEST_METHOD']=$method;
    $_SERVER['REQUEST_URI']='/prototype/tour-de-guilde';http_response_code(200);
    ob_start();$controller->show();$body=ob_get_clean();return [http_response_code(),$body];
}
ob_start();
[$code,$body]=response('test','GET');
check($code===200,'prototype disponible en test');
check(substr_count($body,'data-level=')===12,'douze paliers de démonstration');
check(str_contains($body,'Catégorie de clé et distribution'),'clés non classées arbitrairement');
check(!str_contains($body,'Marquer comme récupérée'),'aucune fausse action de récupération');
check(str_contains($body,'data-renderer="flat"'),'rendu 2D conservé par défaut');
$_GET=['view'=>'relief'];
[$code,$body]=response('test','GET');
check($code===200 && str_contains($body,'data-renderer="relief"'),'variante 2,5D accessible');
check(substr_count($body,'data-level=')===12,'mêmes paliers dans les deux variantes');
check(str_contains($body,'data-architecture="floor" href="#tower-floor-relief"'),'modules relief sélectionnés côté serveur');
$_GET=['view'=>['relief']];
[$code,$body]=response('test','GET');
check($code===200 && str_contains($body,'data-renderer="flat"'),'paramètre inattendu : retour sûr à la 2D');
$_GET=[];
[$code]=response('production','GET');check($code===404,'prototype absent en production');
[$code]=response('','GET');check($code===404,'configuration absente : accès refusé');
[$code]=response('development','POST');check($code===405,'mutations refusées');
ob_end_flush();
