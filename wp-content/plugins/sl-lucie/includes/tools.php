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
            break;
        default:
            return wp_json_encode( [ 'erreur' => 'Outil inconnu.' ] );
    }
    $d = sl_lucie_trim( $d );
    $json = wp_json_encode( $d, JSON_UNESCAPED_UNICODE );
    if ( strlen( $json ) > 30000 ) $json = substr( $json, 0, 30000 );
    return $json;
}
