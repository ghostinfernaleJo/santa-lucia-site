<?php
/**
 * Statistiques & aide a la decision (admins + editeurs).
 *
 * Deux volets :
 *  1. COLLECTE — ce que personne n'enregistrait : les recherches des visiteurs
 *     (le filtre des bons plans est 100% cote navigateur, aucune requete
 *     serveur) et les ajouts au panier. Balise legere en wp_footer + endpoint
 *     AJAX anonyme. Les donnees s'accumulent a partir du deploiement.
 *  2. TABLEAU DE BORD — ventes par agence, top produits, conversion panier,
 *     recherches sans resultat (demande non satisfaite), sante du catalogue.
 *
 * @package SL_Collect
 */

defined( 'ABSPATH' ) || exit;

/* ============================================================
   1. TABLE DES EVENEMENTS
   ============================================================ */

function slst_table() {
    global $wpdb;
    return $wpdb->prefix . 'sl_stats_events';
}

add_action( 'admin_init', 'slst_install_table' );
function slst_install_table() {
    if ( get_option( 'sl_stats_db_ver' ) === '1' ) {
        return;
    }
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( 'CREATE TABLE ' . slst_table() . " (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        type VARCHAR(12) NOT NULL,
        term VARCHAR(120) NULL,
        product_id BIGINT(20) UNSIGNED NULL,
        agence VARCHAR(64) NOT NULL DEFAULT '',
        qty SMALLINT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        KEY type_date (type, created_at),
        KEY term (term(40))
    ) {$wpdb->get_charset_collate()};" );
    update_option( 'sl_stats_db_ver', '1' );
    // Date de debut de collecte : affichee sur le tableau de bord pour que
    // personne n'interprete « peu de recherches » comme « peu de demande ».
    if ( ! get_option( 'sl_stats_started' ) ) {
        update_option( 'sl_stats_started', current_time( 'mysql' ) );
    }
}

function slst_log( $type, $args = [] ) {
    global $wpdb;
    $wpdb->insert( slst_table(), [
        'type'       => substr( (string) $type, 0, 12 ),
        'term'       => isset( $args['term'] ) ? mb_substr( (string) $args['term'], 0, 120 ) : null,
        'product_id' => isset( $args['product_id'] ) ? (int) $args['product_id'] : null,
        'agence'     => isset( $args['agence'] ) ? substr( (string) $args['agence'], 0, 64 ) : '',
        'qty'        => isset( $args['qty'] ) ? max( 0, (int) $args['qty'] ) : 0,
        'created_at' => current_time( 'mysql' ),
    ] );
}

/* ============================================================
   2. COLLECTE — recherches (balise front) et ajouts panier
   ============================================================ */

/**
 * Balise de suivi des recherches. Ecoute par delegation les champs existants
 * (page /bon-plans/, fiches agence, menu fast-food) : AUCUNE modification des
 * JS versionnes (bons-plans-v3y.js etc.) — donc pas de renommage Varnish.
 */
add_action( 'wp_footer', 'slst_print_beacon', 98 );
function slst_print_beacon() {
    if ( is_admin() ) {
        return;
    }
    $ajax = admin_url( 'admin-ajax.php' );
    ?>
    <script>
    (function(){
        var SEL = '.slbp-search, .slf-bp-search-input, .slf-ff-search-input';
        var timers = {}, lastSent = {};

        document.addEventListener('input', function(e){
            var el = e.target;
            if ( ! el.matches || ! el.matches(SEL) ) return;
            var key = el.className;
            clearTimeout(timers[key]);
            timers[key] = setTimeout(function(){
                var term = (el.value || '').trim().toLowerCase();
                if ( term.length < 2 || term === lastSent[key] ) return;
                lastSent[key] = term;

                // Resultats visibles : approximation honnete (cartes non masquees).
                var results = 0;
                document.querySelectorAll('.slbp-card, .slf-deal-card, .sl-ff-item').forEach(function(c){
                    if ( c.offsetParent !== null ) results++;
                });

                var seg = location.pathname.replace(/^\/+|\/+$/g,'').split('/')[0] || '';
                var body = new FormData();
                body.append('action', 'sl_stats_beacon');
                body.append('term', term.slice(0, 120));
                body.append('ctx', seg.slice(0, 64));
                body.append('results', String(results));
                if ( navigator.sendBeacon ) {
                    navigator.sendBeacon(<?php echo wp_json_encode( $ajax ); ?>, body);
                } else {
                    fetch(<?php echo wp_json_encode( $ajax ); ?>, { method:'POST', body: body, keepalive: true });
                }
            }, 1400);
        }, true);
    })();
    </script>
    <?php
}

/**
 * Endpoint anonyme. PAS de nonce : les pages qui portent la recherche sont
 * servies par Varnish, un nonce embarque serait perime pour la plupart des
 * visiteurs et la collecte serait silencieusement vide. Compense par : action
 * sans aucun privilege, assainissement strict, plafond par IP.
 */
add_action( 'wp_ajax_sl_stats_beacon', 'slst_ajax_beacon' );
add_action( 'wp_ajax_nopriv_sl_stats_beacon', 'slst_ajax_beacon' );
function slst_ajax_beacon() {
    $ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    $key = 'slst_rl_' . md5( $ip );
    $n   = (int) get_transient( $key );
    if ( $n > 40 ) { // 40 recherches/heure/IP : large pour un humain, court pour un robot
        wp_send_json_success(); // repondre pareil : ne pas signaler le plafond
    }
    set_transient( $key, $n + 1, HOUR_IN_SECONDS );

    $term = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';
    $term = mb_strtolower( trim( $term ) );
    if ( mb_strlen( $term ) < 2 || mb_strlen( $term ) > 120 ) {
        wp_send_json_success();
    }
    slst_log( 'search', [
        'term'   => $term,
        'agence' => isset( $_POST['ctx'] ) ? sanitize_title( wp_unslash( $_POST['ctx'] ) ) : '',
        'qty'    => isset( $_POST['results'] ) ? (int) $_POST['results'] : 0,
    ] );
    wp_send_json_success();
}

/** Ajouts au panier : signal d'interet produit (le bouton des cartes passe par WC()->cart->add_to_cart). */
add_action( 'woocommerce_add_to_cart', 'slst_log_cart_add', 10, 6 );
function slst_log_cart_add( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
    $agence = function_exists( 'sl_bp_product_agency' ) ? sl_bp_product_agency( $product_id ) : '';
    slst_log( 'cart', [
        'product_id' => $product_id,
        'agence'     => $agence,
        'qty'        => max( 1, (int) $quantity ),
    ] );
}

/* ============================================================
   3. TABLEAU DE BORD
   ============================================================ */

// Priorité 1000 : le menu parent « Commandes retrait » est enregistré à 999
// (admin-agence.php). S'accrocher AVANT lui (ex. 20) rattache le sous-menu à
// un parent qui n'existe pas encore — l'entrée se perd ou déroute le lien du
// menu parent. Même pattern que Réglages (1000) et WhatsApp (1001).
add_action( 'admin_menu', 'slst_add_menu', 1000 );
function slst_add_menu() {
    add_submenu_page(
        'sl-collect',
        'Statistiques & décisions',
        '📊 Statistiques',
        'edit_others_posts', // admins + editeurs ; les responsables ne voient pas cet ecran
        'sl-stats',
        'slst_render_page'
    );
}

/** Statuts consideres comme « payes » (l'argent est acquis). */
function slst_paid_statuses() {
    return [ 'processing', 'sl-prete', 'completed' ];
}

/**
 * Charge et agrege les commandes retrait de la periode en UNE passe PHP.
 * Volume actuel faible (vente lancee cette semaine) : wc_get_orders + boucle
 * est plus robuste que du SQL brut (HPOS-proof) et largement suffisant.
 * A revoir (requetes agregees) si le site depasse ~2000 commandes/mois.
 */
function slst_collect_data( $days ) {
    $orders = wc_get_orders( [
        'limit'        => 1000,
        'status'       => array_merge( slst_paid_statuses(), [ 'pending', 'cancelled' ] ),
        'date_created' => '>' . ( time() - $days * DAY_IN_SECONDS ),
        'return'       => 'objects',
    ] );

    $par_agence = [];
    $produits   = [];
    $vide       = [ 'payees' => 0, 'ca' => 0.0, 'retirees' => 0, 'annulees' => 0, 'attente' => 0 ];

    foreach ( $orders as $o ) {
        $slug = (string) $o->get_meta( '_sl_collect_agence' );
        if ( $slug === '' ) {
            continue; // commande hors circuit retrait
        }
        if ( ! isset( $par_agence[ $slug ] ) ) {
            $par_agence[ $slug ] = $vide;
        }
        $st = $o->get_status();

        if ( in_array( $st, slst_paid_statuses(), true ) ) {
            $par_agence[ $slug ]['payees']++;
            $par_agence[ $slug ]['ca'] += (float) $o->get_total();
            if ( 'completed' === $st ) {
                $par_agence[ $slug ]['retirees']++;
            }
            foreach ( $o->get_items() as $item ) {
                $pid = $item->get_product_id();
                if ( ! isset( $produits[ $pid ] ) ) {
                    $produits[ $pid ] = [ 'nom' => $item->get_name(), 'qte' => 0, 'ca' => 0.0 ];
                }
                $produits[ $pid ]['qte'] += $item->get_quantity();
                $produits[ $pid ]['ca']  += (float) $item->get_total();
            }
        } elseif ( 'cancelled' === $st ) {
            $par_agence[ $slug ]['annulees']++;
        } elseif ( 'pending' === $st ) {
            $par_agence[ $slug ]['attente']++;
        }
    }

    uasort( $par_agence, function ( $a, $b ) { return $b['ca'] <=> $a['ca']; } );
    uasort( $produits, function ( $a, $b ) { return $b['qte'] <=> $a['qte']; } );

    return [ 'agences' => $par_agence, 'produits' => $produits ];
}

/** Sante du catalogue par agence, en une passe sur les BP publies. */
function slst_catalogue() {
    $ids   = get_posts( [
        'post_type'      => 'sl_bon_plan',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ] );
    $today = current_time( 'Y-m-d' );
    $j7    = date( 'Y-m-d', strtotime( $today . ' +7 days' ) );
    $cat   = [];
    $vide  = [ 'actifs' => 0, 'epuises' => 0, 'expire7' => 0 ];
    $ruptures = [];

    foreach ( $ids as $id ) {
        $terms = get_the_terms( $id, 'sl_agence_promo' );
        $slug  = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->slug : '(sans agence)';
        if ( ! isset( $cat[ $slug ] ) ) {
            $cat[ $slug ] = $vide;
        }
        $fin = (string) get_post_meta( $id, '_sl_bp_date_fin', true );
        if ( $fin !== '' && $fin < $today ) {
            continue; // expiree : pas comptee comme active
        }
        $cat[ $slug ]['actifs']++;
        if ( $fin !== '' && $fin <= $j7 ) {
            $cat[ $slug ]['expire7']++;
        }
        $on  = get_post_meta( $id, '_sl_bp_stock_actif', true ) === '1';
        $qte = get_post_meta( $id, '_sl_bp_stock_qty', true );
        if ( $on && $qte !== '' && (int) $qte <= 0 ) {
            $cat[ $slug ]['epuises']++;
            if ( count( $ruptures ) < 10 ) {
                $ruptures[] = [ 'id' => $id, 'titre' => get_the_title( $id ), 'agence' => $slug ];
            }
        }
    }
    ksort( $cat );
    return [ 'agences' => $cat, 'ruptures' => $ruptures ];
}

function slst_fmt( $n ) {
    return number_format( (float) $n, 0, ',', ' ' );
}

function slst_render_page() {
    if ( ! current_user_can( 'edit_others_posts' ) && ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Accès refusé.' );
    }
    global $wpdb;
    $t     = slst_table();
    $days  = isset( $_GET['periode'] ) ? max( 1, min( 365, (int) $_GET['periode'] ) ) : 30;
    $since = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $days * DAY_IN_SECONDS );

    $data = slst_collect_data( $days );
    $cat  = slst_catalogue();

    // --- Recherches ---
    $top_recherches = $wpdb->get_results( $wpdb->prepare(
        "SELECT term, COUNT(*) n, SUM(qty = 0) sans_resultat
           FROM {$t} WHERE type = 'search' AND created_at >= %s
          GROUP BY term ORDER BY n DESC LIMIT 12", $since
    ) );
    $recherches_vides = $wpdb->get_results( $wpdb->prepare(
        "SELECT term, COUNT(*) n FROM {$t}
          WHERE type = 'search' AND qty = 0 AND created_at >= %s
          GROUP BY term ORDER BY n DESC LIMIT 12", $since
    ) );
    $nb_recherches = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$t} WHERE type = 'search' AND created_at >= %s", $since
    ) );
    $nb_sans_res = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$t} WHERE type = 'search' AND qty = 0 AND created_at >= %s", $since
    ) );

    // --- Ajouts panier + conversion ---
    $top_paniers = $wpdb->get_results( $wpdb->prepare(
        "SELECT product_id, COUNT(*) n FROM {$t}
          WHERE type = 'cart' AND created_at >= %s
          GROUP BY product_id ORDER BY n DESC LIMIT 10", $since
    ) );

    // --- KPIs globaux ---
    $tot = [ 'payees' => 0, 'ca' => 0.0, 'retirees' => 0, 'annulees' => 0, 'attente' => 0 ];
    foreach ( $data['agences'] as $a ) {
        foreach ( $tot as $k => $v ) { $tot[ $k ] += $a[ $k ]; }
    }
    $panier_moyen = $tot['payees'] ? $tot['ca'] / $tot['payees'] : 0;
    $taux_retrait = $tot['payees'] ? round( 100 * $tot['retirees'] / $tot['payees'] ) : 0;
    $ca_max = 0.0;
    foreach ( $data['agences'] as $a ) { $ca_max = max( $ca_max, $a['ca'] ); }

    $depuis = get_option( 'sl_stats_started' );
    ?>
    <div class="wrap slst">
        <h1>📊 Statistiques &amp; décisions — retrait en agence</h1>

        <form method="get" style="margin:12px 0;">
            <input type="hidden" name="page" value="sl-stats">
            <label>Période :
                <select name="periode" onchange="this.form.submit()">
                    <?php foreach ( [ 7 => '7 jours', 30 => '30 jours', 90 => '90 jours' ] as $d => $l ) : ?>
                        <option value="<?php echo $d; ?>" <?php selected( $days, $d ); ?>><?php echo $l; ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </form>

        <style>
        .slst .cards{display:flex;flex-wrap:wrap;gap:12px;margin:14px 0 22px;}
        .slst .card{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:14px 18px;min-width:150px;flex:1;}
        .slst .card b{display:block;font-size:22px;margin-top:4px;color:#1d54a0;}
        .slst .card.warn b{color:#b32d2e;}
        .slst .card small{color:#646970;}
        .slst h2{margin-top:28px;}
        .slst table.widefat{max-width:980px;}
        .slst .bar{background:#e91e6322;border-radius:4px;height:14px;position:relative;min-width:2px;}
        .slst .bar i{position:absolute;inset:0;background:#e91e63;border-radius:4px;opacity:.85;}
        .slst .muted{color:#8c8f94;}
        .slst .cols{display:flex;flex-wrap:wrap;gap:26px;align-items:flex-start;}
        .slst .cols > div{flex:1;min-width:330px;}
        .slst .decision{background:#fdf0f5;border-left:4px solid #e91e63;padding:2px 14px;margin:8px 0;}
        </style>

        <div class="cards">
            <div class="card"><small>Chiffre d'affaires payé</small><b><?php echo slst_fmt( $tot['ca'] ); ?> F</b></div>
            <div class="card"><small>Commandes payées</small><b><?php echo slst_fmt( $tot['payees'] ); ?></b></div>
            <div class="card"><small>Panier moyen</small><b><?php echo slst_fmt( $panier_moyen ); ?> F</b></div>
            <div class="card"><small>Taux de retrait</small><b><?php echo $taux_retrait; ?> %</b></div>
            <div class="card <?php echo $tot['annulees'] ? 'warn' : ''; ?>"><small>Annulées (dont 72 h)</small><b><?php echo slst_fmt( $tot['annulees'] ); ?></b></div>
            <div class="card"><small>En attente (non payées)</small><b><?php echo slst_fmt( $tot['attente'] ); ?></b></div>
            <div class="card <?php echo ( $nb_recherches && $nb_sans_res / max( 1, $nb_recherches ) > .3 ) ? 'warn' : ''; ?>">
                <small>Recherches (dont sans résultat)</small>
                <b><?php echo slst_fmt( $nb_recherches ); ?> <span style="font-size:14px;">(<?php echo slst_fmt( $nb_sans_res ); ?>)</span></b>
            </div>
        </div>

        <h2>Ventes par agence</h2>
        <?php if ( empty( $data['agences'] ) ) : ?>
            <p class="muted"><em>Aucune commande retrait payée sur la période. Les chiffres apparaîtront dès les premières ventes.</em></p>
        <?php else : ?>
        <table class="widefat striped">
            <thead><tr>
                <th>Agence</th><th>Payées</th><th>CA</th><th style="width:170px;"></th>
                <th>Panier moy.</th><th>Retirées</th><th>Annulées</th><th>En attente</th>
            </tr></thead>
            <tbody>
            <?php foreach ( $data['agences'] as $slug => $a ) :
                $pct = $ca_max > 0 ? max( 2, round( 100 * $a['ca'] / $ca_max ) ) : 0; ?>
                <tr>
                    <td><strong><?php echo esc_html( function_exists( 'slc_agence_name' ) ? slc_agence_name( $slug ) : $slug ); ?></strong></td>
                    <td><?php echo slst_fmt( $a['payees'] ); ?></td>
                    <td><strong><?php echo slst_fmt( $a['ca'] ); ?> F</strong></td>
                    <td><div class="bar"><i style="width:<?php echo $pct; ?>%"></i></div></td>
                    <td><?php echo $a['payees'] ? slst_fmt( $a['ca'] / $a['payees'] ) . ' F' : '—'; ?></td>
                    <td><?php echo slst_fmt( $a['retirees'] ); ?></td>
                    <td><?php echo $a['annulees'] ? '<span style="color:#b32d2e;">' . slst_fmt( $a['annulees'] ) . '</span>' : '0'; ?></td>
                    <td><?php echo slst_fmt( $a['attente'] ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <div class="cols">
            <div>
                <h2>Produits les plus vendus</h2>
                <?php if ( empty( $data['produits'] ) ) : ?>
                    <p class="muted"><em>Aucune vente sur la période.</em></p>
                <?php else : ?>
                <table class="widefat striped">
                    <thead><tr><th>Produit</th><th>Qté</th><th>CA</th></tr></thead>
                    <tbody>
                    <?php foreach ( array_slice( $data['produits'], 0, 10, true ) as $p ) : ?>
                        <tr><td><?php echo esc_html( $p['nom'] ); ?></td>
                            <td><?php echo slst_fmt( $p['qte'] ); ?></td>
                            <td><?php echo slst_fmt( $p['ca'] ); ?> F</td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>

                <h2>Intérêt panier → achat</h2>
                <?php if ( empty( $top_paniers ) ) : ?>
                    <p class="muted"><em>Aucun ajout au panier enregistré<?php echo $depuis ? ' (suivi actif depuis le ' . esc_html( mysql2date( 'd/m/Y', $depuis ) ) . ')' : ''; ?>.</em></p>
                <?php else : ?>
                <table class="widefat striped">
                    <thead><tr><th>Produit</th><th>Ajouts panier</th><th>Vendus</th><th>Conversion</th></tr></thead>
                    <tbody>
                    <?php foreach ( $top_paniers as $row ) :
                        $pid   = (int) $row->product_id;
                        $vendu = isset( $data['produits'][ $pid ] ) ? (int) $data['produits'][ $pid ]['qte'] : 0;
                        $nom   = isset( $data['produits'][ $pid ] ) ? $data['produits'][ $pid ]['nom'] : get_the_title( $pid );
                        $conv  = $row->n ? round( 100 * $vendu / (int) $row->n ) : 0; ?>
                        <tr><td><?php echo esc_html( $nom ?: ( 'Produit #' . $pid ) ); ?></td>
                            <td><?php echo (int) $row->n; ?></td>
                            <td><?php echo $vendu; ?></td>
                            <td><?php echo $conv; ?> %</td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="muted" style="font-size:12px;">Un produit très ajouté mais peu acheté = frein en aval (prix, paiement, disponibilité).</p>
                <?php endif; ?>
            </div>

            <div>
                <h2>Ce que les clients cherchent</h2>
                <?php if ( empty( $top_recherches ) ) : ?>
                    <p class="muted"><em>Aucune recherche enregistrée pour l'instant<?php echo $depuis ? ' — le suivi est actif depuis le ' . esc_html( mysql2date( 'd/m/Y', $depuis ) ) : ''; ?>. Les données s'accumulent au fil des visites.</em></p>
                <?php else : ?>
                <table class="widefat striped">
                    <thead><tr><th>Terme</th><th>Recherches</th><th>Dont sans résultat</th></tr></thead>
                    <tbody>
                    <?php foreach ( $top_recherches as $r ) : ?>
                        <tr><td><strong><?php echo esc_html( $r->term ); ?></strong></td>
                            <td><?php echo (int) $r->n; ?></td>
                            <td><?php echo (int) $r->sans_resultat ? '<span style="color:#b32d2e;">' . (int) $r->sans_resultat . '</span>' : '0'; ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>

                <?php if ( ! empty( $recherches_vides ) ) : ?>
                <div class="decision">
                    <h3 style="margin:10px 0 4px;">🎯 Demande non satisfaite</h3>
                    <p style="margin:4px 0 10px;">Termes cherchés <strong>sans aucun résultat</strong> : des clients voulaient acheter, le catalogue n'avait rien. C'est la liste d'achats/référencement la plus directe qui soit.</p>
                    <ul style="margin:0 0 10px 18px;list-style:disc;">
                        <?php foreach ( $recherches_vides as $r ) : ?>
                            <li><strong><?php echo esc_html( $r->term ); ?></strong> — <?php echo (int) $r->n; ?> recherche<?php echo (int) $r->n > 1 ? 's' : ''; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <h2>Santé du catalogue par agence</h2>
        <table class="widefat striped" style="max-width:760px;">
            <thead><tr><th>Agence</th><th>Offres actives</th><th>Épuisées</th><th>Expirent ≤ 7 j</th></tr></thead>
            <tbody>
            <?php
            $slugs_connus = function_exists( 'slc_agences' ) ? wp_list_pluck( slc_agences(), 'slug' ) : array_keys( $cat['agences'] );
            foreach ( $slugs_connus as $slug ) :
                $c = isset( $cat['agences'][ $slug ] ) ? $cat['agences'][ $slug ] : [ 'actifs' => 0, 'epuises' => 0, 'expire7' => 0 ]; ?>
                <tr>
                    <td><?php echo esc_html( function_exists( 'slc_agence_name' ) ? slc_agence_name( $slug ) : $slug ); ?></td>
                    <td><?php echo $c['actifs'] ? slst_fmt( $c['actifs'] ) : '<strong style="color:#b32d2e;">0 — vitrine vide</strong>'; ?></td>
                    <td><?php echo $c['epuises'] ? '<span style="color:#b32d2e;">' . $c['epuises'] . '</span>' : '0'; ?></td>
                    <td><?php echo $c['expire7'] ? '<span style="color:#996800;">' . $c['expire7'] . '</span>' : '0'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ( ! empty( $cat['ruptures'] ) ) : ?>
            <div class="decision" style="max-width:730px;">
                <h3 style="margin:10px 0 4px;">📦 Ruptures à réapprovisionner</h3>
                <ul style="margin:4px 0 10px 18px;list-style:disc;">
                    <?php foreach ( $cat['ruptures'] as $r ) : ?>
                        <li><a href="<?php echo esc_url( get_edit_post_link( $r['id'] ) ); ?>"><?php echo esc_html( $r['titre'] ); ?></a>
                            <span class="muted">(<?php echo esc_html( function_exists( 'slc_agence_name' ) ? slc_agence_name( $r['agence'] ) : $r['agence'] ); ?>)</span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <p class="muted" style="margin-top:22px;font-size:12px;">
            Ventes : commandes retrait payées (statuts Payée / Prête / Retirée), 1 000 dernières de la période.
            Recherches et paniers : collecte anonyme (aucune donnée personnelle)<?php echo $depuis ? ', active depuis le ' . esc_html( mysql2date( 'd/m/Y', $depuis ) ) : ''; ?>.
        </p>
    </div>
    <?php
}
