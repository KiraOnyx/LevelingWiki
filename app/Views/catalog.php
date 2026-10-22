<section class="hero" aria-labelledby="catalog-title">
    <div class="hero-copy"><p class="eyebrow"><span></span> LE WIKI DE LEVELING FITNESS</p><h1 id="catalog-title">ARCHIVES<br>DES <span>SUCCÈS</span><span class="title-dot">.</span></h1><p class="hero-description">Découvrez les succès, leurs conditions de déblocage<br class="desktop-break"> et leurs récompenses dans Leveling Fitness.</p><a class="hero-link" href="#catalogue">Explorez les archives <?= icon('arrow') ?></a></div>
    <div class="hero-art" aria-hidden="true"><div class="orbit orbit-outer"></div><div class="orbit orbit-inner"></div><span class="orbit-spark spark-one">✦</span><span class="orbit-spark spark-two">✧</span><div class="medallion neu-flat"><?= icon('medal') ?></div><span class="art-caption">CHAQUE EFFORT COMPTE</span></div>
</section>
<section class="stats-grid" aria-label="Statistiques des archives">
    <?php foreach ([['medal','Succès référencés','total'], ['lock','Succès secrets','secret'], ['spark','Récompenses connues','gold'], ['check','Succès confirmés','confirmed']] as $index => [$symbol, $label, $tone]): ?>
    <div class="stat neu-flat"><span class="stat-symbol <?= e($tone) ?>"><?= icon($symbol) ?></span><div><strong><?= $stats[$index] ?></strong><span><?= e($label) ?></span></div><?php if ($index === 2): ?><span class="stat-help" title="Nombre de succès dont toutes les récompenses sont documentées" aria-label="Succès dont toutes les récompenses sont documentées">ⓘ</span><?php endif; ?></div>
    <?php endforeach; ?>
</section>
<?php require __DIR__ . '/partials/progress.php'; ?>
<section id="catalogue" class="catalogue" aria-labelledby="results-heading">
    <div class="section-heading"><div><p class="eyebrow">LE CODEX</p><h2 id="results-heading">Tous les succès<span class="result-pill" data-count><?= count($items) ?></span></h2></div><span class="section-note">Explorez. Accomplissez. Progressez.</span></div>
    <form id="catalog-filters" hidden>
        <label class="search-label" for="search">Rechercher dans les archives</label>
        <div class="search-box neu-pressed"><?= icon('search') ?><input id="search" name="q" type="search" placeholder="Rechercher un succès, une condition, une récompense…" autocomplete="off"><span class="search-hint" aria-hidden="true">RECHERCHE</span></div>
        <details class="filters-panel" open><summary><?= icon('filter') ?> Filtres <span>Affiner votre exploration</span></summary>
            <div class="filters-grid">
                <label>Type<select name="type"><option value="">Tous</option><option value="secret">Secrets</option><option value="normal">Classiques</option></select></label>
                <label>Rareté<select name="rarity"><option value="">Toutes</option><option value="commun">Commun</option><option value="rare">Rare</option><option value="epique">Épique</option><option value="legendaire">Légendaire</option><option value="unknown">Non renseignée</option></select></label>
                <label>Catégorie<select name="category"><option value="">Toutes les catégories</option><?php foreach ($categories as $category): ?><option value="<?= e($category['id']) ?>"><?= e($category['name']) ?></option><?php endforeach; ?></select></label>
                <label>Statut<select name="status"><option value="">Tous</option><option value="verified">Confirmé</option><option value="unverified">À confirmer</option></select></label>
                <label>Ma progression<select name="progress"><option value="">Tous les succès</option><option value="obtained">Obtenus</option><option value="remaining">Non obtenus</option></select></label>
            </div>
            <div class="filter-bottom"><fieldset><legend>Récompenses <span>(toutes celles cochées)</span></legend><div class="reward-filters"><?php foreach (['xp'=>'XP','gold'=>'Pièces','gems'=>'Gemmes','keys'=>'Clés','titles'=>'Titres'] as $value=>$label): ?><label class="reward-choice <?= e($value) ?>"><input type="checkbox" name="reward" value="<?= e($value) ?>"><span><?= e($label) ?></span></label><?php endforeach; ?></div></fieldset><button class="text-button" type="reset">↺ Réinitialiser</button></div>
        </details>
        <div class="results-toolbar"><p data-results aria-live="polite" aria-atomic="true"><?= count($items) ?> résultat<?= count($items) === 1 ? '' : 's' ?></p><label class="sort-label">Trier par <select name="sort"><option value="name-asc">Nom A–Z</option><option value="name-desc">Nom Z–A</option><option value="xp-desc">XP décroissante</option><option value="xp-asc">XP croissante</option><option value="gold-desc">Pièces décroissantes</option><option value="newest">Date d’ajout</option></select></label></div>
    </form>
    <noscript><p class="notice">Le catalogue reste consultable. Activez JavaScript pour rechercher, filtrer et suivre votre progression.</p></noscript>
    <div class="achievement-grid" data-grid><?php foreach ($items as $item) require __DIR__ . '/partials/card.php'; ?></div>
    <div class="empty-state neu-pressed" data-empty <?= $items ? 'hidden' : '' ?>><div class="empty-emblem"><?= icon('book') ?><span>✦</span></div><p class="eyebrow">L’AVENTURE SE PRÉPARE</p><h3 data-empty-title>Aucun succès référencé pour le moment.</h3><p data-empty-description>Les archives attendent leurs premières découvertes.<br>Revenez bientôt pour explorer les succès de Leveling Fitness.</p><div class="empty-divider"><span></span><?= icon('spark') ?><span></span></div><span class="empty-caption">DE GRANDES QUÊTES COMMENCENT PAR UNE PREMIÈRE PAGE.</span><button type="button" class="secondary-button" data-reset hidden>Réinitialiser les filtres</button></div>
</section>
