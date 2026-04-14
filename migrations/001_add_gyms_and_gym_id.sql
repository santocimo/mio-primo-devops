-- Migration: add gyms table and gym_id to visitatori
-- Run this on your MySQL server as an admin user

CREATE TABLE IF NOT EXISTS gyms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  settings JSON DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add gym_id to visitatori (default to 1 for compatibility)
ALTER TABLE visitatori ADD COLUMN IF NOT EXISTS gym_id INT NOT NULL DEFAULT 1;

-- Create foreign key (optional: ensure gyms.id contains default 1)
ALTER TABLE visitatori ADD CONSTRAINT IF NOT EXISTS fk_visitatori_gyms FOREIGN KEY (gym_id) REFERENCES gyms(id);

-- Useful indexes
CREATE INDEX IF NOT EXISTS idx_visitatori_gym ON visitatori (gym_id);
CREATE UNIQUE INDEX IF NOT EXISTS ux_visitatori_gym_cf ON visitatori (gym_id, codice_fiscale);

-- Backfill note: insert a default gym if none exists
-- INSERT INTO gyms (name, slug) SELECT 'Default Gym', 'default' WHERE NOT EXISTS (SELECT 1 FROM gyms WHERE id=1);
