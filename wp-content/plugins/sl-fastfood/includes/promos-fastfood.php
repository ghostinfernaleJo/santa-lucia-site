<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   PAGE : GESTION DES PROMOTIONS
   ============================================================ */
function sl_ff_promos_page() {
    if ( ! current_user_can( 'sl_ff_manage_promos' ) ) {
        wp_die( 'Acces refuse.' );
    }

    $today       = current_time( 'Y-m-d' );
    $agence_user = get_user_meta( get_current_user_id(), '_sl_agence_ff', true );
    $is_admin    = current_user_can( 'manage_options' ) || current_user_can( 'sl_ff_all_agencies' );

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

    // Grouper par agence (pour admin), sinon par categorie
    $grouped = [];
    if ( $is_admin ) {
        foreach ( $repas as $r ) {
            $agence = get_post_meta( $r->ID, '_sl_ff_agence', true );
            $key    = $agence ? sl_ff_agency_name( $agence ) : '— Sans agence —';
            $grouped[ $key ][] = $r;
        }
    } else {
        foreach ( $repas as $r ) {
            $cats = wp_get_post_terms( $r->ID, 'sl_repas_cat' );
            $cat  = ( ! empty( $cats ) && ! is_wp_error( $cats ) )
                    ? sl_ff_cat_display( $cats[0]->name ) : 'Sans categorie';
            $grouped[ $cat ][] = $r;
        }
    }
    ksort( $grouped );

    $nb_cols = $is_admin ? 8 : 7; // Plat, Prix, PrixPromo, Remise%, Debut, Fin, Statut, [Agence si admin]
    ?>
    <div class="wrap sl-ff-planning-wrap">

        <div class="sl-ff-planning-header">
            <div class="sl-ff-planning-header-left">
                <h1 class="sl-ff-planning-titre">
                    <span class="dashicons dashicons-tag"></span>
                    Gestion des Promotions
                </h1>
                <p class="sl-ff-subtitle">
                    Definissez une remise (%) et une periode pour chaque plat.
                    Le badge promo s&#39;affiche automatiquement sur le site pendant la periode.
                    <span class="sl-ff-today-badge">Aujourd&#39;hui&nbsp;: <?php echo esc_html( date_i18n( 'j F Y', strtotime( $today ) ) ); ?></span>
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
            <p>Aucun repas configure.</p>
        </div>

        <?php else : ?>
        <table class="sl-ff-planning-table sl-ff-promos-table" id="sl-ff-promos-table">
            <thead>
                <tr>
                    <th class="sl-ff-col-plat">Plat</th>
                    <?php if ( $is_admin ) : ?>
                    <th>Agence</th>
                    <?php endif; ?>
                    <th>Prix&nbsp;actuel&nbsp;(FCFA)</th>
                    <th>Prix&nbsp;promo&nbsp;(FCFA)</th>
                    <th>Remise&nbsp;(%)</th>
                    <th>D&eacute;but</th>
                    <th>Fin</th>
                    <th>Statut</th>
                    <th class="sl-ff-col-status"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $grouped as $group_name => $items ) : ?>
                <tr class="sl-ff-cat-row">
                    <td colspan="<?php echo $nb_cols + 1; ?>"><?php echo esc_html( $group_name ); ?></td>
                </tr>
                <?php foreach ( $items as $ri ) :
                    $agence_r    = get_post_meta( $ri->ID, '_sl_ff_agence', true );
                    $thumb_url   = get_the_post_thumbnail_url( $ri->ID, 'thumbnail' );
                    $promo_pct   = (int) get_post_meta( $ri->ID, '_sl_ff_promo_prix',  true );
                    $prix        = (int) get_post_meta( $ri->ID, '_sl_ff_prix',        true );
                    $prix_promo  = (int) get_post_meta( $ri->ID, '_sl_ff_prix_promo',  true );
                    $promo_debut = get_post_meta( $ri->ID, '_sl_ff_promo_debut', true );
                    $promo_fin   = get_post_meta( $ri->ID, '_sl_ff_promo_fin',   true );
                    $promo_info  = sl_ff_get_promo_info( $ri->ID );
                    $is_active   = $promo_info['est_promo'];
                ?>
                <tr class="sl-ff-meal-row<?php echo $is_active ? ' sl-ff-promo-active' : ''; ?>"
                    data-id="<?php echo (int) $ri->ID; ?>"
                    data-agence="<?php echo esc_attr( $agence_r ); ?>">

                    <!-- Plat -->
                    <td class="sl-ff-col-plat">
                        <div class="sl-ff-plat-info">
                            <?php if ( $thumb_url ) : ?>
                            <div class="sl-ff-plat-thumb" style="background-image:url('<?php echo esc_url( $thumb_url ); ?>')"></div>
                            <?php else : ?>
                            <div class="sl-ff-plat-thumb sl-ff-plat-no-img">&#127869;</div>
                            <?php endif; ?>
                            <div class="sl-ff-plat-nom"><?php echo esc_html( $ri->post_title ); ?></div>
                        </div>
                    </td>

                    <?php if ( $is_admin ) : ?>
                    <td style="font-size:12px;color:#888;"><?php echo esc_html( sl_ff_agency_name( $agence_r ) ); ?></td>
                    <?php endif; ?>

                    <!-- Prix actuel -->
                    <td>
                        <input type="number" class="sl-ff-promo-prix"
                               value="<?php echo esc_attr( $prix ?: '' ); ?>"
                               min="0" step="50" placeholder="—"
                               style="width:90px;text-align:center;padding:4px 6px;">
                    </td>

                    <!-- Prix promo -->
                    <td>
                        <input type="number" class="sl-ff-promo-prix-promo"
                               value="<?php echo esc_attr( $prix_promo ?: '' ); ?>"
                               min="0" step="50" placeholder="—"
                               style="width:90px;text-align:center;padding:4px 6px;">
                    </td>

                    <!-- Remise % -->
                    <td>
                        <input type="number" class="sl-ff-promo-pct"
                               value="<?php echo esc_attr( $promo_pct ?: '' ); ?>"
                               min="0" max="100" placeholder="auto"
                               style="width:60px;text-align:center;padding:4px 6px;">
                        <span style="font-size:12px;color:#888;">%</span>
                    </td>

                    <!-- Date debut -->
                    <td>
                        <input type="date" class="sl-ff-promo-debut"
                               value="<?php echo esc_attr( $promo_debut ); ?>"
                               style="padding:4px 6px;font-size:12px;">
                    </td>

                    <!-- Date fin -->
                    <td>
                        <input type="date" class="sl-ff-promo-fin"
                               value="<?php echo esc_attr( $promo_fin ); ?>"
                               style="padding:4px 6px;font-size:12px;">
                    </td>

                    <!-- Statut -->
                    <td>
                        <?php if ( $is_active ) : ?>
                        <span class="sl-ff-promo-badge-actif">&#10003; Actif
                            <?php if ( $promo_info['pct_reduction'] > 0 ) echo ' -' . (int) $promo_info['pct_reduction'] . '%'; ?>
                            <?php if ( $promo_info['prix_promo'] > 0 ) echo ' ' . esc_html( sl_ff_format_prix( $promo_info['prix_promo'] ) ); ?>
                        </span>
                        <?php elseif ( $promo_pct > 0 || $prix_promo > 0 ) : ?>
                        <span style="font-size:12px;color:#aaa;">&#9679; Planifie</span>
                        <?php else : ?>
                        <span style="font-size:12px;color:#ccc;">&#8212; Aucune</span>
                        <?php endif; ?>
                    </td>

                    <!-- Indicateur sauvegarde -->
                    <td class="sl-ff-col-status"><span class="sl-ff-save-icon"></span></td>
                </tr>
                <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <style>
    .sl-ff-promos-table input[type="number"],
    .sl-ff-promos-table input[type="date"] {
        border:1px solid #ddd; border-radius:5px; background:#fafafa; font-size:13px;
    }
    .sl-ff-promos-table input[type="number"]:focus,
    .sl-ff-promos-table input[type="date"]:focus {
        border-color:#e91e8c; outline:none; background:#fff;
    }
    .sl-ff-promo-badge-actif {
        display:inline-block; background:#e91e8c; color:#fff;
        border-radius:20px; padding:2px 9px; font-size:12px; font-weight:700;
    }
    .sl-ff-promo-active > td:first-child { border-left:4px solid #e91e8c !important; }
    </style>
    <?php
}

/* ============================================================
   AJAX : SAUVEGARDER UNE PROMOTION
   ============================================================ */
add_action( 'wp_ajax_sl_ff_save_promo', 'sl_ff_ajax_save_promo' );
function sl_ff_ajax_save_promo() {
    check_ajax_referer( 'sl_ff_toggle', 'nonce' );

    if ( ! current_user_can( 'sl_ff_manage_promos' ) ) {
        wp_send_json_error( 'Acces refuse' );
    }

    $post_id     = intval( $_POST['post_id'] ?? 0 );
    $promo_pct   = intval( $_POST['promo_pct']  ?? 0 );
    $prix        = intval( $_POST['prix']       ?? 0 );
    $prix_promo  = intval( $_POST['prix_promo'] ?? 0 );
    $promo_debut = sanitize_text_field( $_POST['promo_debut'] ?? '' );
    $promo_fin   = sanitize_text_field( $_POST['promo_fin']   ?? '' );

    if ( ! $post_id ) wp_send_json_error( 'ID invalide' );

    // Verifier l'acces a ce repas (multi-agences : matcher n'importe quelle ligne)
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'sl_ff_all_agencies' ) ) {
        $agence_user  = get_user_meta( get_current_user_id(), '_sl_agence_ff', true );
        $agences_post = (array) get_post_meta( $post_id, '_sl_ff_agence' );
        if ( ! $agence_user || ! in_array( $agence_user, $agences_post, true ) ) {
            wp_send_json_error( 'Acces refuse' );
        }
    }

    // Prix actuel : indépendant de la promo (affiché en permanence sur le site)
    if ( $prix > 0 ) {
        update_post_meta( $post_id, '_sl_ff_prix', $prix );
    } else {
        delete_post_meta( $post_id, '_sl_ff_prix' );
    }

    // Promo = remise % et/ou prix promo, avec période
    if ( $promo_pct > 0 || $prix_promo > 0 ) {
        if ( $promo_pct > 0 ) {
            update_post_meta( $post_id, '_sl_ff_promo_prix', $promo_pct );
        } else {
            delete_post_meta( $post_id, '_sl_ff_promo_prix' );
        }
        if ( $prix_promo > 0 ) {
            update_post_meta( $post_id, '_sl_ff_prix_promo', $prix_promo );
        } else {
            delete_post_meta( $post_id, '_sl_ff_prix_promo' );
        }
        update_post_meta( $post_id, '_sl_ff_promo_debut', $promo_debut );
        update_post_meta( $post_id, '_sl_ff_promo_fin',   $promo_fin );
    } else {
        delete_post_meta( $post_id, '_sl_ff_promo_prix' );
        delete_post_meta( $post_id, '_sl_ff_prix_promo' );
        delete_post_meta( $post_id, '_sl_ff_promo_debut' );
        delete_post_meta( $post_id, '_sl_ff_promo_fin' );
    }

    if ( function_exists( 'sl_ff_bump_menu_cache' ) ) sl_ff_bump_menu_cache();

    $promo_info = sl_ff_get_promo_info( $post_id );
    wp_send_json_success( [
        'est_promo'  => $promo_info['est_promo'],
        'pct'        => $promo_info['pct_reduction'],
        'prix'       => $promo_info['prix'],
        'prix_promo' => $promo_info['prix_promo'],
    ] );
}
