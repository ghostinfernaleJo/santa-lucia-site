<?php
/**
 * Bon Plan -> Ajouter au panier
 * Bouton d'achat AJAX sur les cartes Bons Plans (widget + carrousel).
 * Le bon plan a un produit WooCommerce lié (_sl_bp_source_id) ; on l'ajoute
 * au panier via l'endpoint natif ?wc-ajax=add_to_cart, sans quitter la page.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** Cache partagé bon plan => produit WooCommerce pour la requête courante. */
function &sl_bp_product_id_cache() {
    static $cache = [];
    return $cache;
}

/**
 * Précharge en une seule requête les produits liés à une liste de bons plans.
 * Évite une requête SQL par carte sur les grandes pages de promotions.
 */
function sl_bp_preload_product_ids( $bon_plan_ids ) {
    $cache = &sl_bp_product_id_cache();
    $ids   = array_values( array_unique( array_filter( array_map( 'intval', (array) $bon_plan_ids ) ) ) );
    if ( ! $ids ) return;

    $missing = array_values( array_diff( $ids, array_map( 'intval', array_keys( $cache ) ) ) );
    if ( ! $missing ) return;

    // Mémoriser aussi les absences afin de ne pas relancer une requête ensuite.
    foreach ( $missing as $bon_plan_id ) {
        $cache[ $bon_plan_id ] = 0;
    }

    $products = get_posts( [
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'no_found_rows'  => true,
        'meta_query'     => [ [
            'key'     => '_sl_bp_source_id',
            'value'   => $missing,
            'compare' => 'IN',
            'type'    => 'NUMERIC',
        ] ],
    ] );

    foreach ( $products as $product ) {
        $source_id = (int) get_post_meta( $product->ID, '_sl_bp_source_id', true );
        // En cas de doublon, conserver le produit publié le plus récent.
        if ( $source_id && isset( $cache[ $source_id ] ) && ! $cache[ $source_id ] ) {
            $cache[ $source_id ] = (int) $product->ID;
        }
    }
}

/** Produit WooCommerce publié lié à un bon plan (0 si aucun). */
function sl_bp_product_id_for( $bon_plan_id ) {
    $cache = &sl_bp_product_id_cache();
    $bon_plan_id = (int) $bon_plan_id;
    if ( $bon_plan_id <= 0 ) return 0;
    if ( isset( $cache[ $bon_plan_id ] ) ) return $cache[ $bon_plan_id ];
    sl_bp_preload_product_ids( [ $bon_plan_id ] );
    return isset( $cache[ $bon_plan_id ] ) ? (int) $cache[ $bon_plan_id ] : 0;
}

/**
 * HTML du bouton « Ajouter au panier » pour un bon plan (via son bon_plan_id)
 * OU directement un product_id. Rien si pas de produit achetable / panier OFF.
 */
function sl_bp_cart_button_html( $bon_plan_id = 0, $product_id = 0 ) {
    if ( ! function_exists( 'wc_get_product' ) ) return '';
    if ( function_exists( 'slc_cart_enabled' ) && ! slc_cart_enabled() ) return '';
    $pid = $product_id ? (int) $product_id : sl_bp_product_id_for( $bon_plan_id );
    if ( ! $pid ) return '';
    $p = wc_get_product( $pid );
    if ( ! $p || ! $p->is_purchasable() || ! $p->is_in_stock() ) return '';

    $svg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>';
    return '<div class="slbp-cart-wrap"><button type="button" class="slbp-add-cart" data-pid="' . esc_attr( $pid )
         . '" data-label="Ajouter au panier" aria-label="Ajouter au panier">' . $svg . '<span>Ajouter au panier</span></button></div>';
}

/* ------------------------------------------------------------
   BONS PLANS : requêtes et rendu partagés.
   Le widget Elementor et l'API REST utilisent les mêmes règles afin
   qu'une offre affichée soit toujours disponible et achetable de la
   même manière, sans charger toutes les cartes dans le navigateur.
   ------------------------------------------------------------ */

/** Une offre dont le stock limité est épuisé ne doit jamais être affichée. */
function sl_bp_bon_plan_is_available( $bon_plan_id ) {
    $stock_actif = get_post_meta( $bon_plan_id, '_sl_bp_stock_actif', true );
    $stock_qty   = get_post_meta( $bon_plan_id, '_sl_bp_stock_qty', true );

    return ! ( $stock_actif === '1' && $stock_qty !== '' && (int) $stock_qty <= 0 );
}

/** Normalise une liste d'agences transmise par le widget ou l'API. */
function sl_bp_bons_plans_agencies( $values ) {
    if ( ! is_array( $values ) ) {
        $values = preg_split( '/\s*,\s*/', (string) $values );
    }

    return array_values( array_unique( array_filter( array_map( 'sanitize_title', (array) $values ) ) ) );
}

/** Normalise une liste de catégories transmise par le widget ou l'API. */
function sl_bp_bons_plans_categories( $values ) {
    if ( ! is_array( $values ) ) {
        $values = preg_split( '/\s*,\s*/', (string) $values );
    }

    return array_values( array_unique( array_filter( array_map( 'intval', (array) $values ) ) ) );
}

/**
 * Arguments WP_Query communs aux Bons Plans.
 *
 * @param array $params page, per_page, agences/agence, categories/categorie,
 *                      min_price, max_price, search, orderby et actifs.
 */
function sl_bp_bons_plans_query_args( $params = [] ) {
    $params = wp_parse_args( $params, [
        'page'       => 1,
        'per_page'   => 20,
        'agences'    => [],
        'categories' => [],
        'min_price'  => null,
        'max_price'  => null,
        'search'     => '',
        'orderby'    => 'recent',
        'actifs'     => true,
    ] );

    if ( empty( $params['agences'] ) && isset( $params['agence'] ) ) {
        $params['agences'] = $params['agence'];
    }
    if ( empty( $params['categories'] ) && isset( $params['categorie'] ) ) {
        $params['categories'] = $params['categorie'];
    }

    $page       = max( 1, (int) $params['page'] );
    $per_page   = min( 100, max( 1, (int) $params['per_page'] ) );
    $agences    = sl_bp_bons_plans_agencies( $params['agences'] );
    $categories = sl_bp_bons_plans_categories( $params['categories'] );
    $orderby    = sanitize_key( (string) $params['orderby'] );
    $search     = sanitize_text_field( (string) $params['search'] );

    $args = [
        'post_type'      => 'sl_bon_plan',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $page,
    ];

    switch ( $orderby ) {
        case 'reduc':
            $args['meta_key'] = '_sl_bp_reduction_pct';
            $args['orderby']  = 'meta_value_num';
            $args['order']    = 'DESC';
            break;
        case 'prix_asc':
            $args['meta_key'] = '_sl_bp_prix_apres';
            $args['orderby']  = 'meta_value_num';
            $args['order']    = 'ASC';
            break;
        case 'prix_desc':
            $args['meta_key'] = '_sl_bp_prix_apres';
            $args['orderby']  = 'meta_value_num';
            $args['order']    = 'DESC';
            break;
        default:
            $args['orderby'] = 'date';
            $args['order']   = 'DESC';
            break;
    }

    if ( $search !== '' ) {
        $args['s'] = $search;
    }

    $meta_query = [ 'relation' => 'AND' ];
    if ( ! empty( $params['actifs'] ) ) {
        $today        = current_time( 'Y-m-d' );
        $meta_query[] = [
            'relation' => 'OR',
            [ 'key' => '_sl_bp_date_fin', 'value' => $today, 'compare' => '>=', 'type' => 'DATE' ],
            [ 'key' => '_sl_bp_date_fin', 'value' => '', 'compare' => '=' ],
            [ 'key' => '_sl_bp_date_fin', 'compare' => 'NOT EXISTS' ],
        ];
    }

    // Même logique que l'ancien rendu PHP : un stock vide est accepté tant
    // qu'il n'est pas explicitement activé comme stock limité.
    $meta_query[] = [
        'relation' => 'OR',
        [ 'key' => '_sl_bp_stock_actif', 'compare' => 'NOT EXISTS' ],
        [ 'key' => '_sl_bp_stock_actif', 'value' => '1', 'compare' => '!=' ],
        [
            'relation' => 'AND',
            [ 'key' => '_sl_bp_stock_actif', 'value' => '1', 'compare' => '=' ],
            [
                'relation' => 'OR',
                [ 'key' => '_sl_bp_stock_qty', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_sl_bp_stock_qty', 'value' => '', 'compare' => '=' ],
                [ 'key' => '_sl_bp_stock_qty', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ],
            ],
        ],
    ];

    $min_price = is_numeric( $params['min_price'] ) ? (float) $params['min_price'] : null;
    $max_price = is_numeric( $params['max_price'] ) ? (float) $params['max_price'] : null;
    if ( $min_price !== null && $min_price > 0 ) {
        $meta_query[] = [ 'key' => '_sl_bp_prix_apres', 'value' => $min_price, 'compare' => '>=', 'type' => 'NUMERIC' ];
    }
    if ( $max_price !== null && $max_price >= 0 ) {
        $meta_query[] = [ 'key' => '_sl_bp_prix_apres', 'value' => $max_price, 'compare' => '<=', 'type' => 'NUMERIC' ];
    }
    $args['meta_query'] = $meta_query;

    $tax_query = [];
    if ( $agences ) {
        $tax_query[] = [ 'taxonomy' => 'sl_agence_promo', 'field' => 'slug', 'terms' => $agences, 'operator' => 'IN' ];
    }
    if ( $categories ) {
        $tax_query[] = [ 'taxonomy' => 'sl_categorie_promo', 'field' => 'term_id', 'terms' => $categories, 'operator' => 'IN' ];
    }
    if ( $tax_query ) {
        if ( count( $tax_query ) > 1 ) $tax_query['relation'] = 'AND';
        $args['tax_query'] = $tax_query;
    }

    return $args;
}

/** Prix maximum publié : sert uniquement de borne au filtre, sans charger toutes les cartes. */
function sl_bp_bons_plans_max_price() {
    global $wpdb;

    $max = $wpdb->get_var( $wpdb->prepare(
        "SELECT MAX(CAST(pm.meta_value AS DECIMAL(20,2)))
         FROM {$wpdb->posts} AS p
         INNER JOIN {$wpdb->postmeta} AS pm ON pm.post_id = p.ID
         WHERE p.post_type = %s
           AND p.post_status = %s
           AND pm.meta_key = %s
           AND pm.meta_value <> ''",
        'sl_bon_plan',
        'publish',
        '_sl_bp_prix_apres'
    ) );

    return max( 0, (float) $max );
}

/** HTML d'une carte Bons Plans, partagé par le rendu initial et la pagination REST. */
function sl_bp_render_bon_plan_card_html( $post ) {
    $post = $post instanceof WP_Post ? $post : get_post( $post );
    if ( ! $post || ! sl_bp_bon_plan_is_available( $post->ID ) ) return '';

    $id           = (int) $post->ID;
    $stock_actif  = get_post_meta( $id, '_sl_bp_stock_actif', true );
    $prix_av      = (float) get_post_meta( $id, '_sl_bp_prix_avant', true );
    $prix_ap      = (float) get_post_meta( $id, '_sl_bp_prix_apres', true );
    $reduc        = (int) get_post_meta( $id, '_sl_bp_reduction_pct', true );
    $badge        = get_post_meta( $id, '_sl_bp_badge_type', true );
    $date_fin     = get_post_meta( $id, '_sl_bp_date_fin', true );
    $img_url      = get_the_post_thumbnail_url( $id, 'medium' );
    $badge_labels = [
        'flash'     => 'Flash',
        'nouveau'   => 'Nouveau',
        'top-vente' => 'Top Vente',
        'exclusif'  => 'Exclusif',
    ];
    $badge_label  = $badge_labels[ $badge ] ?? ucfirst( str_replace( '-', ' ', $badge ) );

    $c_terms = get_the_terms( $id, 'sl_categorie_promo' );
    if ( is_wp_error( $c_terms ) || ! is_array( $c_terms ) ) $c_terms = [];
    $cat_ids  = implode( ',', wp_list_pluck( $c_terms, 'term_id' ) );
    $cat_name = ! empty( $c_terms ) ? $c_terms[0]->name : '';

    $a_terms = get_the_terms( $id, 'sl_agence_promo' );
    if ( is_wp_error( $a_terms ) || ! is_array( $a_terms ) ) $a_terms = [];
    $agence_slug = ! empty( $a_terms ) ? $a_terms[0]->slug : '';
    $agence_name = ! empty( $a_terms ) ? $a_terms[0]->name : '';

    ob_start();
    ?>
    <a class="slbp-card"
         href="<?php echo esc_url( get_permalink( $id ) ); ?>"
         data-nom="<?php echo esc_attr( strtolower( $post->post_title ) ); ?>"
         data-cat="<?php echo esc_attr( $cat_ids ); ?>"
         data-agence="<?php echo esc_attr( $agence_slug ); ?>"
         data-prix-ap="<?php echo esc_attr( $prix_ap ); ?>"
         data-reduc="<?php echo esc_attr( $reduc ); ?>"
         data-date="<?php echo esc_attr( $post->post_date ); ?>">

        <div class="slbp-card-img-wrap">
            <?php if ( $img_url ) : ?>
                <img src="<?php echo esc_url( $img_url ); ?>"
                     alt="<?php echo esc_attr( $post->post_title ); ?>" loading="lazy">
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
                    data-titre="<?php echo esc_attr( $post->post_title ); ?>"
                    data-prix="<?php echo esc_attr( $prix_ap > 0 ? number_format( $prix_ap, 0, ',', ' ' ) . ' FCFA' : '' ); ?>"
                    aria-label="Partager ce bon plan" title="Partager">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
            </button>
        </div>

        <div class="slbp-card-body">
            <div class="slbp-card-meta">
                <?php if ( $agence_name ) : ?><span class="slbp-agence-tag"><?php echo esc_html( $agence_name ); ?></span><?php endif; ?>
                <?php if ( $cat_name ) : ?><span class="slbp-cat-tag"><?php echo esc_html( $cat_name ); ?></span><?php endif; ?>
            </div>
            <h3 class="slbp-titre"><?php echo esc_html( $post->post_title ); ?></h3>
            <div class="slbp-prix-wrap">
                <?php if ( $prix_ap > 0 ) : ?><span class="slbp-prix-apres"><?php echo number_format( $prix_ap, 0, ',', ' ' ); ?> FCFA</span><?php endif; ?>
                <?php if ( $prix_av > 0 ) : ?><span class="slbp-prix-avant"><?php echo number_format( $prix_av, 0, ',', ' ' ); ?> FCFA</span><?php endif; ?>
            </div>
            <?php if ( $date_fin ) : ?><p class="slbp-date-fin">Valable jusqu'au <?php echo date_i18n( 'd M Y', strtotime( $date_fin ) ); ?></p><?php endif; ?>
            <?php if ( $stock_actif === '1' ) : ?><p class="slbp-stock-mention">Dans la limite des stocks disponibles</p><?php endif; ?>
        </div>

        <?php if ( function_exists( 'sl_bp_cart_button_html' ) ) echo sl_bp_cart_button_html( $id ); ?>
    </a>
    <?php
    return trim( ob_get_clean() );
}

/* ------------------------------------------------------------
   RESTRICTION : une seule agence par panier (Click & Collect).
   On ne peut pas mélanger des offres de deux agences différentes.
   ------------------------------------------------------------ */

/** Slug de l'agence d'un produit (via son bon plan source). '' si aucune. */
function sl_bp_product_agency( $product_id ) {
    static $cache = [];
    $product_id = (int) $product_id;
    if ( isset( $cache[ $product_id ] ) ) return $cache[ $product_id ];
    $slug = '';
    $bp = (int) get_post_meta( $product_id, '_sl_bp_source_id', true );
    if ( $bp ) {
        $terms = get_the_terms( $bp, 'sl_agence_promo' );
        if ( $terms && ! is_wp_error( $terms ) ) $slug = $terms[0]->slug;
    } else {
        // Produit lie a un repas Fast Food (plugin sl-fastfood, includes/fastfood-cart.php) :
        // l'agence est deja le slug directement, pas besoin de remonter a un post source.
        $ff_agence = get_post_meta( $product_id, '_sl_ff_source_agence', true );
        if ( $ff_agence ) $slug = sanitize_title( $ff_agence );
    }
    return $cache[ $product_id ] = $slug;
}

/** Nom lisible d'une agence à partir de son slug. */
function sl_bp_agency_name( $slug ) {
    if ( ! $slug ) return '';
    $t = get_term_by( 'slug', $slug, 'sl_agence_promo' );
    return ( $t && ! is_wp_error( $t ) ) ? $t->name : $slug;
}

/** Agence actuellement présente dans le panier (slug), '' si panier vide/sans agence. */
function sl_bp_cart_agency() {
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) return '';
    foreach ( WC()->cart->get_cart() as $item ) {
        $ag = sl_bp_product_agency( $item['product_id'] );
        if ( $ag ) return $ag;
    }
    return '';
}

/** Validation native (page produit + tout ajout) : bloque si autre agence. */
add_filter( 'woocommerce_add_to_cart_validation', 'sl_bp_one_agency_validation', 20, 2 );
function sl_bp_one_agency_validation( $passed, $product_id ) {
    if ( ! $passed ) return $passed;
    $new_ag = sl_bp_product_agency( $product_id );
    if ( ! $new_ag ) return $passed;
    $cart_ag = sl_bp_cart_agency();
    if ( $cart_ag && $cart_ag !== $new_ag ) {
        wc_add_notice( sprintf(
            'Votre panier contient déjà des offres de l\'agence « %s ». Une commande ne peut concerner qu\'une seule agence (retrait). Videz votre panier pour commander à « %s ».',
            esc_html( sl_bp_agency_name( $cart_ag ) ), esc_html( sl_bp_agency_name( $new_ag ) )
        ), 'error' );
        return false;
    }
    return $passed;
}

/** AJAX (bouton des cartes) : ajoute au panier avec message d'agence clair. */
add_action( 'wc_ajax_sl_bp_add', 'sl_bp_ajax_add_to_cart' );
function sl_bp_ajax_add_to_cart() {
    $pid = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
    // Quantite optionnelle (par defaut 1, comme avant) : utilisee par la fiche
    // repas Fast Food qui propose un selecteur de quantite. Aucun appelant
    // existant n'envoie ce parametre -> comportement inchange partout ailleurs.
    $qty = isset( $_POST['qty'] ) ? max( 1, intval( $_POST['qty'] ) ) : 1;
    if ( ! $pid || ! function_exists( 'WC' ) || ! WC()->cart ) {
        wp_send_json( [ 'ok' => false, 'msg' => 'Produit introuvable.' ] );
    }
    $new_ag  = sl_bp_product_agency( $pid );
    $cart_ag = sl_bp_cart_agency();
    if ( $new_ag && $cart_ag && $cart_ag !== $new_ag ) {
        wp_send_json( [ 'ok' => false, 'agency' => true, 'msg' => sprintf(
            'Panier déjà à l\'agence « %s ». Une seule agence par commande — videz le panier pour choisir « %s ».',
            sl_bp_agency_name( $cart_ag ), sl_bp_agency_name( $new_ag )
        ) ] );
    }
    $added = WC()->cart->add_to_cart( $pid, $qty );
    if ( ! $added ) {
        $errs = function_exists( 'wc_get_notices' ) ? wc_get_notices( 'error' ) : [];
        if ( function_exists( 'wc_clear_notices' ) ) wc_clear_notices();
        wp_send_json( [ 'ok' => false, 'msg' => $errs ? wp_strip_all_tags( $errs[0]['notice'] ) : 'Impossible d\'ajouter ce produit.' ] );
    }
    if ( function_exists( 'wc_clear_notices' ) ) wc_clear_notices();
    wp_send_json( [
        'ok'        => true,
        'fragments' => apply_filters( 'woocommerce_add_to_cart_fragments', [] ),
        'cart_hash' => WC()->cart->get_cart_hash(),
    ] );
}

/**
 * AJAX : vide le panier.
 * Sert surtout au bouton « Vider le panier » propose quand le client tente
 * d'ajouter un produit d'une AUTRE agence (regle 1 agence par commande) : le
 * message l'invitait a vider son panier sans lui en donner le moyen.
 * Pas de nonce : meme raison que sl_bp_add (pages servies par Varnish -> nonce
 * perime pour la plupart des visiteurs). Action sans privilege, limitee au
 * panier de la session courante.
 */
add_action( 'wc_ajax_sl_bp_clear_cart', 'sl_bp_ajax_clear_cart' );
function sl_bp_ajax_clear_cart() {
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
        wp_send_json( [ 'ok' => false, 'msg' => 'Panier indisponible.' ] );
    }
    WC()->cart->empty_cart();
    if ( function_exists( 'wc_clear_notices' ) ) wc_clear_notices();
    wp_send_json( [
        'ok'        => true,
        'fragments' => apply_filters( 'woocommerce_add_to_cart_fragments', [] ),
        'cart_hash' => WC()->cart->get_cart_hash(),
    ] );
}

/* ------------------------------------------------------------
   CHECKOUT : l'agence de retrait = celle des produits du panier.
   Le champ « Agence de retrait » (module sl-collect) est verrouillé
   sur l'agence du panier ; la commande enregistre toujours celle-ci.
   ------------------------------------------------------------ */
add_filter( 'woocommerce_checkout_fields', 'sl_bp_lock_pickup_agency', 30 );
function sl_bp_lock_pickup_agency( $fields ) {
    if ( empty( $fields['billing']['sl_collect_agence'] ) ) return $fields;
    $cart_ag = sl_bp_cart_agency();
    if ( ! $cart_ag ) return $fields; // panier sans agence -> choix libre
    $name = sl_bp_agency_name( $cart_ag );
    $fields['billing']['sl_collect_agence']['options']           = [ $cart_ag => $name ];
    $fields['billing']['sl_collect_agence']['default']           = $cart_ag;
    // Le lien « videz votre panier » est actionnable (class slbp-clear-cart,
    // gere par le JS de ce fichier) : avant, on demandait au client de vider son
    // panier sans lui en donner le moyen depuis cet ecran.
    $fields['billing']['sl_collect_agence']['description']       = 'Agence imposée par les produits de votre panier. Pour changer d\'agence, <a href="#" class="slbp-clear-cart" data-reload="1">videz votre panier</a>.';
    $fields['billing']['sl_collect_agence']['custom_attributes'] = [ 'data-locked' => '1' ];
    return $fields;
}

// Securite : la commande enregistre TOUJOURS l'agence du panier (anti-triche).
add_action( 'woocommerce_checkout_create_order', 'sl_bp_force_order_agency', 20, 1 );
function sl_bp_force_order_agency( $order ) {
    $cart_ag = function_exists( 'sl_bp_cart_agency' ) ? sl_bp_cart_agency() : '';
    if ( $cart_ag ) $order->update_meta_data( '_sl_collect_agence', $cart_ag );
}

// Bloque la validation si l'agence soumise ne correspond pas au panier.
add_action( 'woocommerce_checkout_process', 'sl_bp_validate_pickup_agency', 30 );
function sl_bp_validate_pickup_agency() {
    $cart_ag = sl_bp_cart_agency();
    if ( ! $cart_ag ) return;
    $submitted = isset( $_POST['sl_collect_agence'] ) ? sanitize_title( wp_unslash( $_POST['sl_collect_agence'] ) ) : '';
    if ( $submitted !== '' && $submitted !== $cart_ag ) {
        wc_add_notice( sprintf(
            'L\'agence de retrait doit être « %s » (celle des produits de votre panier).',
            esc_html( sl_bp_agency_name( $cart_ag ) )
        ), 'error' );
    }
}

/** JS + CSS (front, une fois). */
add_action( 'wp_footer', 'sl_bp_cart_assets', 99 );
function sl_bp_cart_assets() {
    if ( is_admin() ) return;
    if ( function_exists( 'slc_cart_enabled' ) && ! slc_cart_enabled() ) return;
    if ( ! class_exists( 'WC_AJAX' ) ) return;
    $endpoint       = WC_AJAX::get_endpoint( 'sl_bp_add' );
    $clear_endpoint = WC_AJAX::get_endpoint( 'sl_bp_clear_cart' );
    $cart_url       = wc_get_cart_url();
    ?>
    <style>
    .slbp-cart-wrap{margin-top:7px;position:relative;z-index:4;}
    .slbp-add-cart{display:flex;align-items:center;justify-content:center;gap:6px;width:100%;border:none;border-radius:6px;background:#E91E63;color:#fff;font-weight:500;font-size:12px;line-height:1;padding:7px 10px;cursor:pointer;transition:.15s;font-family:inherit;}
    .slbp-add-cart:hover{background:#c2185b;}
    .slbp-add-cart svg{flex:0 0 auto;width:14px;height:14px;}
    .slbp-add-cart.loading{opacity:.75;pointer-events:none;}
    .slbp-add-cart.err{background:#e67e22;}
    /* Le theme Grogin injecte parfois un lien WooCommerce « added_to_cart »
       sous le bouton. La confirmation est geree par le toast ci-dessous, sans
       laisser une coche verte permanente dans la mise en page. */
    .slbp-cart-wrap > .added_to_cart{display:none!important;}
    #slbp-toast{position:fixed;left:50%;bottom:24px;transform:translateX(-50%) translateY(18px);background:#1d2327;color:#fff;padding:13px 20px;border-radius:11px;font-size:13.5px;font-weight:500;line-height:1.4;max-width:min(460px,92vw);text-align:center;z-index:99999;opacity:0;pointer-events:none;transition:.25s;box-shadow:0 8px 28px rgba(0,0,0,.28);display:flex;flex-direction:column;align-items:center;gap:10px;}
    #slbp-toast.show{opacity:1;transform:translateX(-50%) translateY(0);pointer-events:auto;}
    #slbp-toast.warn{background:#b45309;}
    .slbp-toast-btn{border:none;border-radius:7px;background:#fff;color:#1d2327;font-weight:700;font-size:13px;font-family:inherit;padding:8px 14px;cursor:pointer;white-space:nowrap;}
    .slbp-toast-btn:hover{background:#f0f0f0;}
    </style>
    <script>
    (function(){
        var ENDPOINT = '<?php echo esc_js( $endpoint ); ?>';
        var CLEAR_ENDPOINT = '<?php echo esc_js( $clear_endpoint ); ?>';
        var CART_URL = '<?php echo esc_url( $cart_url ); ?>';

        document.addEventListener('click', function(e){
            var btn = e.target && e.target.closest ? e.target.closest('.slbp-add-cart') : null;
            if ( ! btn ) return;
            e.preventDefault(); e.stopPropagation();
            if ( btn.classList.contains('loading') ) return;
            slbpAdd(btn);
        }, true);

        function slbpAdd( btn ){
            var pid = btn.getAttribute('data-pid'); if ( ! pid ) return;
            var span = btn.querySelector('span');
            var label = btn.getAttribute('data-label') || 'Ajouter au panier';
            btn.classList.remove('done','err');
            btn.classList.add('loading'); if ( span ) span.textContent = 'Ajout…';
            var body = 'product_id=' + encodeURIComponent(pid);
            fetch(ENDPOINT, { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body })
                .then(function(r){ return r.json(); })
                .then(function(res){
                    btn.classList.remove('loading');
                    if ( ! res || ! res.ok ) {
                        btn.classList.add('err'); if ( span ) span.textContent = 'Impossible';
                        // Conflit d'agence : on propose de vider le panier ET d'ajouter
                        // le produit voulu, en un clic (le message seul laissait le
                        // client sans solution).
                        var action = ( res && res.agency ) ? {
                            label: 'Vider le panier et ajouter',
                            run: function(){ slbpClearThenAdd(btn); }
                        } : null;
                        slbpToast( res && res.msg ? res.msg : 'Impossible d\'ajouter au panier.', res && res.agency, action );
                        setTimeout(reset, 2400); return;
                    }
                    if ( window.jQuery && res.fragments ) { jQuery(document.body).trigger('added_to_cart', [res.fragments, res.cart_hash, jQuery(btn)]); }
                    if ( span ) span.textContent = label;
                    slbpToast('Produit ajouté au panier.', false, {
                        label: 'Voir le panier',
                        run: function(){ window.location.href = CART_URL; }
                    });
                })
                .catch(function(){ btn.classList.remove('loading'); btn.classList.add('err'); if ( span ) span.textContent = 'Réessayer'; setTimeout(reset,1800); });
            function reset(){ btn.classList.remove('err'); if ( span ) span.textContent = label; }
        }

        function slbpClearCart( done ){
            fetch(CLEAR_ENDPOINT, { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: '' })
                .then(function(r){ return r.json(); })
                .then(function(res){
                    if ( res && res.ok && window.jQuery && res.fragments ) {
                        jQuery(document.body).trigger('added_to_cart', [res.fragments, res.cart_hash, jQuery('body')]);
                    }
                    if ( done ) done( !! ( res && res.ok ) );
                })
                .catch(function(){ if ( done ) done(false); });
        }

        function slbpClearThenAdd( btn ){
            slbpHideToast();
            slbpClearCart(function(ok){
                if ( ! ok ) { slbpToast('Impossible de vider le panier. Réessayez.', true); return; }
                slbpAdd(btn);
            });
        }

        // Bouton generique « vider le panier » (utilisable n'importe ou sur le site).
        document.addEventListener('click', function(e){
            var el = e.target && e.target.closest ? e.target.closest('.slbp-clear-cart') : null;
            if ( ! el ) return;
            e.preventDefault(); e.stopPropagation();
            if ( ! window.confirm('Vider entièrement votre panier ?') ) return;
            slbpClearCart(function(ok){
                slbpToast( ok ? 'Panier vidé.' : 'Impossible de vider le panier.', ! ok );
                if ( ok && el.getAttribute('data-reload') === '1' ) location.reload();
            });
        }, true);

        function slbpHideToast(){
            var t = document.getElementById('slbp-toast');
            if ( t ) t.className = t.className.replace('show','');
            clearTimeout( window.__slbpToastT );
        }

        function slbpToast( msg, warn, action ){
            var t = document.getElementById('slbp-toast');
            if ( ! t ) {
                t = document.createElement('div');
                t.id = 'slbp-toast';
                t.setAttribute('role', 'status');
                t.setAttribute('aria-live', 'polite');
                document.body.appendChild(t);
            }
            // textContent (pas innerHTML) pour le message : il vient du serveur
            // mais peut contenir un nom d'agence libre.
            t.innerHTML = '';
            var m = document.createElement('span'); m.textContent = msg; t.appendChild(m);
            if ( action ) {
                var b = document.createElement('button');
                b.type = 'button'; b.className = 'slbp-toast-btn'; b.textContent = action.label;
                b.addEventListener('click', action.run);
                t.appendChild(b);
            }
            t.className = 'show' + ( warn ? ' warn' : '' );
            clearTimeout( window.__slbpToastT );
            window.__slbpToastT = setTimeout( function(){ t.className = t.className.replace('show',''); }, action ? 12000 : 5000 );
        }
    })();
    </script>
    <?php
}
