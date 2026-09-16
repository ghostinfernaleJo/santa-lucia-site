<?php
/** Protections HTTP des endpoints publics de Lucie. */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Origines explicitement autorisees pour le widget web. */
function sl_lucie_allowed_origins() {
    $origins = [];
    foreach ( [ home_url( '/' ), site_url( '/' ) ] as $url ) {
        $parts = wp_parse_url( $url );
        if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) continue;
        $origin = strtolower( $parts['scheme'] . '://' . $parts['host'] );
        if ( ! empty( $parts['port'] ) ) $origin .= ':' . (int) $parts['port'];
        $origins[] = $origin;
    }
    return array_values( array_unique( $origins ) );
}

function sl_lucie_normalize_origin( $origin ) {
    $parts = wp_parse_url( trim( (string) $origin ) );
    if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) return '';
    $value = strtolower( $parts['scheme'] . '://' . $parts['host'] );
    if ( ! empty( $parts['port'] ) ) $value .= ':' . (int) $parts['port'];
    return $value;
}

/**
 * Autorise les ecritures du widget venant du site lui-meme.
 * Le nonce reste verifie quand il est frais ; l'origine permet aux pages
 * servies depuis le cache Varnish de continuer a fonctionner apres son expiration.
 */
function sl_lucie_rest_write_permission( WP_REST_Request $request ) {
    $fetch_site = strtolower( (string) $request->get_header( 'sec-fetch-site' ) );
    if ( 'cross-site' === $fetch_site ) {
        return new WP_Error( 'sl_lucie_cross_site', 'Origine non autorisee.', [ 'status' => 403 ] );
    }

    $origin = sl_lucie_normalize_origin( $request->get_header( 'origin' ) );
    if ( $origin !== '' && in_array( $origin, sl_lucie_allowed_origins(), true ) ) return true;

    $nonce = (string) $request->get_header( 'x-wp-nonce' );
    if ( $nonce !== '' && wp_verify_nonce( $nonce, 'wp_rest' ) ) return true;

    return new WP_Error( 'sl_lucie_forbidden', 'Requete non autorisee.', [ 'status' => 403 ] );
}

/** Retire le CORS reflechi par defaut de WordPress sur les routes Lucie. */
add_filter( 'rest_pre_serve_request', function ( $served, $result, $request ) {
    if ( strpos( $request->get_route(), '/santa-lucia/v1/lucie/' ) !== 0 ) return $served;

    header_remove( 'Access-Control-Allow-Origin' );
    header_remove( 'Access-Control-Allow-Credentials' );
    header_remove( 'Access-Control-Allow-Methods' );

    $origin = sl_lucie_normalize_origin( $request->get_header( 'origin' ) );
    if ( $origin !== '' && in_array( $origin, sl_lucie_allowed_origins(), true ) ) {
        header( 'Access-Control-Allow-Origin: ' . $origin );
        header( 'Access-Control-Allow-Credentials: true' );
        header( 'Access-Control-Allow-Methods: OPTIONS, POST' );
        header( 'Vary: Origin', false );
    }
    header( 'Cache-Control: no-store, private, max-age=0', true );
    return $served;
}, 20, 3 );

add_filter( 'rest_post_dispatch', function ( $response, $server, $request ) {
    if ( strpos( $request->get_route(), '/santa-lucia/v1/lucie/' ) === 0 && $response instanceof WP_REST_Response ) {
        $response->header( 'Cache-Control', 'no-store, private, max-age=0' );
        $response->header( 'X-Robots-Tag', 'noindex, nofollow' );
    }
    return $response;
}, 20, 3 );
