<?php $isObtained=($personal['progress']??'not_started')==='obtained';
$excerpt=static function(string $text):string{preg_match('/^.{0,280}/us',$text,$match);return $match[0].(strlen($match[0])<strlen($text)?'…':'');}; ?>
<article class="achievement-card neu-flat category-<?= e(App\Services\CategoryIdentity::tone($item['category_slug']??null)) ?> <?= $isObtained?'is-obtained':'' ?>" data-card="<?= e($item['slug']) ?>" <?= !empty($personalPage)&&(($collectionFilter==='obtained')!==$isObtained)?'hidden':'' ?>>
<div class="card-top"><div class="achievement-icon"><?= icon($isObtained?'check':'shield') ?></div><div class="card-title"><p class="card-category category-badge"><?php if(preg_match('/^#[a-f0-9]{6}$/iD',$item['category_color']??'')): ?><svg width="9" height="9" aria-hidden="true"><circle cx="4" cy="4" r="4" fill="<?= e($item['category_color']) ?>"/></svg><?php endif; ?> <?= e($item['category']??'Non classé') ?></p><h3><a class="card-link" href="/succes/<?= e($item['slug']) ?>"><?= e($item['name']) ?></a></h3></div><?php if($item['secret_type']==='secret'): ?><span class="secret-badge">SECRET</span><?php endif; ?></div>
<p class="card-description"><?= e($excerpt($item['description'])) ?></p>
<?php if($item['unlock_conditions'] && $item['unlock_conditions']!==$item['description']): ?><div class="card-conditions"><p><strong>Conditions de déblocage</strong><br><?= e($excerpt($item['unlock_conditions'])) ?></p></div><?php endif; ?>
<div class="card-meta"><span class="personal-badge" data-personal-badge><?= $isObtained?'✓ Obtenu':'À débloquer' ?></span><?php if($item['rarity']): ?><span><?= e($item['rarity']) ?></span><?php endif; ?><span class="verification"><?= $item['verification_status']==='verified'?'Confirmé':'À confirmer' ?></span></div>
<?php require __DIR__.'/rewards.php'; ?>
<p class="declaration-date" data-declaration <?= $isObtained?'':'hidden' ?>>Déclaré obtenu le <time><?= $isObtained&&$personal['obtained_at']?e((new DateTimeImmutable($personal['obtained_at']))->format('d/m/Y à H:i')).' UTC':'' ?></time></p>
<div class="card-bottom"><a class="card-open" href="/succes/<?= e($item['slug']) ?>">Voir le guide <?= icon('arrow') ?></a></div>
<?php require dirname(__DIR__).'/member/progress-form.php'; ?>
</article>
