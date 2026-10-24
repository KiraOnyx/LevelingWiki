<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\{Database,TaxonomyService};
if (PHP_SAPI!=='cli' || getenv('APP_ENV')!=='test' || getenv('DB_DATABASE')!=='levelingwiki_test') exit("Base jetable requise.\n");
$pdo=Database::connect();
function checkImport(bool $condition,string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "OK : $message\n";
}
function commandImport(array $options): array {
    $process=proc_open([PHP_BINARY,dirname(__DIR__).'/bin/import-achievements.php',...$options],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    return [proc_close($process),json_decode($out ?: $err,true),$out.$err];
}
function snapshotImport(PDO $pdo): string {
    $tables=[];
    foreach (['achievements','achievement_details','categories','titles','achievement_titles','audit_logs','users'] as $table) $tables[$table]=$pdo->query("SELECT * FROM $table ORDER BY 1")->fetchAll();
    return hash('sha256',json_encode($tables,JSON_THROW_ON_ERROR));
}
$source='database/imports/achievements-valhalla.json';
$document=json_decode(file_get_contents(dirname(__DIR__).'/'.$source),true,512,JSON_THROW_ON_ERROR);
$dir='/var/www/storage/imports'; if (!is_dir($dir)) mkdir($dir,0700,true);
$invalid=$dir.'/invalid.json';
checkImport(commandImport(['--help'])[0]===0,'aide CLI');
foreach ([[],['--file='.$source],['--file='.$source,'--apply','--dry-run'],['--file='.$source,'--apply','--oops'],['--file=/etc/passwd','--apply'],['--file=https://example.com/data.json','--apply']] as $options) checkImport(commandImport($options)[0]!==0,'arguments et chemins non autorisés refusés');
symlink('/etc/passwd',$dir.'/outside.json');
checkImport(commandImport(['--file='.$dir.'/outside.json','--dry-run'])[0]!==0,'symlink sortant refusé');unlink($dir.'/outside.json');
(new TaxonomyService($pdo))->save('categories',['action'=>'save','name'=>'Timing et endurance','slug'=>'bleu','color'=>'#123456','description'=>'Description existante à conserver'],null);
$before=snapshotImport($pdo);
[$code,$report]=commandImport(['--file='.$source,'--dry-run']);
checkImport($code===0 && $report['lus']===33 && $report['valides']===33 && $report['créés']===0 && $report['à_créer']===33 && snapshotImport($pdo)===$before,'premier dry-run : 33 prévus, aucune écriture');
$mutations=[
    'compte'=>function(&$d){array_pop($d['achievements']);},
    'source dupliquée'=>function(&$d){$d['achievements'][32]['source_number']=1;},
    'nom dupliqué collation'=>function(&$d){$d['achievements'][32]['name']='CONTRAT ECLAIR';},
    'slug dupliqué'=>function(&$d){$d['achievements'][32]['slug']='contrat-eclair';},
    'description manquante'=>function(&$d){unset($d['achievements'][32]['description']);},
    'montant négatif'=>function(&$d){$d['achievements'][32]['rewards']['xp']=-1;},
    'montant décimal'=>function(&$d){$d['achievements'][32]['rewards']['xp']=1.5;},
    'montant chaîne'=>function(&$d){$d['achievements'][32]['rewards']['xp']='12';},
    'montant booléen'=>function(&$d){$d['achievements'][32]['rewards']['xp']=true;},
    'clé inconnue'=>function(&$d){$d['achievements'][32]['is_admin']=true;},
    'catégorie inconnue'=>function(&$d){$d['achievements'][32]['category']='Inconnue';},
    'publication interdite'=>function(&$d){$d['achievements'][32]['publication_status']='published';},
    'titre inventé'=>function(&$d){$d['achievements'][32]['titles']=['Faux'];},
    'URL source inventée'=>function(&$d){$d['achievements'][32]['source_url']='https://example.com';},
    'conflit nom existant'=>function(&$d){$d['achievements'][32]['name']='Élan du matin — test';},
];
foreach ($mutations as $label=>$mutate) {
    $data=$document;$mutate($data);file_put_contents($invalid,json_encode($data,JSON_UNESCAPED_UNICODE));
    [$code,$report,$output]=commandImport(['--file='.$invalid,'--apply']);
    checkImport($code!==0 && $report['créés']===0 && snapshotImport($pdo)===$before,'refus atomique : '.$label);
    if ($label==='montant négatif') checkImport(str_contains($output,'Ligne logique 33'),'erreur localisée par ligne logique');
}
file_put_contents($invalid,"{\xff}");
checkImport(commandImport(['--file='.$invalid,'--apply'])[0]!==0 && snapshotImport($pdo)===$before,'JSON UTF-8 invalide sans écriture');
// Défaillance SQL à la dernière insertion : les 32 premières et les catégories/audits doivent disparaître.
$pdo->exec("CREATE TRIGGER test_import_failure BEFORE INSERT ON achievements FOR EACH ROW BEGIN IF NEW.slug = 'meme-jour-autre-monde' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'isolated rollback test'; END IF; END");
try {
    [$code,$report]=commandImport(['--file='.$source,'--apply']);
    checkImport($code!==0 && $report['créés']===0 && snapshotImport($pdo)===$before,'échec SQL tardif : rollback intégral succès, catégories, détails et audit');
} finally { $pdo->exec('DROP TRIGGER test_import_failure'); }
[$code,$report]=commandImport(['--file='.$source,'--apply']);
checkImport($code===0 && $report['créés']===33 && $report['ignorés']===0,'import réel isolé : 33 créations');
$first=$pdo->query("SELECT * FROM achievements WHERE slug='contrat-eclair'")->fetch();
$last=$pdo->query("SELECT * FROM achievements WHERE slug='meme-jour-autre-monde'")->fetch();
checkImport((int)$first['reward_keys']===0 && $last['reward_keys']===null && $last['reward_xp']===null && $last['reward_gold']===null && $last['reward_gems']===null,'zéros explicites et récompenses inconnues préservés');
checkImport((int)$last['category_id']===(int)$pdo->query("SELECT id FROM categories WHERE slug='vert'")->fetchColumn() && $last['rarity']===null,'Même jour, autre monde reste vert avec rareté inconnue');
checkImport($pdo->query("SELECT reward_gems FROM achievements WHERE slug='foulee-decuplee'")->fetchColumn()===null,'récompense partiellement inconnue préservée');
$category=$pdo->query("SELECT * FROM categories WHERE name='Timing et endurance'")->fetch();
checkImport($category['slug']==='bleu' && $category['color']==='#123456' && $category['description']==='Description existante à conserver','catégorie existante et couleur préservées');
checkImport((int)$pdo->query("SELECT COUNT(*) FROM categories WHERE name IN ('Timing et endurance','Actions et conditions spéciales','Progression et défis','Découvertes et butin','Régularité et séries')")->fetchColumn()===5,'cinq catégories réutilisées ou créées');
checkImport((int)$pdo->query("SELECT COUNT(*) FROM achievements WHERE publication_status='draft' AND verification_status='unverified' AND secret_type='secret' AND created_by IS NULL AND rarity IS NULL AND source_url IS NULL")->fetchColumn()===33,'33 brouillons non vérifiés sans acteur usurpé ni URL inventée');
$logs=$pdo->query("SELECT * FROM audit_logs WHERE action='achievement.import'")->fetchAll();
checkImport(count($logs)===33,'33 audits de provenance');
foreach ($logs as $log) {
    $meta=json_decode($log['new_values'],true,512,JSON_THROW_ON_ERROR);
    checkImport($log['actor_id']===null && $meta['import_actor']==='cli:import-achievements' && $meta['source_status']==='guide communautaire non officiel' && $meta['source_sha256']===hash_file('sha256',dirname(__DIR__).'/'.$source),'audit système et empreinte source #'.$meta['source_number']);
}
$pdo->exec("UPDATE achievements SET description='Correction éditoriale à préserver' WHERE slug='contrat-eclair'");
$after=snapshotImport($pdo);
[$code,$report]=commandImport(['--file='.$source,'--dry-run']);
checkImport($code===0 && $report['à_créer']===0 && $report['ignorés']===33 && snapshotImport($pdo)===$after,'simulation de relance : 33 ignorés sans écriture');
[$code,$report]=commandImport(['--file='.$source,'--apply']);
checkImport($code===0 && $report['créés']===0 && $report['ignorés']===33 && snapshotImport($pdo)===$after,'seconde application : 0 création, corrections éditoriales préservées');
$data=$document;$data['achievements'][0]['name']='Nom divergent';file_put_contents($invalid,json_encode($data));
checkImport(commandImport(['--file='.$invalid,'--apply'])[0]!==0 && snapshotImport($pdo)===$after,'conflit même slug et nom différent refusé');
checkImport(count((new App\Models\AchievementRepository($pdo))->all())===4,'catalogue public inchangé : seulement les quatre fixtures');
unlink($invalid);
echo "Import : tests terminés exclusivement sur la base jetable.\n";
