<?php
/**
 * ConsentFlow upgrade 1.0.1 → 1.0.2
 *
 * Adds compound index (ip_address, date_add) to consentflow_log so the
 * rate limiter (introduced in 1.0.2) can look up the last log per IP
 * without a full table scan.
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_0_2(ConsentFlow $module): bool
{
    $db = Db::getInstance();
    $table = _DB_PREFIX_ . 'consentflow_log';

    $existing = $db->executeS('SHOW INDEX FROM `' . $table . '` WHERE Key_name = \'idx_ip_date\'');
    if (empty($existing)) {
        $db->execute('ALTER TABLE `' . $table . '` ADD INDEX `idx_ip_date` (`ip_address`, `date_add`)');
    }

    return true;
}
