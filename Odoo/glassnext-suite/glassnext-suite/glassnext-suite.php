<?php
/**
 * Plugin Name: GlassNext Suite
 * Description: Klant-facing tool voor snijplanner, calculatie, ROI en offerte aanvraag.
 * Version: 1.8.7
 * Author: GlassNext
 * Text Domain: glassnext-suite
 */

if (!defined('ABSPATH')) exit;

define('GN_VERSION', '1.8.7');
define('GN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GN_PLUGIN_BASENAME', plugin_basename(__FILE__));

require_once GN_PLUGIN_DIR . 'includes/class-gn-options.php';
require_once GN_PLUGIN_DIR . 'includes/class-gn-odoo.php';
require_once GN_PLUGIN_DIR . 'includes/class-gn-submissions.php';
require_once GN_PLUGIN_DIR . 'includes/class-gn-email.php';
require_once GN_PLUGIN_DIR . 'includes/class-gn-admin-columns.php';

class GlassNext_Suite {

    public function __construct() {
        GN_Options::instance();
        GN_Odoo::instance();
        GN_Submissions::instance();
        GN_Email::instance();

        add_action('init', [$this, 'init']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_shortcode('glassnext_suite', [$this, 'shortcode_render']);
        add_filter('theme_page_templates', [$this, 'register_page_template']);
        add_filter('template_include', [$this, 'load_page_template']);
    }

    public function init() {
        // Extra init acties kunnen hier worden toegevoegd.
    }

    public function register_assets() {
        wp_register_style('glassnext-style', GN_PLUGIN_URL . 'assets/glassnext-style.css', [], GN_VERSION);
        wp_register_script('jspdf', 'https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js', [], '2.5.1', true);
        wp_register_script('glassnext-tool', GN_PLUGIN_URL . 'assets/glassnext-tool.js', ['jspdf'], GN_VERSION, true);

        $options = GN_Options::get_all_options();

        // Convert tooltip image attachment IDs to URLs for frontend use
        $tooltip_cols = ['kenmerk', 'breedte', 'hoogte', 'aantal', 'rotatie', 'ruimte'];
        foreach ($tooltip_cols as $col) {
            $img_key = 'gn_tooltip_' . $col . '_image';
            if (!empty($options[$img_key])) {
                $url = wp_get_attachment_url($options[$img_key]);
                $options[$img_key] = $url ?: '';
            }
        }

        wp_localize_script('glassnext-tool', 'GN_CONFIG', [
            'ajaxUrl'   => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce('gn_submit_offer'),
            'options'   => $options,
            'logoUrl'   => GN_PLUGIN_URL . 'assets/glassnext-logo.png',
        ]);

        wp_enqueue_style('glassnext-style');
        wp_enqueue_script('jspdf');
        wp_enqueue_script('glassnext-tool');
    }

    public function shortcode_render($atts) {
        ob_start();
        echo '<div id="glassnext-app">';
        $this->render_tool();
        echo '</div>';
        return ob_get_clean();
    }

    public function render_tool() {
        include GN_PLUGIN_DIR . 'templates/glassnext-tool.php';
    }

    public function register_page_template($templates) {
        $templates['glassnext-suite-template.php'] = __('GlassNext Suite', 'glassnext-suite');
        return $templates;
    }

    public function load_page_template($template) {
        if (is_page()) {
            $meta = get_post_meta(get_the_ID(), '_wp_page_template', true);
            if ($meta === 'glassnext-suite-template.php') {
                $plugin_template = GN_PLUGIN_DIR . 'templates/page-glassnext-suite.php';
                if (file_exists($plugin_template)) {
                    return $plugin_template;
                }
            }
        }
        return $template;
    }
}

new GlassNext_Suite();
