<?php
/**
 * Plugin Name: GlassNext Dealer Suite
 * Description: Afgeschermde suite voor admins en dealers: ruitmaten importeren (CSV/XLSX), snijplan berekenen en direct als offerte naar Odoo sturen. Gebruikt de instellingen en Odoo-koppeling van de GlassNext Suite. Shortcode: [glassnext_dealer_suite]
 * Version: 1.0.0
 * Author: GlassNext
 * Requires Plugins: glassnext-suite
 * Text Domain: glassnext-dealer-suite
 */

if (!defined('ABSPATH')) exit;

define('GND_VERSION', '1.0.0');
define('GND_PLUGIN_FILE', __FILE__);
define('GND_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GND_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GND_CAP', 'gn_dealer_access');
define('GND_ROLE', 'gn_dealer');

require_once GND_PLUGIN_DIR . 'includes/class-gnd-quotes.php';

/**
 * Rol + capability. Beheerders krijgen de capability ook, zodat zij de suite kunnen gebruiken.
 */
function gnd_activate() {
    add_role(GND_ROLE, 'GlassNext dealer', ['read' => true, GND_CAP => true]);
    $admin = get_role('administrator');
    if ($admin && !$admin->has_cap(GND_CAP)) {
        $admin->add_cap(GND_CAP);
    }
}
register_activation_hook(__FILE__, 'gnd_activate');

/** Rol blijft bij deactiveren bestaan zodat dealer-accounts niet hun rol verliezen. */

class GlassNext_Dealer_Suite {

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('plugins_loaded', [$this, 'boot'], 20);
    }

    public static function dependencies_ok() {
        return class_exists('GN_Odoo') && class_exists('GN_Options') && class_exists('GN_Submissions')
            && method_exists('GN_Odoo', 'rpc');
    }

    public static function user_can_access() {
        return is_user_logged_in() && (current_user_can(GND_CAP) || current_user_can('manage_options'));
    }

    public function boot() {
        add_action('admin_notices', [$this, 'dependency_notice']);
        add_action('admin_menu', [$this, 'admin_menu'], 30);
        add_action('admin_init', [$this, 'register_settings']);
        add_shortcode('glassnext_dealer_suite', [$this, 'shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);

        if (self::dependencies_ok()) {
            GND_Quotes::instance();
        }
    }

    public function dependency_notice() {
        if (self::dependencies_ok() || !current_user_can('activate_plugins')) return;
        echo '<div class="notice notice-error"><p><strong>GlassNext Dealer Suite</strong> vereist de plugin <strong>GlassNext Suite versie 1.9.2 of hoger</strong> (actief). Installeer/update die eerst.</p></div>';
    }

    /* ------------------------------------------------------------------ */
    /* Instellingen                                                        */
    /* ------------------------------------------------------------------ */

    public static function defaults() {
        return [
            'gnd_odoo_tag'        => 'dealer',
            'gnd_partner_mode'    => 'customer',
            'gnd_notify_admin'    => '1',
            'gnd_login_intro'     => 'Log in met je dealer- of beheerdersaccount om ruitmaten te importeren en offertes aan te maken.',
            'gnd_offer_prefix'    => '',
        ];
    }

    public static function opt($key) {
        $d = self::defaults();
        return get_option($key, $d[$key] ?? '');
    }

    public function admin_menu() {
        $parent = self::dependencies_ok() ? 'glassnext-suite' : 'options-general.php';
        add_submenu_page($parent, 'Dealer Suite', 'Dealer Suite', 'manage_options', 'glassnext-dealer-suite', [$this, 'render_settings']);
    }

    public function register_settings() {
        foreach (array_keys(self::defaults()) as $key) {
            register_setting('gnd_settings_group', $key, ['sanitize_callback' => 'sanitize_text_field']);
        }
    }

    public function render_settings() {
        $dealers = get_users(['role' => GND_ROLE, 'fields' => ['ID', 'display_name', 'user_email']]);
        ?>
        <div class="wrap">
            <h1>GlassNext Dealer Suite</h1>
            <?php if (!self::dependencies_ok()) : ?>
                <div class="notice notice-error inline"><p>GlassNext Suite 1.9.2+ is niet actief. De Dealer Suite werkt pas als die plugin actief is.</p></div>
            <?php endif; ?>
            <p>Plaats de shortcode <code>[glassnext_dealer_suite]</code> op een pagina. Niet-ingelogde bezoekers zien een inlogformulier; alleen beheerders en gebruikers met de rol <strong>GlassNext dealer</strong> krijgen toegang.</p>
            <p>Rolbreedte, snijrand, prijzen, product-ID's en de Odoo-verbinding komen uit <a href="<?php echo esc_url(admin_url('admin.php?page=glassnext-suite')); ?>">GlassNext Suite &gt; Instellingen</a>.</p>

            <form method="post" action="options.php">
                <?php settings_fields('gnd_settings_group'); ?>
                <table class="form-table">
                    <tr><th><label for="gnd_partner_mode">Odoo-klant op de offerte</label></th><td>
                        <select id="gnd_partner_mode" name="gnd_partner_mode">
                            <option value="customer" <?php selected(self::opt('gnd_partner_mode'), 'customer'); ?>>Eindklant (ingevulde klantgegevens)</option>
                            <option value="dealer" <?php selected(self::opt('gnd_partner_mode'), 'dealer'); ?>>Dealer (ingelogde gebruiker)</option>
                        </select>
                        <p class="description">Bij "Dealer" wordt de offerte op naam van de dealer gezet (gezocht op het e-mailadres van het WordPress-account); de eindklant staat dan in de referentie en de chatter.</p>
                    </td></tr>
                    <tr><th><label for="gnd_odoo_tag">Odoo-label</label></th><td>
                        <input type="text" id="gnd_odoo_tag" name="gnd_odoo_tag" value="<?php echo esc_attr(self::opt('gnd_odoo_tag')); ?>" class="regular-text">
                        <p class="description">Label dat aan de verkooporder wordt toegevoegd (in plaats van "website"). Leeg = geen label.</p>
                    </td></tr>
                    <tr><th><label for="gnd_offer_prefix">Voorvoegsel offertenummer</label></th><td>
                        <input type="text" id="gnd_offer_prefix" name="gnd_offer_prefix" value="<?php echo esc_attr(self::opt('gnd_offer_prefix')); ?>" class="small-text" placeholder="<?php echo esc_attr(get_option('gn_offer_prefix', 'GNW')); ?>">
                        <p class="description">Leeg = zelfde voorvoegsel en teller als de websiteaanvragen. Bijv. <code>GND</code> geeft <code>GND-<?php echo esc_html(date('Y')); ?>-001</code> met een eigen teller.</p>
                    </td></tr>
                    <tr><th><label for="gnd_notify_admin">Notificatie</label></th><td>
                        <input type="hidden" name="gnd_notify_admin" value="0">
                        <label><input type="checkbox" id="gnd_notify_admin" name="gnd_notify_admin" value="1" <?php checked(self::opt('gnd_notify_admin'), '1'); ?>> Admin-e-mail sturen bij elke dealerofferte</label>
                    </td></tr>
                    <tr><th><label for="gnd_login_intro">Tekst boven inlogformulier</label></th><td>
                        <input type="text" id="gnd_login_intro" name="gnd_login_intro" value="<?php echo esc_attr(self::opt('gnd_login_intro')); ?>" class="large-text">
                    </td></tr>
                </table>
                <?php submit_button('Instellingen opslaan'); ?>
            </form>

            <h2>Dealers</h2>
            <p>Maak een dealer aan via <a href="<?php echo esc_url(admin_url('user-new.php')); ?>">Gebruikers &gt; Nieuwe toevoegen</a> en kies de rol <strong>GlassNext dealer</strong>. Een dealer ziet alleen zijn eigen offertes; beheerders zien alles.</p>
            <?php if ($dealers) : ?>
                <table class="widefat striped" style="max-width:700px"><thead><tr><th>Naam</th><th>E-mail</th></tr></thead><tbody>
                <?php foreach ($dealers as $u) : ?>
                    <tr><td><a href="<?php echo esc_url(get_edit_user_link($u->ID)); ?>"><?php echo esc_html($u->display_name); ?></a></td><td><?php echo esc_html($u->user_email); ?></td></tr>
                <?php endforeach; ?>
                </tbody></table>
            <?php else : ?>
                <p><em>Nog geen dealers.</em></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /* ------------------------------------------------------------------ */
    /* Frontend                                                            */
    /* ------------------------------------------------------------------ */

    public function register_assets() {
        wp_register_style('gnd-app', GND_PLUGIN_URL . 'assets/gnd-app.css', [], GND_VERSION);
        wp_register_script('gnd-jspdf', 'https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js', [], '2.5.1', true);
        wp_register_script('gnd-xlsx', 'https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js', [], '0.20.3', true);
        wp_register_script('gnd-app', GND_PLUGIN_URL . 'assets/gnd-app.js', ['gnd-jspdf', 'gnd-xlsx'], GND_VERSION, true);
    }

    /** Alleen de opties die de rekentool nodig heeft — nooit Odoo-gegevens of andere geheimen. */
    private function public_config() {
        $keys = [
            'gn_roll_width', 'gn_roll_length', 'gn_margin_side', 'gn_kerf', 'gn_quality', 'gn_rotate_default',
            'gn_material_price', 'gn_cut_price', 'gn_material_discount', 'gn_mount_discount', 'gn_vat_rate',
            'gn_mount_class', 'gn_mount_selected_price', 'gn_mount_area_basis',
            'gn_mount_rate_easy', 'gn_mount_rate_average', 'gn_mount_rate_complex', 'gn_mount_rate_very',
            'gn_other_costs',
        ];
        $all = GN_Options::get_all_options();
        return array_intersect_key($all, array_flip($keys));
    }

    public function shortcode($atts) {
        if (!is_user_logged_in()) {
            return $this->render_login();
        }
        if (!self::user_can_access()) {
            return '<div class="gnd-notice">Je account heeft geen toegang tot de GlassNext Dealer Suite. Neem contact op met GlassNext.</div>';
        }
        if (!self::dependencies_ok()) {
            return '<div class="gnd-notice">De GlassNext Dealer Suite is niet beschikbaar: de plugin GlassNext Suite (1.9.2+) is niet actief.</div>';
        }

        $user = wp_get_current_user();
        wp_enqueue_style('gnd-app');
        wp_enqueue_script('gnd-app');
        wp_localize_script('gnd-app', 'GND_CONFIG', [
            'ajaxUrl'  => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('gnd_quote'),
            'options'  => $this->public_config(),
            'user'     => ['name' => $user->display_name, 'email' => $user->user_email, 'isAdmin' => current_user_can('manage_options')],
            'logoutUrl'=> wp_logout_url(get_permalink()),
        ]);

        ob_start();
        include GND_PLUGIN_DIR . 'templates/dealer-app.php';
        return ob_get_clean();
    }

    private function render_login() {
        wp_enqueue_style('gnd-app');
        $redirect = (is_ssl() ? 'https://' : 'http://') . sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'] ?? '')) . esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'] ?? '/'));
        $html  = '<div class="gnd-login">';
        $html .= '<h2>GlassNext Dealer Suite</h2>';
        $html .= '<p>' . esc_html(self::opt('gnd_login_intro')) . '</p>';
        $html .= wp_login_form([
            'echo'           => false,
            'redirect'       => $redirect,
            'label_username' => 'Gebruikersnaam of e-mailadres',
            'label_password' => 'Wachtwoord',
            'label_remember' => 'Ingelogd blijven',
            'label_log_in'   => 'Inloggen',
        ]);
        $html .= '<p class="gnd-login-lost"><a href="' . esc_url(wp_lostpassword_url($redirect)) . '">Wachtwoord vergeten?</a></p>';
        $html .= '</div>';
        return $html;
    }
}

GlassNext_Dealer_Suite::instance();
