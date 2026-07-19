<?php
/**
 * Journal d'activité Fast Food PAR PERSONNE — « qui travaille ».
 *
 * Chaque action interactive d'un utilisateur sur le menu est enregistrée avec
 * QUI, QUOI, quelle agence, quel repas, quand. On ne journalise qu'aux points
 * INTERACTIFS (save planning, save prix/promo) : les imports et synchros en
 * masse passent par d'autres endpoints et n'y touchent pas — le journal reste
 * donc le reflet du travail réel d'une personne, pas d'un traitement par lot.
 *
 * L'écran répond à la question de fond en trois mesures, pas une seule :
 *   - dernière action (la personne est-elle encore active ?) ;
 *   - volume d'actions sur la période (prolifique ou fantôme ?) ;
 *   - jours actifs distincts (régulier, ou un seul gros sursaut ?).
 *
 * @package SL_FastFood
 */

defined( 'ABSPATH' ) || exit;

function sl_ff_act_table() {
    global $wpdb;
    return $wpdb->prefix . 'sl_ff_activity';
}

add_action( 'admin_init', 'sl_ff_act_install' );
function sl_ff_act_install() {
    if ( get_option( 'sl_ff_act_db_ver' ) === '1' ) {
        return;
    }
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( 'CREATE TABLE ' . sl_ff_act_table() . " (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        action VARCHAR(16) NOT NULL,
        agence VARCHAR(64) NOT NULL DEFAULT '',
        post_id BIGINT(20) UNSIGNED NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        KEY user_date (user_id, created_at),
        KEY created (created_at)
    ) {$wpdb->get_charset_collate()};" );
    update_option( 'sl_ff_act_db_ver', '1' );
    if ( ! get_option( 'sl_ff_act_started' ) ) {
        update_option( 'sl_ff_act_started', current_time( 'mysql' ) );
    }
}

/** Enregistre une action de l'utilisateur courant. */
function sl_ff_activity_log( $action, $agence = '', $post_id = 0 ) {
    $uid = get_current_user_id();
    if ( ! $uid ) {
        return; // action systeme / cron : pas une personne
    }
    global $wpdb;
    $wpdb->insert( sl_ff_act_table(), [
        'user_id'    => $uid,
        'action'     => substr( (string) $action, 0, 16 ),
        'agence'     => substr( sanitize_title( (string) $agence ), 0, 64 ),
        'post_id'    => $post_id ? (int) $post_id : null,
        'created_at' => current_time( 'mysql' ),
    ] );
}

/**
 * Depuis le save planning : derive le type d'action a partir de l'avant/apres
 * des jours pour l'agence, et journalise. Appele par le handler AJAX.
 */
function sl_ff_activity_from_planning( $post_id, $agence, array $avant, array $apres ) {
    $av = ! empty( $avant );
    $ap = ! empty( $apres );
    if ( ! $av && $ap ) {
        $action = 'activation';
    } elseif ( $av && ! $ap ) {
        $action = 'desactivation';
    } elseif ( $av && $ap && $avant !== $apres ) {
        $action = 'planning';
    } else {
        return; // aucun changement reel (re-sauvegarde a l'identique)
    }
    sl_ff_activity_log( $action, $agence, $post_id );
}

/** Libelle lisible d'un type d'action. */
function sl_ff_act_label( $action ) {
    $map = [
        'activation'    => 'Activation',
        'desactivation' => 'Désactivation',
        'planning'      => 'Planning',
        'prix'          => 'Prix',
        'promo'         => 'Promo',
    ];
    return $map[ $action ] ?? ucfirst( $action );
}

add_action( 'admin_menu', 'sl_ff_act_menu', 1001 ); // apres le parent (999) et la supervision (1000)
function sl_ff_act_menu() {
    add_submenu_page(
        'sl-fastfood',
        'Activité des équipes',
        '👷 Activité équipes',
        'edit_others_posts',
        'sl-ff-activite',
        'sl_ff_act_render_page'
    );
}

function sl_ff_act_render_page() {
    if ( ! current_user_can( 'edit_others_posts' ) && ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Accès refusé.' );
    }
    global $wpdb;
    $t     = sl_ff_act_table();
    $days  = isset( $_GET['periode'] ) ? max( 1, min( 365, (int) $_GET['periode'] ) ) : 30;
    $now   = current_time( 'timestamp' );
    $since = date( 'Y-m-d H:i:s', $now - $days * DAY_IN_SECONDS );

    // Agregat par utilisateur : volume, jours actifs, derniere action, repartition.
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT user_id,
                COUNT(*)                         AS total,
                COUNT(DISTINCT DATE(created_at))  AS jours_actifs,
                MAX(created_at)                   AS derniere,
                SUM(action='activation')          AS n_activ,
                SUM(action='desactivation')       AS n_desact,
                SUM(action='planning')            AS n_plan,
                SUM(action='prix')                AS n_prix,
                SUM(action='promo')               AS n_promo
           FROM {$t}
          WHERE created_at >= %s
          GROUP BY user_id
          ORDER BY total DESC", $since
    ) );

    // Créations de repas par auteur (déduites de post_author, rétroactif).
    $crea = $wpdb->get_results( $wpdb->prepare(
        "SELECT post_author uid, COUNT(*) n
           FROM {$wpdb->posts}
          WHERE post_type = 'sl_repas' AND post_status = 'publish' AND post_date >= %s
          GROUP BY post_author", $since
    ), OBJECT_K );

    // Responsables Fast Food connus, meme SANS activite (les fantomes = la reponse).
    $resp = get_users( [ 'role__in' => [ 'sl_responsable_fastfood' ], 'fields' => 'all', 'number' => 200 ] );
    $vus  = wp_list_pluck( $rows, 'user_id' );

    $started = get_option( 'sl_ff_act_started' );
    $max_total = 1;
    foreach ( $rows as $r ) { $max_total = max( $max_total, (int) $r->total ); }
    ?>
    <div class="wrap slffact">
        <h1>👷 Activité des équipes — Fast Food</h1>

        <form method="get" style="margin:12px 0;">
            <input type="hidden" name="page" value="sl-ff-activite">
            <label>Période :
                <select name="periode" onchange="this.form.submit()">
                    <?php foreach ( [ 7 => '7 jours', 30 => '30 jours', 90 => '90 jours' ] as $d => $l ) : ?>
                        <option value="<?php echo $d; ?>" <?php selected( $days, $d ); ?>><?php echo $l; ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </form>

        <style>
        .slffact table.widefat{max-width:1080px;}
        .slffact .bar{background:#1d54a022;border-radius:4px;height:13px;position:relative;min-width:2px;}
        .slffact .bar i{position:absolute;inset:0;background:#1d54a0;border-radius:4px;opacity:.85;}
        .slffact .muted{color:#8c8f94;}
        .slffact .types{font-size:11.5px;color:#555;}
        .slffact .none{color:#b32d2e;font-weight:600;}
        </style>

        <p class="muted" style="max-width:900px;">
            « Actions » = interventions réelles sur le menu (activation, désactivation, changement de jours, prix, promo).
            Les imports et synchros en masse <strong>ne sont pas comptés</strong> : ce tableau reflète le travail d'une personne, pas un traitement par lot.
            « Jours actifs » distingue le travail régulier d'un unique gros sursaut. Suivi démarré le
            <?php echo $started ? esc_html( mysql2date( 'd/m/Y', $started ) ) : '—'; ?>.
        </p>

        <h2>Qui intervient sur le menu ?</h2>
        <table class="widefat striped">
            <thead><tr>
                <th>Personne</th><th>Agence</th><th>Actions (période)</th><th style="width:130px;"></th>
                <th>Jours actifs</th><th>Détail</th><th>Repas créés</th><th>Dernière action</th>
            </tr></thead>
            <tbody>
            <?php if ( empty( $rows ) ) : ?>
                <tr><td colspan="8"><em>Aucune action enregistrée sur la période.</em></td></tr>
            <?php endif; ?>
            <?php foreach ( $rows as $r ) :
                $u   = get_userdata( $r->user_id );
                $ag  = $u ? sanitize_title( (string) get_user_meta( $r->user_id, '_sl_agence_ff', true ) ) : '';
                $agn = $ag ? ( function_exists( 'sl_ff_sup_agence_nom' ) ? sl_ff_sup_agence_nom( $ag ) : $ag ) : '—';
                $pct = max( 2, round( 100 * (int) $r->total / $max_total ) );
                $jd  = $r->derniere ? (int) floor( ( $now - strtotime( $r->derniere ) ) / DAY_IN_SECONDS ) : null;
                $types = [];
                foreach ( [ 'n_activ' => 'act', 'n_desact' => 'désact', 'n_plan' => 'planning', 'n_prix' => 'prix', 'n_promo' => 'promo' ] as $k => $lbl ) {
                    if ( (int) $r->$k > 0 ) { $types[] = (int) $r->$k . ' ' . $lbl; }
                }
                $nc = isset( $crea[ $r->user_id ] ) ? (int) $crea[ $r->user_id ]->n : 0;
            ?>
                <tr>
                    <td><strong><?php echo esc_html( $u ? $u->display_name : ( 'Compte #' . $r->user_id ) ); ?></strong></td>
                    <td><?php echo esc_html( $agn ); ?></td>
                    <td><strong><?php echo (int) $r->total; ?></strong></td>
                    <td><div class="bar"><i style="width:<?php echo $pct; ?>%"></i></div></td>
                    <td><?php echo (int) $r->jours_actifs; ?> <span class="muted" style="font-size:11px;">/ <?php echo (int) $days; ?></span></td>
                    <td class="types"><?php echo esc_html( implode( ' · ', $types ) ); ?></td>
                    <td><?php echo $nc ?: '<span class="muted">0</span>'; ?></td>
                    <td><?php echo null === $jd ? '—' : ( 0 === $jd ? 'aujourd\'hui' : 'il y a ' . $jd . ' j' ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php
        // Responsables Fast Food SANS aucune action sur la periode = a interpeller.
        $fantomes = [];
        foreach ( $resp as $u ) {
            if ( ! in_array( $u->ID, $vus, true ) ) {
                $fantomes[] = $u;
            }
        }
        if ( $fantomes ) : ?>
            <div style="background:#fdf0f5;border-left:4px solid #e91e63;padding:2px 14px;margin:16px 0;max-width:1010px;">
                <h3 style="margin:10px 0 4px;">🚨 Responsables Fast Food sans aucune action sur la période</h3>
                <ul style="margin:4px 0 12px 18px;list-style:disc;">
                    <?php foreach ( $fantomes as $u ) :
                        $ag  = sanitize_title( (string) get_user_meta( $u->ID, '_sl_agence_ff', true ) );
                        $agn = $ag ? ( function_exists( 'sl_ff_sup_agence_nom' ) ? sl_ff_sup_agence_nom( $ag ) : $ag ) : 'aucune agence';
                    ?>
                        <li><strong><?php echo esc_html( $u->display_name ); ?></strong> — <?php echo esc_html( $agn ); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <p class="muted" style="margin-top:18px;font-size:12px;max-width:900px;">
            À lire avec les résultats de vente (écran 📈 Supervision et le tableau de bord des ventes) :
            l'activité est un effort, pas une preuve de performance. Beaucoup de changements sur un menu qui ne se vend pas
            valent moins qu'un menu stable qui tourne bien.
        </p>
    </div>
    <?php
}
