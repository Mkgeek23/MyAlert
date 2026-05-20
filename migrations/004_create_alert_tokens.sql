CREATE TABLE IF NOT EXISTS `alert_tokens` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `alert_id` INT UNSIGNED NOT NULL,
    `token` CHAR(64) NOT NULL,
    `used` BOOLEAN NOT NULL DEFAULT FALSE,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_alert_tokens_token` (`token`),
    INDEX `idx_tokens_token` (`token`),
    INDEX `idx_tokens_alert` (`alert_id`),
    CONSTRAINT `fk_alert_tokens_alert` FOREIGN KEY (`alert_id`) REFERENCES `alerts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
