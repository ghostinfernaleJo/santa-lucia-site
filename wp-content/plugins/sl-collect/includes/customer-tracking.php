<?php
/**
 * Suivi client sans compte, protege par un jeton propre a la commande.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function slc_mask_phone( $phone ) {
    $digits = preg_replace( '/\D+/', '', (string) $phone );
    if ( strlen( $digits ) <= 4 ) return $digits;
    return str_repeat( '•', max( 4, strlen( $digits ) - 4 ) ) . substr( $digits, -4 );
}

function slc_claim_window_open( WC_Order $order ) {
    if ( ! $order->has_status( 'completed' ) ) return false;
    $at = (int) $order->get_meta( '_sl_collect_retire_at' );
    if ( ! $at && $order->get_date_completed() ) $at = $order->get_date_completed()->getTimestamp();
    if ( ! $at ) return false;
    $days = max( 1, min( 30, absint( get_option( 'sl_collect_claim_days', 7 ) ) ) );
    return time() <= $at + $days * DAY_IN_SECONDS;
}

function slc_tracking_redirect( WC_Order $order, $notice ) {
    wp_safe_redirect( add_query_arg( 'slc_notice', sanitize_key( $notice ), slc_order_tracking_url( $order ) ) );
    exit;
}

/** Traite toutes les actions client avant le rendu de la page. */
function slc_tracking_handle_post( WC_Order $order ) {
    if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) || empty( $_POST['slc_tracking_action'] ) ) return;
    $nonce = sanitize_text_field( wp_unslash( $_POST['_slc_nonce'] ?? '' ) );
    if ( ! wp_verify_nonce( $nonce, 'slc_track_' . $order->get_id() ) ) {
        wp_die( 'Votre session a expiré. Rechargez la page et recommencez.', 'Suivi commande', [ 'response' => 403 ] );
    }
    $action = sanitize_key( wp_unslash( $_POST['slc_tracking_action'] ) );

    if ( 'cancel' === $action ) {
        if ( ! slc_order_before_preparation( $order ) ) slc_tracking_redirect( $order, 'action_unavailable' );
        $was_paid = $order->is_paid();
        if ( $was_paid && (float) $order->get_total() > 0 ) {
            slc_add_refund_due( $order, (float) $order->get_total(), 'Annulation demandée par le client' );
        }
        $order->update_status( 'cancelled', 'Drop & Collect — annulation demandée depuis le suivi client.' );
        slc_notify_customer( $order, 'Commande n°' . $order->get_order_number() . ' annulée', 'Votre commande Santa Lucia est annulée.' . ( $was_paid ? ' Le remboursement est transmis à notre équipe.' : '' ), false );
        slc_notify_agency_event( $order, 'Commande n°' . $order->get_order_number() . ' annulée par le client', 'Le client a annulé sa commande depuis son lien de suivi.' );
        slc_tracking_redirect( $order, 'cancelled' );
    }

    if ( 'reschedule' === $action ) {
        if ( ! slc_order_before_preparation( $order ) ) slc_tracking_redirect( $order, 'action_unavailable' );
        $date   = sanitize_text_field( wp_unslash( $_POST['pickup_date'] ?? '' ) );
        $slot   = sanitize_key( wp_unslash( $_POST['pickup_slot'] ?? '' ) );
        $agency = (string) $order->get_meta( '_sl_collect_agence' );
        if ( ! slc_pickup_slot_available( $agency, $date, $slot, $order->get_id() ) ) slc_tracking_redirect( $order, 'slot_full' );
        $order->update_meta_data( '_slc_pickup_date', $date );
        $order->update_meta_data( '_slc_pickup_slot', $slot );
        $order->save();
        $order->add_order_note( 'Drop & Collect — créneau modifié par le client : ' . slc_pickup_slot_label( $order ) . '.' );
        slc_notify_agency_event( $order, 'Nouveau créneau — commande n°' . $order->get_order_number(), 'Le client a déplacé son retrait au ' . slc_pickup_slot_label( $order ) . '.' );
        slc_tracking_redirect( $order, 'rescheduled' );
    }

    if ( 'delegate' === $action ) {
        if ( ! in_array( $order->get_status(), slc_active_pipeline_statuses(), true ) ) slc_tracking_redirect( $order, 'action_unavailable' );
        $name  = sanitize_text_field( wp_unslash( $_POST['collector_name'] ?? '' ) );
        $phone = sanitize_text_field( wp_unslash( $_POST['collector_phone'] ?? '' ) );
        $phone_digits = preg_replace( '/\D+/', '', $phone );
        if ( $name === '' || strlen( $phone_digits ) < 8 || strlen( $phone_digits ) > 15 ) slc_tracking_redirect( $order, 'delegate_invalid' );
        $order->update_meta_data( '_slc_collector_name', $name );
        $order->update_meta_data( '_slc_collector_phone', $phone );
        $order->save();
        $order->add_order_note( 'Drop & Collect — mandataire autorisé par le client : ' . $name . ' (' . $phone . ').' );
        slc_notify_agency_event( $order, 'Mandataire ajouté — commande n°' . $order->get_order_number(), $name . ' est autorisé à retirer la commande. Téléphone : ' . $phone . '.' );
        slc_tracking_redirect( $order, 'delegate_saved' );
    }

    if ( 'substitution' === $action ) {
        $id       = sanitize_text_field( wp_unslash( $_POST['substitution_id'] ?? '' ) );
        $decision = sanitize_key( wp_unslash( $_POST['decision'] ?? '' ) );
        $result   = function_exists( 'slc_apply_substitution_response' ) ? slc_apply_substitution_response( $order, $id, $decision ) : new WP_Error( 'missing', 'Fonction indisponible.' );
        slc_tracking_redirect( $order, is_wp_error( $result ) ? 'substitution_error' : 'substitution_saved' );
    }

    if ( 'claim' === $action ) {
        if ( ! slc_claim_window_open( $order ) ) slc_tracking_redirect( $order, 'claim_closed' );
        $item_id = absint( $_POST['item_id'] ?? 0 );
        $item    = $order->get_item( $item_id );
        $reasons = [
            'missing' => 'Article manquant',
            'wrong'   => 'Mauvais article',
            'damaged' => 'Article endommagé',
            'quality' => 'Problème de qualité',
            'other'   => 'Autre problème',
        ];
        $reason  = sanitize_key( wp_unslash( $_POST['reason'] ?? '' ) );
        $details = sanitize_textarea_field( wp_unslash( $_POST['details'] ?? '' ) );
        $details = function_exists( 'mb_substr' ) ? mb_substr( $details, 0, 1000 ) : substr( $details, 0, 1000 );
        if ( ! $item instanceof WC_Order_Item_Product || ! isset( $reasons[ $reason ] ) || strlen( $details ) < 5 ) slc_tracking_redirect( $order, 'claim_invalid' );
        $claims   = $order->get_meta( '_slc_claims' );
        $claims   = is_array( $claims ) ? $claims : [];
        foreach ( $claims as $existing_claim ) {
            if ( 'open' === ( $existing_claim['status'] ?? '' ) && absint( $existing_claim['item_id'] ?? 0 ) === $item_id ) {
                slc_tracking_redirect( $order, 'claim_duplicate' );
            }
        }
        $claims[] = [
            'id'         => wp_generate_uuid4(),
            'item_id'    => $item_id,
            'item_name'  => $item->get_name(),
            'reason'     => $reasons[ $reason ],
            'details'    => $details,
            'status'     => 'open',
            'created_at' => time(),
        ];
        $order->update_meta_data( '_slc_claims', $claims );
        $order->save();
        $order->add_order_note( 'Drop & Collect — réclamation client : ' . $reasons[ $reason ] . ' / ' . $item->get_name() . '.' );
        slc_notify_agency_event( $order, 'Réclamation — commande n°' . $order->get_order_number(), $reasons[ $reason ] . ' sur « ' . $item->get_name() . ' ». Détail : ' . $details );
        slc_tracking_redirect( $order, 'claim_saved' );
    }
}

function slc_tracking_notice_text( $key ) {
    $messages = [
        'cancelled'          => [ 'ok', 'Votre commande est annulée. Si elle était payée, le remboursement est maintenant suivi par notre équipe.' ],
        'rescheduled'        => [ 'ok', 'Votre nouveau créneau de retrait est enregistré.' ],
        'delegate_saved'     => [ 'ok', 'La personne autorisée à retirer la commande est enregistrée.' ],
        'substitution_saved' => [ 'ok', 'Votre choix a été enregistré et transmis à l’agence.' ],
        'claim_saved'        => [ 'ok', 'Votre réclamation est enregistrée. L’agence va la traiter.' ],
        'slot_full'          => [ 'bad', 'Ce créneau n’est plus disponible ou vient d’être rempli. Choisissez-en un autre.' ],
        'delegate_invalid'   => [ 'bad', 'Renseignez un nom et un numéro de téléphone valide.' ],
        'claim_invalid'      => [ 'bad', 'Sélectionnez un article, un motif et décrivez le problème.' ],
        'claim_duplicate'    => [ 'bad', 'Une réclamation est déjà ouverte pour cet article.' ],
        'claim_closed'       => [ 'bad', 'Le délai de réclamation pour cette commande est terminé.' ],
        'substitution_error' => [ 'bad', 'Ce choix ne peut plus être appliqué. Contactez l’agence.' ],
        'action_unavailable' => [ 'bad', 'Cette modification n’est plus possible car la préparation a déjà avancé.' ],
    ];
    return $messages[ $key ] ?? false;
}

function slc_tracking_stage( WC_Order $order ) {
    $status = $order->get_status();
    if ( 'slc-attente' === $status ) $status = (string) $order->get_meta( '_slc_status_before_customer_wait' );
    $map = [ 'pending' => 0, 'processing' => 1, 'slc-acceptee' => 2, 'slc-prep' => 3, 'sl-prete' => 4, 'completed' => 5 ];
    return isset( $map[ $status ] ) ? $map[ $status ] : -1;
}

add_filter( 'woocommerce_my_account_my_orders_actions', function ( $actions, $order ) {
    if ( $order instanceof WC_Order && $order->get_meta( '_sl_collect_agence' ) ) {
        $actions['slc_tracking'] = [
            'url'  => slc_order_tracking_url( $order ),
            'name' => 'Suivre',
        ];
    }
    return $actions;
}, 15, 2 );

/** Page autonome : elle reste legere et fonctionne meme pour une commande invite. */
add_action( 'template_redirect', 'slc_customer_tracking_route', 4 );
function slc_customer_tracking_route() {
    if ( empty( $_GET['slc_track'] ) ) return;
    $token = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) );
    $order = slc_find_order_by_tracking_token( $token );
    if ( ! $order || ! $order->get_meta( '_sl_collect_agence' ) ) {
        nocache_headers();
        wp_die( 'Lien de suivi invalide ou expiré.', 'Suivi commande', [ 'response' => 404 ] );
    }
    slc_tracking_handle_post( $order );
    nocache_headers();
    header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
    header( 'Referrer-Policy: no-referrer', true );

    $agency       = (string) $order->get_meta( '_sl_collect_agence' );
    $stage        = slc_tracking_stage( $order );
    $cancelled    = $order->has_status( [ 'cancelled', 'failed', 'refunded' ] );
    $notice       = slc_tracking_notice_text( sanitize_key( wp_unslash( $_GET['slc_notice'] ?? '' ) ) );
    $substitutions = function_exists( 'slc_substitution_rows' ) ? slc_substitution_rows( $order ) : [];
    $refunds      = function_exists( 'slc_refund_ledger' ) ? slc_refund_ledger( $order ) : [];
    $claims       = $order->get_meta( '_slc_claims' );
    $claims       = is_array( $claims ) ? $claims : [];
    $steps        = [ 'Commande reçue', 'Paiement confirmé', 'Acceptée par l’agence', 'En préparation', 'Prête au retrait', 'Commande remise' ];
    $nonce        = wp_create_nonce( 'slc_track_' . $order->get_id() );
    $action_url   = slc_order_tracking_url( $order );
    $collector    = (string) $order->get_meta( '_slc_collector_name' );
    ?>
    <!doctype html>
    <html <?php language_attributes(); ?>><head>
        <meta charset="<?php bloginfo( 'charset' ); ?>">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <meta name="robots" content="noindex,nofollow,noarchive">
        <title><?php echo esc_html( 'Suivi commande n°' . $order->get_order_number() . ' — Santa Lucia' ); ?></title>
        <style>
            :root{--pink:#e91e63;--blue:#173f86;--ink:#14213a;--muted:#687386;--line:#e5e8ef;--bg:#f5f7fb;--ok:#16834b;--bad:#b42318}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.shell{width:min(1050px,100%);margin:auto;padding:24px 14px 50px}.hero{padding:26px;border-radius:18px;background:linear-gradient(125deg,var(--blue),#275fb8);color:#fff}.eyebrow{text-transform:uppercase;letter-spacing:.08em;font-size:12px;font-weight:800;opacity:.85}.hero h1{margin:6px 0;font-size:clamp(25px,5vw,40px);line-height:1.12}.hero p{margin:0;opacity:.9}.grid{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(285px,.7fr);gap:16px;margin-top:16px}.card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:20px;box-shadow:0 5px 18px rgba(20,33,58,.04)}.card h2{margin:0 0 14px;font-size:19px}.notice{margin-top:14px;padding:13px 15px;border-radius:12px;font-weight:700}.notice.ok{background:#e9f8ef;color:var(--ok)}.notice.bad{background:#fff0ef;color:var(--bad)}.timeline{display:grid;gap:0}.step{display:grid;grid-template-columns:34px 1fr;gap:10px;min-height:54px}.dot{width:24px;height:24px;border:2px solid #ccd2dd;border-radius:50%;display:grid;place-items:center;background:#fff;color:#fff;font-size:13px;position:relative}.step:not(:last-child) .dot:after{content:"";position:absolute;top:22px;width:2px;height:34px;background:#dfe3eb}.step.done .dot{background:var(--ok);border-color:var(--ok)}.step.current .dot{background:var(--pink);border-color:var(--pink);box-shadow:0 0 0 5px #fde6ef}.step.done .dot:after{background:var(--ok)}.step strong{display:block;padding-top:1px}.muted{color:var(--muted);font-size:13px}.facts{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.fact{padding:12px;background:#f7f8fb;border-radius:11px}.fact span{display:block;color:var(--muted);font-size:12px}.items{width:100%;border-collapse:collapse}.items td{padding:11px 4px;border-top:1px solid var(--line);vertical-align:top}.items tr:first-child td{border-top:0}.items td:last-child{text-align:right;font-weight:800}.total{display:flex;justify-content:space-between;border-top:2px solid var(--blue);padding-top:12px;font-size:18px;font-weight:900}.buttons{display:flex;flex-wrap:wrap;gap:9px}.btn,button{appearance:none;border:0;border-radius:10px;background:var(--pink);color:#fff;padding:11px 15px;font:inherit;font-weight:800;cursor:pointer;text-decoration:none;display:inline-block}.btn.secondary,button.secondary{background:#edf2fb;color:var(--blue)}button.danger{background:#fff0ef;color:var(--bad)}label{display:block;margin-top:10px;font-weight:700}input,select,textarea{width:100%;border:1px solid #cbd2de;border-radius:9px;padding:10px;margin-top:5px;font:inherit;background:#fff}textarea{min-height:82px}.proposal{border:2px solid #f3b5cb;background:#fff8fb}.proposal strong.price{color:var(--pink)}.qr{text-align:center}.qr img{width:180px;height:180px;max-width:100%}.ledger{padding:10px 12px;margin-top:8px;border-radius:9px;background:#fff7df}.claim{padding:10px 12px;margin-top:8px;border-left:4px solid var(--blue);background:#f7f8fb}@media(max-width:760px){.grid{grid-template-columns:1fr}.facts{grid-template-columns:1fr}.hero{padding:21px}.card{padding:16px}}
        </style>
    </head><body><main class="shell">
        <header class="hero"><div class="eyebrow">Complexe Santa Lucia · Drop &amp; Collect</div><h1>Commande n°<?php echo esc_html( $order->get_order_number() ); ?></h1><p><?php echo esc_html( trim( $order->get_billing_first_name() ) ?: 'Client' ); ?> · téléphone <?php echo esc_html( slc_mask_phone( $order->get_billing_phone() ) ); ?></p></header>
        <?php if ( $notice ) : ?><div class="notice <?php echo esc_attr( $notice[0] ); ?>"><?php echo esc_html( $notice[1] ); ?></div><?php endif; ?>
        <?php if ( $cancelled ) : ?><div class="notice bad">Statut : <?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></div><?php endif; ?>

        <div class="grid"><div>
            <section class="card"><h2>Avancement</h2><div class="timeline">
                <?php foreach ( $steps as $i => $label ) : $class = $stage > $i ? 'done' : ( $stage === $i ? 'current' : '' ); ?>
                    <div class="step <?php echo esc_attr( $class ); ?>"><div class="dot"><?php echo $stage > $i ? '✓' : ''; ?></div><div><strong><?php echo esc_html( $label ); ?></strong><?php if ( 3 === $i && $order->has_status( 'slc-attente' ) ) : ?><span class="muted">En pause : votre choix est nécessaire pour un article.</span><?php endif; ?></div></div>
                <?php endforeach; ?>
            </div></section>

            <?php foreach ( $substitutions as $sub ) : if ( 'pending' !== ( $sub['status'] ?? '' ) ) continue; ?>
                <section class="card proposal"><h2>Votre choix est nécessaire</h2><p><strong><?php echo esc_html( $sub['original_name'] ); ?></strong> est indisponible.</p>
                    <?php if ( ! empty( $sub['replacement_product_id'] ) ) : ?><p>Proposition : <strong><?php echo esc_html( $sub['replacement_name'] ); ?></strong> pour <strong class="price"><?php echo wp_kses_post( wc_price( $sub['replacement_total'], [ 'currency' => $order->get_currency() ] ) ); ?></strong>.</p><?php else : ?><p>Aucun produit équivalent n’est disponible. Confirmez le retrait de cet article et son remboursement.</p><?php endif; ?>
                    <?php if ( ! empty( $sub['message'] ) ) : ?><p class="muted"><?php echo esc_html( $sub['message'] ); ?></p><?php endif; ?>
                    <form method="post" action="<?php echo esc_url( $action_url ); ?>" class="buttons">
                        <input type="hidden" name="_slc_nonce" value="<?php echo esc_attr( $nonce ); ?>"><input type="hidden" name="slc_tracking_action" value="substitution"><input type="hidden" name="substitution_id" value="<?php echo esc_attr( $sub['id'] ); ?>">
                        <?php if ( ! empty( $sub['replacement_product_id'] ) ) : ?><button name="decision" value="accept">Accepter le remplacement</button><button class="danger" name="decision" value="reject">Retirer et rembourser</button><?php else : ?><button name="decision" value="reject">Confirmer le retrait et le remboursement</button><?php endif; ?>
                    </form>
                </section>
            <?php endforeach; ?>

            <section class="card"><h2>Articles</h2><table class="items">
                <?php foreach ( $order->get_items( 'line_item' ) as $item ) : ?><tr><td><strong><?php echo (int) $item->get_quantity(); ?> × <?php echo esc_html( $item->get_name() ); ?></strong><?php foreach ( $item->get_formatted_meta_data( '', true ) as $meta ) : ?><div class="muted"><?php echo esc_html( wp_strip_all_tags( $meta->display_key . ' : ' . $meta->display_value ) ); ?></div><?php endforeach; ?></td><td><?php echo wp_kses_post( $order->get_formatted_line_subtotal( $item ) ); ?></td></tr><?php endforeach; ?>
            </table><div class="total"><span>Total</span><span><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span></div></section>

            <?php if ( slc_order_before_preparation( $order ) ) : ?>
                <section class="card"><h2>Modifier le retrait</h2><form method="post" action="<?php echo esc_url( $action_url ); ?>">
                    <input type="hidden" name="_slc_nonce" value="<?php echo esc_attr( $nonce ); ?>"><input type="hidden" name="slc_tracking_action" value="reschedule">
                    <div class="facts"><label>Jour<input type="date" name="pickup_date" min="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" max="<?php echo esc_attr( date_i18n( 'Y-m-d', current_time( 'timestamp' ) + 7 * DAY_IN_SECONDS ) ); ?>" value="<?php echo esc_attr( $order->get_meta( '_slc_pickup_date' ) ?: current_time( 'Y-m-d' ) ); ?>" required></label><label>Créneau<select name="pickup_slot"><?php foreach ( slc_pickup_slots() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $order->get_meta( '_slc_pickup_slot' ), $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label></div>
                    <button style="margin-top:12px">Enregistrer le nouveau créneau</button>
                </form><form method="post" action="<?php echo esc_url( $action_url ); ?>" onsubmit="return confirm('Annuler cette commande ?');" style="margin-top:12px"><input type="hidden" name="_slc_nonce" value="<?php echo esc_attr( $nonce ); ?>"><input type="hidden" name="slc_tracking_action" value="cancel"><button class="danger">Annuler ma commande</button></form></section>
            <?php endif; ?>

            <?php if ( slc_claim_window_open( $order ) ) : ?>
                <section class="card"><h2>Signaler un problème</h2><p class="muted">Votre demande sera directement rattachée à la commande et à l’article concerné.</p><form method="post" action="<?php echo esc_url( $action_url ); ?>">
                    <input type="hidden" name="_slc_nonce" value="<?php echo esc_attr( $nonce ); ?>"><input type="hidden" name="slc_tracking_action" value="claim">
                    <label>Article<select name="item_id" required><option value="">Choisir…</option><?php foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) : ?><option value="<?php echo (int) $item_id; ?>"><?php echo esc_html( $item->get_name() ); ?></option><?php endforeach; ?></select></label>
                    <label>Motif<select name="reason" required><option value="missing">Article manquant</option><option value="wrong">Mauvais article</option><option value="damaged">Article endommagé</option><option value="quality">Problème de qualité</option><option value="other">Autre problème</option></select></label>
                    <label>Détails<textarea name="details" minlength="5" required></textarea></label><button>Envoyer la réclamation</button>
                </form></section>
            <?php endif; ?>
        </div><aside>
            <section class="card"><h2>Votre retrait</h2><div class="facts"><div class="fact"><span>Agence</span><strong><?php echo esc_html( slc_agence_name( $agency ) ); ?></strong></div><div class="fact"><span>Créneau</span><strong><?php echo esc_html( slc_pickup_slot_label( $order ) ); ?></strong></div><div class="fact"><span>Code de retrait</span><strong><?php echo esc_html( $order->get_meta( '_sl_collect_code' ) ?: 'Après paiement' ); ?></strong></div><div class="fact"><span>Préparation estimée</span><strong><?php echo (int) slc_agence_prep_minutes( $agency ); ?> min</strong></div><?php $reservation_expires = (int) $order->get_meta( '_slc_stock_reservation_expires_at' ); if ( $reservation_expires > time() ) : ?><div class="fact"><span>Stock réservé jusqu’à</span><strong><?php echo esc_html( date_i18n( 'H:i', $reservation_expires ) ); ?></strong></div><?php endif; ?></div>
                <div class="buttons" style="margin-top:14px"><a class="btn" href="<?php echo esc_url( slc_facture_url( $order ) ); ?>">Voir la facture PDF</a></div></section>

            <?php if ( $order->has_status( [ 'sl-prete', 'completed' ] ) ) : ?><section class="card qr"><h2>QR d’identification du colis</h2><img src="<?php echo esc_url( slc_facture_qr_url( $order ) ); ?>" alt="QR du colis <?php echo esc_attr( slc_package_code( $order ) ); ?>"><p><strong>ID colis :</strong><br><code style="font-size:16px;font-weight:800;color:#d51f65;overflow-wrap:anywhere;"><?php echo esc_html( slc_package_code( $order ) ); ?></code></p><p class="muted">Présentez ce QR à l’équipe de votre agence. Il permet de retrouver le bon paquet et de contrôler son contenu avant la remise.</p></section><?php endif; ?>

            <?php if ( in_array( $order->get_status(), slc_active_pipeline_statuses(), true ) ) : ?><section class="card"><h2>Personne autorisée</h2><p class="muted">Vous pouvez autoriser une autre personne à retirer la commande avec sa pièce d’identité.</p><form method="post" action="<?php echo esc_url( $action_url ); ?>"><input type="hidden" name="_slc_nonce" value="<?php echo esc_attr( $nonce ); ?>"><input type="hidden" name="slc_tracking_action" value="delegate"><label>Nom complet<input name="collector_name" value="<?php echo esc_attr( $collector ); ?>" required></label><label>Téléphone<input type="tel" name="collector_phone" value="<?php echo esc_attr( $order->get_meta( '_slc_collector_phone' ) ); ?>" required></label><button style="margin-top:12px">Autoriser cette personne</button></form></section><?php endif; ?>

            <?php foreach ( $refunds as $refund ) : ?><section class="ledger"><strong>Remboursement : <?php echo wp_kses_post( wc_price( $refund['amount'] ?? 0, [ 'currency' => $order->get_currency() ] ) ); ?></strong><br><span class="muted"><?php echo esc_html( 'done' === ( $refund['status'] ?? '' ) ? 'Traité' : 'En cours de traitement' ); ?> · <?php echo esc_html( $refund['reason'] ?? '' ); ?></span></section><?php endforeach; ?>
            <?php foreach ( $claims as $claim ) : ?><section class="claim"><strong><?php echo esc_html( $claim['reason'] ?? 'Réclamation' ); ?></strong><br><span class="muted"><?php echo esc_html( 'resolved' === ( $claim['status'] ?? '' ) ? 'Traitée : ' . ( $claim['resolution'] ?? '' ) : 'En cours de traitement' ); ?></span></section><?php endforeach; ?>
        </aside></div>
    </main></body></html>
    <?php
    exit;
}
