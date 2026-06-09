<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ==========================================================================
   SHORTCODE [sl_fastfood_menu agence="slug"]
   Affiche le menu du jour d'une agence avec toggle card/liste + partage.
   ========================================================================== */
add_shortcode( 'sl_fastfood_menu', 'sl_ff_shortcode' );
function sl_ff_shortcode( $atts ) {
    $atts       = shortcode_atts( [ 'agence' => '', 'titre' => '' ], $atts, 'sl_fastfood_menu' );
    $agence     = sanitize_text_field( $atts['agence'] );
    $today      = current_time( 'Y-m-d' );
    $today_jour = sl_ff_today_jour();

    $meta_q = [
        'relation' => 'AND',
        sl_ff_day_meta_query( $today_jour ),
    ];
    if ( $agence ) {
        $meta_q[] = sl_ff_agency_meta_query( $agence );
    }

    $repas = get_posts( [
        'post_type'      => 'sl_repas',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'meta_query'     => $meta_q,
    ] );

    $agence_term = $agence ? get_term_by( 'slug', $agence, 'sl_agence_promo' ) : null;
    $agence_nom  = ( $agence_term && ! is_wp_error( $agence_term ) )
                    ? sl_ff_agency_name( $agence_term->name ) : '';
    $uid         = 'slffm-' . substr( md5( $agence . $today ), 0, 6 );
    $share_url   = add_query_arg( [ 'agence' => $agence, 'vue' => 'fastfood' ], get_permalink() );
    $share_text  = 'Menu Fast Food du ' . date_i18n( 'd/m/Y', strtotime( $today ) ) . ( $agence_nom ? ' - ' . $agence_nom : '' );

    ob_start(); ?>
    <div class="sl-ff-wrap" id="<?php echo esc_attr( $uid ); ?>" data-agence="<?php echo esc_attr( $agence ); ?>">

        <!-- En-tete -->
        <div class="sl-ff-header">
            <div class="sl-ff-header-left">
                <span class="sl-ff-icon">&#127869;</span>
                <div>
                    <h3 class="sl-ff-titre"><?php echo $agence_nom ? esc_html( $agence_nom ) : 'Fast Food'; ?></h3>
                    <p class="sl-ff-date">Menu Journalier</p>
                </div>
            </div>
            <div class="sl-ff-toolbar">
                <?php if ( ! empty( $repas ) ) : ?>
                <div class="sl-ff-view-toggle" role="group" aria-label="Vue">
                    <button class="sl-ff-btn-view active" data-view="cards" aria-pressed="true" title="Vue cartes">Cartes</button>
                    <button class="sl-ff-btn-view" data-view="list" aria-pressed="false" title="Vue liste">Liste</button>
                </div>
                <button class="sl-ff-share-btn"
                        data-url="<?php echo esc_attr( $share_url ); ?>"
                        data-text="<?php echo esc_attr( $share_text ); ?>"
                        title="Partager">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                    Partager
                </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ( empty( $repas ) ) : ?>
        <div class="sl-ff-vide">
            <span class="sl-ff-vide-icon">&#127869;</span>
            <p>Aucun plat disponible aujourd&#39;hui.</p>
            <small>Revenez demain ou consultez une autre agence.</small>
        </div>
        <?php else :
            $grouped = [];
            foreach ( $repas as $r ) {
                $cats = wp_get_post_terms( $r->ID, 'sl_repas_cat' );
                $cat  = ( ! empty( $cats ) && ! is_wp_error( $cats ) )
                        ? sl_ff_cat_display( $cats[0]->name ) : 'Menu du jour';
                $grouped[ $cat ][] = $r;
            }
            ksort( $grouped );
        ?>
        <div class="sl-ff-content sl-ff-view-cards" id="<?php echo esc_attr( $uid ); ?>-content">
            <?php foreach ( $grouped as $cat_name => $items ) : ?>
            <div class="sl-ff-cat-section">
                <h4 class="sl-ff-cat-titre"><?php echo esc_html( $cat_name ); ?></h4>
                <div class="sl-ff-items">
                    <?php foreach ( $items as $item ) :
                        $thumb = function_exists( 'sl_ff_item_image_url' ) ? sl_ff_item_image_url( $item->ID, 'large' ) : get_the_post_thumbnail_url( $item->ID, 'large' );
                        $desc  = wp_trim_words( $item->post_content, 15 );
                        $promo = sl_ff_get_promo_info( $item->ID );
                    ?>
                    <div class="sl-ff-item<?php echo $promo['est_promo'] ? ' sl-ff-item--promo' : ''; ?>">
                        <?php if ( $thumb ) : ?>
                        <div class="sl-ff-item-img" style="background-image:url('<?php echo esc_url( $thumb ); ?>')">
                            <?php if ( $promo['est_promo'] && $promo['pct_reduction'] > 0 ) : ?>
                            <span class="sl-ff-promo-badge">-<?php echo (int) $promo['pct_reduction']; ?>%</span>
                            <?php endif; ?>
                        </div>
                        <?php elseif ( $promo['est_promo'] && $promo['pct_reduction'] > 0 ) : ?>
                        <div class="sl-ff-item-img sl-ff-item-img--empty">
                            <span class="sl-ff-promo-badge">-<?php echo (int) $promo['pct_reduction']; ?>%</span>
                        </div>
                        <?php endif; ?>
                        <div class="sl-ff-item-body">
                            <h5 class="sl-ff-item-titre"><?php echo esc_html( $item->post_title ); ?></h5>
                            <?php if ( $desc ) : ?>
                            <p class="sl-ff-item-desc"><?php echo esc_html( $desc ); ?></p>
                            <?php endif; ?>
                        </div>
                        <span class="sl-ff-dispo-ok">&#10003; Disponible</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
    <?php
    return ob_get_clean();
}


/* ==========================================================================
   SHORTCODE [sl_fastfood_browser]
   Vue globale : sidebar agences + menu du jour en cards/liste + partage.
   ========================================================================== */
add_shortcode( 'sl_fastfood_browser', 'sl_ff_browser_shortcode' );
function sl_ff_browser_shortcode( $atts ) {
    $atts       = shortcode_atts( [ 'agence_defaut' => '' ], $atts, 'sl_fastfood_browser' );
    $today      = current_time( 'Y-m-d' );
    $today_jour = sl_ff_today_jour();

    $agences = get_terms( [ 'taxonomy' => 'sl_agence_promo', 'hide_empty' => false, 'orderby' => 'name' ] );
    if ( is_wp_error( $agences ) || empty( $agences ) ) {
        return '<p>Aucune agence configuree.</p>';
    }

    $agence_active = sanitize_text_field( $_GET['agence'] ?? $atts['agence_defaut'] ?? '' );
    if ( ! $agence_active && ! empty( $agences ) ) {
        $agence_active = $agences[0]->slug;
    }

    // Compter les repas disponibles aujourd'hui par agence (via _sl_ff_jours)
    $counts = [];
    foreach ( $agences as $a ) {
        $n = count( get_posts( [
            'post_type'      => 'sl_repas',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'AND',
                sl_ff_day_meta_query( $today_jour ),
                sl_ff_agency_meta_query( $a->slug ),
            ],
        ] ) );
        $counts[ $a->slug ] = $n;
    }

    ob_start(); ?>
    <div class="sl-ff-browser" data-ajaxurl="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>"
         data-nonce="<?php echo esc_attr( wp_create_nonce( 'sl_ff_get_menu' ) ); ?>">

        <!-- Sidebar agences -->
        <aside class="sl-ff-sidebar">
            <div class="sl-ff-sidebar-header">
                <span>Nos Agences</span>
            </div>
            <div class="sl-ff-sidebar-date">
                Menu Journalier
            </div>
            <ul class="sl-ff-agency-list" id="sl-ff-agency-list">
                <?php foreach ( $agences as $a ) :
                    $nom    = sl_ff_agency_name( $a->name );
                    $count  = $counts[ $a->slug ] ?? 0;
                    $active = ( $a->slug === $agence_active ) ? ' class="active"' : '';
                ?>
                <li>
                    <button<?php echo $active; ?>
                        data-agence="<?php echo esc_attr( $a->slug ); ?>"
                        data-nom="<?php echo esc_attr( $nom ); ?>">
                        <span class="sl-ff-agency-name"><?php echo esc_html( $nom ); ?></span>
                        <?php if ( $count > 0 ) : ?>
                        <span class="sl-ff-agency-badge"><?php echo $count; ?></span>
                        <?php endif; ?>
                    </button>
                </li>
                <?php endforeach; ?>
            </ul>
        </aside>

        <!-- Contenu principal -->
        <main class="sl-ff-browser-main">
            <div class="sl-ff-browser-topbar">
                <h3 class="sl-ff-browser-titre" id="sl-ff-browser-titre">
                    <span class="sl-ff-browser-agence-nom">
                    <?php
                    $t = get_term_by( 'slug', $agence_active, 'sl_agence_promo' );
                    echo $t ? esc_html( sl_ff_agency_name( $t->name ) ) : '';
                    ?>
                    </span>
                </h3>
                <div class="sl-ff-browser-actions">
                    <div class="sl-ff-menu-search-wrap">
                        <input type="search" class="sl-ff-menu-search" id="sl-ff-menu-search"
                               placeholder="Rechercher un plat..." autocomplete="off">
                    </div>
                    <div class="sl-ff-view-toggle" role="group">
                        <button class="sl-ff-btn-view active" data-view="cards" title="Cartes">Cartes</button>
                        <button class="sl-ff-btn-view" data-view="list" title="Liste">Liste</button>
                    </div>
                    <button class="sl-ff-share-btn sl-ff-browser-share"
                            data-text="<?php echo esc_attr( 'Menu Fast Food du ' . date_i18n( 'd/m/Y', strtotime( $today ) ) ); ?>"
                            title="Partager">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                        Partager
                    </button>
                </div>
            </div>

            <div class="sl-ff-browser-content sl-ff-view-cards" id="sl-ff-browser-content">
                <?php echo sl_ff_render_menu_html( $agence_active ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            </div>

            <div class="sl-ff-browser-loading" id="sl-ff-browser-loading" style="display:none;">
                <div class="sl-ff-spinner"></div>
                <span>Chargement...</span>
            </div>
        </main>

    </div>
    <?php
    return ob_get_clean();
}


/* ==========================================================================
   HELPER : genere le HTML des repas pour une agence (menu du jour)
   ========================================================================== */
function sl_ff_render_menu_html( $agence, $date = '' ) {
    $today_jour = sl_ff_today_jour();

    $repas = get_posts( [
        'post_type'      => 'sl_repas',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'meta_query'     => [
            'relation' => 'AND',
            sl_ff_day_meta_query( $today_jour ),
            sl_ff_agency_meta_query( $agence ),
        ],
    ] );

    if ( empty( $repas ) ) {
        return '<div class="sl-ff-vide"><span class="sl-ff-vide-icon">&#127869;</span><p>Aucun plat disponible aujourd&#39;hui pour cette agence.</p></div>';
    }

    $grouped = [];
    foreach ( $repas as $r ) {
        $cats = wp_get_post_terms( $r->ID, 'sl_repas_cat' );
        $cat  = ( ! empty( $cats ) && ! is_wp_error( $cats ) )
                ? sl_ff_cat_display( $cats[0]->name ) : 'Menu du jour';
        $grouped[ $cat ][] = $r;
    }
    ksort( $grouped );

    $html = '';
    foreach ( $grouped as $cat_name => $items ) {
        $html .= '<div class="sl-ff-cat-section">';
        $html .= '<h4 class="sl-ff-cat-titre">' . esc_html( $cat_name ) . '</h4>';
        $html .= '<div class="sl-ff-items">';
        foreach ( $items as $item ) {
            $thumb = function_exists( 'sl_ff_item_image_url' ) ? sl_ff_item_image_url( $item->ID, 'large' ) : get_the_post_thumbnail_url( $item->ID, 'large' );
            $desc  = wp_trim_words( $item->post_content, 15 );
            $promo = sl_ff_get_promo_info( $item->ID );

            $promo_class = $promo['est_promo'] ? ' sl-ff-item--promo' : '';
            $html .= '<div class="sl-ff-item' . $promo_class . '">';

            if ( $thumb ) {
                $badge = '';
                if ( $promo['est_promo'] && $promo['pct_reduction'] > 0 ) {
                    $badge = '<span class="sl-ff-promo-badge">-' . (int) $promo['pct_reduction'] . '%</span>';
                }
                $html .= '<div class="sl-ff-item-img" style="background-image:url(\'' . esc_url( $thumb ) . '\')">' . $badge . '</div>';
            } elseif ( $promo['est_promo'] && $promo['pct_reduction'] > 0 ) {
                $html .= '<div class="sl-ff-item-img sl-ff-item-img--empty">';
                $html .= '<span class="sl-ff-promo-badge">-' . (int) $promo['pct_reduction'] . '%</span>';
                $html .= '</div>';
            }

            $html .= '<div class="sl-ff-item-body">';
            $html .= '<h5 class="sl-ff-item-titre">' . esc_html( $item->post_title ) . '</h5>';
            if ( $desc ) {
                $html .= '<p class="sl-ff-item-desc">' . esc_html( $desc ) . '</p>';
            }
            $html .= '</div>';
            $html .= '<span class="sl-ff-dispo-ok">&#10003; Disponible</span>';
            $html .= '</div>';
        }
        $html .= '</div></div>';
    }
    return $html;
}
