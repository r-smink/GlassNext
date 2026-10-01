<?php
if (!defined('ABSPATH')) exit;

/**
 * GN_Newsletter
 *
 * Nieuwsbrief-inschrijfformulier dat via de bestaande Odoo-koppeling
 * (GN_Odoo) een mailing.contact aanmaakt en inschrijft op een mailing.list.
 * Beveiligd met verplichte opt-in checkbox, honeypot en Google reCAPTCHA
 * (standaard v3; keys worden automatisch uit Elementor Pro gehaald,
 * met eigen invoervelden op de instellingentab als override).
 */
class GN_Newsletter {

    private static $instance = null;
    private $enqueued = false;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_gn_newsletter_subscribe', [$this, 'ajax_subscribe']);
        add_action('wp_ajax_nopriv_gn_newsletter_subscribe', [$this, 'ajax_subscribe']);
    }

    public function is_enabled() {
        return get_option('gn_newsletter_enabled', '') === '1';
    }

    /**
     * Bepaalt de actieve reCAPTCHA-configuratie.
     *
     * Key-resolutie:
     *  1. Eigen opties gn_recaptcha_site_key / gn_recaptcha_secret_key
     *  2. Elementor Pro v3: elementor_pro_recaptcha_v3_*
     *  3. Elementor Pro v2: elementor_pro_recaptcha_*
     *
     * @return array{version:string, site_key:string, secret_key:string}
     */
    public function get_recaptcha_config() {
        $version = get_option('gn_recaptcha_version', 'auto');

        $own_site   = get_option('gn_recaptcha_site_key', '');
        $own_secret = get_option('gn_recaptcha_secret_key', '');

        $el_v3_site   = get_option('elementor_pro_recaptcha_v3_site_key', '');
        $el_v3_secret = get_option('elementor_pro_recaptcha_v3_secret_key', '');
        $el_v2_site   = get_option('elementor_pro_recaptcha_site_key', '');
        $el_v2_secret = get_option('elementor_pro_recaptcha_secret_key', '');

        // Eigen keys hebben voorrang; bij 'auto' gaan we uit van v3 (site-configuratie)
        if ($own_site && $own_secret) {
            return [
                'version'    => ($version === 'auto') ? 'v3' : $version,
                'site_key'   => $own_site,
                'secret_key' => $own_secret,
            ];
        }

        if ($version === 'v3' || $version === 'auto') {
            if ($el_v3_site && $el_v3_secret) {
                return ['version' => 'v3', 'site_key' => $el_v3_site, 'secret_key' => $el_v3_secret];
            }
        }

        if (in_array($version, ['v2_checkbox', 'v2_invisible'], true)
            && $el_v2_site && $el_v2_secret) {
            return ['version' => $version, 'site_key' => $el_v2_site, 'secret_key' => $el_v2_secret];
        }

        // Auto-fallback op v2 als alleen v2-keys aanwezig zijn
        if ($version === 'auto' && $el_v2_site && $el_v2_secret) {
            return ['version' => 'v2_checkbox', 'site_key' => $el_v2_site, 'secret_key' => $el_v2_secret];
        }

        if ($version === 'off') {
            return ['version' => 'off', 'site_key' => '', 'secret_key' => ''];
        }

        return ['version' => 'none', 'site_key' => '', 'secret_key' => ''];
    }

    /**
     * Rendert het inschrijfformulier. Gedeeld door de Elementor widget
     * (en eventuele toekomstige shortcode).
     *
     * @param array $args title, description, name_mode ('hide'|'optional'|'required'),
     *                    name_placeholder, email_placeholder, button_text, consent_text,
     *                    success_msg, error_msg, layout ('stacked'|'inline'), list_ids
     */
    public function render_form($args = []) {
        if (!$this->is_enabled()) {
            return '<!-- GlassNext nieuwsbrief: feature uitgeschakeld in instellingen -->';
        }

        $options = GN_Options::get_all_options();
        $recaptcha = $this->get_recaptcha_config();

        $defaults = [
            'title'             => '',
            'description'       => '',
            'name_mode'         => 'hide',
            'name_placeholder'  => __('Je naam', 'glassnext-suite'),
            'email_placeholder' => __('Je e-mailadres', 'glassnext-suite'),
            'button_text'       => __('Inschrijven', 'glassnext-suite'),
            'consent_text'      => $options['gn_newsletter_consent_text'],
            'success_msg'       => $options['gn_newsletter_success_msg'],
            'error_msg'         => $options['gn_newsletter_error_msg'],
            'layout'            => 'stacked',
            'list_ids'          => $options['gn_newsletter_list_ids'],
        ];
        $a = array_merge($defaults, array_filter($args, function($v) { return $v !== '' && $v !== null; }));

        // Consent-tekst mag HTML bevatten (WYSIWYG in widget) — alleen veilige tags.
        // {{privacy_url}} werkt zowel in de instellings- als widget-tekst.
        $consent_html = wp_kses_post($this->resolve_consent_text($a['consent_text'], $options['gn_newsletter_privacy_url']));
        $list_ids_attr = esc_attr(preg_replace('/[^0-9,]/', '', (string) $a['list_ids']));

        $this->enqueue_frontend_assets($recaptcha);

        $form_id = 'gn-newsletter-' . wp_unique_id();

        ob_start();
        ?>
        <div class="gn-newsletter gn-newsletter--<?php echo esc_attr($a['layout']); ?>" data-gn-newsletter>
            <?php if ($a['title']) : ?>
                <h3 class="gn-newsletter__title"><?php echo esc_html($a['title']); ?></h3>
            <?php endif; ?>
            <?php if ($a['description']) : ?>
                <div class="gn-newsletter__desc"><?php echo wp_kses_post($a['description']); ?></div>
            <?php endif; ?>
            <form id="<?php echo esc_attr($form_id); ?>" class="gn-newsletter__form" novalidate
                  data-list-ids="<?php echo $list_ids_attr; ?>"
                  data-recaptcha-version="<?php echo esc_attr($recaptcha['version']); ?>">
                <div class="gn-newsletter__fields">
                    <?php if ($a['name_mode'] !== 'hide') : ?>
                        <input type="text" name="gn_name" class="gn-newsletter__input gn-newsletter__input--name"
                               placeholder="<?php echo esc_attr($a['name_placeholder']); ?>"
                               <?php echo $a['name_mode'] === 'required' ? 'required' : ''; ?> autocomplete="name">
                    <?php endif; ?>
                    <input type="email" name="gn_email" class="gn-newsletter__input gn-newsletter__input--email"
                           placeholder="<?php echo esc_attr($a['email_placeholder']); ?>"
                           required autocomplete="email">
                    <input type="text" name="gn_website" class="gn-newsletter__hp" value=""
                           tabindex="-1" autocomplete="off" aria-hidden="true">
                </div>

                <label class="gn-newsletter__consent">
                    <input type="checkbox" name="gn_consent" value="1" required>
                    <span class="gn-newsletter__consent-text"><?php echo $consent_html; ?></span>
                </label>

                <?php if ($recaptcha['version'] === 'v2_checkbox') : ?>
                    <div class="g-recaptcha gn-newsletter__recaptcha" data-sitekey="<?php echo esc_attr($recaptcha['site_key']); ?>"></div>
                <?php endif; ?>

                <button type="submit" class="gn-newsletter__button"><?php echo esc_html($a['button_text']); ?></button>
                <div class="gn-newsletter__message" role="status" aria-live="polite"
                     data-success="<?php echo esc_attr($a['success_msg']); ?>"
                     data-error="<?php echo esc_attr($a['error_msg']); ?>"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Vervangt {{privacy_url}} in de toestemmingstekst door de geconfigureerde URL.
     */
    private function resolve_consent_text($text, $privacy_url) {
        if (empty($text)) {
            $text = __('Ik meld me aan voor de nieuwsbrief.', 'glassnext-suite');
        }
        $link = '';
        if ($privacy_url) {
            $link = '<a href="' . esc_url($privacy_url) . '" target="_blank" rel="noopener">' . esc_html($privacy_url) . '</a>';
        }
        return str_replace('{{privacy_url}}', $link, $text);
    }

    /**
     * Laadt JS (+ het juiste reCAPTCHA api.js) zodra een formulier op de
     * pagina staat. Wordt aangeroepen vanuit render_form — veilig omdat
     * widgets renderen vóór wp_footer wordt geprint.
     */
    private function enqueue_frontend_assets($recaptcha) {
        if ($this->enqueued) return;
        $this->enqueued = true;

        wp_register_style('glassnext-newsletter', GN_PLUGIN_URL . 'assets/glassnext-newsletter.css', [], GN_VERSION);
        wp_register_script('glassnext-newsletter', GN_PLUGIN_URL . 'assets/glassnext-newsletter.js', [], GN_VERSION, true);

        wp_localize_script('glassnext-newsletter', 'GN_NEWSLETTER', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('gn_newsletter'),
            'siteKey' => $recaptcha['site_key'],
            'version' => $recaptcha['version'],
            'action'  => 'glassnext_newsletter',
        ]);

        wp_enqueue_style('glassnext-newsletter');
        wp_enqueue_script('glassnext-newsletter');

        // Google api.js: v3 laadt met ?render=sitekey; v2 expliciet renderen
        // via onload-callback zodat we widget-ID's per formulier beheren.
        if (in_array($recaptcha['version'], ['v3', 'v2_checkbox', 'v2_invisible'], true)
            && $recaptcha['site_key']
            && !wp_script_is('google-recaptcha', 'enqueued')) {
            $api_url = 'https://www.google.com/recaptcha/api.js';
            if ($recaptcha['version'] === 'v3') {
                $api_url = add_query_arg('render', $recaptcha['site_key'], $api_url);
            } else {
                $api_url = add_query_arg([
                    'render' => 'explicit',
                    'onload' => 'gnRecaptchaOnload',
                ], $api_url);
            }
            wp_enqueue_script('gn-recaptcha-api', $api_url, [], null, true);
        }
    }

    /**
     * AJAX: verwerkt een inschrijving.
     */
    public function ajax_subscribe() {
        check_ajax_referer('gn_newsletter', 'nonce');

        if (!$this->is_enabled()) {
            wp_send_json_error(['message' => __('Inschrijven is momenteel niet actief.', 'glassnext-suite')]);
        }

        // Honeypot: bots vullen dit verborgen veld
        if (!empty($_POST['gn_website'])) {
            wp_send_json_error(['message' => __('Inschrijving geweigerd.', 'glassnext-suite')]);
        }

        // reCAPTCHA server-side verificatie
        $recaptcha_result = $this->verify_recaptcha();
        if (is_wp_error($recaptcha_result)) {
            wp_send_json_error(['message' => $recaptcha_result->get_error_message()]);
        }

        $email = isset($_POST['gn_email']) ? sanitize_email(wp_unslash($_POST['gn_email'])) : '';
        $name  = isset($_POST['gn_name']) ? sanitize_text_field(wp_unslash($_POST['gn_name'])) : '';
        $consent = isset($_POST['gn_consent']) ? $_POST['gn_consent'] : '';
        $consent_text = isset($_POST['gn_consent_text']) ? sanitize_text_field(wp_unslash($_POST['gn_consent_text'])) : '';
        $page_url = isset($_POST['gn_page_url']) ? esc_url_raw(wp_unslash($_POST['gn_page_url'])) : '';
        $list_ids = isset($_POST['gn_list_ids']) ? array_filter(array_map('intval', explode(',', (string) $_POST['gn_list_ids']))) : [];

        if ($consent !== '1') {
            wp_send_json_error(['message' => __('Vink de toestemming aan om je in te schrijven.', 'glassnext-suite')]);
        }
        if (!is_email($email)) {
            wp_send_json_error(['message' => __('Vul een geldig e-mailadres in.', 'glassnext-suite')]);
        }
        if (empty($list_ids)) {
            $list_ids = array_filter(array_map('intval', explode(',', (string) get_option('gn_newsletter_list_ids', ''))));
        }
        if (empty($list_ids)) {
            wp_send_json_error(['message' => __('Er is geen mailinglijst geconfigureerd.', 'glassnext-suite')]);
        }

        $consent_meta = [
            'ip'   => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '',
            'text' => mb_substr($consent_text, 0, 500),
            'page' => $page_url,
            'time' => current_time('mysql'),
        ];

        $result = GN_Odoo::instance()->subscribe_to_mailing_list($email, $name, $list_ids, $consent_meta);

        if (!empty($result['success'])) {
            $msg = get_option('gn_newsletter_success_msg', '');
            wp_send_json_success([
                'message' => $msg ?: __('Bedankt! Je bent ingeschreven voor de nieuwsbrief.', 'glassnext-suite'),
            ]);
        }

        $msg = get_option('gn_newsletter_error_msg', '');
        wp_send_json_error([
            'message' => $msg ?: __('Er ging iets mis bij het inschrijven. Probeer het later opnieuw.', 'glassnext-suite'),
            'debug'   => defined('WP_DEBUG') && WP_DEBUG ? ($result['error'] ?? '') : '',
        ]);
    }

    /**
     * Verifieert de reCAPTCHA-token server-side via siteverify.
     * Geeft true of WP_Error terug.
     */
    private function verify_recaptcha() {
        $recaptcha = $this->get_recaptcha_config();

        if (in_array($recaptcha['version'], ['off', 'none'], true)) {
            if ($recaptcha['version'] === 'none') {
                error_log('GlassNext nieuwsbrief: geen reCAPTCHA-keys gevonden, verificatie overgeslagen.');
            }
            return true;
        }

        $token = isset($_POST['gn_recaptcha_token']) ? sanitize_text_field(wp_unslash($_POST['gn_recaptcha_token'])) : '';
        if ($token === '') {
            return new WP_Error('recaptcha', __('reCAPTCHA-verificatie ontbreekt. Vernieuw de pagina en probeer opnieuw.', 'glassnext-suite'));
        }

        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'body' => [
                'secret'   => $recaptcha['secret_key'],
                'response' => $token,
                'remoteip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '',
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('recaptcha', __('reCAPTCHA-verificatie mislukt (verbindingsfout).', 'glassnext-suite'));
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['success'])) {
            return new WP_Error('recaptcha', __('reCAPTCHA-verificatie geweigerd.', 'glassnext-suite'));
        }

        if ($recaptcha['version'] === 'v3') {
            $threshold = (float) get_option('gn_recaptcha_score', '0.5');
            $score = isset($data['score']) ? (float) $data['score'] : 0;
            $action = $data['action'] ?? '';
            if ($score < $threshold) {
                return new WP_Error('recaptcha', __('Inschrijving geweigerd (spam-score).', 'glassnext-suite'));
            }
            if ($action && $action !== 'glassnext_newsletter') {
                return new WP_Error('recaptcha', __('reCAPTCHA-action mismatch.', 'glassnext-suite'));
            }
        }

        return true;
    }
}
