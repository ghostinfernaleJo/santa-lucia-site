<?php
/**
 * Widget Elementor "Bons Plans" — Santa Lucia
 * Layout identique à la page Promotions :
 *   Bannière promo + Sidebar gauche + Grille principale 5 colonnes
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Image_Size;

class SL_Bons_Plans_Widget extends Widget_Base {

    public function get_name()           { return 'sl_bons_plans'; }
    public function get_title()          { return __( 'Bons Plans', 'sl-agences' ); }
    public function get_icon()           { return 'eicon-price-list'; }
    public function get_categories()     { return [ 'santa-lucia' ]; }
    public function get_keywords()       { return [ 'bons plans', 'promotions', 'promo', 'offres' ]; }
    public function get_script_depends() { return [ 'sl-bons-plans' ]; }
    public function get_style_depends()  { return [ 'sl-bons-plans' ]; }

    protected function register_controls() {

        /* ── SECTION BANNIÈRE ── */
        $this->start_controls_section( 'section_banner', [
            'label' => __( '🎯 Bannière promotionnelle', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'afficher_banniere', [
            'label'        => __( 'Afficher la bannière', 'sl-agences' ),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ] );

        $this->add_control( 'banner_label', [
            'label'     => __( 'Label badge', 'sl-agences' ),
            'type'      => Controls_Manager::TEXT,
            'default'   => 'Promotions',
            'condition' => [ 'afficher_banniere' => 'yes' ],
        ] );

        $this->add_control( 'banner_titre', [
            'label'     => __( 'Titre bannière', 'sl-agences' ),
            'type'      => Controls_Manager::TEXTAREA,
            'default'   => 'Ne laissez pas passer l\'étincelle, profitez-en !',
            'rows'      => 2,
            'condition' => [ 'afficher_banniere' => 'yes' ],
        ] );

        $this->add_control( 'banner_sous_titre', [
            'label'     => __( 'Sous-titre', 'sl-agences' ),
            'type'      => Controls_Manager::TEXT,
            'default'   => 'Le décompte est lancé : vos packages dès 2 500 FCFA !',
            'condition' => [ 'afficher_banniere' => 'yes' ],
        ] );

        $this->add_control( 'banner_image', [
            'label'     => __( 'Image bannière', 'sl-agences' ),
            'type'      => Controls_Manager::MEDIA,
            'default'   => [ 'url' => '' ],
            'condition' => [ 'afficher_banniere' => 'yes' ],
        ] );

        $this->add_control( 'banner_bg', [
            'label'     => __( 'Couleur de fond', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#fdf6f0',
            'selectors' => [ '{{WRAPPER}} .slbp-banner' => 'background: {{VALUE}};' ],
            'condition' => [ 'afficher_banniere' => 'yes' ],
        ] );

        $this->end_controls_section();

        /* ── SECTION AFFICHAGE ── */
        $this->start_controls_section( 'section_display', [
            'label' => __( '⚙️ Paramètres grille', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'colonnes', [
            'label'   => __( 'Colonnes', 'sl-agences' ),
            'type'    => Controls_Manager::SELECT,
            'default' => '5',
            'options' => [ '3' => '3', '4' => '4', '5' => '5' ],
        ] );

        $this->add_control( 'offres_par_page', [
            'label'   => __( 'Offres par page', 'sl-agences' ),
            'type'    => Controls_Manager::NUMBER,
            'min'     => 5, 'max' => 40, 'step' => 5,
            'default' => 20,
        ] );

        $this->add_control( 'afficher_sidebar', [
            'label'        => __( 'Sidebar (filtres)', 'sl-agences' ),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ] );

        $this->add_control( 'afficher_recherche', [
            'label'        => __( 'Barre de recherche', 'sl-agences' ),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ] );

        $this->end_controls_section();

        /* ── STYLE ── */
        $this->start_controls_section( 'section_style', [
            'label' => __( '🎨 Couleur accent', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        $this->add_control( 'couleur_principale', [
            'label'   => __( 'Couleur principale', 'sl-agences' ),
            'type'    => Controls_Manager::COLOR,
            'default' => '#E91E63',
            'selectors' => [
                '{{WRAPPER}} .slbp-prix-apres'          => 'color: {{VALUE}};',
                '{{WRAPPER}} .slbp-badge-reduc'         => 'background: {{VALUE}};',
                '{{WRAPPER}} .slbp-pagination a.active' => 'background: {{VALUE}}; border-color: {{VALUE}};',
                '{{WRAPPER}} .slbp-check-list li.checked' => 'color: {{VALUE}};',
                '{{WRAPPER}} .slbp-cat-tag'             => 'color: {{VALUE}}; background: #fff0f5;',
                '{{WRAPPER}} .slbp-banner-label'        => 'background: {{VALUE}};',
                '{{WRAPPER}} .slbp-btn-filtre:hover'    => 'background: {{VALUE}};',
                '{{WRAPPER}} .slbp-price-field input:focus' => 'border-color: {{VALUE}};',
            ],
        ] );

        $this->end_controls_section();
    }

    /* ══════════════════════════════════════════
       RENDER
    ══════════════════════════════════════════ */
    protected function render() {
        $s        = $this->get_settings_for_display();
        $wid      = $this->get_id();
        $par_page = max( 5, (int) $s['offres_par_page'] );
        $colonnes = $s['colonnes'];
        $sidebar  = $s['afficher_sidebar'] === 'yes';

        // La page initiale ne charge que les cartes visibles. Les pages et
        // filtres suivants passent par l'API REST, au lieu d'envoyer toutes
        // les offres dans un conteneur caché.
        $initial_query = new \WP_Query( sl_bp_bons_plans_query_args( [
            'page'     => 1,
            'per_page' => $par_page,
            'orderby'  => 'recent',
            'actifs'   => true,
        ] ) );
        $posts         = $initial_query->posts;
        $total         = (int) $initial_query->found_posts;
        $total_pages   = (int) $initial_query->max_num_pages;
        $initial_count = count( $posts );

        // Le bouton panier de chaque carte a besoin du produit WooCommerce lié.
        // Précharger la correspondance évite une requête séparée pour chaque carte.
        if ( function_exists( 'sl_bp_preload_product_ids' ) ) {
            sl_bp_preload_product_ids( wp_list_pluck( $posts, 'ID' ) );
        }

        /* Collecter les termes officiels + prix max */
        $cats_dispo    = [];
        $agences_dispo = [];
        $prix_max_site = 0;

        $cat_terms = get_terms( [
            'taxonomy'   => 'sl_categorie_promo',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ] );
        if ( ! is_wp_error( $cat_terms ) ) {
            foreach ( $cat_terms as $t ) {
                $cats_dispo[ $t->term_id ] = $t->name;
            }
        }

        $agence_terms = get_terms( [
            'taxonomy'   => 'sl_agence_promo',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ] );
        if ( ! is_wp_error( $agence_terms ) ) {
            foreach ( $agence_terms as $t ) {
                $agences_dispo[ $t->slug ] = $t->name;
            }
        }

        if ( function_exists( 'sl_bp_bons_plans_max_price' ) ) {
            $prix_max_site = sl_bp_bons_plans_max_price();
        }

        $prix_max = max( 0, (int) ceil( $prix_max_site ) );
        ?>

        <div class="slbp-wrapper"
             id="slbp-<?php echo esc_attr( $wid ); ?>"
             data-par-page="<?php echo esc_attr( $par_page ); ?>"
             data-colonnes="<?php echo esc_attr( $colonnes ); ?>"
             data-endpoint="<?php echo esc_url( rest_url( 'santa-lucia/v1/bons-plans/web' ) ); ?>"
             data-total="<?php echo esc_attr( $total ); ?>"
             data-total-pages="<?php echo esc_attr( $total_pages ); ?>"
             data-page="1">

            <?php /* ══ BANNIÈRE ══════════════════════════════════════ */ ?>
            <?php if ( $s['afficher_banniere'] === 'yes' ) : ?>
            <div class="slbp-banner">
                <div class="slbp-banner-text">
                    <?php if ( $s['banner_label'] ) : ?>
                        <span class="slbp-banner-label"><?php echo esc_html( $s['banner_label'] ); ?></span>
                    <?php endif; ?>
                    <?php if ( $s['banner_titre'] ) : ?>
                        <h2 class="slbp-banner-title"><?php echo esc_html( $s['banner_titre'] ); ?></h2>
                    <?php endif; ?>
                    <?php if ( $s['banner_sous_titre'] ) : ?>
                        <p class="slbp-banner-sub"><?php echo esc_html( $s['banner_sous_titre'] ); ?></p>
                    <?php endif; ?>
                </div>
                <?php
                $banner_img_url = ! empty( $s['banner_image']['url'] ) ? $s['banner_image']['url'] : '';
                if ( $banner_img_url ) : ?>
                <div class="slbp-banner-img">
                    <img src="<?php echo esc_url( $banner_img_url ); ?>" alt="Bannière promotions">
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php /* ══ LAYOUT 2 COLONNES ══════════════════════════════ */ ?>
            <div class="slbp-layout <?php echo $sidebar ? '' : 'slbp-no-sidebar'; ?>">

                <?php if ( $sidebar ) : ?>
                <!-- ══ SIDEBAR ══════════════════════════════════════ -->
                <aside class="slbp-sidebar">
                    <div class="slbp-sidebar-header">
                        <span>Filtres</span>
                        <button type="button" class="slbp-close-sidebar" title="Fermer">×</button>
                    </div>

                    <!-- Filtre prix -->
                    <div class="slbp-sidebar-block">
                        <p class="slbp-sidebar-title">Filtre de prix</p>
                        <div class="slbp-price-row">
                            <div class="slbp-price-field">
                                <label for="slbp-pmin-<?php echo $wid; ?>">Prix Min</label>
                                <input type="number" id="slbp-pmin-<?php echo $wid; ?>"
                                       class="slbp-pmin" value="0" min="0" max="<?php echo $prix_max; ?>">
                            </div>
                            <div class="slbp-price-field">
                                <label for="slbp-pmax-<?php echo $wid; ?>">Prix Max</label>
                                <input type="number" id="slbp-pmax-<?php echo $wid; ?>"
                                       class="slbp-pmax" value="<?php echo $prix_max; ?>" min="0" max="<?php echo $prix_max; ?>">
                            </div>
                        </div>
                        <input type="range" class="slbp-price-range"
                               min="0" max="<?php echo $prix_max; ?>"
                               value="<?php echo $prix_max; ?>" step="100">
                        <span class="slbp-price-label">
                            prix 0 FCFA — <strong class="slbp-price-label-val"><?php echo number_format( $prix_max, 0, ',', ' ' ); ?></strong> FCFA
                        </span>
                        <button class="slbp-btn-filtre" type="button">Filtrer</button>
                    </div>

                    <!-- Catégories -->
                    <?php if ( ! empty( $cats_dispo ) ) : ?>
                    <div class="slbp-sidebar-block">
                        <p class="slbp-sidebar-title">Catégories</p>
                        <ul class="slbp-check-list" data-filter="cat">
                            <?php foreach ( $cats_dispo as $tid => $name ) : ?>
                            <li data-value="<?php echo esc_attr( $tid ); ?>">
                                <input type="checkbox" id="slbp-cat-<?php echo esc_attr( $wid . '-' . $tid ); ?>">
                                <label for="slbp-cat-<?php echo esc_attr( $wid . '-' . $tid ); ?>"><?php echo esc_html( $name ); ?></label>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <!-- Agences -->
                    <?php if ( ! empty( $agences_dispo ) ) : ?>
                    <div class="slbp-sidebar-block">
                        <p class="slbp-sidebar-title">Agences</p>
                        <div class="slbp-agence-ms" data-filter="agence">
                            <button type="button" class="slbp-agence-ms-toggle" aria-expanded="false">
                                <span class="slbp-agence-ms-label">Toutes les agences</span>
                                <span class="slbp-agence-ms-caret">▾</span>
                            </button>
                            <div class="slbp-agence-ms-panel" hidden>
                                <label class="slbp-agence-ms-option slbp-agence-ms-option-all">
                                    <input type="checkbox" class="slbp-agence-ms-all" value="" checked>
                                    <span>Toutes les agences</span>
                                </label>
                                <?php foreach ( $agences_dispo as $slug => $name ) : ?>
                                <label class="slbp-agence-ms-option">
                                    <input type="checkbox" class="slbp-agence-ms-choice" value="<?php echo esc_attr( $slug ); ?>" data-label="<?php echo esc_attr( $name ); ?>">
                                    <span><?php echo esc_html( $name ); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <a class="slbp-pdf-btn"
                           href="<?php echo esc_url( add_query_arg( 'sl_bp_pdf', '1', home_url( '/' ) ) ); ?>"
                           data-base="<?php echo esc_url( home_url( '/' ) ); ?>">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            Télécharger en PDF
                        </a>
                        <p class="slbp-pdf-hint">PDF des offres selon les agences cochées (toutes si aucune).</p>
                    </div>
                    <?php endif; ?>

                </aside>
                <?php endif; ?>

                <!-- Overlay mobile -->
                <div class="slbp-sidebar-overlay"></div>

                <!-- ══ COLONNE PRINCIPALE ════════════════════════════ -->
                <div class="slbp-main">

                    <!-- ── BARRE DE TRI — identique page Promotions ──
                         [ Affichage de 1-20 sur 21 résultats ]   [Sort:▼][Show:20 Items▼][□□][≡] -->
                    <div class="slbp-sortbar">

                        <!-- Compteur gauche -->
                        <span class="slbp-sortbar-count">
                            Affichage de <strong class="slbp-range-from"><?php echo $initial_count ? 1 : 0; ?></strong>–<strong class="slbp-range-to"><?php echo esc_html( $initial_count ); ?></strong>
                            sur <strong class="slbp-total"><?php echo esc_html( $total ); ?></strong> résultats
                        </span>

                        <!-- Groupe droite -->
                        <div class="slbp-sortbar-right">

                            <!-- Filtre + Trier buttons (mobile, identique page Promotions) -->
                            <a href="#" class="slbp-mobile-filter-btn">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                                Filtre <span class="slbp-filter-badge"></span>
                            </a>
                            <button type="button" class="slbp-sort-mobile-btn">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M7 12h10M11 18h2"/></svg>
                                Trier
                            </button>

                            <?php if ( $s['afficher_recherche'] === 'yes' ) : ?>
                            <div class="slbp-search-bar">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                </svg>
                                <input type="text" class="slbp-search" placeholder="Rechercher...">
                            </div>
                            <?php endif; ?>

                            <!-- Sort: -->
                            <div class="slbp-sortbar-group">
                                <label>Trier :</label>
                                <select class="slbp-sort">
                                    <option value="recent">Tri du plus récent au plus ancien</option>
                                    <option value="reduc">Plus grosse réduction</option>
                                    <option value="prix_asc">Prix : croissant</option>
                                    <option value="prix_desc">Prix : décroissant</option>
                                </select>
                            </div>

                            <!-- Show: -->
                            <div class="slbp-sortbar-group">
                                <label>Afficher :</label>
                                <select class="slbp-per-page-sel">
                                    <option value="10">10 Items</option>
                                    <option value="20" <?php selected( $par_page, 20 ); ?>>20 Items</option>
                                    <option value="40">40 Items</option>
                                </select>
                            </div>

                            <!-- Icônes vue grille / liste -->
                            <div class="slbp-view-btns">
                                <button class="slbp-view-btn active" data-view="grid" title="Vue grille">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <rect x="0" y="0" width="6" height="6" rx="1"/>
                                        <rect x="10" y="0" width="6" height="6" rx="1"/>
                                        <rect x="0" y="10" width="6" height="6" rx="1"/>
                                        <rect x="10" y="10" width="6" height="6" rx="1"/>
                                    </svg>
                                </button>
                                <button class="slbp-view-btn" data-view="list" title="Vue liste">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <rect x="0" y="1" width="4" height="4" rx="1"/>
                                        <rect x="6" y="2" width="10" height="2" rx="1"/>
                                        <rect x="0" y="7" width="4" height="4" rx="1"/>
                                        <rect x="6" y="8" width="10" height="2" rx="1"/>
                                        <rect x="0" y="13" width="4" height="2" rx="1"/>
                                        <rect x="6" y="13" width="10" height="2" rx="1"/>
                                    </svg>
                                </button>
                            </div>

                        </div><!-- .slbp-sortbar-right -->
                    </div><!-- .slbp-sortbar -->

                    <!-- Panel de tri mobile (style grogin shop-sorting-wrapper) -->
                    <div class="slbp-mobile-sort-panel">
                        <span class="slbp-msp-label">Trier par :</span>
                        <select class="slbp-sort slbp-sort-mobile">
                            <option value="recent">Plus récent</option>
                            <option value="reduc">Plus grosse réduction</option>
                            <option value="prix_asc">Prix croissant</option>
                            <option value="prix_desc">Prix décroissant</option>
                        </select>
                    </div>

                    <!-- Les seules cartes initiales sont rendues côté serveur :
                         pas de catalogue complet caché dans le DOM. -->
                    <div class="slbp-grid slbp-cols-<?php echo esc_attr( $colonnes ); ?>"
                         id="slbp-grid-<?php echo esc_attr( $wid ); ?>"
                         aria-busy="false">
                        <?php foreach ( $posts as $p ) : ?>
                            <?php echo sl_bp_render_bon_plan_card_html( $p ); ?>
                        <?php endforeach; ?>
                    </div>

                    <p class="slbp-load-status" role="status" aria-live="polite"></p>

                    <!-- Message vide -->
                    <div class="slbp-empty" id="slbp-empty-<?php echo esc_attr( $wid ); ?>"<?php echo $initial_count ? ' style="display:none;"' : ''; ?>>
                        Aucune offre ne correspond à vos critères.
                    </div>

                    <!-- Pagination -->
                    <div class="slbp-pagination" id="slbp-pag-<?php echo esc_attr( $wid ); ?>"></div>

                </div><!-- .slbp-main -->
            </div><!-- .slbp-layout -->
        </div><!-- .slbp-wrapper -->
        <?php
    }
}
