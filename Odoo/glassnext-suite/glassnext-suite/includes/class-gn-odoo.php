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
        add_action('wp_ajax_gn_fetch_mailing_lists', [$this, 'ajax_fetch_mailing_lists']);
    }

    /**
     * Admin AJAX: haalt de beschikbare mailinglijsten op uit Odoo voor de
     * nieuwsbrief-instellingentab.
     */
    public function ajax_fetch_mailing_lists() {
        check_ajax_referer('gn_fetch_mailing_lists', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Geen toegang.']);
        }
        wp_send_json($this->fetch_mailing_lists());
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
     * Publieke check of de Odoo-koppeling voldoende geconfigureerd is.
     * De nieuwsbrief-inschrijving gebruikt dit los van gn_odoo_enabled,
     * zodat de koppeling ook werkt als alleen de nieuwsbrief-feature aan staat.
     */
    public function is_configured() {
        $has_uid = (int) get_option('gn_odoo_uid', 0) > 0;
        return (bool) (get_option('gn_odoo_url', '')
            && get_option('gn_odoo_db', '')
            && (get_option('gn_odoo_login', '') || $has_uid)
            && get_option('gn_odoo_api_key', ''));
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
    public function sync_order($decoded, $offer_number, $customer_name, $contact_name, $email, $phone, $address, $city, $attachments = [], $flow_mode = 'self', $pref_date1 = '', $pref_date2 = '', $pref_date3 = '', $install_mode = 'professional', $pref_day1 = '', $pref_day2 = '', $pref_day3 = '', $pref_time1 = '', $pref_time2 = '', $pref_time3 = '') {
        if (!$this->is_enabled()) {
            return ['success' => false, 'order_id' => null, 'partner_id' => null, 'error' => 'Odoo-integratie niet geconfigureerd of uitgeschakeld.'];
        }

        try {
            $this->debug_log('sync_order start: offer=' . $offer_number . ' customer=' . $customer_name . ' contact=' . $contact_name . ' email=' . $email . ' flow=' . $flow_mode . ' install=' . $install_mode);

            $this->authenticate();
            $this->debug_log('authenticate ok, uid=' . $this->uid);

            $partner_id = $this->find_or_create_partner($email, $contact_name ?: $customer_name, $phone, $address, $city);
            $this->debug_log('partner_id=' . $partner_id);

            if ($flow_mode === 'measure') {
                // Build notes for lead
                $form_note = '';
                $form_note_html = '';
                try {
                    $form_note = $this->build_form_note($decoded, $offer_number, $customer_name, $contact_name, $email, $phone, $address, $city, $flow_mode, $install_mode, $pref_date1, $pref_date2, $pref_date3, $pref_day1, $pref_day2, $pref_day3, $pref_time1, $pref_time2, $pref_time3, $attachments);
                } catch (Exception $ne) {
                    $this->debug_log('build_form_note error (non-fatal): ' . $ne->getMessage());
                }
                try {
                    $form_note_html = $this->build_form_note_html($decoded, $offer_number, $customer_name, $contact_name, $email, $phone, $address, $city, $flow_mode, $install_mode, $pref_date1, $pref_date2, $pref_date3, $pref_day1, $pref_day2, $pref_day3, $pref_time1, $pref_time2, $pref_time3, $attachments);
                } catch (Exception $ne) {
                    $this->debug_log('build_form_note_html error (non-fatal): ' . $ne->getMessage());
                }

                // Create crm.lead instead of sale.order for "Laten opmeten" flow
                $lead_id = $this->create_crm_lead($partner_id, $offer_number, $customer_name, '', $pref_date1, $pref_day1, $pref_time1);
                $this->debug_log('lead_id=' . $lead_id);

                // Add 'website' tag to the lead
                $this->add_website_tag('crm.lead', $lead_id);

                // Upload attachments to the lead
                $this->debug_log('measure flow: attachments count=' . count($attachments) . ' form_note_html empty=' . (empty($form_note_html) ? 'yes' : 'no'));
                if (!empty($attachments)) {
                    $uploaded_count = 0;
                    foreach ($attachments as $att) {
                        $this->debug_log('measure flow: uploading attachment: ' . $att['name'] . ' mimetype=' . $att['mimetype'] . ' url=' . ($att['url'] ?? ''));
                        $result = $this->upload_attachment_to_model('crm.lead', $lead_id, $att['name'], $att['url'], $att['mimetype']);
                        if ($result) {
                            $uploaded_count++;
                        }
                    }
                    $this->debug_log('uploaded ' . $uploaded_count . '/' . count($attachments) . ' attachment(s) to crm.lead ' . $lead_id);
                }

                // Create calendar event if enabled
                if (get_option('gn_odoo_create_calendar_event', '') === '1' && $pref_date1) {
                    try {
                        $this->create_calendar_event($partner_id, $offer_number, $pref_date1, $customer_name, $pref_time1);
                    } catch (Exception $ce) {
                        $this->debug_log('calendar event error: ' . $ce->getMessage());
                    }
                }

                // Post form data as HTML table message in the chatter
                if ($form_note_html) {
                    $this->post_message_to_chatter('crm.lead', $lead_id, $form_note_html);
                }

                $this->debug_log('sync_order complete (lead): lead_id=' . $lead_id . ' partner_id=' . $partner_id);
                return ['success' => true, 'order_id' => $lead_id, 'partner_id' => $partner_id, 'error' => null];

            } else {
                // Create sale.order for "Zelf opmeten" flow
                $order_id = $this->create_sale_order($partner_id, $offer_number);
                $this->debug_log('order_id=' . $order_id);

                // Add 'website' tag to the order
                $this->add_website_tag('sale.order', $order_id);

                $export = $decoded['exportData'] ?? [];
                $roll_area   = floatval($export['rollArea'] ?? 0);
                $roll_length = floatval($export['rollLength'] ?? 0);
                $pieces      = intval($export['pieces'] ?? 0);
                $this->debug_log('exportData raw: ' . json_encode($export));
                $this->debug_log('roll_area=' . $roll_area . ' roll_length=' . $roll_length . ' pieces=' . $pieces . ' install_mode=' . $install_mode);

                $product_material = (int) get_option('gn_odoo_product_material', 3);
                $product_mount    = (int) get_option('gn_odoo_product_mount', 5);
                $product_cut      = (int) get_option('gn_odoo_product_cut', 85);

                if ($roll_area > 0) {
                    $this->add_fixed_line($order_id, $product_material, $roll_area);
                }
                // Montage: add with calculated rate in Odoo (even though offer shows "nader te berekenen")
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
                if ($product_voorrijd > 0 && $install_mode !== 'self') {
                    $this->add_fixed_line($order_id, $product_voorrijd, 1);
                }

                // Build notes after order lines so note errors don't block line creation
                $form_note = '';
                $form_note_html = '';
                try {
                    $form_note = $this->build_form_note($decoded, $offer_number, $customer_name, $contact_name, $email, $phone, $address, $city, $flow_mode, $install_mode, $pref_date1, $pref_date2, $pref_date3, $pref_day1, $pref_day2, $pref_day3, $pref_time1, $pref_time2, $pref_time3, $attachments);
                } catch (Exception $ne) {
                    $this->debug_log('build_form_note error (non-fatal): ' . $ne->getMessage());
                }
                try {
                    $form_note_html = $this->build_form_note_html($decoded, $offer_number, $customer_name, $contact_name, $email, $phone, $address, $city, $flow_mode, $install_mode, $pref_date1, $pref_date2, $pref_date3, $pref_day1, $pref_day2, $pref_day3, $pref_time1, $pref_time2, $pref_time3, $attachments);
                } catch (Exception $ne) {
                    $this->debug_log('build_form_note_html error (non-fatal): ' . $ne->getMessage());
                }

                // Write the comprehensive form note (empty, HTML is in chatter)
                if ($form_note) {
                    try { $this->call('sale.order', 'write', [[$order_id], ['note' => '']]); } catch (Exception $e) { $this->debug_log('write note error: ' . $e->getMessage()); }
                }

                // Post form data as HTML table message in the chatter
                if ($form_note_html) {
                    $this->post_message_to_chatter('sale.order', $order_id, $form_note_html);
                }

                // Upload attachments to sale.order
                if (!empty($attachments)) {
                    $uploaded_count = 0;
                    foreach ($attachments as $att) {
                        $result = $this->upload_attachment_to_model('sale.order', $order_id, $att['name'], $att['url'], $att['mimetype']);
                        if ($result) {
                            $uploaded_count++;
                        }
                    }
                    $this->debug_log('uploaded ' . $uploaded_count . '/' . count($attachments) . ' attachment(s) to sale.order ' . $order_id);
                }

                $this->debug_log('sync_order complete (order): order_id=' . $order_id . ' partner_id=' . $partner_id);
                return ['success' => true, 'order_id' => $order_id, 'partner_id' => $partner_id, 'error' => null];
            }

        } catch (Exception $e) {
            $this->debug_log('sync_order error: ' . $e->getMessage());
            return ['success' => false, 'order_id' => null, 'partner_id' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build a comprehensive note with all form fields for Odoo (Task 7).
     */
    private function build_form_note($decoded, $offer_number, $customer_name, $contact_name, $email, $phone, $address, $city, $flow_mode, $install_mode, $pref_date1, $pref_date2, $pref_date3, $pref_day1, $pref_day2, $pref_day3, $pref_time1, $pref_time2, $pref_time3, $attachments = []) {
        $values = $decoded['values'] ?? [];
        $export = $decoded['exportData'] ?? [];
        $panes = $decoded['panes'] ?? [];
        $other_costs = $decoded['otherCosts'] ?? [];

        $flow_label = $flow_mode === 'measure' ? 'Laten opmeten' : 'Zelf opmeten';
        $install_label = $install_mode === 'self' ? 'Folie zelf aanbrengen' : 'Folie laten aanbrengen door GlassNext';

        $days = ['ochtend' => 'Ochtend', 'middag' => 'Middag', 'avond' => 'Avond'];

        $note = "=== GlassNext Website Aanvraag ===\n";
        $note .= "Offertenummer: " . $offer_number . "\n";
        $note .= "Flow: " . $flow_label . "\n";
        $note .= "Aanbrengen: " . $install_label . "\n";
        $note .= "Ingediend op: " . current_time('mysql') . "\n\n";

        $note .= "--- Klantgegevens ---\n";
        $note .= "Klant / organisatie: " . $customer_name . "\n";
        $note .= "Contactpersoon: " . $contact_name . "\n";
        $note .= "E-mail: " . $email . "\n";
        $note .= "Telefoon: " . $phone . "\n";
        $note .= "Adres: " . $address . "\n";
        $note .= "Postcode / plaats: " . $city . "\n\n";

        $note .= "--- Project ---\n";
        $note .= "Projectomschrijving: " . ($values['projectDescription'] ?? '') . "\n";
        $note .= "Bijzonderheden: " . ($values['projectNotes'] ?? '') . "\n\n";

        if ($flow_mode === 'measure') {
            $measure_price = get_option('gn_measure_price', '149');
            $note .= "--- Inmeten kosten ---\n";
            $note .= "Inmeten op locatie: € " . $measure_price . ",-\n\n";
        }

        if ($flow_mode === 'measure') {
            $note .= "--- Voorkeursdatums inmeten ---\n";
            for ($i = 1; $i <= 3; $i++) {
                $date_var = "pref_date{$i}";
                $day_var = "pref_day{$i}";
                $time_var = "pref_time{$i}";
                $date_val = $$date_var;
                $day_val = $$day_var;
                $time_val = $$time_var;
                if ($date_val) {
                    $line = "Datum {$i}: {$date_val}";
                    if ($day_val && isset($days[$day_val])) $line .= " (" . $days[$day_val] . ")";
                    if ($time_val) $line .= " om {$time_val}";
                    $note .= $line . "\n";
                }
            }
            $note .= "\n";
        }

        if ($flow_mode !== 'measure' && !empty($panes)) {
            $note .= "--- Ruiten ---\n";
            foreach ($panes as $pane) {
                $note .= sprintf("  %s: %s x %s cm, %s stuks, rotatie=%s, ruimte=%s\n",
                    $pane['id'] ?? '', $pane['w'] ?? '', $pane['h'] ?? '',
                    $pane['n'] ?? '1', $pane['rot'] ?? '1', $pane['room'] ?? '');
            }
            $note .= "\n";
        }

        if ($flow_mode !== 'measure' && !empty($export)) {
            $note .= "--- Snijplan data ---\n";
            $note .= "Aantal stukken: " . ($export['pieces'] ?? 0) . "\n";
            $note .= "Rollengte: " . ($export['rollLength'] ?? 0) . " m\n";
            $note .= "Rolverbruik: " . ($export['rollArea'] ?? 0) . " m²\n\n";
        }

        if ($flow_mode !== 'measure' && !empty($other_costs)) {
            $note .= "--- Overige kosten ---\n";
            foreach ($other_costs as $cost) {
                if (!empty($cost['description']) || floatval($cost['amount'] ?? 0) > 0) {
                    $note .= "  " . ($cost['description'] ?? 'Onbekend') . ": € " . ($cost['amount'] ?? 0) . "\n";
                }
            }
            $note .= "\n";
        }

        $note .= "--- Montage instellingen ---\n";
        $note .= "Montageklasse: " . ($values['mountClass'] ?? 'average') . "\n";
        $note .= "Montagetarief: € " . ($values['mountSelectedPrice'] ?? 0) . "/m²\n";
        $note .= "Montage berekenen over: " . ($values['mountAreaBasis'] ?? 'net') . "\n";

        return $note;
    }

    /**
     * Build an HTML table version of the form data for Odoo chatter message.
     */
    private function build_form_note_html($decoded, $offer_number, $customer_name, $contact_name, $email, $phone, $address, $city, $flow_mode, $install_mode, $pref_date1, $pref_date2, $pref_date3, $pref_day1, $pref_day2, $pref_day3, $pref_time1, $pref_time2, $pref_time3, $attachments = []) {
        $values = $decoded['values'] ?? [];
        $export = $decoded['exportData'] ?? [];
        $panes = $decoded['panes'] ?? [];
        $other_costs = $decoded['otherCosts'] ?? [];

        $flow_label = $flow_mode === 'measure' ? 'Laten opmeten' : 'Zelf opmeten';
        $install_label = $install_mode === 'self' ? 'Folie zelf aanbrengen' : 'Folie laten aanbrengen door GlassNext';
        $days = ['ochtend' => 'Ochtend', 'middag' => 'Middag', 'avond' => 'Avond'];

        $esc = function($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); };

        $rows = function($label, $value) use ($esc) {
            return '<tr><td style="padding:4px 12px 4px 0;font-weight:600;vertical-align:top;color:#4a5568;">' . $esc($label) . '</td><td style="padding:4px 0;">' . $esc($value) . '</td></tr>';
        };

        $h = '<div style="font-family:Arial,sans-serif;font-size:13px;">';
        $h .= '<h3 style="margin:0 0 10px;color:#2c3e50;">GlassNext Website Aanvraag — ' . $esc($offer_number) . '</h3>';
        $h .= '<p style="margin:0 0 12px;color:#718096;font-size:12px;">Ingediend op ' . $esc(current_time('mysql')) . '</p>';

        // Algemeen
        $h .= '<table style="border-collapse:collapse;margin-bottom:14px;">';
        $h .= $rows('Flow', $flow_label);
        $h .= $rows('Aanbrengen', $install_label);
        $h .= '</table>';

        // Klantgegevens
        $h .= '<h4 style="margin:0 0 6px;color:#2c3e50;border-bottom:1px solid #e2e8f0;padding-bottom:4px;">Klantgegevens</h4>';
        $h .= '<table style="border-collapse:collapse;margin-bottom:14px;">';
        $h .= $rows('Klant / organisatie', $customer_name);
        $h .= $rows('Contactpersoon', $contact_name);
        $h .= $rows('E-mail', $email);
        $h .= $rows('Telefoon', $phone);
        $h .= $rows('Adres', $address);
        $h .= $rows('Postcode / plaats', $city);
        $h .= '</table>';

        // Project
        $h .= '<h4 style="margin:0 0 6px;color:#2c3e50;border-bottom:1px solid #e2e8f0;padding-bottom:4px;">Project</h4>';
        $h .= '<table style="border-collapse:collapse;margin-bottom:14px;">';
        $h .= $rows('Projectomschrijving', $values['projectDescription'] ?? '');
        $h .= $rows('Bijzonderheden', $values['projectNotes'] ?? '');
        $h .= '</table>';

        // Inmeten kosten (measure flow)
        if ($flow_mode === 'measure') {
            $measure_price = get_option('gn_measure_price', '149');
            $h .= '<h4 style="margin:0 0 6px;color:#2c3e50;border-bottom:1px solid #e2e8f0;padding-bottom:4px;">Inmeten kosten</h4>';
            $h .= '<table style="border-collapse:collapse;margin-bottom:14px;">';
            $h .= $rows('Inmeten op locatie', '€ ' . $measure_price . ',-');
            $h .= '</table>';
        }

        // Voorkeursdatums (measure flow)
        if ($flow_mode === 'measure') {
            $h .= '<h4 style="margin:0 0 6px;color:#2c3e50;border-bottom:1px solid #e2e8f0;padding-bottom:4px;">Voorkeursdatums inmeten</h4>';
            $h .= '<table style="border-collapse:collapse;margin-bottom:14px;">';
            for ($i = 1; $i <= 3; $i++) {
                $date_var = "pref_date{$i}"; $day_var = "pref_day{$i}"; $time_var = "pref_time{$i}";
                $date_val = $$date_var; $day_val = $$day_var; $time_val = $$time_var;
                if ($date_val) {
                    $line = $date_val;
                    if ($day_val && isset($days[$day_val])) $line .= ' (' . $days[$day_val] . ')';
                    if ($time_val) $line .= ' om ' . $time_val;
                    $h .= $rows('Voorkeur ' . $i, $line);
                }
            }
            $h .= '</table>';
        }

        // Ruiten (self flow)
        if ($flow_mode !== 'measure' && !empty($panes)) {
            $h .= '<h4 style="margin:0 0 6px;color:#2c3e50;border-bottom:1px solid #e2e8f0;padding-bottom:4px;">Ruiten</h4>';
            $h .= '<table style="border-collapse:collapse;margin-bottom:14px;width:100%;">';
            $h .= '<thead><tr style="background:#f7fafc;text-align:left;"><th style="padding:4px 8px;">Kenmerk</th><th style="padding:4px 8px;">B x H (cm)</th><th style="padding:4px 8px;">Aantal</th><th style="padding:4px 8px;">Rotatie</th><th style="padding:4px 8px;">Ruimte</th></tr></thead><tbody>';
            foreach ($panes as $pane) {
                $h .= '<tr><td style="padding:3px 8px;">' . $esc($pane['id'] ?? '') . '</td><td style="padding:3px 8px;">' . $esc($pane['w'] ?? '') . ' x ' . $esc($pane['h'] ?? '') . '</td><td style="padding:3px 8px;">' . $esc($pane['n'] ?? '1') . '</td><td style="padding:3px 8px;">' . (($pane['rot'] ?? '1') === '1' ? 'Ja' : 'Nee') . '</td><td style="padding:3px 8px;">' . $esc($pane['room'] ?? '') . '</td></tr>';
            }
            $h .= '</tbody></table>';
        }

        // Snijplan data (self flow)
        if ($flow_mode !== 'measure' && !empty($export)) {
            $h .= '<h4 style="margin:0 0 6px;color:#2c3e50;border-bottom:1px solid #e2e8f0;padding-bottom:4px;">Snijplan data</h4>';
            $h .= '<table style="border-collapse:collapse;margin-bottom:14px;">';
            $h .= $rows('Aantal stukken', $export['pieces'] ?? 0);
            $h .= $rows('Rollengte', ($export['rollLength'] ?? 0) . ' m');
            $h .= $rows('Rolverbruik', ($export['rollArea'] ?? 0) . ' m²');
            $h .= '</table>';
        }

        // Overige kosten (self flow)
        if ($flow_mode !== 'measure' && !empty($other_costs)) {
            $has_costs = false;
            foreach ($other_costs as $cost) {
                if (!empty($cost['description']) || floatval($cost['amount'] ?? 0) > 0) { $has_costs = true; break; }
            }
            if ($has_costs) {
                $h .= '<h4 style="margin:0 0 6px;color:#2c3e50;border-bottom:1px solid #e2e8f0;padding-bottom:4px;">Overige kosten</h4>';
                $h .= '<table style="border-collapse:collapse;margin-bottom:14px;">';
                foreach ($other_costs as $cost) {
                    if (!empty($cost['description']) || floatval($cost['amount'] ?? 0) > 0) {
                        $h .= $rows($cost['description'] ?? 'Onbekend', '€ ' . ($cost['amount'] ?? 0));
                    }
                }
                $h .= '</table>';
            }
        }

        // Montage instellingen
        $h .= '<h4 style="margin:0 0 6px;color:#2c3e50;border-bottom:1px solid #e2e8f0;padding-bottom:4px;">Montage instellingen</h4>';
        $h .= '<table style="border-collapse:collapse;margin-bottom:14px;">';
        $h .= $rows('Montageklasse', $values['mountClass'] ?? 'average');
        $h .= $rows('Montagetarief', '€ ' . ($values['mountSelectedPrice'] ?? 0) . '/m²');
        $h .= $rows('Montage berekenen over', $values['mountAreaBasis'] ?? 'net');
        $h .= '</table>';

        $h .= '</div>';
        return $h;
    }

    /**
     * Post a message to the Odoo chatter of a record.
     */
    private function post_message_to_chatter($model, $res_id, $body) {
        $this->debug_log('post_message_to_chatter: model=' . $model . ' res_id=' . $res_id);
        try {
            $result = $this->call($model, 'message_post', [[$res_id]], [
                'body' => $body,
                'message_type' => 'comment',
                'subtype_xmlid' => 'mail.mt_note',
                'body_is_html' => true,
            ]);
            $this->debug_log('post_message_to_chatter: result=' . var_export($result, true));
        } catch (Exception $e) {
            $this->debug_log('post_message_to_chatter error: ' . $e->getMessage());
        }
    }

    /**
     * Create a CRM lead in Odoo for "Laten opmeten" flow (Task 5).
     */
    private function create_crm_lead($partner_id, $offer_number, $customer_name, $note, $pref_date1 = '', $pref_day1 = '', $pref_time1 = '') {
        $lead_data = [
            'name'        => 'Inmeten ' . $customer_name . ' (' . $offer_number . ')',
            'partner_id'  => $partner_id,
            'description' => $note,
            'type'        => 'opportunity',
            'stage_id'    => 12,
        ];

        // Set the expected closing date if a preference date is given
        if ($pref_date1) {
            $lead_data['date_deadline'] = $pref_date1;
        }

        $lead_id = $this->call('crm.lead', 'create', [$lead_data]);
        if (!$lead_id) {
            throw new Exception('crm.lead create retourneerde geen geldig ID: ' . var_export($lead_id, true));
        }
        return (int) $lead_id;
    }

    /**
     * Add a 'website' tag to a record (Task 4).
     */
    private function add_website_tag($model, $res_id) {
        try {
            // Find or create the 'website' tag
            $tag_id = null;
            $found = $this->call('crm.tag', 'search', [
                [['name', '=', 'website']],
            ]);
            if (!empty($found) && isset($found[0])) {
                $tag_id = (int) $found[0];
            } else {
                $tag_id = $this->call('crm.tag', 'create', [['name' => 'website']]);
                if ($tag_id) $tag_id = (int) $tag_id;
            }

            if (!$tag_id) {
                $this->debug_log('add_website_tag: could not find or create tag');
                return;
            }

            // Add tag to the record
            if ($model === 'crm.lead') {
                $this->call('crm.lead', 'write', [[$res_id], ['tag_ids' => [[6, 0, [$tag_id]]]]]);
            } elseif ($model === 'sale.order') {
                // sale.order may not have tag_ids directly; try writing it
                try {
                    $this->call('sale.order', 'write', [[$res_id], ['tag_ids' => [[6, 0, [$tag_id]]]]]);
                } catch (Exception $e) {
                    $this->debug_log('add_website_tag: sale.order tag write failed: ' . $e->getMessage());
                }
            }
            $this->debug_log('add_website_tag: tag ' . $tag_id . ' added to ' . $model . ' ' . $res_id);
        } catch (Exception $e) {
            $this->debug_log('add_website_tag error (non-fatal): ' . $e->getMessage());
        }
    }

    /**
     * Upload an attachment to any Odoo model.
     */
    private function upload_attachment_to_model($model, $res_id, $name, $url, $mimetype) {
        if (empty($url)) {
            $this->debug_log('upload_attachment_to_model: SKIPPED empty url for ' . $name);
            return null;
        }
        $this->debug_log('upload_attachment_to_model: model=' . $model . ' res_id=' . $res_id . ' name=' . $name . ' mimetype=' . $mimetype . ' url=' . $url);
        try {
            $result = $this->call('ir.attachment', 'create', [[
                'name'      => $name,
                'url'       => $url,
                'res_model' => $model,
                'res_id'    => $res_id,
                'mimetype'  => $mimetype,
                'type'      => 'url',
            ]]);
            $this->debug_log('upload_attachment_to_model result: ' . var_export($result, true));
            return $result;
        } catch (Exception $e) {
            $this->debug_log('upload_attachment_to_model error: ' . $e->getMessage());
            return null;
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
    private function create_calendar_event($partner_id, $offer_number, $date, $customer_name, $pref_time = '') {
        $this->debug_log('create_calendar_event: partner=' . $partner_id . ' date=' . $date . ' time=' . $pref_time . ' offer=' . $offer_number);
        $start_time = $pref_time ? substr($pref_time, 0, 5) . ':00' : '09:00:00';
        $start = $date . ' ' . $start_time;
        $stop = $date . ' ' . date('H:i:s', strtotime($start_time . ' +1 hour'));
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

    /**
     * Publieke execute_kw-wrapper voor uitbreidingen (bijv. de GlassNext Dealer Suite),
     * zodat die dezelfde Odoo-instellingen en verbindingslogica hergebruiken.
     * Gooit een Exception bij fouten.
     */
    public function rpc($model, $method, $args, $kwargs = null) {
        if (!$this->is_configured()) {
            throw new Exception('Odoo-koppeling is niet geconfigureerd (GlassNext Suite > Instellingen > Odoo).');
        }
        return $this->call($model, $method, $args, $kwargs);
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

    /**
     * Haalt de beschikbare mailinglijsten op uit Odoo (mass_mailing / mailing.list).
     * Geeft altijd een array terug met success + lijsten, gooit nooit een fout.
     *
     * @return array{success:bool, lists:array, error:?string}
     */
    public function fetch_mailing_lists() {
        if (!$this->is_configured()) {
            return ['success' => false, 'lists' => [], 'error' => 'Odoo-koppeling niet geconfigureerd.'];
        }

        try {
            $this->authenticate();
            $lists = $this->call('mailing.list', 'search_read', [
                [],
            ], ['fields' => ['id', 'name', 'is_public'], 'order' => 'name asc']);

            if (!is_array($lists)) {
                return ['success' => false, 'lists' => [], 'error' => 'mailing.list gaf geen geldig resultaat terug.'];
            }

            $this->debug_log('fetch_mailing_lists: ' . count($lists) . ' lijst(en) gevonden');
            return ['success' => true, 'lists' => $lists, 'error' => null];
        } catch (Exception $e) {
            $this->debug_log('fetch_mailing_lists error: ' . $e->getMessage());
            return ['success' => false, 'lists' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * Schrijft een e-mailadres in op één of meerdere Odoo-mailinglijsten.
     * Maakt het mailing.contact aan als het nog niet bestaat, en legt de
     * toestemming (opt-in) vast als notitie in de chatter van het contact.
     * Geeft altijd een array terug, gooit nooit een fout naar buiten.
     *
     * @param string $email
     * @param string $name
     * @param int[]  $list_ids
     * @param array  $consent_meta  ['ip'=>string,'text'=>string,'page'=>string,'time'=>string]
     * @return array{success:bool, contact_id:?int, error:?string}
     */
    public function subscribe_to_mailing_list($email, $name, $list_ids, $consent_meta = []) {
        if (!$this->is_configured()) {
            return ['success' => false, 'contact_id' => null, 'error' => 'Odoo-koppeling niet geconfigureerd.'];
        }
        if (empty($list_ids) || !is_array($list_ids)) {
            return ['success' => false, 'contact_id' => null, 'error' => 'Geen mailinglijst geconfigureerd.'];
        }

        try {
            $this->authenticate();
            $this->debug_log('subscribe start: email=' . $email . ' lists=' . implode(',', $list_ids));

            $contact_id = $this->find_or_create_mailing_contact($email, $name);
            $this->debug_log('mailing.contact id=' . $contact_id);

            foreach ($list_ids as $list_id) {
                $list_id = (int) $list_id;
                if ($list_id <= 0) continue;
                $this->subscribe_contact_to_list($contact_id, $list_id);
            }

            $this->log_consent_to_chatter($contact_id, $email, $name, $list_ids, $consent_meta);

            $this->debug_log('subscribe complete: contact_id=' . $contact_id);
            return ['success' => true, 'contact_id' => $contact_id, 'error' => null];
        } catch (Exception $e) {
            $this->debug_log('subscribe error: ' . $e->getMessage());
            return ['success' => false, 'contact_id' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Zoekt een mailing.contact op e-mailadres of maakt het aan.
     */
    private function find_or_create_mailing_contact($email, $name) {
        // Odoo houdt email_normalized bij (kleine letters, getrimd) — veld bestaat vanaf Odoo 15
        $normalized = strtolower(trim($email));
        try {
            $found = $this->call('mailing.contact', 'search_read', [
                [['email_normalized', '=', $normalized]],
            ], ['fields' => ['id', 'name', 'email'], 'limit' => 1]);
            if (!empty($found) && isset($found[0]['id'])) {
                return (int) $found[0]['id'];
            }
        } catch (Exception $e) {
            $this->debug_log('email_normalized search failed, fallback op email ilike: ' . $e->getMessage());
        }

        // Fallback: niet-genormaliseerd zoeken voor oudere Odoo-versies
        $found = $this->call('mailing.contact', 'search_read', [
            [['email', '=ilike', $email]],
        ], ['fields' => ['id', 'name', 'email'], 'limit' => 1]);

        if (!empty($found) && isset($found[0]['id'])) {
            return (int) $found[0]['id'];
        }

        $new_id = $this->call('mailing.contact', 'create', [[
            'name'  => $name ?: $email,
            'email' => $email,
        ]]);
        if (!$new_id) {
            throw new Exception('mailing.contact create retourneerde geen geldig ID: ' . var_export($new_id, true));
        }
        return (int) $new_id;
    }

    /**
     * Koppelt een mailing.contact aan een mailing.list via
     * mailing.contact.subscription. Zet opt_out terug op False bij
     * her-inschrijving. Valt terug op list_ids M2M als het
     * subscription-model niet bestaat.
     */
    private function subscribe_contact_to_list($contact_id, $list_id) {
        try {
            $existing = $this->call('mailing.contact.subscription', 'search', [
                [['contact_id', '=', $contact_id], ['list_id', '=', $list_id]],
            ]);

            if (!empty($existing) && isset($existing[0])) {
                $this->call('mailing.contact.subscription', 'write', [
                    [(int) $existing[0]],
                    ['opt_out' => false],
                ]);
                $this->debug_log('subscription ' . (int) $existing[0] . ' opt_out=false (her-inschrijving)');
            } else {
                $sub_id = $this->call('mailing.contact.subscription', 'create', [[
                    'contact_id' => $contact_id,
                    'list_id'    => $list_id,
                    'opt_out'    => false,
                ]]);
                $this->debug_log('subscription created: ' . var_export($sub_id, true));
            }
        } catch (Exception $e) {
            // Oudere Odoo-versies zonder subscription-model: direct via list_ids
            $this->debug_log('subscription model failed, fallback list_ids: ' . $e->getMessage());
            $this->call('mailing.contact', 'write', [[$contact_id], ['list_ids' => [[4, $list_id]]]]);
        }
    }

    /**
     * Legt de opt-in toestemming vast als notitie in de chatter van het
     * mailing.contact (AVG-bewijs: tijdstip, IP, tekst, pagina).
     */
    private function log_consent_to_chatter($contact_id, $email, $name, $list_ids, $consent_meta) {
        $esc = function($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); };

        $body = '<p><strong>Nieuwsbrief-inschrijving via website</strong></p>';
        $body .= '<ul style="margin:0 0 0 16px;">';
        $body .= '<li>E-mail: ' . $esc($email) . '</li>';
        if ($name) $body .= '<li>Naam: ' . $esc($name) . '</li>';
        $body .= '<li>Mailinglijst(en): ' . $esc(implode(', ', array_map('intval', $list_ids))) . '</li>';
        $body .= '<li>Tijdstip: ' . $esc($consent_meta['time'] ?? current_time('mysql')) . '</li>';
        if (!empty($consent_meta['ip']))   $body .= '<li>IP-adres: ' . $esc($consent_meta['ip']) . '</li>';
        if (!empty($consent_meta['page'])) $body .= '<li>Pagina: ' . $esc($consent_meta['page']) . '</li>';
        if (!empty($consent_meta['text'])) $body .= '<li>Toestemmingstekst: <em>' . $esc($consent_meta['text']) . '</em></li>';
        $body .= '</ul>';

        $this->post_message_to_chatter('mailing.contact', $contact_id, $body);
    }
}
