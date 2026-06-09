<?php
/**
 * Widget Elementor "Engagements" — Santa Lucia S6
 *
 * Grille 2×2 : numéro large en fond + titre + description.
 * Fond #f8fafc, bordures roses.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

class SL_Engagements_Widget extends Widget_Base {

    public function get_name()       { return 'sl_engagements'; }
    public function get_title()      { return __( 'Engagements', 'sl-agences' ); }
    public function get_icon()       { return 'eicon-check-circle-o'; }
    public function get_categories() { return [ 'santa-lucia' ]; }
    public function get_keywords()   { return [ 'engagements', 'valeurs', 'grille', 'apropos', 'santa lucia' ]; }

    public function get_style_depends()        { return [ 'sl-engagements' ]; }
    public function has_widget_inner_wrapper(): bool { return false; }

    protected function register_controls() {

        /* ── EN-TÊTE ── */
        $this->start_controls_section( 'section_header', [
            'label' => __( '✏️ En-tête', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'label', [
            'label'   => __( 'Label', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => 'Nos engagements',
        ] );

        $this->add_control( 'titre_ligne1', [
            'label'       => __( 'Titre — Ligne 1', 'sl-agences' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => 'Des valeurs au service',
            'label_block' => true,
        ] );

        $this->add_control( 'titre_ligne2', [
            'label'       => __( 'Titre — Ligne 2 (autorise <em>mot</em> en rose)', 'sl-agences' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => 'de <em>chaque client.</em>',
            'label_block' => true,
        ] );

        $this->end_controls_section();

        /* ── ENGAGEMENTS ── */
        $this->start_controls_section( 'section_items', [
            'label' => __( '📋 Engagements', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $rep = new Repeater();

        $rep->add_control( 'numero', [
            'label'   => __( 'Numéro (01, 02…)', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => '01',
        ] );

        $rep->add_control( 'titre_item', [
            'label'       => __( 'Titre', 'sl-agences' ),
            'type'        => Controls_Manager::TEXT,
            'label_block' => true,
            'default'     => '',
        ] );

        $rep->add_control( 'desc_item', [
            'label'   => __( 'Description', 'sl-agences' ),
            'type'    => Controls_Manager::TEXTAREA,
            'rows'    => 3,
            'default' => '',
        ] );

        $this->add_control( 'items', [
            'label'       => __( 'Engagements', 'sl-agences' ),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $rep->get_controls(),
            'default'     => [
                [ 'numero' => '01', 'titre_item' => 'Des prix accessibles à tous',           'desc_item' => 'Chez Santa Lucia, le budget ne doit jamais être un obstacle à la qualité. Nos promotions permanentes garantissent des prix bas toute l\'année.' ],
                [ 'numero' => '02', 'titre_item' => 'Produits frais & locaux',               'desc_item' => 'Nous privilégions les producteurs camerounais, garantissant fraîcheur et soutien à l\'économie locale.' ],
                [ 'numero' => '03', 'titre_item' => 'Disponibilité 24h/24, 7j/7',            'desc_item' => 'Toutes nos portes restent ouvertes à toute heure, chaque jour de l\'année, sans exception.' ],
                [ 'numero' => '04', 'titre_item' => 'Recrutement éthique & transparent',     'desc_item' => 'Aucun frais demandé. Candidatures uniquement en présentiel à Mokolo et Akwa Nord.' ],
            ],
            'title_field' => '{{{ numero }}} — {{{ titre_item }}}',
        ] );

        $this->end_controls_section();

        /* ── STYLE ── */
        $this->start_controls_section( 'section_style', [
            'label' => __( '🎨 Style', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        $this->add_control( 'couleur_fond', [
            'label'     => __( 'Couleur de fond', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#f8fafc',
            'selectors' => [ '{{WRAPPER}} .sleng-section' => 'background: {{VALUE}};' ],
        ] );

        $this->add_control( 'couleur_accent', [
            'label'     => __( 'Couleur accent (rose)', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#e85499',
            'selectors' => [
                '{{WRAPPER}} .sleng-label span'    => 'color: {{VALUE}};',
                '{{WRAPPER}} .sleng-label::after'  => 'background: {{VALUE}}26;',
                '{{WRAPPER}} .sleng-titre em'      => 'color: {{VALUE}};',
                '{{WRAPPER}} .sleng-grid'          => 'border-color: {{VALUE}}1f;',
                '{{WRAPPER}} .sleng-item'          => 'border-color: {{VALUE}}1f;',
                '{{WRAPPER}} .sleng-num'           => 'color: {{VALUE}}1f;',
            ],
        ] );

        $this->end_controls_section();
    }

    protected function render() {
        $s    = $this->get_settings_for_display();
        $t2   = wp_kses( $s['titre_ligne2'], [ 'em' => [] ] );
        $items = '';
        foreach ( $s['items'] ?? [] as $item ) {
            $items .= '<div class="sleng-item">'
                . '<div class="sleng-num">' . esc_html( $item['numero'] ) . '</div>'
                . '<div>'
                .   '<h3 class="sleng-item-titre">' . esc_html( $item['titre_item'] ) . '</h3>'
                .   '<p class="sleng-item-desc">' . esc_html( $item['desc_item'] ) . '</p>'
                . '</div>'
                . '</div>';
        }
        ?>
        <section class="sleng-section ap">
          <div class="apw">
            <div class="sleng-label ap-lbl"><span><?php echo esc_html( $s['label'] ); ?></span></div>
            <h2 class="sleng-titre ap-titre">
              <span><?php echo esc_html( $s['titre_ligne1'] ); ?></span>
              <span><?php echo $t2; ?></span>
            </h2>
            <div class="sleng-grid">
              <?php echo $items; ?>
            </div>
          </div>
        </section>
        <?php
    }
}
