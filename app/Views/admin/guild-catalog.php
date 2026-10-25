<?php
require __DIR__.'/guild-navigation.php';
// Reusable native controls keep validation adjacent and preserve rejected values.
$field=function(string $name,string $label,array $row,string $kind='text',?array $options=null)use($error){
    $value=$row[$name]??'';
    echo '<label>'.e($label);
    if($options!==null){echo '<select aria-label="'.e($label).'" name="'.e($name).'">';foreach($options as $key=>$text)echo '<option value="'.e((string)$key).'" '.((string)$value===(string)$key?'selected':'').'>'.e($text).'</option>';echo '</select>';}
    elseif($kind==='textarea')echo '<textarea name="'.e($name).'" maxlength="4000" rows="3">'.e((string)$value).'</textarea>';
    else echo '<input name="'.e($name).'" type="'.e($kind).'" value="'.e((string)$value).'" '.(in_array($name,['title','name','slug','level'],true)?'required ':'').($kind==='number'?'min="'.($name==='level'?'1':'0').'" step="1"':'').'>';
    if($error&&(str_contains($error,$name)||($name==='level'&&str_contains($error,'niveau'))||($name==='quantity'&&str_contains($error,'récompense'))))echo '<small class="field-error" role="alert">'.e($error).'</small>';
    echo '</label>';
};
$hidden=function(string $entity,array $row)use($csrf,$q,$status,$sort){foreach(['csrf'=>$csrf,'id'=>$row['id']??'','version'=>$row['version']??'','q'=>$q,'status'=>$status,'sort'=>$sort] as $k=>$v)echo '<input type="hidden" name="'.e($k).'" value="'.e((string)$v).'">';};
$values=function(string $entity,array $row)use($failedEntity){return $failedEntity===$entity&&(string)($_POST['id']??'')===(string)($row['id']??'')?array_filter($_POST,static fn($v)=>is_string($v)):$row+['archived'=>empty($row['archived_at'])?'0':'1'];};
?>
<header class="page-heading"><p class="eyebrow">TOUR DE GUILDE</p><h1><?= $section==='types'?'Types de récompenses':'Paliers et récompenses' ?></h1><p>Le catalogue est enregistré dans MariaDB. Les récompenses sont informatives, sans déclaration de récupération.</p></header>
<?php if($section==='paliers'): ?>
<section class="admin-panel"><form method="get" class="admin-form form-grid"><label>Rechercher<input name="q" value="<?= e($q) ?>" maxlength="160"></label><?php $field('status','Statut',['status'=>$status],'text',['active'=>'Actifs','archived'=>'Archivés','all'=>'Tous']);$field('sort','Niveau',['sort'=>$sort],'text',['asc'=>'Croissant','desc'=>'Décroissant']); ?><button class="secondary-button">Filtrer</button></form>
<div class="guild-catalog-cards"><?php foreach($milestones as $m): $rs=array_filter($repo->rewards((int)$m['id']),static fn($r)=>$r['archived_at']===null); ?><article class="admin-panel"><h2>Niv. <?= e($m['level']) ?> · <?= e($m['title']) ?></h2><p><?= e($m['description']) ?></p><p><?= $m['archived_at']?'Archivé':'Actif' ?> · <?= count($rs) ?> récompense(s)</p><ul><?php foreach($rs as $r): ?><li><?= e(App\Models\GuildCatalogRepository::present($r)['label']) ?></li><?php endforeach; ?></ul><a class="secondary-button" href="?<?= e($query) ?>&amp;id=<?= (int)$m['id'] ?>#catalog-editor">Modifier</a></article><?php endforeach; ?></div><?php if(!$milestones): ?><p>Aucun palier correspondant.</p><?php endif; ?></section>
<?php else: ?>
<section class="admin-panel"><div class="guild-catalog-cards"><?php foreach($types as $t): ?><article class="admin-panel"><h2><?= icon($t['icon']) ?> <?= e($t['name']) ?></h2><p><?= e($t['slug']) ?> · <?= $t['archived_at']?'Archivé':'Actif' ?></p><p><?= e($t['description']) ?></p><a class="secondary-button" href="?id=<?= (int)$t['id'] ?>#catalog-editor">Modifier</a></article><?php endforeach; ?></div></section>
<?php endif; ?>
<section class="admin-panel" id="catalog-editor" tabindex="-1"><h2><?= $selected?'Modifier':'Créer' ?> <?= $section==='types'?'un type':'un palier' ?></h2><a href="?<?= e($query) ?>">Créer une autre entrée</a>
<?php $formEntity=$section==='types'?'type':'milestone';$row=$values($formEntity,$selected??[]); ?>
<form method="post" action="/admin/tour-de-guilde/<?= e($section) ?>/save" class="admin-form" data-catalog-form><?php $hidden($formEntity,$row); ?>
<div class="form-grid"><?php if($section==='types'){
$field('slug','Identifiant technique permanent',$row);$field('name','Nom',$row);$field('short_name','Nom court',$row);$field('unit','Unité',$row);
$field('icon','Icône locale',$row+['icon'=>'medal'],'text',['medal'=>'Médaille / or','spark'=>'Cristal / gemmes','lock'=>'Clé / serrure','shield'=>'Bouclier / guilde','book'=>'Livre','check'=>'Validation','filter'=>'Filtres','arrow'=>'Flèche','search'=>'Recherche']);
$field('category','Catégorie',$row+['category'=>'personal'],'text',['personal'=>'Ressource personnelle','collective'=>'Avantage collectif']);$field('display_order','Ordre',$row+['display_order'=>0],'number');
}else{$field('level','Niveau requis',$row,'number');$field('title','Titre',$row);}
$field('archived','Statut',$row,'text',['0'=>'Actif','1'=>'Archivé']); ?></div>
<?php $field('description','Description facultative',$row,'textarea'); ?>
<p>Les références sont conservées à l’archivage. Un type archivé reste affiché pour les récompenses qui l’utilisent déjà.</p><button class="primary-button">Enregistrer</button><p class="form-feedback" role="status"></p></form></section>
<?php if($section==='paliers'&&$selected): ?><section class="admin-panel"><h2>Récompenses · <?= e($selected['title']) ?></h2>
<?php if($selected['archived_at']): ?><p>Restaurez ce palier pour éditer ses récompenses.</p><?php else: ?>
<p>Chaque récompense peut être connue ou inconnue indépendamment. « Inconnue » masque son type, sa quantité, sa description et sa distribution aux membres.</p>
<?php foreach([...$rewards,[]] as $reward): $row=$values('reward',$reward);$options=[''=>'À classer / inconnue'];foreach($types as $t)if(!$t['archived_at']||(string)$t['id']===(string)($row['type_id']??''))$options[(string)$t['id']]=$t['name'].($t['archived_at']?' (archivé)':''); ?>
<details class="admin-panel" <?= !$reward||($failedEntity==='reward'&&(string)($_POST['id']??'')===(string)($reward['id']??''))?'open':'' ?>><summary><?= $reward?'Récompense #'.(int)$reward['id'].' · '.e(App\Models\GuildCatalogRepository::present($reward)['label']).($reward['archived_at']?' · Archivée':''):'Ajouter une récompense' ?></summary>
<form method="post" action="/admin/tour-de-guilde/recompenses/save" class="admin-form" data-catalog-form><?php $hidden('reward',$row); ?><input type="hidden" name="milestone_id" value="<?= (int)$selected['id'] ?>">
<div class="form-grid"><?php $field('type_id','Type',$row,'text',$options);$field('quantity','Quantité entière',$row,'text');$field('distribution','Distribution',$row+['distribution'=>'unconfirmed'],'text',['individual'=>'Individuelle','collective'=>'Collective','unconfirmed'=>'À confirmer']);$field('visibility','Visibilité',$row+['visibility'=>'unknown'],'text',['known'=>'Connue','unknown'=>'Inconnue — ???']);$field('display_order','Ordre',$row+['display_order'=>0],'number');$field('archived','Statut',$row,'text',['0'=>'Active','1'=>'Archivée']); ?></div>
<?php $field('description','Description (requise pour une récompense connue sans type)',$row,'textarea'); ?>
<p data-catalog-preview role="status">Aperçu membre : <?= $reward?e(App\Models\GuildCatalogRepository::present($reward)['label']):'???' ?></p>
<button class="primary-button"><?= $reward?'Enregistrer la récompense':'Ajouter la récompense' ?></button><p class="form-feedback" role="status"></p></form></details>
<?php endforeach; endif; ?></section><?php endif; ?>
<p><a href="/admin/history">Consulter le journal d’audit</a></p>
