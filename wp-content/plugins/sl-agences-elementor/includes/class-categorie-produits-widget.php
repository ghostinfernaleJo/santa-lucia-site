<?php
/**
 * Widget Elementor "Catégorie Produits" — Santa Lucia
 *
 * Une section produits par famille : en-tête compact
 * (numéro · tag · titre · compteur) + grille de cartes 3 colonnes.
 * Utilisé une fois par catégorie (Spaghettis, Farines, Glaces…).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

class SL_Categorie_Produits_Widget extends Widget_Base {

    public function get_name()       { return 'sl_categorie_produits'; }
    public function get_title()      { return __( 'Catégorie Produits', 'sl-agences' ); }
    public function get_icon()       { return 'eicon-products'; }
    public function get_categories() { return [ 'santa-lucia' ]; }
    public function get_keywords()   { return [ 'categorie', 'produits', 'grille', 'famille', 'maison', 'santa lucia' ]; }

    public function get_style_depends()        { return [ 'sl-produits-maison-page' ]; }
    public function has_widget_inner_wrapper(): bool { return false; }

    protected function register_controls() {

        /* ── IDENTITÉ ── */
        $this->start_controls_section( 'section_identite', [
            'label' => __( '🏷️ Identité', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'anchor_id', [
            'label'       => __( 'Ancre (id HTML)', 'sl-agences' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => 'spaghettis',
            'description' => __( 'Doit correspondre au lien dans la Nav Produits.', 'sl-agences' ),
        ] );

        $this->add_control( 'numero', [
            'label'   => __( 'Numéro (01, 02…)', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => '01',
        ] );

        $this->add_control( 'tag', [
            'label'   => __( 'Tag', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => 'Produits Santa Lucia',
        ] );

        $this->add_control( 'titre', [
            'label'       => __( 'Titre (nom de la catégorie)', 'sl-agences' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => 'Spaghettis',
            'label_block' => true,
        ] );

        $this->end_controls_section();

        /* ── PRODUITS ── */
        $this->start_controls_section( 'section_produits', [
            'label' => __( '🛒 Produits', 'sl-agences' ),
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

        $rep = new Repeater();

        $rep->add_control( 'image', [
            'label' => __( 'Photo produit', 'sl-agences' ),
            'type'  => Controls_Manager::MEDIA,
        ] );

        $rep->add_control( 'nom', [
            'label'       => __( 'Nom du produit', 'sl-agences' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => 'Spaghetti Santa Lucia 500g',
            'label_block' => true,
        ] );

        $rep->add_control( 'desc', [
            'label'   => __( 'Description', 'sl-agences' ),
            'type'    => Controls_Manager::TEXTAREA,
            'default' => '',
            'rows'    => 2,
        ] );

        $rep->add_control( 'badge', [
            'label'   => __( 'Badge (ex: Made in Cameroun)', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => 'Santa Lucia Maison',
        ] );

        $this->add_control( 'produits', [
            'label'       => __( 'Produits', 'sl-agences' ),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $rep->get_controls(),
            'default'     => [
                [ 'nom' => 'Spaghetti Santa Lucia 250g', 'desc' => 'Format pratique pour les repas rapides et les petites préparations.', 'badge' => 'Santa Lucia Maison' ],
                [ 'nom' => 'Spaghetti Santa Lucia 500g', 'desc' => 'Format familial pour les plats de pâtes du quotidien.',              'badge' => 'Santa Lucia Maison' ],
                [ 'nom' => 'Spaghetti Omit 250g',        'desc' => 'Portion simple pour cuisiner vite avec une texture régulière.',      'badge' => 'Santa Lucia Maison' ],
                [ 'nom' => 'Spaghetti Omit 500g',        'desc' => 'Format généreux pensé pour les repas partagés.',                     'badge' => 'Santa Lucia Maison' ],
            ],
            'title_field' => '{{{ nom }}}',
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
            'selectors' => [
                '{{WRAPPER}} .slpm-cat-section' => 'background: {{VALUE}} !important;',
            ],
        ] );

        $this->add_control( 'couleur_accent', [
            'label'     => __( 'Couleur accent (rose)', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#e85499',
            'selectors' => [
                '{{WRAPPER}} .slpm-cat-num'      => 'color: {{VALUE}}73;',
                '{{WRAPPER}} .slpm-cat-dot-sep'  => 'background: {{VALUE}}4d;',
                '{{WRAPPER}} .slpm-cat-tag'      => 'color: {{VALUE}};',
                '{{WRAPPER}} .slpm-cat-count-badge' => 'color: {{VALUE}}1f;',
                '{{WRAPPER}} .slpm-grid'         => 'background: {{VALUE}}14; border-color: {{VALUE}}1a;',
                '{{WRAPPER}} .slpm-card-tag'     => 'color: {{VALUE}}; border-top-color: {{VALUE}}40;',
            ],
        ] );

        $this->end_controls_section();
    }

    protected function render() {
        $s      = $this->get_settings_for_display();
        $prods  = $s['produits'] ?? [];
        $cols   = $s['colonnes'] ?? '3';
        $nb     = count( $prods );
        ?>
        <div class="slpm-page">
        <section id="<?php echo esc_attr( $s['anchor_id'] ); ?>" class="slpm-cat-section">
          <div class="slpm-pw">

            <!-- En-tête -->
            <div class="slpm-cat-head">
              <div class="slpm-cat-head-left">
                <div class="slpm-cat-meta">
                  <span class="slpm-cat-num"><?php echo esc_html( $s['numero'] ); ?></span>
                  <span class="slpm-cat-dot-sep"></span>
                  <span class="slpm-cat-tag"><?php echo esc_html( $s['tag'] ); ?></span>
                </div>
                <h2 class="slpm-cat-titre"><?php echo esc_html( $s['titre'] ); ?></h2>
              </div>
              <div class="slpm-cat-count-badge"><?php echo $nb; ?></div>
            </div>

            <!-- Grille produits -->
            <?php if ( $prods ) : ?>
            <div class="slpm-grid slpm-grid-<?php echo esc_attr( $cols ); ?>col">
              <?php foreach ( $prods as $p ) :
                $img_url = $p['image']['url'] ?? '';
                $fallback = 'data:image/svg+xml;charset=UTF-8,' . rawurlencode(
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300"><rect width="400" height="300" fill="#f1f3f8"/><text x="200" y="155" text-anchor="middle" font-family="Inter,Arial,sans-serif" font-size="14" fill="#9ca3af">' . htmlspecialchars( $p['nom'], ENT_QUOTES ) . '</text></svg>'
                );
              ?>
              <article class="slpm-card">
                <div class="slpm-card-img">
                  <img src="<?php echo esc_url( $img_url ?: $fallback ); ?>" alt="<?php echo esc_attr( $p['nom'] ); ?>" loading="lazy">
                </div>
                <div class="slpm-card-body">
                  <h3 class="slpm-card-nom"><?php echo esc_html( $p['nom'] ); ?></h3>
                  <?php if ( $p['desc'] ) : ?>
                  <p class="slpm-card-desc"><?php echo esc_html( $p['desc'] ); ?></p>
                  <?php endif; ?>
                  <span class="slpm-card-tag"><?php echo esc_html( $p['badge'] ); ?></span>
                </div>
              </article>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>

          </div>
        </section>
        </div>
        <?php
    }
}
