<?php
/**
 * Widget Elementor "Hero À Propos" — Santa Lucia
 *
 * Fond dark violet lumineux + halos roses/bleus + grille.
 * Deux colonnes : texte (badge · H1 · desc · stats) | image 4/5 + badge flottant.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

class SL_Hero_Apropos_Widget extends Widget_Base {

    public function get_name()       { return 'sl_hero_apropos'; }
    public function get_title()      { return __( 'Hero À Propos', 'sl-agences' ); }
    public function get_icon()       { return 'eicon-featured-image'; }
    public function get_categories() { return [ 'santa-lucia' ]; }
    public function get_keywords()   { return [ 'hero', 'apropos', 'about', 'stats', 'santa lucia' ]; }

    public function get_style_depends()        { return [ 'sl-hero-apropos' ]; }
    public function has_widget_inner_wrapper(): bool { return false; }

    protected function register_controls() {

        /* ── BADGE ────────────────────────────────────────────────── */
        $this->start_controls_section( 'section_badge', [
            'label' => __( '🏷️ Badge', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'badge_texte', [
            'label'   => __( 'Texte du badge', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => 'À propos de nous',
        ] );

        $this->end_controls_section();

        /* ── TITRE & TEXTE ────────────────────────────────────────── */
        $this->start_controls_section( 'section_titre', [
            'label' => __( '✏️ Titre & Texte', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'titre_ligne1', [
            'label'       => __( 'Titre — Ligne 1 (blanche)', 'sl-agences' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => 'Votre marché,',
            'label_block' => true,
        ] );

        $this->add_control( 'titre_ligne2', [
            'label'       => __( 'Titre — Ligne 2 (autorise <em>mot</em> en rose)', 'sl-agences' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => 'votre <em>bonheur.</em>',
            'label_block' => true,
            'description' => __( 'Ex : votre &lt;em&gt;bonheur.&lt;/em&gt;', 'sl-agences' ),
        ] );

        $this->add_control( 'description', [
            'label'   => __( 'Description', 'sl-agences' ),
            'type'    => Controls_Manager::TEXTAREA,
            'default' => 'Depuis 2006, le Complexe Santa Lucia est l\'endroit où les familles camerounaises trouvent tout ce dont elles ont besoin — des produits frais, du pain chaud, une restauration savoureuse — le tout au meilleur prix, 24h/24.',
            'rows'    => 4,
        ] );

        $this->end_controls_section();

        /* ── STATISTIQUES ─────────────────────────────────────────── */
        $this->start_controls_section( 'section_stats', [
            'label' => __( '📊 Statistiques', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $repeater = new Repeater();

        $repeater->add_control( 'stat_valeur', [
            'label'   => __( 'Valeur', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => '18',
        ] );

        $repeater->add_control( 'stat_label', [
            'label'   => __( 'Label', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => 'Agences',
        ] );

        $this->add_control( 'stats', [
            'label'       => __( 'Stats', 'sl-agences' ),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $repeater->get_controls(),
            'default'     => [
                [ 'stat_valeur' => '18',   'stat_label' => 'Agences' ],
                [ 'stat_valeur' => '2006', 'stat_label' => 'Fondé à Yaoundé' ],
                [ 'stat_valeur' => '24/7', 'stat_label' => 'Ouvert pour vous' ],
            ],
            'title_field' => '{{{ stat_valeur }}} — {{{ stat_label }}}',
        ] );

        $this->end_controls_section();

        /* ── IMAGE ────────────────────────────────────────────────── */
        $this->start_controls_section( 'section_image', [
            'label' => __( '🖼️ Image & Badge', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'image', [
            'label' => __( 'Image (ratio 4/5)', 'sl-agences' ),
            'type'  => Controls_Manager::MEDIA,
            'default' => [ 'url' => '' ],
        ] );

        $this->add_control( 'badge_img_valeur', [
            'label'   => __( 'Badge image — Valeur', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => '+2 500',
        ] );

        $this->add_control( 'badge_img_label', [
            'label'   => __( 'Badge image — Label', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => 'Collaborateurs',
        ] );

        $this->end_controls_section();

        /* ── STYLE ────────────────────────────────────────────────── */
        $this->start_controls_section( 'section_style', [
            'label' => __( '🎨 Style', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        $this->add_control( 'couleur_fond', [
            'label'     => __( 'Couleur de fond', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#130d2e',
            'selectors' => [
                '{{WRAPPER}} .slha-section'   => 'background: {{VALUE}};',
                '{{WRAPPER}} .slha-img-badge' => 'border-color: {{VALUE}};',
            ],
        ] );

        $this->add_control( 'couleur_accent', [
            'label'     => __( 'Couleur accent (rose)', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#e85499',
            'selectors' => [
                '{{WRAPPER}} .slha-badge-dot'      => 'background: {{VALUE}};',
                '{{WRAPPER}} .slha-badge-text'     => 'color: {{VALUE}};',
                '{{WRAPPER}} .slha-badge'          => 'border-color: {{VALUE}}47; background: {{VALUE}}1f;',
                '{{WRAPPER}} .slha-titre em'       => 'color: {{VALUE}};',
                '{{WRAPPER}} .slha-img-badge'      => 'background: {{VALUE}};',
                '{{WRAPPER}} .slha-img-wrap img'   => 'border-color: {{VALUE}}33;',
                '{{WRAPPER}} .slha-stat-sep'       => 'background: {{VALUE}}40;',
            ],
        ] );

        $this->add_control( 'couleur_stats', [
            'label'     => __( 'Couleur valeurs stats', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#ffd200',
            'selectors' => [
                '{{WRAPPER}} .slha-stat-val' => 'color: {{VALUE}};',
            ],
        ] );

        $this->end_controls_section();
    }

    /* ── RENDU ───────────────────────────────────────────────────── */
    protected function render() {
        $s     = $this->get_settings_for_display();
        $stats = $s['stats'] ?? [];

        $img_url        = $s['image']['url'] ?? '';
        $titre_l2_safe  = wp_kses( $s['titre_ligne2'], [ 'em' => [] ] );

        /* Stats HTML */
        $stats_html = '';
        foreach ( $stats as $i => $stat ) {
            if ( $i > 0 ) {
                $stats_html .= '<div class="slha-stat-sep"></div>';
            }
            $stats_html .= '<div class="slha-stat-item">'
                . '<strong class="slha-stat-val">' . esc_html( $stat['stat_valeur'] ) . '</strong>'
                . '<span class="slha-stat-lbl">'   . esc_html( $stat['stat_label'] )  . '</span>'
                . '</div>';
        }
        ?>
        <section class="slha-section">
          <div class="slha-halos"></div>
          <div class="slha-grid-bg"></div>

          <div class="slha-inner">
            <div class="slha-hero-grid">

              <!-- Colonne texte -->
              <div>
                <div class="slha-badge">
                  <span class="slha-badge-dot"></span>
                  <span class="slha-badge-text"><?php echo esc_html( $s['badge_texte'] ); ?></span>
                </div>

                <h1 class="slha-titre">
                  <span><?php echo esc_html( $s['titre_ligne1'] ); ?></span>
                  <span><?php echo $titre_l2_safe; ?></span>
                </h1>

                <p class="slha-desc"><?php echo esc_html( $s['description'] ); ?></p>

                <?php if ( $stats_html ) : ?>
                <div class="slha-stats"><?php echo $stats_html; ?></div>
                <?php endif; ?>
              </div>

              <!-- Colonne image -->
              <?php if ( $img_url ) : ?>
              <div class="slha-img-wrap">
                <img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $s['badge_texte'] ); ?>">
                <?php if ( $s['badge_img_valeur'] ) : ?>
                <div class="slha-img-badge">
                  <strong class="slha-img-badge-val"><?php echo esc_html( $s['badge_img_valeur'] ); ?></strong>
                  <span  class="slha-img-badge-lbl"><?php echo esc_html( $s['badge_img_label'] ); ?></span>
                </div>
                <?php endif; ?>
              </div>
              <?php endif; ?>

            </div>
          </div>
        </section>
        <?php
    }
}
