<?php if (http_response_code() === 404): ?>
<section class="ginnungagap" aria-labelledby="ginnungagap-title">
    <div class="ginnungagap-content">
        <h1 id="ginnungagap-title">404 — Perdu dans le Ginnungagap</h1>
        <p>Même les plus grands chasseurs peuvent s’égarer dans le néant.</p>
        <a class="primary-button" href="/">Retourner au Valhalla</a>
    </div>
</section>
<?php else: ?>
<section class="error-state empty-state"><div class="empty-emblem"><?= icon('book') ?></div><p class="eyebrow">LES ARCHIVES</p><h1><?= e(http_response_code() === 404 ? 'Page introuvable' : 'Un instant de repos') ?></h1><p><?= e($error) ?></p><a class="secondary-button" href="/succes">Retour au catalogue</a></section>

<?php endif; ?>
