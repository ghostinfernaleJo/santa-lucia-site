<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', 'sl_cwoo_add_magic_import_menu', 35 );
function sl_cwoo_add_magic_import_menu() {
    if ( current_user_can( 'manage_options' ) ) {
        add_submenu_page(
            'edit.php?post_type=sl_campagne_woo',
            'Import Magique (IA)',
            'Import Magique 🪄',
            'manage_options',
            'sl-cwoo-magic-import',
            'sl_cwoo_render_magic_import'
        );
    }
}

// Fonction de lecture ultra-légère pour extraire le texte brut d'un XLSX
function sl_cwoo_read_excel_raw($file_path) {
    if ( ! class_exists('ZipArchive') ) return file_get_contents($file_path); // Fallback
    
    $zip = new ZipArchive();
    if ($zip->open($file_path) === true) {
        $shared_strings = [];
        if (($shared_str_xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $xml = simplexml_load_string($shared_str_xml);
            if ($xml) {
                foreach ($xml->si as $val) {
                    if (isset($val->t)) {
                        $shared_strings[] = (string)$val->t;
                    } elseif (isset($val->r)) {
                        $str = '';
                        foreach ($val->r as $r) { if(isset($r->t)) $str .= (string)$r->t; }
                        $shared_strings[] = $str;
                    } else {
                        $shared_strings[] = '';
                    }
                }
            }
        }
        
        // Recuperer la feuille : sheet1.xml, sinon la premiere feuille trouvee.
        $sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheet_xml === false) {
            for ($z = 0; $z < $zip->numFiles; $z++) {
                $name = $zip->getNameIndex($z);
                if ($name && strpos($name, 'xl/worksheets/sheet') === 0 && substr($name, -4) === '.xml') {
                    $sheet_xml = $zip->getFromName($name);
                    break;
                }
            }
        }

        $rows = [];
        if ($sheet_xml !== false) {
            $xml = simplexml_load_string($sheet_xml);
            if ($xml && isset($xml->sheetData->row)) {
                foreach ($xml->sheetData->row as $row) {
                    $row_data = [];
                    foreach ($row->c as $c) {
                        $type = isset($c['t']) ? (string)$c['t'] : '';
                        if ($type === 's') {
                            $val = $shared_strings[(int)$c->v] ?? (string)$c->v;
                        } elseif ($type === 'inlineStr') {
                            $val = isset($c->is->t) ? (string)$c->is->t : '';
                        } else {
                            $val = (string)$c->v;
                        }
                        $row_data[] = $val;
                    }
                    $rows[] = implode(" | ", $row_data);
                }
            }
        }
        $zip->close();
        return implode("\n", $rows);
    }
    return false;
}

function sl_cwoo_render_magic_import() {
    // Fournisseur IA actif (Gemini / OpenAI / Claude) + sa clé. Aucune clé en dur.
    $ai_provider = function_exists('sl_ai_get_provider') ? sl_ai_get_provider() : 'gemini';
    $ai_provider_label = function_exists('sl_ai_providers') ? sl_ai_providers()[$ai_provider]['label'] : 'IA';
    $api_key = function_exists('sl_ai_get_key') ? sl_ai_get_key( $ai_provider ) : trim( (string) get_option('sl_gemini_api_key') );
    if ( empty($api_key) ) {
        echo '<div class="wrap"><h1>Import Magique (IA)</h1><div class="notice notice-error"><p>Aucune clé API configurée pour <strong>' . esc_html($ai_provider_label) . '</strong>. Configurez-la dans <a href="' . esc_url( admin_url('edit.php?post_type=sl_campagne_woo&page=sl-cwoo-settings-ai') ) . '">Réglages IA</a>.</p></div></div>';
        return;
    }

    $step = isset($_POST['magic_step']) ? intval($_POST['magic_step']) : 1;
    $message = '';

    // Etape 3 : Traitement Final
    if ( $step === 3 && isset($_POST['magic_nonce']) && wp_verify_nonce($_POST['magic_nonce'], 'magic_final') ) {
        $campaign_id = intval($_POST['campaign_id']);
        $term_id = get_post_meta($campaign_id, '_sl_cwoo_term_id', true);
        
        $titres = $_POST['final_titre'] ?? [];
        $prix_avants = $_POST['final_prix_avant'] ?? [];
        $prix_apres = $_POST['final_prix_apres'] ?? [];
        $manual_selections = $_POST['final_images'] ?? []; 
        $count = 0;
        
        if (is_array($titres)) {
            foreach ( $titres as $i => $titre ) {
                $titre = sanitize_text_field($titre);
                if (empty($titre)) continue;

                $att_id = isset($manual_selections[$i]) ? intval($manual_selections[$i]) : 0;
                
                $post_id = wp_insert_post([
                    'post_title'   => $titre,
                    'post_status'  => 'publish',
                    'post_type'    => 'product',
                    'post_author'  => get_current_user_id()
                ]);

                if ( ! is_wp_error($post_id) ) {
                    wp_set_object_terms( $post_id, 'simple', 'product_type' );
                    if ( $term_id ) {
                        wp_set_object_terms( $post_id, [(int)$term_id], 'product_cat', true );
                    }

                    $prix_av = floatval(str_replace([' ', ','], ['', '.'], $prix_avants[$i] ?? 0));
                    $prix_ap = floatval(str_replace([' ', ','], ['', '.'], $prix_apres[$i] ?? 0));
                    
                    if ( $prix_av > 0 ) update_post_meta( $post_id, '_regular_price', $prix_av );
                    if ( $prix_ap > 0 ) {
                        update_post_meta( $post_id, '_sale_price', $prix_ap );
                        update_post_meta( $post_id, '_price', $prix_ap );
                    } elseif ( $prix_av > 0 ) {
                        update_post_meta( $post_id, '_price', $prix_av );
                    }

                    if ( $att_id > 0 ) {
                        set_post_thumbnail( $post_id, $att_id );
                    }
                    $count++;
                }
            }
        }
        
        echo '<div class="wrap"><h1>Import Réussi !</h1><div class="notice notice-success"><p>'.$count.' produits importés et classés avec succès !</p></div><a href="?post_type=sl_campagne_woo&page=sl-cwoo-magic-import" class="button">Faire un autre import</a></div>';
        return;
    }

    // Etape 2 : Appel API Google Gemini et Preview
    if ( $step === 2 && isset($_POST['magic_nonce']) && wp_verify_nonce($_POST['magic_nonce'], 'magic_upload') ) {
        if ( empty($_FILES['data_file']['tmp_name']) ) {
            $step = 1;
            $message = 'Veuillez uploader un fichier de données.';
        } else {
            // 1. Lire le fichier de données (Excel ou CSV)
            $file_name = $_FILES['data_file']['name'];
            $file_tmp = $_FILES['data_file']['tmp_name'];
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            $raw_data_string = "";
            if ($ext === 'xlsx') {
                $raw_data_string = sl_cwoo_read_excel_raw($file_tmp);
                if (!$raw_data_string) {
                    $raw_data_string = "Erreur de lecture du fichier Excel.";
                }
            } else {
                $raw_data_string = file_get_contents($file_tmp);
            }
            // 2. Upload temporaire des images dans WP
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
            require_once( ABSPATH . 'wp-admin/includes/media.php' );

            $uploaded_images = []; // filename => attachment_id
            if ( !empty($_FILES['images']['tmp_name'][0]) ) {
                $files = $_FILES['images'];
                $file_count = count($files['name']);
                
                for ($i = 0; $i < $file_count; $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $upload = wp_upload_bits($files['name'][$i], null, file_get_contents($files['tmp_name'][$i]));
                        if ( ! $upload['error'] ) {
                            $filename = $upload['file'];
                            $wp_filetype = wp_check_filetype( $filename, null );
                            $attachment = [
                                'post_mime_type' => $wp_filetype['type'],
                                'post_title'     => sanitize_file_name( $files['name'][$i] ),
                                'post_content'   => '',
                                'post_status'    => 'inherit'
                            ];
                            $attach_id = wp_insert_attachment( $attachment, $filename );
                            require_once( ABSPATH . 'wp-admin/includes/image.php' );
                            $attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
                            wp_update_attachment_metadata( $attach_id, $attach_data );
                            
                            $uploaded_images[$files['name'][$i]] = [
                                'id' => $attach_id,
                                'url' => wp_get_attachment_url($attach_id),
                                'path' => $filename
                            ];
                        }
                    }
                }
            }

            // 3. Appel API Google Gemini
            $prompt = "You are a specialized data extraction and image matching assistant for a supermarket (Santa Lucia).
Your mission is to extract product data from a messy spreadsheet and match it with provided images.

### EXTRACTION RULES:
1. Identify products in the raw text below.
2. For each product, extract:
   - 'titre': The full name as it appears in the Excel.
   - 'prix_avant': The normal price.
   - 'prix_apres': The reduced/promo price.
3. CRITICAL: ONLY extract products where you find ALL THREE (Title, Regular Price, Promo Price). If any price is missing, skip the product.

### MATCHING RULES (IMAGE TO PRODUCT):
1. Match each image filename to the most appropriate product.
2. IGNORE differences in case, accents, apostrophes, dashes, and multiple spaces.
3. HANDLE VARIANTS: Treat '75CL', '75 cl', '0.75L' and '750ML' as equivalent when matching.
4. HANDLE ABBREVIATIONS: Be smart with common supermarket abbreviations:
   - 'SAUV' matches 'SAUVIGNON'
   - 'GINEESTET' matches 'GINESTET'
   - 'LARRONCHI' matches 'LARONCHI'
   - 'VERMOUTI' matches 'VERMOUTH'
   - 'AFRICAN CREAM' matches 'AFRICA CREAM'
5. NO HALLUCINATION: If an image does not clearly match any product, set its filename to null. Do not invent products.

### IMAGES:
Each image is provided right after a line 'Image filename: <name>'. Use that exact <name> as the 'filename' value when an image matches a product.

### OUTPUT FORMAT:
You MUST respond with ONLY a valid JSON object (no other text, no markdown), shaped like:
{
  \"products\": [
    {
      \"titre\": \"Product Name from Excel\",
      \"prix_avant\": \"1500\",
      \"prix_apres\": \"1200\",
      \"filename\": \"exact_image_filename.jpg\"
    }
  ]
}

RAW SPREADSHEET DATA:
" . substr($raw_data_string, 0, 12000);

            // Normaliser les images pour la couche multi-fournisseurs (Gemini / OpenAI / Claude).
            $images_payload = [];
            foreach ( $uploaded_images as $filename => $img ) {
                $mime = @mime_content_type( $img['path'] );
                if ( ! in_array( $mime, [ 'image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif' ], true ) ) {
                    $mime = 'image/jpeg';
                }
                $images_payload[] = [
                    'name' => $filename,
                    'mime' => $mime,
                    'b64'  => base64_encode( file_get_contents( $img['path'] ) ),
                ];
            }

            // Appel IA via le fournisseur actif (sl_ai_extract_products gère Gemini / OpenAI / Claude).
            $ai_result     = sl_ai_extract_products( $prompt, $images_payload );
            $ai_products   = $ai_result['ok'] ? $ai_result['products'] : [];
            $api_error_msg = $ai_result['ok'] ? '' : $ai_result['error'];

            // Préparation du rendu Etape 2
            ?>
            <div class="wrap">
                <h1>Vérification de l'IA (Étape 2/2)</h1>
                <p>Gemini a nettoyé votre fichier Excel et a associé les images aux produits trouvés. Vérifiez et corrigez avant validation.</p>
                
                <?php if (empty($ai_products)) : ?>
                    <div class="notice notice-error"><p>
                        <?php if ( ! empty( $api_error_msg ) ) : ?>
                            Erreur de l'IA : <strong><?php echo esc_html( $api_error_msg ); ?></strong>
                        <?php else : ?>
                            L'IA n'a pas pu extraire de données valides. Vérifiez que le fichier contient bien des titres et des prix (normal + promo).
                        <?php endif; ?>
                    </p></div>
                    <a href="?post_type=sl_campagne_woo&page=sl-cwoo-magic-import" class="button">Retour</a>
                <?php else : ?>
                <form method="post">
                    <input type="hidden" name="magic_step" value="3">
                    <?php wp_nonce_field('magic_final', 'magic_nonce'); ?>
                    <input type="hidden" name="campaign_id" value="<?php echo esc_attr( isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0 ); ?>">
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Produit Extrait (Modifiable)</th>
                                <th>Prix Nettoyés (Modifiables)</th>
                                <th>Image Associée</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ai_products as $i => $prod) : 
                                $matched_filename = $prod['filename'] ?? '';
                                $matched_att_id = 0;
                                if ( $matched_filename && isset($uploaded_images[$matched_filename]) ) {
                                    $matched_att_id = $uploaded_images[$matched_filename]['id'];
                                }
                            ?>
                            <tr>
                                <td>
                                    <input type="text" name="final_titre[<?php echo $i; ?>]" value="<?php echo esc_attr($prod['titre'] ?? ''); ?>" style="width:100%; font-weight:bold;">
                                </td>
                                <td>
                                    Avant: <input type="text" name="final_prix_avant[<?php echo $i; ?>]" value="<?php echo esc_attr($prod['prix_avant'] ?? ''); ?>" style="width:80px;"> FCFA<br><br>
                                    Promo: <input type="text" name="final_prix_apres[<?php echo $i; ?>]" value="<?php echo esc_attr($prod['prix_apres'] ?? ''); ?>" style="width:80px; font-weight:bold; color:#d63638;"> FCFA
                                </td>
                                <td>
                                    <select name="final_images[<?php echo $i; ?>]">
                                        <option value="0">-- Aucune image --</option>
                                        <?php foreach ($uploaded_images as $fname => $img) : ?>
                                            <option value="<?php echo $img['id']; ?>" <?php selected($img['id'], $matched_att_id); ?>>
                                                <?php echo esc_html($fname); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <br>
                                    <?php if ($matched_att_id) : ?>
                                        <img src="<?php echo wp_get_attachment_thumb_url($matched_att_id); ?>" style="max-height:80px; margin-top:10px; border-radius:4px; border:1px solid #ccc;">
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button button-primary button-large">Confirmer et Créer les produits</button>
                    </p>
                </form>
                <?php endif; ?>
            </div>
            <?php
            return;
        }
    }

    // Etape 1
    $campaigns = get_posts([ 'post_type' => 'sl_campagne_woo', 'posts_per_page' => -1 ]);
    ?>
    <div class="wrap">
        <h1>Import Magique par IA 🪄</h1>
        <p class="description">Fournisseur IA actif : <strong><?php echo esc_html( $ai_provider_label ); ?></strong> — modifiable dans <a href="<?php echo esc_url( admin_url('edit.php?post_type=sl_campagne_woo&page=sl-cwoo-settings-ai') ); ?>">Réglages IA</a>.</p>
        <?php if ($message) echo "<div class='notice notice-error'><p>" . esc_html($message) . "</p></div>"; ?>
        
        <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; max-width:800px; margin-top:20px;">
            <p><strong>Étape 1/2</strong> : Uploadez vos fichiers bruts. L'IA va lire et nettoyer votre fichier Excel, extraire les données pertinentes, et lier le tout aux images.</p>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="magic_step" value="2">
                <?php wp_nonce_field('magic_upload', 'magic_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th>Campagne cible</th>
                        <td>
                            <select name="campaign_id" required>
                                <option value="">-- Sélectionnez une campagne --</option>
                                <?php foreach ($campaigns as $camp) : ?>
                                    <option value="<?php echo $camp->ID; ?>"><?php echo esc_html($camp->post_title); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Fichier de données brutes<br><small>(Excel .xlsx ou CSV)</small></th>
                        <td>
                            <input type="file" name="data_file" accept=".csv, .xlsx" required>
                            <p class="description">Le fichier peut être brouillon. L'IA s'occupera d'en extraire le titre, prix avant et prix après.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Images (Vrac)</th>
                        <td>
                            <input type="file" name="images[]" accept="image/*" multiple>
                            <p class="description">Sélectionnez toutes les images des produits correspondants. L'IA s'occupera de les trier.</p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary button-large">Nettoyer avec l'IA</button>
                </p>
            </form>
        </div>
    </div>
    <?php
}
