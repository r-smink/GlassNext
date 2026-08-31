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
        $uid      = isset($_POST['gn_odoo_uid']) ? sanitize_text_field(wp_unslash($_POST['gn_odoo_uid'])) : '';

        $result = $this->test_connection($url, $db, $login, $api_key, $uid);

        // Bewaar hardcoded UID in database zodat offertesync het kan hergebruiken
        if (!empty($result['success']) && (int) $uid > 0) {
            update_option('gn_odoo_uid', (int) $uid);
            $this->debug_log('ajax_test_connection saved gn_odoo_uid=' . (int) $uid);
        }

        wp_send_json($result);
    }

    private function is_enabled() {
        $has_uid = (int) get_option('gn_odoo_uid', 0) > 0;
        return get_option('gn_odoo_enabled', '') === '1'
            && get_option('gn_odoo_url', '')
            && get_option('gn_odoo_db', '')
            && (get_option('gn_odoo_login', '') || $has_uid)
            && get_option('gn_odoo_api_key', '');
    }

    /**
     * Testverbinding: probeert te authenticeren en geeft een leesbaar resultaat
     * terug, inclusief de ruwe JSON-respons, voor gebruik in de instellingenpagina.
     */
    private function debug_log($message) {
        error_log('GlassNext Odoo: ' . $message);
    }

    public function test_connection($url = null, $db = null, $login = null, $api_key = null, $uid = null) {
        $url     = $url !== null ? $url : get_option('gn_odoo_url', '');
        $db      = $db !== null ? $db : get_option('gn_odoo_db', '');
        $login   = $login !== null ? $login : get_option('gn_odoo_login', '');
        $api_key = $api_key !== null ? $api_key : get_option('gn_odoo_api_key', '');
        $uid     = $uid !== null ? (int) $uid : (int) get_option('gn_odoo_uid', 0);

        $endpoint = trailingslashit($url) . 'jsonrpc';
        $this->debug_log('test_connection start: endpoint=' . $endpoint . ' db=' . $db . ' login=' . $login . ' uid=' . $uid . ' key-len=' . strlen($api_key));

        // Als een hardcoded UID is ingesteld, test direct met execute_kw (Make.com-methode)
        if ($uid > 0) {
            $res = $this->try_execute_kw_test($endpoint, $db, $uid, $api_key);
            if ($res['success']) {
                return $res;
            }
            // Valt terug naar gewone auth als hardcoded UID niet werkte
        }

        // Sanity check: reikt Odoo uberhaupt?
        $version = $this->call_version($endpoint);
        $this->debug_log('common.version result: ' . var_export($version, true));

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

        // Poging 3: common.login (oudere login-methode)
        $result3 = $this->try_authenticate_login($url, $db, $login, $api_key);

        // Beide mislukt — geef gecombineerde debug-informatie terug
        $version_raw = is_array($version) ? wp_json_encode($version) : 'geen antwoord';
        return [
            'success' => false,
            'message' => 'Authenticatie mislukt via alle methodes. Zie ruwe antwoorden hieronder.',
            'raw'     => "common.version:\n" . $version_raw . "\n\nPoging 1 (/jsonrpc common.authenticate):\n" . $result['raw'] . "\n\nPoging 2 (/web/session/authenticate):\n" . $result2['raw'] . "\n\nPoging 3 (/jsonrpc common.login):\n" . $result3['raw'],
            'url'     => trailingslashit($url) . 'jsonrpc',
            'debug'   => [
                'db_used'    => $db,
                'login_used' => $login,
                'uid_used'   => $uid,
                'key_length' => strlen($api_key),
            ],
        ];
    }

    private function try_execute_kw_test($url, $db, $uid, $api_key) {
        $body = [
            'jsonrpc' => '2.0',
            'method'  => 'call',
            'id'      => 1,
            'params'  => [
                'service' => 'object',
                'method'  => 'execute_kw',
                'args'    => [
                    $db,
                    $uid,
                    $api_key,
                    'res.partner',
                    'search_read',
                    [[['email', '=', 'no-reply@example.com']]],
                    ['fields' => ['id', 'name', 'email'], 'limit' => 1],
                ],
            ],
        ];

        $json_body = wp_json_encode($body);
        $response = $this->remote_post($url, $json_body);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'Verbindingsfout: ' . $response->get_error_message(),
                'raw'     => '',
                'url'     => $url,
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw  = wp_remote_retrieve_body($response);
        $data = json_decode($raw, true);

        if (isset($data['error'])) {
            return [
                'success' => false,
                'message' => 'execute_kw test gaf een Odoo-fout: ' . ($data['error']['data']['message'] ?? $data['error']['message']),
                'raw'     => $raw,
                'url'     => $url,
            ];
        }

        if (isset($data['result']) && is_array($data['result'])) {
            return [
                'success' => true,
                'message' => 'execute_kw gelukt met hardcoded UID ' . $uid . '. Zoekresultaat: ' . count($data['result']) . ' partner(s).',
                'raw'     => $raw,
                'url'     => $url,
            ];
        }

        return [
            'success' => false,
            'message' => 'execute_kw test retourneerde geen geldig resultaat (HTTP ' . $code . ').',
            'raw'     => $raw,
            'url'     => $url,
        ];
    }

    private function call_version($url) {
        $body = [
            'jsonrpc' => '2.0',
            'method'  => 'call',
            'id'      => 1,
            'params'  => [
                'service' => 'common',
                'method'  => 'version',
                'args'    => [],
            ],
        ];

        $json_body = wp_json_encode($body);
        $response = $this->remote_post($url, $json_body);

        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }

        $raw = wp_remote_retrieve_body($response);
        $data = json_decode($raw, true);
        return $data['result'] ?? ['raw' => $raw];
    }

    private function try_authenticate_login($url, $db, $login, $api_key) {
        $endpoint = trailingslashit($url) . 'jsonrpc';
        $body = [
            'jsonrpc' => '2.0',
            'method'  => 'call',
            'id'      => 1,
            'params'  => [
                'service' => 'common',
                'method'  => 'login',
                'args'    => [$db, $login, $api_key],
            ],
        ];

        $json_body = wp_json_encode($body);
        $response = $this->remote_post($endpoint, $json_body);

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
                ? 'Authenticatie gelukt via common.login, UID: ' . $uid
                : 'Authenticatie mislukt via common.login (HTTP ' . $code . ', result: ' . var_export($uid, true) . ')',
            'raw' => $raw,
            'url' => $endpoint,
            'uid' => is_int($uid) ? $uid : null,
        ];
    }

    private function try_authenticate_jsonrpc($url, $db, $login, $api_key) {
        $endpoint = trailingslashit($url) . 'jsonrpc';
        $body = [
            'jsonrpc' => '2.0',
            'method'  => 'call',
            'id'      => 1,
            'params'  => [
                'service' => 'common',
                'method'  => 'authenticate',
                'args'    => [$db, $login, $api_key, new stdClass()],
            ],
        ];

        $json_body = wp_json_encode($body);
        $response = $this->remote_post($endpoint, $json_body);

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
            'id'      => 1,
            'params'  => [
                'db'      => $db,
                'login'   => $login,
                'password' => $api_key,
            ],
        ];

        $json_body = wp_json_encode($body);
        $response = $this->remote_post($endpoint, $json_body);

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
    public function sync_order($decoded, $offer_number, $customer_name, $contact_name, $email, $phone, $address, $city, $attachments = [], $flow_mode = 'self', $pref_date1 = '', $pref_date2 = '', $pref_date3 = '') {
        if (!$this->is_enabled()) {
            return ['success' => false, 'order_id' => null, 'partner_id' => null, 'error' => 'Odoo-integratie niet geconfigureerd of uitgeschakeld.'];
        }

        try {
            $this->debug_log('sync_order start: offer=' . $offer_number . ' customer=' . $customer_name . ' contact=' . $contact_name . ' email=' . $email);

            $this->authenticate();
            $this->debug_log('authenticate ok, uid=' . $this->uid);

            $partner_id = $this->find_or_create_partner($email, $contact_name ?: $customer_name, $phone, $address, $city);
            $this->debug_log('partner_id=' . $partner_id);

            $order_id = $this->create_sale_order($partner_id, $offer_number);
            $this->debug_log('order_id=' . $order_id);

            $export = $decoded['exportData'] ?? [];
            $roll_area   = floatval($export['rollArea'] ?? 0);
            $roll_length = floatval($export['rollLength'] ?? 0);
            $pieces      = intval($export['pieces'] ?? 0);

            $product_material = (int) get_option('gn_odoo_product_material', 3);
            $product_mount    = (int) get_option('gn_odoo_product_mount', 5);
            $product_cut      = (int) get_option('gn_odoo_product_cut', 85);

            $install_mode = $decoded['values']['installMode'] ?? 'professional';

            if ($flow_mode === 'measure') {
                $product_measure = (int) get_option('gn_odoo_product_measure', 0);
                if ($product_measure > 0) {
                    $this->add_fixed_line($order_id, $product_measure, 1);
                }
                $date_note = "Voorkeursdatums inmeten:\n";
                $date_note .= "1: " . $pref_date1 . "\n";
                $date_note .= "2: " . $pref_date2 . "\n";
                $date_note .= "3: " . $pref_date3;
                $this->call('sale.order', 'write', [[$order_id], ['note' => $date_note]]);

                if (get_option('gn_odoo_create_calendar_event', '') === '1' && $pref_date1) {
                    try {
                        $this->create_calendar_event($partner_id, $offer_number, $pref_date1, $customer_name);
                    } catch (Exception $ce) {
                        $this->debug_log('calendar event error: ' . $ce->getMessage());
                    }
                }
            } else {
                $product_material = (int) get_option('gn_odoo_product_material', 3);
                $product_mount    = (int) get_option('gn_odoo_product_mount', 5);
                $product_cut      = (int) get_option('gn_odoo_product_cut', 85);

                if ($roll_area > 0) {
                    $this->add_fixed_line($order_id, $product_material, $roll_area);
                }
                if ($roll_length > 0 && $install_mode !== 'self') {
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

                $product_voorrijd = (int) get_option('gn_odoo_product_voorrijd', 86);
                if ($product_voorrijd > 0) {
                    $this->add_fixed_line($order_id, $product_voorrijd, 1);
                }
            }

            // Upload bijlagen (Snijplan PDF + CSV) naar Odoo sale.order
            if (!empty($attachments)) {
                foreach ($attachments as $att) {
                    $this->upload_attachment($order_id, $att['name'], $att['datas'], $att['mimetype']);
                }
                $this->debug_log('uploaded ' . count($attachments) . ' attachment(s) to sale.order ' . $order_id);
            }

            $this->debug_log('sync_order complete: order_id=' . $order_id . ' partner_id=' . $partner_id);
            return ['success' => true, 'order_id' => $order_id, 'partner_id' => $partner_id, 'error' => null];

        } catch (Exception $e) {
            $this->debug_log('sync_order error: ' . $e->getMessage());
            return ['success' => false, 'order_id' => null, 'partner_id' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Upload een bijlage naar Odoo via ir.attachment, gekoppeld aan een sale.order.
     * $datas moet base64-geëncodeerd zijn.
     */
    private function upload_attachment($order_id, $name, $datas, $mimetype) {
        $this->debug_log('upload_attachment: order_id=' . $order_id . ' name=' . $name . ' mimetype=' . $mimetype . ' size=' . strlen($datas));
        $this->call('ir.attachment', 'create', [[
            'name'      => $name,
            'datas'     => $datas,
            'res_model' => 'sale.order',
            'res_id'    => $order_id,
            'mimetype'  => $mimetype,
        ]]);
    }

    /**
     * Maak een concept agenda-afspraak in Odoo voor het inmeten.
     */
    private function create_calendar_event($partner_id, $offer_number, $date, $customer_name) {
        $this->debug_log('create_calendar_event: partner=' . $partner_id . ' date=' . $date . ' offer=' . $offer_number);
        $start = $date . ' 09:00:00';
        $stop = $date . ' 10:00:00';
        $this->call('calendar.event', 'create', [[
            'name'      => 'Inmeten ' . $customer_name . ' (' . $offer_number . ')',
            'partner_ids' => [[$partner_id]],
            'start'     => $start,
            'stop'      => $stop,
            'allday'    => false,
            'description' => 'Concept-afspraak voor inmeten. Voorkeursdatum van klant.',
        ]]);
    }

    private function authenticate() {
        if ($this->uid !== null) {
            return $this->uid;
        }

        $url     = get_option('gn_odoo_url', '');
        $db      = get_option('gn_odoo_db', '');
        $login   = get_option('gn_odoo_login', '');
        $api_key = get_option('gn_odoo_api_key', '');
        $uid     = (int) get_option('gn_odoo_uid', 0);

        $this->debug_log('authenticate called: url=' . $url . ' db=' . $db . ' login=' . $login . ' saved_uid=' . $uid);

        // Make.com-methode: gebruik hardcoded UID direct
        if ($uid > 0) {
            $this->uid = $uid;
            return $this->uid;
        }

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

        throw new Exception('Authenticatie bij Odoo mislukt. /jsonrpc result: ' . $result['raw'] . ' | /web/session result: ' . $result2['raw'] . ' | Tip: als de test met hardcoded UID wel werkte, zorg dat de instellingen zijn opgeslagen (opgeslagen gn_odoo_uid=' . $uid . ').');
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
            'id'      => 1,
            'params'  => [
                'service' => 'object',
                'method'  => 'execute_kw',
                'args'    => $call_args,
            ],
        ];

        return $this->request($url, $body);
    }

    private function request($url, $body) {
        $json_body = wp_json_encode($body);
        $this->debug_log('request to ' . $url . ' body: ' . preg_replace('/"[a-zA-Z0-9_]{20,}"/','"***"', $json_body));

        $response = $this->remote_post($url, $json_body);

        if (is_wp_error($response)) {
            throw new Exception('Verbindingsfout met Odoo: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw  = wp_remote_retrieve_body($response);
        $this->debug_log('response HTTP ' . $code . ' body: ' . $raw);
        $data = json_decode($raw, true);

        if ($code >= 400) {
            throw new Exception('Odoo gaf HTTP ' . $code . ' terug: ' . $raw);
        }

        if (isset($data['error'])) {
            $message = $data['error']['data']['message'] ?? ($data['error']['message'] ?? 'Onbekende Odoo-fout');
            throw new Exception($message);
        }

        if ($data['result'] === false) {
            throw new Exception('Odoo execute_kw retourneerde false (waarschijnlijk een rechten- of model-validatie fout). Controleer of de gebruiker toegang heeft om ' . ($body['params']['args'][3] ?? 'het model') . ' te bewerken.');
        }

        return $data['result'] ?? null;
    }

    private function remote_post($url, $json_body) {
        $headers = [
            'Content-Type'   => 'application/json',
            'Content-Length' => strlen($json_body),
            'User-Agent'     => 'GlassNext-Odoo-Connector/1.0',
            'Accept'         => 'application/json',
        ];

        // Eerste poging met WordPress HTTP
        $wp_response = wp_remote_post($url, [
            'headers' => $headers,
            'body'    => $json_body,
            'timeout' => 20,
        ]);

        if (!is_wp_error($wp_response)) {
            $raw = wp_remote_retrieve_body($wp_response);
            $data = json_decode($raw, true);
            if (isset($data['result']) && ($data['result'] !== false || $this->is_version_call($json_body))) {
                return $wp_response;
            }
        }

        // Fallback naar cURL als WP HTTP geen geldig resultaat geeft
        if (!function_exists('curl_init')) {
            return $wp_response;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json_body),
            'User-Agent: GlassNext-Odoo-Connector/1.0',
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $err !== '') {
            return new WP_Error('curl_failed', 'cURL fout: ' . $err);
        }

        // Simuleer een WP_Http response-object
        return [
            'response' => ['code' => $http_code, 'message' => ''],
            'body'     => $raw,
        ];
    }

    private function is_version_call($json_body) {
        return strpos($json_body, '"method":"version"') !== false;
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
        if (!$new_id) {
            throw new Exception('res.partner create retourneerde geen geldig ID: ' . var_export($new_id, true));
        }
        return (int) $new_id;
    }

    private function create_sale_order($partner_id, $client_order_ref) {
        $order_data = [
            'partner_id'       => $partner_id,
            'client_order_ref' => $client_order_ref,
        ];
        $order_id = $this->call('sale.order', 'create', [$order_data]);
        if (!$order_id) {
            throw new Exception('sale.order create retourneerde geen geldig ID: ' . var_export($order_id, true));
        }
        return (int) $order_id;
    }

    private function add_fixed_line($order_id, $product_id, $qty) {
        if ($product_id <= 0) return;
        $this->debug_log('add_fixed_line: order_id=' . $order_id . ' product_id=' . $product_id . ' qty=' . $qty);
        $this->call('sale.order.line', 'create', [[
            'order_id'        => $order_id,
            'product_id'      => $product_id,
            'product_uom_qty' => $qty,
        ]]);
    }

    private function add_free_line($order_id, $name, $qty, $price_unit) {
        $this->debug_log('add_free_line: order_id=' . $order_id . ' name=' . $name . ' qty=' . $qty . ' price=' . $price_unit);
        $this->call('sale.order.line', 'create', [[
            'order_id'        => $order_id,
            'name'            => $name,
            'product_uom_qty' => $qty,
            'price_unit'      => $price_unit,
        ]]);
    }
}
