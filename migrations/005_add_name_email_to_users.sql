-- Migration: add name and email to users
-- Backfills existing records with safe defaults so admin screens can show real names.

ALTER TABLE users
  ADD COLUMN name VARCHAR(150) DEFAULT NULL AFTER id,
  ADD COLUMN email VARCHAR(150) DEFAULT NULL AFTER name;

UPDATE users
SET name = COALESCE(NULLIF(name, ''), username)
WHERE name IS NULL OR name = '';
