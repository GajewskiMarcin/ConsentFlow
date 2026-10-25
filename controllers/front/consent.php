<?php
/**
 * ConsentFlow — Frontend AJAX controller for consent logging.
 *
 * Called via sendBeacon/fetch from consentflow.js.
 * PS token is disabled because sendBeacon cannot send custom headers — abuse is
 * mitigated by three layers: Origin check, per-IP rate limit, global rate limit.
 *
 * @author    Flavor
 * @copyright Flavor
 * @license   AFL-3.0
 */

declare(strict_types=1);

class ConsentFlowConsentModuleFrontController extends ModuleFrontController
{
    /** @var bool */
    public $ajax = true;

    /** @var bool Disable token check — sendBeacon cannot send custom headers/tokens */
    protected $token = false;

    /** Per-IP rate limit window (seconds) — one insert per IP per this window. */
    private const RATE_LIMIT_PER_IP_SECONDS = 2;

    /** Global rate limit — max inserts per minute across all clients. */
    private const RATE_LIMIT_GLOBAL_PER_MINUTE = 1000;

    /** Maximum accepted JSON body size (bytes). Real payload is ~200 B. */
    private const MAX_PAYLOAD_BYTES = 2048;

    public function postProcess(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->ajaxResponse(['success' => false, 'error' => 'Method not allowed'], 405);
            return;
        }

        if (!$this->isOriginAllowed()) {
            $this->ajaxResponse(['success' => false, 'error' => 'Invalid origin'], 403);
            return;
        }

        if (!Configuration::get('CONSENTFLOW_LOG_CONSENT')) {
            $this->ajaxResponse(['success' => true, 'logged' => false]);
            return;
        }

        $input = file_get_contents('php://input');
        if ($input === false || strlen($input) > self::MAX_PAYLOAD_BYTES) {
            $this->ajaxResponse(['success' => false, 'error' => 'Payload too large'], 413);
            return;
        }

        $data = json_decode($input, true);
        if (!$data || !isset($data['action'], $data['consent']) || !is_array($data['consent'])) {
            $this->ajaxResponse(['success' => false, 'error' => 'Invalid payload'], 400);
            return;
        }

        $validActions = ['accept_all', 'reject_all', 'custom', 'update', 'revoke'];
        if (!in_array($data['action'], $validActions, true)) {
            $this->ajaxResponse(['success' => false, 'error' => 'Invalid action'], 400);
            return;
        }

        $ip = ConsentFlow::anonymizeIp(Tools::getRemoteAddr());
        $ipSql = pSQL($ip);
        $db = Db::getInstance();

        // Global circuit breaker — cheap row count over (date_add) index.
        $globalRecent = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'consentflow_log`
             WHERE date_add > DATE_SUB(NOW(), INTERVAL 1 MINUTE)'
        );
        if ($globalRecent >= self::RATE_LIMIT_GLOBAL_PER_MINUTE) {
            $this->ajaxResponse(['success' => true, 'logged' => false, 'reason' => 'global_rate_limit'], 429);
            return;
        }

        // Per-IP rate limit — uses idx_ip_date compound index.
        $lastLog = $db->getValue(
            'SELECT date_add FROM `' . _DB_PREFIX_ . 'consentflow_log`
             WHERE ip_address = \'' . $ipSql . '\'
             ORDER BY date_add DESC LIMIT 1'
        );
        if ($lastLog && (time() - strtotime((string) $lastLog)) < self::RATE_LIMIT_PER_IP_SECONDS) {
            $this->ajaxResponse(['success' => true, 'logged' => false, 'reason' => 'rate_limited'], 429);
            return;
        }

        $customerId = (int) ($this->context->customer->id ?? 0);
        $guestId = (int) ($this->context->cookie->id_guest ?? 0);
        $shopId = (int) $this->context->shop->id;
        $userAgent = pSQL(substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512));

        $customerSql = $customerId > 0 ? $customerId : 'NULL';
        $guestSql = $guestId > 0 ? $guestId : 'NULL';

        $abVariant = isset($data['ab_variant']) && in_array($data['ab_variant'], ['A', 'B'], true)
            ? "'" . $data['ab_variant'] . "'"
            : 'NULL';

        $result = $db->execute(
            'INSERT INTO `' . _DB_PREFIX_ . 'consentflow_log`
             (`id_customer`, `id_guest`, `id_shop`, `consent_necessary`, `consent_analytics`,
              `consent_marketing`, `consent_functional`, `action`, `policy_version`,
              `ip_address`, `user_agent`, `ab_variant`, `date_add`)
             VALUES (' . $customerSql . ', ' . $guestSql . ', ' . $shopId . ', 1,
              ' . (int) !empty($data['consent']['analytics']) . ',
              ' . (int) !empty($data['consent']['marketing']) . ',
              ' . (int) !empty($data['consent']['functional']) . ',
              \'' . pSQL($data['action']) . '\',
              ' . (int) ($data['version'] ?? 1) . ',
              \'' . $ipSql . '\',
              \'' . $userAgent . '\',
              ' . $abVariant . ',
              \'' . date('Y-m-d H:i:s') . '\')'
        );

        $this->ajaxResponse(['success' => (bool) $result, 'logged' => (bool) $result]);
    }

    /**
     * Verify the request originated from one of this PrestaShop's shop domains.
     * Accepts Origin header (preferred — sendBeacon sets it automatically) and
     * falls back to Referer. Rejects requests without either.
     */
    private function isOriginAllowed(): bool
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin === '' || $origin === 'null') {
            $origin = $_SERVER['HTTP_REFERER'] ?? '';
        }
        if ($origin === '') {
            return false;
        }

        $originHost = parse_url($origin, PHP_URL_HOST);
        if (!$originHost) {
            return false;
        }
        $originHost = strtolower($originHost);

        foreach (Shop::getShops(true, null, true) as $shopId) {
            $shop = new Shop((int) $shopId);
            $hosts = array_filter([$shop->domain ?? '', $shop->domain_ssl ?? '']);
            foreach ($hosts as $host) {
                if (strtolower((string) $host) === $originHost) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Send JSON response and exit.
     */
    private function ajaxResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        $this->ajaxRender(json_encode($data));
        exit;
    }
}
