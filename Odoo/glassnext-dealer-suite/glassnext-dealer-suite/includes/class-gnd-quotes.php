<?php
if (!defined('ABSPATH')) exit;

/**
 * GND_Quotes
 *
 * Slaat dealerofferte's op als gn_submission (zodat ze ook onder GlassNext Suite > Aanvragen
 * verschijnen) en zet ze via de Odoo-koppeling van de GlassNext Suite neer als sale.order.
 */
class GND_Quotes {

    private static $instance = null;
    const MAX_UPLOAD = 5242880; // 5 MB

    public static function instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_gnd_submit_quote', [$this, 'ajax_submit']);
        add_action('wp_ajax_gnd_list_quotes', [$this, 'ajax_list']);
        add_action('wp_ajax_gnd_retry_odoo', [$this, 'ajax_retry']);
    }

    private function guard() {
        check_ajax_referer('gnd_quote', 'nonce');
        if (!GlassNext_Dealer_Suite::user_can_access()) {
            wp_send_json_error(['message' => 'Geen toegang.'], 403);
        }
    }

    private function log($msg) {
        error_log('GlassNext Dealer: ' . $msg);
    }

    /* ------------------------------------------------------------------ */
    /* AJAX                                                                */
    /* ------------------------------------------------------------------ */

    public function ajax_submit() {
        $this->guard();

        $raw = isset($_POST['project_json']) ? wp_unslash($_POST['project_json']) : '';
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            wp_send_json_error(['message' => 'Ongeldige projectgegevens ontvangen.']);
        }

        $c = is_array($data['customer'] ?? null) ? $data['customer'] : [];
        $customer = [
            'customerName' => sanitize_text_field($c['customerName'] ?? ''),
            'contactName'  => sanitize_text_field($c['contactName'] ?? ''),
            'email'        => sanitize_email($c['email'] ?? ''),
            'phone'        => sanitize_text_field($c['phone'] ?? ''),
            'address'      => sanitize_text_field($c['address'] ?? ''),
            'city'         => sanitize_text_field($c['city'] ?? ''),
            'reference'    => sanitize_text_field($c['reference'] ?? ''),
            'description'  => sanitize_textarea_field($c['description'] ?? ''),
            'notes'        => sanitize_textarea_field($c['notes'] ?? ''),
        ];
        if ($customer['customerName'] === '') {
            wp_send_json_error(['message' => 'Vul de klant / organisatie in.']);
        }

        $panes = [];
        foreach ((array) ($data['panes'] ?? []) as $p) {
            $w = (float) str_replace(',', '.', (string) ($p['w'] ?? 0));
            $h = (float) str_replace(',', '.', (string) ($p['h'] ?? 0));
            $n = max(1, (int) ($p['n'] ?? 1));
            if ($w <= 0 || $h <= 0) continue;
            $panes[] = [
                'id'   => sanitize_text_field($p['id'] ?? ''),
                'w'    => $w,
                'h'    => $h,
                'n'    => $n,
                'rot'  => (string) ($p['rot'] ?? '1') === '0' ? '0' : '1',
                'room' => sanitize_text_field($p['room'] ?? ''),
            ];
        }
        $plan = is_array($data['plan'] ?? null) ? $data['plan'] : [];
        $placed = is_array($plan['placed'] ?? null) ? $plan['placed'] : [];
        if (!$panes || !$placed) {
            wp_send_json_error(['message' => 'Bereken eerst een snijplan met minimaal één ruit.']);
        }

        $roll_w   = max(0, (float) ($plan['rollW'] ?? 0));
        $total_mm = max(0, (float) ($plan['totalLen'] ?? 0));
        $stats    = is_array($plan['stats'] ?? null) ? $plan['stats'] : [];

        $other_costs = [];
        foreach ((array) ($data['otherCosts'] ?? []) as $oc) {
            $desc = sanitize_text_field($oc['description'] ?? '');
            $amt  = round((float) ($oc['amount'] ?? 0), 2);
            if ($desc !== '' && $amt > 0) $other_costs[] = ['description' => $desc, 'amount' => $amt];
        }

        $install_mode = ($data['installMode'] ?? '') === 'self' ? 'self' : 'professional';
        $mount_class  = in_array($data['mountClass'] ?? '', ['easy', 'average', 'complex', 'very'], true) ? $data['mountClass'] : 'average';

        $user = wp_get_current_user();
        $offer_number = $this->generate_offer_number();

        $record = [
            'version'      => 'GlassNext Dealer Suite ' . GND_VERSION,
            'source'       => 'dealer',
            'offerNumber'  => $offer_number,
            'submittedAt'  => current_time('mysql'),
            'dealer'       => ['id' => $user->ID, 'name' => $user->display_name, 'email' => $user->user_email],
            'customer'     => $customer,
            'installMode'  => $install_mode,
            'mountClass'   => $mount_class,
            'otherCosts'   => $other_costs,
            'panes'        => $panes,
            'importFile'   => sanitize_file_name($data['importFileName'] ?? ''),
            'exportData'   => [
                'pieces'     => count($placed),
                'rollLength' => round($total_mm / 1000, 4),
                'rollArea'   => round($roll_w * $total_mm / 1e6, 4),
                'netArea'    => round((float) ($stats['netArea'] ?? 0), 4),
                'wasteArea'  => round((float) ($stats['wasteArea'] ?? 0), 4),
                'loss'       => round((float) ($stats['loss'] ?? 0), 2),
            ],
            'calc'         => is_array($data['calc'] ?? null) ? array_map('floatval', array_intersect_key($data['calc'], array_flip(['subtotal', 'vat', 'total', 'matGross', 'cut', 'other']))) : [],
            'planData'     => [
                'rollW'    => $roll_w,
                'totalLen' => $total_mm,
                'placed'   => array_map(function ($p) {
                    return [
                        'label'   => sanitize_text_field($p['label'] ?? ''),
                        'baseId'  => sanitize_text_field($p['baseId'] ?? ''),
                        'copy'    => (int) ($p['copy'] ?? 1),
                        'room'    => sanitize_text_field($p['room'] ?? ''),
                        'x'       => (float) ($p['x'] ?? 0),
                        'y'       => (float) ($p['y'] ?? 0),
                        'w'       => (float) ($p['w'] ?? 0),
                        'h'       => (float) ($p['h'] ?? 0),
                        'netW'    => (float) ($p['netW'] ?? 0),
                        'netH'    => (float) ($p['netH'] ?? 0),
                        'rotated' => !empty($p['rotated']),
                        'margin'  => (float) ($p['margin'] ?? 0),
                        'seamSide'=> sanitize_text_field($p['seamSide'] ?? ''),
                    ];
                }, $placed),
            ],
        ];

        $post_id = wp_insert_post([
            'post_type'   => 'gn_submission',
            'post_title'  => sprintf('[%s] %s (dealer: %s)', $offer_number, $customer['customerName'], $user->display_name),
            'post_status' => 'publish',
        ]);
        if (is_wp_error($post_id) || !$post_id) {
            wp_send_json_error(['message' => 'Opslaan van de offerte is mislukt.']);
        }

        $json = wp_json_encode($record);
        update_post_meta($post_id, '_gn_json', wp_slash($json));
        update_post_meta($post_id, '_gn_filename', sanitize_file_name($offer_number . '_' . $customer['customerName'] . '.json'));
        update_post_meta($post_id, '_gn_offer_number', $offer_number);
        update_post_meta($post_id, '_gn_customer_name', $customer['customerName']);
        update_post_meta($post_id, '_gn_contact_name', $customer['contactName']);
        update_post_meta($post_id, '_gn_email', $customer['email']);
        update_post_meta($post_id, '_gn_phone', $customer['phone']);
        update_post_meta($post_id, '_gn_address', $customer['address']);
        update_post_meta($post_id, '_gn_city', $customer['city']);
        update_post_meta($post_id, '_gn_status', 'nieuw');
        update_post_meta($post_id, '_gn_flow_mode', 'dealer');
        update_post_meta($post_id, '_gn_install_mode', $install_mode);
        update_post_meta($post_id, '_gn_source', 'dealer');
        update_post_meta($post_id, '_gnd_user_id', $user->ID);

        // Bijlagen: origineel importbestand, snijplan-CSV en snijplan-PDF.
        $attachment_ids = [];
        $import_id = $this->save_import_file($post_id, $offer_number);
        if ($import_id) $attachment_ids[] = $import_id;
        $csv_id = $this->save_attachment($post_id, $offer_number . '_snijplan.csv', $this->plan_csv($record['planData']['placed']), 'text/csv');
        if ($csv_id) $attachment_ids[] = $csv_id;
        $pdf_id = $this->save_plan_pdf($post_id, $offer_number);
        if ($pdf_id) $attachment_ids[] = $pdf_id;
        update_post_meta($post_id, '_gnd_attachment_ids', $attachment_ids);
        if ($csv_id) update_post_meta($post_id, '_gn_plan_csv_attachment_id', $csv_id);
        if ($pdf_id) update_post_meta($post_id, '_gn_plan_pdf_attachment_id', $pdf_id);

        $odoo = $this->sync_to_odoo($post_id);

        if (GlassNext_Dealer_Suite::opt('gnd_notify_admin') === '1' && class_exists('GN_Email')) {
            GN_Email::send_admin_notification($post_id, $offer_number, $customer['customerName'] . ' (dealer: ' . $user->display_name . ')', $customer['email']);
        }

        wp_send_json_success([
            'offerNumber' => $offer_number,
            'postId'      => $post_id,
            'odoo'        => $odoo,
        ]);
    }

    public function ajax_list() {
        $this->guard();
        $is_admin = current_user_can('manage_options');
        $meta = [['key' => '_gn_source', 'value' => 'dealer']];
        if (!$is_admin) {
            $meta[] = ['key' => '_gnd_user_id', 'value' => get_current_user_id()];
        }
        $posts = get_posts([
            'post_type'      => 'gn_submission',
            'post_status'    => 'any',
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => $meta,
        ]);
        $rows = [];
        foreach ($posts as $p) {
            $rec = json_decode(get_post_meta($p->ID, '_gn_json', true), true) ?: [];
            $rows[] = [
                'postId'     => $p->ID,
                'date'       => get_the_date('d-m-Y H:i', $p),
                'offer'      => get_post_meta($p->ID, '_gn_offer_number', true),
                'customer'   => get_post_meta($p->ID, '_gn_customer_name', true),
                'reference'  => $rec['customer']['reference'] ?? '',
                'pieces'     => $rec['exportData']['pieces'] ?? 0,
                'rollArea'   => $rec['exportData']['rollArea'] ?? 0,
                'dealer'     => $is_admin ? ($rec['dealer']['name'] ?? '') : '',
                'odooId'     => get_post_meta($p->ID, '_gn_odoo_order_id', true),
                'odooName'   => get_post_meta($p->ID, '_gnd_odoo_order_name', true),
                'odooError'  => get_post_meta($p->ID, '_gn_odoo_error', true),
                'odooUrl'    => $is_admin ? $this->odoo_order_url(get_post_meta($p->ID, '_gn_odoo_order_id', true)) : '',
            ];
        }
        wp_send_json_success(['rows' => $rows, 'isAdmin' => $is_admin]);
    }

    public function ajax_retry() {
        $this->guard();
        $post_id = (int) ($_POST['post_id'] ?? 0);
        if (!$post_id || get_post_meta($post_id, '_gn_source', true) !== 'dealer') {
            wp_send_json_error(['message' => 'Offerte niet gevonden.']);
        }
        if (!current_user_can('manage_options') && (int) get_post_meta($post_id, '_gnd_user_id', true) !== get_current_user_id()) {
            wp_send_json_error(['message' => 'Geen toegang tot deze offerte.'], 403);
        }
        if (get_post_meta($post_id, '_gn_odoo_order_id', true)) {
            wp_send_json_error(['message' => 'Deze offerte staat al in Odoo.']);
        }
        wp_send_json_success(['odoo' => $this->sync_to_odoo($post_id)]);
    }

    /* ------------------------------------------------------------------ */
    /* Odoo                                                                */
    /* ------------------------------------------------------------------ */

    /**
     * Zet een opgeslagen dealerofferte neer in Odoo. Gooit nooit; resultaat wordt ook als post meta bewaard.
     */
    public function sync_to_odoo($post_id) {
        $rec = json_decode(get_post_meta($post_id, '_gn_json', true), true);
        $result = ['success' => false, 'orderId' => null, 'orderName' => '', 'url' => '', 'error' => null];
        if (!is_array($rec)) {
            $result['error'] = 'Projectgegevens niet leesbaar.';
            update_post_meta($post_id, '_gn_odoo_error', $result['error']);
            return $result;
        }

        $odoo = GN_Odoo::instance();
        try {
            $cust   = $rec['customer'];
            $dealer = $rec['dealer'];
            $offer  = $rec['offerNumber'];
            $export = $rec['exportData'];
            $self   = ($rec['installMode'] ?? '') === 'self';

            if (GlassNext_Dealer_Suite::opt('gnd_partner_mode') === 'dealer') {
                $partner_id = $this->find_or_create_partner($odoo, $dealer['email'], $dealer['name'], '', '', '');
                $ref = $offer . ' – ' . $cust['customerName'] . ($cust['reference'] ? ' – ' . $cust['reference'] : '');
            } else {
                $partner_id = $this->find_or_create_partner($odoo, $cust['email'], $cust['contactName'] ?: $cust['customerName'], $cust['phone'], $cust['address'], $cust['city'], $cust['customerName']);
                $ref = $offer . ($cust['reference'] ? ' – ' . $cust['reference'] : '');
            }

            $order_id = (int) $odoo->rpc('sale.order', 'create', [['partner_id' => $partner_id, 'client_order_ref' => $ref]]);
            if (!$order_id) throw new Exception('sale.order create gaf geen ID terug.');
            // Direct vastleggen: bij een latere fout niet opnieuw een order aanmaken.
            update_post_meta($post_id, '_gn_odoo_order_id', $order_id);

            $p_material = (int) get_option('gn_odoo_product_material', 3);
            $p_mount    = (int) get_option('gn_odoo_product_mount', 5);
            $p_cut      = (int) get_option('gn_odoo_product_cut', 85);
            $p_travel   = (int) get_option('gn_odoo_product_voorrijd', 86);

            // Zelfde orderregels als de websiteflow van de GlassNext Suite.
            if ($export['rollArea'] > 0) $this->line($odoo, $order_id, $p_material, $export['rollArea']);
            if (!$self && $export['rollLength'] > 0) $this->line($odoo, $order_id, $p_mount, $export['rollLength']);
            if ($export['pieces'] > 0) $this->line($odoo, $order_id, $p_cut, $export['pieces']);
            foreach ($rec['otherCosts'] as $oc) {
                $odoo->rpc('sale.order.line', 'create', [[
                    'order_id' => $order_id, 'name' => $oc['description'], 'product_uom_qty' => 1, 'price_unit' => $oc['amount'],
                ]]);
            }
            if (!$self) $this->line($odoo, $order_id, $p_travel, 1);

            $this->add_tag($odoo, $order_id);

            try {
                $odoo->rpc('sale.order', 'message_post', [[$order_id]], [
                    'body' => $this->chatter_html($rec), 'message_type' => 'comment', 'subtype_xmlid' => 'mail.mt_note', 'body_is_html' => true,
                ]);
            } catch (Exception $e) { $this->log('chatter: ' . $e->getMessage()); }

            foreach ((array) get_post_meta($post_id, '_gnd_attachment_ids', true) as $att_id) {
                $this->upload_attachment($odoo, $order_id, (int) $att_id);
            }

            $name = '';
            try {
                $read = $odoo->rpc('sale.order', 'read', [[$order_id]], ['fields' => ['name']]);
                $name = $read[0]['name'] ?? '';
            } catch (Exception $e) { $this->log('read name: ' . $e->getMessage()); }

            update_post_meta($post_id, '_gnd_odoo_order_name', $name);
            update_post_meta($post_id, '_gn_odoo_error', '');
            $result = ['success' => true, 'orderId' => $order_id, 'orderName' => $name,
                'url' => current_user_can('manage_options') ? $this->odoo_order_url($order_id) : '', 'error' => null];
        } catch (Exception $e) {
            $this->log('sync error post ' . $post_id . ': ' . $e->getMessage());
            update_post_meta($post_id, '_gn_odoo_error', $e->getMessage());
            $result['error'] = $e->getMessage();
            $result['orderId'] = get_post_meta($post_id, '_gn_odoo_order_id', true) ?: null;
        }
        return $result;
    }

    private function line($odoo, $order_id, $product_id, $qty) {
        if ($product_id <= 0 || $qty <= 0) return;
        $odoo->rpc('sale.order.line', 'create', [[
            'order_id' => $order_id, 'product_id' => $product_id, 'product_uom_qty' => round((float) $qty, 4),
        ]]);
    }

    private function find_or_create_partner($odoo, $email, $name, $phone, $address, $city, $company = '') {
        if ($email) {
            $found = $odoo->rpc('res.partner', 'search_read', [[['email', '=ilike', $email]]], ['fields' => ['id'], 'limit' => 1]);
            if (!empty($found[0]['id'])) return (int) $found[0]['id'];
        } elseif ($name) {
            $found = $odoo->rpc('res.partner', 'search_read', [[['name', '=', $name]]], ['fields' => ['id'], 'limit' => 1]);
            if (!empty($found[0]['id'])) return (int) $found[0]['id'];
        }
        $data = ['name' => $name ?: ($company ?: $email)];
        if ($email)   $data['email'] = $email;
        if ($phone)   $data['phone'] = $phone;
        if ($address) $data['street'] = $address;
        if ($city)    $data['city'] = $city;
        if ($company && $company !== $data['name']) $data['comment'] = 'Organisatie: ' . $company;
        $id = (int) $odoo->rpc('res.partner', 'create', [$data]);
        if (!$id) throw new Exception('res.partner create gaf geen ID terug.');
        return $id;
    }

    private function add_tag($odoo, $order_id) {
        $tag = trim((string) GlassNext_Dealer_Suite::opt('gnd_odoo_tag'));
        if ($tag === '') return;
        try {
            $found = $odoo->rpc('crm.tag', 'search', [[['name', '=', $tag]]]);
            $tag_id = !empty($found[0]) ? (int) $found[0] : (int) $odoo->rpc('crm.tag', 'create', [['name' => $tag]]);
            if ($tag_id) $odoo->rpc('sale.order', 'write', [[$order_id], ['tag_ids' => [[4, $tag_id]]]]);
        } catch (Exception $e) {
            $this->log('tag (niet fataal): ' . $e->getMessage());
        }
    }

    private function upload_attachment($odoo, $order_id, $att_id) {
        $path = get_attached_file($att_id);
        if (!$path || !file_exists($path)) return;
        try {
            $odoo->rpc('ir.attachment', 'create', [[
                'name'      => basename($path),
                'datas'     => base64_encode(file_get_contents($path)),
                'res_model' => 'sale.order',
                'res_id'    => $order_id,
                'mimetype'  => get_post_mime_type($att_id) ?: 'application/octet-stream',
            ]]);
        } catch (Exception $e) {
            $this->log('attachment ' . basename($path) . ': ' . $e->getMessage());
        }
    }

    private function odoo_order_url($order_id) {
        $base = get_option('gn_odoo_url', '');
        if (!$order_id || !$base) return '';
        return trailingslashit($base) . 'web#id=' . (int) $order_id . '&model=sale.order&view_type=form';
    }

    private function chatter_html($rec) {
        $e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
        $row = function ($l, $v) use ($e) {
            return '<tr><td style="padding:3px 12px 3px 0;font-weight:600;color:#4a5568;vertical-align:top">' . $e($l) . '</td><td style="padding:3px 0">' . nl2br($e($v)) . '</td></tr>';
        };
        $h4 = function ($t) use ($e) { return '<h4 style="margin:12px 0 6px;color:#2c3e50;border-bottom:1px solid #e2e8f0;padding-bottom:4px">' . $e($t) . '</h4>'; };
        $c = $rec['customer']; $x = $rec['exportData'];
        $classes = ['easy' => 'Eenvoudig', 'average' => 'Gemiddeld', 'complex' => 'Complex', 'very' => 'Zeer complex'];

        $h  = '<div style="font-family:Arial,sans-serif;font-size:13px">';
        $h .= '<h3 style="margin:0 0 6px;color:#2c3e50">GlassNext Dealer Offerte — ' . $e($rec['offerNumber']) . '</h3>';
        $h .= '<p style="margin:0 0 10px;color:#718096;font-size:12px">Aangemaakt op ' . $e($rec['submittedAt']) . ' door ' . $e($rec['dealer']['name']) . ' (' . $e($rec['dealer']['email']) . ')' . ($rec['importFile'] ? ' — import: ' . $e($rec['importFile']) : '') . '</p>';
        $h .= $h4('Klant') . '<table style="border-collapse:collapse">';
        foreach ([['Klant / organisatie', $c['customerName']], ['Contactpersoon', $c['contactName']], ['E-mail', $c['email']], ['Telefoon', $c['phone']], ['Adres', $c['address']], ['Postcode / plaats', $c['city']], ['Referentie dealer', $c['reference']], ['Omschrijving', $c['description']], ['Bijzonderheden', $c['notes']]] as $r) {
            if ($r[1] !== '') $h .= $row($r[0], $r[1]);
        }
        $h .= '</table>';
        $h .= $h4('Uitvoering') . '<table style="border-collapse:collapse">';
        $h .= $row('Aanbrengen', $rec['installMode'] === 'self' ? 'Folie zelf aanbrengen' : 'Folie laten aanbrengen door GlassNext');
        if ($rec['installMode'] !== 'self') $h .= $row('Montageklasse', $classes[$rec['mountClass']] ?? $rec['mountClass']);
        $h .= '</table>';
        $h .= $h4('Ruiten') . '<table style="border-collapse:collapse;width:100%"><thead><tr style="background:#f7fafc;text-align:left"><th style="padding:3px 8px">Kenmerk</th><th style="padding:3px 8px">B × H (cm)</th><th style="padding:3px 8px">Aantal</th><th style="padding:3px 8px">Rotatie</th><th style="padding:3px 8px">Ruimte</th></tr></thead><tbody>';
        foreach ($rec['panes'] as $p) {
            $h .= '<tr><td style="padding:2px 8px">' . $e($p['id']) . '</td><td style="padding:2px 8px">' . $e($p['w']) . ' × ' . $e($p['h']) . '</td><td style="padding:2px 8px">' . $e($p['n']) . '</td><td style="padding:2px 8px">' . ($p['rot'] === '1' ? 'Ja' : 'Nee') . '</td><td style="padding:2px 8px">' . $e($p['room']) . '</td></tr>';
        }
        $h .= '</tbody></table>';
        $h .= $h4('Snijplan') . '<table style="border-collapse:collapse">';
        $h .= $row('Aantal stukken', $x['pieces']);
        $h .= $row('Netto glas', number_format($x['netArea'], 2, ',', '.') . ' m²');
        $h .= $row('Rollengte', number_format($x['rollLength'], 2, ',', '.') . ' m');
        $h .= $row('Rolverbruik', number_format($x['rollArea'], 2, ',', '.') . ' m²');
        $h .= $row('Snijverlies', number_format($x['wasteArea'], 2, ',', '.') . ' m² (' . number_format($x['loss'], 1, ',', '.') . '%)');
        $h .= '</table></div>';
        return $h;
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    private function generate_offer_number() {
        $prefix = trim((string) GlassNext_Dealer_Suite::opt('gnd_offer_prefix'));
        if ($prefix === '') {
            return GN_Submissions::instance()->generate_offer_number();
        }
        $year = date('Y');
        $counter = (int) get_option('gnd_offer_counter', 0);
        if (get_option('gnd_offer_counter_year', $year) != $year) $counter = 0;
        $counter++;
        update_option('gnd_offer_counter', $counter);
        update_option('gnd_offer_counter_year', $year);
        return sprintf('%s-%s-%03d', $prefix, $year, $counter);
    }

    private function plan_csv($placed) {
        usort($placed, function ($a, $b) { return ($a['y'] <=> $b['y']) ?: ($a['x'] <=> $b['x']); });
        $rows = [['Piece_ID', 'Pane_ID', 'Copy', 'Room', 'X_mm', 'Y_mm', 'Gross_Width_mm', 'Gross_Height_mm', 'Net_Width_mm', 'Net_Height_mm', 'Rotated', 'Margin_per_side_mm']];
        foreach ($placed as $p) {
            $rows[] = [$p['label'], $p['baseId'], $p['copy'], $p['room'], round($p['x']), round($p['y']), round($p['w']), round($p['h']), round($p['netW']), round($p['netH']), $p['rotated'] ? 1 : 0, round($p['margin'])];
        }
        $csv = "\u{FEFF}";
        foreach ($rows as $r) {
            $csv .= implode(';', array_map(function ($v) { return '"' . str_replace('"', '""', (string) $v) . '"'; }, $r)) . "\r\n";
        }
        return $csv;
    }

    private function save_import_file($post_id, $offer_number) {
        if (empty($_FILES['import_file']['tmp_name']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) return 0;
        if ($_FILES['import_file']['size'] > self::MAX_UPLOAD) return 0;
        $ext = strtolower(pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION));
        $mimes = ['csv' => 'text/csv', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        if (!isset($mimes[$ext])) return 0;
        $contents = file_get_contents($_FILES['import_file']['tmp_name']);
        if ($contents === false || $contents === '') return 0;
        return $this->save_attachment($post_id, $offer_number . '_ruitmaten_import.' . $ext, $contents, $mimes[$ext]);
    }

    private function save_plan_pdf($post_id, $offer_number) {
        $uri = isset($_POST['plan_pdf']) ? wp_unslash($_POST['plan_pdf']) : '';
        if (strpos($uri, 'data:application/pdf') !== 0 || strpos($uri, 'base64,') === false) return 0;
        $bin = base64_decode(explode('base64,', $uri, 2)[1], true);
        if ($bin === false || strlen($bin) < 100 || strlen($bin) > 15 * self::MAX_UPLOAD || strpos($bin, '%PDF') !== 0) return 0;
        return $this->save_attachment($post_id, $offer_number . '_snijplan.pdf', $bin, 'application/pdf');
    }

    private function save_attachment($post_id, $filename, $data, $mime) {
        $up = wp_upload_dir();
        if (!empty($up['error'])) return 0;
        $dir = $up['path'] . '/glassnext';
        if (!file_exists($dir)) wp_mkdir_p($dir);
        $filename = wp_unique_filename($dir, sanitize_file_name($filename));
        $path = $dir . '/' . $filename;
        if (file_put_contents($path, $data) === false) return 0;
        $id = wp_insert_attachment([
            'post_mime_type' => $mime,
            'post_title'     => preg_replace('/\.[^.]+$/', '', $filename),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'post_parent'    => $post_id,
        ], $path, $post_id);
        return (is_wp_error($id) || !$id) ? 0 : (int) $id;
    }
}
