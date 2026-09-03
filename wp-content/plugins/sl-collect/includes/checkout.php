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
 * Refonte visuelle du checkout.
 *
 * Les regles de commande, de retrait, de SMS et de paiement restent dans les
 * passerelles existantes. Cette couche ne fait qu'ordonner l'information et
 * franciser les messages visibles au client.
 */
add_action( 'wp_enqueue_scripts', 'slc_checkout_enqueue_refonte_assets', 30 );
function slc_checkout_enqueue_refonte_assets() {
    $is_checkout = function_exists( 'is_checkout' ) && is_checkout() && ! ( function_exists( 'is_order_received_page' ) && is_order_received_page() );
    $is_cart     = function_exists( 'is_cart' ) && is_cart();
    if ( ! $is_checkout && ! $is_cart ) {
        return;
    }

    if ( $is_cart ) {
        $cart_css = SL_COLLECT_PATH . 'assets/cart-mobile-v1.css';
        $cart_js  = SL_COLLECT_PATH . 'assets/cart-mobile-v1.js';
        wp_enqueue_style( 'slc-cart-mobile', SL_COLLECT_URL . 'assets/cart-mobile-v1.css', [], file_exists( $cart_css ) ? (string) filemtime( $cart_css ) : SL_COLLECT_VERSION );
        wp_enqueue_script( 'slc-cart-mobile', SL_COLLECT_URL . 'assets/cart-mobile-v1.js', [], file_exists( $cart_js ) ? (string) filemtime( $cart_js ) : SL_COLLECT_VERSION, true );
    }

    if ( ! $is_checkout ) return;

    $css_file = SL_COLLECT_PATH . 'assets/checkout-refonte-v1.css';
    $js_file  = SL_COLLECT_PATH . 'assets/checkout-refonte-v1.js';

    wp_enqueue_style(
        'slc-checkout-refonte',
        SL_COLLECT_URL . 'assets/checkout-refonte-v1.css',
        [],
        file_exists( $css_file ) ? (string) filemtime( $css_file ) : SL_COLLECT_VERSION
    );
    wp_enqueue_script(
        'slc-checkout-refonte',
        SL_COLLECT_URL . 'assets/checkout-refonte-v1.js',
        [ 'jquery' ],
        file_exists( $js_file ) ? (string) filemtime( $js_file ) : SL_COLLECT_VERSION,
        true
    );
}

/** Francisation ciblée du panier, sans modifier les autres écrans WooCommerce. */
add_filter( 'gettext', 'slc_cart_french_labels', 20, 3 );
function slc_cart_french_labels( $translated, $text, $domain ) {
    if ( is_admin() || ! function_exists( 'is_cart' ) || ! is_cart() ) return $translated;
    $labels = [
        'Cart'          => 'Panier',
        'Quantity'      => 'Quantité',
        'Coupon:'       => 'Code promo :',
        'Coupon code'   => 'Saisissez votre code',
        'Apply coupon'  => 'Appliquer',
        'Clear All'     => 'Vider le panier',
        'Shopping Cart' => 'Panier',
    ];
    return isset( $labels[ $text ] ) ? $labels[ $text ] : $translated;
}

/** Etape 1 : informations necessaires au retrait. */
add_action( 'woocommerce_checkout_before_customer_details', 'slc_checkout_customer_intro', 5 );
function slc_checkout_customer_intro() {
    echo '<section class="slc-checkout-intro" aria-labelledby="slc-checkout-title">'
        . '<span class="slc-checkout-intro__eyebrow">Commande en ligne</span>'
        . '<h1 id="slc-checkout-title">Finalisez votre commande</h1>'
        . '<p>Renseignez vos coordonnées, choisissez votre agence et recevez votre code de retrait par SMS ou e-mail.</p>'
        . '<ul class="slc-checkout-reassurance" aria-label="Vos garanties">'
        . '<li>Retrait en agence</li><li>Confirmation par SMS</li><li>Paiement sécurisé</li>'
        . '</ul></section>';
}

add_action( 'woocommerce_before_checkout_billing_form', 'slc_checkout_billing_heading', 5 );
function slc_checkout_billing_heading() {
    echo '<div class="slc-checkout-section-heading">'
        . '<span class="slc-checkout-step" aria-hidden="true">1</span>'
        . '<div><h2>Vos coordonnées et votre retrait</h2><p>Nous les utilisons uniquement pour préparer votre commande et vous prévenir.</p></div>'
        . '</div>';
}

/** Etape 2 : le recapitulatif et le moyen de paiement. */
add_action( 'woocommerce_checkout_order_review', 'slc_checkout_review_heading', 1 );
function slc_checkout_review_heading() {
    echo '<div class="slc-checkout-review-heading">'
        . '<span class="slc-checkout-step" aria-hidden="true">2</span>'
        . '<div><h2>Récapitulatif et paiement</h2><p>Vérifiez votre commande, puis choisissez comment la régler.</p></div>'
        . '</div>';
}

add_action( 'woocommerce_review_order_before_payment', 'slc_checkout_payment_heading', 5 );
function slc_checkout_payment_heading() {
    echo '<div class="slc-payment-heading"><strong>Choisissez un moyen de paiement</strong>'
        . '<span>Vous pourrez vérifier les détails avant la validation finale.</span></div>';
}

/**
 * Texte de confirmation juste au-dessus du bouton. La classe est volontaire :
 * le CSS de checkout habille ce rappel sans modifier la logique de retrait.
 */
add_action( 'woocommerce_review_order_before_submit', 'slc_checkout_process_notice' );
function slc_checkout_process_notice() {
    echo '<div class="slc-checkout-process-notice">'
        . '<strong>Après validation :</strong> vous recevrez votre numéro de commande. '
        . 'Dès que la commande est prête, votre <strong>code de retrait</strong> vous est envoyé par SMS ou e-mail. '
        . 'Le retrait est possible pendant <strong>72 h</strong> ; au-delà, la commande est automatiquement annulée.'
        . '</div>';
}

/** Libelles coherents, sans modifier le comportement des passerelles. */
add_filter( 'woocommerce_available_payment_gateways', 'slc_checkout_payment_copy', 30 );
function slc_checkout_payment_copy( $gateways ) {
    if ( is_admin() || ! function_exists( 'is_checkout' ) || ! is_checkout() || ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) ) {
        return $gateways;
    }

    if ( isset( $gateways['mmgate'] ) ) {
        $gateways['mmgate']->title       = 'Mobile Money (MTN MoMo / Orange Money)';
        $gateways['mmgate']->description = 'Le numéro Mobile Money à débiter vous sera demandé lorsque vous confirmerez votre commande.';
    }
    if ( isset( $gateways['slc_call'] ) ) {
        $gateways['slc_call']->title       = 'Réserver puis payer après confirmation';
        $gateways['slc_call']->description = sprintf( 'Votre stock est réservé pendant %d minutes. Appelez l’agence pour confirmer, puis payez en ligne depuis votre compte.', function_exists( 'slc_reservation_minutes' ) ? slc_reservation_minutes() : 60 );
    }
    return $gateways;
}

add_filter( 'woocommerce_order_button_text', function () {
    return 'Confirmer ma commande';
} );

add_filter( 'woocommerce_get_privacy_policy_text', 'slc_checkout_privacy_copy', 20, 2 );
function slc_checkout_privacy_copy( $text, $type = '' ) {
    if ( 'checkout' !== $type ) {
        return $text;
    }

    $url  = function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '';
    $link = $url ? '<a href="' . esc_url( $url ) . '">politique de confidentialité</a>' : 'politique de confidentialité';
    return 'Vos données servent à préparer et suivre votre commande, conformément à notre ' . $link . '.';
}

add_filter( 'woocommerce_get_terms_and_conditions_checkbox_text', function () {
    return 'J’ai lu et j’accepte les [terms].';
} );

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
        $fields['billing']['billing_phone']['label']    = 'Téléphone de contact (obligatoire — SMS et retrait)';
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

    $today = current_time( 'Y-m-d' );
    $last  = date_i18n( 'Y-m-d', current_time( 'timestamp' ) + 7 * DAY_IN_SECONDS );
    $fields['billing']['sl_collect_pickup_date'] = [
        'type'              => 'date',
        'label'             => 'Jour de retrait souhaité',
        'required'          => true,
        'default'           => $today,
        'priority'          => 130,
        'class'             => [ 'form-row-first', 'sl-collect-pickup-date' ],
        'custom_attributes' => [ 'min' => $today, 'max' => $last ],
    ];
    $fields['billing']['sl_collect_pickup_slot'] = [
        'type'        => 'select',
        'label'       => 'Créneau de retrait',
        'required'    => true,
        'options'     => function_exists( 'slc_pickup_slots' ) ? slc_pickup_slots() : [ 'asap' => 'Dès que possible' ],
        'priority'    => 140,
        'class'       => [ 'form-row-last', 'sl-collect-pickup-slot' ],
        'description' => 'Nous vous prévenons dès que la commande est réellement prête.',
    ];
    $fields['billing']['sl_collect_collector_name'] = [
        'type'        => 'text',
        'label'       => 'Autre personne autorisée à retirer (facultatif)',
        'placeholder' => 'Nom complet du mandataire',
        'required'    => false,
        'priority'    => 150,
        'class'       => [ 'form-row-first', 'sl-collect-collector-name' ],
    ];
    $fields['billing']['sl_collect_collector_phone'] = [
        'type'        => 'tel',
        'label'       => 'Téléphone de cette personne (facultatif)',
        'placeholder' => '6XX XXX XXX',
        'required'    => false,
        'priority'    => 160,
        'class'       => [ 'form-row-last', 'sl-collect-collector-phone' ],
    ];
    return $fields;
}

// Masque aussi la section « Informations complémentaires » (vide sans notes)
add_filter( 'woocommerce_enable_order_notes_field', '__return_false' );

add_action( 'woocommerce_checkout_process', function () {
    $slug = isset( $_POST['sl_collect_agence'] ) ? sanitize_title( wp_unslash( $_POST['sl_collect_agence'] ) ) : '';
    if ( $slug === '' || ! get_term_by( 'slug', $slug, 'sl_agence_promo' ) ) {
        wc_add_notice( 'Veuillez choisir votre <strong>agence de retrait</strong>.', 'error' );
        return;
    }

    $date = isset( $_POST['sl_collect_pickup_date'] ) ? sanitize_text_field( wp_unslash( $_POST['sl_collect_pickup_date'] ) ) : '';
    $slot = isset( $_POST['sl_collect_pickup_slot'] ) ? sanitize_key( wp_unslash( $_POST['sl_collect_pickup_slot'] ) ) : '';
    if ( ! function_exists( 'slc_pickup_date_is_valid' ) || ! slc_pickup_date_is_valid( $date ) ) {
        wc_add_notice( 'Choisissez un <strong>jour de retrait valide</strong> dans les 7 prochains jours.', 'error' );
    } elseif ( ! isset( slc_pickup_slots()[ $slot ] ) ) {
        wc_add_notice( 'Choisissez un <strong>créneau de retrait valide</strong>.', 'error' );
    } elseif ( ! slc_pickup_slot_available( $slug, $date, $slot ) ) {
        wc_add_notice( 'Ce créneau n’est plus disponible ou est complet dans cette agence. Veuillez choisir un autre horaire ou un autre jour.', 'error' );
    }

    $collector_name  = isset( $_POST['sl_collect_collector_name'] ) ? sanitize_text_field( wp_unslash( $_POST['sl_collect_collector_name'] ) ) : '';
    $collector_phone = isset( $_POST['sl_collect_collector_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['sl_collect_collector_phone'] ) ) : '';
    if ( ( $collector_name === '' ) xor ( $collector_phone === '' ) ) {
        wc_add_notice( 'Pour autoriser une autre personne, renseignez son <strong>nom et son téléphone</strong>.', 'error' );
    }
    $collector_digits = preg_replace( '/\D+/', '', $collector_phone );
    if ( $collector_phone !== '' && ( strlen( $collector_digits ) < 8 || strlen( $collector_digits ) > 15 ) ) {
        wc_add_notice( 'Le téléphone de la personne autorisée semble invalide.', 'error' );
    }
} );

add_action( 'woocommerce_checkout_create_order', function ( $order, $data ) {
    $slug = isset( $_POST['sl_collect_agence'] ) ? sanitize_title( wp_unslash( $_POST['sl_collect_agence'] ) ) : '';
    if ( $slug !== '' && get_term_by( 'slug', $slug, 'sl_agence_promo' ) ) {
        $order->update_meta_data( '_sl_collect_agence', $slug );
    }
    $date = isset( $_POST['sl_collect_pickup_date'] ) ? sanitize_text_field( wp_unslash( $_POST['sl_collect_pickup_date'] ) ) : '';
    $slot = isset( $_POST['sl_collect_pickup_slot'] ) ? sanitize_key( wp_unslash( $_POST['sl_collect_pickup_slot'] ) ) : '';
    if ( function_exists( 'slc_pickup_date_is_valid' ) && slc_pickup_date_is_valid( $date ) ) {
        $order->update_meta_data( '_slc_pickup_date', $date );
    }
    if ( function_exists( 'slc_pickup_slots' ) && isset( slc_pickup_slots()[ $slot ] ) ) {
        $order->update_meta_data( '_slc_pickup_slot', $slot );
    }
    $collector_name  = isset( $_POST['sl_collect_collector_name'] ) ? sanitize_text_field( wp_unslash( $_POST['sl_collect_collector_name'] ) ) : '';
    $collector_phone = isset( $_POST['sl_collect_collector_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['sl_collect_collector_phone'] ) ) : '';
    if ( $collector_name !== '' && $collector_phone !== '' ) {
        $order->update_meta_data( '_slc_collector_name', $collector_name );
        $order->update_meta_data( '_slc_collector_phone', $collector_phone );
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
    if ( function_exists( 'slc_pickup_slot_label' ) ) {
        echo '<p style="margin:0 0 10px;">Retrait souhaité : <strong>' . esc_html( slc_pickup_slot_label( $order ) ) . '</strong></p>';
    }
    $collector = (string) $order->get_meta( '_slc_collector_name' );
    if ( $collector !== '' ) {
        echo '<p style="margin:0 0 10px;">Personne autorisée : <strong>' . esc_html( $collector ) . '</strong></p>';
    }

    if ( $order->has_status( 'pending' ) ) {
        echo '<p style="margin:0 0 8px;">📞 <strong>Confirmez la disponibilité de vos produits</strong> en appelant le '
            . '<a href="tel:' . esc_attr( preg_replace( '/\s+/', '', slc_contact_phone() ) ) . '"><strong>' . esc_html( slc_contact_phone() ) . '</strong></a>'
            . ' en indiquant votre numéro de commande.</p>';
        echo '<p style="margin:0;font-size:13px;color:#555;">Après confirmation, réglez votre commande en ligne depuis '
            . '<a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '">votre compte</a> (rubrique Commandes → Payer). '
            . 'Votre code de retrait vous sera envoyé dès le paiement. Le stock est réservé pendant <strong>'
            . (int) ( function_exists( 'slc_reservation_minutes' ) ? slc_reservation_minutes() : 60 ) . ' minutes</strong>.</p>';
    } else {
        $code = $order->get_meta( '_sl_collect_code' );
        if ( $code ) {
            echo '<p style="margin:0 0 8px;">Code de retrait : <strong style="font-size:22px;letter-spacing:2px;color:#e91e8c;">' . esc_html( $code ) . '</strong></p>';
        }
        echo '<p style="margin:0;font-size:13px;color:#555;">Vous recevrez un message dès que votre commande sera prête. '
            . 'Au comptoir : code de retrait + numéro de commande + téléphone + pièce d\'identité.</p>';
    }
    if ( function_exists( 'slc_order_tracking_url' ) ) {
        echo '<p style="margin:12px 0 0;"><a href="' . esc_url( slc_order_tracking_url( $order ) ) . '"><strong>Suivre ou gérer cette commande</strong></a></p>';
    }
    echo '</div>';
}
