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

/** Statuts geres par l'ecran. */
function slc_screen_statuses() {
    return [
        'actives'    => 'Toutes les commandes actives',
        'pending'    => 'En attente (à confirmer/payer)',
        'processing' => 'Payées — à préparer',
        'sl-prete'   => 'Prêtes — à remettre',
        'completed'  => 'Retirées',
        'cancelled'  => 'Annulées',
    ];
}

/** Statuts couverts par la vue « actives ». */
function slc_active_statuses() {
    return [ 'pending', 'processing', 'sl-prete' ];
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
    $statut_sel = isset( $_GET['statut'] ) ? sanitize_key( wp_unslash( $_GET['statut'] ) ) : 'actives';
    if ( ! isset( slc_screen_statuses()[ $statut_sel ] ) ) $statut_sel = 'actives';
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
            $tous_statuts = array_diff( array_keys( slc_screen_statuses() ), [ 'actives' ] );
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
        $statuts_requete = ( 'actives' === $statut_sel ) ? slc_active_statuses() : [ $statut_sel ];
        foreach ( slc_order_ids( $agence_sel, $statuts_requete, 100 ) as $oid ) {
            $o = wc_get_order( $oid );
            if ( $o ) $orders[] = $o;
        }
    }

    $notice = isset( $_GET['slc_msg'] ) ? sanitize_key( $_GET['slc_msg'] ) : '';
    ?>
    <div class="wrap">
        <h1>🏪 Commandes retrait <?php echo $agence_sel ? '— ' . esc_html( slc_agence_name( $agence_sel ) ) : ( $is_admin ? '— toutes les agences' : '' ); ?></h1>

        <?php if ( 'pret' === $notice ) : ?>
            <div class="notice notice-success is-dismissible"><p>Commande marquée <strong>Prête</strong> — le client a été notifié.</p></div>
        <?php elseif ( 'remis' === $notice ) : ?>
            <div class="notice notice-success is-dismissible"><p>Remise confirmée — commande <strong>Retirée</strong>. ✔</p></div>
        <?php elseif ( 'badcode' === $notice ) : ?>
            <div class="notice notice-error is-dismissible"><p><strong>Code de retrait incorrect.</strong> Vérifiez la facture du client.</p></div>
        <?php elseif ( 'err' === $notice ) : ?>
            <div class="notice notice-error is-dismissible"><p>Action impossible (commande introuvable ou statut inattendu).</p></div>
        <?php endif; ?>

        <form method="get" style="margin:14px 0;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
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
        <table class="widefat striped">
            <thead><tr>
                <th>N°</th><th>Client</th><th>Téléphone</th><th>Articles</th><th>Total</th>
                <?php if ( $is_admin && $agence_sel === '' ) : ?><th>Agence</th><?php endif; ?>
                <th>Statut</th><th>Date</th><th style="min-width:260px;">Action</th>
            </tr></thead>
            <tbody>
            <?php foreach ( $orders as $o ) :
                $st = $o->get_status();
                $items = [];
                foreach ( $o->get_items() as $it ) $items[] = $it->get_quantity() . '× ' . $it->get_name();
            ?>
                <tr>
                    <td><strong>n°<?php echo esc_html( $o->get_order_number() ); ?></strong></td>
                    <td><?php echo esc_html( trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() ) ); ?></td>
                    <td><a href="tel:<?php echo esc_attr( $o->get_billing_phone() ); ?>"><?php echo esc_html( $o->get_billing_phone() ); ?></a></td>
                    <td style="max-width:260px;"><?php echo esc_html( implode( ', ', array_slice( $items, 0, 3 ) ) . ( count( $items ) > 3 ? '…' : '' ) ); ?></td>
                    <td><?php echo wp_kses_post( $o->get_formatted_order_total() ); ?></td>
                    <?php if ( $is_admin && $agence_sel === '' ) : ?>
                        <td><?php echo esc_html( slc_agence_name( $o->get_meta( '_sl_collect_agence' ) ) ); ?></td>
                    <?php endif; ?>
                    <td><?php echo esc_html( wc_get_order_status_name( $st ) ); ?></td>
                    <td><?php echo esc_html( $o->get_date_created() ? $o->get_date_created()->date_i18n( 'd/m/Y H:i' ) : '—' ); ?></td>
                    <td>
                        <?php if ( 'processing' === $st ) : ?>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Marquer la commande n°<?php echo esc_js( $o->get_order_number() ); ?> comme PRÊTE ? Le client sera notifié.');">
                                <?php wp_nonce_field( 'slc_action_' . $o->get_id() ); ?>
                                <input type="hidden" name="action" value="slc_mark_ready">
                                <input type="hidden" name="order_id" value="<?php echo (int) $o->get_id(); ?>">
                                <button class="button button-primary">✅ Marquer PRÊTE</button>
                            </form>
                        <?php elseif ( 'sl-prete' === $st ) : ?>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:6px;align-items:center;">
                                <?php wp_nonce_field( 'slc_action_' . $o->get_id() ); ?>
                                <input type="hidden" name="action" value="slc_handover">
                                <input type="hidden" name="order_id" value="<?php echo (int) $o->get_id(); ?>">
                                <input type="text" name="code" placeholder="Code retrait" required
                                       style="width:120px;text-transform:uppercase;" autocomplete="off">
                                <button class="button button-primary">🤝 Remettre</button>
                            </form>
                        <?php elseif ( 'pending' === $st ) : ?>
                            <span style="color:#996800;">⏳ Attente confirmation/paiement client</span>
                        <?php else : ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
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
    if ( ! $order->has_status( 'processing' ) ) slc_redirect_back( 'err' );
    $user = wp_get_current_user();
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
    $order->update_status( 'completed', 'Drop & Collect — remise effectuée (code vérifié) par ' . $user->user_login . '.' );
    slc_redirect_back( 'remis' );
} );
