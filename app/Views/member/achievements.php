<?php $collectionFilter=($_GET['progress']??'')==='remaining'?'remaining':'obtained'; ?>
<div class="page-heading"><p class="eyebrow">VOTRE COLLECTION PERSONNELLE</p><h1>Mes succès</h1><p>Vos exploits obtenus et ceux qui restent à découvrir.</p></div>
<div data-personal-dashboard><?php require dirname(__DIR__).'/partials/ascension.php'; require dirname(__DIR__).'/partials/estimates.php'; ?></div>
<nav class="collection-tabs" aria-label="Ma collection"><a href="/mes-succes?progress=obtained" data-collection-tab="obtained" <?= $collectionFilter==='obtained'?'aria-current="page"':'' ?>>Obtenus <span><?= $summary['obtained'] ?></span></a><a href="/mes-succes?progress=remaining" data-collection-tab="remaining" <?= $collectionFilter==='remaining'?'aria-current="page"':'' ?>>À débloquer <span><?= $summary['remaining'] ?></span></a></nav>
<?php require dirname(__DIR__).'/partials/catalog-results.php'; require dirname(__DIR__).'/partials/category-progress.php'; ?>
