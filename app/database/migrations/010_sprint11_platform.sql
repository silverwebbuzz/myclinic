USE manageclinic;

CREATE TABLE IF NOT EXISTS platform_admins (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email           VARCHAR(150) NOT NULL,
  password_hash   VARCHAR(255) NOT NULL,
  name            VARCHAR(100) NOT NULL,
  is_active       TINYINT(1) DEFAULT 1,
  last_login_at   TIMESTAMP NULL,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_platform_admin_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS impersonation_tokens (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id    BIGINT UNSIGNED NOT NULL,
  clinic_id   BIGINT UNSIGNED NOT NULL,
  user_id     BIGINT UNSIGNED NOT NULL,
  token_hash  VARCHAR(64) NOT NULL,
  expires_at  TIMESTAMP NOT NULL,
  used_at     TIMESTAMP NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_impersonate_token (token_hash, expires_at),
  CONSTRAINT fk_impersonate_admin FOREIGN KEY (admin_id) REFERENCES platform_admins(id),
  CONSTRAINT fk_impersonate_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id),
  CONSTRAINT fk_impersonate_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE tenants ADD COLUMN custom_domain_verified TINYINT(1) DEFAULT 0;
ALTER TABLE tenants ADD COLUMN domain_verify_token VARCHAR(64) NULL;
ALTER TABLE tenants ADD COLUMN churn_risk_level ENUM('none','low','high') DEFAULT 'none';
ALTER TABLE tenants ADD COLUMN churn_risk_reason VARCHAR(255) NULL;
ALTER TABLE tenants ADD COLUMN last_staff_login_at TIMESTAMP NULL;

ALTER TABLE api_keys ADD COLUMN key_prefix VARCHAR(20) NULL AFTER name;
