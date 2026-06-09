<?php
/**
 * Widget Elementor "Section Espace" — Santa Lucia
 *
 * Rendu : en-tête compact (numéro · tag | titre | desc) +
 *         galerie masonry centrée max-width 1100px + lightbox JS.
 *
 * Tip : dans le champ Titre, utilisez <em>mot</em> pour colorier
 *       un mot en rose (ex. : "le <em>paradis</em> des enfants.").
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

class SL_Section_Espace_Widget extends Widget_Base {

    public function get_name()       { return 'sl_section_espace'; }
    public function get_title()      { return __( 'Section Espace', 'sl-agences' ); }
    public function get_icon()       { return 'eicon-gallery-masonry'; }
    public function get_categories() { return [ 'santa-lucia' ]; }
    public function get_keywords()   { return [ 'espace', 'galerie', 'masonry', 'section', 'photos', 'santa lucia' ]; }

    public function get_style_depends()        { return [ 'sl-section-espace' ]; }
    public function get_script_depends()       { return [ 'sl-section-espace' ]; }
    public function has_widget_inner_wrapper(): bool { return false; }

    /* ── CONTRÔLES ──────────────────────────────────────────────── */
    protected function register_controls() {

        /* ── IDENTITÉ DE LA SECTION ────────────────────────────── */
        $this->start_controls_section( 'section_identite', [
            'label' => __( '🏷️ Identité', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'anchor_id', [
            'label'       => __( 'Ancre (id HTML)', 'sl-agences' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => 'espace-jeux',
            'description' => __( 'Utilisé pour les liens #espace-jeux depuis la grille découverte.', 'sl-agences' ),
        ] );

        $this->add_control( 'numero', [
            'label'   => __( 'Numéro', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => '01',
        ] );

        $this->add_control( 'tag', [
            'label'   => __( 'Tag / Catégorie', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => 'Loisirs & Divertissement',
        ] );

        $this->add_control( 'titre', [
            'label'       => __( 'Titre (autorise <em>mot</em> en rose)', 'sl-agences' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => 'L\'espace de jeux&nbsp;: le <em>paradis</em> des enfants.',
            'label_block' => true,
            'description' => __( 'Entourez un mot de &lt;em&gt;&lt;/em&gt; pour le colorier en rose.', 'sl-agences' ),
        ] );

        $this->add_control( 'description', [
            'label'   => __( 'Description', 'sl-agences' ),
            'type'    => Controls_Manager::TEXTAREA,
            'default' => 'Manèges sécurisés, jeux gonflables et animations intérieures & extérieures. Pendant que les enfants s\'éclatent, les parents font leurs courses en toute tranquillité.',
            'rows'    => 3,
        ] );

        $this->add_control( 'lien_retour', [
            'label'   => __( 'Lien "Voir tous les espaces"', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => '#espaces-index',
        ] );

        $this->end_controls_section();

        /* ── GALERIE ─────────────────────────────────────────────── */
        $this->start_controls_section( 'section_galerie', [
            'label' => __( '🖼️ Galerie Photos', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'colonnes', [
            'label'   => __( 'Colonnes (desktop)', 'sl-agences' ),
            'type'    => Controls_Manager::SELECT,
            'default' => '3',
            'options' => [
                '2' => '2 colonnes',
                '3' => '3 colonnes',
                '4' => '4 colonnes',
            ],
        ] );

        $repeater = new Repeater();

        $repeater->add_control( 'image', [
            'label' => __( 'Photo', 'sl-agences' ),
            'type'  => Controls_Manager::MEDIA,
        ] );

        $repeater->add_control( 'alt', [
            'label'   => __( 'Texte alternatif', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => '',
        ] );

        $this->add_control( 'photos', [
            'label'       => __( 'Photos', 'sl-agences' ),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $repeater->get_controls(),
            'default'     => [],
            'title_field' => 'Photo',
        ] );

        $this->end_controls_section();

        /* ── STYLE ───────────────────────────────────────────────── */
        $this->start_controls_section( 'section_style', [
            'label' => __( '🎨 Style', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        $this->add_control( 'fond', [
            'label'     => __( 'Couleur de fond', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => [
                '{{WRAPPER}} .esp6-sec-wrap' => 'background: {{VALUE}};',
            ],
        ] );

        $this->add_control( 'couleur_accent', [
            'label'     => __( 'Couleur accent (rose)', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#e85499',
            'selectors' => [
                '{{WRAPPER}} .esp6-tag-lbl'    => 'color: {{VALUE}};',
                '{{WRAPPER}} .esp6-titre em'   => 'color: {{VALUE}};',
                '{{WRAPPER}} .esp6-btn-back'   => 'color: {{VALUE}};',
                '{{WRAPPER}} .esp6-btn-back::before' => 'background: {{VALUE}};',
                '{{WRAPPER}} .esp6-dot-sep'    => 'background: {{VALUE}}4d;',
                '{{WRAPPER}} .esp6-sep'        => 'background: {{VALUE}}14;',
            ],
        ] );

        $this->add_control( 'max_width', [
            'label'      => __( 'Largeur max du conteneur (px)', 'sl-agences' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px' ],
            'range'      => [ 'px' => [ 'min' => 600, 'max' => 1400 ] ],
            'default'    => [ 'unit' => 'px', 'size' => 1100 ],
            'selectors'  => [
                '{{WRAPPER}} .esp6-inner' => 'max-width: {{SIZE}}{{UNIT}};',
            ],
        ] );

        $this->end_controls_section();
    }

    /* ── RENDU ───────────────────────────────────────────────────── */
    protected function render() {
        $s      = $this->get_settings_for_display();
        $photos = $s['photos'] ?? [];
        $cols   = $s['colonnes'] ?? '3';
        $nb     = count( $photos );

        /* Titre : seules les balises <em> sont autorisées */
        $titre_safe = wp_kses( $s['titre'], [ 'em' => [] ] );

        /* Galerie */
        $gal = '';
        foreach ( $photos as $p ) {
            $url = $p['image']['url'] ?? '';
            $alt = esc_attr( $p['alt'] ?? '' );
            if ( ! $url ) continue;
            $gal .= '<a href="' . esc_url( $url ) . '" class="msn-item">'
                  . '<img src="' . esc_url( $url ) . '" alt="' . $alt . '" loading="lazy">'
                  . '</a>';
        }
        ?>
        <div id="<?php echo esc_attr( $s['anchor_id'] ); ?>" class="esp6-sec-wrap">
          <div class="esp6-inner">

            <!-- EN-TÊTE COMPACT -->
            <div class="esp6-sec-head">

              <div class="esp6-head-left">
                <div class="esp6-head-meta">
                  <span class="esp6-num-tag"><?php echo esc_html( $s['numero'] ); ?></span>
                  <span class="esp6-dot-sep"></span>
                  <span class="esp6-tag-lbl"><?php echo esc_html( $s['tag'] ); ?></span>
                </div>
                <h2 class="esp6-titre"><?php echo $titre_safe; ?></h2>
                <p class="esp6-desc"><?php echo esc_html( $s['description'] ); ?></p>
              </div>

              <div class="esp6-head-right">
                <?php if ( $nb > 0 ) : ?>
                <span class="esp6-photo-count">
                  <strong><?php echo $nb; ?></strong>&nbsp;photo<?php echo $nb > 1 ? 's' : ''; ?>
                </span>
                <?php endif; ?>
                <a href="<?php echo esc_url( $s['lien_retour'] ); ?>" class="esp6-btn-back">
                  Voir tous les espaces
                </a>
              </div>

            </div>

            <!-- GALERIE MASONRY -->
            <?php if ( $gal ) : ?>
            <div class="esp6-gal-rule"></div>
            <div class="esp6-masonry-wrap">
              <div class="msn-gal msn-<?php echo esc_attr( $cols ); ?>col">
                <?php echo $gal; ?>
              </div>
            </div>
            <?php endif; ?>

          </div>
          <div class="esp6-sep"></div>
        </div>
        <?php
    }
}
