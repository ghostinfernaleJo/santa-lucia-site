<?php
/**
 * Plugin Name: Santa Lucia - Catalogue agences
 * Description: Catalogue e-commerce par agence : recherche rapide, disponibilité, prix et stock par magasin, compatible Drop & Collect.
 * Version: 0.1.0
 * Author: Santa Lucia
 * Text Domain: sl-catalogue
 */

defined( 'ABSPATH' ) || exit;

define( 'SL_CATALOGUE_VERSION', '0.1.0' );
define( 'SL_CATALOGUE_PATH', plugin_dir_path( __FILE__ ) );
define( 'SL_CATALOGUE_URL', plugin_dir_url( __FILE__ ) );

add_action( 'before_woocommerce_init', function () {
    if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

add_action( 'plugins_loaded', function () {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-warning"><p><strong>Santa Lucia - Catalogue agences</strong> nécessite WooCommerce.</p></div>';
        } );
        return;
    }

    require_once SL_CATALOGUE_PATH . 'includes/catalogue.php';
} , 25 );

/** Le catalogue reste privé tant qu'un responsable ne le met pas volontairement en ligne. */
register_activation_hook( __FILE__, function () {
    add_option( 'slcat_enabled', 'no' );
} );

function slcat_is_enabled() {
    return get_option( 'slcat_enabled', 'no' ) === 'yes';
}

add_action( 'admin_menu', function () {
    add_submenu_page( 'woocommerce', 'Catalogue agences', 'Catalogue agences', 'manage_woocommerce', 'sl-catalogue', 'slcat_render_settings_page' );
} );

function slcat_render_settings_page() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) return;
    $enabled = slcat_is_enabled();
    ?>
    <div class="wrap">
        <h1>Catalogue agences</h1>
        <p>Préparez le catalogue sans le montrer aux clients. Lorsqu’il est activé, les visiteurs peuvent choisir une agence, consulter les produits disponibles et les ajouter au panier.</p>
        <div style="max-width:760px;padding:24px;background:#fff;border:1px solid #dcdcde;border-left:4px solid <?php echo $enabled ? '#22a06b' : '#d63638'; ?>;">
            <h2 style="margin-top:0;">Statut : <?php echo $enabled ? 'En ligne' : 'Désactivé'; ?></h2>
            <p><?php echo $enabled ? 'La section contenant le shortcode [sl_catalogue] est visible sur le site public.' : 'La section est masquée pour les visiteurs. Les réglages agence, prix et stock sont conservés.'; ?></p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'slcat_toggle_catalogue' ); ?>
                <input type="hidden" name="action" value="slcat_toggle_catalogue">
                <input type="hidden" name="enabled" value="<?php echo $enabled ? 'no' : 'yes'; ?>">
                <button type="submit" class="button button-primary button-hero" style="background:<?php echo $enabled ? '#b32d2e' : '#2271b1'; ?>;border-color:<?php echo $enabled ? '#b32d2e' : '#2271b1'; ?>;">
                    <?php echo $enabled ? 'Désactiver le catalogue' : 'Activer le catalogue'; ?>
                </button>
            </form>
        </div>
        <p style="margin-top:20px;"><strong>À placer dans Elementor :</strong> <code>[sl_catalogue]</code></p>
    </div>
    <?php
}

add_action( 'admin_post_slcat_toggle_catalogue', function () {
    if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Accès non autorisé.' );
    check_admin_referer( 'slcat_toggle_catalogue' );
    update_option( 'slcat_enabled', isset( $_POST['enabled'] ) && wp_unslash( $_POST['enabled'] ) === 'yes' ? 'yes' : 'no' );
    wp_safe_redirect( add_query_arg( 'page', 'sl-catalogue', admin_url( 'admin.php' ) ) );
    exit;
} );

/** Coupe le rendu public et les endpoints, sans effacer aucun réglage. */
add_filter( 'pre_do_shortcode_tag', function ( $return, $tag ) {
    if ( 'sl_catalogue' !== $tag || slcat_is_enabled() ) return $return;
    return current_user_can( 'manage_woocommerce' ) ? '<div class="woocommerce-info">Le catalogue agences est désactivé. Activez-le depuis WooCommerce → Catalogue agences.</div>' : '';
}, 10, 2 );

add_action( 'wc_ajax_sl_catalogue_products', 'slcat_block_disabled_ajax', 1 );
add_action( 'wc_ajax_nopriv_sl_catalogue_products', 'slcat_block_disabled_ajax', 1 );
add_action( 'wc_ajax_sl_catalogue_add', 'slcat_block_disabled_ajax', 1 );
add_action( 'wc_ajax_nopriv_sl_catalogue_add', 'slcat_block_disabled_ajax', 1 );
function slcat_block_disabled_ajax() {
    if ( ! slcat_is_enabled() ) wp_send_json_error( [ 'message' => 'Le catalogue est actuellement désactivé.' ], 403 );
}
