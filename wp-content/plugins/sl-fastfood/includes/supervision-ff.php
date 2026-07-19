<?php
/**
 * Supervision du module Fast Food (admins + editeurs).
 *
 * Question : quelles agences utilisent reellement le menu du jour, lesquelles
 * l'ont laisse tomber ? Le pendant du monitoring des Bons Plans, applique au
 * modele Fast Food (1 repas = N agences, disponibilite par jour et par agence
 * via _sl_ff_jours_by_agence).
 *
 * « Utilise » = a des repas planifies (au moins un jour coche) pour l'agence.
 * « Derniere desactivation » = derniere fois qu'un repas a cesse d'etre propose
 * dans l'agence — donnee CREEE par sl_ff_log_deactivation (aucune source native).
 *
 * @package SL_FastFood
 */

defined( 'ABSPATH' ) || exit;

/** Journal des desactivations : option slff_deact_log = [ slug => [ts, count, dernier_pid] ]. */
function sl_ff_log_deactivation( $agence, $post_id ) {
    $agence = sanitize_title( $agence );
    if ( $agence === '' ) {
        return;
    }
    $log = get_option( 'slff_deact_log', [] );
    if ( ! is_array( $log ) ) {
        $log = [];
    }
    $prec = isset( $log[ $agence ] ) ? $log[ $agence ] : [ 'count' => 0 ];
    $log[ $agence ] = [
        'ts'          => time(),
        'count'       => (int) ( $prec['count'] ?? 0 ) + 1,
        'dernier_pid' => (int) $post_id,
        'dernier_nom' => get_the_title( $post_id ),
    ];
    update_option( 'slff_deact_log', $log, false );
}

/**
 * Agrege l'etat du Fast Food par agence, en une passe sur les repas publies.
 * ~275 repas aujourd'hui, caches termes/metas amorces.
 */
function sl_ff_supervision_collect( $days ) {
    $now      = current_time( 'timestamp' );
    $since_ts = $now - $days * DAY_IN_SECONDS;

    $q = new WP_Query( [
        'post_type'              => 'sl_repas',
        'post_status'            => 'publish',
        'posts_per_page'         => -1,
        'no_found_rows'          => true,
        'update_post_term_cache' => true,
        'update_post_meta_cache' => true,
    ] );

    $vide = [
        'total'        => 0,   // repas rattaches a l'agence
        'proposes'     => 0,   // repas avec au moins un jour coche (reellement au menu)
        'avec_prix'    => 0,   // repas proposes ayant un prix pour l'agence
        'en_promo'     => 0,   // repas proposes en promo pour l'agence
        'ajouts'       => 0,   // repas crees sur la periode et rattaches a l'agence
        'derniere_pub' => 0,   // creation la plus recente d'un repas de l'agence
    ];
    $agences = [];

    foreach ( $q->posts as $p ) {
        $slugs = array_values( array_filter( (array) get_post_meta( $p->ID, '_sl_ff_agence' ) ) );
        $slugs = array_unique( array_map( 'sanitize_title', $slugs ) );
        $created = strtotime( $p->post_date );

        foreach ( $slugs as $slug ) {
            if ( ! isset( $agences[ $slug ] ) ) {
                $agences[ $slug ] = $vide;
            }
            $agences[ $slug ]['total']++;

            if ( $created > $agences[ $slug ]['derniere_pub'] ) {
                $agences[ $slug ]['derniere_pub'] = $created;
            }
            if ( $created >= $since_ts ) {
                $agences[ $slug ]['ajouts']++;
            }

            $jours = function_exists( 'sl_ff_get_agence_jours' ) ? sl_ff_get_agence_jours( $p->ID, $slug ) : [];
            if ( ! empty( $jours ) ) {
                $agences[ $slug ]['proposes']++;

                if ( function_exists( 'sl_ff_get_agence_prix' ) ) {
                    $info = sl_ff_get_agence_prix( $p->ID, $slug );
                    $prix = is_array( $info ) ? ( $info['prix'] ?? 0 ) : 0;
                    if ( (float) $prix > 0 ) {
                        $agences[ $slug ]['avec_prix']++;
                    }
                }
                if ( function_exists( 'sl_ff_get_promo_info' ) ) {
                    $promo = sl_ff_get_promo_info( $p->ID, $slug );
                    if ( ! empty( $promo['est_promo'] ) ) {
                        $agences[ $slug ]['en_promo']++;
                    }
                }
            }
        }
    }

    // Toutes les agences de la taxo, meme a zero repas (celles qui n'utilisent
    // pas du tout le Fast Food = la reponse cherchee).
    $tous = get_terms( [ 'taxonomy' => 'sl_agence_promo', 'hide_empty' => false ] );
    if ( ! is_wp_error( $tous ) ) {
        foreach ( $tous as $t ) {
            if ( ! isset( $agences[ $t->slug ] ) ) {
                $agences[ $t->slug ] = $vide;
            }
        }
    }

    uasort( $agences, function ( $a, $b ) { return $b['proposes'] <=> $a['proposes']; } );
    return $agences;
}

function sl_ff_sup_agence_nom( $slug ) {
    $t = get_term_by( 'slug', $slug, 'sl_agence_promo' );
    return $t ? $t->name : $slug;
}

/** Responsables Fast Food par slug d'agence (meta _sl_agence_ff = slug). */
function sl_ff_sup_responsables() {
    $users = get_users( [
        'role__in' => [ 'sl_responsable_fastfood' ],
        'fields'   => 'all',
        'number'   => 200,
    ] );
    $map = [];
    foreach ( $users as $u ) {
        $slug = sanitize_title( (string) get_user_meta( $u->ID, '_sl_agence_ff', true ) );
        if ( $slug !== '' ) {
            $map[ $slug ][] = $u->display_name;
        }
    }
    return $map;
}

// Priorite 1000 : le menu parent « Fast Food » est construit a 999
// (sl_ff_build_menu). S'accrocher avant -> le parent n'existe pas encore et le
// sous-menu se perd (meme lecon que l'ecran Statistiques de sl-collect).
add_action( 'admin_menu', 'sl_ff_sup_menu', 1000 );
function sl_ff_sup_menu() {
    // Le menu Fast Food (page sl-fastfood) existe pour admins + editeurs.
    add_submenu_page(
        'sl-fastfood',
        'Supervision Fast Food',
        '📈 Supervision',
        'edit_others_posts',
        'sl-ff-supervision',
        'sl_ff_sup_render_page'
    );
}

function sl_ff_sup_render_page() {
    if ( ! current_user_can( 'edit_others_posts' ) && ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Accès refusé.' );
    }
    $days = isset( $_GET['periode'] ) ? max( 1, min( 365, (int) $_GET['periode'] ) ) : 30;
    $now  = current_time( 'timestamp' );

    $agences = sl_ff_supervision_collect( $days );
    $resp    = sl_ff_sup_responsables();
    $deact   = get_option( 'slff_deact_log', [] );
    $deact   = is_array( $deact ) ? $deact : [];

    $utilisent = 0;
    $inactives = [];
    $max_prop  = 0;
    foreach ( $agences as $slug => $a ) {
        if ( $a['proposes'] > 0 ) {
            $utilisent++;
        } else {
            $inactives[] = $slug;
        }
        $max_prop = max( $max_prop, $a['proposes'] );
    }
    ?>
    <div class="wrap slffsup">
        <h1>📈 Supervision Fast Food — usage du menu du jour</h1>

        <div style="display:flex;gap:16px;align-items:flex-end;flex-wrap:wrap;margin:12px 0;">
            <form method="get">
                <input type="hidden" name="page" value="sl-ff-supervision">
                <label>Période :
                    <select name="periode" onchange="this.form.submit()">
                        <?php foreach ( [ 7 => '7 jours', 30 => '30 jours', 90 => '90 jours' ] as $d => $l ) : ?>
                            <option value="<?php echo $d; ?>" <?php selected( $days, $d ); ?>><?php echo $l; ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </form>
            <a class="button button-primary"
               href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=slffrp_rapport&periode=' . $days ), 'slffrp_rapport' ) ); ?>"
               target="_blank" rel="noopener">🖨️ Imprimer le rapport (PDF)</a>
        </div>

        <style>
        .slffsup .cards{display:flex;flex-wrap:wrap;gap:12px;margin:14px 0 20px;}
        .slffsup .card{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:13px 18px;min-width:150px;}
        .slffsup .card b{display:block;font-size:22px;margin-top:3px;color:#1d54a0;}
        .slffsup .card.warn b{color:#b32d2e;}
        .slffsup .card small{color:#646970;}
        .slffsup table.widefat{max-width:1040px;}
        .slffsup .bar{background:#e91e6322;border-radius:4px;height:13px;position:relative;min-width:2px;}
        .slffsup .bar i{position:absolute;inset:0;background:#e91e63;border-radius:4px;opacity:.85;}
        .slffsup .resp{font-size:11.5px;color:#646970;display:block;margin-top:2px;}
        .slffsup .muted{color:#8c8f94;}
        .slffsup .alerte{background:#fdf0f5;border-left:4px solid #e91e63;padding:2px 14px;margin:14px 0;max-width:1010px;}
        </style>

        <div class="cards">
            <div class="card"><small>Agences utilisant le Fast Food</small><b><?php echo (int) $utilisent; ?> / <?php echo count( $agences ); ?></b></div>
            <div class="card <?php echo $inactives ? 'warn' : ''; ?>"><small>Agences sans menu actif</small><b><?php echo count( $inactives ); ?></b></div>
            <div class="card"><small>Repas au menu (toutes agences)</small><b><?php
                $tot_prop = 0; foreach ( $agences as $a ) { $tot_prop += $a['proposes']; } echo (int) $tot_prop; ?></b></div>
        </div>

        <h2>Usage par agence</h2>
        <p class="muted" style="margin-top:-6px;">
            « Au menu » = repas avec au moins un jour coché pour l'agence (réellement proposés).
            « Dernière désactivation » = dernière fois qu'un repas a été retiré du menu de l'agence (suivi depuis aujourd'hui).
        </p>
        <table class="widefat striped">
            <thead><tr>
                <th>Agence</th><th>Statut</th><th>Repas au menu</th><th style="width:130px;"></th>
                <th>Avec prix</th><th>En promo</th><th>Ajouts (période)</th>
                <th>Dernière activité</th><th>Dernière désactivation</th>
            </tr></thead>
            <tbody>
            <?php foreach ( $agences as $slug => $a ) :
                $jours_depuis = $a['derniere_pub'] ? (int) floor( ( $now - $a['derniere_pub'] ) / DAY_IN_SECONDS ) : null;
                if ( 0 === $a['proposes'] ) {
                    $statut = '<span style="color:#b32d2e;font-weight:700;">🔴 aucun menu</span>';
                } elseif ( null !== $jours_depuis && $jours_depuis <= 14 ) {
                    $statut = '<span style="color:#1e7b34;font-weight:600;">🟢 actif</span>';
                } else {
                    $statut = '<span style="color:#996800;font-weight:600;">🟠 à vérifier</span>';
                }
                $pct       = $max_prop > 0 ? max( 2, round( 100 * $a['proposes'] / $max_prop ) ) : 0;
                $noms_resp = isset( $resp[ $slug ] ) ? implode( ', ', $resp[ $slug ] ) : '';
                $dl        = isset( $deact[ $slug ] ) ? $deact[ $slug ] : null;
            ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html( sl_ff_sup_agence_nom( $slug ) ); ?></strong>
                        <span class="resp"><?php echo $noms_resp
                            ? '👤 ' . esc_html( $noms_resp )
                            : '<span style="color:#b32d2e;">aucun responsable Fast Food</span>'; ?></span>
                    </td>
                    <td><?php echo $statut; // phpcs:ignore ?></td>
                    <td><strong><?php echo (int) $a['proposes']; ?></strong> <span class="muted" style="font-size:11px;">/ <?php echo (int) $a['total']; ?></span></td>
                    <td><div class="bar"><i style="width:<?php echo $a['proposes'] ? $pct : 0; ?>%"></i></div></td>
                    <td><?php echo (int) $a['avec_prix']; ?></td>
                    <td><?php echo $a['en_promo'] ? '<span style="color:#1e7b34;">' . (int) $a['en_promo'] . '</span>' : '0'; ?></td>
                    <td><?php echo (int) $a['ajouts']; ?></td>
                    <td><?php echo null === $jours_depuis ? '—' : ( 0 === $jours_depuis ? 'aujourd\'hui' : 'il y a ' . $jours_depuis . ' j' ); ?></td>
                    <td><?php
                        if ( $dl && ! empty( $dl['ts'] ) ) {
                            $jd = (int) floor( ( $now - $dl['ts'] ) / DAY_IN_SECONDS );
                            echo esc_html( 0 === $jd ? 'aujourd\'hui' : 'il y a ' . $jd . ' j' );
                            echo ' <span class="muted" style="font-size:11px;">(' . (int) ( $dl['count'] ?? 0 ) . ')</span>';
                        } else {
                            echo '<span class="muted">—</span>';
                        }
                    ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ( $inactives ) : ?>
            <div class="alerte">
                <h3 style="margin:10px 0 4px;">🚨 Agences sans menu du jour actif</h3>
                <p style="margin:4px 0 10px;">
                    <strong><?php echo count( $inactives ); ?> agence(s)</strong> ne proposent aucun repas :
                    <?php echo esc_html( implode( ', ', array_map( 'sl_ff_sup_agence_nom', $inactives ) ) ); ?>.
                    Vérifiez la colonne « responsable » : sans personne de rattaché, c'est un problème d'organisation, pas d'usage.
                </p>
            </div>
        <?php endif; ?>

        <p class="muted" style="margin-top:20px;font-size:12px;max-width:900px;">
            Le suivi des désactivations démarre à la date d'installation de cette page : les retraits de repas
            antérieurs ne sont pas connus (aucune donnée native ne les enregistrait). Le compteur entre parenthèses
            indique le nombre total de désactivations observées depuis.
        </p>
    </div>
    <?php
}
