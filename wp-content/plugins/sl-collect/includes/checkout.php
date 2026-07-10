<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   CHECKOUT : telephone obligatoire + choix de l'agence de retrait
   ============================================================ */
add_filter( 'woocommerce_checkout_fields', 'slc_checkout_fields', 20 );
function slc_checkout_fields( $fields ) {
    if ( isset( $fields['billing']['billing_phone'] ) ) {
        $fields['billing']['billing_phone']['required'] = true;
        $fields['billing']['billing_phone']['label']    = 'Téléphone (obligatoire — utilisé au retrait)';
    }

    $options = [ '' => 'Choisissez votre agence de retrait…' ];
    foreach ( slc_agences() as $t ) {
        $options[ $t->slug ] = $t->name;
    }
    $fields['billing']['sl_collect_agence'] = [
        'type'     => 'select',
        'label'    => 'Agence de retrait',
        'required' => true,
        'options'  => $options,
        'priority' => 120,
        'class'    => [ 'form-row-wide', 'sl-collect-agence-field' ],
    ];
    return $fields;
}

add_action( 'woocommerce_checkout_process', function () {
    $slug = isset( $_POST['sl_collect_agence'] ) ? sanitize_title( wp_unslash( $_POST['sl_collect_agence'] ) ) : '';
    if ( $slug === '' || ! get_term_by( 'slug', $slug, 'sl_agence_promo' ) ) {
        wc_add_notice( 'Veuillez choisir votre <strong>agence de retrait</strong>.', 'error' );
    }
} );

add_action( 'woocommerce_checkout_create_order', function ( $order, $data ) {
    $slug = isset( $_POST['sl_collect_agence'] ) ? sanitize_title( wp_unslash( $_POST['sl_collect_agence'] ) ) : '';
    if ( $slug !== '' && get_term_by( 'slug', $slug, 'sl_agence_promo' ) ) {
        $order->update_meta_data( '_sl_collect_agence', $slug );
    }
}, 10, 2 );

/* ============================================================
   PAGE DE CONFIRMATION (« merci ») : numero bien visible +
   instructions selon le statut (en attente d'appel vs payee)
   ============================================================ */
add_action( 'woocommerce_thankyou', 'slc_thankyou_bloc', 5 );
function slc_thankyou_bloc( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order || ! $order->get_meta( '_sl_collect_agence' ) ) return;

    $agence = slc_agence_name( $order->get_meta( '_sl_collect_agence' ) );
    echo '<div style="margin:0 0 24px;padding:20px;border-radius:12px;background:#f6f9ff;border:1px solid #d7e3ff;">';
    echo '<p style="margin:0 0 6px;font-size:15px;">Numéro de commande</p>';
    echo '<p style="margin:0 0 14px;font-size:30px;font-weight:800;letter-spacing:1px;">n°' . esc_html( $order->get_order_number() ) . '</p>';
    echo '<p style="margin:0 0 10px;">Agence de retrait : <strong>Santa Lucia — ' . esc_html( $agence ) . '</strong></p>';

    if ( $order->has_status( 'pending' ) ) {
        echo '<p style="margin:0 0 8px;">📞 <strong>Confirmez la disponibilité de vos produits</strong> en appelant le '
            . '<a href="tel:' . esc_attr( preg_replace( '/\s+/', '', slc_contact_phone() ) ) . '"><strong>' . esc_html( slc_contact_phone() ) . '</strong></a>'
            . ' en indiquant votre numéro de commande.</p>';
        echo '<p style="margin:0;font-size:13px;color:#555;">Après confirmation, réglez votre commande en ligne depuis '
            . '<a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '">votre compte</a> (rubrique Commandes → Payer). '
            . 'Votre code de retrait vous sera envoyé dès le paiement.</p>';
    } else {
        $code = $order->get_meta( '_sl_collect_code' );
        if ( $code ) {
            echo '<p style="margin:0 0 8px;">Code de retrait : <strong style="font-size:22px;letter-spacing:2px;color:#e91e8c;">' . esc_html( $code ) . '</strong></p>';
        }
        echo '<p style="margin:0;font-size:13px;color:#555;">Vous recevrez un message dès que votre commande sera prête. '
            . 'Au comptoir : code de retrait + numéro de commande + téléphone + pièce d\'identité.</p>';
    }
    echo '</div>';
}
