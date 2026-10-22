<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0B100C">
    <meta name="description" content="Explorez les succès de Leveling Fitness, leurs conditions de déblocage et leurs récompenses. Suivez votre progression personnelle.">
    <title><?= e($title) ?></title>
    <link rel="icon" href="/assets/images/emblem.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/vendor/neu-axentix/neu-axentix.min.css">
    <link rel="stylesheet" href="/assets/css/theme.css">
    <script type="module" src="/assets/js/app.js"></script>
</head>
<body class="neu-dark lw-theme">
<a class="skip-link" href="#main">Aller au contenu</a>
<header class="site-header">
    <div class="header-inner">
        <a class="brand" href="/" aria-label="LevelingWiki — Accueil"><span class="brand-mark"><?= icon('medal') ?></span><span>Leveling<span class="brand-light">Wiki</span><small>LE CODEX COMMUNAUTAIRE</small></span></a>
        <nav aria-label="Navigation principale">
            <a href="/" <?= ($_SERVER['REQUEST_URI'] ?? '/') === '/' ? 'aria-current="page"' : '' ?>>Accueil</a>
            <a href="/succes" <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/succes') ? 'aria-current="page"' : '' ?>>Succès</a>
        </nav>
        <div class="admin-access"><button type="button" class="admin-button" disabled aria-describedby="admin-note"><?= icon('lock') ?><span>Administration</span></button><small id="admin-note">Connexion bientôt disponible</small></div>
    </div>
</header>
<main id="main" class="page-shell" tabindex="-1">
    <?php require __DIR__ . '/' . $page . '.php'; ?>
</main>
<footer class="site-footer"><a class="footer-brand" href="/">Leveling<span>Wiki</span></a><p>Un wiki par la communauté, pour la communauté.<br><span>Projet indépendant, non affilié à Leveling Fitness.</span></p><span class="footer-note"><span class="status-dot"></span> Chaque succès a son histoire.</span></footer>
<script id="catalog-data" type="application/json"><?= json_encode($items, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
</body>
</html>
