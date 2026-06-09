<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// 1. Enregistrement du CPT
add_action( 'init', 'sl_cwoo_register_cpt' );
function sl_cwoo_register_cpt() {
    register_post_type( 'sl_campagne_woo', [
        'labels' => [
            'name'          => 'Campagnes',
            'singular_name' => 'Campagne',
            'add_new_item'  => 'Ajouter une campagne',
            'edit_item'     => 'Modifier la campagne',
        ],
        'public'          => false,
        'show_ui'         => true,
        'menu_icon'       => 'dashicons-megaphone',
        'menu_position'   => 26,
        'supports'        => [ 'title', 'thumbnail' ], // Thumbnail used as Banner
    ]);
}

// 2. Metaboxes
add_action( 'add_meta_boxes', 'sl_cwoo_add_metaboxes' );
function sl_cwoo_add_metaboxes() {
    add_meta_box( 'sl_cwoo_details', 'Paramètres de la Campagne', 'sl_cwoo_render_metabox', 'sl_campagne_woo', 'normal', 'high' );
    add_meta_box( 'sl_cwoo_products', 'Produits Associés', 'sl_cwoo_render_products_metabox', 'sl_campagne_woo', 'normal', 'high' );
}

function sl_cwoo_render_metabox( $post ) {
    wp_nonce_field( 'sl_cwoo_save', 'sl_cwoo_nonce' );
    $date_debut = get_post_meta( $post->ID, '_sl_cwoo_date_debut', true );
    $date_fin   = get_post_meta( $post->ID, '_sl_cwoo_date_fin', true );
    $action     = get_post_meta( $post->ID, '_sl_cwoo_action', true ) ?: 'keep';
    $term_id    = get_post_meta( $post->ID, '_sl_cwoo_term_id', true );
    
    echo '<p><label><strong>Date de début :</strong><br>';
    echo '<input type="date" name="sl_cwoo_date_debut" value="'.esc_attr($date_debut).'"></label></p>';
    
    echo '<p><label><strong>Date de fin :</strong><br>';
    echo '<input type="date" name="sl_cwoo_date_fin" value="'.esc_attr($date_fin).'"></label></p>';
    
    echo '<p><strong>Action à l\'expiration :</strong><br>';
    echo '<label><input type="radio" name="sl_cwoo_action" value="keep" '.checked($action, 'keep', false).'> Laisser affiché (désactive l\'achat et affiche "Promotion terminée")</label><br>';
    echo '<label><input type="radio" name="sl_cwoo_action" value="hide" '.checked($action, 'hide', false).'> Faire disparaître la campagne (cache la catégorie et ses produits)</label></p>';

    if ( $term_id ) {
        $term = get_term( $term_id, 'product_cat' );
        if ( ! is_wp_error( $term ) && $term ) {
            echo '<p style="color:green; padding: 10px; background: #e5ffe5; border: 1px solid #00cc00;">✓ Associé à la catégorie WooCommerce : <strong>' . esc_html($term->name) . '</strong></p>';
        }
    } else {
        echo '<p style="color:orange;">La catégorie WooCommerce sera créée lors de l\'enregistrement.</p>';
    }
}

function sl_cwoo_render_products_metabox( $post ) {
    $term_id = get_post_meta( $post->ID, '_sl_cwoo_term_id', true );
    $product_ids = [];

    if ( $term_id ) {
        $product_ids = get_posts([
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'tax_query'      => [
                [
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => $term_id
                ]
            ]
        ]);
    }
    ?>
    <p>Recherchez et ajoutez manuellement des produits existants à cette campagne.</p>
    <select class="wc-product-search" multiple="multiple" style="width: 100%;" name="sl_cwoo_manual_products[]" data-placeholder="Rechercher des produits..." data-action="woocommerce_json_search_products_and_variations">
        <?php
        if ( ! empty( $product_ids ) && function_exists('wc_get_product') ) {
            foreach ( $product_ids as $product_id ) {
                $product = wc_get_product( $product_id );
                if ( is_object( $product ) ) {
                    echo '<option value="' . esc_attr( $product_id ) . '" selected="selected">' . wp_kses_post( $product->get_formatted_name() ) . '</option>';
                }
            }
        }
        ?>
    </select>
    <p class="description">Note : Enregistrez la campagne pour appliquer les changements. Si vous retirez un produit d'ici, il perdra la catégorie de cette campagne.</p>
    <?php
    // Ensure WooCommerce scripts for this field are loaded
    if ( function_exists('WC') ) {
        wp_enqueue_style( 'woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css' );
        wp_enqueue_script( 'wc-admin-meta-boxes' );
    }
}

// 3. Save post -> sync with product_cat
add_action( 'save_post_sl_campagne_woo', 'sl_cwoo_save_post', 10, 2 );
function sl_cwoo_save_post( $post_id, $post ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! isset( $_POST['sl_cwoo_nonce'] ) || ! wp_verify_nonce( $_POST['sl_cwoo_nonce'], 'sl_cwoo_save' ) ) return;

    $date_debut = sanitize_text_field( $_POST['sl_cwoo_date_debut'] ?? '' );
    $date_fin   = sanitize_text_field( $_POST['sl_cwoo_date_fin'] ?? '' );
    $action     = sanitize_text_field( $_POST['sl_cwoo_action'] ?? 'keep' );

    update_post_meta( $post_id, '_sl_cwoo_date_debut', $date_debut );
    update_post_meta( $post_id, '_sl_cwoo_date_fin', $date_fin );
    update_post_meta( $post_id, '_sl_cwoo_action', $action );

    // Sync with product_cat
    $term_id = get_post_meta( $post_id, '_sl_cwoo_term_id', true );
    if ( $term_id ) {
        wp_update_term( $term_id, 'product_cat', [ 'name' => $post->post_title ] );
    } else {
        $inserted = wp_insert_term( $post->post_title, 'product_cat' );
        if ( ! is_wp_error( $inserted ) ) {
            $term_id = $inserted['term_id'];
            update_post_meta( $post_id, '_sl_cwoo_term_id', $term_id );
        }
    }

    // Set term meta to easily query
    if ( $term_id && ! is_wp_error($term_id) ) {
        update_term_meta( $term_id, '_sl_cwoo_campaign_id', $post_id );
        
        // Sync thumbnail if possible
        if ( has_post_thumbnail( $post_id ) ) {
            $thumb_id = get_post_thumbnail_id( $post_id );
            update_term_meta( $term_id, 'thumbnail_id', $thumb_id );
        }

        // Sync manual products
        $manual_products = isset($_POST['sl_cwoo_manual_products']) ? array_map('intval', $_POST['sl_cwoo_manual_products']) : [];
        
        // 1. Get current products
        $current_products = get_posts([
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'tax_query'      => [
                [
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => $term_id
                ]
            ]
        ]);

        // 2. Remove term from products that are no longer selected
        $to_remove = array_diff( $current_products, $manual_products );
        foreach ( $to_remove as $p_id ) {
            wp_remove_object_terms( $p_id, $term_id, 'product_cat' );
        }

        // 3. Add term to newly selected products
        $to_add = array_diff( $manual_products, $current_products );
        foreach ( $to_add as $p_id ) {
            wp_set_object_terms( $p_id, [ (int) $term_id ], 'product_cat', true );
        }
    }
}

// 4. Hook pour empêcher l'achat si expiré
add_filter( 'woocommerce_is_purchasable', 'sl_cwoo_is_purchasable', 10, 2 );
function sl_cwoo_is_purchasable( $purchasable, $product ) {
    // Si déjà non achetable, on passe
    if ( ! $purchasable ) return $purchasable;

    $terms = wp_get_post_terms( $product->get_id(), 'product_cat' );
    foreach ( $terms as $term ) {
        $campaign_id = get_term_meta( $term->term_id, '_sl_cwoo_campaign_id', true );
        if ( $campaign_id ) {
            $date_fin = get_post_meta( $campaign_id, '_sl_cwoo_date_fin', true );
            if ( $date_fin ) {
                $today = current_time( 'Y-m-d' );
                if ( $today > $date_fin ) {
                    return false; // Expiré, on ne peut pas acheter
                }
            }
        }
    }
    return $purchasable;
}

// 5. Hook pour "Promotion terminée" badge
add_action( 'woocommerce_before_shop_loop_item_title', 'sl_cwoo_show_expired_badge', 15 );
add_action( 'woocommerce_single_product_summary', 'sl_cwoo_show_expired_badge', 5 );
function sl_cwoo_show_expired_badge() {
    global $product;
    if ( ! $product ) return;

    if ( ! $product->is_purchasable() ) {
        $terms = wp_get_post_terms( $product->get_id(), 'product_cat' );
        foreach ( $terms as $term ) {
            $campaign_id = get_term_meta( $term->term_id, '_sl_cwoo_campaign_id', true );
            if ( $campaign_id ) {
                $date_fin = get_post_meta( $campaign_id, '_sl_cwoo_date_fin', true );
                if ( $date_fin && current_time( 'Y-m-d' ) > $date_fin ) {
                    echo '<span class="sl-cwoo-expired-badge" style="display:block; background:#e91e63; color:#fff; padding:6px 10px; text-align:center; font-weight:bold; font-size:13px; border-radius:4px; margin-bottom:10px;">Promotion terminée</span>';
                    return;
                }
            }
        }
    }
}

// 6. Hook pour cacher les catégories expirées si "hide"
add_filter( 'get_terms', 'sl_cwoo_hide_expired_terms', 10, 4 );
function sl_cwoo_hide_expired_terms( $terms, $taxonomies, $args, $term_query ) {
    if ( is_admin() || empty($taxonomies) || ! in_array( 'product_cat', $taxonomies ) ) {
        return $terms;
    }

    $filtered_terms = [];
    $today = current_time( 'Y-m-d' );

    foreach ( $terms as $term ) {
        if ( ! is_object( $term ) ) {
            $filtered_terms[] = $term;
            continue;
        }

        if ( isset($term->term_id) ) {
            $campaign_id = get_term_meta( $term->term_id, '_sl_cwoo_campaign_id', true );
            if ( $campaign_id ) {
                $date_fin = get_post_meta( $campaign_id, '_sl_cwoo_date_fin', true );
                $action   = get_post_meta( $campaign_id, '_sl_cwoo_action', true );
                if ( $date_fin && $today > $date_fin && $action === 'hide' ) {
                    continue; // On ignore ce terme
                }
            }
        }
        $filtered_terms[] = $term;
    }
    return $filtered_terms;
}

// 7. Cacher les produits des catégories expirées "hide" de la boutique
add_action( 'pre_get_posts', 'sl_cwoo_hide_expired_products' );
function sl_cwoo_hide_expired_products( $query ) {
    if ( is_admin() || ! $query->is_main_query() ) return;

    if ( $query->get('post_type') === 'product' || is_shop() || is_product_category() || is_product_taxonomy() ) {
        $terms_to_exclude = [];
        $today = current_time( 'Y-m-d' );

        $campaigns = get_posts([
            'post_type' => 'sl_campagne_woo',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_sl_cwoo_action',
                    'value' => 'hide'
                ],
                [
                    'key' => '_sl_cwoo_date_fin',
                    'value' => $today,
                    'compare' => '<',
                    'type' => 'DATE'
                ]
            ]
        ]);

        foreach ( $campaigns as $camp ) {
            $term_id = get_post_meta( $camp->ID, '_sl_cwoo_term_id', true );
            if ( $term_id ) {
                $terms_to_exclude[] = $term_id;
            }
        }

        if ( ! empty( $terms_to_exclude ) ) {
            $tax_query = $query->get('tax_query') ?: [];
            $tax_query[] = [
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => $terms_to_exclude,
                'operator' => 'NOT IN'
            ];
            $query->set('tax_query', $tax_query);
        }
    }
}

// 8. Ajouter automatiquement les produits des campagnes actives aux promotions WooCommerce/Grogin
function sl_cwoo_get_active_campaign_product_ids() {
    $today = current_time( 'Y-m-d' );

    static $cache = [];
    if ( isset( $cache[ $today ] ) ) {
        return $cache[ $today ];
    }

    $campaign_ids = get_posts([
        'post_type'      => 'sl_campagne_woo',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
            'relation' => 'AND',
            [
                'relation' => 'OR',
                [
                    'key'     => '_sl_cwoo_date_debut',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key'     => '_sl_cwoo_date_debut',
                    'value'   => '',
                    'compare' => '=',
                ],
                [
                    'key'     => '_sl_cwoo_date_debut',
                    'value'   => $today,
                    'compare' => '<=',
                    'type'    => 'DATE',
                ],
            ],
            [
                'relation' => 'OR',
                [
                    'key'     => '_sl_cwoo_date_fin',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key'     => '_sl_cwoo_date_fin',
                    'value'   => '',
                    'compare' => '=',
                ],
                [
                    'key'     => '_sl_cwoo_date_fin',
                    'value'   => $today,
                    'compare' => '>=',
                    'type'    => 'DATE',
                ],
            ],
        ],
    ]);

    if ( empty( $campaign_ids ) ) {
        $cache[ $today ] = [];
        return $cache[ $today ];
    }

    $term_ids = [];
    foreach ( $campaign_ids as $campaign_id ) {
        $term_id = (int) get_post_meta( $campaign_id, '_sl_cwoo_term_id', true );
        if ( $term_id > 0 ) {
            $term_ids[] = $term_id;
        }
    }

    if ( empty( $term_ids ) ) {
        $cache[ $today ] = [];
        return $cache[ $today ];
    }

    $cache[ $today ] = get_posts([
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'tax_query'      => [
            [
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => array_unique( $term_ids ),
            ],
        ],
    ]);

    return wp_parse_id_list( $cache[ $today ] );
}

function sl_cwoo_get_native_sale_product_ids() {
    static $product_ids_on_sale = null;

    if ( null !== $product_ids_on_sale ) {
        return $product_ids_on_sale;
    }

    if ( ! class_exists( 'WC_Data_Store' ) ) {
        $product_ids_on_sale = [];
        return $product_ids_on_sale;
    }

    $data_store       = WC_Data_Store::load( 'product' );
    $on_sale_products = $data_store->get_on_sale_products();

    $product_ids_on_sale = wp_parse_id_list( array_merge(
        wp_list_pluck( $on_sale_products, 'id' ),
        array_diff( wp_list_pluck( $on_sale_products, 'parent_id' ), [ 0 ] )
    ) );

    return $product_ids_on_sale;
}

function sl_cwoo_include_campaign_products_in_sale_ids( $product_ids ) {
    if ( ! is_array( $product_ids ) ) {
        $product_ids = [];
    }

    return wp_parse_id_list( array_merge( $product_ids, sl_cwoo_get_home_promo_product_ids() ) );
}
add_filter( 'transient_wc_products_onsale', 'sl_cwoo_include_campaign_products_in_sale_ids' );
add_filter( 'pre_set_transient_wc_products_onsale', 'sl_cwoo_include_campaign_products_in_sale_ids' );

function sl_cwoo_get_home_promo_product_ids() {
    $campaign_product_ids = sl_cwoo_get_active_campaign_product_ids();
    if ( ! empty( $campaign_product_ids ) ) {
        return $campaign_product_ids;
    }

    return sl_cwoo_get_bon_plan_fallback_product_ids();
}

function sl_cwoo_query_has_sale_filter( $query ) {
    if ( 'product' !== $query->get( 'post_type' ) ) {
        return false;
    }

    if ( $query->get( 'klb_on_sale_products' ) || 'yes' === $query->get( 'sl_cwoo_home_promos' ) ) {
        return true;
    }

    if ( '_sale_price' === $query->get( 'meta_key' ) ) {
        return true;
    }

    $post_in = $query->get( 'post__in' );
    if ( is_array( $post_in ) && ! empty( $post_in ) ) {
        $native_sale_ids = sl_cwoo_get_native_sale_product_ids();
        if ( ! empty( $native_sale_ids ) ) {
            $current_ids = wp_parse_id_list( $post_in );
            sort( $current_ids );
            sort( $native_sale_ids );
            return $current_ids === $native_sale_ids;
        }
    }

    return false;
}

function sl_cwoo_strip_product_cat_tax_query( $tax_query ) {
    if ( empty( $tax_query ) || ! is_array( $tax_query ) ) {
        return $tax_query;
    }

    foreach ( $tax_query as $key => $clause ) {
        if ( is_array( $clause ) && isset( $clause['taxonomy'] ) && 'product_cat' === $clause['taxonomy'] ) {
            unset( $tax_query[ $key ] );
        }
    }

    return array_values( $tax_query );
}

function sl_cwoo_force_home_promo_query( $query ) {
    if ( is_admin() || ! $query instanceof WP_Query ) {
        return;
    }

    if ( ! ( is_front_page() || is_home() || is_page( 1116 ) ) ) {
        return;
    }

    if ( ! sl_cwoo_query_has_sale_filter( $query ) ) {
        return;
    }

    $promo_ids = sl_cwoo_get_home_promo_product_ids();
    if ( empty( $promo_ids ) ) {
        return;
    }

    $query->set( 'post__in', $promo_ids );
    $query->set( 'orderby', 'post__in' );
    $query->set( 'meta_key', '' );
    $query->set( 'meta_value', '' );
    $query->set( 'meta_compare', '' );
    $query->set( 'tax_query', sl_cwoo_strip_product_cat_tax_query( $query->get( 'tax_query' ) ) );
}
add_action( 'pre_get_posts', 'sl_cwoo_force_home_promo_query', 80 );

function sl_cwoo_merge_campaign_products_into_grogin_sale_queries( $query ) {
    if ( is_admin() || ! $query instanceof WP_Query ) {
        return;
    }

    $post_type = $query->get( 'post_type' );
    $post_in   = $query->get( 'post__in' );

    if ( 'product' !== $post_type || ! is_array( $post_in ) ) {
        return;
    }

    $native_sale_ids = sl_cwoo_get_native_sale_product_ids();
    if ( empty( $native_sale_ids ) ) {
        return;
    }

    $current_ids = wp_parse_id_list( $post_in );
    sort( $current_ids );
    sort( $native_sale_ids );

    if ( $current_ids !== $native_sale_ids ) {
        return;
    }

    $query->set( 'post__in', sl_cwoo_include_campaign_products_in_sale_ids( $post_in ) );
}
add_action( 'pre_get_posts', 'sl_cwoo_merge_campaign_products_into_grogin_sale_queries', 50 );

function sl_cwoo_clear_sale_cache_on_campaign_save() {
    delete_transient( 'wc_products_onsale' );
}
add_action( 'save_post_sl_campagne_woo', 'sl_cwoo_clear_sale_cache_on_campaign_save', 20 );
add_action( 'save_post_sl_bon_plan', 'sl_cwoo_clear_sale_cache_on_campaign_save', 20 );
add_action( 'save_post_product', 'sl_cwoo_clear_sale_cache_on_campaign_save', 20 );

function sl_cwoo_get_home_promo_settings() {
    $defaults = [
        'bp_fallback_enabled' => 'yes',
        'bp_limit'            => 12,
        'bp_orderby'          => 'date',
        'bp_agences'          => [],
        'bp_categories'       => [],
    ];

    $settings = get_option( 'sl_cwoo_home_promo_settings', [] );
    if ( ! is_array( $settings ) ) {
        $settings = [];
    }

    return array_merge( $defaults, $settings );
}

function sl_cwoo_get_bon_plan_fallback_product_ids() {
    $settings = sl_cwoo_get_home_promo_settings();
    if ( 'yes' !== ( $settings['bp_fallback_enabled'] ?? 'yes' ) ) {
        return [];
    }

    if ( ! post_type_exists( 'sl_bon_plan' ) ) {
        return [];
    }

    $today = current_time( 'Y-m-d' );
    $limit = max( 1, min( 48, (int) ( $settings['bp_limit'] ?? 12 ) ) );

    $args = [
        'post_type'      => 'sl_bon_plan',
        'post_status'    => 'publish',
        'posts_per_page' => $limit,
        'fields'         => 'ids',
        'meta_query'     => [
            'relation' => 'OR',
            [
                'key'     => '_sl_bp_date_fin',
                'value'   => $today,
                'compare' => '>=',
                'type'    => 'DATE',
            ],
            [
                'key'     => '_sl_bp_date_fin',
                'value'   => '',
                'compare' => '=',
            ],
            [
                'key'     => '_sl_bp_date_fin',
                'compare' => 'NOT EXISTS',
            ],
        ],
    ];

    $orderby = $settings['bp_orderby'] ?? 'date';
    if ( 'reduction' === $orderby ) {
        $args['meta_key'] = '_sl_bp_reduction_pct';
        $args['orderby']  = 'meta_value_num';
        $args['order']    = 'DESC';
    } elseif ( 'random' === $orderby ) {
        $args['orderby'] = 'rand';
    } else {
        $args['orderby'] = 'date';
        $args['order']   = 'DESC';
    }

    $tax_query = [];
    $agences = array_filter( array_map( 'absint', (array) ( $settings['bp_agences'] ?? [] ) ) );
    if ( ! empty( $agences ) ) {
        $tax_query[] = [
            'taxonomy' => 'sl_agence_promo',
            'field'    => 'term_id',
            'terms'    => $agences,
        ];
    }

    $categories = array_filter( array_map( 'absint', (array) ( $settings['bp_categories'] ?? [] ) ) );
    if ( ! empty( $categories ) ) {
        $tax_query[] = [
            'taxonomy' => 'sl_categorie_promo',
            'field'    => 'term_id',
            'terms'    => $categories,
        ];
    }

    if ( ! empty( $tax_query ) ) {
        $args['tax_query'] = $tax_query;
    }

    $bon_plan_ids = get_posts( $args );
    if ( empty( $bon_plan_ids ) ) {
        return [];
    }

    $product_ids = [];
    foreach ( $bon_plan_ids as $bon_plan_id ) {
        $product_id = sl_cwoo_sync_bon_plan_to_product( (int) $bon_plan_id );
        if ( $product_id ) {
            $product_ids[] = $product_id;
        }
    }

    return wp_parse_id_list( $product_ids );
}

function sl_cwoo_sync_bon_plan_to_product( $bon_plan_id ) {
    if ( 'sl_bon_plan' !== get_post_type( $bon_plan_id ) || 'publish' !== get_post_status( $bon_plan_id ) ) {
        return 0;
    }

    $prix_av = (float) get_post_meta( $bon_plan_id, '_sl_bp_prix_avant', true );
    $prix_ap = (float) get_post_meta( $bon_plan_id, '_sl_bp_prix_apres', true );
    if ( $prix_av <= 0 || $prix_ap <= 0 || $prix_ap >= $prix_av ) {
        return 0;
    }

    $existing = get_posts([
        'post_type'      => 'product',
        'post_status'    => [ 'publish', 'draft', 'private' ],
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_key'       => '_sl_bp_source_id',
        'meta_value'     => $bon_plan_id,
    ]);

    $post_data = [
        'post_title'  => get_the_title( $bon_plan_id ),
        'post_type'   => 'product',
        'post_status' => 'publish',
        'post_author' => (int) get_post_field( 'post_author', $bon_plan_id ),
    ];

    if ( ! empty( $existing ) ) {
        $product_id = (int) $existing[0];
        $post_data['ID'] = $product_id;
        wp_update_post( $post_data );
    } else {
        $product_id = wp_insert_post( $post_data );
    }

    if ( ! $product_id || is_wp_error( $product_id ) ) {
        return 0;
    }

    update_post_meta( $product_id, '_sl_bp_source_id', $bon_plan_id );
    update_post_meta( $product_id, '_regular_price', wc_format_decimal( $prix_av ) );
    update_post_meta( $product_id, '_sale_price', wc_format_decimal( $prix_ap ) );
    update_post_meta( $product_id, '_price', wc_format_decimal( $prix_ap ) );
    update_post_meta( $product_id, '_stock_status', 'instock' );
    update_post_meta( $product_id, '_manage_stock', 'no' );
    update_post_meta( $product_id, '_virtual', 'yes' );
    update_post_meta( $product_id, '_sold_individually', 'yes' );
    update_post_meta( $product_id, '_sl_bp_fallback_product', 'yes' );

    $thumb_id = get_post_thumbnail_id( $bon_plan_id );
    if ( $thumb_id ) {
        set_post_thumbnail( $product_id, $thumb_id );
    }

    $fallback_term = term_exists( 'Bons plans mixtes agences', 'product_cat' );
    if ( ! $fallback_term ) {
        $fallback_term = wp_insert_term( 'Bons plans mixtes agences', 'product_cat' );
    }

    if ( ! is_wp_error( $fallback_term ) && ! empty( $fallback_term['term_id'] ) ) {
        wp_set_object_terms( $product_id, [ (int) $fallback_term['term_id'] ], 'product_cat', false );
    }

    return $product_id;
}

add_action( 'admin_menu', 'sl_cwoo_add_home_promo_settings_page', 45 );
function sl_cwoo_add_home_promo_settings_page() {
    add_submenu_page(
        'edit.php?post_type=sl_bon_plan',
        'Promotions Accueil',
        'Promotions Accueil',
        'manage_sl_bon_plan_terms',
        'sl-promotions-accueil',
        'sl_cwoo_render_home_promo_settings_page'
    );
}

function sl_cwoo_render_home_promo_settings_page() {
    if ( ! current_user_can( 'manage_sl_bon_plan_terms' ) ) {
        wp_die( 'Accès refusé.' );
    }

    if ( isset( $_POST['sl_cwoo_home_promo_nonce'] ) && wp_verify_nonce( $_POST['sl_cwoo_home_promo_nonce'], 'sl_cwoo_home_promo_save' ) ) {
        $settings = [
            'bp_fallback_enabled' => isset( $_POST['bp_fallback_enabled'] ) ? 'yes' : 'no',
            'bp_limit'            => max( 1, min( 48, absint( $_POST['bp_limit'] ?? 12 ) ) ),
            'bp_orderby'          => sanitize_key( $_POST['bp_orderby'] ?? 'date' ),
            'bp_agences'          => array_map( 'absint', (array) ( $_POST['bp_agences'] ?? [] ) ),
            'bp_categories'       => array_map( 'absint', (array) ( $_POST['bp_categories'] ?? [] ) ),
        ];

        if ( ! in_array( $settings['bp_orderby'], [ 'date', 'reduction', 'random' ], true ) ) {
            $settings['bp_orderby'] = 'date';
        }

        update_option( 'sl_cwoo_home_promo_settings', $settings );
        delete_transient( 'wc_products_onsale' );
        echo '<div class="notice notice-success"><p>Réglages enregistrés.</p></div>';
    }

    $settings   = sl_cwoo_get_home_promo_settings();
    $agences    = get_terms([ 'taxonomy' => 'sl_agence_promo', 'hide_empty' => false, 'orderby' => 'name' ]);
    $categories = get_terms([ 'taxonomy' => 'sl_categorie_promo', 'hide_empty' => false, 'orderby' => 'name' ]);
    ?>
    <div class="wrap">
        <h1>Promotions Accueil</h1>
        <p>Quand aucune campagne active ne contient de produits, cette règle affiche automatiquement des bons plans mixtes des agences dans la zone promotions.</p>

        <form method="post">
            <?php wp_nonce_field( 'sl_cwoo_home_promo_save', 'sl_cwoo_home_promo_nonce' ); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Fallback bons plans</th>
                    <td>
                        <label>
                            <input type="checkbox" name="bp_fallback_enabled" value="1" <?php checked( $settings['bp_fallback_enabled'], 'yes' ); ?>>
                            Activer les bons plans quand aucune campagne active n'est disponible
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="bp_limit">Nombre maximum</label></th>
                    <td><input type="number" id="bp_limit" name="bp_limit" min="1" max="48" value="<?php echo esc_attr( $settings['bp_limit'] ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="bp_orderby">Tri</label></th>
                    <td>
                        <select id="bp_orderby" name="bp_orderby">
                            <option value="date" <?php selected( $settings['bp_orderby'], 'date' ); ?>>Plus récents</option>
                            <option value="reduction" <?php selected( $settings['bp_orderby'], 'reduction' ); ?>>Meilleure réduction</option>
                            <option value="random" <?php selected( $settings['bp_orderby'], 'random' ); ?>>Aléatoire</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Agences autorisées</th>
                    <td>
                        <p class="description">Laisser vide pour accepter toutes les agences.</p>
                        <?php if ( ! is_wp_error( $agences ) ) : foreach ( $agences as $agence ) : ?>
                            <label style="display:inline-block; min-width:180px; margin:0 12px 8px 0;">
                                <input type="checkbox" name="bp_agences[]" value="<?php echo esc_attr( $agence->term_id ); ?>" <?php checked( in_array( (int) $agence->term_id, (array) $settings['bp_agences'], true ) ); ?>>
                                <?php echo esc_html( $agence->name ); ?>
                            </label>
                        <?php endforeach; endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Catégories autorisées</th>
                    <td>
                        <p class="description">Laisser vide pour accepter toutes les catégories.</p>
                        <?php if ( ! is_wp_error( $categories ) ) : foreach ( $categories as $category ) : ?>
                            <label style="display:inline-block; min-width:180px; margin:0 12px 8px 0;">
                                <input type="checkbox" name="bp_categories[]" value="<?php echo esc_attr( $category->term_id ); ?>" <?php checked( in_array( (int) $category->term_id, (array) $settings['bp_categories'], true ) ); ?>>
                                <?php echo esc_html( $category->name ); ?>
                            </label>
                        <?php endforeach; endif; ?>
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Enregistrer' ); ?>
        </form>
    </div>
    <?php
}

// 9. Bouton WhatsApp sur les produits WooCommerce
add_action( 'woocommerce_single_product_summary', 'sl_cwoo_add_whatsapp_button', 35 );
function sl_cwoo_add_whatsapp_button() {
    global $product;
    if ( ! $product ) return;

    $phone = '237674152010';
    $text_wa = "Bonjour, je suis intéressé par ce produit :\n";
    $text_wa .= $product->get_name() . "\n";
    $text_wa .= "Prix : " . wc_price($product->get_price()) . "\n";
    $text_wa .= "Lien : " . get_permalink( $product->get_id() );
    
    $whatsapp_url = "https://wa.me/{$phone}?text=" . urlencode(strip_tags($text_wa));

    echo '<a href="' . esc_url($whatsapp_url) . '" target="_blank" rel="noopener" style="display: flex; align-items: center; justify-content: center; gap: 8px; background: #25D366; color: #fff; text-decoration: none; padding: 12px; border-radius: 6px; font-weight: 700; font-size: 15px; margin-top: 15px; margin-bottom: 15px; transition: opacity 0.2s; max-width: 300px;">';
    echo '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>';
    echo 'Commander sur WhatsApp';
    echo '</a>';
}
