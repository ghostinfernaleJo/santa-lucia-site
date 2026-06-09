<?php
/**
 * Widget Elementor "Grille Découverte" — Santa Lucia
 * 7 cartes hover en CSS Grid 3 rangées : (2fr/1fr) → (1fr×3) → (1fr/2fr)
 * Fidèle au design original gen-espaces-v6 : overlay, badge photos, tag couleur, CTA animé.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

class SL_Grille_Decouverte_Widget extends Widget_Base {

    public function get_name()       { return 'sl_grille_decouverte'; }
    public function get_title()      { return __( 'Grille Découverte', 'sl-agences' ); }
    public function get_icon()       { return 'eicon-gallery-grid'; }
    public function get_categories() { return [ 'santa-lucia' ]; }
    public function get_keywords()   { return [ 'grille', 'decouverte', 'espaces', 'cartes', 'grid', 'santa lucia' ]; }

    public function get_style_depends()        { return [ 'sl-grille-decouverte' ]; }
    public function has_widget_inner_wrapper(): bool { return false; }

    protected function register_controls() {

        /* ── EN-TÊTE ─────────────────────────────────────────────── */
        $this->start_controls_section( 'section_header', [
            'label' => __( '📋 En-tête', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'label_header', [
            'label'   => __( 'Label (gauche)', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => 'Choisissez un espace',
        ] );

        $this->add_control( 'label_compte', [
            'label'   => __( 'Sous-label (droite)', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => '7 espaces · Cliquez pour voir la galerie',
        ] );

        $this->end_controls_section();

        /* ── CARTES (REPEATER) ───────────────────────────────────── */
        $this->start_controls_section( 'section_cartes', [
            'label' => __( '🃏 Cartes (7 max recommandées)', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $repeater = new Repeater();

        $repeater->add_control( 'carte_image', [
            'label'   => __( 'Image de fond', 'sl-agences' ),
            'type'    => Controls_Manager::MEDIA,
            'default' => [ 'url' => '' ],
        ] );

        $repeater->add_control( 'carte_tag', [
            'label'   => __( 'Tag / Catégorie', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => 'Loisirs',
        ] );

        $repeater->add_control( 'carte_accent', [
            'label'   => __( 'Couleur accent', 'sl-agences' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'rose',
            'options' => [
                'rose' => '🌸 Rose (#e85499)',
                'bleu' => '🔵 Bleu (#3b549f)',
            ],
        ] );

        $repeater->add_control( 'carte_titre', [
            'label'       => __( 'Titre de la carte', 'sl-agences' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => 'Espace de Jeux',
            'label_block' => true,
        ] );

        $repeater->add_control( 'carte_anchor', [
            'label'       => __( 'Lien / Ancre', 'sl-agences' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => '#espace-jeux',
            'description' => __( 'Ex : #espace-jeux ou /une-page/', 'sl-agences' ),
        ] );

        $repeater->add_control( 'carte_badge', [
            'label'       => __( 'Badge photos (coin droit)', 'sl-agences' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => '6 photos',
            'description' => __( 'Affiché en haut à droite. Ex : "6 photos"', 'sl-agences' ),
        ] );

        $this->add_control( 'cartes', [
            'label'       => __( 'Cartes', 'sl-agences' ),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $repeater->get_controls(),
            'default'     => [
                [ 'carte_image' => [ 'url' => '' ], 'carte_tag' => 'Loisirs',           'carte_accent' => 'rose', 'carte_titre' => 'Espace de Jeux',   'carte_anchor' => '#espace-jeux',    'carte_badge' => '6 photos' ],
                [ 'carte_image' => [ 'url' => '' ], 'carte_tag' => 'Réalité Virtuelle', 'carte_accent' => 'bleu', 'carte_titre' => 'Omitland VR',       'carte_anchor' => '#omitland',       'carte_badge' => '4 photos' ],
                [ 'carte_image' => [ 'url' => '' ], 'carte_tag' => 'Viandes',           'carte_accent' => 'rose', 'carte_titre' => 'Boucherie',          'carte_anchor' => '#boucherie',      'carte_badge' => '4 photos' ],
                [ 'carte_image' => [ 'url' => '' ], 'carte_tag' => 'Restauration',      'carte_accent' => 'rose', 'carte_titre' => 'Fast Food',          'carte_anchor' => '#fast-food',      'carte_badge' => '5 photos' ],
                [ 'carte_image' => [ 'url' => '' ], 'carte_tag' => 'Identité visuelle', 'carte_accent' => 'rose', 'carte_titre' => 'Façades',            'carte_anchor' => '#facades',        'carte_badge' => '4 photos' ],
                [ 'carte_image' => [ 'url' => '' ], 'carte_tag' => 'Espace urbain',     'carte_accent' => 'bleu', 'carte_titre' => 'Bancs publics',      'carte_anchor' => '#bancs-publics',  'carte_badge' => '3 photos' ],
                [ 'carte_image' => [ 'url' => '' ], 'carte_tag' => 'Plein air',         'carte_accent' => 'rose', 'carte_titre' => 'Espaces Dehors',     'carte_anchor' => '#espaces-dehors', 'carte_badge' => '5 photos' ],
            ],
            'title_field' => '{{{ carte_titre }}}',
        ] );

        $this->end_controls_section();

        /* ── STYLE ───────────────────────────────────────────────── */
        $this->start_controls_section( 'section_style', [
            'label' => __( '🎨 Style', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        $this->add_control( 'couleur_accent', [
            'label'     => __( 'Couleur accent rose', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#e85499',
            'selectors' => [
                '{{WRAPPER}} .slgd-header-label' => 'color: {{VALUE}};',
                '{{WRAPPER}} .slgd-header-line'  => 'background: {{VALUE}}26;',
                '{{WRAPPER}} .slgd-card:hover .slgd-bdn' => 'background: {{VALUE}}; border-color: {{VALUE}};',
            ],
        ] );

        $this->add_control( 'fond_section', [
            'label'     => __( 'Fond de la section', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => [
                '{{WRAPPER}} .slgd-wrap' => 'background: {{VALUE}};',
            ],
        ] );

        $this->add_control( 'padding_section', [
            'label'      => __( 'Padding vertical (px)', 'sl-agences' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px' ],
            'range'      => [ 'px' => [ 'min' => 0, 'max' => 160 ] ],
            'default'    => [ 'unit' => 'px', 'size' => 72 ],
            'selectors'  => [
                '{{WRAPPER}} .slgd-wrap' => 'padding-top: {{SIZE}}{{UNIT}}; padding-bottom: {{SIZE}}{{UNIT}};',
            ],
        ] );

        $this->end_controls_section();
    }

    /* ── Rendu d'une carte ─────────────────────────────────────── */
    private function render_card( array $c ): string {
        $img_url = $c['carte_image']['url'] ?? '';
        $accent  = ( $c['carte_accent'] === 'bleu' ) ? 'accent-bleu' : '';
        $href    = esc_url( $c['carte_anchor'] );
        $badge   = esc_html( $c['carte_badge'] );
        $tag     = esc_html( $c['carte_tag'] );
        $titre   = esc_html( $c['carte_titre'] );

        $img_style = $img_url
            ? 'style="background-image:url(' . esc_url( $img_url ) . ');background-size:cover;background-position:center"'
            : '';

        /* SVG flèche réutilisé */
        $arrow = '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">'
               . '<line x1="5" y1="12" x2="19" y2="12"/>'
               . '<polyline points="12 5 19 12 12 19"/>'
               . '</svg>';

        return '<a href="' . $href . '" class="slgd-card">'
             . '<img src="' . esc_url( $img_url ) . '" alt="' . $titre . '" loading="lazy">'
             . '<div class="slgd-ov"></div>'
             . ( $badge ? '<div class="slgd-bdn">' . $badge . '</div>' : '' )
             . '<div class="slgd-bt">'
             .   '<div class="slgd-tag ' . $accent . '">' . $tag . '</div>'
             .   '<div class="slgd-tt">' . $titre . '</div>'
             .   '<div class="slgd-ct ' . $accent . '">Voir ' . $arrow . '</div>'
             . '</div>'
             . '</a>';
    }

    protected function render() {
        $s      = $this->get_settings_for_display();
        $cartes = $s['cartes'] ?? [];
        $total  = count( $cartes );

        /* Répartition des cartes dans les 3 rangées */
        $r1 = array_slice( $cartes, 0, 2 );          // 2 cartes : 2fr / 1fr
        $r2 = array_slice( $cartes, 2, 3 );          // 3 cartes : 1fr × 3
        $r3 = array_slice( $cartes, 5 );             // reste    : 1fr / 2fr
        ?>
        <div class="slgd-wrap">
          <div class="slgd-inner">

            <!-- En-tête -->
            <div class="slgd-header">
              <span class="slgd-header-label"><?php echo esc_html( $s['label_header'] ); ?></span>
              <div class="slgd-header-line"></div>
              <span class="slgd-header-count"><?php echo esc_html( $s['label_compte'] ); ?></span>
            </div>

            <!-- Rangée 1 : 2fr / 1fr -->
            <?php if ( ! empty( $r1 ) ) : ?>
            <div class="slgd-r1">
              <?php foreach ( $r1 as $c ) echo $this->render_card( $c ); ?>
            </div>
            <?php endif; ?>

            <!-- Rangée 2 : 1fr / 1fr / 1fr -->
            <?php if ( ! empty( $r2 ) ) : ?>
            <div class="slgd-r2">
              <?php foreach ( $r2 as $c ) echo $this->render_card( $c ); ?>
            </div>
            <?php endif; ?>

            <!-- Rangée 3 : 1fr / 2fr -->
            <?php if ( ! empty( $r3 ) ) : ?>
            <div class="slgd-r3">
              <?php foreach ( $r3 as $c ) echo $this->render_card( $c ); ?>
            </div>
            <?php endif; ?>

          </div>
        </div>
        <?php
    }
}
