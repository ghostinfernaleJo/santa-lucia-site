<?php
/**
 * Widget Elementor "Hero Produits Maison" — Santa Lucia
 *
 * Hero dark dégradé : badge · H1 · desc · barre de stats.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

class SL_Hero_Produits_Widget extends Widget_Base {

    public function get_name()       { return 'sl_hero_produits'; }
    public function get_title()      { return __( 'Hero Produits Maison', 'sl-agences' ); }
    public function get_icon()       { return 'eicon-product-images'; }
    public function get_categories() { return [ 'santa-lucia' ]; }
    public function get_keywords()   { return [ 'hero', 'produits', 'maison', 'santa lucia' ]; }

    public function get_style_depends()        { return [ 'sl-produits-maison-page' ]; }
    public function has_widget_inner_wrapper(): bool { return false; }

    protected function register_controls() {

        /* ── BADGE & TITRE ── */
        $this->start_controls_section( 'section_hero', [
            'label' => __( '✏️ Contenu', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'badge_texte', [
            'label'   => __( 'Texte du badge', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => 'Produits Maison',
        ] );

        $this->add_control( 'titre', [
            'label'       => __( 'Titre principal', 'sl-agences' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => 'Toutes nos références Santa Lucia',
            'label_block' => true,
        ] );

        $this->add_control( 'description', [
            'label'   => __( 'Description', 'sl-agences' ),
            'type'    => Controls_Manager::TEXTAREA,
            'default' => 'Retrouvez les produits maison par famille : pâtes, farines, snacks, glaces et gammes Chocojoy.',
            'rows'    => 3,
        ] );

        $this->end_controls_section();

        /* ── STATS ── */
        $this->start_controls_section( 'section_stats', [
            'label' => __( '📊 Barre de stats', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $rep = new Repeater();
        $rep->add_control( 'stat_val', [
            'label'   => __( 'Valeur', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => '6',
        ] );
        $rep->add_control( 'stat_lbl', [
            'label'   => __( 'Label', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => 'Familles',
        ] );

        $this->add_control( 'stats', [
            'label'       => __( 'Stats', 'sl-agences' ),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $rep->get_controls(),
            'default'     => [
                [ 'stat_val' => '6',   'stat_lbl' => 'Familles' ],
                [ 'stat_val' => '32+', 'stat_lbl' => 'Références' ],
                [ 'stat_val' => '🇨🇲',  'stat_lbl' => 'Made in Cameroun' ],
            ],
            'title_field' => '{{{ stat_val }}} — {{{ stat_lbl }}}',
        ] );

        $this->end_controls_section();

        /* ── STYLE ── */
        $this->start_controls_section( 'section_style', [
            'label' => __( '🎨 Style', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        $this->add_control( 'couleur_accent', [
            'label'     => __( 'Couleur accent (rose)', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#e85499',
            'selectors' => [
                '{{WRAPPER}} .slpm-hero-badge-dot'  => 'background: {{VALUE}};',
                '{{WRAPPER}} .slpm-hero-badge-text' => 'color: {{VALUE}};',
                '{{WRAPPER}} .slpm-hero-badge'      => 'border-color: {{VALUE}}4d; background: {{VALUE}}1f;',
                '{{WRAPPER}} .slpm-hero-stat-val'   => 'color: #ffd200;',
            ],
        ] );

        $this->end_controls_section();
    }

    protected function render() {
        $s     = $this->get_settings_for_display();
        $stats = $s['stats'] ?? [];
        ?>
        <div class="slpm-page">
        <section class="slpm-hero">
          <div class="slpm-hero-grid-bg"></div>
          <div class="slpm-pw" style="position:relative;z-index:2">

            <div class="slpm-hero-badge">
              <span class="slpm-hero-badge-dot"></span>
              <span class="slpm-hero-badge-text"><?php echo esc_html( $s['badge_texte'] ); ?></span>
            </div>

            <h1 class="slpm-hero-titre">
              <span><?php echo esc_html( $s['titre'] ); ?></span>
            </h1>

            <p class="slpm-hero-desc"><?php echo esc_html( $s['description'] ); ?></p>

            <?php if ( $stats ) : ?>
            <div class="slpm-hero-stats">
              <?php foreach ( $stats as $i => $stat ) : ?>
              <div class="slpm-hero-stat<?php echo $i > 0 ? '' : ''; ?>">
                <strong class="slpm-hero-stat-val"><?php echo esc_html( $stat['stat_val'] ); ?></strong>
                <span class="slpm-hero-stat-lbl"><?php echo esc_html( $stat['stat_lbl'] ); ?></span>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>

          </div>
        </section>
        </div>
        <?php
    }
}
