<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Models\GuildCatalogRepository;
use App\Services\{Database,Auth,GuildDivisionService,GuildCatalogService,Input,ValidationException};
final class GuildCatalogController
{
    public function admin(string $path):void{
        try{
            $pdo=Database::connect();$auth=new Auth($pdo);$user=MemberController::requireUser($auth,true);if(!$user)return;
            if(!\App\Services\PermissionService::edit($user)){http_response_code(403);MemberController::render('denied',['error'=>'Catalogue réservé aux éditeurs et administrateurs.']);return;}
            $method=$_SERVER['REQUEST_METHOD']??'GET';$base='/admin/tour-de-guilde/';
            $routes=['paliers'=>'milestone','types'=>'type','recompenses'=>'reward'];$suffix=substr($path,strlen($base));$deleting=str_ends_with($suffix,'/delete');$save=$deleting||str_ends_with($suffix,'/save');$section=$save?substr($suffix,0,$deleting?-7:-5):$suffix;
            if(($deleting&&$section!=='paliers')||!isset($routes[$section])||(!$save&&$section==='recompenses')){http_response_code(404);return;}
            if(($save&&$method!=='POST')||(!$save&&$method!=='GET')){http_response_code(405);header('Allow: '.($save?'POST':'GET'));return;}
            $entity=$routes[$section];$repo=new GuildCatalogRepository($pdo);$csrf=$auth->csrf();$error=null;$failedEntity=null;
            $filters=$save?$_POST:$_GET;$q=Input::text($filters,'q',160);$status=Input::choice(['status'=>$filters['status']??'active'],'status',['active','archived','all']);$sort=Input::choice(['sort'=>$filters['sort']??'asc'],'sort',['asc','desc']);
            $query=http_build_query(['q'=>$q,'status'=>$status,'sort'=>$sort]);
            if($save){
                if(!$auth->validCsrf($_POST['csrf']??null)){http_response_code(403);$error='Formulaire expiré ou invalide.';}
                else try{
                    if($deleting){(new GuildCatalogService($pdo))->deleteMilestone($_POST,$user);$_SESSION['guild_catalog_flash']='Palier supprimé définitivement.';header('Location: '.$base.'paliers?'.$query,true,303);return;}
                    $id=(new GuildCatalogService($pdo))->save($entity,$_POST,$user);
                    $_SESSION['guild_catalog_flash']='Catalogue enregistré.';
                    $target=$entity==='type'?'types':'paliers';$selected=$entity==='reward'?Input::id($_POST['milestone_id']):$id;
                    header('Location: '.$base.$target.'?'.$query.'&id='.$selected.'#catalog-editor',true,303);return;
                }catch(ValidationException $e){http_response_code(422);$error=$e->getMessage();}
                catch(\RuntimeException $e){if(!in_array($e->getCode(),[403,404,409],true))throw $e;http_response_code($e->getCode());$error=$e->getMessage();}
                $failedEntity=$entity;
            }
            $section=$entity==='type'?'types':'paliers';$selected=null;
            $selection=$save?($entity==='reward'?($_POST['milestone_id']??''):($_POST['id']??'')):($_GET['id']??'');
            if($selection!==''){$selected=$repo->find($section==='types'?'type':'milestone',Input::id($selection));if(!$selected){http_response_code(404);$error='Entrée introuvable.';}}
            $types=$repo->types();$visibleTypes=$repo->types($q,$status,$sort);$milestones=$repo->milestones($q,$status,$sort);
            $rewards=$selected&&$section==='paliers'?$repo->rewards((int)$selected['id']):[];
            $flash=$_SESSION['guild_catalog_flash']??null;unset($_SESSION['guild_catalog_flash']);$view='guild-catalog';
            require_once dirname(__DIR__).'/Views/helpers.php';require dirname(__DIR__).'/Views/admin/layout.php';
        }catch(ValidationException $e){http_response_code(422);MemberController::render('denied',['error'=>$e->getMessage()]);}
        catch(\Throwable){http_response_code(503);MemberController::render('denied',['error'=>'Catalogue indisponible. Vérifiez les migrations.']);}
    }
}
