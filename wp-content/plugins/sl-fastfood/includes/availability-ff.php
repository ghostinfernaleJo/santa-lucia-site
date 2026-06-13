<?php
/**
 * Disponibilité par PLAT × VILLE.
 * Un plat (post sl_repas) peut appartenir à plusieurs agences (_sl_ff_agence multi-valeurs).
 * La disponibilité par jour est désormais propre à chaque agence, stockée dans la méta
 * indexable `_sl_ff_avail` avec des valeurs "agence:jour" (ex: "akwa:lundi").
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function sl_ff_jours_valides() {
    return [ 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche' ];
}

/** Jours de dispo d'un plat POUR une agence donnée (avec repli legacy si non migré). */
function sl_ff_avail_jours( $post_id, $agence ) {
    $agence = sanitize_title( $agence );
    $rows   = (array) get_post_meta( (int) $post_id, '_sl_ff_avail' );

    if ( empty( $rows ) ) {
        // Pas encore migré : repli sur les jours globaux historiques (_sl_ff_jours).
        return array_values( array_intersect( (array) get_post_meta( (int) $post_id, '_sl_ff_jours', true ), sl_ff_jours_valides() ) );
    }

    $jours = [];
    $prefix = $agence . ':';
    $len = strlen( $prefix );
    foreach ( $rows as $v ) {
        if ( strpos( $v, $prefix ) === 0 ) {
            $jours[] = substr( $v, $len );
        }
    }
    return array_values( array_intersect( $jours, sl_ff_jours_valides() ) );
}

/** Définit les jours de dispo d'un plat pour UNE agence (sans toucher aux autres villes). */
function sl_ff_set_avail_jours( $post_id, $agence, $jours ) {
    $post_id = (int) $post_id;
    $agence  = sanitize_title( $agence );
    if ( ! $agence ) return;
    $jours   = array_values( array_intersect( (array) $jours, sl_ff_jours_valides() ) );

    // Migration paresseuse de CE post si jamais il a encore l'ancien format global.
    $rows = (array) get_post_meta( $post_id, '_sl_ff_avail' );
    if ( empty( $rows ) ) {
        $legacy = array_intersect( (array) get_post_meta( $post_id, '_sl_ff_jours', true ), sl_ff_jours_valides() );
        if ( $legacy ) {
            foreach ( array_unique( array_filter( (array) get_post_meta( $post_id, '_sl_ff_agence' ) ) ) as $ag ) {
                $ag = sanitize_title( $ag );
                foreach ( $legacy as $j ) add_post_meta( $post_id, '_sl_ff_avail', $ag . ':' . $j );
            }
            $rows = (array) get_post_meta( $post_id, '_sl_ff_avail' );
        }
    }

    // Retirer les lignes de cette agence, puis ré-ajouter.
    $prefix = $agence . ':';
    foreach ( $rows as $v ) {
        if ( strpos( $v, $prefix ) === 0 ) delete_post_meta( $post_id, '_sl_ff_avail', $v );
    }
    foreach ( $jours as $j ) add_post_meta( $post_id, '_sl_ff_avail', $agence . ':' . $j );

    if ( function_exists( 'sl_ff_bump_menu_cache' ) ) sl_ff_bump_menu_cache();
}

/** Clause meta_query pour le menu d'une agence un jour donné. */
function sl_ff_avail_meta_query( $agence, $jour ) {
    $jour   = sanitize_key( $jour );
    $agence = sanitize_title( $agence );
    if ( $agence ) {
        return [ 'key' => '_sl_ff_avail', 'value' => $agence . ':' . $jour ];
    }
    // Sans agence précise : n'importe quelle ville ce jour-là.
    return [ 'key' => '_sl_ff_avail', 'value' => ':' . $jour, 'compare' => 'LIKE' ];
}

/* ── Migration unique : _sl_ff_jours (global) → _sl_ff_avail (par ville) ── */
add_action( 'init', 'sl_ff_migrate_avail', 99 );
function sl_ff_migrate_avail() {
    if ( get_option( 'sl_ff_avail_migrated_v1' ) ) return;

    $ids = get_posts( [
        'post_type'      => 'sl_repas',
        'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ] );
    foreach ( $ids as $pid ) {
        if ( get_post_meta( $pid, '_sl_ff_avail' ) ) continue; // déjà des lignes
        $jours = array_intersect( (array) get_post_meta( $pid, '_sl_ff_jours', true ), sl_ff_jours_valides() );
        if ( empty( $jours ) ) continue;
        $agences = array_unique( array_filter( (array) get_post_meta( $pid, '_sl_ff_agence' ) ) );
        foreach ( $agences as $ag ) {
            $ag = sanitize_title( $ag );
            foreach ( $jours as $j ) add_post_meta( $pid, '_sl_ff_avail', $ag . ':' . $j );
        }
    }
    update_option( 'sl_ff_avail_migrated_v1', 1 );
}
