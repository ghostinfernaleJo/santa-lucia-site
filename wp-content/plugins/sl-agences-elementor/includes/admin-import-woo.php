<?php
/**
 * Outil d'importation rapide CSV pour les Produits WooCommerce (Campagnes)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// 1. Ajouter le sous-menu
add_action( 'admin_menu', 'sl_cwoo_add_import_submenu', 30 );
function sl_cwoo_add_import_submenu() {
    if ( current_user_can( 'manage_options' ) ) {
        add_submenu_page(
            'edit.php?post_type=sl_campagne_woo',
            __( 'Import Produits (WooCommerce)', 'sl-agences' ),
            __( 'Import Produits CSV', 'sl-agences' ),
            'manage_options',
            'sl-cwoo-import',
            'sl_cwoo_render_import_page'
        );
    }
}

// 2. Rendu de la page d'importation
function sl_cwoo_render_import_page() {
    $message = '';
    $status = '';

    // Traitement
    if ( isset( $_POST['sl_cwoo_import_nonce'] ) && wp_verify_nonce( $_POST['sl_cwoo_import_nonce'], 'sl_cwoo_import_action' ) ) {
        if ( isset( $_FILES['csv_file'] ) && ! empty( $_FILES['csv_file']['tmp_name'] ) && ! empty( $_POST['campaign_id'] ) ) {
            $file = $_FILES['csv_file']['tmp_name'];
            $campaign_id = (int) $_POST['campaign_id'];
            $update_existing = isset($_POST['update_existing']) ? true : false;
            
            $result = sl_cwoo_process_csv_import( $file, $campaign_id, $update_existing );
            $message = $result['message'];
            $status = $result['status'];
        } else {
            $message = __( 'Veuillez sélectionner un fichier CSV et une campagne.', 'sl-agences' );
            $status = 'error';
        }
    }

    // Récupérer les campagnes actives
    $campaigns = get_posts([
        'post_type'      => 'sl_campagne_woo',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    ]);

    ?>
    <div class="wrap">
        <h1><?php _e( 'Import de Produits WooCommerce pour Campagne', 'sl-agences' ); ?></h1>
        
        <?php if ( $message ) : ?>
            <div class="notice notice-<?php echo esc_attr( $status ); ?> is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
        <?php endif; ?>

        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; flex: 1; min-width: 300px; margin-top: 20px;">
                <h2><?php _e( '1. Importer des données', 'sl-agences' ); ?></h2>
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field( 'sl_cwoo_import_action', 'sl_cwoo_import_nonce' ); ?>
                    <table class="form-table">
                        <tr>
                            <th><label for="campaign_id"><?php _e( 'Choisir la Campagne', 'sl-agences' ); ?></label></th>
                            <td>
                                <select name="campaign_id" id="campaign_id" required>
                                    <option value=""><?php _e( '-- Sélectionnez une campagne --', 'sl-agences' ); ?></option>
                                    <?php foreach ( $campaigns as $camp ) : 
                                        $term_id = get_post_meta($camp->ID, '_sl_cwoo_term_id', true);
                                    ?>
                                        <option value="<?php echo esc_attr( $camp->ID ); ?>">
                                            <?php echo esc_html( $camp->post_title ); ?> 
                                            <?php echo $term_id ? '(Catégorie liée)' : '(Sans catégorie)'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Tous les produits importés seront ajoutés à la catégorie WooCommerce associée à cette campagne.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="csv_file"><?php _e( 'Fichier CSV', 'sl-agences' ); ?></label></th>
                            <td>
                                <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="update_existing"><?php _e( 'Mise à jour', 'sl-agences' ); ?></label></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="update_existing" id="update_existing" value="1" checked>
                                    Mettre à jour les produits existants s'ils ont le même titre.
                                </label>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button button-primary button-large"><?php _e( 'Lancer l\'importation', 'sl-agences' ); ?></button>
                    </p>
                </form>
            </div>

            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; flex: 1; min-width: 300px; margin-top: 20px;">
                <h2><?php _e( '2. Instructions', 'sl-agences' ); ?></h2>
                <p><?php _e( 'Votre fichier CSV doit comporter ces colonnes :', 'sl-agences' ); ?></p>
                <ul style="list-style-type: disc; margin-left: 20px;">
                    <li><strong>titre</strong> (Requis) : Le nom du produit</li>
                    <li><strong>prix_avant</strong> : Prix normal</li>
                    <li><strong>prix_apres</strong> : Prix réduit</li>
                    <li><strong>description</strong> : Courte description (optionnel)</li>
                    <li><strong>image_url</strong> : URL complète de l'image (jpg, png...)</li>
                </ul>
                <p>Note : Pas besoin de spécifier la date de fin, ni la catégorie dans le fichier, puisque tout le fichier sera assigné à la campagne sélectionnée.</p>
            </div>
        </div>
    </div>
    <?php
}

// 3. Logique d'import
function sl_cwoo_process_csv_import( $file_path, $campaign_id, $update_existing = false ) {
    $term_id = get_post_meta( $campaign_id, '_sl_cwoo_term_id', true );
    if ( ! $term_id ) {
        return [ 'status' => 'error', 'message' => 'Cette campagne n\'a pas de catégorie WooCommerce associée. Veuillez l\'enregistrer à nouveau.' ];
    }

    $handle = fopen( $file_path, "r" );
    if ( $handle === false ) {
        return [ 'status' => 'error', 'message' => 'Impossible de lire le fichier.' ];
    }

    $first_line = fgets($handle);
    $delimiter = strpos($first_line, ';') !== false ? ';' : ',';
    rewind($handle);

    $headers = fgetcsv( $handle, 1000, $delimiter );
    if ( ! $headers ) {
        return [ 'status' => 'error', 'message' => 'Fichier CSV vide ou invalide.' ];
    }

    $headers = array_map( function($header) {
        $header = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $header);
        return trim( strtolower( $header ) );
    }, $headers );

    // Rétrocompatibilité
    $headers = array_map(function($header) {
        if ($header === 'prix_habituel') return 'prix_avant';
        if ($header === 'prix_promo') return 'prix_apres';
        return $header;
    }, $headers);

    $count_created = 0;
    $count_updated = 0;
    $count_error = 0;

    require_once( ABSPATH . 'wp-admin/includes/file.php' );
    require_once( ABSPATH . 'wp-admin/includes/media.php' );
    require_once( ABSPATH . 'wp-admin/includes/image.php' );

    while ( ( $data = fgetcsv( $handle, 1000, $delimiter ) ) !== false ) {
        $row = [];
        foreach ( $headers as $index => $key ) {
            $val = isset($data[$index]) ? trim($data[$index]) : '';
            if ($key === 'image_url') {
                $row[$key] = esc_url_raw($val);
            } else {
                $row[$key] = sanitize_text_field($val);
            }
        }

        if ( empty( $row['titre'] ) ) {
            $count_error++;
            continue;
        }

        $post_id = 0;

        if ( $update_existing ) {
            $existing_post = get_page_by_title( $row['titre'], OBJECT, 'product' );
            if ( $existing_post ) {
                $post_id = $existing_post->ID;
            }
        }

        $description = $row['description'] ?? '';

        if ( $post_id ) {
            // Update
            wp_update_post([
                'ID'           => $post_id,
                'post_excerpt' => $description,
            ]);
            $count_updated++;
        } else {
            // Create
            $post_id = wp_insert_post([
                'post_title'   => $row['titre'],
                'post_status'  => 'publish',
                'post_type'    => 'product',
                'post_excerpt' => $description,
                'post_author'  => get_current_user_id(),
            ]);
            if ( is_wp_error( $post_id ) ) {
                $count_error++;
                continue;
            }
            $count_created++;
        }

        // Définir comme produit simple WooCommerce
        wp_set_object_terms( $post_id, 'simple', 'product_type' );

        // Assigner à la catégorie WooCommerce de la Campagne
        wp_set_object_terms( $post_id, [ (int) $term_id ], 'product_cat', true ); // true = append (si jamais il avait d'autres catégories)

        // Prix
        $prix_av = isset($row['prix_avant']) ? (float) str_replace(',', '.', $row['prix_avant']) : 0;
        $prix_ap = isset($row['prix_apres']) ? (float) str_replace(',', '.', $row['prix_apres']) : 0;
        
        if ( $prix_av > 0 ) update_post_meta( $post_id, '_regular_price', $prix_av );
        if ( $prix_ap > 0 ) {
            update_post_meta( $post_id, '_sale_price', $prix_ap );
            update_post_meta( $post_id, '_price', $prix_ap );
        } elseif ( $prix_av > 0 ) {
            update_post_meta( $post_id, '_price', $prix_av );
        }

        // Image
        if ( ! empty( $row['image_url'] ) && filter_var($row['image_url'], FILTER_VALIDATE_URL) ) {
            if ( ! has_post_thumbnail( $post_id ) ) {
                $image_id = media_sideload_image( $row['image_url'], $post_id, $row['titre'], 'id' );
                if ( ! is_wp_error( $image_id ) ) {
                    set_post_thumbnail( $post_id, $image_id );
                }
            }
        }
    }

    fclose( $handle );
    delete_transient( 'wc_products_onsale' );

    return [
        'status'  => 'success',
        'message' => sprintf( __( 'Importation WooCommerce terminée : %d produits créés, %d mis à jour (%d erreurs).', 'sl-agences' ), $count_created, $count_updated, $count_error )
    ];
}
