<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Models\AchievementRepository;
use App\Services\{Database, CatalogService, Auth, PersonalService};
use Throwable;

final class CatalogController
{
    public function show(?string $slug = null): void
    {
        $items = $categories = [];
        $item = null;
        $error = null;
        $detail = $slug !== null;
        try {
            $pdo=Database::connect();$auth=new Auth($pdo);
            $user=MemberController::requireUser($auth);
            if (!$user) return;
            $csrf=$auth->csrf();$overview=(new PersonalService($pdo))->overview((int)$user['id']);$personalRows=$overview['rows'];
            $repository = new AchievementRepository($pdo);
            $items = $repository->all();
            $categories = $repository->categories();
            if ($detail) {
                foreach ($items as $candidate) {
                    if ($candidate['slug'] === $slug) { $item = $candidate; break; }
                }
                if (!$item) { http_response_code(404); $error = 'Ce succès est introuvable.'; }
            }
        } catch (Throwable) {
            http_response_code(503);
            $error = 'Les archives sont momentanément indisponibles. Réessayez dans quelques instants.';
            error_log('LevelingWiki : catalogue indisponible.');
        }
        $personal=null;
        if ($item) foreach ($personalRows??[] as $row) if ((int)$row['achievement_id']===(int)$item['id']) $personal=$row;
        $personalById=array_column($personalRows??[],null,'achievement_id');
        $personalPage=!$detail;
        foreach($items as &$catalogItem)$catalogItem['obtained_at']=$personalById[$catalogItem['id']]['obtained_at']??null;unset($catalogItem);
        if(($_GET['sort']??(($_GET['progress']??'')==='obtained'?'obtained-desc':''))==='obtained-desc')usort($items,static fn($a,$b)=>strcmp($b['obtained_at']??'',$a['obtained_at']??'')?:strcmp($a['name'],$b['name']));
        $accountObtained=array_column(array_filter($personalRows??[],fn($r)=>$r['available'] && $r['progress']==='obtained'),'slug');
        $stats = CatalogService::stats($items);
        $title = $item ? $item['name'] . ' — LevelingWiki' : 'Archives des succès — LevelingWiki';
        $page = $error ? 'error' : ($detail ? 'detail' : 'catalog');
        if(isset($overview))extract($overview,EXTR_SKIP);
        require dirname(__DIR__) . '/Views/helpers.php';
        require dirname(__DIR__) . '/Views/layout.php';
    }

    public function notFound(): void
    {
        http_response_code(404);
        $title = 'Page introuvable — LevelingWiki';
        $error = 'Cette page est introuvable.';
        $items = [];
        $page = 'error';
        require dirname(__DIR__) . '/Views/helpers.php';
        require dirname(__DIR__) . '/Views/layout.php';
    }
}
