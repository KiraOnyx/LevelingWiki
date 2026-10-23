<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
if (getenv('APP_ENV') !== 'test' || getenv('DB_DATABASE') !== 'levelingwiki_test') exit(1);
function verify(bool $result, string $label): void {
    if (!$result) throw new RuntimeException($label);
    echo "OK : $label\n";
}
$repository = new App\Models\AchievementRepository(App\Services\Database::connect());
$items = $repository->all();
verify(count($items) === 4, 'lecture des quatre fixtures');
verify(App\Services\CatalogService::stats($items) === [4, 1, 2, 2], 'statistiques calculées depuis les données');
$categoryNames = array_column($repository->categories(), 'name');
verify(in_array('Entraînement', $categoryNames, true) && in_array('Exploration', $categoryNames, true), 'catégories des fixtures conservées dans la base enrichie');
$bySlug = array_column($items, null, 'slug');
verify($bySlug['elan-du-matin-test']['titles'] === ['Éclaireur'], 'titres associés chargés');
verify(str_contains($bySlug['elan-du-matin-test']['unlock_conditions'], 'cinq séances'), 'conditions chargées');
verify($bySlug['archive-inconnue-test']['reward_xp'] === null, 'récompense inconnue préservée');
verify(App\Services\CatalogService::image('https://example.com/image.png') === null, 'image externe refusée');
verify(App\Services\CatalogService::image('/assets/images/../private.png') === null, 'traversée de chemin refusée');
verify(App\Services\CatalogService::image('/assets/images/capture.webp') !== null, 'capture locale acceptée');
require dirname(__DIR__) . '/app/Views/helpers.php';
verify(e('<script>"&') === '&lt;script&gt;&quot;&amp;', 'échappement HTML');
