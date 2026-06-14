<?php
/**
 * Custom Post Type : sl_bon_plan
 * Taxonomies : sl_categorie_promo, sl_agence_promo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
 *  1. ENREGISTREMENT DU CPT
 * ============================================================ */
add_action( 'init', 'sl_bp_register_cpt', 5 );
function sl_bp_register_cpt() {
    register_post_type( 'sl_bon_plan', [
        'labels' => [
            'name'          => __( 'Bons Plans', 'sl-agences' ),
            'singular_name' => __( 'Bon Plan', 'sl-agences' ),
            'add_new_item'  => __( 'Ajouter un bon plan', 'sl-agences' ),
            'edit_item'     => __( 'Modifier le bon plan', 'sl-agences' ),
            'not_found'     => __( 'Aucun bon plan trouvé', 'sl-agences' ),
            'menu_name'     => __( 'Bons Plans', 'sl-agences' ),
            'all_items'     => __( 'Toutes les offres', 'sl-agences' ),
        ],
        'public'          => true,
        'publicly_queryable' => true,
        'show_ui'         => true,
        'show_in_menu'    => true,
        'menu_icon'       => 'dashicons-tickets-alt',
        'menu_position'   => 25,
        'show_in_rest'    => false,
        'supports'        => [ 'title', 'thumbnail', 'author' ],
        'capability_type' => [ 'sl_bon_plan', 'sl_bon_plans' ],
        'map_meta_cap'    => true,
        'has_archive'     => false,
        'rewrite'         => [ 'slug' => 'bon-plan', 'with_front' => false ],
        'query_var'       => true,
    ] );
}

/* ============================================================
 *  2. TAXONOMIES
 * ============================================================ */
add_action( 'init', 'sl_bp_register_taxonomies', 5 );
function sl_bp_register_taxonomies() {

    // Catégories de promotions
    register_taxonomy( 'sl_categorie_promo', 'sl_bon_plan', [
        'labels'      => [
            'name'          => __( 'Catégories', 'sl-agences' ),
            'singular_name' => __( 'Catégorie', 'sl-agences' ),
            'menu_name'     => __( 'Catégories', 'sl-agences' ),
        ],
        'public'            => false,
        'show_ui'           => true,
        'show_in_menu'      => true,
        'show_admin_column' => true,
        'hierarchical'      => true,
        'capabilities'      => [
            'manage_terms' => 'manage_sl_bon_plan_terms',
            'edit_terms'   => 'manage_sl_bon_plan_terms',
            'delete_terms' => 'manage_sl_bon_plan_terms',
            'assign_terms' => 'edit_sl_bon_plans',
        ],
        'rewrite'           => false,
        'query_var'         => false,
    ] );

    // Agences (assignées automatiquement, pas visible dans le menu standard)
    register_taxonomy( 'sl_agence_promo', 'sl_bon_plan', [
        'labels'      => [
            'name'          => __( 'Agences', 'sl-agences' ),
            'singular_name' => __( 'Agence', 'sl-agences' ),
            'menu_name'     => __( 'Agences', 'sl-agences' ),
        ],
        'public'            => false,
        'show_ui'           => true,
        'show_in_menu'      => true,
        'show_admin_column' => true,
        'hierarchical'      => true,
        'capabilities'      => [
            'manage_terms' => 'manage_sl_bon_plan_terms',
            'edit_terms'   => 'manage_sl_bon_plan_terms',
            'delete_terms' => 'manage_sl_bon_plan_terms',
            'assign_terms' => 'edit_sl_bon_plans',
        ],
        'rewrite'           => false,
        'query_var'         => false,
    ] );
}

/* ============================================================
 *  3. INSÉRER LES TERMES PAR DÉFAUT (une seule fois)
 * ============================================================ */
add_action( 'init', 'sl_bp_insert_default_terms', 10 );
function sl_bp_insert_default_terms() {
    if ( get_option( 'sl_bp_terms_v1' ) ) return;

    // Catégories officielles
    $categories = [
        'Fruits & Légumes',
        'Viandes',
        'Charcuterie',
        'Produits laitiers',
        'Boulangerie',
        'Céréales',
        'Légumineuses',
        'Huiles',
        'Épices',
        'Conserves',
        'Farines',
        'Sucreries',
        'Café & Thé',
        'Sodas & Jus',
        'Eaux',
        'Alcools',
        'Surgelés',
        'Snacks',
        'Bébé',
        'Hygiène',
        'Capillaire',
        'Cosmétiques',
        'Entretien',
        'Papeterie',
        'Électroménager',
        'Textile',
        'Cuisine',
        'Décoration',
    ];
    foreach ( $categories as $cat ) {
        if ( ! term_exists( $cat, 'sl_categorie_promo' ) ) {
            wp_insert_term( $cat, 'sl_categorie_promo' );
        }
    }

    // Agences officielles Santa Lucia
    $agences = [
        'Nkondengui',
        'Ngousso',
        'Nkoabang',
        'Mokolo',
        'Mélen',
        'Essos',
        'Ahala',
        'Odza',
        'Mvan',
        'Simbock',
        'Cité cicam',
        'Akwa-nord',
        'Bonaberi',
        'Bonamoussadi',
        'Akwa',
        'Nkolbong',
        'BERCY',
        'Cité des Palmiers',
    ];
    foreach ( $agences as $agence ) {
        if ( ! term_exists( $agence, 'sl_agence_promo' ) ) {
            wp_insert_term( $agence, 'sl_agence_promo' );
        }
    }

    update_option( 'sl_bp_terms_v1', 1 );
}

/* ============================================================
 *  3C. SYNCHRONISER LES CATÉGORIES OFFICIELLES
 * ============================================================ */
add_action( 'init', 'sl_bp_sync_official_categories', 21 );
function sl_bp_sync_official_categories() {
    if ( get_option( 'sl_bp_categories_officielles_v1' ) ) return;

    $official_categories = [
        'Fruits & Légumes',
        'Viandes',
        'Charcuterie',
        'Produits laitiers',
        'Boulangerie',
        'Céréales',
        'Légumineuses',
        'Huiles',
        'Épices',
        'Conserves',
        'Farines',
        'Sucreries',
        'Café & Thé',
        'Sodas & Jus',
        'Eaux',
        'Alcools',
        'Surgelés',
        'Snacks',
        'Bébé',
        'Hygiène',
        'Capillaire',
        'Cosmétiques',
        'Entretien',
        'Papeterie',
        'Électroménager',
        'Textile',
        'Cuisine',
        'Décoration',
    ];

    foreach ( $official_categories as $category ) {
        if ( ! term_exists( $category, 'sl_categorie_promo' ) ) {
            wp_insert_term( $category, 'sl_categorie_promo' );
        }
    }

    $terms = get_terms( [
        'taxonomy'   => 'sl_categorie_promo',
        'hide_empty' => false,
    ] );
    if ( ! is_wp_error( $terms ) ) {
        foreach ( $terms as $term ) {
            if ( ! in_array( $term->name, $official_categories, true ) ) {
                wp_delete_term( $term->term_id, 'sl_categorie_promo' );
            }
        }
    }

    update_option( 'sl_bp_categories_officielles_v1', 1 );
}

/* ============================================================
 *  3B. SYNCHRONISER LES AGENCES OFFICIELLES
 * ============================================================ */
add_action( 'init', 'sl_bp_sync_official_agences', 20 );
function sl_bp_sync_official_agences() {
    if ( get_option( 'sl_bp_agences_officielles_v2' ) ) return;

    $official_agences = [
        'Nkondengui',
        'Ngousso',
        'Nkoabang',
        'Mokolo',
        'Mélen',
        'Essos',
        'Ahala',
        'Odza',
        'Mvan',
        'Simbock',
        'Cité cicam',
        'Akwa-nord',
        'Bonaberi',
        'Bonamoussadi',
        'Akwa',
        'Nkolbong',
        'BERCY',
        'Cité des Palmiers',
    ];

    $normalize_agence = function( $value ) {
        $value = remove_accents( (string) $value );
        $value = strtolower( $value );
        $value = preg_replace( '/[^a-z0-9]+/', '', $value );
        return $value;
    };

    $official_by_key = [];
    foreach ( $official_agences as $agence ) {
        $official_by_key[ $normalize_agence( $agence ) ] = $agence;
    }
    $official_by_key['nkondegui'] = 'Nkondengui';
    $official_by_key['akwanord']  = 'Akwa-nord';
    $official_by_key['citecicam'] = 'Cité cicam';
    $official_by_key['citedespalmiers'] = 'Cité des Palmiers';

    $canonical_terms = [];
    foreach ( $official_agences as $agence ) {
        $term = get_term_by( 'slug', sanitize_title( $agence ), 'sl_agence_promo' );
        if ( ! $term || is_wp_error( $term ) ) {
            $term = get_term_by( 'name', $agence, 'sl_agence_promo' );
        }
        if ( ! $term || is_wp_error( $term ) ) {
            $created = wp_insert_term( $agence, 'sl_agence_promo' );
            if ( ! is_wp_error( $created ) && ! empty( $created['term_id'] ) ) {
                $term = get_term( (int) $created['term_id'], 'sl_agence_promo' );
            }
        }
        if ( $term && ! is_wp_error( $term ) ) {
            if ( $term->name !== $agence ) {
                wp_update_term( (int) $term->term_id, 'sl_agence_promo', [ 'name' => $agence ] );
                $term = get_term( (int) $term->term_id, 'sl_agence_promo' );
            }
            $canonical_terms[ $normalize_agence( $agence ) ] = $term;
        }
    }

    $users = get_users( [
        'meta_key'   => 'sl_agence_assignee',
        'fields'     => 'ID',
    ] );
    foreach ( $users as $user_id ) {
        $agence_user = get_user_meta( $user_id, 'sl_agence_assignee', true );
        $key = $normalize_agence( $agence_user );
        if ( isset( $official_by_key[ $key ] ) && $agence_user !== $official_by_key[ $key ] ) {
            update_user_meta( $user_id, 'sl_agence_assignee', $official_by_key[ $key ] );
        }
    }

    $terms = get_terms( [
        'taxonomy'   => 'sl_agence_promo',
        'hide_empty' => false,
    ] );
    if ( ! is_wp_error( $terms ) ) {
        foreach ( $terms as $term ) {
            $key = $normalize_agence( $term->name );
            $canonical = $canonical_terms[ $key ] ?? null;

            if ( $canonical && (int) $canonical->term_id !== (int) $term->term_id ) {
                $object_ids = get_objects_in_term( (int) $term->term_id, 'sl_agence_promo' );
                if ( ! is_wp_error( $object_ids ) ) {
                    foreach ( $object_ids as $object_id ) {
                        wp_set_object_terms( (int) $object_id, [ (int) $canonical->term_id ], 'sl_agence_promo', true );
                    }
                }
                wp_delete_term( (int) $term->term_id, 'sl_agence_promo' );
                continue;
            }

            if ( ! $canonical ) {
                wp_delete_term( (int) $term->term_id, 'sl_agence_promo' );
            }
        }
    }

    update_option( 'sl_bp_agences_officielles_v2', 1 );
}

/* ============================================================
 *  4. AUTO-ASSIGNER L'AGENCE À LA PUBLICATION
 * ============================================================ */
add_action( 'save_post_sl_bon_plan', 'sl_bp_auto_assign_agence', 10, 2 );
function sl_bp_auto_assign_agence( $post_id, $post ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( wp_is_post_revision( $post_id ) ) return;

    // Les administrateurs et gestionnaires globaux choisissent eux-mêmes l'agence.
    if ( current_user_can( 'manage_options' ) || current_user_can( 'manage_sl_bon_plan_terms' ) ) {
        return;
    }

    $author_id  = (int) $post->post_author;
    $agence_nom = get_user_meta( $author_id, 'sl_agence_assignee', true );
    if ( ! $agence_nom ) return;

    $term = get_term_by( 'name', $agence_nom, 'sl_agence_promo' );
    if ( $term ) {
        wp_set_object_terms( $post_id, [ $term->term_id ], 'sl_agence_promo', false );
    }
}

/* ============================================================
 *  5. EXCLURE LES OFFRES EXPIRÉES DES REQUÊTES PUBLIQUES
 * ============================================================ */
add_action( 'pre_get_posts', 'sl_bp_exclude_expired' );
function sl_bp_exclude_expired( $query ) {
    if ( is_admin() ) return;
    if ( $query->get( 'post_type' ) !== 'sl_bon_plan' ) return;

    $today = current_time( 'Y-m-d' );
    $existing = $query->get( 'meta_query' ) ?: [];
    $existing[] = [
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
    ];
    $query->set( 'meta_query', $existing );
}

add_action( 'added_post_meta', 'sl_bp_clear_offer_cache_after_meta_change', 10, 4 );
add_action( 'updated_post_meta', 'sl_bp_clear_offer_cache_after_meta_change', 10, 4 );
add_action( 'deleted_post_meta', 'sl_bp_clear_offer_cache_after_meta_change', 10, 4 );
function sl_bp_clear_offer_cache_after_meta_change( $meta_id, $post_id, $meta_key, $meta_value ) {
    if ( 'sl_bon_plan' !== get_post_type( $post_id ) ) {
        return;
    }

    $watched_keys = [
        '_sl_bp_date_fin',
        '_sl_bp_prix_avant',
        '_sl_bp_prix_apres',
        '_sl_bp_reduction_pct',
        '_sl_bp_stock_actif',
        '_sl_bp_stock_qty',
    ];

    if ( ! in_array( $meta_key, $watched_keys, true ) ) {
        return;
    }

    clean_post_cache( $post_id );
    delete_transient( 'wc_products_onsale' );
    sl_bp_purge_front_cache( $post_id );
}

/* ============================================================
 *  5B. PURGER LE CACHE VARNISH DE LA PAGE BONS PLANS
 *  WordPress vide son cache objet mais PAS Varnish : sans cette
 *  purge, modifier la date (ou tout champ) d'un bon plan ne se
 *  voit pas côté visiteur (page /bon-plans/ servie depuis le cache).
 * ============================================================ */
function sl_bp_purge_front_cache( $post_id = 0 ) {
    $urls = [
        home_url( '/bon-plans/' ),
        home_url( '/' ),
    ];
    if ( $post_id ) {
        $permalink = get_permalink( $post_id );
        if ( $permalink ) {
            $urls[] = $permalink;
        }
    }

    $urls = array_unique( array_filter( $urls ) );

    foreach ( $urls as $url ) {
        // 1) Varnish (PURGE HTTP)
        wp_remote_request( $url, [
            'method'    => 'PURGE',
            'timeout'   => 3,
            'blocking'  => true,
            'sslverify' => false,
        ] );
        // 2) LiteSpeed Cache (plugin actif) — hook officiel, sans effet si absent.
        //    Sans cette purge, LiteSpeed ressert sa copie périmée de /bon-plans/
        //    même après la purge Varnish (Varnish re-demande au backend, qui sert LiteSpeed).
        do_action( 'litespeed_purge_url', $url );
    }

    // LiteSpeed : purge ciblée par post (le bon plan + la page /bon-plans/)
    if ( $post_id ) {
        do_action( 'litespeed_purge_post', $post_id );
    }
    $bp_page = get_page_by_path( 'bon-plans' );
    if ( $bp_page ) {
        do_action( 'litespeed_purge_post', $bp_page->ID );
    }
}

/* Purger aussi à chaque enregistrement / corbeille / suppression d'un bon plan
   (couvre titre, prix, badge, image, stock — pas seulement les métas surveillées). */
add_action( 'save_post_sl_bon_plan', 'sl_bp_purge_after_save', 99, 1 );
function sl_bp_purge_after_save( $post_id ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( wp_is_post_revision( $post_id ) ) {
        return;
    }
    sl_bp_purge_front_cache( $post_id );
}

add_action( 'trashed_post', 'sl_bp_purge_on_status_change' );
add_action( 'untrashed_post', 'sl_bp_purge_on_status_change' );
add_action( 'deleted_post', 'sl_bp_purge_on_status_change' );
function sl_bp_purge_on_status_change( $post_id ) {
    if ( 'sl_bon_plan' === get_post_type( $post_id ) ) {
        sl_bp_purge_front_cache( $post_id );
    }
}

/* ============================================================
 *  6. FLUSH REWRITE RULES (une seule fois après activation)
 * ============================================================ */
add_action( 'init', 'sl_bp_maybe_flush', 99 );
function sl_bp_maybe_flush() {
    if ( get_option( 'sl_bp_flush_v3' ) ) return;
    flush_rewrite_rules();
    update_option( 'sl_bp_flush_v3', 1 );
}

/* ============================================================
 *  7. ACCORDER LES CAPACITÉS CPT AUX RÔLES PRINCIPAUX
 * ============================================================ */
add_action( 'init', 'sl_bp_grant_admin_caps', 20 );
function sl_bp_grant_admin_caps() {
    $caps = [
        'edit_sl_bon_plan',
        'read_sl_bon_plan',
        'delete_sl_bon_plan',
        'edit_sl_bon_plans',
        'edit_others_sl_bon_plans',
        'publish_sl_bon_plans',
        'read_private_sl_bon_plans',
        'delete_sl_bon_plans',
        'delete_private_sl_bon_plans',
        'delete_published_sl_bon_plans',
        'delete_others_sl_bon_plans',
        'edit_private_sl_bon_plans',
        'edit_published_sl_bon_plans',
        'manage_sl_bon_plan_terms',
    ];

    foreach ( [ 'administrator', 'editor' ] as $role_name ) {
        $role = get_role( $role_name );
        if ( ! $role ) {
            continue;
        }

        foreach ( $caps as $cap ) {
            if ( ! $role->has_cap( $cap ) ) {
                $role->add_cap( $cap );
            }
        }
    }
}
