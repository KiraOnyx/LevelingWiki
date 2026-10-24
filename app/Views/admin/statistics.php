<section class="admin-stats" aria-label="Statistiques administratives">
<?php foreach (['Succès au total','Publiés','Brouillons','Succès secrets','Récompenses à confirmer','Catégories actives','Titres'] as $i=>$label): ?>
<div class="stat neu-flat"><div><strong data-admin-stat="<?= $i ?>"><?= $stats[$i] ?></strong><span><?= e($label) ?></span></div></div>
<?php endforeach; ?>
</section>
<p class="form-help">Total des succès : archives incluses. Autres compteurs de succès et catégories : hors archives. Titres : tous les titres enregistrés.</p>
