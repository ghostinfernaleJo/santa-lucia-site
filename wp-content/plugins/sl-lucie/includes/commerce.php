<?php
/**
 * Commerce conversationnel de Lucie.
 *
 * Les prix, stocks et mutations de panier restent entierement controles par
 * WooCommerce. Le modele ne cree jamais de commande : il peut seulement
 * proposer un panier, le modifier apres une demande explicite, puis fournir
 * le lien du checkout existant.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* -------------------------------------------------------------------------
 * Donnees structurees destinees au widget
 * ---------------------------------------------------------------------- */

function sl_lucie_set_ui_cards( $cards ) {
    $clean = [];
    foreach ( array_slice( (array) $cards, 0, 12 ) as $card ) {
        if ( ! is_array( $card ) || empty( $card['name'] ) ) continue;
        $clean[] = [
            'product_id'          => max( 0, (int) ( $card['product_id'] ?? 0 ) ),
            'name'                => sanitize_text_field( (string) $card['name'] ),
            'price'               => sanitize_text_field( (string) ( $card['price'] ?? '' ) ),
            'price_raw'           => (float) ( $card['price_raw'] ?? 0 ),
            'regular_price'       => sanitize_text_field( (string) ( $card['regular_price'] ?? '' ) ),
            'image'               => esc_url_raw( (string) ( $card['image'] ?? '' ) ),
            'url'                 => esc_url_raw( (string) ( $card['url'] ?? '' ) ),
            'agency'              => sanitize_text_field( (string) ( $card['agency'] ?? '' ) ),
            'agency_slug'         => sanitize_title( (string) ( $card['agency_slug'] ?? '' ) ),
            'addable'             => ! empty( $card['addable'] ),
            'recommended_quantity'=> max( 1, min( 20, (int) ( $card['recommended_quantity'] ?? 1 ) ) ),
        ];
    }
    $GLOBALS['sl_lucie_ui_cards'] = $clean;
    $allowed = (array) ( $GLOBALS['sl_lucie_allowed_product_ids'] ?? [] );
    foreach ( $clean as $card ) if ( $card['product_id'] > 0 ) $allowed[] = $card['product_id'];
    $GLOBALS['sl_lucie_allowed_product_ids'] = array_values( array_unique( array_map( 'intval', $allowed ) ) );
}

function sl_lucie_get_ui_cards() {
    return array_values( (array) ( $GLOBALS['sl_lucie_ui_cards'] ?? [] ) );
}

function sl_lucie_set_ui_cart( $cart ) {
    $GLOBALS['sl_lucie_ui_cart'] = is_array( $cart ) ? $cart : [];
    if ( is_array( $cart ) && ! empty( $cart['items'] ) ) {
        $allowed = (array) ( $GLOBALS['sl_lucie_allowed_product_ids'] ?? [] );
        foreach ( $cart['items'] as $item ) if ( ! empty( $item['product_id'] ) ) $allowed[] = (int) $item['product_id'];
        $GLOBALS['sl_lucie_allowed_product_ids'] = array_values( array_unique( array_map( 'intval', $allowed ) ) );
    }
}

function sl_lucie_get_ui_cart() {
    return is_array( $GLOBALS['sl_lucie_ui_cart'] ?? null ) ? $GLOBALS['sl_lucie_ui_cart'] : null;
}

/* -------------------------------------------------------------------------
 * Initialisation et lecture du panier WooCommerce
 * ---------------------------------------------------------------------- */

function sl_lucie_wc_cart() {
    if ( ! function_exists( 'WC' ) || ! function_exists( 'wc_get_product' ) ) {
        return new WP_Error( 'sl_lucie_no_woocommerce', 'La commande en ligne est indisponible.' );
    }
    try {
        if ( ! WC()->session && method_exists( WC(), 'initialize_session' ) ) WC()->initialize_session();
        if ( ! WC()->customer && class_exists( 'WC_Customer' ) ) WC()->customer = new WC_Customer( get_current_user_id(), true );
        if ( ! WC()->cart && method_exists( WC(), 'initialize_cart' ) ) WC()->initialize_cart();
        if ( ( ! WC()->session || ! WC()->cart ) && function_exists( 'wc_load_cart' ) ) wc_load_cart();
    } catch ( Throwable $e ) {
        return new WP_Error( 'sl_lucie_cart_init', 'Le panier ne peut pas etre initialise pour le moment.' );
    }
    if ( ! WC()->cart ) return new WP_Error( 'sl_lucie_no_cart', 'Le panier est indisponible.' );
    return WC()->cart;
}

function sl_lucie_product_agency_slug( $product_id ) {
    if ( function_exists( 'sl_bp_product_agency' ) ) return sanitize_title( sl_bp_product_agency( $product_id ) );
    $ff = get_post_meta( $product_id, '_sl_ff_source_agence', true );
    if ( $ff ) return sanitize_title( $ff );
    $bp = (int) get_post_meta( $product_id, '_sl_bp_source_id', true );
    if ( $bp ) {
        $terms = get_the_terms( $bp, 'sl_agence_promo' );
        if ( $terms && ! is_wp_error( $terms ) ) return sanitize_title( $terms[0]->slug );
    }
    return '';
}

function sl_lucie_agency_name( $slug ) {
    $slug = sanitize_title( $slug );
    if ( $slug === '' ) return '';
    if ( function_exists( 'sl_bp_agency_name' ) ) return (string) sl_bp_agency_name( $slug );
    $term = taxonomy_exists( 'sl_agence_promo' ) ? get_term_by( 'slug', $slug, 'sl_agence_promo' ) : false;
    if ( $term && ! is_wp_error( $term ) ) return $term->name;
    return ucwords( str_replace( '-', ' ', $slug ) );
}

function sl_lucie_price_text( $amount ) {
    if ( function_exists( 'wc_price' ) ) {
        return trim( html_entity_decode( wp_strip_all_tags( wc_price( (float) $amount ) ), ENT_QUOTES, 'UTF-8' ) );
    }
    return number_format_i18n( (float) $amount, 0 ) . ' FCFA';
}

/** Une offre liee reste recommandable uniquement tant que sa source est active. */
function sl_lucie_product_is_current( $product_id ) {
    $bp_id = (int) get_post_meta( $product_id, '_sl_bp_source_id', true );
    if ( $bp_id ) {
        if ( get_post_status( $bp_id ) !== 'publish' ) return false;
        $end = (string) get_post_meta( $bp_id, '_sl_bp_date_fin', true );
        if ( $end !== '' && $end < current_time( 'Y-m-d' ) ) return false;
    }
    $meal_id = (int) get_post_meta( $product_id, '_sl_ff_source_id', true );
    $agency  = sanitize_title( get_post_meta( $product_id, '_sl_ff_source_agence', true ) );
    if ( $meal_id ) {
        if ( get_post_status( $meal_id ) !== 'publish' ) return false;
        if ( $agency && function_exists( 'sl_ff_is_repas_available_for_agence' ) && function_exists( 'sl_ff_today_jour' ) ) {
            if ( ! sl_ff_is_repas_available_for_agence( $meal_id, $agency, sl_ff_today_jour() ) ) return false;
        }
    }
    return true;
}

/** Carte produit normalisee : aucune valeur commerciale n'est inventee par l'IA. */
function sl_lucie_product_card( $product_id ) {
    $product = function_exists( 'wc_get_product' ) ? wc_get_product( (int) $product_id ) : false;
    if ( ! $product || $product->get_status() !== 'publish' ) return null;
    $agency_slug = sl_lucie_product_agency_slug( $product->get_id() );
    $source_id   = (int) get_post_meta( $product->get_id(), '_sl_ff_source_id', true );
    $bp_id       = (int) get_post_meta( $product->get_id(), '_sl_bp_source_id', true );
    $url         = get_permalink( $product->get_id() );
    if ( $source_id && $agency_slug && function_exists( 'sl_ff_order_url' ) ) {
        $url = sl_ff_order_url( $source_id, $agency_slug );
    } elseif ( $bp_id && get_post_status( $bp_id ) === 'publish' ) {
        $url = get_permalink( $bp_id );
    }
    $image = wp_get_attachment_image_url( $product->get_image_id(), 'medium_large' );
    if ( ! $image && $source_id ) $image = get_the_post_thumbnail_url( $source_id, 'medium_large' );
    if ( ! $image && $bp_id ) $image = get_the_post_thumbnail_url( $bp_id, 'medium_large' );

    $price   = (float) $product->get_price();
    $regular = (float) $product->get_regular_price();
    return [
        'product_id'    => (int) $product->get_id(),
        'name'          => $product->get_name(),
        'price'         => $price > 0 ? sl_lucie_price_text( $price ) : '',
        'price_raw'     => $price,
        'regular_price' => ( $regular > $price && $price > 0 ) ? sl_lucie_price_text( $regular ) : '',
        'image'         => $image ? $image : '',
        'url'           => $url ? $url : '',
        'agency'        => sl_lucie_agency_name( $agency_slug ),
        'agency_slug'   => $agency_slug,
        'addable'       => $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock() && $price > 0 && sl_lucie_product_is_current( $product->get_id() ),
    ];
}

function sl_lucie_cart_notices() {
    $messages = [];
    if ( function_exists( 'wc_get_notices' ) ) {
        foreach ( (array) wc_get_notices( 'error' ) as $notice ) {
            $text = is_array( $notice ) ? ( $notice['notice'] ?? '' ) : $notice;
            $text = trim( wp_strip_all_tags( (string) $text ) );
            if ( $text !== '' ) $messages[] = $text;
        }
    }
    if ( function_exists( 'wc_clear_notices' ) ) wc_clear_notices();
    return $messages;
}

function sl_lucie_cart_snapshot( $cart = null ) {
    if ( ! $cart ) $cart = sl_lucie_wc_cart();
    if ( is_wp_error( $cart ) ) return [ 'available' => false, 'message' => $cart->get_error_message(), 'items' => [] ];
    $cart->calculate_totals();
    $items = [];
    foreach ( $cart->get_cart() as $key => $line ) {
        $product = $line['data'] ?? null;
        if ( ! $product instanceof WC_Product ) continue;
        $card = sl_lucie_product_card( $product->get_id() );
        if ( ! $card ) continue;
        $items[] = array_merge( $card, [
            'cart_item_key' => (string) $key,
            'quantity'      => max( 1, (int) ( $line['quantity'] ?? 1 ) ),
            'line_total'    => sl_lucie_price_text( (float) ( $line['line_total'] ?? 0 ) ),
        ] );
    }
    $agency_slug = function_exists( 'sl_bp_cart_agency' ) ? sl_bp_cart_agency() : '';
    if ( ! $agency_slug ) {
        foreach ( $items as $item ) {
            if ( $item['agency_slug'] !== '' ) { $agency_slug = $item['agency_slug']; break; }
        }
    }
    $total = (float) $cart->get_total( 'edit' );
    return [
        'available'    => true,
        'empty'        => empty( $items ),
        'count'        => (int) $cart->get_cart_contents_count(),
        'items'        => $items,
        'total'        => sl_lucie_price_text( $total ),
        'total_raw'    => $total,
        'agency'       => sl_lucie_agency_name( $agency_slug ),
        'agency_slug'  => sanitize_title( $agency_slug ),
        'cart_url'     => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/panier/' ),
        'checkout_url' => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' ),
    ];
}

function sl_lucie_persist_cart_session() {
    if ( function_exists( 'WC' ) && WC()->session && method_exists( WC()->session, 'set_customer_session_cookie' ) ) {
        WC()->session->set_customer_session_cookie( true );
    }
}

function sl_lucie_cart_add_product( $product_id, $quantity = 1 ) {
    if ( function_exists( 'slc_cart_enabled' ) && ! slc_cart_enabled() ) {
        return [ 'ok' => false, 'message' => 'La commande en ligne est actuellement desactivee.' ];
    }
    $cart = sl_lucie_wc_cart();
    if ( is_wp_error( $cart ) ) return [ 'ok' => false, 'message' => $cart->get_error_message() ];
    $product_id = absint( $product_id );
    $quantity   = max( 1, min( 20, absint( $quantity ) ) );
    $product    = wc_get_product( $product_id );
    if ( ! $product || $product->get_status() !== 'publish' || ! $product->is_type( 'simple' ) ) {
        return [ 'ok' => false, 'message' => 'Ce produit ne peut pas etre ajoute depuis Lucie.' ];
    }
    if ( ! $product->is_purchasable() || ! $product->is_in_stock() || ! sl_lucie_product_is_current( $product_id ) ) {
        return [ 'ok' => false, 'message' => 'Ce produit n\'est plus disponible a la commande.' ];
    }
    $key = $cart->add_to_cart( $product_id, $quantity );
    if ( ! $key ) {
        $notices = sl_lucie_cart_notices();
        return [ 'ok' => false, 'message' => $notices ? $notices[0] : 'Impossible d\'ajouter ce produit au panier.', 'cart' => sl_lucie_cart_snapshot( $cart ) ];
    }
    sl_lucie_cart_notices();
    sl_lucie_persist_cart_session();
    $snapshot = sl_lucie_cart_snapshot( $cart );
    sl_lucie_set_ui_cart( $snapshot );
    return [ 'ok' => true, 'message' => $product->get_name() . ' a ete ajoute au panier.', 'cart' => $snapshot ];
}

function sl_lucie_cart_remove_product( $cart_item_key = '', $product_id = 0 ) {
    $cart = sl_lucie_wc_cart();
    if ( is_wp_error( $cart ) ) return [ 'ok' => false, 'message' => $cart->get_error_message() ];
    $cart_item_key = sanitize_text_field( (string) $cart_item_key );
    if ( $cart_item_key === '' && $product_id ) {
        foreach ( $cart->get_cart() as $key => $line ) {
            if ( (int) ( $line['product_id'] ?? 0 ) === (int) $product_id ) { $cart_item_key = $key; break; }
        }
    }
    if ( $cart_item_key === '' || ! isset( $cart->get_cart()[ $cart_item_key ] ) ) {
        return [ 'ok' => false, 'message' => 'Cet article ne se trouve pas dans le panier.', 'cart' => sl_lucie_cart_snapshot( $cart ) ];
    }
    $cart->remove_cart_item( $cart_item_key );
    sl_lucie_persist_cart_session();
    $snapshot = sl_lucie_cart_snapshot( $cart );
    sl_lucie_set_ui_cart( $snapshot );
    return [ 'ok' => true, 'message' => 'Article retire du panier.', 'cart' => $snapshot ];
}

function sl_lucie_cart_update_product( $cart_item_key, $quantity ) {
    $cart = sl_lucie_wc_cart();
    if ( is_wp_error( $cart ) ) return [ 'ok' => false, 'message' => $cart->get_error_message() ];
    $key = sanitize_text_field( (string) $cart_item_key );
    $qty = max( 0, min( 20, absint( $quantity ) ) );
    if ( $key === '' || ! isset( $cart->get_cart()[ $key ] ) ) {
        return [ 'ok' => false, 'message' => 'Cet article ne se trouve pas dans le panier.', 'cart' => sl_lucie_cart_snapshot( $cart ) ];
    }
    $cart->set_quantity( $key, $qty, true );
    sl_lucie_persist_cart_session();
    $snapshot = sl_lucie_cart_snapshot( $cart );
    sl_lucie_set_ui_cart( $snapshot );
    return [ 'ok' => true, 'message' => $qty > 0 ? 'Quantite mise a jour.' : 'Article retire du panier.', 'cart' => $snapshot ];
}

function sl_lucie_cart_clear() {
    $cart = sl_lucie_wc_cart();
    if ( is_wp_error( $cart ) ) return [ 'ok' => false, 'message' => $cart->get_error_message() ];
    $cart->empty_cart();
    sl_lucie_persist_cart_session();
    $snapshot = sl_lucie_cart_snapshot( $cart );
    sl_lucie_set_ui_cart( $snapshot );
    return [ 'ok' => true, 'message' => 'Panier vide.', 'cart' => $snapshot ];
}

/* -------------------------------------------------------------------------
 * Endpoint deterministe utilise par les boutons du widget
 * ---------------------------------------------------------------------- */

add_action( 'rest_api_init', function () {
    register_rest_route( 'santa-lucia/v1', '/lucie/cart', [
        'methods'             => 'POST',
        'callback'            => 'sl_lucie_cart_rest_handler',
        'permission_callback' => '__return_true',
    ] );
} );

function sl_lucie_cart_rest_handler( WP_REST_Request $request ) {
    $action = sanitize_key( (string) $request->get_param( 'action' ) );
    switch ( $action ) {
        case 'add':
            $result = sl_lucie_cart_add_product( $request->get_param( 'product_id' ), $request->get_param( 'quantity' ) ?: 1 );
            break;
        case 'remove':
            $result = sl_lucie_cart_remove_product( $request->get_param( 'cart_item_key' ), $request->get_param( 'product_id' ) );
            break;
        case 'update':
            $result = sl_lucie_cart_update_product( $request->get_param( 'cart_item_key' ), $request->get_param( 'quantity' ) );
            break;
        case 'clear':
            $result = sl_lucie_cart_clear();
            break;
        case 'view':
        case '':
            $result = [ 'ok' => true, 'message' => '', 'cart' => sl_lucie_cart_snapshot() ];
            break;
        default:
            $result = [ 'ok' => false, 'message' => 'Action de panier inconnue.' ];
    }
    return new WP_REST_Response( $result, ! empty( $result['ok'] ) ? 200 : 400 );
}

/* -------------------------------------------------------------------------
 * Agences et recommandation sous budget
 * ---------------------------------------------------------------------- */

function sl_lucie_normalize_text( $text ) {
    return mb_strtolower( remove_accents( trim( wp_strip_all_tags( (string) $text ) ) ) );
}

function sl_lucie_agency_catalog() {
    $catalog = [];
    if ( taxonomy_exists( 'sl_agence_promo' ) ) {
        $terms = get_terms( [ 'taxonomy' => 'sl_agence_promo', 'hide_empty' => false ] );
        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                $catalog[ $term->slug ] = [ 'slug' => $term->slug, 'name' => $term->name, 'city' => '' ];
            }
        }
    }
    $rows = function_exists( 'sl_lucie_get_agences_repeater' ) ? sl_lucie_get_agences_repeater() : [];
    foreach ( $rows as $row ) {
        $name = trim( (string) ( $row['nom'] ?? '' ) );
        if ( $name === '' ) continue;
        $best = '';
        foreach ( $catalog as $slug => $agency ) {
            $term_name = sl_lucie_normalize_text( $agency['name'] );
            $row_name  = sl_lucie_normalize_text( $name );
            if ( $term_name === $row_name || str_contains( $term_name, $row_name ) || str_contains( $row_name, $term_name ) || $slug === sanitize_title( $name ) ) { $best = $slug; break; }
        }
        if ( $best === '' ) $best = sanitize_title( $name );
        $catalog[ $best ] = [ 'slug' => $best, 'name' => $name, 'city' => trim( (string) ( $row['ville'] ?? '' ) ) ];
    }
    return array_values( $catalog );
}

function sl_lucie_resolve_agencies( $agency = '', $city = '' ) {
    $agency_query = sl_lucie_normalize_text( $agency );
    $city_query   = sl_lucie_normalize_text( $city );
    $catalog      = sl_lucie_agency_catalog();
    if ( $agency_query === '' && $city_query === '' ) return $catalog;
    $matches = [];
    foreach ( $catalog as $item ) {
        $name = sl_lucie_normalize_text( $item['name'] );
        $slug = sl_lucie_normalize_text( str_replace( '-', ' ', $item['slug'] ) );
        $town = sl_lucie_normalize_text( $item['city'] );
        $agency_ok = $agency_query === '' || str_contains( $name, $agency_query ) || str_contains( $agency_query, $name ) || str_contains( $slug, $agency_query );
        $city_ok   = $city_query === '' || str_contains( $town, $city_query ) || str_contains( $city_query, $town );
        if ( $agency_ok && $city_ok ) $matches[] = $item;
    }
    // Un visiteur donne parfois une ville dans le champ agence.
    if ( ! $matches && $agency_query !== '' && $city_query === '' ) {
        foreach ( $catalog as $item ) {
            if ( str_contains( sl_lucie_normalize_text( $item['city'] ), $agency_query ) ) $matches[] = $item;
        }
    }
    return $matches;
}

function sl_lucie_recommendation_tokens( $need, $preferences ) {
    $text = sl_lucie_normalize_text( $need . ' ' . $preferences );
    $stop = [ 'avec', 'pour', 'dans', 'sans', 'chez', 'nous', 'vous', 'une', 'des', 'les', 'mon', 'mes', 'notre', 'quelque', 'chose', 'fcfa', 'cfa' ];
    $tokens = preg_split( '/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
    return array_values( array_unique( array_filter( $tokens, fn( $token ) => mb_strlen( $token ) >= 3 && ! in_array( $token, $stop, true ) ) ) );
}

function sl_lucie_recommendation_candidates( $allowed_slugs, $tokens, $location_required ) {
    $ids = get_posts( [
        'post_type'              => 'product',
        'post_status'            => 'publish',
        'posts_per_page'         => 400,
        'fields'                 => 'ids',
        'orderby'                => 'date',
        'order'                  => 'DESC',
        'no_found_rows'          => true,
        'update_post_meta_cache' => true,
        'update_post_term_cache' => true,
    ] );
    $out = [];
    foreach ( $ids as $id ) {
        $card = sl_lucie_product_card( $id );
        if ( ! $card || ! $card['addable'] || $card['price_raw'] <= 0 ) continue;
        $slug = $card['agency_slug'];
        if ( $location_required && ( $slug === '' || ! in_array( $slug, $allowed_slugs, true ) ) ) continue;

        $categories = wp_get_post_terms( $id, 'product_cat', [ 'fields' => 'names' ] );
        $haystack   = sl_lucie_normalize_text( $card['name'] . ' ' . implode( ' ', is_wp_error( $categories ) ? [] : $categories ) . ' ' . get_post_field( 'post_excerpt', $id ) );
        $score = 10;
        if ( $card['regular_price'] !== '' ) $score += 22;
        foreach ( $tokens as $token ) if ( str_contains( $haystack, $token ) ) $score += 55;
        if ( (int) get_post_meta( $id, '_sl_ff_source_id', true ) > 0 ) $score += 8;
        $card['match_score'] = $score;
        $out[] = $card;
    }
    return $out;
}

/** Selection bornee de produits uniques, optimisee par pertinence puis budget utilise. */
function sl_lucie_best_basket( $candidates, $budget, $max_items ) {
    if ( ! $candidates ) return null;
    $dedup = [];
    foreach ( $candidates as $card ) {
        $key = sl_lucie_normalize_text( $card['name'] );
        if ( ! isset( $dedup[ $key ] ) || $card['match_score'] > $dedup[ $key ]['match_score'] || $card['price_raw'] < $dedup[ $key ]['price_raw'] ) $dedup[ $key ] = $card;
    }
    $candidates = array_values( $dedup );
    usort( $candidates, function ( $a, $b ) {
        return $b['match_score'] === $a['match_score'] ? $a['price_raw'] <=> $b['price_raw'] : $b['match_score'] <=> $a['match_score'];
    } );
    $candidates = array_slice( $candidates, 0, 80 );

    $scale = max( 1, (int) ceil( $budget / 5000 ) );
    $cap   = (int) floor( $budget / $scale );
    $states = [ 0 => [ 'utility' => 0, 'spent' => 0.0, 'items' => [] ] ];
    foreach ( $candidates as $index => $card ) {
        $cost = max( 1, (int) ceil( $card['price_raw'] / $scale ) );
        if ( $cost > $cap ) continue;
        $snapshot = $states;
        krsort( $snapshot );
        foreach ( $snapshot as $used => $state ) {
            if ( count( $state['items'] ) >= $max_items ) continue;
            $next = $used + $cost;
            if ( $next > $cap || $state['spent'] + $card['price_raw'] > $budget ) continue;
            $candidate = [
                'utility' => $state['utility'] + (int) $card['match_score'],
                'spent'   => $state['spent'] + (float) $card['price_raw'],
                'items'   => array_merge( $state['items'], [ $index ] ),
            ];
            if ( ! isset( $states[ $next ] ) || $candidate['utility'] > $states[ $next ]['utility'] || ( $candidate['utility'] === $states[ $next ]['utility'] && $candidate['spent'] > $states[ $next ]['spent'] ) ) {
                $states[ $next ] = $candidate;
            }
        }
    }
    $best = null;
    foreach ( $states as $state ) {
        if ( ! $state['items'] ) continue;
        if ( ! $best || $state['utility'] > $best['utility'] || ( $state['utility'] === $best['utility'] && $state['spent'] > $best['spent'] ) ) $best = $state;
    }
    if ( ! $best ) return null;
    $best['products'] = array_map( fn( $index ) => $candidates[ $index ], $best['items'] );
    unset( $best['items'] );
    return $best;
}

function sl_lucie_recommend_budget( $input ) {
    $budget      = (float) ( $input['budget'] ?? 0 );
    $agency      = sanitize_text_field( (string) ( $input['agence'] ?? '' ) );
    $city        = sanitize_text_field( (string) ( $input['ville'] ?? '' ) );
    $need        = sanitize_text_field( (string) ( $input['besoin'] ?? '' ) );
    $preferences = sanitize_text_field( (string) ( $input['preferences'] ?? '' ) );
    $people      = max( 1, min( 10, (int) ( $input['personnes'] ?? 1 ) ) );
    if ( $budget < 100 ) return [ 'ok' => false, 'message' => 'Precise un budget d\'au moins 100 FCFA.' ];
    if ( $agency === '' && $city === '' ) {
        return [ 'ok' => false, 'needs_location' => true, 'message' => 'Precise une agence ou au moins une ville afin que la proposition corresponde a un lieu de retrait reel.' ];
    }

    $location_required = ( $agency !== '' || $city !== '' );
    $agencies = sl_lucie_resolve_agencies( $agency, $city );
    if ( $location_required && ! $agencies ) {
        return [ 'ok' => false, 'message' => 'Je ne trouve pas cette agence ou cette ville dans la liste Santa Lucia. Demande-moi la liste des agences disponibles.' ];
    }
    $allowed = array_values( array_unique( array_map( fn( $item ) => $item['slug'], $agencies ) ) );
    $tokens  = sl_lucie_recommendation_tokens( $need, $preferences );
    $candidates = sl_lucie_recommendation_candidates( $allowed, $tokens, $location_required );
    if ( ! $candidates ) {
        return [
            'ok'      => false,
            'message' => $location_required
                ? 'Aucun produit achetable avec prix et stock confirmes n\'est disponible pour cette agence actuellement.'
                : 'Aucun produit achetable avec prix et stock confirmes n\'est disponible actuellement.',
        ];
    }

    $groups = [];
    foreach ( $candidates as $card ) {
        $key = $card['agency_slug'] !== '' ? $card['agency_slug'] : '_general';
        $groups[ $key ][] = $card;
    }
    $max_items = max( 3, min( 8, $people * 2 ) );
    $best = null;
    $best_slug = '';
    foreach ( $groups as $slug => $cards ) {
        $basket = sl_lucie_best_basket( $cards, $budget, $max_items );
        if ( ! $basket ) continue;
        if ( ! $best || $basket['utility'] > $best['utility'] || ( $basket['utility'] === $best['utility'] && $basket['spent'] > $best['spent'] ) ) {
            $best = $basket;
            $best_slug = $slug;
        }
    }
    if ( ! $best ) return [ 'ok' => false, 'message' => 'Le budget est inferieur au prix des produits disponibles correspondant a la demande.' ];

    foreach ( $best['products'] as &$card ) $card['recommended_quantity'] = 1;
    unset( $card );
    sl_lucie_set_ui_cards( $best['products'] );
    $articles = array_map( function ( $card ) {
        return [
            'product_id' => $card['product_id'],
            'nom'        => $card['name'],
            'prix'       => $card['price'],
            'agence'     => $card['agency'],
        ];
    }, $best['products'] );
    $agency_name = $best_slug === '_general' ? '' : sl_lucie_agency_name( $best_slug );
    return [
        'ok'             => true,
        'agence_retenue' => $agency_name,
        'budget'         => sl_lucie_price_text( $budget ),
        'total_propose'  => sl_lucie_price_text( $best['spent'] ),
        'reste'          => sl_lucie_price_text( max( 0, $budget - $best['spent'] ) ),
        'articles'       => $articles,
        'note'           => 'Proposition seulement : aucun article n\'a ete ajoute au panier. Les cartes affichees permettent au client de choisir.',
    ];
}

/* -------------------------------------------------------------------------
 * Garde supplementaire pour les mutations demandees par le modele
 * ---------------------------------------------------------------------- */

function sl_lucie_user_explicit_cart_intent( $action ) {
    $message = sl_lucie_normalize_text( $GLOBALS['sl_lucie_current_message'] ?? '' );
    $patterns = [
        'add'      => '/\b(ajoute|ajouter|ajoutez|mets|met|mettre|commande|commander|prends|prendre)\b/u',
        'remove'   => '/\b(retire|retirer|enleve|enlever|supprime|supprimer)\b/u',
        'clear'    => '/\b(vide|vider|efface|effacer)\b.*\bpanier\b|\bpanier\b.*\b(vide|vider|efface|effacer)\b/u',
        'checkout' => '/\b(valide|valider|finalise|finaliser|payer|paiement|terminer|checkout)\b/u',
    ];
    if ( isset( $patterns[ $action ] ) && preg_match( $patterns[ $action ], $message ) ) return true;

    // Accepte un « oui / je confirme » uniquement si la reponse precedente de
    // Lucie demandait explicitement la meme action. Une confirmation courte ne
    // peut donc jamais muter le panier hors contexte.
    if ( preg_match( '/^(oui|ok|d accord|vas y|je confirme)[.! ]*$/u', $message ) ) {
        $context_patterns = [
            'add'      => '/(confirme|souhaitez|veux tu).{0,80}(ajout|panier)|(ajout|panier).{0,80}(confirme|souhaitez)/u',
            'remove'   => '/(confirme|souhaitez).{0,80}(retir|supprim)|(retir|supprim).{0,80}(confirme|souhaitez)/u',
            'clear'    => '/(confirme|souhaitez).{0,80}(vider|panier)|(vider|panier).{0,80}(confirme|souhaitez)/u',
            'checkout' => '/(confirme|souhaitez).{0,80}(paiement|checkout|valid)|(paiement|checkout|valid).{0,80}(confirme|souhaitez)/u',
        ];
        $messages = array_reverse( (array) ( $GLOBALS['sl_lucie_conversation_messages'] ?? [] ) );
        foreach ( $messages as $entry ) {
            if ( ( $entry['role'] ?? '' ) !== 'assistant' ) continue;
            $previous = sl_lucie_normalize_text( $entry['content'] ?? '' );
            return isset( $context_patterns[ $action ] ) && preg_match( $context_patterns[ $action ], $previous );
        }
    }
    return false;
}

function sl_lucie_tool_add_to_cart( $product_id, $quantity ) {
    if ( ! sl_lucie_user_explicit_cart_intent( 'add' ) ) {
        return [ 'ok' => false, 'message' => 'Aucune modification effectuee : demande au client de confirmer explicitement l\'ajout au panier.' ];
    }
    $product_id = absint( $product_id );
    $allowed = array_map( 'intval', (array) ( $GLOBALS['sl_lucie_allowed_product_ids'] ?? [] ) );
    if ( $product_id <= 0 || ! in_array( $product_id, $allowed, true ) ) {
        return [ 'ok' => false, 'message' => 'Identifiant non verifie. Recherche d\'abord ce produit avec un outil dans cette meme demande, puis utilise exactement le product_id renvoye.' ];
    }
    return sl_lucie_cart_add_product( $product_id, $quantity );
}

function sl_lucie_tool_remove_from_cart( $product_id, $cart_item_key = '' ) {
    if ( ! sl_lucie_user_explicit_cart_intent( 'remove' ) ) {
        return [ 'ok' => false, 'message' => 'Aucune modification effectuee : le retrait doit etre demande explicitement.' ];
    }
    return sl_lucie_cart_remove_product( $cart_item_key, $product_id );
}

function sl_lucie_tool_clear_cart() {
    if ( ! sl_lucie_user_explicit_cart_intent( 'clear' ) ) {
        return [ 'ok' => false, 'message' => 'Aucune modification effectuee : vider le panier exige une demande explicite.' ];
    }
    return sl_lucie_cart_clear();
}

function sl_lucie_tool_checkout() {
    if ( ! sl_lucie_user_explicit_cart_intent( 'checkout' ) ) {
        return [ 'ok' => false, 'message' => 'Demande au client de confirmer qu\'il souhaite passer au paiement.' ];
    }
    $cart = sl_lucie_cart_snapshot();
    sl_lucie_set_ui_cart( $cart );
    if ( empty( $cart['available'] ) || ! empty( $cart['empty'] ) ) return [ 'ok' => false, 'message' => 'Le panier est vide.', 'cart' => $cart ];
    return [
        'ok'           => true,
        'message'      => 'Le panier est pret. Le client doit verifier ses articles puis remplir et valider le checkout securise.',
        'checkout_url' => $cart['checkout_url'],
        'cart'         => $cart,
    ];
}

/** Genere les cartes visuelles a partir d'un resultat promotions/bons plans. */
function sl_lucie_capture_product_cards( $data, $kind = 'products' ) {
    $items = [];
    if ( is_array( $data ) && isset( $data['items'] ) && is_array( $data['items'] ) ) $items = $data['items'];
    elseif ( is_array( $data ) && isset( $data['produits'] ) && is_array( $data['produits'] ) ) $items = $data['produits'];
    elseif ( is_array( $data ) && array_is_list( $data ) ) $items = $data;
    $cards = [];
    foreach ( array_slice( $items, 0, 12 ) as $item ) {
        if ( ! is_array( $item ) ) continue;
        $id = (int) ( $item['product_id'] ?? $item['id'] ?? 0 );
        if ( $kind === 'bons_plans' && $id && function_exists( 'sl_bp_product_id_for' ) ) $id = sl_bp_product_id_for( $id );
        $card = $id ? sl_lucie_product_card( $id ) : null;
        if ( $card ) $cards[] = $card;
    }
    if ( $cards ) sl_lucie_set_ui_cards( $cards );
    return $cards;
}
