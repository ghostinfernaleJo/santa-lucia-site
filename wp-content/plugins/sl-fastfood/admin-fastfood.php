<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function sl_ff_is_responsable() {
    return current_user_can( 'edit_sl_repas_items' );
}

/* ============================================================
   MENU ADMIN
   ============================================================ */
add_action( 'admin_menu', 'sl_ff_build_menu', 999 );
function sl_ff_build_menu() {

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
            'manage_options', 'edit-tags.php?taxonomy=sl_repas_cat&post_type=sl_repas' );
        return;
    }

    /* ── Administrateur Fast Food (toutes agences) ── */
    if ( current_user_can( 'sl_ff_all_agencies' ) ) {
        add_menu_page( 'Fast Food', 'Fast Food', 'sl_ff_all_agencies',
            'sl-fastfood', 'sl_ff_admin_page', 'dashicons-food', 26 );
        add_submenu_page( 'sl-fastfood', 'Planning', 'Planning',
            'sl_ff_all_agencies', 'sl-fastfood', 'sl_ff_admin_page' );
        add_submenu_page( 'sl-fastfood', 'Importer des repas', 'Importer des repas',
            'sl_ff_import', 'sl-ff-import', 'sl_ff_import_page' );
        add_submenu_page( 'sl-fastfood', 'Promotions', 'Promotions',
            'sl_ff_manage_promos', 'sl-ff-promos', 'sl_ff_promos_page' );
        add_submenu_page( 'sl-fastfood', 'Tous les repas', 'Tous les repas',
            'sl_ff_all_agencies', 'edit.php?post_type=sl_repas' );
        add_submenu_page( 'sl-fastfood', 'Ajouter un repas', 'Ajouter un repas',
            'sl_ff_all_agencies', 'post-new.php?post_type=sl_repas' );
        add_submenu_page( 'sl-fastfood', 'Categories', 'Categories',
            'manage_sl_repas_terms', 'edit-tags.php?taxonomy=sl_repas_cat&post_type=sl_repas' );
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
   ASSETS ADMIN
   ============================================================ */
add_action( 'admin_enqueue_scripts', 'sl_ff_admin_assets' );
function sl_ff_admin_assets( $hook ) {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    $is_repas_screen = $screen && isset( $screen->post_type ) && $screen->post_type === 'sl_repas';
    $is_ff_page = ( strpos( $hook, 'sl-fastfood' ) !== false )
               || ( strpos( $hook, 'sl-ff-import' ) !== false )
               || ( strpos( $hook, 'sl-ff-promos' ) !== false )
               || $is_repas_screen;
    if ( ! $is_ff_page ) return;

    wp_enqueue_style(  'sl-ff-admin', SL_FF_URL . 'assets/css/fastfood-admin.css', [], SL_FF_VERSION );
    wp_enqueue_script( 'sl-ff-admin', SL_FF_URL . 'assets/js/fastfood-admin.js', [ 'jquery' ], SL_FF_VERSION, true );
    if ( $is_repas_screen && current_user_can( 'upload_files' ) ) {
        wp_enqueue_media();
    }
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
    if ( ! $is_admin && $agence_user ) {
        $args['meta_query'] = [
            [ 'key' => '_sl_ff_agence', 'value' => $agence_user ],
        ];
    }
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
                    $jours_saved = (array) get_post_meta( $ri->ID, '_sl_ff_jours', true );
                    $agence_r    = get_post_meta( $ri->ID, '_sl_ff_agence', true );
                    $thumb_url   = get_the_post_thumbnail_url( $ri->ID, 'thumbnail' );
                    $is_today    = in_array( $today_jour, $jours_saved, true );
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
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php
}
