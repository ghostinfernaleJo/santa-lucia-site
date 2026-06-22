<?php
/**
 * Widget Elementor "Immersion Slider" (Storytelling)
 * A full-screen video slider with synchronized GSAP text and bottom timeline.
 */

if (!defined('ABSPATH'))
    exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Utils;

class SL_Immersion_Slider_Widget extends Widget_Base
{

    public function get_name()
    {
        return 'sl_immersion_slider';
    }

    public function get_title()
    {
        return __('Slider Immersif (Storytelling)', 'sl-agences');
    }

    public function get_icon()
    {
        return 'eicon-slider-video';
    }

    public function get_categories()
    {
        return ['santa-lucia'];
    }

    public function get_keywords()
    {
        return ['slider', 'video', 'storytelling', 'immersion', 'full-screen'];
    }

    public function get_script_depends()
    {
        return ['gsap', 'sl-immersion-slider'];
    }

    public function get_style_depends()
    {
        return ['sl-immersion-slider'];
    }

    protected function register_controls()
    {

        /* ============================================================
         *  SECTION : SLIDES (Répéteur)
         * ============================================================ */
        $this->start_controls_section(
            'section_slides',
            [
                'label' => __('🎥 Slides', 'sl-agences'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $repeater = new Repeater();

        // Titre
        $repeater->add_control(
            'slide_title',
            [
                'label' => __('Titre Principal', 'sl-agences'),
                'type' => Controls_Manager::TEXTAREA,
                'default' => __('It All Starts With the Earth', 'sl-agences'),
                'label_block' => true,
            ]
        );

        // Sous-titre
        $repeater->add_control(
            'slide_subtitle',
            [
                'label' => __('Sous-titre', 'sl-agences'),
                'type' => Controls_Manager::TEXT,
                'default' => __('A season\'s dedication brought to life.', 'sl-agences'),
                'label_block' => true,
            ]
        );

        // Bouton
        $repeater->add_control(
            'slide_btn_text',
            [
                'label' => __('Texte du Bouton', 'sl-agences'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Learn the process', 'sl-agences'),
            ]
        );

        $repeater->add_control(
            'slide_btn_link',
            [
                'label' => __('Lien du Bouton', 'sl-agences'),
                'type' => Controls_Manager::URL,
                'default' => ['url' => '#'],
                'show_external' => true,
            ]
        );

        // Bouton 2
        $repeater->add_control(
            'slide_btn2_text',
            [
                'label' => __('Texte Bouton 2 (optionnel)', 'sl-agences'),
                'type' => Controls_Manager::TEXT,
                'default' => '',
                'placeholder' => 'Qui sommes-nous ?',
            ]
        );

        $repeater->add_control(
            'slide_btn2_link',
            [
                'label' => __('Lien Bouton 2', 'sl-agences'),
                'type' => Controls_Manager::URL,
                'default' => ['url' => '/a-propos/'],
                'show_external' => true,
            ]
        );

        // Label Timeline
        $repeater->add_control(
            'slide_timeline_label',
            [
                'label' => __('Texte dans la barre du bas (Timeline)', 'sl-agences'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Preparing the Land', 'sl-agences'),
                'label_block' => true,
                'description' => __('C\'est le texte qui s\'affiche en bas à côté du numéro.', 'sl-agences'),
            ]
        );

        // Média Vidéo
        $repeater->add_control(
            'slide_video',
            [
                'label' => __('Fichier Vidéo (MP4/WebM)', 'sl-agences'),
                'type' => Controls_Manager::MEDIA,
                'media_types' => ['video'],
                'description' => __('Uploadez une vidéo HD compressée.', 'sl-agences'),
            ]
        );

        // Média Image de fallback
        $repeater->add_control(
            'slide_image',
            [
                'label' => __('Image de fond (Fallback ou si pas de vidéo)', 'sl-agences'),
                'type' => Controls_Manager::MEDIA,
                'default' => [
                    'url' => Utils::get_placeholder_image_src(),
                ],
            ]
        );

        $this->add_control(
            'slides',
            [
                'label' => __('Slides', 'sl-agences'),
                'type' => Controls_Manager::REPEATER,
                'fields' => $repeater->get_controls(),
                'default' => [
                    [
                        'slide_title' => 'It All Starts With the Earth',
                        'slide_subtitle' => 'A season\'s dedication brought to life.',
                        'slide_timeline_label' => 'Preparing the Land',
                        'slide_btn_text' => 'Learn the process'
                    ],
                    [
                        'slide_title' => 'Growth Begins With Care',
                        'slide_subtitle' => 'Caring is heart of all growth.',
                        'slide_timeline_label' => 'Growing & Nurturing',
                        'slide_btn_text' => 'View the approach'
                    ],
                    [
                        'slide_title' => 'The Fruits of Labor',
                        'slide_subtitle' => 'Hard work bears the sweetest fruit.',
                        'slide_timeline_label' => 'Harvesting',
                        'slide_btn_text' => 'Explore the bounty'
                    ],
                    [
                        'slide_title' => 'From Our Fields to Your Table',
                        'slide_subtitle' => 'Delivering real food with stories.',
                        'slide_timeline_label' => 'Farm to Market',
                        'slide_btn_text' => 'Taste the origin'
                    ],
                ],
                'title_field' => '{{{ slide_timeline_label }}}',
            ]
        );

        $this->end_controls_section();

        /* ============================================================
         *  SECTION : BOUTON 2 (widget-level, commun à tous les slides)
         * ============================================================ */
        $this->start_controls_section(
            'section_btn2',
            [
                'label' => __('🔘 Bouton 2 — Qui sommes-nous ?', 'sl-agences'),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'btn2_afficher',
            [
                'label'        => __('Afficher le bouton', 'sl-agences'),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __('Oui', 'sl-agences'),
                'label_off'    => __('Non', 'sl-agences'),
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'btn2_texte',
            [
                'label'     => __('Texte', 'sl-agences'),
                'type'      => Controls_Manager::TEXT,
                'default'   => 'Qui sommes-nous ?',
                'condition' => [ 'btn2_afficher' => 'yes' ],
            ]
        );

        $this->add_control(
            'btn2_lien',
            [
                'label'     => __('Lien', 'sl-agences'),
                'type'      => Controls_Manager::URL,
                'default'   => [ 'url' => '/a-propos/' ],
                'condition' => [ 'btn2_afficher' => 'yes' ],
            ]
        );

        $this->add_control(
            'btn2_fleche',
            [
                'label'        => __('Afficher la flèche →', 'sl-agences'),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __('Oui', 'sl-agences'),
                'label_off'    => __('Non', 'sl-agences'),
                'return_value' => 'yes',
                'default'      => 'yes',
                'condition'    => [ 'btn2_afficher' => 'yes' ],
            ]
        );

        $this->end_controls_section();

        /* ============================================================
         *  SECTION : STYLE BOUTON 2
         * ============================================================ */
        $this->start_controls_section(
            'section_style_btn2',
            [
                'label'     => __('🎨 Style Bouton 2', 'sl-agences'),
                'tab'       => Controls_Manager::TAB_STYLE,
                'condition' => [ 'btn2_afficher' => 'yes' ],
            ]
        );

        $this->add_control(
            'btn2_couleur_texte',
            [
                'label'     => __('Couleur texte', 'sl-agences'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [ '{{WRAPPER}} .sl-btn2' => 'color: {{VALUE}} !important;' ],
            ]
        );

        $this->add_control(
            'btn2_couleur_fond',
            [
                'label'     => __('Couleur fond', 'sl-agences'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [ '{{WRAPPER}} .sl-btn2' => 'background-color: {{VALUE}} !important;' ],
            ]
        );

        $this->add_control(
            'btn2_couleur_bordure',
            [
                'label'     => __('Couleur bordure', 'sl-agences'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [ '{{WRAPPER}} .sl-btn2' => 'border-color: {{VALUE}} !important;' ],
            ]
        );

        $this->add_control(
            'btn2_couleur_texte_hover',
            [
                'label'     => __('Couleur texte (survol)', 'sl-agences'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [ '{{WRAPPER}} .sl-btn2:hover' => 'color: {{VALUE}} !important;' ],
            ]
        );

        $this->add_control(
            'btn2_couleur_fond_hover',
            [
                'label'     => __('Couleur fond (survol)', 'sl-agences'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [ '{{WRAPPER}} .sl-btn2:hover' => 'background-color: {{VALUE}} !important;' ],
            ]
        );

        $this->add_responsive_control(
            'btn2_padding',
            [
                'label'      => __('Padding', 'sl-agences'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', 'em', '%' ],
                'selectors'  => [ '{{WRAPPER}} .sl-btn2' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;' ],
            ]
        );

        $this->add_responsive_control(
            'btn2_margin',
            [
                'label'      => __('Margin', 'sl-agences'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', 'em', '%' ],
                'selectors'  => [ '{{WRAPPER}} .sl-btn2' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;' ],
            ]
        );

        $this->end_controls_section();

        /* ============================================================
         *  SECTION : NAVIGATION MOBILE
         * ============================================================ */
        $this->start_controls_section(
            'section_mobile_nav',
            [
                'label' => __('📱 Navigation Mobile', 'sl-agences'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'mobile_text_prev',
            [
                'label' => __('Texte Précédent', 'sl-agences'),
                'type' => Controls_Manager::TEXT,
                'default' => 'PREV',
            ]
        );

        $this->add_control(
            'mobile_text_next',
            [
                'label' => __('Texte Suivant', 'sl-agences'),
                'type' => Controls_Manager::TEXT,
                'default' => 'NEXT',
            ]
        );

        $this->add_control(
            'mobile_text_sep',
            [
                'label' => __('Séparateur', 'sl-agences'),
                'type' => Controls_Manager::TEXT,
                'default' => '/',
            ]
        );

        $this->end_controls_section();

        /* ============================================================
         *  SECTION : REGLAGES DU SLIDER
         * ============================================================ */
        $this->start_controls_section(
            'section_settings',
            [
                'label' => __('⚙️ Paramètres', 'sl-agences'),
                'tab' => Controls_Manager::TAB_SETTINGS,
            ]
        );

        $this->add_control(
            'slider_autoplay',
            [
                'label' => __('Défilement automatique', 'sl-agences'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'sl-agences'),
                'label_off' => __('Non', 'sl-agences'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'slider_delay',
            [
                'label' => __('Délai entre les slides (en ms)', 'sl-agences'),
                'type' => Controls_Manager::NUMBER,
                'min' => 2000,
                'max' => 20000,
                'step' => 500,
                'default' => 8000,
                'condition' => [
                    'slider_autoplay' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'anim_duration',
            [
                'label' => __('Durée de l\'animation (en s)', 'sl-agences'),
                'type' => Controls_Manager::NUMBER,
                'min' => 0.1,
                'max' => 5,
                'step' => 0.1,
                'default' => 0.8,
            ]
        );

        $this->add_control(
            'slider_height',
            [
                'label' => __('Hauteur du Slider', 'sl-agences'),
                'type' => Controls_Manager::SELECT,
                'default' => '100vh',
                'options' => [
                    '100vh' => __('Plein écran (100vh)', 'sl-agences'),
                    '80vh' => __('80% de l\'écran (80vh)', 'sl-agences'),
                    'custom' => __('Personnalisé', 'sl-agences'),
                ],
                'selectors' => [
                    '{{WRAPPER}} .sl-immersion-container' => 'height: {{VALUE}};',
                ],
                'condition' => [
                    'slider_height!' => 'custom',
                ],
            ]
        );

        $this->add_responsive_control(
            'slider_height_custom',
            [
                'label' => __('Hauteur Personnalisée', 'sl-agences'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', 'vh', 'em', 'rem'],
                'range' => [
                    'px' => ['min' => 200, 'max' => 1200],
                    'vh' => ['min' => 30, 'max' => 100],
                ],
                'default' => ['unit' => 'px', 'size' => 700],
                'condition' => [
                    'slider_height' => 'custom',
                ],
                'selectors' => [
                    '{{WRAPPER}} .sl-immersion-container' => 'height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name' => 'overlay_background',
                'label' => __('Fond de l\'overlay (sur la vidéo)', 'sl-agences'),
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .sl-immersion-slide::after',
            ]
        );

        $this->end_controls_section();

        /* ============================================================
         *  SECTION : STYLE
         * ============================================================ */
        $this->start_controls_section(
            'section_style_texts',
            [
                'label' => __('🎨 Couleurs & Typographie', 'sl-agences'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'color_theme',
            [
                'label' => __('Couleur d\'accent (Barre de progression, hover)', 'sl-agences'),
                'type' => Controls_Manager::COLOR,
                'default' => '#32CD32', // Un beau vert par défaut,
                'selectors' => [
                    '{{WRAPPER}} .sl-progress-fill' => 'background-color: {{VALUE}};',
                    '{{WRAPPER}} .sl-timeline-step.active .sl-step-num' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .sl-mobile-sep' => 'color: {{VALUE}};',
                ]
            ]
        );

        /* Content Position & Padding */
        $this->add_control(
            'heading_content_position',
            [
                'label' => __('Position du bloc global', 'sl-agences'),
                'type' => Controls_Manager::HEADING,
            ]
        );
        $this->add_responsive_control(
            'content_padding',
            [
                'label' => __('Marge Interne (Padding) du Contenu', 'sl-agences'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'vh', 'vw'],
                'selectors' => ['{{WRAPPER}} .sl-immersion-content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );
        $this->add_responsive_control(
            'content_v_align',
            [
                'label' => __('Alignement Vertical (Flex)', 'sl-agences'),
                'type' => Controls_Manager::CHOOSE,
                'options' => [
                    'flex-start' => ['title' => __('Haut', 'sl-agences'), 'icon' => 'eicon-v-align-top'],
                    'center' => ['title' => __('Milieu', 'sl-agences'), 'icon' => 'eicon-v-align-middle'],
                    'flex-end' => ['title' => __('Bas', 'sl-agences'), 'icon' => 'eicon-v-align-bottom'],
                ],
                'selectors' => ['{{WRAPPER}} .sl-immersion-slide' => 'align-items: {{VALUE}};'],
            ]
        );
        $this->add_responsive_control(
            'content_h_align',
            [
                'label' => __('Alignement Horizontal (Global)', 'sl-agences'),
                'type' => Controls_Manager::CHOOSE,
                'options' => [
                    '0 auto auto 0' => ['title' => __('Gauche', 'sl-agences'), 'icon' => 'eicon-h-align-left'],
                    '0 auto' => ['title' => __('Centre', 'sl-agences'), 'icon' => 'eicon-h-align-center'],
                    '0 0 auto auto' => ['title' => __('Droite', 'sl-agences'), 'icon' => 'eicon-h-align-right'],
                ],
                'selectors' => ['{{WRAPPER}} .sl-content-inner' => 'margin: {{VALUE}};'],
            ]
        );
        $this->add_responsive_control(
            'content_text_align',
            [
                'label' => __('Alignement du texte (Interne)', 'sl-agences'),
                'type' => Controls_Manager::CHOOSE,
                'options' => [
                    'left' => ['title' => __('Gauche', 'sl-agences'), 'icon' => 'eicon-text-align-left'],
                    'center' => ['title' => __('Centre', 'sl-agences'), 'icon' => 'eicon-text-align-center'],
                    'right' => ['title' => __('Droite', 'sl-agences'), 'icon' => 'eicon-text-align-right'],
                ],
                'selectors' => ['{{WRAPPER}} .sl-content-inner' => 'text-align: {{VALUE}};'],
            ]
        );

        /* Title Style */
        $this->add_control(
            'heading_title',
            [
                'label' => __('Titre Principal', 'sl-agences'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );
        $this->add_control(
            'color_title',
            [
                'label' => __('Couleur du Titre', 'sl-agences'),
                'type' => Controls_Manager::COLOR,
                'default' => '#FFFFFF',
                'selectors' => [
                    '{{WRAPPER}} .sl-immersion-content h2' => 'color: {{VALUE}};',
                ]
            ]
        );
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'label' => __('Typographie Titre', 'sl-agences'),
                'selector' => '{{WRAPPER}} .sl-immersion-content h2',
            ]
        );
        $this->add_responsive_control(
            'title_margin',
            [
                'label' => __('Marge (Margin)', 'sl-agences'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .sl-immersion-content h2' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        /* Subtitle Style */
        $this->add_control(
            'heading_subtitle',
            [
                'label' => __('Sous-titre', 'sl-agences'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );
        $this->add_control(
            'color_subtitle',
            [
                'label' => __('Couleur du Sous-titre', 'sl-agences'),
                'type' => Controls_Manager::COLOR,
                'default' => '#FFFFFF',
                'selectors' => [
                    '{{WRAPPER}} .sl-immersion-content p' => 'color: {{VALUE}}; opacity: 1;',
                ]
            ]
        );
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'subtitle_typography',
                'label' => __('Typographie Sous-titre', 'sl-agences'),
                'selector' => '{{WRAPPER}} .sl-immersion-content p',
            ]
        );
        $this->add_responsive_control(
            'subtitle_margin',
            [
                'label' => __('Marge (Margin)', 'sl-agences'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .sl-immersion-content p' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        /* ============================================================
         *  SECTION : STYLE - BOUTON
         * ============================================================ */
        $this->start_controls_section(
            'section_style_button',
            [
                'label' => __('🕹️ Bouton', 'sl-agences'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'btn_typography',
                'label' => __('Typographie', 'sl-agences'),
                'selector' => '{{WRAPPER}} .sl-immersion-btn',
            ]
        );

        $this->start_controls_tabs('tabs_button_style');

        $this->start_controls_tab(
            'tab_button_normal',
            ['label' => __('Normal', 'sl-agences')]
        );

        $this->add_control(
            'btn_color',
            [
                'label' => __('Couleur du texte', 'sl-agences'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => ['{{WRAPPER}} .sl-immersion-btn' => 'color: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'btn_bg_color',
            [
                'label' => __('Couleur de fond', 'sl-agences'),
                'type' => Controls_Manager::COLOR,
                'default' => 'transparent',
                'selectors' => ['{{WRAPPER}} .sl-immersion-btn' => 'background-color: {{VALUE}};'],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'tab_button_hover',
            ['label' => __('Survol', 'sl-agences')]
        );

        $this->add_control(
            'btn_color_hover',
            [
                'label' => __('Couleur du texte', 'sl-agences'),
                'type' => Controls_Manager::COLOR,
                'default' => '#000000',
                'selectors' => ['{{WRAPPER}} .sl-immersion-btn:hover' => 'color: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'btn_bg_color_hover',
            [
                'label' => __('Couleur de fond', 'sl-agences'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => ['{{WRAPPER}} .sl-immersion-btn:hover' => 'background-color: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'btn_border_color_hover',
            [
                'label' => __('Couleur bordure (Hover)', 'sl-agences'),
                'type' => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .sl-immersion-btn:hover' => 'border-color: {{VALUE}};'],
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'btn_border',
                'label' => __('Bordure', 'sl-agences'),
                'selector' => '{{WRAPPER}} .sl-immersion-btn',
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'btn_border_radius',
            [
                'label' => __('Rayon de bordure', 'sl-agences'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => ['{{WRAPPER}} .sl-immersion-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'btn_padding',
            [
                'label' => __('Padding', 'sl-agences'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => ['{{WRAPPER}} .sl-immersion-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->end_controls_section();

        /* ============================================================
         *  SECTION : STYLE - TIMELINE
         * ============================================================ */
        $this->start_controls_section(
            'section_style_timeline',
            [
                'label' => __('⏱️ Timeline', 'sl-agences'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name' => 'timeline_background',
                'label' => __('Fond de la timeline', 'sl-agences'),
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .sl-immersion-timeline-wrap',
            ]
        );

        $this->add_responsive_control(
            'timeline_margin',
            [
                'label' => __('Marge de la zone (Détacher du bas)', 'sl-agences'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em', 'vh'],
                'selectors' => ['{{WRAPPER}} .sl-immersion-timeline-wrap' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; position: absolute; bottom: 0;'],
            ]
        );

        $this->add_responsive_control(
            'timeline_padding',
            [
                'label' => __('Padding de la zone', 'sl-agences'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => ['{{WRAPPER}} .sl-immersion-timeline-wrap' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'timeline_box_shadow',
                'label' => __('Ombre de la zone', 'sl-agences'),
                'selector' => '{{WRAPPER}} .sl-immersion-timeline-wrap',
            ]
        );

        $this->add_control(
            'heading_timeline_tabs',
            [
                'label' => __('Style des Onglets (Tabs)', 'sl-agences'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_responsive_control(
            'timeline_steps_gap',
            [
                'label' => __('Espace entre les blocs', 'sl-agences'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', 'vw'],
                'range' => ['px' => ['min' => 0, 'max' => 100]],
                'selectors' => ['{{WRAPPER}} .sl-timeline-steps' => 'gap: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'timeline_num_typography',
                'label' => __('Typographie du Chiffre (01)', 'sl-agences'),
                'selector' => '{{WRAPPER}} .sl-step-num',
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'timeline_label_typography',
                'label' => __('Typographie du Titre', 'sl-agences'),
                'selector' => '{{WRAPPER}} .sl-step-label',
            ]
        );

        $this->start_controls_tabs('tabs_timeline_steps');

        $this->start_controls_tab(
            'tab_timeline_normal',
            ['label' => __('Normal', 'sl-agences')]
        );

        $this->add_control(
            'timeline_color',
            [
                'label' => __('Couleur texte', 'sl-agences'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => ['{{WRAPPER}} .sl-timeline-step' => 'color: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'timeline_tab_bg',
            [
                'label' => __('Fond de l\'onglet', 'sl-agences'),
                'type' => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .sl-timeline-step' => 'background-color: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'timeline_tab_opacity',
            [
                'label' => __('Opacité de l\'onglet', 'sl-agences'),
                'type' => Controls_Manager::SLIDER,
                'range' => ['px' => ['min' => 0, 'max' => 1, 'step' => 0.05]],
                'default' => ['size' => 0.5],
                'selectors' => ['{{WRAPPER}} .sl-timeline-step' => 'opacity: {{SIZE}};'],
            ]
        );

        $this->add_control(
            'timeline_glass_blur',
            [
                'label' => __('Flou Glassmorphism (px)', 'sl-agences'),
                'type' => Controls_Manager::SLIDER,
                'range' => ['px' => ['min' => 0, 'max' => 50]],
                'selectors' => [
                    '{{WRAPPER}} .sl-timeline-step' => 'backdrop-filter: blur({{SIZE}}px); -webkit-backdrop-filter: blur({{SIZE}}px);'
                ],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'tab_timeline_hover',
            ['label' => __('Survol / Actif', 'sl-agences')]
        );

        $this->add_control(
            'timeline_color_active',
            [
                'label' => __('Couleur texte actif', 'sl-agences'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .sl-timeline-step:hover' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .sl-timeline-step.active' => 'color: {{VALUE}};'
                ],
            ]
        );

        $this->add_control(
            'timeline_tab_bg_active',
            [
                'label' => __('Fond onglet actif', 'sl-agences'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .sl-timeline-step:hover' => 'background-color: {{VALUE}};',
                    '{{WRAPPER}} .sl-timeline-step.active' => 'background-color: {{VALUE}};'
                ],
            ]
        );

        $this->add_control(
            'timeline_tab_opacity_active',
            [
                'label' => __('Opacité onglet actif', 'sl-agences'),
                'type' => Controls_Manager::SLIDER,
                'range' => ['px' => ['min' => 0, 'max' => 1, 'step' => 0.05]],
                'default' => ['size' => 1],
                'selectors' => [
                    '{{WRAPPER}} .sl-timeline-step:hover' => 'opacity: {{SIZE}};',
                    '{{WRAPPER}} .sl-timeline-step.active' => 'opacity: {{SIZE}};'
                ],
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_responsive_control(
            'timeline_tab_padding',
            [
                'label' => __('Padding (Espacement interne de l\'onglet)', 'sl-agences'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => ['{{WRAPPER}} .sl-timeline-step' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->add_control(
            'timeline_tab_border_radius',
            [
                'label' => __('Arrondi des angles (Border Radius)', 'sl-agences'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => ['{{WRAPPER}} .sl-timeline-step' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'timeline_tab_border',
                'label' => __('Bordure de l\'onglet', 'sl-agences'),
                'selector' => '{{WRAPPER}} .sl-timeline-step',
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'timeline_tab_box_shadow',
                'label' => __('Ombre de l\'onglet', 'sl-agences'),
                'selector' => '{{WRAPPER}} .sl-timeline-step',
            ]
        );

        $this->add_control(
            'heading_timeline_line',
            [
                'label' => __('Style de la Ligne / Barre de progression', 'sl-agences'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_responsive_control(
            'timeline_line_margin',
            [
                'label' => __('Marges de la ligne (Pour la décoller)', 'sl-agences'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => ['{{WRAPPER}} .sl-progress-track' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->add_control(
            'timeline_line_height',
            [
                'label' => __('Hauteur de la ligne (px)', 'sl-agences'),
                'type' => Controls_Manager::SLIDER,
                'range' => ['px' => ['min' => 1, 'max' => 20]],
                'selectors' => ['{{WRAPPER}} .sl-progress-track, {{WRAPPER}} .sl-progress-fill' => 'height: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->end_controls_section();

        /* ============================================================
         *  SECTION : STYLE - NAVIGATION MOBILE
         * ============================================================ */
        $this->start_controls_section(
            'section_style_mobile_nav',
            [
                'label' => __('📱 Style Nav Mobile', 'sl-agences'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'mob_nav_bottom',
            [
                'label' => __('Position Y (Bas de l\'écran)', 'sl-agences'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', '%', 'vh'],
                'range' => [
                    'px' => ['min' => 0, 'max' => 200],
                ],
                'selectors' => ['{{WRAPPER}} .sl-immersion-timeline-wrap' => 'bottom: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'mob_nav_padding',
            [
                'label' => __('Padding (Marges Horizontales)', 'sl-agences'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'vw'],
                'selectors' => ['{{WRAPPER}} .sl-immersion-timeline-wrap' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; width: 100%; left: 0;'],
            ]
        );

        $this->add_responsive_control(
            'mob_nav_justify',
            [
                'label' => __('Alignement PREV/NEXT et Compteur', 'sl-agences'),
                'type' => Controls_Manager::SELECT,
                'default' => 'space-between',
                'options' => [
                    'flex-start' => __('À gauche', 'sl-agences'),
                    'center' => __('Au centre', 'sl-agences'),
                    'flex-end' => __('À droite', 'sl-agences'),
                    'space-between' => __('Séparés (Gauche / Droite)', 'sl-agences'),
                    'space-around' => __('Espace autour', 'sl-agences'),
                ],
                'selectors' => ['{{WRAPPER}} .sl-mobile-controls' => 'justify-content: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'mob_nav_color',
            [
                'label' => __('Couleur des Textes (PREV/NEXT/01)', 'sl-agences'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .sl-mobile-controls' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .sl-mobile-prev' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .sl-mobile-next' => 'color: {{VALUE}};',
                ]
            ]
        );

        $this->end_controls_section();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $slides = $settings['slides'];

        if (empty($slides)) {
            return;
        }

        $widget_id = $this->get_id();
        $autoplay = $settings['slider_autoplay'] === 'yes' ? 'true' : 'false';
        $delay = isset($settings['slider_delay']) ? $settings['slider_delay'] : 8000;
        $anim_duration = isset($settings['anim_duration']) ? $settings['anim_duration'] : 0.8;

        // Preload du poster du 1er slide (element LCP probable sur mobile) — accelere le LCP
        $sl_first = reset($slides);
        $sl_lcp_poster = $sl_first['slide_image']['url'] ?? '';
        ?>
        <?php if ( $sl_lcp_poster ) : ?>
        <link rel="preload" as="image" href="<?php echo esc_url( $sl_lcp_poster ); ?>" fetchpriority="high">
        <?php endif; ?>
        <style>
        /* Fix : transition CSS ne doit pas inclure opacity/transform (GSAP les gère) */
        .sl-immersion-btn {
            transition: background 0.35s cubic-bezier(.4,0,.2,1),
                        color 0.35s cubic-bezier(.4,0,.2,1),
                        border-color 0.35s cubic-bezier(.4,0,.2,1),
                        box-shadow 0.35s cubic-bezier(.4,0,.2,1) !important;
        }
        </style>
        <div class="sl-immersion-container" id="sl-immersion-<?php echo esc_attr($widget_id); ?>"
            data-autoplay="<?php echo esc_attr($autoplay); ?>" data-delay="<?php echo esc_attr($delay); ?>"
            data-anim-duration="<?php echo esc_attr($anim_duration); ?>">

            <div class="sl-immersion-slides-wrapper">
                <?php foreach ($slides as $index => $slide):
                    $is_active = $index === 0 ? 'active' : '';
                    $video_url = isset($slide['slide_video']['url']) ? $slide['slide_video']['url'] : '';
                    $image_url = isset($slide['slide_image']['url']) ? $slide['slide_image']['url'] : '';
                    ?>
                    <div class="sl-immersion-slide <?php echo esc_attr($is_active); ?>" data-index="<?php echo $index; ?>">

                        <?php if (!empty($video_url)): 
                            $preload = $index === 0 ? 'auto' : 'none';
                            $autoplay_attr = $index === 0 ? 'autoplay' : '';
                        ?>
                            <video class="sl-bg-media" <?php echo $autoplay_attr; ?> muted loop playsinline preload="<?php echo $preload; ?>" poster="<?php echo esc_url($image_url); ?>">
                                <source src="<?php echo esc_url($video_url); ?>" type="video/mp4">
                            </video>
                        <?php elseif (!empty($image_url)): ?>
                            <img class="sl-bg-media" src="<?php echo esc_url($image_url); ?>"
                                alt="<?php echo esc_attr($slide['slide_title']); ?>">
                        <?php endif; ?>

                        <div class="sl-immersion-content">
                            <div class="sl-content-inner">
                                <?php if (!empty($slide['slide_subtitle'])): ?>
                                    <p class="sl-slide-subtitle"><?php echo esc_html($slide['slide_subtitle']); ?></p>
                                <?php endif; ?>

                                <?php if (!empty($slide['slide_title'])): ?>
                                    <h2 class="sl-slide-title"><?php echo wp_kses_post($slide['slide_title']); ?></h2>
                                <?php endif; ?>

                                <?php
                                $has_btn1 = !empty($slide['slide_btn_text']);
                                $has_btn2 = ( $settings['btn2_afficher'] ?? '' ) === 'yes' && !empty( $settings['btn2_texte'] );
                                if ($has_btn1 || $has_btn2):
                                    $btn2_url    = $settings['btn2_lien']['url'] ?? '/a-propos/';
                                    $btn2_target = !empty($settings['btn2_lien']['is_external']) ? '_blank' : '_self';
                                    $btn2_nofollow = !empty($settings['btn2_lien']['nofollow']) ? 'rel="nofollow"' : '';
                                    $btn2_fleche = ( $settings['btn2_fleche'] ?? 'yes' ) === 'yes';
                                ?>
                                    <div class="sl-btn-group">
                                        <?php if ($has_btn1):
                                            $link_url = isset($slide['slide_btn_link']['url']) ? $slide['slide_btn_link']['url'] : '#';
                                            $target   = !empty($slide['slide_btn_link']['is_external']) ? '_blank' : '_self';
                                            $nofollow = !empty($slide['slide_btn_link']['nofollow']) ? 'rel="nofollow"' : '';
                                        ?>
                                        <a class="sl-immersion-btn" href="<?php echo esc_url($link_url); ?>"
                                            target="<?php echo esc_attr($target); ?>" <?php echo $nofollow; ?>>
                                            <?php echo esc_html($slide['slide_btn_text']); ?>
                                            <span class="sl-btn-arrow">→</span>
                                        </a>
                                        <?php endif; ?>
                                        <?php if ($has_btn2): ?>
                                        <a class="sl-immersion-btn sl-btn2"
                                            href="<?php echo esc_url($btn2_url); ?>"
                                            target="<?php echo esc_attr($btn2_target); ?>" <?php echo $btn2_nofollow; ?>>
                                            <?php echo esc_html($settings['btn2_texte']); ?>
                                            <?php if ($btn2_fleche): ?><span class="sl-btn-arrow">→</span><?php endif; ?>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="sl-immersion-timeline-wrap">
                <div class="sl-progress-track">
                    <div class="sl-progress-fill" style="width: <?php echo (100 / count($slides)); ?>%;"></div>
                </div>

                <div class="sl-timeline-steps">
                    <?php foreach ($slides as $index => $slide):
                        $is_active = $index === 0 ? 'active' : '';
                        $num = str_pad($index + 1, 2, '0', STR_PAD_LEFT);
                        ?>
                        <div class="sl-timeline-step <?php echo esc_attr($is_active); ?>" data-index="<?php echo $index; ?>">
                            <span class="sl-step-num"><?php echo $num; ?></span>
                            <span class="sl-step-label"><?php echo esc_html($slide['slide_timeline_label']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- CONTROLES MOBILES (Cachés sur Desktop) -->
                <div class="sl-mobile-controls">
                    <div class="sl-mobile-nav">
                        <button class="sl-mobile-prev"><?php echo esc_html($settings['mobile_text_prev']); ?></button>
                        <span class="sl-mobile-sep"><?php echo esc_html($settings['mobile_text_sep']); ?></span>
                        <button class="sl-mobile-next"><?php echo esc_html($settings['mobile_text_next']); ?></button>
                    </div>
                    <div class="sl-mobile-counter">
                        <span class="sl-mobile-current">01</span>
                    </div>
                </div>
            </div>

        </div>

        <?php
    }
}
