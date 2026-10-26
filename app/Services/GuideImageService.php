<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
final class GuideImageService
{
    public function __construct(private PDO $pdo){}
    public function images(int $id):array{$q=$this->pdo->prepare('SELECT path,alt_text FROM achievement_guide_images WHERE achievement_id=? ORDER BY id');$q->execute([$id]);return $q->fetchAll();}
    public function upload(array $data,?array $file,array $actor):array {
        if(!PermissionService::member($actor)||!PermissionService::edit($actor))throw new \RuntimeException('Accès refusé.',403);
        $id=Input::id($data['achievement_id']??null);$alt=Input::text($data,'alt',500,true);
        $uploads=new UploadService(true);$path=null;$this->pdo->beginTransaction();
        try {
            $q=$this->pdo->prepare('SELECT id FROM achievements WHERE id=? FOR UPDATE');$q->execute([$id]);
            if(!$q->fetchColumn())throw new ValidationException('Enregistrez d’abord le succès.');
            if(count($this->images($id))>=max(1,(int)(getenv('GUIDE_IMAGE_MAX_COUNT')?:30)))throw new ValidationException('Limite d’images atteinte pour ce succès.');
            $path=$uploads->store($file);if(!$path)throw new ValidationException('Sélectionnez une image.');
            $this->pdo->prepare('INSERT INTO achievement_guide_images (achievement_id,path,alt_text) VALUES (?,?,?)')->execute([$id,$path,$alt]);
            Audit::record($this->pdo,(int)$actor['id'],'achievement.guide_image','achievement',$id,null,['guide_image'=>$path]);
            $this->pdo->commit();return ['path'=>$path,'alt'=>$alt];
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();$uploads->remove($path);throw $e;}
    }
}
