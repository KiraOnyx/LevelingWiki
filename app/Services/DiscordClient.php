<?php
declare(strict_types=1);
namespace App\Services;
final class DiscordClient
{
    public function __construct(private array $config) {}
    public function configured(): void
    {
        foreach (['client_id','guild_id'] as $key) if (!preg_match('/^[0-9]{17,20}$/D',$this->config[$key])) throw new ValidationException('Discord n’est pas encore configuré. Utilisez la connexion propriétaire.');
        if ($this->config['client_secret']==='') throw new ValidationException('Discord n’est pas encore configuré.');
        $uri=$this->config['redirect_uri'];
        if (!filter_var($uri,FILTER_VALIDATE_URL) || (parse_url($uri,PHP_URL_SCHEME)!=='https' && !in_array(parse_url($uri,PHP_URL_HOST),['localhost','127.0.0.1'],true))) throw new ValidationException('URL de callback Discord invalide.');
    }
    public function token(array $fields): array
    {
        $data=$this->request('/oauth2/token',null,[...$fields,'client_id'=>$this->config['client_id'],'client_secret'=>$this->config['client_secret']]);
        if (($data['token_type'] ?? '')!=='Bearer' || !is_string($data['access_token'] ?? null) || !is_string($data['refresh_token'] ?? null)
            || !is_int($data['expires_in'] ?? null) || $data['expires_in']<1 || !is_string($data['scope'] ?? null)
            || array_diff(['identify','guilds.members.read'],explode(' ',$data['scope']))) throw new ValidationException('Réponse OAuth2 invalide.');
        foreach (['access_token','refresh_token'] as $key) if (!preg_match('/^[\x21-\x7e]{1,4096}$/D',$data[$key])) throw new ValidationException('Token OAuth2 invalide.');
        return ['access'=>$data['access_token'],'refresh'=>$data['refresh_token'],'expires'=>time()+$data['expires_in']];
    }
    public function me(string $token): array { return $this->request('/v10/users/@me',$token); }
    public function member(string $token): array { return $this->request('/v10/users/@me/guilds/'.$this->config['guild_id'].'/member',$token); }
    public function revoke(string $token): void { try { $this->request('/oauth2/token/revoke',null,['token'=>$token,'client_id'=>$this->config['client_id'],'client_secret'=>$this->config['client_secret']]); } catch (\Throwable) {} }
    private function request(string $path,?string $token=null,?array $fields=null): array
    {
        $headers="Accept: application/json\r\nUser-Agent: LevelingWiki/4\r\n";
        if ($token!==null) {
            if (preg_match('/[\r\n]/',$token)) throw new ValidationException('Token invalide.');
            $headers.="Authorization: Bearer ".$token."\r\n";
        }
        if ($fields!==null) $headers.="Content-Type: application/x-www-form-urlencoded\r\n";
        $context=stream_context_create(['http'=>['method'=>$fields===null?'GET':'POST','header'=>$headers,
            'content'=>$fields===null?'':http_build_query($fields),'timeout'=>8,'ignore_errors'=>true,'follow_location'=>0],
            'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
        $body=@file_get_contents($this->config['api_base'].$path,false,$context,0,1048577);
        preg_match('/\s(\d{3})\s/',$http_response_header[0] ?? '',$status);
        if ($body===false || strlen($body)>1048576 || (int)($status[1]??0)<200 || (int)($status[1]??0)>=300) throw new ValidationException('Vérification Discord impossible ou accès non autorisé. Réessayez ou reconnectez-vous.');
        if ($body==='') return [];
        try { $data=json_decode($body,true,32,JSON_THROW_ON_ERROR); } catch (\Throwable) { throw new ValidationException('Réponse Discord invalide.'); }
        if (!is_array($data)) throw new ValidationException('Réponse Discord invalide.');
        return $data;
    }
}
