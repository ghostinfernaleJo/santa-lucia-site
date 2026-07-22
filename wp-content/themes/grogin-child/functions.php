<?php

/**s
 * functions.php
 * @package WordPress
 * @subpackage Grogin
 * @since Grogin 1.0
 * 
 */

add_action( 'wp_enqueue_scripts', 'grogin_enqueue_styles', 99 );
function grogin_enqueue_styles() {
    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
    wp_style_add_data( 'parent-style', 'rtl', 'replace' );
    wp_enqueue_style( 'child-style', get_stylesheet_directory_uri() . '/style.css', array( 'parent-style' ) );
}

/**
 * Fix : off-canvas drawers (width:100% fixed) causent un scroll horizontal sur mobile.
 * Inline pour contourner le cache Varnish sur les assets CSS.
 */
add_action( 'wp_head', function() {
    echo '<style>html{overflow-x:hidden}</style>';
}, 99 );

/**
 * Keep the custom WhatsApp button intact and shorten only its visible label on product pages.
 */
add_action( 'template_redirect', 'sl_single_product_whatsapp_label_buffer' );
function sl_single_product_whatsapp_label_buffer() {
    if ( function_exists( 'is_product' ) && is_product() && ! is_admin() ) {
        ob_start( 'sl_single_product_whatsapp_label' );
    }
}

function sl_single_product_whatsapp_label( $html ) {
    return str_replace( 'Commander sur WhatsApp', 'Commander', $html );
}

/**
 * Remplace les images app footer du démo klbtheme.com par les copies locales.
 * Approche output buffer = robuste peu importe le format stocké dans le theme mod.
 */
add_action( 'template_redirect', 'sl_footer_app_images_ob_start', 1 );
function sl_footer_app_images_ob_start() {
    if ( is_admin() ) return;
    ob_start( 'sl_footer_app_images_replace' );
}

function sl_footer_app_images_replace( $html ) {
    $replacements = array(
        'https://klbtheme.com/grogin/wp-content/uploads/2023/11/google-play-button-dark.png'
            => 'https://complexesantalucia.com/wp-content/uploads/2026/06/google-play-button-dark.png',
        'https://klbtheme.com/grogin/wp-content/uploads/2023/10/apple-store-button-dark.png'
            => 'https://complexesantalucia.com/wp-content/uploads/2026/06/apple-store-button-dark.png',
    );
    $html = str_replace(
        array_keys( $replacements ),
        array_values( $replacements ),
        $html
    );

    // Recherche site-wide : le formulaire du header force post_type=product,
    // ce qui limite la recherche aux produits WooCommerce. On retire ce champ
    // cache pour que l'URL soit un ?s= propre (la recherche unifiee, cote
    // plugin, prend le relais et cherche produits + bons plans + fast food +
    // articles). On reetiquette aussi le placeholder produit-only.
    // Lookaheads : matche l'input qui porte A LA FOIS name="post_type" ET
    // value="product", quel que soit l'ordre des attributs.
    $html = preg_replace(
        '#<input(?=[^>]*\bname=(["\'])post_type\1)(?=[^>]*\bvalue=(["\'])product\2)[^>]*>#i',
        '',
        $html
    );
    $html = str_replace( 'Rechercher des produits', 'Rechercher sur le site', $html );

    return $html;
}

/**
 * Ajoute une date de fin aux categories utilisees comme campagnes promotionnelles.
 */
add_action( 'product_cat_add_form_fields', 'sl_campaign_end_add_field' );
function sl_campaign_end_add_field() {
    ?>
    <div class="form-field">
        <label for="sl_campaign_end"><?php esc_html_e( 'Fin de campagne', 'grogin-child' ); ?></label>
        <input type="datetime-local" id="sl_campaign_end" name="sl_campaign_end" />
        <p><?php esc_html_e( 'A cette date et heure, les produits de cette campagne disparaissent automatiquement de la page Promotions.', 'grogin-child' ); ?></p>
    </div>
    <?php
}

add_action( 'product_cat_edit_form_fields', 'sl_campaign_end_edit_field' );
function sl_campaign_end_edit_field( $term ) {
    $stored_value = get_term_meta( $term->term_id, '_sl_campaign_end', true );
    $input_value  = $stored_value ? str_replace( ' ', 'T', substr( $stored_value, 0, 16 ) ) : '';
    ?>
    <tr class="form-field">
        <th scope="row"><label for="sl_campaign_end"><?php esc_html_e( 'Fin de campagne', 'grogin-child' ); ?></label></th>
        <td>
            <input type="datetime-local" id="sl_campaign_end" name="sl_campaign_end" value="<?php echo esc_attr( $input_value ); ?>" />
            <p class="description"><?php esc_html_e( 'Laissez vide pour une campagne sans expiration automatique.', 'grogin-child' ); ?></p>
        </td>
    </tr>
    <?php
}

add_action( 'created_product_cat', 'sl_campaign_end_save_field' );
add_action( 'edited_product_cat', 'sl_campaign_end_save_field' );
function sl_campaign_end_save_field( $term_id ) {
    if ( ! current_user_can( 'manage_product_terms' ) ) {
        return;
    }

    $raw_value = isset( $_POST['sl_campaign_end'] ) ? sanitize_text_field( wp_unslash( $_POST['sl_campaign_end'] ) ) : '';
    if ( '' === $raw_value ) {
        delete_term_meta( $term_id, '_sl_campaign_end' );
        return;
    }

    $date = date_create_immutable_from_format( 'Y-m-d\TH:i', $raw_value, wp_timezone() );
    if ( $date ) {
        update_term_meta( $term_id, '_sl_campaign_end', $date->format( 'Y-m-d H:i:s' ) );
    }
}

/**
 * Affiche uniquement les campagnes non expirees sur l'archive Promotions.
 */
add_action( 'pre_get_posts', 'sl_promotions_fallback_to_mixed_deals', 20 );
function sl_promotions_fallback_to_mixed_deals( $query ) {
    if ( is_admin() || ! $query->is_main_query() || empty( $query->query_vars['product_cat'] ) ) {
        return;
    }

    $product_cat = $query->query_vars['product_cat'];
    if ( is_array( $product_cat ) || 'promotions' !== trim( (string) $product_cat, '/' ) ) {
        return;
    }

    $active_product_ids = sl_get_active_promotion_product_ids();

    if ( ! empty( $active_product_ids ) ) {
        $query->set( 'post__in', $active_product_ids );
        return;
    }

    // La requete sera remplacee par la page Bon plans dans template_redirect.
    $query->set( 'post__in', array( 0 ) );
}

/**
 * Charge la vraie page Bon plans a la meme URL lorsque Promotions est vide.
 * Cela garantit une source de donnees et un affichage identiques aux bons plans.
 */
add_action( 'template_redirect', 'sl_promotions_use_deals_page', 50 );
function sl_promotions_use_deals_page() {
    if ( is_admin() || ! is_tax( 'product_cat', 'promotions' ) || ! empty( sl_get_active_promotion_product_ids() ) ) {
        return;
    }

    $deals_page = get_page_by_path( 'bon-plans', OBJECT, 'page' );
    if ( ! $deals_page || 'publish' !== $deals_page->post_status ) {
        return;
    }

    global $wp_query, $wp_the_query, $post;

    $deals_query = new WP_Query( array( 'page_id' => (int) $deals_page->ID ) );
    if ( ! $deals_query->have_posts() ) {
        return;
    }

    $wp_query     = $deals_query;
    $wp_the_query = $deals_query;
    $post         = $deals_page;
    setup_postdata( $post );

    // Empeche WordPress de rediriger /promotions/ vers l'URL canonique de la page.
    add_filter( 'redirect_canonical', '__return_false', 99 );

    // Le cache frontal ne doit pas conserver l'ancienne archive Promotions.
    status_header( 200 );
    nocache_headers();
    do_action( 'litespeed_control_set_nocache', 'Promotions charge la page Bon plans' );

    // Rend directement le document Elementor de Bon plans. Le contenu est genere
    // avant get_header() afin qu'Elementor enregistre ses feuilles de style et
    // scripts avant wp_head(). L'URL reste /promotions/.
    $rest_request  = new WP_REST_Request( 'GET', '/wp/v2/pages/' . (int) $deals_page->ID );
    $rest_response = rest_do_request( $rest_request );
    $rest_data     = is_wp_error( $rest_response ) ? array() : $rest_response->get_data();
    $page_content  = isset( $rest_data['content']['rendered'] ) ? $rest_data['content']['rendered'] : '';

    if ( '' === $page_content && class_exists( '\\Elementor\\Plugin' ) ) {
        $page_content = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( (int) $deals_page->ID );
    }
    if ( '' === $page_content ) {
        $page_content = apply_filters( 'the_content', $deals_page->post_content );
    }

    get_header();
    echo $page_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    get_footer();
    exit;
}

function sl_get_product_cat_by_candidates( $slugs, $names = array() ) {
    foreach ( $slugs as $slug ) {
        $term = get_term_by( 'slug', $slug, 'product_cat' );
        if ( $term && ! is_wp_error( $term ) ) {
            return $term;
        }
    }

    foreach ( $names as $name ) {
        $term = get_term_by( 'name', $name, 'product_cat' );
        if ( $term && ! is_wp_error( $term ) ) {
            return $term;
        }
    }

    return null;
}

function sl_get_active_promotion_product_ids() {
    if ( ! function_exists( 'wc_get_product_ids_on_sale' ) ) {
        return array();
    }

    $promotion_term = sl_get_product_cat_by_candidates( array( 'promotions' ), array( 'Promotions' ) );
    $sale_ids       = wc_get_product_ids_on_sale();

    if ( ! $promotion_term || empty( $sale_ids ) ) {
        return array();
    }

    // Seules les categories enfants directes de Promotions sont des campagnes.
    // get_term_children() ramenait aussi les sous-categories produit (Cereales,
    // Cosmetiques, etc.) qui, sans date de fin, restaient actives par erreur.
    $campaign_ids = get_terms(
        array(
            'taxonomy'   => 'product_cat',
            'parent'     => (int) $promotion_term->term_id,
            'hide_empty' => false,
            'fields'     => 'ids',
        )
    );
    if ( is_wp_error( $campaign_ids ) || empty( $campaign_ids ) ) {
        return array();
    }

    $now                 = current_time( 'mysql' );
    $active_campaign_ids = array();

    foreach ( $campaign_ids as $campaign_id ) {
        $campaign = get_term( $campaign_id, 'product_cat' );
        if ( ! $campaign || is_wp_error( $campaign ) || in_array( $campaign->slug, array( 'bons-plans-mixtes-agences', 'bons-plans-mixes-agences', 'bons-plans-mixtes' ), true ) ) {
            continue;
        }

        $end_value = get_term_meta( $campaign_id, '_sl_campaign_end', true );
        if ( ! $end_value || $end_value > $now ) {
            $active_campaign_ids[] = (int) $campaign_id;
        }
    }

    if ( empty( $active_campaign_ids ) ) {
        return array();
    }

    $active_promotions = new WP_Query(
        array(
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'post__in'       => array_map( 'absint', $sale_ids ),
            'post_status'    => 'publish',
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'tax_query'      => array(
                'relation' => 'AND',
                array(
                    'field'            => 'term_id',
                    'include_children' => true,
                    'taxonomy'         => 'product_cat',
                    'terms'            => array( (int) $promotion_term->term_id ),
                ),
                array(
                    'field'            => 'term_id',
                    'include_children' => true,
                    'taxonomy'         => 'product_cat',
                    'terms'            => $active_campaign_ids,
                ),
            ),
        )
    );

    return array_map( 'absint', $active_promotions->posts );
}
