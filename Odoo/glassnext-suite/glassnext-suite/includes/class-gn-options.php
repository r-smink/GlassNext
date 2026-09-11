<?php
if (!defined('ABSPATH')) exit;

class GN_Options {

    private static $instance = null;
    private $defaults;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->set_defaults();
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    private function set_defaults() {
        $this->defaults = [
            // GlassShield uitgangspunten
            'gn_roll_width'       => '1520',
            'gn_roll_length'      => '60000',
            'gn_margin_side'      => '40',
            'gn_kerf'             => '5',
            'gn_quality'          => '140',
            'gn_rotate_default'   => '1',

            // Prijsinstellingen
            'gn_material_price'     => '130',
            'gn_cut_price'          => '1.50',
            'gn_material_discount'  => '0',
            'gn_mount_discount'     => '0',
            'gn_vat_rate'           => '21',
            'gn_other_costs'        => [],
            'gn_mount_class'        => 'average',
            'gn_mount_selected_price' => '45',
            'gn_mount_area_basis'   => 'net',
            'gn_mount_rate_easy'    => '35',
            'gn_mount_rate_average' => '45',
            'gn_mount_rate_complex' => '60',
            'gn_mount_rate_very'    => '75',

            // ROI technische parameters
            'gn_roi_u_before'       => '5.80',
            'gn_roi_u_after'        => '1.85',
            'gn_roi_hdd'            => '4676',
            'gn_roi_boiler_eff'     => '90',
            'gn_roi_gas_kwh'        => '9.77',
            'gn_roi_gas_save_m2'    => '16.2089',
            'gn_roi_cool_save_m2'   => '15',
            'gn_roi_building_factor'=> '100',

            // ROI financiële parameters
            'gn_roi_eia_net'        => '0',
            'gn_roi_gas_price'      => '1.31',
            'gn_roi_elec_price'     => '0.27',
            'gn_roi_co2_price'      => '70',
            'gn_roi_co2_gas'        => '2.134',
            'gn_roi_co2_elec'       => '0.268',
            'gn_roi_years'          => '10',

            // Offerte instellingen
            'gn_offer_prefix'       => 'GNW',
            'gn_offer_counter'      => '0',
            'gn_offer_counter_year' => date('Y'),

            // E-mail instellingen
            'gn_admin_email'        => get_option('admin_email'),
            'gn_from_email'         => get_option('admin_email'),
            'gn_from_name'          => 'GlassNext Suite',
            'gn_email_subject'      => 'Uw offerte aanvraag is ontvangen – {{offertenummer}}',
            'gn_email_body_template'=> "Beste {{naam}},\n\nWij hebben uw offerte aanvraag in goede orde ontvangen.\nUw referentienummer is: {{offertenummer}}\n\nWij nemen spoedig contact met u op om de offerte definitief te maken.\n\nMet vriendelijke groet,\n{{afzender_naam}}",
            'gn_email_logo_id'      => '',

            // Odoo / Make.com integratie
            'gn_makecom_webhook_url' => '',
            'gn_odoo_enabled'        => '',
            'gn_odoo_url'            => '',
            'gn_odoo_db'             => '',
            'gn_odoo_login'          => '',
            'gn_odoo_api_key'        => '',
            'gn_odoo_uid'              => '2',
            'gn_odoo_product_material' => '3',
            'gn_odoo_product_mount'    => '5',
            'gn_odoo_product_cut'      => '85',
            'gn_odoo_product_voorrijd' => '86',
            'gn_odoo_product_measure'  => '',
            'gn_odoo_create_calendar_event' => '',
            'gn_measure_price'         => '149',

            // Snijplanner tooltips
            'gn_tooltip_kenmerk_text'  => 'Een unieke naam of nummer voor deze ruit, bijv. "R1" of "Raam woonkamer".',
            'gn_tooltip_kenmerk_image' => '',
            'gn_tooltip_breedte_text'  => 'De breedte van de ruit in centimeters, gemeten aan de binnenzijde van de sponning.',
            'gn_tooltip_breedte_image' => '',
            'gn_tooltip_hoogte_text'   => 'De hoogte van de ruit in centimeters, gemeten aan de binnenzijde van de sponning.',
            'gn_tooltip_hoogte_image'  => '',
            'gn_tooltip_aantal_text'   => 'Het aantal ruiten met deze afmetingen.',
            'gn_tooltip_aantal_image'  => '',
            'gn_tooltip_rotatie_text'  => 'Of de ruit 90 graden gedraaid op de rol geplaatst mag worden voor optimale sneding.',
            'gn_tooltip_rotatie_image' => '',
            'gn_tooltip_ruimte_text'   => 'De ruimte of verdieping waar deze ruit zich bevindt, voor de werkbon en planning.',
            'gn_tooltip_ruimte_image'  => '',

            // Thank you page
            'gn_thankyou_url' => '',
        ];
    }

    public static function get_all_options() {
        $instance = self::instance();
        $options = [];
        foreach ($instance->defaults as $key => $default) {
            $val = get_option($key, $default);
            $options[$key] = $val;
        }
        return $options;
    }

    public function add_admin_menu() {
        add_menu_page(
            __('GlassNext Suite', 'glassnext-suite'),
            __('GlassNext Suite', 'glassnext-suite'),
            'manage_options',
            'glassnext-suite',
            [$this, 'render_options_page'],
            'dashicons-admin-generic',
            30
        );

        add_submenu_page(
            'glassnext-suite',
            __('Instellingen', 'glassnext-suite'),
            __('Instellingen', 'glassnext-suite'),
            'manage_options',
            'glassnext-suite',
            [$this, 'render_options_page']
        );
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'glassnext-suite') === false) return;
        wp_enqueue_style('glassnext-admin', GN_PLUGIN_URL . 'assets/glassnext-admin.css', [], GN_VERSION);
        wp_enqueue_script('glassnext-admin', GN_PLUGIN_URL . 'assets/glassnext-admin.js', ['jquery'], GN_VERSION, true);
        wp_enqueue_media();
    }

    public function register_settings() {
        $groups = [
            'gn_roll_width', 'gn_roll_length', 'gn_margin_side', 'gn_kerf', 'gn_quality', 'gn_rotate_default',
            'gn_material_price', 'gn_cut_price', 'gn_material_discount', 'gn_mount_discount', 'gn_vat_rate',
            'gn_other_costs', 'gn_mount_class', 'gn_mount_selected_price', 'gn_mount_area_basis',
            'gn_mount_rate_easy', 'gn_mount_rate_average', 'gn_mount_rate_complex', 'gn_mount_rate_very',
            'gn_roi_u_before', 'gn_roi_u_after', 'gn_roi_hdd', 'gn_roi_boiler_eff', 'gn_roi_gas_kwh',
            'gn_roi_gas_save_m2', 'gn_roi_cool_save_m2', 'gn_roi_building_factor',
            'gn_roi_eia_net', 'gn_roi_gas_price', 'gn_roi_elec_price', 'gn_roi_co2_price',
            'gn_roi_co2_gas', 'gn_roi_co2_elec', 'gn_roi_years',
            'gn_offer_prefix', 'gn_offer_counter', 'gn_offer_counter_year',
            'gn_admin_email', 'gn_from_email', 'gn_from_name',
            'gn_email_subject', 'gn_email_body_template', 'gn_email_logo_id',
            'gn_makecom_webhook_url',
            'gn_odoo_enabled', 'gn_odoo_url', 'gn_odoo_db', 'gn_odoo_login', 'gn_odoo_api_key', 'gn_odoo_uid',
            'gn_odoo_product_material', 'gn_odoo_product_mount', 'gn_odoo_product_cut',
            'gn_odoo_product_voorrijd', 'gn_odoo_product_measure', 'gn_odoo_create_calendar_event',
            'gn_measure_price',
            'gn_tooltip_kenmerk_text', 'gn_tooltip_kenmerk_image',
            'gn_tooltip_breedte_text', 'gn_tooltip_breedte_image',
            'gn_tooltip_hoogte_text', 'gn_tooltip_hoogte_image',
            'gn_tooltip_aantal_text', 'gn_tooltip_aantal_image',
            'gn_tooltip_rotatie_text', 'gn_tooltip_rotatie_image',
            'gn_tooltip_ruimte_text', 'gn_tooltip_ruimte_image',
            'gn_thankyou_url',
        ];

        foreach ($groups as $key) {
            register_setting('glassnext_settings_group', $key);
        }
    }

    public function render_options_page() {
        ?>
        <div class="wrap">
            <h1>GlassNext Suite – Instellingen</h1>
        <nav class="gn-tabs-nav">
            <button type="button" class="gn-tab-link active" data-tab="glass">GlassShield</button>
            <button type="button" class="gn-tab-link" data-tab="price">Prijsinstellingen</button>
            <button type="button" class="gn-tab-link" data-tab="roi-tech">ROI technisch</button>
            <button type="button" class="gn-tab-link" data-tab="roi-finance">ROI financieel</button>
            <button type="button" class="gn-tab-link" data-tab="offer">Offerte</button>
            <button type="button" class="gn-tab-link" data-tab="tooltips">Snijplanner tooltips</button>
            <button type="button" class="gn-tab-link" data-tab="email">E-mail</button>
            <button type="button" class="gn-tab-link" data-tab="odoo">Odoo / Make.com</button>
        </nav>
            <form method="post" action="options.php">
                <?php settings_fields('glassnext_settings_group'); ?>

                <div class="gn-tab-panel active" data-tab="glass">

                <h2 class="title">GlassShield uitgangspunten</h2>
                <table class="form-table">
                    <tr><th><label for="gn_roll_width">Rolbreedte (mm)</label></th><td><input type="number" id="gn_roll_width" name="gn_roll_width" value="<?php echo esc_attr(get_option('gn_roll_width', '1520')); ?>" min="1" class="regular-text"></td></tr>
                    <tr><th><label for="gn_roll_length">Rollengte (mm)</label></th><td><input type="number" id="gn_roll_length" name="gn_roll_length" value="<?php echo esc_attr(get_option('gn_roll_length', '60000')); ?>" min="1" class="regular-text"></td></tr>
                    <tr><th><label for="gn_margin_side">Snijrand per zijde (mm)</label></th><td><input type="number" id="gn_margin_side" name="gn_margin_side" value="<?php echo esc_attr(get_option('gn_margin_side', '40')); ?>" min="0" class="regular-text"></td></tr>
                    <tr><th><label for="gn_kerf">Tussenruimte (mm)</label></th><td><input type="number" id="gn_kerf" name="gn_kerf" value="<?php echo esc_attr(get_option('gn_kerf', '5')); ?>" min="0" class="regular-text"></td></tr>
                    <tr><th><label for="gn_quality">Optimalisatie</label></th><td>
                        <select id="gn_quality" name="gn_quality">
                            <option value="50" <?php selected(get_option('gn_quality', '140'), '50'); ?>>Snel</option>
                            <option value="140" <?php selected(get_option('gn_quality', '140'), '140'); ?>>Normaal</option>
                            <option value="350" <?php selected(get_option('gn_quality', '140'), '350'); ?>>Grondig</option>
                        </select>
                    </td></tr>
                    <tr><th><label for="gn_rotate_default">Rotatie standaard</label></th><td>
                        <select id="gn_rotate_default" name="gn_rotate_default">
                            <option value="1" <?php selected(get_option('gn_rotate_default', '1'), '1'); ?>>Ja</option>
                            <option value="0" <?php selected(get_option('gn_rotate_default', '1'), '0'); ?>>Nee</option>
                        </select>
                    </td></tr>
                </table>

                </div>

                <div class="gn-tab-panel" data-tab="price">
                <h2 class="title">Prijsinstellingen</h2>
                <table class="form-table">
                    <tr><th><label for="gn_material_price">Materiaalprijs per verbruikte m²</label></th><td><input type="number" step="0.01" id="gn_material_price" name="gn_material_price" value="<?php echo esc_attr(get_option('gn_material_price', '130')); ?>" class="regular-text"></td></tr>
                    <tr><th><label for="gn_cut_price">Voorsnijprijs per stuk</label></th><td><input type="number" step="0.01" id="gn_cut_price" name="gn_cut_price" value="<?php echo esc_attr(get_option('gn_cut_price', '1.50')); ?>" class="regular-text"></td></tr>
                    <tr><th><label for="gn_material_discount">Materiaalkorting (%)</label></th><td><input type="number" step="0.1" id="gn_material_discount" name="gn_material_discount" value="<?php echo esc_attr(get_option('gn_material_discount', '0')); ?>" class="regular-text"></td></tr>
                    <tr><th><label for="gn_mount_discount">Montagekorting (%)</label></th><td><input type="number" step="0.1" id="gn_mount_discount" name="gn_mount_discount" value="<?php echo esc_attr(get_option('gn_mount_discount', '0')); ?>" class="regular-text"></td></tr>
                    <tr><th><label for="gn_vat_rate">BTW (%)</label></th><td><input type="number" step="0.1" id="gn_vat_rate" name="gn_vat_rate" value="<?php echo esc_attr(get_option('gn_vat_rate', '21')); ?>" class="regular-text"></td></tr>
                    <tr><th><label>Overige kosten</label></th><td>
                        <div id="gn-other-costs-container">
                            <?php
                            $other_costs = get_option('gn_other_costs', []);
                            if (!is_array($other_costs) || empty($other_costs)) {
                                $other_costs = [['description' => '', 'amount' => 0]];
                            }
                            foreach ($other_costs as $i => $cost) : ?>
                                <div class="gn-other-cost-row" style="display:flex;gap:8px;margin-bottom:8px;align-items:flex-end;">
                                    <div><label>Omschrijving</label><input type="text" class="regular-text gn-oc-desc" name="gn_other_costs[<?php echo $i; ?>][description]" value="<?php echo esc_attr($cost['description'] ?? ''); ?>"></div>
                                    <div><label>Bedrag excl. btw</label><input type="number" step="0.01" min="0" class="gn-oc-amount" name="gn_other_costs[<?php echo $i; ?>][amount]" value="<?php echo esc_attr($cost['amount'] ?? 0); ?>"></div>
                                    <button type="button" class="button gn-remove-cost">Verwijder</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="button" id="gn-add-cost">+ Overige kostenpost toevoegen</button>
                        <p class="description">Gebruik deze posten bijvoorbeeld voor reiskosten, steigerhuur, hoogwerker, etc.</p>
                    </td></tr>
                    <tr><th><label for="gn_mount_area_basis">Montage berekenen over</label></th><td>
                        <select id="gn_mount_area_basis" name="gn_mount_area_basis">
                            <option value="net" <?php selected(get_option('gn_mount_area_basis', 'net'), 'net'); ?>>Netto glasoppervlak</option>
                            <option value="gross" <?php selected(get_option('gn_mount_area_basis', 'net'), 'gross'); ?>>Bruto snijstukken</option>
                        </select>
                    </td></tr>
                    <tr><th><label>Montagetarieven (€/m²)</label></th><td>
                        <div style="display:flex;gap:16px;flex-wrap:wrap;">
                            <div><label>Eenvoudig</label><input type="number" step="0.01" name="gn_mount_rate_easy" value="<?php echo esc_attr(get_option('gn_mount_rate_easy', '35')); ?>" class="small-text"></div>
                            <div><label>Gemiddeld</label><input type="number" step="0.01" name="gn_mount_rate_average" value="<?php echo esc_attr(get_option('gn_mount_rate_average', '45')); ?>" class="small-text"></div>
                            <div><label>Complex</label><input type="number" step="0.01" name="gn_mount_rate_complex" value="<?php echo esc_attr(get_option('gn_mount_rate_complex', '60')); ?>" class="small-text"></div>
                            <div><label>Zeer complex</label><input type="number" step="0.01" name="gn_mount_rate_very" value="<?php echo esc_attr(get_option('gn_mount_rate_very', '75')); ?>" class="small-text"></div>
                        </div>
                        <p class="description">Standaardtarieven per montageklasse. De gekozen klasse wordt in de tool gebruikt.</p>
                    </td></tr>
                    <tr><th><label for="gn_mount_class">Standaard montageklasse</label></th><td>
                        <select id="gn_mount_class" name="gn_mount_class">
                            <option value="easy" <?php selected(get_option('gn_mount_class', 'average'), 'easy'); ?>>Eenvoudig</option>
                            <option value="average" <?php selected(get_option('gn_mount_class', 'average'), 'average'); ?>>Gemiddeld</option>
                            <option value="complex" <?php selected(get_option('gn_mount_class', 'average'), 'complex'); ?>>Complex</option>
                            <option value="very" <?php selected(get_option('gn_mount_class', 'average'), 'very'); ?>>Zeer complex</option>
                        </select>
                        <input type="hidden" id="gn_mount_selected_price" name="gn_mount_selected_price" value="<?php echo esc_attr(get_option('gn_mount_selected_price', '')); ?>">
                        <p class="description">De montagetarief voor de gekozen klasse wordt automatisch ingesteld op basis van de tarieven hierboven.</p>
                    </td></tr>
                </table>

                </div>

                <div class="gn-tab-panel" data-tab="roi-tech">
                <h2 class="title">ROI technische parameters</h2>
                <table class="form-table">
                    <tr><th><label for="gn_roi_u_before">U-/Ug-waarde vóór (W/m²K)</label></th><td><input type="number" step="0.01" min="0" id="gn_roi_u_before" name="gn_roi_u_before" value="<?php echo esc_attr(get_option('gn_roi_u_before', '5.80')); ?>" class="regular-text"></td></tr>
                    <tr><th><label for="gn_roi_u_after">U-/Ug-waarde na GlassShield (W/m²K)</label></th><td><input type="number" step="0.01" min="0" id="gn_roi_u_after" name="gn_roi_u_after" value="<?php echo esc_attr(get_option('gn_roi_u_after', '1.85')); ?>" class="regular-text"></td></tr>
                    <tr><th><label for="gn_roi_hdd">Heating Degree Days per jaar</label></th><td><input type="number" min="0" step="1" id="gn_roi_hdd" name="gn_roi_hdd" value="<?php echo esc_attr(get_option('gn_roi_hdd', '4676')); ?>" class="regular-text"></td></tr>
                    <tr><th><label for="gn_roi_boiler_eff">Ketelrendement (%)</label></th><td><input type="number" min="1" max="100" step="0.1" id="gn_roi_boiler_eff" name="gn_roi_boiler_eff" value="<?php echo esc_attr(get_option('gn_roi_boiler_eff', '90')); ?>" class="regular-text"></td></tr>
                    <tr><th><label for="gn_roi_gas_kwh">Energie-inhoud gas (kWh/m³)</label></th><td><input type="number" min="0.1" step="0.01" id="gn_roi_gas_kwh" name="gn_roi_gas_kwh" value="<?php echo esc_attr(get_option('gn_roi_gas_kwh', '9.77')); ?>" class="regular-text"></td></tr>
                    <tr><th><label for="gn_roi_gas_save_m2">Handmatige gasbesparing (m³/m²/jaar)</label></th><td><input type="number" min="0" step="0.0001" id="gn_roi_gas_save_m2" name="gn_roi_gas_save_m2" value="<?php echo esc_attr(get_option('gn_roi_gas_save_m2', '16.2089')); ?>" class="regular-text"></td></tr>
                    <tr><th><label for="gn_roi_cool_save_m2">Elektrabesparing koeling (kWh/m²/jaar)</label></th><td><input type="number" min="0" step="0.01" id="gn_roi_cool_save_m2" name="gn_roi_cool_save_m2" value="<?php echo esc_attr(get_option('gn_roi_cool_save_m2', '15')); ?>" class="regular-text"></td></tr>
                    <tr><th><label for="gn_roi_building_factor">Correctiefactor gebouw (%)</label></th><td><input type="number" min="0" step="1" id="gn_roi_building_factor" name="gn_roi_building_factor" value="<?php echo esc_attr(get_option('gn_roi_building_factor', '100')); ?>" class="regular-text"></td></tr>
                </table>

                </div>

                <div class="gn-tab-panel" data-tab="roi-finance">
                <h2 class="title">ROI financiële parameters</h2>
                <table class="form-table">
                    <tr><th><label for="gn_roi_eia_net">EIA / subsidie netto voordeel (%)</label></th><td><input type="number" min="0" step="0.1" id="gn_roi_eia_net" name="gn_roi_eia_net" value="<?php echo esc_attr(get_option('gn_roi_eia_net', '0')); ?>" class="regular-text"></td></tr>
                    <tr><th><label for="gn_roi_gas_price">Gasprijs (€/m³)</label></th><td><input type="number" min="0" step="0.01" id="gn_roi_gas_price" name="gn_roi_gas_price" value="<?php echo esc_attr(get_option('gn_roi_gas_price', '1.31')); ?>" class="regular-text"></td></tr>
                    <tr><th><label for="gn_roi_elec_price">Elektriciteitsprijs (€/kWh)</label></th><td><input type="number" min="0" step="0.01" id="gn_roi_elec_price" name="gn_roi_elec_price" value="<?php echo esc_attr(get_option('gn_roi_elec_price', '0.27')); ?>" class="regular-text"></td></tr>
                    <tr><th><label for="gn_roi_co2_price">CO₂-prijs (€/ton)</label></th><td><input type="number" min="0" step="1" id="gn_roi_co2_price" name="gn_roi_co2_price" value="<?php echo esc_attr(get_option('gn_roi_co2_price', '70')); ?>" class="regular-text"></td></tr>
                    <tr><th><label for="gn_roi_co2_gas">CO₂-factor gas (kg/m³)</label></th><td><input type="number" min="0" step="0.001" id="gn_roi_co2_gas" name="gn_roi_co2_gas" value="<?php echo esc_attr(get_option('gn_roi_co2_gas', '2.134')); ?>" class="regular-text"></td></tr>
                    <tr><th><label for="gn_roi_co2_elec">CO₂-factor stroom (kg/kWh)</label></th><td><input type="number" min="0" step="0.001" id="gn_roi_co2_elec" name="gn_roi_co2_elec" value="<?php echo esc_attr(get_option('gn_roi_co2_elec', '0.268')); ?>" class="regular-text"></td></tr>
                    <tr><th><label for="gn_roi_years">Analyseperiode (jaar)</label></th><td><input type="number" min="1" step="1" id="gn_roi_years" name="gn_roi_years" value="<?php echo esc_attr(get_option('gn_roi_years', '10')); ?>" class="regular-text"></td></tr>
                </table>

                </div>

                <div class="gn-tab-panel" data-tab="offer">
                <h2 class="title">Offerte instellingen</h2>
                <table class="form-table">
                    <tr><th><label for="gn_offer_prefix">Voorvoegsel offertenummer</label></th><td><input type="text" id="gn_offer_prefix" name="gn_offer_prefix" value="<?php echo esc_attr(get_option('gn_offer_prefix', 'GNW')); ?>" class="regular-text"><p class="description">Het jaar en volgnummer worden automatisch toegevoegd, bijv. GNW-2026-001.</p></td></tr>
                    <tr><th><label for="gn_thankyou_url">Thank you pagina URL</label></th><td><input type="url" id="gn_thankyou_url" name="gn_thankyou_url" value="<?php echo esc_attr(get_option('gn_thankyou_url', '')); ?>" class="regular-text" placeholder="https://voorbeeld.nl/bedankt"><p class="description">Vul de URL in van de pagina waar de klant naartoe wordt geleid na het indienen van de offerte aanvraag. Leeg = geen redirect, de klant blijft op de tool pagina.</p></td></tr>
                </table>

                </div>

                <div class="gn-tab-panel" data-tab="tooltips">
                <h2 class="title">Snijplanner tooltips</h2>
                <p class="description" style="margin-bottom:15px">Deze teksten en afbeeldingen verschijnen als tooltip (ⓘ) bij de kolomkoppen in de snijplanner tabel. Laat een tekst leeg om de tooltip voor die kolol uit te schakelen.</p>
                <table class="form-table">
                    <?php
                    $tooltip_cols = [
                        'kenmerk'  => 'Kenmerk',
                        'breedte'  => 'Breedte cm',
                        'hoogte'   => 'Hoogte cm',
                        'aantal'   => 'Aantal',
                        'rotatie'  => 'Rotatie',
                        'ruimte'   => 'Ruimte / verdieping',
                    ];
                    foreach ($tooltip_cols as $col => $label) :
                        $text_val = get_option("gn_tooltip_{$col}_text", '');
                        $img_id   = get_option("gn_tooltip_{$col}_image", '');
                        $img_url  = $img_id ? wp_get_attachment_url($img_id) : '';
                    ?>
                    <tr><th><label for="gn_tooltip_<?php echo $col; ?>_text"><?php echo $label; ?></label></th><td>
                        <textarea id="gn_tooltip_<?php echo $col; ?>_text" name="gn_tooltip_<?php echo $col; ?>_text" rows="3" class="large-text"><?php echo esc_textarea($text_val); ?></textarea>
                        <div style="margin-top:8px">
                            <div id="gn-tooltip-<?php echo $col; ?>-preview" style="margin-bottom:8px">
                                <?php if ($img_url) : ?>
                                    <img src="<?php echo esc_url($img_url); ?>" style="max-height:80px;max-width:200px;border:1px solid #ddd;padding:4px;background:#fff;" />
                                <?php endif; ?>
                            </div>
                            <input type="hidden" id="gn_tooltip_<?php echo $col; ?>_image" name="gn_tooltip_<?php echo $col; ?>_image" value="<?php echo esc_attr($img_id); ?>">
                            <button type="button" class="button gn-choose-tooltip-img" data-col="<?php echo $col; ?>">Afbeelding kiezen</button>
                            <button type="button" class="button gn-remove-tooltip-img" data-col="<?php echo $col; ?>" <?php echo $img_id ? '' : 'style="display:none;"'; ?>>Afbeelding verwijderen</button>
                        </div>
                    </td></tr>
                    <?php endforeach; ?>
                </table>

                </div>

                <div class="gn-tab-panel" data-tab="email">
                <h2 class="title">E-mail instellingen</h2>
                <table class="form-table">
                    <tr><th><label for="gn_admin_email">Admin e-mailadres (ontvangt notificaties)</label></th><td><input type="email" id="gn_admin_email" name="gn_admin_email" value="<?php echo esc_attr(get_option('gn_admin_email', get_option('admin_email'))); ?>" class="regular-text"></td></tr>
                    <tr><th><label for="gn_from_email">Afzender e-mailadres</label></th><td><input type="email" id="gn_from_email" name="gn_from_email" value="<?php echo esc_attr(get_option('gn_from_email', get_option('admin_email'))); ?>" class="regular-text"></td></tr>
                    <tr><th><label for="gn_from_name">Afzender naam</label></th><td><input type="text" id="gn_from_name" name="gn_from_name" value="<?php echo esc_attr(get_option('gn_from_name', 'GlassNext Suite')); ?>" class="regular-text"></td></tr>
                    <tr><th><label for="gn_email_subject">Onderwerp bevestigingsmail</label></th><td><input type="text" id="gn_email_subject" name="gn_email_subject" value="<?php echo esc_attr(get_option('gn_email_subject', 'Uw offerte aanvraag is ontvangen – {{offertenummer}}')); ?>" class="regular-text"><p class="description">Beschikbare placeholders: <code>{{offertenummer}}</code>, <code>{{naam}}</code>, <code>{{afzender_naam}}</code>, <code>{{datum}}</code></p></td></tr>
                </table>

                <h2 class="title">Bevestigingsmail tekst</h2>
                <p class="description" style="margin-bottom:10px;">Beschikbare placeholders: <code>{{naam}}</code> (klantnaam), <code>{{offertenummer}}</code>, <code>{{afzender_naam}}</code>, <code>{{datum}}</code>. Het logo (hieronder gekozen) wordt onderaan de e-mail toegevoegd.</p>
                <?php
                $email_body = get_option('gn_email_body_template', '');
                wp_editor($email_body, 'gn_email_body_template', [
                    'textarea_name' => 'gn_email_body_template',
                    'textarea_rows' => 12,
                    'media_buttons' => false,
                    'teeny'         => true,
                    'quicktags'     => true,
                ]);
                ?>

                <h2 class="title" style="margin-top:24px;">Logo in bevestigingsmail</h2>
                <table class="form-table">
                    <tr><th><label>Logo afbeelding</label></th><td>
                        <div id="gn-email-logo-preview" style="margin-bottom:10px;">
                            <?php
                            $logo_id = get_option('gn_email_logo_id', '');
                            if ($logo_id) {
                                $logo_url = wp_get_attachment_url($logo_id);
                                if ($logo_url) {
                                    echo '<img src="' . esc_url($logo_url) . '" style="max-height:80px;max-width:300px;border:1px solid #ddd;padding:4px;background:#fff;" />';
                                }
                            }
                            ?>
                        </div>
                        <input type="hidden" id="gn_email_logo_id" name="gn_email_logo_id" value="<?php echo esc_attr($logo_id); ?>">
                        <button type="button" class="button" id="gn-choose-logo">Logo kiezen</button>
                        <button type="button" class="button" id="gn-remove-logo" <?php echo $logo_id ? '' : 'style="display:none;"'; ?>>Logo verwijderen</button>
                        <p class="description">Het logo wordt onderaan de bevestigingsmail toegevoegd. Laat leeg voor geen logo.</p>
                    </td></tr>
                </table>

                </div>

                <div class="gn-tab-panel" data-tab="odoo">
                <h2 class="title">Odoo / Make.com integratie</h2>
                <table class="form-table">
                    <tr><th><label for="gn_makecom_webhook_url">Make.com Webhook URL</label></th><td><input type="url" id="gn_makecom_webhook_url" name="gn_makecom_webhook_url" value="<?php echo esc_attr(get_option('gn_makecom_webhook_url', '')); ?>" class="regular-text" placeholder="https://hook.eu1.make.com/xxxxx"><p class="description">Optioneel, naast of in plaats van de rechtstreekse Odoo-koppeling hieronder. Leeg laten = niet gebruikt.</p></td></tr>
                </table>

                <h2 class="title">Rechtstreekse Odoo-koppeling</h2>
                <table class="form-table">
                    <tr><th><label for="gn_odoo_enabled">Odoo-koppeling actief</label></th><td>
                        <label><input type="checkbox" id="gn_odoo_enabled" name="gn_odoo_enabled" value="1" <?php checked(get_option('gn_odoo_enabled', ''), '1'); ?>> Bij elke nieuwe offerte-aanvraag automatisch een klant en verkooporder aanmaken in Odoo.</label>
                    </td></tr>
                    <tr><th><label for="gn_odoo_url">Odoo URL</label></th><td><input type="url" id="gn_odoo_url" name="gn_odoo_url" value="<?php echo esc_attr(get_option('gn_odoo_url', '')); ?>" class="regular-text" placeholder="https://glassnext.odoo.com"><p class="description">Gebruik het echte <code>.odoo.com</code>-adres van je database, geen custom domain.</p></td></tr>
                    <tr><th><label for="gn_odoo_db">Database</label></th><td><input type="text" id="gn_odoo_db" name="gn_odoo_db" value="<?php echo esc_attr(get_option('gn_odoo_db', '')); ?>" class="regular-text" placeholder="glassnext"></td></tr>
                    <tr><th><label for="gn_odoo_login">Login (e-mailadres)</label></th><td><input type="email" id="gn_odoo_login" name="gn_odoo_login" value="<?php echo esc_attr(get_option('gn_odoo_login', '')); ?>" class="regular-text"></td></tr>
                    <tr><th><label for="gn_odoo_api_key">API-key</label></th><td><input type="password" id="gn_odoo_api_key" name="gn_odoo_api_key" value="<?php echo esc_attr(get_option('gn_odoo_api_key', '')); ?>" class="regular-text" autocomplete="new-password"><p class="description">Aanmaken via Odoo: Instellingen &gt; Voorkeuren &gt; Accountbeveiliging &gt; Nieuwe API-key.</p></td></tr>
                    <tr><th><label for="gn_odoo_uid">Gebruikers UID (hardcodeer)</label></th><td><input type="number" id="gn_odoo_uid" name="gn_odoo_uid" value="<?php echo esc_attr(get_option('gn_odoo_uid', '2')); ?>" class="small-text"><p class="description">Zet op 0 om automatisch via <code>common.authenticate</code> te bepalen. Vul een getal in (bijv. 2) om rechtstreeks <code>execute_kw</code> aan te roepen zoals in Make.com.</p></td></tr>
                    <tr><th><label for="gn_odoo_product_material">Product-ID: GlassShield per m²</label></th><td><input type="number" id="gn_odoo_product_material" name="gn_odoo_product_material" value="<?php echo esc_attr(get_option('gn_odoo_product_material', '3')); ?>" class="small-text"><p class="description">Aantal = netto rolverbruik (m²).</p></td></tr>
                    <tr><th><label for="gn_odoo_product_mount">Product-ID: Aanbrengen GlassShield per m²</label></th><td><input type="number" id="gn_odoo_product_mount" name="gn_odoo_product_mount" value="<?php echo esc_attr(get_option('gn_odoo_product_mount', '5')); ?>" class="small-text"><p class="description">Aantal = rollengte (m²).</p></td></tr>
                    <tr><th><label for="gn_odoo_product_cut">Product-ID: Programmeren en voorsnijden</label></th><td><input type="number" id="gn_odoo_product_cut" name="gn_odoo_product_cut" value="<?php echo esc_attr(get_option('gn_odoo_product_cut', '85')); ?>" class="small-text"><p class="description">Aantal = aantal stuks.</p></td></tr>
                    <tr><th><label for="gn_odoo_product_voorrijd">Product-ID: Voorrijkosten</label></th><td><input type="number" id="gn_odoo_product_voorrijd" name="gn_odoo_product_voorrijd" value="<?php echo esc_attr(get_option('gn_odoo_product_voorrijd', '86')); ?>" class="small-text"><p class="description">Wordt altijd als vaste regel toegevoegd. Prijs = "nader te berekenen" (stel in Odoo in).</p></td></tr>
                    <tr><th><label for="gn_odoo_product_measure">Product-ID: Laten opmeten (€149)</label></th><td><input type="number" id="gn_odoo_product_measure" name="gn_odoo_product_measure" value="<?php echo esc_attr(get_option('gn_odoo_product_measure', '')); ?>" class="small-text"><p class="description">Product-ID voor de "Laten opmeten" service. Wordt gebruikt bij de inmeet-aanvraag flow.</p></td></tr>
                    <tr><th><label for="gn_odoo_create_calendar_event">Agenda-afspraak aanmaken</label></th><td>
                        <label><input type="checkbox" id="gn_odoo_create_calendar_event" name="gn_odoo_create_calendar_event" value="1" <?php checked(get_option('gn_odoo_create_calendar_event', ''), '1'); ?>> Bij "Laten opmeten" automatisch een concept-agenda-afspraak aanmaken in Odoo voor de eerste voorkeursdatum.</label>
                    </td></tr>
                    <tr><th><label for="gn_measure_price">Prijs "Laten opmeten" (€)</label></th><td><input type="number" step="0.01" id="gn_measure_price" name="gn_measure_price" value="<?php echo esc_attr(get_option('gn_measure_price', '149')); ?>" class="small-text"></td></tr>
                    <tr><th></th><td><p class="description">Voor alle product-ID's wordt geen prijs meegestuurd; Odoo gebruikt de eigen verkoopprijs van het product. "Overige kosten" (hierboven bij Prijsinstellingen) worden wel als losse regel met eigen prijs meegestuurd.</p></td></tr>
                    <tr><th>Verbinding testen</th><td>
                        <button type="button" class="button" id="gn-test-odoo-connection">Test Odoo-verbinding</button>
                        <span id="gn-test-odoo-spinner" class="spinner" style="float:none;"></span>
                        <div id="gn-test-odoo-result" style="margin-top:10px;"></div>
                        <p class="description">Let op: dit test met de waarden die nu in de velden hierboven staan, ook als je nog niet op "Instellingen opslaan" hebt geklikt.</p>
                    </td></tr>
                </table>

                </div>

                <script>
                (function($){
                    $('#gn-test-odoo-connection').on('click', function(){
                        var $btn = $(this), $spinner = $('#gn-test-odoo-spinner'), $result = $('#gn-test-odoo-result');
                        $btn.prop('disabled', true);
                        $spinner.addClass('is-active');
                        $result.html('');
                        $.post(ajaxurl, {
                            action: 'gn_test_odoo_connection',
                            nonce: '<?php echo esc_js(wp_create_nonce('gn_test_odoo')); ?>',
                            gn_odoo_url: $('#gn_odoo_url').val(),
                            gn_odoo_db: $('#gn_odoo_db').val(),
                            gn_odoo_login: $('#gn_odoo_login').val(),
                            gn_odoo_api_key: $('#gn_odoo_api_key').val(),
                            gn_odoo_uid: $('#gn_odoo_uid').val()
                        }).done(function(res){
                            var color = res.success ? '#087f5b' : '#c0392b';
                            var html = '<p style="color:' + color + ';font-weight:700;">' + (res.message || '') + '</p>';
                            html += '<p><strong>URL:</strong> ' + (res.url || '') + '</p>';
                            if(res.debug){
                                html += '<p><strong>Debug:</strong> db=' + (res.debug.db_used||'') + ' | login=' + (res.debug.login_used||'') + ' | key-lengte=' + (res.debug.key_length||0) + '</p>';
                            }
                            html += '<pre style="background:#f6f7f7;padding:10px;overflow:auto;max-height:300px;">' + (res.raw || '') + '</pre>';
                            $result.html(html);
                        }).fail(function(xhr){
                            $result.html('<p style="color:#c0392b;">AJAX-fout: ' + xhr.status + '</p>');
                        }).always(function(){
                            $btn.prop('disabled', false);
                            $spinner.removeClass('is-active');
                        });
                    });
                })(jQuery);

                (function($){
                    $('.gn-choose-tooltip-img').on('click', function(e){
                        e.preventDefault();
                        var col = $(this).data('col');
                        var frame = wp.media({
                            title: 'Kies een afbeelding voor de tooltip',
                            library: { type: 'image' },
                            multiple: false
                        });
                        frame.on('select', function(){
                            var attachment = frame.state().get('selection').first().toJSON();
                            $('#gn-tooltip-' + col + '-preview').html('<img src="' + attachment.url + '" style="max-height:80px;max-width:200px;border:1px solid #ddd;padding:4px;background:#fff;" />');
                            $('#gn_tooltip_' + col + '_image').val(attachment.id);
                            $('.gn-remove-tooltip-img[data-col="' + col + '"]').show();
                        });
                        frame.open();
                    });
                    $('.gn-remove-tooltip-img').on('click', function(e){
                        e.preventDefault();
                        var col = $(this).data('col');
                        $('#gn-tooltip-' + col + '-preview').html('');
                        $('#gn_tooltip_' + col + '_image').val('');
                        $(this).hide();
                    });
                })(jQuery);
                </script>

                <?php submit_button('Instellingen opslaan'); ?>
            </form>
        </div>
        <?php
    }
}
