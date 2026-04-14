-- Migration 034: Expenses (Ausgaben) table
CREATE TABLE IF NOT EXISTS `expenses` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `date`        DATE         NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `category`    VARCHAR(100) NOT NULL DEFAULT 'Sonstiges',
    `supplier`    VARCHAR(255) NULL,
    `amount_net`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `tax_rate`    DECIMAL(5,2)  NOT NULL DEFAULT 19.00,
    `amount_gross` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `notes`       TEXT NULL,
    `receipt_file` VARCHAR(255) NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_date` (`date`),
    INDEX `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
