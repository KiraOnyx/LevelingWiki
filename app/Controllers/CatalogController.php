<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Models\AchievementRepository;
use App\Services\{Database, CatalogService};
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
            $repository = new AchievementRepository(Database::connect());
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
        $stats = CatalogService::stats($items);
        $title = $item ? $item['name'] . ' — LevelingWiki' : 'Archives des succès — LevelingWiki';
        $page = $error ? 'error' : ($detail ? 'detail' : 'catalog');
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
