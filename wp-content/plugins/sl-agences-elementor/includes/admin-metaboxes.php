<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
 *  AJOUTER LES METABOXES POUR LES GESTIONNAIRES BONS PLANS
 * ============================================================ */
add_action( 'add_meta_boxes', 'sl_bp_add_metaboxes' );
function sl_bp_add_metaboxes() {
    if ( current_user_can( 'edit_sl_bon_plans' ) || current_user_can( 'manage_sl_bon_plan_terms' ) ) {
        add_meta_box(
            'sl_bp_details',
            __( 'Détails du Bon Plan', 'sl-agences' ),
            'sl_bp_render_metabox',
            'sl_bon_plan',
            'normal',
            'high'
        );
    }
}

function sl_bp_render_metabox( $post ) {
    wp_nonce_field( 'sl_bp_save_meta', 'sl_bp_meta_nonce' );

    $prix_av  = get_post_meta( $post->ID, '_sl_bp_prix_avant', true );
    $prix_ap  = get_post_meta( $post_id = $post->ID, '_sl_bp_prix_apres', true );
    $badge    = get_post_meta( $post->ID, '_sl_bp_badge_type', true ) ?: 'none';
    $date_fin = get_post_meta( $post->ID, '_sl_bp_date_fin', true );

    $badges = [
        'none'      => 'Aucun badge',
        'flash'     => '🔥 Flash',
        'nouveau'   => '🟢 Nouveau',
        'top-vente' => '👑 Top Vente',
        'exclusif'  => '💎 Exclusif',
    ];
    ?>
    <style>
        .sl-bp-admin-row { display: flex; gap: 20px; margin-bottom: 15px; }
        .sl-bp-admin-field { flex: 1; }
        .sl-bp-admin-field label { font-weight: bold; display: block; margin-bottom: 5px; }
        .sl-bp-admin-field input[type="number"], .sl-bp-admin-field input[type="date"] { width: 100%; padding: 5px; }
    </style>
    <div class="sl-bp-admin-row">
        <div class="sl-bp-admin-field">
            <label for="sl_prix_avant">Prix habituel (FCFA)</label>
            <input type="number" id="sl_prix_avant" name="sl_prix_avant" value="<?php echo esc_attr( $prix_av ); ?>" step="1" min="0">
        </div>
        <div class="sl-bp-admin-field">
            <label for="sl_prix_apres">Prix promotionnel (FCFA) *</label>
            <input type="number" id="sl_prix_apres" name="sl_prix_apres" value="<?php echo esc_attr( $prix_ap ); ?>" step="1" min="0" required>
        </div>
        <div class="sl-bp-admin-field">
            <label for="sl_date_fin">Valable jusqu'au *</label>
            <input type="date" id="sl_date_fin" name="sl_date_fin" value="<?php echo esc_attr( $date_fin ); ?>" required>
        </div>
    </div>
    <div class="sl-bp-admin-row">
        <div class="sl-bp-admin-field">
            <label>Type de badge</label>
            <?php foreach ( $badges as $val => $label ) : ?>
                <label style="margin-right: 15px;">
                    <input type="radio" name="sl_badge_type" value="<?php echo esc_attr( $val ); ?>" <?php checked( $badge, $val ); ?>>
                    <?php echo esc_html( $label ); ?>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
    <p class="description">N'oubliez pas d'assigner une <strong>Catégorie</strong>, une <strong>Agence</strong>, et une <strong>Image mise en avant</strong> dans les panneaux sur la droite.</p>
    <?php
}

/* ============================================================
 *  SAUVEGARDER LES METADONNÉES NATIVES
 * ============================================================ */
add_action( 'save_post_sl_bon_plan', 'sl_bp_save_native_meta' );
function sl_bp_save_native_meta( $post_id ) {
    if ( ! isset( $_POST['sl_bp_meta_nonce'] ) || ! wp_verify_nonce( $_POST['sl_bp_meta_nonce'], 'sl_bp_save_meta' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $prix_av  = (float) str_replace( ',', '.', $_POST['sl_prix_avant'] ?? 0 );
    $prix_ap  = (float) str_replace( ',', '.', $_POST['sl_prix_apres'] ?? 0 );
    $badge    = sanitize_key( $_POST['sl_badge_type'] ?? 'none' );
    $badge    = $badge === 'none' ? '' : $badge;
    $date_fin = sanitize_text_field( $_POST['sl_date_fin'] ?? '' );

    $reduction = ( $prix_av > 0 && $prix_ap > 0 ) ? round( ( ( $prix_av - $prix_ap ) / $prix_av ) * 100 ) : 0;

    update_post_meta( $post_id, '_sl_bp_prix_avant', $prix_av );
    update_post_meta( $post_id, '_sl_bp_prix_apres', $prix_ap );
    update_post_meta( $post_id, '_sl_bp_reduction_pct', $reduction );
    update_post_meta( $post_id, '_sl_bp_badge_type', $badge );
    update_post_meta( $post_id, '_sl_bp_date_fin', $date_fin );
}
