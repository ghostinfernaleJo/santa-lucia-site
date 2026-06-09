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

    register_rest_route( $ns, '/promotions/campagnes', [
        'methods'             => 'GET',
        'callback'            => 'slm_rest_promo_campagnes',
        'permission_callback' => $open,
    ] );

    register_rest_route( $ns, '/promotions', [
        'methods'             => 'GET',
        'callback'            => 'slm_rest_promotions',
        'permission_callback' => $open,
        'args'                => [
            'campagne' => [ 'description' => 'ID d\'une campagne (sl_campagne_woo) pour ne garder que ses produits.', 'type' => 'integer' ],
            'category' => [ 'description' => 'ID de catégorie produit WooCommerce (product_cat).', 'type' => 'integer' ],
            'page'     => [ 'description' => 'Page (défaut 1).', 'type' => 'integer' ],
            'per_page' => [ 'description' => 'Éléments par page (1-100, défaut 50).', 'type' => 'integer' ],
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
function slm_empty_page( $page, $per ) {
    return rest_ensure_response( [
        'items'      => [],
        'pagination' => [ 'total' => 0, 'page' => (int) $page, 'per_page' => (int) $per, 'total_pages' => 0 ],
    ] );
}

/**
 * Objet image multi-tailles à partir d'un ID d'attachement (ou null).
 */
function slm_image( $att_id ) {
    $att_id = (int) $att_id;
    if ( ! $att_id ) return null;
    $full = wp_get_attachment_image_url( $att_id, 'full' );
    if ( ! $full ) return null;
    return [
        'thumbnail' => wp_get_attachment_image_url( $att_id, 'thumbnail' ) ?: $full,
        'medium'    => wp_get_attachment_image_url( $att_id, 'medium' ) ?: $full,
        'large'     => wp_get_attachment_image_url( $att_id, 'large' ) ?: $full,
        'full'      => $full,
    ];
}

/**
 * Image d'un plat Fast Food : résolue par nom de plat (option sl_ff_dish_images),
 * avec repli sur l'image à la une du post. Renvoie un objet multi-tailles ou null.
 */
function slm_ff_image( $post_id ) {
    $att = 0;
    if ( function_exists( 'sl_ff_dish_image_id' ) ) {
        $att = (int) sl_ff_dish_image_id( get_the_title( $post_id ) );
    }
    if ( ! $att ) {
        $att = (int) get_post_thumbnail_id( $post_id );
    }
    return slm_image( $att );
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

    $today = function_exists( 'sl_ff_today_jour' ) ? sl_ff_today_jour() : '';

    return [
        'id'                    => $id,
        'titre'                 => get_the_title( $id ),
        'agence'                => get_post_meta( $id, '_sl_ff_agence', true ) ?: null,
        'categorie'             => $cat,
        'jours'                 => array_values( $jours ),
        'disponible_aujourdhui' => $today ? in_array( $today, $jours, true ) : null,
        'promo'                 => $promo,
        'image'                 => slm_ff_image( $id ),
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
        'image'         => slm_image( get_post_thumbnail_id( $id ) ),
    ];
}

/* ---------- Promotions (produits WooCommerce en campagne + soldes) ---------- */

/** Liste des campagnes (sl_campagne_woo) avec leur catégorie produit liée. */
function slm_rest_promo_campagnes() {
    $today = current_time( 'Y-m-d' );
    $camps = get_posts( [ 'post_type' => 'sl_campagne_woo', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'DESC' ] );
    $out = [];
    foreach ( $camps as $c ) {
        $dd = get_post_meta( $c->ID, '_sl_cwoo_date_debut', true );
        $df = get_post_meta( $c->ID, '_sl_cwoo_date_fin', true );
        $active = ( empty( $dd ) || $dd <= $today ) && ( empty( $df ) || $df >= $today );
        $out[] = [
            'id'           => $c->ID,
            'titre'        => $c->post_title,
            'categorie_id' => (int) get_post_meta( $c->ID, '_sl_cwoo_term_id', true ),
            'date_debut'   => $dd ?: null,
            'date_fin'     => $df ?: null,
            'active'       => (bool) $active,
        ];
    }
    return rest_ensure_response( $out );
}

/** Produits en promotion : campagnes actives + produits en solde natif WooCommerce. */
function slm_rest_promotions( $req ) {
    list( $page, $per ) = slm_page_args( $req );
    $cat      = (int) $req->get_param( 'category' );
    $campagne = (int) $req->get_param( 'campagne' );

    $ids = [];
    if ( function_exists( 'sl_cwoo_get_active_campaign_product_ids' ) ) {
        $ids = array_merge( $ids, (array) sl_cwoo_get_active_campaign_product_ids() );
    }
    if ( function_exists( 'sl_cwoo_get_native_sale_product_ids' ) ) {
        $ids = array_merge( $ids, (array) sl_cwoo_get_native_sale_product_ids() );
    }
    $ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
    if ( empty( $ids ) ) {
        return slm_empty_page( $page, $per );
    }

    if ( $campagne ) {
        $term = (int) get_post_meta( $campagne, '_sl_cwoo_term_id', true );
        if ( $term ) $cat = $term;
    }

    $args = [
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'post__in'       => $ids,
        'posts_per_page' => $per,
        'paged'          => $page,
        'orderby'        => 'post__in',
    ];
    if ( $cat ) {
        $args['tax_query'] = [ [ 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $cat ] ];
    }

    $q     = new WP_Query( $args );
    $items = array_map( 'slm_format_product', $q->posts );
    return slm_paginated( $items, $q, $page, $per );
}

function slm_format_product( $post ) {
    $id = $post->ID;
    $reg = $sale = $price = null;
    $on_sale = false;

    if ( function_exists( 'wc_get_product' ) ) {
        $p = wc_get_product( $id );
        if ( $p ) {
            $reg     = ( $p->get_regular_price() !== '' ) ? (float) $p->get_regular_price() : null;
            $sale    = ( $p->get_sale_price() !== '' ) ? (float) $p->get_sale_price() : null;
            $price   = ( $p->get_price() !== '' ) ? (float) $p->get_price() : null;
            $on_sale = $p->is_on_sale();
        }
    } else {
        $reg   = (float) get_post_meta( $id, '_regular_price', true ) ?: null;
        $sale  = (float) get_post_meta( $id, '_sale_price', true ) ?: null;
        $price = (float) get_post_meta( $id, '_price', true ) ?: null;
        $on_sale = ( $sale && $reg && $sale < $reg );
    }

    $reduction = ( $reg && $sale && $sale < $reg ) ? (int) round( 100 * ( $reg - $sale ) / $reg ) : 0;

    $cats = wp_get_post_terms( $id, 'product_cat' );
    $catlist = ( ! is_wp_error( $cats ) ) ? array_map( function ( $t ) {
        return [ 'id' => $t->term_id, 'slug' => $t->slug, 'nom' => $t->name ];
    }, $cats ) : [];

    return [
        'id'            => $id,
        'titre'         => get_the_title( $id ),
        'prix_avant'    => $reg,
        'prix_apres'    => ( $sale !== null ? $sale : $price ),
        'reduction_pct' => $reduction,
        'en_promo'      => (bool) $on_sale,
        'categories'    => $catlist,
        'image'         => slm_image( get_post_thumbnail_id( $id ) ),
        'permalink'     => get_permalink( $id ),
    ];
}
