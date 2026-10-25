<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
require __DIR__.'/http-client.php';
if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'test' || getenv('DB_DATABASE') !== 'levelingwiki_test') exit(1);
function artCheck(bool $ok,string $label): void {if(!$ok)throw new RuntimeException($label);echo "OK : $label\n";}
$client=new HttpTestClient();
$r=$client->request('/illustration-introuvable');
artCheck($r['status']===404 && str_contains($r['body'],'404 — Perdu dans le Ginnungagap') && str_contains($r['body'],'Même les plus grands chasseurs peuvent s’égarer dans le néant.') && str_contains($r['body'],'href="/">Retourner au Valhalla'),'404 illustrée, texte et retour accueil');
artCheck(!str_contains($r['body'],'yggdrasil-page'),'pas de Yggdrasil sur 404');
foreach(['ginnungagap-404','valhalla-gates','yggdrasil-background'] as $asset){
 $r=$client->request('/assets/illustrations/valhalla/'.$asset.'.webp');
 artCheck($r['status']===200 && str_contains(implode(' ',$r['headers']),'image/webp') && substr($r['body'],8,4)==='WEBP','illustration WebP servie : '.$asset);
}
$r=$client->request('/connexion');artCheck($r['status']===200&&!str_contains($r['body'],'yggdrasil-page')&&!str_contains($r['body'],'ginnungagap-page'),'connexion sans décor conflictuel');
$credentials=json_decode(file_get_contents('/tmp/levelingwiki-test-admin.json'),true);
artCheck($client->login($credentials['username'],$credentials['password'])['status']===303,'session isolée pour inspection des vues');
foreach(['/','/succes','/succes','/profil'] as $route){$r=$client->request($route);artCheck($r['status']===200&&(bool)preg_match('/<body class="[^"]*member-theme[^"]*yggdrasil-page/', $r['body']),'décor membre limité : '.$route);}
$r=$client->request('/');artCheck(str_contains($r['body'],'Bienvenue, ')&&str_contains($r['body'],'Explorer le Codex')&&str_contains($r['body'],'Voir les succès manquants')&&str_contains($r['body'],'Votre ascension'),'bannière et progression conservées');
$r=$client->request('/admin');artCheck($r['status']===200&&!str_contains($r['body'],'yggdrasil-page'),'administration inchangée');
$r=$client->request('/progression',['action'=>'obtained']);artCheck($r['status']===403&&!str_contains($r['body'],'ginnungagap-page'),'erreur CSRF conserve son statut et sa vue');
// Verify the existing unavailable-service view without altering a database or service.
http_response_code(503);$error='Indisponibilité de test';require_once dirname(__DIR__).'/app/Views/helpers.php';ob_start();require dirname(__DIR__).'/app/Views/error.php';$html=ob_get_clean();
artCheck(http_response_code()===503&&str_contains($html,'Un instant de repos')&&!str_contains($html,'ginnungagap'),'503 conserve sa présentation');
