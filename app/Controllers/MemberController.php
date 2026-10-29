<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\{Database,Auth,DiscordAuthService,PermissionService,PersonalService,Input,ValidationException,CatalogService,DiscordAccessException};
use App\Models\AchievementRepository;
final class MemberController {
    public function dispatch(string $path): void {
        try {
            $pdo=Database::connect();$auth=new Auth($pdo);$auth->start();$method=$_SERVER['REQUEST_METHOD']??'GET';
            if (!in_array($method,['GET','POST'],true)) {http_response_code(405);return;}
            if ((in_array($path,['/auth/discord','/deconnexion','/progression/import-local'],true) && $method!=='POST') || ($path==='/auth/discord/callback' && $method!=='GET')) {http_response_code(405);return;}
            if ($path==='/connexion') {self::render('login',['csrf'=>$auth->csrf()]);return;}
            if ($path==='/auth/discord/callback' && $method==='GET') {
                try {(new DiscordAuthService($pdo))->callback($_GET);header('Location: /profil',true,303);}
                catch (DiscordAccessException $e) {http_response_code($e->status());self::render('denied',['error'=>$e->getMessage(),'errorTitle'=>'Connexion Discord impossible','csrf'=>$auth->csrf()]);}
                catch (ValidationException $e) {http_response_code(403);self::render('denied',['error'=>$e->getMessage(),'csrf'=>$auth->csrf()]);}
                return;
            }
            if ($method==='POST' && !$auth->validCsrf($_POST['csrf']??null)) {http_response_code(403);self::render('denied',['error'=>'Formulaire expiré ou invalide.']);return;}
            if ($path==='/auth/discord' && $method==='POST') {header('Location: '.(new DiscordAuthService($pdo))->begin(),true,303);return;}
            if ($path==='/deconnexion' && $method==='POST') {(new DiscordAuthService($pdo))->revoke();if(isset($_SESSION['user_id']))\App\Services\Audit::record($pdo,(int)$_SESSION['user_id'],'logout','user',(int)$_SESSION['user_id']);$auth->logout();header('Location: /connexion',true,303);return;}
            $user=self::requireUser($auth,$method==='POST');if (!$user) return;
            $service=new PersonalService($pdo);
            if ($method==='POST') {
                if ($path==='/progression/import-local') {
                    if (($_POST['confirm']??'')!=='1') throw new ValidationException('Confirmation volontaire requise.');
                    $slugs=json_decode(Input::text($_POST,'slugs',100000,true),true,16,JSON_THROW_ON_ERROR);
                    if (!is_array($slugs) || !array_is_list($slugs)) throw new ValidationException('Liste invalide.');
                    $service->importLocal((int)$user['id'],$slugs);
                } elseif (in_array($path,['/progression'],true)) {
                    // L’identité cible vient exclusivement de la session, jamais d’un paramètre.
                    if (isset($_POST['user_id'])) throw new ValidationException('Paramètre utilisateur interdit.');
                    $action=Input::text($_POST,'action',30,true);
                    if ($path==='/progression' && !in_array($action,['not_started','obtained'],true)) throw new ValidationException('État invalide.');
                    if ($action==='not_started' && ($_POST['confirm']??'')!=='1') throw new ValidationException('Confirmez le retrait de votre déclaration.');
                    $service->act((int)$user['id'],Input::id($_POST['achievement_id']??null),$action);
                } else {http_response_code(404);return;}
                if ($path==='/progression' && ($_SERVER['HTTP_ACCEPT']??'')==='application/json') {
                    header('Content-Type: application/json');
                    $overview=$service->overview((int)$user['id']);
                    header('Cache-Control: no-store');
                    echo json_encode(['ok'=>true,'achievement_id'=>(int)$_POST['achievement_id'],'progress'=>$action,
                        'obtained_at'=>$overview['personalById'][(int)$_POST['achievement_id']]['obtained_at']??null,
                        'obtained'=>array_column(array_filter($overview['rows'],fn($r)=>$r['available']&&$r['progress']==='obtained'),'slug'),
                        'summary'=>$overview['summary'],'rewards'=>$overview['rewardsEstimate'],'categories'=>$overview['categoriesProgress']],JSON_THROW_ON_ERROR);return;
                }
                $_SESSION['member_flash']='Votre suivi a été enregistré.';
                $target='/succes?progress=obtained';
                header('Location: '.$target,true,303);return;
            }
            $data=$service->overview((int)$user['id']);
            $q=$pdo->prepare('SELECT * FROM discord_identities WHERE user_id=?');$q->execute([$user['id']]);$identity=$q->fetch();
            self::render($path==='/'?'home':'profile',$data+['user'=>$user,'identity'=>$identity,'csrf'=>$auth->csrf()]);
        } catch (DiscordAccessException $e) {http_response_code($e->status());self::render('denied',['error'=>$e->getMessage(),'errorTitle'=>'Connexion Discord impossible']);}
        catch (ValidationException $e) {http_response_code(422);self::render('denied',['error'=>$e->getMessage()]);}
        catch (\Throwable) {http_response_code(503);self::render('denied',['error'=>'Service momentanément indisponible. Aucun accès supplémentaire n’a été accordé.']);}
    }
    public static function requireUser(Auth $auth,bool $sensitive=false): ?array {
        $user=$auth->user($sensitive);
        if (!$user) {header('Location: /connexion'.($auth->expired?'?expired=1':''),true,303);return null;}
        if (!PermissionService::member($user)) {
            http_response_code(403);
            self::render('denied',['error'=>$user['role']==='legacy'?'Compte local conservé. La désignation explicite du propriétaire est nécessaire via la commande CLI.':DiscordAccessException::messageFor($_SESSION['discord_failure']??'roles'),'csrf'=>$auth->csrf()]);
            return null;
        }
        return $user;
    }
    public static function render(string $view,array $data=[]): void {
        require_once dirname(__DIR__).'/Views/helpers.php';
        $title='Espace membre — LevelingWiki';$page='member/'.$view;
        $flash=$_SESSION['member_flash']??null;unset($_SESSION['member_flash']);
        extract($data,EXTR_SKIP);$items=$items??[];
        require dirname(__DIR__).'/Views/layout.php';
    }
}
