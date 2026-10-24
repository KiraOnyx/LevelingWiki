<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='test'||getenv('DB_DATABASE')!=='levelingwiki_test')exit(1);
$p=App\Services\Database::connect();$file='/tmp/category-copy-snapshot.json';
$tables=$p->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);$data=[];
foreach($tables as $table){if($table==='schema_migrations')continue;$data[$table]=$p->query('SELECT * FROM `'.str_replace('`','``',$table).'` ORDER BY 1')->fetchAll();}
if(($argv[1]??'')==='before'){file_put_contents($file,json_encode($data,JSON_THROW_ON_ERROR));chmod($file,0600);echo "Instantané privé de la copie isolée enregistré.\n";exit;}
$expected=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);$green=null;
foreach($expected['categories'] as &$row){$identity=App\Services\CategoryIdentity::HISTORICAL[$row['slug']]??null;if($identity&&$row['name']===$identity['old']){$row['name']=$identity['name'];$row['color']=$identity['color'];}if($row['slug']==='vert'&&$row['name']==='Régularité et séries')$green=$row['id'];unset($row['updated_at']);}unset($row);
foreach($data['categories'] as &$row)unset($row['updated_at']);unset($row);
foreach($expected['achievements'] as &$row)if($row['slug']==='meme-jour-autre-monde'&&$row['name']==='Même jour, autre monde'&&$row['category_id']===null)$row['category_id']=$green;unset($row);
if($expected!==$data)throw new RuntimeException('Différence inattendue dans la copie isolée.');
unlink($file);echo "OK : copie réelle isolée, seuls les cinq noms/couleurs et le rattachement prévu ont changé ; toutes les autres tables et valeurs sont identiques.\n";
