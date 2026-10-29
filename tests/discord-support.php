<?php
declare(strict_types=1);
require_once __DIR__.'/http-client.php';
function mockDiscord(array $data): void {
    $ctx=stream_context_create(['http'=>['method'=>'POST','header'=>'Content-Type: application/json','content'=>json_encode($data),'timeout'=>5]]);
    if (file_get_contents('http://discord:8082/test/state',false,$ctx)===false) throw new RuntimeException('Mock Discord indisponible.');
}
function seedDiscordRoles(PDO $pdo): void {
    foreach (['member'=>'200000000000000001','editor'=>'200000000000000002','admin'=>'200000000000000003'] as $level=>$role) $pdo->prepare('INSERT IGNORE INTO discord_roles(level,discord_role_id,label) VALUES(?,?,?)')->execute([$level,$role,'Test '.$level]);
    $pdo->exec('UPDATE authorization_policy SET version=version+1 WHERE id=1');
}
function discordFlow(HttpTestClient $client,string $id,array $roles,array $extra=[]): array {
    mockDiscord(['user'=>['id'=>$id,'roles'=>$roles,...$extra]]);
    $start=$client->request('/auth/discord',['csrf'=>$client->token('/connexion')]);
    parse_str(parse_url($start['location'],PHP_URL_QUERY)??'',$params);
    if (($params['code_challenge_method']??'')!=='S256' || ($params['scope']??'')!=='identify guilds.members.read') throw new RuntimeException('OAuth scopes/PKCE manquants.');
    $code=bin2hex(random_bytes(16));mockDiscord(['code'=>['value'=>$code,'id'=>$id,'challenge'=>$params['code_challenge']]]);
    return $client->request('/auth/discord/callback?'.http_build_query(['state'=>$params['state'],'code'=>$code,'iss'=>'https://discord.com']));
}
function discordLogin(string $id,array $roles): HttpTestClient {
    $client=new HttpTestClient();$response=discordFlow($client,$id,$roles);
    if ($response['status']!==303) throw new RuntimeException('Connexion Discord simulée échouée : '.$response['body']);
    return $client;
}

function expireDiscordCache(HttpTestClient $client):void {
    $code='session_id(trim(stream_get_contents(STDIN)));session_start();$_SESSION["discord"]["checked"]=time()-86400;session_write_close();';
    $proc=proc_open([PHP_BINARY,'-r',$code],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],$client->cookies['lw_session']);fclose($pipes[0]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($proc)!==0)throw new RuntimeException('Expiration cache test');
}
