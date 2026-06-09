<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ── Sauvegarde du planning hebdomadaire (admin) ── */
add_action( 'wp_ajax_sl_ff_save_planning', 'sl_ff_ajax_save_planning' );
function sl_ff_ajax_save_planning() {
    check_ajax_referer( 'sl_ff_toggle', 'nonce' );

    $post_id = intval( $_POST['post_id'] ?? 0 );
    if ( ! $post_id ) wp_send_json_error( 'ID invalide' );

    // Verifier les droits : l'utilisateur doit gerer ce repas.
    // Les administrateurs WP et administrateurs Fast Food gerent toutes les agences.
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'sl_ff_all_agencies' ) ) {
        $agence_user = get_user_meta( get_current_user_id(), '_sl_agence_ff', true );
        $agence_post = get_post_meta( $post_id, '_sl_ff_agence', true );
        if ( ! $agence_user || $agence_user !== $agence_post ) {
            wp_send_json_error( 'Acces refuse' );
        }
    }

    $jours_valides = [ 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche' ];
    $jours = array_values( array_intersect(
        (array) ( $_POST['jours'] ?? [] ),
        $jours_valides
    ) );

    update_post_meta( $post_id, '_sl_ff_jours', $jours );
    wp_send_json_success( [ 'jours' => $jours ] );
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
