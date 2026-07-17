<?php
/**
 * Adresse et telephone PAR AGENCE (metas de la taxonomie sl_agence_promo).
 *
 * Ces donnees n'existaient nulle part sous forme exploitable : les seules
 * coordonnees du site vivent dans le repeater Elementor de /nos-agences/,
 * enfouies dans du JSON, et connues pour etre fausses (le telephone de
 * Bonamoussadi etait duplique sur toutes les fiches). Impossible d'imprimer
 * une facture de retrait fiable sans dire au client OU se rendre.
 *
 * @package SL_Collect
 */

defined( 'ABSPATH' ) || exit;

/** Champs a la creation d'une agence. */
add_action( 'sl_agence_promo_add_form_fields', 'slc_agence_add_fields' );
function slc_agence_add_fields() {
    ?>
    <div class="form-field">
        <label for="slc_agence_adresse">Adresse de retrait</label>
        <textarea name="slc_agence_adresse" id="slc_agence_adresse" rows="2"></textarea>
        <p>Adresse précise (quartier, repère). Elle est imprimée sur la facture de retrait du client.</p>
    </div>
    <div class="form-field">
        <label for="slc_agence_tel">Téléphone de l'agence</label>
        <input type="text" name="slc_agence_tel" id="slc_agence_tel" value="">
        <p>Numéro que le client appelle pour cette agence.</p>
    </div>
    <?php
}

/** Champs a l'edition d'une agence. */
add_action( 'sl_agence_promo_edit_form_fields', 'slc_agence_edit_fields', 10, 1 );
function slc_agence_edit_fields( $term ) {
    $adresse = get_term_meta( $term->term_id, '_slc_agence_adresse', true );
    $tel     = get_term_meta( $term->term_id, '_slc_agence_tel', true );
    ?>
    <tr class="form-field">
        <th scope="row"><label for="slc_agence_adresse">Adresse de retrait</label></th>
        <td>
            <textarea name="slc_agence_adresse" id="slc_agence_adresse" rows="3" cols="50"><?php echo esc_textarea( $adresse ); ?></textarea>
            <p class="description">Adresse précise (quartier, repère). Imprimée sur la facture de retrait du client — sans elle, il ne sait pas où venir chercher sa commande.</p>
        </td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="slc_agence_tel">Téléphone de l'agence</label></th>
        <td>
            <input type="text" name="slc_agence_tel" id="slc_agence_tel" value="<?php echo esc_attr( $tel ); ?>" size="30">
            <p class="description">À défaut, le numéro de contact général des réglages Drop &amp; Collect est utilisé.</p>
        </td>
    </tr>
    <?php
}

add_action( 'created_sl_agence_promo', 'slc_agence_save_fields' );
add_action( 'edited_sl_agence_promo', 'slc_agence_save_fields' );
function slc_agence_save_fields( $term_id ) {
    if ( ! current_user_can( 'manage_categories' ) && ! current_user_can( 'manage_options' ) ) {
        return;
    }
    if ( isset( $_POST['slc_agence_adresse'] ) ) {
        update_term_meta( $term_id, '_slc_agence_adresse', sanitize_textarea_field( wp_unslash( $_POST['slc_agence_adresse'] ) ) );
    }
    if ( isset( $_POST['slc_agence_tel'] ) ) {
        update_term_meta( $term_id, '_slc_agence_tel', sanitize_text_field( wp_unslash( $_POST['slc_agence_tel'] ) ) );
    }
}

/** Colonne « Adresse » dans la liste des agences : voir d'un coup ce qui manque. */
add_filter( 'manage_edit-sl_agence_promo_columns', function ( $cols ) {
    $cols['slc_adresse'] = 'Adresse de retrait';
    return $cols;
} );
add_filter( 'manage_sl_agence_promo_custom_column', function ( $out, $col, $term_id ) {
    if ( 'slc_adresse' !== $col ) {
        return $out;
    }
    $a = get_term_meta( $term_id, '_slc_agence_adresse', true );
    return $a !== ''
        ? esc_html( wp_trim_words( $a, 10 ) )
        : '<span style="color:#b32d2e;">— à renseigner</span>';
}, 10, 3 );

/**
 * Coordonnees d'une agence pour la facture.
 *
 * @return array{nom:string,adresse:string,tel:string}
 */
function slc_agence_contact( $slug ) {
    $nom     = slc_agence_name( $slug );
    $adresse = '';
    $tel     = '';

    $term = get_term_by( 'slug', $slug, 'sl_agence_promo' );
    if ( $term && ! is_wp_error( $term ) ) {
        $adresse = (string) get_term_meta( $term->term_id, '_slc_agence_adresse', true );
        $tel     = (string) get_term_meta( $term->term_id, '_slc_agence_tel', true );
    }
    // Repli sur le contact general : mieux qu'un blanc sur la facture.
    if ( $tel === '' && function_exists( 'slc_contact_phone' ) ) {
        $tel = slc_contact_phone();
    }
    return [ 'nom' => $nom, 'adresse' => $adresse, 'tel' => $tel ];
}
