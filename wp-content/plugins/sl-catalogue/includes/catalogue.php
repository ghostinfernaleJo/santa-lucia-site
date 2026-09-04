<?php
/**
 * Catalogue public Santa Lucia.
 *
 * Les produits WooCommerce restent la source de vente. L'association agence,
 * les prix et les stocks sont stockés dans trois métas JSON, afin que le futur
 * connecteur Odoo puisse les écrire sans créer une copie du catalogue par agence.
 */

defined( 'ABSPATH' ) || exit;

const SLCAT_AGENCIES_META = '_sl_catalogue_agencies';
const SLCAT_PRICES_META   = '_sl_catalogue_prices';
const SLCAT_STOCK_META    = '_sl_catalogue_stock';

/** @return WP_Term[] */
function slcat_agencies() {
    $terms = get_terms( [
        'taxonomy'   => 'sl_agence_promo',
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ] );
    return is_wp_error( $terms ) ? [] : $terms;
}

function slcat_agency_name( $slug ) {
    $term = get_term_by( 'slug', sanitize_title( (string) $slug ), 'sl_agence_promo' );
    return $term && ! is_wp_error( $term ) ? $term->name : (string) $slug;
}

/** @return array<string,mixed> */
function slcat_json_meta( $product_id, $key ) {
    $raw = get_post_meta( (int) $product_id, $key, true );
    if ( is_array( $raw ) ) {
        return $raw;
    }
    $value = json_decode( (string) $raw, true );
    return is_array( $value ) ? $value : [];
}

/** @return string[] */
function slcat_product_agencies( $product_id ) {
    return array_values( array_unique( array_filter( array_map( 'sanitize_title', slcat_json_meta( $product_id, SLCAT_AGENCIES_META ) ) ) ) );
}

function slcat_product_is_catalogue_item( $product_id ) {
    return (bool) slcat_product_agencies( $product_id );
}

function slcat_product_is_available_for( $product_id, $agency ) {
    $agency = sanitize_title( (string) $agency );
    if ( ! in_array( $agency, slcat_product_agencies( $product_id ), true ) ) {
        return false;
    }
    $stock = slcat_json_meta( $product_id, SLCAT_STOCK_META );
    return ! isset( $stock[ $agency ] ) || $stock[ $agency ] === '' || (int) $stock[ $agency ] > 0;
}

function slcat_product_price_for( WC_Product $product, $agency ) {
    $prices = slcat_json_meta( $product->get_id(), SLCAT_PRICES_META );
    $price  = isset( $prices[ $agency ] ) && is_numeric( $prices[ $agency ] ) ? (float) $prices[ $agency ] : (float) $product->get_price();
    return max( 0, $price );
}

function slcat_money( $amount ) {
    return number_format_i18n( (float) $amount, 0 ) . ' FCFA';
}

/** Le catalogue ne charge ses assets que lorsqu'il est effectivement rendu. */
function slcat_enqueue_assets() {
    $css = SL_CATALOGUE_PATH . 'assets/catalogue.css';
    $js  = SL_CATALOGUE_PATH . 'assets/catalogue.js';
    wp_enqueue_style( 'sl-catalogue', SL_CATALOGUE_URL . 'assets/catalogue.css', [], file_exists( $css ) ? (string) filemtime( $css ) : SL_CATALOGUE_VERSION );
    wp_enqueue_script( 'sl-catalogue', SL_CATALOGUE_URL . 'assets/catalogue.js', [], file_exists( $js ) ? (string) filemtime( $js ) : SL_CATALOGUE_VERSION, true );

    $ajax = class_exists( 'WC_AJAX' ) ? WC_AJAX::get_endpoint( 'sl_catalogue_%endpoint%' ) : '';
    wp_localize_script( 'sl-catalogue', 'SLCatalogue', [
        'ajax'       => $ajax,
        'cartUrl'    => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
        'emptyCopy'  => 'Choisissez une agence pour voir les produits, les prix et le stock disponibles.',
        'errorCopy'  => 'Le catalogue est momentanément indisponible. Réessayez dans un instant.',
    ] );
}

/** @return WP_Term[] */
function slcat_categories() {
    $cached = get_transient( 'slcat_top_categories_v1' );
    if ( is_array( $cached ) ) return $cached;

    $catalogue_ids = get_posts( [
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [ [ 'key' => SLCAT_AGENCIES_META, 'value' => '[', 'compare' => 'LIKE' ] ],
    ] );
    if ( ! $catalogue_ids ) {
        set_transient( 'slcat_top_categories_v1', [], 15 * MINUTE_IN_SECONDS );
        return [];
    }
    $terms = wp_get_object_terms( $catalogue_ids, 'product_cat', [
        'number'     => 8,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ] );
    $terms = is_wp_error( $terms ) ? [] : $terms;
    set_transient( 'slcat_top_categories_v1', $terms, 15 * MINUTE_IN_SECONDS );
    return $terms;
}

add_action( 'created_product_cat', 'slcat_clear_category_cache' );
add_action( 'edited_product_cat', 'slcat_clear_category_cache' );
add_action( 'delete_product_cat', 'slcat_clear_category_cache' );
function slcat_clear_category_cache() { delete_transient( 'slcat_top_categories_v1' ); }

function slcat_category_image( WP_Term $term ) {
    $thumb_id = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
    if ( ! $thumb_id ) return '';
    return wp_get_attachment_image( $thumb_id, 'medium_large', false, [ 'loading' => 'lazy', 'alt' => '' ] );
}

/** Shortcode utilisable dans une section Elementor : [sl_catalogue]. */
add_shortcode( 'sl_catalogue', 'slcat_render_catalogue' );
function slcat_render_catalogue() {
    if ( ! class_exists( 'WooCommerce' ) ) return '';
    slcat_enqueue_assets();

    $agencies   = slcat_agencies();
    $categories = slcat_categories();
    $cart_agency = function_exists( 'sl_bp_cart_agency' ) ? sl_bp_cart_agency() : '';
    ob_start();
    ?>
    <section class="slcat" data-cart-agency="<?php echo esc_attr( $cart_agency ); ?>" aria-labelledby="slcat-title">
        <header class="slcat__intro">
            <p class="slcat__eyebrow">Santa Lucia en ligne</p>
            <h1 id="slcat-title">Faire mes courses</h1>
            <p>Choisissez votre agence pour voir le bon prix et le stock disponible.</p>
        </header>

        <div class="slcat__control" role="search">
            <label class="slcat__agency-label" for="slcat-agency">Votre agence</label>
            <select id="slcat-agency" class="slcat__agency" aria-describedby="slcat-agency-help">
                <option value="">Choisir mon agence</option>
                <?php foreach ( $agencies as $agency ) : ?>
                    <option value="<?php echo esc_attr( $agency->slug ); ?>" <?php selected( $cart_agency, $agency->slug ); ?>><?php echo esc_html( $agency->name ); ?></option>
                <?php endforeach; ?>
            </select>
            <span id="slcat-agency-help" class="screen-reader-text">Les prix et la disponibilité dépendent de l’agence sélectionnée.</span>
            <label class="screen-reader-text" for="slcat-search">Rechercher un produit, une marque ou un code-barres</label>
            <input id="slcat-search" class="slcat__search" type="search" autocomplete="off" placeholder="Rechercher un produit, une marque ou un code-barres" disabled>
        </div>

        <p class="slcat__availability" aria-live="polite"><span></span>Choisissez votre agence : les prix et le stock peuvent varier selon le magasin.</p>

        <div class="slcat__body">
            <?php if ( count( $categories ) > 1 ) : ?>
            <section class="slcat__featured" aria-labelledby="slcat-departments-title">
                <div class="slcat__section-head">
                    <div>
                        <p class="slcat__eyebrow">Rayons</p>
                        <h2 id="slcat-departments-title">Choisir un rayon</h2>
                    </div>
                    <button class="slcat__all-cats" type="button">Voir tous les rayons</button>
                </div>
                <div class="slcat__categories">
                    <?php foreach ( $categories as $category ) : ?>
                        <button class="slcat__category" type="button" data-category="<?php echo esc_attr( $category->term_id ); ?>"><?php echo esc_html( $category->name ); ?></button>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <section class="slcat__results" aria-labelledby="slcat-results-title">
                <div class="slcat__results-head">
                    <h2 id="slcat-results-title">Produits disponibles</h2>
                    <button class="slcat__reset" type="button" hidden>Tout afficher</button>
                </div>
                <div class="slcat__results-content" aria-live="polite"><p class="slcat__empty">Choisissez une agence pour voir les produits, les prix et le stock disponibles.</p></div>
            </section>
        </div>
        <div class="slcat__toast" role="status" aria-live="polite" hidden></div>
    </section>
    <?php
    return trim( ob_get_clean() );
}

/** Recherche courte, paginée et filtrée serveur : jamais les 2 000 articles dans le navigateur. */
add_action( 'wc_ajax_sl_catalogue_products', 'slcat_ajax_products' );
add_action( 'wc_ajax_nopriv_sl_catalogue_products', 'slcat_ajax_products' );
function slcat_ajax_products() {
    $agency = isset( $_REQUEST['agency'] ) ? sanitize_title( wp_unslash( $_REQUEST['agency'] ) ) : '';
    if ( ! $agency || ! get_term_by( 'slug', $agency, 'sl_agence_promo' ) ) {
        wp_send_json_error( [ 'message' => 'Choisissez une agence valide.' ], 400 );
    }

    $category = isset( $_REQUEST['category'] ) ? absint( $_REQUEST['category'] ) : 0;
    $search   = isset( $_REQUEST['search'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['search'] ) ) : '';
    $query = new WP_Query( [
        'post_type'              => 'product',
        'post_status'            => 'publish',
        'posts_per_page'         => 12,
        'no_found_rows'          => true,
        'ignore_sticky_posts'    => true,
        's'                      => $search,
        'meta_query'             => [ [ 'key' => SLCAT_AGENCIES_META, 'value' => '"' . $agency . '"', 'compare' => 'LIKE' ] ],
        'tax_query'              => $category ? [ [ 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $category ] ] : [],
    ] );

    ob_start();
    while ( $query->have_posts() ) {
        $query->the_post();
        $product = wc_get_product( get_the_ID() );
        if ( ! $product || ! $product->is_purchasable() || ! slcat_product_is_available_for( $product->get_id(), $agency ) ) continue;
        slcat_render_product_card( $product, $agency );
    }
    wp_reset_postdata();
    $html = trim( ob_get_clean() );
    wp_send_json_success( [ 'html' => $html, 'empty' => $html === '' ] );
}

function slcat_render_product_card( WC_Product $product, $agency ) {
    $image = $product->get_image( 'woocommerce_thumbnail', [ 'loading' => 'lazy', 'alt' => $product->get_name() ] );
    $price = slcat_product_price_for( $product, $agency );
    ?>
    <article class="slcat-product">
        <a class="slcat-product__image" href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>"><?php echo $image; ?></a>
        <div class="slcat-product__copy">
            <h3><a href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>"><?php echo esc_html( $product->get_name() ); ?></a></h3>
            <p class="slcat-product__price"><?php echo esc_html( slcat_money( $price ) ); ?></p>
            <button class="slcat-product__add" type="button" data-product="<?php echo esc_attr( $product->get_id() ); ?>" data-agency="<?php echo esc_attr( $agency ); ?>">Ajouter au panier</button>
        </div>
    </article>
    <?php
}

function slcat_cart_agency() {
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) return '';
    foreach ( WC()->cart->get_cart() as $item ) {
        if ( ! empty( $item['sl_catalogue_agency'] ) ) return sanitize_title( $item['sl_catalogue_agency'] );
    }
    return function_exists( 'sl_bp_cart_agency' ) ? sl_bp_cart_agency() : '';
}

/** Une seule agence reste obligatoire, y compris si le panier contient un Bon Plan ou un repas. */
function slcat_validate_cart_agency( $product_id, $agency ) {
    $cart_agency = slcat_cart_agency();
    if ( $cart_agency && $cart_agency !== $agency ) {
        return new WP_Error( 'slcat_cart_agency', sprintf( 'Votre panier est déjà rattaché à l’agence « %s ». Videz-le pour commander dans une autre agence.', slcat_agency_name( $cart_agency ) ) );
    }
    return true;
}

add_action( 'wc_ajax_sl_catalogue_add', 'slcat_ajax_add' );
add_action( 'wc_ajax_nopriv_sl_catalogue_add', 'slcat_ajax_add' );
function slcat_ajax_add() {
    $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
    $agency     = isset( $_POST['agency'] ) ? sanitize_title( wp_unslash( $_POST['agency'] ) ) : '';
    $product    = $product_id ? wc_get_product( $product_id ) : false;
    if ( ! $product || ! $agency || ! slcat_product_is_available_for( $product_id, $agency ) ) {
        wp_send_json_error( [ 'message' => 'Ce produit n’est pas disponible dans cette agence.' ], 400 );
    }
    $valid = slcat_validate_cart_agency( $product_id, $agency );
    if ( is_wp_error( $valid ) ) wp_send_json_error( [ 'message' => $valid->get_error_message(), 'agency' => true ], 409 );

    $price = slcat_product_price_for( $product, $agency );
    // WC déclenche aussi la validation native lors de cet ajout AJAX.
    $_REQUEST['sl_catalogue_agency'] = $agency;
    $data  = [ 'sl_catalogue_agency' => $agency, 'sl_catalogue_unit_price' => $price ];
    $added = WC()->cart->add_to_cart( $product_id, 1, 0, [], $data );
    if ( ! $added ) wp_send_json_error( [ 'message' => 'Impossible d’ajouter ce produit au panier.' ], 400 );

    wp_send_json_success( [
        'message'   => 'Produit ajouté au panier.',
        'fragments' => apply_filters( 'woocommerce_add_to_cart_fragments', [] ),
        'cart_hash' => WC()->cart->get_cart_hash(),
    ] );
}

/** Bloque l'ajout depuis une fiche WooCommerce lorsqu'aucune agence n'a été précisée. */
add_filter( 'woocommerce_add_to_cart_validation', 'slcat_native_add_validation', 25, 2 );
function slcat_native_add_validation( $passed, $product_id ) {
    if ( ! $passed || ! slcat_product_is_catalogue_item( $product_id ) ) return $passed;
    $agency = isset( $_REQUEST['sl_catalogue_agency'] ) ? sanitize_title( wp_unslash( $_REQUEST['sl_catalogue_agency'] ) ) : '';
    if ( ! $agency ) {
        wc_add_notice( 'Choisissez d’abord votre agence depuis le catalogue afin de connaître le prix et la disponibilité.', 'error' );
        return false;
    }
    if ( ! slcat_product_is_available_for( $product_id, $agency ) ) {
        wc_add_notice( 'Ce produit n’est pas disponible dans cette agence.', 'error' );
        return false;
    }
    $valid = slcat_validate_cart_agency( $product_id, $agency );
    if ( is_wp_error( $valid ) ) {
        wc_add_notice( $valid->get_error_message(), 'error' );
        return false;
    }
    return true;
}

add_action( 'woocommerce_before_calculate_totals', function ( $cart ) {
    if ( is_admin() && ! wp_doing_ajax() ) return;
    foreach ( $cart->get_cart() as $item ) {
        if ( isset( $item['sl_catalogue_unit_price'], $item['data'] ) ) $item['data']->set_price( (float) $item['sl_catalogue_unit_price'] );
    }
}, 20 );

add_filter( 'woocommerce_get_item_data', function ( $data, $item ) {
    if ( empty( $item['sl_catalogue_agency'] ) ) return $data;
    $data[] = [ 'key' => 'Agence de retrait', 'value' => slcat_agency_name( $item['sl_catalogue_agency'] ) ];
    return $data;
}, 10, 2 );

/** Verrouille l'agence au checkout pour les produits du nouveau catalogue. */
add_filter( 'woocommerce_checkout_fields', function ( $fields ) {
    $agency = slcat_cart_agency();
    $has_catalogue = false;
    if ( function_exists( 'WC' ) && WC()->cart ) {
        foreach ( WC()->cart->get_cart() as $item ) if ( ! empty( $item['sl_catalogue_agency'] ) ) $has_catalogue = true;
    }
    if ( ! $has_catalogue || ! $agency || empty( $fields['billing']['sl_collect_agence'] ) ) return $fields;
    $fields['billing']['sl_collect_agence']['options'] = [ $agency => slcat_agency_name( $agency ) ];
    $fields['billing']['sl_collect_agence']['default'] = $agency;
    $fields['billing']['sl_collect_agence']['custom_attributes'] = [ 'data-locked' => '1' ];
    $fields['billing']['sl_collect_agence']['description'] = 'Agence imposée par les produits de votre panier.';
    return $fields;
}, 40 );

add_action( 'woocommerce_checkout_create_order', function ( $order ) {
    $agency = slcat_cart_agency();
    if ( $agency ) $order->update_meta_data( '_sl_collect_agence', $agency );
}, 30 );

/** Configuration manuelle temporaire : le futur connecteur Odoo utilisera les mêmes métas. */
add_action( 'add_meta_boxes_product', function () {
    add_meta_box( 'slcat-product-agencies', 'Catalogue Santa Lucia — agences, prix et stock', 'slcat_product_metabox', 'product', 'normal', 'default' );
} );

function slcat_product_metabox( WP_Post $post ) {
    wp_nonce_field( 'slcat_save_product', 'slcat_product_nonce' );
    $selected = slcat_product_agencies( $post->ID );
    $prices   = slcat_json_meta( $post->ID, SLCAT_PRICES_META );
    $stock    = slcat_json_meta( $post->ID, SLCAT_STOCK_META );
    ?>
    <p>Activez les agences où cet article peut être vendu. Ces valeurs seront remplacées automatiquement par la synchronisation Odoo lorsqu’elle sera branchée.</p>
    <table class="widefat striped"><thead><tr><th>Agence</th><th>Disponible</th><th>Prix FCFA</th><th>Stock agence</th></tr></thead><tbody>
    <?php foreach ( slcat_agencies() as $agency ) : $slug = $agency->slug; ?>
        <tr><td><strong><?php echo esc_html( $agency->name ); ?></strong></td>
        <td><label><input type="checkbox" name="slcat_agencies[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $selected, true ) ); ?>> Oui</label></td>
        <td><input type="number" min="0" step="1" name="slcat_prices[<?php echo esc_attr( $slug ); ?>]" value="<?php echo esc_attr( $prices[ $slug ] ?? '' ); ?>"></td>
        <td><input type="number" min="0" step="1" name="slcat_stock[<?php echo esc_attr( $slug ); ?>]" value="<?php echo esc_attr( $stock[ $slug ] ?? '' ); ?>"><small> vide = stock WooCommerce</small></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php
}

add_action( 'save_post_product', function ( $post_id ) {
    if ( ! isset( $_POST['slcat_product_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['slcat_product_nonce'] ) ), 'slcat_save_product' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE || ! current_user_can( 'edit_post', $post_id ) ) return;
    $agencies = isset( $_POST['slcat_agencies'] ) ? array_values( array_unique( array_filter( array_map( 'sanitize_title', (array) wp_unslash( $_POST['slcat_agencies'] ) ) ) ) ) : [];
    $prices = $stock = [];
    foreach ( $agencies as $agency ) {
        $raw_price = $_POST['slcat_prices'][ $agency ] ?? '';
        $raw_stock = $_POST['slcat_stock'][ $agency ] ?? '';
        if ( $raw_price !== '' && is_numeric( $raw_price ) ) $prices[ $agency ] = max( 0, (float) $raw_price );
        if ( $raw_stock !== '' && is_numeric( $raw_stock ) ) $stock[ $agency ] = max( 0, (int) $raw_stock );
    }
    update_post_meta( $post_id, SLCAT_AGENCIES_META, wp_json_encode( $agencies ) );
    update_post_meta( $post_id, SLCAT_PRICES_META, wp_json_encode( $prices ) );
    update_post_meta( $post_id, SLCAT_STOCK_META, wp_json_encode( $stock ) );
} );
