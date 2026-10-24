<?php $collectionFilter=($_GET['progress']??'')==='remaining'?'remaining':'obtained'; ?>
<div class="page-heading"><p class="eyebrow">VOTRE COLLECTION PERSONNELLE</p><h1>Mes succès</h1><p>Gérez votre collection personnelle, déclarez vos exploits et retrouvez leurs guides.</p></div>
<div data-personal-dashboard class="collection-summary"><?php require dirname(__DIR__).'/partials/ascension.php'; ?></div>
<p class="form-help"><a href="/#domaines">Voir mes récompenses et ma progression par catégorie →</a></p>
<nav class="collection-tabs" aria-label="Ma collection"><a href="/mes-succes?progress=obtained" data-collection-tab="obtained" <?= $collectionFilter==='obtained'?'aria-current="page"':'' ?>>Obtenus <span data-summary="obtained"><?= $summary['obtained'] ?></span></a><a href="/mes-succes?progress=remaining" data-collection-tab="remaining" <?= $collectionFilter==='remaining'?'aria-current="page"':'' ?>>À débloquer <span data-summary="remaining"><?= $summary['remaining'] ?></span></a></nav>
<?php require dirname(__DIR__).'/partials/catalog-results.php'; ?>
