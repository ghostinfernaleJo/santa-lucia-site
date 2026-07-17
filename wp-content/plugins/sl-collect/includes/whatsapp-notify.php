<?php
/**
 * Drop & Collect — Notifications WhatsApp (Meta Cloud API)
 * Envoie des messages « modèle » (template) approuvés par Meta aux étapes clés
 * de la commande : reçue, payée (code), prête au retrait, annulée.
 * Réglages : Commandes retrait → WhatsApp. Rien n'est envoyé tant que ce n'est
 * pas activé ET renseigné (Phone Number ID + token + nom de modèle).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ------------------------------------------------------------------ Helpers */

function slc_wa_opts() {
    return wp_parse_args( (array) get_option( 'slc_wa', [] ), [
        'enabled'       => '',
        'api_version'   => 'v20.0',
        'phone_id'      => '',
        'token'         => '',
        'lang'          => 'fr',
        'tpl_received'  => '',
        'tpl_paid'      => '',
        'tpl_ready'     => '',
        'tpl_cancelled' => '',
    ] );
}

function slc_wa_enabled() {
    $o = slc_wa_opts();
    return $o['enabled'] === '1' && $o['phone_id'] !== '' && $o['token'] !== '';
}

/** Numéro au format international sans « + » (Cameroun par défaut). */
function slc_wa_format_phone( $raw ) {
    $d = preg_replace( '/\D+/', '', (string) $raw );
    if ( $d === '' ) return '';
    $d = preg_replace( '/^00/', '', $d );
    if ( strlen( $d ) === 9 && preg_match( '/^[62]/', $d ) ) $d = '237' . $d; // 6xxxxxxxx -> 2376xxxxxxxx
    return $d;
}

/** Envoi d'un message « template ». $params = variables {{1}},{{2}}… du corps. */
function slc_wa_send( $to, $template, $params = [] ) {
    if ( ! slc_wa_enabled() || $template === '' ) {
        return [ 'ok' => false, 'msg' => 'WhatsApp non configuré (ou modèle vide).' ];
    }
    $o  = slc_wa_opts();
    $to = slc_wa_format_phone( $to );
    if ( $to === '' ) return [ 'ok' => false, 'msg' => 'Numéro de téléphone invalide.' ];

    $components = [];
    if ( ! empty( $params ) ) {
        $components[] = [
            'type'       => 'body',
            'parameters' => array_map( function ( $p ) { return [ 'type' => 'text', 'text' => (string) $p ]; }, array_values( $params ) ),
        ];
    }
    $payload = [
        'messaging_product' => 'whatsapp',
        'to'                => $to,
        'type'              => 'template',
        'template'          => [
            'name'       => $template,
            'language'   => [ 'code' => $o['lang'] ?: 'fr' ],
            'components' => $components,
        ],
    ];
    $ver  = $o['api_version'] ?: 'v20.0';
    $resp = wp_remote_post( "https://graph.facebook.com/{$ver}/{$o['phone_id']}/messages", [
        'headers' => [ 'Authorization' => 'Bearer ' . $o['token'], 'Content-Type' => 'application/json' ],
        'body'    => wp_json_encode( $payload ),
        'timeout' => 20,
    ] );

    if ( is_wp_error( $resp ) ) {
        $r = [ 'ok' => false, 'msg' => $resp->get_error_message() ];
    } else {
        $code = (int) wp_remote_retrieve_response_code( $resp );
        $body = json_decode( wp_remote_retrieve_body( $resp ), true );
        if ( $code < 300 ) {
            $r = [ 'ok' => true, 'msg' => 'Envoyé', 'id' => $body['messages'][0]['id'] ?? '' ];
        } else {
            $r = [ 'ok' => false, 'msg' => 'Erreur ' . $code . ' : ' . ( $body['error']['message'] ?? wp_remote_retrieve_body( $resp ) ) ];
        }
    }
    update_option( 'slc_wa_last', [ 'time' => current_time( 'mysql' ), 'to' => $to, 'tpl' => $template, 'r' => $r ], false );
    return $r;
}

/* ------------------------------------------------- Déclencheurs de commande */

// Commande passée (Click & Collect) -> « reçue »
add_action( 'woocommerce_checkout_order_processed', 'slc_wa_on_new_order', 20, 1 );
function slc_wa_on_new_order( $order_id ) {
    if ( ! slc_wa_enabled() ) return;
    $order = wc_get_order( $order_id );
    if ( ! $order || ! $order->get_meta( '_sl_collect_agence' ) ) return;
    $o = slc_wa_opts();
    slc_wa_send( $order->get_billing_phone(), $o['tpl_received'], [
        $order->get_order_number(), slc_agence_name( $order->get_meta( '_sl_collect_agence' ) ),
    ] );
}

// Paiement reçu -> « payée » (+ code de retrait)
add_action( 'woocommerce_payment_complete', 'slc_wa_on_paid', 30, 1 );
function slc_wa_on_paid( $order_id ) {
    if ( ! slc_wa_enabled() ) return;
    $order = wc_get_order( $order_id );
    if ( ! $order || ! $order->get_meta( '_sl_collect_agence' ) ) return;
    $o = slc_wa_opts();
    slc_wa_send( $order->get_billing_phone(), $o['tpl_paid'], [
        $order->get_order_number(), $order->get_meta( '_sl_collect_code' ) ?: '—',
    ] );
}

// Changement de statut -> « prête » (retrait) ou « annulée »
add_action( 'woocommerce_order_status_changed', 'slc_wa_on_status', 30, 4 );
function slc_wa_on_status( $order_id, $from, $to, $order ) {
    if ( ! slc_wa_enabled() || ! $order || ! $order->get_meta( '_sl_collect_agence' ) ) return;
    $o = slc_wa_opts();
    if ( 'sl-prete' === $to ) {
        slc_wa_send( $order->get_billing_phone(), $o['tpl_ready'], [
            $order->get_order_number(),
            slc_agence_name( $order->get_meta( '_sl_collect_agence' ) ),
            $order->get_meta( '_sl_collect_code' ) ?: '—',
        ] );
    } elseif ( 'cancelled' === $to ) {
        slc_wa_send( $order->get_billing_phone(), $o['tpl_cancelled'], [ $order->get_order_number() ] );
    }
}

/* ----------------------------------------------------- Page de réglages */

add_action( 'admin_menu', 'slc_wa_menu', 1001 );
function slc_wa_menu() {
    if ( function_exists( 'slc_is_admin_user' ) && ! slc_is_admin_user() ) return;
    add_submenu_page( 'sl-collect', 'Notifications WhatsApp', 'WhatsApp', 'read', 'sl-collect-wa', 'slc_wa_settings_page' );
}

function slc_wa_settings_page() {
    if ( function_exists( 'slc_is_admin_user' ) && ! slc_is_admin_user() ) wp_die( 'Accès refusé.' );
    $msg = '';

    if ( isset( $_POST['slc_wa_save'] ) && check_admin_referer( 'slc_wa_save' ) ) {
        update_option( 'slc_wa', [
            'enabled'       => empty( $_POST['enabled'] ) ? '' : '1',
            'api_version'   => sanitize_text_field( wp_unslash( $_POST['api_version'] ?? 'v20.0' ) ) ?: 'v20.0',
            'phone_id'      => sanitize_text_field( wp_unslash( $_POST['phone_id'] ?? '' ) ),
            'token'         => trim( wp_unslash( $_POST['token'] ?? '' ) ),
            'lang'          => sanitize_text_field( wp_unslash( $_POST['lang'] ?? 'fr' ) ) ?: 'fr',
            'tpl_received'  => sanitize_text_field( wp_unslash( $_POST['tpl_received'] ?? '' ) ),
            'tpl_paid'      => sanitize_text_field( wp_unslash( $_POST['tpl_paid'] ?? '' ) ),
            'tpl_ready'     => sanitize_text_field( wp_unslash( $_POST['tpl_ready'] ?? '' ) ),
            'tpl_cancelled' => sanitize_text_field( wp_unslash( $_POST['tpl_cancelled'] ?? '' ) ),
        ] );
        $msg = 'Réglages WhatsApp enregistrés.';
    }

    if ( isset( $_POST['slc_wa_test'] ) && check_admin_referer( 'slc_wa_save' ) ) {
        $to  = sanitize_text_field( wp_unslash( $_POST['test_phone'] ?? '' ) );
        $tpl = sanitize_text_field( wp_unslash( $_POST['test_tpl'] ?? '' ) );
        $r   = slc_wa_send( $to, $tpl, [ 'TEST-001', 'Agence Test', 'SL-TEST' ] );
        $msg = 'Test : ' . ( $r['ok'] ? '✅ ' . $r['msg'] . ( ! empty( $r['id'] ) ? ' (id ' . esc_html( $r['id'] ) . ')' : '' ) : '❌ ' . esc_html( $r['msg'] ) );
    }

    $o    = slc_wa_opts();
    $last = get_option( 'slc_wa_last', [] );
    ?>
    <div class="wrap">
        <h1>💬 Notifications WhatsApp (Drop &amp; Collect)</h1>
        <?php if ( $msg ) : ?><div class="notice notice-info"><p><?php echo wp_kses_post( $msg ); ?></p></div><?php endif; ?>
        <p style="max-width:760px;">Envoi automatique via <strong>Meta WhatsApp Cloud API</strong>. Les messages sont des <strong>modèles approuvés par Meta</strong> (obligatoire). Indiquez le nom exact de chaque modèle et l'ordre des variables ci-dessous.</p>

        <form method="post">
            <?php wp_nonce_field( 'slc_wa_save' ); ?>
            <table class="form-table">
                <tr><th>Activer les notifications</th><td><label><input type="checkbox" name="enabled" value="1" <?php checked( $o['enabled'], '1' ); ?>> Envoyer les messages WhatsApp</label></td></tr>
                <tr><th>Phone Number ID</th><td><input type="text" name="phone_id" value="<?php echo esc_attr( $o['phone_id'] ); ?>" class="regular-text" placeholder="ex. 123456789012345"></td></tr>
                <tr><th>Token d'accès (permanent)</th><td><input type="password" name="token" value="<?php echo esc_attr( $o['token'] ); ?>" class="large-text" autocomplete="off"></td></tr>
                <tr><th>Version API</th><td><input type="text" name="api_version" value="<?php echo esc_attr( $o['api_version'] ); ?>" style="width:100px;"> · Langue modèles <input type="text" name="lang" value="<?php echo esc_attr( $o['lang'] ); ?>" style="width:70px;" placeholder="fr"></td></tr>
            </table>

            <h2>Modèles Meta (noms exacts)</h2>
            <table class="form-table">
                <tr><th>Commande reçue</th><td><input type="text" name="tpl_received" value="<?php echo esc_attr( $o['tpl_received'] ); ?>" class="regular-text"> <span class="description">Variables : {{1}} n° commande · {{2}} agence</span></td></tr>
                <tr><th>Paiement reçu</th><td><input type="text" name="tpl_paid" value="<?php echo esc_attr( $o['tpl_paid'] ); ?>" class="regular-text"> <span class="description">{{1}} n° commande · {{2}} code de retrait</span></td></tr>
                <tr><th>Commande prête</th><td><input type="text" name="tpl_ready" value="<?php echo esc_attr( $o['tpl_ready'] ); ?>" class="regular-text"> <span class="description">{{1}} n° commande · {{2}} agence · {{3}} code de retrait</span></td></tr>
                <tr><th>Commande annulée</th><td><input type="text" name="tpl_cancelled" value="<?php echo esc_attr( $o['tpl_cancelled'] ); ?>" class="regular-text"> <span class="description">{{1}} n° commande</span></td></tr>
            </table>
            <p><button type="submit" name="slc_wa_save" class="button button-primary">Enregistrer</button></p>
        </form>

        <hr>
        <h2>Test d'envoi</h2>
        <form method="post">
            <?php wp_nonce_field( 'slc_wa_save' ); ?>
            <p>
                Numéro <input type="text" name="test_phone" class="regular-text" placeholder="+237 6XX XX XX XX">
                · Modèle <input type="text" name="test_tpl" class="regular-text" placeholder="ex. commande_prete">
                <button type="submit" name="slc_wa_test" class="button">Envoyer un test</button>
            </p>
            <p class="description">Le test envoie les variables « TEST-001 / Agence Test / SL-TEST ». Adaptez le modèle à son nombre de variables.</p>
        </form>
        <?php if ( ! empty( $last ) ) : ?>
        <p class="description">Dernier envoi : <?php echo esc_html( $last['time'] ?? '' ); ?> → <?php echo esc_html( $last['to'] ?? '' ); ?> · <?php echo esc_html( $last['tpl'] ?? '' ); ?> · <?php echo ! empty( $last['r']['ok'] ) ? '✅' : '❌ ' . esc_html( $last['r']['msg'] ?? '' ); ?></p>
        <?php endif; ?>
    </div>
    <?php
}
