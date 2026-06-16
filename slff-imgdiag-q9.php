<?php
/**
 * Diagnostic Image Optimizer (LECTURE SEULE). Temporaire, protege par cle.
 */
if ( ! isset( $_GET['k'] ) || $_GET['k'] !== 'slCacheDiagQ9' ) { http_response_code( 404 ); exit; }
require_once __DIR__ . '/wp-load.php';
if ( ! defined( 'ABSPATH' ) ) exit;
nocache_headers();
header( 'Content-Type: application/json; charset=utf-8' );

global $wpdb;

// Toutes les options liees a l'image optimizer
$rows = $wpdb->get_results(
    "SELECT option_name, LENGTH(option_value) AS len FROM {$wpdb->options}
     WHERE option_name LIKE '%image_optim%'
        OR option_name LIKE 'image-optim%'
        OR option_name LIKE '%imageoptim%'
        OR option_name LIKE '%img_optim%'
     ORDER BY option_name"
);
$opt_names = [];
foreach ( (array) $rows as $r ) { $opt_names[ $r->option_name ] = (int) $r->len; }

// Valeurs des options de reglages probables (sans secrets)
$dump = [];
foreach ( array_keys( $opt_names ) as $name ) {
    if ( stripos( $name, 'token' ) !== false || stripos( $name, 'secret' ) !== false || stripos( $name, 'key' ) !== false ) {
        $dump[ $name ] = '(masque, present=' . ( get_option( $name ) ? 'oui' : 'non' ) . ')';
        continue;
    }
    $v = get_option( $name );
    $dump[ $name ] = is_scalar( $v ) ? ( strlen( (string) $v ) > 300 ? substr( $v, 0, 300 ) . '...' : $v ) : $v;
}

// Combien d'images ont une version optimisee (meta du plugin) ?
$meta_rows = $wpdb->get_results(
    "SELECT meta_key, COUNT(*) AS n FROM {$wpdb->postmeta}
     WHERE meta_key LIKE '%image_optim%' OR meta_key LIKE '%imageoptim%' OR meta_key LIKE '%_io_%'
     GROUP BY meta_key ORDER BY n DESC LIMIT 20"
);
$meta = [];
foreach ( (array) $meta_rows as $r ) { $meta[ $r->meta_key ] = (int) $r->n; }

echo json_encode( [
    'gd_webp'        => function_exists( 'imagewebp' ) ? 'OUI' : 'NON',
    'option_names'   => $opt_names,
    'options_dump'   => $dump,
    'meta_keys'      => $meta,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
