<?php
/**
 * Interface admin simplifiée pour les Responsables d'Agence
 * Pages : Liste des offres + Formulaire ajout/édition
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
 *  ASSETS ADMIN
 * ============================================================ */
add_action( 'admin_enqueue_scripts', 'sl_bp_admin_assets' );
function sl_bp_admin_assets( $hook ) {
    $allowed_pages = [ 'sl-mes-bons-plans', 'sl-ajouter-offre', 'sl-bp-import', 'sl-mes-produits' ];
    $current_page  = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
    $allowed_hooks = [ 'toplevel_page_sl-mes-bons-plans', 'mes-bons-plans_page_sl-ajouter-offre', 'mes-bons-plans_page_sl-bp-import', 'mes-bons-plans_page_sl-mes-produits' ];

    if ( ! in_array( $current_page, $allowed_pages, true ) && ! in_array( $hook, $allowed_hooks, true ) ) {
        return;
    }

    // Media uploader
    wp_enqueue_media();
    wp_enqueue_style( 'sl-bp-admin', SL_AGENCES_URL . 'assets/css/bons-plans-admin.css', [], SL_AGENCES_VERSION );
    wp_enqueue_script( 'sl-bp-admin', SL_AGENCES_URL . 'assets/js/bons-plans-admin.js', [ 'jquery' ], SL_AGENCES_VERSION, true );
    wp_localize_script( 'sl-bp-admin', 'slBpAdmin', [
        'nonce' => wp_create_nonce( 'sl_bp_admin' ),
    ] );
}

/* ============================================================
 *  TRAITEMENT DU FORMULAIRE (Save)
 * ============================================================ */
add_action( 'admin_post_sl_bp_save', 'sl_bp_handle_save' );
function sl_bp_handle_save() {
    if ( ! sl_bp_is_responsable() ) wp_die( 'Accès refusé.' );

    check_admin_referer( 'sl_bp_save_offer', 'sl_bp_nonce' );

    $post_id   = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
    $titre     = sanitize_text_field( $_POST['titre'] ?? '' );
    $desc      = sanitize_textarea_field( $_POST['description'] ?? '' );
    $cat_id    = (int) ( $_POST['categorie'] ?? 0 );
    $prix_av   = (float) str_replace( ',', '.', $_POST['prix_avant'] ?? 0 );
    $prix_ap   = (float) str_replace( ',', '.', $_POST['prix_apres'] ?? 0 );
    $badge     = sanitize_key( $_POST['badge_type'] ?? 'none' );
    $badge     = $badge === 'none' ? '' : $badge;
    $date_fin  = sanitize_text_field( $_POST['date_fin'] ?? '' );
    $image_id  = (int) ( $_POST['image_id'] ?? 0 );

    if ( empty( $titre ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=sl-ajouter-offre&error=titre_vide&post_id=' . $post_id ) );
        exit;
    }

    $reduction = ( $prix_av > 0 && $prix_ap > 0 )
        ? round( ( ( $prix_av - $prix_ap ) / $prix_av ) * 100 )
        : 0;

    // Créer ou mettre à jour
    $data = [
        'post_title'   => $titre,
        'post_content' => $desc,
        'post_status'  => 'publish',
        'post_type'    => 'sl_bon_plan',
    ];

    if ( $post_id > 0 ) {
        // Vérifier que l'offre appartient bien à son agence
        $existing = get_post( $post_id );
        if ( ! $existing || ! sl_bp_user_can_manage_offer( $post_id ) ) {
            wp_die( 'Accès refusé.' );
        }
        $data['ID'] = $post_id;
        $post_id = wp_update_post( $data );
    } else {
        $data['post_author'] = get_current_user_id();
        $post_id = wp_insert_post( $data );
    }

    if ( is_wp_error( $post_id ) || ! $post_id ) {
        wp_safe_redirect( admin_url( 'admin.php?page=sl-ajouter-offre&error=save_failed' ) );
        exit;
    }

    // Méta-données
    update_post_meta( $post_id, '_sl_bp_prix_avant',    $prix_av );
    update_post_meta( $post_id, '_sl_bp_prix_apres',    $prix_ap );
    update_post_meta( $post_id, '_sl_bp_reduction_pct', $reduction );
    update_post_meta( $post_id, '_sl_bp_badge_type',    $badge );
    update_post_meta( $post_id, '_sl_bp_date_fin',      $date_fin );

    // Limite de stock (mêmes métas que la metabox admin)
    $stock_actif   = ! empty( $_POST['stock_actif'] ) ? '1' : '';
    $stock_qty_raw = isset( $_POST['stock_qty'] ) ? trim( wp_unslash( $_POST['stock_qty'] ) ) : '';
    $stock_qty     = ( $stock_qty_raw === '' ) ? '' : max( 0, (int) $stock_qty_raw );
    update_post_meta( $post_id, '_sl_bp_stock_actif', $stock_actif );
    update_post_meta( $post_id, '_sl_bp_stock_qty',   $stock_qty );

    // Image à la une
    if ( $image_id > 0 ) {
        set_post_thumbnail( $post_id, $image_id );
    } else {
        delete_post_thumbnail( $post_id );
    }

    // Catégorie
    if ( $cat_id > 0 ) {
        wp_set_object_terms( $post_id, [ $cat_id ], 'sl_categorie_promo', false );
    }

    // Agence (auto depuis user meta)
    sl_bp_assign_current_user_agence_to_offer( $post_id );

    // Le produit WooCommerce vendable doit refleter les metas qu'on vient d'ecrire.
    // L'autosync branchee sur save_post_sl_bon_plan s'est declenchee au
    // wp_update_post/wp_insert_post ci-dessus, AVANT ces update_post_meta : elle a
    // donc lu les anciens prix (ou aucun, a la creation). On resynchronise ici.
    if ( function_exists( 'sl_cwoo_sync_bon_plan_to_product' ) ) {
        sl_cwoo_sync_bon_plan_to_product( $post_id );
    }

    wp_safe_redirect( admin_url( 'admin.php?page=sl-mes-bons-plans&saved=1' ) );
    exit;
}

function sl_bp_get_current_user_agence_term() {
    $agence_nom = get_user_meta( get_current_user_id(), 'sl_agence_assignee', true );
    if ( ! $agence_nom ) return null;

    $term = get_term_by( 'name', $agence_nom, 'sl_agence_promo' );
    if ( ! $term || is_wp_error( $term ) ) {
        $created = wp_insert_term( $agence_nom, 'sl_agence_promo' );
        if ( ! is_wp_error( $created ) && ! empty( $created['term_id'] ) ) {
            $term = get_term( (int) $created['term_id'], 'sl_agence_promo' );
        }
    }
    return $term && ! is_wp_error( $term ) ? $term : null;
}

function sl_bp_user_can_manage_offer( $post_id ) {
    $post = get_post( $post_id );
    if ( ! $post || $post->post_type !== 'sl_bon_plan' ) return false;
    if ( (int) $post->post_author === get_current_user_id() ) return true;

    $agence_term = sl_bp_get_current_user_agence_term();
    if ( ! $agence_term ) return false;

    return has_term( (int) $agence_term->term_id, 'sl_agence_promo', $post_id );
}

function sl_bp_assign_current_user_agence_to_offer( $post_id ) {
    $agence_term = sl_bp_get_current_user_agence_term();
    if ( ! $agence_term ) return false;

    wp_set_object_terms( $post_id, [ (int) $agence_term->term_id ], 'sl_agence_promo', false );
    return true;
}

/* ============================================================
 *  SUPPRESSION D'UNE OFFRE
 * ============================================================ */
add_action( 'admin_init', 'sl_bp_handle_delete' );
function sl_bp_handle_delete() {
    if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'sl_bp_delete' ) return;
    if ( ! sl_bp_is_responsable() ) return;

    $post_id = (int) ( $_GET['post_id'] ?? 0 );
    check_admin_referer( 'sl_bp_delete_' . $post_id );

    if ( sl_bp_user_can_manage_offer( $post_id ) ) {
        wp_delete_post( $post_id, true );
    }

    wp_safe_redirect( admin_url( 'admin.php?page=sl-mes-bons-plans&deleted=1' ) );
    exit;
}

/* ============================================================
 *  PAGE : LISTE DES OFFRES
 * ============================================================ */
function sl_bp_page_list() {
    $user_id = get_current_user_id();
    $agence  = get_user_meta( $user_id, 'sl_agence_assignee', true ) ?: '—';
    $today   = current_time( 'Y-m-d' );
    $agence_term = sl_bp_get_current_user_agence_term();
    $posts_by_id = [];

    $base_query_args = [
        'post_type'      => 'sl_bon_plan',
        'posts_per_page' => -1,
        'post_status'    => [ 'publish', 'draft' ],
        'orderby'        => 'date',
        'order'          => 'DESC',
    ];

    if ( $agence_term ) {
        $agency_query_args = $base_query_args;
        $agency_query_args['tax_query'] = [
            [
                'taxonomy' => 'sl_agence_promo',
                'field'    => 'term_id',
                'terms'    => [ (int) $agence_term->term_id ],
            ],
        ];
        foreach ( get_posts( $agency_query_args ) as $post ) {
            $posts_by_id[ $post->ID ] = $post;
        }
    }

    $author_query_args = $base_query_args;
    $author_query_args['author'] = $user_id;
    foreach ( get_posts( $author_query_args ) as $post ) {
        if ( $agence_term && ! has_term( (int) $agence_term->term_id, 'sl_agence_promo', $post->ID ) ) {
            sl_bp_assign_current_user_agence_to_offer( $post->ID );
        }
        $posts_by_id[ $post->ID ] = $post;
    }

    $posts = array_values( $posts_by_id );
    usort( $posts, function( $a, $b ) {
        return strtotime( $b->post_date ) <=> strtotime( $a->post_date );
    } );

    $saved   = isset( $_GET['saved'] );
    $deleted = isset( $_GET['deleted'] );
    ?>
    <div class="sl-bp-wrap">
        <div class="sl-bp-header">
            <div>
                <h1>🔥 Mes Bons Plans</h1>
                <p class="sl-bp-agence-label">Agence : <strong><?php echo esc_html( $agence ); ?></strong></p>
            </div>
            <a href="<?php echo admin_url( 'admin.php?page=sl-ajouter-offre' ); ?>" class="sl-bp-btn-add">
                ➕ Ajouter une offre
            </a>
        </div>

        <?php if ( $saved ) : ?>
            <div class="sl-bp-notice sl-bp-notice-success">✅ Offre publiée avec succès !</div>
        <?php endif; ?>
        <?php if ( $deleted ) : ?>
            <div class="sl-bp-notice sl-bp-notice-success">🗑️ Offre supprimée.</div>
        <?php endif; ?>

        <?php if ( empty( $posts ) ) : ?>
            <div class="sl-bp-empty">
                <p>Vous n'avez pas encore publié d'offres.</p>
                <a href="<?php echo admin_url( 'admin.php?page=sl-ajouter-offre' ); ?>" class="sl-bp-btn-add">Créer ma première offre</a>
            </div>
        <?php else : ?>
            <div class="sl-bp-table-wrap">
                <table class="sl-bp-table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Titre</th>
                            <th>Catégorie</th>
                            <th>Prix promo</th>
                            <th>Réduction</th>
                            <th>Stock en ligne</th>
                            <th>Expire le</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $posts as $post ) :
                        $img       = get_the_post_thumbnail_url( $post->ID, 'thumbnail' );
                        $prix_av   = (float) get_post_meta( $post->ID, '_sl_bp_prix_avant', true );
                        $prix_ap   = (float) get_post_meta( $post->ID, '_sl_bp_prix_apres', true );
                        $reduc     = (int) get_post_meta( $post->ID, '_sl_bp_reduction_pct', true );
                        $badge     = get_post_meta( $post->ID, '_sl_bp_badge_type', true );
                        $date_fin  = get_post_meta( $post->ID, '_sl_bp_date_fin', true );
                        $cats      = wp_get_object_terms( $post->ID, 'sl_categorie_promo' );
                        $cat_name  = ! empty( $cats ) ? $cats[0]->name : '—';
                        $expired   = $date_fin && $date_fin < $today;
                        $stock_on  = get_post_meta( $post->ID, '_sl_bp_stock_actif', true ) === '1';
                        $stock_qty = get_post_meta( $post->ID, '_sl_bp_stock_qty', true );
                        $delete_url = wp_nonce_url(
                            admin_url( 'admin.php?action=sl_bp_delete&post_id=' . $post->ID ),
                            'sl_bp_delete_' . $post->ID
                        );
                        $edit_url = admin_url( 'admin.php?page=sl-ajouter-offre&post_id=' . $post->ID );
                    ?>
                        <tr class="<?php echo $expired ? 'sl-bp-expired' : ''; ?>">
                            <td>
                                <?php if ( $img ) : ?>
                                    <img src="<?php echo esc_url( $img ); ?>" width="60" height="60" style="object-fit:cover;border-radius:6px;">
                                <?php else : ?>
                                    <div class="sl-bp-no-img">📷</div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo esc_html( $post->post_title ); ?></strong></td>
                            <td><?php echo esc_html( $cat_name ); ?></td>
                            <td><?php echo $prix_ap ? number_format( $prix_ap, 0, ',', ' ' ) . ' FCFA' : '—'; ?></td>
                            <td><?php echo $reduc ? '<span class="sl-bp-badge-reduc">-' . $reduc . '%</span>' : '—'; ?></td>
                            <td class="sl-bp-stock-cell" data-id="<?php echo (int) $post->ID; ?>">
                                <label class="sl-bp-stock-toggle">
                                    <input type="checkbox" class="sl-bp-stock-actif" <?php checked( $stock_on ); ?>>
                                    <span>Limiter</span>
                                </label>
                                <input type="number" class="sl-bp-stock-qty" min="0" step="1"
                                       value="<?php echo esc_attr( $stock_qty ); ?>"
                                       placeholder="—" <?php disabled( ! $stock_on ); ?>>
                                <span class="sl-bp-stock-state"><?php
                                    if ( ! $stock_on ) {
                                        echo '<em>illimité</em>';
                                    } elseif ( $stock_qty === '' ) {
                                        echo '<em>à saisir</em>';
                                    } elseif ( (int) $stock_qty <= 0 ) {
                                        echo '<strong class="sl-bp-stock-out">épuisé</strong>';
                                    } else {
                                        echo '<span class="sl-bp-stock-ok">en vente</span>';
                                    }
                                ?></span>
                            </td>
                            <td><?php echo $date_fin ? date( 'd/m/Y', strtotime( $date_fin ) ) : '—'; ?></td>
                            <td>
                                <?php if ( $expired ) : ?>
                                    <span class="sl-bp-status sl-bp-status-expired">Expirée</span>
                                <?php else : ?>
                                    <span class="sl-bp-status sl-bp-status-active">Active</span>
                                <?php endif; ?>
                            </td>
                            <td class="sl-bp-actions">
                                <a href="<?php echo esc_url( $edit_url ); ?>" class="sl-bp-btn-edit">✏️ Modifier</a>
                                <a href="<?php echo esc_url( $delete_url ); ?>" class="sl-bp-btn-delete" onclick="return confirm('Supprimer cette offre ?')">🗑️</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <style>
            .sl-bp-stock-cell{white-space:nowrap;}
            .sl-bp-stock-toggle{display:flex;align-items:center;gap:4px;font-size:12px;margin-bottom:4px;cursor:pointer;}
            .sl-bp-stock-toggle input{margin:0;}
            .sl-bp-stock-qty{width:72px;padding:3px 6px;}
            .sl-bp-stock-qty:disabled{background:#f0f0f1;color:#8c8f94;}
            .sl-bp-stock-state{display:block;margin-top:3px;font-size:11px;color:#646970;}
            .sl-bp-stock-out{color:#b32d2e;}
            .sl-bp-stock-ok{color:#1e7b34;}
            .sl-bp-stock-cell.saving{opacity:.55;}
            .sl-bp-stock-cell.saved .sl-bp-stock-state{color:#1e7b34;font-weight:600;}
            .sl-bp-stock-cell.failed .sl-bp-stock-state{color:#b32d2e;font-weight:600;}
            </style>
            <script>
            (function(){
                var AJAX  = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
                var NONCE = <?php echo wp_json_encode( wp_create_nonce( 'sl_bp_stock' ) ); ?>;
                var timers = {};

                function save( cell ){
                    var id    = cell.getAttribute('data-id');
                    var actif = cell.querySelector('.sl-bp-stock-actif').checked;
                    var qtyEl = cell.querySelector('.sl-bp-stock-qty');
                    var state = cell.querySelector('.sl-bp-stock-state');
                    cell.classList.remove('saved','failed');
                    cell.classList.add('saving');
                    var body = 'action=sl_bp_save_stock&_wpnonce=' + encodeURIComponent(NONCE)
                             + '&post_id=' + encodeURIComponent(id)
                             + '&stock_actif=' + (actif ? '1' : '')
                             + '&stock_qty=' + encodeURIComponent(qtyEl.value);
                    fetch(AJAX, { method:'POST', credentials:'same-origin',
                        headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body })
                        .then(function(r){ return r.json(); })
                        .then(function(res){
                            cell.classList.remove('saving');
                            if ( ! res || ! res.success ) {
                                cell.classList.add('failed');
                                state.textContent = (res && res.data) ? res.data : 'Échec';
                                return;
                            }
                            cell.classList.add('saved');
                            state.innerHTML = res.data.state;
                            setTimeout(function(){ cell.classList.remove('saved'); }, 1500);
                        })
                        .catch(function(){ cell.classList.remove('saving'); cell.classList.add('failed');
                                           state.textContent = 'Réseau indisponible'; });
                }

                document.addEventListener('change', function(e){
                    var cell = e.target.closest ? e.target.closest('.sl-bp-stock-cell') : null;
                    if ( ! cell ) return;
                    if ( e.target.classList.contains('sl-bp-stock-actif') ) {
                        cell.querySelector('.sl-bp-stock-qty').disabled = ! e.target.checked;
                    }
                    save( cell );
                });
                // La saisie au clavier s'enregistre seule, sans attendre de quitter le champ.
                document.addEventListener('input', function(e){
                    if ( ! e.target.classList.contains('sl-bp-stock-qty') ) return;
                    var cell = e.target.closest('.sl-bp-stock-cell');
                    var id   = cell.getAttribute('data-id');
                    clearTimeout( timers[id] );
                    timers[id] = setTimeout(function(){ save( cell ); }, 600);
                });
            })();
            </script>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Enregistrement du stock depuis la liste « Mes Bons Plans » (edition en ligne).
 * Ecrit les memes metas que le formulaire complet, puis resynchronise le produit
 * WooCommerce vendable (sans quoi la limite saisie ne bornerait jamais la vente).
 */
add_action( 'wp_ajax_sl_bp_save_stock', 'sl_bp_ajax_save_stock' );
function sl_bp_ajax_save_stock() {
    check_ajax_referer( 'sl_bp_stock' );

    $post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
    if ( ! $post_id || get_post_type( $post_id ) !== 'sl_bon_plan' ) {
        wp_send_json_error( 'Offre introuvable' );
    }
    // Meme controle que le formulaire complet : l'offre doit etre a lui (ou son agence).
    if ( ! sl_bp_user_can_manage_offer( $post_id ) ) {
        wp_send_json_error( 'Accès refusé' );
    }

    $stock_actif = ! empty( $_POST['stock_actif'] ) ? '1' : '';
    $qty_raw     = isset( $_POST['stock_qty'] ) ? trim( wp_unslash( $_POST['stock_qty'] ) ) : '';
    $stock_qty   = ( $qty_raw === '' ) ? '' : max( 0, (int) $qty_raw );

    update_post_meta( $post_id, '_sl_bp_stock_actif', $stock_actif );
    update_post_meta( $post_id, '_sl_bp_stock_qty',   $stock_qty );

    if ( function_exists( 'sl_cwoo_sync_bon_plan_to_product' ) ) {
        sl_cwoo_sync_bon_plan_to_product( $post_id );
    }
    // Pas de purge explicite ici : les update_post_meta ci-dessus déclenchent
    // déjà le watcher (_sl_bp_stock_* sont surveillées), qui met la purge en
    // file. L'appeler en plus doublait le travail pour rien.

    if ( $stock_actif !== '1' ) {
        $state = '<em>illimité</em>';
    } elseif ( $stock_qty === '' ) {
        $state = '<em>à saisir</em>';
    } elseif ( (int) $stock_qty <= 0 ) {
        $state = '<strong class="sl-bp-stock-out">épuisé</strong>';
    } else {
        $state = '<span class="sl-bp-stock-ok">en vente</span>';
    }

    wp_send_json_success( [ 'state' => $state ] );
}

/* ============================================================
 *  PAGE : LISTE DES PRODUITS CHARGÉS
 * ============================================================ */
function sl_bp_page_products() {
    $user_id = get_current_user_id();
    $agence  = get_user_meta( $user_id, 'sl_agence_assignee', true ) ?: '—';

    $products = get_posts( [
        'post_type'      => 'product',
        'author'         => $user_id,
        'posts_per_page' => -1,
        'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
        'orderby'        => 'date',
        'order'          => 'DESC',
    ] );
    ?>
    <div class="sl-bp-wrap">
        <div class="sl-bp-header">
            <div>
                <h1>Mes produits</h1>
                <p class="sl-bp-agence-label">Agence : <strong><?php echo esc_html( $agence ); ?></strong></p>
            </div>
        </div>

        <?php if ( empty( $products ) ) : ?>
            <div class="sl-bp-empty">
                <p>Vous n'avez pas encore chargé de produits.</p>
            </div>
        <?php else : ?>
            <div class="sl-bp-table-wrap">
                <table class="sl-bp-table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Produit</th>
                            <th>Catégorie</th>
                            <th>Prix</th>
                            <th>Statut</th>
                            <th>Date</th>
                            <th>Lien</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $products as $product_post ) :
                        $img          = get_the_post_thumbnail_url( $product_post->ID, 'thumbnail' );
                        $product      = function_exists( 'wc_get_product' ) ? wc_get_product( $product_post->ID ) : null;
                        $price        = $product ? $product->get_price_html() : get_post_meta( $product_post->ID, '_price', true );
                        $terms        = wp_get_object_terms( $product_post->ID, 'product_cat' );
                        $cat_name     = ! empty( $terms ) && ! is_wp_error( $terms ) ? $terms[0]->name : '—';
                        $status_label = get_post_status_object( $product_post->post_status );
                    ?>
                        <tr>
                            <td>
                                <?php if ( $img ) : ?>
                                    <img src="<?php echo esc_url( $img ); ?>" width="60" height="60" style="object-fit:cover;border-radius:6px;">
                                <?php else : ?>
                                    <div class="sl-bp-no-img">📷</div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo esc_html( $product_post->post_title ); ?></strong></td>
                            <td><?php echo esc_html( $cat_name ); ?></td>
                            <td><?php echo $price ? wp_kses_post( $price ) : '—'; ?></td>
                            <td><?php echo $status_label ? esc_html( $status_label->label ) : esc_html( $product_post->post_status ); ?></td>
                            <td><?php echo esc_html( get_the_date( 'd/m/Y', $product_post ) ); ?></td>
                            <td><a href="<?php echo esc_url( get_permalink( $product_post ) ); ?>" target="_blank" rel="noopener">Voir</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/* ============================================================
 *  PAGE : FORMULAIRE AJOUT / ÉDITION
 * ============================================================ */
function sl_bp_page_form() {
    $post_id    = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;
    $error      = sanitize_key( $_GET['error'] ?? '' );
    $is_edit    = false;
    $post_data  = [];

    if ( $post_id > 0 ) {
        $existing = get_post( $post_id );
        if ( $existing && sl_bp_user_can_manage_offer( $post_id ) ) {
            $is_edit = true;
            $cats    = wp_get_object_terms( $post_id, 'sl_categorie_promo' );
            $post_data = [
                'titre'       => $existing->post_title,
                'description' => $existing->post_content,
                'prix_avant'  => get_post_meta( $post_id, '_sl_bp_prix_avant', true ),
                'prix_apres'  => get_post_meta( $post_id, '_sl_bp_prix_apres', true ),
                'badge_type'  => get_post_meta( $post_id, '_sl_bp_badge_type', true ) ?: 'none',
                'date_fin'    => get_post_meta( $post_id, '_sl_bp_date_fin', true ),
                'image_id'    => get_post_thumbnail_id( $post_id ),
                'image_url'   => get_the_post_thumbnail_url( $post_id, 'medium' ),
                'cat_id'      => ! empty( $cats ) ? $cats[0]->term_id : 0,
                'stock_actif' => get_post_meta( $post_id, '_sl_bp_stock_actif', true ),
                'stock_qty'   => get_post_meta( $post_id, '_sl_bp_stock_qty', true ),
            ];
        } else {
            wp_safe_redirect( admin_url( 'admin.php?page=sl-mes-bons-plans' ) );
            exit;
        }
    }

    $all_cats = get_terms( [ 'taxonomy' => 'sl_categorie_promo', 'hide_empty' => false ] );
    $agence   = get_user_meta( get_current_user_id(), 'sl_agence_assignee', true ) ?: '—';

    $badges = [
        'none'      => 'Aucun badge',
        'flash'     => '🔥 Flash',
        'nouveau'   => '🟢 Nouveau',
        'top-vente' => '👑 Top Vente',
        'exclusif'  => '💎 Exclusif',
    ];
    ?>
    <div class="sl-bp-wrap">
        <div class="sl-bp-header">
            <div>
                <h1><?php echo $is_edit ? '✏️ Modifier l\'offre' : '➕ Nouvelle offre'; ?></h1>
                <p class="sl-bp-agence-label">Agence : <strong><?php echo esc_html( $agence ); ?></strong></p>
            </div>
            <a href="<?php echo admin_url( 'admin.php?page=sl-mes-bons-plans' ); ?>" class="sl-bp-btn-back">← Retour</a>
        </div>

        <?php if ( $error === 'titre_vide' ) : ?>
            <div class="sl-bp-notice sl-bp-notice-error">⚠️ Le titre de l'offre est obligatoire.</div>
        <?php endif; ?>

        <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" class="sl-bp-form">
            <?php wp_nonce_field( 'sl_bp_save_offer', 'sl_bp_nonce' ); ?>
            <input type="hidden" name="action" value="sl_bp_save">
            <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">

            <div class="sl-bp-form-grid">

                <!-- Colonne principale -->
                <div class="sl-bp-form-main">

                    <div class="sl-bp-field">
                        <label for="sl-titre">Titre de l'offre <span class="sl-bp-required">*</span></label>
                        <input type="text" id="sl-titre" name="titre" value="<?php echo esc_attr( $post_data['titre'] ?? '' ); ?>" placeholder="Ex : Pizza Microwavable Great Value" required>
                    </div>

                    <div class="sl-bp-field">
                        <label for="sl-desc">Description (optionnel)</label>
                        <textarea id="sl-desc" name="description" rows="3" placeholder="Quelques mots sur cette offre..."><?php echo esc_textarea( $post_data['description'] ?? '' ); ?></textarea>
                    </div>

                    <div class="sl-bp-field-row">
                        <div class="sl-bp-field">
                            <label for="sl-prix-av">Prix habituel (FCFA)</label>
                            <input type="number" id="sl-prix-av" name="prix_avant" value="<?php echo esc_attr( $post_data['prix_avant'] ?? '' ); ?>" placeholder="5000" min="0" step="1">
                        </div>
                        <div class="sl-bp-field">
                            <label for="sl-prix-ap">Prix promotionnel (FCFA) <span class="sl-bp-required">*</span></label>
                            <input type="number" id="sl-prix-ap" name="prix_apres" value="<?php echo esc_attr( $post_data['prix_apres'] ?? '' ); ?>" placeholder="3500" min="0" step="1" required>
                        </div>
                    </div>

                    <div class="sl-bp-field-row">
                        <div class="sl-bp-field">
                            <label>Catégorie <span class="sl-bp-required">*</span></label>
                            <select name="categorie" required>
                                <option value="">-- Choisir --</option>
                                <?php foreach ( $all_cats as $cat ) : ?>
                                    <option value="<?php echo $cat->term_id; ?>" <?php selected( $post_data['cat_id'] ?? 0, $cat->term_id ); ?>>
                                        <?php echo esc_html( $cat->name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="sl-bp-field">
                            <label for="sl-date-fin">Valable jusqu'au <span class="sl-bp-required">*</span></label>
                            <input type="date" id="sl-date-fin" name="date_fin" value="<?php echo esc_attr( $post_data['date_fin'] ?? '' ); ?>" required min="<?php echo current_time( 'Y-m-d' ); ?>">
                        </div>
                    </div>

                    <div class="sl-bp-field">
                        <label>Type de badge</label>
                        <div class="sl-bp-badge-picker">
                            <?php foreach ( $badges as $val => $label ) : ?>
                                <label class="sl-bp-badge-option">
                                    <input type="radio" name="badge_type" value="<?php echo esc_attr( $val ); ?>"
                                        <?php checked( $post_data['badge_type'] ?? 'none', $val ); ?>>
                                    <span class="sl-bp-badge-label sl-badge-<?php echo esc_attr( $val ); ?>"><?php echo $label; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php $stock_on = ( ( $post_data['stock_actif'] ?? '' ) === '1' ); ?>
                    <div class="sl-bp-field" style="background:#fff8f1;border:1px solid #f3d9bd;border-radius:6px;padding:12px 14px;">
                        <label style="display:flex;align-items:center;gap:8px;font-weight:bold;margin-bottom:0;">
                            <input type="checkbox" id="sl-stock-actif" name="stock_actif" value="1" style="width:auto;" <?php checked( $stock_on ); ?>>
                            Limiter les quantit&eacute;s vendues en ligne
                        </label>
                        <p class="description" style="margin:6px 0 0;color:#7a5a36;font-size:12px;">
                            R&eacute;serve un nombre pr&eacute;cis d&apos;articles &agrave; la vente en ligne : chaque commande pay&eacute;e
                            d&eacute;compte une unit&eacute;, et l&apos;offre passe en rupture toute seule &agrave; z&eacute;ro.
                            Affiche aussi la mention &laquo; <em>Dans la limite des stocks disponibles</em> &raquo; sur la carte.<br>
                            <strong>D&eacute;coch&eacute; = vente sans limite</strong> (aucun d&eacute;compte, jamais de rupture).
                        </p>
                        <div id="sl-stock-qty-wrap" style="margin-top:12px;max-width:260px;<?php echo $stock_on ? '' : 'display:none;'; ?>">
                            <label for="sl-stock-qty" style="font-weight:bold;display:block;margin-bottom:5px;">Quantit&eacute; disponible (optionnel)</label>
                            <input type="number" id="sl-stock-qty" name="stock_qty" value="<?php echo esc_attr( $post_data['stock_qty'] ?? '' ); ?>" step="1" min="0" placeholder="Vide = illimit&eacute;" style="width:100%;padding:5px;">
                            <p class="description" style="margin:4px 0 0;font-size:12px;color:#7a5a36;">&Agrave; 0, l&apos;offre est masqu&eacute;e du site.</p>
                        </div>
                    </div>
                    <script>
                    (function(){
                        var cb = document.getElementById('sl-stock-actif');
                        var w  = document.getElementById('sl-stock-qty-wrap');
                        if (cb && w) cb.addEventListener('change', function(){ w.style.display = cb.checked ? '' : 'none'; });
                    })();
                    </script>

                </div>

                <!-- Colonne image -->
                <div class="sl-bp-form-sidebar">

                    <div class="sl-bp-field">
                        <label>Photo du produit</label>
                        <div class="sl-bp-image-upload" id="sl-image-upload">
                            <input type="hidden" name="image_id" id="sl-image-id" value="<?php echo esc_attr( $post_data['image_id'] ?? 0 ); ?>">
                            <div class="sl-bp-image-preview" id="sl-image-preview"
                                 style="<?php echo ! empty( $post_data['image_url'] ) ? 'background-image:url(' . esc_url( $post_data['image_url'] ) . ')' : ''; ?>">
                                <?php if ( empty( $post_data['image_url'] ) ) : ?>
                                    <span class="sl-bp-upload-placeholder">📷<br>Cliquer pour ajouter une image</span>
                                <?php endif; ?>
                            </div>
                            <div class="sl-bp-image-actions">
                                <button type="button" class="sl-bp-btn-upload" id="sl-btn-upload">Choisir une image</button>
                                <button type="button" class="sl-bp-btn-remove" id="sl-btn-remove" style="<?php echo empty( $post_data['image_id'] ) ? 'display:none' : ''; ?>">Supprimer</button>
                            </div>
                        </div>
                    </div>

                    <div class="sl-bp-field">
                        <label>Agence (automatique)</label>
                        <div class="sl-bp-agence-badge">
                            🏪 <?php echo esc_html( $agence ); ?>
                        </div>
                    </div>

                </div>
            </div>

            <div class="sl-bp-form-footer">
                <button type="submit" class="sl-bp-btn-submit">
                    🚀 <?php echo $is_edit ? 'Enregistrer les modifications' : 'Publier le bon plan'; ?>
                </button>
            </div>
        </form>
    </div>
    <?php
}
