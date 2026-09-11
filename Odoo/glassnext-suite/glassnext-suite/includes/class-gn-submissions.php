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
        // wp_unslash can corrupt JSON strings that contain escaped quotes.
        // Re-encode properly: decode, then encode again.
        $decoded = json_decode($project_json, true);
        if ($decoded === null && isset($_POST['project_json'])) {
            // wp_unslash broke the JSON escaping — try with raw POST data
            $raw_json = $_POST['project_json'];
            // WordPress magic quotes added slashes; use stripslashes to get original
            $decoded = json_decode(stripslashes($raw_json), true);
            if ($decoded !== null) {
                $project_json = wp_json_encode($decoded);
            }
        }
        $customer_name = isset($_POST['customer_name']) ? sanitize_text_field($_POST['customer_name']) : '';
        $contact_name = isset($_POST['contact_name']) ? sanitize_text_field($_POST['contact_name']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $address = isset($_POST['address']) ? sanitize_text_field($_POST['address']) : '';
        $city = isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '';
        $flow_mode = isset($_POST['flow_mode']) ? sanitize_text_field($_POST['flow_mode']) : 'self';
        $install_mode = isset($_POST['install_mode']) ? sanitize_text_field($_POST['install_mode']) : 'professional';
        $pref_date1 = isset($_POST['pref_date1']) ? sanitize_text_field($_POST['pref_date1']) : '';
        $pref_date2 = isset($_POST['pref_date2']) ? sanitize_text_field($_POST['pref_date2']) : '';
        $pref_date3 = isset($_POST['pref_date3']) ? sanitize_text_field($_POST['pref_date3']) : '';
        $pref_day1 = isset($_POST['pref_day1']) ? sanitize_text_field($_POST['pref_day1']) : '';
        $pref_day2 = isset($_POST['pref_day2']) ? sanitize_text_field($_POST['pref_day2']) : '';
        $pref_day3 = isset($_POST['pref_day3']) ? sanitize_text_field($_POST['pref_day3']) : '';
        $pref_time1 = isset($_POST['pref_time1']) ? sanitize_text_field($_POST['pref_time1']) : '';
        $pref_time2 = isset($_POST['pref_time2']) ? sanitize_text_field($_POST['pref_time2']) : '';
        $pref_time3 = isset($_POST['pref_time3']) ? sanitize_text_field($_POST['pref_time3']) : '';

        if (empty($customer_name)) {
            wp_send_json_error(['message' => 'Klant / organisatie naam is verplicht.']);
        }

        $offer_number = $this->generate_offer_number();

        $decoded = json_decode($project_json, true);
        if ($decoded) {
            $decoded['offerNumber'] = $offer_number;
            if (isset($decoded['values']) && is_array($decoded['values'])) {
                $decoded['values']['offerNumber'] = $offer_number;
            }
            $decoded['flowMode'] = $flow_mode;
            $decoded['installMode'] = $install_mode;
            if ($flow_mode === 'measure') {
                $decoded['prefDate1'] = $pref_date1;
                $decoded['prefDate2'] = $pref_date2;
                $decoded['prefDate3'] = $pref_date3;
                $decoded['prefDay1'] = $pref_day1;
                $decoded['prefDay2'] = $pref_day2;
                $decoded['prefDay3'] = $pref_day3;
                $decoded['prefTime1'] = $pref_time1;
                $decoded['prefTime2'] = $pref_time2;
                $decoded['prefTime3'] = $pref_time3;
            }
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
        update_post_meta($post_id, '_gn_flow_mode', $flow_mode);
        update_post_meta($post_id, '_gn_install_mode', $install_mode);
        if ($flow_mode === 'measure') {
            update_post_meta($post_id, '_gn_pref_date1', $pref_date1);
            update_post_meta($post_id, '_gn_pref_date2', $pref_date2);
            update_post_meta($post_id, '_gn_pref_date3', $pref_date3);
            update_post_meta($post_id, '_gn_pref_day1', $pref_day1);
            update_post_meta($post_id, '_gn_pref_day2', $pref_day2);
            update_post_meta($post_id, '_gn_pref_day3', $pref_day3);
            update_post_meta($post_id, '_gn_pref_time1', $pref_time1);
            update_post_meta($post_id, '_gn_pref_time2', $pref_time2);
            update_post_meta($post_id, '_gn_pref_time3', $pref_time3);
        }

        // Sla Snijplan PDF op (vanuit browser als base64 meegestuurd)
        $pdf_attachment_id = $this->save_plan_pdf_attachment($post_id, $offer_number, $decoded);
        if ($pdf_attachment_id) {
            update_post_meta($post_id, '_gn_plan_pdf_attachment_id', $pdf_attachment_id);
        }

        // Genereer en sla Snijplan CSV op (server-side uit planData)
        $csv_attachment_id = $this->save_plan_csv_attachment($post_id, $offer_number, $decoded);
        if ($csv_attachment_id) {
            update_post_meta($post_id, '_gn_plan_csv_attachment_id', $csv_attachment_id);
        }

        // Sla geüploade foto's op als WordPress attachments
        $photo_attachment_ids = $this->save_uploaded_photos($post_id, $offer_number);
        if ($photo_attachment_ids) {
            update_post_meta($post_id, '_gn_photo_attachment_ids', $photo_attachment_ids);
        }

        GN_Email::send_customer_confirmation($email, $offer_number, $customer_name);
        GN_Email::send_admin_notification($post_id, $offer_number, $customer_name, $email);

        $this->dispatch_makecom_webhook($decoded, $offer_number, $customer_name, $contact_name, $email, $phone, $address, $city);

        // Verzamel bijlagen voor Odoo (PDF + CSV + foto's als URL)
        $odoo_attachments = $this->build_odoo_attachments($pdf_attachment_id, $csv_attachment_id, $offer_number);
        foreach ($photo_attachment_ids as $pid) {
            $path = get_attached_file($pid);
            if ($path && file_exists($path)) {
                $url = wp_get_attachment_url($pid);
                if ($url) {
                    $odoo_attachments[] = [
                        'name'     => basename($path),
                        'url'      => $url,
                        'mimetype' => get_post_mime_type($pid) ?: 'image/jpeg',
                    ];
                }
            }
        }

        $odoo_result = GN_Odoo::instance()->sync_order($decoded, $offer_number, $customer_name, $contact_name, $email, $phone, $address, $city, $odoo_attachments, $flow_mode, $pref_date1, $pref_date2, $pref_date3, $install_mode, $pref_day1, $pref_day2, $pref_day3, $pref_time1, $pref_time2, $pref_time3);
        update_post_meta($post_id, '_gn_odoo_order_id', $odoo_result['order_id'] ?? '');
        update_post_meta($post_id, '_gn_odoo_error', $odoo_result['error'] ?? '');

        $thankyou_url = get_option('gn_thankyou_url', '');

        wp_send_json_success([
            'offerNumber'  => $offer_number,
            'message'      => 'Uw offerte aanvraag is ontvangen. Wij nemen spoedig contact met u op.',
            'thankYouUrl'  => $thankyou_url,
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
            'flowMode'     => $decoded['flowMode'] ?? 'self',
            'installMode'  => $decoded['installMode'] ?? 'professional',
            'prefDates'    => ($decoded['flowMode'] ?? 'self') === 'measure' ? [$decoded['prefDate1'] ?? '', $decoded['prefDate2'] ?? '', $decoded['prefDate3'] ?? ''] : [],
            'prefDays'     => ($decoded['flowMode'] ?? 'self') === 'measure' ? [$decoded['prefDay1'] ?? '', $decoded['prefDay2'] ?? '', $decoded['prefDay3'] ?? ''] : [],
            'prefTimes'    => ($decoded['flowMode'] ?? 'self') === 'measure' ? [$decoded['prefTime1'] ?? '', $decoded['prefTime2'] ?? '', $decoded['prefTime3'] ?? ''] : [],
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
                'installMode' => $values['installMode'] ?? 'professional',
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

        if (($decoded['flowMode'] ?? 'self') === 'measure') {
            // For measure flow, inmeten cost is included as information in the CRM lead note,
            // not as a product line. Return empty items.
            return [];
        }

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

        if (($values['installMode'] ?? 'professional') !== 'self') {
            $items[] = [
                'name'        => 'Montage – ' . $mountClass,
                'description' => sprintf('%.2f m² à €%.2f/m²', $mountArea, $mountRate),
                'quantity'    => 1,
                'unitPrice'   => round($mountGross * (1 - $mountDisc / 100), 2),
            ];
        }

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

        $install_mode = $decoded['installMode'] ?? ($values['installMode'] ?? 'professional');
        if (($decoded['flowMode'] ?? 'self') === 'measure' || $install_mode !== 'self') {
            $items[] = [
                'name'        => 'Voorrijkosten',
                'description' => 'Nader te berekenen',
                'quantity'    => 1,
                'unitPrice'   => 0,
                'productId'   => (int) get_option('gn_odoo_product_voorrijd', 86),
            ];
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

    /**
     * Slaat de Snijplan PDF (als base64 data URI meegestuurd door de browser) op
     * als WordPress attachment op de gn_submission post.
     */
    private function save_plan_pdf_attachment($post_id, $offer_number, $decoded) {
        if (empty($_POST['plan_pdf'])) return 0;

        $data_uri = wp_unslash($_POST['plan_pdf']);
        // Verwacht formaat: data:application/pdf;base64,XXXX
        if (strpos($data_uri, 'base64,') === false) return 0;

        $parts = explode('base64,', $data_uri, 2);
        $base64 = $parts[1] ?? '';
        if (empty($base64)) return 0;

        $binary = base64_decode($base64);
        if ($binary === false || strlen($binary) < 100) return 0;

        $filename = !empty($_POST['plan_pdf_filename']) ? sanitize_file_name($_POST['plan_pdf_filename']) : sanitize_file_name($offer_number . '_snijplan.pdf');
        // Zorg dat de bestandsnaam het offertenummer bevat
        if (strpos($filename, $offer_number) === false) {
            $filename = sanitize_file_name($offer_number . '_snijplan.pdf');
        }

        return $this->save_binary_attachment($post_id, $filename, $binary, 'application/pdf');
    }

    /**
     * Genereert de Snijplan CSV server-side uit de planData in de project-JSON
     * en slaat deze op als WordPress attachment.
     */
    private function save_plan_csv_attachment($post_id, $offer_number, $decoded) {
        $plan_data = $decoded['planData'] ?? null;
        if (!$plan_data || empty($plan_data['placed'])) return 0;

        $csv = $this->generate_csv_from_plan($plan_data);
        if (empty($csv)) return 0;

        $filename = sanitize_file_name($offer_number . '_snijplan.csv');
        return $this->save_binary_attachment($post_id, $filename, $csv, 'text/csv');
    }

    /**
     * Genereert CSV string uit planData (zelfde formaat als JS downloadCSV).
     */
    private function generate_csv_from_plan($plan_data) {
        $placed = $plan_data['placed'] ?? [];
        if (empty($placed)) return '';

        // Sorteer op Y, dan X (zelfde als JS)
        usort($placed, function($a, $b) {
            return $a['y'] <=> $b['y'] ?: $a['x'] <=> $b['x'];
        });

        $rows = [['Piece_ID', 'Pane_ID', 'Copy', 'Room', 'X_mm', 'Y_mm', 'Gross_Width_mm', 'Gross_Height_mm', 'Net_Width_mm', 'Net_Height_mm', 'Rotated', 'Margin_per_side_mm']];
        foreach ($placed as $p) {
            $rows[] = [
                $p['label'] ?? '',
                $p['baseId'] ?? '',
                $p['copy'] ?? '',
                $p['room'] ?? '',
                round($p['x'] ?? 0),
                round($p['y'] ?? 0),
                round($p['w'] ?? 0),
                round($p['h'] ?? 0),
                round($p['netW'] ?? 0),
                round($p['netH'] ?? 0),
                !empty($p['rotated']) ? 1 : 0,
                round($p['margin'] ?? 0),
            ];
        }

        // BOM + CSV met ; separator en " quoting (zelfde als JS)
        $csv = "\u{FEFF}";
        foreach ($rows as $row) {
            $csv .= implode(';', array_map(function($v) {
                return '"' . str_replace('"', '""', (string) $v) . '"';
            }, $row)) . "\r\n";
        }
        return $csv;
    }

    /**
     * Slaat binaire data op als WordPress attachment op de gn_submission post.
     */
    private function save_binary_attachment($post_id, $filename, $data, $mime_type) {
        $upload_dir = wp_upload_dir();
        if (!empty($upload_dir['error'])) return 0;

        // Plaats bestanden in een glassnext submap
        $gn_dir = $upload_dir['path'] . '/glassnext';
        if (!file_exists($gn_dir)) {
            wp_mkdir_p($gn_dir);
        }

        // Unieke bestandsnaam
        $unique_filename = wp_unique_filename($gn_dir, $filename);
        $filepath = $gn_dir . '/' . $unique_filename;

        file_put_contents($filepath, $data);
        if (!file_exists($filepath)) return 0;

        $attachment = [
            'post_mime_type' => $mime_type,
            'post_title'     => preg_replace('/\.[^.]+$/', '', $unique_filename),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'post_parent'    => $post_id,
        ];

        $attach_id = wp_insert_attachment($attachment, $filepath, $post_id);
        if (is_wp_error($attach_id) || !$attach_id) return 0;

        // Genereer metadata (alleen relevant voor afbeeldingen, maar niet schadelijk voor andere types)
        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
        $metadata = wp_generate_attachment_metadata($attach_id, $filepath);
        if ($metadata) {
            wp_update_attachment_metadata($attach_id, $metadata);
        }

        return (int) $attach_id;
    }

    /**
     * Slaat geüploade foto's (photo_0, photo_1, photo_2) op als WordPress attachments.
     */
    private function save_uploaded_photos($post_id, $offer_number) {
        $attachment_ids = [];
        for ($i = 0; $i < 3; $i++) {
            $key = 'photo_' . $i;
            if (empty($_FILES[$key]['tmp_name']) || $_FILES[$key]['error'] !== UPLOAD_ERR_OK) continue;
            $tmp_name = $_FILES[$key]['tmp_name'];
            $orig_name = $_FILES[$key]['name'];
            $mime = $_FILES[$key]['type'] ?: 'image/jpeg';
            $contents = file_get_contents($tmp_name);
            if ($contents === false || strlen($contents) < 100) continue;
            $ext = pathinfo($orig_name, PATHINFO_EXTENSION) ?: 'jpg';
            $filename = sanitize_file_name($offer_number . '_foto_' . ($i + 1) . '.' . $ext);
            $attach_id = $this->save_binary_attachment($post_id, $filename, $contents, $mime);
            if ($attach_id) $attachment_ids[] = $attach_id;
        }
        return $attachment_ids;
    }

    /**
     * Bouwt een array met URL-bijlagen voor Odoo (PDF + CSV).
     */
    private function build_odoo_attachments($pdf_attachment_id, $csv_attachment_id, $offer_number) {
        $attachments = [];

        if ($pdf_attachment_id) {
            $path = get_attached_file($pdf_attachment_id);
            if ($path && file_exists($path)) {
                $url = wp_get_attachment_url($pdf_attachment_id);
                if ($url) {
                    $attachments[] = [
                        'name'       => $offer_number . '_snijplan.pdf',
                        'url'        => $url,
                        'mimetype'   => 'application/pdf',
                    ];
                } else {
                    error_log('GlassNext build_odoo_attachments: PDF has no URL for attachment_id=' . $pdf_attachment_id);
                }
            } else {
                error_log('GlassNext build_odoo_attachments: PDF file not found for attachment_id=' . $pdf_attachment_id);
            }
        }

        if ($csv_attachment_id) {
            $path = get_attached_file($csv_attachment_id);
            if ($path && file_exists($path)) {
                $url = wp_get_attachment_url($csv_attachment_id);
                if ($url) {
                    $attachments[] = [
                        'name'       => $offer_number . '_snijplan.csv',
                        'url'        => $url,
                        'mimetype'   => 'text/csv',
                    ];
                } else {
                    error_log('GlassNext build_odoo_attachments: CSV has no URL for attachment_id=' . $csv_attachment_id);
                }
            } else {
                error_log('GlassNext build_odoo_attachments: CSV file not found for attachment_id=' . $csv_attachment_id);
            }
        }

        return $attachments;
    }
}
