ALTER TABLE services
  ADD COLUMN provider_name VARCHAR(150) NULL AFTER description,
  ADD COLUMN provider_type VARCHAR(30) NOT NULL DEFAULT 'internal' AFTER provider_name;
