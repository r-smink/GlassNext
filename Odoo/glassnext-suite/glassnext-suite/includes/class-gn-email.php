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

        $subject = 'Uw offerte aanvraag is ontvangen – ' . $offer_number;
        $message = "Beste " . $customer_name . ",\n\n";
        $message .= "Wij hebben uw offerte aanvraag in goede orde ontvangen.\n";
        $message .= "Uw referentienummer is: " . $offer_number . "\n\n";
        $message .= "Wij nemen spoedig contact met u op om de offerte definitief te maken.\n\n";
        $message .= "Met vriendelijke groet,\n";
        $message .= get_option('gn_from_name', 'GlassNext Suite');

        wp_mail($customer_email, $subject, $message);
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
