<?php
/**
 * Outils agence : cycle de preparation, substitutions, remboursements et SAV.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function slc_substitution_rows( WC_Order $order ) {
    $rows = $order->get_meta( '_slc_substitutions' );
    return is_array( $rows ) ? $rows : [];
}

function slc_pending_substitution_for_item( WC_Order $order, $item_id ) {
    foreach ( slc_substitution_rows( $order ) as $row ) {
        if ( absint( $row['item_id'] ?? 0 ) === absint( $item_id ) && 'pending' === ( $row['status'] ?? '' ) ) {
            return $row;
        }
    }
    return false;
}

/** Produits simples, disponibles et pas plus chers que la ligne remplacee. */
function slc_substitution_candidates( WC_Order_Item_Product $item, WC_Order $order = null ) {
    static $cache = [];
    $original = $item->get_product();
    if ( ! $original ) return [];
    $base_id  = $original->is_type( 'variation' ) ? $original->get_parent_id() : $original->get_id();
    $term_ids = wc_get_product_term_ids( $base_id, 'product_cat' );
    $slugs    = [];
    foreach ( $term_ids as $term_id ) {
        $term = get_term( $term_id, 'product_cat' );
        if ( $term && ! is_wp_error( $term ) ) $slugs[] = $term->slug;
    }
    $args = [
        'limit'        => 20,
        'status'       => 'publish',
        'stock_status' => 'instock',
        'exclude'      => [ $base_id ],
        'orderby'      => 'popularity',
        'order'        => 'DESC',
        'return'       => 'objects',
    ];
    if ( $slugs ) $args['category'] = $slugs;

    $qty       = max( 1, (int) $item->get_quantity() );
    $max_total = max( 0, (float) $item->get_total() );
    $agency    = $order ? (string) $order->get_meta( '_sl_collect_agence' ) : '';
    $cache_key = implode( '|', [ $base_id, $qty, $max_total, $agency ] );
    if ( isset( $cache[ $cache_key ] ) ) return $cache[ $cache_key ];
    $out       = [];
    foreach ( wc_get_products( $args ) as $candidate ) {
        if ( ! $candidate instanceof WC_Product || ! $candidate->is_type( 'simple' ) || ! $candidate->is_purchasable() ) continue;
        if ( $agency && function_exists( 'sl_bp_product_agency' ) ) {
            $candidate_agency = (string) sl_bp_product_agency( $candidate->get_id() );
            if ( $candidate_agency !== '' && $candidate_agency !== $agency ) continue;
        }
        if ( ! $candidate->has_enough_stock( $qty ) ) continue;
        $total = (float) $candidate->get_price() * $qty;
        if ( $total < 0 || $total > $max_total + 0.01 ) continue;
        $out[] = $candidate;
        if ( count( $out ) >= 8 ) break;
    }
    return $cache[ $cache_key ] = $out;
}

/** Controle affiche dans la colonne Gestion de chaque article. */
function slc_admin_substitution_control( WC_Order $order, $item_id, WC_Order_Item_Product $item ) {
    $pending = slc_pending_substitution_for_item( $order, $item_id );
    if ( $pending ) {
        echo '<span class="slc-status-meta" style="color:#8a4b00;">Choix envoyé au client : <strong>'
            . esc_html( $pending['replacement_name'] ?: 'retrait et remboursement' ) . '</strong></span>';
        return;
    }
    if ( ! in_array( $order->get_status(), [ 'processing', 'slc-acceptee', 'slc-prep' ], true ) ) return;

    $candidates = slc_substitution_candidates( $item, $order );
    ?>
    <details style="margin-top:7px;">
        <summary style="cursor:pointer;color:#2271b1;">Produit indisponible</summary>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:block;margin-top:8px;min-width:260px;">
            <?php wp_nonce_field( 'slc_action_' . $order->get_id() ); ?>
            <input type="hidden" name="action" value="slc_propose_substitution">
            <input type="hidden" name="order_id" value="<?php echo (int) $order->get_id(); ?>">
            <input type="hidden" name="item_id" value="<?php echo (int) $item_id; ?>">
            <select name="replacement_product_id" style="width:100%;max-width:330px;" required>
                <option value="">Choisir une solution…</option>
                <?php foreach ( $candidates as $candidate ) : ?>
                    <option value="<?php echo (int) $candidate->get_id(); ?>"><?php echo esc_html( $candidate->get_name() . ' — ' . wp_strip_all_tags( wc_price( $candidate->get_price() * max( 1, $item->get_quantity() ) ) ) ); ?></option>
                <?php endforeach; ?>
                <option value="0">Aucun remplacement — retirer et rembourser</option>
            </select>
            <textarea name="message" rows="2" style="width:100%;max-width:330px;margin-top:5px;" placeholder="Précision facultative pour le client"></textarea>
            <button class="button" style="margin-top:5px;">Envoyer le choix au client</button>
        </form>
    </details>
    <?php
}

/** Encadre les informations operationnelles sous le detail des articles. */
function slc_admin_order_operations_panel( WC_Order $order ) {
    $accepted = (int) $order->get_meta( '_slc_accepted_at' );
    $started  = (int) $order->get_meta( '_slc_preparation_at' );
    $ready    = (int) $order->get_meta( '_sl_collect_prete_at' );
    $handed   = (int) $order->get_meta( '_sl_collect_retire_at' );
    $delegate = (string) $order->get_meta( '_slc_collector_name' );
    $claims   = $order->get_meta( '_slc_claims' );
    $claims   = is_array( $claims ) ? $claims : [];
    $substitutions = slc_substitution_rows( $order );
    $due      = function_exists( 'slc_refund_due_total' ) ? slc_refund_due_total( $order ) : 0;
    $staff_name = static function ( $user_id ) {
        $user = $user_id ? get_userdata( absint( $user_id ) ) : false;
        return $user ? $user->display_name : '—';
    };
    $paid_date  = $order->get_date_paid() ?: $order->get_date_created();
    $created_at = $paid_date ? $paid_date->getTimestamp() : 0;
    $current_status = $order->get_status();
    $ack_end    = $accepted ?: ( 'processing' === $current_status ? time() : 0 );
    $ack_used   = $created_at && $ack_end ? max( 0, (int) ceil( ( $ack_end - $created_at ) / MINUTE_IN_SECONDS ) ) : 0;
    $waiting_from_prep = 'slc-attente' === $current_status && 'slc-prep' === (string) $order->get_meta( '_slc_status_before_customer_wait' );
    $prep_end   = $ready ?: ( in_array( $current_status, [ 'slc-prep' ], true ) || $waiting_from_prep ? time() : 0 );
    $prep_used  = $started && $prep_end ? max( 0, (int) ceil( ( $prep_end - $started ) / MINUTE_IN_SECONDS ) ) : 0;
    $prep_sla   = slc_agence_prep_minutes( $order->get_meta( '_sl_collect_agence' ) );
    $pickup_date = (string) $order->get_meta( '_slc_pickup_date' );
    $pickup_slot = (string) $order->get_meta( '_slc_pickup_slot' );
    $slot_count  = ( $pickup_date && $pickup_slot ) ? slc_slot_order_count( $order->get_meta( '_sl_collect_agence' ), $pickup_date, $pickup_slot ) : 0;
    $slot_capacity = slc_agence_slot_capacity( $order->get_meta( '_sl_collect_agence' ) );
    ?>
    <div style="margin-top:10px;padding:10px 12px;background:#fff;border:1px solid #dcdcde;border-radius:6px;">
        <strong>Organisation du retrait</strong>
        <p style="margin:6px 0 0;"><strong>Créneau :</strong> <?php echo esc_html( slc_pickup_slot_label( $order ) ); ?>
            <?php if ( $pickup_date && $pickup_slot ) : ?> · <strong>Charge :</strong> <?php echo (int) $slot_count; ?>/<?php echo (int) $slot_capacity; ?><?php endif; ?>
            · <strong>Préparation estimée :</strong> <?php echo (int) slc_agence_prep_minutes( $order->get_meta( '_sl_collect_agence' ) ); ?> min</p>
        <?php if ( $delegate !== '' ) : ?>
            <p style="margin:4px 0 0;"><strong>Mandataire autorisé :</strong> <?php echo esc_html( $delegate ); ?> · <?php echo esc_html( $order->get_meta( '_slc_collector_phone' ) ); ?></p>
        <?php endif; ?>
        <ul style="margin:8px 0 0 18px;color:#50575e;">
            <li>Prise en charge : <?php echo $accepted ? esc_html( date_i18n( 'd/m/Y H:i', $accepted ) . ' · ' . $staff_name( $order->get_meta( '_slc_accepted_by' ) ) ) : ( 'processing' === $current_status ? 'en attente' : 'non tracée' ); ?><?php if ( $ack_used ) : ?> · <?php echo (int) $ack_used; ?>/<?php echo (int) slc_ack_minutes(); ?> min<?php endif; ?></li>
            <li>Préparation : <?php echo $started ? esc_html( date_i18n( 'd/m/Y H:i', $started ) . ' · ' . $staff_name( $order->get_meta( '_slc_preparation_by' ) ) ) : ( in_array( $current_status, [ 'slc-acceptee', 'slc-prep' ], true ) ? 'non démarrée' : 'non tracée' ); ?><?php if ( $prep_used ) : ?> · <strong style="color:<?php echo $prep_used > $prep_sla ? '#b32d2e' : '#16834b'; ?>"><?php echo (int) $prep_used; ?>/<?php echo (int) $prep_sla; ?> min</strong><?php endif; ?></li>
            <li>Prête : <?php echo $ready ? esc_html( date_i18n( 'd/m/Y H:i', $ready ) . ' · ' . $staff_name( $order->get_meta( '_slc_prete_by' ) ) ) : '—'; ?></li>
            <li>Remise : <?php echo $handed ? esc_html( date_i18n( 'd/m/Y H:i', $handed ) . ' · ' . $staff_name( $order->get_meta( '_sl_collect_retire_by' ) ) ) : '—'; ?></li>
        </ul>
        <p style="margin:6px 0 0;"><a href="<?php echo esc_url( slc_order_tracking_url( $order ) ); ?>" target="_blank" rel="noopener">Ouvrir le suivi client</a></p>

        <?php foreach ( $substitutions as $substitution ) : ?>
            <p style="margin:7px 0 0;padding:8px;background:#f6f7f7;border-radius:5px;"><strong>Substitution :</strong>
                <?php echo esc_html( $substitution['original_name'] ?? 'Article' ); ?> →
                <?php echo esc_html( ! empty( $substitution['replacement_name'] ) ? $substitution['replacement_name'] : 'retrait/remboursement' ); ?>
                · <?php echo esc_html( [ 'pending' => 'réponse attendue', 'accepted' => 'acceptée', 'refunded' => 'retirée', 'unavailable' => 'à reproposer' ][ $substitution['status'] ?? '' ] ?? ( $substitution['status'] ?? '' ) ); ?>
            </p>
        <?php endforeach; ?>

        <?php if ( $due > 0 ) : ?>
            <div style="margin-top:10px;padding:10px;background:#fff4ce;border-left:4px solid #dba617;">
                <strong>Remboursement à traiter : <?php echo wp_kses_post( wc_price( $due, [ 'currency' => $order->get_currency() ] ) ); ?></strong>
                <?php if ( slc_is_admin_user() ) : ?>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:6px;margin-top:7px;">
                        <?php wp_nonce_field( 'slc_action_' . $order->get_id() ); ?>
                        <input type="hidden" name="action" value="slc_mark_refund_done">
                        <input type="hidden" name="order_id" value="<?php echo (int) $order->get_id(); ?>">
                        <input type="text" name="reference" placeholder="Référence opérateur / caisse" required>
                        <button class="button">Marquer remboursé</button>
                    </form>
                <?php else : ?><p style="margin:5px 0 0;color:#646970;">La confirmation financière doit être faite par un gestionnaire.</p><?php endif; ?>
            </div>
        <?php endif; ?>

        <?php foreach ( $claims as $claim ) : ?>
            <div style="margin-top:10px;padding:10px;background:#f6f7f7;border-left:4px solid <?php echo 'open' === ( $claim['status'] ?? '' ) ? '#d63638' : '#16834b'; ?>;">
                <strong>Réclamation <?php echo esc_html( 'open' === ( $claim['status'] ?? '' ) ? 'ouverte' : 'traitée' ); ?></strong>
                — <?php echo esc_html( $claim['reason'] ?? '' ); ?>
                <?php if ( ! empty( $claim['item_name'] ) ) : ?> · <?php echo esc_html( $claim['item_name'] ); ?><?php endif; ?>
                <?php if ( ! empty( $claim['details'] ) ) : ?><br><?php echo esc_html( $claim['details'] ); ?><?php endif; ?>
                <?php if ( 'open' === ( $claim['status'] ?? '' ) ) : ?>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:6px;margin-top:7px;">
                        <?php wp_nonce_field( 'slc_action_' . $order->get_id() ); ?>
                        <input type="hidden" name="action" value="slc_resolve_claim">
                        <input type="hidden" name="order_id" value="<?php echo (int) $order->get_id(); ?>">
                        <input type="hidden" name="claim_id" value="<?php echo esc_attr( $claim['id'] ?? '' ); ?>">
                        <input type="text" name="resolution" placeholder="Solution apportée" required>
                        <button class="button">Clôturer</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

/* -------------------------------------------------------------------------
 * Actions du cycle de preparation
 * ---------------------------------------------------------------------- */

add_action( 'admin_post_slc_accept_order', function () {
    $order = slc_check_action_order();
    if ( ! $order->has_status( 'processing' ) ) slc_redirect_back( 'err' );
    $user = wp_get_current_user();
    $order->update_meta_data( '_slc_accepted_at', time() );
    $order->update_meta_data( '_slc_accepted_by', $user->ID );
    $order->delete_meta_data( '_slc_ack_deadline' );
    $order->update_status( 'slc-acceptee', 'Drop & Collect — commande acceptée par ' . $user->user_login . '.' );
    $ack_event = wp_next_scheduled( 'slc_check_order_ack', [ (int) $order->get_id() ] );
    if ( $ack_event ) wp_unschedule_event( $ack_event, 'slc_check_order_ack', [ (int) $order->get_id() ] );
    slc_redirect_back( 'accepted' );
} );

add_action( 'admin_post_slc_start_preparation', function () {
    $order = slc_check_action_order();
    if ( ! $order->has_status( 'slc-acceptee' ) ) slc_redirect_back( 'err' );
    $user = wp_get_current_user();
    $order->update_meta_data( '_slc_preparation_at', time() );
    $order->update_meta_data( '_slc_preparation_by', $user->ID );
    $order->update_status( 'slc-prep', 'Drop & Collect — préparation commencée par ' . $user->user_login . '.' );
    slc_redirect_back( 'preparing' );
} );

/* -------------------------------------------------------------------------
 * Proposition et application d'une substitution
 * ---------------------------------------------------------------------- */

add_action( 'admin_post_slc_propose_substitution', function () {
    $order   = slc_check_action_order();
    $item_id = absint( $_POST['item_id'] ?? 0 );
    $items   = $order->get_items( 'line_item' );
    $item    = $items[ $item_id ] ?? false;
    if ( ! $item instanceof WC_Order_Item_Product || ! in_array( $order->get_status(), [ 'processing', 'slc-acceptee', 'slc-prep' ], true ) ) {
        slc_redirect_back( 'substitution_err' );
    }
    if ( slc_pending_substitution_for_item( $order, $item_id ) ) slc_redirect_back( 'substitution_err' );

    $replacement_id = absint( $_POST['replacement_product_id'] ?? 0 );
    $replacement     = $replacement_id ? wc_get_product( $replacement_id ) : false;
    $qty             = max( 1, (int) $item->get_quantity() );
    $new_total       = $replacement ? (float) $replacement->get_price() * $qty : 0.0;
    $replacement_agency = ( $replacement && function_exists( 'sl_bp_product_agency' ) ) ? (string) sl_bp_product_agency( $replacement_id ) : '';
    if ( $replacement_id && ( ! $replacement || ! $replacement->is_type( 'simple' ) || ! $replacement->is_purchasable() || ! $replacement->has_enough_stock( $qty ) || $new_total > (float) $item->get_total() + 0.01 || ( $replacement_agency !== '' && $replacement_agency !== (string) $order->get_meta( '_sl_collect_agence' ) ) ) ) {
        slc_redirect_back( 'substitution_err' );
    }

    $rows   = slc_substitution_rows( $order );
    $rows[] = [
        'id'                     => wp_generate_uuid4(),
        'item_id'                => $item_id,
        'original_product_id'    => $item->get_product_id(),
        'original_name'          => $item->get_name(),
        'original_qty'           => $qty,
        'original_total'         => (float) $item->get_total(),
        'replacement_product_id' => $replacement_id,
        'replacement_name'       => $replacement ? $replacement->get_name() : '',
        'replacement_total'      => $new_total,
        'message'                => sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) ),
        'status'                 => 'pending',
        'created_at'             => time(),
        'created_by'             => get_current_user_id(),
    ];
    $order->update_meta_data( '_slc_substitutions', $rows );
    $order->update_meta_data( '_slc_status_before_customer_wait', $order->get_status() );
    $order->update_status( 'slc-attente', 'Drop & Collect — attente du choix client pour un article indisponible.' );
    $message = 'Un article de votre commande n°' . $order->get_order_number() . ' est indisponible. Choisissez le remplacement ou le remboursement ici : ' . slc_order_tracking_url( $order );
    slc_notify_customer( $order, 'Choix nécessaire pour votre commande Santa Lucia', $message );
    slc_redirect_back( 'substitution_sent' );
} );

/** Applique la decision signee du client. Retourne true ou WP_Error. */
function slc_apply_substitution_response( WC_Order $order, $substitution_id, $decision ) {
    $lock = slc_acquire_order_lock( $order->get_id(), 'substitution', 45 );
    if ( ! $lock ) return new WP_Error( 'slc_substitution_busy', 'Cette commande est déjà en cours de mise à jour. Réessayez dans quelques secondes.' );
    try {
    $rows  = slc_substitution_rows( $order );
    $index = null;
    foreach ( $rows as $i => $row ) {
        if ( hash_equals( (string) ( $row['id'] ?? '' ), (string) $substitution_id ) && 'pending' === ( $row['status'] ?? '' ) ) {
            $index = $i;
            break;
        }
    }
    if ( null === $index ) return new WP_Error( 'slc_substitution_missing', 'Cette proposition a déjà été traitée ou n’existe plus.' );
    if ( ! in_array( $decision, [ 'accept', 'reject' ], true ) ) return new WP_Error( 'slc_substitution_decision', 'Décision invalide.' );

    $row     = $rows[ $index ];
    $expire_proposal = static function ( $message ) use ( &$rows, $index, $order ) {
        $rows[ $index ]['status']       = 'unavailable';
        $rows[ $index ]['responded_at'] = time();
        $order->update_meta_data( '_slc_substitutions', $rows );
        $restore = (string) $order->get_meta( '_slc_status_before_customer_wait' );
        $order->delete_meta_data( '_slc_status_before_customer_wait' );
        if ( ! in_array( $restore, [ 'processing', 'slc-acceptee', 'slc-prep' ], true ) ) $restore = 'slc-prep';
        $order->update_status( $restore, 'Drop & Collect — proposition devenue indisponible, nouveau choix requis.' );
        $order->save();
        slc_notify_agency_event( $order, 'Nouvelle proposition requise — commande n°' . $order->get_order_number(), $message );
        return new WP_Error( 'slc_substitution_stock', $message );
    };
    $item_id = absint( $row['item_id'] ?? 0 );
    $item    = $order->get_item( $item_id );
    if ( ! $item instanceof WC_Order_Item_Product ) return $expire_proposal( 'La ligne concernée n’est plus disponible.' );
    $old_total       = (float) $order->get_total();
    $replacement_id  = absint( $row['replacement_product_id'] ?? 0 );
    $use_replacement = 'accept' === $decision && $replacement_id > 0;
    $new_item_id     = 0;

    if ( $use_replacement ) {
        $product = wc_get_product( $replacement_id );
        $qty     = max( 1, absint( $row['original_qty'] ?? 1 ) );
        $product_agency = ( $product && function_exists( 'sl_bp_product_agency' ) ) ? (string) sl_bp_product_agency( $replacement_id ) : '';
        if ( ! $product || ! $product->is_purchasable() || ! $product->has_enough_stock( $qty ) || ( $product_agency !== '' && $product_agency !== (string) $order->get_meta( '_sl_collect_agence' ) ) ) {
            return $expire_proposal( 'Le produit proposé n’est malheureusement plus disponible. L’agence doit faire une nouvelle proposition.' );
        }
        $line_total  = min( (float) $row['original_total'], (float) $product->get_price() * $qty );
        $new_item_id = $order->add_product( $product, $qty, [ 'subtotal' => $line_total, 'total' => $line_total ] );
        $new_item    = $order->get_item( $new_item_id );
        if ( ! $new_item instanceof WC_Order_Item_Product || ! slc_reduce_replacement_stock( $order, $new_item ) ) {
            if ( $new_item_id ) $order->remove_item( $new_item_id );
            return $expire_proposal( 'Impossible de réserver le produit de remplacement. Une nouvelle proposition est nécessaire.' );
        }
    }

    slc_restore_item_stock( $order, $item );
    $order->remove_item( $item_id );
    $order->calculate_totals( true );
    $order->save();
    $difference = max( 0, $old_total - (float) $order->get_total() );
    if ( $difference > 0 ) {
        slc_add_refund_due( $order, $difference, $use_replacement ? 'Différence après substitution' : 'Article indisponible retiré', $item_id );
    }

    $rows[ $index ]['status']       = $use_replacement ? 'accepted' : 'refunded';
    $rows[ $index ]['decision']     = $decision;
    $rows[ $index ]['responded_at'] = time();
    $rows[ $index ]['new_item_id']  = $new_item_id;
    $order->update_meta_data( '_slc_substitutions', $rows );
    $restore = (string) $order->get_meta( '_slc_status_before_customer_wait' );
    $order->delete_meta_data( '_slc_status_before_customer_wait' );

    if ( count( $order->get_items( 'line_item' ) ) === 0 ) {
        if ( $old_total > 0 && $difference <= 0 ) slc_add_refund_due( $order, $old_total, 'Commande sans article après indisponibilité', $item_id );
        $order->update_status( 'cancelled', 'Drop & Collect — dernier article indisponible, annulation après décision client.' );
    } else {
        if ( ! in_array( $restore, [ 'processing', 'slc-acceptee', 'slc-prep' ], true ) ) $restore = 'slc-prep';
        $order->update_status( $restore, 'Drop & Collect — choix client enregistré pour la substitution.' );
    }
    $order->save();
    slc_notify_agency_event( $order, 'Choix client — commande n°' . $order->get_order_number(), 'Le client a répondu à la proposition de remplacement. Consultez la commande dans le tableau Drop & Collect.' );
    return true;
    } finally {
        slc_release_order_lock( $lock );
    }
}

/* -------------------------------------------------------------------------
 * Remboursement et reclamations
 * ---------------------------------------------------------------------- */

add_action( 'admin_post_slc_mark_refund_done', function () {
    if ( ! slc_is_admin_user() ) wp_die( 'Accès refusé : validation réservée à un gestionnaire.' );
    $order = slc_check_action_order();
    $reference = sanitize_text_field( wp_unslash( $_POST['reference'] ?? '' ) );
    if ( $reference === '' || ! slc_mark_refunds_done( $order, $reference ) ) slc_redirect_back( 'refund_err' );
    slc_notify_customer( $order, 'Remboursement traité — commande n°' . $order->get_order_number(), 'Votre remboursement lié à la commande n°' . $order->get_order_number() . ' a été traité. Référence : ' . $reference, false );
    slc_redirect_back( 'refund_done' );
} );

add_action( 'admin_post_slc_resolve_claim', function () {
    $order      = slc_check_action_order();
    $claim_id   = sanitize_text_field( wp_unslash( $_POST['claim_id'] ?? '' ) );
    $resolution = sanitize_text_field( wp_unslash( $_POST['resolution'] ?? '' ) );
    $claims     = $order->get_meta( '_slc_claims' );
    $claims     = is_array( $claims ) ? $claims : [];
    $found      = false;
    foreach ( $claims as &$claim ) {
        if ( hash_equals( (string) ( $claim['id'] ?? '' ), $claim_id ) && 'open' === ( $claim['status'] ?? '' ) ) {
            $claim['status']       = 'resolved';
            $claim['resolution']   = $resolution;
            $claim['resolved_at']  = time();
            $claim['resolved_by']  = get_current_user_id();
            $found = true;
            break;
        }
    }
    unset( $claim );
    if ( ! $found ) slc_redirect_back( 'claim_err' );
    $order->update_meta_data( '_slc_claims', $claims );
    $order->save();
    slc_notify_customer( $order, 'Réclamation traitée — commande n°' . $order->get_order_number(), 'Votre réclamation a été traitée. Solution : ' . $resolution, false );
    slc_redirect_back( 'claim_done' );
} );
