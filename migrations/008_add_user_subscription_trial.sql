ALTER TABLE users
  ADD COLUMN trial_start_date DATETIME NULL,
  ADD COLUMN subscription_status VARCHAR(20) NULL,
  ADD COLUMN subscription_plan VARCHAR(50) NULL,
  ADD COLUMN subscription_expires_at DATETIME NULL;

UPDATE users
SET trial_start_date = NOW(), subscription_status = 'trial'
WHERE LOWER(role) = 'operatore';
