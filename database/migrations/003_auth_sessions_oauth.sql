USE manageclinic;

CREATE TABLE IF NOT EXISTS user_sessions (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id            BIGINT UNSIGNED NOT NULL,
  refresh_token_hash VARCHAR(64) NOT NULL,
  device_label       VARCHAR(120) NULL,
  ip_address         VARCHAR(45) NULL,
  user_agent         VARCHAR(255) NULL,
  is_current         TINYINT(1) NOT NULL DEFAULT 0,
  last_active_at     TIMESTAMP NULL,
  expires_at         TIMESTAMP NOT NULL,
  created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_refresh_hash (refresh_token_hash),
  KEY idx_user_sessions (user_id, expires_at),
  CONSTRAINT fk_user_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE users
  ADD COLUMN google_id VARCHAR(255) NULL AFTER email,
  ADD UNIQUE KEY uq_google_id (google_id);
