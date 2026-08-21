<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   ECRAN « Commandes retrait » — responsable d'agence + admins
   Scoping par agence FAIL-CLOSED (meme principe que le Fast Food) :
   un responsable sans agence assignee ne voit AUCUNE commande.
   ============================================================ */
add_action( 'admin_menu', 'slc_admin_menu', 999 );
function slc_admin_menu() {
    if ( ! slc_is_admin_user() && slc_user_agence_slug() === '' ) {
        return; // ni admin, ni responsable rattache : pas de menu
    }
    add_menu_page(
        'Commandes retrait', 'Commandes retrait', 'read',
        'sl-collect', 'slc_admin_page', 'dashicons-store', 27
    );
}

add_action( 'admin_head', 'slc_admin_styles' );
function slc_admin_styles() {
    if ( ! isset( $_GET['page'] ) || 'sl-collect' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) return;
    ?>
    <style id="slc-admin-styles">
        .slc-admin-shell { max-width: 1680px; }
        .slc-admin-shell h1 { display:flex; align-items:center; gap:8px; margin-bottom:18px; }
        .slc-filter-bar { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; margin:0 0 18px; padding:16px; background:#fff; border:1px solid #dcdcde; border-radius:8px; box-shadow:0 1px 2px rgba(0,0,0,.04); }
        .slc-filter-bar label { display:flex; flex-direction:column; gap:5px; color:#50575e; font-size:12px; font-weight:600; }
        .slc-filter-bar select, .slc-filter-bar input[type="search"] { min-height:40px; }
        .slc-filter-bar input[type="search"] { min-width:280px; }
        .slc-filter-bar .button { min-height:40px; padding:0 16px; }
        .slc-orders-wrap { overflow-x:auto; border:1px solid #dcdcde; border-radius:8px; background:#fff; }
        .slc-orders-table { min-width:1080px; border:0; box-shadow:none; }
        .slc-orders-table thead th { padding:12px 10px; color:#50575e; font-size:11px; font-weight:700; letter-spacing:.04em; text-transform:uppercase; white-space:nowrap; }
        .slc-orders-table tbody td { padding:14px 10px; vertical-align:top; }
        .slc-orders-table tbody tr:not(.slc-ticket-row):hover { background:#f6f7f7; }
        .slc-order-number { color:#1d2327; font-size:14px; }
        .slc-customer-name { font-weight:600; color:#1d2327; }
        .slc-customer-phone { white-space:nowrap; }
        .slc-items-summary { max-width:290px; line-height:1.45; }
        .slc-order-details { margin-top:8px; }
        .slc-order-details summary { display:inline-flex; align-items:center; gap:4px; cursor:pointer; color:#2271b1; font-weight:600; }
        .slc-order-details summary:hover { color:#135e96; }
        .slc-order-detail-box { min-width:560px; margin-top:10px; padding:10px; background:#f6f7f7; border:1px solid #dcdcde; border-radius:6px; }
        .slc-order-detail-box table { background:#fff; }
        .slc-order-detail-box th { font-size:11px; text-transform:uppercase; }
        .slc-order-detail-box td, .slc-order-detail-box th { padding:8px; }
        .slc-total { font-weight:700; white-space:nowrap; }
        .slc-status { display:inline-flex; align-items:center; min-height:24px; padding:3px 9px; border-radius:999px; background:#eef0f2; color:#50575e; font-size:12px; font-weight:700; white-space:nowrap; }
        .slc-status-processing { background:#e7f3ff; color:#075985; }
        .slc-status-slc-acceptee { background:#e8f2ff; color:#174ea6; }
        .slc-status-slc-prep { background:#eee8ff; color:#5b35a8; }
        .slc-status-slc-attente { background:#fff4ce; color:#8a4b00; }
        .slc-status-sl-prete { background:#fff4ce; color:#8a4b00; }
        .slc-status-pending, .slc-status-on-hold { background:#fff4ce; color:#7a4d00; }
        .slc-status-completed { background:#dff7e8; color:#116329; }
        .slc-status-failed, .slc-status-cancelled, .slc-status-refunded { background:#fbeaea; color:#b32d2e; }
        .slc-status-meta { display:block; margin-top:6px; color:#646970; font-size:11px; line-height:1.5; }
        .slc-status-meta code { padding:2px 4px; background:#f0f0f1; }
        .slc-actions { display:flex; flex-direction:column; align-items:flex-start; gap:7px; min-width:240px; }
        .slc-actions .button { margin:0; }
        .slc-actions form { display:flex; gap:7px; align-items:center; margin:0; }
        .slc-actions input[name="code"] { width:120px; min-height:34px; text-transform:uppercase; }
        .slc-ticket-row td { background:#f6f7f7; }
        @media (max-width:782px) {
            .slc-admin-shell h1 { font-size:22px; }
            .slc-filter-bar { align-items:stretch; }
            .slc-filter-bar label, .slc-filter-bar input[type="search"], .slc-filter-bar select, .slc-filter-bar .button { width:100%; box-sizing:border-box; }
            .slc-filter-bar .button { text-align:center; }
        }
    </style>
    <?php
}

/**
 * Statuts geres par l'ecran — TOUS les etats d'une commande, choisissables.
 * Un paiement echoue est une commande comme une autre : la masquer du listing
 * revenait a pretendre qu'elle n'existait pas (constat utilisateur 2026-07-17).
 * La liste est completee dynamiquement depuis wc_get_order_statuses() : un
 * statut ajoute demain (par Woo ou un autre plugin) apparaitra sans modification.
 */
function slc_screen_statuses() {
    $labels = [
        'toutes'     => 'Toutes les commandes (tout statut, y compris échouées)',
        'actives'    => 'Commandes actives seulement (en attente/payées/prêtes)',
        'pending'    => 'En attente (à confirmer/payer)',
        'processing' => 'Payées — à accepter par l’agence',
        'slc-acceptee'=> 'Acceptées — préparation à démarrer',
        'slc-prep'   => 'En préparation',
        'slc-attente'=> 'Attente du choix client',
        'sl-prete'   => 'Prêtes — à remettre',
        'failed'     => '❌ Paiement échoué',
        'on-hold'    => 'En pause (on-hold)',
        'completed'  => 'Retirées',
        'cancelled'  => 'Annulées',
        'refunded'   => 'Remboursées',
    ];
    if ( function_exists( 'wc_get_order_statuses' ) ) {
        foreach ( wc_get_order_statuses() as $key => $label ) {
            $slug = preg_replace( '/^wc-/', '', $key );
            if ( 'checkout-draft' === $slug ) {
                continue; // paniers en cours de saisie, pas des commandes
            }
            if ( ! isset( $labels[ $slug ] ) ) {
                $labels[ $slug ] = $label;
            }
        }
    }
    return $labels;
}

/** Statuts couverts par la vue « actives ». */
function slc_active_statuses() {
    return function_exists( 'slc_active_pipeline_statuses' )
        ? slc_active_pipeline_statuses()
        : [ 'pending', 'processing', 'sl-prete' ];
}

/** Tous les statuts REELS geres par l'ecran (exclut les pseudo-filtres « toutes »/« actives »). */
function slc_all_statuses() {
    return array_diff( array_keys( slc_screen_statuses() ), [ 'toutes', 'actives' ] );
}

function slc_admin_page() {
    $is_admin = slc_is_admin_user();
    $ma_agence = slc_user_agence_slug();

    if ( ! $is_admin && $ma_agence === '' ) {
        echo '<div class="wrap"><h1>Commandes retrait</h1><div class="notice notice-warning"><p>'
            . '<strong>Aucune agence ne vous est attribuée.</strong> Contactez un administrateur.</p></div></div>';
        return;
    }

    $agence_sel = $is_admin
        ? ( isset( $_GET['agence'] ) ? sanitize_title( wp_unslash( $_GET['agence'] ) ) : '' )
        : $ma_agence;
    $statut_sel = isset( $_GET['statut'] ) ? sanitize_key( wp_unslash( $_GET['statut'] ) ) : 'toutes';
    if ( ! isset( slc_screen_statuses()[ $statut_sel ] ) ) $statut_sel = 'toutes';
    $recherche = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';

    // Recherche directe par code de retrait / numero de commande / telephone
    $orders = [];
    if ( $recherche !== '' ) {
        $found = slc_find_order_by_code( $recherche );
        if ( ! $found && is_numeric( $recherche ) ) {
            $maybe = wc_get_order( (int) $recherche );
            if ( $maybe && $maybe->get_meta( '_sl_collect_agence' ) ) $found = $maybe;
        }
        if ( $found ) {
            $orders = [ $found ];
        } else {
            // Recherche par telephone sur TOUS les statuts : un client rappelle
            // souvent APRES le retrait (reclamation, oubli d'article) — sa
            // commande terminee doit rester trouvable par son numero, comme
            // elle l'est deja par code de retrait ou numero de commande.
            $tous_statuts = slc_all_statuses();
            foreach ( slc_order_ids( $agence_sel, $tous_statuts, 300 ) as $oid ) {
                $o = wc_get_order( $oid );
                if ( $o && false !== strpos( preg_replace( '/\D/', '', $o->get_billing_phone() ), preg_replace( '/\D/', '', $recherche ) ) ) {
                    $orders[] = $o;
                }
            }
        }
        // fail-closed : un responsable ne voit pas les commandes d'une autre agence
        if ( ! $is_admin ) {
            $orders = array_values( array_filter( $orders, function ( $o ) use ( $ma_agence ) {
                return $o->get_meta( '_sl_collect_agence' ) === $ma_agence;
            } ) );
        }
    } else {
        if ( 'toutes' === $statut_sel ) {
            $statuts_requete = slc_all_statuses();
        } elseif ( 'actives' === $statut_sel ) {
            $statuts_requete = slc_active_statuses();
        } else {
            $statuts_requete = [ $statut_sel ];
        }
        foreach ( slc_order_ids( $agence_sel, $statuts_requete, 200 ) as $oid ) {
            $o = wc_get_order( $oid );
            if ( $o ) $orders[] = $o;
        }
    }

    $notice = isset( $_GET['slc_msg'] ) ? sanitize_key( $_GET['slc_msg'] ) : '';
    ?>
    <div class="wrap slc-admin-shell">
        <h1>🏪 Commandes retrait <?php echo $agence_sel ? '— ' . esc_html( slc_agence_name( $agence_sel ) ) : ( $is_admin ? '— toutes les agences' : '' ); ?></h1>

        <?php if ( 'pret' === $notice ) : ?>
            <div class="notice notice-success is-dismissible"><p>Commande marquée <strong>Prête</strong> — le client a été notifié.</p></div>
        <?php elseif ( 'remis' === $notice ) : ?>
            <div class="notice notice-success is-dismissible"><p>Remise confirmée — commande <strong>Retirée</strong>. ✔</p></div>
        <?php elseif ( 'badcode' === $notice ) : ?>
            <div class="notice notice-error is-dismissible"><p><strong>Code de retrait incorrect.</strong> Vérifiez la facture du client.</p></div>
        <?php elseif ( 'err' === $notice ) : ?>
            <div class="notice notice-error is-dismissible"><p>Action impossible (commande introuvable ou statut inattendu).</p></div>
        <?php elseif ( 'removed' === $notice ) : ?>
            <div class="notice notice-success is-dismissible"><p>La ligne a été supprimée, le stock libéré et le total recalculé. Si la commande était payée, le remboursement apparaît dans son détail.</p></div>
        <?php elseif ( 'remove_err' === $notice ) : ?>
            <div class="notice notice-error is-dismissible"><p>Impossible de supprimer cette ligne. Vérifiez le statut de la commande et les droits du compte.</p></div>
        <?php elseif ( 'accepted' === $notice ) : ?>
            <div class="notice notice-success is-dismissible"><p>Commande acceptée par l’agence.</p></div>
        <?php elseif ( 'preparing' === $notice ) : ?>
            <div class="notice notice-success is-dismissible"><p>La préparation a démarré.</p></div>
        <?php elseif ( 'substitution_sent' === $notice ) : ?>
            <div class="notice notice-success is-dismissible"><p>La proposition a été envoyée au client. La commande attend sa réponse.</p></div>
        <?php elseif ( 'substitution_err' === $notice ) : ?>
            <div class="notice notice-error is-dismissible"><p>Impossible d’envoyer cette proposition. Vérifiez le produit, le prix et le stock.</p></div>
        <?php elseif ( 'refund_done' === $notice ) : ?>
            <div class="notice notice-success is-dismissible"><p>Le remboursement a été marqué comme traité.</p></div>
        <?php elseif ( in_array( $notice, [ 'refund_err', 'claim_err' ], true ) ) : ?>
            <div class="notice notice-error is-dismissible"><p>Cette opération n’a pas pu être enregistrée.</p></div>
        <?php elseif ( 'claim_done' === $notice ) : ?>
            <div class="notice notice-success is-dismissible"><p>La réclamation est clôturée et le client a été informé.</p></div>
        <?php endif; ?>

        <form method="get" class="slc-filter-bar">
            <input type="hidden" name="page" value="sl-collect">
            <label>Statut<br>
                <select name="statut">
                    <?php foreach ( slc_screen_statuses() as $k => $label ) : ?>
                        <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $statut_sel, $k ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if ( $is_admin ) : ?>
            <label>Agence<br>
                <select name="agence">
                    <option value="">Toutes</option>
                    <?php foreach ( slc_agences() as $t ) : ?>
                        <option value="<?php echo esc_attr( $t->slug ); ?>" <?php selected( $agence_sel, $t->slug ); ?>><?php echo esc_html( $t->name ); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php endif; ?>
            <label>Recherche (code retrait / n° commande / téléphone)<br>
                <input type="search" name="q" value="<?php echo esc_attr( $recherche ); ?>" style="min-width:260px;">
            </label>
            <button class="button button-primary">Filtrer</button>
            <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sl-collect' ) ); ?>">Réinitialiser</a>
        </form>

        <?php if ( empty( $orders ) ) : ?>
            <p><em>Aucune commande pour ces critères.</em></p>
        <?php else : ?>
        <div class="slc-orders-wrap">
        <table class="widefat striped slc-orders-table">
            <thead><tr>
                <th>N°</th><th>Client</th><th>Téléphone</th><th>Articles</th><th>Total</th>
                <?php if ( $is_admin && $agence_sel === '' ) : ?><th>Agence</th><?php endif; ?>
                <th>Statut</th><th>Date</th><th style="min-width:260px;">Action</th>
            </tr></thead>
            <tbody>
            <?php foreach ( $orders as $o ) :
                $st = $o->get_status();
                $line_items = $o->get_items( 'line_item' );
                $items = [];
                foreach ( $line_items as $it ) $items[] = $it->get_quantity() . '× ' . $it->get_name();
            ?>
                <tr>
                    <td><strong class="slc-order-number">n°<?php echo esc_html( $o->get_order_number() ); ?></strong></td>
                    <td><span class="slc-customer-name"><?php echo esc_html( trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() ) ); ?></span></td>
                    <td><a class="slc-customer-phone" href="tel:<?php echo esc_attr( $o->get_billing_phone() ); ?>"><?php echo esc_html( $o->get_billing_phone() ); ?></a></td>
                    <td class="slc-items-summary">
                        <?php echo esc_html( implode( ', ', array_slice( $items, 0, 3 ) ) . ( count( $items ) > 3 ? '…' : '' ) ); ?>
                        <details class="slc-order-details">
                            <summary>Voir les <?php echo count( $line_items ); ?> ligne(s)</summary>
                            <div class="slc-order-detail-box">
                                <table class="widefat striped" style="margin:0;">
                                    <thead><tr><th>Article</th><th>Options</th><th>Qté</th><th>Total ligne</th><th>Gestion</th></tr></thead>
                                    <tbody>
                                    <?php foreach ( $line_items as $item_id => $item ) :
                                        $meta_rows = [];
                                        foreach ( $item->get_formatted_meta_data( '', true ) as $meta ) {
                                            $meta_rows[] = esc_html( wp_strip_all_tags( $meta->display_key ) . ': ' . wp_strip_all_tags( $meta->display_value ) );
                                        }
                                        $product = $item->get_product();
                                        $sku = $product && $product->get_sku() ? 'SKU: ' . $product->get_sku() : '';
                                        if ( $sku !== '' ) $meta_rows[] = esc_html( $sku );
                                        $can_remove = in_array( $st, [ 'pending', 'processing', 'slc-acceptee', 'slc-prep', 'slc-attente', 'sl-prete' ], true )
                                            && ( ! function_exists( 'slc_pending_substitution_for_item' ) || ! slc_pending_substitution_for_item( $o, $item_id ) );
                                    ?>
                                        <tr>
                                            <td><strong><?php echo esc_html( $item->get_name() ); ?></strong></td>
                                            <td><?php echo $meta_rows ? implode( '<br>', $meta_rows ) : '—'; ?></td>
                                            <td><?php echo (int) $item->get_quantity(); ?></td>
                                            <td><?php echo wp_kses_post( $o->get_formatted_line_subtotal( $item ) ); ?></td>
                                            <td>
                                                <?php if ( $can_remove ) : ?>
                                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Supprimer cette ligne ? Le stock sera libéré et, si la commande est payée, un remboursement à traiter sera créé.');">
                                                        <?php wp_nonce_field( 'slc_action_' . $o->get_id() ); ?>
                                                        <input type="hidden" name="action" value="slc_remove_order_item">
                                                        <input type="hidden" name="order_id" value="<?php echo (int) $o->get_id(); ?>">
                                                        <input type="hidden" name="item_id" value="<?php echo (int) $item_id; ?>">
                                                        <button type="submit" class="button-link-delete">Supprimer</button>
                                                    </form>
                                                <?php else : ?>
                                                    <span style="color:#777;">—</span>
                                                <?php endif; ?>
                                                <?php if ( function_exists( 'slc_admin_substitution_control' ) ) slc_admin_substitution_control( $o, $item_id, $item ); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <p style="margin:8px 0 0;color:#666;font-size:12px;">Une suppression libère le stock. Sur une commande payée, le montant à rembourser est suivi jusqu'à sa confirmation.</p>
                                <?php if ( function_exists( 'slc_admin_order_operations_panel' ) ) slc_admin_order_operations_panel( $o ); ?>
                            </div>
                        </details>
                    </td>
                    <td class="slc-total"><?php echo wp_kses_post( $o->get_formatted_order_total() ); ?></td>
                    <?php if ( $is_admin && $agence_sel === '' ) : ?>
                        <td><?php echo esc_html( slc_agence_name( $o->get_meta( '_sl_collect_agence' ) ) ); ?></td>
                    <?php endif; ?>
                    <td><?php
                        echo '<span class="slc-status slc-status-' . esc_attr( sanitize_html_class( $st ) ) . '">' . esc_html( wc_get_order_status_name( $st ) ) . '</span>';
                        // Un echec sans raison visible oblige a ouvrir la commande :
                        // la raison (posee par la passerelle) s'affiche directement.
                        if ( 'failed' === $st ) {
                            $raison = (string) $o->get_meta( '_mmgate_fail_reason' );
                            if ( $raison !== '' ) {
                                echo '<span class="slc-status-meta" style="color:#b32d2e;">' . esc_html( $raison ) . '</span>';
                            }
                        }
                        // Reference MMGate (IDOPER) : pour rapprocher la transaction avec
                        // MMGate (support, reconciliation), quel que soit le statut.
                        $idoper = (string) $o->get_meta( '_mmgate_idoper' );
                        if ( $idoper !== '' ) {
                            echo '<span class="slc-status-meta">IDOPER&nbsp;: <code>' . esc_html( $idoper ) . '</code></span>';
                        }
                        if ( function_exists( 'slc_pickup_slot_label' ) ) {
                            echo '<span class="slc-status-meta">Retrait : ' . esc_html( slc_pickup_slot_label( $o ) ) . '</span>';
                        }
                    ?></td>
                    <td><?php echo esc_html( $o->get_date_created() ? $o->get_date_created()->date_i18n( 'd/m/Y H:i' ) : '—' ); ?></td>
                    <td>
                        <div class="slc-actions">
                        <button type="button" class="button" onclick="slcPrintTicket(<?php echo (int) $o->get_id(); ?>);">🖨️ Imprimer le ticket</button>
                        <?php if ( 'processing' === $st ) : ?>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                <?php wp_nonce_field( 'slc_action_' . $o->get_id() ); ?>
                                <input type="hidden" name="action" value="slc_accept_order">
                                <input type="hidden" name="order_id" value="<?php echo (int) $o->get_id(); ?>">
                                <button class="button button-primary">Accepter la commande</button>
                            </form>
                        <?php elseif ( 'slc-acceptee' === $st ) : ?>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                <?php wp_nonce_field( 'slc_action_' . $o->get_id() ); ?>
                                <input type="hidden" name="action" value="slc_start_preparation">
                                <input type="hidden" name="order_id" value="<?php echo (int) $o->get_id(); ?>">
                                <button class="button button-primary">Commencer la préparation</button>
                            </form>
                        <?php elseif ( 'slc-prep' === $st ) : ?>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Marquer la commande n°<?php echo esc_js( $o->get_order_number() ); ?> comme PRÊTE ? Le client sera notifié.');">
                                <?php wp_nonce_field( 'slc_action_' . $o->get_id() ); ?>
                                <input type="hidden" name="action" value="slc_mark_ready">
                                <input type="hidden" name="order_id" value="<?php echo (int) $o->get_id(); ?>">
                                <button class="button button-primary">✅ Marquer PRÊTE</button>
                            </form>
                        <?php elseif ( 'slc-attente' === $st ) : ?>
                            <span style="color:#8a4b00;">⏳ Attente du choix du client</span>
                        <?php elseif ( 'sl-prete' === $st ) : ?>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                <?php wp_nonce_field( 'slc_action_' . $o->get_id() ); ?>
                                <input type="hidden" name="action" value="slc_handover">
                                <input type="hidden" name="order_id" value="<?php echo (int) $o->get_id(); ?>">
                                <input type="text" name="code" placeholder="Code retrait" required
                                       style="width:120px;text-transform:uppercase;" autocomplete="off">
                                <button class="button button-primary">🤝 Remettre</button>
                            </form>
                        <?php elseif ( 'pending' === $st ) : ?>
                            <span style="color:#996800;">⏳ Attente confirmation/paiement client</span>
                        <?php elseif ( 'failed' === $st ) : ?>
                            <span style="color:#b32d2e;">❌ Paiement non abouti — le client peut réessayer depuis « Mon compte → Payer »</span>
                        <?php else : ?>
                            —
                        <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php
                $ticket_qr_url      = function_exists( 'slc_facture_qr_url' ) ? slc_facture_qr_url( $o ) : '';
                $ticket_package_code = function_exists( 'slc_package_code' ) ? slc_package_code( $o ) : 'CMD-' . $o->get_order_number();
                ?>
                <tr id="slc-ticket-row-<?php echo (int) $o->get_id(); ?>" style="display:none;">
                    <td colspan="<?php echo (int) ( 8 + ( $is_admin && $agence_sel === '' ? 1 : 0 ) ); ?>">
                         <div id="slc-ticket-<?php echo (int) $o->get_id(); ?>" class="slc-ticket-content">
                            <h2>Ticket de préparation - colis <?php echo esc_html( $ticket_package_code ); ?></h2>
                            <?php if ( $ticket_qr_url !== '' ) : ?><div style="float:right;text-align:center;margin:0 0 12px 18px;"><img src="<?php echo esc_url( $ticket_qr_url ); ?>" alt="QR du colis <?php echo esc_attr( $ticket_package_code ); ?>" width="120" height="120" style="display:block;width:120px;height:120px;"><small>Scanner pour identifier le colis</small></div><?php endif; ?>
                            <p style="padding:10px 12px;border:2px solid #1d54a0;background:#f2f7ff;"><strong>ID COLIS :</strong><br><code style="font-size:17px;font-weight:700;color:#d51f65;"><?php echo esc_html( $ticket_package_code ); ?></code><br><small>Commande n°<?php echo esc_html( $o->get_order_number() ); ?></small></p>
                            <p><strong>Client :</strong> <?php echo esc_html( trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() ) ); ?><br>
                            <strong>Téléphone :</strong> <?php echo esc_html( $o->get_billing_phone() ); ?><br>
                            <?php $payment_phone = (string) $o->get_meta( '_sl_collect_payment_phone' ); if ( $payment_phone === '' ) $payment_phone = (string) $o->get_meta( '_mmgate_msisdn' ); ?>
                            <?php if ( $payment_phone !== '' ) : ?><strong>Numéro de paiement :</strong> <?php echo esc_html( $payment_phone ); ?><br><?php endif; ?>
                            <strong>Agence :</strong> <?php echo esc_html( slc_agence_name( $o->get_meta( '_sl_collect_agence' ) ) ); ?><br>
                            <strong>Créneau :</strong> <?php echo esc_html( function_exists( 'slc_pickup_slot_label' ) ? slc_pickup_slot_label( $o ) : '—' ); ?><br>
                            <?php if ( $o->get_meta( '_slc_collector_name' ) ) : ?><strong>Mandataire :</strong> <?php echo esc_html( $o->get_meta( '_slc_collector_name' ) . ' · ' . $o->get_meta( '_slc_collector_phone' ) ); ?><br><?php endif; ?>
                            <strong>Date :</strong> <?php echo esc_html( $o->get_date_created() ? $o->get_date_created()->date_i18n( 'd/m/Y H:i' ) : '—' ); ?></p>
                            <table style="width:100%;border-collapse:collapse;"><thead><tr><th style="text-align:left;border-bottom:1px solid #333;padding:5px;">Article</th><th style="text-align:center;border-bottom:1px solid #333;padding:5px;">Qté</th><th style="text-align:right;border-bottom:1px solid #333;padding:5px;">Total</th></tr></thead><tbody>
                            <?php foreach ( $line_items as $ticket_item ) : ?>
                                <tr><td style="padding:5px;border-bottom:1px solid #ddd;"><?php echo esc_html( $ticket_item->get_name() ); ?></td><td style="text-align:center;padding:5px;border-bottom:1px solid #ddd;"><?php echo (int) $ticket_item->get_quantity(); ?></td><td style="text-align:right;padding:5px;border-bottom:1px solid #ddd;"><?php echo wp_kses_post( $o->get_formatted_line_subtotal( $ticket_item ) ); ?></td></tr>
                            <?php endforeach; ?>
                            </tbody></table>
                            <p style="text-align:right;font-size:16px;"><strong>Total commande : <?php echo wp_kses_post( $o->get_formatted_order_total() ); ?></strong></p>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
    <?php
}

add_action( 'admin_footer', 'slc_print_ticket_script' );
function slc_print_ticket_script() {
    if ( ! isset( $_GET['page'] ) || 'sl-collect' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) return;
    ?>
    <script>
    function slcPrintTicket(orderId) {
        var source = document.getElementById('slc-ticket-' + orderId);
        if (!source) return;
        var popup = window.open('', '_blank', 'width=760,height=800');
        if (!popup) { window.alert('Autorisez les fenêtres popup pour imprimer le ticket.'); return; }
        popup.document.write('<!doctype html><html><head><title>Ticket commande</title><style>body{font-family:Arial,sans-serif;color:#111;margin:30px;max-width:720px}h2{margin:0 0 18px}table{font-size:14px}p{line-height:1.5}@media print{body{margin:10mm}}</style></head><body>' + source.innerHTML + '</body></html>');
        popup.document.close();
        popup.focus();
        popup.onload = function(){ popup.print(); };
    }
    </script>
    <?php
}

/* ============================================================
   ACTIONS (admin-post) — nonce + verification d'appartenance agence
   ============================================================ */
function slc_check_action_order() {
    $order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
    if ( ! $order_id ) wp_die( 'Commande manquante.' );
    check_admin_referer( 'slc_action_' . $order_id );

    $order = wc_get_order( $order_id );
    if ( ! $order || ! $order->get_meta( '_sl_collect_agence' ) ) wp_die( 'Commande introuvable.' );

    if ( ! slc_is_admin_user() ) {
        $ma = slc_user_agence_slug();
        if ( $ma === '' || $order->get_meta( '_sl_collect_agence' ) !== $ma ) {
            wp_die( 'Accès refusé : cette commande appartient à une autre agence.' );
        }
    }
    return $order;
}

function slc_redirect_back( $msg ) {
    $url = add_query_arg( [ 'page' => 'sl-collect', 'slc_msg' => $msg ], admin_url( 'admin.php' ) );
    wp_safe_redirect( $url );
    exit;
}

add_action( 'admin_post_slc_mark_ready', function () {
    $order = slc_check_action_order();
    if ( ! $order->has_status( [ 'processing', 'slc-acceptee', 'slc-prep' ] ) ) slc_redirect_back( 'err' );
    $user = wp_get_current_user();
    $order->update_meta_data( '_slc_prete_by', $user->ID );
    $order->update_status( 'sl-prete', 'Drop & Collect — marquée PRÊTE par ' . $user->user_login . ' (' . slc_agence_name( $order->get_meta( '_sl_collect_agence' ) ) . ').' );
    slc_redirect_back( 'pret' );
} );

add_action( 'admin_post_slc_handover', function () {
    $order = slc_check_action_order();
    if ( ! $order->has_status( 'sl-prete' ) ) slc_redirect_back( 'err' );

    $code_saisi = isset( $_POST['code'] ) ? strtoupper( trim( sanitize_text_field( wp_unslash( $_POST['code'] ) ) ) ) : '';
    $code_reel  = strtoupper( (string) $order->get_meta( '_sl_collect_code' ) );
    if ( $code_reel === '' || $code_saisi !== $code_reel ) {
        $order->add_order_note( 'Drop & Collect — tentative de remise avec code INCORRECT (' . $code_saisi . ').' );
        slc_redirect_back( 'badcode' );
    }

    $user = wp_get_current_user();
    $order->update_meta_data( '_sl_collect_retire_by', $user->ID );
    $order->update_status( 'completed', 'Drop & Collect — remise effectuée (code vérifié) par ' . $user->user_login . '.' );
    slc_redirect_back( 'remis' );
} );

add_action( 'admin_post_slc_remove_order_item', function () {
    $order = slc_check_action_order();
    $item_id = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
    $allowed_statuses = [ 'pending', 'processing', 'slc-acceptee', 'slc-prep', 'slc-attente', 'sl-prete' ];

    if ( ! $item_id || ! in_array( $order->get_status(), $allowed_statuses, true ) ) {
        slc_redirect_back( 'remove_err' );
    }

    $items = $order->get_items( 'line_item' );
    $item  = isset( $items[ $item_id ] ) ? $items[ $item_id ] : false;
    if ( ! $item ) {
        slc_redirect_back( 'remove_err' );
    }

    if ( function_exists( 'slc_pending_substitution_for_item' ) && slc_pending_substitution_for_item( $order, $item_id ) ) {
        slc_redirect_back( 'remove_err' );
    }

    $name       = $item->get_name();
    $qty        = (int) $item->get_quantity();
    $old_total  = (float) $order->get_total();
    $was_paid   = $order->is_paid();
    $last_item  = count( $items ) === 1;
    if ( function_exists( 'slc_restore_item_stock' ) ) slc_restore_item_stock( $order, $item );
    $order->remove_item( $item_id );
    $order->calculate_totals( true );
    $difference = max( 0, $old_total - (float) $order->get_total() );
    if ( $was_paid && $difference > 0 && function_exists( 'slc_add_refund_due' ) ) {
        slc_add_refund_due( $order, $difference, 'Ligne supprimée par l’agence', $item_id );
    }
    $order->add_order_note( sprintf( 'Drop & Collect — ligne supprimée du back-office : %s × %d. Stock libéré et total recalculé par %s.', $name, $qty, wp_get_current_user()->user_login ) );
    if ( $last_item ) {
        $order->update_status( 'cancelled', 'Drop & Collect — commande annulée car sa dernière ligne a été supprimée.' );
    }
    $order->save();
    if ( function_exists( 'slc_notify_customer' ) ) {
        $message = $last_item
            ? 'Votre commande n°' . $order->get_order_number() . ' est annulée car son dernier article est indisponible.'
            : 'L’article « ' . $name . ' » a été retiré de votre commande n°' . $order->get_order_number() . '. Le total a été mis à jour.';
        if ( $was_paid && $difference > 0 ) $message .= ' Le remboursement correspondant est en cours de traitement.';
        if ( function_exists( 'slc_order_tracking_url' ) ) $message .= ' Suivi : ' . slc_order_tracking_url( $order );
        slc_notify_customer( $order, 'Mise à jour de votre commande Santa Lucia', $message );
    }
    slc_redirect_back( 'removed' );
} );
