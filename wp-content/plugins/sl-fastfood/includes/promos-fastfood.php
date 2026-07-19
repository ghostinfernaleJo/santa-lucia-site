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
    $agence_user = sanitize_title( (string) get_user_meta( get_current_user_id(), '_sl_agence_ff', true ) );
    $is_admin    = current_user_can( 'manage_options' ) || current_user_can( 'sl_ff_all_agencies' );

    // Agence ciblee : responsable = son agence ; admin = agence choisie (GET ffa).
    // Les promos sont propres a l'agence -> on travaille toujours sur UNE agence.
    $agency_sel   = ( $is_admin && isset( $_GET['ffa'] ) ) ? sanitize_title( wp_unslash( $_GET['ffa'] ) ) : '';
    $scope_agence = $is_admin ? $agency_sel : $agence_user;
    $scope_nom    = $scope_agence ? sl_ff_agency_name( $scope_agence ) : '';

    $agence_terms = get_terms( [ 'taxonomy' => 'sl_agence_promo', 'hide_empty' => false, 'orderby' => 'name' ] );
    $agence_terms = is_wp_error( $agence_terms ) ? [] : $agence_terms;

    $no_agence_resp = ( ! $is_admin && $scope_agence === '' ); // responsable non rattache

    $repas = [];
    if ( $scope_agence !== '' ) {
        $repas = get_posts( [
            'post_type'      => 'sl_repas',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'meta_query'     => [ [ 'key' => '_sl_ff_agence', 'value' => $scope_agence ] ],
        ] );
    }

    // Produits « Bons Plans » (module sl_bon_plan) rendus compatibles + visibles
    // dans la liste des promotions. L'edition ecrit leurs vrais champs _sl_bp_*
    // (voir save AJAX). Le prix d'un bon plan est GLOBAL (pas par agence).
    //   - Admin : TOUS les bons plans, sans exception (independamment de l'agence).
    //   - Responsable : uniquement ceux rattaches a son agence (securite).
    $bons = [];
    if ( post_type_exists( 'sl_bon_plan' ) ) {
        $bp_args = [
            'post_type'      => 'sl_bon_plan',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];
        if ( ! $is_admin ) {
            // Responsable : borne a son agence (ou rien s'il n'en a pas).
            if ( $scope_agence === '' ) {
                $bp_args = null;
            } else {
                $bp_args['tax_query'] = [ [ 'taxonomy' => 'sl_agence_promo', 'field' => 'slug', 'terms' => $scope_agence ] ];
            }
        }
        if ( $bp_args ) {
            $bons = get_posts( $bp_args );
        }
    }

    // Groupement par categorie (une seule agence en scope)
    $grouped = [];
    foreach ( $repas as $r ) {
        $cats = wp_get_post_terms( $r->ID, 'sl_repas_cat' );
        $cat  = ( ! empty( $cats ) && ! is_wp_error( $cats ) )
                ? sl_ff_cat_display( $cats[0]->name ) : 'Sans categorie';
        $grouped[ $cat ][] = $r;
    }
    ksort( $grouped );

    $nb_cols = 7; // Plat, Prix, PrixPromo, Remise%, Debut, Fin, Statut
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
                    Les promotions sont <strong>propres a chaque agence</strong>.
                    <?php if ( $scope_nom ) : ?>
                    <span class="sl-ff-today-badge">Agence&nbsp;: <?php echo esc_html( $scope_nom ); ?></span>
                    <?php endif; ?>
                    <span class="sl-ff-today-badge">Aujourd&#39;hui&nbsp;: <?php echo esc_html( date_i18n( 'j F Y', strtotime( $today ) ) ); ?></span>
                </p>
            </div>
            <div class="sl-ff-filter-bar">
                <label>
                    <strong>Rechercher un repas</strong>
                    <input type="search" id="sl-ff-promo-search" placeholder="Nom du repas..." style="min-width:240px;">
                </label>
                <?php if ( $is_admin ) : ?>
                <form method="get" style="margin:0;">
                    <input type="hidden" name="page" value="sl-ff-promos">
                    <label>
                        <strong>Agence</strong>
                        <select name="ffa" onchange="this.form.submit()">
                            <option value="">— Choisir une agence —</option>
                            <?php foreach ( $agence_terms as $a ) : ?>
                            <option value="<?php echo esc_attr( $a->slug ); ?>" <?php selected( $agency_sel, $a->slug ); ?>><?php echo esc_html( sl_ff_agency_name( $a->name ) ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <p class="sl-ff-result-line" id="sl-ff-promo-count" style="display:none;">
            <strong>0</strong> repas affich&eacute;(s)
        </p>

        <?php if ( $no_agence_resp ) : ?>
        <div class="notice notice-warning" style="margin-top:16px;">
            <p><strong>Aucune agence ne vous est attribuee.</strong> Contactez un administrateur pour rattacher votre compte a une agence.</p>
        </div>

        <?php elseif ( $is_admin && $scope_agence === '' ) : ?>
        <div class="notice notice-info" style="margin-top:16px;">
            <p>Choisissez une agence dans le menu ci-dessus pour gerer ses promotions.</p>
        </div>

        <?php elseif ( empty( $repas ) ) : ?>
        <div class="sl-ff-empty">
            <p>Aucun repas pour cette agence.<?php echo ! empty( $bons ) ? ' Les Bons Plans sont list&eacute;s ci-dessous.' : ''; ?></p>
        </div>

        <?php else : ?>
        <table class="sl-ff-planning-table sl-ff-promos-table" id="sl-ff-promos-table">
            <thead>
                <tr>
                    <th class="sl-ff-col-plat">Plat</th>
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
                    $thumb_url   = get_the_post_thumbnail_url( $ri->ID, 'thumbnail' );
                    $pdata       = sl_ff_get_agence_prix( $ri->ID, $scope_agence );
                    $promo_pct   = $pdata['promo_pct'];
                    $prix        = $pdata['prix'];
                    $prix_promo  = $pdata['prix_promo'];
                    $promo_debut = $pdata['debut'];
                    $promo_fin   = $pdata['fin'];
                    $promo_info  = sl_ff_get_promo_info( $ri->ID, $scope_agence );
                    $is_active   = $promo_info['est_promo'];
                ?>
                <tr class="sl-ff-meal-row<?php echo $is_active ? ' sl-ff-promo-active' : ''; ?>"
                    data-id="<?php echo (int) $ri->ID; ?>"
                    data-agence="<?php echo esc_attr( $scope_agence ); ?>"
                    data-search="<?php echo esc_attr( sl_ff_norm_txt( $ri->post_title . ' ' . $group_name ) ); ?>">

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

        <?php if ( ! empty( $bons ) ) : ?>
        <h2 class="sl-ff-bp-heading" style="margin:26px 0 8px;">
            &#127991; Bons Plans
            <span style="font-size:13px;font-weight:400;color:#888;">&mdash; tous les produits Bons Plans, sans exception (prix global, s'applique &agrave; toutes les agences)</span>
        </h2>
        <table class="sl-ff-planning-table sl-ff-promos-table" id="sl-ff-promos-bp-table">
            <thead>
                <tr>
                    <th class="sl-ff-col-plat">Produit</th>
                    <th>Prix&nbsp;habituel&nbsp;(FCFA)</th>
                    <th>Prix&nbsp;promo&nbsp;(FCFA)</th>
                    <th>Remise&nbsp;(%)</th>
                    <th></th>
                    <th>Fin</th>
                    <th>Statut</th>
                    <th class="sl-ff-col-status"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $bons as $bp ) :
                    $thumb_url = get_the_post_thumbnail_url( $bp->ID, 'thumbnail' );
                    $bp_av     = (int) get_post_meta( $bp->ID, '_sl_bp_prix_avant', true );
                    $bp_ap     = (int) get_post_meta( $bp->ID, '_sl_bp_prix_apres', true );
                    $bp_red    = (int) get_post_meta( $bp->ID, '_sl_bp_reduction_pct', true );
                    $bp_fin    = (string) get_post_meta( $bp->ID, '_sl_bp_date_fin', true );
                    $bp_active = ( $bp_ap > 0 && ( $bp_fin === '' || $bp_fin >= $today ) );
                ?>
                <tr class="sl-ff-meal-row sl-ff-bp-row<?php echo $bp_active ? ' sl-ff-promo-active' : ''; ?>"
                    data-id="<?php echo (int) $bp->ID; ?>"
                    data-agence="<?php echo esc_attr( $scope_agence ); ?>"
                    data-type="bon_plan"
                    data-search="<?php echo esc_attr( sl_ff_norm_txt( $bp->post_title . ' bon plan' ) ); ?>">

                    <td class="sl-ff-col-plat">
                        <div class="sl-ff-plat-info">
                            <?php if ( $thumb_url ) : ?>
                            <div class="sl-ff-plat-thumb" style="background-image:url('<?php echo esc_url( $thumb_url ); ?>')"></div>
                            <?php else : ?>
                            <div class="sl-ff-plat-thumb sl-ff-plat-no-img">&#127991;</div>
                            <?php endif; ?>
                            <div>
                                <div class="sl-ff-plat-nom"><?php echo esc_html( $bp->post_title ); ?></div>
                                <span class="sl-ff-bp-tag">Bon Plan</span>
                            </div>
                        </div>
                    </td>

                    <!-- Prix habituel -->
                    <td>
                        <input type="number" class="sl-ff-promo-prix"
                               value="<?php echo esc_attr( $bp_av ?: '' ); ?>"
                               min="0" step="50" placeholder="&mdash;"
                               style="width:90px;text-align:center;padding:4px 6px;">
                    </td>

                    <!-- Prix promo -->
                    <td>
                        <input type="number" class="sl-ff-promo-prix-promo"
                               value="<?php echo esc_attr( $bp_ap ?: '' ); ?>"
                               min="0" step="50" placeholder="&mdash;"
                               style="width:90px;text-align:center;padding:4px 6px;">
                    </td>

                    <!-- Remise % (auto) -->
                    <td>
                        <?php if ( $bp_red > 0 ) : ?>
                        <span style="font-size:13px;color:#555;font-weight:600;">-<?php echo (int) $bp_red; ?>%</span>
                        <?php else : ?>
                        <span style="font-size:12px;color:#bbb;">auto</span>
                        <?php endif; ?>
                    </td>

                    <!-- Debut (les bons plans n'en ont pas) -->
                    <td><span style="font-size:12px;color:#ccc;">&#8212;</span></td>

                    <!-- Fin (valable jusqu'au) -->
                    <td>
                        <input type="date" class="sl-ff-promo-fin"
                               value="<?php echo esc_attr( $bp_fin ); ?>"
                               style="padding:4px 6px;font-size:12px;">
                    </td>

                    <!-- Statut -->
                    <td>
                        <?php if ( $bp_active ) : ?>
                        <span class="sl-ff-promo-badge-actif">&#10003; Actif<?php echo $bp_red > 0 ? ' -' . (int) $bp_red . '%' : ''; ?></span>
                        <?php elseif ( $bp_ap > 0 ) : ?>
                        <span style="font-size:12px;color:#e67e22;">&#9679; Expir&eacute;</span>
                        <?php else : ?>
                        <span style="font-size:12px;color:#ccc;">&#8212; Aucune</span>
                        <?php endif; ?>
                    </td>

                    <td class="sl-ff-col-status"><span class="sl-ff-save-icon"></span></td>
                </tr>
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
    .sl-ff-cat-row-bp td { background:#fff8f1 !important; color:#b25a00 !important; }
    .sl-ff-bp-tag {
        display:inline-block; margin-top:3px; background:#ffe6cc; color:#b25a00;
        border-radius:12px; padding:1px 8px; font-size:11px; font-weight:700;
    }
    .sl-ff-bp-row .sl-ff-plat-no-img { background:#fff2e3; }
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
    $req_agence  = sanitize_title( wp_unslash( $_POST['agence'] ?? '' ) );

    if ( ! $post_id ) wp_send_json_error( 'ID invalide' );

    /* --- Branche « Bons Plans » : le produit est un sl_bon_plan (module Bons Plans).
       On ecrit ses vrais champs _sl_bp_* pour que ca se reflete cote /bon-plans/.
       Prix habituel <- « Prix actuel », Prix promo <- « Prix promo », date_fin <- « Fin ».
       La remise % est auto-calculee (comme la metabox du module). --- */
    if ( get_post_type( $post_id ) === 'sl_bon_plan' ) {
        $bp_is_admin = current_user_can( 'manage_options' ) || current_user_can( 'sl_ff_all_agencies' );
        // Admin : peut editer TOUT bon plan (le prix d'un bon plan est global).
        // Responsable : borne a son agence ; le bon plan doit y etre rattache.
        if ( ! $bp_is_admin ) {
            $bp_agence = sanitize_title( (string) get_user_meta( get_current_user_id(), '_sl_agence_ff', true ) );
            if ( $bp_agence === '' || ! has_term( $bp_agence, 'sl_agence_promo', $post_id ) ) {
                wp_send_json_error( 'Acces refuse' );
            }
        }

        $bp_av  = max( 0, (int) $prix );        // prix habituel
        $bp_ap  = max( 0, (int) $prix_promo );  // prix promotionnel
        $bp_fin = $promo_fin;                   // valable jusqu'au (peut etre vide)
        $bp_red = ( $bp_av > 0 && $bp_ap > 0 ) ? (int) round( ( ( $bp_av - $bp_ap ) / $bp_av ) * 100 ) : 0;

        update_post_meta( $post_id, '_sl_bp_prix_avant',    $bp_av );
        update_post_meta( $post_id, '_sl_bp_prix_apres',    $bp_ap );
        update_post_meta( $post_id, '_sl_bp_reduction_pct', $bp_red );
        update_post_meta( $post_id, '_sl_bp_date_fin',      $bp_fin );

        // Les hooks updated_post_meta du module purgent deja Varnish/LiteSpeed ;
        // on force la purge de /bon-plans/ par securite.
        if ( function_exists( 'sl_bp_purge_front_cache' ) ) {
            sl_bp_purge_front_cache( $post_id );
        }

        // Le bon plan est vendable : son produit WooCommerce lie porte le prix
        // reellement paye. Ici on n'ecrit que des metas (pas de wp_update_post),
        // donc save_post_sl_bon_plan ne se declenche pas et l'autosync ne tourne
        // jamais. Sans cet appel, la carte afficherait le nouveau prix et le
        // panier facturerait l'ancien.
        if ( function_exists( 'sl_cwoo_sync_bon_plan_to_product' ) ) {
            sl_cwoo_sync_bon_plan_to_product( $post_id );
        }

        $bp_today = current_time( 'Y-m-d' );
        wp_send_json_success( [
            'est_promo'  => ( $bp_ap > 0 && ( $bp_fin === '' || $bp_fin >= $bp_today ) ),
            'pct'        => $bp_red,
            'prix'       => $bp_av,
            'prix_promo' => $bp_ap,
            'type'       => 'bon_plan',
        ] );
    }

    $is_admin     = current_user_can( 'manage_options' ) || current_user_can( 'sl_ff_all_agencies' );
    $agences_post = (array) get_post_meta( $post_id, '_sl_ff_agence' );

    // Agence cible de la promo (les promos sont propres a l'agence)
    if ( $is_admin ) {
        $agence = $req_agence;
    } else {
        // Responsable : toujours SON agence (le POST est ignore pour la securite)
        $agence = sanitize_title( (string) get_user_meta( get_current_user_id(), '_sl_agence_ff', true ) );
    }

    if ( $agence === '' ) {
        wp_send_json_error( 'Agence non precisee' );
    }
    // Le repas doit bien appartenir a cette agence
    if ( ! in_array( $agence, $agences_post, true ) ) {
        wp_send_json_error( 'Acces refuse' );
    }

    // Enregistrement PAR AGENCE (prix + promo)
    sl_ff_set_agence_prix( $post_id, $agence, [
        'prix'       => $prix,
        'promo_pct'  => $promo_pct,
        'prix_promo' => $prix_promo,
        'debut'      => $promo_debut,
        'fin'        => $promo_fin,
    ] );

    // Journal d'activite « qui travaille » : promo si une reduction/prix promo
    // est posee, sinon simple mise a jour de prix.
    if ( function_exists( 'sl_ff_activity_log' ) ) {
        $est_promo = ( (int) $promo_pct > 0 || (int) $prix_promo > 0 );
        sl_ff_activity_log( $est_promo ? 'promo' : 'prix', $agence, $post_id );
    }

    if ( function_exists( 'sl_ff_bump_menu_cache' ) ) sl_ff_bump_menu_cache();

    $promo_info = sl_ff_get_promo_info( $post_id, $agence );
    wp_send_json_success( [
        'est_promo'  => $promo_info['est_promo'],
        'pct'        => $promo_info['pct_reduction'],
        'prix'       => $promo_info['prix'],
        'prix_promo' => $promo_info['prix_promo'],
    ] );
}
