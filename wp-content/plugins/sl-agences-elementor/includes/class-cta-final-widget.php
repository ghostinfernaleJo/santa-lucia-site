<?php
/**
 * Widget Elementor "CTA Final" — Santa Lucia S8
 *
 * Fond rose plein, deux cercles décoratifs, contenu centré :
 * H2 (blanc + ligne jaune), desc, deux boutons (plein + outline).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class SL_CTA_Final_Widget extends Widget_Base {

    public function get_name()       { return 'sl_cta_final'; }
    public function get_title()      { return __( 'CTA Final', 'sl-agences' ); }
    public function get_icon()       { return 'eicon-call-to-action'; }
    public function get_categories() { return [ 'santa-lucia' ]; }
    public function get_keywords()   { return [ 'cta', 'final', 'appel', 'action', 'apropos', 'santa lucia' ]; }

    public function get_style_depends()        { return [ 'sl-cta-final' ]; }
    public function has_widget_inner_wrapper(): bool { return false; }

    protected function register_controls() {

        /* ── TEXTE ── */
        $this->start_controls_section( 'section_texte', [
            'label' => __( '✏️ Texte', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'titre_ligne1', [
            'label'       => __( 'Titre — Ligne 1 (blanche)', 'sl-agences' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => 'Venez nous rendre visite,',
            'label_block' => true,
        ] );

        $this->add_control( 'titre_ligne2', [
            'label'       => __( 'Titre — Ligne 2 (jaune)', 'sl-agences' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => 'vous êtes chez vous.',
            'label_block' => true,
        ] );

        $this->add_control( 'description', [
            'label'   => __( 'Description', 'sl-agences' ),
            'type'    => Controls_Manager::TEXTAREA,
            'default' => 'Trouvez l\'agence la plus proche et découvrez tout ce que Santa Lucia a à vous offrir — chaque jour, à toute heure.',
            'rows'    => 3,
        ] );

        $this->end_controls_section();

        /* ── BOUTON 1 (plein blanc) ── */
        $this->start_controls_section( 'section_btn1', [
            'label' => __( '🔘 Bouton 1 (fond blanc)', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'btn1_texte', [
            'label'   => __( 'Texte', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => 'Nous appeler',
        ] );

        $this->add_control( 'btn1_url', [
            'label'   => __( 'Lien', 'sl-agences' ),
            'type'    => Controls_Manager::URL,
            'default' => [ 'url' => 'tel:+237674152010' ],
        ] );

        $this->end_controls_section();

        /* ── BOUTON 2 (outline) ── */
        $this->start_controls_section( 'section_btn2', [
            'label' => __( '🔘 Bouton 2 (outline blanc)', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'btn2_texte', [
            'label'   => __( 'Texte', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => 'Nos agences →',
        ] );

        $this->add_control( 'btn2_url', [
            'label'   => __( 'Lien', 'sl-agences' ),
            'type'    => Controls_Manager::URL,
            'default' => [ 'url' => '/nos-agences/' ],
        ] );

        $this->end_controls_section();

        /* ── STYLE GÉNÉRAL ── */
        $this->start_controls_section( 'section_style', [
            'label' => __( '🎨 Fond & Titre', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        $this->add_control( 'couleur_fond', [
            'label'     => __( 'Couleur de fond', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#e85499',
            'selectors' => [ '{{WRAPPER}} .slcta-section' => 'background: {{VALUE}};' ],
        ] );

        $this->add_control( 'couleur_titre1', [
            'label'     => __( 'Couleur titre ligne 1', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => [ '{{WRAPPER}} .slcta-titre' => 'color: {{VALUE}};' ],
        ] );

        $this->add_control( 'couleur_titre2', [
            'label'     => __( 'Couleur titre ligne 2', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#ffd200',
            'selectors' => [ '{{WRAPPER}} .slcta-titre-l2' => 'color: {{VALUE}};' ],
        ] );

        $this->add_control( 'couleur_desc', [
            'label'     => __( 'Couleur description', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => 'rgba(255,255,255,0.75)',
            'selectors' => [ '{{WRAPPER}} .slcta-desc' => 'color: {{VALUE}};' ],
        ] );

        $this->end_controls_section();

        /* ── STYLE BOUTON 1 ── */
        $this->start_controls_section( 'section_style_btn1', [
            'label' => __( '🔘 Style Bouton 1', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        $this->add_control( 'btn1_couleur_texte', [
            'label'     => __( 'Couleur du texte', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#e85499',
            'selectors' => [ '{{WRAPPER}} .slcta-btn1' => 'color: {{VALUE}};' ],
        ] );

        $this->add_control( 'btn1_couleur_fond', [
            'label'     => __( 'Couleur de fond', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => [ '{{WRAPPER}} .slcta-btn1' => 'background: {{VALUE}};' ],
        ] );

        $this->add_control( 'btn1_couleur_bordure', [
            'label'     => __( 'Couleur de bordure', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => 'transparent',
            'selectors' => [ '{{WRAPPER}} .slcta-btn1' => 'border-color: {{VALUE}};' ],
        ] );

        $this->add_control( 'btn1_couleur_texte_hover', [
            'label'     => __( 'Couleur texte (survol)', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [ '{{WRAPPER}} .slcta-btn1:hover' => 'color: {{VALUE}};' ],
        ] );

        $this->add_control( 'btn1_couleur_fond_hover', [
            'label'     => __( 'Couleur fond (survol)', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [ '{{WRAPPER}} .slcta-btn1:hover' => 'background: {{VALUE}};' ],
        ] );

        $this->end_controls_section();

        /* ── STYLE BOUTON 2 ── */
        $this->start_controls_section( 'section_style_btn2', [
            'label' => __( '🔘 Style Bouton 2', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        $this->add_control( 'btn2_couleur_texte', [
            'label'     => __( 'Couleur du texte', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => 'rgba(255,255,255,0.85)',
            'selectors' => [ '{{WRAPPER}} .slcta-btn2' => 'color: {{VALUE}};' ],
        ] );

        $this->add_control( 'btn2_couleur_fond', [
            'label'     => __( 'Couleur de fond', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => 'transparent',
            'selectors' => [ '{{WRAPPER}} .slcta-btn2' => 'background: {{VALUE}};' ],
        ] );

        $this->add_control( 'btn2_couleur_bordure', [
            'label'     => __( 'Couleur de bordure', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => 'rgba(255,255,255,0.35)',
            'selectors' => [ '{{WRAPPER}} .slcta-btn2' => 'border-color: {{VALUE}};' ],
        ] );

        $this->add_control( 'btn2_couleur_texte_hover', [
            'label'     => __( 'Couleur texte (survol)', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [ '{{WRAPPER}} .slcta-btn2:hover' => 'color: {{VALUE}};' ],
        ] );

        $this->add_control( 'btn2_couleur_fond_hover', [
            'label'     => __( 'Couleur fond (survol)', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [ '{{WRAPPER}} .slcta-btn2:hover' => 'background: {{VALUE}};' ],
        ] );

        $this->end_controls_section();
    }

    protected function render() {
        $s    = $this->get_settings_for_display();
        $url1 = $s['btn1_url']['url'] ?? '#';
        $url2 = $s['btn2_url']['url'] ?? '#';
        ?>
        <section class="slcta-section ap">
          <!-- Cercles décoratifs -->
          <div class="slcta-circle slcta-circle-1"></div>
          <div class="slcta-circle slcta-circle-2"></div>

          <div class="apw slcta-inner">
            <h2 class="slcta-titre">
              <?php echo esc_html( $s['titre_ligne1'] ); ?><br>
              <span class="slcta-titre-l2"><?php echo esc_html( $s['titre_ligne2'] ); ?></span>
            </h2>
            <p class="slcta-desc"><?php echo esc_html( $s['description'] ); ?></p>
            <div class="slcta-btns">
              <?php if ( $s['btn1_texte'] && $url1 ) : ?>
              <a href="<?php echo esc_url( $url1 ); ?>" class="slcta-btn1">
                <?php echo esc_html( $s['btn1_texte'] ); ?>
              </a>
              <?php endif; ?>
              <?php if ( $s['btn2_texte'] && $url2 ) : ?>
              <a href="<?php echo esc_url( $url2 ); ?>" class="slcta-btn2">
                <?php echo esc_html( $s['btn2_texte'] ); ?>
              </a>
              <?php endif; ?>
            </div>
          </div>
        </section>
        <?php
    }
}
