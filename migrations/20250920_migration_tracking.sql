-- Migration tracking table for Genesis Reborn
-- This must be the first migration to establish tracking system
CREATE TABLE IF NOT EXISTS migrations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL UNIQUE,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    checksum VARCHAR(64) NULL COMMENT 'SHA256 hash of file content for integrity',
    INDEX idx_migrations_filename (filename),
    INDEX idx_migrations_applied (applied_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;