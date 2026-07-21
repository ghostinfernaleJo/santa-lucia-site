<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   COMMANDE EN LIGNE DES REPAS FAST FOOD (retrait en agence)
   Reutilise entierement l'infrastructure Bons Plans (panier,
   verrou 1 agence/panier, checkout, code de retrait) :
   voir sl-agences-elementor/includes/bon-plan-cart.php.
   Un repas peut etre servi par plusieurs agences a des prix et
   des jours differents -> il faut UN produit WooCommerce par
   couple (repas, agence), contrairement aux Bons Plans (1 BP =
   1 agence = 1 produit).
   ============================================================ */

/** Terme product_cat partage pour tous les produits Fast Food (cree si absent). */
function sl_ff_product_category_id() {
    static $id = null;
    if ( $id !== null ) return $id;
    $term = get_term_by( 'name', 'Fast Food (retrait agence)', 'product_cat' );
    if ( $term && ! is_wp_error( $term ) ) {
        return $id = (int) $term->term_id;
    }
    $created = wp_insert_term( 'Fast Food (retrait agence)', 'product_cat' );
    return $id = ( ! is_wp_error( $created ) ) ? (int) $created['term_id'] : 0;
}

/**
 * Produit WooCommerce lie a un repas POUR UNE AGENCE precise.
 * Retourne 0 si le repas n'a pas de prix pour cette agence, ou n'est pas
 * au menu aujourd'hui pour elle (repas juste "consultable", pas vendable).
 * Cree le produit au premier appel, sinon rafraichit prix/stock SEULEMENT
 * si un ecart est detecte (meme precaution que la synchro Bons Plans).
 */
function sl_ff_product_id_for( $repas_id, $agence ) {
    if ( ! function_exists( 'wc_get_product' ) ) return 0;

    static $cache = [];
    $repas_id = (int) $repas_id;
    $agence   = sanitize_title( $agence );
    if ( $repas_id <= 0 || $agence === '' ) return 0;
    $ck = $repas_id . '|' . $agence;
    if ( isset( $cache[ $ck ] ) ) return $cache[ $ck ];

    $promo      = sl_ff_get_promo_info( $repas_id, $agence );
    $prix_vente = ( $promo['est_promo'] && $promo['prix_promo'] > 0 ) ? $promo['prix_promo'] : $promo['prix'];
    $today_jour = sl_ff_today_jour();
    $dispo      = sl_ff_is_repas_available_for_agence( $repas_id, $agence, $today_jour );

    $ids = get_posts( [
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_query'     => [
            [ 'key' => '_sl_ff_source_id',     'value' => $repas_id ],
            [ 'key' => '_sl_ff_source_agence', 'value' => $agence ],
        ],
    ] );
    $pid = $ids ? (int) $ids[0] : 0;

    if ( ! $dispo || $prix_vente <= 0 ) {
        // Pas vendable en ligne aujourd'hui : si un produit existe deja
        // (dispo/promo d'un jour precedent), le passer hors-stock sans le
        // supprimer (une commande passee hier doit rester valide).
        if ( $pid ) {
            $p = wc_get_product( $pid );
            if ( $p && $p->get_stock_status() !== 'outofstock' ) {
                $p->set_stock_status( 'outofstock' );
                $p->save();
            }
        }
        return $cache[ $ck ] = 0;
    }

    $repas = get_post( $repas_id );
    if ( ! $repas || $repas->post_type !== 'sl_repas' || $repas->post_status !== 'publish' ) {
        return $cache[ $ck ] = 0;
    }

    if ( ! $pid ) {
        $product = new WC_Product_Simple();
        $product->set_name( $repas->post_title );
        $product->set_status( 'publish' );
        $product->set_catalog_visibility( 'hidden' ); // pas dans la boutique/recherche Woo, seulement via le menu FF
        $product->set_virtual( true );
        $product->set_sold_individually( false );
        $product->set_manage_stock( false );
        $product->set_sku( 'ffagence-' . $agence . '-' . $repas_id );
        $thumb_id = get_post_thumbnail_id( $repas_id );
        if ( $thumb_id ) $product->set_image_id( $thumb_id );
        $pid = $product->save();
        if ( ! $pid || is_wp_error( $pid ) ) return $cache[ $ck ] = 0;
        update_post_meta( $pid, '_sl_ff_source_id', $repas_id );
        update_post_meta( $pid, '_sl_ff_source_agence', $agence );
        $cat_id = sl_ff_product_category_id();
        if ( $cat_id ) wp_set_object_terms( $pid, [ $cat_id ], 'product_cat' );
    }

    $product = wc_get_product( $pid );
    if ( ! $product ) return $cache[ $ck ] = 0;

    $dirty = false;
    if ( (string) $product->get_regular_price( 'edit' ) !== (string) $promo['prix'] ) {
        $product->set_regular_price( (string) $promo['prix'] );
        $dirty = true;
    }
    $sale = ( $promo['est_promo'] && $promo['prix_promo'] > 0 ) ? (string) $promo['prix_promo'] : '';
    if ( (string) $product->get_sale_price( 'edit' ) !== $sale ) {
        $product->set_sale_price( $sale );
        $dirty = true;
    }
    if ( $product->get_stock_status() !== 'instock' ) {
        $product->set_stock_status( 'instock' );
        $dirty = true;
    }
    if ( $product->get_name() !== $repas->post_title ) {
        $product->set_name( $repas->post_title );
        $dirty = true;
    }
    if ( $dirty ) $product->save();

    return $cache[ $ck ] = $pid;
}

/** URL de la fiche "commander ce repas" (partageable, ex: WhatsApp). */
function sl_ff_order_url( $repas_id, $agence ) {
    return add_query_arg(
        [ 'slff_repas' => 1, 'r' => (int) $repas_id, 'a' => sanitize_title( $agence ) ],
        home_url( '/' )
    );
}

/** Lien wa.me pre-rempli pour commander un repas via WhatsApp. */
function sl_ff_whatsapp_url( $titre, $prix_affiche, $agence_nom, $order_url ) {
    $phone = '237674152010'; // meme numero que les Bons Plans (includes/bon-plan-cart.php n'en a pas besoin, defini dans le template single-bon-plan.php)
    $texte = "Bonjour, je suis intéressé par ce repas :\n" . $titre . "\n"
        . "Agence : " . $agence_nom . "\n"
        . "Prix : " . $prix_affiche . "\n"
        . "Commander : " . $order_url;
    return 'https://wa.me/' . $phone . '?text=' . rawurlencode( $texte );
}

/** CSS inline (une fois par page) pour le bouton WhatsApp du menu Fast Food. */
function sl_ff_order_css() {
    static $done = false;
    if ( $done ) return '';
    $done = true;
    return '<style>
    .sl-ff-order-actions{display:flex;gap:6px;margin-top:8px;flex-wrap:wrap;}
    .sl-ff-order-actions .slbp-cart-wrap{flex:1;min-width:120px;margin-top:0;}
    .sl-ff-wa-btn{flex:1;min-width:120px;display:flex;align-items:center;justify-content:center;gap:6px;background:#25D366;color:#fff;text-decoration:none;padding:7px 10px;border-radius:6px;font-weight:600;font-size:12px;line-height:1;transition:.15s;}
    .sl-ff-wa-btn:hover{background:#1ebe57;color:#fff;}
    .sl-ff-wa-btn svg{flex:0 0 auto;width:14px;height:14px;}
    </style>';
}

/**
 * HTML des boutons de commande (panier + WhatsApp) pour un repas/agence,
 * a placer juste apres le prix dans les boucles de rendu du menu.
 * Ne retourne rien si le repas n'est pas vendable (pas de prix / pas dispo).
 */
function sl_ff_order_buttons_html( $repas_id, $agence, $agence_nom, $titre, $promo ) {
    $ff_pid = sl_ff_product_id_for( $repas_id, $agence );
    if ( ! $ff_pid || ! function_exists( 'sl_bp_cart_button_html' ) ) return '';

    $prix_affiche = ( $promo['est_promo'] && $promo['prix_promo'] > 0 )
        ? sl_ff_format_prix( $promo['prix_promo'] )
        : sl_ff_format_prix( $promo['prix'] );

    $order_url = sl_ff_order_url( $repas_id, $agence );
    $wa_url    = sl_ff_whatsapp_url( $titre, $prix_affiche, $agence_nom, $order_url );

    $wa_svg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>';

    return sl_ff_order_css()
        . '<div class="sl-ff-order-actions">'
        . sl_bp_cart_button_html( 0, $ff_pid )
        // esc_attr (pas esc_url) : esc_url() supprime silencieusement les
        // sequences %0a/%0A (defense anti-injection d'en-tetes) meme quand
        // elles font partie d'un texte deja rawurlencode() -> les retours a
        // la ligne du message WhatsApp disparaissaient. $wa_url est notre
        // propre construction (hote wa.me fixe, texte deja encode) : esc_attr
        // suffit et n'altere pas l'encodage.
        . '<a class="sl-ff-wa-btn" href="' . esc_attr( $wa_url ) . '" target="_blank" rel="noopener">' . $wa_svg . 'WhatsApp</a>'
        . '</div>';
}

/* ============================================================
   FICHE "COMMANDER CE REPAS" (page virtuelle, sans rewrite rule)
   URL : /?slff_repas=1&r={repas_id}&a={agence_slug}
   Meme principe que d'autres endpoints virtuels du site (ex.
   /?sl_bp_pdf=1 dans pdf-bons-plans.php) : pas de regle de
   reecriture a maintenir, fonctionne immediatement.
   ============================================================ */
add_action( 'template_redirect', 'sl_ff_maybe_render_order_page', 5 );
function sl_ff_maybe_render_order_page() {
    if ( empty( $_GET['slff_repas'] ) ) return;
    if ( ! function_exists( 'wc_get_product' ) ) return;

    $repas_id = isset( $_GET['r'] ) ? (int) $_GET['r'] : 0;
    $agence   = isset( $_GET['a'] ) ? sanitize_title( wp_unslash( $_GET['a'] ) ) : '';
    $repas    = ( $repas_id > 0 ) ? get_post( $repas_id ) : null;

    if ( ! $repas || $repas->post_type !== 'sl_repas' || $repas->post_status !== 'publish' || $agence === '' ) {
        wp_die( 'Repas introuvable ou lien expiré.', 'Repas introuvable', [ 'response' => 404 ] );
    }

    nocache_headers();
    include SL_FF_PATH . 'templates/single-repas.php';
    exit;
}
