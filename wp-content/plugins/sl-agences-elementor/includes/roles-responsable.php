<?php
/**
 * Rôle Responsable Agence + restrictions interface admin
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
 *  1. CRÉER LE RÔLE (une seule fois)
 * ============================================================ */
add_action( 'init', 'sl_bp_create_role' );
function sl_bp_create_role() {
    if ( ! get_role( 'sl_responsable_agence' ) ) {
        add_role( 'sl_responsable_agence', __( 'Responsable Agence', 'sl-agences' ), [
            'read'                              => true,
            'upload_files'                      => true,
            // CPT capabilities
            'read_sl_bon_plan'                  => true,
            'edit_sl_bon_plan'                  => true,
            'delete_sl_bon_plan'                => true,
            'edit_sl_bon_plans'                 => true,
            'publish_sl_bon_plans'              => true,
            'delete_sl_bon_plans'               => true,
            'edit_published_sl_bon_plans'       => true,
            'delete_published_sl_bon_plans'     => true,
            // Interdit
            'edit_others_sl_bon_plans'          => false,
            'delete_others_sl_bon_plans'        => false,
            'read_private_sl_bon_plans'         => false,
            'manage_options'                    => false,
        ] );
    }

    $responsable = get_role( 'sl_responsable_agence' );
    if ( $responsable && ! $responsable->has_cap( 'upload_files' ) ) {
        $responsable->add_cap( 'upload_files' );
    }

    $gestionnaire_caps = [
        'read'                              => true,
        'upload_files'                      => true,
        'manage_sl_bon_plan_terms'          => true,
        'read_sl_bon_plan'                  => true,
        'edit_sl_bon_plan'                  => true,
        'delete_sl_bon_plan'                => true,
        'edit_sl_bon_plans'                 => true,
        'edit_others_sl_bon_plans'          => true,
        'publish_sl_bon_plans'              => true,
        'read_private_sl_bon_plans'         => true,
        'delete_sl_bon_plans'               => true,
        'delete_private_sl_bon_plans'       => true,
        'delete_published_sl_bon_plans'     => true,
        'delete_others_sl_bon_plans'        => true,
        'edit_private_sl_bon_plans'         => true,
        'edit_published_sl_bon_plans'       => true,
    ];

    if ( ! get_role( 'sl_gestionnaire_bons_plans' ) ) {
        add_role( 'sl_gestionnaire_bons_plans', __( 'Gestionnaire Bons Plans', 'sl-agences' ), $gestionnaire_caps );
    }

    $gestionnaire = get_role( 'sl_gestionnaire_bons_plans' );
    if ( ! $gestionnaire ) return;

    foreach ( $gestionnaire_caps as $cap => $grant ) {
        if ( $grant && ! $gestionnaire->has_cap( $cap ) ) {
            $gestionnaire->add_cap( $cap );
        }
    }
}

/* ============================================================
 *  2. MENU ADMIN SIMPLIFIÉ
 * ============================================================ */
add_action( 'admin_menu', 'sl_bp_build_admin_menu', 999 );
function sl_bp_build_admin_menu() {
    if ( ! sl_bp_is_responsable() ) return;

    // Menu principal
    add_menu_page(
        __( 'Mes Bons Plans', 'sl-agences' ),
        __( '🔥 Mes Bons Plans', 'sl-agences' ),
        'edit_sl_bon_plans',
        'sl-mes-bons-plans',
        'sl_bp_page_list',
        'dashicons-tag',
        2
    );

    add_submenu_page(
        'sl-mes-bons-plans',
        __( 'Mes offres', 'sl-agences' ),
        __( 'Mes offres', 'sl-agences' ),
        'edit_sl_bon_plans',
        'sl-mes-bons-plans',
        'sl_bp_page_list'
    );

    add_submenu_page(
        'sl-mes-bons-plans',
        __( 'Ajouter une offre', 'sl-agences' ),
        __( '➕ Ajouter une offre', 'sl-agences' ),
        'publish_sl_bon_plans',
        'sl-ajouter-offre',
        'sl_bp_page_form'
    );

    add_submenu_page(
        'sl-mes-bons-plans',
        __( 'Import Rapide CSV', 'sl-agences' ),
        __( '⚡ Import Rapide CSV', 'sl-agences' ),
        'publish_sl_bon_plans',
        'sl-bp-import',
        'sl_bp_render_import_page'
    );

    add_submenu_page(
        'sl-mes-bons-plans',
        __( 'Mes produits', 'sl-agences' ),
        __( 'Mes produits', 'sl-agences' ),
        'read',
        'sl-mes-produits',
        'sl_bp_page_products'
    );

    // Lien profil
    add_menu_page(
        __( 'Mon Profil', 'sl-agences' ),
        __( '👤 Mon Profil', 'sl-agences' ),
        'read',
        'profile.php',
        '',
        'dashicons-admin-users',
        99
    );
}

/* ============================================================
 *  3. MASQUER TOUS LES AUTRES MENUS
 * ============================================================ */
add_action( 'admin_init', 'sl_bp_hide_admin_menus' );
function sl_bp_hide_admin_menus() {
    if ( ! sl_bp_is_responsable() ) return;

    // Masquer les menus natifs inutiles
    $to_remove = [
        'edit.php', 'edit-comments.php',
        'themes.php', 'plugins.php', 'users.php', 'tools.php', 'options-general.php',
        'edit.php?post_type=page', 'edit.php?post_type=sl_bon_plan', 'woocommerce', 'elementor',
    ];
    foreach ( $to_remove as $menu ) {
        remove_menu_page( $menu );
    }
}

/* ============================================================
 *  4. REDIRECTION FORCÉE VERS NOTRE PAGE
 * ============================================================ */
add_action( 'admin_init', 'sl_bp_admin_redirect' );
function sl_bp_admin_redirect() {
    if ( ! sl_bp_is_responsable() ) return;
    if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) return;

    $page    = $_GET['page'] ?? '';
    $allowed = [ 'sl-mes-bons-plans', 'sl-ajouter-offre', 'sl-bp-import', 'sl-mes-produits' ];

    // Autoriser le formulaire de sauvegarde et le profil
    $action  = $_REQUEST['action'] ?? '';
    $script  = basename( $_SERVER['PHP_SELF'] ?? '' );

    if ( in_array( $page, $allowed ) ) return;
    if ( $script === 'index.php' ) return;
    if ( $script === 'profile.php' ) return;
    if ( $script === 'admin-post.php' && in_array( $action, [ 'sl_bp_save', 'sl_export_csv' ], true ) ) return;
    if ( $script === 'upload.php' ) return;
    if ( $script === 'media-upload.php' ) return;
    if ( $script === 'async-upload.php' ) return;
    if ( $action === 'sl_bp_delete' ) return;

    wp_safe_redirect( admin_url( 'admin.php?page=sl-mes-bons-plans' ) );
    exit;
}

/* ============================================================
 *  4B. REDIRECTION APRÈS CONNEXION
 * ============================================================ */
add_filter( 'login_redirect', 'sl_bp_redirect_responsable_after_login', 20, 3 );
function sl_bp_redirect_responsable_after_login( $redirect_to, $requested_redirect_to, $user ) {
    if ( $user instanceof WP_User && in_array( 'sl_responsable_agence', (array) $user->roles, true ) ) {
        return admin_url( 'index.php' );
    }

    if ( $user instanceof WP_User && in_array( 'sl_gestionnaire_bons_plans', (array) $user->roles, true ) ) {
        return admin_url( 'index.php' );
    }

    return $redirect_to;
}

add_filter( 'woocommerce_login_redirect', 'sl_bp_redirect_responsable_after_woo_login', 20, 2 );
function sl_bp_redirect_responsable_after_woo_login( $redirect, $user ) {
    if ( $user instanceof WP_User && in_array( 'sl_responsable_agence', (array) $user->roles, true ) ) {
        return admin_url( 'index.php' );
    }

    if ( $user instanceof WP_User && in_array( 'sl_gestionnaire_bons_plans', (array) $user->roles, true ) ) {
        return admin_url( 'index.php' );
    }

    return $redirect;
}

/* ============================================================
 *  5. MASQUER LA BARRE ADMIN EN FRONT
 * ============================================================ */
add_filter( 'show_admin_bar', function( $show ) {
    if ( sl_bp_is_responsable() ) return false;
    return $show;
} );

/* ============================================================
 *  6. RESTREINDRE LES REQUÊTES : NE VOIR QUE SES OFFRES
 * ============================================================ */
add_action( 'pre_get_posts', 'sl_bp_only_own_posts' );
function sl_bp_only_own_posts( $query ) {
    if ( ! is_admin() ) return;
    if ( ! sl_bp_is_responsable() ) return;
    if ( $query->get( 'post_type' ) !== 'sl_bon_plan' ) return;
    $query->set( 'author', get_current_user_id() );
}

/* ============================================================
 *  HELPER
 * ============================================================ */
function sl_bp_is_responsable() {
    $user = wp_get_current_user();
    return $user && in_array( 'sl_responsable_agence', (array) $user->roles, true );
}

/* ============================================================
 *  7. AJOUTER LE CHAMP AGENCE DANS LE PROFIL UTILISATEUR
 * ============================================================ */
add_action( 'show_user_profile', 'sl_bp_user_agence_field' );
add_action( 'edit_user_profile', 'sl_bp_user_agence_field' );
add_action( 'user_new_form', 'sl_bp_user_agence_field' );

function sl_bp_user_agence_field( $user ) {
    // Si c'est un nouvel utilisateur (formulaire de création), $user est une chaîne de contexte, on crée un objet vide
    $user_id = is_object( $user ) ? $user->ID : 0;
    
    $is_admin = current_user_can( 'manage_options' );
    $current_agence = $user_id ? get_user_meta( $user_id, 'sl_agence_assignee', true ) : '';
    $agences = get_terms( [ 'taxonomy' => 'sl_agence_promo', 'hide_empty' => false ] );
    
    ?>
    <h3><?php _e("Gestion Bons Plans (Santa Lucia)", "sl-agences"); ?></h3>
    <table class="form-table">
        <tr>
            <th><label for="sl_agence_assignee"><?php _e("Agence Assignée", "sl-agences"); ?></label></th>
            <td>
                <?php if ( $is_admin ) : ?>
                    <select name="sl_agence_assignee" id="sl_agence_assignee">
                        <option value=""><?php _e("-- Aucune agence --", "sl-agences"); ?></option>
                        <?php foreach ( $agences as $agence ) : ?>
                            <option value="<?php echo esc_attr( $agence->name ); ?>" <?php selected( $current_agence, $agence->name ); ?>>
                                <?php echo esc_html( $agence->name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php _e("Sélectionnez l'agence pour ce profil. Lorsqu'il créera un bon plan, cette agence lui sera automatiquement attribuée (il ne pourra pas la modifier).", "sl-agences"); ?></p>
                <?php else : ?>
                    <input type="text" id="sl_agence_assignee" value="<?php echo esc_attr( $current_agence ?: 'Aucune agence assignée' ); ?>" disabled class="regular-text" />
                    <p class="description"><?php _e("Ceci est votre agence assignée de façon permanente. Seul l'administrateur peut la modifier.", "sl-agences"); ?></p>
                <?php endif; ?>
            </td>
        </tr>
    </table>
    <?php
}

add_action( 'personal_options_update', 'sl_bp_save_user_agence_field' );
add_action( 'edit_user_profile_update', 'sl_bp_save_user_agence_field' );
add_action( 'user_register', 'sl_bp_save_user_agence_field' );

function sl_bp_save_user_agence_field( $user_id ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        return false;
    }
    
    if ( isset( $_POST['sl_agence_assignee'] ) ) {
        update_user_meta( $user_id, 'sl_agence_assignee', sanitize_text_field( $_POST['sl_agence_assignee'] ) );
    }
}
