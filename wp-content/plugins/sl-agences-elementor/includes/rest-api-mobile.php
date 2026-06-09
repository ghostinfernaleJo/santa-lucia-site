<?php
/**
 * API REST publique (lecture seule) pour l'application mobile React Native.
 * Namespace : santa-lucia/v1
 *
 * Expose les mêmes données que le site public :
 *   - Agences (taxonomie partagée sl_agence_promo)
 *   - Fast Food : catégories + menu (CPT sl_repas)
 *   - Bons Plans : catégories + offres (CPT sl_bon_plan)
 *
 * Aucune authentification (données déjà publiques sur le site).
 * Les gestionnaires publient via wp-admin -> l'app reçoit instantanément les mêmes données.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'rest_api_init', 'slm_register_rest_routes' );
function slm_register_rest_routes() {
    $ns   = 'santa-lucia/v1';
    $open = '__return_true';

    register_rest_route( $ns, '/agences', [
        'methods'             => 'GET',
        'callback'            => 'slm_rest_agences',
        'permission_callback' => $open,
    ] );

    register_rest_route( $ns, '/fastfood/categories', [
        'methods'             => 'GET',
        'callback'            => 'slm_rest_ff_categories',
        'permission_callback' => $open,
    ] );

    register_rest_route( $ns, '/fastfood/menu', [
        'methods'             => 'GET',
        'callback'            => 'slm_rest_ff_menu',
        'permission_callback' => $open,
        'args'                => [
            'agence'   => [ 'description' => 'Slug d\'agence (ex: akwa).', 'type' => 'string' ],
            'jour'     => [ 'description' => 'lundi..dimanche, ou "today" pour le jour courant.', 'type' => 'string' ],
            'category' => [ 'description' => 'ID de catégorie (sl_repas_cat).', 'type' => 'integer' ],
            'page'     => [ 'description' => 'Page (défaut 1).', 'type' => 'integer' ],
            'per_page' => [ 'description' => 'Éléments par page (1-100, défaut 50).', 'type' => 'integer' ],
        ],
    ] );

    register_rest_route( $ns, '/bons-plans/categories', [
        'methods'             => 'GET',
        'callback'            => 'slm_rest_bp_categories',
        'permission_callback' => $open,
    ] );

    register_rest_route( $ns, '/bons-plans', [
        'methods'             => 'GET',
        'callback'            => 'slm_rest_bons_plans',
        'permission_callback' => $open,
        'args'                => [
            'agence'    => [ 'description' => 'Slug d\'agence.', 'type' => 'string' ],
            'categorie' => [ 'description' => 'ID de catégorie (sl_categorie_promo).', 'type' => 'integer' ],
            'orderby'   => [ 'description' => 'reduc | prix_asc | prix_desc | date (défaut date).', 'type' => 'string' ],
            'actifs'    => [ 'description' => 'true = seulement non expirés (défaut true).', 'type' => 'boolean' ],
            'page'      => [ 'description' => 'Page (défaut 1).', 'type' => 'integer' ],
            'per_page'  => [ 'description' => 'Éléments par page (1-100, défaut 50).', 'type' => 'integer' ],
        ],
    ] );
}

/* ---------- Helpers ---------- */
function slm_page_args( $req ) {
    $page = max( 1, (int) $req->get_param( 'page' ) );
    $per  = (int) $req->get_param( 'per_page' );
    $per  = $per ? min( 100, max( 1, $per ) ) : 50;
    return [ $page, $per ];
}
function slm_paginated( $items, $query, $page, $per ) {
    return rest_ensure_response( [
        'items'      => $items,
        'pagination' => [
            'total'       => (int) $query->found_posts,
            'page'        => (int) $page,
            'per_page'    => (int) $per,
            'total_pages' => (int) $query->max_num_pages,
        ],
    ] );
}

/* ---------- Agences ---------- */
function slm_rest_agences() {
    $terms = get_terms( [ 'taxonomy' => 'sl_agence_promo', 'hide_empty' => false, 'orderby' => 'name' ] );
    if ( is_wp_error( $terms ) ) return rest_ensure_response( [] );
    $out = [];
    foreach ( $terms as $t ) {
        $out[] = [ 'id' => $t->term_id, 'slug' => $t->slug, 'nom' => $t->name, 'nombre' => (int) $t->count ];
    }
    return rest_ensure_response( $out );
}

/* ---------- Fast Food : catégories ---------- */
function slm_rest_ff_categories() {
    $terms = get_terms( [ 'taxonomy' => 'sl_repas_cat', 'hide_empty' => false, 'orderby' => 'name' ] );
    if ( is_wp_error( $terms ) ) return rest_ensure_response( [] );
    $out = [];
    foreach ( $terms as $t ) {
        $out[] = [
            'id'          => $t->term_id,
            'slug'        => $t->slug,
            'nom'         => $t->name,
            'nom_affiche' => function_exists( 'sl_ff_cat_display' ) ? sl_ff_cat_display( $t->name ) : $t->name,
        ];
    }
    return rest_ensure_response( $out );
}

/* ---------- Fast Food : menu ---------- */
function slm_rest_ff_menu( $req ) {
    list( $page, $per ) = slm_page_args( $req );
    $agence = sanitize_title( (string) $req->get_param( 'agence' ) );
    $jour   = strtolower( trim( (string) $req->get_param( 'jour' ) ) );
    $cat    = (int) $req->get_param( 'category' );

    if ( $jour === 'today' || $jour === 'aujourdhui' ) {
        $jour = function_exists( 'sl_ff_today_jour' ) ? sl_ff_today_jour() : '';
    }

    $args = [
        'post_type'      => 'sl_repas',
        'post_status'    => 'publish',
        'posts_per_page' => $per,
        'paged'          => $page,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ];
    $meta = [];
    if ( $agence ) {
        $meta[] = function_exists( 'sl_ff_agency_meta_query' )
            ? sl_ff_agency_meta_query( $agence )
            : [ 'key' => '_sl_ff_agence', 'value' => $agence ];
    }
    if ( $jour ) {
        $meta[] = function_exists( 'sl_ff_day_meta_query' )
            ? sl_ff_day_meta_query( $jour )
            : [ 'key' => '_sl_ff_jours', 'value' => '"' . $jour . '"', 'compare' => 'LIKE' ];
    }
    if ( $meta ) {
        if ( count( $meta ) > 1 ) $meta['relation'] = 'AND';
        $args['meta_query'] = $meta;
    }
    if ( $cat ) {
        $args['tax_query'] = [ [ 'taxonomy' => 'sl_repas_cat', 'field' => 'term_id', 'terms' => $cat ] ];
    }

    $q     = new WP_Query( $args );
    $items = array_map( 'slm_format_repas', $q->posts );
    return slm_paginated( $items, $q, $page, $per );
}

function slm_format_repas( $post ) {
    $id    = $post->ID;
    $jours = get_post_meta( $id, '_sl_ff_jours', true );
    if ( ! is_array( $jours ) ) $jours = [];
    $cats  = wp_get_post_terms( $id, 'sl_repas_cat' );
    $cat   = ( ! is_wp_error( $cats ) && $cats ) ? [
        'id'          => $cats[0]->term_id,
        'nom'         => $cats[0]->name,
        'nom_affiche' => function_exists( 'sl_ff_cat_display' ) ? sl_ff_cat_display( $cats[0]->name ) : $cats[0]->name,
    ] : null;

    $promo_prix = get_post_meta( $id, '_sl_ff_promo_prix', true );
    $promo = ( $promo_prix !== '' && (float) $promo_prix > 0 ) ? [
        'prix'  => (float) $promo_prix,
        'debut' => get_post_meta( $id, '_sl_ff_promo_debut', true ) ?: null,
        'fin'   => get_post_meta( $id, '_sl_ff_promo_fin', true ) ?: null,
    ] : null;

    $img = function_exists( 'sl_ff_item_image_url' )
        ? sl_ff_item_image_url( $id, 'large' )
        : get_the_post_thumbnail_url( $id, 'large' );

    $today = function_exists( 'sl_ff_today_jour' ) ? sl_ff_today_jour() : '';

    return [
        'id'                    => $id,
        'titre'                 => get_the_title( $id ),
        'agence'                => get_post_meta( $id, '_sl_ff_agence', true ) ?: null,
        'categorie'             => $cat,
        'jours'                 => array_values( $jours ),
        'disponible_aujourdhui' => $today ? in_array( $today, $jours, true ) : null,
        'promo'                 => $promo,
        'image'                 => $img ?: null,
    ];
}

/* ---------- Bons Plans : catégories ---------- */
function slm_rest_bp_categories() {
    $terms = get_terms( [ 'taxonomy' => 'sl_categorie_promo', 'hide_empty' => false, 'orderby' => 'name' ] );
    if ( is_wp_error( $terms ) ) return rest_ensure_response( [] );
    $out = [];
    foreach ( $terms as $t ) {
        $out[] = [ 'id' => $t->term_id, 'slug' => $t->slug, 'nom' => $t->name ];
    }
    return rest_ensure_response( $out );
}

/* ---------- Bons Plans : offres ---------- */
function slm_rest_bons_plans( $req ) {
    list( $page, $per ) = slm_page_args( $req );
    $agence  = sanitize_title( (string) $req->get_param( 'agence' ) );
    $cat     = (int) $req->get_param( 'categorie' );
    $orderby = sanitize_key( (string) $req->get_param( 'orderby' ) );
    $actifs  = $req->get_param( 'actifs' );
    $actifs  = ( $actifs === null ) ? true : rest_sanitize_boolean( $actifs );

    $args = [
        'post_type'      => 'sl_bon_plan',
        'post_status'    => 'publish',
        'posts_per_page' => $per,
        'paged'          => $page,
    ];

    switch ( $orderby ) {
        case 'reduc':
            $args['meta_key'] = '_sl_bp_reduction_pct'; $args['orderby'] = 'meta_value_num'; $args['order'] = 'DESC'; break;
        case 'prix_asc':
            $args['meta_key'] = '_sl_bp_prix_apres'; $args['orderby'] = 'meta_value_num'; $args['order'] = 'ASC'; break;
        case 'prix_desc':
            $args['meta_key'] = '_sl_bp_prix_apres'; $args['orderby'] = 'meta_value_num'; $args['order'] = 'DESC'; break;
        default:
            $args['orderby'] = 'date'; $args['order'] = 'DESC';
    }

    $meta = [];
    if ( $actifs ) {
        $today  = current_time( 'Y-m-d' );
        $meta[] = [
            'relation' => 'OR',
            [ 'key' => '_sl_bp_date_fin', 'value' => $today, 'compare' => '>=', 'type' => 'DATE' ],
            [ 'key' => '_sl_bp_date_fin', 'value' => '', 'compare' => '=' ],
            [ 'key' => '_sl_bp_date_fin', 'compare' => 'NOT EXISTS' ],
        ];
    }
    if ( $meta ) $args['meta_query'] = $meta;

    $tax = [];
    if ( $agence ) $tax[] = [ 'taxonomy' => 'sl_agence_promo', 'field' => 'slug', 'terms' => $agence ];
    if ( $cat )    $tax[] = [ 'taxonomy' => 'sl_categorie_promo', 'field' => 'term_id', 'terms' => $cat ];
    if ( $tax ) {
        if ( count( $tax ) > 1 ) $tax['relation'] = 'AND';
        $args['tax_query'] = $tax;
    }

    $q     = new WP_Query( $args );
    $items = array_map( 'slm_format_bon_plan', $q->posts );
    return slm_paginated( $items, $q, $page, $per );
}

function slm_format_bon_plan( $post ) {
    $id   = $post->ID;
    $cats = wp_get_post_terms( $id, 'sl_categorie_promo' );
    $cat  = ( ! is_wp_error( $cats ) && $cats ) ? [ 'id' => $cats[0]->term_id, 'nom' => $cats[0]->name ] : null;
    $ags  = wp_get_post_terms( $id, 'sl_agence_promo' );
    $agence = ( ! is_wp_error( $ags ) && $ags ) ? $ags[0]->slug : ( get_post_meta( $id, 'sl_agence_assignee', true ) ?: null );

    $img = get_the_post_thumbnail_url( $id, 'large' );

    return [
        'id'            => $id,
        'titre'         => get_the_title( $id ),
        'agence'        => $agence,
        'categorie'     => $cat,
        'prix_avant'    => (float) get_post_meta( $id, '_sl_bp_prix_avant', true ),
        'prix_apres'    => (float) get_post_meta( $id, '_sl_bp_prix_apres', true ),
        'reduction_pct' => (int) get_post_meta( $id, '_sl_bp_reduction_pct', true ),
        'economie'      => (float) get_post_meta( $id, '_sl_bp_save', true ),
        'badge'         => get_post_meta( $id, '_sl_bp_badge_type', true ) ?: null,
        'date_fin'      => get_post_meta( $id, '_sl_bp_date_fin', true ) ?: null,
        'image'         => $img ?: null,
    ];
}
