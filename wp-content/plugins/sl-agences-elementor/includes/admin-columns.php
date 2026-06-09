<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// 1. Ajouter les colonnes personnalisées
add_filter( 'manage_sl_bon_plan_posts_columns', 'sl_bp_set_custom_columns' );
function sl_bp_set_custom_columns( $columns ) {
    $new_columns = [];
    $new_columns['cb'] = $columns['cb'];
    $new_columns['image'] = __( 'Image', 'sl-agences' );
    $new_columns['title'] = $columns['title'];
    $new_columns['agence'] = __( 'Agence', 'sl-agences' );
    $new_columns['prix'] = __( 'Prix & Réduction', 'sl-agences' );
    $new_columns['expiration'] = __( 'Expiration', 'sl-agences' );
    $new_columns['author'] = __( 'Auteur', 'sl-agences' );
    $new_columns['date'] = $columns['date'];
    return $new_columns;
}

// 1.5. Enqueue scripts pour le Quick Image
add_action( 'admin_enqueue_scripts', 'sl_bp_admin_columns_scripts' );
function sl_bp_admin_columns_scripts( $hook ) {
    global $typenow;
    if ( $hook === 'edit.php' && $typenow === 'sl_bon_plan' ) {
        wp_enqueue_media();
        wp_enqueue_script( 'sl-bp-admin', SL_AGENCES_URL . 'assets/js/bons-plans-admin.js', [ 'jquery' ], SL_AGENCES_VERSION, true );
        wp_localize_script( 'sl-bp-admin', 'slBpAdmin', [
            'nonce' => wp_create_nonce( 'sl_quick_image_nonce' )
        ]);
    }
}

// 2. Remplir les colonnes personnalisées
add_action( 'manage_sl_bon_plan_posts_custom_column', 'sl_bp_custom_column_content', 10, 2 );
function sl_bp_custom_column_content( $column, $post_id ) {
    switch ( $column ) {
        case 'image':
            $img = get_the_post_thumbnail_url( $post_id, 'thumbnail' );
            echo '<div class="sl-quick-img-wrapper" data-post-id="' . esc_attr( $post_id ) . '" style="position:relative; width:50px; height:50px;">';
            if ( $img ) {
                echo '<img src="' . esc_url( $img ) . '" width="50" height="50" style="object-fit:cover; border-radius:4px; cursor:pointer;" class="sl-quick-img-btn" title="' . esc_attr__( 'Changer l\'image', 'sl-agences' ) . '">';
            } else {
                echo '<button type="button" class="button sl-quick-img-btn" style="width:100%; height:100%; padding:0; line-height:1; display:flex; align-items:center; justify-content:center; border-radius:4px;" title="' . esc_attr__( 'Ajouter une image', 'sl-agences' ) . '"><span class="dashicons dashicons-format-image" style="color:#888;"></span></button>';
            }
            // Loader overlay hidden by default
            echo '<div class="sl-quick-img-loader" style="display:none; position:absolute; top:0; left:0; right:0; bottom:0; background:rgba(255,255,255,0.8); align-items:center; justify-content:center; border-radius:4px;"><span class="spinner is-active" style="float:none; margin:0;"></span></div>';
            echo '</div>';
            break;

        case 'agence':
            $terms = get_the_terms( $post_id, 'sl_agence_promo' );
            if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                $agences = wp_list_pluck( $terms, 'name' );
                echo esc_html( implode( ', ', $agences ) );
            } else {
                echo '—';
            }
            break;

        case 'prix':
            $prix_av = get_post_meta( $post_id, '_sl_bp_prix_avant', true );
            $prix_ap = get_post_meta( $post_id, '_sl_bp_prix_apres', true );
            $reduc   = get_post_meta( $post_id, '_sl_bp_reduction_pct', true );
            
            if ( $prix_ap ) {
                echo '<strong>' . number_format( (float)$prix_ap, 0, ',', ' ' ) . ' FCFA</strong><br>';
            } else {
                echo '—<br>';
            }
            if ( $prix_av ) {
                echo '<span style="text-decoration:line-through; color:#888;">' . number_format( (float)$prix_av, 0, ',', ' ' ) . ' FCFA</span> ';
            }
            if ( $reduc ) {
                echo '<span style="background:#E91E63; color:#fff; padding:2px 5px; border-radius:3px; font-size:10px; font-weight:bold;">-' . esc_html( $reduc ) . '%</span>';
            }
            break;

        case 'expiration':
            $date_fin = get_post_meta( $post_id, '_sl_bp_date_fin', true );
            if ( $date_fin ) {
                $today = current_time( 'Y-m-d' );
                if ( $date_fin < $today ) {
                    echo '<span style="color:#d63638; font-weight:bold;">Expiré le ' . date( 'd/m/Y', strtotime( $date_fin ) ) . '</span>';
                } else {
                    echo '<span style="color:#00a32a;">Jusqu\'au ' . date( 'd/m/Y', strtotime( $date_fin ) ) . '</span>';
                }
            } else {
                echo '—';
            }
            break;
    }
}

// 3. AJAX Handler pour le Quick Image
add_action( 'wp_ajax_sl_quick_image_save', 'sl_bp_ajax_quick_image_save' );
function sl_bp_ajax_quick_image_save() {
    check_ajax_referer( 'sl_quick_image_nonce', 'security' );

    $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
    $image_id = isset( $_POST['image_id'] ) ? intval( $_POST['image_id'] ) : 0;

    if ( ! $post_id || ! $image_id ) {
        wp_send_json_error( 'Données invalides' );
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        wp_send_json_error( 'Permission refusée' );
    }

    $result = set_post_thumbnail( $post_id, $image_id );

    if ( $result ) {
        wp_send_json_success( 'Image sauvegardée' );
    } else {
        wp_send_json_error( 'Erreur lors de la sauvegarde de l\'image' );
    }
}
