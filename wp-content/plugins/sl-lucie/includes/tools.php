<?php
/**
 * Outils de Lucie (function calling). Lecture seule, donnees publiques.
 * Chaque outil appelle un endpoint EXISTANT de l'API santa-lucia/v1 en interne
 * (rest_do_request, sans requete HTTP externe).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Definitions des outils envoyees a Claude. */
function sl_lucie_tools_defs() {
    return [
        [
            'name' => 'lister_agences',
            'description' => 'Liste toutes les agences Santa Lucia (nom, ville). A appeler quand l\'utilisateur demande les agences, les villes, ou pour connaitre les slugs d\'agence avant un autre outil.',
            'input_schema' => [ 'type' => 'object', 'properties' => new stdClass(), ],
        ],
        [
            'name' => 'menu_du_jour',
            'description' => 'Retourne le menu Fast Food disponible pour une agence un jour donne. A appeler des qu\'on demande le menu, les plats du jour, ce qui est disponible a manger.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'agence' => [ 'type' => 'string', 'description' => 'Slug ou nom de l\'agence (ex: bonamoussadi, akwa-nord).' ],
                    'jour'   => [ 'type' => 'string', 'description' => 'Jour en francais minuscule (lundi..dimanche). Par defaut, aujourd\'hui.' ],
                ],
                'required' => [ 'agence' ],
            ],
        ],
        [
            'name' => 'promotions',
            'description' => 'Retourne les produits en promotion. A appeler des qu\'on demande les promos, reductions, soldes, offres. On peut filtrer par agence et/ou categorie.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'agence'    => [ 'type' => 'string', 'description' => 'Slug ou nom de l\'agence (optionnel).' ],
                    'categorie' => [ 'type' => 'string', 'description' => 'Categorie de produit (optionnel).' ],
                ],
            ],
        ],
        [
            'name' => 'bons_plans',
            'description' => 'Retourne les bons plans / offres en cours. A appeler quand on demande les bons plans, les offres speciales. Filtrable par agence et categorie.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'agence'    => [ 'type' => 'string', 'description' => 'Slug ou nom de l\'agence (optionnel).' ],
                    'categorie' => [ 'type' => 'string', 'description' => 'Categorie (optionnel).' ],
                ],
            ],
        ],
        [
            'name' => 'rechercher_contenu',
            'description' => 'Recherche en direct dans TOUT le contenu publie du site (pages, articles, produits) par mots-cles. A appeler des qu\'on demande une information qui n\'est PAS couverte par les autres outils : services, livraison, a-propos, horaires ou contact d\'une agence, une page precise, recrutement, ou tout sujet general sur Santa Lucia.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'requete' => [ 'type' => 'string', 'description' => 'Mots-cles a rechercher (ex: "livraison", "horaires Akwa", "recrutement").' ],
                ],
                'required' => [ 'requete' ],
            ],
        ],
        [
            'name' => 'infos_produits',
            'description' => 'Recherche des produits de la boutique en direct (nom, prix, disponibilite/stock, categorie, lien). A appeler des qu\'on demande un produit, un prix, la disponibilite d\'un article ou les nouveautes.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'recherche' => [ 'type' => 'string', 'description' => 'Nom ou mot-cle du produit (optionnel).' ],
                    'categorie' => [ 'type' => 'string', 'description' => 'Categorie de produit (optionnel).' ],
                ],
            ],
        ],
    ];
}

/** Helper : appelle un endpoint REST interne en GET et renvoie les donnees. */
function sl_lucie_rest_get( $route, $params = [] ) {
    $req = new WP_REST_Request( 'GET', $route );
    foreach ( (array) $params as $k => $v ) {
        if ( $v !== '' && $v !== null ) $req->set_param( $k, $v );
    }
    $res = rest_do_request( $req );
    if ( is_wp_error( $res ) ) {
        return [ 'erreur' => $res->get_error_message() ];
    }
    return $res->get_data();
}

/** Reduit la taille des donnees renvoyees a Claude (evite de saturer le contexte). */
function sl_lucie_trim( $data, $max = 40 ) {
    if ( is_array( $data ) && isset( $data['items'] ) && is_array( $data['items'] ) ) {
        $data['items'] = array_slice( $data['items'], 0, $max );
    } elseif ( is_array( $data ) && array_is_list( $data ) ) {
        $data = array_slice( $data, 0, $max );
    }
    return $data;
}

/** Recherche generale dans le contenu publie (pages, articles, produits). Donnees LIVE. */
function sl_lucie_tool_search_content( $requete ) {
    $requete = sanitize_text_field( (string) $requete );
    if ( $requete === '' ) return [ 'erreur' => 'Requete vide.' ];
    $types = [ 'page', 'post' ];
    if ( post_type_exists( 'product' ) ) $types[] = 'product';
    $q = new WP_Query( [
        's'              => $requete,
        'post_type'      => $types,
        'post_status'    => 'publish',
        'posts_per_page' => 8,
        'no_found_rows'  => true,
        'ignore_sticky_posts' => true,
    ] );
    $items = [];
    foreach ( $q->posts as $p ) {
        $extrait = has_excerpt( $p ) ? get_the_excerpt( $p ) : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $p->post_content ) ), 45 );
        $items[] = [
            'titre'   => get_the_title( $p ),
            'type'    => $p->post_type,
            'extrait' => trim( wp_strip_all_tags( (string) $extrait ) ),
            'url'     => get_permalink( $p ),
        ];
    }
    wp_reset_postdata();
    return [ 'resultats' => $items ];
}

/** Recherche de produits WooCommerce (nom, prix, stock, categorie). Donnees LIVE. */
function sl_lucie_tool_products( $recherche, $categorie ) {
    if ( ! function_exists( 'wc_get_product' ) || ! post_type_exists( 'product' ) ) {
        return [ 'erreur' => 'La boutique n\'est pas disponible.' ];
    }
    $args = [
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 12,
        'no_found_rows'  => true,
        'ignore_sticky_posts' => true,
    ];
    $recherche = sanitize_text_field( (string) $recherche );
    $categorie = sanitize_text_field( (string) $categorie );
    if ( $recherche !== '' ) $args['s'] = $recherche;
    if ( $categorie !== '' ) {
        $args['tax_query'] = [ [ 'taxonomy' => 'product_cat', 'field' => 'name', 'terms' => $categorie ] ];
    }
    $q = new WP_Query( $args );
    $items = [];
    foreach ( $q->posts as $p ) {
        $prod = wc_get_product( $p->ID );
        if ( ! $prod ) continue;
        $items[] = [
            'nom'        => $prod->get_name(),
            'prix'       => trim( wp_strip_all_tags( wc_price( $prod->get_price() ) ) ),
            'disponible' => $prod->is_in_stock() ? 'oui' : 'non',
            'stock'      => $prod->managing_stock() ? $prod->get_stock_quantity() : null,
            'categories' => wp_get_post_terms( $p->ID, 'product_cat', [ 'fields' => 'names' ] ),
            'url'        => get_permalink( $p->ID ),
        ];
    }
    wp_reset_postdata();
    return [ 'produits' => $items ];
}

/** Execute un outil demande par Claude. Retourne une chaine (JSON) pour le tool_result. */
function sl_lucie_run_tool( $name, $input ) {
    $input = is_array( $input ) ? $input : [];
    // Comptabilise l'outil appele (pour les statistiques)
    if ( ! isset( $GLOBALS['sl_lucie_tools_called'] ) ) $GLOBALS['sl_lucie_tools_called'] = [];
    $GLOBALS['sl_lucie_tools_called'][] = $name;
    switch ( $name ) {
        case 'lister_agences':
            $d = sl_lucie_rest_get( '/santa-lucia/v1/agences' );
            break;
        case 'menu_du_jour':
            $d = sl_lucie_rest_get( '/santa-lucia/v1/fastfood/menu', [
                'agence' => sanitize_text_field( $input['agence'] ?? '' ),
                'jour'   => sanitize_text_field( $input['jour'] ?? '' ),
            ] );
            break;
        case 'promotions':
            $d = sl_lucie_rest_get( '/santa-lucia/v1/promotions', [
                'agence'   => sanitize_text_field( $input['agence'] ?? '' ),
                'category' => sanitize_text_field( $input['categorie'] ?? '' ),
            ] );
            break;
        case 'bons_plans':
            $d = sl_lucie_rest_get( '/santa-lucia/v1/bons-plans', [
                'agence'    => sanitize_text_field( $input['agence'] ?? '' ),
                'categorie' => sanitize_text_field( $input['categorie'] ?? '' ),
            ] );
            // Masque les offres expirees et ajoute le lien vers la page bons plans.
            if ( is_array( $d ) && ! empty( $d['items'] ) && is_array( $d['items'] ) ) {
                $today    = current_time( 'Y-m-d' );
                $bp_page  = home_url( '/bon-plans/' );
                $vivants  = [];
                foreach ( $d['items'] as $it ) {
                    $fin = (string) ( $it['date_fin'] ?? '' );
                    if ( $fin !== '' && $fin < $today ) continue; // expire -> masque
                    $it['lien'] = $bp_page;
                    $vivants[]  = $it;
                }
                $d['items'] = $vivants;
            }
            break;
        case 'rechercher_contenu':
            $d = sl_lucie_tool_search_content( $input['requete'] ?? '' );
            break;
        case 'infos_produits':
            $d = sl_lucie_tool_products( $input['recherche'] ?? '', $input['categorie'] ?? '' );
            break;
        default:
            return wp_json_encode( [ 'erreur' => 'Outil inconnu.' ] );
    }
    $d = sl_lucie_trim( $d );
    $json = wp_json_encode( $d, JSON_UNESCAPED_UNICODE );
    if ( strlen( $json ) > 30000 ) $json = substr( $json, 0, 30000 );
    return $json;
}
