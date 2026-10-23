<section class="personal-progress neu-flat" aria-labelledby="progress-heading" data-progress hidden>
    <div class="progress-icon"><?= icon('medal') ?></div>
    <div class="progress-content"><div class="progress-heading"><h2 id="progress-heading">Votre aventure</h2><span><strong data-obtained-count>0</strong> / <?= count($items) ?> succès obtenus <b data-progress-percent>0 %</b></span></div>
    <progress max="100" value="0" aria-label="Progression personnelle"></progress>
    <p><label>Suivi affiché <select data-progress-mode><option value="account">Mon compte</option><option value="local">Ce navigateur uniquement</option></select></label> Le suivi local reste disponible et peut être importé volontairement depuis le profil.</p></div>
</section>
<p class="storage-notice" data-storage-notice role="status" hidden></p>
