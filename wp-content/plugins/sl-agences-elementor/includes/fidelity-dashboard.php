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
		'sl_superviseur_fidelite' => [
			'label' => 'Superviseur fidélité',
			'caps'  => [ 'read' => true, 'slfd_supervise_fidelity' => true ],
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
        'login' => [
            'slug'    => 'connexion-fidelite',
            'title'   => 'Connexion Fidélité',
            'screen'  => 'login',
        ],
        'agents' => [
            'slug'    => 'gerer-agents-fidelite',
            'title'   => 'Gérer les agents Fidélité',
            'screen'  => 'agents',
        ],
        'campaigns' => [
            'slug'    => 'campagnes-fidelite',
            'title'   => 'Campagnes Fidélité',
            'screen'  => 'campaigns',
        ],
        'campaign_public' => [
            'slug'    => 'suivi-campagne-fidelite',
            'title'   => 'Suivi campagne Fidélité',
            'screen'  => 'campaign_public',
        ],
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
    return $id && in_array( get_post_meta( $id, '_slfd_internal_page', true ), [ 'login', 'agents', 'campaigns', 'dashboard', 'report', 'supply' ], true );
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
    $ids = array_filter( [ slfd_page_id( 'login' ), slfd_page_id( 'agents' ), slfd_page_id( 'campaigns' ), slfd_page_id( 'dashboard' ), slfd_page_id( 'report' ), slfd_page_id( 'supply' ), slfd_page_id( 'campaign_public' ) ] );
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
    return is_user_logged_in() && ( current_user_can( 'manage_options' ) || current_user_can( 'edit_others_posts' ) || current_user_can( 'slfd_supervise_fidelity' ) || slfd_user_has_role( [ 'sl_agent_fidelite', 'sl_responsable_fidelite', 'sl_superviseur_fidelite', 'sl_responsable_agence', 'sl_gestionnaire_bons_plans' ] ) );
}

function slfd_can_view_dashboard() {
    return current_user_can( 'manage_options' ) || current_user_can( 'edit_others_posts' ) || current_user_can( 'slfd_supervise_fidelity' ) || slfd_user_has_role( [ 'sl_responsable_fidelite', 'sl_superviseur_fidelite', 'sl_responsable_agence', 'sl_gestionnaire_bons_plans' ] );
}

function slfd_can_validate() {
    return current_user_can( 'manage_options' ) || current_user_can( 'edit_others_posts' ) || current_user_can( 'slfd_supervise_fidelity' ) || slfd_user_has_role( [ 'sl_responsable_fidelite', 'sl_superviseur_fidelite', 'sl_gestionnaire_bons_plans' ] );
}

/** Only the fidelity coordinator can provision new agent accounts. */
function slfd_can_manage_agents() {
    return current_user_can( 'manage_options' ) || current_user_can( 'slfd_supervise_fidelity' ) || slfd_user_has_role( [ 'sl_superviseur_fidelite' ] );
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

function slfd_login_url() {
    $id = slfd_page_id( 'login' );
    return $id ? get_permalink( $id ) : home_url( '/connexion-fidelite/' );
}

function slfd_logout_url() {
    return wp_logout_url( slfd_login_url() );
}

function slfd_agents_url() {
    $id = slfd_page_id( 'agents' );
    return $id ? get_permalink( $id ) : home_url( '/gerer-agents-fidelite/' );
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

    if ( array_intersect( [ 'sl_responsable_fidelite', 'sl_superviseur_fidelite' ], (array) $user->roles ) ) {
        return slfd_dashboard_url();
    }

    return $redirect_to;
}
add_filter( 'login_redirect', 'slfd_login_redirect', 30, 3 );

function slfd_woocommerce_login_redirect( $redirect, $user ) {
    return slfd_login_redirect( $redirect, '', $user );
}
add_filter( 'woocommerce_login_redirect', 'slfd_woocommerce_login_redirect', 30, 2 );

/** Agents use only the dedicated front-office, never the WordPress back-office. */
function slfd_is_frontend_agent( $user = null ) {
    $user = $user instanceof WP_User ? $user : wp_get_current_user();
    if ( ! $user || ! $user->exists() || ! in_array( 'sl_agent_fidelite', (array) $user->roles, true ) ) {
        return false;
    }
    return ! current_user_can( 'edit_posts' ) && ! current_user_can( 'manage_options' );
}

add_action( 'admin_init', 'slfd_redirect_agent_from_admin', 1 );
function slfd_redirect_agent_from_admin() {
    if ( ! slfd_is_frontend_agent() || wp_doing_ajax() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
        return;
    }
    if ( 'admin-post.php' === basename( $_SERVER['PHP_SELF'] ?? '' ) ) {
        return;
    }
    wp_safe_redirect( slfd_report_url() );
    exit;
}

add_filter( 'show_admin_bar', 'slfd_hide_admin_bar_for_agents' );
function slfd_hide_admin_bar_for_agents( $show ) {
    return slfd_is_frontend_agent() ? false : $show;
}

add_action( 'template_redirect', 'slfd_redirect_authenticated_login_page', 2 );
function slfd_redirect_authenticated_login_page() {
    if ( ! slfd_is_internal_page() || 'login' !== get_post_meta( get_queried_object_id(), '_slfd_internal_page', true ) || ! is_user_logged_in() ) {
        return;
    }
    wp_safe_redirect( slfd_can_view_dashboard() ? slfd_dashboard_url() : slfd_report_url() );
    exit;
}

/**
 * Adds a dedicated, permission-aware entry in the WordPress administration.
 * The loyalty screens remain the protected internal pages, while this menu
 * gives programme users a clear way to reach the appropriate screen.
 */
add_action( 'admin_menu', 'slfd_register_admin_menu', 80 );
function slfd_register_admin_menu() {
    if ( ! slfd_can_access() ) {
        return;
    }

    add_menu_page(
        __( 'Fidélité', 'sl-agences' ),
        __( 'Fidélité', 'sl-agences' ),
        'read',
        'slfd-fidelity',
        'slfd_render_admin_menu_placeholder',
        'dashicons-awards',
        58
    );

    if ( slfd_can_view_dashboard() ) {
        add_submenu_page(
            'slfd-fidelity',
            __( 'Tableau de bord fidélité', 'sl-agences' ),
            __( 'Tableau de bord', 'sl-agences' ),
            'read',
            'slfd-fidelity-dashboard',
            'slfd_render_admin_menu_placeholder'
        );
    }

    add_submenu_page(
        'slfd-fidelity',
        __( 'Déclarer un rapport fidélité', 'sl-agences' ),
        __( 'Déclarer un rapport', 'sl-agences' ),
        'read',
        'slfd-fidelity-report',
        'slfd_render_admin_menu_placeholder'
    );

    if ( slfd_can_supply() ) {
        add_submenu_page(
            'slfd-fidelity',
            __( 'Approvisionner une agence', 'sl-agences' ),
            __( 'Approvisionner les cartes', 'sl-agences' ),
            'read',
            'slfd-fidelity-supply',
            'slfd_render_admin_menu_placeholder'
        );
    }

    if ( slfd_can_validate() ) {
        add_submenu_page(
            'slfd-fidelity',
            __( 'Historique et exports fidélité', 'sl-agences' ),
            __( 'Historique et exports', 'sl-agences' ),
            'read',
            'slfd-fidelity-history',
            'slfd_render_admin_menu_placeholder'
        );
    }

    if ( slfd_can_manage_agents() ) {
        add_submenu_page(
            'slfd-fidelity',
            __( 'Campagnes Fidélité', 'sl-agences' ),
            __( 'Campagnes', 'sl-agences' ),
            'read',
            'slfd-fidelity-campaigns',
            'slfd_render_admin_menu_placeholder'
        );
        add_submenu_page(
            'slfd-fidelity',
            __( 'Gérer les agents Fidélité', 'sl-agences' ),
            __( 'Gérer les agents', 'sl-agences' ),
            'read',
            'slfd-fidelity-agents',
            'slfd_render_admin_menu_placeholder'
        );
    }
}

/** Redirect menu endpoints before WordPress starts rendering the admin page. */
add_action( 'admin_init', 'slfd_redirect_admin_menu_page' );
function slfd_redirect_admin_menu_page() {
    if ( ! is_admin() || empty( $_GET['page'] ) ) {
        return;
    }

    $page = sanitize_key( wp_unslash( $_GET['page'] ) );
    $targets = [
        'slfd-fidelity-report'    => slfd_report_url(),
        'slfd-fidelity-supply'    => slfd_supply_url(),
        'slfd-fidelity-dashboard' => slfd_dashboard_url(),
        'slfd-fidelity-history'   => slfd_dashboard_url() . '#analyses-historique',
        'slfd-fidelity-agents'    => slfd_agents_url(),
        'slfd-fidelity-campaigns' => slfd_campaigns_url(),
    ];

    if ( 'slfd-fidelity' === $page ) {
        $targets[ $page ] = slfd_can_view_dashboard() ? slfd_dashboard_url() : slfd_report_url();
    }

    if ( ! isset( $targets[ $page ] ) ) {
        return;
    }

    if ( ! slfd_can_access() ) {
        wp_die( esc_html__( 'Accès refusé.', 'sl-agences' ), '', [ 'response' => 403 ] );
    }

    if ( 'slfd-fidelity-supply' === $page && ! slfd_can_supply() ) {
        wp_die( esc_html__( 'Accès refusé.', 'sl-agences' ), '', [ 'response' => 403 ] );
    }

    if ( in_array( $page, [ 'slfd-fidelity-dashboard', 'slfd-fidelity-history' ], true ) && ! slfd_can_view_dashboard() ) {
        wp_die( esc_html__( 'Accès refusé.', 'sl-agences' ), '', [ 'response' => 403 ] );
    }

    if ( 'slfd-fidelity-agents' === $page && ! slfd_can_manage_agents() ) {
        wp_die( esc_html__( 'Accès refusé.', 'sl-agences' ), '', [ 'response' => 403 ] );
    }

    if ( 'slfd-fidelity-campaigns' === $page && ! slfd_can_manage_agents() ) {
        wp_die( esc_html__( 'Accès refusé.', 'sl-agences' ), '', [ 'response' => 403 ] );
    }

    wp_safe_redirect( $targets[ $page ] );
    exit;
}

function slfd_render_admin_menu_placeholder() {
    // The admin_init redirect above always handles these menu endpoints.
}

add_action( 'wp_enqueue_scripts', 'slfd_enqueue_assets', 110 );
function slfd_enqueue_assets() {
    if ( ! slfd_is_internal_page() && ! slfd_is_campaign_public_page() ) {
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
    $print_file = SL_AGENCES_PATH . 'assets/css/fidelity-print.css';
    wp_enqueue_style(
        'sl-fidelity-print',
        SL_AGENCES_URL . 'assets/css/fidelity-print.css',
        [ 'sl-fidelity-dashboard' ],
        file_exists( $print_file ) ? (string) filemtime( $print_file ) : SL_AGENCES_VERSION
    );
    $report_v2_file = SL_AGENCES_PATH . 'assets/css/fidelity-report-v2.css';
    wp_enqueue_style(
        'sl-fidelity-report-v2',
        SL_AGENCES_URL . 'assets/css/fidelity-report-v2.css',
        [ 'sl-fidelity-dashboard', 'sl-fidelity-supply' ],
        file_exists( $report_v2_file ) ? (string) filemtime( $report_v2_file ) : SL_AGENCES_VERSION
    );
    $mobile_file = SL_AGENCES_PATH . 'assets/css/fidelity-mobile.css';
    wp_enqueue_style(
        'sl-fidelity-mobile',
        SL_AGENCES_URL . 'assets/css/fidelity-mobile.css',
        [ 'sl-fidelity-dashboard', 'sl-fidelity-supply', 'sl-fidelity-report-v2' ],
        file_exists( $mobile_file ) ? (string) filemtime( $mobile_file ) : SL_AGENCES_VERSION
    );
    $campaign_file = SL_AGENCES_PATH . 'assets/css/fidelity-campaign.css';
    wp_enqueue_style(
        'sl-fidelity-campaign',
        SL_AGENCES_URL . 'assets/css/fidelity-campaign.css',
        [ 'sl-fidelity-dashboard', 'sl-fidelity-mobile' ],
        file_exists( $campaign_file ) ? (string) filemtime( $campaign_file ) : SL_AGENCES_VERSION
    );
    $report_js_file = SL_AGENCES_PATH . 'assets/js/fidelity-report.js';
    wp_enqueue_script(
        'sl-fidelity-report',
        SL_AGENCES_URL . 'assets/js/fidelity-report.js',
        [],
        file_exists( $report_js_file ) ? (string) filemtime( $report_js_file ) : SL_AGENCES_VERSION,
        true
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

add_action( 'admin_post_slfd_create_agent', 'slfd_create_agent' );
function slfd_create_agent() {
    if ( ! slfd_can_manage_agents() ) {
        wp_die( 'Accès refusé.', 403 );
    }
    check_admin_referer( 'slfd_create_agent' );

    $login      = sanitize_user( wp_unslash( $_POST['slfd_agent_login'] ?? '' ), true );
    $first_name = sanitize_text_field( wp_unslash( $_POST['slfd_agent_first_name'] ?? '' ) );
    $email      = sanitize_email( wp_unslash( $_POST['slfd_agent_email'] ?? '' ) );
    $password   = (string) ( $_POST['slfd_agent_password'] ?? '' );
    $agency     = sanitize_title( wp_unslash( $_POST['slfd_agent_agency'] ?? '' ) );
    $term       = $agency ? get_term_by( 'slug', $agency, 'sl_agence_promo' ) : null;

    if ( '' === $login || ! validate_username( $login ) || username_exists( $login ) ) {
        slfd_flash( 'error', 'Choisissez un identifiant disponible et valide.' );
    } elseif ( ! is_email( $email ) || email_exists( $email ) ) {
        slfd_flash( 'error', 'Indiquez une adresse e-mail valide qui n’est pas déjà utilisée.' );
    } elseif ( strlen( $password ) < 8 ) {
        slfd_flash( 'error', 'Le mot de passe doit contenir au moins 8 caractères.' );
    } elseif ( ! $term || is_wp_error( $term ) ) {
        slfd_flash( 'error', 'Sélectionnez une agence valide pour cet agent.' );
    } else {
        // The role is deliberately fixed: this screen can never create an administrator.
        $user_id = wp_insert_user( [
            'user_login' => $login,
            'user_email' => $email,
            'user_pass'  => $password,
            'first_name' => $first_name,
            'role'       => 'sl_agent_fidelite',
        ] );
        if ( is_wp_error( $user_id ) ) {
            slfd_flash( 'error', $user_id->get_error_message() );
        } else {
            update_user_meta( $user_id, 'sl_agence_assignee', $term->name );
            update_user_meta( $user_id, '_slfd_created_by', get_current_user_id() );
            slfd_flash( 'success', sprintf( 'Le compte agent %s a été créé et rattaché à %s.', $login, $term->name ) );
        }
    }

    wp_safe_redirect( slfd_agents_url() );
    exit;
}

add_action( 'admin_post_slfd_delete_agent', 'slfd_delete_agent' );
function slfd_delete_agent() {
    if ( ! slfd_can_manage_agents() ) {
        wp_die( 'Accès refusé.', 403 );
    }

    $agent_id = absint( $_POST['agent_id'] ?? 0 );
    check_admin_referer( 'slfd_delete_agent_' . $agent_id );
    $agent = get_userdata( $agent_id );
    $is_created_by_current_user = (int) get_user_meta( $agent_id, '_slfd_created_by', true ) === get_current_user_id();
    $has_reports = (bool) get_posts( [
        'post_type'      => SLFD_POST_TYPE,
        'post_status'    => [ 'pending', 'publish' ],
        'author'         => $agent_id,
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ] );

    if ( ! $agent || ! in_array( 'sl_agent_fidelite', (array) $agent->roles, true ) || ! $is_created_by_current_user ) {
        slfd_flash( 'error', 'Vous pouvez supprimer uniquement les comptes agents que vous avez créés.' );
    } elseif ( $has_reports ) {
        slfd_flash( 'error', 'Ce compte possède déjà des rapports. Il ne peut pas être supprimé afin de préserver l’historique.' );
    } else {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        if ( wp_delete_user( $agent_id ) ) {
            slfd_flash( 'success', sprintf( 'Le compte agent %s a été supprimé.', $agent->user_login ) );
        } else {
            slfd_flash( 'error', 'La suppression du compte a échoué. Réessayez.' );
        }
    }

    wp_safe_redirect( slfd_agents_url() );
    exit;
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
    $playstore   = absint( $_POST['slfd_playstore'] ?? 0 );
    $cards_distributed = absint( $_POST['slfd_cards_distributed'] ?? 0 );
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
    update_post_meta( $post_id, '_slfd_playstore', $playstore );
    update_post_meta( $post_id, '_slfd_cards_distributed', $cards_distributed );
    update_post_meta( $post_id, '_slfd_damaged', $damaged );
    update_post_meta( $post_id, '_slfd_closing', $closing );
    update_post_meta( $post_id, '_slfd_expected_closing', $expected );
    update_post_meta( $post_id, '_slfd_stock_delta', $closing - $expected );
    update_post_meta( $post_id, '_slfd_issues', $issues );
    update_post_meta( $post_id, '_slfd_notes', $notes );
    update_post_meta( $post_id, '_slfd_request', $request );
    update_post_meta( $post_id, '_slfd_updated_at', current_time( 'timestamp' ) );
    $campaign = slfd_campaign_for_date( $date );
    if ( $campaign ) {
        update_post_meta( $post_id, '_slfd_campaign_id', $campaign['id'] );
    } else {
        delete_post_meta( $post_id, '_slfd_campaign_id' );
    }

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
    $campaign = slfd_campaign_for_date( $date );
    if ( $campaign ) {
        update_post_meta( $supply_id, '_slfd_campaign_id', $campaign['id'] );
    }
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

/** Tous les rapports d'une journee, y compris ceux qui attendent validation. */
function slfd_reports_for_date( $date ) {
    return get_posts( [
        'post_type'      => SLFD_POST_TYPE,
        'post_status'    => [ 'pending', 'publish' ],
        'posts_per_page' => -1,
        'meta_key'       => '_slfd_date',
        'meta_value'     => $date,
        'orderby'        => 'modified',
        'order'          => 'DESC',
    ] );
}

function slfd_history_filters() {
	$today = current_time( 'Y-m-d' );
	$from = isset( $_GET['history_from'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['history_from'] ) ? sanitize_text_field( wp_unslash( $_GET['history_from'] ) ) : wp_date( 'Y-m-01' );
	$to = isset( $_GET['history_to'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['history_to'] ) ? sanitize_text_field( wp_unslash( $_GET['history_to'] ) ) : $today;
	if ( $from > $to ) { $from = $to; }
	return [
		'from' => $from, 'to' => min( $to, $today ),
		'agency' => isset( $_GET['history_agency'] ) ? sanitize_title( wp_unslash( $_GET['history_agency'] ) ) : '',
		'status' => isset( $_GET['history_status'] ) ? sanitize_key( wp_unslash( $_GET['history_status'] ) ) : '',
		'q' => isset( $_GET['history_q'] ) ? sanitize_text_field( wp_unslash( $_GET['history_q'] ) ) : '',
	];
}

function slfd_history_reports( $filters ) {
	$meta_query = [ [ 'key' => '_slfd_date', 'value' => [ $filters['from'], $filters['to'] ], 'compare' => 'BETWEEN', 'type' => 'DATE' ] ];
	if ( $filters['agency'] ) $meta_query[] = [ 'key' => '_slfd_agency', 'value' => $filters['agency'] ];
	$args = [ 'post_type' => SLFD_POST_TYPE, 'post_status' => $filters['status'] ? $filters['status'] : [ 'pending', 'publish' ], 'posts_per_page' => 200, 'meta_query' => $meta_query, 'orderby' => 'meta_value', 'meta_key' => '_slfd_date', 'order' => 'DESC' ];
	if ( $filters['q'] ) $args['s'] = $filters['q'];
	return get_posts( $args );
}

function slfd_history_summary( $reports ) {
	$summary = [ 'reports' => count( $reports ), 'validated' => 0, 'enrollments' => 0, 'supplies' => 0, 'delta' => 0, 'by_day' => [] ];
	foreach ( $reports as $report ) {
		$date = get_post_meta( $report->ID, '_slfd_date', true );
		$enrollments = slfd_meta_int( $report->ID, '_slfd_enrollments' );
		$summary['validated'] += 'publish' === $report->post_status ? 1 : 0;
		$summary['enrollments'] += $enrollments;
		$summary['supplies'] += slfd_meta_int( $report->ID, '_slfd_received' );
		$summary['delta'] += (int) get_post_meta( $report->ID, '_slfd_stock_delta', true );
		$summary['by_day'][ $date ] = (int) ( $summary['by_day'][ $date ] ?? 0 ) + $enrollments;
	}
	krsort( $summary['by_day'] );
	return $summary;
}

function slfd_history_export_url( $action, $filters ) {
	return add_query_arg( array_merge( [ 'action' => $action ], $filters ), admin_url( 'admin-post.php' ) );
}

add_action( 'admin_post_slfd_export_reports', 'slfd_export_reports' );
function slfd_export_reports() {
	if ( ! slfd_can_validate() ) wp_die( 'Accès refusé.', 403 );
	$reports = slfd_history_reports( slfd_history_filters() );
	nocache_headers(); header( 'Content-Type: text/csv; charset=utf-8' ); header( 'Content-Disposition: attachment; filename=rapports-fidelite-' . gmdate( 'Y-m-d' ) . '.csv' );
	$output = fopen( 'php://output', 'w' ); fwrite( $output, "\xEF\xBB\xBF" );
    fputcsv( $output, [ 'Date', 'Agence', 'Statut', 'Début', 'Approvisionnées', 'Enrôlements', 'Play Store', 'Cartes remises clients', 'Endommagées', 'Fin', 'Écart' ], ';' );
    foreach ( $reports as $report ) fputcsv( $output, [ get_post_meta( $report->ID, '_slfd_date', true ), slfd_agency_name( get_post_meta( $report->ID, '_slfd_agency', true ) ), 'publish' === $report->post_status ? 'Validé' : 'En attente', slfd_meta_int( $report->ID, '_slfd_opening' ), slfd_meta_int( $report->ID, '_slfd_received' ), slfd_meta_int( $report->ID, '_slfd_enrollments' ), slfd_meta_int( $report->ID, '_slfd_playstore' ), slfd_meta_int( $report->ID, '_slfd_cards_distributed' ), slfd_meta_int( $report->ID, '_slfd_damaged' ), slfd_meta_int( $report->ID, '_slfd_closing' ), (int) get_post_meta( $report->ID, '_slfd_stock_delta', true ) ], ';' );
	fclose( $output ); exit;
}

add_action( 'admin_post_slfd_export_supplies', 'slfd_export_supplies' );
function slfd_export_supplies() {
	if ( ! slfd_can_validate() ) wp_die( 'Accès refusé.', 403 );
	$filters = slfd_history_filters();
	$supplies = get_posts( [ 'post_type' => SLFD_SUPPLY_POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => -1, 'meta_query' => [ [ 'key' => '_slfd_date', 'value' => [ $filters['from'], $filters['to'] ], 'compare' => 'BETWEEN', 'type' => 'DATE' ] ], 'orderby' => 'meta_value', 'meta_key' => '_slfd_date', 'order' => 'DESC' ] );
	nocache_headers(); header( 'Content-Type: text/csv; charset=utf-8' ); header( 'Content-Disposition: attachment; filename=approvisionnements-fidelite-' . gmdate( 'Y-m-d' ) . '.csv' );
	$output = fopen( 'php://output', 'w' ); fwrite( $output, "\xEF\xBB\xBF" ); fputcsv( $output, [ 'Date', 'Agence', 'Quantité', 'Référence', 'Note' ], ';' );
	foreach ( $supplies as $supply ) fputcsv( $output, [ get_post_meta( $supply->ID, '_slfd_date', true ), slfd_agency_name( get_post_meta( $supply->ID, '_slfd_agency', true ) ), slfd_meta_int( $supply->ID, '_slfd_quantity' ), get_post_meta( $supply->ID, '_slfd_reference', true ), get_post_meta( $supply->ID, '_slfd_notes', true ) ], ';' );
	fclose( $output ); exit;
}

/** A supervisor can create a short-lived public performance summary to share manually. */
function slfd_share_token() {
	return isset( $_GET['slfd_share'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', (string) wp_unslash( $_GET['slfd_share'] ) ) : '';
}

function slfd_share_url( $token ) {
	return add_query_arg( 'slfd_share', rawurlencode( $token ), home_url( '/' ) );
}

function slfd_share_message( $filters, $summary, $url ) {
	$agency = $filters['agency'] ? slfd_agency_name( $filters['agency'] ) : 'toutes les agences';
	return sprintf(
		"Bonjour,\n\nVoici la synthèse Fidélité de %s, du %s au %s :\n- %d rapport(s) validé(s)\n- %d enrôlement(s)\n- %d carte(s) approvisionnée(s)\n\nConsulter le rapport : %s\n\nComplexe Santa Lucia",
		$agency,
		date_i18n( 'd/m/Y', strtotime( $filters['from'] ) ),
		date_i18n( 'd/m/Y', strtotime( $filters['to'] ) ),
		(int) $summary['validated'],
		(int) $summary['enrollments'],
		(int) $summary['supplies'],
		$url
	);
}

add_action( 'admin_post_slfd_create_share_link', 'slfd_create_share_link' );
function slfd_create_share_link() {
	if ( ! slfd_can_validate() ) {
		wp_die( 'Accès refusé.', 403 );
	}
	check_admin_referer( 'slfd_create_share_link' );

	$filters = slfd_history_filters();
	// A shareable performance report intentionally excludes unvalidated figures.
	$filters['status'] = 'publish';
	$filters['q']      = '';
	$token = wp_generate_password( 32, false, false );
	set_transient( 'slfd_share_' . $token, [
		'filters'    => $filters,
		'created_by' => get_current_user_id(),
		'created_at' => current_time( 'timestamp' ),
	], 7 * DAY_IN_SECONDS );

	wp_safe_redirect( add_query_arg( array_merge( $filters, [ 'share' => $token ] ), slfd_dashboard_url() ) . '#analyses-historique' );
	exit;
}

/** Render the public, token-protected performance summary without customer data. */
add_action( 'template_redirect', 'slfd_render_shared_performance', 1 );
function slfd_render_shared_performance() {
	$token = slfd_share_token();
	if ( ! $token ) {
		return;
	}
	$share = get_transient( 'slfd_share_' . $token );
	if ( ! is_array( $share ) || empty( $share['filters'] ) ) {
		status_header( 404 );
		wp_die( 'Ce lien de partage est invalide ou a expiré.', 'Rapport indisponible', [ 'response' => 404 ] );
	}

	$filters = $share['filters'];
	$reports = slfd_history_reports( $filters );
	$summary = slfd_history_summary( $reports );
	$agency  = $filters['agency'] ? slfd_agency_name( $filters['agency'] ) : 'Toutes les agences';
	nocache_headers();
	?><!doctype html>
	<html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Performance Fidélité - Complexe Santa Lucia</title><style>body{margin:0;background:#f5f7f8;color:#18232a;font:16px/1.5 Arial,sans-serif}.slfd-share{max-width:900px;margin:48px auto;padding:0 20px}.slfd-share header{border-bottom:3px solid #d71920;padding-bottom:20px}.slfd-share h1{margin:0;font-size:30px}.slfd-share .eyebrow{color:#9b1218;font-weight:700;text-transform:uppercase;font-size:12px;letter-spacing:0}.slfd-share .cards{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin:24px 0}.slfd-share .card,.slfd-share table{background:#fff;border:1px solid #dbe2e6;border-radius:6px}.slfd-share .card{padding:18px}.slfd-share .card span{display:block;color:#5d6870;font-size:13px}.slfd-share .card strong{font-size:28px}.slfd-share table{width:100%;border-collapse:separate;border-spacing:0;overflow:hidden}.slfd-share th,.slfd-share td{padding:12px;border-bottom:1px solid #edf0f2;text-align:left}.slfd-share th{background:#f1f4f5}.slfd-share footer{color:#5d6870;font-size:13px;margin-top:22px}@media(max-width:620px){.slfd-share{margin:24px auto}.slfd-share .cards{grid-template-columns:1fr}.slfd-share th:nth-child(3),.slfd-share td:nth-child(3){display:none}}</style></head><body><main class="slfd-share"><header><p class="eyebrow">Complexe Santa Lucia · Programme Fidélité</p><h1>Rapport de performance</h1><p><?php echo esc_html( $agency ); ?> · du <?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $filters['from'] ) ) ); ?> au <?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $filters['to'] ) ) ); ?></p></header><section class="cards"><div class="card"><span>Rapports validés</span><strong><?php echo (int) $summary['validated']; ?></strong></div><div class="card"><span>Enrôlements</span><strong><?php echo (int) $summary['enrollments']; ?></strong></div><div class="card"><span>Cartes approvisionnées</span><strong><?php echo (int) $summary['supplies']; ?></strong></div></section><table><thead><tr><th>Date</th><th>Agence</th><th>Enrôlements</th><th>Stock final</th></tr></thead><tbody><?php foreach ( $reports as $report ) : ?><tr><td><?php echo esc_html( get_post_meta( $report->ID, '_slfd_date', true ) ); ?></td><td><?php echo esc_html( slfd_agency_name( get_post_meta( $report->ID, '_slfd_agency', true ) ) ); ?></td><td><?php echo (int) slfd_meta_int( $report->ID, '_slfd_enrollments' ); ?></td><td><?php echo (int) slfd_meta_int( $report->ID, '_slfd_closing' ); ?></td></tr><?php endforeach; ?><?php if ( ! $reports ) : ?><tr><td colspan="4">Aucun rapport validé sur cette période.</td></tr><?php endif; ?></tbody></table><footer>Lien de partage temporaire · Complexe Santa Lucia</footer></main></body></html><?php
	exit;
}

function slfd_report_print_url( $report_id ) {
    return add_query_arg( 'rapport', absint( $report_id ), slfd_dashboard_url() );
}

function slfd_issue_labels( $issues ) {
    $labels = [
        'network'         => 'Réseau instable',
        'defective_cards' => 'Cartes défectueuses',
        'stock'           => 'Risque de rupture de stock',
        'other'           => 'Autre difficulté',
    ];
    return array_values( array_intersect_key( $labels, array_flip( (array) $issues ) ) );
}

/** Version imprimable, accessible uniquement aux responsables fidelite et administrateurs. */
function slfd_render_report_print( $report_id ) {
    $report = get_post( absint( $report_id ) );
    if ( ! $report || SLFD_POST_TYPE !== $report->post_type || ! in_array( $report->post_status, [ 'pending', 'publish' ], true ) ) {
        return '<main class="slfd-login-wrap"><section class="slfd-login-card"><span class="dashicons dashicons-warning"></span><h1>Rapport introuvable</h1><p>Ce rapport n’existe pas ou n’est plus disponible.</p></section></main>';
    }

    $agency       = slfd_agency_name( get_post_meta( $report->ID, '_slfd_agency', true ) );
    $date         = (string) get_post_meta( $report->ID, '_slfd_date', true );
    $author       = get_userdata( (int) $report->post_author );
    $validator    = get_userdata( (int) get_post_meta( $report->ID, '_slfd_validated_by', true ) );
    $validated_at = (int) get_post_meta( $report->ID, '_slfd_validated_at', true );
    $issues       = slfd_issue_labels( get_post_meta( $report->ID, '_slfd_issues', true ) );
    $delta        = (int) get_post_meta( $report->ID, '_slfd_stock_delta', true );
    $status       = 'publish' === $report->post_status ? 'Validé' : 'En attente de validation';
    ob_start(); ?>
    <main class="slfd-print-report">
        <header class="slfd-print-header"><div><p class="slfd-eyebrow">Complexe Santa Lucia · Programme cartes de fidélité</p><h1>Rapport quotidien d’enrôlement</h1><p><?php echo esc_html( $agency ); ?> · <?php echo esc_html( date_i18n( 'l j F Y', strtotime( $date ) ) ); ?></p></div><div class="slfd-print-actions"><a href="<?php echo esc_url( slfd_dashboard_url() ); ?>">Retour au tableau de bord</a><button type="button" onclick="window.print()"><span class="dashicons dashicons-printer"></span> Imprimer</button></div></header>
        <section class="slfd-print-status"><strong>Statut : <?php echo esc_html( $status ); ?></strong><?php if ( $author ) : ?><span>Déclaré par <?php echo esc_html( $author->display_name ); ?></span><?php endif; ?><?php if ( $validator && $validated_at ) : ?><span>Validé par <?php echo esc_html( $validator->display_name ); ?> le <?php echo esc_html( date_i18n( 'd/m/Y H:i', $validated_at ) ); ?></span><?php endif; ?></section>
        <section class="slfd-print-card"><h2>Mouvement des cartes</h2><div class="slfd-print-grid"><div><span>Stock au début</span><strong><?php echo (int) slfd_meta_int( $report->ID, '_slfd_opening' ); ?></strong></div><div><span>Approvisionnées</span><strong>+<?php echo (int) slfd_meta_int( $report->ID, '_slfd_received' ); ?></strong></div><div><span>Enrôlements</span><strong><?php echo (int) slfd_meta_int( $report->ID, '_slfd_enrollments' ); ?></strong></div><div><span>Endommagées</span><strong><?php echo (int) slfd_meta_int( $report->ID, '_slfd_damaged' ); ?></strong></div><div><span>Stock théorique</span><strong><?php echo (int) slfd_meta_int( $report->ID, '_slfd_expected_closing' ); ?></strong></div><div><span>Stock réel final</span><strong><?php echo (int) slfd_meta_int( $report->ID, '_slfd_closing' ); ?></strong></div></div><?php if ( $delta ) : ?><p class="slfd-print-delta">Écart de stock constaté : <strong><?php echo esc_html( $delta > 0 ? '+' . $delta : (string) $delta ); ?> carte(s)</strong></p><?php endif; ?></section>
        <section class="slfd-print-card"><h2>Difficultés signalées</h2><p><?php echo $issues ? esc_html( implode( ' · ', $issues ) ) : 'Aucune difficulté signalée.'; ?></p><?php if ( get_post_meta( $report->ID, '_slfd_notes', true ) ) : ?><h3>Détails</h3><p><?php echo nl2br( esc_html( get_post_meta( $report->ID, '_slfd_notes', true ) ) ); ?></p><?php endif; ?><?php if ( get_post_meta( $report->ID, '_slfd_request', true ) ) : ?><h3>Demande / recommandation</h3><p><?php echo nl2br( esc_html( get_post_meta( $report->ID, '_slfd_request', true ) ) ); ?></p><?php endif; ?></section>
        <footer class="slfd-print-footer"><span>Document interne · Complexe Santa Lucia</span><span>Imprimé le <?php echo esc_html( date_i18n( 'd/m/Y à H:i' ) ); ?></span></footer>
    </main>
    <?php return ob_get_clean();
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

function slfd_render_agent_management() {
    if ( ! slfd_can_manage_agents() ) {
        return slfd_render_access_denied();
    }
    $flash    = slfd_flash( 'read' );
    $agencies = get_terms( [ 'taxonomy' => 'sl_agence_promo', 'hide_empty' => false, 'orderby' => 'name' ] );
    $agents   = get_users( [ 'role' => 'sl_agent_fidelite', 'orderby' => 'display_name', 'order' => 'ASC' ] );
    ob_start(); ?>
    <main class="slfd-shell">
        <header class="slfd-topbar"><a class="slfd-brand" href="<?php echo esc_url( slfd_dashboard_url() ); ?>"><span class="dashicons dashicons-cart"></span><span><small>Complexe</small>Santa Lucia</span></a><span class="slfd-internal"><span class="dashicons dashicons-shield"></span> Coordination Fidélité</span><a class="slfd-top-link" href="<?php echo esc_url( slfd_dashboard_url() ); ?>">Retour au tableau de bord</a></header>
        <section class="slfd-content slfd-report-page">
            <?php if ( $flash ) : ?><div class="slfd-flash slfd-flash--<?php echo esc_attr( $flash[0] ); ?>"><?php echo esc_html( $flash[1] ); ?></div><?php endif; ?>
            <div class="slfd-heading"><div><p class="slfd-eyebrow">Coordination</p><h1>Gérer les agents Fidélité</h1><p>Créez un accès pour une agence. Les comptes créés ici reçoivent uniquement le rôle Agent fidélité.</p></div></div>
            <form class="slfd-report-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="slfd_create_agent"><?php wp_nonce_field( 'slfd_create_agent' ); ?>
                <section><h2>Nouvel agent</h2><div class="slfd-form-grid"><p><label for="slfd_agent_first_name">Nom complet</label><input id="slfd_agent_first_name" name="slfd_agent_first_name" autocomplete="name" required></p><p><label for="slfd_agent_login">Identifiant de connexion</label><input id="slfd_agent_login" name="slfd_agent_login" autocomplete="username" required></p><p><label for="slfd_agent_email">E-mail professionnel</label><input id="slfd_agent_email" type="email" name="slfd_agent_email" autocomplete="email" required></p><p><label for="slfd_agent_password">Mot de passe initial</label><input id="slfd_agent_password" type="password" name="slfd_agent_password" autocomplete="new-password" minlength="8" required></p><p><label for="slfd_agent_agency">Agence rattachée</label><select id="slfd_agent_agency" name="slfd_agent_agency" required><option value="">Choisissez une agence...</option><?php if ( ! is_wp_error( $agencies ) ) foreach ( $agencies as $agency ) : ?><option value="<?php echo esc_attr( $agency->slug ); ?>"><?php echo esc_html( $agency->name ); ?></option><?php endforeach; ?></select></p><p><label>Rôle attribué</label><input value="Agent fidélité" readonly></p></div></section>
                <div class="slfd-form-actions"><a href="<?php echo esc_url( slfd_dashboard_url() ); ?>">Annuler</a><button type="submit">Créer l’agent</button></div>
            </form>
            <section class="slfd-pending slfd-agents-list"><h2>Agents enregistrés</h2><?php if ( $agents ) : foreach ( $agents as $agent ) :
                $agency = (string) get_user_meta( $agent->ID, 'sl_agence_assignee', true );
                $created_by_current_user = (int) get_user_meta( $agent->ID, '_slfd_created_by', true ) === get_current_user_id();
                $has_reports = (bool) get_posts( [
                    'post_type'      => SLFD_POST_TYPE,
                    'post_status'    => [ 'pending', 'publish' ],
                    'author'         => $agent->ID,
                    'posts_per_page' => 1,
                    'fields'         => 'ids',
                ] );
            ?><article><div><strong><?php echo esc_html( $agent->display_name ?: $agent->user_login ); ?></strong><span><?php echo esc_html( $agency ?: 'Agence non attribuée' ); ?> · <?php echo esc_html( $agent->user_email ); ?></span><?php if ( $created_by_current_user && $has_reports ) : ?><em>Historique de rapports conservé</em><?php endif; ?></div><div class="slfd-agent-actions"><span class="slfd-pill slfd-pill--good">Agent fidélité</span><?php if ( $created_by_current_user && ! $has_reports ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return window.confirm('Supprimer définitivement ce compte agent ?');"><input type="hidden" name="action" value="slfd_delete_agent"><input type="hidden" name="agent_id" value="<?php echo esc_attr( $agent->ID ); ?>"><?php wp_nonce_field( 'slfd_delete_agent_' . $agent->ID ); ?><button class="slfd-delete-agent" type="submit" title="Supprimer ce compte" aria-label="Supprimer le compte de <?php echo esc_attr( $agent->display_name ?: $agent->user_login ); ?>"><span class="dashicons dashicons-trash"></span></button></form><?php endif; ?></div></article><?php endforeach; else : ?><p class="slfd-muted">Aucun agent n’est encore enregistré.</p><?php endif; ?></section>
        </section>
    </main>
    <?php return ob_get_clean();
}

function slfd_render_dashboard() {
    $date = current_time( 'Y-m-d' );
    if ( ! empty( $_GET['date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['date'] ) ) {
        $date = sanitize_text_field( wp_unslash( $_GET['date'] ) );
    }
    $rows    = slfd_dashboard_rows( $date );
    $pending = slfd_pending_reports();
    $reports = slfd_can_validate() ? slfd_reports_for_date( $date ) : [];
    $supplies = slfd_supplies_for_date( $date );
    $flash   = slfd_flash( 'read' );
    $alerts  = array_filter( $rows, function ( $row ) { return $row['report'] && $row['stock'] <= 50; } );
    $network = array_filter( $rows, function ( $row ) {
        return $row['report'] && in_array( 'network', (array) get_post_meta( $row['report']->ID, '_slfd_issues', true ), true );
    } );
	$history_filters = slfd_history_filters();
	$history_reports = slfd_can_validate() ? slfd_history_reports( $history_filters ) : [];
	$history_summary = slfd_history_summary( $history_reports );
	$agencies = get_terms( [ 'taxonomy' => 'sl_agence_promo', 'hide_empty' => false, 'orderby' => 'name' ] );
	$share_token = isset( $_GET['share'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', (string) wp_unslash( $_GET['share'] ) ) : '';
	$share_data  = $share_token ? get_transient( 'slfd_share_' . $share_token ) : false;
	$share_url   = $share_data && (int) $share_data['created_by'] === get_current_user_id() ? slfd_share_url( $share_token ) : '';
	$share_message = $share_url ? slfd_share_message( $history_filters, $history_summary, $share_url ) : '';
	$create_share_url = wp_nonce_url( add_query_arg( array_merge( [ 'action' => 'slfd_create_share_link' ], $history_filters ), admin_url( 'admin-post.php' ) ), 'slfd_create_share_link' );
    $campaign = slfd_campaign_data();
    $campaign_stats = slfd_campaign_stats( $campaign );
    $campaign_alerts = slfd_campaign_alerts( $campaign_stats );
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
                <div class="slfd-heading-actions"><?php if ( slfd_can_manage_agents() ) : ?><a class="slfd-secondary" href="<?php echo esc_url( slfd_campaigns_url() ); ?>"><span class="dashicons dashicons-chart-bar"></span>Gérer la campagne</a><a class="slfd-secondary" href="<?php echo esc_url( slfd_agents_url() ); ?>"><span class="dashicons dashicons-admin-users"></span>Gérer les agents</a><?php endif; ?><?php if ( slfd_can_supply() ) : ?><a class="slfd-secondary" href="<?php echo esc_url( slfd_supply_url() ); ?>"><span class="dashicons dashicons-plus-alt"></span>Approvisionner une agence</a><?php endif; ?><a class="slfd-primary" href="<?php echo esc_url( slfd_report_url() ); ?>"><span class="dashicons dashicons-edit-page"></span>Saisir le rapport du jour</a></div>
            </div>
            <?php if ( $campaign_stats ) : ?>
                <?php echo slfd_render_campaign_overview( $campaign_stats ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                <section class="slfd-table-card slfd-dashboard-campaign-chart" aria-labelledby="slfd-campaign-chart-title">
                    <div class="slfd-table-head">
                        <div>
                            <p class="slfd-eyebrow">Pilotage de campagne</p>
                            <h2 id="slfd-campaign-chart-title">Évolution de la campagne</h2>
                            <p>Enregistrements validés sur les quatorze derniers jours.</p>
                        </div>
                        <strong class="slfd-campaign-daily-target"><?php echo (int) $campaign_stats['clients']['daily']; ?><span>clients / jour requis</span></strong>
                    </div>
                    <?php echo slfd_campaign_chart_markup( $campaign_stats ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    <p class="slfd-footnote"><span class="dashicons dashicons-chart-line"></span>La courbe est basée uniquement sur les rapports validés. Le classement quotidien se trouve ci-dessous.</p>
                </section>
            <?php elseif ( slfd_can_manage_agents() ) : ?>
                <section class="slfd-campaign-empty" aria-labelledby="slfd-campaign-empty-title">
                    <span class="dashicons dashicons-chart-area"></span>
                    <div>
                        <p class="slfd-eyebrow">Pilotage de campagne</p>
                        <h2 id="slfd-campaign-empty-title">La campagne n’est pas encore configurée</h2>
                        <p>Définissez une période et vos objectifs pour afficher les courbes, les progressions par agence et le rythme quotidien attendu.</p>
                    </div>
                    <a class="slfd-primary" href="<?php echo esc_url( slfd_campaigns_url() ); ?>"><span class="dashicons dashicons-chart-bar"></span>Configurer la campagne</a>
                </section>
            <?php endif; ?>
            <div class="slfd-layout">
                <section class="slfd-table-card" aria-labelledby="slfd-ranking-title">
                    <div class="slfd-table-head"><div><p class="slfd-eyebrow">Suivi opérationnel</p><h2 id="slfd-ranking-title">Classement du jour</h2></div><form method="get"><label for="slfd-date">Date</label><input id="slfd-date" type="date" name="date" value="<?php echo esc_attr( $date ); ?>"><button type="submit">Afficher</button></form></div>
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
                    <?php if ( $campaign_stats ) : ?><section class="slfd-alert-card slfd-campaign-alert-card"><h2><span class="dashicons dashicons-chart-line"></span>Rythme de campagne</h2>
                        <?php if ( $campaign_alerts['agencies'] ) : ?><p><strong><?php echo (int) count( $campaign_alerts['agencies'] ); ?> agence(s)</strong> sont sous le rythme attendu.</p><?php foreach ( array_slice( $campaign_alerts['agencies'], 0, 4 ) as $campaign_agency ) : ?><div class="slfd-alert-row"><strong><?php echo esc_html( $campaign_agency['term']->name ); ?></strong><span class="slfd-pill slfd-pill--critical">À accompagner</span><b><?php echo (int) $campaign_agency['clients']['daily']; ?> / jour requis</b></div><?php endforeach; else : ?><p class="slfd-muted">Toutes les agences sont dans le rythme prévu.</p><?php endif; ?>
                        <?php if ( slfd_can_manage_agents() && $campaign_alerts['agents'] ) : ?><p class="slfd-campaign-alert-note"><strong><?php echo (int) count( $campaign_alerts['agents'] ); ?> agent(s)</strong> ont besoin d’un rappel. Les e-mails sont envoyés chaque jour lorsque cette option est activée dans la campagne.</p><?php endif; ?>
                    </section><?php endif; ?>
                </aside>
            </div>
			<?php if ( slfd_can_validate() ) : ?><section class="slfd-pending"><div class="slfd-table-head"><h2>Partager les performances</h2><?php if ( ! $share_url ) : ?><a class="slfd-secondary" href="<?php echo esc_url( $create_share_url ); ?>"><span class="dashicons dashicons-share"></span>Préparer un lien de partage</a><?php endif; ?></div><?php if ( $share_url ) : ?><p>Ce lien présente uniquement les résultats validés de la période sélectionnée. Il expirera automatiquement dans 7 jours.</p><p><input type="url" value="<?php echo esc_attr( $share_url ); ?>" readonly onclick="this.select()" style="width:min(100%,620px)"> <a class="slfd-secondary" target="_blank" rel="noopener" href="https://wa.me/?text=<?php echo rawurlencode( $share_message ); ?>"><span class="dashicons dashicons-whatsapp"></span>Partager sur WhatsApp</a></p><?php else : ?><p class="slfd-muted">Sélectionnez la période et l’agence dans l’historique, puis créez un lien à transmettre manuellement.</p><?php endif; ?></section><?php endif; ?>
			<?php if ( slfd_can_validate() ) : ?><section id="analyses-historique" class="slfd-pending slfd-history"><div class="slfd-table-head"><h2>Analyses et historique</h2><div class="slfd-heading-actions"><a class="slfd-secondary" href="<?php echo esc_url( slfd_history_export_url( 'slfd_export_reports', $history_filters ) ); ?>"><span class="dashicons dashicons-download"></span>Exporter les rapports</a><a class="slfd-secondary" href="<?php echo esc_url( slfd_history_export_url( 'slfd_export_supplies', $history_filters ) ); ?>"><span class="dashicons dashicons-download"></span>Exporter les approvisionnements</a></div></div><form method="get" class="slfd-history-filters"><label>Du<input type="date" name="history_from" value="<?php echo esc_attr( $history_filters['from'] ); ?>"></label><label>Au<input type="date" name="history_to" value="<?php echo esc_attr( $history_filters['to'] ); ?>"></label><label>Agence<select name="history_agency"><option value="">Toutes</option><?php if ( ! is_wp_error( $agencies ) ) foreach ( $agencies as $agency ) : ?><option value="<?php echo esc_attr( $agency->slug ); ?>" <?php selected( $history_filters['agency'], $agency->slug ); ?>><?php echo esc_html( $agency->name ); ?></option><?php endforeach; ?></select></label><label>Statut<select name="history_status"><option value="">Tous</option><option value="publish" <?php selected( $history_filters['status'], 'publish' ); ?>>Validé</option><option value="pending" <?php selected( $history_filters['status'], 'pending' ); ?>>En attente</option></select></label><label>Recherche<input type="search" name="history_q" value="<?php echo esc_attr( $history_filters['q'] ); ?>" placeholder="Agence ou date"></label><button type="submit">Analyser</button></form><div class="slfd-history-kpis"><div><span>Rapports</span><strong><?php echo (int) $history_summary['reports']; ?></strong></div><div><span>Validés</span><strong><?php echo (int) $history_summary['validated']; ?></strong></div><div><span>Enrôlements</span><strong><?php echo (int) $history_summary['enrollments']; ?></strong></div><div><span>Cartes approvisionnées</span><strong><?php echo (int) $history_summary['supplies']; ?></strong></div></div><div class="slfd-table-scroll"><table><thead><tr><th>Date</th><th>Agence</th><th>Statut</th><th>Enrôlements</th><th>Stock final</th><th>Écart</th><th>Document</th></tr></thead><tbody><?php foreach ( $history_reports as $history_report ) : ?><tr><td><?php echo esc_html( get_post_meta( $history_report->ID, '_slfd_date', true ) ); ?></td><td><?php echo esc_html( slfd_agency_name( get_post_meta( $history_report->ID, '_slfd_agency', true ) ) ); ?></td><td><?php echo 'publish' === $history_report->post_status ? 'Validé' : 'En attente'; ?></td><td><?php echo (int) slfd_meta_int( $history_report->ID, '_slfd_enrollments' ); ?></td><td><?php echo (int) slfd_meta_int( $history_report->ID, '_slfd_closing' ); ?></td><td><?php echo esc_html( (string) get_post_meta( $history_report->ID, '_slfd_stock_delta', true ) ); ?></td><td><a href="<?php echo esc_url( slfd_report_print_url( $history_report->ID ) ); ?>" target="_blank" rel="noopener">Imprimer</a></td></tr><?php endforeach; ?><?php if ( ! $history_reports ) : ?><tr><td colspan="7" class="slfd-empty">Aucun rapport ne correspond aux filtres.</td></tr><?php endif; ?></tbody></table></div><p class="slfd-footnote">Tendance : <?php echo esc_html( $history_summary['by_day'] ? implode( ' · ', array_map( static function ( $day, $count ) { return $day . ' : ' . $count; }, array_keys( $history_summary['by_day'] ), $history_summary['by_day'] ) ) : 'aucune donnée' ); ?></p></section><?php endif; ?>
            <?php if ( slfd_can_validate() ) : ?><section class="slfd-pending slfd-reports-list"><h2>Rapports reçus pour cette journée</h2><?php if ( $reports ) : foreach ( $reports as $report ) : $is_validated = 'publish' === $report->post_status; ?><article><div><strong><?php echo esc_html( slfd_agency_name( get_post_meta( $report->ID, '_slfd_agency', true ) ) ); ?></strong><span><?php echo (int) slfd_meta_int( $report->ID, '_slfd_enrollments' ); ?> enrôlements · stock final <?php echo (int) slfd_meta_int( $report->ID, '_slfd_closing' ); ?> · <?php echo $is_validated ? 'Validé' : 'En attente'; ?></span></div><a class="slfd-secondary" target="_blank" rel="noopener" href="<?php echo esc_url( slfd_report_print_url( $report->ID ) ); ?>"><span class="dashicons dashicons-printer"></span>Voir / imprimer</a></article><?php endforeach; else : ?><p class="slfd-muted">Aucun rapport n’a été reçu pour cette date.</p><?php endif; ?></section><?php endif; ?>
            <?php if ( slfd_can_validate() && $pending ) : ?><section class="slfd-pending"><h2>Rapports à valider</h2><?php foreach ( $pending as $report ) : $delta = (int) get_post_meta( $report->ID, '_slfd_stock_delta', true ); ?><article><div><strong><?php echo esc_html( slfd_agency_name( get_post_meta( $report->ID, '_slfd_agency', true ) ) ); ?></strong><span><?php echo esc_html( get_post_meta( $report->ID, '_slfd_date', true ) ); ?> · <?php echo (int) slfd_meta_int( $report->ID, '_slfd_enrollments' ); ?> enrôlements</span><?php if ( $delta ) : ?><em>Écart de stock : <?php echo esc_html( $delta > 0 ? '+' . $delta : (string) $delta ); ?></em><?php endif; ?></div><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="slfd_validate_report"><input type="hidden" name="report_id" value="<?php echo (int) $report->ID; ?>"><?php wp_nonce_field( 'slfd_validate_' . $report->ID ); ?><button type="submit">Valider</button></form></article><?php endforeach; ?></section><?php endif; ?>
        </section>
    </main>
    <?php return ob_get_clean();
}

function slfd_render_report_form() {
    $agency             = slfd_current_agency();
    $flash              = slfd_flash( 'read' );
    $terms              = slfd_can_validate() ? get_terms( [ 'taxonomy' => 'sl_agence_promo', 'hide_empty' => false, 'orderby' => 'name' ] ) : [];
    $today_supply       = $agency ? slfd_supplies_total( $agency->slug, current_time( 'Y-m-d' ) ) : 0;
    $can_view_dashboard = slfd_can_view_dashboard();
    $back_url           = $can_view_dashboard ? slfd_dashboard_url() : slfd_report_url();
    $campaign_stats     = slfd_campaign_stats();
    $agent_campaign_row = $campaign_stats ? slfd_campaign_agent_row( $campaign_stats, get_current_user_id() ) : null;
    ob_start(); ?>
    <main class="slfd-shell">
        <header class="slfd-topbar slfd-report-topbar">
            <a class="slfd-brand" href="<?php echo esc_url( $back_url ); ?>"><span class="dashicons dashicons-cart"></span><span><small>Complexe</small>Santa Lucia</span></a>
            <span class="slfd-report-module"><span class="dashicons dashicons-clipboard"></span> Rapport quotidien</span>
            <?php if ( $can_view_dashboard ) : ?><a class="slfd-top-link" href="<?php echo esc_url( slfd_dashboard_url() ); ?>">Voir le classement</a><?php endif; ?>
            <a class="slfd-top-link slfd-logout-link" href="<?php echo esc_url( slfd_logout_url() ); ?>"><span class="dashicons dashicons-exit"></span>Déconnexion</a>
        </header>
        <section class="slfd-content slfd-report-page">
            <?php if ( $flash ) : ?><div class="slfd-flash slfd-flash--<?php echo esc_attr( $flash[0] ); ?>"><?php echo esc_html( $flash[1] ); ?></div><?php endif; ?>
            <div class="slfd-heading slfd-report-heading"><div><p class="slfd-eyebrow">Programme cartes de fidélité</p><h1>Déclarer le rapport du jour</h1><p>Le classement est actualisé uniquement après validation par un Responsable fidélité.</p></div></div>
            <?php if ( $agent_campaign_row && ! $can_view_dashboard ) : $personal = $agent_campaign_row['clients']; ?><section class="slfd-agent-goal <?php echo $personal['gap'] < 0 ? 'is-late' : ''; ?>"><span class="dashicons <?php echo $personal['gap'] < 0 ? 'dashicons-warning' : 'dashicons-awards'; ?>"></span><div><strong><?php echo $personal['gap'] < 0 ? 'Votre objectif nécessite un effort supplémentaire' : 'Vous êtes dans le rythme de la campagne'; ?></strong><p><?php echo number_format_i18n( $personal['actual'] ); ?> / <?php echo number_format_i18n( $personal['target'] ); ?> clients enregistrés. <?php echo $personal['gap'] < 0 ? 'Pour revenir au rythme, visez ' . number_format_i18n( $personal['daily'] ) . ' enregistrements par jour.' : 'Continuez ainsi pour atteindre votre objectif.'; ?></p></div><?php echo slfd_campaign_trend_markup( $agent_campaign_row['trend'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></section><?php endif; ?>
            <?php if ( ! $agency && ! slfd_can_validate() ) : ?>
                <div class="slfd-notice">Votre compte n’est rattaché à aucune agence. Demandez à un administrateur de compléter votre profil.</div>
            <?php else : ?>
                <form class="slfd-report-form slfd-report-form--v2" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="slfd_submit_report"><?php wp_nonce_field( 'slfd_submit_report' ); ?>
                    <div class="slfd-report-layout">
                        <div class="slfd-report-main">
                            <section class="slfd-report-identification" aria-labelledby="slfd-section-information">
                                <h2 id="slfd-section-information" class="screen-reader-text">Identification du rapport</h2>
                                <div class="slfd-form-grid">
                                    <p><label for="slfd_date">Date du rapport</label><input id="slfd_date" type="date" name="slfd_date" max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required></p>
                                    <?php if ( slfd_can_validate() ) : ?>
                                        <p><label for="slfd_agency">Agence</label><select id="slfd_agency" name="slfd_agency" required><?php foreach ( $terms as $term ) : ?><option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $agency && $agency->slug, $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option><?php endforeach; ?></select></p>
                                    <?php else : ?>
                                        <p><label for="slfd_agency_readonly">Agence</label><input id="slfd_agency_readonly" value="<?php echo esc_attr( $agency->name ); ?>" readonly></p>
                                    <?php endif; ?>
                                </div>
                            </section>
                            <section aria-labelledby="slfd-section-cards">
                                <h2 id="slfd-section-cards">Mouvement des cartes</h2>
                                <p class="slfd-form-help">Saisissez uniquement les mouvements de cette journée. Ne comptez pas les cartes déjà attribuées aux clients.</p>
                                <div class="slfd-stock-equation">
                                    <p><label for="slfd_opening">Cartes disponibles au début</label><input id="slfd_opening" type="number" inputmode="numeric" name="slfd_opening" min="0" value="0" required></p>
                                    <span class="slfd-equation-sign" aria-hidden="true">+</span>
                                    <p class="slfd-auto-field"><label for="slfd_received">Cartes approvisionnées</label><output id="slfd_received" data-value="<?php echo (int) $today_supply; ?>">+<?php echo (int) $today_supply; ?> carte(s)</output><small>Enregistrées par le Responsable fidélité.</small></p>
                                    <span class="slfd-equation-sign" aria-hidden="true">−</span>
                                    <p><label for="slfd_enrollments">Enrôlements validés</label><input id="slfd_enrollments" type="number" inputmode="numeric" name="slfd_enrollments" min="0" value="0" required></p>
                                    <span class="slfd-equation-sign" aria-hidden="true">−</span>
                                    <p><label for="slfd_damaged">Cartes endommagées</label><input id="slfd_damaged" type="number" inputmode="numeric" name="slfd_damaged" min="0" value="0" required></p>
                                </div>
                                <p class="slfd-closing-field"><label for="slfd_closing">Cartes disponibles à la fin de la journée</label><input id="slfd_closing" type="number" inputmode="numeric" name="slfd_closing" min="0" value="0" required><small>Comptage physique réel effectué en fin de journée.</small></p>
                                <p class="slfd-equation-help"><span class="dashicons dashicons-info-outline"></span> Calcul automatique : début + approvisionnements − enrôlements − cartes endommagées.</p>
                            </section>
                            <section aria-labelledby="slfd-section-digital">
                                <h2 id="slfd-section-digital">Inscriptions Play Store</h2>
                                <p class="slfd-form-help">Indiquez le nombre de personnes inscrites sur le Play Store grâce à l’activité de l’agence aujourd’hui.</p>
                                <div class="slfd-form-grid"><p><label for="slfd_playstore">Inscriptions Play Store du jour</label><input id="slfd_playstore" type="number" inputmode="numeric" name="slfd_playstore" min="0" value="0"></p><p><label for="slfd_cards_distributed">Cartes remises aux clients</label><input id="slfd_cards_distributed" type="number" inputmode="numeric" name="slfd_cards_distributed" min="0" value="0"><small>À distinguer des cartes approvisionnées à l’agence.</small></p></div>
                            </section>
                            <section aria-labelledby="slfd-section-issues">
                                <h2 id="slfd-section-issues">Difficultés et besoins</h2>
                                <div class="slfd-checks slfd-checks--rows">
                                    <label><input type="checkbox" name="slfd_issues[]" value="network"><span class="slfd-issue-icon"><span class="dashicons dashicons-rss"></span></span><span>Réseau instable</span></label>
                                    <label><input type="checkbox" name="slfd_issues[]" value="defective_cards"><span class="slfd-issue-icon"><span class="dashicons dashicons-id"></span></span><span>Cartes défectueuses</span></label>
                                    <label><input type="checkbox" name="slfd_issues[]" value="stock"><span class="slfd-issue-icon"><span class="dashicons dashicons-warning"></span></span><span>Risque de rupture de stock</span></label>
                                    <label><input type="checkbox" name="slfd_issues[]" value="other"><span class="slfd-issue-icon"><span class="dashicons dashicons-format-chat"></span></span><span>Autre difficulté</span></label>
                                </div>
                                <div class="slfd-text-fields">
                                    <p><label for="slfd_notes">Détails des difficultés</label><textarea id="slfd_notes" name="slfd_notes" rows="4" placeholder="Ex. réseau instable, série CF21000 non fonctionnelle…"></textarea></p>
                                    <p><label for="slfd_request">Demande ou recommandation</label><textarea id="slfd_request" name="slfd_request" rows="4" placeholder="Ex. besoin de réapprovisionnement avant le week-end…"></textarea></p>
                                </div>
                            </section>
                        </div>
                        <aside class="slfd-day-summary" aria-live="polite">
                            <h2><span class="dashicons dashicons-clipboard"></span>Résumé du jour</h2>
                            <p class="slfd-summary-agency"><span>Agence</span><strong id="slfd_summary_agency"><?php echo esc_html( $agency ? $agency->name : '' ); ?></strong></p>
                            <div class="slfd-summary-list">
                                <div><span class="slfd-summary-icon slfd-summary-icon--pink"><span class="dashicons dashicons-id"></span></span><span>Cartes approvisionnées</span><strong id="slfd_summary_received">+<?php echo (int) $today_supply; ?></strong></div>
                                <div><span class="slfd-summary-icon"><span class="dashicons dashicons-archive"></span></span><span>Cartes disponibles au début</span><strong id="slfd_summary_opening">0</strong></div>
                                <div><span class="slfd-summary-icon"><span class="dashicons dashicons-groups"></span></span><span>Enrôlements validés</span><strong id="slfd_summary_enrollments">0</strong></div>
                                <div><span class="slfd-summary-icon"><span class="dashicons dashicons-warning"></span></span><span>Cartes endommagées</span><strong id="slfd_summary_damaged">0</strong></div>
                            </div>
                            <div class="slfd-summary-totals">
                                <div><span>Stock théorique en fin de journée<small>Début + approvisionnements − enrôlements − endommagées</small></span><strong id="slfd_expected_output">0</strong></div>
                                <div><span><span class="dashicons dashicons-clipboard"></span> Stock réel déclaré</span><strong id="slfd_declared_output">0</strong></div>
                                <div id="slfd_delta_box"><span>Écart<small>Stock réel − stock théorique</small></span><strong id="slfd_delta_output">0</strong></div>
                            </div>
                            <div id="slfd_summary_status" class="slfd-summary-status slfd-summary-status--ok"><span class="dashicons dashicons-yes-alt"></span><span><small>Statut</small><strong id="slfd_status_text">Écart conforme</strong></span></div>
                        </aside>
                    </div>
                    <div class="slfd-submit-panel"><p><span class="dashicons dashicons-shield"></span><span>Veuillez vérifier attentivement les informations saisies.<small>Après transmission, le rapport devra être validé par un Responsable fidélité.</small></span></p><button type="submit"><span class="dashicons dashicons-email-alt"></span>Transmettre pour validation</button></div>
                </form>
            <?php endif; ?>
        </section>
    </main>
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
