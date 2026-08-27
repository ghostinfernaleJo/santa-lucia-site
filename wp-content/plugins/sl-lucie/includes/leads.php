<?php
/**
 * Contacts / Prospects collectes par Lucie pendant les chats.
 * CPT prive `sl_lucie_lead` (nom, telephone, quartier, anniversaire), liste admin sous le
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

/* ── Fiche détaillée dans le back-office ── */
add_action( 'add_meta_boxes', function () {
    add_meta_box(
        'sl_lucie_lead_details',
        'Informations collectées par Lucie',
        'sl_lucie_lead_details_box',
        'sl_lucie_lead',
        'normal',
        'high'
    );
} );

function sl_lucie_lead_details_box( $post ) {
    $fields = [
        'Nom / prénom'       => get_post_meta( $post->ID, '_sll_nom', true ),
        'Téléphone / WhatsApp' => get_post_meta( $post->ID, '_sll_tel', true ),
        'Ville / quartier'   => get_post_meta( $post->ID, '_sll_quartier', true ),
        'Date d’anniversaire' => get_post_meta( $post->ID, '_sll_anniversaire', true ),
        'Session de conversation' => get_post_meta( $post->ID, '_sll_session', true ),
    ];
    echo '<table class="widefat striped" style="border:0;box-shadow:none;">';
    foreach ( $fields as $label => $value ) {
        echo '<tr><td style="width:240px;"><strong>' . esc_html( $label ) . '</strong></td><td>';
        if ( $label === 'Date d’anniversaire' && $value ) {
            echo esc_html( mysql2date( 'd/m/Y', $value ) );
        } elseif ( $label === 'Session de conversation' && $value ) {
            echo '<code>' . esc_html( $value ) . '</code>';
        } else {
            echo $value !== '' ? esc_html( $value ) : '<span style="color:#777;">Non communiqué</span>';
        }
        echo '</td></tr>';
    }
    echo '</table>';
    echo '<p style="margin-bottom:0;color:#646970;">Ces informations ont été communiquées volontairement dans le chat. Elles doivent être utilisées uniquement pour le suivi client et le programme de fidélité.</p>';
}

/**
 * Enregistre (ou met a jour) un contact. Dedoublonne par session de chat.
 * Retourne l'ID du post, ou false.
 */
function sl_lucie_save_lead( $nom, $tel, $quartier, $session = '', $anniversaire = '' ) {
    $nom      = sanitize_text_field( (string) $nom );
    $tel      = sanitize_text_field( (string) $tel );
    // Compare les formats courants comme le même numéro (+237 6xx… / 2376xx…
    // / 6xx…). Le numéro est proprement stocké en chiffres pour éviter les
    // doublons liés uniquement aux espaces, au + ou aux tirets.
    $tel = preg_replace( '/\D+/', '', $tel );
    if ( strlen( $tel ) === 9 && strpos( $tel, '6' ) === 0 ) $tel = '237' . $tel;
    $quartier = sanitize_text_field( (string) $quartier );
    $session  = sanitize_text_field( (string) $session );
    $anniversaire = sanitize_text_field( (string) $anniversaire );
    if ( preg_match( '/^(\d{2})[\/-](\d{2})[\/-](\d{4})$/', $anniversaire, $parts ) ) {
        $anniversaire = $parts[3] . '-' . $parts[2] . '-' . $parts[1];
    }
    $date = DateTime::createFromFormat( '!Y-m-d', $anniversaire );
    if ( ! $date || $date->format( 'Y-m-d' ) !== $anniversaire || $date > new DateTime( 'today' ) ) {
        $anniversaire = '';
    }

    if ( $nom === '' && $tel === '' && $quartier === '' && $anniversaire === '' ) return false;

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

    // Une même personne peut revenir avec une nouvelle session. Le numéro
    // exact est l'identifiant de déduplication le plus fiable disponible ici.
    // On réutilise donc sa fiche au lieu d'en créer une deuxième.
    if ( $tel !== '' ) {
        $hit = get_posts( [
            'post_type'   => 'sl_lucie_lead',
            'post_status' => 'any',
            'numberposts' => 1,
            'fields'      => 'ids',
            'meta_key'    => '_sll_tel',
            'meta_value'  => $tel,
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
    if ( $anniversaire !== '' ) update_post_meta( $id, '_sll_anniversaire', $anniversaire );
    if ( $session !== '' ) {
        update_post_meta( $id, '_sll_session', $session );
        $sessions = get_post_meta( $id, '_sll_sessions', true );
        $sessions = is_array( $sessions ) ? $sessions : [];
        if ( ! in_array( $session, $sessions, true ) ) $sessions[] = $session;
        update_post_meta( $id, '_sll_sessions', array_values( array_slice( $sessions, -50 ) ) );
    }
    return $id;
}

/** Retourne les coordonnees deja enregistrees pour une session, ou null. */
function sl_lucie_lead_for_session( $session ) {
    $session = sanitize_text_field( (string) $session );
    if ( $session === '' ) return null;
    $hit = get_posts( [
        'post_type' => 'sl_lucie_lead', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids',
        'meta_query' => [
            'relation' => 'OR',
            [ 'key' => '_sll_session', 'value' => $session, 'compare' => '=' ],
            [ 'key' => '_sll_sessions', 'value' => $session, 'compare' => 'LIKE' ],
        ],
    ] );
    if ( empty( $hit ) ) return null;
    $id = $hit[0];
    return [
        'nom'      => (string) get_post_meta( $id, '_sll_nom', true ),
        'tel'      => (string) get_post_meta( $id, '_sll_tel', true ),
        'quartier' => (string) get_post_meta( $id, '_sll_quartier', true ),
        'anniversaire' => (string) get_post_meta( $id, '_sll_anniversaire', true ),
    ];
}

/* ── Colonnes de la liste admin ── */
add_filter( 'manage_sl_lucie_lead_posts_columns', function ( $cols ) {
    return [
        'cb'            => $cols['cb'] ?? '',
        'title'         => 'Nom',
        'sll_tel'       => 'Telephone',
        'sll_quartier'  => 'Quartier / Ville',
        'sll_anniversaire' => 'Anniversaire',
        'sll_session'   => 'Session',
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
    } elseif ( $col === 'sll_anniversaire' ) {
        $date = get_post_meta( $id, '_sll_anniversaire', true );
        echo $date ? esc_html( mysql2date( 'd/m/Y', $date ) ) : '—';
    } elseif ( $col === 'sll_session' ) {
        $session = get_post_meta( $id, '_sll_session', true );
        echo $session ? '<code title="' . esc_attr( $session ) . '">' . esc_html( substr( $session, 0, 12 ) ) . '…</code>' : '—';
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
    fputcsv( $out, [ 'Nom', 'Telephone', 'Quartier/Ville', 'Anniversaire', 'Date' ] );
    foreach ( $leads as $l ) {
        fputcsv( $out, [
            get_post_meta( $l->ID, '_sll_nom', true ),
            get_post_meta( $l->ID, '_sll_tel', true ),
            get_post_meta( $l->ID, '_sll_quartier', true ),
            get_post_meta( $l->ID, '_sll_anniversaire', true ),
            get_the_date( 'Y-m-d H:i', $l ),
        ] );
    }
    fclose( $out );
    exit;
} );
