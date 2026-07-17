<?php
/**
 * Points d'acces a la facture de retrait.
 *
 * Un document que le client ne trouve pas n'existe pas : on l'expose partout ou
 * il ira le chercher — apres commande, dans son compte, dans ses emails, et
 * cote agence pour le responsable.
 *
 * @package SL_Collect
 */

defined( 'ABSPATH' ) || exit;

/** La facture n'a de sens que pour une commande de retrait. */
function slc_facture_dispo( $order ) {
    return $order instanceof WC_Order
        && $order->get_meta( '_sl_collect_agence' )
        && ! $order->has_status( [ 'cancelled', 'failed', 'refunded' ] );
}

function slc_facture_bouton( $order, $label = '' ) {
    if ( ! slc_facture_dispo( $order ) ) {
        return '';
    }
    $label = $label ?: __( 'Télécharger ma facture / bon de retrait', 'sl-collect' );
    return '<a href="' . esc_url( slc_facture_url( $order ) ) . '" class="button slc-facture-btn" target="_blank" rel="noopener">'
        . esc_html( $label ) . '</a>';
}

/** 1. Page de remerciement, juste apres la commande. */
add_action( 'woocommerce_thankyou', 'slc_facture_thankyou', 20 );
function slc_facture_thankyou( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! slc_facture_dispo( $order ) ) {
        return;
    }
    echo '<p class="slc-facture-wrap" style="margin:18px 0;">' . slc_facture_bouton( $order ) . '</p>';
}

/** 2. Detail d'une commande dans « Mon compte ». */
add_action( 'woocommerce_order_details_after_order_table', 'slc_facture_compte', 20 );
function slc_facture_compte( $order ) {
    if ( is_admin() || ! slc_facture_dispo( $order ) ) {
        return;
    }
    echo '<p class="slc-facture-wrap" style="margin:14px 0;">' . slc_facture_bouton( $order ) . '</p>';
}

/** 3. Liste des commandes : une action dediee, sans ouvrir le detail. */
add_filter( 'woocommerce_my_account_my_orders_actions', 'slc_facture_action_liste', 10, 2 );
function slc_facture_action_liste( $actions, $order ) {
    if ( slc_facture_dispo( $order ) ) {
        $actions['slc_facture'] = [
            'url'  => slc_facture_url( $order ),
            'name' => __( 'Facture', 'sl-collect' ),
        ];
    }
    return $actions;
}

/**
 * 4. Emails WooCommerce. Le lien porte la cle de commande : le client le suit
 *    depuis sa boite mail sans avoir a se connecter.
 */
add_action( 'woocommerce_email_after_order_table', 'slc_facture_email', 20, 4 );
function slc_facture_email( $order, $sent_to_admin, $plain_text, $email = null ) {
    if ( $sent_to_admin || ! slc_facture_dispo( $order ) ) {
        return;
    }
    $url = slc_facture_url( $order );
    if ( $plain_text ) {
        echo "\n" . __( 'Votre facture / bon de retrait :', 'sl-collect' ) . "\n" . esc_url_raw( $url ) . "\n";
        return;
    }
    echo '<p style="margin:16px 0;"><a href="' . esc_url( $url )
        . '" style="background:#E91E63;color:#fff;padding:11px 18px;border-radius:6px;text-decoration:none;display:inline-block;font-weight:600;">'
        . esc_html__( 'Télécharger ma facture / bon de retrait', 'sl-collect' ) . '</a></p>';
}

/** 5. Ecran admin de la commande : le responsable peut la rimprimer au comptoir. */
add_action( 'woocommerce_admin_order_data_after_billing_address', 'slc_facture_admin', 20 );
function slc_facture_admin( $order ) {
    if ( ! slc_facture_dispo( $order ) ) {
        return;
    }
    echo '<p style="margin-top:10px;">' . slc_facture_bouton( $order, __( 'Ouvrir la facture (PDF)', 'sl-collect' ) ) . '</p>';
}
