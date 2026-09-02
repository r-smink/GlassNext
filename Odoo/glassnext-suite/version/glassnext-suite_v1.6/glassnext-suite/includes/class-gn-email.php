<?php
if (!defined('ABSPATH')) exit;

class GN_Email {

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter('wp_mail_from', [$this, 'filter_from_email']);
        add_filter('wp_mail_from_name', [$this, 'filter_from_name']);
    }

    public function filter_from_email($from) {
        $configured = get_option('gn_from_email', '');
        if ($configured && is_email($configured)) {
            return $configured;
        }
        return $from;
    }

    public function filter_from_name($name) {
        $configured = get_option('gn_from_name', '');
        if ($configured) {
            return $configured;
        }
        return $name;
    }

    public static function send_customer_confirmation($customer_email, $offer_number, $customer_name) {
        if (!is_email($customer_email)) return;

        $from_name = get_option('gn_from_name', 'GlassNext Suite');
        $date_str = wp_date('j F Y');
        $subject_template = get_option('gn_email_subject', 'Uw offerte aanvraag is ontvangen – {{offertenummer}}');
        $body_template = get_option('gn_email_body_template', '');

        $placeholders = [
            '{{naam}}'           => $customer_name,
            '{{offertenummer}}'  => $offer_number,
            '{{afzender_naam}}'  => $from_name,
            '{{datum}}'          => $date_str,
        ];

        $subject = strtr($subject_template, $placeholders);

        $logo_id = get_option('gn_email_logo_id', '');
        $has_logo = $logo_id && wp_get_attachment_url($logo_id);
        $has_html_template = !empty($body_template);

        if (!$has_html_template && !$has_logo) {
            // Plain text fallback (original behavior)
            $message = "Beste " . $customer_name . ",\n\n";
            $message .= "Wij hebben uw offerte aanvraag in goede orde ontvangen.\n";
            $message .= "Uw referentienummer is: " . $offer_number . "\n\n";
            $message .= "Wij nemen spoedig contact met u op om de offerte definitief te maken.\n\n";
            $message .= "Met vriendelijke groet,\n" . $from_name;
            wp_mail($customer_email, $subject, $message);
            return;
        }

        // HTML email with optional logo
        $body_html = $has_html_template ? strtr($body_template, $placeholders) : nl2br(esc_html("Beste " . $customer_name . ",\n\nWij hebben uw offerte aanvraag in goede orde ontvangen.\nUw referentienummer is: " . $offer_number . "\n\nWij nemen spoedig contact met u op om de offerte definitief te maken.\n\nMet vriendelijke groet,\n" . $from_name));

        // Convert any newlines in plain template to <br> if no HTML tags present
        if ($has_html_template && stripos($body_html, '<') === false) {
            $body_html = nl2br(esc_html($body_html));
        }

        $logo_cid = '';
        $logo_path = '';
        if ($has_logo) {
            $logo_path = get_attached_file($logo_id);
            if ($logo_path && file_exists($logo_path)) {
                $logo_cid = 'gn_logo_' . $logo_id;
            }
        }

        if ($logo_cid) {
            $body_html .= '<div style="margin-top:24px;text-align:left;"><img src="cid:' . esc_attr($logo_cid) . '" style="max-height:100px;max-width:300px;" /></div>';
        }

        // Set HTML content type temporarily
        $html_filter = function() { return 'text/html'; };
        add_filter('wp_mail_content_type', $html_filter);

        // Embed logo as inline image via PHPMailer
        if ($logo_cid && $logo_path && file_exists($logo_path)) {
            $embed_filter = function($phpmailer) use ($logo_cid, $logo_path) {
                $phpmailer->addEmbeddedImage($logo_path, $logo_cid);
            };
            add_action('phpmailer_init', $embed_filter);
        } else {
            $embed_filter = null;
        }

        wp_mail($customer_email, $subject, $body_html);

        remove_filter('wp_mail_content_type', $html_filter);
        if ($embed_filter) {
            remove_action('phpmailer_init', $embed_filter);
        }
    }

    public static function send_admin_notification($post_id, $offer_number, $customer_name, $customer_email) {
        $admin_email = get_option('gn_admin_email', get_option('admin_email'));
        if (!is_email($admin_email)) return;

        $subject = 'Nieuwe offerte aanvraag – ' . $offer_number;
        $message = "Er is een nieuwe offerte aanvraag binnengekomen.\n\n";
        $message .= "Offertenummer: " . $offer_number . "\n";
        $message .= "Klant / organisatie: " . $customer_name . "\n";
        $message .= "E-mail: " . $customer_email . "\n\n";
        $message .= "Bekijk de aanvraag in de WordPress backend:\n";
        $message .= admin_url('post.php?post=' . $post_id . '&action=edit');

        wp_mail($admin_email, $subject, $message);
    }
}
