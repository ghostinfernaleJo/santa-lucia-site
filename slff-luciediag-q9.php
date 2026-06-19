<?php
/** Diagnostic config Lucie (LECTURE SEULE, ne revele JAMAIS les cles). Temporaire. */
if ( ! isset( $_GET['k'] ) || $_GET['k'] !== 'slCacheDiagQ9' ) { http_response_code( 404 ); exit; }
require_once __DIR__ . '/wp-load.php';
if ( ! defined( 'ABSPATH' ) ) exit;
nocache_headers();
header( 'Content-Type: application/json; charset=utf-8' );

$anthropic = (array) get_option( 'sl_lucie_api_keys', [] );
$google    = (array) get_option( 'sl_lucie_google_keys', [] );

echo json_encode( [
    'plugin_actif'        => function_exists( 'sl_lucie_provider' ) ? 'OUI' : 'NON (plugin non charge/active)',
    'fournisseur_actif'   => get_option( 'sl_lucie_provider', 'anthropic' ),
    'nb_cles_anthropic'   => count( array_filter( array_map( 'trim', $anthropic ) ) ),
    'nb_cles_google'      => count( array_filter( array_map( 'trim', $google ) ) ),
    'modele_gemini'       => get_option( 'sl_lucie_google_model', '(defaut)' ),
    'lucie_activee'       => get_option( 'sl_lucie_enabled', '1' ),
    'pret_a_repondre'     => ( function_exists( 'sl_lucie_provider_has_key' ) && sl_lucie_provider_has_key() ) ? 'OUI' : 'NON',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
