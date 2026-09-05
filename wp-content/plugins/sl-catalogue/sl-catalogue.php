<?php
/**
 * Plugin Name: Santa Lucia - Catalogue agences
 * Description: Catalogue e-commerce par agence : recherche rapide, disponibilité, prix et stock par magasin, compatible Drop & Collect.
 * Version: 0.2.0
 * Author: Santa Lucia
 * Text Domain: sl-catalogue
 */

defined( 'ABSPATH' ) || exit;

define( 'SL_CATALOGUE_VERSION', '0.2.0' );
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

/** Retrouve un visuel déjà présent dans la médiathèque, sans téléverser de copie. */
function slcat_demo_media_id( $needle ) {
    global $wpdb;
    $needle = sanitize_text_field( (string) $needle );
    if ( '' === $needle ) return 0;
    $slug = sanitize_title( $needle );
    $id = $wpdb->get_var( $wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%%' AND post_status = 'inherit' AND (post_title LIKE %s OR post_name LIKE %s) ORDER BY ID DESC LIMIT 1",
        '%' . $wpdb->esc_like( $needle ) . '%',
        '%' . $wpdb->esc_like( $slug ) . '%'
    ) );
    return absint( $id );
}

/** Crée un catalogue de test reproductible et le rend visible dans chaque agence. */
add_action( 'admin_post_slcat_seed_demo_catalogue', function () {
    if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Accès non autorisé.' );
    check_admin_referer( 'slcat_seed_demo_catalogue' );

    // Conserve les clés des 20 démos existantes. Chaque visuel a été vérifié
    // dans la médiathèque : aucun remplacement aléatoire entre familles.
    $items = [
        [ 'cafe-moulu', 'Petit beurre pur beurre BF 200 g', 1850, 16953, 'petit-dejeuner' ],
        [ 'boisson-top', 'Boisson Top 50 cl', 650, 17004, 'boissons' ],
        [ 'boulettes-sautees', 'Boulettes sautées', 1450, 16994, 'traiteur' ],
        [ 'biere-blonde', 'Bière blonde Pilsner Urquell 33 cl', 900, 17001, 'boissons' ],
        [ 'produit-demo-huile-de-tournesol-1-l', 'Riz à la viande', 2100, 17045, 'traiteur' ],
        [ 'produit-demo-lait-uht-demi-ecreme-1-l', 'Lait concentré sucré OMIT 1 kg', 1100, 16980, 'petit-dejeuner' ],
        [ 'produit-demo-biscuits-chocolat-200-g', 'Goûters fourrés chocolat BF 300 g', 950, 16972, 'biscuits-gouters' ],
        [ 'sirop-cassis', 'Sirop cassis 75 cl', 1750, 16995, 'boissons' ],
        [ 'produit-demo-the-citron-25-sachets', 'Goûters ronds fourrés chocolat BF 300 g', 1200, 16977, 'biscuits-gouters' ],
        [ 'detergent-viking', 'Détergent liquide Viking 2 L', 1600, 17023, 'entretien-maison' ],
        [ 'produit-demo-papier-toilette-doux-x12', 'Dégraissant Sim Fluch 1 L', 2800, 17026, 'entretien-maison' ],
        [ 'produit-demo-shampooing-nutrition-400-ml', 'Shampooing Evoluderm', 2400, 16913, 'hygiene-beaute' ],
        [ 'produit-demo-dentifrice-fraicheur-100-g', 'Masque capillaire Byphasse', 1350, 16907, 'hygiene-beaute' ],
        [ 'produit-demo-creme-hydratante-250-ml', 'Savon Soft Boni 750 ml', 2200, 17029, 'entretien-maison' ],
        [ 'produit-demo-arachides-grillees-150-g', 'Cookies nougatine et chocolat BF 200 g', 700, 16967, 'biscuits-gouters' ],
        [ 'produit-demo-chips-de-plantain-100-g', 'Sablés noix de coco BF 125 g', 800, 16959, 'biscuits-gouters' ],
        [ 'produit-demo-chocolat-noir-100-g', 'Poulet yassa', 1150, 17033, 'traiteur' ],
        [ 'produit-demo-eau-gazeuse-50-cl', 'Jus Planet assorti 1,20 L', 500, 17046, 'boissons' ],
        [ 'produit-demo-cafe-instantane-100-g', 'Poisson carpe à la poêle', 2900, 17041, 'traiteur' ],
        [ 'jus-pomme', 'Jus de pomme Ceres 1 L', 1250, 16998, 'boissons' ],
    ];
    $departments = [
        'boissons' => 'Boissons',
        'petit-dejeuner' => 'Petit déjeuner',
        'biscuits-gouters' => 'Biscuits & goûters',
        'traiteur' => 'Plats cuisinés',
        'hygiene-beaute' => 'Hygiène & beauté',
        'entretien-maison' => 'Entretien de la maison',
    ];
    $category_ids = [];
    foreach ( $departments as $slug => $label ) {
        $term = term_exists( 'slcat-demo-' . $slug, 'product_cat' );
        if ( ! $term ) $term = wp_insert_term( $label, 'product_cat', [ 'slug' => 'slcat-demo-' . $slug ] );
        if ( is_wp_error( $term ) ) wp_die( esc_html( $term->get_error_message() ) );
        $category_ids[ $slug ] = (int) $term['term_id'];
        update_term_meta( $category_ids[ $slug ], '_slcat_demo_department', '1' );
    }
    $agencies = function_exists( 'slcat_agencies' ) ? wp_list_pluck( slcat_agencies(), 'slug' ) : [];

    $created = 0;
    $updated = 0;
    foreach ( $items as [ $key, $name, $price, $image_id, $department ] ) {
        $existing_ids = get_posts( [ 'post_type' => 'product', 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_slcat_demo_key', 'meta_value' => $key ] );
        $existing = $existing_ids ? get_post( $existing_ids[0] ) : null;
        if ( $existing ) {
            $product_id = (int) $existing->ID;
            $product = wc_get_product( $product_id );
            $updated++;
        } else {
            $product = new WC_Product_Simple();
            $product->set_status( 'publish' );
            $product->set_catalog_visibility( 'visible' );
            $product->set_stock_status( 'instock' );
            $product->set_manage_stock( false );
            $created++;
        }
        if ( ! $product ) continue;
        $product->set_name( 'Produit démo — ' . $name );
        $product->set_category_ids( [ $category_ids[ $department ] ] );
        $product->set_regular_price( (string) $price );
        $product->set_price( (string) $price );
        $product->set_image_id( wp_attachment_is_image( $image_id ) ? $image_id : 0 );
        $product_id = $product->save();
        if ( ! $product_id ) continue;

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
        update_post_meta( $product_id, '_slcat_demo_key', $key );
    }
    update_option( 'slcat_demo_seed_version', '2', false );
    slcat_clear_category_cache();
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
