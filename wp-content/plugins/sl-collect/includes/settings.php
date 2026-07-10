<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   REGLAGES DROP & COLLECT
   - Interrupteur « Ajouter au panier » (ON = achat en ligne actif,
     OFF = mode WhatsApp seul, comme avant)
   - Telephone de contact affiche aux clients
   ============================================================ */

/** Le bouton « Ajouter au panier » est-il actif ? */
function slc_cart_enabled() {
    return get_option( 'sl_collect_add_to_cart', 'yes' ) === 'yes';
}

/* ------------------------------------------------------------
   Application front : restaure/retire le bouton panier.
   Le theme Grogin (option « Order on WhatsApp ») RETIRE le bouton
   Ajouter au panier et ajoute son propre bouton WhatsApp anglais.
   ------------------------------------------------------------ */
add_action( 'wp', 'slc_apply_cart_toggle', 99 );
function slc_apply_cart_toggle() {
    if ( is_admin() ) return;

    // Toujours : retirer le bouton WhatsApp ANGLAIS du theme (doublon du
    // bouton francais ajoute par sl-agences-elementor).
    remove_action( 'woocommerce_single_product_summary', 'grogin_order_on_whatsapp', 29 );

    if ( slc_cart_enabled() ) {
        // Restaurer le bouton « Ajouter au panier » (retire par le theme)
        if ( false === has_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart' ) ) {
            add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
        }
    } else {
        // Mode WhatsApp seul : pas d'achat en ligne
        remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
    }
}

// Mode OFF robuste : produits non achetables -> WooCommerce masque
// nativement TOUS les boutons panier (fiche, barres sticky, listes) et
// bloque les ajouts directs (?add-to-cart=...). Le bouton WhatsApp reste.
add_filter( 'woocommerce_is_purchasable', 'slc_filter_purchasable', 5 );
function slc_filter_purchasable( $purchasable ) {
    return slc_cart_enabled() ? $purchasable : false;
}

// Barres sticky « ajouter au panier » du theme (desktop + mobile) :
// desactivees quand l'achat en ligne est OFF (elles ne verifient pas
// l'achetabilite du produit).
add_filter( 'theme_mod_grogin_single_sticky_cart', 'slc_filter_sticky_mod', 99 );
add_filter( 'theme_mod_grogin_mobile_single_sticky_cart', 'slc_filter_sticky_mod', 99 );
function slc_filter_sticky_mod( $value ) {
    return slc_cart_enabled() ? $value : false;
}

// Icone panier du header : visible quand l'achat en ligne est actif
add_filter( 'theme_mod_grogin_header_cart', 'slc_filter_header_cart', 99 );
function slc_filter_header_cart( $value ) {
    return slc_cart_enabled() ? true : $value;
}

// Option « Order on WhatsApp » du theme : neutralisee UNIQUEMENT quand
// l'achat en ligne est actif (c'est cette option du theme qui retire le
// bouton panier ; en mode OFF on la laisse faire, et on retire seulement
// son bouton anglais via remove_action ci-dessus).
add_filter( 'theme_mod_grogin_shop_single_orderonwhatsapp', 'slc_filter_theme_whatsapp', 99 );
function slc_filter_theme_whatsapp( $value ) {
    return slc_cart_enabled() ? false : $value;
}

/* ------------------------------------------------------------
   Page de reglages (sous-menu de « Commandes retrait »)
   ------------------------------------------------------------ */
add_action( 'admin_menu', 'slc_settings_menu', 1000 );
function slc_settings_menu() {
    if ( ! slc_is_admin_user() ) return;
    add_submenu_page(
        'sl-collect', 'Réglages Drop & Collect', 'Réglages',
        'read', 'sl-collect-settings', 'slc_settings_page'
    );
}

function slc_settings_page() {
    if ( ! slc_is_admin_user() ) {
        wp_die( 'Accès refusé.' );
    }

    $message = '';
    if ( isset( $_POST['slc_save_settings'] ) ) {
        check_admin_referer( 'slc_save_settings' );
        update_option( 'sl_collect_add_to_cart', empty( $_POST['slc_add_to_cart'] ) ? 'no' : 'yes' );
        $phone = sanitize_text_field( wp_unslash( $_POST['slc_phone'] ?? '' ) );
        if ( $phone !== '' ) update_option( 'sl_collect_phone', $phone );
        $message = 'Réglages enregistrés.';

        // Purger le cache front (Varnish + LiteSpeed) : l'affichage des
        // produits change immediatement pour les visiteurs.
        foreach ( [ home_url( '/' ), wc_get_page_permalink( 'shop' ) ] as $url ) {
            if ( ! $url ) continue;
            wp_remote_request( set_url_scheme( $url, 'http' ), [ 'method' => 'PURGE', 'timeout' => 3, 'sslverify' => false, 'blocking' => false ] );
        }
        if ( has_action( 'litespeed_purge_all' ) ) do_action( 'litespeed_purge_all' );
    }

    $cart_on = slc_cart_enabled();
    $phone   = slc_contact_phone();
    ?>
    <div class="wrap">
        <h1>⚙️ Réglages Drop &amp; Collect</h1>

        <?php if ( $message ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
        <?php endif; ?>

        <form method="post" style="max-width:660px;">
            <?php wp_nonce_field( 'slc_save_settings' ); ?>

            <div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:20px 24px;margin:18px 0;">
                <h2 style="margin-top:0;">🛒 Achat en ligne</h2>
                <label style="display:flex;align-items:flex-start;gap:10px;font-size:14px;">
                    <input type="checkbox" name="slc_add_to_cart" value="1" <?php checked( $cart_on ); ?> style="margin-top:3px;">
                    <span>
                        <strong>Activer le bouton « Ajouter au panier »</strong><br>
                        <span style="color:#666;">Activé : les clients peuvent commander en ligne et retirer en agence (Drop &amp; Collect).<br>
                        Désactivé : retour au mode vitrine — seul le bouton « Commander sur WhatsApp » est proposé.</span>
                    </span>
                </label>
                <p style="color:#666;font-size:12px;margin:12px 0 0;">
                    L'icône panier du haut de page suit automatiquement ce réglage.
                    Le bouton WhatsApp reste affiché dans les deux modes (un seul, en français).
                </p>
            </div>

            <div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:20px 24px;margin:18px 0;">
                <h2 style="margin-top:0;">📞 Téléphone de contact</h2>
                <p style="color:#666;margin-top:0;">Affiché aux clients pour la confirmation téléphonique des commandes (page de confirmation, emails).</p>
                <input type="text" name="slc_phone" value="<?php echo esc_attr( $phone ); ?>" class="regular-text" placeholder="+237 6XX XXX XXX">
            </div>

            <p><button type="submit" name="slc_save_settings" class="button button-primary button-hero">Enregistrer les réglages</button></p>
        </form>
    </div>
    <?php
}
