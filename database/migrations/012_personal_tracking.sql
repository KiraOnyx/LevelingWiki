CREATE TABLE IF NOT EXISTS personal_achievements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    achievement_id BIGINT UNSIGNED NULL,
    progress ENUM('not_started','in_progress','obtained') NOT NULL DEFAULT 'not_started',
    task_status ENUM('none','active','completed','abandoned') NOT NULL DEFAULT 'none',
    accepted_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    obtained_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_personal_achievement (user_id,achievement_id),
    INDEX idx_personal_tasks (user_id,task_status),
    CONSTRAINT fk_personal_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_personal_achievement FOREIGN KEY(achievement_id) REFERENCES achievements(id) ON DELETE SET NULL,
    CONSTRAINT chk_task_progress CHECK ((task_status <> 'active' OR progress='in_progress') AND (task_status <> 'completed' OR progress='obtained')),
    CONSTRAINT chk_task_dates CHECK ((task_status='none' AND accepted_at IS NULL) OR (task_status<>'none' AND accepted_at IS NOT NULL)),
    CONSTRAINT chk_completed_date CHECK ((task_status='completed' AND completed_at IS NOT NULL) OR (task_status<>'completed' AND completed_at IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
