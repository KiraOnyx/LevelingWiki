<?php $isObtained=($personal['progress']??'not_started')==='obtained'; ?>
<article class="achievement-card neu-flat <?= $isObtained?'is-obtained':'' ?>" data-card="<?= e($item['slug']) ?>" <?= !empty($personalPage)&&(($collectionFilter==='obtained')!==$isObtained)?'hidden':'' ?>>
<div class="card-top"><div class="achievement-icon"><?= icon($isObtained?'check':'shield') ?></div><div class="card-title"><p class="card-category"><?php if(preg_match('/^#[a-f0-9]{6}$/iD',$item['category_color']??'')): ?><svg width="9" height="9" aria-hidden="true"><circle cx="4" cy="4" r="4" fill="<?= e($item['category_color']) ?>"/></svg><?php endif; ?> <?= e($item['category']??'Non classé') ?></p><h3><a class="card-link" href="/succes/<?= e($item['slug']) ?>"><?= e($item['name']) ?></a></h3></div><?php if($item['secret_type']==='secret'): ?><span class="secret-badge">SECRET</span><?php endif; ?></div>
<p class="card-description"><?= e(!empty($personalPage)?($item['unlock_conditions']?:$item['description']):$item['description']) ?></p>
<div class="card-meta"><span class="personal-badge" data-personal-badge><?= $isObtained?'✓ Obtenu':'À débloquer' ?></span><?php if($item['rarity']): ?><span><?= e($item['rarity']) ?></span><?php endif; ?><span class="verification"><?= $item['verification_status']==='verified'?'Confirmé':'À confirmer' ?></span></div>
<?php require __DIR__.'/rewards.php'; ?>
<?php if(!empty($personalPage)&&$isObtained&&$personal['obtained_at']): ?><p class="declaration-date">Déclaré obtenu le <time datetime="<?= e(str_replace(' ','T',$personal['obtained_at']).'Z') ?>"><?= e((new DateTimeImmutable($personal['obtained_at']))->format('d/m/Y à H:i')) ?> UTC</time></p><?php endif; ?>
<div class="card-bottom"><span class="card-open"><?= !empty($personalPage)?($isObtained?'Voir le succès':'Voir le guide'):'Consulter' ?> <?= icon('arrow') ?></span></div>
<?php if(!empty($personalPage))require dirname(__DIR__).'/member/progress-form.php'; ?>
</article>
