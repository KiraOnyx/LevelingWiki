-- Private objects are streamed by the application only after historical permission checks.
BEGIN;
DO $$ BEGIN
 IF EXISTS (SELECT 1 FROM storage.buckets WHERE id='levelingwiki-private' AND public) THEN
   RAISE EXCEPTION 'Existing bucket is public; refusing to change it automatically';
 END IF;
END $$;
INSERT INTO storage.buckets(id,name,public,file_size_limit,allowed_mime_types)
VALUES('levelingwiki-private','levelingwiki-private',false,5242880,ARRAY['image/jpeg','image/png','image/webp'])
ON CONFLICT(id) DO NOTHING;
COMMIT;
