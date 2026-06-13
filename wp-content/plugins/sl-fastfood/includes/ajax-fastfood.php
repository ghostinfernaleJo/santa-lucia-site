<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ── Sauvegarde du planning hebdomadaire (admin) ── */
add_action( 'wp_ajax_sl_ff_save_planning', 'sl_ff_ajax_save_planning' );
function sl_ff_ajax_save_planning() {
    check_ajax_referer( 'sl_ff_toggle', 'nonce' );

    $post_id = intval( $_POST['post_id'] ?? 0 );
    if ( ! $post_id ) wp_send_json_error( 'ID invalide' );

    // Agences du plat (multi-villes possibles).
    $agences_post = array_map( 'sanitize_title', array_filter( (array) get_post_meta( $post_id, '_sl_ff_agence' ) ) );
    $is_admin     = current_user_can( 'manage_options' ) || current_user_can( 'sl_ff_all_agencies' );

    // La disponibilité est PAR VILLE : on détermine l'agence cible.
    if ( $is_admin ) {
        $agence = sanitize_title( $_POST['agence'] ?? '' );
        if ( ! $agence || ! in_array( $agence, $agences_post, true ) ) {
            wp_send_json_error( 'Veuillez sélectionner une agence valide.' );
        }
    } else {
        $agence = sanitize_title( get_user_meta( get_current_user_id(), '_sl_agence_ff', true ) );
        if ( ! $agence || ! in_array( $agence, $agences_post, true ) ) {
            wp_send_json_error( 'Acces refuse' );
        }
    }

    $jours = array_values( array_intersect( (array) ( $_POST['jours'] ?? [] ), sl_ff_jours_valides() ) );

    // Écrit la dispo de CETTE agence uniquement (les autres villes ne changent pas).
    sl_ff_set_avail_jours( $post_id, $agence, $jours );

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
