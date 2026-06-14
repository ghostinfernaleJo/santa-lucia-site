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
    if ( ! sl_ff_is_responsable() ) return;
    add_menu_page( 'Fast Food', 'Fast Food', 'edit_sl_repas_items',
        'sl-fastfood', 'sl_ff_admin_page', 'dashicons-food', 26 );
    add_submenu_page( 'sl-fastfood', 'Planning', 'Planning',
        'edit_sl_repas_items', 'sl-fastfood', 'sl_ff_admin_page' );
    add_submenu_page( 'sl-fastfood', 'Mes repas', 'Mes repas',
        'edit_sl_repas_items', 'edit.php?post_type=sl_repas' );
    add_submenu_page( 'sl-fastfood', 'Ajouter un repas', 'Ajouter un repas',
        'edit_sl_repas_items', 'post-new.php?post_type=sl_repas' );
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
    $admin_js_ver  = @filemtime( SL_FF_PATH . 'assets/js/fastfood-admin.js' ) ?: SL_FF_VERSION;

    wp_enqueue_style(  'sl-ff-admin', SL_FF_URL . 'assets/css/fastfood-admin.css', [], $admin_css_ver );
    wp_enqueue_script( 'sl-ff-admin', SL_FF_URL . 'assets/js/fastfood-admin.js', [ 'jquery' ], $admin_js_ver, true );
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

    $jours_list = [
        'lundi'    => 'Lun',
        'mardi'    => 'Mar',
        'mercredi' => 'Mer',
        'jeudi'    => 'Jeu',
        'vendredi' => 'Ven',
        'samedi'   => 'Sam',
        'dimanche' => 'Dim',
    ];

    $args = [
        'post_type'      => 'sl_repas',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ];
    $repas = get_posts( $args );

    $grouped = [];
    foreach ( $repas as $r ) {
        $cats = wp_get_post_terms( $r->ID, 'sl_repas_cat' );
        $cat  = ( ! empty( $cats ) && ! is_wp_error( $cats ) )
                ? sl_ff_cat_display( $cats[0]->name ) : 'Sans categorie';
        $grouped[ $cat ][] = $r;
    }
    ksort( $grouped );

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
            <?php if ( $is_admin ) : ?>
            <div class="sl-ff-filter-bar">
                <label>
                    <strong>Rechercher un repas</strong>
                    <input type="search" id="sl-ff-meal-search" placeholder="Nom du repas..." style="min-width:240px;">
                </label>
                <label>
                    <strong>Filtrer par agence</strong>
                    <select id="sl-ff-agence-filter">
                        <option value="">Toutes les agences</option>
                        <?php
                        $agences = get_terms( [ 'taxonomy' => 'sl_agence_promo', 'hide_empty' => false ] );
                        if ( ! is_wp_error( $agences ) ) :
                            foreach ( $agences as $a ) : ?>
                            <option value="<?php echo esc_attr( $a->slug ); ?>"><?php echo esc_html( sl_ff_agency_name( $a->name ) ); ?></option>
                        <?php endforeach; endif; ?>
                    </select>
                </label>
            </div>
            <?php endif; ?>
        </div>

        <?php if ( empty( $repas ) ) : ?>
        <div class="sl-ff-empty">
            <span class="dashicons dashicons-food" style="font-size:48px;color:#ddd;width:auto;height:auto;display:block;margin-bottom:8px;"></span>
            <p>Aucun repas configure. <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=sl_repas' ) ); ?>">Ajouter le premier repas &rarr;</a></p>
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
                <tr class="sl-ff-cat-row">
                    <td colspan="<?php echo $nb_cols; ?>"><?php echo esc_html( $cat_name ); ?></td>
                </tr>
                <?php foreach ( $items as $ri ) :
                    $agences_r = function_exists( 'sl_ff_post_agence_slugs' )
                        ? sl_ff_post_agence_slugs( $ri->ID )
                        : array_values( array_filter( array_unique( array_map( 'sanitize_title', (array) get_post_meta( $ri->ID, '_sl_ff_agence' ) ) ) ) );
                    if ( $is_admin && ! empty( $all_agences ) ) {
                        $agences_r = $all_agences;
                    } elseif ( ! $is_admin && $agence_user ) {
                        $agences_r = [ sanitize_title( $agence_user ) ];
                    } elseif ( empty( $agences_r ) ) {
                        $agences_r = [ '' ];
                    }
                    $thumb_url = get_the_post_thumbnail_url( $ri->ID, 'thumbnail' );
                    foreach ( $agences_r as $agence_r ) :
                    $jours_saved = function_exists( 'sl_ff_get_agence_jours' )
                        ? sl_ff_get_agence_jours( $ri->ID, $agence_r )
                        : (array) get_post_meta( $ri->ID, '_sl_ff_jours', true );
                    $is_today = in_array( $today_jour, $jours_saved, true );
                ?>
                <tr class="sl-ff-meal-row<?php echo $is_today ? ' sl-ff-meal-dispo' : ''; ?>"
                    data-id="<?php echo (int) $ri->ID; ?>"
                    data-agence="<?php echo esc_attr( $agence_r ); ?>">
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
        <?php endif; ?>
    </div>
    <?php
}
