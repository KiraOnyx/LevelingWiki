BEGIN;
SET search_path TO levelingwiki, public;
CREATE TABLE web_sessions (
  id_hash char(64) PRIMARY KEY,
  payload text NOT NULL,
  version bigint NOT NULL DEFAULT 1,
  expires_at timestamptz NOT NULL,
  created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX web_sessions_expiry ON web_sessions(expires_at);
ALTER TABLE web_sessions ENABLE ROW LEVEL SECURITY;
REVOKE ALL ON web_sessions FROM PUBLIC;
-- The historical schema is private: PostgREST's anon/authenticated roles have no access.
DO $$ BEGIN
  IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname='anon') THEN
    REVOKE ALL ON SCHEMA levelingwiki FROM anon;
    REVOKE ALL ON ALL TABLES IN SCHEMA levelingwiki FROM anon;
  END IF;
  IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname='authenticated') THEN
    REVOKE ALL ON SCHEMA levelingwiki FROM authenticated;
    REVOKE ALL ON ALL TABLES IN SCHEMA levelingwiki FROM authenticated;
  END IF;
END $$;
COMMIT;
