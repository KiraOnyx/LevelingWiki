<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
final class DiscordAuthService
{
    private array $config;
    private DiscordClient $client;
    public function __construct(private PDO $pdo) {
        $this->config=require dirname(__DIR__,2).'/config/discord.php';
        $this->client=new DiscordClient($this->config);
    }
    public function begin(): string {
        $this->client->configured();
        $state=bin2hex(random_bytes(32));$verifier=bin2hex(random_bytes(32));
        $_SESSION['oauth']=['state'=>$state,'verifier'=>$verifier,'at'=>time()];
        return 'https://discord.com/oauth2/authorize?'.http_build_query([
            'client_id'=>$this->config['client_id'],'redirect_uri'=>$this->config['redirect_uri'],
            'response_type'=>'code','scope'=>'identify guilds.members.read','state'=>$state,
            'code_challenge'=>rtrim(strtr(base64_encode(hash('sha256',$verifier,true)),'+/','-_'),'='),
            'code_challenge_method'=>'S256',
        ]);
    }
    public function callback(array $query): array {
        $pending=$_SESSION['oauth'] ?? null;unset($_SESSION['oauth']);
        if (!$pending || time()-$pending['at']>600 || !is_string($query['state']??null) || !hash_equals($pending['state'],$query['state'])) throw new ValidationException('Connexion expirée ou state invalide.');
        if (isset($query['error']) || !is_string($query['code']??null) || strlen($query['code'])>2048 || (isset($query['iss']) && $query['iss']!=='https://discord.com')) throw new ValidationException('Autorisation Discord refusée ou invalide.');
        $this->client->configured();
        $tokens=$this->client->token(['grant_type'=>'authorization_code','code'=>$query['code'],'redirect_uri'=>$this->config['redirect_uri'],'code_verifier'=>$pending['verifier']]);
        $identity=$this->client->me($tokens['access']);
        $id=$identity['id'] ?? null;
        if (!is_string($id) || !preg_match('/^[0-9]{17,20}$/D',$id) || !is_string($identity['username']??null) || ($identity['bot']??false)) throw new ValidationException('Identité Discord invalide.');
        $display=Input::text(['name'=>$identity['global_name']??$identity['username']],'name',100,true);
        $avatar=$identity['avatar']??null;
        if ($avatar!==null && (!is_string($avatar) || !preg_match('/^(?:a_)?[a-f0-9]{32}$/D',$avatar))) throw new ValidationException('Avatar Discord invalide.');
        $version=$this->version();
        $role=$this->role($this->client->member($tokens['access']));
        if ($role==='denied') { $_SESSION['discord_denied']=true;throw new ValidationException('Accès refusé : un rôle MEMBER autorisé est nécessaire.'); }
        $this->pdo->beginTransaction();
        try {
            $q=$this->pdo->prepare('SELECT u.* FROM discord_identities d JOIN users u ON u.id=d.user_id WHERE d.discord_user_id=? FOR UPDATE');$q->execute([$id]);$user=$q->fetch();
            if (!$user) {
                // L’identifiant interne aléatoire ne rapproche jamais un pseudo Discord d’un compte local.
                $username='discord_'.bin2hex(random_bytes(12));
                $this->pdo->prepare("INSERT INTO users(username,password_hash,role,auth_provider) VALUES(?,? ,?,'discord')")->execute([$username,password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT),$role]);
                $userId=(int)$this->pdo->lastInsertId();
                $this->pdo->prepare('INSERT INTO discord_identities(user_id,discord_user_id,display_name,avatar_hash) VALUES(?,?,?,?)')->execute([$userId,$id,$display,$avatar]);
                Audit::record($this->pdo,$userId,'discord.account_create','user',$userId,null,['discord_user_id'=>$id,'role'=>$role]);
            } else {
                if (!$user['is_active'] || $user['auth_provider']!=='discord') throw new ValidationException('Compte indisponible.');
                $userId=(int)$user['id'];
                $this->pdo->prepare('UPDATE discord_identities SET display_name=?,avatar_hash=? WHERE user_id=?')->execute([$display,$avatar,$userId]);
                $this->pdo->prepare('UPDATE users SET role=? WHERE id=?')->execute([$role,$userId]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) { if ($this->pdo->inTransaction()) $this->pdo->rollBack();throw $e; }
        $q=$this->pdo->prepare('SELECT * FROM users WHERE id=?');$q->execute([$userId]);$user=$q->fetch();
        session_regenerate_id(true);
        $_SESSION=['user_id'=>$userId,'session_version'=>(int)$user['session_version'],'signed_in'=>time(),'last_seen'=>time(),'csrf'=>bin2hex(random_bytes(32)),
            'discord'=>['tokens'=>$tokens,'checked'=>time(),'version'=>$version,'role'=>$role,'guild'=>$this->config['guild_id'],'client'=>$this->config['client_id']]];
        Audit::record($this->pdo,$userId,'discord.login','user',$userId);
        return $user;
    }
    public function authorize(array $user,bool $force=false): string {
        $session=$_SESSION['discord']??null;
        if (!$session || $session['guild']!==$this->config['guild_id'] || $session['client']!==$this->config['client_id']) return 'denied';
        $version=$this->version();
        if (!$force && $session['version']===$version && time()-$session['checked']<$this->config['role_ttl']) return $session['role'];
        // Échec fermé : ne jamais réutiliser un ancien rôle après une erreur API.
        $_SESSION['discord']['role']='denied';$_SESSION['discord']['checked']=0;
        try {
            $tokens=$session['tokens'];
            if ($tokens['expires']<=time()+30) $tokens=$this->client->token(['grant_type'=>'refresh_token','refresh_token'=>$tokens['refresh']]);
            $_SESSION['discord']['tokens']=$tokens;
            $role=$this->role($this->client->member($tokens['access']));
            $_SESSION['discord']=[...$_SESSION['discord'],'checked'=>time(),'version'=>$version,'role'=>$role];
            if ($role!=='denied') $this->pdo->prepare('UPDATE users SET role=? WHERE id=? AND auth_provider=\'discord\'')->execute([$role,$user['id']]);
            if ($role!==$session['role']) Audit::record($this->pdo,(int)$user['id'],'discord.permissions','user',(int)$user['id'],['role'=>$session['role']],['role'=>$role]);
            return $role;
        } catch (\Throwable) { return 'denied'; }
    }
    public function role(array $member): string {
        if (!isset($member['roles']) || !is_array($member['roles']) || !array_is_list($member['roles']) || ($member['pending']??false)) return 'denied';
        foreach ($member['roles'] as $id) if (!is_string($id) || !preg_match('/^[0-9]{17,20}$/D',$id)) return 'denied';
        $allowed=['member'=>false,'editor'=>false,'admin'=>false];
        foreach ($this->pdo->query('SELECT level,discord_role_id FROM discord_roles') as $row) if (in_array($row['discord_role_id'],$member['roles'],true)) $allowed[$row['level']]=true;
        return !$allowed['member']?'denied':($allowed['admin']?'admin':($allowed['editor']?'editor':'member'));
    }
    public function version(): int { return (int)$this->pdo->query('SELECT version FROM authorization_policy WHERE id=1')->fetchColumn(); }
    public function revoke(): void { if (isset($_SESSION['discord']['tokens']['access'])) $this->client->revoke($_SESSION['discord']['tokens']['access']); }
}
