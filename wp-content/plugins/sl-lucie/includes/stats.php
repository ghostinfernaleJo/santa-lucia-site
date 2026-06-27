<?php
/**
 * Statistiques d'utilisation de Lucie.
 * Journalisation anonyme (IP hachee) dans une table dediee + page d'admin.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const SL_LUCIE_DB_VER = '2';

function sl_lucie_log_table() {
    global $wpdb;
    return $wpdb->prefix . 'sl_lucie_log';
}

/** Cree / met a jour la table de logs si besoin. */
add_action( 'plugins_loaded', 'sl_lucie_maybe_create_table' );
function sl_lucie_maybe_create_table() {
    if ( get_option( 'sl_lucie_db_ver' ) === SL_LUCIE_DB_VER ) return;
    global $wpdb;
    $table = sl_lucie_log_table();
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        created datetime NOT NULL,
        ip_hash char(32) NOT NULL DEFAULT '',
        session_id varchar(40) NOT NULL DEFAULT '',
        message text NOT NULL,
        reply mediumtext NOT NULL,
        reply_len int unsigned NOT NULL DEFAULT 0,
        in_scope tinyint(1) NOT NULL DEFAULT 1,
        used_tools varchar(255) NOT NULL DEFAULT '',
        provider varchar(20) NOT NULL DEFAULT '',
        is_error tinyint(1) NOT NULL DEFAULT 0,
        response_ms int unsigned NOT NULL DEFAULT 0,
        PRIMARY KEY  (id),
        KEY created (created),
        KEY session_id (session_id)
    ) {$charset};" );
    update_option( 'sl_lucie_db_ver', SL_LUCIE_DB_VER, false );
}

/** Enregistre un evenement de conversation. */
function sl_lucie_log_event( $args ) {
    global $wpdb;
    $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
    $wpdb->insert( sl_lucie_log_table(), [
        'created'     => current_time( 'mysql' ),
        'ip_hash'     => $ip ? md5( $ip . ( defined( 'AUTH_SALT' ) ? AUTH_SALT : '' ) ) : '',
        'session_id'  => substr( sanitize_text_field( $args['session_id'] ?? '' ), 0, 40 ),
        'message'     => mb_substr( (string) ( $args['message'] ?? '' ), 0, 300 ),
        'reply'       => mb_substr( (string) ( $args['reply'] ?? '' ), 0, 6000 ),
        'reply_len'   => (int) ( $args['reply_len'] ?? 0 ),
        'in_scope'    => ! empty( $args['in_scope'] ) ? 1 : 0,
        'used_tools'  => substr( (string) ( $args['used_tools'] ?? '' ), 0, 255 ),
        'provider'    => substr( (string) ( $args['provider'] ?? '' ), 0, 20 ),
        'is_error'    => ! empty( $args['is_error'] ) ? 1 : 0,
        'response_ms' => (int) ( $args['response_ms'] ?? 0 ),
    ] );
    // Purge opportuniste (1 chance sur 50) des logs > 365 jours
    if ( mt_rand( 1, 50 ) === 1 ) {
        $wpdb->query( "DELETE FROM " . sl_lucie_log_table() . " WHERE created < DATE_SUB(NOW(), INTERVAL 365 DAY)" );
    }
}

/* ============================================================
   MENU + PAGE STATISTIQUES
   ============================================================ */
add_action( 'admin_menu', function () {
    if ( ! sl_lucie_can_manage() ) return;
    add_submenu_page( 'sl-lucie', 'Statistiques Lucie', 'Statistiques', 'edit_others_posts', 'sl-lucie-stats', 'sl_lucie_stats_page' );
}, 20 );

function sl_lucie_stats_page() {
    if ( ! sl_lucie_can_manage() ) wp_die( 'Acces refuse.' );
    global $wpdb;
    $table = sl_lucie_log_table();

    $allowed = [ 7, 30, 90, 365 ];
    $days = isset( $_GET['j'] ) ? (int) $_GET['j'] : 30;
    if ( ! in_array( $days, $allowed, true ) ) $days = 30;
    $since = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $days * DAY_IN_SECONDS );

    $exists = ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) === $table );

    // Agregats principaux
    $row = $exists ? $wpdb->get_row( $wpdb->prepare(
        "SELECT COUNT(*) total,
                COUNT(DISTINCT ip_hash) visiteurs,
                COUNT(DISTINCT NULLIF(session_id,'')) conversations,
                SUM(in_scope) en_scope,
                SUM(is_error) erreurs,
                AVG(NULLIF(response_ms,0)) tps_moy
         FROM {$table} WHERE created >= %s", $since ), ARRAY_A ) : null;

    $total   = (int) ( $row['total'] ?? 0 );
    $visit   = (int) ( $row['visiteurs'] ?? 0 );
    $convs   = max( (int) ( $row['conversations'] ?? 0 ), 0 );
    $enscope = (int) ( $row['en_scope'] ?? 0 );
    $errs    = (int) ( $row['erreurs'] ?? 0 );
    $tpsmoy  = round( (float) ( $row['tps_moy'] ?? 0 ) );
    $hors    = max( $total - $enscope, 0 );

    // Moyennes par jour
    $moy_msg_jour   = $total ? round( $total / $days, 1 ) : 0;
    // Visiteurs uniques moyens par jour (moyenne des distincts quotidiens)
    $daily = $exists ? $wpdb->get_results( $wpdb->prepare(
        "SELECT DATE(created) d, COUNT(*) n, COUNT(DISTINCT ip_hash) v
         FROM {$table} WHERE created >= %s GROUP BY DATE(created) ORDER BY d ASC", $since ), ARRAY_A ) : [];
    $jours_actifs = count( $daily );
    $moy_visit_jour = $jours_actifs ? round( array_sum( array_column( $daily, 'v' ) ) / $jours_actifs, 1 ) : 0;
    $moy_msg_conv   = $convs ? round( $total / $convs, 1 ) : 0;

    // Top questions
    $top = $exists ? $wpdb->get_results( $wpdb->prepare(
        "SELECT LOWER(TRIM(message)) q, COUNT(*) n FROM {$table}
         WHERE created >= %s AND message <> '' GROUP BY q ORDER BY n DESC LIMIT 30", $since ), ARRAY_A ) : [];

    // Utilisation des outils (agregation PHP sur la periode)
    $tool_rows = $exists ? $wpdb->get_col( $wpdb->prepare(
        "SELECT used_tools FROM {$table} WHERE created >= %s AND used_tools <> ''", $since ) ) : [];
    $tool_count = [];
    foreach ( $tool_rows as $r ) {
        foreach ( explode( ',', $r ) as $t ) { $t = trim( $t ); if ( $t !== '' ) $tool_count[ $t ] = ( $tool_count[ $t ] ?? 0 ) + 1; }
    }
    arsort( $tool_count );

    $max_day = 0; foreach ( $daily as $d ) { $max_day = max( $max_day, (int) $d['n'] ); }
    $card = function ( $label, $val, $sub = '' ) {
        echo '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:16px;min-width:150px;flex:1;">'
           . '<div style="font-size:28px;font-weight:700;color:#1D54A0;">' . esc_html( $val ) . '</div>'
           . '<div style="color:#555;font-size:13px;">' . esc_html( $label ) . '</div>'
           . ( $sub ? '<div style="color:#999;font-size:11px;margin-top:2px;">' . esc_html( $sub ) . '</div>' : '' )
           . '</div>';
    };
    ?>
    <div class="wrap">
        <h1><span class="dashicons dashicons-chart-bar"></span> Statistiques de Lucie</h1>

        <p>
            Periode :
            <?php foreach ( $allowed as $a ) :
                $url = admin_url( 'admin.php?page=sl-lucie-stats&j=' . $a );
                $lbl = $a === 365 ? '1 an' : $a . ' jours'; ?>
                <a class="button <?php echo $days === $a ? 'button-primary' : ''; ?>" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $lbl ); ?></a>
            <?php endforeach; ?>
        </p>

        <?php if ( ! $exists || $total === 0 ) : ?>
            <div class="notice notice-info"><p>Aucune donnee pour cette periode. Les statistiques apparaitront des que des visiteurs discuteront avec Lucie.</p></div>
        <?php else : ?>

        <div style="display:flex;gap:14px;flex-wrap:wrap;margin:16px 0;">
            <?php
            $card( 'Messages', number_format_i18n( $total ) );
            $card( 'Visiteurs uniques', number_format_i18n( $visit ) );
            $card( 'Conversations', number_format_i18n( $convs ) );
            $card( 'Moy. visiteurs / jour', $moy_visit_jour );
            $card( 'Moy. messages / jour', $moy_msg_jour );
            $card( 'Moy. messages / conversation', $moy_msg_conv );
            $card( 'Temps de reponse moyen', $tpsmoy . ' ms' );
            $card( 'Dans le perimetre', $total ? round( 100 * $enscope / $total ) . ' %' : '—', $hors . ' hors-sujet' );
            $card( 'Erreurs', number_format_i18n( $errs ) );
            ?>
        </div>

        <h2>Activite par jour</h2>
        <div style="display:flex;align-items:flex-end;gap:3px;height:160px;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px;overflow-x:auto;">
            <?php foreach ( $daily as $d ) :
                $h = $max_day ? max( 3, round( 130 * $d['n'] / $max_day ) ) : 3; ?>
                <div title="<?php echo esc_attr( $d['d'] . ' : ' . $d['n'] . ' messages, ' . $d['v'] . ' visiteurs' ); ?>"
                     style="width:14px;height:<?php echo (int) $h; ?>px;background:linear-gradient(180deg,#1D54A0,#E91E63);border-radius:3px 3px 0 0;"></div>
            <?php endforeach; ?>
        </div>

        <div style="display:flex;gap:24px;flex-wrap:wrap;margin-top:24px;">
            <div style="flex:1;min-width:320px;">
                <h2>Questions les plus frequentes</h2>
                <table class="widefat striped"><thead><tr><th>Question</th><th style="width:60px;">Nb</th></tr></thead><tbody>
                <?php if ( $top ) : foreach ( $top as $t ) : ?>
                    <tr><td><?php echo esc_html( mb_substr( $t['q'], 0, 120 ) ); ?></td><td><?php echo (int) $t['n']; ?></td></tr>
                <?php endforeach; else : ?><tr><td colspan="2">—</td></tr><?php endif; ?>
                </tbody></table>
            </div>
            <div style="flex:1;min-width:280px;">
                <h2>Outils utilises</h2>
                <table class="widefat striped"><thead><tr><th>Outil</th><th style="width:60px;">Nb</th></tr></thead><tbody>
                <?php if ( $tool_count ) : foreach ( $tool_count as $name => $n ) : ?>
                    <tr><td><?php echo esc_html( $name ); ?></td><td><?php echo (int) $n; ?></td></tr>
                <?php endforeach; else : ?><tr><td colspan="2">Aucun outil appele sur la periode.</td></tr><?php endif; ?>
                </tbody></table>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php
}
