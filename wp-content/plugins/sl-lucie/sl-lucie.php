<?php
/**
 * Plugin Name: Santa Lucia - Lucie (Assistant IA)
 * Description: Assistante Santa Lucia multi-fournisseur : connaissance du site, menus, promotions, agences, recommandations budget et panier WooCommerce conversationnel securise.
 * Version:     1.1.0
 * Author:      Santa Lucia
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SL_LUCIE_VERSION', '1.1.0' );
define( 'SL_LUCIE_PATH', plugin_dir_path( __FILE__ ) );
define( 'SL_LUCIE_URL',  plugin_dir_url( __FILE__ ) );

require_once SL_LUCIE_PATH . 'includes/claude-client.php';
require_once SL_LUCIE_PATH . 'includes/gemini-client.php';
require_once SL_LUCIE_PATH . 'includes/provider.php';
require_once SL_LUCIE_PATH . 'includes/knowledge.php';
require_once SL_LUCIE_PATH . 'includes/site-index.php';
require_once SL_LUCIE_PATH . 'includes/commerce.php';
require_once SL_LUCIE_PATH . 'includes/tools.php';
require_once SL_LUCIE_PATH . 'includes/rest-chat.php';
require_once SL_LUCIE_PATH . 'includes/admin-settings.php';
require_once SL_LUCIE_PATH . 'includes/stats.php';
require_once SL_LUCIE_PATH . 'includes/leads.php';
require_once SL_LUCIE_PATH . 'includes/conversations.php';

/* ============================================================
   WIDGET FRONT — charge sur TOUT le site public (asynchrone)
   ============================================================ */
/**
 * Lucie est-elle active MAINTENANT ? (interrupteur global + planning horaire)
 * Utilise le fuseau horaire du site.
 */
function sl_lucie_is_active_now() {
    if ( get_option( 'sl_lucie_enabled', '1' ) !== '1' ) return false;
    if ( get_option( 'sl_lucie_schedule_enabled', '0' ) !== '1' ) return true;

    // Jour de la semaine (0=dimanche .. 6=samedi)
    $days  = (array) get_option( 'sl_lucie_schedule_days', [ '0', '1', '2', '3', '4', '5', '6' ] );
    $today = (string) current_time( 'w' );
    if ( ! in_array( $today, array_map( 'strval', $days ), true ) ) return false;

    $start = (string) get_option( 'sl_lucie_schedule_start', '08:00' );
    $end   = (string) get_option( 'sl_lucie_schedule_end',   '20:00' );
    $now   = current_time( 'H:i' );
    if ( $start === $end ) return true;                 // 24h/24
    if ( $start < $end )   return ( $now >= $start && $now < $end );
    return ( $now >= $start || $now < $end );           // fenetre qui passe minuit
}

add_action( 'wp_enqueue_scripts', 'sl_lucie_front_assets' );
function sl_lucie_front_assets() {
    if ( is_admin() ) return;
    // L'interrupteur global masque Lucie. Hors planning, la bulle reste visible
    // avec le message d'indisponibilite configure (aucune requete IA n'est faite).
    if ( get_option( 'sl_lucie_enabled', '1' ) !== '1' ) return;

    $css_ver = @filemtime( SL_LUCIE_PATH . 'assets/css/lucie-widget-v4.css' ) ?: SL_LUCIE_VERSION;
    $shop_css_ver = @filemtime( SL_LUCIE_PATH . 'assets/css/lucie-commerce-v1.css' ) ?: SL_LUCIE_VERSION;
    $js_ver  = @filemtime( SL_LUCIE_PATH . 'assets/js/lucie-widget-v7.js' )  ?: SL_LUCIE_VERSION;

    wp_enqueue_style( 'sl-lucie', SL_LUCIE_URL . 'assets/css/lucie-widget-v4.css', [], $css_ver );
    wp_enqueue_style( 'sl-lucie-commerce', SL_LUCIE_URL . 'assets/css/lucie-commerce-v1.css', [ 'sl-lucie' ], $shop_css_ver );
    wp_enqueue_script( 'sl-lucie', SL_LUCIE_URL . 'assets/js/lucie-widget-v7.js', [], $js_ver, true );
    $active  = sl_lucie_is_active_now();
    $offline = trim( (string) get_option( 'sl_lucie_offline_message', '' ) );
    if ( $offline === '' ) $offline = 'Je ne suis pas disponible pour le moment. Merci de revenir pendant nos horaires de service 🙂';
    wp_localize_script( 'sl-lucie', 'slLucie', [
        'rest'       => esc_url_raw( rest_url( 'santa-lucia/v1/lucie/chat' ) ),
        'cartRest'   => esc_url_raw( rest_url( 'santa-lucia/v1/lucie/cart' ) ),
        'nonce'      => wp_create_nonce( 'wp_rest' ),
        'nom'        => get_option( 'sl_lucie_nom', 'Lucie' ),
        'avatar'     => esc_url( get_option( 'sl_lucie_avatar', '' ) ),
        'accueil'    => get_option( 'sl_lucie_message_accueil', 'Bonjour 👋 Je suis Lucie, l\'assistante de Santa Lucia. Je peux vous aider à trouver une agence, un menu, une promotion ou préparer un panier selon votre budget.' ),
        'active'     => $active,
        'offline'    => $offline,
        'privacy'    => esc_url_raw( function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : home_url( '/politique-de-confidentialite/' ) ),
        'cartEnabled'=> ! function_exists( 'slc_cart_enabled' ) || slc_cart_enabled(),
    ] );
}

/* Reglages par defaut a l'activation */
register_activation_hook( __FILE__, function () {
    add_option( 'sl_lucie_enabled', '1' );
    add_option( 'sl_lucie_nom', 'Lucie' );
    add_option( 'sl_lucie_scope_guard', '1' );
    if ( function_exists( 'sl_lucie_index_maybe_install' ) ) sl_lucie_index_maybe_install();
} );
