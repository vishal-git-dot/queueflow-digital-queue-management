CREATE DATABASE IF NOT EXISTS queueflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE queueflow;

CREATE TABLE IF NOT EXISTS queues (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    token_prefix VARCHAR(5) NOT NULL DEFAULT 'Q',
    last_token_number INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_queue_prefix (token_prefix)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admins (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_admin_username (username)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS queue_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue_id BIGINT UNSIGNED NOT NULL,
    token_number INT UNSIGNED NOT NULL,
    public_code CHAR(32) NOT NULL,
    status ENUM('waiting', 'called', 'served', 'skipped') NOT NULL DEFAULT 'waiting',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    called_at DATETIME NULL,
    served_at DATETIME NULL,
    CONSTRAINT fk_queue_tokens_queue FOREIGN KEY (queue_id) REFERENCES queues(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_queue_token_number (queue_id, token_number),
    UNIQUE KEY uq_queue_public_code (public_code),
    KEY idx_queue_status_number (queue_id, status, token_number),
    KEY idx_queue_created (queue_id, created_at)
) ENGINE=InnoDB;

INSERT IGNORE INTO queues (name, token_prefix, last_token_number, is_active) VALUES
('General Enquiry', 'G', 0, 1),
('Admissions', 'A', 0, 1),
('Accounts & Fees', 'F', 0, 1);
