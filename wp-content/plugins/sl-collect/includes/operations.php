<?php
/**
 * Drop & Collect — socle operationnel.
 *
 * Regroupe les statuts de preparation, les creneaux, la reservation de stock,
 * l'escalade agence, les liens de suivi et le registre de remboursements.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* -------------------------------------------------------------------------
 * Cycle de preparation
 * ---------------------------------------------------------------------- */

function slc_operational_status_labels() {
    return [
        'slc-acceptee' => 'Acceptée par l’agence',
        'slc-prep'     => 'En préparation',
        'slc-attente'  => 'Attente du choix client',
    ];
}

add_action( 'init', 'slc_register_operational_statuses', 11 );
function slc_register_operational_statuses() {
    foreach ( slc_operational_status_labels() as $slug => $label ) {
        register_post_status( 'wc-' . $slug, [
            'label'                     => $label,
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                $label . ' <span class="count">(%s)</span>',
                $label . ' <span class="count">(%s)</span>'
            ),
        ] );
    }
}

add_filter( 'wc_order_statuses', 'slc_add_operational_statuses', 30 );
function slc_add_operational_statuses( $statuses ) {
    $out      = [];
    $inserted = false;
    foreach ( $statuses as $key => $label ) {
        $out[ $key ] = $label;
        if ( 'wc-processing' === $key ) {
            foreach ( slc_operational_status_labels() as $slug => $custom_label ) {
                $out[ 'wc-' . $slug ] = $custom_label;
            }
            $inserted = true;
        }
    }
    if ( ! $inserted ) {
        foreach ( slc_operational_status_labels() as $slug => $custom_label ) {
            $out[ 'wc-' . $slug ] = $custom_label;
        }
    }
    return $out;
}

add_filter( 'woocommerce_order_is_paid_statuses', function ( $statuses ) {
    return array_values( array_unique( array_merge( $statuses, [ 'slc-acceptee', 'slc-prep', 'slc-attente' ] ) ) );
}, 20 );

function slc_active_pipeline_statuses() {
    return [ 'pending', 'processing', 'slc-acceptee', 'slc-prep', 'slc-attente', 'sl-prete' ];
}

function slc_order_before_preparation( WC_Order $order ) {
    $status = $order->get_status();
    if ( 'slc-attente' === $status ) {
        $status = (string) $order->get_meta( '_slc_status_before_customer_wait' );
    }
    return in_array( $status, [ 'pending', 'processing', 'slc-acceptee' ], true );
}

function slc_order_is_staff_accessible( WC_Order $order, $user_id = 0 ) {
    $user_id = $user_id ? absint( $user_id ) : get_current_user_id();
    if ( ! $user_id ) return false;
    if ( user_can( $user_id, 'manage_options' ) || user_can( $user_id, 'edit_others_posts' ) ) return true;
    $agency = slc_user_agence_slug( $user_id );
    return $agency !== '' && hash_equals( $agency, (string) $order->get_meta( '_sl_collect_agence' ) );
}

/** Verrou atomique court pour les operations qui deplacent du stock. */
function slc_acquire_order_lock( $order_id, $scope, $ttl = 30 ) {
    $key   = 'slc_lock_' . absint( $order_id ) . '_' . substr( md5( (string) $scope ), 0, 12 );
    $stamp = time();
    if ( add_option( $key, $stamp, '', 'no' ) ) return $key;
    $previous = (int) get_option( $key );
    if ( $previous && $previous < $stamp - max( 10, absint( $ttl ) ) ) {
        delete_option( $key );
        if ( add_option( $key, $stamp, '', 'no' ) ) return $key;
    }
    return false;
}

function slc_release_order_lock( $key ) {
    if ( is_string( $key ) && strpos( $key, 'slc_lock_' ) === 0 ) delete_option( $key );
}

/* -------------------------------------------------------------------------
 * Lien de suivi client signe
 * ---------------------------------------------------------------------- */

function slc_order_tracking_token( WC_Order $order ) {
    $token = (string) $order->get_meta( '_slc_tracking_token' );
    if ( ! preg_match( '/^[a-f0-9]{48}$/', $token ) ) {
        try {
            $token = bin2hex( random_bytes( 24 ) );
        } catch ( Exception $e ) {
            $token = substr( hash( 'sha256', wp_generate_password( 64, true, true ) . microtime( true ) ), 0, 48 );
        }
        $order->update_meta_data( '_slc_tracking_token', $token );
        // Pendant woocommerce_checkout_create_order, l'objet n'a pas encore
        // toujours d'identifiant. WooCommerce persistera alors la meta avec
        // le reste de la commande ; hors checkout on sauvegarde immediatement.
        if ( $order->get_id() ) $order->save();
    }
    return $token;
}

function slc_order_tracking_url( WC_Order $order ) {
    return add_query_arg( [
        'slc_track' => '1',
        'token'     => slc_order_tracking_token( $order ),
    ], home_url( '/' ) );
}

add_action( 'woocommerce_checkout_create_order', function ( $order ) {
    if ( $order instanceof WC_Order ) {
        slc_order_tracking_token( $order );
    }
}, 5 );

function slc_find_order_by_tracking_token( $token ) {
    $token = strtolower( sanitize_text_field( (string) $token ) );
    if ( ! preg_match( '/^[a-f0-9]{48}$/', $token ) ) return false;
    $orders = wc_get_orders( [
        'limit'      => 1,
        'type'       => 'shop_order',
        'meta_key'   => '_slc_tracking_token',
        'meta_value' => $token,
        'return'     => 'objects',
    ] );
    return ! empty( $orders ) ? $orders[0] : false;
}

/* -------------------------------------------------------------------------
 * Creneaux et capacite agence
 * ---------------------------------------------------------------------- */

function slc_pickup_slots() {
    return apply_filters( 'slc_pickup_slots', [
        'asap'  => 'Dès que possible',
        '09-11' => '09 h – 11 h',
        '11-13' => '11 h – 13 h',
        '13-15' => '13 h – 15 h',
        '15-17' => '15 h – 17 h',
        '17-19' => '17 h – 19 h',
    ] );
}

function slc_agence_term( $slug ) {
    $term = get_term_by( 'slug', sanitize_title( (string) $slug ), 'sl_agence_promo' );
    return ( $term && ! is_wp_error( $term ) ) ? $term : false;
}

function slc_agence_slot_capacity( $slug ) {
    $term = slc_agence_term( $slug );
    $n    = $term ? absint( get_term_meta( $term->term_id, '_slc_slot_capacity', true ) ) : 0;
    return $n > 0 ? $n : 12;
}

function slc_agence_prep_minutes( $slug ) {
    $term = slc_agence_term( $slug );
    $n    = $term ? absint( get_term_meta( $term->term_id, '_slc_prep_minutes', true ) ) : 0;
    return $n > 0 ? $n : 90;
}

function slc_pickup_date_is_valid( $date ) {
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date ) ) return false;
    try {
        $tz    = wp_timezone();
        $day   = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, $tz );
        $errors = DateTimeImmutable::getLastErrors();
        if ( ! $day || ( is_array( $errors ) && ( $errors['warning_count'] || $errors['error_count'] ) ) || $day->format( 'Y-m-d' ) !== $date ) return false;
        $today = new DateTimeImmutable( current_time( 'Y-m-d' ) . ' 00:00:00', $tz );
        $last  = $today->modify( '+7 days' );
        return $day >= $today && $day <= $last;
    } catch ( Exception $e ) {
        return false;
    }
}

function slc_slot_order_count( $agency, $date, $slot, $exclude_order_id = 0 ) {
    global $wpdb;
    static $cache = [];
    $agency = sanitize_title( (string) $agency );
    $date   = sanitize_text_field( (string) $date );
    $slot   = sanitize_key( (string) $slot );
    $cache_key = implode( '|', [ $agency, $date, $slot, absint( $exclude_order_id ) ] );
    if ( isset( $cache[ $cache_key ] ) ) return $cache[ $cache_key ];

    $statuses = array_map( function ( $status ) {
        return 'wc-' . preg_replace( '/^wc-/', '', $status );
    }, slc_active_pipeline_statuses() );
    $placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
    $params       = $statuses;

    if ( slc_hpos() ) {
        $sql = "SELECT COUNT(DISTINCT o.id)
                FROM {$wpdb->prefix}wc_orders o
                JOIN {$wpdb->prefix}wc_orders_meta ma ON ma.order_id=o.id AND ma.meta_key='_sl_collect_agence'
                JOIN {$wpdb->prefix}wc_orders_meta md ON md.order_id=o.id AND md.meta_key='_slc_pickup_date'
                JOIN {$wpdb->prefix}wc_orders_meta ms ON ms.order_id=o.id AND ms.meta_key='_slc_pickup_slot'
                WHERE o.type='shop_order' AND o.status IN ($placeholders)
                  AND ma.meta_value=%s AND md.meta_value=%s AND ms.meta_value=%s";
        $id_column = 'o.id';
    } else {
        $sql = "SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} p
                JOIN {$wpdb->postmeta} ma ON ma.post_id=p.ID AND ma.meta_key='_sl_collect_agence'
                JOIN {$wpdb->postmeta} md ON md.post_id=p.ID AND md.meta_key='_slc_pickup_date'
                JOIN {$wpdb->postmeta} ms ON ms.post_id=p.ID AND ms.meta_key='_slc_pickup_slot'
                WHERE p.post_type='shop_order' AND p.post_status IN ($placeholders)
                  AND ma.meta_value=%s AND md.meta_value=%s AND ms.meta_value=%s";
        $id_column = 'p.ID';
    }
    array_push( $params, $agency, $date, $slot );
    if ( $exclude_order_id ) {
        $sql      .= " AND {$id_column}<>%d";
        $params[] = absint( $exclude_order_id );
    }
    $cache[ $cache_key ] = (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
    return $cache[ $cache_key ];
}

function slc_pickup_slot_available( $agency, $date, $slot, $exclude_order_id = 0 ) {
    if ( ! slc_pickup_date_is_valid( $date ) || ! isset( slc_pickup_slots()[ $slot ] ) ) return false;
    if ( $date === current_time( 'Y-m-d' ) && 'asap' !== $slot && preg_match( '/^(\d{2})-(\d{2})$/', $slot, $hours ) ) {
        $end = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $date . ' ' . $hours[2] . ':00', wp_timezone() );
        $ready_at = ( new DateTimeImmutable( 'now', wp_timezone() ) )->modify( '+' . slc_agence_prep_minutes( $agency ) . ' minutes' );
        if ( ! $end || $ready_at > $end ) return false;
    }
    return slc_slot_order_count( $agency, $date, $slot, $exclude_order_id ) < slc_agence_slot_capacity( $agency );
}

function slc_pickup_slot_label( WC_Order $order ) {
    $date  = (string) $order->get_meta( '_slc_pickup_date' );
    $slot  = (string) $order->get_meta( '_slc_pickup_slot' );
    $slots = slc_pickup_slots();
    if ( $date === '' && $slot === '' ) return 'Dès que la commande est prête';
    $label = isset( $slots[ $slot ] ) ? $slots[ $slot ] : $slot;
    if ( $date !== '' ) {
        $time = strtotime( $date . ' 12:00:00' );
        $date = $time ? date_i18n( 'd/m/Y', $time ) : $date;
    }
    return trim( $date . ( $label !== '' ? ' · ' . $label : '' ), ' ·' );
}

/* -------------------------------------------------------------------------
 * Reservation de stock pour la confirmation differee
 * ---------------------------------------------------------------------- */

function slc_reservation_minutes() {
    $minutes = absint( get_option( 'sl_collect_reservation_minutes', 60 ) );
    return max( 15, min( 240, $minutes ?: 60 ) );
}

add_action( 'woocommerce_checkout_order_processed', 'slc_reserve_pending_order_stock', 40, 3 );
function slc_reserve_pending_order_stock( $order_id, $posted_data = [], $order = null ) {
    if ( ! $order instanceof WC_Order ) $order = wc_get_order( $order_id );
    if ( ! $order || 'slc_call' !== $order->get_payment_method() ) return;
    if ( $order->get_meta( '_slc_stock_reservation_expires_at' ) ) return;

    wc_reduce_stock_levels( $order_id );
    $expires = time() + slc_reservation_minutes() * MINUTE_IN_SECONDS;
    $order->update_meta_data( '_slc_stock_reservation_expires_at', $expires );
    $order->update_meta_data( '_slc_stock_reserved_at', time() );
    $order->save();
    if ( ! wp_next_scheduled( 'slc_expire_stock_reservation', [ (int) $order_id ] ) ) {
        wp_schedule_single_event( $expires, 'slc_expire_stock_reservation', [ (int) $order_id ] );
    }
    $order->add_order_note( sprintf(
        'Drop & Collect — stock réservé pendant %d minutes dans l’attente du paiement.',
        slc_reservation_minutes()
    ) );
}

function slc_ack_minutes() {
    $minutes = absint( get_option( 'sl_collect_ack_minutes', 15 ) );
    return max( 5, min( 120, $minutes ?: 15 ) );
}

function slc_set_ack_deadline( $order_id ) {
    $order = $order_id instanceof WC_Order ? $order_id : wc_get_order( $order_id );
    if ( ! $order || ! $order->get_meta( '_sl_collect_agence' ) ) return;
    if ( $order->get_meta( '_slc_ack_deadline' ) || $order->get_meta( '_slc_accepted_at' ) ) return;
    $deadline = time() + slc_ack_minutes() * MINUTE_IN_SECONDS;
    $order->update_meta_data( '_slc_ack_deadline', $deadline );
    $order->delete_meta_data( '_slc_stock_reservation_expires_at' );
    $order->save();
    $reservation_event = wp_next_scheduled( 'slc_expire_stock_reservation', [ (int) $order->get_id() ] );
    if ( $reservation_event ) wp_unschedule_event( $reservation_event, 'slc_expire_stock_reservation', [ (int) $order->get_id() ] );
    if ( ! wp_next_scheduled( 'slc_check_order_ack', [ (int) $order->get_id() ] ) ) {
        wp_schedule_single_event( $deadline, 'slc_check_order_ack', [ (int) $order->get_id() ] );
    }
}
add_action( 'woocommerce_payment_complete', 'slc_set_ack_deadline', 15 );
add_action( 'woocommerce_order_status_processing', 'slc_set_ack_deadline', 15 );

function slc_expire_reserved_order( WC_Order $order, $now = 0 ) {
    $now     = $now ?: time();
    $expires = (int) $order->get_meta( '_slc_stock_reservation_expires_at' );
    if ( ! $order->has_status( [ 'pending', 'failed' ] ) || 'slc_call' !== $order->get_payment_method() || ! $expires || $expires > $now ) return false;
    $order->update_status( 'cancelled', 'Drop & Collect — réservation expirée sans paiement.' );
    wc_maybe_increase_stock_levels( $order->get_id() );
    $message = sprintf(
        'Votre commande n°%s a expiré faute de paiement. Vous pouvez recommencer votre panier sur Santa Lucia.',
        $order->get_order_number()
    );
    slc_notify_customer( $order, 'Réservation de commande expirée', $message );
    return true;
}

add_action( 'slc_expire_stock_reservation', function ( $order_id ) {
    $order = wc_get_order( absint( $order_id ) );
    if ( $order ) slc_expire_reserved_order( $order );
} );

function slc_escalate_unaccepted_order( WC_Order $order, $now = 0 ) {
    $now      = $now ?: time();
    $deadline = (int) $order->get_meta( '_slc_ack_deadline' );
    if ( ! $order->has_status( 'processing' ) || ! $deadline || $deadline > $now || $order->get_meta( '_slc_ack_escalated_at' ) ) return false;

    $order->update_meta_data( '_slc_ack_escalated_at', $now );
    $order->save();
    $agency = (string) $order->get_meta( '_sl_collect_agence' );
    $dest   = [];
    if ( function_exists( 'slc_agence_users' ) ) {
        foreach ( slc_agence_users( $agency ) as $user ) {
            if ( is_email( $user->user_email ) ) $dest[] = $user->user_email;
        }
    }
    $admin_email = get_option( 'admin_email' );
    if ( is_email( $admin_email ) ) $dest[] = $admin_email;
    $dest = array_values( array_unique( $dest ) );
    if ( $dest ) {
        wp_mail(
            $dest,
            sprintf( '[URGENT] Commande n°%s non prise en charge', $order->get_order_number() ),
            sprintf(
                "La commande payée n°%s attend depuis plus de %d minutes à l’agence %s.\n\n%s",
                $order->get_order_number(),
                slc_ack_minutes(),
                slc_agence_name( $agency ),
                admin_url( 'admin.php?page=sl-collect' )
            )
        );
    }
    $order->add_order_note( 'Drop & Collect — alerte d’escalade : commande non prise en charge à temps.' );
    return true;
}

add_action( 'slc_check_order_ack', function ( $order_id ) {
    $order = wc_get_order( absint( $order_id ) );
    if ( $order ) slc_escalate_unaccepted_order( $order );
} );

add_action( 'sl_collect_cron', 'slc_operational_cron', 5 );
function slc_operational_cron() {
    $now = time();

    // Libere les paniers reserves qui n'ont pas ete payes a temps.
    foreach ( slc_order_ids( '', [ 'pending', 'failed' ], 500 ) as $order_id ) {
        $order = wc_get_order( $order_id );
        if ( $order ) slc_expire_reserved_order( $order, $now );
    }

    // Escalade les commandes payees qui n'ont pas ete acceptees par l'agence.
    foreach ( slc_order_ids( '', [ 'processing' ], 500 ) as $order_id ) {
        $order = wc_get_order( $order_id );
        if ( $order ) slc_escalate_unaccepted_order( $order, $now );
    }
}

/* -------------------------------------------------------------------------
 * Remboursements assistes et ajustements de stock
 * ---------------------------------------------------------------------- */

function slc_refund_ledger( WC_Order $order ) {
    $rows = $order->get_meta( '_slc_refund_ledger' );
    return is_array( $rows ) ? $rows : [];
}

function slc_add_refund_due( WC_Order $order, $amount, $reason, $item_id = 0 ) {
    $amount = round( max( 0, (float) $amount ), wc_get_price_decimals() );
    if ( $amount <= 0 ) return '';
    $rows = slc_refund_ledger( $order );
    $id   = wp_generate_uuid4();
    $rows[] = [
        'id'         => $id,
        'amount'     => $amount,
        'reason'     => sanitize_text_field( (string) $reason ),
        'item_id'    => absint( $item_id ),
        'status'     => 'pending',
        'created_at' => time(),
        'created_by' => get_current_user_id(),
        'reference'  => '',
    ];
    $order->update_meta_data( '_slc_refund_ledger', $rows );
    $order->save();
    $order->add_order_note( sprintf(
        'Drop & Collect — remboursement à traiter : %s (%s).',
        wp_strip_all_tags( wc_price( $amount, [ 'currency' => $order->get_currency() ] ) ),
        sanitize_text_field( (string) $reason )
    ) );
    return $id;
}

function slc_refund_due_total( WC_Order $order ) {
    $total = 0.0;
    foreach ( slc_refund_ledger( $order ) as $row ) {
        if ( 'pending' === ( $row['status'] ?? '' ) ) $total += (float) ( $row['amount'] ?? 0 );
    }
    return round( $total, wc_get_price_decimals() );
}

function slc_mark_refunds_done( WC_Order $order, $reference ) {
    $rows      = slc_refund_ledger( $order );
    $reference = sanitize_text_field( (string) $reference );
    $changed   = false;
    foreach ( $rows as &$row ) {
        if ( 'pending' !== ( $row['status'] ?? '' ) ) continue;
        $row['status']       = 'done';
        $row['done_at']      = time();
        $row['done_by']      = get_current_user_id();
        $row['reference']    = $reference;
        $changed             = true;
    }
    unset( $row );
    if ( $changed ) {
        $order->update_meta_data( '_slc_refund_ledger', $rows );
        $order->save();
        $order->add_order_note( 'Drop & Collect — remboursement marqué traité. Référence : ' . ( $reference ?: 'non renseignée' ) . '.' );
    }
    return $changed;
}

function slc_order_stock_reduced( WC_Order $order ) {
    return in_array( strtolower( (string) $order->get_meta( '_order_stock_reduced' ) ), [ '1', 'yes', 'true' ], true );
}

function slc_restore_item_stock( WC_Order $order, WC_Order_Item_Product $item ) {
    if ( ! slc_order_stock_reduced( $order ) ) return;
    $product = $item->get_product();
    if ( ! $product || ! $product->managing_stock() ) return;
    $qty = (int) $item->get_meta( '_reduced_stock', true );
    if ( $qty <= 0 ) $qty = (int) $item->get_quantity();
    if ( $qty <= 0 ) return;
    wc_update_product_stock( $product, $qty, 'increase' );
    $item->delete_meta_data( '_reduced_stock' );
    $item->save();
}

function slc_reduce_replacement_stock( WC_Order $order, WC_Order_Item_Product $item ) {
    if ( ! slc_order_stock_reduced( $order ) ) return true;
    $product = $item->get_product();
    $qty     = (int) $item->get_quantity();
    if ( ! $product || ! $product->managing_stock() || $qty <= 0 ) return true;
    if ( ! $product->has_enough_stock( $qty ) ) return false;
    wc_update_product_stock( $product, $qty, 'decrease' );
    $item->update_meta_data( '_reduced_stock', $qty );
    $item->save();
    return true;
}

/* -------------------------------------------------------------------------
 * Notifications generiques
 * ---------------------------------------------------------------------- */

function slc_notify_customer( WC_Order $order, $subject, $message, $sms = true ) {
    if ( $order->get_billing_email() ) wp_mail( $order->get_billing_email(), $subject, $message );
    $sms_allowed = ! function_exists( 'slc_sms_enabled' ) || slc_sms_enabled();
    if ( $sms && $sms_allowed && function_exists( 'slc_send_sms' ) && $order->get_billing_phone() ) {
        $result = slc_send_sms( $order->get_billing_phone(), $message );
        $order->add_order_note( is_wp_error( $result )
            ? 'Notification SMS opérationnelle non envoyée : ' . $result->get_error_message()
            : 'Notification SMS opérationnelle envoyée au client.' );
    }
}

function slc_notify_agency_event( WC_Order $order, $subject, $message ) {
    $dest   = [];
    $agency = (string) $order->get_meta( '_sl_collect_agence' );
    if ( function_exists( 'slc_agence_users' ) ) {
        foreach ( slc_agence_users( $agency ) as $user ) {
            if ( is_email( $user->user_email ) ) $dest[] = $user->user_email;
        }
    }
    if ( ! $dest && is_email( get_option( 'admin_email' ) ) ) $dest[] = get_option( 'admin_email' );
    if ( $dest ) wp_mail( array_values( array_unique( $dest ) ), $subject, $message );
}
