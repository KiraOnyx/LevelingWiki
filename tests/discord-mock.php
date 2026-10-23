<?php
declare(strict_types=1);
if (getenv('APP_ENV')!=='test' || getenv('DB_DATABASE')!=='levelingwiki_test') {http_response_code(404);exit;}
header('Content-Type: application/json');
$file='/tmp/discord-state.json';$state=is_file($file)?json_decode(file_get_contents($file),true):['users'=>[],'codes'=>[],'tokens'=>[],'outage'=>false];
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
$save=static function() use(&$state,$file){file_put_contents($file,json_encode($state));};
$fail=static function(int $code){http_response_code($code);echo '{"error":"mock_error"}';exit;};
if ($path==='/test/state') {
    $input=json_decode(file_get_contents('php://input'),true);
    if (isset($input['user'])) $state['users'][$input['user']['id']]=$input['user'];
    if (isset($input['code'])) $state['codes'][$input['code']['value']]=$input['code'];
    if (array_key_exists('outage',$input)) $state['outage']=$input['outage'];
    $save();echo '{}';exit;
}
if ($state['outage']) $fail(503);
if ($path==='/oauth2/token') {
    if (($_POST['client_secret']??'')!=='isolated-test-only') $fail(401);
    if (($_POST['grant_type']??'')==='authorization_code') {
        $code=$state['codes'][$_POST['code']??'']??null;
        if (!$code || !hash_equals($code['challenge'],rtrim(strtr(base64_encode(hash('sha256',$_POST['code_verifier']??'',true)),'+/','-_'),'='))) $fail(400);
        $id=$code['id'];unset($state['codes'][$_POST['code']]);
    } else {$id=$state['tokens'][$_POST['refresh_token']??'']??null;if (!$id) $fail(401);}
    $access=bin2hex(random_bytes(24));$refresh=bin2hex(random_bytes(24));$state['tokens'][$access]=$id;$state['tokens'][$refresh]=$id;$save();
    echo json_encode(['token_type'=>'Bearer','access_token'=>$access,'refresh_token'=>$refresh,'expires_in'=>3600,'scope'=>'identify guilds.members.read']);exit;
}
if ($path==='/oauth2/token/revoke') {unset($state['tokens'][$_POST['token']??'']);$save();echo '{}';exit;}
$token=substr($_SERVER['HTTP_AUTHORIZATION']??'',7);$id=$state['tokens'][$token]??null;$user=$state['users'][$id]??null;
if (!$user || ($user['invalid_token']??false)) $fail(401);
if ($path==='/v10/users/@me') {echo json_encode(['id'=>(string)$id,'username'=>$user['name']??'Test Discord','global_name'=>$user['name']??'Test Discord','avatar'=>null]);exit;}
if ($path==='/v10/users/@me/guilds/400000000000000001/member') {
    if ($user['absent']??false) $fail(404);
    echo json_encode(['roles'=>$user['roles'],'pending'=>$user['pending']??false]);exit;
}
$fail(404);
