<?php
/**
 * Plugin Name: GlassNext Dealer Suite
 * Description: Afgeschermde mobiele webapp (PWA) voor dealers en beheerders: ruitmaten invoeren of importeren (CSV/XLSX), snijplan berekenen en direct als offerte naar Odoo sturen. Gebruikt de instellingen en Odoo-koppeling van de GlassNext Suite. App: /dealers/ — shortcode: [glassnext_dealer_suite]
 * Version: 1.1.2
 * Author: GlassNext
 * Requires Plugins: glassnext-suite
 * Text Domain: glassnext-dealer-suite
 */

if (!defined('ABSPATH')) exit;

define('GND_VERSION', '1.1.2');
define('GND_PLUGIN_FILE', __FILE__);
define('GND_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GND_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GND_CAP', 'gn_dealer_access');
define('GND_ROLE', 'gn_dealer');

require_once GND_PLUGIN_DIR . 'includes/class-gnd-quotes.php';
require_once GND_PLUGIN_DIR . 'includes/class-gnd-pwa.php';

/**
 * Rol + capability. Beheerders krijgen de capability ook, zodat zij de suite kunnen gebruiken.
 */
function gnd_activate() {
    add_role(GND_ROLE, 'GlassNext dealer', ['read' => true, GND_CAP => true]);
    $admin = get_role('administrator');
    if ($admin && !$admin->has_cap(GND_CAP)) {
        $admin->add_cap(GND_CAP);
    }
    delete_option('gnd_rules_hash'); // rewrite rules bij de volgende request opnieuw opbouwen
}
register_activation_hook(__FILE__, 'gnd_activate');

function gnd_deactivate() {
    delete_option('gnd_rules_hash');
    flush_rewrite_rules(false);
}
register_deactivation_hook(__FILE__, 'gnd_deactivate');

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
        add_filter('auth_cookie_expiration', [$this, 'cookie_expiration'], 10, 3);

        GND_PWA::instance();
        if (self::dependencies_ok()) {
            GND_Quotes::instance();
        }
    }

    public function dependency_notice() {
        if (self::dependencies_ok() || !current_user_can('activate_plugins')) return;
        echo '<div class="notice notice-error"><p><strong>GlassNext Dealer Suite</strong> vereist de plugin <strong>GlassNext Suite versie 1.9.2 of hoger</strong> (actief). Installeer/update die eerst.</p></div>';
    }

    /**
     * Dealers die "Ingelogd blijven" aanvinken blijven 30 dagen ingelogd (WordPress-standaard: 14),
     * zodat de geïnstalleerde app niet steeds om een wachtwoord vraagt. Beheerders houden de standaard.
     */
    public function cookie_expiration($length, $user_id, $remember) {
        if (!$remember) return $length;
        $user = get_userdata($user_id);
        if ($user && in_array(GND_ROLE, (array) $user->roles, true) && !user_can($user, 'manage_options')) {
            return max($length, 30 * DAY_IN_SECONDS);
        }
        return $length;
    }

    /* ------------------------------------------------------------------ */
    /* Instellingen                                                        */
    /* ------------------------------------------------------------------ */

    public static function defaults() {
        return [
            'gnd_odoo_tag'               => 'dealer',
            'gnd_partner_mode'           => 'customer',
            'gnd_notify_admin'           => '1',
            'gnd_login_intro'            => 'Log in met je dealer- of beheerdersaccount om ruitmaten in te voeren en offertes aan te maken.',
            'gnd_offer_prefix'           => '',
            'gnd_app_slug'               => 'dealers',
            'gnd_app_icon'               => '',
            'gnd_android_package'        => '',
            'gnd_android_fingerprint'    => '',
        ];
    }

    public static function opt($key) {
        $d = self::defaults();
        $v = get_option($key, $d[$key] ?? '');
        if ($key === 'gnd_app_slug') {
            $v = sanitize_title($v);
            if ($v === '') $v = 'dealers';
        }
        return $v;
    }

    /** Volledige URL van de app, altijd met afsluitende slash. */
    public static function app_url() {
        return trailingslashit(home_url('/' . self::opt('gnd_app_slug')));
    }

    /** Pad van de app (scope van de service worker), bijv. /dealers/ of /wp/dealers/. */
    public static function app_path() {
        $path = wp_parse_url(self::app_url(), PHP_URL_PATH);
        return $path ? trailingslashit($path) : '/';
    }

    public function admin_menu() {
        $parent = self::dependencies_ok() ? 'glassnext-suite' : 'options-general.php';
        add_submenu_page($parent, 'Dealer Suite', 'Dealer Suite', 'manage_options', 'glassnext-dealer-suite', [$this, 'render_settings']);
    }

    public function register_settings() {
        $callbacks = [
            'gnd_app_slug'            => function ($v) { $v = sanitize_title($v); return $v === '' ? 'dealers' : $v; },
            'gnd_app_icon'            => function ($v) { $v = absint($v); return $v ? (string) $v : ''; },
            'gnd_android_package'     => function ($v) { return preg_replace('/[^A-Za-z0-9._]/', '', (string) $v); },
            'gnd_android_fingerprint' => function ($v) { return strtoupper(preg_replace('/[^A-Fa-f0-9:,\s]/', '', (string) $v)); },
        ];
        foreach (array_keys(self::defaults()) as $key) {
            register_setting('gnd_settings_group', $key, ['sanitize_callback' => $callbacks[$key] ?? 'sanitize_text_field']);
        }
        // Gewijzigde slug → rewrite rules opnieuw opbouwen.
        add_action('update_option_gnd_app_slug', function () { delete_option('gnd_rules_hash'); });
        add_action('add_option_gnd_app_slug', function () { delete_option('gnd_rules_hash'); });
    }

    public function render_settings() {
        $dealers = get_users(['role' => GND_ROLE, 'fields' => ['ID', 'display_name', 'user_email']]);
        $app_url = self::app_url();
        $icon_id = (int) self::opt('gnd_app_icon');
        ?>
        <div class="wrap">
            <h1>GlassNext Dealer Suite</h1>
            <?php if (!self::dependencies_ok()) : ?>
                <div class="notice notice-error inline"><p>GlassNext Suite 1.9.2+ is niet actief. De Dealer Suite werkt pas als die plugin actief is.</p></div>
            <?php endif; ?>
            <p><strong>Dealer-app:</strong> <a href="<?php echo esc_url($app_url); ?>" target="_blank" rel="noopener"><?php echo esc_html($app_url); ?></a>
                — installeerbaar als app op iOS en Android (Deel → "Zet op beginscherm" / "App installeren").</p>
            <p>De suite kan ook binnen het thema op een pagina met de shortcode <code>[glassnext_dealer_suite]</code>. Alleen beheerders en gebruikers met de rol <strong>GlassNext dealer</strong> krijgen toegang.</p>
            <p>Rolbreedte, snijrand, prijzen, product-ID's en de Odoo-verbinding komen uit <a href="<?php echo esc_url(admin_url('admin.php?page=glassnext-suite')); ?>">GlassNext Suite &gt; Instellingen</a>.</p>

            <form method="post" action="options.php">
                <?php settings_fields('gnd_settings_group'); ?>
                <h2>Offertes</h2>
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

                <h2>App</h2>
                <table class="form-table">
                    <tr><th><label for="gnd_app_slug">Adres van de app</label></th><td>
                        <code><?php echo esc_html(trailingslashit(home_url('/'))); ?></code><input type="text" id="gnd_app_slug" name="gnd_app_slug" value="<?php echo esc_attr(self::opt('gnd_app_slug')); ?>" class="regular-text" style="width:160px">
                        <p class="description">Standaard <code>dealers</code>. Let op: er mag geen gewone pagina met hetzelfde adres bestaan. Laat <code>dealer.glassnext.nl</code> met een 301-redirect hierheen verwijzen (zie README).</p>
                    </td></tr>
                    <tr><th><label for="gnd_app_icon">App-icoon (ID mediabibliotheek)</label></th><td>
                        <input type="number" min="0" id="gnd_app_icon" name="gnd_app_icon" value="<?php echo esc_attr($icon_id ?: ''); ?>" class="small-text">
                        <?php if ($icon_id && wp_attachment_is_image($icon_id)) echo wp_get_attachment_image($icon_id, [48, 48], false, ['style' => 'vertical-align:middle;margin-left:8px;border-radius:8px']); ?>
                        <p class="description">Vierkante PNG van minimaal 512×512 px (logo met wat ruimte rondom). Leeg = het meegeleverde GlassNext-logo. Het ID staat in de URL bij Media &gt; bestand bewerken (<code>post=123</code>).</p>
                    </td></tr>
                    <tr><th><label for="gnd_android_package">Android-app (later)</label></th><td>
                        <input type="text" id="gnd_android_package" name="gnd_android_package" value="<?php echo esc_attr(self::opt('gnd_android_package')); ?>" class="regular-text" placeholder="nl.glassnext.dealer"><br>
                        <input type="text" id="gnd_android_fingerprint" name="gnd_android_fingerprint" value="<?php echo esc_attr(self::opt('gnd_android_fingerprint')); ?>" class="large-text" placeholder="SHA-256 fingerprint(s) van de signing key, komma-gescheiden" style="margin-top:6px">
                        <p class="description">Alleen nodig voor een Play Store-versie (Trusted Web Activity). Ingevuld = <code><?php echo esc_html(home_url('/.well-known/assetlinks.json')); ?></code> wordt geserveerd.</p>
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
    /* Assets                                                              */
    /* ------------------------------------------------------------------ */

    /** Asset-URL's (met versie) — gedeeld door shortcode, app-shell en service worker. */
    public static function asset_urls() {
        $v = '?ver=' . GND_VERSION;
        return [
            'css'   => GND_PLUGIN_URL . 'assets/gnd-app.css' . $v,
            'jspdf' => GND_PLUGIN_URL . 'assets/vendor/jspdf.umd.min.js' . $v,
            'xlsx'  => GND_PLUGIN_URL . 'assets/vendor/xlsx.full.min.js' . $v,
            'app'   => GND_PLUGIN_URL . 'assets/gnd-app.js' . $v,
        ];
    }

    public function register_assets() {
        wp_register_style('gnd-app', GND_PLUGIN_URL . 'assets/gnd-app.css', [], GND_VERSION);
        wp_register_script('gnd-jspdf', GND_PLUGIN_URL . 'assets/vendor/jspdf.umd.min.js', [], GND_VERSION, true);
        wp_register_script('gnd-xlsx', GND_PLUGIN_URL . 'assets/vendor/xlsx.full.min.js', [], GND_VERSION, true);
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

    /** Configuratie voor gnd-app.js. */
    public function app_config($mode) {
        $user = wp_get_current_user();
        $back = $mode === 'app' ? self::app_url() : get_permalink();
        $cfg = [
            'mode'      => $mode,
            'version'   => GND_VERSION,
            'ajaxUrl'   => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce('gnd_quote'),
            'options'   => $this->public_config(),
            'user'      => ['id' => (int) $user->ID, 'name' => $user->display_name, 'email' => $user->user_email, 'isAdmin' => current_user_can('manage_options')],
            'logoutUrl' => wp_logout_url($back),
            'appUrl'    => self::app_url(),
        ];
        if ($mode === 'app') {
            $cfg['swUrl'] = GND_PWA::sw_url();
            $cfg['scope'] = self::app_path();
        }
        return $cfg;
    }

    /** Status van de huidige bezoeker: 'login', 'denied', 'unavailable' of 'ok'. */
    public static function access_state() {
        if (!is_user_logged_in()) return 'login';
        if (!self::user_can_access()) return 'denied';
        if (!self::dependencies_ok()) return 'unavailable';
        return 'ok';
    }

    public static function notice_html($state, $logout_back) {
        if ($state === 'denied') {
            return '<div class="gnd-notice">Je account heeft geen toegang tot de GlassNext Dealer Suite. Neem contact op met GlassNext.'
                . ' <a href="' . esc_url(wp_logout_url($logout_back)) . '">Uitloggen</a></div>';
        }
        return '<div class="gnd-notice">De GlassNext Dealer Suite is tijdelijk niet beschikbaar: de plugin GlassNext Suite (1.9.2+) is niet actief.</div>';
    }

    public function shortcode($atts) {
        $state = self::access_state();
        if ($state === 'login') {
            wp_enqueue_style('gnd-app');
            return $this->login_html(null);
        }
        if ($state !== 'ok') {
            wp_enqueue_style('gnd-app');
            return self::notice_html($state, get_permalink());
        }

        wp_enqueue_style('gnd-app');
        wp_enqueue_script('gnd-app');
        wp_localize_script('gnd-app', 'GND_CONFIG', $this->app_config('shortcode'));

        $gnd_mode = 'shortcode';
        ob_start();
        include GND_PLUGIN_DIR . 'templates/dealer-app.php';
        return ob_get_clean();
    }

    /** Volledig scherm-app op /dealers/ (zonder thema). */
    public function render_app_page() {
        nocache_headers();
        header('Content-Type: text/html; charset=' . get_option('blog_charset'));
        header('X-Robots-Tag: noindex, nofollow');

        $state  = self::access_state();
        $assets = self::asset_urls();
        $config = $state === 'ok' ? $this->app_config('app') : null;
        $icons  = GND_PWA::icons();
        $body   = '';

        if ($state === 'login') {
            $body = $this->login_html(self::app_url());
        } elseif ($state !== 'ok') {
            $body = self::notice_html($state, self::app_url());
        } else {
            $gnd_mode = 'app';
            ob_start();
            include GND_PLUGIN_DIR . 'templates/dealer-app.php';
            $body = ob_get_clean();
        }

        include GND_PLUGIN_DIR . 'templates/app-shell.php';
        exit;
    }

    /**
     * Inlogformulier. $redirect = null → huidige URL.
     */
    public function login_html($redirect) {
        if ($redirect === null) {
            $redirect = (is_ssl() ? 'https://' : 'http://') . sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'] ?? '')) . esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'] ?? '/'));
        }
        $failed = isset($_GET['login']) && $_GET['login'] === 'failed';
        $html  = '<div class="gnd-login">';
        $html .= '<img class="gnd-login-logo" src="' . esc_url(GND_PWA::icons()['list'][0]['src']) . '" alt="" width="72" height="72">';
        $html .= '<h1>GlassNext Dealer</h1>';
        $html .= '<p>' . esc_html(self::opt('gnd_login_intro')) . '</p>';
        if ($failed) {
            $html .= '<p class="gnd-login-error" role="alert">Inloggen mislukt. Controleer je gebruikersnaam en wachtwoord.</p>';
        }
        $html .= wp_login_form([
            'echo'           => false,
            'redirect'       => $redirect,
            'label_username' => 'Gebruikersnaam of e-mailadres',
            'label_password' => 'Wachtwoord',
            'label_remember' => 'Ingelogd blijven',
            'label_log_in'   => 'Inloggen',
            'value_remember' => true,
        ]);
        $html .= '<p class="gnd-login-lost"><a href="' . esc_url(wp_lostpassword_url($redirect)) . '">Wachtwoord vergeten?</a></p>';
        $html .= '</div>';
        return $html;
    }
}

GlassNext_Dealer_Suite::instance();
