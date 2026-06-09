<?php
/**
 * Widget Elementor "Nav Produits" — Santa Lucia
 *
 * Barre de navigation sticky avec liens ancres vers chaque
 * catégorie de produits + compteur de références.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

class SL_Nav_Produits_Widget extends Widget_Base {

    public function get_name()       { return 'sl_nav_produits'; }
    public function get_title()      { return __( 'Nav Catégories Produits', 'sl-agences' ); }
    public function get_icon()       { return 'eicon-nav-menu'; }
    public function get_categories() { return [ 'santa-lucia' ]; }
    public function get_keywords()   { return [ 'nav', 'navigation', 'categories', 'produits', 'sticky', 'santa lucia' ]; }

    public function get_style_depends()        { return [ 'sl-produits-maison-page' ]; }
    public function get_script_depends()       { return [ 'sl-nav-produits' ]; }
    public function has_widget_inner_wrapper(): bool { return false; }

    protected function register_controls() {

        $this->start_controls_section( 'section_liens', [
            'label' => __( '🔗 Catégories', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $rep = new Repeater();

        $rep->add_control( 'label', [
            'label'   => __( 'Nom de la catégorie', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => 'Spaghettis',
        ] );

        $rep->add_control( 'ancre', [
            'label'       => __( 'Ancre (sans #)', 'sl-agences' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => 'spaghettis',
            'description' => __( 'Doit correspondre à l\'ancre de la section produits.', 'sl-agences' ),
        ] );

        $rep->add_control( 'count', [
            'label'   => __( 'Nombre de références', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => '4',
        ] );

        $this->add_control( 'categories', [
            'label'       => __( 'Catégories', 'sl-agences' ),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $rep->get_controls(),
            'default'     => [
                [ 'label' => 'Spaghettis',              'ancre' => 'spaghettis',              'count' => '4' ],
                [ 'label' => 'Farines',                 'ancre' => 'farines',                 'count' => '7' ],
                [ 'label' => 'Chips & Apéro',           'ancre' => 'chips-apero',             'count' => '8' ],
                [ 'label' => 'Glaces',                  'ancre' => 'glaces',                  'count' => '4' ],
                [ 'label' => 'Pâtes à tartiner Chocojoy','ancre' => 'pates-a-tartiner-chocojoy','count' => '7' ],
                [ 'label' => 'Autres',                  'ancre' => 'autres',                  'count' => '2' ],
            ],
            'title_field' => '{{{ label }}} ({{{ count }}})',
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
                '{{WRAPPER}} .slpm-nav-link:hover'    => 'color: {{VALUE}}; border-bottom-color: {{VALUE}};',
                '{{WRAPPER}} .slpm-nav-link.active'   => 'color: {{VALUE}}; border-bottom-color: {{VALUE}};',
                '{{WRAPPER}} .slpm-nav-count'         => 'background: {{VALUE}}1a; color: {{VALUE}};',
                '{{WRAPPER}} .slpm-nav'               => 'border-bottom-color: {{VALUE}}1f;',
            ],
        ] );

        $this->end_controls_section();
    }

    protected function render() {
        $s    = $this->get_settings_for_display();
        $cats = $s['categories'] ?? [];
        ?>
        <div class="slpm-page">
        <nav class="slpm-nav" aria-label="Catégories de produits">
          <div class="slpm-pw">
            <div class="slpm-nav-inner">
              <?php foreach ( $cats as $cat ) : ?>
              <a href="#<?php echo esc_attr( $cat['ancre'] ); ?>" class="slpm-nav-link">
                <?php echo esc_html( $cat['label'] ); ?>
                <?php if ( $cat['count'] ) : ?>
                <span class="slpm-nav-count"><?php echo esc_html( $cat['count'] ); ?></span>
                <?php endif; ?>
              </a>
              <?php endforeach; ?>
            </div>
          </div>
        </nav>
        </div>
        <?php
    }
}
