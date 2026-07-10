<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   EXPIRATION AUTOMATIQUE (workflow valide par le client)
   - rappel au client 48 h apres le passage en « Prête »
   - annulation automatique a 72 h si non retiree
   ============================================================ */
add_action( 'sl_collect_cron', 'slc_cron_expiration' );
function slc_cron_expiration() {
    $ids = slc_order_ids( '', [ 'sl-prete' ], 300 );
    $now = time();

    foreach ( $ids as $oid ) {
        $order = wc_get_order( $oid );
        if ( ! $order ) continue;

        $prete_at = (int) $order->get_meta( '_sl_collect_prete_at' );
        if ( ! $prete_at ) {
            // horodatage manquant (transition manuelle) : on le pose maintenant
            $order->update_meta_data( '_sl_collect_prete_at', $now );
            $order->save();
            continue;
        }
        $age = $now - $prete_at;

        // 72 h : annulation
        if ( $age >= 72 * HOUR_IN_SECONDS ) {
            $order->update_status( 'cancelled', 'Drop & Collect — annulée automatiquement : non retirée sous 72 h.' );
            $to = $order->get_billing_email();
            if ( $to ) {
                wp_mail(
                    $to,
                    sprintf( 'Commande n°%s annulée — non retirée sous 72 h', $order->get_order_number() ),
                    sprintf(
                        "Bonjour %s,\n\nVotre commande n°%s, prête depuis plus de 72 heures à l'agence Santa Lucia — %s, "
                        . "n'a pas été retirée et a été annulée.\n\n"
                        . "Pour toute question ou nouveau retrait, contactez-nous au %s.\n\nComplexe Santa Lucia",
                        $order->get_billing_first_name() ?: 'cher client',
                        $order->get_order_number(),
                        slc_agence_name( $order->get_meta( '_sl_collect_agence' ) ),
                        slc_contact_phone()
                    )
                );
            }
            continue;
        }

        // 48 h : rappel (une seule fois)
        if ( $age >= 48 * HOUR_IN_SECONDS && ! $order->get_meta( '_sl_collect_rappel' ) ) {
            $order->update_meta_data( '_sl_collect_rappel', $now );
            $order->save();
            $to = $order->get_billing_email();
            if ( $to ) {
                wp_mail(
                    $to,
                    sprintf( 'Rappel : votre commande n°%s vous attend — Santa Lucia', $order->get_order_number() ),
                    sprintf(
                        "Bonjour %s,\n\nPetit rappel : votre commande n°%s est prête à l'agence Santa Lucia — %s "
                        . "depuis 2 jours.\n\nCode de retrait : %s\n\n"
                        . "⚠️ Sans retrait sous 24 h, elle sera automatiquement annulée.\n\nComplexe Santa Lucia",
                        $order->get_billing_first_name() ?: 'cher client',
                        $order->get_order_number(),
                        slc_agence_name( $order->get_meta( '_sl_collect_agence' ) ),
                        $order->get_meta( '_sl_collect_code' ) ?: '—'
                    )
                );
            }
            $order->add_order_note( 'Drop & Collect — rappel 48 h envoyé au client.' );
        }
    }
}
