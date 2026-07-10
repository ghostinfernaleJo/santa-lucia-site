<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   STATUT CUSTOM « Prête pour retrait » (wc-sl-prete)
   Cycle : pending (en attente) -> processing (payee, en preparation)
           -> sl-prete (prete) -> completed (retiree) / cancelled
   ============================================================ */
add_action( 'init', function () {
    register_post_status( 'wc-sl-prete', [
        'label'                     => 'Prête pour retrait',
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop(
            'Prête pour retrait <span class="count">(%s)</span>',
            'Prêtes pour retrait <span class="count">(%s)</span>'
        ),
    ] );
} );

add_filter( 'wc_order_statuses', function ( $statuses ) {
    $new = [];
    foreach ( $statuses as $k => $v ) {
        $new[ $k ] = $v;
        if ( 'wc-processing' === $k ) {
            $new['wc-sl-prete'] = 'Prête pour retrait';
        }
    }
    if ( ! isset( $new['wc-sl-prete'] ) ) $new['wc-sl-prete'] = 'Prête pour retrait';
    return $new;
} );

// Une commande « prete » est une commande payee (rapports, logique Woo)
add_filter( 'woocommerce_order_is_paid_statuses', function ( $statuses ) {
    $statuses[] = 'sl-prete';
    return $statuses;
} );

/* ============================================================
   CODE DE RETRAIT — genere des que la commande est payee
   ============================================================ */
add_action( 'woocommerce_payment_complete',        'slc_ensure_code' );
add_action( 'woocommerce_order_status_processing', 'slc_ensure_code' );
function slc_ensure_code( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order || ! $order->get_meta( '_sl_collect_agence' ) ) return;
    if ( $order->get_meta( '_sl_collect_code' ) ) return;
    $code = slc_generate_code();
    $order->update_meta_data( '_sl_collect_code', $code );
    $order->save();
    $order->add_order_note( 'Drop & Collect — code de retrait généré : ' . $code );
}

/* ============================================================
   TRANSITIONS : horodatage + notifications client
   ============================================================ */
add_action( 'woocommerce_order_status_changed', 'slc_on_status_changed', 20, 4 );
function slc_on_status_changed( $order_id, $from, $to, $order ) {
    if ( ! $order instanceof WC_Order ) $order = wc_get_order( $order_id );
    if ( ! $order || ! $order->get_meta( '_sl_collect_agence' ) ) return;

    if ( 'sl-prete' === $to ) {
        $order->update_meta_data( '_sl_collect_prete_at', time() );
        $order->save();
        slc_mail_commande_prete( $order );
    }

    if ( 'completed' === $to && 'sl-prete' === $from ) {
        $order->update_meta_data( '_sl_collect_retire_at', time() );
        $order->save();
    }
}

/** Email « votre commande est prête » (SMS/WhatsApp en Phase 3). */
function slc_mail_commande_prete( WC_Order $order ) {
    $to = $order->get_billing_email();
    if ( ! $to ) return;
    $agence = slc_agence_name( $order->get_meta( '_sl_collect_agence' ) );
    $code   = $order->get_meta( '_sl_collect_code' );
    $sujet  = sprintf( 'Votre commande n°%s est prête — Santa Lucia %s', $order->get_order_number(), $agence );
    $corps  = sprintf(
        "Bonjour %s,\n\n"
        . "Bonne nouvelle : votre commande n°%s est PRÊTE !\n\n"
        . "Agence de retrait : Santa Lucia — %s\n"
        . "Code de retrait   : %s\n\n"
        . "Pour retirer votre commande, présentez au comptoir :\n"
        . "  - votre code de retrait (%s)\n"
        . "  - votre numéro de commande (%s)\n"
        . "  - votre téléphone (%s)\n"
        . "  - une pièce d'identité\n\n"
        . "⏰ Votre commande vous est réservée pendant 72 heures.\n\n"
        . "Merci de votre confiance,\nComplexe Santa Lucia",
        $order->get_billing_first_name() ?: 'cher client',
        $order->get_order_number(),
        $agence,
        $code ?: '(sera vérifié au comptoir)',
        $code ?: '—',
        $order->get_order_number(),
        $order->get_billing_phone() ?: '—'
    );
    wp_mail( $to, $sujet, $corps );
    $order->add_order_note( 'Drop & Collect — email « commande prête » envoyé à ' . $to );
}

/* ============================================================
   AFFICHAGE agence + code dans les emails Woo et l'espace client
   ============================================================ */
function slc_bloc_retrait_html( WC_Order $order ) {
    $agence = $order->get_meta( '_sl_collect_agence' );
    if ( ! $agence ) return '';
    $code = $order->get_meta( '_sl_collect_code' );
    $out  = '<div style="margin:18px 0;padding:16px;border:2px solid #e91e8c;border-radius:10px;background:#fff7fb;">';
    $out .= '<p style="margin:0 0 8px;font-size:16px;"><strong>🏪 Retrait en agence : Santa Lucia — ' . esc_html( slc_agence_name( $agence ) ) . '</strong></p>';
    if ( $code ) {
        $out .= '<p style="margin:0 0 8px;">Code de retrait : <strong style="font-size:20px;letter-spacing:2px;color:#e91e8c;">' . esc_html( $code ) . '</strong></p>';
    }
    $out .= '<p style="margin:0;font-size:13px;color:#555;">Au comptoir, présentez : le code de retrait, le numéro de commande, votre téléphone et une pièce d\'identité.</p>';
    $out .= '</div>';
    return $out;
}

add_action( 'woocommerce_email_after_order_table', function ( $order, $sent_to_admin, $plain_text ) {
    if ( $sent_to_admin || ! $order instanceof WC_Order ) return;
    $agence = $order->get_meta( '_sl_collect_agence' );
    if ( ! $agence ) return;
    if ( $plain_text ) {
        echo "\nRETRAIT EN AGENCE : Santa Lucia — " . slc_agence_name( $agence ) . "\n";
        $code = $order->get_meta( '_sl_collect_code' );
        if ( $code ) echo 'CODE DE RETRAIT : ' . $code . "\n";
        echo "Au comptoir : code de retrait + numéro de commande + téléphone + pièce d'identité.\n";
    } else {
        echo wp_kses_post( slc_bloc_retrait_html( $order ) );
    }
}, 10, 3 );

// Page de confirmation + « voir la commande » dans Mon compte
add_action( 'woocommerce_order_details_after_order_table', function ( $order ) {
    if ( $order instanceof WC_Order ) echo wp_kses_post( slc_bloc_retrait_html( $order ) );
} );

// Metabox admin : afficher agence + code sur la commande
add_action( 'woocommerce_admin_order_data_after_billing_address', function ( $order ) {
    $agence = $order->get_meta( '_sl_collect_agence' );
    if ( ! $agence ) return;
    echo '<p><strong>Agence de retrait :</strong> ' . esc_html( slc_agence_name( $agence ) ) . '</p>';
    $code = $order->get_meta( '_sl_collect_code' );
    if ( $code ) echo '<p><strong>Code de retrait :</strong> <code>' . esc_html( $code ) . '</code></p>';
} );
