<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Ajoute le champ "Agence Fast Food" sur la page de profil utilisateur.
 * Visible uniquement par les administrateurs.
 */
add_action( 'show_user_profile', 'sl_ff_user_profile_field' );
add_action( 'edit_user_profile', 'sl_ff_user_profile_field' );
function sl_ff_user_profile_field( $user ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $current_agence = get_user_meta( $user->ID, '_sl_agence_ff', true );
    $agences        = get_terms( [ 'taxonomy' => 'sl_agence_promo', 'hide_empty' => false ] );
    ?>
    <h2>Fast Food</h2>
    <table class="form-table">
        <tr>
            <th><label for="sl_agence_ff">Agence Fast Food</label></th>
            <td>
                <select name="sl_agence_ff" id="sl_agence_ff">
                    <option value="">-- Aucune agence --</option>
                    <?php if ( ! is_wp_error( $agences ) ) :
                        foreach ( $agences as $a ) : ?>
                        <option value="<?php echo esc_attr( $a->slug ); ?>"
                            <?php selected( $current_agence, $a->slug ); ?>>
                            <?php echo esc_html( $a->name ); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
                <p class="description">
                    Assigne ce responsable Fast Food a une agence specifique.
                    Il ne verra que les repas de cette agence.
                </p>
            </td>
        </tr>
    </table>
    <?php
}

/**
 * Sauvegarde le champ agence lors de l'enregistrement du profil.
 */
add_action( 'personal_options_update',  'sl_ff_save_user_profile_field' );
add_action( 'edit_user_profile_update', 'sl_ff_save_user_profile_field' );
function sl_ff_save_user_profile_field( $user_id ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    if ( ! isset( $_POST['sl_agence_ff'] ) ) {
        return;
    }

    $agence = sanitize_text_field( $_POST['sl_agence_ff'] );

    if ( $agence ) {
        update_user_meta( $user_id, '_sl_agence_ff', $agence );
    } else {
        delete_user_meta( $user_id, '_sl_agence_ff' );
    }
}
