<?php
declare(strict_types=1);
return static function(PDO $pdo):void {
    if(!in_array('participants_required',$pdo->query('SHOW COLUMNS FROM achievements')->fetchAll(PDO::FETCH_COLUMN),true))
        $pdo->exec('ALTER TABLE achievements ADD participants_required SMALLINT UNSIGNED NOT NULL DEFAULT 1 CHECK(participants_required BETWEEN 1 AND 100)');
};
