<?php
declare(strict_types=1);
return static function (PDO $pdo): void {
    $columns=$pdo->query('SHOW COLUMNS FROM achievement_details')->fetchAll(PDO::FETCH_COLUMN);
    if(!in_array('guide_markdown',$columns,true)) $pdo->exec('ALTER TABLE achievement_details ADD guide_markdown MEDIUMTEXT NULL');
    $pdo->exec("CREATE TABLE IF NOT EXISTS achievement_guide_images (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        achievement_id BIGINT UNSIGNED NOT NULL,
        path VARCHAR(191) NOT NULL UNIQUE,
        alt_text VARCHAR(500) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_guide_achievement (achievement_id),
        CONSTRAINT fk_guide_image_achievement FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
