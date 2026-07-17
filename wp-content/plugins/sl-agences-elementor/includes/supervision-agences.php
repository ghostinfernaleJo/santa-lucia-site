<?php
/**
 * Supervision des agences (admins + editeurs).
 *
 * Question a laquelle repond cet ecran : « qui travaille reellement pour son
 * agence ? ». Toutes les donnees existaient deja — auteur, date de creation,
 * agence, stock de chaque bon plan — personne ne les croisait.
 *
 * Honnetete de la mesure, affichee sur l'ecran :
 *  - l'activite mesuree = fiches creees (post_author) et fraicheur des offres ;
 *  - un import en masse attribue toutes les fiches a celui qui l'a lance ;
 *  - le reapprovisionnement de stock via la colonne AJAX ne bump pas
 *    post_modified — le travail « entretien » est donc sous-estime ;
 *  - ce que fait un responsable HORS ligne (comptoir, appels) n'est pas ici.
 *
 * @package SL_Agences
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'slsv_add_menu', 50 );
function slsv_add_menu() {
    add_submenu_page(
        'edit.php?post_type=sl_bon_plan',
        'Supervision des agences',
        '📈 Supervision',
        'edit_others_posts', // admins + editeurs ; pas les responsables (ils sont l'objet de l'ecran)
        'sl-bp-supervision',
        'slsv_render_page'
    );
}

/** Responsables rattaches, groupes par slug d'agence (via sl_agence_assignee = NOM). */
function slsv_responsables_par_agence() {
    $users = get_users( [
        'role__in'   => [ 'sl_gestionnaire_bons_plans', 'sl_responsable_agence' ],
        'fields'     => 'all',
        'number'     => 200,
    ] );
    $map = [];
    foreach ( $users as $u ) {
        $nom_agence = (string) get_user_meta( $u->ID, 'sl_agence_assignee', true );
        if ( $nom_agence === '' ) {
            $map['(sans agence)'][] = $u->display_name;
            continue;
        }
        $t = get_term_by( 'name', $nom_agence, 'sl_agence_promo' );
        if ( ! $t ) {
            $t = get_term_by( 'slug', sanitize_title( $nom_agence ), 'sl_agence_promo' );
        }
        $slug = $t ? $t->slug : sanitize_title( $nom_agence );
        $map[ $slug ][] = $u->display_name;
    }
    return $map;
}

/**
 * Une passe sur tous les bons plans publies : agregats par agence, par auteur
 * et par jour. ~300 posts aujourd'hui — WP_Query avec caches de termes et de
 * metas amorces, aucune requete par ligne ensuite.
 */
function slsv_collect( $days ) {
    $now      = current_time( 'timestamp' );
    $since_ts = $now - $days * DAY_IN_SECONDS;
    $today    = date( 'Y-m-d', $now );

    $q = new WP_Query( [
        'post_type'              => 'sl_bon_plan',
        'post_status'            => 'publish',
        'posts_per_page'         => -1,
        'no_found_rows'          => true,
        'update_post_term_cache' => true,
        'update_post_meta_cache' => true,
    ] );

    $vide = [
        'crees'        => 0,   // fiches creees sur la periode
        'derniere_pub' => 0,   // timestamp de la publication la plus recente (toutes periodes)
        'actives'      => 0,   // non expirees
        'stock_gere'   => 0,   // actives avec limite de stock cochee
        'expirees'     => 0,   // publiees mais date_fin passee (fiches mortes a nettoyer)
        'epuisees'     => 0,   // stock 0
    ];
    $agences = [];
    $auteurs = [];
    $par_jour = [];

    foreach ( $q->posts as $p ) {
        $terms = get_the_terms( $p->ID, 'sl_agence_promo' );
        $slug  = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->slug : '(sans agence)';
        if ( ! isset( $agences[ $slug ] ) ) {
            $agences[ $slug ] = $vide;
        }

        $created = strtotime( $p->post_date );
        if ( $created > $agences[ $slug ]['derniere_pub'] ) {
            $agences[ $slug ]['derniere_pub'] = $created;
        }

        if ( $created >= $since_ts ) {
            $agences[ $slug ]['crees']++;

            $aid = (int) $p->post_author;
            if ( ! isset( $auteurs[ $aid ] ) ) {
                $auteurs[ $aid ] = [ 'crees' => 0, 'agences' => [] ];
            }
            $auteurs[ $aid ]['crees']++;
            $auteurs[ $aid ]['agences'][ $slug ] = true;

            $jour = date( 'Y-m-d', $created );
            $par_jour[ $jour ] = ( $par_jour[ $jour ] ?? 0 ) + 1;
        }

        $fin = (string) get_post_meta( $p->ID, '_sl_bp_date_fin', true );
        if ( $fin !== '' && $fin < $today ) {
            $agences[ $slug ]['expirees']++;
            continue;
        }
        $agences[ $slug ]['actives']++;

        $on  = get_post_meta( $p->ID, '_sl_bp_stock_actif', true ) === '1';
        $qte = get_post_meta( $p->ID, '_sl_bp_stock_qty', true );
        if ( $on ) {
            $agences[ $slug ]['stock_gere']++;
            if ( $qte !== '' && (int) $qte <= 0 ) {
                $agences[ $slug ]['epuisees']++;
            }
        }
    }

    // Toutes les agences de la taxo, y compris celles a ZERO fiche : les
    // absentes du classement sont precisement celles qu'on cherche.
    $tous = get_terms( [ 'taxonomy' => 'sl_agence_promo', 'hide_empty' => false ] );
    if ( ! is_wp_error( $tous ) ) {
        foreach ( $tous as $t ) {
            if ( ! isset( $agences[ $t->slug ] ) ) {
                $agences[ $t->slug ] = $vide;
            }
        }
    }

    uasort( $agences, function ( $a, $b ) { return $b['crees'] <=> $a['crees']; } );
    uasort( $auteurs, function ( $a, $b ) { return $b['crees'] <=> $a['crees']; } );
    ksort( $par_jour );

    return [ 'agences' => $agences, 'auteurs' => $auteurs, 'par_jour' => $par_jour ];
}

function slsv_agence_nom( $slug ) {
    $t = get_term_by( 'slug', $slug, 'sl_agence_promo' );
    return $t ? $t->name : $slug;
}

function slsv_render_page() {
    if ( ! current_user_can( 'edit_others_posts' ) && ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Accès refusé.' );
    }

    $days = isset( $_GET['periode'] ) ? max( 1, min( 365, (int) $_GET['periode'] ) ) : 30;
    $now  = current_time( 'timestamp' );

    $data = slsv_collect( $days );
    $resp = slsv_responsables_par_agence();

    // KPIs
    $tot_crees = 0;
    $actives_n = 0;
    $muettes   = [];
    foreach ( $data['agences'] as $slug => $a ) {
        $tot_crees += $a['crees'];
        if ( $a['crees'] > 0 ) {
            $actives_n++;
        } else {
            $muettes[] = $slug;
        }
    }
    $moyenne = $days > 0 ? round( $tot_crees / $days, 1 ) : 0;
    $max_crees = 0;
    foreach ( $data['agences'] as $a ) { $max_crees = max( $max_crees, $a['crees'] ); }

    // Graphique : les N derniers jours (21 max pour rester lisible)
    $chart_days = min( $days, 21 );
    $chart = [];
    $chart_max = 1;
    for ( $i = $chart_days - 1; $i >= 0; $i-- ) {
        $d = date( 'Y-m-d', $now - $i * DAY_IN_SECONDS );
        $chart[ $d ] = (int) ( $data['par_jour'][ $d ] ?? 0 );
        $chart_max = max( $chart_max, $chart[ $d ] );
    }
    ?>
    <div class="wrap slsv">
        <h1>📈 Supervision des agences — Bons Plans</h1>

        <div style="display:flex;gap:16px;align-items:flex-end;flex-wrap:wrap;margin:12px 0;">
            <form method="get">
                <input type="hidden" name="post_type" value="sl_bon_plan">
                <input type="hidden" name="page" value="sl-bp-supervision">
                <label>Période :
                    <select name="periode" onchange="this.form.submit()">
                        <?php foreach ( [ 7 => '7 jours', 30 => '30 jours', 90 => '90 jours' ] as $d => $l ) : ?>
                            <option value="<?php echo $d; ?>" <?php selected( $days, $d ); ?>><?php echo $l; ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </form>
            <a class="button button-primary"
               href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=slrp_rapport&periode=' . $days ), 'slrp_rapport' ) ); ?>"
               target="_blank" rel="noopener">🖨️ Imprimer le rapport (PDF)</a>
        </div>

        <style>
        .slsv .cards{display:flex;flex-wrap:wrap;gap:12px;margin:14px 0 20px;}
        .slsv .card{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:13px 18px;min-width:150px;}
        .slsv .card b{display:block;font-size:22px;margin-top:3px;color:#1d54a0;}
        .slsv .card.warn b{color:#b32d2e;}
        .slsv .card small{color:#646970;}
        .slsv table.widefat{max-width:1040px;}
        .slsv .bar{background:#e91e6322;border-radius:4px;height:13px;position:relative;min-width:2px;}
        .slsv .bar i{position:absolute;inset:0;background:#e91e63;border-radius:4px;opacity:.85;}
        .slsv .resp{font-size:11.5px;color:#646970;display:block;margin-top:2px;}
        .slsv .chart{display:flex;align-items:flex-end;gap:4px;height:110px;max-width:720px;background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:14px 14px 26px;position:relative;}
        .slsv .chart .col{flex:1;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;height:100%;position:relative;}
        .slsv .chart .col i{display:block;width:100%;max-width:26px;background:#1d54a0;border-radius:3px 3px 0 0;min-height:2px;}
        .slsv .chart .col span{position:absolute;bottom:-20px;font-size:9.5px;color:#8c8f94;white-space:nowrap;}
        .slsv .chart .col b{font-size:10.5px;color:#1d54a0;margin-bottom:2px;}
        .slsv .muted{color:#8c8f94;}
        .slsv .alerte{background:#fdf0f5;border-left:4px solid #e91e63;padding:2px 14px;margin:14px 0;max-width:1010px;}
        </style>

        <div class="cards">
            <div class="card"><small>Bons plans créés (période)</small><b><?php echo (int) $tot_crees; ?></b></div>
            <div class="card"><small>Moyenne par jour</small><b><?php echo esc_html( str_replace( '.', ',', (string) $moyenne ) ); ?></b></div>
            <div class="card"><small>Agences ayant publié</small><b><?php echo (int) $actives_n; ?> / <?php echo count( $data['agences'] ); ?></b></div>
            <div class="card <?php echo $muettes ? 'warn' : ''; ?>"><small>Agences muettes (0 création)</small><b><?php echo count( $muettes ); ?></b></div>
        </div>

        <h2>Publications par jour (<?php echo (int) $chart_days; ?> derniers jours)</h2>
        <div class="chart">
            <?php $j = 0; foreach ( $chart as $d => $n ) : $j++; ?>
                <div class="col" title="<?php echo esc_attr( mysql2date( 'd/m/Y', $d . ' 00:00:00' ) . ' — ' . $n . ' bon(s) plan(s)' ); ?>">
                    <?php if ( $n > 0 ) : ?><b><?php echo $n; ?></b><?php endif; ?>
                    <i style="height:<?php echo max( 2, round( 92 * $n / $chart_max ) ); ?>%;"></i>
                    <?php if ( 1 === $j % max( 1, (int) ceil( $chart_days / 10 ) ) ) : ?>
                        <span><?php echo esc_html( mysql2date( 'd/m', $d . ' 00:00:00' ) ); ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <h2>Classement des agences</h2>
        <table class="widefat striped">
            <thead><tr>
                <th>Agence</th><th>Statut</th><th>Créés (période)</th><th style="width:140px;"></th>
                <th>Dernière publication</th><th>Offres actives</th><th>Stock géré</th>
                <th>Expirées affichées</th><th>Épuisées</th>
            </tr></thead>
            <tbody>
            <?php foreach ( $data['agences'] as $slug => $a ) :
                $jours_depuis = $a['derniere_pub'] ? (int) floor( ( $now - $a['derniere_pub'] ) / DAY_IN_SECONDS ) : null;
                if ( null === $jours_depuis ) {
                    $statut = '<span style="color:#b32d2e;font-weight:700;">🔴 jamais</span>';
                } elseif ( $jours_depuis <= 7 ) {
                    $statut = '<span style="color:#1e7b34;font-weight:600;">🟢 actif</span>';
                } elseif ( $jours_depuis <= 21 ) {
                    $statut = '<span style="color:#996800;font-weight:600;">🟠 ralenti</span>';
                } else {
                    $statut = '<span style="color:#b32d2e;font-weight:700;">🔴 inactif</span>';
                }
                $pct       = $max_crees > 0 ? max( 2, round( 100 * $a['crees'] / $max_crees ) ) : 0;
                $pct_stock = $a['actives'] > 0 ? round( 100 * $a['stock_gere'] / $a['actives'] ) : 0;
                $noms_resp = isset( $resp[ $slug ] ) ? implode( ', ', $resp[ $slug ] ) : '';
            ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html( slsv_agence_nom( $slug ) ); ?></strong>
                        <span class="resp"><?php echo $noms_resp
                            ? '👤 ' . esc_html( $noms_resp )
                            : '<span style="color:#b32d2e;">aucun responsable rattaché</span>'; ?></span>
                    </td>
                    <td><?php echo $statut; // phpcs:ignore ?></td>
                    <td><strong><?php echo (int) $a['crees']; ?></strong></td>
                    <td><div class="bar"><i style="width:<?php echo $a['crees'] ? $pct : 0; ?>%"></i></div></td>
                    <td><?php echo null === $jours_depuis ? '—' : ( 0 === $jours_depuis ? 'aujourd\'hui' : 'il y a ' . $jours_depuis . ' j' ); ?></td>
                    <td><?php echo $a['actives'] ?: '<strong style="color:#b32d2e;">0</strong>'; ?></td>
                    <td><?php echo $a['actives'] ? $pct_stock . ' %' : '—'; ?></td>
                    <td><?php echo $a['expirees'] ? '<span style="color:#996800;">' . (int) $a['expirees'] . '</span>' : '0'; ?></td>
                    <td><?php echo $a['epuisees'] ? '<span style="color:#b32d2e;">' . (int) $a['epuisees'] . '</span>' : '0'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ( $muettes ) : ?>
            <div class="alerte">
                <h3 style="margin:10px 0 4px;">🚨 À regarder de près</h3>
                <p style="margin:4px 0 10px;">
                    <strong><?php echo count( $muettes ); ?> agence(s) sans aucune création sur la période :</strong>
                    <?php echo esc_html( implode( ', ', array_map( 'slsv_agence_nom', $muettes ) ) ); ?>.
                    Avant de conclure, vérifier la colonne « responsable » : une agence sans personne de rattaché
                    n'a personne à qui demander des comptes — c'est un problème d'organisation, pas de motivation.
                </p>
            </div>
        <?php endif; ?>

        <h2>Qui publie ? (période)</h2>
        <?php if ( empty( $data['auteurs'] ) ) : ?>
            <p class="muted"><em>Aucune création sur la période.</em></p>
        <?php else : ?>
        <table class="widefat striped" style="max-width:640px;">
            <thead><tr><th>Compte</th><th>Fiches créées</th><th>Agences touchées</th></tr></thead>
            <tbody>
            <?php foreach ( array_slice( $data['auteurs'], 0, 15, true ) as $aid => $st ) :
                $u = get_userdata( $aid ); ?>
                <tr>
                    <td><strong><?php echo esc_html( $u ? $u->display_name : ( 'Compte #' . $aid ) ); ?></strong></td>
                    <td><?php echo (int) $st['crees']; ?></td>
                    <td><?php echo esc_html( implode( ', ', array_map( 'slsv_agence_nom', array_keys( $st['agences'] ) ) ) ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <p class="muted" style="margin-top:20px;font-size:12px;max-width:900px;">
            Lecture honnête de ces chiffres : l'activité mesurée = fiches créées (auteur de la fiche) et fraîcheur
            des offres. Un import en masse attribue toutes ses fiches à celui qui l'a lancé ; le réapprovisionnement
            du stock via la colonne « Stock en ligne » ne compte pas comme une publication ; et le travail au comptoir
            n'apparaît pas ici — croisez avec l'écran 📊 Statistiques (ventes) avant tout jugement définitif.
        </p>
    </div>
    <?php
}
