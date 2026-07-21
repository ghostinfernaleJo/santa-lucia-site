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

    $meta_q = [];
    if ( $agence ) {
        $meta_q[] = sl_ff_agency_meta_query( $agence );
    } else {
        $meta_q[] = sl_ff_day_meta_query( $today_jour );
    }

    $repas = get_posts( [
        'post_type'      => 'sl_repas',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'meta_query'     => $meta_q,
    ] );
    if ( $agence && function_exists( 'sl_ff_filter_repas_available_for_agence' ) ) {
        $repas = sl_ff_filter_repas_available_for_agence( $repas, $agence, $today_jour );
    }
    $repas = sl_ff_dedupe_repas_by_title( $repas, $agence );

    $agence_term = $agence ? get_term_by( 'slug', $agence, 'sl_agence_promo' ) : null;
    $agence_nom  = ( $agence_term && ! is_wp_error( $agence_term ) )
                    ? sl_ff_agency_name( $agence_term->name ) : '';
    $uid         = 'slffm-' . substr( md5( $agence . $today ), 0, 6 );
    $share_url   = add_query_arg( [ 'agence' => $agence, 'vue' => 'fastfood' ], get_permalink() );
    $share_text  = 'Menu Fast Food du ' . date_i18n( 'd/m/Y', strtotime( $today ) ) . ( $agence_nom ? ' - ' . $agence_nom : '' );

    ob_start();
    echo sl_ff_prix_css(); ?>
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
                        $promo = sl_ff_get_promo_info( $item->ID, $agence );
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
                            <?php echo sl_ff_prix_html( $promo ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                            <?php if ( $agence && function_exists( 'sl_ff_order_buttons_html' ) ) : ?>
                            <?php echo sl_ff_order_buttons_html( $item->ID, $agence, $agence_nom ?: $agence, $item->post_title, $promo ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
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

    // Compter les repas disponibles aujourd'hui par agence.
    // 18 requêtes coûteuses → mises en cache (même invalidation que le menu).
    $ckey   = 'sl_ff_counts_' . sl_ff_menu_cache_ver() . '_' . md5( current_time( 'Y-m-d' ) );
    $counts = get_transient( $ckey );
    if ( ! is_array( $counts ) ) {
        $counts = [];
        foreach ( $agences as $a ) {
            $posts_for_agence = get_posts( [
                'post_type'      => 'sl_repas',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => [ sl_ff_agency_meta_query( $a->slug ) ],
            ] );
            $dispo_posts = function_exists( 'sl_ff_filter_repas_available_for_agence' )
                ? sl_ff_filter_repas_available_for_agence( array_map( 'get_post', $posts_for_agence ), $a->slug, $today_jour )
                : array_map( 'get_post', $posts_for_agence );
            $dispo_posts = sl_ff_dedupe_repas_by_title( $dispo_posts, $a->slug );
            $counts[ $a->slug ] = count( $dispo_posts );
        }
        set_transient( $ckey, $counts, 6 * HOUR_IN_SECONDS );
    }

    ob_start();
    echo sl_ff_prix_css(); ?>
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
    $today_jour  = sl_ff_today_jour();
    $agence_term = $agence ? get_term_by( 'slug', $agence, 'sl_agence_promo' ) : null;
    $agence_nom  = ( $agence_term && ! is_wp_error( $agence_term ) ) ? sl_ff_agency_name( $agence_term->name ) : $agence;

    // Cache : ce HTML est demandé par chaque visiteur via admin-ajax
    // (non cacheable par Varnish). Invalidé par bump de version.
    $ckey   = sl_ff_menu_cache_key( $agence );
    $cached = get_transient( $ckey );
    if ( $cached !== false ) {
        return $cached;
    }

    $repas = get_posts( [
        'post_type'      => 'sl_repas',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'meta_query'     => [ sl_ff_agency_meta_query( $agence ) ],
    ] );
    if ( function_exists( 'sl_ff_filter_repas_available_for_agence' ) ) {
        $repas = sl_ff_filter_repas_available_for_agence( $repas, $agence, $today_jour );
    }
    $repas = sl_ff_dedupe_repas_by_title( $repas, $agence );

    if ( empty( $repas ) ) {
        $vide = '<div class="sl-ff-vide"><span class="sl-ff-vide-icon">&#127869;</span><p>Aucun plat disponible aujourd&#39;hui pour cette agence.</p></div>';
        set_transient( $ckey, $vide, 6 * HOUR_IN_SECONDS );
        return $vide;
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
            $promo = sl_ff_get_promo_info( $item->ID, $agence );

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
            $html .= sl_ff_prix_html( $promo );
            if ( $agence && function_exists( 'sl_ff_order_buttons_html' ) ) {
                $html .= sl_ff_order_buttons_html( $item->ID, $agence, $agence_nom, $item->post_title, $promo );
            }
            $html .= '</div>';
            $html .= '<span class="sl-ff-dispo-ok">&#10003; Disponible</span>';
            $html .= '</div>';
        }
        $html .= '</div></div>';
    }

    set_transient( $ckey, $html, 6 * HOUR_IN_SECONDS );
    return $html;
}

/**
 * CSS des prix, inline (une fois par page) : le fichier CSS front est
 * caché par Varnish PAR NOM DE FICHIER, le modifier ne suffirait pas.
 */
function sl_ff_prix_css() {
    static $done = false;
    if ( $done ) return '';
    $done = true;
    return '<style>
    .sl-ff-prix{margin:6px 0 0;font-size:15px;display:flex;align-items:baseline;gap:8px;flex-wrap:wrap;}
    .sl-ff-prix-avant{color:#9aa0a6;font-size:13px;text-decoration:line-through;}
    .sl-ff-prix-promo{color:#e91e8c;font-weight:800;}
    .sl-ff-prix-normal{color:#1d2327;font-weight:700;}
    .sl-ff-view-list .sl-ff-prix{margin:2px 0 0;}
    </style>';
}

/**
 * Bloc prix d'un plat : prix barré + prix promo si promo active,
 * sinon prix normal seul. Rien si aucun prix saisi.
 */
function sl_ff_prix_html( $promo ) {
    if ( $promo['est_promo'] && $promo['prix_promo'] > 0 ) {
        $avant = $promo['prix'] > 0
            ? '<del class="sl-ff-prix-avant">' . esc_html( sl_ff_format_prix( $promo['prix'] ) ) . '</del> '
            : '';
        return '<p class="sl-ff-prix">' . $avant
             . '<strong class="sl-ff-prix-promo">' . esc_html( sl_ff_format_prix( $promo['prix_promo'] ) ) . '</strong></p>';
    }
    if ( $promo['prix'] > 0 ) {
        return '<p class="sl-ff-prix"><strong class="sl-ff-prix-normal">' . esc_html( sl_ff_format_prix( $promo['prix'] ) ) . '</strong></p>';
    }
    return '';
}

/**
 * Dedoublonne une liste de repas par NOM de plat (normalise).
 * La base contient parfois plusieurs fiches publiees pour le meme plat
 * (imports successifs) : cote client on n'en affiche qu'UNE. On garde la
 * fiche « maitresse » : celle qui a un prix pour l'agence, puis celle
 * rattachee au plus d'agences, puis la plus ancienne (ID le plus petit).
 */
function sl_ff_dedupe_repas_by_title( $repas, $agence = '' ) {
    $repas = array_values( array_filter( (array) $repas, function ( $r ) {
        return $r && isset( $r->ID, $r->post_title );
    } ) );
    if ( count( $repas ) < 2 ) {
        return $repas;
    }

    // Ordre de priorite : la meilleure fiche de chaque nom gagne.
    $ranked = $repas;
    usort( $ranked, function ( $a, $b ) use ( $agence ) {
        if ( function_exists( 'sl_ff_get_agence_prix' ) ) {
            $pa = sl_ff_get_agence_prix( $a->ID, $agence );
            $pb = sl_ff_get_agence_prix( $b->ID, $agence );
            $ha = ( (int) ( $pa['prix'] ?? 0 ) > 0 ) ? 1 : 0;
            $hb = ( (int) ( $pb['prix'] ?? 0 ) > 0 ) ? 1 : 0;
            if ( $ha !== $hb ) return $hb - $ha;
        }
        if ( function_exists( 'sl_ff_post_agence_slugs' ) ) {
            $na = count( sl_ff_post_agence_slugs( $a->ID ) );
            $nb = count( sl_ff_post_agence_slugs( $b->ID ) );
            if ( $na !== $nb ) return $nb - $na;
        }
        return $a->ID - $b->ID;
    } );

    $keep = [];
    foreach ( $ranked as $r ) {
        $k = function_exists( 'sl_ff_norm_txt' ) ? sl_ff_norm_txt( $r->post_title ) : mb_strtolower( trim( $r->post_title ) );
        if ( isset( $keep[ $k ] ) ) continue;
        $keep[ $k ] = $r->ID;
    }
    $keep_ids = array_flip( $keep );   // ID => k

    // On restitue la liste dans l'ordre d'origine (par titre), sans les doublons.
    $out = [];
    foreach ( $repas as $r ) {
        if ( isset( $keep_ids[ $r->ID ] ) ) {
            $out[] = $r;
        }
    }
    return $out;
}
