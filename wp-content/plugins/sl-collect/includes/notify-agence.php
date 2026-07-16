<?php
/**
 * Drop & Collect — notification du responsable d'agence.
 *
 * Jusqu'ici, les trois emails du plugin partaient tous vers le CLIENT
 * (commande prete, rappel 48 h, annulation 72 h). Le responsable, lui, devait
 * penser a ouvrir son ecran pour decouvrir qu'une commande l'attendait.
 * Ici : un email a la commande payee + une pastille de comptage sur le menu.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Comptes responsables rattaches a une agence (slug).
 * Les deux metas coexistent sur le site : _sl_agence_ff porte le SLUG (Fast Food),
 * sl_agence_assignee porte le NOM (Bons Plans). Un responsable peut n'avoir que
 * l'une des deux -> on interroge les deux, sinon on rate la moitie des comptes.
 */
function slc_agence_users( $slug ) {
    $slug = sanitize_title( (string) $slug );
    if ( $slug === '' ) return [];

    $found = [];

    $by_slug = get_users( [
        'meta_key'   => '_sl_agence_ff',
        'meta_value' => $slug,
        'fields'     => [ 'ID', 'user_email', 'display_name' ],
    ] );
    foreach ( $by_slug as $u ) $found[ $u->ID ] = $u;

    $term = get_term_by( 'slug', $slug, 'sl_agence_promo' );
    if ( $term && ! is_wp_error( $term ) ) {
        $by_name = get_users( [
            'meta_key'   => 'sl_agence_assignee',
            'meta_value' => $term->name,
            'fields'     => [ 'ID', 'user_email', 'display_name' ],
        ] );
        foreach ( $by_name as $u ) $found[ $u->ID ] = $u;
    }

    return array_values( $found );
}

/**
 * Commande payee -> prevenir le responsable de l'agence de retrait.
 * Priorite 20 : passe apres slc_ensure_code (prio 10) pour que le code de
 * retrait existe deja et parte dans le message.
 */
add_action( 'woocommerce_payment_complete',        'slc_notify_agence_paid', 20 );
add_action( 'woocommerce_order_status_processing', 'slc_notify_agence_paid', 20 );
function slc_notify_agence_paid( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) return;

    $slug = (string) $order->get_meta( '_sl_collect_agence' );
    if ( $slug === '' ) return;

    // Garde anti-doublon : les deux hooks peuvent tomber sur la meme commande.
    if ( $order->get_meta( '_sl_collect_notif_agence' ) ) return;
    $order->update_meta_data( '_sl_collect_notif_agence', current_time( 'mysql' ) );
    $order->save();

    $users = slc_agence_users( $slug );
    $dest  = [];
    foreach ( $users as $u ) {
        if ( is_email( $u->user_email ) ) $dest[] = $u->user_email;
    }
    // Repli : sans responsable joignable, la commande ne doit pas rester muette.
    if ( ! $dest ) {
        $fallback = get_option( 'slf_email', get_option( 'admin_email' ) );
        if ( is_email( $fallback ) ) $dest[] = $fallback;
    }
    if ( ! $dest ) {
        $order->add_order_note( 'Drop & Collect — aucun responsable joignable pour prévenir l\'agence.' );
        return;
    }

    $agence = slc_agence_name( $slug );
    $code   = (string) $order->get_meta( '_sl_collect_code' );

    $lignes = [];
    foreach ( $order->get_items() as $item ) {
        $lignes[] = '- ' . $item->get_name() . ' x ' . $item->get_quantity();
    }

    $sujet = sprintf( '[%s] Nouvelle commande payée n°%s à préparer', $agence, $order->get_order_number() );
    $corps = implode( "\n", [
        'Une commande vient d\'être payée pour un retrait à ' . $agence . '.',
        '',
        'Commande  : n°' . $order->get_order_number(),
        'Code      : ' . ( $code !== '' ? $code : '(en cours de génération)' ),
        'Client    : ' . trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
        'Téléphone : ' . $order->get_billing_phone(),
        'Total     : ' . wp_strip_all_tags( $order->get_formatted_order_total() ),
        '',
        'Articles :',
        implode( "\n", $lignes ),
        '',
        'Le stock en ligne a déjà été décompté.',
        'Préparez la commande puis marquez-la « Prête » ici :',
        admin_url( 'admin.php?page=sl-collect' ),
        '',
        'Sans retrait sous 72 h, la commande est annulée automatiquement et le stock remis en ligne.',
    ] );

    wp_mail( $dest, $sujet, $corps );
    $order->add_order_note( 'Drop & Collect — agence prévenue par email (' . implode( ', ', $dest ) . ').' );
}

/**
 * Pastille sur le menu « Commandes retrait » = nombre de commandes actives
 * de SON agence. Le responsable voit qu'il a du travail sans ouvrir l'ecran.
 */
add_action( 'admin_menu', 'slc_menu_bubble', 99 );
function slc_menu_bubble() {
    global $menu;
    if ( ! is_array( $menu ) ) return;

    $n = slc_count_active_orders_for_current_user();
    if ( $n < 1 ) return;

    foreach ( $menu as $i => $item ) {
        if ( isset( $item[2] ) && $item[2] === 'sl-collect' ) {
            $menu[ $i ][0] .= ' <span class="awaiting-mod"><span class="pending-count">'
                            . (int) $n . '</span></span>';
            break;
        }
    }
}

/** Commandes actives visibles par l'utilisateur courant (admin = toutes agences). */
function slc_count_active_orders_for_current_user() {
    if ( ! function_exists( 'wc_get_orders' ) ) return 0;

    $args = [
        'status' => slc_active_statuses(),
        'limit'  => 50,
        'return' => 'ids',
    ];

    if ( ! slc_is_admin_user() ) {
        $slug = slc_user_agence_slug();
        if ( $slug === '' ) return 0; // fail-closed, comme le reste de l'ecran
        $args['meta_key']   = '_sl_collect_agence';
        $args['meta_value'] = $slug;
    }

    return count( wc_get_orders( $args ) );
}
