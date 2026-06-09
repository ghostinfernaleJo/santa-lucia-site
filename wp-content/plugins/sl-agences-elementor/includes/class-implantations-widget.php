<?php
/**
 * Widget Elementor "Implantations" — Santa Lucia S7
 *
 * 2 colonnes : Yaoundé (10 agences) + Douala (8 agences).
 * Chaque ville : en-tête dark + liste avec points roses + badges optionnels.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

class SL_Implantations_Widget extends Widget_Base {

    public function get_name()       { return 'sl_implantations'; }
    public function get_title()      { return __( 'Implantations', 'sl-agences' ); }
    public function get_icon()       { return 'eicon-map-pin'; }
    public function get_categories() { return [ 'santa-lucia' ]; }
    public function get_keywords()   { return [ 'implantations', 'agences', 'villes', 'carte', 'apropos', 'santa lucia' ]; }

    public function get_style_depends()        { return [ 'sl-implantations' ]; }
    public function has_widget_inner_wrapper(): bool { return false; }

    /* ── Helper : contrôles d'une ville ── */
    private function add_city_controls( string $prefix, string $ville_def, string $count_def, array $agences_def ): void {

        $this->add_control( $prefix . '_ville', [
            'label'   => __( 'Nom de la ville', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => $ville_def,
        ] );

        $this->add_control( $prefix . '_count', [
            'label'   => __( 'Nombre d\'agences', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => $count_def,
        ] );

        $rep = new Repeater();
        $rep->add_control( 'nom', [
            'label'   => __( 'Nom de l\'agence', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => '',
        ] );
        $rep->add_control( 'badge', [
            'label'   => __( 'Badge (optionnel)', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => '',
        ] );
        $rep->add_control( 'badge_style', [
            'label'   => __( 'Style badge', 'sl-agences' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'rose',
            'options' => [
                'rose'  => 'Rose (Direction)',
                'gris'  => 'Gris (Info)',
            ],
        ] );

        $this->add_control( $prefix . '_agences', [
            'label'       => __( 'Agences', 'sl-agences' ),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $rep->get_controls(),
            'default'     => $agences_def,
            'title_field' => '{{{ nom }}}',
        ] );
    }

    protected function register_controls() {

        /* ── EN-TÊTE ── */
        $this->start_controls_section( 'section_header', [
            'label' => __( '✏️ En-tête', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'label', [
            'label'   => __( 'Label', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => 'Nos implantations',
        ] );
        $this->add_control( 'titre_ligne1', [
            'label'       => __( 'Titre — Ligne 1', 'sl-agences' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => 'Toujours près de',
            'label_block' => true,
        ] );
        $this->add_control( 'titre_ligne2', [
            'label'       => __( 'Titre — Ligne 2 (autorise <em>mot</em> en rose)', 'sl-agences' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => '<em>chez vous.</em>',
            'label_block' => true,
        ] );
        $this->add_control( 'description', [
            'label'   => __( 'Description', 'sl-agences' ),
            'type'    => Controls_Manager::TEXTAREA,
            'default' => '18 agences réparties à Douala et Yaoundé pour être au plus proche de chaque famille.',
            'rows'    => 2,
        ] );

        $this->end_controls_section();

        /* ── VILLE 1 (Yaoundé) ── */
        $this->start_controls_section( 'section_ville1', [
            'label' => __( '📍 Ville 1 — Yaoundé', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );
        $this->add_city_controls( 'v1', 'Yaoundé', '10 agences', [
            [ 'nom' => 'Mokolo',     'badge' => 'Direction Générale', 'badge_style' => 'rose' ],
            [ 'nom' => 'Kondengui',  'badge' => 'Site historique 2006', 'badge_style' => 'gris' ],
            [ 'nom' => 'Ngousso',    'badge' => '', 'badge_style' => 'rose' ],
            [ 'nom' => 'Nkoabang',   'badge' => '', 'badge_style' => 'rose' ],
            [ 'nom' => 'Mélen',      'badge' => '', 'badge_style' => 'rose' ],
            [ 'nom' => 'Essos',      'badge' => '', 'badge_style' => 'rose' ],
            [ 'nom' => 'Ahala',      'badge' => '', 'badge_style' => 'rose' ],
            [ 'nom' => 'Odza',       'badge' => '', 'badge_style' => 'rose' ],
            [ 'nom' => 'Mvan',       'badge' => '', 'badge_style' => 'rose' ],
            [ 'nom' => 'Simbock',    'badge' => '', 'badge_style' => 'rose' ],
        ] );
        $this->end_controls_section();

        /* ── VILLE 2 (Douala) ── */
        $this->start_controls_section( 'section_ville2', [
            'label' => __( '📍 Ville 2 — Douala', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );
        $this->add_city_controls( 'v2', 'Douala', '8 agences', [
            [ 'nom' => 'Akwa Nord',         'badge' => 'Direction Régionale', 'badge_style' => 'rose' ],
            [ 'nom' => 'Bonabéri',          'badge' => '', 'badge_style' => 'rose' ],
            [ 'nom' => 'Bonamoussadi',      'badge' => '', 'badge_style' => 'rose' ],
            [ 'nom' => 'Nkolbong',          'badge' => '', 'badge_style' => 'rose' ],
            [ 'nom' => 'Cité des Palmiers', 'badge' => '', 'badge_style' => 'rose' ],
            [ 'nom' => 'Cité Cicam',        'badge' => '', 'badge_style' => 'rose' ],
            [ 'nom' => 'Akwa',              'badge' => '', 'badge_style' => 'rose' ],
            [ 'nom' => 'Bercy',             'badge' => '', 'badge_style' => 'rose' ],
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
            'default'   => '#ffffff',
            'selectors' => [ '{{WRAPPER}} .slimp-section' => 'background: {{VALUE}};' ],
        ] );

        $this->add_control( 'couleur_accent', [
            'label'     => __( 'Couleur accent (rose)', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#e85499',
            'selectors' => [
                '{{WRAPPER}} .slimp-label span'     => 'color: {{VALUE}};',
                '{{WRAPPER}} .slimp-label::after'   => 'background: {{VALUE}}26;',
                '{{WRAPPER}} .slimp-titre em'       => 'color: {{VALUE}};',
                '{{WRAPPER}} .slimp-dot'            => 'background: {{VALUE}};',
                '{{WRAPPER}} .slimp-badge-rose'     => 'color: {{VALUE}};',
                '{{WRAPPER}} .slimp-count-badge'    => 'color: {{VALUE}}; border-color: {{VALUE}}66;',
                '{{WRAPPER}} .slimp-card'           => 'border-color: {{VALUE}}26;',
                '{{WRAPPER}} .slimp-li'             => 'border-bottom-color: {{VALUE}}14;',
            ],
        ] );

        $this->add_control( 'couleur_header_ville', [
            'label'     => __( 'Fond en-tête ville', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#030712',
            'selectors' => [ '{{WRAPPER}} .slimp-city-head' => 'background: {{VALUE}};' ],
        ] );

        $this->end_controls_section();
    }

    private function render_city( string $prefix ): string {
        $s     = $this->get_settings_for_display();
        $ville  = $s[ $prefix . '_ville' ] ?? '';
        $count  = $s[ $prefix . '_count' ] ?? '';
        $agences = $s[ $prefix . '_agences' ] ?? [];

        $lis = '';
        $total = count( $agences );
        foreach ( $agences as $i => $ag ) {
            $is_last = ( $i === $total - 1 );
            $badge_html = '';
            if ( $ag['badge'] ) {
                $cls = ( $ag['badge_style'] === 'rose' ) ? 'slimp-badge-rose' : 'slimp-badge-gris';
                $badge_html = '<span class="slimp-badge ' . $cls . '">' . esc_html( $ag['badge'] ) . '</span>';
            }
            $lis .= '<li class="slimp-li' . ( $is_last ? ' slimp-li-last' : '' ) . '">'
                  . '<span class="slimp-dot"></span>'
                  . '<span class="slimp-ag-nom">' . esc_html( $ag['nom'] ) . '</span>'
                  . $badge_html
                  . '</li>';
        }

        return '<div class="slimp-card">'
             . '<div class="slimp-city-head">'
             .   '<h3 class="slimp-city-nom">' . esc_html( $ville ) . '</h3>'
             .   '<span class="slimp-count-badge">' . esc_html( $count ) . '</span>'
             . '</div>'
             . '<ul class="slimp-list">' . $lis . '</ul>'
             . '</div>';
    }

    protected function render() {
        $s  = $this->get_settings_for_display();
        $t2 = wp_kses( $s['titre_ligne2'], [ 'em' => [] ] );
        ?>
        <section class="slimp-section ap">
          <div class="apw">
            <div class="slimp-label ap-lbl"><span><?php echo esc_html( $s['label'] ); ?></span></div>
            <h2 class="slimp-titre ap-titre">
              <span><?php echo esc_html( $s['titre_ligne1'] ); ?></span>
              <span><?php echo $t2; ?></span>
            </h2>
            <p class="ap-corps slimp-desc"><?php echo esc_html( $s['description'] ); ?></p>
            <div class="slimp-grid">
              <?php echo $this->render_city( 'v1' ); ?>
              <?php echo $this->render_city( 'v2' ); ?>
            </div>
          </div>
        </section>
        <?php
    }
}
