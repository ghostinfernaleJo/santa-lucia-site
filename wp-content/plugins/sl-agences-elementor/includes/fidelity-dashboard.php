<?php
/**
 * Tableau de bord interne du programme de fidelite.
 *
 * Les responsables d'agence deposent un rapport quotidien. Les gestionnaires
 * le valident avant qu'il ne soit pris en compte dans le classement. Les deux
 * pages creees par le module ne sont ni dans le menu ni indexables.
 */

defined( 'ABSPATH' ) || exit;

const SLFD_POST_TYPE = 'sl_fidelity_report';
const SLFD_SUPPLY_POST_TYPE = 'sl_fidelity_supply';

/**
 * Roles dedicated to the loyalty programme. They are deliberately independent
 * from the existing agency and Bons Plans roles, so their login flow and
 * permissions cannot change the other back-office modules.
 */
add_action( 'init', 'slfd_register_roles', 5 );
function slfd_register_roles() {
    $roles = [
        'sl_agent_fidelite' => [
            'label' => 'Agent fidélité',
            'caps'  => [ 'read' => true ],
        ],
        'sl_responsable_fidelite' => [
            'label' => 'Responsable fidélité',
            'caps'  => [ 'read' => true ],
        ],
    ];

    foreach ( $roles as $slug => $role ) {
        if ( ! get_role( $slug ) ) {
            add_role( $slug, __( $role['label'], 'sl-agences' ), $role['caps'] );
        }
        $wp_role = get_role( $slug );
        if ( $wp_role && ! $wp_role->has_cap( 'read' ) ) {
            $wp_role->add_cap( 'read' );
        }
    }
}

add_action( 'init', 'slfd_register_report_type', 20 );
function slfd_register_report_type() {
    register_post_type( SLFD_POST_TYPE, [
        'labels' => [
            'name'          => 'Rapports fidélité',
            'singular_name' => 'Rapport fidélité',
        ],
        'public'             => false,
        'publicly_queryable' => false,
        'show_ui'            => false,
        'show_in_menu'       => false,
        'supports'           => [ 'title', 'author' ],
        'map_meta_cap'       => true,
        'capability_type'    => 'post',
    ] );
    register_post_type( SLFD_SUPPLY_POST_TYPE, [
        'labels' => [
            'name'          => 'Approvisionnements fidélité',
            'singular_name' => 'Approvisionnement fidélité',
        ],
        'public'             => false,
        'publicly_queryable' => false,
        'show_ui'            => false,
        'show_in_menu'       => false,
        'supports'           => [ 'title', 'author' ],
        'map_meta_cap'       => true,
        'capability_type'    => 'post',
    ] );
}

/** Cree les pages internes une seule fois, sans les ajouter a la navigation. */
add_action( 'init', 'slfd_ensure_internal_pages', 30 );
function slfd_ensure_internal_pages() {
    $pages = [
        'dashboard' => [
            'slug'    => 'espace-fidelite-interne',
            'title'   => 'Tableau de bord Fidélité',
            'screen'  => 'dashboard',
        ],
        'report' => [
            'slug'    => 'declarer-rapport-fidelite',
            'title'   => 'Déclarer un rapport fidélité',
            'screen'  => 'report',
        ],
        'supply' => [
            'slug'    => 'approvisionner-cartes-fidelite',
            'title'   => 'Approvisionner une agence',
            'screen'  => 'supply',
        ],
    ];

    foreach ( $pages as $key => $page ) {
        $option = 'slfd_page_' . $key;
        $id     = (int) get_option( $option );
        if ( $id && get_post( $id ) ) {
            continue;
        }

        $existing = get_page_by_path( $page['slug'] );
        if ( $existing instanceof WP_Post ) {
            $id = (int) $existing->ID;
        } else {
            $id = wp_insert_post( [
                'post_type'    => 'page',
                'post_status'  => 'publish',
                'post_title'   => $page['title'],
                'post_name'    => $page['slug'],
                'post_content' => '',
            ], true );
            if ( is_wp_error( $id ) ) {
                continue;
            }
        }

        update_post_meta( $id, '_slfd_internal_page', $page['screen'] );
        update_option( $option, $id, false );
    }
}

function slfd_page_id( $screen ) {
    return (int) get_option( 'slfd_page_' . $screen );
}

function slfd_is_internal_page() {
    $id = get_queried_object_id();
    return $id && in_array( get_post_meta( $id, '_slfd_internal_page', true ), [ 'dashboard', 'report', 'supply' ], true );
}

/** Page exclue de la recherche et des sitemaps : le lien ne devient pas public. */
add_filter( 'wp_robots', 'slfd_robots_noindex' );
function slfd_robots_noindex( $robots ) {
    if ( slfd_is_internal_page() ) {
        $robots['noindex']  = true;
        $robots['nofollow'] = true;
        $robots['noarchive'] = true;
    }
    return $robots;
}

add_filter( 'wp_sitemaps_posts_query_args', 'slfd_exclude_from_sitemap', 10, 2 );
function slfd_exclude_from_sitemap( $args, $post_type ) {
    if ( 'page' !== $post_type ) {
        return $args;
    }
    $ids = array_filter( [ slfd_page_id( 'dashboard' ), slfd_page_id( 'report' ), slfd_page_id( 'supply' ) ] );
    if ( $ids ) {
        $args['post__not_in'] = array_unique( array_merge( (array) ( $args['post__not_in'] ?? [] ), $ids ) );
    }
    return $args;
}

/** Acces : roles fidelite dedies, avec conservation des acces historiques. */
function slfd_user_has_role( $roles ) {
    $user = wp_get_current_user();
    return $user && (bool) array_intersect( (array) $roles, (array) $user->roles );
}

function slfd_can_access() {
    return is_user_logged_in() && ( current_user_can( 'manage_options' ) || current_user_can( 'edit_others_posts' ) || slfd_user_has_role( [ 'sl_agent_fidelite', 'sl_responsable_fidelite', 'sl_responsable_agence', 'sl_gestionnaire_bons_plans' ] ) );
}

function slfd_can_view_dashboard() {
    return current_user_can( 'manage_options' ) || current_user_can( 'edit_others_posts' ) || slfd_user_has_role( [ 'sl_responsable_fidelite', 'sl_responsable_agence', 'sl_gestionnaire_bons_plans' ] );
}

function slfd_can_validate() {
    return current_user_can( 'manage_options' ) || current_user_can( 'edit_others_posts' ) || slfd_user_has_role( [ 'sl_responsable_fidelite', 'sl_gestionnaire_bons_plans' ] );
}

/** Seuls les responsables du programme fidelite peuvent enregistrer une dotation. */
function slfd_can_supply() {
    return slfd_can_validate();
}

function slfd_current_agency() {
    $name = (string) get_user_meta( get_current_user_id(), 'sl_agence_assignee', true );
    if ( '' === $name ) {
        return null;
    }
    $term = get_term_by( 'name', $name, 'sl_agence_promo' );
    if ( ! $term ) {
        $term = get_term_by( 'slug', sanitize_title( $name ), 'sl_agence_promo' );
    }
    return $term && ! is_wp_error( $term ) ? $term : null;
}

function slfd_agency_name( $slug ) {
    $term = get_term_by( 'slug', $slug, 'sl_agence_promo' );
    return $term ? $term->name : ucwords( str_replace( '-', ' ', $slug ) );
}

function slfd_dashboard_url() {
    $id = slfd_page_id( 'dashboard' );
    return $id ? get_permalink( $id ) : home_url( '/espace-fidelite-interne/' );
}

function slfd_report_url() {
    $id = slfd_page_id( 'report' );
    return $id ? get_permalink( $id ) : home_url( '/declarer-rapport-fidelite/' );
}

function slfd_supply_url() {
    $id = slfd_page_id( 'supply' );
    return $id ? get_permalink( $id ) : home_url( '/approvisionner-cartes-fidelite/' );
}

function slfd_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
    if ( ! ( $user instanceof WP_User ) ) {
        return $redirect_to;
    }

    if ( in_array( 'sl_agent_fidelite', (array) $user->roles, true ) ) {
        return slfd_report_url();
    }

    if ( in_array( 'sl_responsable_fidelite', (array) $user->roles, true ) ) {
        return slfd_dashboard_url();
    }

    return $redirect_to;
}
add_filter( 'login_redirect', 'slfd_login_redirect', 30, 3 );

function slfd_woocommerce_login_redirect( $redirect, $user ) {
    return slfd_login_redirect( $redirect, '', $user );
}
add_filter( 'woocommerce_login_redirect', 'slfd_woocommerce_login_redirect', 30, 2 );

add_action( 'wp_enqueue_scripts', 'slfd_enqueue_assets', 110 );
function slfd_enqueue_assets() {
    if ( ! slfd_is_internal_page() ) {
        return;
    }
    $file = SL_AGENCES_PATH . 'assets/css/fidelity-dashboard.css';
    wp_enqueue_style( 'dashicons' );
    wp_enqueue_style(
        'sl-fidelity-dashboard',
        SL_AGENCES_URL . 'assets/css/fidelity-dashboard.css',
        [],
        file_exists( $file ) ? (string) filemtime( $file ) : SL_AGENCES_VERSION
    );
    $supply_file = SL_AGENCES_PATH . 'assets/css/fidelity-supply.css';
    wp_enqueue_style(
        'sl-fidelity-supply',
        SL_AGENCES_URL . 'assets/css/fidelity-supply.css',
        [ 'sl-fidelity-dashboard' ],
        file_exists( $supply_file ) ? (string) filemtime( $supply_file ) : SL_AGENCES_VERSION
    );
}

/** Utilise un template autonome : aucune navigation publique autour des donnees internes. */
add_filter( 'template_include', 'slfd_internal_template', 99 );
function slfd_internal_template( $template ) {
    if ( slfd_is_internal_page() ) {
        $internal = SL_AGENCES_PATH . 'templates/fidelity-dashboard.php';
        if ( file_exists( $internal ) ) {
            return $internal;
        }
    }
    return $template;
}

function slfd_report_for_agency_date( $agency, $date ) {
    $q = new WP_Query( [
        'post_type'      => SLFD_POST_TYPE,
        'post_status'    => [ 'pending', 'publish' ],
        'posts_per_page' => 1,
        'meta_query'     => [
            'relation' => 'AND',
            [ 'key' => '_slfd_agency', 'value' => $agency ],
            [ 'key' => '_slfd_date', 'value' => $date ],
        ],
        'orderby'        => 'ID',
        'order'          => 'DESC',
    ] );
    return $q->posts ? $q->posts[0] : null;
}

/** Approvisionnements declares par le responsable du programme, source unique des cartes recues. */
function slfd_supplies_for_agency_date( $agency, $date ) {
    return get_posts( [
        'post_type'      => SLFD_SUPPLY_POST_TYPE,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [
            'relation' => 'AND',
            [ 'key' => '_slfd_agency', 'value' => $agency ],
            [ 'key' => '_slfd_date', 'value' => $date ],
        ],
        'orderby'        => 'ID',
        'order'          => 'ASC',
    ] );
}

function slfd_supplies_total( $agency, $date ) {
    return array_sum( array_map( function ( $supply ) {
        return slfd_meta_int( $supply->ID, '_slfd_quantity' );
    }, slfd_supplies_for_agency_date( $agency, $date ) ) );
}

function slfd_supplies_for_date( $date ) {
    return get_posts( [
        'post_type'      => SLFD_SUPPLY_POST_TYPE,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_key'       => '_slfd_date',
        'meta_value'     => $date,
        'orderby'        => 'ID',
        'order'          => 'DESC',
    ] );
}

function slfd_meta_int( $post_id, $key ) {
    return max( 0, (int) get_post_meta( $post_id, $key, true ) );
}

function slfd_stock_state( $stock ) {
    if ( $stock <= 20 ) return [ 'Critique', 'critical' ];
    if ( $stock <= 50 ) return [ 'Faible', 'low' ];
    return [ 'Bon', 'good' ];
}

function slfd_flash( $key, $message = null ) {
    if ( null !== $message ) {
        set_transient( 'slfd_flash_' . get_current_user_id(), [ $key, $message ], MINUTE_IN_SECONDS );
        return;
    }
    $flash = get_transient( 'slfd_flash_' . get_current_user_id() );
    delete_transient( 'slfd_flash_' . get_current_user_id() );
    return $flash;
}

/** Le rapport peut etre corrige par son auteur tant qu'il n'est pas valide. */
add_action( 'admin_post_slfd_submit_report', 'slfd_submit_report' );
function slfd_submit_report() {
    if ( ! slfd_can_access() ) {
        wp_die( 'Accès refusé.', 403 );
    }
    check_admin_referer( 'slfd_submit_report' );

    $date = isset( $_POST['slfd_date'] ) ? sanitize_text_field( wp_unslash( $_POST['slfd_date'] ) ) : '';
    $date_object = DateTime::createFromFormat( 'Y-m-d', $date, wp_timezone() );
    if ( ! $date_object || $date_object->format( 'Y-m-d' ) !== $date || $date > current_time( 'Y-m-d' ) ) {
        slfd_flash( 'error', 'Indiquez une date valide, sans choisir une date future.' );
        wp_safe_redirect( slfd_report_url() );
        exit;
    }

    $agency = slfd_current_agency();
    if ( slfd_can_validate() && ! empty( $_POST['slfd_agency'] ) ) {
        $candidate = get_term_by( 'slug', sanitize_title( wp_unslash( $_POST['slfd_agency'] ) ), 'sl_agence_promo' );
        if ( $candidate && ! is_wp_error( $candidate ) ) {
            $agency = $candidate;
        }
    }
    if ( ! $agency ) {
        slfd_flash( 'error', 'Aucune agence n’est associée à ce compte. Un administrateur doit d’abord vous rattacher à une agence.' );
        wp_safe_redirect( slfd_report_url() );
        exit;
    }

    $opening     = absint( $_POST['slfd_opening'] ?? 0 );
    // Les cartes recues ne sont jamais saisies par l'agence : elles viennent
    // exclusivement des approvisionnements enregistres par le programme fidelite.
    $received    = slfd_supplies_total( $agency->slug, $date );
    $enrollments = absint( $_POST['slfd_enrollments'] ?? 0 );
    $damaged     = absint( $_POST['slfd_damaged'] ?? 0 );
    $closing     = absint( $_POST['slfd_closing'] ?? 0 );
    $expected    = max( 0, $opening + $received - $enrollments - $damaged );
    $issues      = array_map( 'sanitize_key', (array) ( $_POST['slfd_issues'] ?? [] ) );
    $allowed     = [ 'network', 'defective_cards', 'stock', 'other' ];
    $issues      = array_values( array_intersect( $issues, $allowed ) );
    $notes       = sanitize_textarea_field( wp_unslash( $_POST['slfd_notes'] ?? '' ) );
    $request     = sanitize_textarea_field( wp_unslash( $_POST['slfd_request'] ?? '' ) );

    if ( $enrollments > $opening + $received ) {
        slfd_flash( 'error', 'Les enrôlements ne peuvent pas dépasser les cartes disponibles pour cette journée.' );
        wp_safe_redirect( slfd_report_url() );
        exit;
    }

    $existing = slfd_report_for_agency_date( $agency->slug, $date );
    if ( $existing && ( 'publish' === $existing->post_status || (int) $existing->post_author !== get_current_user_id() ) ) {
        slfd_flash( 'error', 'Un rapport existe déjà pour cette agence et cette date. Contactez un gestionnaire pour le corriger.' );
        wp_safe_redirect( slfd_report_url() );
        exit;
    }

    $title = sprintf( 'Rapport fidélité — %s — %s', $agency->name, $date );
    $post_id = $existing ? (int) $existing->ID : wp_insert_post( [
        'post_type'   => SLFD_POST_TYPE,
        'post_status' => 'pending',
        'post_title'  => $title,
        'post_author' => get_current_user_id(),
    ], true );
    if ( is_wp_error( $post_id ) || ! $post_id ) {
        slfd_flash( 'error', 'Le rapport n’a pas pu être enregistré. Réessayez dans un instant.' );
        wp_safe_redirect( slfd_report_url() );
        exit;
    }
    if ( $existing ) {
        wp_update_post( [ 'ID' => $post_id, 'post_title' => $title ] );
    }

    update_post_meta( $post_id, '_slfd_agency', $agency->slug );
    update_post_meta( $post_id, '_slfd_date', $date );
    update_post_meta( $post_id, '_slfd_opening', $opening );
    update_post_meta( $post_id, '_slfd_received', $received );
    update_post_meta( $post_id, '_slfd_enrollments', $enrollments );
    update_post_meta( $post_id, '_slfd_damaged', $damaged );
    update_post_meta( $post_id, '_slfd_closing', $closing );
    update_post_meta( $post_id, '_slfd_expected_closing', $expected );
    update_post_meta( $post_id, '_slfd_stock_delta', $closing - $expected );
    update_post_meta( $post_id, '_slfd_issues', $issues );
    update_post_meta( $post_id, '_slfd_notes', $notes );
    update_post_meta( $post_id, '_slfd_request', $request );
    update_post_meta( $post_id, '_slfd_updated_at', current_time( 'timestamp' ) );

    slfd_flash( 'success', 0 === ( $closing - $expected )
        ? 'Rapport enregistré et transmis pour validation.'
        : 'Rapport enregistré. L’écart de stock sera signalé au gestionnaire lors de la validation.'
    );
    wp_safe_redirect( slfd_can_view_dashboard() ? slfd_dashboard_url() : slfd_report_url() );
    exit;
}

/** Enregistre une dotation faite par le responsable du programme fidelite. */
add_action( 'admin_post_slfd_create_supply', 'slfd_create_supply' );
function slfd_create_supply() {
    if ( ! slfd_can_supply() ) {
        wp_die( 'Accès refusé.', 403 );
    }
    check_admin_referer( 'slfd_create_supply' );

    $date = isset( $_POST['slfd_date'] ) ? sanitize_text_field( wp_unslash( $_POST['slfd_date'] ) ) : '';
    $date_object = DateTime::createFromFormat( 'Y-m-d', $date, wp_timezone() );
    $agency = ! empty( $_POST['slfd_agency'] ) ? get_term_by( 'slug', sanitize_title( wp_unslash( $_POST['slfd_agency'] ) ), 'sl_agence_promo' ) : null;
    $quantity = absint( $_POST['slfd_quantity'] ?? 0 );
    $reference = sanitize_text_field( wp_unslash( $_POST['slfd_reference'] ?? '' ) );
    $notes = sanitize_textarea_field( wp_unslash( $_POST['slfd_notes'] ?? '' ) );

    if ( ! $date_object || $date_object->format( 'Y-m-d' ) !== $date || $date > current_time( 'Y-m-d' ) || ! $agency || is_wp_error( $agency ) || ! $quantity ) {
        slfd_flash( 'error', 'Indiquez une agence, une quantité supérieure à zéro et une date valide.' );
        wp_safe_redirect( slfd_supply_url() );
        exit;
    }
    $report = slfd_report_for_agency_date( $agency->slug, $date );
    if ( $report && 'publish' === $report->post_status ) {
        slfd_flash( 'error', 'Le rapport de cette agence est déjà validé pour cette date. Enregistrez l’approvisionnement avant la validation du rapport.' );
        wp_safe_redirect( slfd_supply_url() );
        exit;
    }

    $supply_id = wp_insert_post( [
        'post_type'   => SLFD_SUPPLY_POST_TYPE,
        'post_status' => 'publish',
        'post_title'  => sprintf( 'Approvisionnement fidélité — %s — %s', $agency->name, $date ),
        'post_author' => get_current_user_id(),
    ], true );
    if ( is_wp_error( $supply_id ) || ! $supply_id ) {
        slfd_flash( 'error', 'L’approvisionnement n’a pas pu être enregistré. Réessayez dans un instant.' );
        wp_safe_redirect( slfd_supply_url() );
        exit;
    }
    update_post_meta( $supply_id, '_slfd_agency', $agency->slug );
    update_post_meta( $supply_id, '_slfd_date', $date );
    update_post_meta( $supply_id, '_slfd_quantity', $quantity );
    update_post_meta( $supply_id, '_slfd_reference', $reference );
    update_post_meta( $supply_id, '_slfd_notes', $notes );
    update_post_meta( $supply_id, '_slfd_created_at', current_time( 'timestamp' ) );
    slfd_flash( 'success', sprintf( '%d cartes ont été ajoutées à l’approvisionnement de %s.', $quantity, $agency->name ) );
    wp_safe_redirect( slfd_dashboard_url() );
    exit;
}

add_action( 'admin_post_slfd_validate_report', 'slfd_validate_report' );
function slfd_validate_report() {
    if ( ! slfd_can_validate() ) {
        wp_die( 'Accès refusé.', 403 );
    }
    $report_id = absint( $_POST['report_id'] ?? 0 );
    check_admin_referer( 'slfd_validate_' . $report_id );
    $report = get_post( $report_id );
    if ( ! $report || SLFD_POST_TYPE !== $report->post_type || 'pending' !== $report->post_status ) {
        slfd_flash( 'error', 'Ce rapport ne peut plus être validé.' );
    } else {
        wp_update_post( [ 'ID' => $report_id, 'post_status' => 'publish' ] );
        update_post_meta( $report_id, '_slfd_validated_by', get_current_user_id() );
        update_post_meta( $report_id, '_slfd_validated_at', current_time( 'timestamp' ) );
        slfd_flash( 'success', 'Le rapport est validé et apparaît maintenant dans le classement.' );
    }
    wp_safe_redirect( slfd_dashboard_url() );
    exit;
}

function slfd_dashboard_rows( $date ) {
    $terms = get_terms( [ 'taxonomy' => 'sl_agence_promo', 'hide_empty' => false, 'orderby' => 'name' ] );
    if ( is_wp_error( $terms ) ) {
        return [];
    }
    $reports = new WP_Query( [
        'post_type'      => SLFD_POST_TYPE,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_key'       => '_slfd_date',
        'meta_value'     => $date,
        'orderby'        => 'ID',
        'order'          => 'DESC',
    ] );
    $by_agency = [];
    foreach ( $reports->posts as $report ) {
        $by_agency[ (string) get_post_meta( $report->ID, '_slfd_agency', true ) ] = $report;
    }

    $rows = [];
    foreach ( $terms as $term ) {
        $report = $by_agency[ $term->slug ] ?? null;
        $rows[] = [
            'agency'  => $term,
            'report'  => $report,
            'enrollments' => $report ? slfd_meta_int( $report->ID, '_slfd_enrollments' ) : null,
            'stock'   => $report ? slfd_meta_int( $report->ID, '_slfd_closing' ) : null,
        ];
    }
    usort( $rows, function ( $a, $b ) {
        if ( ! $a['report'] && ! $b['report'] ) return strcasecmp( $a['agency']->name, $b['agency']->name );
        if ( ! $a['report'] ) return 1;
        if ( ! $b['report'] ) return -1;
        return $b['enrollments'] <=> $a['enrollments'] ?: strcasecmp( $a['agency']->name, $b['agency']->name );
    } );
    $rank = 0;
    foreach ( $rows as &$row ) {
        $row['rank'] = $row['report'] ? ++$rank : null;
    }
    return $rows;
}

function slfd_pending_reports() {
    return get_posts( [
        'post_type'      => SLFD_POST_TYPE,
        'post_status'    => 'pending',
        'posts_per_page' => 30,
        'orderby'        => 'modified',
        'order'          => 'DESC',
    ] );
}

function slfd_render_login() {
    ob_start(); ?>
    <main class="slfd-login-wrap">
        <section class="slfd-login-card">
            <span class="dashicons dashicons-lock"></span>
            <p class="slfd-eyebrow">Complexe Santa Lucia</p>
            <h1>Espace interne Fidélité</h1>
            <p>Connectez-vous avec votre compte professionnel pour consulter les rapports et le classement des agences.</p>
            <?php wp_login_form( [ 'redirect' => esc_url_raw( get_permalink() ), 'label_log_in' => 'Se connecter' ] ); ?>
        </section>
    </main>
    <?php return ob_get_clean();
}

function slfd_render_access_denied() {
    return '<main class="slfd-login-wrap"><section class="slfd-login-card"><span class="dashicons dashicons-shield"></span><p class="slfd-eyebrow">Accès protégé</p><h1>Accès non autorisé</h1><p>Ce tableau de bord est réservé aux responsables et gestionnaires des agences Santa Lucia.</p></section></main>';
}

function slfd_render_dashboard() {
    $date = current_time( 'Y-m-d' );
    if ( ! empty( $_GET['date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['date'] ) ) {
        $date = sanitize_text_field( wp_unslash( $_GET['date'] ) );
    }
    $rows    = slfd_dashboard_rows( $date );
    $pending = slfd_pending_reports();
    $supplies = slfd_supplies_for_date( $date );
    $flash   = slfd_flash( 'read' );
    $alerts  = array_filter( $rows, function ( $row ) { return $row['report'] && $row['stock'] <= 50; } );
    $network = array_filter( $rows, function ( $row ) {
        return $row['report'] && in_array( 'network', (array) get_post_meta( $row['report']->ID, '_slfd_issues', true ), true );
    } );
    ob_start(); ?>
    <main class="slfd-shell">
        <header class="slfd-topbar">
            <a class="slfd-brand" href="<?php echo esc_url( slfd_dashboard_url() ); ?>"><span class="dashicons dashicons-cart"></span><span><small>Complexe</small>Santa Lucia</span></a>
            <span class="slfd-internal"><span class="dashicons dashicons-lock"></span> Espace interne</span>
            <time datetime="<?php echo esc_attr( $date ); ?>"><span class="dashicons dashicons-calendar-alt"></span><?php echo esc_html( date_i18n( 'l j F Y', strtotime( $date ) ) ); ?></time>
        </header>
        <section class="slfd-content">
            <?php if ( $flash ) : ?><div class="slfd-flash slfd-flash--<?php echo esc_attr( $flash[0] ); ?>"><?php echo esc_html( $flash[1] ); ?></div><?php endif; ?>
            <div class="slfd-heading">
                <div><p class="slfd-eyebrow">Programme cartes de fidélité</p><h1>Tableau de bord Fidélité</h1><p>Suivi quotidien des enrôlements et du stock de cartes par agence.</p></div>
                <div class="slfd-heading-actions"><?php if ( slfd_can_supply() ) : ?><a class="slfd-secondary" href="<?php echo esc_url( slfd_supply_url() ); ?>"><span class="dashicons dashicons-plus-alt"></span>Approvisionner une agence</a><?php endif; ?><a class="slfd-primary" href="<?php echo esc_url( slfd_report_url() ); ?>"><span class="dashicons dashicons-edit-page"></span>Saisir le rapport du jour</a></div>
            </div>
            <div class="slfd-layout">
                <section class="slfd-table-card" aria-labelledby="slfd-ranking-title">
                    <div class="slfd-table-head"><h2 id="slfd-ranking-title">Classement du jour</h2><form method="get"><label for="slfd-date">Date</label><input id="slfd-date" type="date" name="date" value="<?php echo esc_attr( $date ); ?>"><button type="submit">Afficher</button></form></div>
                    <div class="slfd-table-scroll"><table><thead><tr><th>Rang</th><th>Agence</th><th>Enrôlements validés</th><th>Stock restant</th></tr></thead><tbody>
                    <?php if ( ! $rows ) : ?><tr><td colspan="4" class="slfd-empty">Aucune agence n’est encore configurée.</td></tr><?php endif; ?>
                    <?php foreach ( $rows as $row ) : $state = null !== $row['stock'] ? slfd_stock_state( $row['stock'] ) : null; ?>
                        <tr class="<?php echo $row['report'] ? '' : 'slfd-row-missing'; ?>">
                            <td><?php if ( $row['rank'] ) : ?><span class="slfd-rank slfd-rank--<?php echo (int) min( 3, $row['rank'] ); ?>"><?php echo (int) $row['rank']; ?></span><?php else : ?>—<?php endif; ?></td>
                            <td><strong><?php echo esc_html( $row['agency']->name ); ?></strong><?php if ( ! $row['report'] ) : ?><span>Rapport non reçu</span><?php endif; ?></td>
                            <td><?php echo null !== $row['enrollments'] ? '<strong class="slfd-number">' . (int) $row['enrollments'] . '</strong>' : '—'; ?></td>
                            <td><?php if ( $state ) : ?><strong class="slfd-stock slfd-stock--<?php echo esc_attr( $state[1] ); ?>"><?php echo (int) $row['stock']; ?></strong><span class="slfd-stock-label"><?php echo esc_html( $state[0] ); ?></span><?php else : ?>—<?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?></tbody></table></div>
                    <p class="slfd-footnote"><span class="dashicons dashicons-info-outline"></span>Seuls les rapports validés sont pris en compte dans le classement.</p>
                </section>
                <aside class="slfd-alerts">
                    <section class="slfd-alert-card"><h2><span class="dashicons dashicons-warning"></span>Alertes stock</h2>
                        <?php if ( $alerts ) : foreach ( $alerts as $row ) : $state = slfd_stock_state( $row['stock'] ); ?><div class="slfd-alert-row"><strong><?php echo esc_html( $row['agency']->name ); ?></strong><span class="slfd-pill slfd-pill--<?php echo esc_attr( $state[1] ); ?>"><?php echo esc_html( $state[0] ); ?></span><b><?php echo (int) $row['stock']; ?> cartes</b></div><?php endforeach; else : ?><p class="slfd-muted">Aucune agence n’a signalé de stock faible aujourd’hui.</p><?php endif; ?>
                    </section>
                    <section class="slfd-alert-card"><h2><span class="dashicons dashicons-rss"></span>Incidents réseau</h2>
                        <?php if ( $network ) : ?><p><?php echo esc_html( count( $network ) ); ?> agence(s) ont signalé un réseau instable : <strong><?php echo esc_html( implode( ', ', array_map( function ( $row ) { return $row['agency']->name; }, $network ) ) ); ?></strong>.</p><?php else : ?><p class="slfd-muted">Aucun incident réseau signalé dans les rapports validés.</p><?php endif; ?>
                    </section>
                    <section class="slfd-alert-card"><h2><span class="dashicons dashicons-archive"></span>Approvisionnements</h2>
                        <?php if ( $supplies ) : ?><p><strong><?php echo (int) array_sum( array_map( function ( $supply ) { return slfd_meta_int( $supply->ID, '_slfd_quantity' ); }, $supplies ) ); ?> cartes</strong> approvisionnées aujourd’hui.</p><?php foreach ( array_slice( $supplies, 0, 4 ) as $supply ) : ?><div class="slfd-alert-row"><strong><?php echo esc_html( slfd_agency_name( get_post_meta( $supply->ID, '_slfd_agency', true ) ) ); ?></strong><b>+<?php echo (int) slfd_meta_int( $supply->ID, '_slfd_quantity' ); ?> cartes</b></div><?php endforeach; else : ?><p class="slfd-muted">Aucun approvisionnement enregistré pour cette date.</p><?php endif; ?>
                    </section>
                </aside>
            </div>
            <?php if ( slfd_can_validate() && $pending ) : ?><section class="slfd-pending"><h2>Rapports à valider</h2><?php foreach ( $pending as $report ) : $delta = (int) get_post_meta( $report->ID, '_slfd_stock_delta', true ); ?><article><div><strong><?php echo esc_html( slfd_agency_name( get_post_meta( $report->ID, '_slfd_agency', true ) ) ); ?></strong><span><?php echo esc_html( get_post_meta( $report->ID, '_slfd_date', true ) ); ?> · <?php echo (int) slfd_meta_int( $report->ID, '_slfd_enrollments' ); ?> enrôlements</span><?php if ( $delta ) : ?><em>Écart de stock : <?php echo esc_html( $delta > 0 ? '+' . $delta : (string) $delta ); ?></em><?php endif; ?></div><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="slfd_validate_report"><input type="hidden" name="report_id" value="<?php echo (int) $report->ID; ?>"><?php wp_nonce_field( 'slfd_validate_' . $report->ID ); ?><button type="submit">Valider</button></form></article><?php endforeach; ?></section><?php endif; ?>
        </section>
    </main>
    <?php return ob_get_clean();
}

function slfd_render_report_form() {
    $agency = slfd_current_agency();
    $flash  = slfd_flash( 'read' );
    $terms  = slfd_can_validate() ? get_terms( [ 'taxonomy' => 'sl_agence_promo', 'hide_empty' => false, 'orderby' => 'name' ] ) : [];
    $today_supply = $agency ? slfd_supplies_total( $agency->slug, current_time( 'Y-m-d' ) ) : 0;
    ob_start(); ?>
    <main class="slfd-shell"><header class="slfd-topbar"><a class="slfd-brand" href="<?php echo esc_url( slfd_dashboard_url() ); ?>"><span class="dashicons dashicons-cart"></span><span><small>Complexe</small>Santa Lucia</span></a><span class="slfd-internal"><span class="dashicons dashicons-lock"></span> Espace interne</span><a class="slfd-top-link" href="<?php echo esc_url( slfd_dashboard_url() ); ?>">Voir le classement</a></header>
    <section class="slfd-content slfd-report-page"><?php if ( $flash ) : ?><div class="slfd-flash slfd-flash--<?php echo esc_attr( $flash[0] ); ?>"><?php echo esc_html( $flash[1] ); ?></div><?php endif; ?>
        <div class="slfd-heading"><div><p class="slfd-eyebrow">Programme cartes de fidélité</p><h1>Déclarer le rapport du jour</h1><p>Le classement est actualisé uniquement après validation par un gestionnaire.</p></div></div>
        <?php if ( ! $agency && ! slfd_can_validate() ) : ?><div class="slfd-notice">Votre compte n’est rattaché à aucune agence. Demandez à un administrateur de compléter votre profil.</div><?php else : ?>
        <form class="slfd-report-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="slfd_submit_report"><?php wp_nonce_field( 'slfd_submit_report' ); ?>
            <section><h2>Identification</h2><div class="slfd-form-grid"><p><label for="slfd_date">Date du rapport</label><input id="slfd_date" type="date" name="slfd_date" max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required></p>
            <?php if ( slfd_can_validate() ) : ?><p><label for="slfd_agency">Agence</label><select id="slfd_agency" name="slfd_agency" required><?php foreach ( $terms as $term ) : ?><option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $agency && $agency->slug, $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option><?php endforeach; ?></select></p><?php else : ?><p><label>Agence</label><input value="<?php echo esc_attr( $agency->name ); ?>" readonly></p><?php endif; ?></div></section>
            <section><h2>Mouvement des cartes</h2><p class="slfd-form-help">Saisissez uniquement les mouvements de la journée. Les cartes historiques déjà attribuées ne doivent pas être ajoutées ici.</p><div class="slfd-form-grid slfd-form-grid--four"><p><label for="slfd_opening">Stock utilisable au début</label><input id="slfd_opening" type="number" name="slfd_opening" min="0" required></p><p class="slfd-auto-field"><label>Cartes approvisionnées</label><output>+<?php echo (int) $today_supply; ?> carte(s)</output><small>Enregistrées par le responsable fidélité. Le total est recalculé selon la date choisie lors de l’envoi.</small></p><p><label for="slfd_enrollments">Enrôlements validés</label><input id="slfd_enrollments" type="number" name="slfd_enrollments" min="0" required></p><p><label for="slfd_damaged">Cartes endommagées aujourd’hui</label><input id="slfd_damaged" type="number" name="slfd_damaged" min="0" value="0" required></p></div><p><label for="slfd_closing">Stock réel en fin de journée</label><input id="slfd_closing" type="number" name="slfd_closing" min="0" required><small>Contrôle automatique : début + approvisionnements − enrôlements − endommagées.</small></p></section>
            <section><h2>Difficultés et besoins</h2><div class="slfd-checks"><label><input type="checkbox" name="slfd_issues[]" value="network"> Réseau instable</label><label><input type="checkbox" name="slfd_issues[]" value="defective_cards"> Cartes défectueuses</label><label><input type="checkbox" name="slfd_issues[]" value="stock"> Risque de rupture de stock</label><label><input type="checkbox" name="slfd_issues[]" value="other"> Autre difficulté</label></div><p><label for="slfd_notes">Détails des difficultés</label><textarea id="slfd_notes" name="slfd_notes" rows="4" placeholder="Ex. réseau instable, série CF21000 non fonctionnelle…"></textarea></p><p><label for="slfd_request">Demande ou recommandation</label><textarea id="slfd_request" name="slfd_request" rows="4" placeholder="Ex. besoin de réapprovisionnement avant le week-end…"></textarea></p></section>
            <div class="slfd-form-actions"><a href="<?php echo esc_url( slfd_dashboard_url() ); ?>">Annuler</a><button type="submit">Transmettre pour validation</button></div>
        </form><?php endif; ?></section></main>
    <?php return ob_get_clean();
}

function slfd_render_supply_form() {
    $flash = slfd_flash( 'read' );
    $terms = get_terms( [ 'taxonomy' => 'sl_agence_promo', 'hide_empty' => false, 'orderby' => 'name' ] );
    ob_start(); ?>
    <main class="slfd-shell"><header class="slfd-topbar"><a class="slfd-brand" href="<?php echo esc_url( slfd_dashboard_url() ); ?>"><span class="dashicons dashicons-cart"></span><span><small>Complexe</small>Santa Lucia</span></a><span class="slfd-internal"><span class="dashicons dashicons-lock"></span> Espace interne</span><a class="slfd-top-link" href="<?php echo esc_url( slfd_dashboard_url() ); ?>">Voir le classement</a></header>
    <section class="slfd-content slfd-report-page"><?php if ( $flash ) : ?><div class="slfd-flash slfd-flash--<?php echo esc_attr( $flash[0] ); ?>"><?php echo esc_html( $flash[1] ); ?></div><?php endif; ?>
        <div class="slfd-heading"><div><p class="slfd-eyebrow">Programme cartes de fidélité</p><h1>Approvisionner une agence</h1><p>Cette opération alimente automatiquement le rapport quotidien de l’agence concernée.</p></div></div>
        <form class="slfd-report-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="slfd_create_supply"><?php wp_nonce_field( 'slfd_create_supply' ); ?>
            <section><h2>Dotation de cartes</h2><div class="slfd-form-grid"><p><label for="slfd_date">Date de l’approvisionnement</label><input id="slfd_date" type="date" name="slfd_date" max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required></p><p><label for="slfd_agency">Agence bénéficiaire</label><select id="slfd_agency" name="slfd_agency" required><option value="">Choisissez une agence…</option><?php foreach ( $terms as $term ) : ?><option value="<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></option><?php endforeach; ?></select></p><p><label for="slfd_quantity">Nombre de cartes remises</label><input id="slfd_quantity" type="number" name="slfd_quantity" min="1" required></p><p><label for="slfd_reference">Référence / série de cartes</label><input id="slfd_reference" name="slfd_reference" placeholder="Ex. CF21000…"></p></div></section><section><h2>Observation</h2><p><label for="slfd_notes">Note de remise (facultatif)</label><textarea id="slfd_notes" name="slfd_notes" rows="4" placeholder="Ex. remise effectuée à la responsable de Ngousso…"></textarea></p></section><div class="slfd-form-actions"><a href="<?php echo esc_url( slfd_dashboard_url() ); ?>">Annuler</a><button type="submit">Enregistrer l’approvisionnement</button></div></form>
    </section></main>
    <?php return ob_get_clean();
}
