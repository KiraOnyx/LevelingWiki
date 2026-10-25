-- Additive only. No fixtures or changes to existing tables/data.
CREATE TABLE IF NOT EXISTS guild_divisions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL,
 short_name VARCHAR(20) NOT NULL,
 description TEXT NOT NULL,
 color CHAR(7) NOT NULL DEFAULT '#B6A2FF',
 level INT UNSIGNED NOT NULL DEFAULT 1,
 version INT UNSIGNED NOT NULL DEFAULT 1,
 archived_at TIMESTAMP NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_guild_division_name(name),
 INDEX idx_guild_active_level(archived_at,level),
 CONSTRAINT chk_guild_level CHECK(level>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS guild_division_masters (
 division_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL,
 assigned_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(division_id,user_id),
 INDEX idx_guild_master(user_id,division_id),
 FOREIGN KEY(division_id) REFERENCES guild_divisions(id) ON DELETE RESTRICT,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY(assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS guild_division_levels (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 division_id BIGINT UNSIGNED NOT NULL,
 actor_id BIGINT UNSIGNED NULL,
 old_level INT UNSIGNED NULL,
 new_level INT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_guild_level_history(division_id,id),
 FOREIGN KEY(division_id) REFERENCES guild_divisions(id) ON DELETE RESTRICT,
 FOREIGN KEY(actor_id) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT chk_guild_history_level CHECK(new_level>=1 AND (old_level IS NULL OR old_level>=1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
