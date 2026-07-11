-- Migration: Add delivery OTP and verification logs tables

CREATE TABLE IF NOT EXISTS delivery_otps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    user_id INT NOT NULL,
    otp_encrypted VARCHAR(255) NOT NULL,
    otp_hash VARCHAR(255) NOT NULL,
    method VARCHAR(32) DEFAULT NULL,
    generated_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    status ENUM('pending','sent','verified','expired') NOT NULL DEFAULT 'pending',
    attempts INT NOT NULL DEFAULT 0,
    max_attempts INT NOT NULL DEFAULT 5,
    verified_at DATETIME DEFAULT NULL,
    UNIQUE KEY uq_order (order_id),
    INDEX idx_user (user_id),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS otp_verification_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    otp_id INT NOT NULL,
    order_id INT NOT NULL,
    user_id INT NOT NULL,
    attempt_time DATETIME NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    note VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (otp_id) REFERENCES delivery_otps(id) ON DELETE CASCADE
);

-- Optional: clean up expired otps periodically (scheduled job recommended)
