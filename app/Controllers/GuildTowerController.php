<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Models\GuildDivisionRepository;
use App\Services\{Database,Auth,PermissionService,GuildDivisionService,Input,ValidationException};
final class GuildTowerController
{
    public function show(): void {
        try {
            if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){http_response_code(405);header('Allow: GET');return;}
            $pdo=Database::connect();$auth=new Auth($pdo);$user=MemberController::requireUser($auth);if(!$user)return;
            $csrf=$auth->csrf();$repository=new GuildDivisionRepository($pdo);
            $divisions=array_map(static fn(array $d):array=>['id'=>'guild-'.$d['id'],'name'=>$d['name'],'short'=>$d['short_name'],'level'=>(int)$d['level'],'color'=>'custom','accent'=>$d['color']],$repository->all());
            $levels=(new \App\Models\GuildCatalogRepository($pdo))->publicLevels();
            $fixture=['levels'=>$levels,'divisions'=>$divisions];$isPrototype=false;
            $renderer=($_GET['view']??'')==='flat'?'flat':'relief';$title='Tour de guilde — LevelingWiki';$page='tower/prototype';$items=[];
            require_once dirname(__DIR__).'/Views/helpers.php';require dirname(__DIR__).'/Views/layout.php';
        }catch(\Throwable){http_response_code(503);MemberController::render('denied',['error'=>'La tour est momentanément indisponible. Vérifiez que les migrations ont été appliquées.']);}
    }
    public function admin(string $path): void {
        try {
            $pdo=Database::connect();$auth=new Auth($pdo);$user=MemberController::requireUser($auth,true);if(!$user)return;
            if(!PermissionService::edit($user)){http_response_code(403);MemberController::render('denied',['error'=>'Accès réservé aux administrateurs et éditeurs autorisés.']);return;}
            $repository=new GuildDivisionRepository($pdo);$service=new GuildDivisionService($pdo);$isAdmin=GuildDivisionService::admin($user);$csrf=$auth->csrf();
            $method=$_SERVER['REQUEST_METHOD']??'GET';$error=null;
            if(!in_array($method,['GET','POST'],true)){http_response_code(405);header('Allow: GET, POST');return;}
            if($method==='POST'){
                if(!in_array($path,['/admin/tour-de-guilde/save','/admin/tour-de-guilde/level'],true)){http_response_code(405);return;}
                if(!$auth->validCsrf($_POST['csrf']??null)){http_response_code(403);$error='Formulaire expiré ou invalide. Rechargez la page.';}
                else try {
                    $id=$service->save($_POST,$user,$path==='/admin/tour-de-guilde/level');
                    $_SESSION['guild_flash']='Division enregistrée. La tour reflète le nouveau niveau à son prochain chargement.';
                    header('Location: /admin/tour-de-guilde?division='.$id,true,303);return;
                }catch(ValidationException $e){http_response_code(422);$error=$e->getMessage();}
                catch(\RuntimeException $e){if(!in_array($e->getCode(),[403,404,409],true))throw $e;http_response_code($e->getCode());$error=$e->getMessage();}
            }elseif($path!=='/admin/tour-de-guilde'){http_response_code(404);return;}
            $divisions=$repository->all(true);$assigned=$repository->assignedIds((int)$user['id']);$selected=null;$history=[];$masterIds=[];
            $selection=$_GET['division']??($_POST['id']??'');
            if($selection!==''){
                try {$selected=$repository->find(Input::id($selection));}catch(ValidationException){http_response_code(422);$error='Division invalide.';}
                if($selected){
                    if($service->canManage($user,(int)$selected['id'])){
                        $before=isset($_GET['before'])?Input::id($_GET['before']):0;
                        $history=$repository->history((int)$selected['id'],$before);$masterIds=$repository->masterIds((int)$selected['id']);
                    }else{http_response_code(403);$error='Vous ne gérez pas cette division.';$selected=null;}
                }elseif(!$error){http_response_code(404);$error='Division introuvable.';}
            }
            $candidates=$isAdmin?$repository->candidates():[];
            $flash=$_SESSION['guild_flash']??null;unset($_SESSION['guild_flash']);$view='guild-divisions';
            require_once dirname(__DIR__).'/Views/helpers.php';require dirname(__DIR__).'/Views/admin/layout.php';
        }catch(ValidationException $e){http_response_code(422);MemberController::render('denied',['error'=>$e->getMessage()]);}
        catch(\Throwable){http_response_code(503);MemberController::render('denied',['error'=>'Gestion de la tour indisponible. Vérifiez les migrations puis réessayez.']);}
    }
}
