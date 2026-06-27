<?php
/**
 * Consultation des conversations de Lucie dans le back-office.
 * S'appuie sur la table de logs (stats.php) : chaque ligne = 1 echange
 * (message visiteur + reponse de Lucie), regroupes par session.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', function () {
    if ( ! function_exists( 'sl_lucie_can_manage' ) || ! sl_lucie_can_manage() ) return;
    add_submenu_page( 'sl-lucie', 'Conversations', 'Conversations', 'edit_others_posts', 'sl-lucie-convos', 'sl_lucie_convos_page' );
}, 15 );

/** Nom du contact (prospect) lie a une session, s'il existe. */
function sl_lucie_session_contact( $session ) {
    if ( $session === '' ) return null;
    $hit = get_posts( [
        'post_type' => 'sl_lucie_lead', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids',
        'meta_key' => '_sll_session', 'meta_value' => $session,
    ] );
    if ( empty( $hit ) ) return null;
    $id = $hit[0];
    return [
        'nom'      => get_post_meta( $id, '_sll_nom', true ),
        'tel'      => get_post_meta( $id, '_sll_tel', true ),
        'quartier' => get_post_meta( $id, '_sll_quartier', true ),
    ];
}

function sl_lucie_convos_page() {
    if ( ! sl_lucie_can_manage() ) wp_die( 'Acces refuse.' );
    global $wpdb;
    $table   = sl_lucie_log_table();
    $session = isset( $_GET['session'] ) ? sanitize_text_field( wp_unslash( $_GET['session'] ) ) : '';

    echo '<div class="wrap"><h1><span class="dashicons dashicons-format-chat"></span> Conversations de Lucie</h1>';

    if ( $session !== '' ) {
        // ── Vue d'une conversation ──
        $contact = sl_lucie_session_contact( $session );
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT message, reply, created FROM {$table} WHERE session_id = %s ORDER BY created ASC LIMIT 500", $session ), ARRAY_A );

        echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=sl-lucie-convos' ) ) . '">&larr; Toutes les conversations</a></p>';

        echo '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:16px;max-width:760px;">';
        if ( $contact ) {
            echo '<p style="margin:0 0 12px;font-size:14px;"><strong>Contact :</strong> '
                . esc_html( $contact['nom'] ?: '—' )
                . ( $contact['tel'] ? ' &middot; 📞 ' . esc_html( $contact['tel'] ) : '' )
                . ( $contact['quartier'] ? ' &middot; 📍 ' . esc_html( $contact['quartier'] ) : '' )
                . '</p><hr>';
        } else {
            echo '<p style="margin:0 0 12px;color:#777;">Visiteur anonyme (coordonnees non fournies). Session : <code>' . esc_html( $session ) . '</code></p><hr>';
        }

        if ( empty( $rows ) ) {
            echo '<p>Aucun message pour cette session.</p>';
        } else {
            foreach ( $rows as $r ) {
                $when = esc_html( mysql2date( 'd/m/Y H:i', $r['created'] ) );
                // Message visiteur
                echo '<div style="margin:14px 0 4px;text-align:right;">'
                   . '<span style="display:inline-block;background:#e85499;color:#fff;padding:8px 12px;border-radius:14px 14px 4px 14px;max-width:80%;text-align:left;">'
                   . nl2br( esc_html( $r['message'] ) ) . '</span>'
                   . '<div style="font-size:11px;color:#aaa;margin-top:2px;">' . $when . '</div></div>';
                // Reponse Lucie
                if ( trim( (string) $r['reply'] ) !== '' ) {
                    echo '<div style="margin:4px 0 14px;text-align:left;">'
                       . '<span style="display:inline-block;background:#f1f2f4;color:#1f2430;padding:8px 12px;border-radius:14px 14px 14px 4px;max-width:85%;">'
                       . nl2br( esc_html( mb_substr( $r['reply'], 0, 6000 ) ) ) . '</span></div>';
                }
            }
        }
        echo '</div></div>';
        return;
    }

    // ── Liste des conversations ──
    $exists = ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) === $table );
    $sessions = $exists ? $wpdb->get_results(
        "SELECT session_id, MAX(created) last_at, COUNT(*) n
         FROM {$table} WHERE session_id <> '' GROUP BY session_id ORDER BY last_at DESC LIMIT 150", ARRAY_A ) : [];

    if ( empty( $sessions ) ) {
        echo '<div class="notice notice-info"><p>Aucune conversation enregistree pour le moment.</p></div></div>';
        return;
    }

    echo '<p style="color:#666;">Les conversations sont conservees 1 an. Cliquez pour lire l\'echange complet.</p>';
    echo '<table class="widefat striped"><thead><tr><th>Visiteur</th><th>Premiere question</th><th style="width:70px;">Messages</th><th style="width:140px;">Derniere activite</th><th style="width:90px;"></th></tr></thead><tbody>';
    foreach ( $sessions as $s ) {
        $sid    = $s['session_id'];
        $first  = $wpdb->get_var( $wpdb->prepare( "SELECT message FROM {$table} WHERE session_id=%s ORDER BY created ASC LIMIT 1", $sid ) );
        $contact = sl_lucie_session_contact( $sid );
        $who = $contact && $contact['nom'] ? esc_html( $contact['nom'] ) . ( $contact['tel'] ? ' <span style="color:#888;">(' . esc_html( $contact['tel'] ) . ')</span>' : '' ) : '<span style="color:#999;">Anonyme</span>';
        $url = admin_url( 'admin.php?page=sl-lucie-convos&session=' . rawurlencode( $sid ) );
        echo '<tr>'
           . '<td>' . $who . '</td>'
           . '<td>' . esc_html( mb_substr( (string) $first, 0, 90 ) ) . '</td>'
           . '<td>' . (int) $s['n'] . '</td>'
           . '<td>' . esc_html( mysql2date( 'd/m/Y H:i', $s['last_at'] ) ) . '</td>'
           . '<td><a class="button button-small" href="' . esc_url( $url ) . '">Voir</a></td>'
           . '</tr>';
    }
    echo '</tbody></table></div>';
}

/** Lien "Conversation" dans la liste des contacts/prospects. */
add_filter( 'post_row_actions', function ( $actions, $post ) {
    if ( $post->post_type !== 'sl_lucie_lead' ) return $actions;
    $session = get_post_meta( $post->ID, '_sll_session', true );
    if ( $session ) {
        $url = admin_url( 'admin.php?page=sl-lucie-convos&session=' . rawurlencode( $session ) );
        $actions['sl_convo'] = '<a href="' . esc_url( $url ) . '">💬 Voir la conversation</a>';
    }
    return $actions;
}, 10, 2 );
