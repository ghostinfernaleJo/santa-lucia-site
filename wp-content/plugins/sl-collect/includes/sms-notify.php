<?php
/**
 * SMS automatiques au client via MMGate (5 FCFA/SMS, debites du solde
 * partenaire — le meme compte que l'encaissement, aucun contrat en plus).
 *
 * Deux envois par commande retrait, aux moments qui comptent :
 *  1. paiement confirme  -> numero de commande + CODE DE RETRAIT ;
 *  2. commande PRETE     -> agence + rappel du code + delai 72 h.
 *
 * Concu pour les clients SANS compte : leur telephone (obligatoire au
 * checkout) est le seul canal garanti avec l'email. Corps en ASCII pur —
 * mmgate_send_sms() translitere, et le GSM 7 bits abime les accents.
 *
 * Chaque envoi est trace en note de commande, y compris les echecs (ETAT 309
 * = solde MMGate insuffisant : la note le dit, personne ne cherche pourquoi
 * « les SMS ne partent pas »).
 *
 * @package SL_Collect
 */

defined( 'ABSPATH' ) || exit;

function slc_sms_enabled() {
    return get_option( 'sl_collect_sms', 'yes' ) === 'yes' && function_exists( 'mmgate_send_sms' );
}

/**
 * Envoie un SMS lie a une commande, avec garde anti-doublon et note de resultat.
 *
 * @param WC_Order $order   Commande.
 * @param string   $etape   Cle d'etape (paid|ready) pour la garde anti-doublon.
 * @param string   $message Corps du SMS (ASCII de preference).
 */
function slc_sms_order( $order, $etape, $message ) {
    if ( ! slc_sms_enabled() ) {
        return;
    }
    if ( ! $order->get_meta( '_sl_collect_agence' ) ) {
        return; // hors circuit retrait
    }
    $garde = '_slc_sms_' . $etape;
    if ( $order->get_meta( $garde ) ) {
        return; // deja envoye pour cette etape (les hooks Woo peuvent rejouer)
    }
    $tel = $order->get_billing_phone();
    if ( ! $tel ) {
        return;
    }

    // Garde posee AVANT l'envoi : mieux vaut rater un SMS que le facturer double.
    $order->update_meta_data( $garde, current_time( 'mysql' ) );
    $order->save();

    $res = mmgate_send_sms( $tel, $message );

    if ( is_wp_error( $res ) ) {
        $order->add_order_note( 'SMS ' . $etape . ' non envoyé : ' . $res->get_error_message() );
        return;
    }
    $etat = isset( $res['ETAT'] ) ? (int) $res['ETAT'] : 0;
    if ( 300 === $etat ) {
        $order->add_order_note( 'SMS ' . $etape . ' envoyé au ' . $tel . ' (IDOPER ' . ( $res['IDOPER'] ?? '?' ) . ').' );
    } elseif ( 309 === $etat ) {
        $order->add_order_note( 'SMS ' . $etape . ' NON envoyé : solde MMGate insuffisant (5 F/SMS — approvisionner le compte partenaire).' );
    } else {
        $order->add_order_note( 'SMS ' . $etape . ' NON envoyé (ETAT ' . $etat . ').' );
    }
}

/** 1. Paiement confirme : numero de commande + code de retrait. */
add_action( 'woocommerce_payment_complete', 'slc_sms_on_paid', 40 );
add_action( 'woocommerce_order_status_processing', 'slc_sms_on_paid', 40 ); // prio 40 : apres la generation du code (10)
function slc_sms_on_paid( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }
    $code = (string) $order->get_meta( '_sl_collect_code' );
    $msg  = 'Complexe Santa Lucia: paiement recu, commande no ' . $order->get_order_number() . '.'
        . ( $code !== '' ? ' Code retrait: ' . $code . '.' : '' )
        . ' Vous serez prevenu des que votre commande sera prete.';
    slc_sms_order( $order, 'paid', $msg );
}

/** 2. Commande prete : agence + code + delai. */
add_action( 'woocommerce_order_status_sl-prete', 'slc_sms_on_ready', 40 );
function slc_sms_on_ready( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }
    $agence = function_exists( 'slc_agence_name' )
        ? slc_agence_name( (string) $order->get_meta( '_sl_collect_agence' ) )
        : (string) $order->get_meta( '_sl_collect_agence' );
    $code = (string) $order->get_meta( '_sl_collect_code' );
    $msg  = 'Complexe Santa Lucia: votre commande no ' . $order->get_order_number()
        . ' est PRETE a l\'agence ' . $agence . '.'
        . ( $code !== '' ? ' Code retrait: ' . $code . '.' : '' )
        . ' Retrait sous 72h.';
    slc_sms_order( $order, 'ready', $msg );
}
