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

function slfd_campaigns_url() {
    $id = slfd_page_id( 'campaigns' );
    return $id ? get_permalink( $id ) : home_url( '/campagnes-fidelite/' );
}

function slfd_campaign_public_url() {
    $id = slfd_page_id( 'campaign_public' );
    return $id ? get_permalink( $id ) : home_url( '/suivi-campagne-fidelite/' );
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
    $remaining = $today > $campaign['end'] ? 0 : max( 0, $total - $elapsed );
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
    return get_posts( $query );
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
    foreach ( $reports as $report ) {
        $agency = (string) get_post_meta( $report->ID, '_slfd_agency', true );
        $clients = slfd_meta_int( $report->ID, '_slfd_enrollments' );
        $playstore = slfd_meta_int( $report->ID, '_slfd_playstore' );
        $cards = slfd_meta_int( $report->ID, '_slfd_received' );
        $totals['clients'] += $clients;
        $totals['playstore'] += $playstore;
        $totals['cards'] += $cards;
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
        $report_date = (string) get_post_meta( $report->ID, '_slfd_date', true );
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
        $agent_target = $agency_row && $agency_row['agents'] ? (int) ceil( $agency_target / $agency_row['agents'] ) : 0;
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
    update_option( 'slfd_active_campaign_id', $campaign_id, false );
    slfd_flash( 'success', 'La campagne et ses objectifs ont été enregistrés.' );
    wp_safe_redirect( slfd_campaigns_url() ); exit;
}

function slfd_render_campaign_management() {
    if ( ! slfd_can_manage_agents() ) { return slfd_render_access_denied(); }
    $campaign = slfd_campaign_data();
    $stats = slfd_campaign_stats( $campaign );
    $flash = slfd_flash( 'read' );
    $terms = get_terms( [ 'taxonomy' => 'sl_agence_promo', 'hide_empty' => false, 'orderby' => 'name' ] );
    $default_target = $campaign ? (int) ceil( $campaign['clients_target'] / max( 1, count( $terms ) ) ) : 0;
    ob_start(); ?>
    <main class="slfd-shell"><header class="slfd-topbar"><a class="slfd-brand" href="<?php echo esc_url( slfd_dashboard_url() ); ?>"><span class="dashicons dashicons-awards"></span><span><small>Complexe</small>Santa Lucia</span></a><span class="slfd-internal">Pilotage de campagne</span><a class="slfd-top-link" href="<?php echo esc_url( slfd_dashboard_url() ); ?>">Tableau de bord</a></header>
    <section class="slfd-content slfd-report-page"><?php if ( $flash ) : ?><div class="slfd-flash slfd-flash--<?php echo esc_attr( $flash[0] ); ?>"><?php echo esc_html( $flash[1] ); ?></div><?php endif; ?>
        <div class="slfd-heading"><div><p class="slfd-eyebrow">Coordination</p><h1>Campagne Fidélité</h1><p>Définissez la période et les objectifs qui guident chaque agence et chaque agent.</p></div><?php if ( $campaign && $campaign['is_public'] ) : ?><a class="slfd-secondary" target="_blank" rel="noopener" href="<?php echo esc_url( slfd_campaign_public_url() ); ?>"><span class="dashicons dashicons-chart-bar"></span>Voir la page partagée</a><?php endif; ?></div>
        <form class="slfd-report-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="slfd_save_campaign"><input type="hidden" name="campaign_id" value="<?php echo (int) ( $campaign['id'] ?? 0 ); ?>"><?php wp_nonce_field( 'slfd_save_campaign' ); ?>
            <section><h2>Cadre de la campagne</h2><div class="slfd-form-grid"><p><label for="slfd_campaign_name">Nom de la campagne</label><input id="slfd_campaign_name" name="slfd_campaign_name" value="<?php echo esc_attr( $campaign['name'] ?? '' ); ?>" placeholder="Campagne en cours"></p><p><label for="slfd_campaign_start">Date de début</label><input id="slfd_campaign_start" type="date" name="slfd_campaign_start" value="<?php echo esc_attr( $campaign['start'] ?? '' ); ?>" required></p><p><label for="slfd_campaign_end">Date de fin</label><input id="slfd_campaign_end" type="date" name="slfd_campaign_end" value="<?php echo esc_attr( $campaign['end'] ?? '' ); ?>" required></p><p><label for="slfd_clients_target">Objectif nouveaux clients</label><input id="slfd_clients_target" type="number" min="1" name="slfd_clients_target" value="<?php echo esc_attr( $campaign['clients_target'] ?? '' ); ?>" placeholder="30000" required></p><p><label for="slfd_playstore_target">Objectif inscriptions Play Store</label><input id="slfd_playstore_target" type="number" min="0" name="slfd_playstore_target" value="<?php echo esc_attr( $campaign['playstore_target'] ?? '' ); ?>"></p><p><label for="slfd_cards_target">Objectif cartes distribuées</label><input id="slfd_cards_target" type="number" min="0" name="slfd_cards_target" value="<?php echo esc_attr( $campaign['cards_target'] ?? '' ); ?>"></p></div></section>
            <section><h2>Objectifs par agence</h2><p class="slfd-form-help">Répartissez l’objectif clients. Ces cibles déterminent le rythme quotidien des agences et de leurs agents.</p><div class="slfd-form-grid"><?php foreach ( is_wp_error( $terms ) ? [] : $terms as $term ) : ?><p><label for="slfd_target_<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></label><input id="slfd_target_<?php echo esc_attr( $term->slug ); ?>" type="number" min="0" name="slfd_agency_targets[<?php echo esc_attr( $term->slug ); ?>]" value="<?php echo (int) ( $campaign['agency_targets'][ $term->slug ] ?? $default_target ); ?>"></p><?php endforeach; ?></div></section>
            <section><h2>Visibilité de la page partagée</h2><div class="slfd-checks slfd-checks--rows"><label><input type="checkbox" name="slfd_is_public" value="1" <?php checked( ! empty( $campaign['is_public'] ) ); ?>><span>Autoriser le partage de la page de résultats</span></label><label><input type="checkbox" name="slfd_show_agents" value="1" <?php checked( ! empty( $campaign['show_agents'] ) ); ?>><span>Afficher les performances individuelles des agents</span></label></div><p class="slfd-form-help">Les coordonnées des clients et les détails des rapports restent masqués.</p></section>
            <div class="slfd-form-actions"><a href="<?php echo esc_url( slfd_dashboard_url() ); ?>">Annuler</a><button type="submit">Enregistrer la campagne</button></div>
        </form>
        <?php if ( $stats ) : ?><section class="slfd-pending"><h2>Lecture rapide</h2><p><strong><?php echo esc_html( $campaign['name'] ); ?></strong> · <?php echo (int) $stats['days']['remaining']; ?> jour(s) restant(s) · <?php echo (int) $stats['clients']['daily']; ?> nouveaux clients à enregistrer par jour pour atteindre l’objectif.</p></section><?php endif; ?>
    </section></main><?php return ob_get_clean();
}

function slfd_campaign_progress_markup( $metric, $label ) {
    $progress = min( 100, max( 0, (float) $metric['percent'] ) );
    return '<div class="slfd-campaign-progress"><div><span>' . esc_html( $label ) . '</span><strong>' . number_format_i18n( $metric['actual'] ) . ' / ' . number_format_i18n( $metric['target'] ) . '</strong></div><div class="slfd-campaign-track"><i style="width:' . esc_attr( $progress ) . '%"></i></div><small>' . esc_html( number_format_i18n( $metric['percent'], 1 ) ) . '% atteint · ' . esc_html( number_format_i18n( $metric['daily'] ) ) . ' / jour restant</small></div>';
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

function slfd_render_campaign_overview( $stats, $is_public = false ) {
    if ( ! $stats ) { return ''; }
    $campaign = $stats['campaign'];
    ob_start(); ?>
    <section class="slfd-campaign-overview <?php echo $is_public ? 'slfd-campaign-overview--public' : ''; ?>"><div class="slfd-campaign-title"><div><p class="slfd-eyebrow">Campagne en cours</p><h2><?php echo esc_html( $campaign['name'] ); ?></h2><p>Du <?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $campaign['start'] ) ) ); ?> au <?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $campaign['end'] ) ) ); ?> · <strong><?php echo (int) $stats['days']['remaining']; ?> jour(s) restant(s)</strong></p></div><span class="slfd-campaign-days"><?php echo (int) $stats['days']['elapsed']; ?> / <?php echo (int) $stats['days']['total']; ?><small>jours écoulés</small></span></div><div class="slfd-campaign-kpis"><?php echo slfd_campaign_progress_markup( $stats['clients'], 'Nouveaux clients' ); ?><?php if ( $campaign['playstore_target'] ) echo slfd_campaign_progress_markup( $stats['playstore'], 'Inscriptions Play Store' ); ?><?php if ( $campaign['cards_target'] ) echo slfd_campaign_progress_markup( $stats['cards'], 'Cartes distribuées' ); ?></div></section>
    <?php return ob_get_clean();
}

function slfd_render_campaign_public() {
    $campaign = slfd_campaign_data();
    $stats = slfd_campaign_stats( $campaign );
    if ( ! $campaign || ! $campaign['is_public'] || ! $stats ) {
        status_header( 404 );
        return '<main class="slfd-public-shell"><h1>Résultats indisponibles</h1><p>La page de suivi n’est pas encore activée par la coordination.</p></main>';
    }
    ob_start(); ?>
    <main class="slfd-public-shell"><header class="slfd-public-header"><p>Complexe Santa Lucia</p><h1>Suivi de la campagne Fidélité</h1><span>Mise à jour : <?php echo esc_html( date_i18n( 'd/m/Y à H:i', current_time( 'timestamp' ) ) ); ?></span></header><?php echo slfd_render_campaign_overview( $stats, true ); ?>
    <section class="slfd-public-section"><div class="slfd-public-heading"><h2>Classement des agences</h2><p>Objectif, progression et rythme quotidien de chaque agence.</p></div><div class="slfd-public-agencies"><?php foreach ( $stats['agencies'] as $rank => $agency ) : $is_late = $agency['clients']['gap'] < 0; ?><article class="<?php echo $is_late ? 'is-late' : ''; ?>"><div class="slfd-public-rank">#<?php echo (int) ( $rank + 1 ); ?></div><div class="slfd-public-agency-main"><h3><?php echo esc_html( $agency['term']->name ); ?></h3><?php echo slfd_campaign_progress_markup( $agency['clients'], 'Clients enregistrés' ); ?></div><div class="slfd-public-agency-state"><strong><?php echo $is_late ? 'À accompagner' : 'Dans le rythme'; ?></strong><span><?php echo (int) $agency['agents']; ?> agent(s)</span></div></article><?php endforeach; ?></div></section>
    <?php if ( $campaign['show_agents'] ) : ?><section class="slfd-public-section"><div class="slfd-public-heading"><h2>Classement des agents</h2><p>La progression de chacun est comparée à son objectif personnel.</p></div><div class="slfd-public-agents"><table><thead><tr><th>Rang</th><th>Agent</th><th>Agence</th><th>Réalisé</th><th>Objectif</th><th>Évolution</th><th>Statut</th></tr></thead><tbody><?php foreach ( $stats['agents'] as $rank => $agent ) : $late = $agent['clients']['gap'] < 0; ?><tr class="<?php echo $late ? 'is-late' : ''; ?>"><td>#<?php echo (int) ( $rank + 1 ); ?></td><td><strong><?php echo esc_html( $agent['user']->display_name ?: $agent['user']->user_login ); ?></strong></td><td><?php echo esc_html( $agent['agency'] ?: 'Non attribuée' ); ?></td><td><?php echo number_format_i18n( $agent['clients']['actual'] ); ?></td><td><?php echo number_format_i18n( $agent['clients']['target'] ); ?></td><td><?php echo slfd_campaign_trend_markup( $agent['trend'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td><td><span><?php echo $late ? 'En dessous du rythme' : 'Dans le rythme'; ?></span></td></tr><?php endforeach; ?></tbody></table></div></section><?php endif; ?>
    </main><?php return ob_get_clean();
}
