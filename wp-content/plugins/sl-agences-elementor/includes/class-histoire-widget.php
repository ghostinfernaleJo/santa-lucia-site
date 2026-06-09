<?php
/**
 * Widget Elementor "Histoire" — Santa Lucia
 *
 * Deux colonnes : texte (label · H2 · paragraphes) | image + badge.
 * Fond blanc, séparateur rose en bas.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

class SL_Histoire_Widget extends Widget_Base {

    public function get_name()       { return 'sl_histoire'; }
    public function get_title()      { return __( 'Histoire', 'sl-agences' ); }
    public function get_icon()       { return 'eicon-document-file'; }
    public function get_categories() { return [ 'santa-lucia' ]; }
    public function get_keywords()   { return [ 'histoire', 'about', 'apropos', 'santa lucia' ]; }

    public function get_style_depends()        { return [ 'sl-histoire' ]; }
    public function has_widget_inner_wrapper(): bool { return false; }

    protected function register_controls() {

        /* ── LABEL & TITRE ── */
        $this->start_controls_section( 'section_titre', [
            'label' => __( '✏️ Label & Titre', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'label', [
            'label'   => __( 'Label (ligne rose)', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => 'Notre histoire',
        ] );

        $this->add_control( 'titre_ligne1', [
            'label'       => __( 'Titre — Ligne 1', 'sl-agences' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => 'Né à Yaoundé,',
            'label_block' => true,
        ] );

        $this->add_control( 'titre_ligne2', [
            'label'       => __( 'Titre — Ligne 2 (autorise <em>mot</em> en rose)', 'sl-agences' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => 'présent partout <em>pour vous.</em>',
            'label_block' => true,
        ] );

        $this->end_controls_section();

        /* ── PARAGRAPHES ── */
        $this->start_controls_section( 'section_textes', [
            'label' => __( '📝 Paragraphes', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $repeater = new Repeater();
        $repeater->add_control( 'texte', [
            'label' => __( 'Paragraphe', 'sl-agences' ),
            'type'  => Controls_Manager::WYSIWYG,
        ] );

        $this->add_control( 'paragraphes', [
            'label'       => __( 'Paragraphes', 'sl-agences' ),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $repeater->get_controls(),
            'default'     => [
                [ 'texte' => 'Le Complexe Santa Lucia ouvre ses portes en <strong>mai 2006</strong> dans le quartier Kondengui, à Yaoundé. Dès le premier jour, la promesse est claire : offrir à chaque client des produits de qualité, des rayons toujours achalandés, et des prix accessibles à toutes les bourses.' ],
                [ 'texte' => 'En moins de vingt ans, Santa Lucia est devenu bien plus qu\'un supermarché. C\'est un espace de vie — où l\'on fait ses courses, savoure un pain chaud, où les enfants profitent des manèges. Un complexe pensé pour <strong>toute la famille, à toute heure.</strong>' ],
                [ 'texte' => 'Aujourd\'hui, avec <strong>18 agences</strong> implantées à Douala et Yaoundé, ouvertes 24h/24 et 7j/7, Santa Lucia est la référence de la grande distribution au Cameroun.' ],
            ],
            'title_field' => 'Paragraphe',
        ] );

        $this->end_controls_section();

        /* ── IMAGE ── */
        $this->start_controls_section( 'section_image', [
            'label' => __( '🖼️ Image', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'image', [
            'label' => __( 'Image', 'sl-agences' ),
            'type'  => Controls_Manager::MEDIA,
            'default' => [ 'url' => '' ],
        ] );

        $this->add_control( 'badge_label', [
            'label'   => __( 'Badge image', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => 'Notre magasin',
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
            'selectors' => [ '{{WRAPPER}} .slhi-section' => 'background: {{VALUE}};' ],
        ] );

        $this->add_control( 'couleur_accent', [
            'label'     => __( 'Couleur accent (rose)', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#e85499',
            'selectors' => [
                '{{WRAPPER}} .slhi-label span'     => 'color: {{VALUE}};',
                '{{WRAPPER}} .slhi-label::after'   => 'background: {{VALUE}}26;',
                '{{WRAPPER}} .slhi-titre em'        => 'color: {{VALUE}};',
                '{{WRAPPER}} .slhi-img-badge'       => 'background: {{VALUE}};',
                '{{WRAPPER}} .slhi-img'             => 'border-color: {{VALUE}}26;',
                '{{WRAPPER}} .slhi-section'         => 'border-bottom-color: {{VALUE}}1a;',
            ],
        ] );

        $this->end_controls_section();
    }

    protected function render() {
        $s    = $this->get_settings_for_display();
        $img  = $s['image']['url'] ?? '';
        $t2   = wp_kses( $s['titre_ligne2'], [ 'em' => [], 'strong' => [] ] );

        $paras = '';
        foreach ( $s['paragraphes'] ?? [] as $p ) {
            $allowed = [ 'strong' => [], 'em' => [], 'br' => [], 'a' => [ 'href' => [], 'target' => [] ] ];
            $paras .= '<p class="slhi-corps">' . wp_kses( $p['texte'], $allowed ) . '</p>';
        }
        ?>
        <section class="slhi-section">
          <div class="slhi-inner">
            <div class="slhi-grid">

              <!-- Colonne texte -->
              <div>
                <div class="slhi-label">
                  <span><?php echo esc_html( $s['label'] ); ?></span>
                </div>
                <h2 class="slhi-titre">
                  <span><?php echo esc_html( $s['titre_ligne1'] ); ?></span>
                  <span><?php echo $t2; ?></span>
                </h2>
                <div class="slhi-textes">
                  <?php echo $paras; ?>
                </div>
              </div>

              <!-- Colonne image -->
              <?php if ( $img ) : ?>
              <div class="slhi-img-wrap">
                <img class="slhi-img" src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $s['badge_label'] ); ?>">
                <?php if ( $s['badge_label'] ) : ?>
                <div class="slhi-img-badge"><?php echo esc_html( $s['badge_label'] ); ?></div>
                <?php endif; ?>
              </div>
              <?php endif; ?>

            </div>
          </div>
        </section>
        <?php
    }
}
