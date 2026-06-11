<?php
/**
 * Disponibilité multi-agences — Santa Lucia Fast Food
 *
 * Active le menu d'une agence dans d'autres agences SANS dupliquer les repas :
 * un repas peut porter PLUSIEURS lignes méta `_sl_ff_agence` (une par agence).
 * Les requêtes existantes (`meta value = slug`) matchent n'importe quelle ligne,
 * donc le front, l'AJAX menu et l'API mobile fonctionnent sans modification.
 *
 * Règles d'activation pour chaque plat de la source × chaque agence cible :
 *  - un post couvre déjà ce plat (même titre) dans la cible → on aligne ses jours
 *    sur la source (même programme hebdomadaire), rien n'est créé ;
 *  - sinon → on AJOUTE la méta agence cible au post source (zéro nouveau post).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Agences par ville (slugs de la taxonomie sl_agence_promo). */
function sl_ff_city_map() {
    return [
        'Yaoundé' => [ 'nkondengui', 'ngousso', 'nkoabang', 'mokolo', 'melen', 'essos', 'ahala', 'odza', 'mvan', 'simbock' ],
        'Douala'  => [ 'cite-cicam', 'akwa-nord', 'bonaberi', 'bonamoussadi', 'akwa', 'nkolbong', 'bercy', 'cite-des-palmiers' ],
    ];
}

/* ============================================================
   JOURNAL DES OPÉRATIONS (option, 50 dernières entrées)
   ============================================================ */
function sl_ff_sync_log_get() {
    return (array) get_option( 'sl_ff_sync_log', [] );
}
function sl_ff_sync_log_start( $job, $action, $source, $targets, $total ) {
    $log = sl_ff_sync_log_get();
    $u   = wp_get_current_user();
    $log[] = [
        'job'     => $job,
        'date'    => current_time( 'Y-m-d H:i' ),
        'user'    => ( $u && $u->exists() ) ? $u->display_name : '?',
        'action'  => $action,
        'source'  => $source,
        'targets' => $targets,
        'total'   => (int) $total,
        'done1'   => 0,
        'done2'   => 0,
        'fini'    => false,
    ];
    if ( count( $log ) > 50 ) $log = array_slice( $log, -50 );
    update_option( 'sl_ff_sync_log', $log, false );
}
function sl_ff_sync_log_progress( $job, $d1, $d2, $fini ) {
    $log = sl_ff_sync_log_get();
    for ( $i = count( $log ) - 1; $i >= 0; $i-- ) {
        if ( $log[ $i ]['job'] === $job ) {
            $log[ $i ]['done1'] += (int) $d1;
            $log[ $i ]['done2'] += (int) $d2;
            if ( $fini ) $log[ $i ]['fini'] = true;
            break;
        }
    }
    update_option( 'sl_ff_sync_log', $log, false );
}

/* ============================================================
   MENU ADMIN (prio 1000 : après sl_ff_build_menu qui tourne à 999)
   ============================================================ */
add_action( 'admin_menu', 'sl_ff_sync_menu', 1000 );
function sl_ff_sync_menu() {
    if ( ! current_user_can( 'manage_options' ) && ! ( function_exists( 'sl_ff_is_admin_user' ) && sl_ff_is_admin_user() ) ) {
        return;
    }
    add_submenu_page(
        'sl-fastfood',
        'Disponibilité multi-agences',
        'Disponibilité multi-agences',
        'read',
        'sl-ff-sync',
        'sl_ff_sync_page'
    );
}

/* ============================================================
   PAGE ADMIN
   ============================================================ */
function sl_ff_sync_page() {
    if ( ! current_user_can( 'manage_options' ) && ! ( function_exists( 'sl_ff_is_admin_user' ) && sl_ff_is_admin_user() ) ) {
        wp_die( 'Acces refuse.' );
    }

    $agences = get_terms( [ 'taxonomy' => 'sl_agence_promo', 'hide_empty' => false, 'orderby' => 'name' ] );
    if ( is_wp_error( $agences ) ) $agences = [];
    $by_slug = [];
    foreach ( $agences as $a ) { $by_slug[ $a->slug ] = $a; }

    // Regrouper par ville (les slugs inconnus de la carte vont dans « Autres »)
    $groups = [];
    $placed = [];
    foreach ( sl_ff_city_map() as $ville => $slugs ) {
        foreach ( $slugs as $s ) {
            if ( isset( $by_slug[ $s ] ) ) {
                $groups[ $ville ][] = $by_slug[ $s ];
                $placed[ $s ] = 1;
            }
        }
    }
    foreach ( $agences as $a ) {
        if ( ! isset( $placed[ $a->slug ] ) ) $groups['Autres'][] = $a;
    }

    // Nombre de plats disponibles par agence (compte les lignes méta, 1 requête)
    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT pm.meta_value AS ag, COUNT(*) AS n
         FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         WHERE pm.meta_key = '_sl_ff_agence'
           AND p.post_type = 'sl_repas'
           AND p.post_status = 'publish'
         GROUP BY pm.meta_value"
    );
    $counts = [];
    foreach ( (array) $rows as $r ) { $counts[ $r->ag ] = (int) $r->n; }
    ?>
    <div class="wrap">
        <h1>Disponibilité multi-agences</h1>
        <p>Active ou désactive le menu d'une agence dans les agences de votre choix, <strong>sans dupliquer les repas</strong>
           (la base de données n'est pas alourdie : le plat existant devient simplement disponible ailleurs,
           avec le même programme hebdomadaire). Les images suivent automatiquement.
           La désactivation retire le plat du menu des agences ciblées (mise en brouillon ou retrait de l'étiquette agence) :
           <strong>rien n'est jamais supprimé</strong>, une réactivation restaure tout à l'identique.</p>

        <div class="sl-ff-sync-card"
             id="sl-ff-sync-app"
             data-ajaxurl="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
             data-nonce="<?php echo esc_attr( wp_create_nonce( 'sl_ff_sync_ajax' ) ); ?>">

            <h2>1. Agence source (celle dont le menu sert de modèle)</h2>
            <select id="sl-ff-sync-source" style="min-width:300px;">
                <option value="">— Choisir l'agence source —</option>
                <?php foreach ( $groups as $ville => $list ) : ?>
                    <optgroup label="<?php echo esc_attr( $ville ); ?>">
                    <?php foreach ( $list as $a ) :
                        $n = $counts[ $a->slug ] ?? 0; ?>
                        <option value="<?php echo esc_attr( $a->slug ); ?>" data-count="<?php echo esc_attr( $n ); ?>">
                            <?php echo esc_html( $a->name . ' — ' . $n . ' plat' . ( $n > 1 ? 's' : '' ) ); ?>
                        </option>
                    <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>

            <h2>2. Agences cibles</h2>
            <div class="sl-ff-sync-quick">
                <button type="button" class="button" id="sl-ff-sync-all-btn">Toutes les agences</button>
                <?php foreach ( $groups as $ville => $list ) : if ( $ville === 'Autres' ) continue; ?>
                <button type="button" class="button sl-ff-city-btn" data-ville="<?php echo esc_attr( $ville ); ?>">
                    Toute la ville de <?php echo esc_html( $ville ); ?> (<?php echo count( $list ); ?>)
                </button>
                <?php endforeach; ?>
                <button type="button" class="button" id="sl-ff-sync-none-btn">Tout décocher</button>
            </div>

            <?php foreach ( $groups as $ville => $list ) : ?>
            <h3 class="sl-ff-sync-ville"><?php echo esc_html( $ville ); ?></h3>
            <div class="sl-ff-sync-targets">
                <?php foreach ( $list as $a ) : ?>
                    <label data-ville="<?php echo esc_attr( $ville ); ?>">
                        <input type="checkbox" class="sl-ff-sync-target" value="<?php echo esc_attr( $a->slug ); ?>" data-ville="<?php echo esc_attr( $ville ); ?>">
                        <?php echo esc_html( $a->name ); ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>

            <p class="sl-ff-sync-resume" id="sl-ff-sync-resume"></p>

            <p style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
                <button type="button" class="button button-primary button-hero" id="sl-ff-sync-go" disabled>
                    Activer la disponibilité
                </button>
                <button type="button" class="button button-hero sl-ff-deact-btn" id="sl-ff-deact-go" disabled>
                    Désactiver la disponibilité
                </button>
            </p>

            <div id="sl-ff-sync-progress" style="display:none;"></div>
        </div>

        <?php $jlog = array_reverse( array_slice( sl_ff_sync_log_get(), -12 ) ); if ( $jlog ) : ?>
        <div class="sl-ff-sync-card" style="margin-top:18px;">
            <h2>Journal des opérations (12 dernières)</h2>
            <table class="widefat striped" style="margin-top:8px;">
                <thead><tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Source</th><th>Cibles</th><th>Résultat</th></tr></thead>
                <tbody>
                <?php foreach ( $jlog as $e ) : ?>
                    <tr>
                        <td><?php echo esc_html( $e['date'] ); ?></td>
                        <td><?php echo esc_html( $e['user'] ); ?></td>
                        <td><?php echo $e['action'] === 'desactivation'
                            ? '<span style="color:#b32d2e;font-weight:600;">Désactivation</span>'
                            : '<span style="color:#2271b1;font-weight:600;">Activation</span>'; ?></td>
                        <td><?php echo esc_html( $e['source'] ); ?></td>
                        <td><?php echo (int) count( (array) $e['targets'] ); ?> agence(s)</td>
                        <td><?php echo ! empty( $e['fini'] )
                            ? esc_html( $e['done1'] . ' traités, ' . $e['done2'] . ' déjà OK' )
                            : '<em>interrompu (' . (int) ( $e['done1'] + $e['done2'] ) . '/' . (int) $e['total'] . ')</em>'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <style>
    .sl-ff-sync-card { background:#fff; border-radius:10px; padding:24px; box-shadow:0 1px 4px rgba(0,0,0,.08); max-width:900px; }
    .sl-ff-sync-card h2 { margin:18px 0 10px; font-size:15px; }
    .sl-ff-sync-card h2:first-child { margin-top:0; }
    .sl-ff-sync-quick { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:6px; }
    .sl-ff-sync-ville { margin:14px 0 6px; font-size:13px; text-transform:uppercase; letter-spacing:.4px; color:#646970; }
    .sl-ff-sync-targets { display:grid; grid-template-columns:repeat(auto-fill, minmax(190px, 1fr)); gap:6px 16px; }
    .sl-ff-sync-targets label { display:flex; align-items:center; gap:6px; }
    .sl-ff-sync-targets label.is-source { opacity:.4; pointer-events:none; }
    .sl-ff-sync-resume { margin-top:16px; font-weight:600; color:#1d2327; }
    #sl-ff-sync-progress .bar { height:14px; background:#e2e4e7; border-radius:7px; overflow:hidden; margin:8px 0; }
    #sl-ff-sync-progress .bar > span { display:block; height:100%; width:0; background:#2271b1; transition:width .25s; }
    #sl-ff-sync-progress .line { font-size:13px; color:#444; }
    #sl-ff-sync-progress .ok  { color:#2e7d32; font-weight:600; }
    #sl-ff-sync-progress .err { color:#c62828; }
    .sl-ff-deact-btn { background:#b32d2e!important; border-color:#a02222!important; color:#fff!important; }
    .sl-ff-deact-btn:hover:not(:disabled) { background:#8c1c1c!important; }
    .sl-ff-deact-btn:disabled { opacity:.5; cursor:default; }
    </style>

    <script>
    (function () {
        var app     = document.getElementById('sl-ff-sync-app');
        var ajaxurl = app.getAttribute('data-ajaxurl');
        var nonce   = app.getAttribute('data-nonce');
        var srcSel  = document.getElementById('sl-ff-sync-source');
        var targets = Array.prototype.slice.call(document.querySelectorAll('.sl-ff-sync-target'));
        var resume  = document.getElementById('sl-ff-sync-resume');
        var goBtn    = document.getElementById('sl-ff-sync-go');
        var deactBtn = document.getElementById('sl-ff-deact-go');
        var box     = document.getElementById('sl-ff-sync-progress');
        var running = false;

        function selectedTargets() {
            return targets.filter(function (c) { return c.checked && !c.disabled; })
                          .map(function (c) { return c.value; });
        }

        function refresh() {
            var src = srcSel.value;
            targets.forEach(function (c) {
                var isSrc = (c.value === src);
                c.disabled = isSrc;
                if (isSrc) c.checked = false;
                c.closest('label').classList.toggle('is-source', isSrc);
            });
            var n   = selectedTargets().length;
            var cnt = srcSel.selectedOptions[0] ? parseInt(srcSel.selectedOptions[0].getAttribute('data-count') || '0') : 0;
            if (src && n > 0 && cnt > 0) {
                resume.textContent = cnt + ' plat(s) × ' + n + ' agence(s) = ' + (cnt * n) + ' opération(s).';
                goBtn.disabled    = running;
                deactBtn.disabled = running;
            } else {
                resume.textContent = (src && cnt === 0) ? 'Cette agence n\'a aucun plat publié.' : '';
                goBtn.disabled    = true;
                deactBtn.disabled = true;
            }
        }

        srcSel.addEventListener('change', refresh);
        targets.forEach(function (c) { c.addEventListener('change', refresh); });

        document.getElementById('sl-ff-sync-all-btn').addEventListener('click', function () {
            targets.forEach(function (c) { if (!c.disabled) c.checked = true; });
            refresh();
        });
        document.getElementById('sl-ff-sync-none-btn').addEventListener('click', function () {
            targets.forEach(function (c) { c.checked = false; });
            refresh();
        });
        Array.prototype.forEach.call(document.querySelectorAll('.sl-ff-city-btn'), function (btn) {
            btn.addEventListener('click', function () {
                var ville = btn.getAttribute('data-ville');
                targets.forEach(function (c) {
                    if (c.getAttribute('data-ville') === ville && !c.disabled) c.checked = true;
                });
                refresh();
            });
        });

        async function runOperation(mode) {
            if (running) return;
            var src = srcSel.value;
            var tg  = selectedTargets();
            if (!src || tg.length === 0) return;

            if (mode === 'deactivate') {
                var cnt = srcSel.selectedOptions[0] ? parseInt(srcSel.selectedOptions[0].getAttribute('data-count') || '0') : 0;
                if (!confirm('Désactiver ' + cnt + ' plat(s) dans ' + tg.length + ' agence(s) ?\nLes visiteurs ne verront plus ces plats dans les agences sélectionnées.')) {
                    return;
                }
            }

            running = true;
            goBtn.disabled    = true;
            deactBtn.disabled = true;
            box.style.display = 'block';
            box.innerHTML = '<div class="line">Préparation…</div><div class="bar"><span></span></div><div class="line" id="sl-ff-sync-status"></div>';
            var barFill = box.querySelector('.bar > span');
            var status  = document.getElementById('sl-ff-sync-status');

            function fail(msg) {
                status.innerHTML = '<span class="err">' + msg + '</span>';
                running = false;
                refresh();
            }

            var startAction = mode === 'deactivate' ? 'sl_ff_deact_start' : 'sl_ff_sync_start';
            var chunkAction = mode === 'deactivate' ? 'sl_ff_deact_chunk' : 'sl_ff_sync_chunk';

            var fd = new FormData();
            fd.append('action', startAction);
            fd.append('nonce', nonce);
            fd.append('source', src);
            tg.forEach(function (s) { fd.append('targets[]', s); });
            var start;
            try {
                start = await fetch(ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
            } catch (e) { return fail('Erreur réseau au démarrage.'); }
            if (!start || !start.success) return fail((start && start.data && start.data.msg) || 'Échec du démarrage.');

            var job = start.data.job, total = start.data.total;
            var offset = 0, done1 = 0, done2 = 0, errs = [];
            var label1 = mode === 'deactivate' ? 'désactivé(s)' : 'activé(s)';
            var label2 = mode === 'deactivate' ? 'déjà absent(s)' : 'déjà présents (jours alignés)';

            while (offset < total) {
                var fd2 = new FormData();
                fd2.append('action', chunkAction);
                fd2.append('nonce', nonce);
                fd2.append('job', job);
                fd2.append('offset', offset);
                fd2.append('size', 100);
                var c;
                try {
                    c = await fetch(ajaxurl, { method: 'POST', body: fd2, credentials: 'same-origin' }).then(function (r) { return r.json(); });
                } catch (e) { return fail('Erreur réseau pendant le traitement (relancez : reprise sans risque).'); }
                if (!c || !c.success) return fail((c && c.data && c.data.msg) || 'Échec d\'un lot.');
                done1 += c.data.done1;
                done2 += c.data.done2;
                errs = errs.concat(c.data.errors || []);
                offset = c.data.next;
                var pct = Math.round(100 * offset / total);
                barFill.style.width = pct + '%';
                status.textContent = offset + ' / ' + total + ' (' + pct + '%) — ' + done1 + ' ' + label1 + ', ' + done2 + ' ' + label2;
                if (c.data.done) break;
            }

            barFill.style.width = '100%';
            status.innerHTML = '<span class="ok">Terminé : ' + done1 + ' plat(s) ' + label1 + ', ' + done2 + ' ' + label2 + '.</span>'
                + (errs.length ? '<br><span class="err">' + errs.length + ' erreur(s) : ' + errs.slice(0, 5).join(' | ') + '</span>' : '');
            running = false;
            refresh();
        }

        goBtn.addEventListener('click',    function () { runOperation('activate');   });
        deactBtn.addEventListener('click', function () { runOperation('deactivate'); });

        refresh();
    })();
    </script>
    <?php
}

/* ============================================================
   AJAX — démarrage : construit les unités depuis le menu source
   ============================================================ */
add_action( 'wp_ajax_sl_ff_sync_start', 'sl_ff_ajax_sync_start' );
function sl_ff_ajax_sync_start() {
    if ( ! current_user_can( 'sl_ff_import' ) && ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'msg' => 'Acces refuse.' ], 403 );
    }
    check_ajax_referer( 'sl_ff_sync_ajax', 'nonce' );

    $source  = isset( $_POST['source'] ) ? sanitize_title( wp_unslash( $_POST['source'] ) ) : '';
    $targets = isset( $_POST['targets'] ) ? array_map( 'sanitize_title', (array) wp_unslash( $_POST['targets'] ) ) : [];
    $targets = array_values( array_unique( array_filter( $targets, function ( $t ) use ( $source ) {
        return $t !== '' && $t !== $source;
    } ) ) );

    if ( $source === '' )    wp_send_json_error( [ 'msg' => 'Agence source manquante.' ] );
    if ( empty( $targets ) ) wp_send_json_error( [ 'msg' => 'Aucune agence cible.' ] );

    $valid = get_terms( [ 'taxonomy' => 'sl_agence_promo', 'hide_empty' => false, 'fields' => 'slugs' ] );
    $valid = is_wp_error( $valid ) ? [] : $valid;
    if ( ! in_array( $source, $valid, true ) ) {
        wp_send_json_error( [ 'msg' => 'Agence source inconnue.' ] );
    }
    $targets = array_values( array_intersect( $targets, $valid ) );
    if ( empty( $targets ) ) wp_send_json_error( [ 'msg' => 'Agences cibles invalides.' ] );

    $posts = get_posts( [
        'post_type'      => 'sl_repas',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [ [ 'key' => '_sl_ff_agence', 'value' => $source ] ],
        'orderby'        => 'title',
        'order'          => 'ASC',
    ] );
    if ( empty( $posts ) ) {
        wp_send_json_error( [ 'msg' => 'L\'agence source n\'a aucun plat publié.' ] );
    }

    $units = [];
    $seen  = [];
    foreach ( $posts as $p ) {
        $jours = get_post_meta( $p->ID, '_sl_ff_jours', true );
        $jours = is_array( $jours ) ? array_values( $jours ) : [];
        foreach ( $targets as $ta ) {
            $key = mb_strtolower( $p->post_title, 'UTF-8' ) . '|' . $ta;
            if ( isset( $seen[ $key ] ) ) continue;
            $seen[ $key ] = 1;
            $units[] = [
                'src'    => (int) $p->ID,
                'nom'    => $p->post_title,
                'agence' => $ta,
                'jours'  => $jours,
            ];
        }
    }

    $job = 'sl_ff_syn_' . wp_generate_password( 12, false );
    set_transient( $job, $units, HOUR_IN_SECONDS );
    sl_ff_sync_log_start( $job, 'activation', $source, $targets, count( $units ) );

    wp_send_json_success( [
        'job'   => $job,
        'total' => count( $units ),
    ] );
}

/* ============================================================
   AJAX — traitement d'un lot (activation sans duplication)
   ============================================================ */
add_action( 'wp_ajax_sl_ff_sync_chunk', 'sl_ff_ajax_sync_chunk' );
function sl_ff_ajax_sync_chunk() {
    if ( ! current_user_can( 'sl_ff_import' ) && ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'msg' => 'Acces refuse.' ], 403 );
    }
    check_ajax_referer( 'sl_ff_sync_ajax', 'nonce' );

    $job    = isset( $_POST['job'] ) ? sanitize_text_field( wp_unslash( $_POST['job'] ) ) : '';
    $offset = isset( $_POST['offset'] ) ? max( 0, (int) $_POST['offset'] ) : 0;
    $size   = isset( $_POST['size'] ) ? min( 200, max( 1, (int) $_POST['size'] ) ) : 100;

    if ( strpos( $job, 'sl_ff_syn_' ) !== 0 ) {
        wp_send_json_error( [ 'msg' => 'Job invalide.' ] );
    }
    $units = get_transient( $job );
    if ( $units === false ) {
        wp_send_json_error( [ 'msg' => 'Session expiree, relancez l\'operation.' ] );
    }

    $total   = count( $units );
    $done1   = 0;
    $done2   = 0;
    $errors  = [];

    $slice = array_slice( $units, $offset, $size );
    foreach ( $slice as $u ) {
        sl_ff_sync_activate_unit( $u, $done1, $done2, $errors );
    }

    $next = $offset + count( $slice );
    $done = ( $next >= $total );
    if ( $done ) {
        delete_transient( $job );
    }
    if ( function_exists( 'sl_ff_bump_menu_cache' ) ) sl_ff_bump_menu_cache();
    sl_ff_sync_log_progress( $job, $done1, $done2, $done );

    wp_send_json_success( [
        'done1'  => $done1,
        'done2'  => $done2,
        'errors' => $errors,
        'next'   => $next,
        'total'  => $total,
        'done'   => $done,
    ] );
}

/* ============================================================
   AJAX — démarrage désactivation
   ============================================================ */
add_action( 'wp_ajax_sl_ff_deact_start', 'sl_ff_ajax_deact_start' );
function sl_ff_ajax_deact_start() {
    if ( ! current_user_can( 'sl_ff_import' ) && ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'msg' => 'Acces refuse.' ], 403 );
    }
    check_ajax_referer( 'sl_ff_sync_ajax', 'nonce' );

    $source  = isset( $_POST['source'] ) ? sanitize_title( wp_unslash( $_POST['source'] ) ) : '';
    $targets = isset( $_POST['targets'] ) ? array_map( 'sanitize_title', (array) wp_unslash( $_POST['targets'] ) ) : [];
    $targets = array_values( array_unique( array_filter( $targets, function ( $t ) use ( $source ) {
        return $t !== '' && $t !== $source;
    } ) ) );

    if ( $source === '' )    wp_send_json_error( [ 'msg' => 'Agence source manquante.' ] );
    if ( empty( $targets ) ) wp_send_json_error( [ 'msg' => 'Aucune agence cible.' ] );

    $valid = get_terms( [ 'taxonomy' => 'sl_agence_promo', 'hide_empty' => false, 'fields' => 'slugs' ] );
    $valid = is_wp_error( $valid ) ? [] : $valid;
    if ( ! in_array( $source, $valid, true ) ) {
        wp_send_json_error( [ 'msg' => 'Agence source inconnue.' ] );
    }
    $targets = array_values( array_intersect( $targets, $valid ) );
    if ( empty( $targets ) ) wp_send_json_error( [ 'msg' => 'Agences cibles invalides.' ] );

    $posts = get_posts( [
        'post_type'      => 'sl_repas',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [ [ 'key' => '_sl_ff_agence', 'value' => $source ] ],
        'orderby'        => 'title',
        'order'          => 'ASC',
    ] );
    if ( empty( $posts ) ) {
        wp_send_json_error( [ 'msg' => 'L\'agence source n\'a aucun plat publié.' ] );
    }

    $units = [];
    $seen  = [];
    foreach ( $posts as $p ) {
        foreach ( $targets as $ta ) {
            $key = mb_strtolower( $p->post_title, 'UTF-8' ) . '|' . $ta;
            if ( isset( $seen[ $key ] ) ) continue;
            $seen[ $key ] = 1;
            $units[] = [ 'src' => (int) $p->ID, 'nom' => $p->post_title, 'agence' => $ta ];
        }
    }

    $job = 'sl_ff_dea_' . wp_generate_password( 12, false );
    set_transient( $job, $units, HOUR_IN_SECONDS );
    sl_ff_sync_log_start( $job, 'desactivation', $source, $targets, count( $units ) );

    wp_send_json_success( [ 'job' => $job, 'total' => count( $units ) ] );
}

/* ============================================================
   AJAX — traitement d'un lot (désactivation)
   ============================================================ */
add_action( 'wp_ajax_sl_ff_deact_chunk', 'sl_ff_ajax_deact_chunk' );
function sl_ff_ajax_deact_chunk() {
    if ( ! current_user_can( 'sl_ff_import' ) && ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'msg' => 'Acces refuse.' ], 403 );
    }
    check_ajax_referer( 'sl_ff_sync_ajax', 'nonce' );

    $job    = isset( $_POST['job'] ) ? sanitize_text_field( wp_unslash( $_POST['job'] ) ) : '';
    $offset = isset( $_POST['offset'] ) ? max( 0, (int) $_POST['offset'] ) : 0;
    $size   = isset( $_POST['size'] )   ? min( 200, max( 1, (int) $_POST['size'] ) ) : 100;

    if ( strpos( $job, 'sl_ff_dea_' ) !== 0 ) {
        wp_send_json_error( [ 'msg' => 'Job invalide.' ] );
    }
    $units = get_transient( $job );
    if ( $units === false ) {
        wp_send_json_error( [ 'msg' => 'Session expiree, relancez l\'operation.' ] );
    }

    $total  = count( $units );
    $done1  = 0; // désactivés
    $done2  = 0; // déjà absents
    $errors = [];

    $slice = array_slice( $units, $offset, $size );
    foreach ( $slice as $u ) {
        sl_ff_sync_deactivate_unit( $u, $done1, $done2, $errors );
    }

    $next = $offset + count( $slice );
    $done = ( $next >= $total );
    if ( $done ) delete_transient( $job );
    if ( function_exists( 'sl_ff_bump_menu_cache' ) ) sl_ff_bump_menu_cache();
    sl_ff_sync_log_progress( $job, $done1, $done2, $done );

    wp_send_json_success( [
        'done1'  => $done1,
        'done2'  => $done2,
        'errors' => $errors,
        'next'   => $next,
        'total'  => $total,
        'done'   => $done,
    ] );
}

/**
 * Désactive un plat dans une agence cible. Deux cas, miroir de l'activation :
 *  - le post source porte la méta `_sl_ff_agence = target` (plat partagé)
 *    → on supprime cette ligne méta (les autres agences restent intactes) ;
 *  - un post NATIF du même nom existe dans la cible (ancien modèle dupliqué)
 *    → on le passe en BROUILLON : invisible sur le front/AJAX/API qui ne
 *    requêtent que `publish`, et 100 % réversible (l'activation re-publie
 *    ce même post via wp_update_post). Rien n'est jamais supprimé.
 */
function sl_ff_sync_deactivate_unit( $unit, &$deactivated, &$skipped, &$errors ) {
    $src    = (int) $unit['src'];
    $nom    = $unit['nom'];
    $target = $unit['agence'];
    $done   = false;

    if ( ! get_post( $src ) ) {
        $errors[] = $nom . ' : post source introuvable.';
        return;
    }

    // Cas 1 : étiquette partagée sur le post source
    $rows = get_post_meta( $src, '_sl_ff_agence' );
    if ( in_array( $target, (array) $rows, true ) ) {
        delete_post_meta( $src, '_sl_ff_agence', $target );
        $done = true;
    }

    // Cas 2 : post(s) natif(s) publié(s) du même nom dans la cible
    $natives = get_posts( [
        'post_type'              => 'sl_repas',
        'post_status'            => 'publish',
        'posts_per_page'         => -1,
        'fields'                 => 'ids',
        'title'                  => $nom,
        'post__not_in'           => [ $src ],
        'meta_query'             => [ [ 'key' => '_sl_ff_agence', 'value' => $target ] ],
        'no_found_rows'          => true,
        'update_post_term_cache' => false,
        'update_post_meta_cache' => false,
        'suppress_filters'       => true,
    ] );
    foreach ( $natives as $pid ) {
        $res = wp_update_post( [ 'ID' => (int) $pid, 'post_status' => 'draft' ], true );
        if ( is_wp_error( $res ) ) {
            $errors[] = $nom . ' (' . $target . ') : ' . $res->get_error_message();
        } else {
            $done = true;
        }
    }

    if ( $done ) {
        $deactivated++;
    } else {
        $skipped++;
    }
}

/**
 * Active un plat dans une agence cible :
 *  - un post du même nom couvre déjà la cible → aligne ses jours (même hebdo) ;
 *  - sinon → ajoute la méta agence au post source (AUCUN post créé).
 */
function sl_ff_sync_activate_unit( $unit, &$activated, &$aligned, &$errors ) {
    $src    = (int) $unit['src'];
    $nom    = $unit['nom'];
    $target = $unit['agence'];
    $jours  = is_array( $unit['jours'] ) ? $unit['jours'] : [];

    $existing = get_posts( [
        'post_type'              => 'sl_repas',
        'post_status'            => [ 'publish', 'draft', 'pending', 'private' ],
        'posts_per_page'         => 1,
        'fields'                 => 'ids',
        'title'                  => $nom,
        'meta_query'             => [
            [ 'key' => '_sl_ff_agence', 'value' => $target ],
            // Ignorer les doublons fusionnés dans un post partagé (migration) :
            // les re-publier recréerait les doublons que la fusion a éliminés.
            [ 'key' => '_sl_ff_merged_into', 'compare' => 'NOT EXISTS' ],
        ],
        'no_found_rows'          => true,
        'update_post_term_cache' => false,
        'update_post_meta_cache' => false,
        'suppress_filters'       => true,
    ] );

    if ( ! empty( $existing ) ) {
        $pid = (int) $existing[0];
        wp_update_post( [ 'ID' => $pid, 'post_status' => 'publish' ] );
        update_post_meta( $pid, '_sl_ff_jours', $jours );
        $aligned++;
        return;
    }

    if ( ! get_post( $src ) ) {
        $errors[] = $nom . ' : post source introuvable.';
        return;
    }
    $rows = get_post_meta( $src, '_sl_ff_agence' );
    if ( ! in_array( $target, (array) $rows, true ) ) {
        add_post_meta( $src, '_sl_ff_agence', $target );
    }
    $activated++;
}
