<section class="admin-panel neu-flat guide-editor" data-guide-editor>
<h2>Guide de réalisation</h2><p class="form-help">Facultatif. Les conditions courtes restent séparées du guide. Le HTML est neutralisé ; seuls les liens HTTP(S) et les images associées sont affichés.</p>
<input type="hidden" name="guide_revision" value="<?= e($item['guide_revision']??hash('sha256',$item['guide_markdown']??'')) ?>">
<div class="guide-toolbar" role="group" aria-label="Mise en forme Markdown">
<?php foreach(['heading'=>'Titre','bold'=>'Gras','italic'=>'Italique','bullet'=>'Liste','number'=>'Étapes','link'=>'Lien','quote'=>'Citation'] as $command=>$label): ?><button type="button" class="secondary-button" data-markdown="<?= $command ?>"><?= $label ?></button><?php endforeach; ?>
</div><label for="guide-markdown">Contenu Markdown<textarea id="guide-markdown" name="guide_markdown" rows="14" maxlength="100000" spellcheck="true"><?= e($item['guide_markdown']??'') ?></textarea></label>
<div class="admin-actions"><button type="button" class="secondary-button" data-guide-preview>Actualiser l’aperçu</button><span class="form-help">Enregistré avec le succès.</span></div>
<p data-guide-feedback role="status" aria-live="polite"></p><section aria-label="Aperçu du guide" class="guide-prose guide-preview" data-guide-output><p>L’aperçu utilise le même rendu que la fiche membre.</p></section>
<details><summary>Ajouter une image / Images associées</summary>
<?php if(!$editing): ?><p>Enregistrez d’abord le succès pour lui associer des images. Votre guide peut être rédigé dès maintenant.</p><?php else: ?>
<label for="guide-alt">Texte alternatif de l’image<input id="guide-alt" disabled maxlength="500" data-guide-alt placeholder="Décrivez ce que montre la capture"></label>
<label for="guide-image">Fichier JPEG, PNG ou WebP<input id="guide-image" type="file" disabled accept="image/jpeg,image/png,image/webp" data-guide-file></label><p class="form-help">5 Mo par défaut, 20 mégapixels maximum. L’image est conservée même si vous annulez ensuite la rédaction.</p>
<button type="button" class="secondary-button" data-guide-upload disabled>Ajouter l’image au guide</button>
<ul class="guide-images" data-guide-images><?php foreach(($guideImages??[]) as $guideImage): ?><li><img src="<?= e($guideImage['path']) ?>" alt="<?= e($guideImage['alt_text']) ?>" loading="lazy"><button type="button" class="secondary-button" data-guide-insert="<?= e($guideImage['path']) ?>" data-alt="<?= e($guideImage['alt_text']) ?>">Insérer : <?= e($guideImage['alt_text']) ?></button></li><?php endforeach; ?></ul>
<?php endif; ?></details><div class="admin-actions"><button class="primary-button">Enregistrer le succès</button><a class="secondary-button" href="/admin/achievements">Annuler</a></div></section>
