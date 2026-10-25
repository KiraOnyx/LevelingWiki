<?php $member=isset($user)&&App\Services\PermissionService::member($user);$currentPath=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH); ?>
<header class="site-header"><div class="header-inner">
<a class="brand" href="/" aria-label="LevelingWiki — Accueil"><span class="brand-mark"><?= icon('shield') ?></span><span>Leveling<span class="brand-light">Wiki</span><small>LE CODEX DU VALHALLA</small></span></a>
<?php if($member): ?>
<nav aria-label="Navigation principale"><?php foreach(['/'=>'Accueil','/succes'=>'Le Codex','/mes-succes'=>'Mes succès','/tour-de-guilde'=>'Tour de guilde','/profil'=>'Mon profil'] as $url=>$label): ?><a href="<?= $url ?>" <?= $currentPath===$url?'aria-current="page"':'' ?>><?= $label ?></a><?php endforeach; ?><?php if(App\Services\PermissionService::edit($user)): ?><a href="/admin" <?= str_starts_with($currentPath,'/admin')?'aria-current="page"':'' ?>>Administration</a><?php endif; ?></nav>
<a class="nav-identity" href="/profil" aria-label="<?= e('Mon profil : '.($user['display_name']??$user['username'])) ?>"><?php require __DIR__.'/avatar.php'; ?><span><?= e($user['display_name']??$user['username']) ?></span></a>
<form method="post" action="/deconnexion" class="nav-logout"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><button class="secondary-button">Déconnexion</button></form>

<?php endif; ?>
</div></header>
