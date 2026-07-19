<?php
/**
 * Notifications push Web Push (Bons Plans & promotions).
 *
 * Sans bibliotheque externe : VAPID (JWT ES256 via openssl) + envois « sans
 * contenu » (le service worker va chercher le message du moment sur un
 * endpoint public) -> pas de chiffrement de payload aes128gcm a implementer.
 *
 * Regles d'envoi, pour ne pas faire fuir les abonnes :
 *  - AUTO : un DIGEST quotidien au plus (« N nouveaux bons plans »), jamais
 *    une notification par publication (77 BP publies en un seul jour le 16/07).
 *  - MANUEL : ecran d'envoi pour les promos flash (admins + editeurs).
 *
 * @package SL_Collect
 */

defined( 'ABSPATH' ) || exit;

/* ============================================================
   1. TABLE DES ABONNEMENTS
   ============================================================ */

function slp_table() {
    global $wpdb;
    return $wpdb->prefix . 'sl_push_subs';
}

add_action( 'admin_init', 'slp_install_table' );
function slp_install_table() {
    if ( get_option( 'slc_push_db_ver' ) === '1' ) {
        return;
    }
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    // endpoint_hash UNIQUE : un navigateur qui se re-abonne met a jour sa ligne
    // au lieu d'en creer une seconde (et de recevoir tout en double).
    dbDelta( 'CREATE TABLE ' . slp_table() . " (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        endpoint TEXT NOT NULL,
        endpoint_hash CHAR(32) NOT NULL,
        created_at DATETIME NOT NULL,
        last_seen DATETIME NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY endpoint_hash (endpoint_hash)
    ) {$wpdb->get_charset_collate()};" );
    update_option( 'slc_push_db_ver', '1' );
}

function slp_sub_count() {
    global $wpdb;
    return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . slp_table() );
}

/* ============================================================
   2. VAPID — cles et signature (openssl, courbe P-256)
   ============================================================ */

function slp_b64u( $bin ) {
    return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' );
}

/**
 * Paire de cles VAPID, generee une fois puis conservee en option.
 * ⚠️ Ne JAMAIS regenerer a la legere : tous les abonnements existants sont
 * lies a la cle publique — la changer les invalide tous.
 */
function slp_vapid_keys() {
    $keys = get_option( 'slc_push_vapid' );
    if ( is_array( $keys ) && ! empty( $keys['pub'] ) && ! empty( $keys['pem'] ) ) {
        return $keys;
    }
    if ( ! function_exists( 'openssl_pkey_new' ) ) {
        return null;
    }
    $res = openssl_pkey_new( [
        'curve_name'       => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ] );
    if ( ! $res || ! openssl_pkey_export( $res, $pem ) ) {
        return null;
    }
    $d = openssl_pkey_get_details( $res );
    if ( empty( $d['ec']['x'] ) || empty( $d['ec']['y'] ) ) {
        return null;
    }
    // Cle publique = point EC non compresse : 0x04 || X(32) || Y(32).
    $pub  = slp_b64u( "\x04"
        . str_pad( $d['ec']['x'], 32, "\0", STR_PAD_LEFT )
        . str_pad( $d['ec']['y'], 32, "\0", STR_PAD_LEFT ) );
    $keys = [ 'pub' => $pub, 'pem' => $pem ];
    update_option( 'slc_push_vapid', $keys, false );
    return $keys;
}

/** Signature DER (openssl) -> R||S brut 64 octets (format JWT ES256). */
function slp_der_to_raw( $der ) {
    $offset = 2;
    if ( ord( $der[1] ) & 0x80 ) {
        $offset = 2 + ( ord( $der[1] ) & 0x7F );
    }
    $out = '';
    for ( $i = 0; $i < 2; $i++ ) {
        $offset++; // tag INTEGER (0x02)
        $len     = ord( $der[ $offset ] );
        $offset++;
        $int     = ltrim( substr( $der, $offset, $len ), "\0" );
        $offset += $len;
        $out    .= str_pad( $int, 32, "\0", STR_PAD_LEFT );
    }
    return $out;
}

/** JWT VAPID pour un service push donne (mis en cache par origine et par requete). */
function slp_jwt( $aud ) {
    static $cache = [];
    if ( isset( $cache[ $aud ] ) ) {
        return $cache[ $aud ];
    }
    $k = slp_vapid_keys();
    if ( ! $k ) {
        return '';
    }
    $head = slp_b64u( wp_json_encode( [ 'typ' => 'JWT', 'alg' => 'ES256' ] ) );
    $body = slp_b64u( wp_json_encode( [
        'aud' => $aud,
        'exp' => time() + 12 * HOUR_IN_SECONDS,
        'sub' => 'mailto:' . get_option( 'admin_email' ),
    ] ) );
    $data = $head . '.' . $body;
    if ( ! openssl_sign( $data, $der, $k['pem'], OPENSSL_ALGO_SHA256 ) ) {
        return '';
    }
    return $cache[ $aud ] = $data . '.' . slp_b64u( slp_der_to_raw( $der ) );
}

/* ============================================================
   3. ENVOI
   ============================================================ */

/**
 * Envoie un signal push (sans contenu) a un abonnement.
 * @return int Code HTTP (201 = accepte ; 404/410 = abonnement mort).
 */
function slp_send_one( $endpoint ) {
    $p = wp_parse_url( $endpoint );
    if ( empty( $p['scheme'] ) || empty( $p['host'] ) || 'https' !== $p['scheme'] ) {
        return 410; // endpoint invalide : a purger
    }
    $jwt = slp_jwt( $p['scheme'] . '://' . $p['host'] );
    if ( '' === $jwt ) {
        return 0;
    }
    $k = slp_vapid_keys();
    $r = wp_remote_post( $endpoint, [
        'timeout' => 6,
        'headers' => [
            'TTL'           => '86400',
            'Urgency'       => 'normal',
            'Authorization' => 'vapid t=' . $jwt . ', k=' . $k['pub'],
            'Content-Length' => '0',
        ],
        'body'    => '',
    ] );
    return is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r );
}

/**
 * Diffusion a tous les abonnes, PAR LOTS de 80 via des evenements cron
 * chaines : aucun visiteur ni admin n'attend pendant les envois, et un
 * gros parc d'abonnes ne peut pas faire tomber une requete en timeout.
 */
function slp_broadcast( $title, $body, $url ) {
    update_option( 'slc_push_last', [
        'title' => $title,
        'body'  => $body,
        'url'   => $url ?: home_url( '/bon-plans/' ),
        'tag'   => 'slbp',
        'ts'    => time(),
    ], false );
    update_option( 'slc_push_cursor', 0, false );
    update_option( 'slc_push_sent_run', 0, false );

    // Journal (10 derniers envois), affiche dans l'ecran d'admin.
    $log = get_option( 'slc_push_log', [] );
    array_unshift( $log, [ 'date' => current_time( 'mysql' ), 'titre' => $title, 'abonnes' => slp_sub_count() ] );
    update_option( 'slc_push_log', array_slice( $log, 0, 10 ), false );

    if ( ! wp_next_scheduled( 'slc_push_batch' ) ) {
        wp_schedule_single_event( time(), 'slc_push_batch' );
    }
}

add_action( 'slc_push_batch', 'slp_process_batch' );
function slp_process_batch() {
    global $wpdb;
    $t      = slp_table();
    $cursor = (int) get_option( 'slc_push_cursor', 0 );
    $rows   = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, endpoint FROM {$t} WHERE id > %d ORDER BY id ASC LIMIT 80", $cursor
    ), ARRAY_A );

    if ( ! $rows ) {
        return; // diffusion terminee
    }
    $sent = (int) get_option( 'slc_push_sent_run', 0 );
    foreach ( $rows as $r ) {
        $code = slp_send_one( $r['endpoint'] );
        if ( in_array( $code, [ 404, 410 ], true ) ) {
            // Abonnement expire (navigateur reinstalle, permission retiree...) :
            // le service push nous le dit, on purge — sinon la table enfle.
            $wpdb->delete( $t, [ 'id' => $r['id'] ] );
        } elseif ( $code >= 200 && $code < 300 ) {
            $sent++;
        }
        $cursor = (int) $r['id'];
    }
    update_option( 'slc_push_cursor', $cursor, false );
    update_option( 'slc_push_sent_run', $sent, false );
    wp_schedule_single_event( time() + 30, 'slc_push_batch' );
}

/* ============================================================
   4. DIGEST QUOTIDIEN (via le cron horaire existant)
   ============================================================ */

add_action( 'sl_collect_cron', 'slp_daily_digest', 30 );
function slp_daily_digest() {
    if ( get_option( 'slc_push_digest_on', '1' ) !== '1' ) {
        return;
    }
    $now = current_time( 'timestamp' );
    if ( (int) date( 'G', $now ) < (int) get_option( 'slc_push_digest_hour', 10 ) ) {
        return;
    }
    $today = date( 'Y-m-d', $now );
    if ( get_option( 'slc_push_digest_done' ) === $today ) {
        return;
    }
    update_option( 'slc_push_digest_done', $today, false );

    if ( slp_sub_count() < 1 ) {
        return;
    }
    $nouveaux = get_posts( [
        'post_type'      => 'sl_bon_plan',
        'post_status'    => 'publish',
        'date_query'     => [ [ 'after' => '24 hours ago' ] ],
        'fields'         => 'ids',
        'posts_per_page' => 100,
        'no_found_rows'  => true,
    ] );
    $n = count( $nouveaux );
    if ( $n < 1 ) {
        return; // rien de neuf : on se tait (le silence protege l'attention)
    }
    slp_broadcast(
        $n > 1 ? sprintf( '🔥 %d nouveaux bons plans aujourd\'hui', $n ) : '🔥 Nouveau bon plan chez Santa Lucia',
        'Découvrez les offres du jour dans votre agence.',
        home_url( '/bon-plans/' )
    );
}

/* ============================================================
   5. ENDPOINTS PUBLICS (abonnement / contenu du moment)
   Sans nonce : pages servies par Varnish (meme raisonnement que la
   balise stats) — compense par assainissement strict + plafond IP.
   ============================================================ */

add_action( 'wp_ajax_sl_push_sub', 'slp_ajax_subscribe' );
add_action( 'wp_ajax_nopriv_sl_push_sub', 'slp_ajax_subscribe' );
function slp_ajax_subscribe() {
    $ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    $key = 'slp_rl_' . md5( $ip );
    $n   = (int) get_transient( $key );
    if ( $n > 10 ) {
        wp_send_json_success();
    }
    set_transient( $key, $n + 1, HOUR_IN_SECONDS );

    $endpoint = isset( $_POST['endpoint'] ) ? esc_url_raw( wp_unslash( $_POST['endpoint'] ) ) : '';
    if ( '' === $endpoint || strlen( $endpoint ) > 1000 || 0 !== strpos( $endpoint, 'https://' ) ) {
        wp_send_json_error();
    }

    global $wpdb;
    $now = current_time( 'mysql' );
    $wpdb->query( $wpdb->prepare(
        'INSERT INTO ' . slp_table() . ' (endpoint, endpoint_hash, created_at, last_seen)
         VALUES (%s, %s, %s, %s)
         ON DUPLICATE KEY UPDATE last_seen = VALUES(last_seen)',
        $endpoint, md5( $endpoint ), $now, $now
    ) );
    wp_send_json_success();
}

add_action( 'wp_ajax_sl_push_unsub', 'slp_ajax_unsubscribe' );
add_action( 'wp_ajax_nopriv_sl_push_unsub', 'slp_ajax_unsubscribe' );
function slp_ajax_unsubscribe() {
    $endpoint = isset( $_POST['endpoint'] ) ? esc_url_raw( wp_unslash( $_POST['endpoint'] ) ) : '';
    if ( $endpoint !== '' ) {
        global $wpdb;
        $wpdb->delete( slp_table(), [ 'endpoint_hash' => md5( $endpoint ) ] );
    }
    wp_send_json_success();
}

/** Contenu du moment, lu par le service worker apres chaque signal. */
add_action( 'wp_ajax_sl_push_latest', 'slp_ajax_latest' );
add_action( 'wp_ajax_nopriv_sl_push_latest', 'slp_ajax_latest' );
function slp_ajax_latest() {
    $last = get_option( 'slc_push_last' );
    wp_send_json_success( is_array( $last ) ? $last : [] );
}

/* ============================================================
   6. BOUTON D'ABONNEMENT (front, pages bons plans uniquement)
   ============================================================ */

add_action( 'wp_footer', 'slp_print_front_js', 97 );
function slp_print_front_js() {
    if ( is_admin() ) {
        return;
    }
    $k = slp_vapid_keys();
    if ( ! $k ) {
        return; // openssl EC indisponible : pas de push, pas de bouton
    }
    // Worker UNIQUE a la racine (portee /) : sert la PWA ET le push — requis
    // notamment pour le push iOS en application installee. L'ancien
    // assets/sw-push.js (portee dossier plugin) est migre automatiquement
    // cote client (voir le nettoyage legacy dans le JS ci-dessous).
    $sw   = home_url( '/sw.js' );
    $ajax = admin_url( 'admin-ajax.php' );
    ?>
    <script>
    (function(){
        if ( !('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window) ) return;
        // Uniquement la ou vivent les bons plans : pas de harcelement site-wide.
        if ( ! document.querySelector('.slbp-card, .slbp-grid, .slf-bp-embed') ) return;
        if ( Notification.permission === 'denied' ) return;
        if ( localStorage.getItem('slpDismiss') ) return;

        var PUB  = <?php echo wp_json_encode( $k['pub'] ); ?>;
        var SW   = <?php echo wp_json_encode( $sw ); ?>;
        var AJAX = <?php echo wp_json_encode( $ajax ); ?>;

        function b64ToU8(s){
            var pad = '='.repeat((4 - s.length % 4) % 4);
            var raw = atob((s + pad).replace(/-/g,'+').replace(/_/g,'/'));
            var out = new Uint8Array(raw.length);
            for (var i=0;i<raw.length;i++) out[i] = raw.charCodeAt(i);
            return out;
        }

        // Migration : purger l'ancien worker sw-push.js (portee plugin). Son
        // abonnement est resilie proprement (serveur prevenu) — le visiteur
        // pourra se reabonner en un clic sur le worker racine.
        navigator.serviceWorker.getRegistrations().then(function(regs){
            regs.forEach(function(r){
                var src = (r.active && r.active.scriptURL) || (r.waiting && r.waiting.scriptURL) || '';
                if ( src.indexOf('sw-push.js') === -1 ) return;
                r.pushManager.getSubscription().then(function(s){
                    if ( s ) {
                        var body = 'action=sl_push_unsub&endpoint=' + encodeURIComponent(s.endpoint);
                        fetch(AJAX, { method:'POST', credentials:'same-origin',
                            headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body });
                        s.unsubscribe();
                    }
                    r.unregister();
                }).catch(function(){ r.unregister(); });
            });
        }).catch(function(){});

        navigator.serviceWorker.getRegistration(SW).then(function(reg){
            return reg ? reg.pushManager.getSubscription() : null;
        }).then(function(sub){
            if ( sub ) return; // deja abonne : rien a afficher
            show();
        }).catch(show);

        function show(){
            var btn = document.createElement('div');
            btn.id = 'slp-bell';
            btn.innerHTML = '<button type="button" class="slp-cta">🔔 M\'alerter des bons plans</button><button type="button" class="slp-x" aria-label="Fermer">×</button>';
            document.body.appendChild(btn);
            var css = document.createElement('style');
            /* z-index : la navbar mobile du theme est a 1000000 (gotcha connu) */
            css.textContent = '#slp-bell{position:fixed;right:14px;bottom:76px;z-index:1000005;display:flex;align-items:center;gap:4px;}'
                + '#slp-bell .slp-cta{border:none;border-radius:22px;background:#E91E63;color:#fff;font-weight:600;font-size:13px;padding:10px 16px;cursor:pointer;box-shadow:0 6px 18px rgba(0,0,0,.25);}'
                + '#slp-bell .slp-cta:hover{background:#c2185b;}'
                + '#slp-bell .slp-x{border:none;background:rgba(0,0,0,.45);color:#fff;border-radius:50%;width:22px;height:22px;line-height:1;cursor:pointer;font-size:13px;}';
            document.head.appendChild(css);

            btn.querySelector('.slp-x').addEventListener('click', function(){
                localStorage.setItem('slpDismiss','1');
                btn.remove();
            });

            btn.querySelector('.slp-cta').addEventListener('click', function(){
                var cta = this;
                cta.disabled = true; cta.textContent = 'Activation…';
                navigator.serviceWorker.register(SW).then(function(reg){
                    return navigator.serviceWorker.ready.then(function(){ return reg; });
                }).then(function(reg){
                    return reg.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: b64ToU8(PUB)
                    });
                }).then(function(sub){
                    var body = 'action=sl_push_sub&endpoint=' + encodeURIComponent(sub.endpoint);
                    return fetch(AJAX, { method:'POST', credentials:'same-origin',
                        headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body });
                }).then(function(){
                    cta.textContent = '✔ Alertes activées';
                    setTimeout(function(){ btn.remove(); }, 2200);
                }).catch(function(){
                    // Permission refusee ou souscription impossible : on n'insiste pas.
                    localStorage.setItem('slpDismiss','1');
                    btn.remove();
                });
            });
        }
    })();
    </script>
    <?php
}

/* ============================================================
   7. ECRAN D'ADMIN (envoi manuel + reglages digest)
   ============================================================ */

add_action( 'admin_menu', 'slp_menu', 1000 ); // le parent sl-collect est a 999
function slp_menu() {
    add_submenu_page(
        'sl-collect',
        'Notifications push',
        '🔔 Notifications push',
        'edit_others_posts',
        'sl-push',
        'slp_render_page'
    );
}

function slp_render_page() {
    if ( ! current_user_can( 'edit_others_posts' ) && ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Accès refusé.' );
    }

    $notice = '';
    if ( isset( $_POST['slp_send'] ) && check_admin_referer( 'slp_send' ) ) {
        $titre = sanitize_text_field( wp_unslash( $_POST['slp_title'] ?? '' ) );
        $corps = sanitize_text_field( wp_unslash( $_POST['slp_body'] ?? '' ) );
        $url   = esc_url_raw( wp_unslash( $_POST['slp_url'] ?? '' ) );
        if ( $titre === '' ) {
            $notice = '<div class="notice notice-error inline"><p>Le titre est obligatoire.</p></div>';
        } elseif ( slp_sub_count() < 1 ) {
            $notice = '<div class="notice notice-warning inline"><p>Aucun abonné pour l\'instant : rien n\'a été envoyé.</p></div>';
        } else {
            slp_broadcast( $titre, $corps, $url );
            $notice = '<div class="notice notice-success inline"><p><strong>Envoi lancé</strong> vers ' . (int) slp_sub_count() . ' abonné(s), par lots (quelques minutes au plus).</p></div>';
        }
    }
    if ( isset( $_POST['slp_settings'] ) && check_admin_referer( 'slp_settings' ) ) {
        update_option( 'slc_push_digest_on', empty( $_POST['digest_on'] ) ? '0' : '1' );
        update_option( 'slc_push_digest_hour', max( 6, min( 20, (int) ( $_POST['digest_hour'] ?? 10 ) ) ) );
        $notice = '<div class="notice notice-success inline"><p>Réglages enregistrés.</p></div>';
    }

    $vapid = slp_vapid_keys();
    $log   = get_option( 'slc_push_log', [] );
    ?>
    <div class="wrap">
        <h1>🔔 Notifications push — Bons Plans</h1>
        <?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput ?>

        <?php if ( ! $vapid ) : ?>
            <div class="notice notice-error"><p><strong>OpenSSL (courbe P-256) indisponible sur ce serveur :</strong> le push ne peut pas fonctionner. Contactez l'hébergeur.</p></div>
        <?php endif; ?>

        <div style="display:flex;gap:12px;flex-wrap:wrap;margin:14px 0;">
            <div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:12px 18px;"><small>Abonnés</small><br><b style="font-size:20px;color:#1d54a0;"><?php echo slp_sub_count(); ?></b></div>
            <div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:12px 18px;"><small>Digest quotidien</small><br><b style="font-size:20px;color:<?php echo get_option( 'slc_push_digest_on', '1' ) === '1' ? '#1e7b34' : '#b32d2e'; ?>;"><?php echo get_option( 'slc_push_digest_on', '1' ) === '1' ? 'Actif — ' . (int) get_option( 'slc_push_digest_hour', 10 ) . ' h' : 'Coupé'; ?></b></div>
        </div>

        <p class="description" style="max-width:760px;">
            Le bouton « 🔔 M'alerter des bons plans » s'affiche sur les pages bons plans pour les visiteurs
            (Chrome/Firefox/Edge, Android inclus ; sur iPhone, uniquement si le site est installé sur l'écran d'accueil).
            L'abonnement est anonyme — aucun lien avec les comptes clients.
        </p>

        <h2>Envoyer une notification maintenant</h2>
        <p class="description">Pour une promo flash. À utiliser avec parcimonie : chaque notification de trop fabrique des désabonnés.</p>
        <form method="post" style="max-width:560px;background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:16px 20px;">
            <?php wp_nonce_field( 'slp_send' ); ?>
            <p><label><strong>Titre</strong> (obligatoire)<br>
                <input type="text" name="slp_title" class="regular-text" style="width:100%;" maxlength="80"
                       placeholder="🔥 Vente flash à Mokolo : -40% ce week-end"></label></p>
            <p><label><strong>Message</strong><br>
                <input type="text" name="slp_body" class="regular-text" style="width:100%;" maxlength="140"
                       placeholder="Jusqu'à dimanche soir, dans la limite des stocks."></label></p>
            <p><label><strong>Lien à l'ouverture</strong> (défaut : page des bons plans)<br>
                <input type="url" name="slp_url" class="regular-text" style="width:100%;"
                       placeholder="<?php echo esc_attr( home_url( '/bon-plans/' ) ); ?>"></label></p>
            <p><button class="button button-primary" name="slp_send" value="1"
                       onclick="return confirm('Envoyer cette notification à tous les abonnés ?');">Envoyer aux <?php echo slp_sub_count(); ?> abonné(s)</button></p>
        </form>

        <h2>Digest automatique</h2>
        <form method="post" style="max-width:560px;">
            <?php wp_nonce_field( 'slp_settings' ); ?>
            <p><label><input type="checkbox" name="digest_on" value="1" <?php checked( get_option( 'slc_push_digest_on', '1' ), '1' ); ?>>
                Envoyer chaque jour un résumé des nouveaux bons plans (un seul envoi, uniquement s'il y a du nouveau)</label></p>
            <p><label>À partir de
                <select name="digest_hour">
                    <?php for ( $h = 6; $h <= 20; $h++ ) : ?>
                        <option value="<?php echo $h; ?>" <?php selected( (int) get_option( 'slc_push_digest_hour', 10 ), $h ); ?>><?php echo $h; ?> h</option>
                    <?php endfor; ?>
                </select>
            </label></p>
            <p><button class="button" name="slp_settings" value="1">Enregistrer</button></p>
        </form>

        <?php if ( $log ) : ?>
            <h2>Derniers envois</h2>
            <table class="widefat striped" style="max-width:560px;">
                <thead><tr><th>Date</th><th>Titre</th><th>Abonnés visés</th></tr></thead>
                <tbody>
                <?php foreach ( $log as $l ) : ?>
                    <tr><td><?php echo esc_html( mysql2date( 'd/m/Y H:i', $l['date'] ) ); ?></td>
                        <td><?php echo esc_html( $l['titre'] ); ?></td>
                        <td><?php echo (int) $l['abonnes']; ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}
