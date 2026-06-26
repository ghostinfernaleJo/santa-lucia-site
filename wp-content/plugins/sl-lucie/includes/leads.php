<?php
/**
 * Contacts / Prospects collectes par Lucie pendant les chats.
 * CPT prive `sl_lucie_lead` (nom, telephone, quartier), liste admin sous le
 * menu Lucie + export CSV. Rempli par l'outil enregistrer_contact.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ── CPT ── */
add_action( 'init', function () {
    register_post_type( 'sl_lucie_lead', [
        'labels' => [
            'name'          => 'Contacts (chat)',
            'singular_name' => 'Contact',
            'menu_name'     => 'Contacts (chat)',
            'all_items'     => 'Contacts collectes',
        ],
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => 'sl-lucie',
        'show_in_rest'  => false,
        'supports'      => [ 'title' ],
        'map_meta_cap'  => true,
        'capability_type' => 'post',
        // Pas de creation manuelle : ces fiches viennent du chat.
        'capabilities'  => [ 'create_posts' => 'do_not_allow' ],
    ] );
} );

/**
 * Enregistre (ou met a jour) un contact. Dedoublonne par session de chat.
 * Retourne l'ID du post, ou false.
 */
function sl_lucie_save_lead( $nom, $tel, $quartier, $session = '' ) {
    $nom      = sanitize_text_field( (string) $nom );
    $tel      = sanitize_text_field( (string) $tel );
    $quartier = sanitize_text_field( (string) $quartier );
    $session  = sanitize_text_field( (string) $session );

    if ( $nom === '' && $tel === '' && $quartier === '' ) return false;

    // Une fiche par session de chat (mise a jour si elle existe deja).
    $existing = 0;
    if ( $session !== '' ) {
        $hit = get_posts( [
            'post_type'   => 'sl_lucie_lead',
            'post_status' => 'any',
            'numberposts' => 1,
            'fields'      => 'ids',
            'meta_key'    => '_sll_session',
            'meta_value'  => $session,
        ] );
        if ( ! empty( $hit ) ) $existing = (int) $hit[0];
    }

    $titre = $nom !== '' ? $nom : ( $tel !== '' ? $tel : 'Visiteur' );

    if ( $existing ) {
        wp_update_post( [ 'ID' => $existing, 'post_title' => $titre ] );
        $id = $existing;
    } else {
        $id = wp_insert_post( [
            'post_type'   => 'sl_lucie_lead',
            'post_status' => 'publish',
            'post_title'  => $titre,
        ] );
    }
    if ( ! $id || is_wp_error( $id ) ) return false;

    if ( $nom !== '' )      update_post_meta( $id, '_sll_nom', $nom );
    if ( $tel !== '' )      update_post_meta( $id, '_sll_tel', $tel );
    if ( $quartier !== '' ) update_post_meta( $id, '_sll_quartier', $quartier );
    if ( $session !== '' )  update_post_meta( $id, '_sll_session', $session );
    return $id;
}

/* ── Colonnes de la liste admin ── */
add_filter( 'manage_sl_lucie_lead_posts_columns', function ( $cols ) {
    return [
        'cb'            => $cols['cb'] ?? '',
        'title'         => 'Nom',
        'sll_tel'       => 'Telephone',
        'sll_quartier'  => 'Quartier / Ville',
        'date'          => 'Recu le',
    ];
} );
add_action( 'manage_sl_lucie_lead_posts_custom_column', function ( $col, $id ) {
    if ( $col === 'sll_tel' ) {
        $tel = get_post_meta( $id, '_sll_tel', true );
        echo $tel ? esc_html( $tel ) : '—';
    } elseif ( $col === 'sll_quartier' ) {
        $q = get_post_meta( $id, '_sll_quartier', true );
        echo $q ? esc_html( $q ) : '—';
    }
}, 10, 2 );

/* ── Bouton d'export CSV au-dessus de la liste ── */
add_action( 'manage_posts_extra_tablenav', function ( $which ) {
    global $typenow;
    if ( $typenow !== 'sl_lucie_lead' || $which !== 'top' ) return;
    $url = wp_nonce_url( admin_url( 'edit.php?post_type=sl_lucie_lead&sl_lucie_export=1' ), 'sl_lucie_export' );
    echo '<a href="' . esc_url( $url ) . '" class="button" style="margin:0 8px;">⬇ Exporter en CSV</a>';
} );

/* ── Traitement de l'export CSV ── */
add_action( 'admin_init', function () {
    if ( empty( $_GET['sl_lucie_export'] ) ) return;
    if ( ! current_user_can( 'edit_others_posts' ) ) return;
    check_admin_referer( 'sl_lucie_export' );

    $leads = get_posts( [
        'post_type'   => 'sl_lucie_lead',
        'post_status' => 'any',
        'numberposts' => -1,
        'orderby'     => 'date',
        'order'       => 'DESC',
    ] );

    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=contacts-lucie-' . date( 'Y-m-d' ) . '.csv' );
    $out = fopen( 'php://output', 'w' );
    fprintf( $out, "\xEF\xBB\xBF" ); // BOM UTF-8 pour Excel
    fputcsv( $out, [ 'Nom', 'Telephone', 'Quartier/Ville', 'Date' ] );
    foreach ( $leads as $l ) {
        fputcsv( $out, [
            get_post_meta( $l->ID, '_sll_nom', true ),
            get_post_meta( $l->ID, '_sll_tel', true ),
            get_post_meta( $l->ID, '_sll_quartier', true ),
            get_the_date( 'Y-m-d H:i', $l ),
        ] );
    }
    fclose( $out );
    exit;
} );
