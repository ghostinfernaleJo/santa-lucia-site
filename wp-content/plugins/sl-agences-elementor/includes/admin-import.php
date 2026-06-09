<?php
/**
 * Outil d'importation rapide CSV pour les Bons Plans
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// 0. Gérer l'exportation dynamique du CSV
add_action( 'admin_post_sl_export_csv', 'sl_bp_export_csv_action' );
function sl_bp_export_csv_action() {
    if ( ! current_user_can( 'publish_sl_bon_plans' ) ) {
        wp_die( 'Non autorisé' );
    }

    $is_admin = current_user_can( 'manage_options' ) || current_user_can( 'manage_sl_bon_plan_terms' );
    $current_user_agence = get_user_meta( get_current_user_id(), 'sl_agence_assignee', true );

    $args = [
        'post_type' => 'sl_bon_plan',
        'posts_per_page' => -1,
        'post_status' => 'any'
    ];

    // Si pas admin, limiter à son agence
    if ( ! $is_admin && $current_user_agence ) {
        $args['tax_query'] = [
            [
                'taxonomy' => 'sl_agence_promo',
                'field'    => 'name',
                'terms'    => $current_user_agence,
            ]
        ];
    }

    $posts = get_posts( $args );

    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="export-bons-plans.csv"' );
    echo "\xEF\xBB\xBF";
    
    $output = fopen( 'php://output', 'w' );
    fputcsv( $output, ['titre', 'prix_avant', 'prix_apres', 'date_fin', 'badge', 'categorie', 'agence', 'image_url'], ';' );

    foreach ( $posts as $p ) {
        $prix_avant = get_post_meta( $p->ID, '_sl_bp_prix_avant', true );
        $prix_apres = get_post_meta( $p->ID, '_sl_bp_prix_apres', true );
        $date_fin   = get_post_meta( $p->ID, '_sl_bp_date_fin', true );
        $badge      = get_post_meta( $p->ID, '_sl_bp_badge_type', true );

        $image_url = '';
        if ( has_post_thumbnail( $p->ID ) ) {
            $image_url = get_the_post_thumbnail_url( $p->ID, 'full' );
        }

        $cat_terms = get_the_terms( $p->ID, 'sl_categorie_promo' );
        $cat_name = ( $cat_terms && ! is_wp_error( $cat_terms ) ) ? $cat_terms[0]->name : '';

        $ag_terms = get_the_terms( $p->ID, 'sl_agence_promo' );
        $ag_name = ( $ag_terms && ! is_wp_error( $ag_terms ) ) ? $ag_terms[0]->name : '';

        fputcsv( $output, [
            $p->post_title,
            $prix_avant,
            $prix_apres,
            $date_fin,
            $badge,
            $cat_name,
            $ag_name,
            $image_url
        ], ';' );
    }

    fclose( $output );
    exit;
}

// 1. Ajouter le sous-menu pour les gestionnaires Bons Plans
add_action( 'admin_menu', 'sl_bp_add_import_submenu', 30 );
function sl_bp_add_import_submenu() {
    if ( current_user_can( 'publish_sl_bon_plans' ) ) {
        add_submenu_page(
            'edit.php?post_type=sl_bon_plan',
            __( 'Import / Export CSV', 'sl-agences' ),
            __( 'Import / Export CSV', 'sl-agences' ),
            'publish_sl_bon_plans',
            'sl-bp-import',
            'sl_bp_render_import_page'
        );
    }
}

// 2. Rendu de la page d'importation
function sl_bp_render_import_page() {
    $message = '';
    $status = '';

    // Traitement du formulaire
    if ( isset( $_POST['sl_bp_import_nonce'] ) && wp_verify_nonce( $_POST['sl_bp_import_nonce'], 'sl_bp_import_action' ) ) {
        if ( isset( $_FILES['csv_file'] ) && ! empty( $_FILES['csv_file']['tmp_name'] ) ) {
            $file = $_FILES['csv_file']['tmp_name'];
            $update_existing = isset($_POST['update_existing']) ? true : false;
            $result = sl_bp_process_csv_import( $file, $update_existing );
            $message = $result['message'];
            $status = $result['status'];
        } else {
            $message = __( 'Veuillez sélectionner un fichier CSV.', 'sl-agences' );
            $status = 'error';
        }
    }

    ?>
    <div class="wrap">
        <h1><?php _e( 'Import / Export de Bons Plans (CSV)', 'sl-agences' ); ?></h1>
        
        <?php if ( $message ) : ?>
            <div class="notice notice-<?php echo esc_attr( $status ); ?> is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
        <?php endif; ?>

        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; flex: 1; min-width: 300px; margin-top: 20px;">
                <h2><?php _e( '1. Importer des données', 'sl-agences' ); ?></h2>
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field( 'sl_bp_import_action', 'sl_bp_import_nonce' ); ?>
                    <table class="form-table">
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
                                    <input type="checkbox" name="update_existing" id="update_existing" value="1">
                                    Mettre à jour les produits existants s'ils ont le même titre (au lieu de créer des doublons).
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
                <h2><?php _e( '2. Instructions & Modèle', 'sl-agences' ); ?></h2>
                <p><?php _e( 'Chargez un fichier CSV avec les colonnes suivantes (séparateur: ; ou ,) :', 'sl-agences' ); ?></p>
                <ul style="list-style-type: disc; margin-left: 20px;">
                    <li><strong>titre</strong> (Requis) : Le nom du produit</li>
                    <li><strong>prix_avant</strong> : Prix normal (ex: 3500)</li>
                    <li><strong>prix_apres</strong> : Prix réduit (ex: 2800)</li>
                    <li><strong>date_fin</strong> : Date de validité (ex: 2026-05-30)</li>
                    <li><strong>badge</strong> : flash, nouveau, exclusif, top-vente</li>
                    <li><strong>categorie</strong> : (ex: Alimentaire)</li>
                    <li><strong>agence</strong> : (ex: Bastos) - <em>Requis si admin</em></li>
                    <li><strong>image_url</strong> : URL complète de l'image (jpg, png...)</li>
                </ul>
                <p>
                    <a href="<?php echo esc_url( SL_AGENCES_URL . 'modele-import.csv' ); ?>" class="button button-secondary" download="modele-import.csv">
                        📥 Télécharger le modèle CSV
                    </a>
                </p>
            </div>

            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; width: 100%; margin-top: 20px;">
                <h2><?php _e( '3. Exporter les données', 'sl-agences' ); ?></h2>
                <p><?php _e( 'Téléchargez tous vos Bons Plans actuels dans un fichier Excel (CSV). C\'est très utile pour faire une sauvegarde, ou pour utiliser ce fichier comme modèle de base pour vos futures importations !', 'sl-agences' ); ?></p>
                <p>
                    <a href="<?php echo esc_url( admin_url('admin-post.php?action=sl_export_csv') ); ?>" class="button button-secondary button-large">
                        📤 Exporter mes Bons Plans (CSV)
                    </a>
                </p>
            </div>
        </div>
    </div>
    <?php
}

// 3. Logique de traitement du CSV
function sl_bp_process_csv_import( $file_path, $update_existing = false ) {
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
        $header = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $header); // Enlever les caractères invisibles
        return trim( strtolower( $header ) );
    }, $headers );

    // Gérer l'ancienne convention de nommage pour assurer la rétrocompatibilité
    $headers = array_map(function($header) {
        if ($header === 'prix_habituel') return 'prix_avant';
        if ($header === 'prix_promo') return 'prix_apres';
        return $header;
    }, $headers);

    $count_created = 0;
    $count_updated = 0;
    $count_error = 0;
    $is_admin = current_user_can( 'manage_options' ) || current_user_can( 'manage_sl_bon_plan_terms' );
    $current_user_agence = get_user_meta( get_current_user_id(), 'sl_agence_assignee', true );

    // Inclure les fonctions requises pour le téléchargement d'images
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
            $existing_post = get_page_by_title( $row['titre'], OBJECT, 'sl_bon_plan' );
            if ( $existing_post ) {
                $post_id = $existing_post->ID;
            }
        }

        if ( $post_id ) {
            // Mise à jour
            $post_data = [
                'ID' => $post_id,
                'post_status' => 'publish',
            ];
            wp_update_post( $post_data );
            $count_updated++;
        } else {
            // Création
            $post_data = [
                'post_title'  => $row['titre'],
                'post_status' => 'publish',
                'post_type'   => 'sl_bon_plan',
                'post_author' => get_current_user_id(),
            ];
            $post_id = wp_insert_post( $post_data );
            if ( is_wp_error( $post_id ) ) {
                $count_error++;
                continue;
            }
            $count_created++;
        }

        // Méta données
        $prix_av = isset($row['prix_avant']) ? (float) str_replace(',', '.', $row['prix_avant']) : 0;
        $prix_ap = isset($row['prix_apres']) ? (float) str_replace(',', '.', $row['prix_apres']) : 0;
        
        if ( isset( $row['prix_avant'] ) ) update_post_meta( $post_id, '_sl_bp_prix_avant', $prix_av );
        if ( isset( $row['prix_apres'] ) ) update_post_meta( $post_id, '_sl_bp_prix_apres', $prix_ap );
        
        // Calcul du % de réduction
        $reduction = ( $prix_av > 0 && $prix_ap > 0 ) ? round( ( ( $prix_av - $prix_ap ) / $prix_av ) * 100 ) : 0;
        update_post_meta( $post_id, '_sl_bp_reduction_pct', $reduction );

        if ( ! empty( $row['date_fin'] ) ) {
            update_post_meta( $post_id, '_sl_bp_date_fin', $row['date_fin'] );
        }
        if ( ! empty( $row['badge'] ) ) {
            update_post_meta( $post_id, '_sl_bp_badge_type', strtolower( $row['badge'] ) );
        }

        // Taxonomies (Catégorie)
        if ( ! empty( $row['categorie'] ) ) {
            $term = get_term_by( 'name', $row['categorie'], 'sl_categorie_promo' );
            if ( ! $term ) {
                $inserted = wp_insert_term( $row['categorie'], 'sl_categorie_promo' );
                if ( ! is_wp_error( $inserted ) ) {
                    wp_set_object_terms( $post_id, [ (int) $inserted['term_id'] ], 'sl_categorie_promo' );
                }
            } else {
                wp_set_object_terms( $post_id, [ (int) $term->term_id ], 'sl_categorie_promo' );
            }
        }

        // Taxonomies (Agence)
        $agence_a_assigner = '';
        if ( ! $is_admin && $current_user_agence ) {
            $agence_a_assigner = $current_user_agence;
        } elseif ( ! empty( $row['agence'] ) ) {
            $agence_a_assigner = $row['agence'];
        }

        if ( $agence_a_assigner ) {
            $term_agence = get_term_by( 'name', $agence_a_assigner, 'sl_agence_promo' );
            if ( ! $term_agence ) {
                $inserted_ag = wp_insert_term( $agence_a_assigner, 'sl_agence_promo' );
                if ( ! is_wp_error( $inserted_ag ) ) {
                    wp_set_object_terms( $post_id, [ (int) $inserted_ag['term_id'] ], 'sl_agence_promo' );
                }
            } else {
                wp_set_object_terms( $post_id, [ (int) $term_agence->term_id ], 'sl_agence_promo' );
            }
        }

        // Image (sideload)
        if ( ! empty( $row['image_url'] ) && filter_var($row['image_url'], FILTER_VALIDATE_URL) ) {
            // Télécharger si le produit n'a pas d'image
            if ( ! has_post_thumbnail( $post_id ) ) {
                $image_id = media_sideload_image( $row['image_url'], $post_id, $row['titre'], 'id' );
                if ( ! is_wp_error( $image_id ) ) {
                    set_post_thumbnail( $post_id, $image_id );
                }
            }
        }
    }

    fclose( $handle );

    return [
        'status'  => 'success',
        'message' => sprintf( __( 'Importation terminée : %d créés, %d mis à jour (%d erreurs).', 'sl-agences' ), $count_created, $count_updated, $count_error )
    ];
}
