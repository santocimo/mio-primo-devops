-- Migration: add category to existing gyms for multi-context support
ALTER TABLE gyms
  ADD COLUMN category VARCHAR(50) NOT NULL DEFAULT 'gym';

UPDATE gyms
  SET category = 'gym'
  WHERE category IS NULL;
