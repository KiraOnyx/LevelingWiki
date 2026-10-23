<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='test'||getenv('DB_DATABASE')!=='levelingwiki_test')exit(1);
$p=App\Services\Database::connect();
function migrationCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "OK : $label\n";}
// Reconstruct pre-migration states only inside the disposable test schema.
$p->exec('ALTER TABLE personal_achievements DROP CONSTRAINT chk_binary_progress');
$p->exec("INSERT INTO users(username,password_hash,role) VALUES('migration_only','unusable','member')");$u=(int)$p->lastInsertId();
$ids=$p->query('SELECT id FROM achievements ORDER BY id LIMIT 3')->fetchAll(PDO::FETCH_COLUMN);
$q=$p->prepare('INSERT INTO personal_achievements(user_id,achievement_id,progress,task_status,accepted_at,completed_at,obtained_at) VALUES(?,?,?,?,?,?,?)');
$q->execute([$u,$ids[0],'obtained','completed','2025-01-01 10:00:00','2025-01-02 11:00:00',null]);
$q->execute([$u,$ids[1],'in_progress','active','2025-01-03 10:00:00',null,null]);
$q->execute([$u,$ids[2],'obtained','abandoned','2025-01-04 10:00:00',null,'2025-01-05 12:00:00']);
$catalogBefore=$p->query('SELECT * FROM achievements ORDER BY id')->fetchAll();
$migrate=require dirname(__DIR__).'/database/migrations/013_binary_progress.php';$migrate($p);
$rows=$p->query("SELECT * FROM personal_achievements WHERE user_id=$u ORDER BY achievement_id")->fetchAll();
migrationCheck($rows[0]['progress']==='obtained'&&$rows[0]['obtained_at']==='2025-01-02 11:00:00','tâche terminée migrée avec sa date');
migrationCheck($rows[1]['progress']==='not_started','tâche active convertie en non obtenu');
migrationCheck($rows[2]['progress']==='obtained'&&$rows[2]['obtained_at']==='2025-01-05 12:00:00','ancien obtenu conservé après abandon');
$archive=$p->query("SELECT * FROM personal_achievements_legacy WHERE user_id=$u ORDER BY achievement_id")->fetchAll();
migrationCheck(count($archive)===3&&$archive[1]['task_status']==='active','archive complète des anciens états');
$migrate($p);
migrationCheck($rows===$p->query("SELECT * FROM personal_achievements WHERE user_id=$u ORDER BY achievement_id")->fetchAll(),'migration binaire idempotente');
migrationCheck($archive===$p->query("SELECT * FROM personal_achievements_legacy WHERE user_id=$u ORDER BY achievement_id")->fetchAll(),'archive non écrasée à la réexécution');
migrationCheck($catalogBefore===$p->query('SELECT * FROM achievements ORDER BY id')->fetchAll(),'catalogue et récompenses inchangés');
try{$p->exec("UPDATE personal_achievements SET progress='in_progress' WHERE user_id=$u");throw new RuntimeException('État intermédiaire accepté');}catch(PDOException){echo "OK : contrainte binaire en base\n";}
$p->exec("DELETE FROM users WHERE id=$u");
