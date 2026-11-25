CREATE TABLE IF NOT EXISTS admin_audit_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_user_id INT NOT NULL,
  actor_role VARCHAR(32) NOT NULL,
  action VARCHAR(16) NOT NULL,
  resource_type VARCHAR(32) NOT NULL,
  resource_id BIGINT UNSIGNED NULL,
  gym_id INT UNSIGNED NULL,
  response_status SMALLINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  INDEX idx_admin_audit_created (created_at, id),
  INDEX idx_admin_audit_actor (actor_user_id, created_at),
  INDEX idx_admin_audit_resource (resource_type, resource_id, created_at),
  INDEX idx_admin_audit_gym (gym_id, created_at)
) ENGINE=InnoDB CHARSET=utf8mb4;
