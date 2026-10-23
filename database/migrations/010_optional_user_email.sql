-- Les emails existants et leur unicité sont conservés ; plusieurs NULL sont autorisés.
ALTER TABLE users MODIFY COLUMN email VARCHAR(254) NULL DEFAULT NULL;
UPDATE users SET email = NULL WHERE TRIM(email) = '';
