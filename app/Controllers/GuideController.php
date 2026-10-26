<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\{Database,Auth,PermissionService,Input,GuideImageService,GuideMarkdown,UploadService,ValidationException};
final class GuideController
{
    public function dispatch(string $path):void {
        try {
            $pdo=Database::connect();$auth=new Auth($pdo);$user=$auth->user(true);
            if(!$user||!PermissionService::member($user)){http_response_code(403);return;}
            $images=new GuideImageService($pdo);
            if(str_starts_with($path,'/guide-media/')){
                if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){http_response_code(405);return;}
                $q=$pdo->prepare('SELECT a.publication_status,a.archived_at FROM achievement_guide_images i JOIN achievements a ON a.id=i.achievement_id WHERE i.path=?');$q->execute([$path]);$item=$q->fetch();
                if(!$item||(!PermissionService::edit($user)&&($item['publication_status']!=='published'||$item['archived_at']!==null))){http_response_code(404);return;}
                $file=(new UploadService(true))->resolve($path);if(!$file){http_response_code(404);return;}
                header('Content-Type: '.['jpg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'][pathinfo($file,PATHINFO_EXTENSION)]);
                header('Content-Length: '.filesize($file));header("Content-Security-Policy: default-src 'none'; sandbox");readfile($file);return;
            }
            header('Content-Type: application/json; charset=utf-8');
            if(!PermissionService::edit($user)){http_response_code(403);echo json_encode(['message'=>'Accès refusé.']);return;}
            if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){http_response_code(405);return;}
            if(!$auth->validCsrf($_POST['csrf']??null)){http_response_code(403);echo json_encode(['message'=>'Formulaire expiré. Rechargez la page.']);return;}
            if($path==='/admin/achievements/guide-image')$result=$images->upload($_POST,is_array($_FILES['image']??null)?$_FILES['image']:null,$user);
            else {$id=empty($_POST['achievement_id'])?0:Input::id($_POST['achievement_id']);$result=['html'=>GuideMarkdown::render(Input::text($_POST,'markdown',100000),$id?array_column($images->images($id),'path'):[])];}
            echo json_encode(['ok'=>true,...$result],JSON_THROW_ON_ERROR);
        }catch(ValidationException $e){http_response_code(422);echo json_encode(['message'=>$e->getMessage()]);}
        catch(\Throwable){http_response_code(503);echo json_encode(['message'=>'Service momentanément indisponible.']);}
    }
}
