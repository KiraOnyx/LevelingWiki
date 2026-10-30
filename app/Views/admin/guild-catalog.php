<?php
require __DIR__.'/guild-navigation.php';
$editingCatalog=$selected!==null||($_GET['new']??'')==='1'||$failedEntity!==null||!empty($error);
$listUrl='/admin/tour-de-guilde/'.$section.'?'.$query;
// Reusable native controls keep validation adjacent and preserve rejected values.
$field=function(string $name,string $label,array $row,string $kind='text',?array $options=null)use($error){
    $value=$row[$name]??'';
    echo '<label>'.e($label);
    if($options!==null){echo '<select aria-label="'.e($label).'" name="'.e($name).'">';foreach($options as $key=>$text)echo '<option value="'.e((string)$key).'" '.((string)$value===(string)$key?'selected':'').'>'.e($text).'</option>';echo '</select>';}
    elseif($kind==='textarea')echo '<textarea name="'.e($name).'" maxlength="4000" rows="3">'.e((string)$value).'</textarea>';
    else echo '<input name="'.e($name).'" type="'.e($kind).'" value="'.e((string)$value).'" '.(in_array($name,['title','name','slug','level'],true)?'required ':'').($name==='slug'&&!empty($row['id'])?'readonly ':'').($kind==='number'?'min="'.($name==='level'?'1':'0').'" step="1"':'').'>';
    if($error&&(str_contains($error,$name)||($name==='level'&&str_contains($error,'niveau'))||($name==='quantity'&&str_contains($error,'récompense'))))echo '<small class="field-error" role="alert">'.e($error).'</small>';
    echo '</label>';
};
$hidden=function(string $entity,array $row)use($csrf,$q,$status,$sort){foreach(['csrf'=>$csrf,'id'=>$row['id']??'','version'=>$row['version']??'','q'=>$q,'status'=>$status,'sort'=>$sort] as $k=>$v)echo '<input type="hidden" name="'.e($k).'" value="'.e((string)$v).'">';};
$cycle=function(string $entity,array $row)use($hidden){
$fields=match($entity){'milestone'=>['level','title','description'],'type'=>['slug','name','short_name','description','icon','unit','category','display_order'],'reward'=>['milestone_id','type_id','quantity','distribution','description','visibility','display_order']};
echo '<form method="post" class="catalog-cycle" action="/admin/tour-de-guilde/'.(['milestone'=>'paliers','type'=>'types','reward'=>'recompenses'][$entity]).'/save" data-confirm="Cette action modifie la visibilité. Les références et l’historique seront conservés.">';$hidden($entity,$row);
foreach($fields as $key)echo '<input type="hidden" name="'.e($key).'" value="'.e((string)$row[$key]).'">';
echo '<input type="hidden" name="archived" value="'.($row['archived_at']?'0':'1').'"><label class="check-label"><input type="checkbox" required>Confirmer</label><button class="secondary-button">'.($row['archived_at']?'Restaurer':'Archiver').'</button></form>';
};
$delete=function(array $row)use($hidden,$user){if(!App\Services\PermissionService::admin($user))return;
$message='Supprimer définitivement le palier « '.$row['title'].' » (niveau '.$row['level'].') ? Ce palier et les récompenses qui lui sont associées seront supprimés définitivement. Cette action est irréversible. Pour retrouver ce palier, vous devrez le recréer.';
echo '<form method="post" class="catalog-delete" action="/admin/tour-de-guilde/paliers/delete" data-confirm="'.e($message).'">';$hidden('milestone',$row);
echo '<input type="hidden" name="action" value="delete"><label class="check-label"><input type="checkbox" name="confirm" value="1" required>Je confirme la suppression irréversible de ce palier et de ses récompenses.</label><button class="secondary-button danger-button">Supprimer définitivement</button></form>';
};
$values=function(string $entity,array $row)use($failedEntity){return $failedEntity===$entity&&(string)($_POST['id']??'')===(string)($row['id']??'')?array_filter($_POST,static fn($v)=>is_string($v)):$row+['archived'=>empty($row['archived_at'])?'0':'1'];};
?>
<header class="page-heading"><p class="eyebrow">TOUR DE GUILDE</p><h1><?= $section==='types'?'Types de récompenses':'Paliers et récompenses' ?></h1><p>Gérez les étapes de progression et leurs récompenses.</p><p><?= $section==='paliers'?count($milestones):count($visibleTypes) ?> <?= $section==='paliers'?'palier(s) correspondant(s)':'type(s) enregistré(s)' ?></p><a class="primary-button" href="<?= e($listUrl) ?>&amp;new=1#catalog-editor">+ Ajouter <?= $section==='types'?'un type':'un palier' ?></a></header>
<?php if(!$editingCatalog): ?>
<?php if($section==='paliers'): ?>
<section class="admin-panel"><form method="get" action="/admin/tour-de-guilde/paliers" class="admin-form admin-filters"><label>Rechercher<input name="q" value="<?= e($q) ?>" maxlength="160"></label><?php $field('status','Statut',['status'=>$status],'text',['active'=>'Actifs','archived'=>'Archivés','all'=>'Tous']);$field('sort','Niveau',['sort'=>$sort],'text',['asc'=>'Croissant','desc'=>'Décroissant']); ?><button class="secondary-button">Filtrer</button><a class="secondary-button" href="/admin/tour-de-guilde/paliers">Réinitialiser</a></form>
<div class="guild-catalog-cards"><?php foreach($milestones as $m): $rs=array_filter($repo->rewards((int)$m['id']),static fn($r)=>$r['archived_at']===null); ?><article class="admin-panel"><h2>Niv. <?= e($m['level']) ?> · <?= e($m['title']) ?></h2><p><?= e($m['description']) ?></p><p><?= $m['archived_at']?'Archivé':'Actif' ?> · <?= count($rs) ?> récompense(s)</p><ul><?php foreach($rs as $r): ?><li><?= e(App\Models\GuildCatalogRepository::present($r)['label']) ?></li><?php endforeach; ?></ul><div class="card-actions"><a class="secondary-button" href="?<?= e($query) ?>&amp;id=<?= (int)$m['id'] ?>#catalog-editor">Modifier</a><?php $cycle('milestone',$m);$delete($m); ?></div></article><?php endforeach; ?></div><?php if(!$milestones): ?><p>Aucun palier correspondant.</p><?php endif; ?></section>
<?php else: ?>
<section class="admin-panel"><form method="get" action="/admin/tour-de-guilde/types" class="admin-form admin-filters"><label>Rechercher<input name="q" value="<?= e($q) ?>" maxlength="160"></label><?php $field('status','Statut',['status'=>$status],'text',['active'=>'Actifs','archived'=>'Archivés','all'=>'Tous']);$field('sort','Ordre',['sort'=>$sort],'text',['asc'=>'Croissant','desc'=>'Décroissant']); ?><button class="secondary-button">Filtrer</button><a class="secondary-button" href="/admin/tour-de-guilde/types">Réinitialiser</a></form><?php if(!$visibleTypes): ?><p>Aucun type correspondant.</p><?php endif; ?><div class="guild-catalog-cards"><?php foreach($visibleTypes as $t): ?><article class="admin-panel"><h2><?= icon($t['icon']) ?> <?= e($t['name']) ?></h2><p><?= e($t['slug']) ?> · <?= $t['category']==='personal'?'Ressource personnelle':'Avantage collectif' ?> · <?= $t['archived_at']?'Archivé':'Actif' ?></p><p><?= e($t['description']) ?></p><div class="card-actions"><a class="secondary-button" href="?<?= e($query) ?>&amp;id=<?= (int)$t['id'] ?>#catalog-editor">Modifier</a><?php $cycle('type',$t); ?></div></article><?php endforeach; ?></div></section>
<?php endif; ?>
<?php else: ?><p><a class="secondary-button" href="<?= e($listUrl) ?>">← Retour à la liste</a></p><section class="admin-panel admin-editor" data-admin-editor id="catalog-editor" tabindex="-1"><h2><?= $selected?'Modifier':'Créer' ?> <?= $section==='types'?'un type':'un palier' ?></h2>
<?php $formEntity=$section==='types'?'type':'milestone';$row=$values($formEntity,$selected??[]); ?>
<form method="post" action="/admin/tour-de-guilde/<?= e($section) ?>/save" class="admin-form" data-catalog-form><?php $hidden($formEntity,$row); ?>
<div class="form-grid"><?php if($section==='types'){
$field('name','Nom',$row);$field('slug','Identifiant technique permanent',$row);$field('short_name','Nom court',$row);$field('unit','Unité',$row);
$field('icon','Icône locale',$row+['icon'=>'medal'],'text',['medal'=>'Médaille / or','spark'=>'Cristal / gemmes','lock'=>'Clé / serrure','shield'=>'Bouclier / guilde','book'=>'Livre','check'=>'Validation','filter'=>'Filtres','arrow'=>'Flèche','search'=>'Recherche']);
$field('category','Catégorie',$row+['category'=>'personal'],'text',['personal'=>'Ressource personnelle','collective'=>'Avantage collectif']);$field('display_order','Ordre',$row+['display_order'=>0],'number');
}else{$field('level','Niveau requis',$row,'number');}
$field('archived','Statut',$row,'text',['0'=>'Actif','1'=>'Archivé']); ?></div><?php if($section==='paliers')$field('title','Titre',$row); ?>
<?php $field('description','Description facultative',$row,'textarea'); ?>
<p><?= $section==='types'?'Un type archivé reste affiché pour les récompenses qui l’utilisent déjà.':'Un palier archivé est masqué sur la tour. Ses récompenses et son historique sont conservés.' ?></p><div class="admin-actions"><button class="primary-button">Enregistrer</button><a class="secondary-button" href="<?= e($listUrl) ?>">Annuler</a></div><p class="form-feedback" role="status"></p></form></section>
<?php if($section==='paliers'&&$selected): ?><div class="admin-editor"><?php $delete($selected); ?></div><?php endif; ?>
<?php if($section==='paliers'&&$selected): ?><section class="admin-panel admin-editor"><h2>Récompenses · <?= e($selected['title']) ?></h2>
<?php if($selected['archived_at']): ?><p>Restaurez ce palier pour éditer ses récompenses.</p><?php else: ?>
<p>Chaque récompense peut être connue ou inconnue indépendamment. « Inconnue » masque son type, sa quantité, sa description et sa distribution aux membres.</p>
<?php foreach([...$rewards,[]] as $reward): $row=$values('reward',$reward);$options=[''=>'À classer / inconnue'];foreach($types as $t)if(!$t['archived_at']||(string)$t['id']===(string)($row['type_id']??''))$options[(string)$t['id']]=$t['name'].($t['archived_at']?' (archivé)':''); ?>
<article class="reward-editor-card">
<?php if($reward): $presentation=App\Models\GuildCatalogRepository::present($reward); ?>
<header class="reward-card-heading"><span aria-hidden="true"><?= icon($presentation['icon']) ?></span><div><h3><?= $reward['visibility']==='unknown'?'Récompense inconnue':e(trim($presentation['label'])) ?></h3>
<p>Distribution : <?= e($reward['visibility']==='unknown'?'Masquée aux membres':(['individual'=>'Par membre','collective'=>'Collective','unconfirmed'=>'À confirmer'][$reward['distribution']])) ?></p>
<?php if($reward['archived_at']): ?><small>Archivée</small><?php endif; ?></div></header>
<?php endif; ?>
<details data-admin-disclosure <?= ($failedEntity==='reward'&&(string)($_POST['id']??'')===(string)($reward['id']??''))?'open':'' ?>><summary class="<?= $reward?'secondary-button':'primary-button' ?>"><?= $reward?'Modifier':'+ Ajouter une récompense' ?></summary>
<form method="post" action="/admin/tour-de-guilde/recompenses/save" class="admin-form" data-catalog-form><?php $hidden('reward',$row); ?><input type="hidden" name="milestone_id" value="<?= (int)$selected['id'] ?>">
<div class="form-grid"><?php $field('type_id','Type',$row,'text',$options);$field('quantity','Quantité entière',$row,'text');$field('distribution','Distribution',$row+['distribution'=>'unconfirmed'],'text',['individual'=>'Individuelle','collective'=>'Collective','unconfirmed'=>'À confirmer']);$field('visibility','Visibilité',$row+['visibility'=>'unknown'],'text',['known'=>'Connue','unknown'=>'Inconnue — ???']);$field('display_order','Ordre',$row+['display_order'=>0],'number');$field('archived','Statut',$row,'text',['0'=>'Active','1'=>'Archivée']); ?></div>
<?php $field('description','Description (requise pour une récompense connue sans type)',$row,'textarea'); ?>
<p data-catalog-preview role="status">Aperçu membre : <?= $reward?e(App\Models\GuildCatalogRepository::present($reward)['label']):'???' ?></p>
<div class="admin-actions"><button class="primary-button"><?= $reward?'Enregistrer':'Ajouter' ?></button><a class="secondary-button" data-disclosure-cancel href="<?= e($listUrl) ?>&amp;id=<?= (int)$selected['id'] ?>#catalog-editor">Annuler</a></div><p class="form-feedback" role="status"></p></form></details>
<?php if($reward)$cycle('reward',$reward); ?></article>
<?php endforeach; endif; ?></section><?php endif; ?>
<?php endif; ?><?php if(App\Services\PermissionService::admin($user)): ?><p><a href="/admin/history">Consulter le journal d’audit</a></p><?php endif; ?>
