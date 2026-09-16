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
    if ( strpos( $request->get_route(), '/wp/v2/users' ) === 0 ) {
        if ( $request->get_method() === 'POST' && ! current_user_can( 'create_users' ) ) {
            return new WP_Error(
                'rest_cannot_create_user',
                __( 'La création d\'utilisateur via l\'API est désactivée.', 'sl-agences' ),
                [ 'status' => 403 ]
            );
        }
        // Les noms de connexion, slugs d'auteur et metadonnees WooCommerce ne
        // doivent pas servir a preparer une attaque ciblee sur les comptes.
        if ( $request->get_method() === 'GET' && ! current_user_can( 'list_users' ) ) {
            return new WP_Error(
                'rest_cannot_list_users',
                __( 'La liste des utilisateurs n\'est pas publique.', 'sl-agences' ),
                [ 'status' => 403 ]
            );
        }
    }
    return $result;
}

/* ============================================================
 *  5. EN-TETES DE SECURITE — CSP EN OBSERVATION
 * ============================================================ */
add_action( 'send_headers', 'sl_security_send_headers', 20 );
function sl_security_send_headers() {
    if ( headers_sent() ) return;

    if ( is_ssl() ) {
        // Pas de includeSubDomains/preload avant inventaire de tous les sous-domaines.
        header( 'Strict-Transport-Security: max-age=31536000' );
    }
    header( 'X-Frame-Options: SAMEORIGIN' );
    header( 'X-Content-Type-Options: nosniff' );
    header( 'Referrer-Policy: strict-origin-when-cross-origin' );
    header( 'Permissions-Policy: camera=(), microphone=(), geolocation=()' );

    // Rapport seulement : mesure les incompatibilites avant activation stricte.
    $csp = "default-src 'self' https: data: blob:; "
        . "base-uri 'self'; object-src 'none'; frame-ancestors 'self'; "
        . "script-src 'self' 'unsafe-inline' 'unsafe-eval' https: blob:; "
        . "style-src 'self' 'unsafe-inline' https:; img-src 'self' https: data: blob:; "
        . "font-src 'self' https: data:; connect-src 'self' https: wss:; "
        . "frame-src 'self' https:; form-action 'self' https:; upgrade-insecure-requests";
    header( 'Content-Security-Policy-Report-Only: ' . $csp );
}

/* ============================================================
 *  6. PURGE UNIQUE DES REPONSES ANTERIEURES AU DURCISSEMENT
 * ============================================================ */
add_action( 'init', 'sl_security_schedule_hardening_purge', 2 );
function sl_security_schedule_hardening_purge() {
    if ( get_option( 'sl_security_hardening_purge_v1' ) ) return;
    update_option( 'sl_security_hardening_purge_v1', 'scheduled', false );
    wp_schedule_single_event( time() + 5, 'sl_security_hardening_purge' );
}

add_action( 'sl_security_hardening_purge', 'sl_security_run_hardening_purge' );
function sl_security_run_hardening_purge() {
    $urls = [
        home_url( '/' ),
        home_url( '/bon-plans/' ),
        add_query_arg( 'page_id', '17088', home_url( '/' ) ),
        rest_url( 'wp/v2/users' ),
        add_query_arg( 'per_page', '1', rest_url( 'wp/v2/users' ) ),
        rest_url( 'santa-lucia/v1/lucie/chat' ),
        rest_url( 'santa-lucia/v1/lucie/cart' ),
    ];
    foreach ( array_unique( $urls ) as $url ) {
        do_action( 'litespeed_purge_url', $url );
        wp_remote_request( $url, [
            'method'    => 'PURGE',
            'timeout'   => 5,
            'blocking'  => true,
            'sslverify' => false,
        ] );
    }
    update_option( 'sl_security_hardening_purge_v1', 'done', false );
}
