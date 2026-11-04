-- Historical schema only. No private data. Apply once to an unused levelingwiki schema.
BEGIN;
CREATE SCHEMA levelingwiki;
SET search_path TO levelingwiki, public;
CREATE COLLATION historical_ci (provider=icu, locale='und-u-ks-level1', deterministic=false);
CREATE TYPE "achievements_secret_type" AS ENUM ('unknown','normal','secret');
CREATE TYPE "achievements_verification_status" AS ENUM ('unverified','pending','verified','rejected');
CREATE TYPE "achievements_publication_status" AS ENUM ('draft','published');
CREATE TYPE "discord_roles_level" AS ENUM ('member','editor','admin');
CREATE TYPE "guild_milestone_rewards_distribution" AS ENUM ('individual','collective','unconfirmed');
CREATE TYPE "guild_milestone_rewards_visibility" AS ENUM ('known','unknown');
CREATE TYPE "guild_reward_types_category" AS ENUM ('personal','collective');
CREATE TYPE "personal_achievements_progress" AS ENUM ('not_started','in_progress','obtained');
CREATE TYPE "personal_achievements_task_status" AS ENUM ('none','active','completed','abandoned');
CREATE TYPE "personal_achievements_legacy_progress" AS ENUM ('not_started','in_progress','obtained');
CREATE TYPE "personal_achievements_legacy_task_status" AS ENUM ('none','active','completed','abandoned');
CREATE TYPE "users_role" AS ENUM ('admin','editor','member','super_admin');
CREATE TYPE "users_auth_provider" AS ENUM ('local','discord');
CREATE FUNCTION touch_updated_at() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN IF NEW IS DISTINCT FROM OLD AND NEW.updated_at IS NOT DISTINCT FROM OLD.updated_at THEN NEW.updated_at = date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC'); END IF; RETURN NEW; END $$;
CREATE TABLE "achievement_details" (
  "achievement_id" numeric(20,0) NOT NULL CHECK ("achievement_id" >= 0 AND "achievement_id" <= 18446744073709551615),
  "unlock_conditions" text COLLATE historical_ci DEFAULT NULL,
  "icon_path" varchar(512) COLLATE historical_ci DEFAULT NULL,
  "evidence_path" varchar(512) COLLATE historical_ci DEFAULT NULL,
  "created_at" timestamp(0) NOT NULL DEFAULT (date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC')),
  "updated_at" timestamp(0) NOT NULL DEFAULT (date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC')),
  "guide_markdown" text COLLATE historical_ci DEFAULT NULL,
  PRIMARY KEY ("achievement_id")
);
CREATE SEQUENCE "achievement_guide_images_id_seq";
CREATE TABLE "achievement_guide_images" (
  "id" numeric(20,0) NOT NULL DEFAULT nextval('levelingwiki.achievement_guide_images_id_seq') CHECK ("id" >= 0 AND "id" <= 18446744073709551615),
  "achievement_id" numeric(20,0) NOT NULL CHECK ("achievement_id" >= 0 AND "achievement_id" <= 18446744073709551615),
  "path" varchar(191) COLLATE historical_ci NOT NULL,
  "alt_text" varchar(500) COLLATE historical_ci NOT NULL,
  "created_at" timestamp(0) NOT NULL DEFAULT (date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC')),
  PRIMARY KEY ("id")
);
ALTER SEQUENCE "achievement_guide_images_id_seq" OWNED BY "achievement_guide_images"."id";
CREATE TABLE "achievement_titles" (
  "achievement_id" numeric(20,0) NOT NULL CHECK ("achievement_id" >= 0 AND "achievement_id" <= 18446744073709551615),
  "title_id" numeric(20,0) NOT NULL CHECK ("title_id" >= 0 AND "title_id" <= 18446744073709551615),
  "created_at" timestamp(0) NOT NULL DEFAULT (date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC')),
  "updated_at" timestamp(0) NOT NULL DEFAULT (date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC')),
  PRIMARY KEY ("achievement_id","title_id")
);
CREATE SEQUENCE "achievements_id_seq";
CREATE TABLE "achievements" (
  "id" numeric(20,0) NOT NULL DEFAULT nextval('levelingwiki.achievements_id_seq') CHECK ("id" >= 0 AND "id" <= 18446744073709551615),
  "slug" varchar(191) COLLATE historical_ci NOT NULL,
  "name" varchar(191) COLLATE historical_ci NOT NULL,
  "description" text COLLATE historical_ci DEFAULT NULL,
  "category_id" numeric(20,0) DEFAULT NULL CHECK ("category_id" >= 0 AND "category_id" <= 18446744073709551615),
  "rarity" varchar(50) COLLATE historical_ci DEFAULT NULL,
  "secret_type" "achievements_secret_type" NOT NULL DEFAULT 'unknown',
  "reward_xp" numeric(20,0) DEFAULT NULL CHECK ("reward_xp" >= 0 AND "reward_xp" <= 18446744073709551615),
  "reward_gold" numeric(20,0) DEFAULT NULL CHECK ("reward_gold" >= 0 AND "reward_gold" <= 18446744073709551615),
  "reward_gems" numeric(20,0) DEFAULT NULL CHECK ("reward_gems" >= 0 AND "reward_gems" <= 18446744073709551615),
  "reward_keys" numeric(20,0) DEFAULT NULL CHECK ("reward_keys" >= 0 AND "reward_keys" <= 18446744073709551615),
  "title_rewards_known" smallint NOT NULL DEFAULT 0,
  "verification_status" "achievements_verification_status" NOT NULL DEFAULT 'unverified',
  "source_url" varchar(2048) COLLATE historical_ci DEFAULT NULL,
  "created_by" numeric(20,0) DEFAULT NULL CHECK ("created_by" >= 0 AND "created_by" <= 18446744073709551615),
  "verified_by" numeric(20,0) DEFAULT NULL CHECK ("verified_by" >= 0 AND "verified_by" <= 18446744073709551615),
  "verified_at" timestamp(0) NULL DEFAULT NULL,
  "created_at" timestamp(0) NOT NULL DEFAULT (date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC')),
  "updated_at" timestamp(0) NOT NULL DEFAULT (date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC')),
  "publication_status" "achievements_publication_status" NOT NULL DEFAULT 'draft',
  "archived_at" timestamp(0) NULL DEFAULT NULL,
  "participants_required" integer NOT NULL DEFAULT 1 CHECK ("participants_required" between 1 and 100) CHECK ("participants_required" >= 0 AND "participants_required" <= 65535),
  PRIMARY KEY ("id"),
  CONSTRAINT "chk_title_rewards_known" CHECK ("title_rewards_known" in (0,1)),
  CONSTRAINT "chk_verification_date" CHECK ("verification_status" = 'verified' and "verified_at" is not null or "verification_status" <> 'verified' and "verified_at" is null)
);
ALTER SEQUENCE "achievements_id_seq" OWNED BY "achievements"."id";
CREATE SEQUENCE "audit_logs_id_seq";
CREATE TABLE "audit_logs" (
  "id" numeric(20,0) NOT NULL DEFAULT nextval('levelingwiki.audit_logs_id_seq') CHECK ("id" >= 0 AND "id" <= 18446744073709551615),
  "actor_id" numeric(20,0) DEFAULT NULL CHECK ("actor_id" >= 0 AND "actor_id" <= 18446744073709551615),
  "action" varchar(100) COLLATE historical_ci NOT NULL,
  "entity_type" varchar(64) COLLATE historical_ci NOT NULL,
  "entity_id" numeric(20,0) DEFAULT NULL CHECK ("entity_id" >= 0 AND "entity_id" <= 18446744073709551615),
  "old_values" json DEFAULT NULL,
  "new_values" json DEFAULT NULL,
  "created_at" timestamp(0) NOT NULL DEFAULT (date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC')),
  "updated_at" timestamp(0) NOT NULL DEFAULT (date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC')),
  PRIMARY KEY ("id")
);
ALTER SEQUENCE "audit_logs_id_seq" OWNED BY "audit_logs"."id";
CREATE TABLE "authorization_policy" (
  "id" smallint NOT NULL,
  "version" numeric(20,0) NOT NULL DEFAULT 1 CHECK ("version" >= 0 AND "version" <= 18446744073709551615),
  "updated_at" timestamp(0) NOT NULL DEFAULT (date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC')),
  PRIMARY KEY ("id"),
  CONSTRAINT "chk_policy_singleton" CHECK ("id" = 1)
);
CREATE SEQUENCE "categories_id_seq";
CREATE TABLE "categories" (
  "id" numeric(20,0) NOT NULL DEFAULT nextval('levelingwiki.categories_id_seq') CHECK ("id" >= 0 AND "id" <= 18446744073709551615),
  "slug" varchar(191) COLLATE historical_ci NOT NULL,
  "name" varchar(100) COLLATE historical_ci NOT NULL,
  "description" text COLLATE historical_ci DEFAULT NULL,
  "created_at" timestamp(0) NOT NULL DEFAULT (date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC')),
  "updated_at" timestamp(0) NOT NULL DEFAULT (date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC')),
  "color" char(7) COLLATE historical_ci NOT NULL DEFAULT '#65C66B',
  "archived_at" timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY ("id")
);
ALTER SEQUENCE "categories_id_seq" OWNED BY "categories"."id";
CREATE TABLE "discord_identities" (
  "user_id" numeric(20,0) NOT NULL CHECK ("user_id" >= 0 AND "user_id" <= 18446744073709551615),
  "discord_user_id" varchar(20) NOT NULL,
  "display_name" varchar(100) COLLATE historical_ci NOT NULL,
  "avatar_hash" varchar(100) COLLATE historical_ci DEFAULT NULL,
  "created_at" timestamp(0) NOT NULL DEFAULT (date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC')),
  "updated_at" timestamp(0) NOT NULL DEFAULT (date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC')),
  PRIMARY KEY ("user_id")
);
CREATE SEQUENCE "discord_roles_id_seq";
CREATE TABLE "discord_roles" (
  "id" numeric(20,0) NOT NULL DEFAULT nextval('levelingwiki.discord_roles_id_seq') CHECK ("id" >= 0 AND "id" <= 18446744073709551615),
  "level" "discord_roles_level" NOT NULL,
  "discord_role_id" varchar(20) NOT NULL,
  "label" varchar(100) COLLATE historical_ci NOT NULL,
  "created_at" timestamp(0) NOT NULL DEFAULT (date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC')),
  "updated_at" timestamp(0) NOT NULL DEFAULT (date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC')),
  PRIMARY KEY ("id")
);
ALTER SEQUENCE "discord_roles_id_seq" OWNED BY "discord_roles"."id";
CREATE TABLE "guild_catalog_initializations" (
  "name" varchar(100) COLLATE historical_ci NOT NULL,
  "created_at" timestamp(0) NOT NULL DEFAULT (date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC')),
  PRIMARY KEY ("name")
);
CREATE SEQUENCE "guild_division_levels_id_seq";
CREATE TABLE "guild_division_levels" (
  "id" numeric(20,0) NOT NULL DEFAULT nextval('levelingwiki.guild_division_levels_id_seq') CHECK ("id" >= 0 AND "id" <= 18446744073709551615),
  "division_id" numeric(20,0) NOT NULL CHECK ("division_id" >= 0 AND "division_id" <= 18446744073709551615),
  "actor_id" numeric(20,0) DEFAULT NULL CHECK ("actor_id" >= 0 AND "actor_id" <= 18446744073709551615),
  "old_level" bigint DEFAULT NULL CHECK ("old_level" >= 0 AND "old_level" <= 4294967295),
  "new_level" bigint NOT NULL CHECK ("new_level" >= 0 AND "new_level" <= 4294967295),
  "created_at" timestamp(0) NOT NULL DEFAULT (date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC')),
  PRIMARY KEY ("id"),
  CONSTRAINT "chk_guild_history_level" CHECK ("new_level" >= 1 and ("old_level" is null or "old_level" >= 1))
);
ALTER SEQUENCE "guild_division_levels_id_seq" OWNED BY "guild_division_levels"."id";
CREATE TABLE "guild_division_masters" (
  "division_id" numeric(20,0) NOT NULL CHECK ("division_id" >= 0 AND "division_id" <= 18446744073709551615),
  "user_id" numeric(20,0) NOT NULL CHECK ("user_id" >= 0 AND "user_id" <= 18446744073709551615),
  "assigned_by" numeric(20,0) DEFAULT NULL CHECK ("assigned_by" >= 0 AND "assigned_by" <= 18446744073709551615),
  "created_at" timestamp(0) NOT NULL DEFAULT (date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC')),
  PRIMARY KEY ("division_id","user_id")
);
CREATE SEQUENCE "guild_divisions_id_seq";
CREATE TABLE "guild_divisions" (
  "id" numeric(20,0) NOT NULL DEFAULT nextval('levelingwiki.guild_divisions_id_seq') CHECK ("id" >= 0 AND "id" <= 18446744073709551615),
  "name" varchar(100) COLLATE historical_ci NOT NULL,
  "short_name" varchar(20) COLLATE historical_ci NOT NULL,
  "description" text COLLATE historical_ci NOT NULL,
  "color" char(7) COLLATE historical_ci NOT NULL DEFAULT '#B6A2FF',
  "level" bigint NOT NULL DEFAULT 1 CHECK ("level" >= 0 AND "level" <= 4294967295),
  "version" bigint NOT NULL DEFAULT 1 CHECK ("version" >= 0 AND "version" <= 4294967295),
  "archived_at" timestamp(0) NULL DEFAULT NULL,
  "created_at" timestamp(0) NOT NULL DEFAULT (date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC')),
  "updated_at" timestamp(0) NOT NULL DEFAULT (date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC')),
  PRIMARY KEY ("id"),
  CONSTRAINT "chk_guild_level" CHECK ("level" >= 1)
);
ALTER SEQUENCE "guild_divisions_id_seq" OWNED BY "guild_divisions"."id";
CREATE SEQUENCE "guild_milestone_rewards_id_seq";
CREATE TABLE "guild_milestone_rewards" (
  "id" numeric(20,0) NOT NULL DEFAULT nextval('levelingwiki.guild_milestone_rewards_id_seq') CHECK ("id" >= 0 AND "id" <= 18446744073709551615),
  "milestone_id" numeric(20,0) NOT NULL CHECK ("milestone_id" >= 0 AND "milestone_id" <= 18446744073709551615),
  "type_id" numeric(20,0) DEFAULT NULL CHECK ("type_id" >= 0 AND "type_id" <= 18446744073709551615),
  "quantity" numeric(20,0) DEFAULT NULL CHECK ("quantity" >= 0 AND "quantity" <= 18446744073709551615),
  "distribution" "guild_milestone_rewards_distribution" NOT NULL,
  "description" text COLLATE historical_ci NOT NULL,
  "visibility" "guild_milestone_rewards_visibility" NOT NULL,
  "display_order" bigint NOT NULL DEFAULT 0 CHECK ("display_order" >= 0 AND "display_order" <= 4294967295),
  "archived_at" timestamp(0) DEFAULT NULL,
  "version" bigint NOT NULL DEFAULT 1 CHECK ("version" >= 0 AND "version" <= 4294967295),
  "created_at" timestamp(0) NOT NULL DEFAULT (date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC')),
  "updated_at" timestamp(0) NOT NULL DEFAULT (date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC')),
  PRIMARY KEY ("id"),
  CONSTRAINT "guild_known_quantity" CHECK ("visibility" = 'unknown' or "quantity" is not null)
);
ALTER SEQUENCE "guild_milestone_rewards_id_seq" OWNED BY "guild_milestone_rewards"."id";
CREATE SEQUENCE "guild_milestones_id_seq";
CREATE TABLE "guild_milestones" (
  "id" numeric(20,0) NOT NULL DEFAULT nextval('levelingwiki.guild_milestones_id_seq') CHECK ("id" >= 0 AND "id" <= 18446744073709551615),
  "level" bigint NOT NULL CHECK ("level" > 0) CHECK ("level" >= 0 AND "level" <= 4294967295),
  "title" varchar(160) COLLATE historical_ci NOT NULL,
  "description" text COLLATE historical_ci NOT NULL,
  "archived_at" timestamp(0) DEFAULT NULL,
  "active_level" bigint GENERATED ALWAYS AS (CASE WHEN "archived_at" IS NULL THEN "level" ELSE NULL END) STORED CHECK ("active_level" >= 0 AND "active_level" <= 4294967295),
  "version" bigint NOT NULL DEFAULT 1 CHECK ("version" >= 0 AND "version" <= 4294967295),
  "created_at" timestamp(0) NOT NULL DEFAULT (date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC')),
  "updated_at" timestamp(0) NOT NULL DEFAULT (date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC')),
  PRIMARY KEY ("id")
);
ALTER SEQUENCE "guild_milestones_id_seq" OWNED BY "guild_milestones"."id";
CREATE SEQUENCE "guild_reward_types_id_seq";
CREATE TABLE "guild_reward_types" (
  "id" numeric(20,0) NOT NULL DEFAULT nextval('levelingwiki.guild_reward_types_id_seq') CHECK ("id" >= 0 AND "id" <= 18446744073709551615),
  "slug" varchar(100) COLLATE historical_ci NOT NULL,
  "name" varchar(100) COLLATE historical_ci NOT NULL,
  "short_name" varchar(30) COLLATE historical_ci NOT NULL DEFAULT '',
  "description" text COLLATE historical_ci NOT NULL,
  "icon" varchar(30) COLLATE historical_ci NOT NULL DEFAULT 'medal',
  "unit" varchar(60) COLLATE historical_ci NOT NULL DEFAULT '',
  "category" "guild_reward_types_category" NOT NULL,
  "display_order" bigint NOT NULL DEFAULT 0 CHECK ("display_order" >= 0 AND "display_order" <= 4294967295),
  "archived_at" timestamp(0) DEFAULT NULL,
  "version" bigint NOT NULL DEFAULT 1 CHECK ("version" >= 0 AND "version" <= 4294967295),
  "created_at" timestamp(0) NOT NULL DEFAULT (date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC')),
  "updated_at" timestamp(0) NOT NULL DEFAULT (date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC')),
  PRIMARY KEY ("id")
);
ALTER SEQUENCE "guild_reward_types_id_seq" OWNED BY "guild_reward_types"."id";
CREATE TABLE "login_limits" (
  "bucket" char(64) COLLATE historical_ci NOT NULL,
  "attempts" bigint NOT NULL DEFAULT 0 CHECK ("attempts" >= 0 AND "attempts" <= 4294967295),
  "window_started" numeric(20,0) NOT NULL CHECK ("window_started" >= 0 AND "window_started" <= 18446744073709551615),
  PRIMARY KEY ("bucket")
);
CREATE SEQUENCE "personal_achievements_id_seq";
CREATE TABLE "personal_achievements" (
  "id" numeric(20,0) NOT NULL DEFAULT nextval('levelingwiki.personal_achievements_id_seq') CHECK ("id" >= 0 AND "id" <= 18446744073709551615),
  "user_id" numeric(20,0) NOT NULL CHECK ("user_id" >= 0 AND "user_id" <= 18446744073709551615),
  "achievement_id" numeric(20,0) DEFAULT NULL CHECK ("achievement_id" >= 0 AND "achievement_id" <= 18446744073709551615),
  "progress" "personal_achievements_progress" NOT NULL DEFAULT 'not_started',
  "task_status" "personal_achievements_task_status" NOT NULL DEFAULT 'none',
  "accepted_at" timestamp(0) NULL DEFAULT NULL,
  "completed_at" timestamp(0) NULL DEFAULT NULL,
  "obtained_at" timestamp(0) NULL DEFAULT NULL,
  "created_at" timestamp(0) NOT NULL DEFAULT (date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC')),
  "updated_at" timestamp(0) NOT NULL DEFAULT (date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC')),
  PRIMARY KEY ("id"),
  CONSTRAINT "chk_task_progress" CHECK (("task_status" <> 'active' or "progress" = 'in_progress') and ("task_status" <> 'completed' or "progress" = 'obtained')),
  CONSTRAINT "chk_task_dates" CHECK ("task_status" = 'none' and "accepted_at" is null or "task_status" <> 'none' and "accepted_at" is not null),
  CONSTRAINT "chk_completed_date" CHECK ("task_status" = 'completed' and "completed_at" is not null or "task_status" <> 'completed' and "completed_at" is null),
  CONSTRAINT "chk_binary_progress" CHECK ("progress" in ('not_started','obtained') and "task_status" = 'none')
);
ALTER SEQUENCE "personal_achievements_id_seq" OWNED BY "personal_achievements"."id";
CREATE SEQUENCE "personal_achievements_legacy_id_seq";
CREATE TABLE "personal_achievements_legacy" (
  "id" numeric(20,0) NOT NULL DEFAULT nextval('levelingwiki.personal_achievements_legacy_id_seq') CHECK ("id" >= 0 AND "id" <= 18446744073709551615),
  "user_id" numeric(20,0) NOT NULL CHECK ("user_id" >= 0 AND "user_id" <= 18446744073709551615),
  "achievement_id" numeric(20,0) DEFAULT NULL CHECK ("achievement_id" >= 0 AND "achievement_id" <= 18446744073709551615),
  "progress" "personal_achievements_legacy_progress" NOT NULL DEFAULT 'not_started',
  "task_status" "personal_achievements_legacy_task_status" NOT NULL DEFAULT 'none',
  "accepted_at" timestamp(0) NULL DEFAULT NULL,
  "completed_at" timestamp(0) NULL DEFAULT NULL,
  "obtained_at" timestamp(0) NULL DEFAULT NULL,
  "created_at" timestamp(0) NOT NULL DEFAULT (date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC')),
  "updated_at" timestamp(0) NOT NULL DEFAULT (date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC')),
  PRIMARY KEY ("id"),
  CONSTRAINT "chk_task_progress" CHECK (("task_status" <> 'active' or "progress" = 'in_progress') and ("task_status" <> 'completed' or "progress" = 'obtained')),
  CONSTRAINT "chk_task_dates" CHECK ("task_status" = 'none' and "accepted_at" is null or "task_status" <> 'none' and "accepted_at" is not null),
  CONSTRAINT "chk_completed_date" CHECK ("task_status" = 'completed' and "completed_at" is not null or "task_status" <> 'completed' and "completed_at" is null)
);
ALTER SEQUENCE "personal_achievements_legacy_id_seq" OWNED BY "personal_achievements_legacy"."id";
CREATE TABLE "schema_migrations" (
  "version" varchar(191) COLLATE historical_ci NOT NULL,
  "checksum" char(64) COLLATE historical_ci NOT NULL,
  "applied_at" timestamp(0) NOT NULL DEFAULT (date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC')),
  PRIMARY KEY ("version")
);
CREATE SEQUENCE "titles_id_seq";
CREATE TABLE "titles" (
  "id" numeric(20,0) NOT NULL DEFAULT nextval('levelingwiki.titles_id_seq') CHECK ("id" >= 0 AND "id" <= 18446744073709551615),
  "slug" varchar(191) COLLATE historical_ci NOT NULL,
  "name" varchar(191) COLLATE historical_ci NOT NULL,
  "description" text COLLATE historical_ci DEFAULT NULL,
  "created_at" timestamp(0) NOT NULL DEFAULT (date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC')),
  "updated_at" timestamp(0) NOT NULL DEFAULT (date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC')),
  PRIMARY KEY ("id")
);
ALTER SEQUENCE "titles_id_seq" OWNED BY "titles"."id";
CREATE SEQUENCE "users_id_seq";
CREATE TABLE "users" (
  "id" numeric(20,0) NOT NULL DEFAULT nextval('levelingwiki.users_id_seq') CHECK ("id" >= 0 AND "id" <= 18446744073709551615),
  "username" varchar(50) COLLATE historical_ci NOT NULL,
  "email" varchar(254) COLLATE historical_ci DEFAULT NULL,
  "password_hash" varchar(255) COLLATE historical_ci NOT NULL,
  "role" "users_role" NOT NULL DEFAULT 'member',
  "created_at" timestamp(0) NOT NULL DEFAULT (date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC')),
  "updated_at" timestamp(0) NOT NULL DEFAULT (date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC')),
  "is_active" smallint NOT NULL DEFAULT 1,
  "session_version" bigint NOT NULL DEFAULT 1 CHECK ("session_version" >= 0 AND "session_version" <= 4294967295),
  "must_change_password" smallint NOT NULL DEFAULT 0,
  "auth_provider" "users_auth_provider" NOT NULL DEFAULT 'local',
  "owner_slot" smallint GENERATED ALWAYS AS (case when "role" = 'super_admin' then 1 else NULL end) STORED,
  PRIMARY KEY ("id"),
  CONSTRAINT "chk_local_owner" CHECK ("role" <> 'super_admin' or "auth_provider" = 'local')
);
ALTER SEQUENCE "users_id_seq" OWNED BY "users"."id";
ALTER TABLE "achievement_details" ADD CONSTRAINT "fk_details_achievement" FOREIGN KEY ("achievement_id") REFERENCES "achievements" ("id") ON DELETE CASCADE DEFERRABLE INITIALLY IMMEDIATE;
ALTER TABLE "achievement_guide_images" ADD CONSTRAINT "fk_guide_image_achievement" FOREIGN KEY ("achievement_id") REFERENCES "achievements" ("id") ON DELETE CASCADE DEFERRABLE INITIALLY IMMEDIATE;
ALTER TABLE "achievement_titles" ADD CONSTRAINT "fk_reward_achievement" FOREIGN KEY ("achievement_id") REFERENCES "achievements" ("id") ON DELETE CASCADE DEFERRABLE INITIALLY IMMEDIATE;
ALTER TABLE "achievement_titles" ADD CONSTRAINT "fk_reward_title" FOREIGN KEY ("title_id") REFERENCES "titles" ("id") ON DELETE CASCADE DEFERRABLE INITIALLY IMMEDIATE;
ALTER TABLE "achievements" ADD CONSTRAINT "fk_achievement_category" FOREIGN KEY ("category_id") REFERENCES "categories" ("id") ON DELETE SET NULL DEFERRABLE INITIALLY IMMEDIATE;
ALTER TABLE "achievements" ADD CONSTRAINT "fk_achievement_creator" FOREIGN KEY ("created_by") REFERENCES "users" ("id") ON DELETE SET NULL DEFERRABLE INITIALLY IMMEDIATE;
ALTER TABLE "achievements" ADD CONSTRAINT "fk_achievement_verifier" FOREIGN KEY ("verified_by") REFERENCES "users" ("id") ON DELETE SET NULL DEFERRABLE INITIALLY IMMEDIATE;
ALTER TABLE "audit_logs" ADD CONSTRAINT "fk_audit_actor" FOREIGN KEY ("actor_id") REFERENCES "users" ("id") ON DELETE SET NULL DEFERRABLE INITIALLY IMMEDIATE;
ALTER TABLE "discord_identities" ADD CONSTRAINT "fk_discord_user" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON DELETE CASCADE DEFERRABLE INITIALLY IMMEDIATE;
ALTER TABLE "guild_division_levels" ADD CONSTRAINT "guild_division_levels_ibfk_1" FOREIGN KEY ("division_id") REFERENCES "guild_divisions" ("id") DEFERRABLE INITIALLY IMMEDIATE;
ALTER TABLE "guild_division_levels" ADD CONSTRAINT "guild_division_levels_ibfk_2" FOREIGN KEY ("actor_id") REFERENCES "users" ("id") ON DELETE SET NULL DEFERRABLE INITIALLY IMMEDIATE;
ALTER TABLE "guild_division_masters" ADD CONSTRAINT "guild_division_masters_ibfk_1" FOREIGN KEY ("division_id") REFERENCES "guild_divisions" ("id") DEFERRABLE INITIALLY IMMEDIATE;
ALTER TABLE "guild_division_masters" ADD CONSTRAINT "guild_division_masters_ibfk_2" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON DELETE CASCADE DEFERRABLE INITIALLY IMMEDIATE;
ALTER TABLE "guild_division_masters" ADD CONSTRAINT "guild_division_masters_ibfk_3" FOREIGN KEY ("assigned_by") REFERENCES "users" ("id") ON DELETE SET NULL DEFERRABLE INITIALLY IMMEDIATE;
ALTER TABLE "guild_milestone_rewards" ADD CONSTRAINT "guild_milestone_rewards_ibfk_1" FOREIGN KEY ("milestone_id") REFERENCES "guild_milestones" ("id") DEFERRABLE INITIALLY IMMEDIATE;
ALTER TABLE "guild_milestone_rewards" ADD CONSTRAINT "guild_milestone_rewards_ibfk_2" FOREIGN KEY ("type_id") REFERENCES "guild_reward_types" ("id") DEFERRABLE INITIALLY IMMEDIATE;
ALTER TABLE "personal_achievements" ADD CONSTRAINT "fk_personal_achievement" FOREIGN KEY ("achievement_id") REFERENCES "achievements" ("id") ON DELETE SET NULL DEFERRABLE INITIALLY IMMEDIATE;
ALTER TABLE "personal_achievements" ADD CONSTRAINT "fk_personal_user" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON DELETE CASCADE DEFERRABLE INITIALLY IMMEDIATE;
CREATE UNIQUE INDEX "achievement_guide_images_path" ON "achievement_guide_images" ("path");
CREATE INDEX "achievement_guide_images_idx_guide_achievement" ON "achievement_guide_images" ("achievement_id");
CREATE INDEX "achievement_titles_idx_achievement_titles_title" ON "achievement_titles" ("title_id");
CREATE UNIQUE INDEX "achievements_slug" ON "achievements" ("slug");
CREATE INDEX "achievements_fk_achievement_creator" ON "achievements" ("created_by");
CREATE INDEX "achievements_fk_achievement_verifier" ON "achievements" ("verified_by");
CREATE INDEX "achievements_idx_achievement_category" ON "achievements" ("category_id");
CREATE INDEX "achievements_idx_achievement_rarity" ON "achievements" ("rarity");
CREATE INDEX "achievements_idx_achievement_secret" ON "achievements" ("secret_type");
CREATE INDEX "achievements_idx_achievement_verification" ON "achievements" ("verification_status");
CREATE INDEX "achievements_idx_publication" ON "achievements" ("publication_status");
CREATE INDEX "audit_logs_idx_audit_entity" ON "audit_logs" ("entity_type","entity_id");
CREATE INDEX "audit_logs_idx_audit_created" ON "audit_logs" ("created_at");
CREATE INDEX "audit_logs_idx_audit_actor_created" ON "audit_logs" ("actor_id","created_at");
CREATE UNIQUE INDEX "categories_slug" ON "categories" ("slug");
CREATE UNIQUE INDEX "categories_name" ON "categories" ("name");
CREATE UNIQUE INDEX "discord_identities_discord_user_id" ON "discord_identities" ("discord_user_id");
CREATE UNIQUE INDEX "discord_roles_uq_discord_level_role" ON "discord_roles" ("level","discord_role_id");
CREATE INDEX "guild_division_levels_idx_guild_level_history" ON "guild_division_levels" ("division_id","id");
CREATE INDEX "guild_division_levels_actor_id" ON "guild_division_levels" ("actor_id");
CREATE INDEX "guild_division_masters_idx_guild_master" ON "guild_division_masters" ("user_id","division_id");
CREATE INDEX "guild_division_masters_assigned_by" ON "guild_division_masters" ("assigned_by");
CREATE UNIQUE INDEX "guild_divisions_uq_guild_division_name" ON "guild_divisions" ("name");
CREATE INDEX "guild_divisions_idx_guild_active_level" ON "guild_divisions" ("archived_at","level");
CREATE INDEX "guild_milestone_rewards_type_id" ON "guild_milestone_rewards" ("type_id");
CREATE INDEX "guild_milestone_rewards_guild_rewards_order" ON "guild_milestone_rewards" ("milestone_id","display_order","id");
CREATE UNIQUE INDEX "guild_milestones_uq_guild_active_level" ON "guild_milestones" ("active_level");
CREATE UNIQUE INDEX "guild_reward_types_slug" ON "guild_reward_types" ("slug");
CREATE INDEX "login_limits_idx_login_window" ON "login_limits" ("window_started");
CREATE UNIQUE INDEX "personal_achievements_uq_personal_achievement" ON "personal_achievements" ("user_id","achievement_id");
CREATE INDEX "personal_achievements_idx_personal_tasks" ON "personal_achievements" ("user_id","task_status");
CREATE INDEX "personal_achievements_fk_personal_achievement" ON "personal_achievements" ("achievement_id");
CREATE UNIQUE INDEX "personal_achievements_legacy_uq_personal_achievement" ON "personal_achievements_legacy" ("user_id","achievement_id");
CREATE INDEX "personal_achievements_legacy_idx_personal_tasks" ON "personal_achievements_legacy" ("user_id","task_status");
CREATE INDEX "personal_achievements_legacy_fk_personal_achievement" ON "personal_achievements_legacy" ("achievement_id");
CREATE UNIQUE INDEX "titles_slug" ON "titles" ("slug");
CREATE UNIQUE INDEX "titles_name" ON "titles" ("name");
CREATE UNIQUE INDEX "users_username" ON "users" ("username");
CREATE UNIQUE INDEX "users_email" ON "users" ("email");
CREATE UNIQUE INDEX "users_uq_single_owner" ON "users" ("owner_slot");
CREATE TRIGGER touch_updated_at BEFORE UPDATE ON "achievement_details" FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
ALTER TABLE "achievement_details" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "achievement_guide_images" ENABLE ROW LEVEL SECURITY;
CREATE TRIGGER touch_updated_at BEFORE UPDATE ON "achievement_titles" FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
ALTER TABLE "achievement_titles" ENABLE ROW LEVEL SECURITY;
CREATE TRIGGER touch_updated_at BEFORE UPDATE ON "achievements" FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
ALTER TABLE "achievements" ENABLE ROW LEVEL SECURITY;
CREATE TRIGGER touch_updated_at BEFORE UPDATE ON "audit_logs" FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
ALTER TABLE "audit_logs" ENABLE ROW LEVEL SECURITY;
CREATE TRIGGER touch_updated_at BEFORE UPDATE ON "authorization_policy" FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
ALTER TABLE "authorization_policy" ENABLE ROW LEVEL SECURITY;
CREATE TRIGGER touch_updated_at BEFORE UPDATE ON "categories" FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
ALTER TABLE "categories" ENABLE ROW LEVEL SECURITY;
CREATE TRIGGER touch_updated_at BEFORE UPDATE ON "discord_identities" FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
ALTER TABLE "discord_identities" ENABLE ROW LEVEL SECURITY;
CREATE TRIGGER touch_updated_at BEFORE UPDATE ON "discord_roles" FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
ALTER TABLE "discord_roles" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "guild_catalog_initializations" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "guild_division_levels" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "guild_division_masters" ENABLE ROW LEVEL SECURITY;
CREATE TRIGGER touch_updated_at BEFORE UPDATE ON "guild_divisions" FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
ALTER TABLE "guild_divisions" ENABLE ROW LEVEL SECURITY;
CREATE TRIGGER touch_updated_at BEFORE UPDATE ON "guild_milestone_rewards" FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
ALTER TABLE "guild_milestone_rewards" ENABLE ROW LEVEL SECURITY;
CREATE TRIGGER touch_updated_at BEFORE UPDATE ON "guild_milestones" FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
ALTER TABLE "guild_milestones" ENABLE ROW LEVEL SECURITY;
CREATE TRIGGER touch_updated_at BEFORE UPDATE ON "guild_reward_types" FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
ALTER TABLE "guild_reward_types" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "login_limits" ENABLE ROW LEVEL SECURITY;
CREATE TRIGGER touch_updated_at BEFORE UPDATE ON "personal_achievements" FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
ALTER TABLE "personal_achievements" ENABLE ROW LEVEL SECURITY;
CREATE TRIGGER touch_updated_at BEFORE UPDATE ON "personal_achievements_legacy" FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
ALTER TABLE "personal_achievements_legacy" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "schema_migrations" ENABLE ROW LEVEL SECURITY;
CREATE TRIGGER touch_updated_at BEFORE UPDATE ON "titles" FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
ALTER TABLE "titles" ENABLE ROW LEVEL SECURITY;
CREATE TRIGGER touch_updated_at BEFORE UPDATE ON "users" FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
ALTER TABLE "users" ENABLE ROW LEVEL SECURITY;
REVOKE ALL ON SCHEMA levelingwiki FROM PUBLIC;
REVOKE ALL ON ALL TABLES IN SCHEMA levelingwiki FROM PUBLIC;
REVOKE ALL ON ALL SEQUENCES IN SCHEMA levelingwiki FROM PUBLIC;
COMMIT;
