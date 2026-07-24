<?php
/**
 * ConsentFlow upgrade 1.0.2 → 1.0.3
 *
 * Backfills the multilingual text keys introduced in 1.0.3 for the cookie
 * table headers and the "Always active" badge. Previously these labels were
 * hardcoded in English in the frontend JS; they are now part of the editable
 * Texts system (Texts tab) so they translate on the storefront.
 *
 * Only sets a value when the key is still empty, so it never overwrites a
 * label a shop owner may have already customized.
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_0_3(ConsentFlow $module): bool
{
    // key => [pl, en]
    $defaults = [
        'CONSENTFLOW_CAT_ALWAYS_ACTIVE' => ['Zawsze aktywne', 'Always active'],
        'CONSENTFLOW_TABLE_COOKIE' => ['Ciasteczko', 'Cookie'],
        'CONSENTFLOW_TABLE_PROVIDER' => ['Dostawca', 'Provider'],
        'CONSENTFLOW_TABLE_DURATION' => ['Czas', 'Duration'],
        'CONSENTFLOW_TABLE_DESCRIPTION' => ['Opis', 'Description'],
    ];

    $languages = Language::getLanguages(false);

    foreach ($defaults as $key => $texts) {
        // Skip keys that already have a value in any language (customized).
        $hasValue = false;
        foreach ($languages as $lang) {
            if ((string) Configuration::get($key, (int) $lang['id_lang']) !== '') {
                $hasValue = true;
                break;
            }
        }
        if ($hasValue) {
            continue;
        }

        $values = [];
        foreach ($languages as $lang) {
            $iso = strtolower($lang['iso_code']);
            // Polish text for PL, English for everything else — mirrors install.
            $values[(int) $lang['id_lang']] = ($iso === 'pl') ? $texts[0] : $texts[1];
        }
        Configuration::updateValue($key, $values);
    }

    return true;
}
