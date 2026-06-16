<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   ROLES
   ============================================================ */
add_action( 'init', 'sl_ff_create_roles', 1 );
function sl_ff_create_roles() {

    /* ── Responsable Fast Food (agence unique) ── */
    if ( ! get_role( 'sl_responsable_fastfood' ) ) {
        add_role( 'sl_responsable_fastfood', 'Responsable Fast Food', [
            'read'                          => true,
            'upload_files'                  => true,
            'read_sl_repas'                 => true,
            'edit_sl_repas'                 => true,
            'delete_sl_repas'               => true,
            'edit_sl_repas_items'           => true,
            'publish_sl_repas_items'        => true,
            'delete_sl_repas_items'         => true,
            'edit_published_sl_repas_items' => true,
            'edit_others_sl_repas_items'    => false,
            'delete_others_sl_repas_items'  => false,
            'manage_options'                => false,
        ] );
    } else {
        // S'assurer que upload_files est present
        $r = get_role( 'sl_responsable_fastfood' );
        if ( $r && ! $r->has_cap( 'upload_files' ) ) {
            $r->add_cap( 'upload_files' );
        }
    }

    /* ── Administrateur Fast Food (toutes agences) ── */
    if ( ! get_role( 'sl_ff_admin' ) ) {
        add_role( 'sl_ff_admin', 'Administrateur Fast Food', [
            'read'                          => true,
            'upload_files'                  => true,
            'read_sl_repas'                 => true,
            'edit_sl_repas'                 => true,
            'delete_sl_repas'               => true,
            'edit_sl_repas_items'           => true,
            'publish_sl_repas_items'        => true,
            'delete_sl_repas_items'         => true,
            'edit_published_sl_repas_items' => true,
            'edit_others_sl_repas_items'    => true,
            'delete_others_sl_repas_items'  => true,
            'create_sl_repas_items'         => true,  // ajouter des repas
            'manage_options'                => false,
            // Capacites specifiques FF Admin
            'sl_ff_all_agencies'            => true,  // acces toutes agences
            'sl_ff_import'                  => true,  // importer CSV/Excel
            'sl_ff_manage_promos'           => true,  // gerer les promos
            'manage_sl_repas_terms'         => true,  // gerer les categories repas
        ] );
    } else {
        // Mettre a jour les caps au cas ou le role existerait deja
        $r = get_role( 'sl_ff_admin' );
        foreach ( [
            'read',
            'upload_files',
            'read_sl_repas',
            'edit_sl_repas',
            'delete_sl_repas',
            'edit_sl_repas_items',
            'edit_others_sl_repas_items',
            'publish_sl_repas_items',
            'read_private_sl_repas_items',
            'delete_sl_repas_items',
            'delete_private_sl_repas_items',
            'delete_published_sl_repas_items',
            'delete_others_sl_repas_items',
            'edit_private_sl_repas_items',
            'edit_published_sl_repas_items',
            'create_sl_repas_items',
            'sl_ff_all_agencies',
            'sl_ff_import',
            'sl_ff_manage_promos',
            'manage_sl_repas_terms',
        ] as $cap ) {
            if ( $r && ! $r->has_cap( $cap ) ) $r->add_cap( $cap );
        }
    }

    /* ── Accorder les caps sl_repas a l'administrateur WP ── */
    $admin = get_role( 'administrator' );
    if ( $admin ) {
        $admin_caps = [
            'read_sl_repas', 'edit_sl_repas', 'delete_sl_repas',
            'edit_sl_repas_items', 'edit_others_sl_repas_items',
            'publish_sl_repas_items', 'read_private_sl_repas_items',
            'delete_sl_repas_items', 'delete_private_sl_repas_items',
            'delete_published_sl_repas_items', 'delete_others_sl_repas_items',
            'edit_private_sl_repas_items', 'edit_published_sl_repas_items',
            'create_sl_repas_items',
            'manage_sl_repas_terms',
            'sl_ff_all_agencies', 'sl_ff_import', 'sl_ff_manage_promos',
        ];
        foreach ( $admin_caps as $cap ) {
            if ( ! $admin->has_cap( $cap ) ) $admin->add_cap( $cap );
        }
    }
}

/* ============================================================
   FILTRE user_has_cap : accorder les caps aux admins WP + FF
   ============================================================ */
add_filter( 'user_has_cap', 'sl_ff_grant_admin_caps', 10, 3 );
function sl_ff_grant_admin_caps( $allcaps, $caps, $args ) {
    $user_id = isset( $args[1] ) ? (int) $args[1] : 0;
    $user    = $user_id ? get_userdata( $user_id ) : null;
    $is_ff_admin = $user && in_array( 'sl_ff_admin', (array) $user->roles, true );
    // L'editeur WP a TOUS les droits Fast Food (= admin Fast Food complet) :
    // planning toutes agences, import, promos, categories, disponibilite
    // multi-agences, reglages.
    $is_editor   = $user && in_array( 'editor', (array) $user->roles, true );

    if ( empty( $allcaps['manage_options'] ) && ! $is_ff_admin && ! $is_editor ) return $allcaps;

    $sl_caps = [
        'read_sl_repas', 'edit_sl_repas', 'delete_sl_repas',
        'edit_sl_repas_items', 'edit_others_sl_repas_items',
        'publish_sl_repas_items', 'read_private_sl_repas_items',
        'delete_sl_repas_items', 'delete_private_sl_repas_items',
        'delete_published_sl_repas_items', 'delete_others_sl_repas_items',
        'edit_private_sl_repas_items', 'edit_published_sl_repas_items',
        'create_sl_repas_items',
        'manage_sl_repas_terms',
        'sl_ff_all_agencies', 'sl_ff_import', 'sl_ff_manage_promos',
    ];
    foreach ( $sl_caps as $cap ) {
        $allcaps[ $cap ] = true;
    }
    return $allcaps;
}

/* ============================================================
   AJOUT DE REPAS PAR LE RESPONSABLE : autorisable/interdisable
   PAR RESPONSABLE (reglage individuel). On accorde dynamiquement
   create_sl_repas_items a chaque responsable selon son reglage, sans
   toucher a edit_sl_repas_items (il garde la gestion du planning).
   ============================================================ */

/** Reglage individuel d'un utilisateur (defaut : autorise). */
function sl_ff_user_can_add_repas( $user_id ) {
    $v = get_user_meta( $user_id, 'sl_ff_can_add', true );
    if ( $v === '' ) {
        // Pas de reglage individuel -> ancien reglage global (defaut autorise)
        return get_option( 'sl_ff_resp_can_add', '1' ) === '1';
    }
    return $v === '1';
}

/** Compat : ancien helper global (defaut). */
function sl_ff_resp_can_add() {
    return get_option( 'sl_ff_resp_can_add', '1' ) === '1';
}

/** Qui peut modifier ces reglages : admin WP, admin Fast Food, editeur WP. */
function sl_ff_can_manage_settings() {
    return current_user_can( 'manage_options' )
        || current_user_can( 'sl_ff_all_agencies' )
        || current_user_can( 'edit_others_posts' );
}

add_filter( 'user_has_cap', 'sl_ff_grant_resp_create_cap', 10, 4 );
function sl_ff_grant_resp_create_cap( $allcaps, $caps, $args, $user ) {
    // Ne concerne que la capacite de creation de repas
    if ( ! in_array( 'create_sl_repas_items', (array) $caps, true ) ) {
        return $allcaps;
    }
    // Admin WP / FF Admin / Editeur : toujours autorises (geres ailleurs)
    if ( ! empty( $allcaps['manage_options'] ) || ! empty( $allcaps['sl_ff_all_agencies'] ) ) {
        return $allcaps;
    }
    // Responsable Fast Food : selon SON reglage individuel
    if ( ! empty( $allcaps['edit_sl_repas_items'] ) ) {
        $uid = $user ? (int) $user->ID : 0;
        $allcaps['create_sl_repas_items'] = $uid ? sl_ff_user_can_add_repas( $uid ) : true;
    }
    return $allcaps;
}

/* ============================================================
   CONNEXION : redirection vers le planning
   (evite la redirection WooCommerce vers /my-account/)
   ============================================================ */

// Hook WordPress standard
add_filter( 'login_redirect', 'sl_ff_login_redirect', 10, 3 );
function sl_ff_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
    if ( ! $user || is_wp_error( $user ) ) return $redirect_to;
    if ( user_can( $user, 'edit_sl_repas_items' ) && ! user_can( $user, 'manage_options' ) ) {
        return admin_url( 'admin.php?page=sl-fastfood' );
    }
    return $redirect_to;
}

// Hook WooCommerce (formulaire de connexion front)
add_filter( 'woocommerce_login_redirect', 'sl_ff_wc_login_redirect', 10, 2 );
function sl_ff_wc_login_redirect( $redirect, $user ) {
    if ( ! $user || is_wp_error( $user ) ) return $redirect;
    if ( user_can( $user, 'edit_sl_repas_items' ) && ! user_can( $user, 'manage_options' ) ) {
        return admin_url( 'admin.php?page=sl-fastfood' );
    }
    return $redirect;
}

// WooCommerce bloque par defaut l'acces au dashboard pour les non-admins
add_filter( 'woocommerce_prevent_admin_access', 'sl_ff_wc_allow_admin_access' );
function sl_ff_wc_allow_admin_access( $prevent ) {
    if ( current_user_can( 'edit_sl_repas_items' ) ) return false;
    return $prevent;
}
