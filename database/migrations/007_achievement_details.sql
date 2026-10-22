CREATE TABLE IF NOT EXISTS achievement_details (
    achievement_id BIGINT UNSIGNED PRIMARY KEY,
    unlock_conditions TEXT NULL,
    icon_path VARCHAR(512) NULL COMMENT 'Chemin local /assets/images/ uniquement',
    evidence_path VARCHAR(512) NULL COMMENT 'Capture locale /assets/images/ uniquement',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_details_achievement FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
