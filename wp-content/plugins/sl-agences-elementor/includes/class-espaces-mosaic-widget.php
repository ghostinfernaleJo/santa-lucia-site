<?php
/**
 * Widget Elementor "Espaces Mosaïque" — Santa Lucia S5
 *
 * En-tête : label · H2 · desc (gauche) + bouton (droite).
 * Mosaïque 5 photos : grande à gauche (2 rangées) + 4 en 2×2.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

class SL_Espaces_Mosaic_Widget extends Widget_Base {

    public function get_name()       { return 'sl_espaces_mosaic'; }
    public function get_title()      { return __( 'Espaces Mosaïque', 'sl-agences' ); }
    public function get_icon()       { return 'eicon-gallery-grid'; }
    public function get_categories() { return [ 'santa-lucia' ]; }
    public function get_keywords()   { return [ 'espaces', 'mosaique', 'galerie', 'photos', 'apropos', 'santa lucia' ]; }

    public function get_style_depends()        { return [ 'sl-espaces-mosaic' ]; }
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
            'default' => 'Nos espaces',
        ] );

        $this->add_control( 'titre_ligne1', [
            'label'       => __( 'Titre — Ligne 1', 'sl-agences' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => 'Une ambiance chaleureuse',
            'label_block' => true,
        ] );

        $this->add_control( 'titre_ligne2', [
            'label'       => __( 'Titre — Ligne 2 (autorise <em>mot</em> en rose)', 'sl-agences' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => '<em>à chaque visite.</em>',
            'label_block' => true,
        ] );

        $this->add_control( 'description', [
            'label'   => __( 'Description', 'sl-agences' ),
            'type'    => Controls_Manager::TEXTAREA,
            'default' => 'De l\'entrée du magasin au rayon boulangerie, chaque espace Santa Lucia est pensé pour votre confort et votre plaisir.',
            'rows'    => 3,
        ] );

        $this->end_controls_section();

        /* ── BOUTON ── */
        $this->start_controls_section( 'section_btn', [
            'label' => __( '🔗 Bouton', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'btn_texte', [
            'label'   => __( 'Texte du bouton', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => 'Découvrir nos espaces',
        ] );

        $this->add_control( 'btn_url', [
            'label'   => __( 'Lien', 'sl-agences' ),
            'type'    => Controls_Manager::URL,
            'default' => [ 'url' => '/nos-espaces/' ],
        ] );

        $this->end_controls_section();

        /* ── PHOTOS ── */
        $this->start_controls_section( 'section_photos', [
            'label' => __( '🖼️ Photos (5 recommandées)', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $rep = new Repeater();

        $rep->add_control( 'image', [
            'label' => __( 'Photo', 'sl-agences' ),
            'type'  => Controls_Manager::MEDIA,
        ] );

        $rep->add_control( 'legende', [
            'label'   => __( 'Légende', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => '',
        ] );

        $rep->add_control( 'bg_size', [
            'label'   => __( 'Taille de l\'image', 'sl-agences' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'cover',
            'options' => [
                'cover'   => 'Cover (remplit la cellule)',
                'contain' => 'Contain (image entière visible)',
                'auto'    => 'Auto (taille naturelle)',
                '50%'     => '50%',
                '75%'     => '75%',
                '100%'    => '100%',
            ],
        ] );

        $rep->add_control( 'bg_position', [
            'label'   => __( 'Position', 'sl-agences' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'center center',
            'options' => [
                'center center' => 'Centre',
                'top center'    => 'Haut — Centre',
                'top left'      => 'Haut — Gauche',
                'top right'     => 'Haut — Droite',
                'center left'   => 'Milieu — Gauche',
                'center right'  => 'Milieu — Droite',
                'bottom center' => 'Bas — Centre',
                'bottom left'   => 'Bas — Gauche',
                'bottom right'  => 'Bas — Droite',
            ],
        ] );

        $rep->add_control( 'bg_repeat', [
            'label'   => __( 'Répétition', 'sl-agences' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'no-repeat',
            'options' => [
                'no-repeat' => 'Aucune répétition',
                'repeat'    => 'Répéter (X & Y)',
                'repeat-x'  => 'Répéter horizontalement',
                'repeat-y'  => 'Répéter verticalement',
            ],
        ] );

        $rep->add_control( 'bg_attachment', [
            'label'   => __( 'Comportement au scroll', 'sl-agences' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'scroll',
            'options' => [
                'scroll' => 'Scroll (normal)',
                'fixed'  => 'Fixed (parallax)',
            ],
        ] );

        $rep->add_control( 'bg_overlay_opacite', [
            'label'      => __( 'Opacité de l\'overlay sombre (%)', 'sl-agences' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ '%' ],
            'range'      => [ '%' => [ 'min' => 0, 'max' => 100 ] ],
            'default'    => [ 'size' => 72, 'unit' => '%' ],
        ] );

        $rep->add_control( 'bg_overlay_couleur', [
            'label'   => __( 'Couleur de l\'overlay', 'sl-agences' ),
            'type'    => Controls_Manager::COLOR,
            'default' => '#030712',
        ] );

        $this->add_control( 'photos', [
            'label'       => __( 'Photos', 'sl-agences' ),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $rep->get_controls(),
            'default'     => [
                [ 'legende' => 'Agence Bonabéri' ],
                [ 'legende' => 'Bonamoussadi' ],
                [ 'legende' => 'Cité des Palmiers' ],
                [ 'legende' => 'Boulangerie' ],
                [ 'legende' => 'Restauration' ],
            ],
            'title_field' => '{{{ legende }}}',
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
                '{{WRAPPER}} .slem-label span'    => 'color: {{VALUE}};',
                '{{WRAPPER}} .slem-label::after'  => 'background: {{VALUE}}26;',
                '{{WRAPPER}} .slem-titre em'      => 'color: {{VALUE}};',
                '{{WRAPPER}} .slem-btn'           => 'border-color: {{VALUE}}; color: {{VALUE}};',
                '{{WRAPPER}} .slem-btn:hover'     => 'background: {{VALUE}}; color: #fff;',
            ],
        ] );

        $this->end_controls_section();
    }

    protected function render() {
        $s      = $this->get_settings_for_display();
        $t2     = wp_kses( $s['titre_ligne2'], [ 'em' => [] ] );
        $photos = array_values( $s['photos'] ?? [] );
        $btn_url = $s['btn_url']['url'] ?? '#';

        $mosaic = '';
        foreach ( $photos as $i => $p ) {
            $img_url       = $p['image']['url'] ?? '';
            $leg           = esc_html( $p['legende'] ?? '' );
            $bg_size       = ! empty( $p['bg_size'] )       ? $p['bg_size']       : 'cover';
            $bg_position   = ! empty( $p['bg_position'] )   ? $p['bg_position']   : 'center center';
            $bg_repeat     = ! empty( $p['bg_repeat'] )     ? $p['bg_repeat']     : 'no-repeat';
            $bg_attachment = ! empty( $p['bg_attachment'] ) ? $p['bg_attachment'] : 'scroll';
            $ov_opacity    = isset( $p['bg_overlay_opacite']['size'] ) ? (float) $p['bg_overlay_opacite']['size'] / 100 : 0.72;
            $ov_hex        = ltrim( ! empty( $p['bg_overlay_couleur'] ) ? $p['bg_overlay_couleur'] : '#030712', '#' );
            if ( strlen( $ov_hex ) === 3 ) {
                $ov_hex = $ov_hex[0].$ov_hex[0].$ov_hex[1].$ov_hex[1].$ov_hex[2].$ov_hex[2];
            }
            $ov_r   = hexdec( substr( $ov_hex, 0, 2 ) );
            $ov_g   = hexdec( substr( $ov_hex, 2, 2 ) );
            $ov_b   = hexdec( substr( $ov_hex, 4, 2 ) );
            $ov_bot = "rgba({$ov_r},{$ov_g},{$ov_b},{$ov_opacity})";
            $ov_mid = 'rgba(' . $ov_r . ',' . $ov_g . ',' . $ov_b . ',' . round( $ov_opacity * 0.45, 3 ) . ')';
            $ov_top = 'rgba(' . $ov_r . ',' . $ov_g . ',' . $ov_b . ',' . round( $ov_opacity * 0.08, 3 ) . ')';
            $overlay_style = 'background:linear-gradient(to top,' . $ov_bot . ' 0%,' . $ov_mid . ' 45%,' . $ov_top . ' 100%)';

            $span_style = 'grid-row:' . ( $i === 0 ? '1/3' : 'auto' );
            $bg_style   = '';
            if ( $img_url ) {
                $bg_style = 'background-image:url(' . esc_url( $img_url ) . ');'
                    . 'background-size:' . esc_attr( $bg_size ) . ';'
                    . 'background-position:' . esc_attr( $bg_position ) . ';'
                    . 'background-repeat:' . esc_attr( $bg_repeat ) . ';'
                    . 'background-attachment:' . esc_attr( $bg_attachment ) . ';';
            }
            $mosaic .= '<div class="slem-cell" style="' . $span_style . ';' . $bg_style . '">';
            $mosaic .= '<div class="slem-overlay" style="' . esc_attr( $overlay_style ) . '"></div>';
            if ( $leg ) $mosaic .= '<div class="slem-lbl"><span>' . $leg . '</span></div>';
            $mosaic .= '</div>';
        }
        ?>
        <section class="slem-section ap">
          <div class="apw">

            <!-- En-tête -->
            <div class="slem-header">
              <div class="slem-header-left">
                <div class="slem-label ap-lbl"><span><?php echo esc_html( $s['label'] ); ?></span></div>
                <h2 class="slem-titre ap-titre">
                  <span><?php echo esc_html( $s['titre_ligne1'] ); ?></span>
                  <span><?php echo $t2; ?></span>
                </h2>
                <p class="ap-corps"><?php echo esc_html( $s['description'] ); ?></p>
              </div>
              <?php if ( $s['btn_texte'] && $btn_url ) : ?>
              <a href="<?php echo esc_url( $btn_url ); ?>" class="slem-btn">
                <?php echo esc_html( $s['btn_texte'] ); ?>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                </svg>
              </a>
              <?php endif; ?>
            </div>

            <!-- Mosaïque -->
            <?php if ( $mosaic ) : ?>
            <div class="slem-mosaic">
              <?php echo $mosaic; ?>
            </div>
            <?php endif; ?>

          </div>
        </section>
        <?php
    }
}
