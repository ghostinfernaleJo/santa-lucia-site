<?php
/**
 * Widget Elementor "Hero Espaces" — Santa Lucia
 * Section hero dark gradient : badge, H1 deux tons, sous-titre, nav tabs.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

class SL_Hero_Espaces_Widget extends Widget_Base {

    public function get_name()       { return 'sl_hero_espaces'; }
    public function get_title()      { return __( 'Hero Espaces', 'sl-agences' ); }
    public function get_icon()       { return 'eicon-banner'; }
    public function get_categories() { return [ 'santa-lucia' ]; }
    public function get_keywords()   { return [ 'hero', 'espaces', 'banner', 'header', 'santa lucia' ]; }

    public function get_style_depends()        { return [ 'sl-hero-espaces' ]; }
    public function has_widget_inner_wrapper(): bool { return false; }

    protected function register_controls() {

        /* ── CONTENU ─────────────────────────────────────────────── */
        $this->start_controls_section( 'section_hero', [
            'label' => __( '🎯 Contenu Hero', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'badge_text', [
            'label'   => __( 'Texte du badge', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => 'Complexe Santa Lucia',
        ] );

        $this->add_control( 'titre_ligne1', [
            'label'       => __( 'Titre — Ligne 1 (blanche)', 'sl-agences' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => 'Nos espaces,',
            'label_block' => true,
        ] );

        $this->add_control( 'titre_ligne2', [
            'label'       => __( 'Titre — Ligne 2 (rose)', 'sl-agences' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => 'votre expérience.',
            'label_block' => true,
        ] );

        $this->add_control( 'sous_titre', [
            'label'   => __( 'Sous-titre', 'sl-agences' ),
            'type'    => Controls_Manager::TEXTAREA,
            'default' => 'Du supermarché à la salle de réalité virtuelle, chaque espace Santa Lucia est conçu pour offrir une expérience unique à toute la famille.',
            'rows'    => 3,
        ] );

        $this->end_controls_section();

        /* ── ONGLETS DE NAVIGATION ───────────────────────────────── */
        $this->start_controls_section( 'section_tabs', [
            'label' => __( '🔗 Onglets de navigation', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $repeater = new Repeater();

        $repeater->add_control( 'tab_label', [
            'label'       => __( 'Label', 'sl-agences' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => 'Espace',
            'label_block' => true,
        ] );

        $repeater->add_control( 'tab_anchor', [
            'label'       => __( 'Ancre (sans #)', 'sl-agences' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => 'espace',
            'description' => __( 'Ex : espace-jeux → lien vers #espace-jeux', 'sl-agences' ),
        ] );

        $this->add_control( 'tabs', [
            'label'       => __( 'Onglets', 'sl-agences' ),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $repeater->get_controls(),
            'default'     => [
                [ 'tab_label' => 'Espace de Jeux',   'tab_anchor' => 'espace-jeux'    ],
                [ 'tab_label' => 'Omitland VR',       'tab_anchor' => 'omitland'       ],
                [ 'tab_label' => 'Boucherie',         'tab_anchor' => 'boucherie'      ],
                [ 'tab_label' => 'Fast Food',         'tab_anchor' => 'fast-food'      ],
                [ 'tab_label' => 'Façades',           'tab_anchor' => 'facades'        ],
                [ 'tab_label' => 'Bancs Publics',     'tab_anchor' => 'bancs-publics'  ],
                [ 'tab_label' => 'Espaces Dehors',    'tab_anchor' => 'espaces-dehors' ],
            ],
            'title_field' => '{{{ tab_label }}}',
        ] );

        $this->end_controls_section();

        /* ── STYLE ───────────────────────────────────────────────── */
        $this->start_controls_section( 'section_style', [
            'label' => __( '🎨 Couleur accent', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        $this->add_control( 'couleur_accent', [
            'label'     => __( 'Couleur accent (rose)', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#e85499',
            'selectors' => [
                '{{WRAPPER}} .slh-badge'      => 'border-color: {{VALUE}}33;',
                '{{WRAPPER}} .slh-badge-dot'  => 'background: {{VALUE}};',
                '{{WRAPPER}} .slh-badge-text' => 'color: {{VALUE}};',
                '{{WRAPPER}} .slh-titre-l2'   => 'color: {{VALUE}};',
                '{{WRAPPER}} .slh-tab:hover'  => 'color: {{VALUE}} !important; border-color: {{VALUE}} !important; background: {{VALUE}}14 !important;',
            ],
        ] );

        $this->end_controls_section();
    }

    protected function render() {
        $s    = $this->get_settings_for_display();
        $tabs = $s['tabs'] ?? [];
        ?>
        <style>
        /* ── Responsive Hero Espaces — inline (CDN bypass) ──────── */

        /* Tablet large ≤ 1024px */
        @media (max-width: 1024px) {
            .slh-inner { padding: 0 40px; }
            .slh-tab   { font-size: 8.5px; padding: 10px 10px; }
        }

        /* Tablet ≤ 768px */
        @media (max-width: 768px) {
            .slh-section    { padding: 64px 0 44px; }
            .slh-inner      { padding: 0 24px; }
            .slh-titre      { font-size: clamp(30px, 7vw, 44px); }
            .slh-sous-titre { font-size: 14px; margin-bottom: 36px; }
            .slh-tabs-wrap  { gap: 5px; }
            .slh-tab        { flex: 0 0 auto; font-size: 8px; letter-spacing: 1.5px; padding: 10px 14px; }
        }

        /* Mobile ≤ 480px */
        @media (max-width: 480px) {
            .slh-section    { padding: 52px 0 36px; }
            .slh-inner      { padding: 0 16px; }
            .slh-badge      { padding: 5px 12px; margin-bottom: 20px; }
            .slh-badge-text { font-size: 8px; letter-spacing: 2px; }
            .slh-titre      { font-size: clamp(26px, 8.5vw, 36px); margin-bottom: 14px; }
            .slh-sous-titre { font-size: 13px; line-height: 1.65; margin-bottom: 24px; }
            .slh-tabs-wrap  { gap: 4px; }
            .slh-tab        { padding: 8px 12px; font-size: 7.5px; letter-spacing: 1px; }
        }
        </style>
        <div class="slh-hero-wrap">
          <section class="slh-section">
            <div class="slh-dot-grid"></div>
            <div class="slh-inner">

              <!-- Badge -->
              <div class="slh-badge">
                <span class="slh-badge-dot"></span>
                <span class="slh-badge-text"><?php echo esc_html( $s['badge_text'] ); ?></span>
              </div>

              <!-- Titre H1 deux tons -->
              <h1 class="slh-titre">
                <span class="slh-titre-l1"><?php echo esc_html( $s['titre_ligne1'] ); ?></span>
                <span class="slh-titre-l2"><?php echo esc_html( $s['titre_ligne2'] ); ?></span>
              </h1>

              <!-- Sous-titre -->
              <p class="slh-sous-titre"><?php echo esc_html( $s['sous_titre'] ); ?></p>

              <!-- Tabs navigation -->
              <?php if ( ! empty( $tabs ) ) : ?>
              <div class="slh-tabs-wrap">
                <?php foreach ( $tabs as $tab ) : ?>
                  <a href="#<?php echo esc_attr( $tab['tab_anchor'] ); ?>" class="slh-tab">
                    <?php echo esc_html( $tab['tab_label'] ); ?>
                  </a>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>

            </div>
          </section>
        </div>
        <?php
    }
}
