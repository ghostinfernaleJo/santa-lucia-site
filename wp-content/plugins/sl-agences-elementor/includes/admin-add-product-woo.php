<?php
/**
 * Outil d'ajout rapide d'un produit WooCommerce pour une Campagne
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_enqueue_scripts', 'sl_cwoo_add_product_enqueue_media' );
function sl_cwoo_add_product_enqueue_media( $hook ) {
    if ( empty( $_GET['page'] ) || $_GET['page'] !== 'sl-cwoo-add-product' ) {
        return;
    }

    wp_enqueue_media();
    wp_add_inline_script( 'jquery-core', "
        jQuery(function($) {
            var frame;

            $('#sl-cwoo-select-image').on('click', function(e) {
                e.preventDefault();

                if (frame) {
                    frame.open();
                    return;
                }

                frame = wp.media({
                    title: 'Choisir une image produit',
                    button: { text: 'Utiliser cette image' },
                    multiple: false
                });

                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    var imageUrl = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;

                    $('#prod_image_id').val(attachment.id);
                    $('#sl-cwoo-image-preview').html('<img src=\"' + imageUrl + '\" style=\"width:90px;height:90px;object-fit:cover;border-radius:6px;border:1px solid #ccd0d4;\">');
                    $('#sl-cwoo-remove-image').show();
                });

                frame.open();
            });

            $('#sl-cwoo-remove-image').on('click', function(e) {
                e.preventDefault();
                $('#prod_image_id').val('');
                $('#sl-cwoo-image-preview').empty();
                $(this).hide();
            });
        });
    " );
}

// 1. Ajouter le sous-menu
add_action( 'admin_menu', 'sl_cwoo_add_single_product_submenu', 30 );
function sl_cwoo_add_single_product_submenu() {
    if ( current_user_can( 'manage_options' ) ) {
        add_submenu_page(
            'edit.php?post_type=sl_campagne_woo',
            __( 'Ajouter un produit', 'sl-agences' ),
            __( 'Ajouter un produit', 'sl-agences' ),
            'manage_options',
            'sl-cwoo-add-product',
            'sl_cwoo_render_add_product_page'
        );
    }
}

function sl_cwoo_render_add_product_page() {
    $message = '';
    $status = '';

    // Traitement du formulaire
    if ( isset( $_POST['sl_cwoo_add_nonce'] ) && wp_verify_nonce( $_POST['sl_cwoo_add_nonce'], 'sl_cwoo_add_action' ) ) {
        
        $titre       = sanitize_text_field( $_POST['prod_titre'] ?? '' );
        $prix_avant  = (float) str_replace(',', '.', $_POST['prod_prix_avant'] ?? 0);
        $prix_apres  = (float) str_replace(',', '.', $_POST['prod_prix_apres'] ?? 0);
        $campaign_id = (int) $_POST['campaign_id'];
        $image_id    = absint( $_POST['prod_image_id'] ?? 0 );

        if ( empty( $titre ) || empty( $campaign_id ) ) {
            $message = 'Le titre et la campagne sont obligatoires.';
            $status = 'error';
        } else {
            $term_id = get_post_meta( $campaign_id, '_sl_cwoo_term_id', true );
            
            if ( ! $term_id ) {
                $message = 'Cette campagne n\'a pas de catégorie valide.';
                $status = 'error';
            } else {
                // Création du produit WooCommerce
                $post_id = wp_insert_post([
                    'post_title'   => $titre,
                    'post_status'  => 'publish',
                    'post_type'    => 'product',
                    'post_author'  => get_current_user_id(),
                ]);

                if ( is_wp_error( $post_id ) ) {
                    $message = 'Erreur lors de la création du produit.';
                    $status = 'error';
                } else {
                    // Produit simple
                    wp_set_object_terms( $post_id, 'simple', 'product_type' );

                    // Assigner à la catégorie de la campagne
                    wp_set_object_terms( $post_id, [ (int) $term_id ], 'product_cat', true );

                    // Prix
                    if ( $prix_avant > 0 ) update_post_meta( $post_id, '_regular_price', $prix_avant );
                    if ( $prix_apres > 0 ) {
                        update_post_meta( $post_id, '_sale_price', $prix_apres );
                        update_post_meta( $post_id, '_price', $prix_apres );
                    } elseif ( $prix_avant > 0 ) {
                        update_post_meta( $post_id, '_price', $prix_avant );
                    }

                    // Image depuis la médiathèque, avec fallback upload classique.
                    require_once( ABSPATH . 'wp-admin/includes/file.php' );
                    require_once( ABSPATH . 'wp-admin/includes/media.php' );
                    require_once( ABSPATH . 'wp-admin/includes/image.php' );

                    if ( $image_id ) {
                        set_post_thumbnail( $post_id, $image_id );
                    } elseif ( ! empty( $_FILES['prod_image']['tmp_name'] ) ) {
                        $attachment_id = media_handle_upload( 'prod_image', $post_id );
                        if ( ! is_wp_error( $attachment_id ) ) {
                            set_post_thumbnail( $post_id, $attachment_id );
                        }
                    }

                    delete_transient( 'wc_products_onsale' );

                    $message = 'Produit "' . esc_html($titre) . '" ajouté avec succès à la campagne !';
                    $status = 'success';
                }
            }
        }
    }

    $campaigns = get_posts([
        'post_type'      => 'sl_campagne_woo',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    ]);
    ?>
    <div class="wrap">
        <h1><?php _e( 'Ajouter un Produit à une Campagne', 'sl-agences' ); ?></h1>
        
        <?php if ( $message ) : ?>
            <div class="notice notice-<?php echo esc_attr( $status ); ?> is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
        <?php endif; ?>

        <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; max-width: 600px; margin-top: 20px;">
            <p>Ce formulaire simplifié vous permet de créer rapidement un produit WooCommerce et de l'assigner directement à l'une de vos campagnes, sans passer par l'interface complexe de WooCommerce.</p>
            
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'sl_cwoo_add_action', 'sl_cwoo_add_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="campaign_id"><?php _e( 'Campagne cible *', 'sl-agences' ); ?></label></th>
                        <td>
                            <select name="campaign_id" id="campaign_id" required style="width: 100%; max-width: 400px;">
                                <option value=""><?php _e( '-- Sélectionnez une campagne --', 'sl-agences' ); ?></option>
                                <?php foreach ( $campaigns as $camp ) : ?>
                                    <option value="<?php echo esc_attr( $camp->ID ); ?>">
                                        <?php echo esc_html( $camp->post_title ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="prod_titre"><?php _e( 'Nom du produit *', 'sl-agences' ); ?></label></th>
                        <td>
                            <input type="text" name="prod_titre" id="prod_titre" required style="width: 100%; max-width: 400px;">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="prod_prix_avant"><?php _e( 'Prix normal (FCFA)', 'sl-agences' ); ?></label></th>
                        <td>
                            <input type="number" name="prod_prix_avant" id="prod_prix_avant" min="0" step="1" style="width: 100%; max-width: 200px;">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="prod_prix_apres"><?php _e( 'Prix promo (FCFA)', 'sl-agences' ); ?></label></th>
                        <td>
                            <input type="number" name="prod_prix_apres" id="prod_prix_apres" min="0" step="1" style="width: 100%; max-width: 200px;">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="prod_image"><?php _e( 'Image du produit', 'sl-agences' ); ?></label></th>
                        <td>
                            <input type="hidden" name="prod_image_id" id="prod_image_id" value="">
                            <div id="sl-cwoo-image-preview" style="margin-bottom:10px;"></div>
                            <button type="button" class="button" id="sl-cwoo-select-image">
                                <?php _e( 'Choisir dans la médiathèque', 'sl-agences' ); ?>
                            </button>
                            <button type="button" class="button" id="sl-cwoo-remove-image" style="display:none;">
                                <?php _e( 'Retirer', 'sl-agences' ); ?>
                            </button>
                            <p class="description" style="margin-top:10px;">Ou importer une nouvelle image depuis votre ordinateur :</p>
                            <input type="file" name="prod_image" id="prod_image" accept="image/*">
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary button-large"><?php _e( 'Créer le produit', 'sl-agences' ); ?></button>
                </p>
            </form>
        </div>
    </div>
    <?php
}
