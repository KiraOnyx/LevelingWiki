<?php
$visibleCount = count($items);
if (!empty($personalPage)) {
    $visibleCount = count(array_filter($items, static function (array $item) use ($personalById, $collectionFilter): bool {
        $obtained = ($personalById[$item['id']]['progress'] ?? 'not_started') === 'obtained';
        return ($collectionFilter === 'obtained') === $obtained;
    }));
}
$emptyTitle = !$items ? 'Aucun succès publié pour le moment.' : 'Aucun exploit ne correspond à votre recherche.';
$emptyDescription = 'Ajustez vos filtres ou explorez une autre catégorie.';
if (!empty($personalPage) && $collectionFilter === 'obtained' && $visibleCount === 0) {
    $emptyTitle = 'Votre légende commence ici.';
    $emptyDescription = 'Explorez le Codex pour découvrir vos premiers exploits.';
}
?>
<section id="catalogue" class="catalogue" aria-labelledby="results-heading">
    <div class="section-heading"><div><p class="eyebrow">LE CODEX</p><h2 id="results-heading"><?= !empty($personalPage)?'Votre collection':'Tous les succès' ?><span class="result-pill" data-count><?= $visibleCount ?></span></h2></div><?php if(empty($personalPage)): ?><span class="collection-status"><span data-summary="obtained"><?= $summary['obtained'] ?></span> / <span data-summary="total"><?= $summary['total'] ?></span> obtenus · <span data-summary-percent><?= e(str_replace('.',',',(string)$summary['percent'])) ?> %</span></span><?php endif; ?></div>
    <div class="catalog-layout">
    <form id="catalog-filters" hidden><input type="hidden" name="view" value="<?= e($displayMode??'cards') ?>">
        <details class="filters-panel" open><summary><?= icon('filter') ?> Filtres <span>Affiner votre exploration</span></summary>
            <div class="filters-grid">
                <label class="collection-search" for="search">Rechercher <?= !empty($personalPage)?'dans ma collection':'dans le Codex' ?><input id="search" name="q" type="search" maxlength="200" placeholder="Un succès, une condition…" value="<?= e(is_string($_GET['q']??null)?$_GET['q']:'') ?>"></label>
                <label>Type<select name="type"><option value="">Tous</option><option value="secret">Secrets</option><option value="normal">Classiques</option></select></label>
                <label>Rareté<select name="rarity"><option value="">Toutes</option><option value="commun">Commun</option><option value="rare">Rare</option><option value="epique">Épique</option><option value="legendaire">Légendaire</option><option value="unknown">Non renseignée</option></select></label>
                <label>Catégorie<select name="category"><option value="">Toutes les catégories</option><?php foreach ($categories as $category): ?><option value="<?= e($category['id']) ?>"><?= e($category['name']) ?></option><?php endforeach; ?></select></label>
                <label>Statut<select name="status"><option value="">Tous</option><option value="verified">Confirmé</option><option value="unverified">À confirmer</option></select></label>
                <label <?= !empty($personalPage)?'hidden':'' ?>>Ma progression<select name="progress"><option value="">Tous les succès</option><option value="obtained">Obtenus</option><option value="remaining">Non obtenus</option></select></label>
            </div>
            <div class="filter-bottom"><fieldset><legend>Récompenses <span>(toutes celles cochées)</span></legend><div class="reward-filters"><?php foreach ([...array_map(static fn($r)=>$r['label'],App\Services\CatalogService::resources()),'titles'=>'Titres','unknown'=>'À confirmer'] as $value=>$label): ?><label class="reward-choice <?= e($value) ?>"><input type="checkbox" name="reward" value="<?= e($value) ?>"><span><?= e($label) ?></span></label><?php endforeach; ?></div></fieldset><button class="text-button" type="reset">↺ Réinitialiser</button></div>
        </details>
        <div class="results-toolbar"><p data-results aria-live="polite" aria-atomic="true"><?= $visibleCount ?> résultat<?= $visibleCount === 1 ? '' : 's' ?></p><label class="sort-label">Trier par <select name="sort"><option value="name-asc">Nom A–Z</option><option value="name-desc">Nom Z–A</option><?php foreach(App\Services\CatalogService::resources() as $resourceKey=>$resource): ?><option value="<?= e($resourceKey) ?>-desc"><?= e($resource['label']) ?> · décroissant</option><?php endforeach; ?><option value="xp-asc">XP croissante</option><option value="newest">Date d’ajout</option><option value="obtained-desc">Date d’obtention · récente</option></select></label></div>
    </form>
    <noscript><p class="notice">Le catalogue reste consultable. Activez JavaScript pour la recherche et les filtres dynamiques. Les déclarations de progression restent disponibles.</p></noscript>
    <div class="achievement-grid" data-grid data-display="<?= e($displayMode??'cards') ?>"><?php foreach ($items as $item): $personal=$personalById[$item['id']]??null; require __DIR__ . '/card.php'; endforeach; ?></div>
    <div class="empty-state neu-pressed" data-empty <?= $visibleCount ? 'hidden' : '' ?>><div class="empty-emblem"><?= icon('book') ?><span>✦</span></div><p class="eyebrow">L’AVENTURE SE PRÉPARE</p><h3 data-empty-title><?= e($emptyTitle) ?></h3><p data-empty-description><?= e($emptyDescription) ?></p><div class="empty-divider"><span></span><?= icon('spark') ?><span></span></div><span class="empty-caption">DE GRANDES QUÊTES COMMENCENT PAR UNE PREMIÈRE PAGE.</span><button type="button" class="secondary-button" data-reset hidden>Réinitialiser les filtres</button></div>
    </div>
</section>
