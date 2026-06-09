<?php
/**
 * Sécurisation du système d'inscription
 * Option A : Blocage total des inscriptions publiques
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
 *  1. FORCER LA DÉSACTIVATION DES INSCRIPTIONS WORDPRESS
 * ============================================================ */
// On force l'option au démarrage
add_action( 'init', 'sl_security_disable_registration', 1 );
function sl_security_disable_registration() {
    if ( get_option( 'users_can_register' ) ) {
        update_option( 'users_can_register', 0 );
    }
}

/* ============================================================
 *  2. BLOQUER LA PAGE D'INSCRIPTION WP-LOGIN.PHP
 * ============================================================ */
add_action( 'login_init', 'sl_security_block_login_register' );
function sl_security_block_login_register() {
    $action = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';
    if ( 'register' === $action ) {
        // Rediriger vers la page de connexion standard
        wp_safe_redirect( wp_login_url() );
        exit;
    }
}

/* ============================================================
 *  3. DÉSACTIVER LES INSCRIPTIONS WOOCOMMERCE
 * ============================================================ */
add_filter( 'woocommerce_is_registration_enabled', '__return_false' );
add_filter( 'woocommerce_checkout_registration_enabled', '__return_false' );

// Au cas où une requête arrive quand même sur le handler WooCommerce
add_action( 'woocommerce_process_registration_errors', 'sl_security_block_woo_register', 10, 4 );
function sl_security_block_woo_register( $validation_error, $username, $password, $email ) {
    $validation_error->add( 'registration_disabled', __( 'Les inscriptions sont actuellement fermées sur ce site.', 'sl-agences' ) );
    return $validation_error;
}

/* ============================================================
 *  4. BLOQUER L'API REST POUR LA CRÉATION D'UTILISATEURS
 * ============================================================ */
add_filter( 'rest_pre_dispatch', 'sl_security_block_rest_user_creation', 10, 3 );
function sl_security_block_rest_user_creation( $result, $server, $request ) {
    if ( strpos( $request->get_route(), '/wp/v2/users' ) !== false && $request->get_method() === 'POST' ) {
        if ( ! current_user_can( 'create_users' ) ) {
            return new WP_Error(
                'rest_cannot_create_user',
                __( 'La création d\'utilisateur via l\'API est désactivée.', 'sl-agences' ),
                [ 'status' => 403 ]
            );
        }
    }
    return $result;
}
