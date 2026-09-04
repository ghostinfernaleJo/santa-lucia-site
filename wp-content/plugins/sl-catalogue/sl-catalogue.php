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
        <div style="max-width:760px;margin-top:20px;padding:24px;background:#fff;border:1px solid #dcdcde;">
            <h2 style="margin-top:0;">Jeu de démonstration</h2>
            <p>Crée ou actualise des références clairement identifiées <strong>« Produit démo »</strong> pour vérifier l’interface, la recherche, les prix et la disponibilité dans toutes les agences. Les références existantes ne sont jamais dupliquées.</p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'slcat_seed_demo_catalogue' ); ?>
                <input type="hidden" name="action" value="slcat_seed_demo_catalogue">
                <button type="submit" class="button button-secondary">Créer / actualiser les produits de démonstration</button>
            </form>
            <?php if ( isset( $_GET['slcat_demo_created'] ) || isset( $_GET['slcat_demo_updated'] ) ) : ?>
                <p style="margin-bottom:0;color:#15803d;"><strong><?php echo esc_html( absint( $_GET['slcat_demo_created'] ?? 0 ) ); ?> créé(s) · <?php echo esc_html( absint( $_GET['slcat_demo_updated'] ?? 0 ) ); ?> actualisé(s).</strong></p>
            <?php endif; ?>
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

/** Crée un catalogue de test reproductible et le rend visible dans chaque agence. */
add_action( 'admin_post_slcat_seed_demo_catalogue', function () {
    if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Accès non autorisé.' );
    check_admin_referer( 'slcat_seed_demo_catalogue' );

    $items = [
        [ 'Produit démo — Café moulu Santa Lucia 250 g', 1850 ],
        [ 'Produit démo — Eau minérale source 1,5 L', 650 ],
        [ 'Produit démo — Riz parfumé 1 kg', 1450 ],
        [ 'Produit démo — Pâtes spaghetti 500 g', 900 ],
        [ 'Produit démo — Huile de tournesol 1 L', 2100 ],
        [ 'Produit démo — Lait UHT demi-écrémé 1 L', 1100 ],
        [ 'Produit démo — Biscuits chocolat 200 g', 950 ],
        [ 'Produit démo — Confiture fraise 370 g', 1750 ],
        [ 'Produit démo — Thé citron 25 sachets', 1200 ],
        [ 'Produit démo — Savon liquide aloe vera 500 ml', 1600 ],
        [ 'Produit démo — Papier toilette doux x12', 2800 ],
        [ 'Produit démo — Shampooing nutrition 400 ml', 2400 ],
        [ 'Produit démo — Dentifrice fraîcheur 100 g', 1350 ],
        [ 'Produit démo — Crème hydratante 250 ml', 2200 ],
        [ 'Produit démo — Arachides grillées 150 g', 700 ],
        [ 'Produit démo — Chips de plantain 100 g', 800 ],
        [ 'Produit démo — Chocolat noir 100 g', 1150 ],
        [ 'Produit démo — Eau gazeuse 50 cl', 500 ],
        [ 'Produit démo — Café instantané 100 g', 2900 ],
        [ 'Produit démo — Jus orange pressé 1 L', 1250 ],
    ];

    $category = get_term_by( 'name', 'Produit frais et transformé', 'product_cat' );
    $agencies = function_exists( 'slcat_agencies' ) ? wp_list_pluck( slcat_agencies(), 'slug' ) : [];

    $created = 0;
    $updated = 0;
    foreach ( $items as [ $name, $price ] ) {
        $existing = get_page_by_title( $name, OBJECT, 'product' );
        if ( $existing ) {
            $product_id = (int) $existing->ID;
            $updated++;
        } else {
            $product = new WC_Product_Simple();
            $product->set_name( $name );
            $product->set_status( 'publish' );
            $product->set_catalog_visibility( 'visible' );
            $product->set_regular_price( (string) $price );
            $product->set_price( (string) $price );
            $product->set_stock_status( 'instock' );
            $product->set_manage_stock( false );
            if ( $category && ! is_wp_error( $category ) ) $product->set_category_ids( [ (int) $category->term_id ] );
            $product_id = $product->save();
            if ( ! $product_id ) continue;
            $created++;
        }

        $agency_prices = [];
        $agency_stock  = [];
        foreach ( $agencies as $index => $agency ) {
            $agency_prices[ $agency ] = $price + ( $index * 50 );
            $agency_stock[ $agency ]  = 10 + ( $index * 5 );
        }
        update_post_meta( $product_id, SLCAT_AGENCIES_META, wp_json_encode( $agencies ) );
        update_post_meta( $product_id, SLCAT_PRICES_META, wp_json_encode( $agency_prices ) );
        update_post_meta( $product_id, SLCAT_STOCK_META, wp_json_encode( $agency_stock ) );
        update_post_meta( $product_id, '_slcat_demo_seed', '1' );
    }
    delete_transient( 'slcat_top_categories_v1' );
    wp_safe_redirect( add_query_arg( [ 'page' => 'sl-catalogue', 'slcat_demo_created' => $created, 'slcat_demo_updated' => $updated ], admin_url( 'admin.php' ) ) );
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
