<?php
/**
 * MIGRATION par lots — fusion des sl_repas dupliqués vers le modèle partagé.
 * Temporaire, protégé par clé, retiré après la migration.
 *
 * Modes :
 *  ?mode=verify  → comptage publiés par agence (posts + titres distincts), lecture seule
 *  ?mode=run&offset=N&size=M → traite les groupes de titres [N..N+M)
 *      - groupe propre (toutes copies identiques hors agence) :
 *          post le plus ancien = canonique ; il reçoit l'union des étiquettes agence ;
 *          les autres passent en brouillon + méta _sl_ff_merged_into = ID canonique
 *      - groupe à 1 post ou en conflit : ignoré (rien n'est modifié)
 *      - journal de rollback append-only dans uploads/slff-mig-rollback-x7k2.json
 *  ?mode=purge   → purge LiteSpeed (all) après migration
 */
if ( ! isset( $_GET['k'] ) || $_GET['k'] !== 'sl2026migrateX7K2' ) {
    http_response_code( 404 );
    exit;
}
require_once __DIR__ . '/wp-load.php';
if ( ! defined( 'ABSPATH' ) ) exit;
nocache_headers();
header( 'Content-Type: application/json; charset=utf-8' );
global $wpdb;
@set_time_limit( 180 );

$mode = isset( $_GET['mode'] ) ? $_GET['mode'] : 'verify';

/* ---------- VERIFY : état publié par agence (lecture seule) ---------- */
if ( $mode === 'verify' ) {
    $rows = $wpdb->get_results(
        "SELECT pm.meta_value AS ag, COUNT(DISTINCT p.ID) AS posts, COUNT(DISTINCT p.post_title) AS titres
         FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         WHERE pm.meta_key = '_sl_ff_agence' AND p.post_type = 'sl_repas' AND p.post_status = 'publish'
         GROUP BY pm.meta_value ORDER BY pm.meta_value"
    );
    $total = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='sl_repas' AND post_status='publish'"
    );
    echo json_encode( [ 'total_publies' => $total, 'par_agence' => $rows ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
    exit;
}

/* ---------- PURGE : caches après migration ---------- */
if ( $mode === 'purge' ) {
    $done = [];
    if ( has_action( 'litespeed_purge_all' ) || class_exists( '\LiteSpeed\Purge' ) ) {
        do_action( 'litespeed_purge_all' );
        $done[] = 'litespeed_all';
    }
    foreach ( [ home_url( '/' ) ] as $u ) {
        wp_remote_request( $u, [ 'method' => 'PURGE', 'timeout' => 3, 'sslverify' => false, 'blocking' => false ] );
        $done[] = 'varnish ' . $u;
    }
    echo json_encode( [ 'purge' => $done ] );
    exit;
}

if ( $mode !== 'run' ) { echo json_encode( [ 'error' => 'mode inconnu' ] ); exit; }

/* ---------- Reconstruction des groupes (même logique que la simulation) ---------- */
$posts = $wpdb->get_results(
    "SELECT ID, post_title, post_date, MD5(CONCAT(post_content,'|',post_excerpt)) AS chash
     FROM {$wpdb->posts}
     WHERE post_type = 'sl_repas' AND post_status = 'publish'"
);
$ids = wp_list_pluck( $posts, 'ID' );

$meta = [];
foreach ( array_chunk( $ids, 1000 ) as $chunk ) {
    $in   = implode( ',', array_map( 'intval', $chunk ) );
    $rows = $wpdb->get_results(
        "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta}
         WHERE post_id IN ($in) AND meta_key NOT LIKE '\_edit%'"
    );
    foreach ( $rows as $r ) { $meta[ $r->post_id ][ $r->meta_key ][] = $r->meta_value; }
}
$cats = [];
foreach ( array_chunk( $ids, 1000 ) as $chunk ) {
    $in   = implode( ',', array_map( 'intval', $chunk ) );
    $rows = $wpdb->get_results(
        "SELECT tr.object_id, t.name
         FROM {$wpdb->term_relationships} tr
         INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'sl_repas_cat'
         INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
         WHERE tr.object_id IN ($in)"
    );
    foreach ( $rows as $r ) { $cats[ $r->object_id ][] = $r->name; }
}

$groups = [];
foreach ( $posts as $p ) {
    $key = mb_strtolower( trim( preg_replace( '/\s+/u', ' ', $p->post_title ) ), 'UTF-8' );
    $groups[ $key ][] = $p;
}
ksort( $groups );

function slff_sig( $p, $meta, $cats ) {
    $m = isset( $meta[ $p->ID ] ) ? $meta[ $p->ID ] : [];
    unset( $m['_sl_ff_agence'] );
    foreach ( $m as &$v ) { sort( $v ); }
    unset( $v );
    ksort( $m );
    $c = isset( $cats[ $p->ID ] ) ? $cats[ $p->ID ] : [];
    sort( $c );
    return md5( serialize( $m ) . '|' . serialize( $c ) . '|' . $p->chash );
}

$offset = isset( $_GET['offset'] ) ? max( 0, (int) $_GET['offset'] ) : 0;
$size   = isset( $_GET['size'] )   ? min( 40, max( 1, (int) $_GET['size'] ) ) : 20;
$keys   = array_keys( $groups );
$total  = count( $keys );
$slice  = array_slice( $keys, $offset, $size );

$up      = wp_get_upload_dir();
$logfile = trailingslashit( $up['basedir'] ) . 'slff-mig-rollback-x7k2.json';
$log     = file_exists( $logfile ) ? (array) json_decode( (string) file_get_contents( $logfile ), true ) : [];

$migrated = 0; $drafted = 0; $skipped = 0; $errors = [];

foreach ( $slice as $key ) {
    $list = $groups[ $key ];
    if ( count( $list ) === 1 ) { $skipped++; continue; }

    // Re-vérifier la propreté au moment T (sécurité)
    $sigs = [];
    foreach ( $list as $p ) { $sigs[ slff_sig( $p, $meta, $cats ) ] = 1; }
    if ( count( $sigs ) > 1 ) { $skipped++; continue; }

    // Canonique = post le plus ancien (ID le plus petit à date égale)
    usort( $list, function ( $a, $b ) {
        return strcmp( $a->post_date . str_pad( $a->ID, 12, '0', STR_PAD_LEFT ),
                       $b->post_date . str_pad( $b->ID, 12, '0', STR_PAD_LEFT ) );
    } );
    $canon  = (int) $list[0]->ID;
    $others = array_slice( $list, 1 );

    // Union des agences du groupe
    $union = [];
    foreach ( $list as $p ) {
        foreach ( (array) ( $meta[ $p->ID ]['_sl_ff_agence'] ?? [] ) as $ag ) {
            if ( $ag !== '' ) $union[ $ag ] = 1;
        }
    }
    $have  = (array) ( $meta[ $canon ]['_sl_ff_agence'] ?? [] );
    $added = [];
    foreach ( array_keys( $union ) as $ag ) {
        if ( ! in_array( $ag, $have, true ) ) {
            add_post_meta( $canon, '_sl_ff_agence', $ag );
            $added[] = $ag;
        }
    }

    // Brouillon direct en SQL (pas de wp_update_post : pas de hooks lourds × 3145)
    $draft_ids = [];
    foreach ( $others as $p ) {
        $pid = (int) $p->ID;
        $ok  = $wpdb->update( $wpdb->posts, [ 'post_status' => 'draft' ], [ 'ID' => $pid ] );
        if ( $ok === false ) { $errors[] = $p->post_title . ' #' . $pid . ' : echec draft'; continue; }
        add_post_meta( $pid, '_sl_ff_merged_into', $canon, true );
        clean_post_cache( $pid );
        $draft_ids[] = $pid;
        $drafted++;
    }
    clean_post_cache( $canon );

    $log[] = [ 'plat' => $list[0]->post_title, 'canonical' => $canon, 'added' => $added, 'drafted' => $draft_ids ];
    $migrated++;
}

file_put_contents( $logfile, json_encode( $log, JSON_UNESCAPED_UNICODE ) );

$next = $offset + count( $slice );
echo json_encode( [
    'migrated' => $migrated,
    'drafted'  => $drafted,
    'skipped'  => $skipped,
    'errors'   => $errors,
    'next'     => $next,
    'total'    => $total,
    'done'     => ( $next >= $total ),
], JSON_UNESCAPED_UNICODE );
