<?php
/**
 * Diagnostic cache (LECTURE SEULE). Temporaire, protege par cle.
 * Repond : vrai serveur web + etat du cache LiteSpeed.
 */
if ( ! isset( $_GET['k'] ) || $_GET['k'] !== 'slCacheDiagQ9' ) { http_response_code( 404 ); exit; }
require_once __DIR__ . '/wp-load.php';
if ( ! defined( 'ABSPATH' ) ) exit;
nocache_headers();
header( 'Content-Type: application/json; charset=utf-8' );

global $wpdb;

$server_sw = $_SERVER['SERVER_SOFTWARE'] ?? '';
$is_litespeed =
    ( stripos( $server_sw, 'litespeed' ) !== false )
 || ( php_sapi_name() === 'litespeed' )
 || isset( $_SERVER['LSWS_EDITION'] )
 || isset( $_SERVER['X-LSCACHE'] )
 || ! empty( $_SERVER['HTTP_X_LSCACHE'] );

// Options LiteSpeed Cache (cle = 'litespeed.conf.*')
$ls_rows = $wpdb->get_results(
    "SELECT option_name, option_value FROM {$wpdb->options}
     WHERE option_name LIKE 'litespeed.conf.cache%'
        OR option_name = 'litespeed.conf.cache'
        OR option_name LIKE 'litespeed.conf.optm%'
     ORDER BY option_name"
);
$ls = [];
foreach ( (array) $ls_rows as $r ) {
    $v = $r->option_value;
    if ( strlen( $v ) > 80 ) $v = substr( $v, 0, 80 ) . '...';
    $ls[ $r->option_name ] = $v;
}

// Quelques options clefs lues proprement
$key_opts = [];
foreach ( [ 'litespeed.conf.cache', 'litespeed.conf.cache-priv', 'litespeed.conf.cache-ttl_pub',
            'litespeed.conf.optm-css_min', 'litespeed.conf.optm-js_min',
            'litespeed.conf.optm-css_comb', 'litespeed.conf.optm-js_comb',
            'litespeed.conf.optm-qs_rm' ] as $k ) {
    $key_opts[ $k ] = get_option( $k, '(absent)' );
}

echo json_encode( [
    'server_software'   => $server_sw,
    'php_sapi'          => php_sapi_name(),
    'is_litespeed'      => $is_litespeed ? 'OUI' : 'NON',
    'php_version'       => PHP_VERSION,
    'litespeed_options' => $ls,
    'key_options'       => $key_opts,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
