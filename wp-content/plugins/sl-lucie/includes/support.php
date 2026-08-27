<?php
/** Outils de support : suivi de commande et transfert vers WhatsApp. */

if ( ! defined( 'ABSPATH' ) ) exit;

function sl_lucie_normalize_phone( $phone ) {
    $digits = preg_replace( '/\D+/', '', (string) $phone );
    if ( strlen( $digits ) === 9 && $digits[0] === '6' ) $digits = '237' . $digits;
    return $digits;
}

function sl_lucie_phone_matches( $left, $right ) {
    $a = sl_lucie_normalize_phone( $left );
    $b = sl_lucie_normalize_phone( $right );
    if ( $a === '' || $b === '' ) return false;
    return $a === $b || ( strlen( $a ) >= 9 && strlen( $b ) >= 9 && substr( $a, -9 ) === substr( $b, -9 ) );
}

/** Recherche avec jeton de suivi, ou avec numéro de commande + téléphone. */
function sl_lucie_find_order_for_tracking( $token = '', $order_number = '', $phone = '' ) {
    $token = strtolower( trim( sanitize_text_field( (string) $token ) ) );
    $phone_raw = trim( sanitize_text_field( (string) $phone ) );
    $phone = sl_lucie_normalize_phone( $phone );

    // Le jeton signé est le moyen recommandé : il ne révèle aucune autre commande.
    if ( $token !== '' && function_exists( 'slc_find_order_by_tracking_token' ) ) {
        $order = slc_find_order_by_tracking_token( $token );
        if ( $order ) return [ 'order' => $order, 'authorized_by_token' => true ];
    }

    $number = trim( sanitize_text_field( (string) $order_number ) );
    if ( $number === '' || $phone === '' || ! function_exists( 'wc_get_orders' ) ) return false;

    // Avec le numéro WooCommerce par défaut, la lecture directe évite une
    // requête large et couvre aussi les anciennes commandes.
    if ( ctype_digit( $number ) && function_exists( 'wc_get_order' ) ) {
        $direct = wc_get_order( (int) $number );
        if ( $direct && (string) $direct->get_order_number() === $number && sl_lucie_phone_matches( $direct->get_billing_phone(), $phone ) ) {
            return [ 'order' => $direct, 'authorized_by_token' => false ];
        }
    }

    $phone_queries = array_values( array_unique( array_filter( [ $phone_raw, $phone, strlen( $phone ) >= 9 ? substr( $phone, -9 ) : '' ] ) ) );
    $orders = [];
    foreach ( $phone_queries as $phone_query ) {
        $orders = array_merge( $orders, wc_get_orders( [
            'limit'         => 20,
            'type'          => 'shop_order',
            'status'        => 'any',
            'billing_phone' => $phone_query,
            'orderby'       => 'date',
            'order'         => 'DESC',
            'return'        => 'objects',
        ] ) );
    }
    foreach ( $orders as $order ) {
        if ( (string) $order->get_order_number() !== $number ) continue;
        if ( sl_lucie_phone_matches( $order->get_billing_phone(), $phone ) ) {
            return [ 'order' => $order, 'authorized_by_token' => false ];
        }
    }
    return false;
}

function sl_lucie_tracking_payload( $token = '', $order_number = '', $phone = '' ) {
    $found = sl_lucie_find_order_for_tracking( $token, $order_number, $phone );
    if ( ! $found ) return [ 'ok' => false, 'message' => 'Commande introuvable. Utilisez le lien de suivi reçu ou indiquez le numéro de commande et le téléphone utilisés lors de la commande.' ];
    $order = $found['order'];
    $agency_slug = (string) $order->get_meta( '_sl_collect_agence' );
    $items = [];
    foreach ( $order->get_items() as $item ) {
        $items[] = [ 'nom' => $item->get_name(), 'quantite' => (int) $item->get_quantity() ];
    }
    $payload = [
        'ok'             => true,
        'commande'       => $order->get_order_number(),
        'statut'         => function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $order->get_status() ) : $order->get_status(),
        'agence'         => function_exists( 'slc_agence_name' ) ? slc_agence_name( $agency_slug ) : $agency_slug,
        'articles'       => $items,
        'total'          => wp_strip_all_tags( $order->get_formatted_order_total() ),
        'lien_suivi'     => function_exists( 'slc_order_tracking_url' ) ? slc_order_tracking_url( $order ) : '',
        'protection'     => $found['authorized_by_token'] ? 'Lien de suivi vérifié.' : 'Vérification par numéro de commande et téléphone.',
    ];
    if ( $found['authorized_by_token'] ) {
        $payload['code_retrait'] = (string) $order->get_meta( '_sl_collect_code' ) ?: 'Après paiement / préparation';
        if ( function_exists( 'slc_pickup_slot_label' ) ) $payload['creneau'] = slc_pickup_slot_label( $order );
    }
    return $payload;
}

function sl_lucie_whatsapp_support_url() {
    $number = preg_replace( '/\D+/', '', (string) get_option( 'sl_lucie_whatsapp', '' ) );
    if ( $number === '' ) return [ 'ok' => false, 'message' => 'Le contact WhatsApp du service client n’est pas configuré.' ];
    $messages = isset( $GLOBALS['sl_lucie_conversation_messages'] ) && is_array( $GLOBALS['sl_lucie_conversation_messages'] ) ? $GLOBALS['sl_lucie_conversation_messages'] : [];
    $lines = [];
    foreach ( array_slice( $messages, -8 ) as $message ) {
        if ( ( $message['role'] ?? '' ) !== 'user' ) continue;
        $text = trim( wp_strip_all_tags( (string) ( $message['content'] ?? '' ) ) );
        if ( $text !== '' ) $lines[] = mb_substr( $text, 0, 180 );
    }
    $summary = implode( "\n", array_slice( $lines, -4 ) );
    $prefill = "Bonjour Santa Lucia, je viens de Lucie.\nMa demande :\n" . ( $summary ?: 'Je souhaite être aidé par un conseiller.' );
    return [ 'ok' => true, 'url' => 'https://wa.me/' . $number . '?text=' . rawurlencode( $prefill ), 'message' => 'Le résumé de votre demande est prêt. Ouvrez WhatsApp pour poursuivre avec un conseiller.' ];
}
