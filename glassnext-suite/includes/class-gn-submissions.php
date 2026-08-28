<?php
if (!defined('ABSPATH')) exit;

class GN_Submissions {

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', [$this, 'register_post_type']);
        add_action('admin_menu', [$this, 'add_submissions_menu'], 20);
        add_action('wp_ajax_gn_submit_offer', [$this, 'ajax_submit_offer']);
        add_action('wp_ajax_nopriv_gn_submit_offer', [$this, 'ajax_submit_offer']);
        add_action('admin_init', [$this, 'handle_json_download']);
    }

    public function register_post_type() {
        register_post_type('gn_submission', [
            'labels' => [
                'name'          => 'Offerte aanvragen',
                'singular_name' => 'Offerte aanvraag',
                'menu_name'     => 'Aanvragen',
                'view_item'     => 'Aanvraag bekijken',
                'search_items'  => 'Aanvragen zoeken',
                'not_found'     => 'Geen aanvragen gevonden.',
            ],
            'public'          => false,
            'show_ui'         => true,
            'show_in_menu'    => false,
            'capability_type' => 'post',
            'capabilities'    => ['edit_post' => 'manage_options', 'read_post' => 'manage_options', 'delete_post' => 'manage_options', 'edit_posts' => 'manage_options', 'edit_others_posts' => 'manage_options', 'publish_posts' => 'manage_options', 'read_private_posts' => 'manage_options'],
            'map_meta_cap'    => false,
            'supports'        => ['title', 'custom-fields'],
            'has_archive'     => false,
        ]);
    }

    public function add_submissions_menu() {
        if (!current_user_can('manage_options')) return;
        global $submenu;
        $submenu['glassnext-suite'][] = ['Aanvragen', 'manage_options', 'edit.php?post_type=gn_submission'];
    }

    public function generate_offer_number() {
        $prefix = get_option('gn_offer_prefix', 'GNW');
        $year   = date('Y');
        $stored_year = get_option('gn_offer_counter_year', $year);
        $counter = (int) get_option('gn_offer_counter', 0);

        if ($stored_year != $year) {
            $counter = 0;
            update_option('gn_offer_counter_year', $year);
        }

        $counter++;
        update_option('gn_offer_counter', $counter);

        return sprintf('%s-%s-%03d', $prefix, $year, $counter);
    }

    public function ajax_submit_offer() {
        check_ajax_referer('gn_submit_offer', 'nonce');

        $project_json = isset($_POST['project_json']) ? wp_unslash($_POST['project_json']) : '';
        $customer_name = isset($_POST['customer_name']) ? sanitize_text_field($_POST['customer_name']) : '';
        $contact_name = isset($_POST['contact_name']) ? sanitize_text_field($_POST['contact_name']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $address = isset($_POST['address']) ? sanitize_text_field($_POST['address']) : '';
        $city = isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '';

        if (empty($customer_name)) {
            wp_send_json_error(['message' => 'Klant / organisatie naam is verplicht.']);
        }

        $offer_number = $this->generate_offer_number();

        $decoded = json_decode($project_json, true);
        if ($decoded) {
            $decoded['offerNumber'] = $offer_number;
            $decoded['submittedAt'] = current_time('mysql');
            $project_json = wp_json_encode($decoded);
        }

        $date_str = current_time('Y-m-d');
        $safe_name = sanitize_file_name($customer_name);
        $filename = $safe_name . '_' . $date_str . '.json';

        $post_id = wp_insert_post([
            'post_type'    => 'gn_submission',
            'post_title'   => sprintf('[%s] %s', $offer_number, $customer_name),
            'post_status'  => 'publish',
            'post_content' => '',
        ]);

        if (is_wp_error($post_id)) {
            wp_send_json_error(['message' => 'Fout bij opslaan van de aanvraag.']);
        }

        update_post_meta($post_id, '_gn_json', $project_json);
        update_post_meta($post_id, '_gn_filename', $filename);
        update_post_meta($post_id, '_gn_offer_number', $offer_number);
        update_post_meta($post_id, '_gn_customer_name', $customer_name);
        update_post_meta($post_id, '_gn_contact_name', $contact_name);
        update_post_meta($post_id, '_gn_email', $email);
        update_post_meta($post_id, '_gn_phone', $phone);
        update_post_meta($post_id, '_gn_address', $address);
        update_post_meta($post_id, '_gn_city', $city);
        update_post_meta($post_id, '_gn_status', 'nieuw');

        GN_Email::send_customer_confirmation($email, $offer_number, $customer_name);
        GN_Email::send_admin_notification($post_id, $offer_number, $customer_name, $email);

        $this->dispatch_makecom_webhook($decoded, $offer_number, $customer_name, $contact_name, $email, $phone, $address, $city);

        wp_send_json_success([
            'offerNumber' => $offer_number,
            'message'     => 'Uw offerte aanvraag is ontvangen. Wij nemen spoedig contact met u op.',
        ]);
    }

    public function dispatch_makecom_webhook($decoded, $offer_number, $customer_name, $contact_name, $email, $phone, $address, $city) {
        $webhook_url = get_option('gn_makecom_webhook_url', '');
        if (empty($webhook_url)) return;

        $values = $decoded['values'] ?? [];
        $export = $decoded['exportData'] ?? [];

        $payload = [
            'offerNumber'  => $offer_number,
            'submittedAt'  => current_time('mysql'),
            'customer' => [
                'name'        => $customer_name,
                'contactName' => $contact_name,
                'email'       => $email,
                'phone'       => $phone,
                'address'     => $address,
                'city'        => $city,
            ],
            'project' => [
                'description' => $values['projectDescription'] ?? '',
                'notes'       => $values['projectNotes'] ?? '',
            ],
            'calculation' => [
                'pieces'     => $export['pieces'] ?? 0,
                'rollArea'   => $export['rollArea'] ?? 0,
                'rollLength' => $export['rollLength'] ?? 0,
                'mountClass' => $values['mountClass'] ?? 'average',
            ],
            'lineItems' => $this->extract_line_items($decoded),
            'totals' => $this->extract_totals($decoded),
            'panes'   => $decoded['panes'] ?? [],
            'rawJson' => $decoded,
        ];

        wp_remote_post($webhook_url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($payload),
            'timeout' => 15,
        ]);
    }

    private function extract_line_items($decoded) {
        $items = [];
        $values = $decoded['values'] ?? [];
        $export = $decoded['exportData'] ?? [];

        $rollArea = floatval($export['rollArea'] ?? 0);
        $matRate = floatval($values['materialPrice'] ?? 0);
        $matDisc = floatval($values['materialDiscount'] ?? 0);
        $matGross = $rollArea * $matRate;
        $items[] = [
            'name'        => 'GlassShield materiaal',
            'description' => sprintf('%.2f m² rolverbruik à €%.2f/m²', $rollArea, $matRate),
            'quantity'    => 1,
            'unitPrice'   => round($matGross * (1 - $matDisc / 100), 2),
        ];

        $pieces = intval($export['pieces'] ?? 0);
        $cutRate = floatval($values['cutPrice'] ?? 0);
        $items[] = [
            'name'        => 'Voorsnijden',
            'description' => sprintf('%d stuks à €%.2f/stuk', $pieces, $cutRate),
            'quantity'    => 1,
            'unitPrice'   => round($pieces * $cutRate, 2),
        ];

        $mountClass = $values['mountClass'] ?? 'average';
        $mountRate = floatval($values['mountSelectedPrice'] ?? 0);
        $mountAreaBasis = $values['mountAreaBasis'] ?? 'net';
        $mountArea = $mountAreaBasis === 'gross'
            ? floatval($export['rollArea'] ?? 0)
            : floatval($values['roiArea'] ?? 0);
        $mountDisc = floatval($values['mountDiscount'] ?? 0);
        $mountGross = $mountArea * $mountRate;
        $items[] = [
            'name'        => 'Montage – ' . $mountClass,
            'description' => sprintf('%.2f m² à €%.2f/m²', $mountArea, $mountRate),
            'quantity'    => 1,
            'unitPrice'   => round($mountGross * (1 - $mountDisc / 100), 2),
        ];

        foreach (($decoded['otherCosts'] ?? []) as $cost) {
            if (!empty($cost['description']) && floatval($cost['amount']) > 0) {
                $items[] = [
                    'name'        => $cost['description'],
                    'description' => 'Overige kosten',
                    'quantity'    => 1,
                    'unitPrice'   => floatval($cost['amount']),
                ];
            }
        }

        return $items;
    }

    private function extract_totals($decoded) {
        $lineItems = $this->extract_line_items($decoded);
        $subtotal = array_reduce($lineItems, function($sum, $item) {
            return $sum + $item['unitPrice'];
        }, 0);
        $vatRate = floatval($decoded['values']['vatRate'] ?? 21);
        return [
            'subtotal' => round($subtotal, 2),
            'vatRate'  => $vatRate,
            'total'    => round($subtotal * (1 + $vatRate / 100), 2),
        ];
    }

    public function handle_json_download() {
        if (!isset($_GET['gn_download_json']) || !isset($_GET['post_id'])) return;
        if (!current_user_can('manage_options')) return;
        $post_id = (int) $_GET['post_id'];
        $json = get_post_meta($post_id, '_gn_json', true);
        $filename = get_post_meta($post_id, '_gn_filename', true);
        if (!$filename) {
            $filename = 'glassnext_submission_' . $post_id . '.json';
        }
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($json));
        echo $json;
        exit;
    }
}
