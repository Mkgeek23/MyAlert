-- Add renewal configuration columns to alerts table
ALTER TABLE `alerts`
    ADD COLUMN `renewal_mode` ENUM('day_of_month', 'number_of_days') NULL DEFAULT NULL AFTER `default_next_days`,
    ADD COLUMN `renewal_value` INT UNSIGNED NULL DEFAULT NULL AFTER `renewal_mode`,
    ADD COLUMN `count_from_close_date` BOOLEAN NOT NULL DEFAULT TRUE AFTER `renewal_value`;

-- Add 'recurring_renewal' to alert_type ENUM
ALTER TABLE `alerts`
    MODIFY COLUMN `alert_type` ENUM('one_time', 'repeat_until_closed', 'recurring_series', 'recurring_renewal') NOT NULL;
