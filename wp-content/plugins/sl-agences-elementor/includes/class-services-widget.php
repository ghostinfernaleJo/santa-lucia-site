<?php
/**
 * Widget Elementor "Services" — Santa Lucia S3
 *
 * Grille 3×2 de cartes : emoji · titre · description · badge.
 * Fond clair (#f8fafc), séparateur rose.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

class SL_Services_Widget extends Widget_Base {

    public function get_name()       { return 'sl_services'; }
    public function get_title()      { return __( 'Services', 'sl-agences' ); }
    public function get_icon()       { return 'eicon-apps'; }
    public function get_categories() { return [ 'santa-lucia' ]; }
    public function get_keywords()   { return [ 'services', 'grille', 'cards', 'apropos', 'santa lucia' ]; }

    public function get_style_depends()        { return [ 'sl-services' ]; }
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
            'default' => 'Nos services',
        ] );

        $this->add_control( 'titre_ligne1', [
            'label'       => __( 'Titre — Ligne 1', 'sl-agences' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => 'Plus qu\'un supermarché :',
            'label_block' => true,
        ] );

        $this->add_control( 'titre_ligne2', [
            'label'       => __( 'Titre — Ligne 2 (autorise <em>mot</em> en rose)', 'sl-agences' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => '<em>un complexe de vie.</em>',
            'label_block' => true,
        ] );

        $this->add_control( 'description', [
            'label'   => __( 'Description', 'sl-agences' ),
            'type'    => Controls_Manager::TEXTAREA,
            'default' => 'Sous un même toit, Santa Lucia réunit tous les services du quotidien pour simplifier votre vie.',
            'rows'    => 3,
        ] );

        $this->end_controls_section();

        /* ── CARTES ── */
        $this->start_controls_section( 'section_cartes', [
            'label' => __( '🃏 Cartes de services', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $rep = new Repeater();

        $rep->add_control( 'emoji', [
            'label'   => __( 'Emoji', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => '🛒',
        ] );

        $rep->add_control( 'titre_card', [
            'label'   => __( 'Titre', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => 'Supermarché',
        ] );

        $rep->add_control( 'desc_card', [
            'label'   => __( 'Description', 'sl-agences' ),
            'type'    => Controls_Manager::TEXTAREA,
            'default' => '',
            'rows'    => 2,
        ] );

        $rep->add_control( 'badge', [
            'label'   => __( 'Badge', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => 'Ouvert 24h/24',
        ] );

        $this->add_control( 'cartes', [
            'label'       => __( 'Cartes', 'sl-agences' ),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $rep->get_controls(),
            'default'     => [
                [ 'emoji' => '🛒', 'titre_card' => 'Supermarché',           'desc_card' => 'Fruits, légumes, viandes, poissons, épicerie, droguerie, parfumerie, articles bébé.',                          'badge' => 'Ouvert 24h/24' ],
                [ 'emoji' => '🍞', 'titre_card' => 'Boulangerie & Pâtisserie','desc_card' => 'Pain artisanal cuit chaque jour, viennoiseries, gâteaux sur commande et service traiteur.',             'badge' => 'Fait maison' ],
                [ 'emoji' => '🍽️', 'titre_card' => 'Restauration',           'desc_card' => 'Rôtisserie, shawarma, fast-food, glacier artisanal et bar à jus naturels.',                               'badge' => 'Cuisine fraîche' ],
                [ 'emoji' => '🎠', 'titre_card' => 'Loisirs & Famille',       'desc_card' => 'Manèges sécurisés, espaces de détente et atmosphère conviviale.',                                          'badge' => 'Pour toute la famille' ],
                [ 'emoji' => '🏨', 'titre_card' => 'Hôtellerie',              'desc_card' => 'Chambres de standing pour voyageurs et hommes d\'affaires.',                                               'badge' => 'Standing & confort' ],
                [ 'emoji' => '🚚', 'titre_card' => 'Livraison à domicile',    'desc_card' => 'Commandez en ligne. Livraison partout à Douala et Yaoundé.',                                              'badge' => 'Express & fiable' ],
            ],
            'title_field' => '{{{ titre_card }}}',
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
            'selectors' => [ '{{WRAPPER}} .slsv-section' => 'background: {{VALUE}};' ],
        ] );

        $this->add_control( 'couleur_accent', [
            'label'     => __( 'Couleur accent (rose)', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#e85499',
            'selectors' => [
                '{{WRAPPER}} .slsv-label span'      => 'color: {{VALUE}};',
                '{{WRAPPER}} .slsv-label::after'    => 'background: {{VALUE}}26;',
                '{{WRAPPER}} .slsv-titre em'        => 'color: {{VALUE}};',
                '{{WRAPPER}} .slsv-badge'           => 'color: {{VALUE}}; border-bottom-color: {{VALUE}};',
                '{{WRAPPER}} .slsv-grid'            => 'background: {{VALUE}}1f; border-color: {{VALUE}}1f;',
            ],
        ] );

        $this->end_controls_section();
    }

    protected function render() {
        $s = $this->get_settings_for_display();
        $t2 = wp_kses( $s['titre_ligne2'], [ 'em' => [] ] );

        $cards = '';
        foreach ( $s['cartes'] ?? [] as $c ) {
            $cards .= '<div class="slsv-card">'
                . '<span class="slsv-emoji">' . esc_html( $c['emoji'] ) . '</span>'
                . '<h3 class="slsv-card-titre">' . esc_html( $c['titre_card'] ) . '</h3>'
                . '<p class="slsv-card-desc">' . esc_html( $c['desc_card'] ) . '</p>'
                . '<span class="slsv-badge">' . esc_html( $c['badge'] ) . '</span>'
                . '</div>';
        }
        ?>
        <section class="slsv-section ap">
          <div class="apw">
            <div class="slsv-label ap-lbl"><span><?php echo esc_html( $s['label'] ); ?></span></div>
            <h2 class="slsv-titre ap-titre">
              <span><?php echo esc_html( $s['titre_ligne1'] ); ?></span>
              <span><?php echo $t2; ?></span>
            </h2>
            <p class="ap-corps slsv-desc"><?php echo esc_html( $s['description'] ); ?></p>
            <div class="slsv-grid">
              <?php echo $cards; ?>
            </div>
          </div>
        </section>
        <?php
    }
}
