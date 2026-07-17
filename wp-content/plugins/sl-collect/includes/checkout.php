<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   CHECKOUT : telephone obligatoire + choix de l'agence de retrait
   ============================================================ */

// Le theme (Grogin) desactive la creation de compte au checkout via
// __return_false -> on la reactive en priorite haute (elle reste PROPOSEE,
// via la case « Creer un compte ? », plus jamais imposee).
add_filter( 'woocommerce_checkout_registration_enabled', '__return_true', 99 );

/**
 * Commande SANS compte (invite), pilotee par l'option sl_collect_guest.
 * L'activation du plugin avait force woocommerce_enable_guest_checkout=no ;
 * plutot que de retoucher la base, ce filtre fait foi. Garde-fou metier :
 * telephone (billing_phone, requis plus bas) + email (requis par Woo) restent
 * OBLIGATOIRES — c'est ce qui permet d'envoyer la facture (lien a cle par
 * email, consultable sans connexion) et le SMS a un client sans compte.
 */
add_filter( 'pre_option_woocommerce_enable_guest_checkout', function () {
    return get_option( 'sl_collect_guest', 'yes' ) === 'yes' ? 'yes' : 'no';
} );
add_filter( 'woocommerce_checkout_fields', 'slc_checkout_fields', 20 );
function slc_checkout_fields( $fields ) {
    if ( isset( $fields['billing']['billing_phone'] ) ) {
        $fields['billing']['billing_phone']['required'] = true;
        $fields['billing']['billing_phone']['label']    = 'Téléphone (obligatoire — utilisé au retrait)';
    }

    // Un seul champ « Nom complet » au lieu de Prénom + Nom : au comptoir on
    // demande le nom, pas l'etat civil. On reutilise billing_first_name en
    // pleine largeur et on masque billing_last_name. Le nom saisi reste dans
    // first_name ; last_name demeure vide. Tous les affichages du site font
    // deja trim(first . ' ' . last) -> le nom complet ressort correctement,
    // aucune recopie necessaire (verifie : aucun code ne lit last_name seul).
    if ( isset( $fields['billing']['billing_first_name'] ) ) {
        $fields['billing']['billing_first_name']['label']       = 'Nom complet';
        $fields['billing']['billing_first_name']['class']       = [ 'form-row-wide' ];
        $fields['billing']['billing_first_name']['priority']    = 10;
        $fields['billing']['billing_first_name']['placeholder'] = 'Prénom et nom';
    }
    unset( $fields['billing']['billing_last_name'] );

    // Retrait en agence : les champs d'adresse de livraison sont inutiles
    unset(
        $fields['billing']['billing_address_1'],  // Numéro et nom de rue
        $fields['billing']['billing_address_2'],  // Appartement, suite...
        $fields['billing']['billing_city'],       // Ville
        $fields['billing']['billing_state'],      // Région / Département
        $fields['billing']['billing_postcode'],   // Code postal
        $fields['billing']['billing_company'],    // Société (superflu)
        $fields['billing']['billing_country']     // Pays (retrait au Cameroun)
    );
    // Notes de commande (« Informations complémentaires »)
    unset( $fields['order']['order_comments'] );

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

// Masque aussi la section « Informations complémentaires » (vide sans notes)
add_filter( 'woocommerce_enable_order_notes_field', '__return_false' );

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
