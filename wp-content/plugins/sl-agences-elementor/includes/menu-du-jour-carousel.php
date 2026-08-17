<?php
/**
 * Données et rendu du widget Elementor « Menu du jour ».
 *
 * Le widget réutilise le CPT Fast Food existant : il ne duplique ni les
 * repas, ni les prix, ni les règles de disponibilité par agence.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Retourne les agences utilisées par le Fast Food. */
function sl_mdt_get_agencies() {
    $agencies = get_terms( [
        'taxonomy'   => 'sl_agence_promo',
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ] );

    return is_wp_error( $agencies ) ? [] : $agencies;
}

/** Retourne le terme d'agence, ou null si le slug n'est pas valide. */
function sl_mdt_get_agency( $agency ) {
    $agency = sanitize_title( $agency );
    if ( $agency === '' ) return null;

    $term = get_term_by( 'slug', $agency, 'sl_agence_promo' );
    return ( $term && ! is_wp_error( $term ) ) ? $term : null;
}

/** Nom lisible d'une agence, en utilisant la normalisation Fast Food si disponible. */
function sl_mdt_agency_name( $agency ) {
    $term = sl_mdt_get_agency( $agency );
    if ( ! $term ) return '';

    return function_exists( 'sl_ff_agency_name' )
        ? sl_ff_agency_name( $term->name )
        : $term->name;
}

/** Récupère les repas réellement disponibles aujourd'hui dans une agence. */
function sl_mdt_get_today_meals( $agency, $limit = 12 ) {
    $agency = sanitize_title( $agency );
    if ( $agency === '' || ! post_type_exists( 'sl_repas' ) ) return [];

    $limit = max( 1, min( 24, (int) $limit ) );
    $meta  = function_exists( 'sl_ff_agency_meta_query' )
        ? sl_ff_agency_meta_query( $agency )
        : [ 'key' => '_sl_ff_agence', 'value' => $agency ];

    $meals = get_posts( [
        'post_type'      => 'sl_repas',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'meta_query'     => [ $meta ],
    ] );

    if ( function_exists( 'sl_ff_filter_repas_available_for_agence' ) && function_exists( 'sl_ff_today_jour' ) ) {
        $meals = sl_ff_filter_repas_available_for_agence( $meals, $agency, sl_ff_today_jour() );
    }
    if ( function_exists( 'sl_ff_dedupe_repas_by_title' ) ) {
        $meals = sl_ff_dedupe_repas_by_title( $meals, $agency );
    }

    return array_slice( array_values( $meals ), 0, $limit );
}

/** Choisit la première agence ayant un menu aujourd'hui, avec cache journalier. */
function sl_mdt_find_first_available_agency( $agencies ) {
    if ( empty( $agencies ) ) return '';

    $version = function_exists( 'sl_ff_menu_cache_ver' ) ? sl_ff_menu_cache_ver() : 1;
    $today   = current_time( 'Y-m-d' );
    $key     = 'sl_mdt_default_agency_' . $version . '_' . md5( $today );
    $cached  = get_transient( $key );

    if ( is_string( $cached ) && $cached !== '' && sl_mdt_get_agency( $cached ) ) {
        return $cached;
    }

    if ( post_type_exists( 'sl_repas' ) && function_exists( 'sl_ff_post_agence_slugs' )
        && function_exists( 'sl_ff_is_repas_available_for_agence' ) && function_exists( 'sl_ff_today_jour' ) ) {
        $available = [];
        $day       = sl_ff_today_jour();
        $all_meals = get_posts( [
            'post_type'      => 'sl_repas',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
        ] );

        foreach ( $all_meals as $meal ) {
            foreach ( sl_ff_post_agence_slugs( $meal->ID ) as $agency ) {
                if ( sl_ff_is_repas_available_for_agence( $meal->ID, $agency, $day ) ) {
                    $available[ $agency ] = true;
                }
            }
        }

        foreach ( $agencies as $agency ) {
            if ( ! empty( $available[ $agency->slug ] ) ) {
                set_transient( $key, $agency->slug, 6 * HOUR_IN_SECONDS );
                return $agency->slug;
            }
        }
    }

    return isset( $agencies[0] ) ? $agencies[0]->slug : '';
}

/** Retourne la catégorie lisible d'un repas. */
function sl_mdt_meal_category( $meal_id ) {
    $terms = wp_get_post_terms( $meal_id, 'sl_repas_cat' );
    if ( empty( $terms ) || is_wp_error( $terms ) ) return 'Menu du jour';

    return function_exists( 'sl_ff_cat_display' )
        ? sl_ff_cat_display( $terms[0]->name )
        : $terms[0]->name;
}

/** Lit le prix et la promotion d'un repas en conservant les règles par agence. */
function sl_mdt_meal_price( $meal_id, $agency ) {
    if ( function_exists( 'sl_ff_get_promo_info' ) ) {
        return sl_ff_get_promo_info( $meal_id, $agency );
    }

    return [
        'est_promo'     => false,
        'pct_reduction' => 0,
        'prix'          => (int) get_post_meta( $meal_id, '_sl_ff_prix', true ),
        'prix_promo'    => 0,
    ];
}

/** Produit le HTML des cartes, ou l'état vide, pour une agence donnée. */
function sl_mdt_menu_payload( $agency, $limit = 12, $show_order_button = true ) {
    $agency = sanitize_title( $agency );
    $limit  = max( 1, min( 24, (int) $limit ) );
    $show_order_button = (bool) $show_order_button;

    $version = function_exists( 'sl_ff_menu_cache_ver' ) ? sl_ff_menu_cache_ver() : 1;
    $key     = 'sl_mdt_cards_' . $version . '_' . md5( implode( '|', [ $agency, current_time( 'Y-m-d' ), $limit, (int) $show_order_button ] ) );
    $cached  = get_transient( $key );
    if ( is_array( $cached ) && isset( $cached['html'], $cached['count'] ) ) {
        return $cached;
    }

    $agency_name = sl_mdt_agency_name( $agency );
    $meals       = sl_mdt_get_today_meals( $agency, $limit );
    $count       = count( $meals );

    if ( $count === 0 ) {
        $payload = [
            'count' => 0,
            'html'  => '<div class="sl-mdt-empty" role="status"><span class="sl-mdt-empty-icon" aria-hidden="true">🍽️</span><strong>Aucun menu disponible aujourd’hui</strong><p>Cette agence n’a pas encore publié de repas pour aujourd’hui.</p></div>',
        ];
        set_transient( $key, $payload, 6 * HOUR_IN_SECONDS );
        return $payload;
    }

    ob_start();
    ?>
    <div class="sl-mdt-track" tabindex="0" aria-label="Repas du jour à <?php echo esc_attr( $agency_name ); ?>">
        <?php foreach ( $meals as $meal ) :
            $image = function_exists( 'sl_ff_item_image_url' )
                ? sl_ff_item_image_url( $meal->ID, 'medium_large' )
                : get_the_post_thumbnail_url( $meal->ID, 'medium_large' );
            $price = sl_mdt_meal_price( $meal->ID, $agency );
            $is_promo = ! empty( $price['est_promo'] ) && ! empty( $price['prix_promo'] );
            $current_price = $is_promo ? (int) $price['prix_promo'] : (int) $price['prix'];
            $order_url = ( $show_order_button && $current_price > 0 && function_exists( 'sl_ff_order_url' ) )
                ? sl_ff_order_url( $meal->ID, $agency ) : '';
        ?>
        <article class="sl-mdt-card">
            <div class="sl-mdt-card-image">
                <?php if ( $image ) : ?>
                    <img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $meal->post_title ); ?>" loading="lazy" decoding="async">
                <?php else : ?>
                    <span class="sl-mdt-image-placeholder" aria-hidden="true">🍲</span>
                <?php endif; ?>
                <?php if ( $is_promo && ! empty( $price['pct_reduction'] ) ) : ?>
                    <span class="sl-mdt-promo-badge">-<?php echo (int) $price['pct_reduction']; ?>%</span>
                <?php endif; ?>
            </div>
            <div class="sl-mdt-card-body">
                <p class="sl-mdt-category"><?php echo esc_html( sl_mdt_meal_category( $meal->ID ) ); ?></p>
                <h3><?php echo esc_html( $meal->post_title ); ?></h3>
                <div class="sl-mdt-price">
                    <?php if ( $current_price > 0 ) : ?>
                        <strong><?php echo esc_html( function_exists( 'sl_ff_format_prix' ) ? sl_ff_format_prix( $current_price ) : number_format( $current_price, 0, ',', ' ' ) . ' FCFA' ); ?></strong>
                        <?php if ( $is_promo && ! empty( $price['prix'] ) ) : ?>
                            <del><?php echo esc_html( function_exists( 'sl_ff_format_prix' ) ? sl_ff_format_prix( $price['prix'] ) : number_format( (int) $price['prix'], 0, ',', ' ' ) . ' FCFA' ); ?></del>
                        <?php endif; ?>
                    <?php else : ?>
                        <span>Prix à confirmer</span>
                    <?php endif; ?>
                </div>
                <?php if ( $order_url ) : ?>
                    <a class="sl-mdt-order" href="<?php echo esc_url( $order_url ); ?>">Commander <span aria-hidden="true">→</span></a>
                <?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php

    $payload = [ 'count' => $count, 'html' => ob_get_clean() ];
    set_transient( $key, $payload, 6 * HOUR_IN_SECONDS );
    return $payload;
}

/** Endpoint public utilisé lors du changement d'agence dans le widget. */
add_action( 'wp_ajax_sl_mdt_load_menu', 'sl_mdt_ajax_load_menu' );
add_action( 'wp_ajax_nopriv_sl_mdt_load_menu', 'sl_mdt_ajax_load_menu' );
function sl_mdt_ajax_load_menu() {
    check_ajax_referer( 'sl_mdt_load_menu', 'nonce' );

    $agency = isset( $_POST['agence'] ) ? sanitize_title( wp_unslash( $_POST['agence'] ) ) : '';
    if ( ! sl_mdt_get_agency( $agency ) ) {
        wp_send_json_error( [ 'message' => 'Agence introuvable.' ], 400 );
    }

    $limit = isset( $_POST['limit'] ) ? (int) $_POST['limit'] : 12;
    $show  = ! empty( $_POST['show_order_button'] );
    $data  = sl_mdt_menu_payload( $agency, $limit, $show );

    wp_send_json_success( [
        'html'        => $data['html'],
        'count'       => $data['count'],
        'agency_name' => sl_mdt_agency_name( $agency ),
    ] );
}
