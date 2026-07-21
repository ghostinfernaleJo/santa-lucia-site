<?php
/**
 * Bon Plan -> Ajouter au panier
 * Bouton d'achat AJAX sur les cartes Bons Plans (widget + carrousel).
 * Le bon plan a un produit WooCommerce lié (_sl_bp_source_id) ; on l'ajoute
 * au panier via l'endpoint natif ?wc-ajax=add_to_cart, sans quitter la page.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** Produit WooCommerce publié lié à un bon plan (0 si aucun). */
function sl_bp_product_id_for( $bon_plan_id ) {
    static $cache = [];
    $bon_plan_id = (int) $bon_plan_id;
    if ( $bon_plan_id <= 0 ) return 0;
    if ( isset( $cache[ $bon_plan_id ] ) ) return $cache[ $bon_plan_id ];
    $ids = get_posts( [
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_key'       => '_sl_bp_source_id',
        'meta_value'     => $bon_plan_id,
        'no_found_rows'  => true,
    ] );
    return $cache[ $bon_plan_id ] = ( $ids ? (int) $ids[0] : 0 );
}

/**
 * HTML du bouton « Ajouter au panier » pour un bon plan (via son bon_plan_id)
 * OU directement un product_id. Rien si pas de produit achetable / panier OFF.
 */
function sl_bp_cart_button_html( $bon_plan_id = 0, $product_id = 0 ) {
    if ( ! function_exists( 'wc_get_product' ) ) return '';
    if ( function_exists( 'slc_cart_enabled' ) && ! slc_cart_enabled() ) return '';
    $pid = $product_id ? (int) $product_id : sl_bp_product_id_for( $bon_plan_id );
    if ( ! $pid ) return '';
    $p = wc_get_product( $pid );
    if ( ! $p || ! $p->is_purchasable() || ! $p->is_in_stock() ) return '';

    $svg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>';
    return '<div class="slbp-cart-wrap"><button type="button" class="slbp-add-cart" data-pid="' . esc_attr( $pid )
         . '" data-label="Ajouter au panier" aria-label="Ajouter au panier">' . $svg . '<span>Ajouter au panier</span></button></div>';
}

/* ------------------------------------------------------------
   RESTRICTION : une seule agence par panier (Click & Collect).
   On ne peut pas mélanger des offres de deux agences différentes.
   ------------------------------------------------------------ */

/** Slug de l'agence d'un produit (via son bon plan source). '' si aucune. */
function sl_bp_product_agency( $product_id ) {
    static $cache = [];
    $product_id = (int) $product_id;
    if ( isset( $cache[ $product_id ] ) ) return $cache[ $product_id ];
    $slug = '';
    $bp = (int) get_post_meta( $product_id, '_sl_bp_source_id', true );
    if ( $bp ) {
        $terms = get_the_terms( $bp, 'sl_agence_promo' );
        if ( $terms && ! is_wp_error( $terms ) ) $slug = $terms[0]->slug;
    } else {
        // Produit lie a un repas Fast Food (plugin sl-fastfood, includes/fastfood-cart.php) :
        // l'agence est deja le slug directement, pas besoin de remonter a un post source.
        $ff_agence = get_post_meta( $product_id, '_sl_ff_source_agence', true );
        if ( $ff_agence ) $slug = sanitize_title( $ff_agence );
    }
    return $cache[ $product_id ] = $slug;
}

/** Nom lisible d'une agence à partir de son slug. */
function sl_bp_agency_name( $slug ) {
    if ( ! $slug ) return '';
    $t = get_term_by( 'slug', $slug, 'sl_agence_promo' );
    return ( $t && ! is_wp_error( $t ) ) ? $t->name : $slug;
}

/** Agence actuellement présente dans le panier (slug), '' si panier vide/sans agence. */
function sl_bp_cart_agency() {
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) return '';
    foreach ( WC()->cart->get_cart() as $item ) {
        $ag = sl_bp_product_agency( $item['product_id'] );
        if ( $ag ) return $ag;
    }
    return '';
}

/** Validation native (page produit + tout ajout) : bloque si autre agence. */
add_filter( 'woocommerce_add_to_cart_validation', 'sl_bp_one_agency_validation', 20, 2 );
function sl_bp_one_agency_validation( $passed, $product_id ) {
    if ( ! $passed ) return $passed;
    $new_ag = sl_bp_product_agency( $product_id );
    if ( ! $new_ag ) return $passed;
    $cart_ag = sl_bp_cart_agency();
    if ( $cart_ag && $cart_ag !== $new_ag ) {
        wc_add_notice( sprintf(
            'Votre panier contient déjà des offres de l\'agence « %s ». Une commande ne peut concerner qu\'une seule agence (retrait). Videz votre panier pour commander à « %s ».',
            esc_html( sl_bp_agency_name( $cart_ag ) ), esc_html( sl_bp_agency_name( $new_ag ) )
        ), 'error' );
        return false;
    }
    return $passed;
}

/** AJAX (bouton des cartes) : ajoute au panier avec message d'agence clair. */
add_action( 'wc_ajax_sl_bp_add', 'sl_bp_ajax_add_to_cart' );
function sl_bp_ajax_add_to_cart() {
    $pid = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
    // Quantite optionnelle (par defaut 1, comme avant) : utilisee par la fiche
    // repas Fast Food qui propose un selecteur de quantite. Aucun appelant
    // existant n'envoie ce parametre -> comportement inchange partout ailleurs.
    $qty = isset( $_POST['qty'] ) ? max( 1, intval( $_POST['qty'] ) ) : 1;
    if ( ! $pid || ! function_exists( 'WC' ) || ! WC()->cart ) {
        wp_send_json( [ 'ok' => false, 'msg' => 'Produit introuvable.' ] );
    }
    $new_ag  = sl_bp_product_agency( $pid );
    $cart_ag = sl_bp_cart_agency();
    if ( $new_ag && $cart_ag && $cart_ag !== $new_ag ) {
        wp_send_json( [ 'ok' => false, 'agency' => true, 'msg' => sprintf(
            'Panier déjà à l\'agence « %s ». Une seule agence par commande — videz le panier pour choisir « %s ».',
            sl_bp_agency_name( $cart_ag ), sl_bp_agency_name( $new_ag )
        ) ] );
    }
    $added = WC()->cart->add_to_cart( $pid, $qty );
    if ( ! $added ) {
        $errs = function_exists( 'wc_get_notices' ) ? wc_get_notices( 'error' ) : [];
        if ( function_exists( 'wc_clear_notices' ) ) wc_clear_notices();
        wp_send_json( [ 'ok' => false, 'msg' => $errs ? wp_strip_all_tags( $errs[0]['notice'] ) : 'Impossible d\'ajouter ce produit.' ] );
    }
    if ( function_exists( 'wc_clear_notices' ) ) wc_clear_notices();
    wp_send_json( [
        'ok'        => true,
        'fragments' => apply_filters( 'woocommerce_add_to_cart_fragments', [] ),
        'cart_hash' => WC()->cart->get_cart_hash(),
    ] );
}

/* ------------------------------------------------------------
   CHECKOUT : l'agence de retrait = celle des produits du panier.
   Le champ « Agence de retrait » (module sl-collect) est verrouillé
   sur l'agence du panier ; la commande enregistre toujours celle-ci.
   ------------------------------------------------------------ */
add_filter( 'woocommerce_checkout_fields', 'sl_bp_lock_pickup_agency', 30 );
function sl_bp_lock_pickup_agency( $fields ) {
    if ( empty( $fields['billing']['sl_collect_agence'] ) ) return $fields;
    $cart_ag = sl_bp_cart_agency();
    if ( ! $cart_ag ) return $fields; // panier sans agence -> choix libre
    $name = sl_bp_agency_name( $cart_ag );
    $fields['billing']['sl_collect_agence']['options']           = [ $cart_ag => $name ];
    $fields['billing']['sl_collect_agence']['default']           = $cart_ag;
    $fields['billing']['sl_collect_agence']['description']       = 'Agence imposée par les produits de votre panier. Pour changer d\'agence, videz votre panier.';
    $fields['billing']['sl_collect_agence']['custom_attributes'] = [ 'data-locked' => '1' ];
    return $fields;
}

// Securite : la commande enregistre TOUJOURS l'agence du panier (anti-triche).
add_action( 'woocommerce_checkout_create_order', 'sl_bp_force_order_agency', 20, 1 );
function sl_bp_force_order_agency( $order ) {
    $cart_ag = function_exists( 'sl_bp_cart_agency' ) ? sl_bp_cart_agency() : '';
    if ( $cart_ag ) $order->update_meta_data( '_sl_collect_agence', $cart_ag );
}

// Bloque la validation si l'agence soumise ne correspond pas au panier.
add_action( 'woocommerce_checkout_process', 'sl_bp_validate_pickup_agency', 30 );
function sl_bp_validate_pickup_agency() {
    $cart_ag = sl_bp_cart_agency();
    if ( ! $cart_ag ) return;
    $submitted = isset( $_POST['sl_collect_agence'] ) ? sanitize_title( wp_unslash( $_POST['sl_collect_agence'] ) ) : '';
    if ( $submitted !== '' && $submitted !== $cart_ag ) {
        wc_add_notice( sprintf(
            'L\'agence de retrait doit être « %s » (celle des produits de votre panier).',
            esc_html( sl_bp_agency_name( $cart_ag ) )
        ), 'error' );
    }
}

/** JS + CSS (front, une fois). */
add_action( 'wp_footer', 'sl_bp_cart_assets', 99 );
function sl_bp_cart_assets() {
    if ( is_admin() ) return;
    if ( function_exists( 'slc_cart_enabled' ) && ! slc_cart_enabled() ) return;
    if ( ! class_exists( 'WC_AJAX' ) ) return;
    $endpoint = WC_AJAX::get_endpoint( 'sl_bp_add' );
    ?>
    <style>
    .slbp-cart-wrap{margin-top:7px;position:relative;z-index:4;}
    .slbp-add-cart{display:flex;align-items:center;justify-content:center;gap:6px;width:100%;border:none;border-radius:6px;background:#E91E63;color:#fff;font-weight:500;font-size:12px;line-height:1;padding:7px 10px;cursor:pointer;transition:.15s;font-family:inherit;}
    .slbp-add-cart:hover{background:#c2185b;}
    .slbp-add-cart svg{flex:0 0 auto;width:14px;height:14px;}
    .slbp-add-cart.loading{opacity:.75;pointer-events:none;}
    .slbp-add-cart.done{background:#16a34a;}
    .slbp-add-cart.err{background:#e67e22;}
    #slbp-toast{position:fixed;left:50%;bottom:24px;transform:translateX(-50%) translateY(18px);background:#1d2327;color:#fff;padding:13px 20px;border-radius:11px;font-size:13.5px;font-weight:500;line-height:1.4;max-width:min(460px,92vw);text-align:center;z-index:99999;opacity:0;pointer-events:none;transition:.25s;box-shadow:0 8px 28px rgba(0,0,0,.28);}
    #slbp-toast.show{opacity:1;transform:translateX(-50%) translateY(0);}
    #slbp-toast.warn{background:#b45309;}
    </style>
    <script>
    (function(){
        var ENDPOINT = '<?php echo esc_js( $endpoint ); ?>';
        document.addEventListener('click', function(e){
            var btn = e.target && e.target.closest ? e.target.closest('.slbp-add-cart') : null;
            if ( ! btn ) return;
            e.preventDefault(); e.stopPropagation();
            if ( btn.classList.contains('loading') || btn.classList.contains('done') ) return;
            var pid = btn.getAttribute('data-pid'); if ( ! pid ) return;
            var span = btn.querySelector('span');
            var label = btn.getAttribute('data-label') || 'Ajouter au panier';
            btn.classList.add('loading'); if ( span ) span.textContent = 'Ajout…';
            var body = 'product_id=' + encodeURIComponent(pid);
            fetch(ENDPOINT, { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body })
                .then(function(r){ return r.json(); })
                .then(function(res){
                    btn.classList.remove('loading');
                    if ( ! res || ! res.ok ) {
                        btn.classList.add('err'); if ( span ) span.textContent = 'Impossible';
                        slbpToast( res && res.msg ? res.msg : 'Impossible d\'ajouter au panier.', res && res.agency );
                        setTimeout(reset, 2400); return;
                    }
                    btn.classList.add('done'); if ( span ) span.textContent = '✓ Ajouté';
                    if ( window.jQuery && res.fragments ) { jQuery(document.body).trigger('added_to_cart', [res.fragments, res.cart_hash, jQuery(btn)]); }
                    setTimeout(reset, 1800);
                })
                .catch(function(){ btn.classList.remove('loading'); btn.classList.add('err'); if ( span ) span.textContent = 'Réessayer'; setTimeout(reset,1800); });
            function reset(){ btn.classList.remove('done','err'); if ( span ) span.textContent = label; }
        }, true);

        function slbpToast( msg, warn ){
            var t = document.getElementById('slbp-toast');
            if ( ! t ) { t = document.createElement('div'); t.id = 'slbp-toast'; document.body.appendChild(t); }
            t.textContent = msg; t.className = 'show' + ( warn ? ' warn' : '' );
            clearTimeout( window.__slbpToastT );
            window.__slbpToastT = setTimeout( function(){ t.className = t.className.replace('show',''); }, 5000 );
        }
    })();
    </script>
    <?php
}
