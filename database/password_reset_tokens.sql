-- attempt_count = OTP emails sent; last_attempt = time of last send (NOW()).
-- When attempt_count >= 3, resend is blocked until TIMESTAMPDIFF(last_attempt, NOW()) >= 24h (checked in PHP via SQL).

CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token VARCHAR(64) NOT NULL,
  otp_hash VARCHAR(255) NOT NULL,
  otp_expires_at DATETIME NOT NULL,
  last_sent_at DATETIME NOT NULL,
  last_attempt DATETIME NULL,
  attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
  verified_at DATETIME NULL,
  UNIQUE KEY uk_pr_user_id (user_id),
  UNIQUE KEY uk_pr_token (token),
  KEY idx_pr_expires (otp_expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
