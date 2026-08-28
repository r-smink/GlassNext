<?php
if (!defined('ABSPATH')) exit;

/**
 * GN_Odoo
 *
 * Rechtstreekse Odoo-integratie via JSON-RPC (/jsonrpc), zonder Make.com
 * als tussenlaag. Zoekt een partner op e-mail, maakt er zo nodig een aan,
 * en zet een sale.order met orderregels neer.
 */
class GN_Odoo {

    private static $instance = null;
    private $uid = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_gn_test_odoo_connection', [$this, 'ajax_test_connection']);
    }

    public function ajax_test_connection() {
        check_ajax_referer('gn_test_odoo', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Geen toegang.']);
        }
        $url      = isset($_POST['gn_odoo_url']) ? esc_url_raw(wp_unslash($_POST['gn_odoo_url'])) : '';
        $db       = isset($_POST['gn_odoo_db']) ? sanitize_text_field(wp_unslash($_POST['gn_odoo_db'])) : '';
        $login    = isset($_POST['gn_odoo_login']) ? sanitize_text_field(wp_unslash($_POST['gn_odoo_login'])) : '';
        $api_key  = isset($_POST['gn_odoo_api_key']) ? sanitize_text_field(wp_unslash($_POST['gn_odoo_api_key'])) : '';

        $result = $this->test_connection($url, $db, $login, $api_key);
        wp_send_json($result);
    }

    private function is_enabled() {
        return get_option('gn_odoo_enabled', '') === '1'
            && get_option('gn_odoo_url', '')
            && get_option('gn_odoo_db', '')
            && get_option('gn_odoo_login', '')
            && get_option('gn_odoo_api_key', '');
    }

    /**
     * Testverbinding: probeert te authenticeren en geeft een leesbaar resultaat
     * terug, inclusief de ruwe JSON-respons, voor gebruik in de instellingenpagina.
     */
    public function test_connection($url = null, $db = null, $login = null, $api_key = null) {
        $url     = $url !== null ? $url : get_option('gn_odoo_url', '');
        $db      = $db !== null ? $db : get_option('gn_odoo_db', '');
        $login   = $login !== null ? $login : get_option('gn_odoo_login', '');
        $api_key = $api_key !== null ? $api_key : get_option('gn_odoo_api_key', '');

        // Poging 1: standaard /jsonrpc common.authenticate
        $result = $this->try_authenticate_jsonrpc($url, $db, $login, $api_key);
        if ($result['success']) {
            return $result;
        }

        // Poging 2: fallback via /web/session/authenticate
        $result2 = $this->try_authenticate_web_session($url, $db, $login, $api_key);
        if ($result2['success']) {
            return $result2;
        }

        // Beide mislukt — geef gecombineerde debug-informatie terug
        return [
            'success' => false,
            'message' => 'Authenticatie mislukt via beide methodes. Controleer database-naam (gebruik alleen de subdomain, bijv. "glassnext" niet "glassnext.odoo.com"), login e-mailadres en API-key.',
            'raw'     => "Poging 1 (/jsonrpc):\n" . $result['raw'] . "\n\nPoging 2 (/web/session/authenticate):\n" . $result2['raw'],
            'url'     => trailingslashit($url) . 'jsonrpc (en /web/session/authenticate)',
            'debug'   => [
                'db_used'    => $db,
                'login_used' => $login,
                'key_length' => strlen($api_key),
            ],
        ];
    }

    private function try_authenticate_jsonrpc($url, $db, $login, $api_key) {
        $endpoint = trailingslashit($url) . 'jsonrpc';
        $body = [
            'jsonrpc' => '2.0',
            'method'  => 'call',
            'params'  => [
                'service' => 'common',
                'method'  => 'authenticate',
                'args'    => [$db, $login, $api_key, new stdClass()],
            ],
        ];

        $response = wp_remote_post($endpoint, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($body),
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'WordPress kon geen verbinding maken: ' . $response->get_error_message(),
                'raw'     => '',
                'url'     => $endpoint,
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw  = wp_remote_retrieve_body($response);
        $data = json_decode($raw, true);
        $uid  = $data['result'] ?? null;

        return [
            'success' => is_int($uid) && $uid > 0,
            'message' => (is_int($uid) && $uid > 0)
                ? 'Authenticatie gelukt via /jsonrpc, UID: ' . $uid
                : 'Authenticatie mislukt via /jsonrpc (HTTP ' . $code . ', result: ' . var_export($uid, true) . ')',
            'raw' => $raw,
            'url' => $endpoint,
            'uid' => is_int($uid) ? $uid : null,
        ];
    }

    private function try_authenticate_web_session($url, $db, $login, $api_key) {
        $endpoint = trailingslashit($url) . 'web/session/authenticate';
        $body = [
            'jsonrpc' => '2.0',
            'method'  => 'call',
            'params'  => [
                'db'      => $db,
                'login'   => $login,
                'password' => $api_key,
            ],
        ];

        $response = wp_remote_post($endpoint, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($body),
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'WordPress kon geen verbinding maken: ' . $response->get_error_message(),
                'raw'     => '',
                'url'     => $endpoint,
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw  = wp_remote_retrieve_body($response);
        $data = json_decode($raw, true);

        $uid = null;
        if (isset($data['result']['uid'])) {
            $uid = (int) $data['result']['uid'];
        } elseif (isset($data['result']) && is_int($data['result'])) {
            $uid = (int) $data['result'];
        }

        return [
            'success' => $uid > 0,
            'message' => ($uid > 0)
                ? 'Authenticatie gelukt via /web/session/authenticate, UID: ' . $uid
                : 'Authenticatie mislukt via /web/session/authenticate (HTTP ' . $code . ')',
            'raw' => $raw,
            'url' => $endpoint,
            'uid' => $uid > 0 ? $uid : null,
        ];
    }

    /**
     * Hoofdfunctie: synchroniseert één offerte-aanvraag naar Odoo.
     * Geeft altijd een array terug, gooit nooit een fout naar buiten,
     * zodat een Odoo-storing de offerte-flow voor de klant nooit breekt.
     *
     * @return array{success:bool, order_id:?int, partner_id:?int, error:?string}
     */
    public function sync_order($decoded, $offer_number, $customer_name, $contact_name, $email, $phone, $address, $city) {
        if (!$this->is_enabled()) {
            return ['success' => false, 'order_id' => null, 'partner_id' => null, 'error' => 'Odoo-integratie niet geconfigureerd of uitgeschakeld.'];
        }

        try {
            $this->authenticate();

            $partner_id = $this->find_or_create_partner($email, $contact_name ?: $customer_name, $phone, $address, $city);

            $order_id = $this->create_sale_order($partner_id, $offer_number);

            $export = $decoded['exportData'] ?? [];
            $roll_area   = floatval($export['rollArea'] ?? 0);
            $roll_length = floatval($export['rollLength'] ?? 0);
            $pieces      = intval($export['pieces'] ?? 0);

            $product_material = (int) get_option('gn_odoo_product_material', 3);
            $product_mount    = (int) get_option('gn_odoo_product_mount', 5);
            $product_cut      = (int) get_option('gn_odoo_product_cut', 85);

            if ($roll_area > 0) {
                $this->add_fixed_line($order_id, $product_material, $roll_area);
            }
            if ($roll_length > 0) {
                $this->add_fixed_line($order_id, $product_mount, $roll_length);
            }
            if ($pieces > 0) {
                $this->add_fixed_line($order_id, $product_cut, $pieces);
            }

            foreach (($decoded['otherCosts'] ?? []) as $cost) {
                $desc = $cost['description'] ?? '';
                $amount = floatval($cost['amount'] ?? 0);
                if ($desc !== '' && $amount > 0) {
                    $this->add_free_line($order_id, $desc, 1, $amount);
                }
            }

            return ['success' => true, 'order_id' => $order_id, 'partner_id' => $partner_id, 'error' => null];

        } catch (Exception $e) {
            return ['success' => false, 'order_id' => null, 'partner_id' => null, 'error' => $e->getMessage()];
        }
    }

    private function authenticate() {
        if ($this->uid !== null) {
            return $this->uid;
        }

        $url     = get_option('gn_odoo_url', '');
        $db      = get_option('gn_odoo_db', '');
        $login   = get_option('gn_odoo_login', '');
        $api_key = get_option('gn_odoo_api_key', '');

        // Poging 1: /jsonrpc common.authenticate
        $result = $this->try_authenticate_jsonrpc($url, $db, $login, $api_key);
        if ($result['success'] && !empty($result['uid'])) {
            $this->uid = $result['uid'];
            return $this->uid;
        }

        // Poging 2: /web/session/authenticate
        $result2 = $this->try_authenticate_web_session($url, $db, $login, $api_key);
        if ($result2['success'] && !empty($result2['uid'])) {
            $this->uid = $result2['uid'];
            return $this->uid;
        }

        throw new Exception('Authenticatie bij Odoo mislukt via beide methodes. Controleer database-naam (gebruik alleen de subdomain, bijv. "glassnext"), login e-mailadres en API-key. /jsonrpc result: ' . $result['raw'] . ' | /web/session result: ' . $result2['raw']);
    }

    private function call($model, $method, $args, $kwargs = null) {
        $url = trailingslashit(get_option('gn_odoo_url', '')) . 'jsonrpc';

        $call_args = [
            get_option('gn_odoo_db', ''),
            $this->authenticate(),
            get_option('gn_odoo_api_key', ''),
            $model,
            $method,
            $args,
        ];
        if ($kwargs !== null) {
            $call_args[] = $kwargs;
        }

        $body = [
            'jsonrpc' => '2.0',
            'method'  => 'call',
            'params'  => [
                'service' => 'object',
                'method'  => 'execute_kw',
                'args'    => $call_args,
            ],
        ];

        return $this->request($url, $body);
    }

    private function request($url, $body) {
        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($body),
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            throw new Exception('Verbindingsfout met Odoo: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw  = wp_remote_retrieve_body($response);
        $data = json_decode($raw, true);

        if ($code >= 400) {
            throw new Exception('Odoo gaf HTTP ' . $code . ' terug: ' . $raw);
        }

        if (isset($data['error'])) {
            $message = $data['error']['data']['message'] ?? ($data['error']['message'] ?? 'Onbekende Odoo-fout');
            throw new Exception($message);
        }

        return $data['result'] ?? null;
    }

    private function find_or_create_partner($email, $name, $phone, $address, $city) {
        if ($email) {
            $found = $this->call(
                'res.partner',
                'search_read',
                [[['email', '=', $email]]],
                ['fields' => ['id', 'name', 'email'], 'limit' => 1]
            );

            if (!empty($found) && isset($found[0]['id'])) {
                return (int) $found[0]['id'];
            }
        }

        $partner_data = ['name' => $name ?: $email];
        if ($email)   $partner_data['email'] = $email;
        if ($phone)   $partner_data['phone'] = $phone;
        if ($address) $partner_data['street'] = $address;
        if ($city)    $partner_data['city'] = $city;

        $new_id = $this->call('res.partner', 'create', [$partner_data]);
        return (int) $new_id;
    }

    private function create_sale_order($partner_id, $client_order_ref) {
        $order_data = [
            'partner_id'       => $partner_id,
            'client_order_ref' => $client_order_ref,
        ];
        $order_id = $this->call('sale.order', 'create', [$order_data]);
        return (int) $order_id;
    }

    private function add_fixed_line($order_id, $product_id, $qty) {
        if ($product_id <= 0) return;
        $this->call('sale.order.line', 'create', [[
            'order_id'        => $order_id,
            'product_id'      => $product_id,
            'product_uom_qty' => $qty,
        ]]);
    }

    private function add_free_line($order_id, $name, $qty, $price_unit) {
        $this->call('sale.order.line', 'create', [[
            'order_id'        => $order_id,
            'name'            => $name,
            'product_uom_qty' => $qty,
            'price_unit'      => $price_unit,
        ]]);
    }
}
