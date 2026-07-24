<?php
/**
 * ConsentFlow — Cookie Consent Module for PrestaShop
 *
 * @author    Flavor
 * @copyright Flavor
 * @license   AFL-3.0
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

class ConsentFlow extends Module
{
    /** @var array Default cookie definitions pre-populated on install */
    private const DEFAULT_COOKIES = [
        ['category' => 'necessary', 'cookie_name' => 'PrestaShop-*', 'provider' => 'PrestaShop', 'duration' => 'Session', 'description_pl' => 'Ciasteczka sesyjne wymagane do działania sklepu.', 'description_en' => 'Session cookies required for the store to function.'],
        ['category' => 'necessary', 'cookie_name' => 'flavor_cookie_consent', 'provider' => 'ConsentFlow', 'duration' => '1 rok', 'description_pl' => 'Przechowuje Twoje wybory dotyczące zgód na cookies.', 'description_en' => 'Stores your cookie consent choices.'],
        ['category' => 'analytics', 'cookie_name' => '_ga', 'provider' => 'Google Analytics', 'duration' => '2 lata', 'description_pl' => 'Rozróżnia użytkowników w Google Analytics.', 'description_en' => 'Distinguishes users in Google Analytics.'],
        ['category' => 'analytics', 'cookie_name' => '_ga_*', 'provider' => 'Google Analytics', 'duration' => '2 lata', 'description_pl' => 'Utrzymuje stan sesji w Google Analytics 4.', 'description_en' => 'Maintains session state in Google Analytics 4.'],
        ['category' => 'analytics', 'cookie_name' => '_gid', 'provider' => 'Google Analytics', 'duration' => '24 godziny', 'description_pl' => 'Rozróżnia użytkowników w ciągu 24h.', 'description_en' => 'Distinguishes users within 24 hours.'],
        ['category' => 'marketing', 'cookie_name' => '_gcl_au', 'provider' => 'Google Ads', 'duration' => '90 dni', 'description_pl' => 'Przechowuje dane konwersji Google Ads.', 'description_en' => 'Stores Google Ads conversion data.'],
        ['category' => 'marketing', 'cookie_name' => '_fbp', 'provider' => 'Meta Pixel', 'duration' => '90 dni', 'description_pl' => 'Identyfikuje przeglądarkę dla reklam Meta.', 'description_en' => 'Identifies browser for Meta advertising.'],
        ['category' => 'marketing', 'cookie_name' => 'IDE', 'provider' => 'Google DoubleClick', 'duration' => '1 rok', 'description_pl' => 'Służy do targetowania i mierzenia reklam.', 'description_en' => 'Used for ad targeting and measurement.'],
    ];

    /** @var array Default configuration values */
    private const DEFAULT_CONFIG = [
        'CONSENTFLOW_ENABLED' => '0',
        'CONSENTFLOW_POLICY_VERSION' => '1',
        'CONSENTFLOW_COOKIE_LIFETIME' => '365',
        'CONSENTFLOW_BANNER_POSITION' => 'bottom',
        'CONSENTFLOW_SHOW_FLOAT_BTN' => '1',
        'CONSENTFLOW_FLOAT_BTN_POSITION' => 'bottom-left',
        'CONSENTFLOW_LOG_CONSENT' => '1',
        'CONSENTFLOW_LOG_RETENTION' => '730',
        'CONSENTFLOW_GOOGLE_CONSENT' => '1',
        'CONSENTFLOW_SHOW_FUNCTIONAL' => '0',
        'CONSENTFLOW_PRIVACY_CMS_ID' => '0',
        // Appearance
        'CONSENTFLOW_COLOR_PRIMARY' => '#2563eb',
        'CONSENTFLOW_COLOR_PRIMARY_HOVER' => '#1d4ed8',
        'CONSENTFLOW_COLOR_BG' => '#ffffff',
        'CONSENTFLOW_COLOR_TEXT' => '#1f2937',
        'CONSENTFLOW_COLOR_BTN_TEXT' => '#ffffff',
        'CONSENTFLOW_BORDER_RADIUS' => '8',
        // Iframe blocking
        'CONSENTFLOW_IFRAME_BLOCKING' => '0',
        // A/B testing
        'CONSENTFLOW_AB_ENABLED' => '0',
        'CONSENTFLOW_AB_VARIANT_B_POSITION' => 'center',
        'CONSENTFLOW_AB_SPLIT' => '50',
    ];

    /** @var array Default multilingual text keys */
    private const TEXT_KEYS = [
        'CONSENTFLOW_BANNER_TITLE',
        'CONSENTFLOW_BANNER_TEXT',
        'CONSENTFLOW_BTN_ACCEPT_ALL',
        'CONSENTFLOW_BTN_REJECT_ALL',
        'CONSENTFLOW_BTN_PREFERENCES',
        'CONSENTFLOW_BTN_SAVE',
        'CONSENTFLOW_CAT_NECESSARY_TITLE',
        'CONSENTFLOW_CAT_NECESSARY_DESC',
        'CONSENTFLOW_CAT_ANALYTICS_TITLE',
        'CONSENTFLOW_CAT_ANALYTICS_DESC',
        'CONSENTFLOW_CAT_MARKETING_TITLE',
        'CONSENTFLOW_CAT_MARKETING_DESC',
        'CONSENTFLOW_CAT_FUNCTIONAL_TITLE',
        'CONSENTFLOW_CAT_FUNCTIONAL_DESC',
        'CONSENTFLOW_FLOAT_BTN_LABEL',
        'CONSENTFLOW_PRIVACY_LINK_TEXT',
        'CONSENTFLOW_IFRAME_PLACEHOLDER_TEXT',
        'CONSENTFLOW_IFRAME_PLACEHOLDER_BTN',
        'CONSENTFLOW_CAT_ALWAYS_ACTIVE',
        'CONSENTFLOW_TABLE_COOKIE',
        'CONSENTFLOW_TABLE_PROVIDER',
        'CONSENTFLOW_TABLE_DURATION',
        'CONSENTFLOW_TABLE_DESCRIPTION',
    ];

    /** @var array Default texts: key => [pl, en] */
    private const DEFAULT_TEXTS = [
        'CONSENTFLOW_BANNER_TITLE' => ['Szanujemy Twoją prywatność', 'We respect your privacy'],
        'CONSENTFLOW_BANNER_TEXT' => ['Używamy plików cookies, aby zapewnić najlepszą jakość korzystania z naszej strony. Wybierz, które cookies chcesz zaakceptować.', 'We use cookies to ensure the best experience on our website. Choose which cookies you want to accept.'],
        'CONSENTFLOW_BTN_ACCEPT_ALL' => ['Akceptuję wszystkie', 'Accept all'],
        'CONSENTFLOW_BTN_REJECT_ALL' => ['Odrzuć wszystkie', 'Reject all'],
        'CONSENTFLOW_BTN_PREFERENCES' => ['Ustawienia', 'Preferences'],
        'CONSENTFLOW_BTN_SAVE' => ['Zapisz preferencje', 'Save preferences'],
        'CONSENTFLOW_CAT_NECESSARY_TITLE' => ['Niezbędne', 'Necessary'],
        'CONSENTFLOW_CAT_NECESSARY_DESC' => ['Wymagane do działania strony. Nie można ich wyłączyć.', 'Required for the website to function. Cannot be disabled.'],
        'CONSENTFLOW_CAT_ANALYTICS_TITLE' => ['Analityczne', 'Analytics'],
        'CONSENTFLOW_CAT_ANALYTICS_DESC' => ['Pomagają nam zrozumieć, jak użytkownicy korzystają ze strony.', 'Help us understand how visitors use the website.'],
        'CONSENTFLOW_CAT_MARKETING_TITLE' => ['Marketingowe', 'Marketing'],
        'CONSENTFLOW_CAT_MARKETING_DESC' => ['Służą do wyświetlania spersonalizowanych reklam.', 'Used to display personalized advertisements.'],
        'CONSENTFLOW_CAT_FUNCTIONAL_TITLE' => ['Funkcjonalne', 'Functional'],
        'CONSENTFLOW_CAT_FUNCTIONAL_DESC' => ['Zapamiętują Twoje preferencje, np. język i walutę.', 'Remember your preferences like language and currency.'],
        'CONSENTFLOW_FLOAT_BTN_LABEL' => ['Ustawienia cookies', 'Cookie settings'],
        'CONSENTFLOW_PRIVACY_LINK_TEXT' => ['Polityka prywatności', 'Privacy policy'],
        'CONSENTFLOW_IFRAME_PLACEHOLDER_TEXT' => ['Ta treść wymaga zgody na cookies marketingowe.', 'This content requires marketing cookie consent.'],
        'CONSENTFLOW_IFRAME_PLACEHOLDER_BTN' => ['Zaakceptuj i wyświetl', 'Accept and show'],
        'CONSENTFLOW_CAT_ALWAYS_ACTIVE' => ['Zawsze aktywne', 'Always active'],
        'CONSENTFLOW_TABLE_COOKIE' => ['Ciasteczko', 'Cookie'],
        'CONSENTFLOW_TABLE_PROVIDER' => ['Dostawca', 'Provider'],
        'CONSENTFLOW_TABLE_DURATION' => ['Czas', 'Duration'],
        'CONSENTFLOW_TABLE_DESCRIPTION' => ['Opis', 'Description'],
    ];

    public function __construct()
    {
        $this->name = 'consentflow';
        $this->tab = 'front_office_features';
        $this->version = '1.0.3';
        $this->author = 'Flavor';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('ConsentFlow — Cookie Consent', [], 'Modules.Consentflow.Admin');
        $this->description = $this->trans('GDPR/ePrivacy compliant cookie consent module with Google Consent Mode v2 support.', [], 'Modules.Consentflow.Admin');
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => '9.99.99'];
    }

    /**
     * Redirect to Symfony configuration route
     */
    public function getContent(): string
    {
        $route = $this->get('router')->generate('consentflow_configuration');
        Tools::redirectAdmin($route);

        return ''; // Never reached, but satisfies return type
    }

    /* ------------------------------------------------------------------
     *  INSTALL / UNINSTALL
     * ------------------------------------------------------------------ */

    public function install(): bool
    {
        return parent::install()
            && $this->executeSqlFile('install')
            && $this->installConfiguration()
            && $this->installTexts()
            && $this->installDefaultCookies()
            && $this->installTab()
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayAfterBodyOpeningTag')
            && $this->registerHook('actionFrontControllerSetMedia')
            && $this->registerHook('actionDeleteGDPRCustomer')
            && $this->registerHook('actionExportGDPRData');
    }

    public function uninstall(): bool
    {
        return $this->uninstallTab()
            && $this->executeSqlFile('uninstall')
            && $this->uninstallConfiguration()
            && parent::uninstall();
    }

    /* ------------------------------------------------------------------
     *  HOOKS — Frontend
     * ------------------------------------------------------------------ */

    /**
     * Emit Google Consent Mode v2 defaults inline in <head>, BEFORE GTM loads.
     * Reads current consent from cookie (set by consentflow.js on prior visits)
     * and pushes gtag('consent','default', ...) so GTM starts with correct state.
     */
    public function hookDisplayHeader(array $params): string
    {
        if (!Configuration::get('CONSENTFLOW_ENABLED')) {
            return '';
        }
        if (!(bool) Configuration::get('CONSENTFLOW_GOOGLE_CONSENT')) {
            return '';
        }

        $showFunctional = (bool) Configuration::get('CONSENTFLOW_SHOW_FUNCTIONAL');
        $analytics = false;
        $marketing = false;
        $functional = !$showFunctional;

        $raw = $_COOKIE['flavor_cookie_consent'] ?? null;
        if (is_string($raw) && $raw !== '') {
            $data = json_decode($raw, true);
            if (is_array($data) && !empty($data['necessary'])) {
                $analytics = !empty($data['analytics']);
                $marketing = !empty($data['marketing']);
                $functional = !empty($data['functional']) || !$showFunctional;
            }
        }

        $signals = [
            'analytics_storage' => $analytics ? 'granted' : 'denied',
            'ad_storage' => $marketing ? 'granted' : 'denied',
            'ad_user_data' => $marketing ? 'granted' : 'denied',
            'ad_personalization' => $marketing ? 'granted' : 'denied',
            'personalization_storage' => $functional ? 'granted' : 'denied',
            'functionality_storage' => 'granted',
            'security_storage' => 'granted',
            'wait_for_update' => 500,
        ];

        $json = json_encode($signals, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

        // ads_data_redaction + url_passthrough support Advanced Consent Mode:
        // cookieless pings for denied users + gclid passthrough via URL when ad_storage=denied.
        return '<script>window.dataLayer=window.dataLayer||[];'
            . 'function gtag(){dataLayer.push(arguments);}'
            . 'gtag("set","ads_data_redaction",true);'
            . 'gtag("set","url_passthrough",true);'
            . 'gtag("consent","default",' . $json . ');'
            . '</script>';
    }

    /**
     * Inject inline <script> with configuration JSON for frontend JS.
     * This fires right after <body> so the config is available before consentflow.js loads.
     */
    public function hookDisplayAfterBodyOpeningTag(array $params): string
    {
        if (!Configuration::get('CONSENTFLOW_ENABLED')) {
            return '';
        }

        $langId = (int) $this->context->language->id;
        $shopId = (int) $this->context->shop->id;

        // Build texts for current language
        $texts = [];
        foreach (self::TEXT_KEYS as $key) {
            $texts[$key] = Configuration::get($key, $langId);
        }

        // Structural labels (cookie table headers, "Always active" badge) added
        // in 1.0.3 — fall back to bundled defaults when the DB value is still
        // empty, so they render translated even before the upgrade has run.
        $iso = strtolower((string) $this->context->language->iso_code);
        foreach (['CONSENTFLOW_CAT_ALWAYS_ACTIVE', 'CONSENTFLOW_TABLE_COOKIE', 'CONSENTFLOW_TABLE_PROVIDER', 'CONSENTFLOW_TABLE_DURATION', 'CONSENTFLOW_TABLE_DESCRIPTION'] as $key) {
            if ((string) ($texts[$key] ?? '') === '' && isset(self::DEFAULT_TEXTS[$key])) {
                $texts[$key] = ($iso === 'pl') ? self::DEFAULT_TEXTS[$key][0] : self::DEFAULT_TEXTS[$key][1];
            }
        }

        // Build cookie definitions
        $cookies = $this->getCookieDefinitions($langId, $shopId);

        // Privacy policy URL
        $privacyCmsId = (int) Configuration::get('CONSENTFLOW_PRIVACY_CMS_ID');
        $privacyUrl = '';
        if ($privacyCmsId > 0) {
            $privacyUrl = $this->context->link->getCMSLink($privacyCmsId);
        }

        // A/B test: determine variant for this visitor (sticky via cf_ab_variant cookie).
        // Random assignment only on first visit — persisted client-side so the same user
        // always sees the same variant across page loads (otherwise A/B stats are noise).
        $abEnabled = (bool) Configuration::get('CONSENTFLOW_AB_ENABLED');
        $abVariant = 'A';
        if ($abEnabled) {
            $existing = $_COOKIE['cf_ab_variant'] ?? '';
            if ($existing === 'A' || $existing === 'B') {
                $abVariant = $existing;
            } else {
                $split = (int) Configuration::get('CONSENTFLOW_AB_SPLIT');
                $abVariant = (mt_rand(1, 100) <= $split) ? 'A' : 'B';
            }
        }
        $bannerPosition = Configuration::get('CONSENTFLOW_BANNER_POSITION');
        if ($abEnabled && $abVariant === 'B') {
            $bannerPosition = Configuration::get('CONSENTFLOW_AB_VARIANT_B_POSITION');
        }

        $config = [
            'enabled' => true,
            'policyVersion' => (int) Configuration::get('CONSENTFLOW_POLICY_VERSION'),
            'cookieLifetime' => (int) Configuration::get('CONSENTFLOW_COOKIE_LIFETIME'),
            'bannerPosition' => $bannerPosition,
            'showFloatBtn' => (bool) Configuration::get('CONSENTFLOW_SHOW_FLOAT_BTN'),
            'floatBtnPosition' => Configuration::get('CONSENTFLOW_FLOAT_BTN_POSITION'),
            'googleConsent' => (bool) Configuration::get('CONSENTFLOW_GOOGLE_CONSENT'),
            'showFunctional' => (bool) Configuration::get('CONSENTFLOW_SHOW_FUNCTIONAL'),
            'logConsent' => (bool) Configuration::get('CONSENTFLOW_LOG_CONSENT'),
            'consentAjaxUrl' => $this->context->link->getModuleLink('consentflow', 'consent'),
            'privacyUrl' => $privacyUrl,
            'texts' => $texts,
            'cookies' => $cookies,
            // Appearance
            'colors' => [
                'primary' => Configuration::get('CONSENTFLOW_COLOR_PRIMARY') ?: '#2563eb',
                'primaryHover' => Configuration::get('CONSENTFLOW_COLOR_PRIMARY_HOVER') ?: '#1d4ed8',
                'bg' => Configuration::get('CONSENTFLOW_COLOR_BG') ?: '#ffffff',
                'text' => Configuration::get('CONSENTFLOW_COLOR_TEXT') ?: '#1f2937',
                'btnText' => Configuration::get('CONSENTFLOW_COLOR_BTN_TEXT') ?: '#ffffff',
                'borderRadius' => (int) (Configuration::get('CONSENTFLOW_BORDER_RADIUS') ?: 8),
            ],
            // Iframe blocking
            'iframeBlocking' => (bool) Configuration::get('CONSENTFLOW_IFRAME_BLOCKING'),
            // A/B
            'abVariant' => $abVariant,
            'abEnabled' => $abEnabled,
        ];

        $json = json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

        $script = 'window.__consentFlowConfig=' . $json . ';';
        if ($abEnabled) {
            // Persist A/B variant client-side (90 days). Re-runs noop on subsequent loads
            // because PHP already read it back from the cookie on this request.
            $script .= 'if(!document.cookie.match(/(?:^|; )cf_ab_variant=/)){'
                . 'document.cookie="cf_ab_variant=' . $abVariant
                . '; max-age=7776000; path=/; SameSite=Lax"'
                . '+(location.protocol=="https:"?"; Secure":"");}';
        }

        return '<script>' . $script . '</script>';
    }

    /**
     * Register frontend CSS and JS assets.
     */
    public function hookActionFrontControllerSetMedia(): void
    {
        if (!Configuration::get('CONSENTFLOW_ENABLED')) {
            return;
        }

        $this->context->controller->registerStylesheet(
            'consentflow-css',
            'modules/' . $this->name . '/views/css/consentflow.css',
            ['media' => 'all', 'priority' => 10]
        );

        $this->context->controller->registerJavascript(
            'consentflow-js',
            'modules/' . $this->name . '/views/js/consentflow.js',
            ['position' => 'bottom', 'priority' => 10, 'attributes' => 'defer']
        );
    }

    /* ------------------------------------------------------------------
     *  HOOKS — GDPR
     * ------------------------------------------------------------------ */

    /**
     * Delete consent logs when customer requests GDPR data deletion.
     */
    public function hookActionDeleteGDPRCustomer(array $params): void
    {
        $customerId = (int) ($params['id_customer'] ?? 0);
        if ($customerId) {
            Db::getInstance()->delete('consentflow_log', 'id_customer = ' . $customerId);
        }
    }

    /**
     * Export consent logs when customer requests GDPR data export.
     */
    public function hookActionExportGDPRData(array $params): string
    {
        $customerId = (int) ($params['id_customer'] ?? 0);
        if (!$customerId) {
            return '';
        }

        $logs = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'consentflow_log`
             WHERE id_customer = ' . $customerId . '
             ORDER BY date_add DESC'
        );

        if (empty($logs)) {
            return '';
        }

        $html = '<h3>ConsentFlow — Cookie Consent Logs</h3>';
        $html .= '<table border="1" cellpadding="5" cellspacing="0">';
        $html .= '<tr><th>Date</th><th>Action</th><th>Analytics</th><th>Marketing</th><th>Functional</th><th>Policy Version</th></tr>';

        foreach ($logs as $log) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($log['date_add']) . '</td>';
            $html .= '<td>' . htmlspecialchars($log['action']) . '</td>';
            $html .= '<td>' . ($log['consent_analytics'] ? 'Yes' : 'No') . '</td>';
            $html .= '<td>' . ($log['consent_marketing'] ? 'Yes' : 'No') . '</td>';
            $html .= '<td>' . ($log['consent_functional'] ? 'Yes' : 'No') . '</td>';
            $html .= '<td>' . (int) $log['policy_version'] . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';

        return $html;
    }

    /* ------------------------------------------------------------------
     *  PRIVATE — Installation helpers
     * ------------------------------------------------------------------ */

    private function executeSqlFile(string $name): bool
    {
        $file = dirname(__FILE__) . '/sql/' . $name . '.sql';
        if (!file_exists($file)) {
            return false;
        }

        $sql = str_replace('PREFIX_', _DB_PREFIX_, file_get_contents($file));
        $statements = array_filter(array_map('trim', explode(';', $sql)));

        foreach ($statements as $statement) {
            if (!empty($statement) && !Db::getInstance()->execute($statement)) {
                return false;
            }
        }

        return true;
    }

    private function installConfiguration(): bool
    {
        foreach (self::DEFAULT_CONFIG as $key => $value) {
            if (!Configuration::updateValue($key, $value)) {
                return false;
            }
        }

        return true;
    }

    private function installTexts(): bool
    {
        $languages = Language::getLanguages(false);

        foreach (self::DEFAULT_TEXTS as $key => $defaults) {
            $values = [];
            foreach ($languages as $lang) {
                $iso = strtolower($lang['iso_code']);
                // Use Polish text for PL, English for everything else
                $values[(int) $lang['id_lang']] = ($iso === 'pl') ? $defaults[0] : $defaults[1];
            }
            if (!Configuration::updateValue($key, $values)) {
                return false;
            }
        }

        return true;
    }

    private function installDefaultCookies(): bool
    {
        $shopId = (int) $this->context->shop->id;
        $languages = Language::getLanguages(false);
        $db = Db::getInstance();

        foreach (self::DEFAULT_COOKIES as $pos => $cookie) {
            $db->insert('consentflow_cookie_definition', [
                'category' => pSQL($cookie['category']),
                'cookie_name' => pSQL($cookie['cookie_name']),
                'provider' => pSQL($cookie['provider']),
                'duration' => pSQL($cookie['duration']),
                'id_shop' => $shopId,
                'position' => $pos,
            ]);

            $defId = (int) $db->Insert_ID();

            foreach ($languages as $lang) {
                $iso = strtolower($lang['iso_code']);
                $desc = ($iso === 'pl') ? $cookie['description_pl'] : $cookie['description_en'];
                $db->insert('consentflow_cookie_definition_lang', [
                    'id_cookie_def' => $defId,
                    'id_lang' => (int) $lang['id_lang'],
                    'description' => pSQL($desc),
                ]);
            }
        }

        return true;
    }

    private function installTab(): bool
    {
        // Check if parent "Secret Sauce" tab exists (shared with TagPilot)
        $parentId = (int) Tab::getIdFromClassName('AdminSecretSauce');
        if (!$parentId) {
            // Create the parent tab
            $parentTab = new Tab();
            $parentTab->active = 1;
            $parentTab->class_name = 'AdminSecretSauce';
            $parentTab->name = [];
            foreach (Language::getLanguages(true) as $lang) {
                $parentTab->name[$lang['id_lang']] = 'Secret Sauce';
            }
            $parentTab->icon = 'science';
            $parentTab->id_parent = (int) Tab::getIdFromClassName('IMPROVE');
            $parentTab->module = '';
            $parentTab->save();
            $parentId = (int) $parentTab->id;
        }

        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = 'AdminConsentFlow';
        $tab->route_name = 'consentflow_configuration';
        $tab->name = [];
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'ConsentFlow';
        }
        $tab->icon = 'cookie';
        $tab->id_parent = $parentId;
        $tab->module = $this->name;

        return (bool) $tab->save();
    }

    private function uninstallTab(): bool
    {
        $tabId = (int) Tab::getIdFromClassName('AdminConsentFlow');
        if ($tabId) {
            $tab = new Tab($tabId);
            $tab->delete();
        }

        return true;
    }

    private function uninstallConfiguration(): bool
    {
        foreach (array_keys(self::DEFAULT_CONFIG) as $key) {
            Configuration::deleteByName($key);
        }
        foreach (self::TEXT_KEYS as $key) {
            Configuration::deleteByName($key);
        }

        return true;
    }

    /* ------------------------------------------------------------------
     *  PUBLIC — Helpers used by admin controller
     * ------------------------------------------------------------------ */

    /**
     * Get cookie definitions grouped by category.
     */
    public function getCookieDefinitions(int $langId, int $shopId): array
    {
        $sql = 'SELECT cd.*, cdl.description
                FROM `' . _DB_PREFIX_ . 'consentflow_cookie_definition` cd
                LEFT JOIN `' . _DB_PREFIX_ . 'consentflow_cookie_definition_lang` cdl
                    ON cd.id_cookie_def = cdl.id_cookie_def AND cdl.id_lang = ' . $langId . '
                WHERE cd.id_shop = ' . $shopId . '
                ORDER BY cd.category ASC, cd.position ASC';

        $rows = Db::getInstance()->executeS($sql);
        if (!$rows) {
            return [];
        }

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['category']][] = $row;
        }

        return $grouped;
    }

    /**
     * Get all cookie definitions as flat list.
     */
    public function getCookieDefinitionsFlat(int $langId, int $shopId): array
    {
        $sql = 'SELECT cd.*, cdl.description
                FROM `' . _DB_PREFIX_ . 'consentflow_cookie_definition` cd
                LEFT JOIN `' . _DB_PREFIX_ . 'consentflow_cookie_definition_lang` cdl
                    ON cd.id_cookie_def = cdl.id_cookie_def AND cdl.id_lang = ' . $langId . '
                WHERE cd.id_shop = ' . $shopId . '
                ORDER BY cd.position ASC';

        return Db::getInstance()->executeS($sql) ?: [];
    }

    /**
     * Anonymize IP address — zero last octet (IPv4) or last 80 bits (IPv6).
     */
    public static function anonymizeIp(string $ip): string
    {
        if (str_contains($ip, ':')) {
            // IPv6: zero last 80 bits (5 groups)
            $parts = explode(':', $ip);
            $len = count($parts);
            for ($i = max(0, $len - 5); $i < $len; $i++) {
                $parts[$i] = '0';
            }
            return implode(':', $parts);
        }

        // IPv4: zero last octet
        return preg_replace('/\.\d+$/', '.0', $ip);
    }

    /**
     * Purge old consent logs.
     */
    public function purgeOldLogs(): int
    {
        $retention = (int) Configuration::get('CONSENTFLOW_LOG_RETENTION');
        if ($retention <= 0) {
            return 0;
        }

        $db = Db::getInstance();
        $db->execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'consentflow_log`
             WHERE date_add < DATE_SUB(NOW(), INTERVAL ' . $retention . ' DAY)'
        );

        return (int) $db->Affected_Rows();
    }
}
