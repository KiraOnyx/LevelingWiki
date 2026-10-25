<?php
declare(strict_types=1);
// Immutable seed, independent of application configuration. DDL is restartable.
return static function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS guild_milestones (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, level INT UNSIGNED NOT NULL CHECK(level>0),
        title VARCHAR(160) NOT NULL, description TEXT NOT NULL, archived_at DATETIME NULL,
        active_level INT UNSIGNED GENERATED ALWAYS AS (IF(archived_at IS NULL,level,NULL)) PERSISTENT,
        UNIQUE KEY uq_guild_active_level(active_level), version INT UNSIGNED NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS guild_reward_types (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(100) NOT NULL UNIQUE,
        name VARCHAR(100) NOT NULL, short_name VARCHAR(30) NOT NULL DEFAULT '', description TEXT NOT NULL,
        icon VARCHAR(30) NOT NULL DEFAULT 'medal', unit VARCHAR(60) NOT NULL DEFAULT '',
        category ENUM('personal','collective') NOT NULL, display_order INT UNSIGNED NOT NULL DEFAULT 0,
        archived_at DATETIME NULL, version INT UNSIGNED NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS guild_milestone_rewards (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, milestone_id BIGINT UNSIGNED NOT NULL,
        type_id BIGINT UNSIGNED NULL, quantity BIGINT UNSIGNED NULL,
        distribution ENUM('individual','collective','unconfirmed') NOT NULL,
        description TEXT NOT NULL, visibility ENUM('known','unknown') NOT NULL,
        display_order INT UNSIGNED NOT NULL DEFAULT 0, archived_at DATETIME NULL, version INT UNSIGNED NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY(milestone_id) REFERENCES guild_milestones(id) ON DELETE RESTRICT,
        FOREIGN KEY(type_id) REFERENCES guild_reward_types(id) ON DELETE RESTRICT,
        INDEX guild_rewards_order(milestone_id,display_order,id),
        CONSTRAINT guild_known_quantity CHECK(visibility='unknown' OR quantity IS NOT NULL)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // A durable ledger prevents re-import even if a seeded item was deliberately removed.
    $pdo->exec("CREATE TABLE IF NOT EXISTS guild_catalog_initializations (name VARCHAR(100) PRIMARY KEY, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
    $pdo->beginTransaction();
    try {
        $pdo->exec("INSERT IGNORE INTO guild_catalog_initializations(name) VALUES('phase-c-v1')");
        if ((int)$pdo->query('SELECT ROW_COUNT()')->fetchColumn()===0) {$pdo->commit();return;}
        $types=[['gold','Pièces d’or','medal','personal'],['gems','Gemmes','spark','personal'],['dungeon-keys','Clés de donjon','lock','personal'],['guild-keys','Clés de guilde','lock','personal'],['member-slots','Places de membres','shield','collective']];
        $ids=[];
        foreach($types as $order=>$t){
            $pdo->prepare("INSERT IGNORE INTO guild_reward_types(slug,name,description,icon,category,display_order) VALUES(?,?,'',?,?,?)")->execute([$t[0],$t[1],$t[2],$t[3],$order]);
            $q=$pdo->prepare('SELECT id FROM guild_reward_types WHERE slug=?');$q->execute([$t[0]]);$ids[$t[0]]=$q->fetchColumn();
        }
        $seeds=[[1,'gold',300,'individual',''],[5,'gems',25,'individual',''],[10,'member-slots',5,'collective',''],[20,null,1,'individual','Clé par membre — catégorie à préciser'],[30,null,10,'unconfirmed','Clés — catégorie et distribution à préciser'],[40,'member-slots',10,'collective',''],[50,'gold',1500,'individual','']];
        foreach([60,70,80,90,100] as $n)$seeds[]=[$n,null,null,'unconfirmed',''];
        foreach($seeds as [$level,$key,$amount,$distribution,$description]){
            $q=$pdo->prepare('SELECT id FROM guild_milestones WHERE level=?');$q->execute([$level]);if($q->fetchColumn())continue;
            $pdo->prepare("INSERT INTO guild_milestones(level,title,description) VALUES(?,?,'')")->execute([$level,'Palier '.$level]);$id=$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO guild_milestone_rewards(milestone_id,type_id,quantity,distribution,description,visibility) VALUES(?,?,?,?,?,?)')->execute([$id,$key?$ids[$key]:null,$amount,$distribution,$description,$amount===null?'unknown':'known']);
        }
        $pdo->commit();
    }catch(Throwable $e){$pdo->rollBack();throw $e;}
};
