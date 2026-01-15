CREATE TABLE login_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    ip VARBINARY(16) NOT NULL,
    first_attempt_at DATETIME NOT NULL,
    last_attempt_at DATETIME NOT NULL,
    attempts INT NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_username_ip (username, ip),
    KEY idx_locked_until (locked_until),
    KEY idx_last_attempt_at (last_attempt_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;