<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ── Sauvegarde du planning hebdomadaire (admin) ── */
add_action( 'wp_ajax_sl_ff_save_planning', 'sl_ff_ajax_save_planning' );
function sl_ff_ajax_save_planning() {
    check_ajax_referer( 'sl_ff_toggle', 'nonce' );

    $post_id = intval( $_POST['post_id'] ?? 0 );
    if ( ! $post_id ) wp_send_json_error( 'ID invalide' );
    $agence = sanitize_title( $_POST['agence'] ?? '' );

    $jours = function_exists( 'sl_ff_normalize_jours' )
        ? sl_ff_normalize_jours( $_POST['jours'] ?? [] )
        : array_values( (array) ( $_POST['jours'] ?? [] ) );

    // Verifier les droits : l'utilisateur doit gerer ce repas.
    // Les administrateurs WP et administrateurs Fast Food gerent toutes les agences.
    $agences_post = function_exists( 'sl_ff_post_agence_slugs' )
        ? sl_ff_post_agence_slugs( $post_id )
        : array_map( 'sanitize_title', (array) get_post_meta( $post_id, '_sl_ff_agence' ) );
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'sl_ff_all_agencies' ) ) {
        $agence_user  = get_user_meta( get_current_user_id(), '_sl_agence_ff', true );
        $agence = $agence ?: sanitize_title( $agence_user );
        if ( ! $agence_user || $agence !== sanitize_title( $agence_user ) ) {
            wp_send_json_error( 'Acces refuse' );
        }
    }

    if ( ! $agence ) {
        $agence = get_post_meta( $post_id, '_sl_ff_agence', true );
    }

    if ( $agence && ! empty( $jours ) && ! in_array( $agence, $agences_post, true ) && function_exists( 'sl_ff_set_agence_meta' ) ) {
        sl_ff_set_agence_meta( $post_id, $agence );
    }

    if ( function_exists( 'sl_ff_set_agence_jours' ) ) {
        sl_ff_set_agence_jours( $post_id, $agence, $jours );
    } else {
        update_post_meta( $post_id, '_sl_ff_jours', $jours );
    }
    if ( function_exists( 'sl_ff_bump_menu_cache' ) ) sl_ff_bump_menu_cache();
    wp_send_json_success( [ 'agence' => $agence, 'jours' => $jours ] );
}

/* ── Charge le menu d'une agence (front, browser shortcode) ── */
add_action( 'wp_ajax_sl_ff_get_menu',        'sl_ff_ajax_get_menu' );
add_action( 'wp_ajax_nopriv_sl_ff_get_menu', 'sl_ff_ajax_get_menu' );
function sl_ff_ajax_get_menu() {
    check_ajax_referer( 'sl_ff_get_menu', 'nonce' );

    $agence = sanitize_text_field( $_POST['agence'] ?? '' );
    if ( ! $agence ) wp_send_json_error( 'Agence manquante' );

    $html = sl_ff_render_menu_html( $agence );
    wp_send_json_success( [ 'html' => $html ] );
}
