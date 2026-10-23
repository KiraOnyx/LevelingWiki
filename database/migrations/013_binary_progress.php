<?php
declare(strict_types=1);
return static function(PDO $pdo): void {
    // A durable, non-destructive snapshot. No foreign keys: later account/content edits cannot erase it.
    $pdo->exec('CREATE TABLE IF NOT EXISTS personal_achievements_legacy LIKE personal_achievements');
    $pdo->beginTransaction();
    try {
        $pdo->exec('INSERT IGNORE INTO personal_achievements_legacy SELECT * FROM personal_achievements');
        $pdo->exec("UPDATE personal_achievements SET
            obtained_at=CASE WHEN progress='obtained' OR task_status='completed' THEN COALESCE(obtained_at,completed_at,updated_at) ELSE NULL END,
            progress=CASE WHEN progress='obtained' OR task_status='completed' THEN 'obtained' ELSE 'not_started' END,
            task_status='none',accepted_at=NULL,completed_at=NULL,updated_at=updated_at");
        $pdo->commit();
    }catch(Throwable $e){$pdo->rollBack();throw $e;}
    $q=$pdo->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='personal_achievements' AND CONSTRAINT_NAME='chk_binary_progress'");
    if(!(int)$q->fetchColumn())$pdo->exec("ALTER TABLE personal_achievements ADD CONSTRAINT chk_binary_progress CHECK(progress IN ('not_started','obtained') AND task_status='none')");
};
