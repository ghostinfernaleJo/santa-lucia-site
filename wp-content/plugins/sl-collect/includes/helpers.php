<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** Liste des agences (termes de la taxonomie sl_agence_promo). */
function slc_agences() {
    $terms = get_terms( [ 'taxonomy' => 'sl_agence_promo', 'hide_empty' => false, 'orderby' => 'name' ] );
    return is_wp_error( $terms ) ? [] : $terms;
}

/** Slug d'agence -> nom affichable. */
function slc_agence_name( $slug ) {
    $t = get_term_by( 'slug', sanitize_title( (string) $slug ), 'sl_agence_promo' );
    return ( $t && ! is_wp_error( $t ) ) ? $t->name : (string) $slug;
}

/**
 * Agence (slug) d'un utilisateur responsable.
 * Resolue depuis _sl_agence_ff (slug, Fast Food) puis sl_agence_assignee
 * (nom, Bons Plans) — les deux rattachements profil existants.
 */
function slc_user_agence_slug( $user_id = 0 ) {
    $user_id = $user_id ? (int) $user_id : get_current_user_id();
    if ( ! $user_id ) return '';

    $slug = sanitize_title( (string) get_user_meta( $user_id, '_sl_agence_ff', true ) );
    if ( $slug !== '' && get_term_by( 'slug', $slug, 'sl_agence_promo' ) ) {
        return $slug;
    }

    $name = (string) get_user_meta( $user_id, 'sl_agence_assignee', true );
    if ( $name !== '' ) {
        $t = get_term_by( 'name', $name, 'sl_agence_promo' );
        if ( ! $t || is_wp_error( $t ) ) {
            $t = get_term_by( 'slug', sanitize_title( $name ), 'sl_agence_promo' );
        }
        if ( $t && ! is_wp_error( $t ) ) return $t->slug;
    }
    return '';
}

/** L'utilisateur courant voit-il TOUTES les agences ? (admin WP ou editeur) */
function slc_is_admin_user() {
    return current_user_can( 'manage_options' ) || current_user_can( 'edit_others_posts' );
}

/** WooCommerce stocke-t-il les commandes en tables dediees (HPOS) ? */
function slc_hpos() {
    return class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
        && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
}

/** Genere un code de retrait unique et lisible (sans caracteres ambigus O/0, I/1, L). */
function slc_generate_code() {
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $tries = 0;
    do {
        $code = 'SL-';
        for ( $i = 0; $i < 6; $i++ ) {
            $code .= $alphabet[ wp_rand( 0, strlen( $alphabet ) - 1 ) ];
        }
        $tries++;
    } while ( slc_find_order_by_code( $code ) && $tries < 10 );
    return $code;
}

/** Retrouve une commande par son code de retrait (compatible HPOS et legacy). */
function slc_find_order_by_code( $code ) {
    global $wpdb;
    $code = strtoupper( trim( sanitize_text_field( (string) $code ) ) );
    if ( $code === '' ) return false;
    if ( slc_hpos() ) {
        $id = $wpdb->get_var( $wpdb->prepare(
            "SELECT order_id FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key='_sl_collect_code' AND meta_value=%s LIMIT 1", $code ) );
    } else {
        $id = $wpdb->get_var( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_sl_collect_code' AND meta_value=%s LIMIT 1", $code ) );
    }
    return $id ? wc_get_order( (int) $id ) : false;
}

/**
 * IDs des commandes Drop & Collect d'une agence (ou toutes si slug vide),
 * filtrees par statuts (avec prefixe wc-). Compatible HPOS et legacy.
 */
function slc_order_ids( $agence, array $statuses, $limit = 200 ) {
    global $wpdb;
    if ( empty( $statuses ) ) return [];
    $st = array_map( function ( $s ) { return 'wc-' . preg_replace( '/^wc-/', '', $s ); }, $statuses );
    $ph = implode( ',', array_fill( 0, count( $st ), '%s' ) );
    $agence = sanitize_title( (string) $agence );

    if ( slc_hpos() ) {
        $sql = "SELECT o.id FROM {$wpdb->prefix}wc_orders o
                JOIN {$wpdb->prefix}wc_orders_meta m ON m.order_id = o.id AND m.meta_key = '_sl_collect_agence'
                WHERE o.type = 'shop_order' AND o.status IN ($ph)"
             . ( $agence !== '' ? ' AND m.meta_value = %s' : '' )
             . ' ORDER BY o.date_created_gmt DESC LIMIT %d';
    } else {
        $sql = "SELECT p.ID FROM {$wpdb->posts} p
                JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_sl_collect_agence'
                WHERE p.post_type = 'shop_order' AND p.post_status IN ($ph)"
             . ( $agence !== '' ? ' AND m.meta_value = %s' : '' )
             . ' ORDER BY p.post_date DESC LIMIT %d';
    }
    $params = $st;
    if ( $agence !== '' ) $params[] = $agence;
    $params[] = (int) $limit;
    return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( $sql, $params ) ) );
}

/** Telephone de contact affiche pour la confirmation telephonique. */
function slc_contact_phone() {
    return get_option( 'sl_collect_phone', '+237 672 703 795' );
}
