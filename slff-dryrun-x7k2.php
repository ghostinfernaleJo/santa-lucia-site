<?php
/**
 * SIMULATION (lecture seule) — fusion des sl_repas dupliqués vers le modèle partagé.
 * N'écrit RIEN en base. Script temporaire, retiré après analyse.
 */
if ( ! isset( $_GET['k'] ) || $_GET['k'] !== 'sl2026dryrunX7K2' ) {
    http_response_code( 404 );
    exit;
}
require_once __DIR__ . '/wp-load.php';
if ( ! defined( 'ABSPATH' ) ) exit;
nocache_headers();
header( 'Content-Type: application/json; charset=utf-8' );

global $wpdb;
@set_time_limit( 120 );

/* 1. Tous les repas publiés (id, titre, contenu hashé, date) */
$posts = $wpdb->get_results(
    "SELECT ID, post_title, post_date, MD5(CONCAT(post_content,'|',post_excerpt)) AS chash
     FROM {$wpdb->posts}
     WHERE post_type = 'sl_repas' AND post_status = 'publish'"
);
if ( ! $posts ) { echo json_encode( [ 'error' => 'aucun repas publie' ] ); exit; }

$ids = wp_list_pluck( $posts, 'ID' );

/* 2. Toutes les métas de ces posts en 1 requête (hors verrous d'édition) */
$meta = [];
foreach ( array_chunk( $ids, 1000 ) as $chunk ) {
    $in   = implode( ',', array_map( 'intval', $chunk ) );
    $rows = $wpdb->get_results(
        "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta}
         WHERE post_id IN ($in) AND meta_key NOT LIKE '\_edit%'"
    );
    foreach ( $rows as $r ) {
        $meta[ $r->post_id ][ $r->meta_key ][] = $r->meta_value;
    }
}

/* 3. Catégorie(s) par post en 1 requête */
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

/* 4. Groupement par titre normalisé */
$groups = [];
foreach ( $posts as $p ) {
    $key = mb_strtolower( trim( preg_replace( '/\s+/u', ' ', $p->post_title ) ), 'UTF-8' );
    $groups[ $key ][] = $p;
}

/* Signature d'un post pour comparer les groupes : tout sauf l'agence */
function slff_sig( $p, $meta, $cats, $key_filter = null ) {
    $m = isset( $meta[ $p->ID ] ) ? $meta[ $p->ID ] : [];
    unset( $m['_sl_ff_agence'] );
    if ( $key_filter !== null ) {
        $m = array_intersect_key( $m, array_flip( (array) $key_filter ) );
    }
    foreach ( $m as &$v ) { sort( $v ); }
    unset( $v );
    ksort( $m );
    $c = isset( $cats[ $p->ID ] ) ? $cats[ $p->ID ] : [];
    sort( $c );
    return md5( serialize( $m ) . '|' . serialize( $c ) . ( $key_filter === null ? '|' . $p->chash : '' ) );
}

$report = [
    'total_posts'        => count( $posts ),
    'titres_distincts'   => count( $groups ),
    'deja_uniques'       => 0,   // 1 seul post pour ce titre
    'fusion_propre'      => 0,   // doublons strictement identiques (hors agence)
    'fusion_posts_draft' => 0,   // posts qui passeraient en brouillon
    'conflits'           => 0,   // groupes avec divergences
    'conflits_par_meta'  => [],  // quelle méta diverge, combien de groupes
    'exemples_conflits'  => [],
    'exemples_fusion'    => [],
];

foreach ( $groups as $key => $list ) {
    if ( count( $list ) === 1 ) { $report['deja_uniques']++; continue; }

    // signatures complètes (toutes métas + contenu + catégorie)
    $sigs = [];
    foreach ( $list as $p ) { $sigs[ slff_sig( $p, $meta, $cats ) ] = 1; }

    if ( count( $sigs ) === 1 ) {
        $report['fusion_propre']++;
        $report['fusion_posts_draft'] += count( $list ) - 1;
        if ( count( $report['exemples_fusion'] ) < 8 ) {
            $report['exemples_fusion'][] = $list[0]->post_title . ' (' . count( $list ) . ' posts)';
        }
        continue;
    }

    // Conflit : identifier QUELLES métas divergent
    $report['conflits']++;
    $all_keys = [];
    foreach ( $list as $p ) {
        if ( isset( $meta[ $p->ID ] ) ) $all_keys = array_merge( $all_keys, array_keys( $meta[ $p->ID ] ) );
    }
    $all_keys   = array_diff( array_unique( $all_keys ), [ '_sl_ff_agence' ] );
    $divergents = [];
    foreach ( $all_keys as $mk ) {
        $vals = [];
        foreach ( $list as $p ) {
            $v = isset( $meta[ $p->ID ][ $mk ] ) ? $meta[ $p->ID ][ $mk ] : [];
            sort( $v );
            $vals[ md5( serialize( $v ) ) ] = 1;
        }
        if ( count( $vals ) > 1 ) {
            $divergents[] = $mk;
            $report['conflits_par_meta'][ $mk ] = ( $report['conflits_par_meta'][ $mk ] ?? 0 ) + 1;
        }
    }
    // contenu / catégorie divergents ?
    $ch = []; $cc = [];
    foreach ( $list as $p ) {
        $ch[ $p->chash ] = 1;
        $c = isset( $cats[ $p->ID ] ) ? $cats[ $p->ID ] : [];
        sort( $c );
        $cc[ md5( serialize( $c ) ) ] = 1;
    }
    if ( count( $ch ) > 1 ) { $divergents[] = 'post_content'; $report['conflits_par_meta']['post_content'] = ( $report['conflits_par_meta']['post_content'] ?? 0 ) + 1; }
    if ( count( $cc ) > 1 ) { $divergents[] = 'categorie';    $report['conflits_par_meta']['categorie']    = ( $report['conflits_par_meta']['categorie'] ?? 0 ) + 1; }

    if ( count( $report['exemples_conflits'] ) < 12 ) {
        $report['exemples_conflits'][] = [
            'plat'      => $list[0]->post_title,
            'posts'     => count( $list ),
            'divergent' => array_values( array_unique( $divergents ) ),
        ];
    }
}

arsort( $report['conflits_par_meta'] );
echo json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
