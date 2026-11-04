BEGIN;
CREATE SCHEMA IF NOT EXISTS extensions;
CREATE EXTENSION IF NOT EXISTS unaccent WITH SCHEMA extensions;
-- Nondeterministic ICU collation preserves historical case/accent-insensitive equality.
-- LIKE is evaluated on a deterministic normalized projection on PostgreSQL 15–17.
DO $$
DECLARE extension_schema text;
BEGIN
 SELECT n.nspname INTO extension_schema FROM pg_extension e JOIN pg_namespace n ON n.oid=e.extnamespace WHERE e.extname='unaccent';
 EXECUTE format('CREATE FUNCTION levelingwiki.search_text(value text) RETURNS text LANGUAGE sql IMMUTABLE PARALLEL SAFE AS %L',
   format('SELECT %I.unaccent(%L::regdictionary, value) COLLATE "C"',extension_schema,extension_schema||'.unaccent'));
END $$;
REVOKE ALL ON FUNCTION levelingwiki.search_text(text) FROM PUBLIC;
COMMIT;
