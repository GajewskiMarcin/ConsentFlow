CREATE TABLE IF NOT EXISTS `PREFIX_consentflow_log` (
    `id_consent_log` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_customer` INT UNSIGNED DEFAULT NULL,
    `id_guest` INT UNSIGNED DEFAULT NULL,
    `id_shop` INT UNSIGNED NOT NULL DEFAULT 1,
    `consent_necessary` TINYINT(1) NOT NULL DEFAULT 1,
    `consent_analytics` TINYINT(1) NOT NULL DEFAULT 0,
    `consent_marketing` TINYINT(1) NOT NULL DEFAULT 0,
    `consent_functional` TINYINT(1) NOT NULL DEFAULT 0,
    `action` ENUM('accept_all','reject_all','custom','update','revoke') NOT NULL,
    `policy_version` INT UNSIGNED NOT NULL DEFAULT 1,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(512) DEFAULT NULL,
    `ab_variant` CHAR(1) DEFAULT NULL,
    `date_add` DATETIME NOT NULL,
    PRIMARY KEY (`id_consent_log`),
    KEY `idx_customer` (`id_customer`),
    KEY `idx_guest` (`id_guest`),
    KEY `idx_date` (`date_add`),
    KEY `idx_shop` (`id_shop`),
    KEY `idx_ip_date` (`ip_address`, `date_add`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_consentflow_cookie_definition` (
    `id_cookie_def` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `category` VARCHAR(32) NOT NULL,
    `cookie_name` VARCHAR(128) NOT NULL,
    `provider` VARCHAR(128) DEFAULT NULL,
    `duration` VARCHAR(64) DEFAULT NULL,
    `id_shop` INT UNSIGNED NOT NULL DEFAULT 1,
    `position` INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id_cookie_def`),
    KEY `idx_category` (`category`),
    KEY `idx_shop` (`id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_consentflow_cookie_definition_lang` (
    `id_cookie_def` INT UNSIGNED NOT NULL,
    `id_lang` INT UNSIGNED NOT NULL,
    `description` VARCHAR(512) DEFAULT NULL,
    PRIMARY KEY (`id_cookie_def`, `id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
