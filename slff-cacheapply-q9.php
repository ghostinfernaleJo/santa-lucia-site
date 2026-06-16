<?php
/**
 * Active des optimisations LiteSpeed SANS RISQUE puis purge. Protege par cle.
 * ?mode=apply pour appliquer ; sinon lecture des valeurs.
 * Options touchees (toutes reversibles) :
 *   - optm-html_min  : minifie le HTML (whitespace) -> page plus legere
 *   - optm-emoji_rm  : retire le script emoji WP -> 1 requete/JS en moins
 *   - optm-qs_rm     : retire les ?ver= des assets statiques -> meilleur cache
 * NE TOUCHE PAS : combine/defer/async/ucss (peuvent casser le rendu).
 */
if ( ! isset( $_GET['k'] ) || $_GET['k'] !== 'slCacheDiagQ9' ) { http_response_code( 404 ); exit; }
require_once __DIR__ . '/wp-load.php';
if ( ! defined( 'ABSPATH' ) ) exit;
nocache_headers();
header( 'Content-Type: application/json; charset=utf-8' );

$safe = [
    'litespeed.conf.optm-html_min' => '1',
    'litespeed.conf.optm-emoji_rm' => '1',
    'litespeed.conf.optm-qs_rm'    => '1',
];

$result = [];
if ( isset( $_GET['mode'] ) && $_GET['mode'] === 'apply' ) {
    foreach ( $safe as $k => $v ) {
        $before = get_option( $k, '(absent)' );
        update_option( $k, $v );
        $result[ $k ] = [ 'avant' => $before, 'apres' => get_option( $k ) ];
    }
    // Purge complet LiteSpeed pour regenerer les pages avec les nouvelles optims
    if ( has_action( 'litespeed_purge_all' ) ) {
        do_action( 'litespeed_purge_all' );
        $result['_purge'] = 'litespeed_purge_all declenche';
    } else {
        $result['_purge'] = 'hook indisponible';
    }
} else {
    foreach ( $safe as $k => $v ) {
        $result[ $k ] = get_option( $k, '(absent)' );
    }
    $result['_note'] = 'lecture seule ; ajouter &mode=apply pour appliquer';
}

echo json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
