<?php
/**
 * Plugin Name: Santa Lucia - Lucie (Assistant IA)
 * Description: Chatbot "Lucie" propulse par Claude. Repond uniquement aux questions sur Santa Lucia, a partir des donnees du site (menus, promos, agences) et d'une base de connaissances (PDF/texte). Multi-cles API avec basculement automatique.
 * Version:     1.0.0
 * Author:      Santa Lucia
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SL_LUCIE_VERSION', '1.0.0' );
define( 'SL_LUCIE_PATH', plugin_dir_path( __FILE__ ) );
define( 'SL_LUCIE_URL',  plugin_dir_url( __FILE__ ) );

require_once SL_LUCIE_PATH . 'includes/claude-client.php';
require_once SL_LUCIE_PATH . 'includes/knowledge.php';
require_once SL_LUCIE_PATH . 'includes/tools.php';
require_once SL_LUCIE_PATH . 'includes/rest-chat.php';
require_once SL_LUCIE_PATH . 'includes/admin-settings.php';

/* ============================================================
   WIDGET FRONT — charge sur TOUT le site public (asynchrone)
   ============================================================ */
add_action( 'wp_enqueue_scripts', 'sl_lucie_front_assets' );
function sl_lucie_front_assets() {
    if ( is_admin() ) return;
    // Ne charge que si Lucie est activee ET au moins une cle API est configuree
    if ( get_option( 'sl_lucie_enabled', '1' ) !== '1' ) return;

    $css_ver = @filemtime( SL_LUCIE_PATH . 'assets/css/lucie-widget.css' ) ?: SL_LUCIE_VERSION;
    $js_ver  = @filemtime( SL_LUCIE_PATH . 'assets/js/lucie-widget.js' )  ?: SL_LUCIE_VERSION;

    wp_enqueue_style( 'sl-lucie', SL_LUCIE_URL . 'assets/css/lucie-widget.css', [], $css_ver );
    wp_enqueue_script( 'sl-lucie', SL_LUCIE_URL . 'assets/js/lucie-widget.js', [], $js_ver, true );
    wp_localize_script( 'sl-lucie', 'slLucie', [
        'rest'   => esc_url_raw( rest_url( 'santa-lucia/v1/lucie/chat' ) ),
        'nonce'  => wp_create_nonce( 'wp_rest' ),
        'nom'    => get_option( 'sl_lucie_nom', 'Lucie' ),
        'accueil'=> get_option( 'sl_lucie_message_accueil', 'Bonjour 👋 Je suis Lucie, l\'assistante de Santa Lucia. Posez-moi vos questions sur nos menus, promotions, agences ou notre recrutement.' ),
    ] );
}

/* Reglages par defaut a l'activation */
register_activation_hook( __FILE__, function () {
    add_option( 'sl_lucie_enabled', '1' );
    add_option( 'sl_lucie_nom', 'Lucie' );
    add_option( 'sl_lucie_scope_guard', '1' );
} );
