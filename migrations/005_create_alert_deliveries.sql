CREATE TABLE IF NOT EXISTS `alert_deliveries` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `alert_id` INT UNSIGNED NOT NULL,
    `status` ENUM('success', 'failed') NOT NULL,
    `http_status_code` INT UNSIGNED NULL,
    `response_body` TEXT NULL,
    `sent_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_deliveries_alert` (`alert_id`),
    INDEX `idx_deliveries_sent` (`sent_at` DESC),
    CONSTRAINT `fk_alert_deliveries_alert` FOREIGN KEY (`alert_id`) REFERENCES `alerts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
