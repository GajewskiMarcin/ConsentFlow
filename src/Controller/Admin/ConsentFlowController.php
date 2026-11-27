<?php
/**
 * ConsentFlow — Admin Symfony Controller
 *
 * @author    Flavor
 * @copyright Flavor
 * @license   AFL-3.0
 */

declare(strict_types=1);

namespace Flavor\ConsentFlow\Controller\Admin;

use Configuration;
use Db;
use Language;
use Module;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConsentFlowController extends FrameworkBundleAdminController
{
    /**
     * Main configuration page — handles GET (display) and POST (save settings/texts).
     */
    public function indexAction(Request $request): Response
    {
        $module = Module::getInstanceByName('consentflow');

        // Auto-purge old logs on admin load
        $module->purgeOldLogs();

        // Handle form submissions
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('consentflow', $request->request->get('_token'))) {
                $this->addFlash('error', $this->trans('Invalid security token. Please try again.', 'Admin.Notifications.Error'));
                return $this->redirectToRoute('consentflow_configuration');
            }

            $tab = $request->request->get('cf_tab', 'settings');

            if ($tab === 'settings') {
                $this->saveSettings($request);
                $this->addFlash('success', $this->trans('Settings saved.', 'Modules.Consentflow.Admin'));
            } elseif ($tab === 'texts') {
                $this->saveTexts($request);
                $this->addFlash('success', $this->trans('Texts saved.', 'Modules.Consentflow.Admin'));
            }

            return $this->redirectToRoute('consentflow_configuration', ['tab' => $tab]);
        }

        $langId = (int) $this->getContext()->language->id;
        $shopId = (int) $this->getContext()->shop->id;
        $languages = Language::getLanguages(true);

        // Load current settings
        $settings = [];
        $configKeys = [
            'CONSENTFLOW_ENABLED', 'CONSENTFLOW_POLICY_VERSION', 'CONSENTFLOW_COOKIE_LIFETIME',
            'CONSENTFLOW_BANNER_POSITION', 'CONSENTFLOW_SHOW_FLOAT_BTN', 'CONSENTFLOW_FLOAT_BTN_POSITION',
            'CONSENTFLOW_LOG_CONSENT', 'CONSENTFLOW_LOG_RETENTION', 'CONSENTFLOW_GOOGLE_CONSENT',
            'CONSENTFLOW_SHOW_FUNCTIONAL', 'CONSENTFLOW_PRIVACY_CMS_ID',
            // Appearance
            'CONSENTFLOW_COLOR_PRIMARY', 'CONSENTFLOW_COLOR_PRIMARY_HOVER',
            'CONSENTFLOW_COLOR_BG', 'CONSENTFLOW_COLOR_TEXT', 'CONSENTFLOW_COLOR_BTN_TEXT',
            'CONSENTFLOW_BORDER_RADIUS',
            // Iframe blocking
            'CONSENTFLOW_IFRAME_BLOCKING',
            // A/B testing
            'CONSENTFLOW_AB_ENABLED', 'CONSENTFLOW_AB_VARIANT_B_POSITION', 'CONSENTFLOW_AB_SPLIT',
        ];
        foreach ($configKeys as $key) {
            $settings[$key] = Configuration::get($key);
        }

        // Load multilingual texts
        $texts = [];
        $textKeys = [
            'CONSENTFLOW_BANNER_TITLE', 'CONSENTFLOW_BANNER_TEXT',
            'CONSENTFLOW_BTN_ACCEPT_ALL', 'CONSENTFLOW_BTN_REJECT_ALL',
            'CONSENTFLOW_BTN_PREFERENCES', 'CONSENTFLOW_BTN_SAVE',
            'CONSENTFLOW_CAT_NECESSARY_TITLE', 'CONSENTFLOW_CAT_NECESSARY_DESC',
            'CONSENTFLOW_CAT_ANALYTICS_TITLE', 'CONSENTFLOW_CAT_ANALYTICS_DESC',
            'CONSENTFLOW_CAT_MARKETING_TITLE', 'CONSENTFLOW_CAT_MARKETING_DESC',
            'CONSENTFLOW_CAT_FUNCTIONAL_TITLE', 'CONSENTFLOW_CAT_FUNCTIONAL_DESC',
            'CONSENTFLOW_FLOAT_BTN_LABEL', 'CONSENTFLOW_PRIVACY_LINK_TEXT',
            'CONSENTFLOW_IFRAME_PLACEHOLDER_TEXT', 'CONSENTFLOW_IFRAME_PLACEHOLDER_BTN',
            'CONSENTFLOW_CAT_ALWAYS_ACTIVE',
            'CONSENTFLOW_TABLE_COOKIE', 'CONSENTFLOW_TABLE_PROVIDER',
            'CONSENTFLOW_TABLE_DURATION', 'CONSENTFLOW_TABLE_DESCRIPTION',
        ];
        foreach ($textKeys as $key) {
            foreach ($languages as $lang) {
                $texts[$key][$lang['id_lang']] = Configuration::get($key, (int) $lang['id_lang']);
            }
        }

        // Load cookie definitions with all language descriptions
        $cookieDefs = $module->getCookieDefinitionsFlat($langId, $shopId);
        $cookieDescriptions = $this->getCookieDescriptionsAllLangs($cookieDefs, $languages);

        // Load CMS pages for privacy policy dropdown
        $cmsPages = $this->getCmsPages($langId);

        // Count logs
        $logCount = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'consentflow_log` WHERE id_shop = ' . $shopId
        );

        return $this->render('@Modules/consentflow/views/templates/admin/configuration.html.twig', [
            'settings' => $settings,
            'texts' => $texts,
            'textKeys' => $textKeys,
            'languages' => $languages,
            'currentLangId' => $langId,
            'cookieDefs' => $cookieDefs,
            'cookieDescriptions' => $cookieDescriptions,
            'cmsPages' => $cmsPages,
            'logCount' => $logCount,
            'moduleVersion' => $module->version,
            'moduleAuthor' => $module->author,
            'psVersion' => _PS_VERSION_,
            'phpVersion' => PHP_VERSION,
            'saveCookieUrl' => $this->generateUrl('consentflow_cookie_save'),
            'logsUrl' => $this->generateUrl('consentflow_log_list'),
            'exportLogsUrl' => $this->generateUrl('consentflow_log_export'),
            'purgeLogsUrl' => $this->generateUrl('consentflow_log_purge'),
            'adminCssPath' => _MODULE_DIR_ . 'consentflow/views/css/admin-consentflow.css',
            'quincePromoCssPath' => _MODULE_DIR_ . 'consentflow/views/css/quince-promo.css',
            // Support / About tab
            'githubRepoUrl' => 'https://github.com/GajewskiMarcin/ConsentFlow',
            'githubWikiUrl' => 'https://github.com/GajewskiMarcin/ConsentFlow/wiki',
            'githubIssuesUrl' => 'https://github.com/GajewskiMarcin/ConsentFlow/issues',
            'githubDiscussionsUrl' => 'https://github.com/GajewskiMarcin/ConsentFlow/discussions',
            'kofiUrl' => 'https://ko-fi.com/quinceproject',
            'quinceUrl' => 'https://github.com/quinceproject',
        ]);
    }

    /**
     * Save or update a cookie definition.
     */
    public function saveCookieAction(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('consentflow', $request->request->get('_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token.', 'Admin.Notifications.Error'));
            return $this->redirectToRoute('consentflow_configuration', ['tab' => 'cookies']);
        }

        $id = (int) $request->request->get('id_cookie_def', 0);
        $shopId = (int) $this->getContext()->shop->id;
        $languages = Language::getLanguages(true);
        $db = Db::getInstance();

        // Validate required fields
        $cookieName = trim($request->request->get('cookie_name', ''));
        if (empty($cookieName)) {
            $this->addFlash('error', $this->trans('Cookie name is required.', 'Modules.Consentflow.Admin'));
            return $this->redirectToRoute('consentflow_configuration', ['tab' => 'cookies']);
        }

        // Validate category
        $allowedCategories = ['necessary', 'analytics', 'marketing', 'functional'];
        $category = $request->request->get('category', 'necessary');
        if (!in_array($category, $allowedCategories, true)) {
            $category = 'necessary';
        }

        $data = [
            'category' => pSQL($category),
            'cookie_name' => pSQL($cookieName),
            'provider' => pSQL(trim($request->request->get('provider', ''))),
            'duration' => pSQL(trim($request->request->get('duration', ''))),
            'id_shop' => $shopId,
            'position' => (int) $request->request->get('position', 0),
        ];

        if ($id > 0) {
            // Verify cookie belongs to this shop (multi-shop protection)
            $db->update('consentflow_cookie_definition', $data, 'id_cookie_def = ' . $id . ' AND id_shop = ' . $shopId);
        } else {
            $db->insert('consentflow_cookie_definition', $data);
            $id = (int) $db->Insert_ID();
        }

        // Save multilingual descriptions
        foreach ($languages as $lang) {
            $desc = pSQL($request->request->get('description_' . $lang['id_lang'], ''));
            $exists = $db->getValue(
                'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'consentflow_cookie_definition_lang`
                 WHERE id_cookie_def = ' . $id . ' AND id_lang = ' . (int) $lang['id_lang']
            );

            if ($exists) {
                $db->update('consentflow_cookie_definition_lang', [
                    'description' => $desc,
                ], 'id_cookie_def = ' . $id . ' AND id_lang = ' . (int) $lang['id_lang']);
            } else {
                $db->insert('consentflow_cookie_definition_lang', [
                    'id_cookie_def' => $id,
                    'id_lang' => (int) $lang['id_lang'],
                    'description' => $desc,
                ]);
            }
        }

        $this->addFlash('success', $this->trans('Cookie definition saved.', 'Modules.Consentflow.Admin'));

        return $this->redirectToRoute('consentflow_configuration', ['tab' => 'cookies']);
    }

    /**
     * Delete a cookie definition.
     */
    public function deleteCookieAction(Request $request, int $id): Response
    {
        if (!$this->isCsrfTokenValid('consentflow', $request->request->get('_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token.', 'Admin.Notifications.Error'));
            return $this->redirectToRoute('consentflow_configuration', ['tab' => 'cookies']);
        }

        $shopId = (int) $this->getContext()->shop->id;
        $db = Db::getInstance();

        // Verify cookie belongs to this shop (multi-shop protection)
        $exists = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'consentflow_cookie_definition`
             WHERE id_cookie_def = ' . $id . ' AND id_shop = ' . $shopId
        );

        if ($exists) {
            $db->delete('consentflow_cookie_definition_lang', 'id_cookie_def = ' . $id);
            $db->delete('consentflow_cookie_definition', 'id_cookie_def = ' . $id . ' AND id_shop = ' . $shopId);
            $this->addFlash('success', $this->trans('Cookie definition deleted.', 'Modules.Consentflow.Admin'));
        } else {
            $this->addFlash('error', $this->trans('Cookie definition not found.', 'Modules.Consentflow.Admin'));
        }

        return $this->redirectToRoute('consentflow_configuration', ['tab' => 'cookies']);
    }

    /**
     * Display consent logs as JSON for AJAX loading.
     */
    public function logsAction(Request $request): JsonResponse
    {
        $shopId = (int) $this->getContext()->shop->id;
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 25;
        $offset = ($page - 1) * $perPage;

        $where = 'WHERE cl.id_shop = ' . $shopId;

        // Filters
        $action = $request->query->get('action_filter', '');
        if ($action) {
            $where .= ' AND cl.action = \'' . pSQL($action) . '\'';
        }
        $dateFrom = $request->query->get('date_from', '');
        if ($dateFrom) {
            $where .= ' AND cl.date_add >= \'' . pSQL($dateFrom) . ' 00:00:00\'';
        }
        $dateTo = $request->query->get('date_to', '');
        if ($dateTo) {
            $where .= ' AND cl.date_add <= \'' . pSQL($dateTo) . ' 23:59:59\'';
        }

        $total = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'consentflow_log` cl ' . $where
        );

        $logs = Db::getInstance()->executeS(
            'SELECT cl.*, c.firstname, c.lastname, c.email
             FROM `' . _DB_PREFIX_ . 'consentflow_log` cl
             LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON cl.id_customer = c.id_customer
             ' . $where . '
             ORDER BY cl.date_add DESC
             LIMIT ' . $offset . ', ' . $perPage
        );

        return new JsonResponse([
            'logs' => $logs ?: [],
            'total' => $total,
            'page' => $page,
            'pages' => (int) ceil($total / $perPage),
        ]);
    }

    /**
     * Export consent logs to CSV.
     */
    public function exportLogsAction(Request $request): StreamedResponse
    {
        $shopId = (int) $this->getContext()->shop->id;

        $where = 'WHERE cl.id_shop = ' . $shopId;
        $action = $request->query->get('action_filter', '');
        if ($action) {
            $where .= ' AND cl.action = \'' . pSQL($action) . '\'';
        }
        $dateFrom = $request->query->get('date_from', '');
        if ($dateFrom) {
            $where .= ' AND cl.date_add >= \'' . pSQL($dateFrom) . ' 00:00:00\'';
        }
        $dateTo = $request->query->get('date_to', '');
        if ($dateTo) {
            $where .= ' AND cl.date_add <= \'' . pSQL($dateTo) . ' 23:59:59\'';
        }

        $response = new StreamedResponse(function () use ($where) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Date', 'Customer', 'Action', 'Analytics', 'Marketing', 'Functional', 'Policy Version', 'A/B Variant', 'IP']);

            $logs = Db::getInstance()->executeS(
                'SELECT cl.*, c.firstname, c.lastname, c.email
                 FROM `' . _DB_PREFIX_ . 'consentflow_log` cl
                 LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON cl.id_customer = c.id_customer
                 ' . $where . '
                 ORDER BY cl.date_add DESC'
            );

            foreach ($logs ?: [] as $log) {
                $customer = $log['firstname']
                    ? $log['firstname'] . ' ' . $log['lastname'] . ' (' . $log['email'] . ')'
                    : 'Guest #' . ($log['id_guest'] ?? '?');
                fputcsv($handle, [
                    $log['id_consent_log'],
                    $log['date_add'],
                    $customer,
                    $log['action'],
                    $log['consent_analytics'] ? 'Yes' : 'No',
                    $log['consent_marketing'] ? 'Yes' : 'No',
                    $log['consent_functional'] ? 'Yes' : 'No',
                    $log['policy_version'],
                    $log['ab_variant'] ?? '',
                    $log['ip_address'],
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="consentflow_logs_' . date('Y-m-d') . '.csv"');

        return $response;
    }

    /**
     * Purge old logs.
     */
    public function purgeLogsAction(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('consentflow', $request->request->get('_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token.', 'Admin.Notifications.Error'));
            return $this->redirectToRoute('consentflow_configuration', ['tab' => 'logs']);
        }

        $module = Module::getInstanceByName('consentflow');
        $deleted = $module->purgeOldLogs();

        $this->addFlash('success', $this->trans('Purged %count% old log entries.', 'Modules.Consentflow.Admin', ['%count%' => $deleted]));

        return $this->redirectToRoute('consentflow_configuration', ['tab' => 'logs']);
    }

    /* ------------------------------------------------------------------
     *  PRIVATE — Settings / Texts save
     * ------------------------------------------------------------------ */

    private function saveSettings(Request $request): void
    {
        $toggles = [
            'CONSENTFLOW_ENABLED', 'CONSENTFLOW_SHOW_FLOAT_BTN', 'CONSENTFLOW_LOG_CONSENT',
            'CONSENTFLOW_GOOGLE_CONSENT', 'CONSENTFLOW_SHOW_FUNCTIONAL',
            'CONSENTFLOW_IFRAME_BLOCKING', 'CONSENTFLOW_AB_ENABLED',
        ];
        foreach ($toggles as $key) {
            Configuration::updateValue($key, $request->request->has($key) ? '1' : '0');
        }

        $numbers = [
            'CONSENTFLOW_POLICY_VERSION', 'CONSENTFLOW_COOKIE_LIFETIME',
            'CONSENTFLOW_LOG_RETENTION', 'CONSENTFLOW_PRIVACY_CMS_ID',
            'CONSENTFLOW_BORDER_RADIUS', 'CONSENTFLOW_AB_SPLIT',
        ];
        foreach ($numbers as $key) {
            Configuration::updateValue($key, (string) (int) $request->request->get($key, '0'));
        }

        // Select fields with allowed values
        $allowedValues = [
            'CONSENTFLOW_BANNER_POSITION' => ['bottom', 'top', 'center'],
            'CONSENTFLOW_FLOAT_BTN_POSITION' => ['bottom-left', 'bottom-right'],
            'CONSENTFLOW_AB_VARIANT_B_POSITION' => ['bottom', 'top', 'center'],
        ];
        foreach ($allowedValues as $key => $allowed) {
            $val = $request->request->get($key, $allowed[0]);
            if (!in_array($val, $allowed, true)) {
                $val = $allowed[0];
            }
            Configuration::updateValue($key, $val);
        }

        // Color fields — validate hex format
        $colorKeys = [
            'CONSENTFLOW_COLOR_PRIMARY', 'CONSENTFLOW_COLOR_PRIMARY_HOVER',
            'CONSENTFLOW_COLOR_BG', 'CONSENTFLOW_COLOR_TEXT', 'CONSENTFLOW_COLOR_BTN_TEXT',
        ];
        foreach ($colorKeys as $key) {
            $val = $request->request->get($key, '');
            if (preg_match('/^#[0-9a-fA-F]{6}$/', $val)) {
                Configuration::updateValue($key, $val);
            }
        }
    }

    private function saveTexts(Request $request): void
    {
        $textKeys = [
            'CONSENTFLOW_BANNER_TITLE', 'CONSENTFLOW_BANNER_TEXT',
            'CONSENTFLOW_BTN_ACCEPT_ALL', 'CONSENTFLOW_BTN_REJECT_ALL',
            'CONSENTFLOW_BTN_PREFERENCES', 'CONSENTFLOW_BTN_SAVE',
            'CONSENTFLOW_CAT_NECESSARY_TITLE', 'CONSENTFLOW_CAT_NECESSARY_DESC',
            'CONSENTFLOW_CAT_ANALYTICS_TITLE', 'CONSENTFLOW_CAT_ANALYTICS_DESC',
            'CONSENTFLOW_CAT_MARKETING_TITLE', 'CONSENTFLOW_CAT_MARKETING_DESC',
            'CONSENTFLOW_CAT_FUNCTIONAL_TITLE', 'CONSENTFLOW_CAT_FUNCTIONAL_DESC',
            'CONSENTFLOW_FLOAT_BTN_LABEL', 'CONSENTFLOW_PRIVACY_LINK_TEXT',
            'CONSENTFLOW_IFRAME_PLACEHOLDER_TEXT', 'CONSENTFLOW_IFRAME_PLACEHOLDER_BTN',
            'CONSENTFLOW_CAT_ALWAYS_ACTIVE',
            'CONSENTFLOW_TABLE_COOKIE', 'CONSENTFLOW_TABLE_PROVIDER',
            'CONSENTFLOW_TABLE_DURATION', 'CONSENTFLOW_TABLE_DESCRIPTION',
        ];

        $languages = Language::getLanguages(true);

        foreach ($textKeys as $key) {
            $values = [];
            foreach ($languages as $lang) {
                $values[(int) $lang['id_lang']] = $request->request->get($key . '_' . $lang['id_lang'], '');
            }
            Configuration::updateValue($key, $values);
        }
    }

    /**
     * Get CMS pages for privacy policy dropdown.
     */
    private function getCmsPages(int $langId): array
    {
        return Db::getInstance()->executeS(
            'SELECT c.id_cms, cl.meta_title
             FROM `' . _DB_PREFIX_ . 'cms` c
             LEFT JOIN `' . _DB_PREFIX_ . 'cms_lang` cl ON c.id_cms = cl.id_cms AND cl.id_lang = ' . $langId . '
             WHERE c.active = 1
             ORDER BY cl.meta_title ASC'
        ) ?: [];
    }

    /**
     * Get multilingual descriptions for all cookie definitions.
     * Returns: [ id_cookie_def => [ id_lang => description ] ]
     */
    private function getCookieDescriptionsAllLangs(array $cookieDefs, array $languages): array
    {
        if (empty($cookieDefs)) {
            return [];
        }

        $ids = array_column($cookieDefs, 'id_cookie_def');
        $idList = implode(',', array_map('intval', $ids));

        $rows = Db::getInstance()->executeS(
            'SELECT id_cookie_def, id_lang, description
             FROM `' . _DB_PREFIX_ . 'consentflow_cookie_definition_lang`
             WHERE id_cookie_def IN (' . $idList . ')'
        );

        $result = [];
        foreach ($rows ?: [] as $row) {
            $result[(int) $row['id_cookie_def']][(int) $row['id_lang']] = $row['description'];
        }

        return $result;
    }
}
