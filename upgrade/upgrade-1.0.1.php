<?php
/**
 * ConsentFlow upgrade 1.0.0 → 1.0.1
 *
 * Registers displayHeader hook so Google Consent Mode v2 defaults
 * are emitted BEFORE GTM loads. Forces ConsentFlow ahead of TagPilot
 * in the hook position list so `gtag('consent','default', ...)` runs
 * before the GTM bootstrap snippet.
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_0_1(ConsentFlow $module): bool
{
    if (!$module->registerHook('displayHeader')) {
        return false;
    }

    $hookId = (int) Hook::getIdByName('displayHeader');
    if ($hookId <= 0) {
        return true;
    }

    $shopIds = Shop::getShops(true, null, true);
    if (empty($shopIds)) {
        $shopIds = [(int) Context::getContext()->shop->id];
    }

    foreach ($shopIds as $shopId) {
        $modules = Hook::getModulesFromHook($hookId, (int) $shopId);
        if (!is_array($modules) || empty($modules)) {
            continue;
        }

        $ordered = [];
        foreach ($modules as $m) {
            if ((int) $m['id_module'] === (int) $module->id) {
                continue;
            }
            $ordered[] = (int) $m['id_module'];
        }
        array_unshift($ordered, (int) $module->id);

        foreach ($ordered as $position => $moduleId) {
            Db::getInstance()->update(
                'hook_module',
                ['position' => $position + 1],
                'id_hook = ' . $hookId . ' AND id_module = ' . $moduleId . ' AND id_shop = ' . (int) $shopId
            );
        }
    }

    return true;
}
