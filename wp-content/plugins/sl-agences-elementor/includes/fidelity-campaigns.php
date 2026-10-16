<?php
/**
 * Campaign goals and public performance board for the fidelity programme.
 */

defined( 'ABSPATH' ) || exit;

const SLFD_CAMPAIGN_POST_TYPE = 'sl_fidelity_campaign';

add_action( 'init', 'slfd_register_campaign_type', 21 );
function slfd_register_campaign_type() {
    register_post_type( SLFD_CAMPAIGN_POST_TYPE, [
        'labels'             => [ 'name' => 'Campagnes fidélité', 'singular_name' => 'Campagne fidélité' ],
        'public'             => false,
        'publicly_queryable' => false,
        'show_ui'            => false,
        'show_in_menu'       => false,
        'supports'           => [ 'title' ],
    ] );
}

function slfd_campaigns_url( $campaign_id = 0, $new = false ) {
    $id = slfd_page_id( 'campaigns' );
    $url = $id ? get_permalink( $id ) : home_url( '/campagnes-fidelite/' );
    if ( $campaign_id ) {
        $url = add_query_arg( 'campaign', absint( $campaign_id ), $url );
    }
    return $new ? add_query_arg( 'new', '1', $url ) : $url;
}

function slfd_campaign_public_url( $campaign_id = 0 ) {
    $id = slfd_page_id( 'campaign_public' );
    $url = $id ? get_permalink( $id ) : home_url( '/suivi-campagne-fidelite/' );
    return $campaign_id ? add_query_arg( 'campaign', absint( $campaign_id ), $url ) : $url;
}

function slfd_is_campaign_public_page() {
    $page_id = slfd_page_id( 'campaign_public' );
    return $page_id && $page_id === (int) get_queried_object_id();
}

add_filter( 'wp_robots', 'slfd_campaign_public_robots' );
function slfd_campaign_public_robots( $robots ) {
    if ( slfd_is_campaign_public_page() ) {
        $robots['noindex'] = true;
        $robots['nofollow'] = true;
    }
    return $robots;
}

add_filter( 'template_include', 'slfd_campaign_public_template', 100 );
function slfd_campaign_public_template( $template ) {
    if ( slfd_is_campaign_public_page() ) {
        $campaign_template = SL_AGENCES_PATH . 'templates/fidelity-campaign-public.php';
        if ( file_exists( $campaign_template ) ) {
            return $campaign_template;
        }
    }
    return $template;
}

function slfd_active_campaign_id() {
    $id = (int) get_option( 'slfd_active_campaign_id' );
    return $id && SLFD_CAMPAIGN_POST_TYPE === get_post_type( $id ) ? $id : 0;
}

function slfd_campaign_data( $campaign_id = 0 ) {
    $campaign_id = $campaign_id ?: slfd_active_campaign_id();
    if ( ! $campaign_id || SLFD_CAMPAIGN_POST_TYPE !== get_post_type( $campaign_id ) ) {
        return null;
    }

    $start = (string) get_post_meta( $campaign_id, '_slfd_campaign_start', true );
    $end = (string) get_post_meta( $campaign_id, '_slfd_campaign_end', true );
    return [
        'id'              => $campaign_id,
        'name'            => get_the_title( $campaign_id ) ?: 'Campagne en cours',
        'start'           => $start,
        'end'             => $end,
        'clients_target'  => max( 0, (int) get_post_meta( $campaign_id, '_slfd_clients_target', true ) ),
        'playstore_target'=> max( 0, (int) get_post_meta( $campaign_id, '_slfd_playstore_target', true ) ),
        'cards_target'    => max( 0, (int) get_post_meta( $campaign_id, '_slfd_cards_target', true ) ),
        'agency_targets'  => (array) get_post_meta( $campaign_id, '_slfd_agency_targets', true ),
        'is_public'       => '1' === get_post_meta( $campaign_id, '_slfd_is_public', true ),
        'show_agents'     => '1' === get_post_meta( $campaign_id, '_slfd_show_agents', true ),
        'email_alerts'    => '1' === get_post_meta( $campaign_id, '_slfd_email_alerts', true ),
        'agent_targets'   => (array) get_post_meta( $campaign_id, '_slfd_agent_targets', true ),
        'state'           => (string) get_post_meta( $campaign_id, '_slfd_campaign_state', true ) ?: 'active',
    ];
}

function slfd_campaign_dates_are_valid( $start, $end ) {
    return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start )
        && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end )
        && $start <= $end;
}

function slfd_campaign_days( $campaign ) {
    $total = (int) floor( ( strtotime( $campaign['end'] ) - strtotime( $campaign['start'] ) ) / DAY_IN_SECONDS ) + 1;
    $today = current_time( 'Y-m-d' );
    $elapsed = $today < $campaign['start'] ? 0 : min( $total, (int) floor( ( strtotime( min( $today, $campaign['end'] ) ) - strtotime( $campaign['start'] ) ) / DAY_IN_SECONDS ) + 1 );
    // The current day is still available for registrations and must count in the required daily pace.
    $remaining = $today > $campaign['end'] ? 0 : ( $today < $campaign['start'] ? $total : max( 1, $total - $elapsed + 1 ) );
    return [ 'total' => max( 1, $total ), 'elapsed' => $elapsed, 'remaining' => $remaining ];
}

function slfd_campaign_reports( $campaign, $author = 0 ) {
    $query = [
        'post_type'      => SLFD_POST_TYPE,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_key'       => '_slfd_date',
        'meta_value'     => [ $campaign['start'], $campaign['end'] ],
        'meta_compare'   => 'BETWEEN',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
    ];
    if ( $author ) {
        $query['author'] = $author;
    }
    return array_values( array_filter( get_posts( $query ), static function ( $report ) use ( $campaign ) {
        $linked_campaign = (int) get_post_meta( $report->ID, '_slfd_campaign_id', true );
        return ! $linked_campaign || $linked_campaign === (int) $campaign['id'];
    } ) );
}

function slfd_campaign_for_date( $date ) {
    $campaigns = get_posts( [ 'post_type' => SLFD_CAMPAIGN_POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'DESC' ] );
    $active_id = slfd_active_campaign_id();
    usort( $campaigns, static function ( $a, $b ) use ( $active_id ) { return (int) ( $b->ID === $active_id ) <=> (int) ( $a->ID === $active_id ); } );
    foreach ( $campaigns as $campaign_post ) {
        $campaign = slfd_campaign_data( $campaign_post->ID );
        if ( $campaign && 'archived' !== $campaign['state'] && $date >= $campaign['start'] && $date <= $campaign['end'] ) {
            return $campaign;
        }
    }
    return null;
}

function slfd_campaign_metric( $actual, $target, $days ) {
    $percent = $target ? min( 100, round( $actual / $target * 100, 1 ) ) : 0;
    $expected = $target ? (int) ceil( $target * $days['elapsed'] / $days['total'] ) : 0;
    $daily = $target && $days['remaining'] ? (int) ceil( max( 0, $target - $actual ) / $days['remaining'] ) : 0;
    return [
        'actual'   => $actual,
        'target'   => $target,
        'percent'  => $percent,
        'expected' => $expected,
        'gap'      => $actual - $expected,
        'daily'    => $daily,
    ];
}

function slfd_campaign_stats( $campaign = null ) {
    $campaign = $campaign ?: slfd_campaign_data();
    if ( ! $campaign || ! slfd_campaign_dates_are_valid( $campaign['start'], $campaign['end'] ) ) {
        return null;
    }

    $days = slfd_campaign_days( $campaign );
    $reports = slfd_campaign_reports( $campaign );
    $totals = [ 'clients' => 0, 'playstore' => 0, 'cards' => 0 ];
    $by_agency = [];
    $by_agent = [];
    $daily = [];
    foreach ( $reports as $report ) {
        $agency = (string) get_post_meta( $report->ID, '_slfd_agency', true );
        $clients = slfd_meta_int( $report->ID, '_slfd_enrollments' );
        $playstore = slfd_meta_int( $report->ID, '_slfd_playstore' );
        $stored_cards = get_post_meta( $report->ID, '_slfd_cards_distributed', true );
        $cards = '' === $stored_cards ? $clients : max( 0, (int) $stored_cards );
        $totals['clients'] += $clients;
        $totals['playstore'] += $playstore;
        $totals['cards'] += $cards;
        $report_date = (string) get_post_meta( $report->ID, '_slfd_date', true );
        $daily[ $report_date ] = (int) ( $daily[ $report_date ] ?? 0 ) + $clients;
        if ( ! isset( $by_agency[ $agency ] ) ) {
            $by_agency[ $agency ] = [ 'clients' => 0, 'playstore' => 0, 'cards' => 0 ];
        }
        $by_agency[ $agency ]['clients'] += $clients;
        $by_agency[ $agency ]['playstore'] += $playstore;
        $by_agency[ $agency ]['cards'] += $cards;
        $author_id = (int) $report->post_author;
        if ( ! isset( $by_agent[ $author_id ] ) ) {
            $by_agent[ $author_id ] = [ 'clients' => 0, 'agency' => $agency, 'daily' => [] ];
        }
        $by_agent[ $author_id ]['clients'] += $clients;
        $by_agent[ $author_id ]['daily'][ $report_date ] = (int) ( $by_agent[ $author_id ]['daily'][ $report_date ] ?? 0 ) + $clients;
    }

    $terms = get_terms( [ 'taxonomy' => 'sl_agence_promo', 'hide_empty' => false, 'orderby' => 'name' ] );
    $agencies = [];
    foreach ( is_wp_error( $terms ) ? [] : $terms as $term ) {
        $actual = $by_agency[ $term->slug ] ?? [ 'clients' => 0, 'playstore' => 0, 'cards' => 0 ];
        $target = max( 0, (int) ( $campaign['agency_targets'][ $term->slug ] ?? 0 ) );
        $agents = get_users( [ 'role' => 'sl_agent_fidelite', 'meta_key' => 'sl_agence_assignee', 'meta_value' => $term->name, 'fields' => 'ID' ] );
        $agencies[] = [
            'term'    => $term,
            'clients' => slfd_campaign_metric( $actual['clients'], $target, $days ),
            'playstore' => $actual['playstore'],
            'cards' => $actual['cards'],
            'agents' => count( $agents ),
        ];
    }
    usort( $agencies, static function ( $a, $b ) { return $b['clients']['actual'] <=> $a['clients']['actual']; } );

    $agents = [];
    foreach ( get_users( [ 'role' => 'sl_agent_fidelite', 'orderby' => 'display_name', 'order' => 'ASC' ] ) as $agent ) {
        $agency_name = (string) get_user_meta( $agent->ID, 'sl_agence_assignee', true );
        $agency_slug = $agency_name ? sanitize_title( $agency_name ) : '';
        $agency_row = null;
        foreach ( $agencies as $row ) {
            if ( $row['term']->slug === $agency_slug ) { $agency_row = $row; break; }
        }
        $agency_target = $agency_row ? $agency_row['clients']['target'] : 0;
        $agent_target = max( 0, (int) ( $campaign['agent_targets'][ $agent->ID ] ?? ( $agency_row && $agency_row['agents'] ? ceil( $agency_target / $agency_row['agents'] ) : 0 ) ) );
        $actual = (int) ( $by_agent[ $agent->ID ]['clients'] ?? 0 );
        $agents[] = [
            'user' => $agent,
            'agency' => $agency_name,
            'clients' => slfd_campaign_metric( $actual, $agent_target, $days ),
            'trend' => (array) ( $by_agent[ $agent->ID ]['daily'] ?? [] ),
        ];
    }
    usort( $agents, static function ( $a, $b ) { return $b['clients']['actual'] <=> $a['clients']['actual']; } );

    return [
        'campaign' => $campaign,
        'days' => $days,
        'clients' => slfd_campaign_metric( $totals['clients'], $campaign['clients_target'], $days ),
        'playstore' => slfd_campaign_metric( $totals['playstore'], $campaign['playstore_target'], $days ),
        'cards' => slfd_campaign_metric( $totals['cards'], $campaign['cards_target'], $days ),
        'agencies' => $agencies,
        'agents' => $agents,
        'daily' => $daily,
    ];
}

add_action( 'admin_post_slfd_save_campaign', 'slfd_save_campaign' );
function slfd_save_campaign() {
    if ( ! slfd_can_manage_agents() ) { wp_die( 'Accès refusé.', 403 ); }
    check_admin_referer( 'slfd_save_campaign' );

    $campaign_id = absint( $_POST['campaign_id'] ?? 0 );
    $name = sanitize_text_field( wp_unslash( $_POST['slfd_campaign_name'] ?? '' ) ) ?: 'Campagne en cours';
    $start = sanitize_text_field( wp_unslash( $_POST['slfd_campaign_start'] ?? '' ) );
    $end = sanitize_text_field( wp_unslash( $_POST['slfd_campaign_end'] ?? '' ) );
    $clients = absint( $_POST['slfd_clients_target'] ?? 0 );
    $playstore = absint( $_POST['slfd_playstore_target'] ?? 0 );
    $cards = absint( $_POST['slfd_cards_target'] ?? 0 );
    if ( ! slfd_campaign_dates_are_valid( $start, $end ) || ! $clients ) {
        slfd_flash( 'error', 'Indiquez une période valide et un objectif clients supérieur à zéro.' );
        wp_safe_redirect( slfd_campaigns_url() ); exit;
    }
    $campaign_id = $campaign_id && SLFD_CAMPAIGN_POST_TYPE === get_post_type( $campaign_id ) ? $campaign_id : wp_insert_post( [
        'post_type' => SLFD_CAMPAIGN_POST_TYPE, 'post_status' => 'publish', 'post_title' => $name, 'post_author' => get_current_user_id(),
    ], true );
    if ( is_wp_error( $campaign_id ) || ! $campaign_id ) {
        slfd_flash( 'error', 'La campagne n’a pas pu être enregistrée.' );
        wp_safe_redirect( slfd_campaigns_url() ); exit;
    }
    wp_update_post( [ 'ID' => $campaign_id, 'post_title' => $name ] );
    $targets = [];
    foreach ( (array) ( $_POST['slfd_agency_targets'] ?? [] ) as $slug => $target ) {
        $targets[ sanitize_title( $slug ) ] = absint( $target );
    }
    update_post_meta( $campaign_id, '_slfd_campaign_start', $start );
    update_post_meta( $campaign_id, '_slfd_campaign_end', $end );
    update_post_meta( $campaign_id, '_slfd_clients_target', $clients );
    update_post_meta( $campaign_id, '_slfd_playstore_target', $playstore );
    update_post_meta( $campaign_id, '_slfd_cards_target', $cards );
    update_post_meta( $campaign_id, '_slfd_agency_targets', $targets );
    update_post_meta( $campaign_id, '_slfd_is_public', empty( $_POST['slfd_is_public'] ) ? '0' : '1' );
    update_post_meta( $campaign_id, '_slfd_show_agents', empty( $_POST['slfd_show_agents'] ) ? '0' : '1' );
    update_post_meta( $campaign_id, '_slfd_email_alerts', empty( $_POST['slfd_email_alerts'] ) ? '0' : '1' );
    $agent_targets = [];
    foreach ( (array) ( $_POST['slfd_agent_targets'] ?? [] ) as $user_id => $target ) {
        $user_id = absint( $user_id );
        if ( $user_id && get_userdata( $user_id ) ) {
            $agent_targets[ $user_id ] = absint( $target );
        }
    }
    update_post_meta( $campaign_id, '_slfd_agent_targets', $agent_targets );
    update_post_meta( $campaign_id, '_slfd_campaign_state', 'active' );
    if ( ! empty( $_POST['slfd_is_active'] ) || ! slfd_active_campaign_id() ) {
        update_option( 'slfd_active_campaign_id', $campaign_id, false );
    }
    slfd_flash( 'success', 'La campagne et ses objectifs ont été enregistrés.' );
    wp_safe_redirect( slfd_campaigns_url() ); exit;
}

add_action( 'admin_post_slfd_campaign_action', 'slfd_campaign_action' );
function slfd_campaign_action() {
    if ( ! slfd_can_manage_agents() ) { wp_die( 'Accès refusé.', 403 ); }
    $campaign_id = absint( $_POST['campaign_id'] ?? 0 );
    check_admin_referer( 'slfd_campaign_action_' . $campaign_id );
    if ( SLFD_CAMPAIGN_POST_TYPE !== get_post_type( $campaign_id ) ) {
        slfd_flash( 'error', 'Cette campagne est introuvable.' );
    } elseif ( 'delete' === ( $_POST['campaign_action'] ?? '' ) ) {
        if ( $campaign_id === slfd_active_campaign_id() ) {
            delete_option( 'slfd_active_campaign_id' );
        }
        if ( wp_trash_post( $campaign_id ) ) {
            slfd_flash( 'success', 'La campagne a été supprimée. Les rapports et approvisionnements restent disponibles dans l’historique.' );
        } else {
            slfd_flash( 'error', 'La campagne n’a pas pu être supprimée.' );
        }
    } elseif ( 'activate' === ( $_POST['campaign_action'] ?? '' ) ) {
        update_post_meta( $campaign_id, '_slfd_campaign_state', 'active' );
        update_option( 'slfd_active_campaign_id', $campaign_id, false );
        slfd_flash( 'success', 'La campagne sélectionnée est désormais la campagne en cours.' );
    } elseif ( 'archive' === ( $_POST['campaign_action'] ?? '' ) ) {
        update_post_meta( $campaign_id, '_slfd_campaign_state', 'archived' );
        if ( $campaign_id === slfd_active_campaign_id() ) {
            delete_option( 'slfd_active_campaign_id' );
        }
        slfd_flash( 'success', 'La campagne a été archivée. Ses résultats restent consultables.' );
    }
    wp_safe_redirect( slfd_campaigns_url() ); exit;
}

add_action( 'slfd_daily_campaign_alerts', 'slfd_send_daily_campaign_alerts' );
add_action( 'init', 'slfd_schedule_daily_campaign_alerts', 35 );
function slfd_schedule_daily_campaign_alerts() {
    if ( ! wp_next_scheduled( 'slfd_daily_campaign_alerts' ) ) {
        wp_schedule_event( strtotime( 'tomorrow 08:00', current_time( 'timestamp' ) ), 'daily', 'slfd_daily_campaign_alerts' );
    }
}

function slfd_send_daily_campaign_alerts() {
    $campaign = slfd_campaign_data();
    $stats = slfd_campaign_stats( $campaign );
    if ( ! $campaign || ! $campaign['email_alerts'] || ! $stats || ! $stats['days']['remaining'] ) { return; }
    $today = current_time( 'Y-m-d' );
    foreach ( $stats['agents'] as $agent ) {
        if ( $agent['clients']['gap'] >= 0 || ! is_email( $agent['user']->user_email ) ) { continue; }
        $notice_key = '_slfd_campaign_alert_' . $campaign['id'];
        if ( $today === get_user_meta( $agent['user']->ID, $notice_key, true ) ) { continue; }
        $subject = 'Votre objectif Fidélité du jour';
        $message = sprintf(
            "Bonjour %s,\n\nVous avez enregistré %d client(s) sur un objectif de %d pour la campagne %s.\n\nPour revenir dans le rythme, visez %d enregistrement(s) par jour jusqu’au %s.\n\nConnectez-vous à votre espace Fidélité pour déclarer votre activité.\n\nComplexe Santa Lucia",
            $agent['user']->display_name ?: $agent['user']->user_login,
            $agent['clients']['actual'],
            $agent['clients']['target'],
            $campaign['name'],
            $agent['clients']['daily'],
            date_i18n( 'd/m/Y', strtotime( $campaign['end'] ) )
        );
        if ( wp_mail( $agent['user']->user_email, $subject, $message ) ) {
            update_user_meta( $agent['user']->ID, $notice_key, $today );
        }
    }
}

function slfd_render_campaign_management() {
    if ( ! slfd_can_manage_agents() ) { return slfd_render_access_denied(); }
    $selected_id = empty( $_GET['new'] ) ? absint( $_GET['campaign'] ?? slfd_active_campaign_id() ) : 0;
    $campaign = $selected_id ? slfd_campaign_data( $selected_id ) : null;
    $stats = slfd_campaign_stats( $campaign );
    $flash = slfd_flash( 'read' );
    $terms = get_terms( [ 'taxonomy' => 'sl_agence_promo', 'hide_empty' => false, 'orderby' => 'name' ] );
    $default_target = $campaign ? (int) ceil( $campaign['clients_target'] / max( 1, count( $terms ) ) ) : 0;
    $campaigns = get_posts( [ 'post_type' => SLFD_CAMPAIGN_POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'DESC' ] );
    $agents = get_users( [ 'role' => 'sl_agent_fidelite', 'orderby' => 'display_name', 'order' => 'ASC' ] );
    ob_start(); ?>
    <main class="slfd-shell"><header class="slfd-topbar"><a class="slfd-brand" href="<?php echo esc_url( slfd_dashboard_url() ); ?>"><span class="dashicons dashicons-awards"></span><span><small>Complexe</small>Santa Lucia</span></a><span class="slfd-internal">Pilotage de campagne</span><a class="slfd-top-link" href="<?php echo esc_url( slfd_dashboard_url() ); ?>">Tableau de bord</a></header>
    <section class="slfd-content slfd-report-page"><?php if ( $flash ) : ?><div class="slfd-flash slfd-flash--<?php echo esc_attr( $flash[0] ); ?>"><?php echo esc_html( $flash[1] ); ?></div><?php endif; ?>
        <div class="slfd-heading"><div><p class="slfd-eyebrow">Coordination</p><h1>Campagne Fidélité</h1><p>Définissez la période et les objectifs qui guident chaque agence et chaque agent.</p></div><div class="slfd-heading-actions"><a class="slfd-secondary" href="<?php echo esc_url( slfd_campaigns_url( 0, true ) ); ?>"><span class="dashicons dashicons-plus-alt"></span>Nouvelle campagne</a><?php if ( $campaign ) : ?><a class="slfd-secondary" href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'slfd_export_campaign', 'campaign' => $campaign['id'] ], admin_url( 'admin-post.php' ) ), 'slfd_export_campaign' ) ); ?>"><span class="dashicons dashicons-download"></span>Exporter</a><?php endif; ?><?php if ( $campaign && $campaign['is_public'] ) : $public_url = slfd_campaign_public_url( $campaign['id'] ); $share_message = slfd_campaign_share_message( $stats, $public_url ); ?><a class="slfd-secondary" target="_blank" rel="noopener" href="<?php echo esc_url( $public_url ); ?>"><span class="dashicons dashicons-chart-bar"></span>Voir la page partagée</a><a class="slfd-secondary" target="_blank" rel="noopener" href="https://wa.me/?text=<?php echo rawurlencode( $share_message ); ?>"><span class="dashicons dashicons-whatsapp"></span>Partager</a><?php endif; ?></div></div>
        <?php if ( $campaigns ) : ?>
            <section class="slfd-pending slfd-campaign-list">
                <h2>Campagnes enregistrées</h2>
                <?php foreach ( $campaigns as $campaign_post ) :
                    $campaign_item = slfd_campaign_data( $campaign_post->ID );
                    $is_active = $campaign_item['id'] === slfd_active_campaign_id();
                ?>
                    <article>
                        <div>
                            <strong><?php echo esc_html( $campaign_item['name'] ); ?></strong>
                            <span>Du <?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $campaign_item['start'] ) ) ); ?> au <?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $campaign_item['end'] ) ) ); ?> · <?php echo 'archived' === $campaign_item['state'] ? 'Archivée' : ( $is_active ? 'Campagne en cours' : 'Prête à activer' ); ?></span>
                        </div>
                        <div class="slfd-campaign-actions">
                            <a class="slfd-secondary" href="<?php echo esc_url( slfd_campaigns_url( $campaign_item['id'] ) ); ?>">Ouvrir</a>
                            <?php if ( ! $is_active ) : ?>
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                    <input type="hidden" name="action" value="slfd_campaign_action">
                                    <input type="hidden" name="campaign_id" value="<?php echo (int) $campaign_item['id']; ?>">
                                    <input type="hidden" name="campaign_action" value="activate">
                                    <?php wp_nonce_field( 'slfd_campaign_action_' . $campaign_item['id'] ); ?>
                                    <button type="submit"><?php echo 'archived' === $campaign_item['state'] ? 'Réouvrir' : 'Activer'; ?></button>
                                </form>
                            <?php endif; ?>
                            <?php if ( 'archived' !== $campaign_item['state'] ) : ?>
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return window.confirm('Archiver cette campagne ? Ses résultats resteront consultables.');">
                                    <input type="hidden" name="action" value="slfd_campaign_action">
                                    <input type="hidden" name="campaign_id" value="<?php echo (int) $campaign_item['id']; ?>">
                                    <input type="hidden" name="campaign_action" value="archive">
                                    <?php wp_nonce_field( 'slfd_campaign_action_' . $campaign_item['id'] ); ?>
                                    <button class="slfd-campaign-archive" type="submit">Archiver</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return window.confirm('Supprimer cette campagne ? Elle sera retirée du pilotage. Les rapports et approvisionnements resteront disponibles dans l’historique.');">
                                <input type="hidden" name="action" value="slfd_campaign_action">
                                <input type="hidden" name="campaign_id" value="<?php echo (int) $campaign_item['id']; ?>">
                                <input type="hidden" name="campaign_action" value="delete">
                                <?php wp_nonce_field( 'slfd_campaign_action_' . $campaign_item['id'] ); ?>
                                <button class="slfd-campaign-delete" type="submit">Supprimer</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
            <section class="slfd-pending slfd-campaign-comparison"><h2>Comparaison des campagnes</h2><div class="slfd-table-scroll"><table><thead><tr><th>Campagne</th><th>Période</th><th>Clients</th><th>Objectif</th><th>Atteinte</th></tr></thead><tbody><?php foreach ( $campaigns as $comparison_post ) : $comparison = slfd_campaign_data( $comparison_post->ID ); $comparison_stats = slfd_campaign_stats( $comparison ); if ( ! $comparison_stats ) continue; ?><tr><td><strong><?php echo esc_html( $comparison['name'] ); ?></strong></td><td><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $comparison['start'] ) ) . ' - ' . date_i18n( 'd/m/Y', strtotime( $comparison['end'] ) ) ); ?></td><td><?php echo number_format_i18n( $comparison_stats['clients']['actual'] ); ?></td><td><?php echo number_format_i18n( $comparison_stats['clients']['target'] ); ?></td><td><?php echo number_format_i18n( $comparison_stats['clients']['percent'], 1 ); ?>%</td></tr><?php endforeach; ?></tbody></table></div></section>
        <?php endif; ?>
        <form class="slfd-report-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="slfd_save_campaign"><input type="hidden" name="campaign_id" value="<?php echo (int) ( $campaign['id'] ?? 0 ); ?>"><?php wp_nonce_field( 'slfd_save_campaign' ); ?>
            <section><h2>Cadre de la campagne</h2><div class="slfd-form-grid"><p><label for="slfd_campaign_name">Nom de la campagne</label><input id="slfd_campaign_name" name="slfd_campaign_name" value="<?php echo esc_attr( $campaign['name'] ?? '' ); ?>" placeholder="Campagne en cours"></p><p><label for="slfd_campaign_start">Date de début</label><input id="slfd_campaign_start" type="date" name="slfd_campaign_start" value="<?php echo esc_attr( $campaign['start'] ?? '' ); ?>" required></p><p><label for="slfd_campaign_end">Date de fin</label><input id="slfd_campaign_end" type="date" name="slfd_campaign_end" value="<?php echo esc_attr( $campaign['end'] ?? '' ); ?>" required></p><p><label for="slfd_clients_target">Objectif nouveaux clients</label><input id="slfd_clients_target" type="number" min="1" name="slfd_clients_target" value="<?php echo esc_attr( $campaign['clients_target'] ?? '' ); ?>" placeholder="30000" required></p><p><label for="slfd_playstore_target">Objectif inscriptions Play Store</label><input id="slfd_playstore_target" type="number" min="0" name="slfd_playstore_target" value="<?php echo esc_attr( $campaign['playstore_target'] ?? '' ); ?>"></p><p><label for="slfd_cards_target">Objectif cartes distribuées</label><input id="slfd_cards_target" type="number" min="0" name="slfd_cards_target" value="<?php echo esc_attr( $campaign['cards_target'] ?? '' ); ?>"></p></div></section>
            <section><h2>Objectifs par agence</h2><p class="slfd-form-help">Répartissez l’objectif clients. Ces cibles déterminent le rythme quotidien des agences et de leurs agents.</p><div class="slfd-form-grid"><?php foreach ( is_wp_error( $terms ) ? [] : $terms as $term ) : ?><p><label for="slfd_target_<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></label><input id="slfd_target_<?php echo esc_attr( $term->slug ); ?>" type="number" min="0" name="slfd_agency_targets[<?php echo esc_attr( $term->slug ); ?>]" value="<?php echo (int) ( $campaign['agency_targets'][ $term->slug ] ?? $default_target ); ?>"></p><?php endforeach; ?></div></section>
            <section><h2>Objectifs personnalisés des agents</h2><p class="slfd-form-help">Laissez vide pour utiliser la répartition automatique de l’objectif de l’agence.</p><div class="slfd-form-grid"><?php foreach ( $agents as $agent ) : ?><p><label for="slfd_agent_target_<?php echo (int) $agent->ID; ?>"><?php echo esc_html( $agent->display_name ?: $agent->user_login ); ?> <small><?php echo esc_html( get_user_meta( $agent->ID, 'sl_agence_assignee', true ) ); ?></small></label><input id="slfd_agent_target_<?php echo (int) $agent->ID; ?>" type="number" min="0" name="slfd_agent_targets[<?php echo (int) $agent->ID; ?>]" value="<?php echo esc_attr( $campaign['agent_targets'][ $agent->ID ] ?? '' ); ?>" placeholder="Automatique"></p><?php endforeach; ?></div></section>
            <section><h2>Visibilité et rappels</h2><div class="slfd-checks slfd-checks--rows"><label><input type="checkbox" name="slfd_is_active" value="1" <?php checked( ! $campaign || (int) $campaign['id'] === slfd_active_campaign_id() ); ?>><span>Utiliser comme campagne en cours</span></label><label><input type="checkbox" name="slfd_is_public" value="1" <?php checked( ! empty( $campaign['is_public'] ) ); ?>><span>Autoriser le partage de la page de résultats</span></label><label><input type="checkbox" name="slfd_show_agents" value="1" <?php checked( ! empty( $campaign['show_agents'] ) ); ?>><span>Afficher les performances individuelles des agents</span></label><label><input type="checkbox" name="slfd_email_alerts" value="1" <?php checked( ! empty( $campaign['email_alerts'] ) ); ?>><span>Envoyer un rappel e-mail quotidien aux agents en retard</span></label></div><p class="slfd-form-help">Les coordonnées des clients et les détails des rapports restent masqués.</p></section>
            <div class="slfd-form-actions"><a href="<?php echo esc_url( slfd_dashboard_url() ); ?>">Annuler</a><button type="submit">Enregistrer la campagne</button></div>
        </form>
        <?php if ( $stats ) : ?><section class="slfd-pending"><h2>Lecture rapide</h2><p><strong><?php echo esc_html( $campaign['name'] ); ?></strong> · Il reste <?php echo (int) $stats['days']['remaining']; ?> jour(s) de campagne, aujourd’hui inclus · <?php echo (int) $stats['clients']['daily']; ?> nouveaux clients à enregistrer chaque jour pour atteindre l’objectif.</p></section><?php endif; ?>
    </section></main><?php return ob_get_clean();
}

function slfd_campaign_progress_markup( $metric, $label, $days = null ) {
    $progress = min( 100, max( 0, (float) $metric['percent'] ) );
    $remaining = max( 0, (int) $metric['target'] - (int) $metric['actual'] );
    $pace      = $remaining ? number_format_i18n( $metric['daily'] ) . ' à réaliser par jour' : 'objectif atteint';
    $period    = $days && ! empty( $days['remaining'] ) ? ' sur ' . (int) $days['remaining'] . ' jour(s), aujourd’hui inclus' : '';
    return '<div class="slfd-campaign-progress"><div><span>' . esc_html( $label ) . '</span><strong>' . number_format_i18n( $metric['actual'] ) . ' / ' . number_format_i18n( $metric['target'] ) . '</strong></div><div class="slfd-campaign-track"><i style="width:' . esc_attr( $progress ) . '%"></i></div><small><strong>' . esc_html( number_format_i18n( $metric['percent'], 1 ) ) . '% de l’objectif atteint</strong> · Reste : ' . esc_html( number_format_i18n( $remaining ) ) . ' · Rythme nécessaire : ' . esc_html( $pace . $period ) . '</small></div>';
}

function slfd_campaign_trend_markup( $trend ) {
    $days = [];
    for ( $offset = 6; $offset >= 0; $offset-- ) {
        $date = date_i18n( 'Y-m-d', current_time( 'timestamp' ) - $offset * DAY_IN_SECONDS );
        $days[ $date ] = (int) ( $trend[ $date ] ?? 0 );
    }
    $max = max( 1, max( $days ) );
    $html = '<span class="slfd-agent-trend" aria-label="Évolution des sept derniers jours">';
    foreach ( $days as $date => $value ) {
        $height = max( 8, (int) round( $value / $max * 100 ) );
        $html .= '<i title="' . esc_attr( date_i18n( 'd/m', strtotime( $date ) ) . ' : ' . $value ) . '" style="height:' . esc_attr( $height ) . '%"></i>';
    }
    return $html . '</span>';
}

function slfd_campaign_agent_row( $stats, $user_id ) {
    foreach ( (array) ( $stats['agents'] ?? [] ) as $agent ) {
        if ( (int) $agent['user']->ID === (int) $user_id ) {
            return $agent;
        }
    }
    return null;
}

function slfd_campaign_chart_markup( $stats ) {
    $campaign = $stats['campaign'];
    $end = min( current_time( 'Y-m-d' ), $campaign['end'] );
    $start = max( $campaign['start'], date_i18n( 'Y-m-d', strtotime( $end . ' -13 days' ) ) );
    $values = [];
    for ( $date = $start; $date <= $end; $date = date_i18n( 'Y-m-d', strtotime( $date . ' +1 day' ) ) ) {
        $values[ $date ] = (int) ( $stats['daily'][ $date ] ?? 0 );
    }
    $max = max( 1, max( $values ), (int) ceil( $stats['clients']['target'] / $stats['days']['total'] ) );
    $html = '<div class="slfd-campaign-chart" role="img" aria-label="Évolution des enregistrements sur les quatorze derniers jours">';
    foreach ( $values as $date => $value ) {
        $height = max( 4, (int) round( $value / $max * 100 ) );
        $html .= '<span><i title="' . esc_attr( date_i18n( 'd/m', strtotime( $date ) ) . ' : ' . $value . ' clients' ) . '" style="height:' . esc_attr( $height ) . '%"></i><small>' . esc_html( date_i18n( 'd/m', strtotime( $date ) ) ) . '</small></span>';
    }
    return $html . '</div>';
}

function slfd_campaign_share_message( $stats, $url ) {
    $campaign = $stats['campaign'];
    return sprintf(
        "Bonjour,\n\nSuivez la campagne Fidélité %s :\n- %d / %d nouveaux clients (%s%%)\n- %d jour(s) restant(s)\n- %d enregistrement(s) par jour nécessaires\n\nVoir le classement : %s\n\nComplexe Santa Lucia",
        $campaign['name'],
        $stats['clients']['actual'],
        $stats['clients']['target'],
        number_format_i18n( $stats['clients']['percent'], 1 ),
        $stats['days']['remaining'],
        $stats['clients']['daily'],
        $url
    );
}

function slfd_campaign_alerts( $stats ) {
    if ( ! $stats ) { return [ 'agencies' => [], 'agents' => [] ]; }
    return [
        'agencies' => array_values( array_filter( $stats['agencies'], static function ( $agency ) { return $agency['clients']['gap'] < 0; } ) ),
        'agents' => array_values( array_filter( $stats['agents'], static function ( $agent ) { return $agent['clients']['gap'] < 0; } ) ),
    ];
}

add_action( 'admin_post_slfd_export_campaign', 'slfd_export_campaign' );
function slfd_export_campaign() {
    if ( ! slfd_can_manage_agents() ) { wp_die( 'Accès refusé.', 403 ); }
    check_admin_referer( 'slfd_export_campaign' );
    $campaign = slfd_campaign_data( absint( $_GET['campaign'] ?? 0 ) );
    $stats = slfd_campaign_stats( $campaign );
    if ( ! $campaign || ! $stats ) { wp_die( 'Campagne introuvable.', 404 ); }
    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=campagne-fidelite-' . sanitize_title( $campaign['name'] ) . '.csv' );
    $output = fopen( 'php://output', 'w' );
    fwrite( $output, "\xEF\xBB\xBF" );
    fputcsv( $output, [ 'Campagne', $campaign['name'], 'Période', $campaign['start'] . ' au ' . $campaign['end'] ], ';' );
    fputcsv( $output, [ 'Indicateur', 'Réalisé', 'Objectif', 'Pourcentage', 'Rythme quotidien restant' ], ';' );
    foreach ( [ 'clients' => 'Nouveaux clients', 'playstore' => 'Inscriptions Play Store', 'cards' => 'Cartes distribuées' ] as $key => $label ) {
        $metric = $stats[ $key ];
        fputcsv( $output, [ $label, $metric['actual'], $metric['target'], $metric['percent'] . '%', $metric['daily'] ], ';' );
    }
    fputcsv( $output, [], ';' );
    fputcsv( $output, [ 'Agence', 'Réalisé', 'Objectif', 'Pourcentage', 'Rythme quotidien restant', 'Agents actifs' ], ';' );
    foreach ( $stats['agencies'] as $agency ) {
        $metric = $agency['clients'];
        fputcsv( $output, [ $agency['term']->name, $metric['actual'], $metric['target'], $metric['percent'] . '%', $metric['daily'], $agency['agents'] ], ';' );
    }
    fputcsv( $output, [], ';' );
    fputcsv( $output, [ 'Agent', 'Agence', 'Réalisé', 'Objectif', 'Pourcentage', 'Rythme quotidien restant' ], ';' );
    foreach ( $stats['agents'] as $agent ) {
        $metric = $agent['clients'];
        fputcsv( $output, [ $agent['user']->display_name ?: $agent['user']->user_login, $agent['agency'], $metric['actual'], $metric['target'], $metric['percent'] . '%', $metric['daily'] ], ';' );
    }
    fclose( $output ); exit;
}

function slfd_render_campaign_overview( $stats, $is_public = false ) {
    if ( ! $stats ) { return ''; }
    $campaign = $stats['campaign'];
    ob_start(); ?>
    <section class="slfd-campaign-overview <?php echo $is_public ? 'slfd-campaign-overview--public' : ''; ?>"><div class="slfd-campaign-title"><div><p class="slfd-eyebrow">Campagne en cours</p><h2><?php echo esc_html( $campaign['name'] ); ?></h2><p>Du <?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $campaign['start'] ) ) ); ?> au <?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $campaign['end'] ) ) ); ?> · <strong><?php echo (int) $stats['days']['remaining']; ?> jour(s) de campagne, aujourd’hui inclus</strong></p></div><span class="slfd-campaign-days"><?php echo (int) $stats['days']['elapsed']; ?> / <?php echo (int) $stats['days']['total']; ?><small>jours écoulés</small></span></div><div class="slfd-campaign-kpis"><?php echo slfd_campaign_progress_markup( $stats['clients'], 'Nouveaux clients', $stats['days'] ); ?><?php if ( $campaign['playstore_target'] ) echo slfd_campaign_progress_markup( $stats['playstore'], 'Inscriptions Play Store', $stats['days'] ); ?><?php if ( $campaign['cards_target'] ) echo slfd_campaign_progress_markup( $stats['cards'], 'Cartes distribuées', $stats['days'] ); ?></div></section>
    <?php return ob_get_clean();
}

function slfd_render_campaign_public() {
    $campaign = slfd_campaign_data( absint( $_GET['campaign'] ?? 0 ) );
    $stats = slfd_campaign_stats( $campaign );
    if ( ! $campaign || ! $campaign['is_public'] || ! $stats ) {
        status_header( 404 );
        return '<main class="slfd-public-shell"><h1>Résultats indisponibles</h1><p>La page de suivi n’est pas encore activée par la coordination.</p></main>';
    }
    ob_start(); ?>
    <main class="slfd-public-shell"><header class="slfd-public-header"><button class="slfd-print-campaign" type="button" onclick="window.print()" title="Imprimer ou enregistrer en PDF" aria-label="Imprimer ou enregistrer en PDF"><span class="dashicons dashicons-printer"></span></button><p>Complexe Santa Lucia</p><h1>Suivi de la campagne Fidélité</h1><span>Mise à jour : <?php echo esc_html( date_i18n( 'd/m/Y à H:i', current_time( 'timestamp' ) ) ); ?></span></header><?php echo slfd_render_campaign_overview( $stats, true ); ?><section class="slfd-public-section"><div class="slfd-public-heading"><h2>Évolution des enregistrements</h2><p>Les quatorze derniers jours de la campagne.</p></div><?php echo slfd_campaign_chart_markup( $stats ); // phpcs:ignore WordPress.Security.EscapeOutput ?></section>
    <section class="slfd-public-section"><div class="slfd-public-heading"><h2>Classement des agences</h2><p>Objectif, progression et rythme quotidien de chaque agence.</p></div><div class="slfd-public-agencies"><?php foreach ( $stats['agencies'] as $rank => $agency ) : $is_late = $agency['clients']['gap'] < 0; ?><article class="<?php echo $is_late ? 'is-late' : ''; ?>"><div class="slfd-public-rank">#<?php echo (int) ( $rank + 1 ); ?></div><div class="slfd-public-agency-main"><h3><?php echo esc_html( $agency['term']->name ); ?></h3><?php echo slfd_campaign_progress_markup( $agency['clients'], 'Clients enregistrés', $stats['days'] ); ?></div><div class="slfd-public-agency-state"><strong><?php echo $is_late ? 'À accompagner' : 'Dans le rythme'; ?></strong><span><?php echo (int) $agency['agents']; ?> agent(s)</span></div></article><?php endforeach; ?></div></section>
    <?php if ( $campaign['show_agents'] ) : ?><section class="slfd-public-section"><div class="slfd-public-heading"><h2>Classement des agents</h2><p>La progression de chacun est comparée à son objectif personnel.</p></div><div class="slfd-public-agents"><table><thead><tr><th>Rang</th><th>Agent</th><th>Agence</th><th>Réalisé</th><th>Objectif</th><th>Évolution</th><th>Statut</th></tr></thead><tbody><?php foreach ( $stats['agents'] as $rank => $agent ) : $late = $agent['clients']['gap'] < 0; ?><tr class="<?php echo $late ? 'is-late' : ''; ?>"><td>#<?php echo (int) ( $rank + 1 ); ?></td><td><strong><?php echo esc_html( $agent['user']->display_name ?: $agent['user']->user_login ); ?></strong></td><td><?php echo esc_html( $agent['agency'] ?: 'Non attribuée' ); ?></td><td><?php echo number_format_i18n( $agent['clients']['actual'] ); ?></td><td><?php echo number_format_i18n( $agent['clients']['target'] ); ?></td><td><?php echo slfd_campaign_trend_markup( $agent['trend'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td><td><span><?php echo $late ? 'En dessous du rythme' : 'Dans le rythme'; ?></span></td></tr><?php endforeach; ?></tbody></table></div></section><?php endif; ?>
    </main><?php return ob_get_clean();
}
