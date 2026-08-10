CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  attempt_key CHAR(64) NOT NULL,
  attempted_at DATETIME NOT NULL,
  INDEX idx_login_attempts_key_time (attempt_key, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE users
SET must_change_password = 1
WHERE email IN ('admin@transmore.local', 'superadmin@transmore.id')
  AND updated_at IS NULL;
