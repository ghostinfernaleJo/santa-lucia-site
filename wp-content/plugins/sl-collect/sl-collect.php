<?php
/**
 * Plugin Name: Santa Lucia Drop & Collect
 * Description: Click & Collect multi-agences — commande en ligne, retrait en agence (choix d'agence au checkout, code de retrait, facture PDF, ecran responsable, statistiques de vente, expiration automatique).
 * Version:     0.4.0
 * Author:      Santa Lucia
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SL_COLLECT_VERSION', '0.4.0' );
define( 'SL_COLLECT_PATH', plugin_dir_path( __FILE__ ) );
define( 'SL_COLLECT_URL',  plugin_dir_url( __FILE__ ) );

/**
 * FPDF est deja installe par sl-agences-elementor (PDF des Bons Plans) : on le
 * reutilise plutot que d'embarquer une seconde copie de la bibliotheque.
 * Dependance assumee mais verifiee a l'usage (file_exists avant require).
 */
define( 'SL_COLLECT_FPDF', WP_PLUGIN_DIR . '/sl-agences-elementor/lib/fpdf/fpdf.php' );

// Compatibilite WooCommerce HPOS (tables de commandes dediees)
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

// Ne rien charger si WooCommerce est absent
add_action( 'plugins_loaded', 'sl_collect_boot', 20 );
function sl_collect_boot() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>Santa Lucia Drop &amp; Collect</strong> n&eacute;cessite WooCommerce.</p></div>';
        } );
        return;
    }
    require_once SL_COLLECT_PATH . 'includes/helpers.php';
    require_once SL_COLLECT_PATH . 'includes/settings.php';
    require_once SL_COLLECT_PATH . 'includes/status.php';
    require_once SL_COLLECT_PATH . 'includes/checkout.php';
    require_once SL_COLLECT_PATH . 'includes/gateway-call.php';
    require_once SL_COLLECT_PATH . 'includes/admin-agence.php';
    require_once SL_COLLECT_PATH . 'includes/notify-agence.php';
    require_once SL_COLLECT_PATH . 'includes/agence-fields.php';
    require_once SL_COLLECT_PATH . 'includes/facture.php';
    require_once SL_COLLECT_PATH . 'includes/facture-links.php';
    require_once SL_COLLECT_PATH . 'includes/stats.php';
    require_once SL_COLLECT_PATH . 'includes/cron.php';
}

register_activation_hook( __FILE__, 'sl_collect_activate' );
function sl_collect_activate() {
    // Workflow valide par le client : compte obligatoire au checkout
    update_option( 'woocommerce_enable_guest_checkout', 'no' );
    update_option( 'woocommerce_enable_signup_and_login_from_checkout', 'yes' );
    update_option( 'woocommerce_enable_checkout_login_reminder', 'yes' );

    if ( ! wp_next_scheduled( 'sl_collect_cron' ) ) {
        wp_schedule_event( time() + 300, 'hourly', 'sl_collect_cron' );
    }
}

register_deactivation_hook( __FILE__, function () {
    wp_clear_scheduled_hook( 'sl_collect_cron' );
} );
