CREATE TABLE IF NOT EXISTS subscription_payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  provider VARCHAR(20) NOT NULL,
  provider_payment_id VARCHAR(255) NOT NULL,
  user_id INT NULL,
  plan VARCHAR(20) NOT NULL,
  amount_minor INT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL,
  paid_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_subscription_payment_provider_id (provider, provider_payment_id),
  INDEX idx_subscription_payments_user (user_id)
) ENGINE=InnoDB CHARSET=utf8mb4;
