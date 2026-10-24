<?php
declare(strict_types=1);
return static function(PDO $pdo): void {
    // Frozen mapping: do not depend on mutable application configuration in migrations.
    $map=[
        'bleu'=>['Bleu','Timing et endurance','#4F8FD1'],
        'rose'=>['Rose','Actions et conditions spéciales','#C778A8'],
        'jaune'=>['Jaune','Progression et défis','#D2AA56'],
        'turquoise'=>['Turquoise','Découvertes et butin','#39AAA7'],
        'vert'=>['Vert','Régularité et séries','#70A47A'],
    ];
    $pdo->beginTransaction();
    try {
        foreach($map as $slug=>[$old,$name,$color]) {
            // Exact pair only: a custom category using a similar name is left untouched.
            $q=$pdo->prepare('SELECT id,name FROM categories WHERE BINARY slug=? FOR UPDATE');$q->execute([$slug]);$row=$q->fetch();
            if(!$row || !in_array($row['name'],[$old,$name],true))continue;
            $conflict=$pdo->prepare('SELECT id FROM categories WHERE name=? AND id<>?');$conflict->execute([$name,$row['id']]);
            if($conflict->fetchColumn())throw new RuntimeException('Category name conflict; no changes applied.');
            $pdo->prepare('UPDATE categories SET name=?,color=? WHERE id=? AND (BINARY name<>? OR BINARY color<>? OR color IS NULL)')->execute([$name,$color,$row['id'],$name,$color]);
        }
        $q=$pdo->query("SELECT id FROM categories WHERE BINARY slug='vert' AND BINARY name='Régularité et séries'");$green=$q->fetchColumn();
        if($green!==false)$pdo->prepare("UPDATE achievements SET category_id=?,updated_at=updated_at WHERE BINARY slug='meme-jour-autre-monde' AND BINARY name='Même jour, autre monde' AND category_id IS NULL")->execute([$green]);
        $pdo->commit();
    } catch(Throwable $e){$pdo->rollBack();throw $e;}
};
