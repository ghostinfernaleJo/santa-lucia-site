<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function sl_ff_is_responsable() {
    return current_user_can( 'edit_sl_repas_items' );
}

function sl_ff_is_admin_user() {
    $user = wp_get_current_user();
    return current_user_can( 'manage_options' )
        || current_user_can( 'sl_ff_all_agencies' )
        || ( $user && in_array( 'sl_ff_admin', (array) $user->roles, true ) );
}

/* ============================================================
   MENU ADMIN
   ============================================================ */
add_action( 'admin_menu', 'sl_ff_build_menu', 999 );
function sl_ff_build_menu() {
    $can_manage_categories = current_user_can( 'manage_sl_repas_terms' );

    /* ── Administrateur WP ── */
    if ( current_user_can( 'manage_options' ) ) {
        add_menu_page( 'Fast Food', 'Fast Food', 'manage_options',
            'sl-fastfood', 'sl_ff_admin_page', 'dashicons-food', 26 );
        add_submenu_page( 'sl-fastfood', 'Planning', 'Planning',
            'manage_options', 'sl-fastfood', 'sl_ff_admin_page' );
        add_submenu_page( 'sl-fastfood', 'Importer des repas', 'Importer des repas',
            'manage_options', 'sl-ff-import', 'sl_ff_import_page' );
        add_submenu_page( 'sl-fastfood', 'Promotions', 'Promotions',
            'manage_options', 'sl-ff-promos', 'sl_ff_promos_page' );
        add_submenu_page( 'sl-fastfood', 'Tous les repas', 'Tous les repas',
            'manage_options', 'edit.php?post_type=sl_repas' );
        add_submenu_page( 'sl-fastfood', 'Ajouter un repas', 'Ajouter un repas',
            'manage_options', 'post-new.php?post_type=sl_repas' );
        add_submenu_page( 'sl-fastfood', 'Categories', 'Categories',
            'manage_options', 'sl-ff-categories', 'sl_ff_categories_page' );
        add_submenu_page( 'sl-fastfood', 'Reglages', 'Reglages',
            'manage_options', 'sl-ff-settings', 'sl_ff_settings_page' );
        return;
    }

    /* ── Administrateur Fast Food (toutes agences) ── */
    if ( sl_ff_is_admin_user() ) {
        add_menu_page( 'Fast Food', 'Fast Food', 'read',
            'sl-fastfood', 'sl_ff_admin_page', 'dashicons-food', 26 );
        add_submenu_page( 'sl-fastfood', 'Planning', 'Planning',
            'read', 'sl-fastfood', 'sl_ff_admin_page' );
        add_submenu_page( 'sl-fastfood', 'Importer des repas', 'Importer des repas',
            'read', 'sl-ff-import', 'sl_ff_import_page' );
        add_submenu_page( 'sl-fastfood', 'Promotions', 'Promotions',
            'read', 'sl-ff-promos', 'sl_ff_promos_page' );
        add_submenu_page( 'sl-fastfood', 'Tous les repas', 'Tous les repas',
            'read', 'edit.php?post_type=sl_repas' );
        add_submenu_page( 'sl-fastfood', 'Ajouter un repas', 'Ajouter un repas',
            'read', 'post-new.php?post_type=sl_repas' );
        add_submenu_page( 'sl-fastfood', 'Categories', 'Categories',
            'read', 'sl-ff-categories', 'sl_ff_categories_page' );
        add_submenu_page( 'sl-fastfood', 'Reglages', 'Reglages',
            'read', 'sl-ff-settings', 'sl_ff_settings_page' );
        return;
    }

    /* ── Acces categories seul (fallback si le role a ete personnalise) ── */
    if ( $can_manage_categories ) {
        add_menu_page( 'Fast Food', 'Fast Food', 'manage_sl_repas_terms',
            'sl-fastfood', 'sl_ff_categories_page', 'dashicons-food', 26 );
        add_submenu_page( 'sl-fastfood', 'Categories', 'Categories',
            'manage_sl_repas_terms', 'sl-ff-categories', 'sl_ff_categories_page' );
        return;
    }

    /* ── Responsable Fast Food (agence unique) ── */
    if ( sl_ff_is_responsable() ) {
        add_menu_page( 'Fast Food', 'Fast Food', 'edit_sl_repas_items',
            'sl-fastfood', 'sl_ff_admin_page', 'dashicons-food', 26 );
        add_submenu_page( 'sl-fastfood', 'Planning', 'Planning',
            'edit_sl_repas_items', 'sl-fastfood', 'sl_ff_admin_page' );
        add_submenu_page( 'sl-fastfood', 'Mes repas', 'Mes repas',
            'edit_sl_repas_items', 'edit.php?post_type=sl_repas' );
        // « Ajouter un repas » seulement si l'ajout est autorise (reglage admin)
        if ( current_user_can( 'create_sl_repas_items' ) ) {
            add_submenu_page( 'sl-fastfood', 'Ajouter un repas', 'Ajouter un repas',
                'create_sl_repas_items', 'post-new.php?post_type=sl_repas' );
        }
        return;
    }
    // Note : l'editeur WP recoit sl_ff_all_agencies (roles-fastfood.php) ->
    // il passe par la branche "Administrateur Fast Food" ci-dessus et obtient
    // le menu complet (planning toutes agences, disponibilite multi-agences,
    // reglages, etc.).
}

/* ============================================================
   PAGE CATEGORIES
   ============================================================ */
function sl_ff_categories_page() {
    if ( ! sl_ff_is_admin_user() && ! current_user_can( 'manage_sl_repas_terms' ) ) {
        wp_die( 'Acces refuse.' );
    }

    $message = '';
    $error   = '';

    if ( isset( $_POST['sl_ff_create_category'] ) ) {
        check_admin_referer( 'sl_ff_create_category' );

        $name = sanitize_text_field( $_POST['sl_ff_category_name'] ?? '' );
        if ( $name === '' ) {
            $error = 'Le nom de la categorie est obligatoire.';
        } else {
            $result = wp_insert_term( $name, 'sl_repas_cat' );
            if ( is_wp_error( $result ) ) {
                $error = $result->get_error_message();
            } else {
                $message = 'Categorie creee avec succes.';
            }
        }
    }

    if ( isset( $_POST['sl_ff_update_category'] ) ) {
        check_admin_referer( 'sl_ff_update_category' );

        $term_id = absint( $_POST['sl_ff_category_id'] ?? 0 );
        $name    = sanitize_text_field( $_POST['sl_ff_category_name'] ?? '' );
        if ( ! $term_id || $name === '' ) {
            $error = 'Categorie invalide.';
        } else {
            $result = wp_update_term( $term_id, 'sl_repas_cat', [ 'name' => $name ] );
            if ( is_wp_error( $result ) ) {
                $error = $result->get_error_message();
            } else {
                $message = 'Categorie modifiee avec succes.';
            }
        }
    }

    if ( isset( $_POST['sl_ff_delete_category'] ) ) {
        check_admin_referer( 'sl_ff_delete_category' );

        $term_id = absint( $_POST['sl_ff_category_id'] ?? 0 );
        if ( ! $term_id ) {
            $error = 'Categorie invalide.';
        } else {
            $result = wp_delete_term( $term_id, 'sl_repas_cat' );
            if ( is_wp_error( $result ) ) {
                $error = $result->get_error_message();
            } else {
                $message = 'Categorie supprimee avec succes.';
            }
        }
    }

    $terms = get_terms( [
        'taxonomy'   => 'sl_repas_cat',
        'hide_empty' => false,
        'orderby'    => 'name',
    ] );
    $terms = is_wp_error( $terms ) ? [] : $terms;
    ?>
    <div class="wrap sl-ff-planning-wrap">
        <div class="sl-ff-planning-header">
            <div class="sl-ff-planning-header-left">
                <h1 class="sl-ff-planning-titre">
                    <span class="dashicons dashicons-category"></span>
                    Categories Fast Food
                </h1>
                <p class="sl-ff-subtitle">Creez les categories utilisees pour classer les repas Fast Food.</p>
            </div>
        </div>

        <?php if ( $message ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
        <?php endif; ?>
        <?php if ( $error ) : ?>
            <div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
        <?php endif; ?>

        <div class="sl-ff-import-layout" style="display:grid;grid-template-columns:360px 1fr;gap:24px;max-width:980px;">
            <div class="sl-ff-import-card" style="background:#fff;border-radius:10px;padding:22px;box-shadow:0 1px 4px rgba(0,0,0,.08);">
                <h2 style="margin-top:0;">Ajouter une categorie</h2>
                <form method="post">
                    <?php wp_nonce_field( 'sl_ff_create_category' ); ?>
                    <p>
                        <label for="sl_ff_category_name"><strong>Nom</strong></label><br>
                        <input type="text" class="regular-text" id="sl_ff_category_name" name="sl_ff_category_name" style="width:100%;" required>
                    </p>
                    <p>
                        <button type="submit" name="sl_ff_create_category" class="button button-primary">Creer la categorie</button>
                    </p>
                </form>
            </div>

            <div class="sl-ff-import-card" style="background:#fff;border-radius:10px;padding:22px;box-shadow:0 1px 4px rgba(0,0,0,.08);">
                <h2 style="margin-top:0;">Categories existantes</h2>
                <?php if ( empty( $terms ) ) : ?>
                    <p>Aucune categorie pour le moment.</p>
                <?php else : ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Slug</th>
                                <th>Repas</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $terms as $term ) : ?>
                            <tr>
                                <td>
                                    <form method="post" style="display:flex;gap:8px;align-items:center;">
                                        <?php wp_nonce_field( 'sl_ff_update_category' ); ?>
                                        <input type="hidden" name="sl_ff_category_id" value="<?php echo (int) $term->term_id; ?>">
                                        <input type="text" name="sl_ff_category_name" value="<?php echo esc_attr( $term->name ); ?>" class="regular-text" style="max-width:260px;">
                                        <button type="submit" name="sl_ff_update_category" class="button">Modifier</button>
                                    </form>
                                </td>
                                <td><code><?php echo esc_html( $term->slug ); ?></code></td>
                                <td><?php echo (int) $term->count; ?></td>
                                <td>
                                    <form method="post" onsubmit="return confirm('Supprimer cette categorie ?');">
                                        <?php wp_nonce_field( 'sl_ff_delete_category' ); ?>
                                        <input type="hidden" name="sl_ff_category_id" value="<?php echo (int) $term->term_id; ?>">
                                        <button type="submit" name="sl_ff_delete_category" class="button button-link-delete">Supprimer</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

/* ============================================================
   PAGE REGLAGES
   ============================================================ */
function sl_ff_settings_page() {
    if ( ! sl_ff_can_manage_settings() ) {
        wp_die( 'Acces refuse.' );
    }

    // Liste des responsables Fast Food
    $responsables = get_users( [ 'role' => 'sl_responsable_fastfood', 'orderby' => 'display_name' ] );

    $message = '';
    if ( isset( $_POST['sl_ff_save_settings'] ) ) {
        check_admin_referer( 'sl_ff_save_settings' );
        $managed = array_map( 'intval', (array) ( $_POST['sl_ff_managed_users'] ?? [] ) );
        $allowed = array_map( 'intval', (array) ( $_POST['sl_ff_can_add'] ?? [] ) );
        foreach ( $managed as $uid ) {
            update_user_meta( $uid, 'sl_ff_can_add', in_array( $uid, $allowed, true ) ? '1' : '0' );
        }
        $message = 'Reglages enregistres.';
    }
    ?>
    <div class="wrap sl-ff-planning-wrap">
        <div class="sl-ff-planning-header">
            <div class="sl-ff-planning-header-left">
                <h1 class="sl-ff-planning-titre">
                    <span class="dashicons dashicons-admin-settings"></span>
                    Reglages Fast Food
                </h1>
                <p class="sl-ff-subtitle">Choisissez, responsable par responsable, qui peut ajouter de nouveaux repas.</p>
            </div>
        </div>

        <?php if ( $message ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
        <?php endif; ?>

        <div class="sl-ff-import-card" style="background:#fff;border-radius:10px;padding:22px;box-shadow:0 1px 4px rgba(0,0,0,.08);max-width:760px;">
            <h2 style="margin-top:0;">Ajout de repas par les responsables</h2>
            <p style="color:#555;">
                Cochez les responsables autorises a creer de nouveaux repas (menu
                &laquo;&nbsp;Ajouter un repas&nbsp;&raquo;). Decoche, un responsable peut toujours gerer
                le planning et modifier les repas existants, mais ne peut plus en ajouter.
            </p>

            <?php if ( empty( $responsables ) ) : ?>
                <p><em>Aucun responsable Fast Food n'est enregistre pour le moment.</em></p>
            <?php else : ?>
            <form method="post">
                <?php wp_nonce_field( 'sl_ff_save_settings' ); ?>
                <table class="widefat striped" style="margin-top:8px;max-width:720px;">
                    <thead>
                        <tr>
                            <th>Responsable</th>
                            <th>Agence</th>
                            <th style="text-align:center;width:200px;">Peut ajouter des repas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $responsables as $u ) :
                            $uid     = (int) $u->ID;
                            $ag      = get_user_meta( $uid, '_sl_agence_ff', true );
                            $can_add = sl_ff_user_can_add_repas( $uid );
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html( $u->display_name ); ?></strong><br>
                                <span style="color:#888;font-size:12px;"><?php echo esc_html( $u->user_login ); ?></span>
                                <input type="hidden" name="sl_ff_managed_users[]" value="<?php echo $uid; ?>">
                            </td>
                            <td><?php echo $ag ? esc_html( sl_ff_agency_name( $ag ) ) : '<span style="color:#bbb;">—</span>'; ?></td>
                            <td style="text-align:center;">
                                <label style="display:inline-flex;align-items:center;gap:8px;">
                                    <input type="checkbox" name="sl_ff_can_add[]" value="<?php echo $uid; ?>" <?php checked( $can_add ); ?>>
                                    <span><?php echo $can_add ? 'Autorise' : 'Bloque'; ?></span>
                                </label>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="margin-top:18px;">
                    <button type="submit" name="sl_ff_save_settings" class="button button-primary">Enregistrer</button>
                </p>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/* ============================================================
   GARDE-FOU : bloquer l'ecran "Ajouter un repas" si non autorise
   (defense en profondeur, au cas ou l'URL serait ouverte directement)
   ============================================================ */
add_action( 'load-post-new.php', 'sl_ff_guard_add_repas' );
function sl_ff_guard_add_repas() {
    $pt = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : 'post';
    if ( $pt === 'sl_repas' && ! current_user_can( 'create_sl_repas_items' ) ) {
        wp_die(
            'L\'ajout de nouveaux repas a ete desactive par l\'administrateur. '
            . 'Vous pouvez toujours modifier les repas existants et le planning.',
            'Ajout de repas desactive',
            [ 'response' => 403, 'back_link' => true ]
        );
    }
}

/* ============================================================
   ASSETS ADMIN
   ============================================================ */
add_action( 'admin_enqueue_scripts', 'sl_ff_admin_assets' );
function sl_ff_admin_assets( $hook ) {
    $is_ff_page = ( strpos( $hook, 'sl-fastfood' ) !== false )
               || ( strpos( $hook, 'sl-ff-import' ) !== false )
               || ( strpos( $hook, 'sl-ff-promos' ) !== false )
               || ( strpos( $hook, 'sl-ff-categories' ) !== false );
    if ( ! $is_ff_page ) return;

    $admin_css_ver = @filemtime( SL_FF_PATH . 'assets/css/fastfood-admin.css' ) ?: SL_FF_VERSION;
    $admin_js_ver  = @filemtime( SL_FF_PATH . 'assets/js/fastfood-admin-v4.js' ) ?: SL_FF_VERSION;

    wp_enqueue_style(  'sl-ff-admin', SL_FF_URL . 'assets/css/fastfood-admin.css', [], $admin_css_ver );
    wp_enqueue_script( 'sl-ff-admin', SL_FF_URL . 'assets/js/fastfood-admin-v4.js', [ 'jquery' ], $admin_js_ver, true );
    wp_localize_script( 'sl-ff-admin', 'slFF', [
        'ajaxurl'   => admin_url( 'admin-ajax.php' ),
        'nonce'     => wp_create_nonce( 'sl_ff_toggle' ),
        'todayJour' => sl_ff_today_jour(),
    ] );
}

/* ============================================================
   PAGE PLANNING HEBDOMADAIRE
   ============================================================ */
function sl_ff_admin_page() {
    $today       = current_time( 'Y-m-d' );
    $today_jour  = sl_ff_today_jour();
    $today_long  = date_i18n( 'l j F Y', strtotime( $today ) );
    $agence_user = get_user_meta( get_current_user_id(), '_sl_agence_ff', true );
    $is_admin    = current_user_can( 'manage_options' ) || current_user_can( 'sl_ff_all_agencies' );
    $all_agences = function_exists( 'sl_ff_all_agence_slugs' ) ? sl_ff_all_agence_slugs() : [];

    /* ---- Garde fail-closed : responsable sans agence assignee ----
       Sans agence, on n'affiche AUCUN repas (au lieu de toute la base) et on
       invite a contacter l'administrateur pour l'attribution. */
    if ( ! $is_admin && trim( (string) $agence_user ) === '' ) {
        ?>
        <div class="wrap sl-ff-planning-wrap">
            <div class="sl-ff-planning-header">
                <div class="sl-ff-planning-header-left">
                    <h1 class="sl-ff-planning-titre">
                        <span class="dashicons dashicons-food"></span>
                        Planning Hebdomadaire
                    </h1>
                </div>
            </div>
            <div class="notice notice-warning" style="margin-top:16px;">
                <p><strong>Aucune agence ne vous est encore attribuee.</strong></p>
                <p>Vous ne pouvez pas encore gerer de repas. Merci de contacter un
                   administrateur pour qu'il rattache votre compte a une agence.</p>
            </div>
        </div>
        <?php
        return;
    }

    $jours_list = [
        'lundi' => 'Lun', 'mardi' => 'Mar', 'mercredi' => 'Mer', 'jeudi' => 'Jeu',
        'vendredi' => 'Ven', 'samedi' => 'Sam', 'dimanche' => 'Dim',
    ];

    /* ---- Parametres de filtre/pagination (GET, couvrent TOUTE la base) ---- */
    $per_page   = 25;
    $cur_page   = isset( $_GET['ffp'] ) ? max( 1, (int) $_GET['ffp'] ) : 1;
    $search     = isset( $_GET['ffs'] ) ? sanitize_text_field( wp_unslash( $_GET['ffs'] ) ) : '';
    $agency_sel = ( $is_admin && isset( $_GET['ffa'] ) ) ? sanitize_title( wp_unslash( $_GET['ffa'] ) ) : '';
    $cat_sel    = isset( $_GET['ffc'] ) ? sanitize_title( wp_unslash( $_GET['ffc'] ) ) : '';
    $dispo      = isset( $_GET['ffd'] ) ? sanitize_key( wp_unslash( $_GET['ffd'] ) ) : '';

    // Scope agence : responsable = son agence ; admin = agence choisie (ou toutes)
    $scope_agence = $is_admin ? $agency_sel : sanitize_title( (string) $agence_user );

    /* ---- 1) IDs candidats (recherche + categorie + agence), sans limite ---- */
    $q_args = [
        'post_type'      => 'sl_repas',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ];
    if ( $search !== '' ) {
        $q_args['s'] = $search;
    }
    if ( $cat_sel !== '' ) {
        $q_args['tax_query'] = [ [ 'taxonomy' => 'sl_repas_cat', 'field' => 'slug', 'terms' => $cat_sel ] ];
    }
    if ( $scope_agence !== '' ) {
        $q_args['meta_query'] = [ [ 'key' => '_sl_ff_agence', 'value' => $scope_agence ] ];
    }
    $all_ids = get_posts( $q_args );

    /* ---- 2) Filtre disponibilite (calcule, sur le jeu candidat reduit) ---- */
    if ( $dispo !== '' && ! empty( $all_ids ) ) {
        $all_ids = array_values( array_filter( $all_ids, function ( $pid ) use ( $scope_agence, $today_jour ) {
            $jours = sl_ff_jours_for_filter( $pid, $scope_agence );
            if ( $dispo === 'today' )     return in_array( $today_jour, $jours, true );
            if ( $dispo === 'checked' )   return ! empty( $jours );
            if ( $dispo === 'unchecked' ) return empty( $jours );
            return true;
        } ) );
    }

    /* ---- 3) Pagination de la liste d'IDs ---- */
    $total      = count( $all_ids );
    $page_count = max( 1, (int) ceil( $total / $per_page ) );
    if ( $cur_page > $page_count ) $cur_page = $page_count;
    $offset    = ( $cur_page - 1 ) * $per_page;
    $page_ids  = array_slice( $all_ids, $offset, $per_page );

    /* ---- 4) Charger les posts de la page (amorce les caches meta/terms) ---- */
    $repas = [];
    if ( ! empty( $page_ids ) ) {
        $repas = get_posts( [
            'post_type'      => 'sl_repas',
            'post_status'    => 'publish',
            'post__in'       => $page_ids,
            'orderby'        => 'post__in',
            'posts_per_page' => count( $page_ids ),
        ] );
    }

    $grouped = [];
    foreach ( $repas as $r ) {
        $terms = get_the_terms( $r->ID, 'sl_repas_cat' );
        $cat   = ( $terms && ! is_wp_error( $terms ) ) ? sl_ff_cat_display( $terms[0]->name ) : 'Sans categorie';
        $grouped[ $cat ][] = $r;
    }
    ksort( $grouped );

    // Liste complete des categories pour le menu deroulant (toute la base)
    $cat_terms = get_terms( [ 'taxonomy' => 'sl_repas_cat', 'hide_empty' => false, 'orderby' => 'name' ] );
    $cat_terms = is_wp_error( $cat_terms ) ? [] : $cat_terms;
    $agence_terms = [];
    if ( $is_admin ) {
        $agence_terms = get_terms( [ 'taxonomy' => 'sl_agence_promo', 'hide_empty' => false, 'orderby' => 'name' ] );
        $agence_terms = is_wp_error( $agence_terms ) ? [] : $agence_terms;
    }

    // URL de base pour les liens de pagination (conserve les filtres)
    $base_args = [ 'page' => 'sl-fastfood' ];
    if ( $search !== '' )     $base_args['ffs'] = $search;
    if ( $agency_sel !== '' ) $base_args['ffa'] = $agency_sel;
    if ( $cat_sel !== '' )    $base_args['ffc'] = $cat_sel;
    if ( $dispo !== '' )      $base_args['ffd'] = $dispo;

    $nb_cols = 1 + 7 + ( $is_admin ? 1 : 0 ) + 1;
    ?>
    <div class="wrap sl-ff-planning-wrap">

        <div class="sl-ff-planning-header">
            <div class="sl-ff-planning-header-left">
                <h1 class="sl-ff-planning-titre">
                    <span class="dashicons dashicons-food"></span>
                    Planning Hebdomadaire
                </h1>
                <p class="sl-ff-subtitle">
                    Cochez les jours o&ugrave; chaque plat est disponible.
                    Le site affiche automatiquement les bons plats chaque jour.
                    <span class="sl-ff-today-badge">Aujourd&#39;hui&nbsp;: <?php echo esc_html( $today_long ); ?></span>
                </p>
            </div>
            <form class="sl-ff-filter-bar" id="sl-ff-filter-form" method="get">
                <input type="hidden" name="page" value="sl-fastfood">
                <label>
                    <strong>Rechercher un repas</strong>
                    <input type="search" name="ffs" value="<?php echo esc_attr( $search ); ?>" placeholder="Nom du repas (toute la base)..." style="min-width:240px;">
                </label>
                <?php if ( $is_admin ) : ?>
                <label>
                    <strong>Filtrer par agence</strong>
                    <select name="ffa" class="sl-ff-autosubmit">
                        <option value="">Toutes les agences</option>
                        <?php foreach ( $agence_terms as $a ) : ?>
                            <option value="<?php echo esc_attr( $a->slug ); ?>" <?php selected( $agency_sel, $a->slug ); ?>><?php echo esc_html( sl_ff_agency_name( $a->name ) ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php endif; ?>
                <label>
                    <strong>Categorie</strong>
                    <select name="ffc" class="sl-ff-autosubmit">
                        <option value="">Toutes les categories</option>
                        <?php foreach ( $cat_terms as $t ) : ?>
                        <option value="<?php echo esc_attr( $t->slug ); ?>" <?php selected( $cat_sel, $t->slug ); ?>><?php echo esc_html( sl_ff_cat_display( $t->name ) ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <strong>Disponibilite</strong>
                    <select name="ffd" class="sl-ff-autosubmit">
                        <option value="">Tous les repas</option>
                        <option value="today"     <?php selected( $dispo, 'today' ); ?>>Disponibles aujourd&#39;hui</option>
                        <option value="checked"   <?php selected( $dispo, 'checked' ); ?>>Au moins un jour coch&eacute;</option>
                        <option value="unchecked" <?php selected( $dispo, 'unchecked' ); ?>>Aucun jour coch&eacute;</option>
                    </select>
                </label>
                <button type="submit" class="button button-primary">Rechercher</button>
                <?php if ( $search !== '' || $agency_sel !== '' || $cat_sel !== '' || $dispo !== '' ) : ?>
                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sl-fastfood' ) ); ?>">R&eacute;initialiser</a>
                <?php endif; ?>
            </form>
        </div>

        <p class="sl-ff-result-line">
            <strong><?php echo (int) $total; ?></strong> repas trouv&eacute;(s)
            <?php if ( $total > 0 ) : ?>
                &mdash; page <?php echo (int) $cur_page; ?> / <?php echo (int) $page_count; ?>
            <?php endif; ?>
        </p>

        <?php if ( empty( $repas ) ) : ?>
        <div class="sl-ff-empty">
            <span class="dashicons dashicons-food" style="font-size:48px;color:#ddd;width:auto;height:auto;display:block;margin-bottom:8px;"></span>
            <?php if ( $search !== '' || $agency_sel !== '' || $cat_sel !== '' || $dispo !== '' ) : ?>
            <p>Aucun repas ne correspond &agrave; ces crit&egrave;res. <a href="<?php echo esc_url( admin_url( 'admin.php?page=sl-fastfood' ) ); ?>">R&eacute;initialiser la recherche</a></p>
            <?php else : ?>
            <p>Aucun repas configure. <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=sl_repas' ) ); ?>">Ajouter le premier repas &rarr;</a></p>
            <?php endif; ?>
        </div>
        <?php else : ?>

        <table class="sl-ff-planning-table" id="sl-ff-planning-table">
            <thead>
                <tr>
                    <th class="sl-ff-col-plat">Plat</th>
                    <?php foreach ( $jours_list as $slug => $label ) : ?>
                    <th class="sl-ff-col-jour<?php echo ( $slug === $today_jour ) ? ' sl-ff-today-col' : ''; ?>">
                        <?php echo esc_html( $label ); ?>
                        <?php if ( $slug === $today_jour ) : ?><span class="sl-ff-today-dot">&#9679;</span><?php endif; ?>
                    </th>
                    <?php endforeach; ?>
                    <?php if ( $is_admin ) : ?><th class="sl-ff-col-action"></th><?php endif; ?>
                    <th class="sl-ff-col-status"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $grouped as $cat_name => $items ) : ?>
                <tr class="sl-ff-cat-row" data-cat="<?php echo esc_attr( sl_ff_norm_txt( $cat_name ) ); ?>">
                    <td colspan="<?php echo $nb_cols; ?>"><?php echo esc_html( $cat_name ); ?></td>
                </tr>
                <?php foreach ( $items as $ri ) :
                    if ( $scope_agence !== '' ) {
                        // Une agence ciblee (responsable, ou admin filtre par agence) : 1 ligne/repas
                        $agences_r = [ $scope_agence ];
                    } elseif ( $is_admin && ! empty( $all_agences ) ) {
                        // Admin toutes agences : 1 ligne par agence, MEME non cochee,
                        // pour pouvoir activer le repas dans n'importe quelle agence.
                        $agences_r = $all_agences;
                    } else {
                        $agences_r = function_exists( 'sl_ff_post_agence_slugs' )
                            ? sl_ff_post_agence_slugs( $ri->ID )
                            : array_values( array_filter( array_unique( array_map( 'sanitize_title', (array) get_post_meta( $ri->ID, '_sl_ff_agence' ) ) ) ) );
                        if ( empty( $agences_r ) ) $agences_r = [ '' ];
                    }
                    $thumb_url = get_the_post_thumbnail_url( $ri->ID, 'thumbnail' );
                    foreach ( $agences_r as $agence_r ) :
                    $jours_saved = function_exists( 'sl_ff_get_agence_jours' )
                        ? sl_ff_get_agence_jours( $ri->ID, $agence_r )
                        : (array) get_post_meta( $ri->ID, '_sl_ff_jours', true );
                    $is_today    = in_array( $today_jour, $jours_saved, true );
                    $has_checked = ! empty( $jours_saved );
                    $search_str  = sl_ff_norm_txt( $ri->post_title . ' '
                        . ( $agence_r ? sl_ff_agency_name( $agence_r ) : '' ) . ' ' . $cat_name );
                ?>
                <tr class="sl-ff-meal-row<?php echo $is_today ? ' sl-ff-meal-dispo' : ''; ?>"
                    data-id="<?php echo (int) $ri->ID; ?>"
                    data-agence="<?php echo esc_attr( $agence_r ); ?>"
                    data-cat="<?php echo esc_attr( sl_ff_norm_txt( $cat_name ) ); ?>"
                    data-search="<?php echo esc_attr( $search_str ); ?>"
                    data-checked="<?php echo $has_checked ? '1' : '0'; ?>"
                    data-today="<?php echo $is_today ? '1' : '0'; ?>">
                    <td class="sl-ff-col-plat">
                        <div class="sl-ff-plat-info">
                            <?php if ( $thumb_url ) : ?>
                            <div class="sl-ff-plat-thumb" style="background-image:url('<?php echo esc_url( $thumb_url ); ?>')"></div>
                            <?php else : ?>
                            <div class="sl-ff-plat-thumb sl-ff-plat-no-img">&#127869;</div>
                            <?php endif; ?>
                            <div>
                                <div class="sl-ff-plat-nom"><?php echo esc_html( $ri->post_title ); ?></div>
                                <?php if ( $is_admin && $agence_r ) : ?>
                                <div class="sl-ff-plat-agence"><?php echo esc_html( sl_ff_agency_name( $agence_r ) ); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <?php foreach ( $jours_list as $slug => $label ) :
                        $is_checked   = in_array( $slug, $jours_saved, true );
                        $is_today_col = ( $slug === $today_jour );
                    ?>
                    <td class="sl-ff-day-cell<?php echo $is_today_col ? ' sl-ff-today-col-body' : ''; ?>">
                        <label class="sl-ff-cb-label">
                            <input type="checkbox" class="sl-ff-day-cb"
                                   value="<?php echo esc_attr( $slug ); ?>"
                                   <?php checked( $is_checked ); ?>>
                        </label>
                    </td>
                    <?php endforeach; ?>
                    <?php if ( $is_admin ) : ?>
                    <td class="sl-ff-col-action">
                        <a href="<?php echo esc_url( get_edit_post_link( $ri->ID ) ); ?>"
                           class="sl-ff-edit-btn" title="Modifier la fiche">&#9998;</a>
                    </td>
                    <?php endif; ?>
                    <td class="sl-ff-col-status"><span class="sl-ff-save-icon"></span></td>
                </tr>
                <?php endforeach; ?>
                <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ( $page_count > 1 ) :
            $prev = max( 1, $cur_page - 1 );
            $next = min( $page_count, $cur_page + 1 );
        ?>
        <div class="sl-ff-pagination tablenav-pages">
            <?php if ( $cur_page > 1 ) : ?>
                <a class="button" href="<?php echo esc_url( add_query_arg( array_merge( $base_args, [ 'ffp' => 1 ] ), admin_url( 'admin.php' ) ) ); ?>">&laquo; Premi&egrave;re</a>
                <a class="button" href="<?php echo esc_url( add_query_arg( array_merge( $base_args, [ 'ffp' => $prev ] ), admin_url( 'admin.php' ) ) ); ?>">&lsaquo; Pr&eacute;c&eacute;dent</a>
            <?php endif; ?>
            <span class="sl-ff-page-indicator">Page <?php echo (int) $cur_page; ?> sur <?php echo (int) $page_count; ?></span>
            <?php if ( $cur_page < $page_count ) : ?>
                <a class="button" href="<?php echo esc_url( add_query_arg( array_merge( $base_args, [ 'ffp' => $next ] ), admin_url( 'admin.php' ) ) ); ?>">Suivant &rsaquo;</a>
                <a class="button" href="<?php echo esc_url( add_query_arg( array_merge( $base_args, [ 'ffp' => $page_count ] ), admin_url( 'admin.php' ) ) ); ?>">Derni&egrave;re &raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Jours effectifs d'un repas pour le filtre de disponibilite.
 * - agence precisee : jours de cette agence ;
 * - sinon (admin toutes agences) : union des jours de toutes ses agences
 *   (+ ancien champ global pour compat).
 */
function sl_ff_jours_for_filter( $post_id, $agence = '' ) {
    if ( $agence !== '' ) {
        return sl_ff_get_agence_jours( $post_id, $agence );
    }
    $u = (array) get_post_meta( $post_id, '_sl_ff_jours', true );
    if ( function_exists( 'sl_ff_post_agence_slugs' ) ) {
        foreach ( sl_ff_post_agence_slugs( $post_id ) as $ag ) {
            $u = array_merge( $u, sl_ff_get_agence_jours( $post_id, $ag ) );
        }
    }
    return array_values( array_unique( array_filter( $u ) ) );
}
