<?php
$leader = $divisions ? max(array_column($divisions, 'level')) : 0;
$isPrototype = $isPrototype ?? true;
$kindLabels = ['personal'=>'Personnelle', 'collective'=>'Collective', 'unconfirmed'=>'À préciser', 'unknown'=>'Inconnue'];
?>
<div class="guild-prototype" data-tower-prototype data-renderer="<?= e($renderer) ?>" data-atmosphere="auto" data-live="<?= $isPrototype?'false':'true' ?>">
    <?php if($isPrototype): ?><div class="tower-preview-note"><span>LABORATOIRE VISUEL</span> Prototype · progression fictive · aucune donnée enregistrée</div><?php else: ?><div class="tower-preview-note"><span>VALHALLA</span> Progression des divisions · niveaux déclarés par leurs responsables</div><?php endif; ?>
    <header class="tower-heading" id="tower-intro">
        <p class="eyebrow">VALHALLA / EXPLORATION</p>
        <h1>La Tour de guilde</h1>
        <p>Une ascension commune. Chaque division écrit sa légende.</p>
        <div class="tower-summary"><span><strong><?= count($divisions) ?></strong> divisions</span><span><strong><?= count($levels) ?></strong> paliers connus</span><span><strong><?= $leader ?></strong> niveau de tête</span></div>
    </header>
    <?php if(!$isPrototype && !$divisions): ?><p class="admin-notice">Aucune division active pour le moment. Les administrateurs peuvent créer les divisions depuis l’administration.</p><?php endif; ?>
    <?php if(!$isPrototype && App\Services\PermissionService::edit($user)): ?><p><a class="secondary-button" href="/admin/tour-de-guilde">Gérer les divisions</a></p><?php endif; ?>
    <div class="tower-layout">
        <aside class="tower-sidebar">
            <details class="tower-navigation" open>
                <summary><?= icon('filter') ?> Explorer la tour <span aria-hidden="true">⌃</span></summary>
                <div class="tower-render-controls">
                    <label for="tower-renderer">Rendu architectural</label>
                    <select id="tower-renderer"><option value="flat" <?= $renderer==='flat'?'selected':'' ?>>2D · originale</option><option value="relief" <?= $renderer==='relief'?'selected':'' ?>>2,5D · relief</option></select>
                    <label for="tower-atmosphere">Ambiance</label>
                    <select id="tower-atmosphere"><option value="auto">Automatique</option><option value="full">Complète · statique</option><option value="lite">Légère</option><option value="off">Sans décor</option></select>
                    <small data-render-info>Architecture SVG · interactions HTML</small>
                </div>
                <nav aria-label="Navigation de la tour">
                    <a href="#tower-top"><span>↑</span> Le sommet <small>Niv. <?= max(array_column($levels,'level')) ?></small></a>
                    <p>REJOINDRE UNE DIVISION</p>
                    <?php foreach ($divisions as $division): ?>
                    <a class="division-<?= e($division['color']) ?>" data-division-color="<?= e($division['accent']??'') ?>" href="#division-<?= e($division['id']) ?>"><i></i><?= e($division['name']) ?><small><?= $division['level'] ?></small></a>
                    <?php endforeach; ?>
                    <a href="#level-1"><span>↓</span> Le pied de la tour <small>Niv. 1</small></a>
                    <a href="#tower-intro"><span>↗</span> Présentation</a>
                </nav>
            </details>
            <section class="tower-ranking" aria-labelledby="ranking-title">
                <p class="tower-overline">LES FORCES DU VALHALLA</p><h2 id="ranking-title">Les divisions</h2>
                <p><?= $isPrototype?'Progressions de démonstration':'Progressions des divisions actives' ?></p>
                <ol><?php foreach ($divisions as $index=>$division): ?>
                    <li class="division-<?= e($division['color']) ?>" data-division-color="<?= e($division['accent']??'') ?>"><span class="division-rank"><?= $index+1 ?></span><a href="#division-<?= e($division['id']) ?>"><?= e($division['name']) ?><small><?= $leader === $division['level'] ? 'En tête' : ($leader-$division['level']).' niveaux derrière' ?></small></a><strong><?= $division['level'] ?></strong></li>
                <?php endforeach; ?></ol>
                <p class="tower-ranking-note">Chaque palier est débloqué indépendamment par chaque division.</p>
            </section>
            <?php if($isPrototype): ?><details class="tower-lab"><summary>Scénarios de test visuel</summary><label for="tower-scenario">Jeu de données fictives</label><select id="tower-scenario"><option value="default">Trois divisions · 43 / 37 / 24</option><option value="collision">Huit divisions · niveaux proches</option><option value="extended">Au-delà de 100 · niveau 135</option><option value="edges">Pied et sommet · 1 / 100</option></select><p>Ces essais restent dans cette page, sans sauvegarde.</p></details><?php endif; ?>
        </aside>
        <div class="tower-world">
            <div class="tower-atmosphere" aria-hidden="true"><div class="tower-atmosphere-frame"><div class="tower-distant-light"></div><div class="tower-mountains"></div><div class="tower-fog tower-fog-far"></div><div class="tower-fog tower-fog-near"></div></div></div>
            <div class="tower-sky" aria-hidden="true"><span>AU-DELÀ DES BRUMES</span></div>
            <div class="tower-stage">
                <?php require __DIR__.'/architecture.php'; require __DIR__.'/architecture-relief.php'; ?>
                <div id="tower-top" class="tower-crown" tabindex="-1"><svg viewBox="0 0 320 210" aria-hidden="true"><use data-architecture="crown" href="#tower-crown<?= $renderer==='relief'?'-relief':'' ?>"/></svg></div>
                <div class="tower-floors">
                    <?php foreach (array_reverse($levels) as $index=>$level): ?>
                    <section class="tower-floor <?= $index % 2 ? 'reward-left' : 'reward-right' ?>" data-level="<?= $level['level'] ?>" id="level-<?= $level['level'] ?>" tabindex="-1" aria-label="Palier de niveau <?= $level['level'] ?>">
                        <svg class="floor-architecture" viewBox="0 0 320 320" preserveAspectRatio="none" aria-hidden="true"><use data-architecture="floor" href="#tower-floor<?= $renderer==='relief'?'-relief':'' ?>"/></svg>
                        <div class="floor-medallion"><small>NIV.</small><strong><?= $level['level'] ?></strong></div>
                        <article class="tower-reward" data-kind="<?= e($level['kind']) ?>">
                            <div class="tower-reward-top"><span class="tower-reward-icon"><?= icon($level['icon']) ?></span><span class="tower-overline">PALIER <?= $level['level'] ?></span><span class="reward-availability"><?= icon('lock') ?></span></div>
                            <h2><?= e($level['label']) ?></h2>
                            <span class="tower-kind"><?= e($kindLabels[$level['kind']]) ?></span>
                            <?php if ($level['kind']==='unconfirmed'): ?><p>Catégorie de clé<?= $level['level']===30?' et distribution':'' ?> à confirmer.</p>
                            <?php elseif ($level['kind']==='unknown'): ?><p>Ce palier garde encore ses secrets.</p>
                            <?php elseif ($level['kind']==='collective'): ?><p>Capacité supplémentaire pour la division.</p>
                            <?php else: ?><p>Récompense individuelle, déclarative.</p><?php endif; ?>
                            <button type="button" class="tower-detail-button" data-reward-detail>Voir les divisions <?= icon('arrow') ?></button>
                            <div class="tower-unlocks" hidden></div>
                        </article>
                    </section>
                    <?php endforeach; ?>
                </div>
                <svg class="tower-connectors" aria-hidden="true"></svg>
                <div class="tower-markers" aria-label="Positions des divisions"></div>
                <div class="tower-foundation" aria-hidden="true"><svg viewBox="0 0 420 300"><use data-architecture="base" href="#tower-base<?= $renderer==='relief'?'-relief':'' ?>"/></svg></div>
            </div>
            <div class="tower-welcome"><span class="tower-overline">L’ASCENSION COMMENCE ICI</span><h2>Des fondations à la légende.</h2><p>Remontez pour découvrir les paliers.<br>Les divisions avancent, les récompenses se dévoilent.</p><?php if($divisions): ?><a class="tower-start" href="#division-<?= e($divisions[0]['id']) ?>">Rejoindre <?= e($divisions[0]['name']) ?> <?= icon('arrow') ?></a><?php endif; ?><small><?= $isPrototype?'Prototype visuel : aucune récupération n’est enregistrée.':'Les récompenses sont informatives. Le suivi individuel sera disponible dans une prochaine étape.' ?></small></div>
        </div>
    </div>
    <p class="tower-feedback" role="status" aria-live="polite"></p>
    <noscript><p>JavaScript permet le positionnement proportionnel des divisions et l’arrivée au pied de la tour. Les paliers et la comparaison restent consultables.</p></noscript>
</div>
<script type="application/json" id="tower-data"><?= json_encode($fixture, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
