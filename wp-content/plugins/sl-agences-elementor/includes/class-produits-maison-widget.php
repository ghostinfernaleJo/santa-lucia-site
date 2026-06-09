<?php
/**
 * Widget Elementor "Produits Maison" — Santa Lucia S4
 *
 * Fond dark dégradé, grille éditoriale 3 rangées :
 *  Row 1 : 2fr + 1fr (cartes 0–1, h 460px)
 *  Row 2 : 3 colonnes égales (cartes 2–4, h 360px)
 *  Row 3 : pleine largeur (carte 5, h 220px)
 * + CTA standalone en bas.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

class SL_Produits_Maison_Widget extends Widget_Base {

    public function get_name()       { return 'sl_produits_maison'; }
    public function get_title()      { return __( 'Produits Maison', 'sl-agences' ); }
    public function get_icon()       { return 'eicon-product-images'; }
    public function get_categories() { return [ 'santa-lucia' ]; }
    public function get_keywords()   { return [ 'produits', 'maison', 'editorial', 'grille', 'santa lucia' ]; }

    public function get_style_depends()        { return [ 'sl-produits-maison' ]; }
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
            'default' => 'Nos produits maison',
        ] );

        $this->add_control( 'titre_ligne1', [
            'label'       => __( 'Titre — Ligne 1', 'sl-agences' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => 'Fabriqués au Cameroun,',
            'label_block' => true,
        ] );

        $this->add_control( 'titre_ligne2', [
            'label'       => __( 'Titre — Ligne 2 (autorise <em>mot</em> en rose)', 'sl-agences' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => 'pour <em>toutes les familles.</em>',
            'label_block' => true,
        ] );

        $this->add_control( 'description', [
            'label'   => __( 'Description', 'sl-agences' ),
            'type'    => Controls_Manager::TEXTAREA,
            'default' => 'Gammes développées localement avec des matières premières camerounaises. Même qualité, prix accessibles à tous.',
            'rows'    => 2,
        ] );

        $this->add_control( 'nb_categories', [
            'label'   => __( 'Nombre de catégories (grand chiffre)', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => '6',
        ] );

        $this->end_controls_section();

        /* ── CARTES PRODUITS ── */
        $this->start_controls_section( 'section_cartes', [
            'label' => __( '🃏 Cartes produits (max 6)', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $rep = new Repeater();

        $rep->add_control( 'image', [
            'label' => __( 'Image de fond', 'sl-agences' ),
            'type'  => Controls_Manager::MEDIA,
        ] );

        $rep->add_control( 'bg_size', [
            'label'   => __( 'Taille de l\'image', 'sl-agences' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'cover',
            'options' => [
                'cover'   => 'Cover (remplit la carte)',
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

        $rep->add_control( 'emoji', [
            'label'   => __( 'Emoji', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => '🍝',
        ] );

        $rep->add_control( 'nom', [
            'label'   => __( 'Nom', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => 'Spaghettis',
        ] );

        $rep->add_control( 'references', [
            'label'   => __( 'Nombre de références', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => '4',
        ] );

        $rep->add_control( 'desc_card', [
            'label'   => __( 'Description', 'sl-agences' ),
            'type'    => Controls_Manager::TEXTAREA,
            'default' => '',
            'rows'    => 2,
        ] );

        $rep->add_control( 'url', [
            'label'   => __( 'Lien', 'sl-agences' ),
            'type'    => Controls_Manager::URL,
            'default' => [ 'url' => '' ],
        ] );

        $rep->add_control( 'accent', [
            'label'   => __( 'Couleur accent', 'sl-agences' ),
            'type'    => Controls_Manager::COLOR,
            'default' => '#e85499',
        ] );

        $this->add_control( 'cartes', [
            'label'       => __( 'Produits', 'sl-agences' ),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $rep->get_controls(),
            'default'     => [
                [ 'emoji' => '🍝', 'nom' => 'Spaghettis',      'references' => '4', 'desc_card' => 'Nos pâtes Santa Lucia et Omit, fabriquées à partir de semoule de blé de qualité supérieure.',                                   'accent' => '#e85499' ],
                [ 'emoji' => '🍟', 'nom' => 'Chips & Apéro',   'references' => '8', 'desc_card' => 'Apéro, Fiesta (banane, pommes) et Pop Corn — vos moments de détente à prix local.',                                             'accent' => '#ffd200' ],
                [ 'emoji' => '🌾', 'nom' => 'Farines',          'references' => '7', 'desc_card' => 'Farine Amira, La Fleur Blanche, Mami Lou — de 1kg à 25kg pour les boulangers et les familles.',                                'accent' => '#e85499' ],
                [ 'emoji' => '🍦', 'nom' => 'Glaces',           'references' => '4', 'desc_card' => 'La Fiesta Coconut, Vanille, Choco, Fraise — de vraies glaces artisanales pour une pause fraîche.',                            'accent' => '#ffd200' ],
                [ 'emoji' => '🍫', 'nom' => 'Chocojoy',         'references' => '7', 'desc_card' => 'La pâte à tartiner made in Cameroun — de 200g jusqu\'à 10kg pour les familles et les professionnels.',                         'accent' => '#e85499' ],
                [ 'emoji' => '☕', 'nom' => 'Autres produits',  'references' => '2', 'desc_card' => 'Déjeuner Lacté Chocojoy 20g & 35g — une pause matinée saine et savoureuse pour toute la famille.',                            'accent' => '#ffd200' ],
            ],
            'title_field' => '{{{ nom }}}',
        ] );

        $this->end_controls_section();

        /* ── CTA ── */
        $this->start_controls_section( 'section_cta', [
            'label' => __( '🔗 Bouton CTA', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'cta_texte', [
            'label'   => __( 'Texte du bouton', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => 'Voir tous nos produits maison',
        ] );

        $this->add_control( 'cta_url', [
            'label'   => __( 'Lien', 'sl-agences' ),
            'type'    => Controls_Manager::URL,
            'default' => [ 'url' => '/produits-maison/' ],
        ] );

        $this->end_controls_section();

        /* ── STYLE GÉNÉRAL ── */
        $this->start_controls_section( 'section_style', [
            'label' => __( '🎨 Section & Textes', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        $this->add_control( 'couleur_accent', [
            'label'     => __( 'Couleur accent (rose)', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#e85499',
            'selectors' => [
                '{{WRAPPER}} .slpm-label span'   => 'color: {{VALUE}};',
                '{{WRAPPER}} .slpm-label::after' => 'background: {{VALUE}}33;',
                '{{WRAPPER}} .slpm-titre em'     => 'color: {{VALUE}};',
                '{{WRAPPER}} .slpm-nb-cat'       => 'color: {{VALUE}};',
            ],
        ] );

        $this->add_control( 'couleur_fond_section', [
            'label'     => __( 'Fond de la section', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [ '{{WRAPPER}} .slpm-section' => 'background: {{VALUE}};' ],
            'description' => __( 'Laissez vide pour garder le dégradé sombre par défaut.', 'sl-agences' ),
        ] );

        $this->add_control( 'couleur_titre', [
            'label'     => __( 'Couleur du titre', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => [ '{{WRAPPER}} .slpm-titre.ap-titre' => 'color: {{VALUE}} !important;' ],
        ] );

        $this->add_control( 'couleur_desc', [
            'label'     => __( 'Couleur de la description', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => 'rgba(255,255,255,0.42)',
            'selectors' => [ '{{WRAPPER}} .slpm-desc.ap-corps' => 'color: {{VALUE}} !important;' ],
        ] );

        $this->end_controls_section();

        /* ── STYLE BOUTON CTA ── */
        $this->start_controls_section( 'section_style_cta', [
            'label' => __( '🔘 Style du bouton CTA', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        $this->add_control( 'cta_couleur_texte', [
            'label'     => __( 'Couleur du texte', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => [ '{{WRAPPER}} .slpm-cta-btn' => 'color: {{VALUE}};' ],
        ] );

        $this->add_control( 'cta_couleur_fond', [
            'label'     => __( 'Couleur de fond', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#e85499',
            'selectors' => [ '{{WRAPPER}} .slpm-cta-btn' => 'background: {{VALUE}};' ],
        ] );

        $this->add_control( 'cta_couleur_bordure', [
            'label'     => __( 'Couleur de bordure', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [ '{{WRAPPER}} .slpm-cta-btn' => 'border: 2px solid {{VALUE}};' ],
        ] );

        $this->add_control( 'cta_couleur_texte_hover', [
            'label'     => __( 'Couleur texte (survol)', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [ '{{WRAPPER}} .slpm-cta-btn:hover' => 'color: {{VALUE}};' ],
        ] );

        $this->add_control( 'cta_couleur_fond_hover', [
            'label'     => __( 'Couleur fond (survol)', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#3b549f',
            'selectors' => [ '{{WRAPPER}} .slpm-cta-btn:hover' => 'background: {{VALUE}};' ],
        ] );

        $this->add_responsive_control( 'cta_padding', [
            'label'      => __( 'Espacement intérieur', 'sl-agences' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px', 'em' ],
            'default'    => [ 'top' => '20', 'right' => '48', 'bottom' => '20', 'left' => '48', 'unit' => 'px', 'isLinked' => false ],
            'selectors'  => [ '{{WRAPPER}} .slpm-cta-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
        ] );

        $this->add_control( 'cta_font_size', [
            'label'      => __( 'Taille du texte', 'sl-agences' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px' ],
            'range'      => [ 'px' => [ 'min' => 8, 'max' => 24 ] ],
            'default'    => [ 'size' => 13, 'unit' => 'px' ],
            'selectors'  => [ '{{WRAPPER}} .slpm-cta-btn' => 'font-size: {{SIZE}}{{UNIT}};' ],
        ] );

        $this->end_controls_section();

        /* ── STYLE CARTES ── */
        $this->start_controls_section( 'section_style_cartes', [
            'label' => __( '🃏 Style des cartes', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        $this->add_control( 'carte_couleur_nom', [
            'label'     => __( 'Couleur nom produit', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => [ '{{WRAPPER}} .slpm-card-nom' => 'color: {{VALUE}};' ],
        ] );

        $this->add_control( 'carte_couleur_desc', [
            'label'     => __( 'Couleur description carte', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => 'rgba(255,255,255,0.62)',
            'selectors' => [ '{{WRAPPER}} .slpm-card-desc' => 'color: {{VALUE}};' ],
        ] );

        $this->add_control( 'carte_overlay_intensite', [
            'label'     => __( 'Intensité de l\'overlay sombre', 'sl-agences' ),
            'type'      => Controls_Manager::SLIDER,
            'range'     => [ 'px' => [ 'min' => 0, 'max' => 100 ] ],
            'default'   => [ 'size' => 92 ],
            'selectors' => [
                '{{WRAPPER}} .slpm-overlay' => 'background: linear-gradient(to top, rgba(3,7,18,calc({{SIZE}} / 100)) 0%, rgba(3,7,18,0.48) 45%, rgba(3,7,18,0.1) 100%);',
            ],
        ] );

        $this->end_controls_section();
    }

    /* ── Rendu d'une carte ── */
    private function render_card( array $c ): string {
        $img           = $c['image']['url'] ?? '';
        $url           = $c['url']['url'] ?? '#';
        $accent        = $c['accent'] ?: '#e85499';
        $bg_size       = ! empty( $c['bg_size'] )       ? $c['bg_size']       : 'cover';
        $bg_position   = ! empty( $c['bg_position'] )   ? $c['bg_position']   : 'center center';
        $bg_repeat     = ! empty( $c['bg_repeat'] )     ? $c['bg_repeat']     : 'no-repeat';
        $bg_attachment = ! empty( $c['bg_attachment'] ) ? $c['bg_attachment'] : 'scroll';
        $ov_opacity    = isset( $c['bg_overlay_opacite']['size'] ) ? (float) $c['bg_overlay_opacite']['size'] / 100 : 0.72;
        $ov_color_hex  = ! empty( $c['bg_overlay_couleur'] ) ? $c['bg_overlay_couleur'] : '#030712';

        /* Convert hex overlay color to rgba with variable opacity */
        $ov_color_hex = ltrim( $ov_color_hex, '#' );
        if ( strlen( $ov_color_hex ) === 3 ) {
            $ov_color_hex = $ov_color_hex[0].$ov_color_hex[0].$ov_color_hex[1].$ov_color_hex[1].$ov_color_hex[2].$ov_color_hex[2];
        }
        $ov_r = hexdec( substr( $ov_color_hex, 0, 2 ) );
        $ov_g = hexdec( substr( $ov_color_hex, 2, 2 ) );
        $ov_b = hexdec( substr( $ov_color_hex, 4, 2 ) );
        $ov_bot = "rgba({$ov_r},{$ov_g},{$ov_b},{$ov_opacity})";
        $ov_mid = 'rgba(' . $ov_r . ',' . $ov_g . ',' . $ov_b . ',' . round( $ov_opacity * 0.52, 3 ) . ')';
        $ov_top = 'rgba(' . $ov_r . ',' . $ov_g . ',' . $ov_b . ',' . round( $ov_opacity * 0.14, 3 ) . ')';
        $overlay_style = 'background:linear-gradient(to top,' . $ov_bot . ' 0%,' . $ov_mid . ' 45%,' . $ov_top . ' 100%)';

        $bg_style = '';
        if ( $img ) {
            $bg_style = ' style="background-image:url(' . esc_url( $img ) . ');'
                . 'background-size:' . esc_attr( $bg_size ) . ';'
                . 'background-position:' . esc_attr( $bg_position ) . ';'
                . 'background-repeat:' . esc_attr( $bg_repeat ) . ';'
                . 'background-attachment:' . esc_attr( $bg_attachment ) . '"';
        }
        return '<a href="' . esc_url( $url ) . '" class="slpm-card slpm-card-bg"' . $bg_style . '>'
            . '<div class="slpm-overlay" style="' . esc_attr( $overlay_style ) . '"></div>'
            . '<div class="slpm-ref-badge" style="background:' . esc_attr( $accent ) . '">' . esc_html( $c['references'] ) . ' réf.</div>'
            . '<div class="slpm-content">'
            .   '<div class="slpm-card-head">'
            .     '<span class="slpm-emoji">' . esc_html( $c['emoji'] ) . '</span>'
            .     '<h3 class="slpm-card-nom">' . esc_html( $c['nom'] ) . '</h3>'
            .   '</div>'
            .   '<p class="slpm-card-desc">' . esc_html( $c['desc_card'] ) . '</p>'
            .   '<div class="slpm-decouvrir" style="color:' . esc_attr( $accent ) . ';border-bottom-color:' . esc_attr( $accent ) . '">'
            .     'Découvrir'
            .     '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>'
            .   '</div>'
            . '</div>'
            . '</a>';
    }

    protected function render() {
        $s      = $this->get_settings_for_display();
        $t2     = wp_kses( $s['titre_ligne2'], [ 'em' => [] ] );
        $cartes = array_values( $s['cartes'] ?? [] );
        $cta_url = $s['cta_url']['url'] ?? '#';
        ?>
        <section class="slpm-section ap">
          <div class="apw">
            <!-- En-tête -->
            <div class="slpm-header">
              <div class="slpm-header-left">
                <div class="slpm-label ap-lbl"><span><?php echo esc_html( $s['label'] ); ?></span></div>
                <h2 class="slpm-titre ap-titre">
                  <span><?php echo esc_html( $s['titre_ligne1'] ); ?></span>
                  <span><?php echo $t2; ?></span>
                </h2>
                <p class="ap-corps slpm-desc"><?php echo esc_html( $s['description'] ); ?></p>
              </div>
              <?php if ( $s['nb_categories'] ) : ?>
              <div class="slpm-nb-wrap">
                <strong class="slpm-nb-cat"><?php echo esc_html( $s['nb_categories'] ); ?></strong>
                <span class="slpm-nb-lbl">Catégories</span>
              </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Grille éditoriale -->
          <div class="apw slpm-grid-wrap">
            <?php if ( isset( $cartes[0], $cartes[1] ) ) : ?>
            <div class="slpm-row1">
              <?php echo $this->render_card( $cartes[0] ); ?>
              <?php echo $this->render_card( $cartes[1] ); ?>
            </div>
            <?php endif; ?>

            <?php if ( isset( $cartes[2] ) ) : ?>
            <div class="slpm-row2">
              <?php for ( $i = 2; $i <= 4 && isset( $cartes[$i] ); $i++ ) echo $this->render_card( $cartes[$i] ); ?>
            </div>
            <?php endif; ?>

            <?php if ( isset( $cartes[5] ) ) : ?>
            <div class="slpm-row3">
              <?php echo $this->render_card( $cartes[5] ); ?>
            </div>
            <?php endif; ?>
          </div>

          <!-- CTA -->
          <?php if ( $s['cta_texte'] && $cta_url ) : ?>
          <div class="slpm-cta-wrap">
            <a href="<?php echo esc_url( $cta_url ); ?>" class="slpm-cta-btn">
              <span><?php echo esc_html( $s['cta_texte'] ); ?></span>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
              </svg>
            </a>
          </div>
          <?php endif; ?>

        </section>
        <?php
    }
}
