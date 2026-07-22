<?php
/**
 * Recherche unifiee du site.
 *
 * Le champ de recherche du header (theme Grogin) force post_type=product ->
 * la recherche ne trouvait que les produits WooCommerce. La recherche native
 * WordPress, elle, ratait les repas Fast Food (CPT sl_repas non-public) et
 * affichait en double chaque Bon Plan (le CPT + son produit synchronise).
 *
 * Ici on elargit la requete de recherche a produits + bons plans + repas +
 * articles, on retire les produits-proxy synchronises (dedup), et on rend le
 * resultat via un template groupe par section.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * A. Elargit la requete de recherche principale.
 * Definir explicitement post_type outrepasse exclude_from_search : les repas
 * Fast Food (sl_repas, public=false) redeviennent trouvables UNIQUEMENT ici,
 * sans toucher l'enregistrement du CPT.
 */
add_action( 'pre_get_posts', 'sl_search_broaden_query' );
function sl_search_broaden_query( $q ) {
    if ( is_admin() || ! $q->is_main_query() || ! $q->is_search() ) {
        return;
    }

    $q->set( 'post_type', [ 'product', 'sl_bon_plan', 'sl_repas', 'post' ] );
    $q->set( 'post_status', 'publish' );
    $q->set( 'posts_per_page', 120 );

    // Dedup : exclure les produits WooCommerce qui ne sont que des proxys de
    // vente synchronises depuis un Bon Plan (_sl_bp_source_id) ou un repas
    // Fast Food (_sl_ff_source_id). On montre le Bon Plan / le repas d'origine.
    // Les vrais produits, bons plans, repas et articles n'ont pas ces metas
    // -> NOT EXISTS vrai -> conserves.
    $meta = (array) $q->get( 'meta_query' );
    $meta['relation'] = 'AND';
    $meta[] = [ 'key' => '_sl_bp_source_id', 'compare' => 'NOT EXISTS' ];
    $meta[] = [ 'key' => '_sl_ff_source_id', 'compare' => 'NOT EXISTS' ];
    $q->set( 'meta_query', $meta );
}

/**
 * B. Lien cliquable pour un repas Fast Food (il n'a pas de page individuelle).
 * Priorite a la fiche commande d'une agence ou il est dispo+prix aujourd'hui ;
 * repli sur le menu de cette agence, sinon le menu global.
 */
function sl_search_repas_link( $repas_id ) {
    $repas_id = (int) $repas_id;

    if ( function_exists( 'sl_ff_post_agence_slugs' ) && function_exists( 'sl_ff_order_url' ) ) {
        $slugs = sl_ff_post_agence_slugs( $repas_id );
        $today = function_exists( 'sl_ff_today_jour' ) ? sl_ff_today_jour() : '';

        // 1) une agence ou le repas est commandable en ligne aujourd'hui
        foreach ( $slugs as $slug ) {
            $dispo = ! function_exists( 'sl_ff_is_repas_available_for_agence' )
                || sl_ff_is_repas_available_for_agence( $repas_id, $slug, $today );
            $pid = function_exists( 'sl_ff_product_id_for' ) ? sl_ff_product_id_for( $repas_id, $slug ) : 0;
            if ( $dispo && $pid ) {
                return sl_ff_order_url( $repas_id, $slug );
            }
        }

        // 2) repli : menu de la premiere agence rattachee
        if ( ! empty( $slugs ) ) {
            return home_url( '/menu-fast-food/?agence=' . rawurlencode( $slugs[0] ) );
        }
    }

    // 3) dernier repli : menu global
    return home_url( '/menu-fast-food/' );
}

/**
 * Prix affichable d'un repas Fast Food pour la 1ere agence pertinente ('' si aucun).
 */
function sl_search_repas_price_html( $repas_id ) {
    if ( ! function_exists( 'sl_ff_get_promo_info' ) || ! function_exists( 'sl_ff_format_prix' ) ) {
        return '';
    }
    $slugs = function_exists( 'sl_ff_post_agence_slugs' ) ? sl_ff_post_agence_slugs( $repas_id ) : [];
    foreach ( $slugs as $slug ) {
        $promo = sl_ff_get_promo_info( $repas_id, $slug );
        $prix  = ( $promo['est_promo'] && $promo['prix_promo'] > 0 ) ? $promo['prix_promo'] : $promo['prix'];
        if ( $prix > 0 ) {
            return sl_ff_format_prix( $prix );
        }
    }
    return '';
}

/**
 * C. Page de resultats : template dedie sur is_search().
 */
add_filter( 'template_include', 'sl_search_template', 99 );
function sl_search_template( $template ) {
    if ( is_search() && ! is_admin() ) {
        $custom = SL_AGENCES_PATH . 'templates/search-results.php';
        if ( file_exists( $custom ) ) {
            return $custom;
        }
    }
    return $template;
}
