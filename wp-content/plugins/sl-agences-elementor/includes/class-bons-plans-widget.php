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
        $today    = current_time( 'Y-m-d' );
        $par_page = max( 5, (int) $s['offres_par_page'] );
        $colonnes = $s['colonnes'];
        $sidebar  = $s['afficher_sidebar'] === 'yes';

        /* Requête WP */
        $posts = get_posts( [
            'post_type'      => 'sl_bon_plan',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                'relation' => 'OR',
                [ 'key' => '_sl_bp_date_fin', 'value' => $today, 'compare' => '>=', 'type' => 'DATE' ],
                [ 'key' => '_sl_bp_date_fin', 'value' => '', 'compare' => '=' ],
                [ 'key' => '_sl_bp_date_fin', 'compare' => 'NOT EXISTS' ],
            ],
        ] );

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

        foreach ( $posts as $p ) {
            $pa = (float) get_post_meta( $p->ID, '_sl_bp_prix_apres', true );
            if ( $pa > $prix_max_site ) $prix_max_site = $pa;
        }

        $prix_max = max( 0, (int) ceil( $prix_max_site ) );
        ?>

        <div class="slbp-wrapper"
             id="slbp-<?php echo esc_attr( $wid ); ?>"
             data-par-page="<?php echo esc_attr( $par_page ); ?>"
             data-colonnes="<?php echo esc_attr( $colonnes ); ?>">

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
                            Affichage de <strong class="slbp-range-from">1</strong>–<strong class="slbp-range-to">0</strong>
                            sur <strong class="slbp-total">0</strong> résultats
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

                    <!-- Toutes les cartes (masquées — JS gère l'affichage) -->
                    <div class="slbp-all-cards" style="display:none !important;">
                        <?php foreach ( $posts as $p ) :
                            $stock_actif = get_post_meta( $p->ID, '_sl_bp_stock_actif', true );
                            $stock_qty   = get_post_meta( $p->ID, '_sl_bp_stock_qty', true );
                            // Masquer l'offre si la limite de stock est active ET la quantité renseignée atteint 0.
                            if ( $stock_actif === '1' && $stock_qty !== '' && (int) $stock_qty <= 0 ) {
                                continue;
                            }
                            $prix_av     = (float) get_post_meta( $p->ID, '_sl_bp_prix_avant', true );
                            $prix_ap     = (float) get_post_meta( $p->ID, '_sl_bp_prix_apres', true );
                            $reduc       = (int)   get_post_meta( $p->ID, '_sl_bp_reduction_pct', true );
                            $badge       = get_post_meta( $p->ID, '_sl_bp_badge_type', true );
                            $badge_labels = [
                                'flash'     => 'Flash',
                                'nouveau'   => 'Nouveau',
                                'top-vente' => 'Top Vente',
                                'exclusif'  => 'Exclusif',
                            ];
                            $badge_label = $badge_labels[ $badge ] ?? ucfirst( str_replace( '-', ' ', $badge ) );
                            $date_fin    = get_post_meta( $p->ID, '_sl_bp_date_fin', true );
                            $img_url     = get_the_post_thumbnail_url( $p->ID, 'medium' );

                            $c_terms     = wp_get_object_terms( $p->ID, 'sl_categorie_promo' );
                            if ( is_wp_error( $c_terms ) ) $c_terms = [];
                            $cat_ids     = implode( ',', wp_list_pluck( $c_terms, 'term_id' ) );
                            $cat_name    = ! empty( $c_terms ) ? $c_terms[0]->name : '';

                            $a_terms     = wp_get_object_terms( $p->ID, 'sl_agence_promo' );
                            if ( is_wp_error( $a_terms ) ) $a_terms = [];
                            $agence_slug = ! empty( $a_terms ) ? $a_terms[0]->slug : '';
                            $agence_name = ! empty( $a_terms ) ? $a_terms[0]->name : '';
                        ?>
                            <a class="slbp-card"
                                 href="<?php echo esc_url( get_permalink( $p->ID ) ); ?>"
                                 data-nom="<?php echo esc_attr( strtolower( $p->post_title ) ); ?>"
                                 data-cat="<?php echo esc_attr( $cat_ids ); ?>"
                                 data-agence="<?php echo esc_attr( $agence_slug ); ?>"
                                 data-prix-ap="<?php echo esc_attr( $prix_ap ); ?>"
                                 data-reduc="<?php echo esc_attr( $reduc ); ?>"
                                 data-date="<?php echo esc_attr( $p->post_date ); ?>">

                                <div class="slbp-card-img-wrap">
                                    <?php if ( $img_url ) : ?>
                                        <img src="<?php echo esc_url( $img_url ); ?>"
                                             alt="<?php echo esc_attr( $p->post_title ); ?>" loading="lazy">
                                    <?php else : ?>
                                        <div class="slbp-no-img">🛒</div>
                                    <?php endif; ?>

                                     <?php if ( $reduc > 0 ) : ?>
                                         <span class="slbp-badge-reduc">-<?php echo $reduc; ?>%</span>
                                     <?php endif; ?>

                                    <?php if ( $badge ) : ?>
                                        <span class="slbp-badge-type slbp-badge-<?php echo esc_attr( $badge ); ?>">
                                            <?php echo esc_html( $badge_label ); ?>
                                        </span>
                                    <?php endif; ?>

                                     <div class="slbp-eye-btn" title="Voir l'offre">👁</div>

                                     <button type="button" class="slbp-share-btn"
                                             data-titre="<?php echo esc_attr( $p->post_title ); ?>"
                                             data-prix="<?php echo esc_attr( $prix_ap > 0 ? number_format( $prix_ap, 0, ',', ' ' ) . ' FCFA' : '' ); ?>"
                                             aria-label="Partager ce bon plan" title="Partager">
                                         <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                                     </button>
                                 </div>

                                <div class="slbp-card-body">
                                    <div class="slbp-card-meta">
                                        <?php if ( $agence_name ) : ?>
                                            <span class="slbp-agence-tag"><?php echo esc_html( $agence_name ); ?></span>
                                        <?php endif; ?>
                                        <?php if ( $cat_name ) : ?>
                                            <span class="slbp-cat-tag"><?php echo esc_html( $cat_name ); ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <h3 class="slbp-titre"><?php echo esc_html( $p->post_title ); ?></h3>

                                    <div class="slbp-prix-wrap">
                                        <?php if ( $prix_ap > 0 ) : ?>
                                        <span class="slbp-prix-apres">
                                            <?php echo number_format( $prix_ap, 0, ',', ' ' ); ?> FCFA
                                        </span>
                                        <?php endif; ?>
                                        <?php if ( $prix_av > 0 ) : ?>
                                            <span class="slbp-prix-avant">
                                                <?php echo number_format( $prix_av, 0, ',', ' ' ); ?> FCFA
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ( $date_fin ) : ?>
                                        <p class="slbp-date-fin">
                                            Valable jusqu'au <?php echo date_i18n( 'd M Y', strtotime( $date_fin ) ); ?>
                                        </p>
                                    <?php endif; ?>

                                    <?php if ( $stock_actif === '1' ) : ?>
                                        <p class="slbp-stock-mention">Dans la limite des stocks disponibles</p>
                                    <?php endif; ?>
                                </div>

                                <?php if ( function_exists( 'sl_bp_cart_button_html' ) ) echo sl_bp_cart_button_html( $p->ID ); ?>

                            </a><!-- .slbp-card -->
                        <?php endforeach; ?>
                    </div><!-- .slbp-all-cards -->

                    <!-- Grille visible (JS injecte les cartes ici) -->
                    <div class="slbp-grid slbp-cols-<?php echo esc_attr( $colonnes ); ?>"
                         id="slbp-grid-<?php echo esc_attr( $wid ); ?>"></div>

                    <!-- Message vide -->
                    <div class="slbp-empty" id="slbp-empty-<?php echo esc_attr( $wid ); ?>" style="display:none;">
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
