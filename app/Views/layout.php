<?php
$illustratedNotFound = $page === 'error' && http_response_code() === 404;
$hasYggdrasil = in_array($page, ['member/home', 'catalog', 'member/achievements', 'member/profile'], true);
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <?php require __DIR__.'/partials/mobile-meta.php'; ?>
    <meta name="description" content="Explorez les succès de Leveling Fitness, leurs conditions de déblocage et leurs récompenses. Suivez votre progression personnelle.">
    <title><?= e($title) ?></title>
    <link rel="icon" href="/assets/images/emblem.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/vendor/neu-axentix/neu-axentix.min.css">
    <link rel="stylesheet" href="/assets/css/theme.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <link rel="stylesheet" href="/assets/css/valhalla.css?v=9.1">
    <script defer src="/assets/js/interface.js?v=9"></script>
    <script type="module" src="/assets/js/app.js?v=9"></script>
</head>
<body class="neu-dark lw-theme member-theme<?= in_array($page,['catalog','member/achievements'],true)?' collection-page':'' ?><?= $illustratedNotFound ? ' ginnungagap-page' : ($hasYggdrasil ? ' yggdrasil-page' : '') ?>">
<a class="skip-link" href="#main">Aller au contenu</a>
<?php require __DIR__.'/partials/navigation.php'; ?>
<main id="main" class="page-shell" tabindex="-1">
    <?php require __DIR__ . '/' . $page . '.php'; ?>
</main>
<footer class="site-footer"><a class="footer-brand" href="/">Leveling<span>Wiki</span></a><p>Un wiki par la communauté, pour la communauté.<br><span>Projet indépendant, non affilié à Leveling Fitness.</span></p><span class="footer-note"><span class="status-dot"></span> Chaque succès a son histoire.</span></footer>
<script id="catalog-data" type="application/json"><?= json_encode($items, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<?php if (isset($accountObtained)): ?><script id="account-progress" type="application/json"><?= json_encode(['obtained'=>$accountObtained,'csrf'=>$csrf,'states'=>array_column($personalRows??[],'progress','slug')],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script><?php endif; ?>
</body>
</html>
