<?php
if (!defined('ABSPATH')) exit;

/**
 * GN_Elementor_Newsletter_Widget
 *
 * Elementor-widget "GlassNext Nieuwsbrief". Rendert het inschrijfformulier
 * via GN_Newsletter::render_form() en biedt content- en style-controls
 * zodat de widget volledig te stylen is in de Elementor-editor.
 */
class GN_Elementor_Newsletter_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'gn_newsletter_signup';
    }

    public function get_title() {
        return __('GlassNext Nieuwsbrief', 'glassnext-suite');
    }

    public function get_icon() {
        return 'eicon-mail';
    }

    public function get_categories() {
        return ['general'];
    }

    public function get_keywords() {
        return ['nieuwsbrief', 'newsletter', 'mailing', 'odoo', 'glassnext', 'inschrijven'];
    }

    protected function register_controls() {

        /* ========== CONTENT ========== */

        $this->start_controls_section('section_content', [
            'label' => __('Inhoud', 'glassnext-suite'),
        ]);

        $this->add_control('title', [
            'label'       => __('Titel', 'glassnext-suite'),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'default'     => __('Blijf op de hoogte', 'glassnext-suite'),
            'placeholder' => __('Blijf op de hoogte', 'glassnext-suite'),
        ]);

        $this->add_control('description', [
            'label'   => __('Beschrijving', 'glassnext-suite'),
            'type'    => \Elementor\Controls_Manager::TEXTAREA,
            'default' => __('Schrijf je in voor onze nieuwsbrief.', 'glassnext-suite'),
            'rows'    => 3,
        ]);

        $this->add_control('name_mode', [
            'label'   => __('Naamveld', 'glassnext-suite'),
            'type'    => \Elementor\Controls_Manager::SELECT,
            'default' => 'hide',
            'options' => [
                'hide'     => __('Verbergen', 'glassnext-suite'),
                'optional' => __('Optioneel', 'glassnext-suite'),
                'required' => __('Verplicht', 'glassnext-suite'),
            ],
        ]);

        $this->add_control('name_placeholder', [
            'label'     => __('Naam placeholder', 'glassnext-suite'),
            'type'      => \Elementor\Controls_Manager::TEXT,
            'default'   => __('Je naam', 'glassnext-suite'),
            'condition' => ['name_mode!' => 'hide'],
        ]);

        $this->add_control('email_placeholder', [
            'label'   => __('E-mail placeholder', 'glassnext-suite'),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => __('Je e-mailadres', 'glassnext-suite'),
        ]);

        $this->add_control('button_text', [
            'label'   => __('Knoptekst', 'glassnext-suite'),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => __('Inschrijven', 'glassnext-suite'),
        ]);

        $this->add_control('consent_text', [
            'label'       => __('Toestemmingstekst (checkbox)', 'glassnext-suite'),
            'type'        => \Elementor\Controls_Manager::WYSIWYG,
            'default'     => '',
            'description' => __('Leeg = tekst uit de plugin-instellingen (met privacylink). HTML-links toegestaan.', 'glassnext-suite'),
        ]);

        $this->add_control('layout', [
            'label'   => __('Layout', 'glassnext-suite'),
            'type'    => \Elementor\Controls_Manager::SELECT,
            'default' => 'stacked',
            'options' => [
                'stacked' => __('Gestapeld (velden onder elkaar)', 'glassnext-suite'),
                'inline'  => __('Inline (veld en knop naast elkaar)', 'glassnext-suite'),
            ],
        ]);

        $this->add_control('list_ids', [
            'label'       => __('Mailinglijst-ID\'s (override)', 'glassnext-suite'),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'default'     => '',
            'description' => __('Komma-gescheiden Odoo mailing.list ID\'s. Leeg = instelling uit de plugin-opties.', 'glassnext-suite'),
        ]);

        $this->add_control('success_msg', [
            'label'       => __('Succesmelding', 'glassnext-suite'),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'default'     => '',
            'description' => __('Leeg = melding uit de plugin-instellingen.', 'glassnext-suite'),
        ]);

        $this->add_control('error_msg', [
            'label'       => __('Foutmelding', 'glassnext-suite'),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'default'     => '',
            'description' => __('Leeg = melding uit de plugin-instellingen.', 'glassnext-suite'),
        ]);

        $this->end_controls_section();

        /* ========== STYLE: CONTAINER ========== */

        $this->start_controls_section('style_container', [
            'label' => __('Container', 'glassnext-suite'),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('container_padding', [
            'label'      => __('Padding', 'glassnext-suite'),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'selectors'  => [
                '{{WRAPPER}} .gn-newsletter' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('container_align', [
            'label'     => __('Uitlijning', 'glassnext-suite'),
            'type'      => \Elementor\Controls_Manager::CHOOSE,
            'options'   => [
                'left'   => ['title' => __('Links', 'glassnext-suite'),   'icon' => 'eicon-text-align-left'],
                'center' => ['title' => __('Midden', 'glassnext-suite'),  'icon' => 'eicon-text-align-center'],
                'right'  => ['title' => __('Rechts', 'glassnext-suite'),  'icon' => 'eicon-text-align-right'],
            ],
            'selectors' => [
                '{{WRAPPER}} .gn-newsletter' => 'text-align: {{VALUE}};',
            ],
        ]);

        $this->add_control('container_bg', [
            'label'     => __('Achtergrondkleur', 'glassnext-suite'),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gn-newsletter' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();

        /* ========== STYLE: TITEL & BESCHRIJVING ========== */

        $this->start_controls_section('style_heading', [
            'label' => __('Titel & beschrijving', 'glassnext-suite'),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('title_color', [
            'label'     => __('Titel kleur', 'glassnext-suite'),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .gn-newsletter__title' => 'color: {{VALUE}};'],
        ]);

        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), [
            'name'     => 'title_typo',
            'label'    => __('Titel typografie', 'glassnext-suite'),
            'selector' => '{{WRAPPER}} .gn-newsletter__title',
        ]);

        $this->add_responsive_control('title_spacing', [
            'label'      => __('Titel afstand onder', 'glassnext-suite'),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'size_units' => ['px', 'em'],
            'selectors'  => ['{{WRAPPER}} .gn-newsletter__title' => 'margin-bottom: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('desc_color', [
            'label'     => __('Beschrijving kleur', 'glassnext-suite'),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .gn-newsletter__desc' => 'color: {{VALUE}};'],
        ]);

        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), [
            'name'     => 'desc_typo',
            'label'    => __('Beschrijving typografie', 'glassnext-suite'),
            'selector' => '{{WRAPPER}} .gn-newsletter__desc',
        ]);

        $this->add_responsive_control('desc_spacing', [
            'label'      => __('Beschrijving afstand onder', 'glassnext-suite'),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'size_units' => ['px', 'em'],
            'selectors'  => ['{{WRAPPER}} .gn-newsletter__desc' => 'margin-bottom: {{SIZE}}{{UNIT}};'],
        ]);

        $this->end_controls_section();

        /* ========== STYLE: VELDEN ========== */

        $this->start_controls_section('style_fields', [
            'label' => __('Invoervelden', 'glassnext-suite'),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('field_text_color', [
            'label'     => __('Tekstkleur', 'glassnext-suite'),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gn-newsletter__input' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('field_placeholder_color', [
            'label'     => __('Placeholder kleur', 'glassnext-suite'),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gn-newsletter__input::placeholder' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('field_bg', [
            'label'     => __('Achtergrondkleur', 'glassnext-suite'),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gn-newsletter__input' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(\Elementor\Group_Control_Border::get_type(), [
            'name'     => 'field_border',
            'selector' => '{{WRAPPER}} .gn-newsletter__input',
        ]);

        $this->add_responsive_control('field_radius', [
            'label'      => __('Border radius', 'glassnext-suite'),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors'  => [
                '{{WRAPPER}} .gn-newsletter__input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('field_padding', [
            'label'      => __('Padding', 'glassnext-suite'),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em'],
            'selectors'  => [
                '{{WRAPPER}} .gn-newsletter__input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), [
            'name'     => 'field_typo',
            'label'    => __('Typografie', 'glassnext-suite'),
            'selector' => '{{WRAPPER}} .gn-newsletter__input',
        ]);

        $this->add_responsive_control('fields_gap', [
            'label'      => __('Afstand tussen velden', 'glassnext-suite'),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'size_units' => ['px', 'em'],
            'range'      => ['px' => ['min' => 0, 'max' => 60]],
            'selectors'  => [
                '{{WRAPPER}} .gn-newsletter__fields' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();

        /* ========== STYLE: KNOP ========== */

        $this->start_controls_section('style_button', [
            'label' => __('Knop', 'glassnext-suite'),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->start_controls_tabs('button_tabs');

        $this->start_controls_tab('button_normal', ['label' => __('Normaal', 'glassnext-suite')]);
        $this->add_control('button_color', [
            'label'     => __('Tekstkleur', 'glassnext-suite'),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .gn-newsletter__button' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('button_bg', [
            'label'     => __('Achtergrondkleur', 'glassnext-suite'),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .gn-newsletter__button' => 'background-color: {{VALUE}};'],
        ]);
        $this->end_controls_tab();

        $this->start_controls_tab('button_hover', ['label' => __('Hover', 'glassnext-suite')]);
        $this->add_control('button_color_hover', [
            'label'     => __('Tekstkleur', 'glassnext-suite'),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .gn-newsletter__button:hover' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('button_bg_hover', [
            'label'     => __('Achtergrondkleur', 'glassnext-suite'),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .gn-newsletter__button:hover' => 'background-color: {{VALUE}};'],
        ]);
        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), [
            'name'     => 'button_typo',
            'label'    => __('Typografie', 'glassnext-suite'),
            'selector' => '{{WRAPPER}} .gn-newsletter__button',
        ]);

        $this->add_group_control(\Elementor\Group_Control_Border::get_type(), [
            'name'      => 'button_border',
            'selector'  => '{{WRAPPER}} .gn-newsletter__button',
            'separator' => 'before',
        ]);

        $this->add_responsive_control('button_radius', [
            'label'      => __('Border radius', 'glassnext-suite'),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors'  => [
                '{{WRAPPER}} .gn-newsletter__button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('button_padding', [
            'label'      => __('Padding', 'glassnext-suite'),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em'],
            'selectors'  => [
                '{{WRAPPER}} .gn-newsletter__button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('button_width', [
            'label'     => __('Breedte', 'glassnext-suite'),
            'type'      => \Elementor\Controls_Manager::SELECT,
            'default'   => '',
            'options'   => [
                ''      => __('Auto', 'glassnext-suite'),
                '100%'  => __('Volle breedte', 'glassnext-suite'),
            ],
            'selectors' => ['{{WRAPPER}} .gn-newsletter__button' => 'width: {{VALUE}};'],
        ]);

        $this->add_responsive_control('button_spacing', [
            'label'      => __('Afstand boven', 'glassnext-suite'),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'size_units' => ['px', 'em'],
            'range'      => ['px' => ['min' => 0, 'max' => 60]],
            'selectors'  => [
                '{{WRAPPER}} .gn-newsletter__button' => 'margin-top: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();

        /* ========== STYLE: CHECKBOX & MELDINGEN ========== */

        $this->start_controls_section('style_misc', [
            'label' => __('Checkbox & meldingen', 'glassnext-suite'),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('consent_color', [
            'label'     => __('Checkbox-tekst kleur', 'glassnext-suite'),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gn-newsletter__consent' => 'color: {{VALUE}};',
                '{{WRAPPER}} .gn-newsletter__consent a' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), [
            'name'     => 'consent_typo',
            'label'    => __('Checkbox-tekst typografie', 'glassnext-suite'),
            'selector' => '{{WRAPPER}} .gn-newsletter__consent',
        ]);

        $this->add_responsive_control('consent_spacing', [
            'label'      => __('Afstand boven checkbox', 'glassnext-suite'),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'size_units' => ['px', 'em'],
            'range'      => ['px' => ['min' => 0, 'max' => 60]],
            'selectors'  => [
                '{{WRAPPER}} .gn-newsletter__consent' => 'margin-top: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('msg_success_color', [
            'label'     => __('Succesmelding kleur', 'glassnext-suite'),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'separator' => 'before',
            'selectors' => [
                '{{WRAPPER}} .gn-newsletter__message.is-success' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('msg_error_color', [
            'label'     => __('Foutmelding kleur', 'glassnext-suite'),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gn-newsletter__message.is-error' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), [
            'name'     => 'msg_typo',
            'label'    => __('Meldingen typografie', 'glassnext-suite'),
            'selector' => '{{WRAPPER}} .gn-newsletter__message',
        ]);

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $newsletter = GN_Newsletter::instance();

        if (!$newsletter->is_enabled()) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div style="padding:12px;border:1px dashed #cc0000;color:#cc0000;">'
                    . esc_html__('GlassNext Nieuwsbrief: schakel de feature in via GlassNext Suite → Instellingen → Nieuwsbrief.', 'glassnext-suite')
                    . '</div>';
            }
            return;
        }

        echo $newsletter->render_form([
            'title'             => $settings['title'] ?? '',
            'description'       => $settings['description'] ?? '',
            'name_mode'         => $settings['name_mode'] ?? 'hide',
            'name_placeholder'  => $settings['name_placeholder'] ?? '',
            'email_placeholder' => $settings['email_placeholder'] ?? '',
            'button_text'       => $settings['button_text'] ?? '',
            'consent_text'      => $settings['consent_text'] ?? '',
            'success_msg'       => $settings['success_msg'] ?? '',
            'error_msg'         => $settings['error_msg'] ?? '',
            'layout'            => $settings['layout'] ?? 'stacked',
            'list_ids'          => $settings['list_ids'] ?? '',
        ]);
    }
}
