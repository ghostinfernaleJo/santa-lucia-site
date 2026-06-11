<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   PAGE : IMPORTER DES REPAS (CSV / XLSX)
   ============================================================ */
/**
 * Telechargements (modele CSV + export) : geres sur admin_init, AVANT tout HTML.
 * Les declencher dans le callback de page produirait un fichier pollue par le
 * HTML de l'admin (headers deja envoyes).
 */
add_action( 'admin_init', 'sl_ff_handle_downloads' );
function sl_ff_handle_downloads() {
    if ( empty( $_GET['page'] ) || $_GET['page'] !== 'sl-ff-import' ) {
        return;
    }
    if ( ! current_user_can( 'sl_ff_import' ) ) {
        return;
    }
    if ( isset( $_GET['sl_ff_template'] ) && check_admin_referer( 'sl_ff_template' ) ) {
        sl_ff_download_template(); // exit interne
    }
    if ( isset( $_GET['sl_ff_template_xlsx'] ) && check_admin_referer( 'sl_ff_template_xlsx' ) ) {
        sl_ff_download_template_xlsx(); // exit interne
    }
    if ( isset( $_GET['sl_ff_export'] ) && check_admin_referer( 'sl_ff_export' ) ) {
        sl_ff_export_repas(); // exit interne
    }
}

function sl_ff_import_page() {
    if ( ! current_user_can( 'sl_ff_import' ) ) {
        wp_die( 'Acces refuse.' );
    }

    $results = null;
    $error   = '';

    // Traitement du formulaire d'import
    if ( isset( $_POST['sl_ff_do_import'] ) ) {
        check_admin_referer( 'sl_ff_import_nonce' );

        if ( empty( $_FILES['sl_ff_file']['tmp_name'] ) ) {
            $error = 'Aucun fichier recu. Veuillez selectionner un fichier.';
        } else {
            $file     = $_FILES['sl_ff_file'];
            $ext      = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
            $tmpfile  = $file['tmp_name'];

            if ( $file['error'] !== UPLOAD_ERR_OK ) {
                $error = 'Erreur lors du telechargement du fichier (code ' . $file['error'] . ').';
            } elseif ( ! in_array( $ext, [ 'csv', 'xlsx' ], true ) ) {
                $error = 'Format non supporte. Utilisez un fichier .csv ou .xlsx.';
            } else {
                if ( $ext === 'csv' ) {
                    $rows = sl_ff_csv_to_array( $tmpfile );
                } else {
                    $rows = sl_ff_xlsx_to_array( $tmpfile );
                }

                if ( $rows === false || empty( $rows ) ) {
                    $error = 'Impossible de lire le fichier. Verifiez le format.';
                } else {
                    $results = sl_ff_process_import_rows( $rows );
                }
            }
        }
    }

    // Recuperer la liste des agences pour l'aide
    $agences = get_terms( [ 'taxonomy' => 'sl_agence_promo', 'hide_empty' => false, 'orderby' => 'name' ] );
    $agences = is_wp_error( $agences ) ? [] : $agences;
    ?>
    <div class="wrap sl-ff-planning-wrap">
        <div class="sl-ff-planning-header">
            <div class="sl-ff-planning-header-left">
                <h1 class="sl-ff-planning-titre">
                    <span class="dashicons dashicons-upload"></span>
                    Importer des repas
                </h1>
                <p class="sl-ff-subtitle">
                    Importez une liste de repas depuis un fichier CSV ou Excel (.xlsx).
                    Les repas existants (meme nom + meme agence) seront mis a jour.
                </p>
            </div>
        </div>

        <!-- Resultats de l'import -->
        <?php if ( $error ) : ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
        <?php endif; ?>

        <?php if ( $results !== null ) : ?>
        <div class="sl-ff-import-results">
            <h2>Resultat de l&#39;import</h2>
            <div class="sl-ff-import-stats">
                <div class="sl-ff-stat sl-ff-stat--created">
                    <span class="sl-ff-stat-nb"><?php echo (int) $results['created']; ?></span>
                    <span class="sl-ff-stat-lbl">repas crees</span>
                </div>
                <div class="sl-ff-stat sl-ff-stat--updated">
                    <span class="sl-ff-stat-nb"><?php echo (int) $results['updated']; ?></span>
                    <span class="sl-ff-stat-lbl">repas mis a jour</span>
                </div>
                <div class="sl-ff-stat sl-ff-stat--errors">
                    <span class="sl-ff-stat-nb"><?php echo count( $results['errors'] ); ?></span>
                    <span class="sl-ff-stat-lbl">erreurs</span>
                </div>
            </div>
            <?php if ( ! empty( $results['errors'] ) ) : ?>
            <details class="sl-ff-import-errors">
                <summary>Voir les erreurs (<?php echo count( $results['errors'] ); ?>)</summary>
                <ul>
                    <?php foreach ( $results['errors'] as $e ) : ?>
                    <li><?php echo esc_html( $e ); ?></li>
                    <?php endforeach; ?>
                </ul>
            </details>
            <?php endif; ?>
            <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=sl-fastfood' ) ); ?>" class="button button-primary">
                Voir le planning &rarr;
            </a></p>
        </div>
        <?php endif; ?>

        <div class="sl-ff-import-layout">

            <!-- Colonne gauche : formulaire d'upload -->
            <div class="sl-ff-import-card">
                <h2>&#128196; Charger un fichier</h2>

                <form method="post" enctype="multipart/form-data" action="" id="sl-ff-import-form"
                      data-ajax-nonce="<?php echo esc_attr( wp_create_nonce( 'sl_ff_import_ajax' ) ); ?>"
                      data-ajaxurl="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
                    <?php wp_nonce_field( 'sl_ff_import_nonce' ); ?>
                    <div class="sl-ff-file-zone" id="sl-ff-file-zone">
                        <div class="sl-ff-file-icon">&#128228;</div>
                        <p class="sl-ff-file-hint">Glissez votre fichier ici ou cliquez pour parcourir</p>
                        <p class="sl-ff-file-formats">Formats acceptes : .csv, .xlsx</p>
                        <input type="file" name="sl_ff_file" id="sl_ff_file"
                               accept=".csv,.xlsx"
                               style="position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;">
                        <p class="sl-ff-file-name" id="sl-ff-file-name" style="display:none;"></p>
                    </div>
                    <p>
                        <button type="submit" name="sl_ff_do_import" class="button button-primary button-large"
                                style="width:100%;justify-content:center;">
                            &#10148; Lancer l&#39;import
                        </button>
                    </p>
                </form>

                <!-- Progression import par lots (AJAX) -->
                <div id="sl-ff-ajax-progress" class="sl-ff-ajax-progress" style="display:none;"></div>

                <!-- Telecharger le modele -->
                <div class="sl-ff-template-box">
                    <p><strong>Vous n&#39;avez pas de fichier ?</strong></p>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=sl-ff-import&sl_ff_template=1' ), 'sl_ff_template' ) ); ?>"
                       class="button">
                        &#11015; Telecharger le modele CSV
                    </a>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=sl-ff-import&sl_ff_template_xlsx=1' ), 'sl_ff_template_xlsx' ) ); ?>"
                       class="button" style="margin-top:6px;">
                        &#11015; Telecharger le modele Excel (.xlsx)
                    </a>
                </div>

                <!-- Exporter les repas existants -->
                <div class="sl-ff-template-box">
                    <p><strong>Sauvegarder / modifier en masse&nbsp;?</strong></p>
                    <p style="margin:0 0 8px;font-size:12px;color:#888;">
                        Exportez tous les repas actuels dans un fichier CSV (modifiable puis re-importable).
                    </p>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=sl-ff-import&sl_ff_export=1' ), 'sl_ff_export' ) ); ?>"
                       class="button button-secondary">
                        &#11015; Exporter tous les repas (CSV)
                    </a>
                </div>
            </div>

            <!-- Colonne droite : aide format -->
            <div class="sl-ff-import-card sl-ff-import-help">
                <h2>&#128220; Format attendu</h2>
                <p>Votre fichier doit contenir les colonnes suivantes (1<sup>ere</sup> ligne = en-tetes) :</p>
                <div class="sl-ff-col-table">
                    <div class="sl-ff-col-row sl-ff-col-header">
                        <span>Colonne</span><span>Valeurs acceptees</span>
                    </div>
                    <div class="sl-ff-col-row">
                        <span><strong>Nom du plat</strong></span>
                        <span>Texte libre (obligatoire)</span>
                    </div>
                    <div class="sl-ff-col-row">
                        <span><strong>Categorie</strong></span>
                        <span>
                            <?php
                            $cat_terms = get_terms( [ 'taxonomy' => 'sl_repas_cat', 'hide_empty' => false, 'orderby' => 'name' ] );
                            if ( ! is_wp_error( $cat_terms ) && $cat_terms ) {
                                echo implode( ', ', array_map( function( $t ) {
                                    return '<code>' . esc_html( sl_ff_cat_display( $t->name ) ) . '</code>';
                                }, $cat_terms ) );
                            } else {
                                echo 'Plats Traditionnels, Plats Classiques';
                            }
                            ?>
                            <br><small>Le nom affiche ou le nom reel sont acceptes.</small>
                        </span>
                    </div>
                    <div class="sl-ff-col-row">
                        <span><strong>Agence</strong></span>
                        <span>
                            Slug de l&#39;agence (ex&nbsp;: <code>akwa</code>)
                            ou <code>toutes</code> pour <strong>toutes les agences</strong>
                            (le plat sera cree dans chaque agence).
                            <?php if ( $agences ) : ?>
                            <br><small>Disponibles : <?php echo implode(', ', array_map( function($a){ return '<code>' . esc_html($a->slug) . '</code>'; }, $agences ) ); ?></small>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="sl-ff-col-row">
                        <span><strong>Lundi … Dimanche</strong></span>
                        <span><code>1</code> = disponible, <code>0</code> = non disponible</span>
                    </div>
                </div>

                <p style="margin-top:14px;"><strong>Exemple :</strong></p>
                <div class="sl-ff-csv-preview">
Nom du plat,Categorie,Agence,Lundi,Mardi,Mercredi,Jeudi,Vendredi,Samedi,Dimanche
Poulet braise,Plats Traditionnels,akwa,1,1,0,0,1,0,0
Pizza,Plats Classiques,bonaberi,1,0,1,0,1,0,0
Jus naturel,Plats Traditionnels,toutes,1,1,1,1,1,1,1</div>
            </div>

        </div>
    </div>

    <style>
    .sl-ff-import-layout { display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-top:8px; }
    .sl-ff-import-card { background:#fff; border-radius:10px; padding:24px; box-shadow:0 1px 4px rgba(0,0,0,.08); }
    .sl-ff-import-card h2 { margin:0 0 16px; font-size:16px; }
    .sl-ff-file-zone { position:relative; border:2px dashed #ddd; border-radius:10px; padding:32px 16px;
        text-align:center; background:#fafafa; transition:border-color .2s,background .2s; margin-bottom:14px; }
    .sl-ff-file-zone:hover { border-color:#e91e8c; background:#fff0f8; }
    .sl-ff-file-icon { font-size:36px; margin-bottom:8px; }
    .sl-ff-file-hint { margin:0 0 4px; font-weight:600; color:#444; }
    .sl-ff-file-formats { margin:0; font-size:12px; color:#aaa; }
    .sl-ff-file-name { margin:8px 0 0; font-size:13px; font-weight:700; color:#e91e8c; }
    .sl-ff-template-box { background:#f8f8f8; border-radius:8px; padding:14px 16px; margin-top:14px; text-align:center; }
    .sl-ff-template-box p { margin:0 0 8px; }
    .sl-ff-col-table { border:1px solid #eee; border-radius:8px; overflow:hidden; font-size:13px; }
    .sl-ff-col-row { display:grid; grid-template-columns:160px 1fr; gap:12px; padding:8px 12px; border-bottom:1px solid #f0f0f0; }
    .sl-ff-col-row:last-child { border-bottom:none; }
    .sl-ff-col-header { background:#f5f5f5; font-weight:700; font-size:11px; text-transform:uppercase; color:#666; }
    .sl-ff-csv-preview { background:#f5f5f5; border:1px solid #e0e0e0; border-radius:6px;
        padding:12px 14px; font-family:monospace; font-size:11px; white-space:pre; overflow-x:auto; }
    .sl-ff-import-results { background:#fff; border-radius:10px; padding:20px 24px; margin-bottom:20px;
        box-shadow:0 1px 4px rgba(0,0,0,.08); border-left:4px solid #4caf50; }
    .sl-ff-import-results h2 { margin:0 0 14px; font-size:16px; }
    .sl-ff-import-stats { display:flex; gap:20px; margin-bottom:14px; flex-wrap:wrap; }
    .sl-ff-stat { background:#f8f8f8; border-radius:8px; padding:12px 20px; text-align:center; min-width:100px; }
    .sl-ff-stat-nb { display:block; font-size:28px; font-weight:700; line-height:1; }
    .sl-ff-stat-lbl { display:block; font-size:12px; color:#888; margin-top:4px; }
    .sl-ff-stat--created .sl-ff-stat-nb { color:#4caf50; }
    .sl-ff-stat--updated .sl-ff-stat-nb { color:#2196f3; }
    .sl-ff-stat--errors  .sl-ff-stat-nb { color:#e53935; }
    .sl-ff-import-errors { margin-top:10px; }
    .sl-ff-import-errors ul { margin:.5em 0 0 1.5em; }
    @media (max-width:700px) { .sl-ff-import-layout { grid-template-columns:1fr; } }
    </style>

    <style>
    .sl-ff-ajax-progress { margin-top:14px; }
    .sl-ff-ajax-bar { height:14px; background:#eee; border-radius:7px; overflow:hidden; margin:8px 0; }
    .sl-ff-ajax-bar > span { display:block; height:100%; width:0; background:#e91e8c; transition:width .25s; }
    .sl-ff-ajax-progress .sl-ff-ajax-line { font-size:13px; color:#444; }
    .sl-ff-ajax-progress .sl-ff-ajax-done { color:#2e7d32; font-weight:600; }
    .sl-ff-ajax-progress .sl-ff-ajax-err  { color:#c62828; }
    .sl-ff-ajax-progress details { margin-top:6px; }
    </style>
    <script>
    (function(){
        var fileInput = document.getElementById('sl_ff_file');
        if (fileInput) {
            fileInput.addEventListener('change', function(){
                var name = this.files[0] ? this.files[0].name : '';
                var el = document.getElementById('sl-ff-file-name');
                if (name) { el.textContent = '✓ ' + name; el.style.display = ''; }
                else { el.style.display = 'none'; }
            });
        }

        var form = document.getElementById('sl-ff-import-form');
        if (!form) return;
        var ajaxurl = form.getAttribute('data-ajaxurl');
        var nonce   = form.getAttribute('data-ajax-nonce');
        var box     = document.getElementById('sl-ff-ajax-progress');
        var CHUNK   = 100;

        function esc(s){ return String(s).replace(/[&<>]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c]; }); }
        function render(pct, done, created, updated, errCount, errors){
            var html = '';
            html += '<div class="sl-ff-ajax-bar"><span style="width:'+pct+'%"></span></div>';
            html += '<p class="sl-ff-ajax-line">'+(done ? '<span class="sl-ff-ajax-done">✓ Import terminé.</span> ' : 'Import en cours… '+pct+'%')+
                    ' &mdash; <strong>'+created+'</strong> créés, <strong>'+updated+'</strong> mis à jour'+
                    (errCount ? ', <span class="sl-ff-ajax-err"><strong>'+errCount+'</strong> erreurs</span>' : '')+'.</p>';
            if (done && errors && errors.length){
                html += '<details><summary>Voir les erreurs ('+errors.length+')</summary><ul>';
                errors.slice(0,200).forEach(function(e){ html += '<li>'+esc(e)+'</li>'; });
                html += '</ul></details>';
            }
            if (done){
                html += '<p><a href="'+esc(form.getAttribute('data-planning')||'admin.php?page=sl-fastfood')+'" class="button button-primary">Voir le planning &rarr;</a></p>';
            }
            box.innerHTML = html;
        }

        form.addEventListener('submit', function(e){
            if (!fileInput || !fileInput.files || !fileInput.files.length) return; // pas de fichier : laisser le POST classique afficher l'erreur
            e.preventDefault();
            runImport(fileInput.files[0]);
        });

        async function runImport(file){
            box.style.display = 'block';
            box.innerHTML = '<p class="sl-ff-ajax-line">Préparation du fichier…</p>';
            var fd = new FormData();
            fd.append('action', 'sl_ff_import_start');
            fd.append('nonce', nonce);
            fd.append('sl_ff_file', file);
            var start;
            try { start = await fetch(ajaxurl, { method:'POST', body:fd, credentials:'same-origin' }).then(function(r){ return r.json(); }); }
            catch(err){ box.innerHTML = '<p class="sl-ff-ajax-err">Erreur réseau au démarrage.</p>'; return; }
            if (!start || !start.success){
                box.innerHTML = '<p class="sl-ff-ajax-err">'+esc(start && start.data && start.data.msg || 'Erreur au démarrage de l\'import.')+'</p>';
                return;
            }
            var job = start.data.job, total = start.data.total;
            var created = 0, updated = 0, errors = (start.data.buildErrors || []).slice();
            var offset = 0;
            render(0, false, 0, 0, errors.length, errors);

            while (offset < total){
                var fd2 = new FormData();
                fd2.append('action', 'sl_ff_import_chunk');
                fd2.append('nonce', nonce);
                fd2.append('job', job);
                fd2.append('offset', offset);
                fd2.append('size', CHUNK);
                var c;
                try { c = await fetch(ajaxurl, { method:'POST', body:fd2, credentials:'same-origin' }).then(function(r){ return r.json(); }); }
                catch(err){ box.innerHTML += '<p class="sl-ff-ajax-err">Erreur réseau (lot à '+offset+'). Réessayez.</p>'; return; }
                if (!c || !c.success){
                    box.innerHTML += '<p class="sl-ff-ajax-err">'+esc(c && c.data && c.data.msg || 'Erreur sur un lot.')+'</p>';
                    return;
                }
                created += c.data.created; updated += c.data.updated;
                if (c.data.errors && c.data.errors.length) errors = errors.concat(c.data.errors);
                offset = c.data.next;
                render(Math.round(offset / total * 100), false, created, updated, errors.length, errors);
                if (c.data.done) break;
            }
            render(100, true, created, updated, errors.length, errors);
        }
    })();
    </script>
    <?php
}

/* ============================================================
   TELECHARGER LE MODELE CSV
   ============================================================ */
function sl_ff_download_template() {
    $agences = get_terms( [ 'taxonomy' => 'sl_agence_promo', 'hide_empty' => false, 'orderby' => 'name' ] );
    $ex_agence = ( ! is_wp_error( $agences ) && ! empty( $agences ) ) ? $agences[0]->slug : 'akwa';

    while ( ob_get_level() > 0 ) { ob_end_clean(); }
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="modele-repas-fastfood.csv"' );
    header( 'Cache-Control: no-cache' );

    $out = fopen( 'php://output', 'w' );
    // BOM UTF-8 pour Excel
    fputs( $out, "\xEF\xBB\xBF" );

    fputcsv( $out, [ 'Nom du plat', 'Categorie', 'Agence', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche' ] );
    fputcsv( $out, [ 'Poulet braise', 'Plats Traditionnels', $ex_agence, 1, 1, 0, 0, 1, 0, 0 ] );
    fputcsv( $out, [ 'Pizza', 'Plats Classiques', $ex_agence, 1, 0, 1, 0, 1, 0, 0 ] );
    fputcsv( $out, [ 'Jus naturel', 'Plats Traditionnels', 'toutes', 1, 1, 1, 1, 1, 1, 1 ] );
    fclose( $out );
    exit;
}

/* ============================================================
   TELECHARGER LE MODELE EXCEL (.xlsx)
   Genere un vrai fichier OOXML minimal (sans librairie) via ZipArchive.
   Cellules texte en inlineStr, jours en nombres -> relisible par notre
   propre parseur sl_ff_xlsx_to_array et ouvrable par Excel/LibreOffice.
   ============================================================ */
function sl_ff_xml_esc( $s ) {
    return htmlspecialchars( (string) $s, ENT_QUOTES | ENT_XML1, 'UTF-8' );
}

function sl_ff_download_template_xlsx() {
    if ( ! class_exists( 'ZipArchive' ) ) {
        // Repli : si ZipArchive indisponible, servir le CSV a la place.
        sl_ff_download_template();
        return;
    }

    $agences   = get_terms( [ 'taxonomy' => 'sl_agence_promo', 'hide_empty' => false, 'orderby' => 'name' ] );
    $ex_agence = ( ! is_wp_error( $agences ) && ! empty( $agences ) ) ? $agences[0]->slug : 'akwa';

    $rows = [
        [ 'Nom du plat', 'Categorie', 'Agence', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche' ],
        [ 'Poulet braise', 'Plats Traditionnels', $ex_agence, 1, 1, 0, 0, 1, 0, 0 ],
        [ 'Pizza', 'Plats Classiques', $ex_agence, 1, 0, 1, 0, 1, 0, 0 ],
        [ 'Jus naturel', 'Plats Traditionnels', 'toutes', 1, 1, 1, 1, 1, 1, 1 ],
    ];

    // Lettres de colonnes A..J
    $letters = range( 'A', 'Z' );

    // Construire la feuille
    $sheet  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $sheet .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
    foreach ( $rows as $ri => $row ) {
        $r = $ri + 1;
        $sheet .= '<row r="' . $r . '">';
        $ci = 0;
        foreach ( array_values( $row ) as $val ) {
            $ref = $letters[ $ci ] . $r;
            if ( is_int( $val ) || ( is_string( $val ) && preg_match( '/^-?\d+$/', $val ) ) ) {
                $sheet .= '<c r="' . $ref . '"><v>' . (int) $val . '</v></c>';
            } else {
                $sheet .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . sl_ff_xml_esc( $val ) . '</t></is></c>';
            }
            $ci++;
        }
        $sheet .= '</row>';
    }
    $sheet .= '</sheetData></worksheet>';

    $content_types =
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
      . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
      . '<Default Extension="xml" ContentType="application/xml"/>'
      . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
      . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
      . '</Types>';

    $rels =
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
      . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
      . '</Relationships>';

    $workbook =
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
      . '<sheets><sheet name="Repas" sheetId="1" r:id="rId1"/></sheets>'
      . '</workbook>';

    $wb_rels =
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
      . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
      . '</Relationships>';

    $tmp = tempnam( sys_get_temp_dir(), 'slffxlsx' );
    $zip = new ZipArchive();
    if ( $zip->open( $tmp, ZipArchive::OVERWRITE ) !== true ) {
        @unlink( $tmp );
        sl_ff_download_template(); // repli CSV
        return;
    }
    $zip->addFromString( '[Content_Types].xml', $content_types );
    $zip->addFromString( '_rels/.rels', $rels );
    $zip->addFromString( 'xl/workbook.xml', $workbook );
    $zip->addFromString( 'xl/_rels/workbook.xml.rels', $wb_rels );
    $zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet );
    $zip->close();

    // Vider tout buffer de sortie (un fichier inclus peut emettre un BOM/espace
    // parasite qui corromprait le binaire .xlsx).
    while ( ob_get_level() > 0 ) { ob_end_clean(); }

    header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
    header( 'Content-Disposition: attachment; filename="modele-repas-fastfood.xlsx"' );
    header( 'Content-Length: ' . filesize( $tmp ) );
    header( 'Cache-Control: no-cache' );
    readfile( $tmp );
    @unlink( $tmp );
    exit;
}

/* ============================================================
   PARSEURS : CSV ET XLSX
   ============================================================ */

/**
 * Convertit un fichier CSV en tableau 2D.
 * Auto-detecte le separateur (, ou ;).
 */
function sl_ff_csv_to_array( $filepath ) {
    $handle = @fopen( $filepath, 'r' );
    if ( ! $handle ) return false;

    // Detecter separateur
    $first = fgets( $handle );
    rewind( $handle );
    $sep = ( substr_count( $first, ';' ) > substr_count( $first, ',' ) ) ? ';' : ',';

    $rows = [];
    while ( ( $data = fgetcsv( $handle, 2000, $sep ) ) !== false ) {
        // Ignorer lignes vraiment vides
        if ( count( array_filter( $data, 'strlen' ) ) === 0 ) continue;
        $rows[] = array_map( 'trim', $data );
    }
    fclose( $handle );
    return $rows;
}

/**
 * Convertit un fichier XLSX en tableau 2D.
 * Necessite l'extension ZipArchive (active par defaut sur la plupart des hebergements).
 */
function sl_ff_xlsx_to_array( $filepath ) {
    if ( ! class_exists( 'ZipArchive' ) ) return false;

    $zip = new ZipArchive();
    if ( $zip->open( $filepath ) !== true ) return false;

    // Chaines partagees
    $shared = [];
    $ss = $zip->getFromName( 'xl/sharedStrings.xml' );
    if ( $ss !== false ) {
        $xml = @simplexml_load_string( $ss );
        if ( $xml ) {
            foreach ( $xml->si as $si ) {
                if ( isset( $si->t ) ) {
                    $shared[] = (string) $si->t;
                } else {
                    $text = '';
                    if ( isset( $si->r ) ) {
                        foreach ( $si->r as $r ) {
                            $text .= (string) $r->t;
                        }
                    }
                    $shared[] = $text;
                }
            }
        }
    }

    // Feuille 1 — avec repli si elle n'est pas nommee sheet1.xml (selon le logiciel)
    $sheet = $zip->getFromName( 'xl/worksheets/sheet1.xml' );
    if ( $sheet === false ) {
        for ( $i = 0; $i < $zip->numFiles; $i++ ) {
            $name = $zip->getNameIndex( $i );
            if ( $name && strpos( $name, 'xl/worksheets/sheet' ) === 0 && substr( $name, -4 ) === '.xml' ) {
                $sheet = $zip->getFromName( $name );
                break;
            }
        }
    }
    $zip->close();
    if ( $sheet === false ) return false;

    $xml = @simplexml_load_string( $sheet );
    if ( ! $xml || ! isset( $xml->sheetData ) ) return false;

    $rows = [];
    foreach ( $xml->sheetData->row as $row ) {
        $row_data = [];
        $prev_col = 0;

        foreach ( $row->c as $cell ) {
            // Calculer l'indice de colonne a partir de la reference (ex: A1, B2)
            $ref = (string) $cell['r'];
            preg_match( '/([A-Z]+)/', $ref, $m );
            $col_str = $m[1] ?? 'A';
            $col_idx = 0;
            for ( $i = 0; $i < strlen( $col_str ); $i++ ) {
                $col_idx = $col_idx * 26 + ( ord( $col_str[$i] ) - 64 );
            }
            $col_idx--; // 0-base

            // Combler les cases vides
            while ( $prev_col < $col_idx ) {
                $row_data[] = '';
                $prev_col++;
            }

            $type = (string) $cell['t'];
            if ( $type === 's' ) {
                $row_data[] = $shared[ (int) $cell->v ] ?? '';
            } elseif ( $type === 'inlineStr' ) {
                $row_data[] = (string) ( $cell->is->t ?? '' );
            } else {
                $row_data[] = (string) ( $cell->v ?? '' );
            }
            $prev_col++;
        }

        if ( count( array_filter( $row_data, 'strlen' ) ) > 0 ) {
            $rows[] = $row_data;
        }
    }
    return $rows;
}

/* ============================================================
   TRAITEMENT DE L'IMPORT
   ============================================================ */
function sl_ff_process_import_rows( $rows ) {
    // Chemin synchrone (POST classique, sans JS) : construit les unites puis upsert.
    // Adapte aux PETITS fichiers. Les gros volumes passent par l'import AJAX par lots.
    $built   = sl_ff_build_units( $rows );
    $created = 0;
    $updated = 0;
    $errors  = $built['errors'];
    foreach ( $built['units'] as $u ) {
        sl_ff_upsert_unit( $u, $created, $updated, $errors );
    }
    return compact( 'created', 'updated', 'errors' );
}

/* ============================================================
   CONSTRUCTION DES UNITES D'IMPORT
   Transforme les lignes brutes en une liste plate d'unites
   [ 'nom', 'agence', 'jours', 'term_id' ] — une par (plat, agence).
   - "toutes" => expansion sur chaque agence.
   - categories resolues/creees UNE seule fois (cache).
   - dedoublonnage intra-fichier (meme nom+agence => derniere ligne gagne).
   ============================================================ */
function sl_ff_build_units( $rows ) {
    $units  = [];
    $errors = [];
    if ( ! is_array( $rows ) || count( $rows ) < 2 ) {
        return [ 'units' => [], 'errors' => [ 'Fichier trop court (pas de donnees apres l\'en-tete).' ] ];
    }

    $jours_all = [ 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche' ];

    $all_agence_terms = get_terms( [ 'taxonomy' => 'sl_agence_promo', 'hide_empty' => false, 'orderby' => 'name' ] );
    $all_agence_slugs = ( ! is_wp_error( $all_agence_terms ) )
        ? array_map( function( $t ) { return sanitize_title( $t->slug ); }, $all_agence_terms )
        : [];
    $all_agence_keywords = [ 'toutes', 'toute', 'tous', 'toutes les agences', 'toutes-les-agences', 'all', 'all_agencies', '__all_agencies', '*' ];

    // Cache categories : nom normalise => term_id (resolution + creation une seule fois)
    $cat_cache = [];
    $all_terms = get_terms( [ 'taxonomy' => 'sl_repas_cat', 'hide_empty' => false ] );
    $all_terms = is_wp_error( $all_terms ) ? [] : $all_terms;

    $header = array_map( 'sl_ff_norm_header', array_shift( $rows ) );
    $col = [
        'nom'    => array_search( 'nom du plat', $header, true ),
        'cat'    => array_search( 'categorie', $header, true ),
        'agence' => array_search( 'agence', $header, true ),
    ];
    $col_jours = [];
    foreach ( $jours_all as $j ) {
        $col_jours[] = array_search( $j, $header, true );
    }
    $positional = ( $col['nom'] === false );

    $seen = []; // "nom_lc|agence" => index dans $units (dedoublonnage intra-fichier)

    foreach ( $rows as $i => $row ) {
        if ( count( array_filter( $row, 'strlen' ) ) === 0 ) continue;
        $line = $i + 2;

        if ( $positional ) {
            $nom    = trim( $row[0] ?? '' );
            $cat    = trim( $row[1] ?? '' );
            $agence = mb_strtolower( trim( $row[2] ?? '' ), 'UTF-8' );
            $days   = array_slice( $row, 3, 7 );
        } else {
            $nom    = trim( $row[ $col['nom'] ] ?? '' );
            $cat    = trim( $row[ $col['cat'] ] ?? '' );
            $agence = mb_strtolower( trim( $row[ $col['agence'] ] ?? '' ), 'UTF-8' );
            $days   = [];
            foreach ( $col_jours as $cidx ) {
                $days[] = $cidx !== false ? trim( $row[ $cidx ] ?? '0' ) : '0';
            }
        }

        if ( $nom === '' ) {
            $errors[] = "Ligne $line : nom du plat manquant.";
            continue;
        }

        // Jours
        $jours = [];
        foreach ( $jours_all as $j => $jour_slug ) {
            $v = mb_strtolower( trim( $days[ $j ] ?? '' ), 'UTF-8' );
            if ( in_array( $v, [ '1', 'oui', 'yes', 'x', 'vrai', 'true' ], true ) ) {
                $jours[] = $jour_slug;
            }
        }

        // Categorie (resolue une seule fois grace au cache)
        $term_id = null;
        if ( $cat !== '' ) {
            $cat_norm = sl_ff_norm_header( $cat );
            if ( array_key_exists( $cat_norm, $cat_cache ) ) {
                $term_id = $cat_cache[ $cat_norm ];
            } else {
                foreach ( $all_terms as $t ) {
                    if ( in_array( $cat_norm, [ sl_ff_norm_header( $t->name ), sl_ff_norm_header( sl_ff_cat_display( $t->name ) ) ], true ) ) {
                        $term_id = (int) $t->term_id;
                        break;
                    }
                }
                if ( ! $term_id ) {
                    $res = wp_insert_term( $cat, 'sl_repas_cat' );
                    if ( ! is_wp_error( $res ) ) {
                        $term_id = (int) $res['term_id'];
                        $nt = get_term( $term_id, 'sl_repas_cat' );
                        if ( $nt && ! is_wp_error( $nt ) ) { $all_terms[] = $nt; }
                    }
                }
                $cat_cache[ $cat_norm ] = $term_id;
            }
        }

        // Agence(s) cible(s)
        if ( in_array( $agence, $all_agence_keywords, true ) ) {
            $targets = $all_agence_slugs;
            if ( empty( $targets ) ) {
                $errors[] = "Ligne $line : aucune agence disponible pour \"toutes les agences\".";
                continue;
            }
        } else {
            $targets = [ $agence ];
        }

        foreach ( $targets as $ta ) {
            $key  = mb_strtolower( $nom, 'UTF-8' ) . '|' . $ta;
            $unit = [ 'nom' => $nom, 'agence' => $ta, 'jours' => $jours, 'term_id' => $term_id ];
            if ( isset( $seen[ $key ] ) ) {
                $units[ $seen[ $key ] ] = $unit; // doublon intra-fichier : derniere ligne gagne
            } else {
                $seen[ $key ] = count( $units );
                $units[]      = $unit;
            }
        }
    }

    return [ 'units' => $units, 'errors' => $errors ];
}

/* ============================================================
   UPSERT D'UNE UNITE (anti-doublon FIABLE)
   Correspondance par TITRE EXACT + meme agence (parametre 'title' de
   WP_Query), au lieu de l'ancienne recherche floue 's' qui pouvait
   rater une correspondance et donc creer des doublons.
   ============================================================ */
function sl_ff_upsert_unit( $unit, &$created, &$updated, &$errors ) {
    $nom     = $unit['nom'];
    $agence  = $unit['agence'];
    $jours   = $unit['jours'];
    $term_id = $unit['term_id'];

    $meta_q = $agence ? [ [ 'key' => '_sl_ff_agence', 'value' => $agence ] ] : [];
    // Ignorer les doublons fusionnés dans un post partagé (migration) :
    // les re-publier recréerait les doublons que la fusion a éliminés.
    $meta_q[] = [ 'key' => '_sl_ff_merged_into', 'compare' => 'NOT EXISTS' ];
    $existing = get_posts( [
        'post_type'              => 'sl_repas',
        'post_status'            => [ 'publish', 'draft', 'pending', 'private' ],
        'posts_per_page'         => 1,
        'fields'                 => 'ids',
        'title'                  => $nom,   // correspondance EXACTE du titre
        'meta_query'             => $meta_q,
        'no_found_rows'          => true,
        'update_post_term_cache' => false,
        'update_post_meta_cache' => false,
        'suppress_filters'       => true,
    ] );
    $post_id = ! empty( $existing ) ? (int) $existing[0] : 0;

    if ( $post_id ) {
        wp_update_post( [ 'ID' => $post_id, 'post_status' => 'publish' ] );
        $updated++;
    } else {
        $post_id = wp_insert_post( [
            'post_type'   => 'sl_repas',
            'post_title'  => $nom,
            'post_status' => 'publish',
        ], true );
        if ( is_wp_error( $post_id ) ) {
            $errors[] = $nom . ' (' . ( $agence ?: 'sans agence' ) . ') : ' . $post_id->get_error_message();
            return;
        }
        $created++;
    }

    if ( $agence ) {
        // Non destructif : un post peut être multi-agences (Disponibilité multi-agences).
        // update_post_meta écraserait TOUTES les lignes → on n'ajoute que si absente.
        $ag_rows = get_post_meta( $post_id, '_sl_ff_agence' );
        if ( ! in_array( $agence, (array) $ag_rows, true ) ) {
            if ( empty( $ag_rows ) ) {
                update_post_meta( $post_id, '_sl_ff_agence', $agence );
            } else {
                add_post_meta( $post_id, '_sl_ff_agence', $agence );
            }
        }
    }
    update_post_meta( $post_id, '_sl_ff_jours', $jours );
    if ( $term_id ) wp_set_post_terms( $post_id, [ $term_id ], 'sl_repas_cat' );
}

/* ============================================================
   IMPORT PAR LOTS (AJAX) — pour gros volumes (2000+ repas)
   sl_ff_import_start : parse le fichier, construit les unites, les
   stocke dans un transient, renvoie le total.
   sl_ff_import_chunk : traite un lot d'unites par offset.
   ============================================================ */
add_action( 'wp_ajax_sl_ff_import_start', 'sl_ff_ajax_import_start' );
function sl_ff_ajax_import_start() {
    if ( ! current_user_can( 'sl_ff_import' ) ) {
        wp_send_json_error( [ 'msg' => 'Acces refuse.' ], 403 );
    }
    check_ajax_referer( 'sl_ff_import_ajax', 'nonce' );

    if ( empty( $_FILES['sl_ff_file']['tmp_name'] ) ) {
        wp_send_json_error( [ 'msg' => 'Aucun fichier recu.' ] );
    }
    $file = $_FILES['sl_ff_file'];
    if ( $file['error'] !== UPLOAD_ERR_OK ) {
        wp_send_json_error( [ 'msg' => 'Erreur lors du telechargement (code ' . (int) $file['error'] . ').' ] );
    }
    $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
    if ( ! in_array( $ext, [ 'csv', 'xlsx' ], true ) ) {
        wp_send_json_error( [ 'msg' => 'Format non supporte (.csv ou .xlsx).' ] );
    }

    $rows = ( $ext === 'csv' ) ? sl_ff_csv_to_array( $file['tmp_name'] ) : sl_ff_xlsx_to_array( $file['tmp_name'] );
    if ( $rows === false || count( $rows ) < 2 ) {
        wp_send_json_error( [ 'msg' => 'Impossible de lire le fichier ou fichier vide.' ] );
    }

    $built = sl_ff_build_units( $rows );
    if ( empty( $built['units'] ) ) {
        wp_send_json_error( [ 'msg' => 'Aucune ligne valide. ' . implode( ' | ', array_slice( $built['errors'], 0, 5 ) ) ] );
    }

    $job = 'sl_ff_imp_' . wp_generate_password( 12, false );
    set_transient( $job, $built['units'], HOUR_IN_SECONDS );

    wp_send_json_success( [
        'job'         => $job,
        'total'       => count( $built['units'] ),
        'buildErrors' => $built['errors'],
    ] );
}

add_action( 'wp_ajax_sl_ff_import_chunk', 'sl_ff_ajax_import_chunk' );
function sl_ff_ajax_import_chunk() {
    if ( ! current_user_can( 'sl_ff_import' ) ) {
        wp_send_json_error( [ 'msg' => 'Acces refuse.' ], 403 );
    }
    check_ajax_referer( 'sl_ff_import_ajax', 'nonce' );

    $job    = isset( $_POST['job'] ) ? sanitize_text_field( wp_unslash( $_POST['job'] ) ) : '';
    $offset = isset( $_POST['offset'] ) ? max( 0, (int) $_POST['offset'] ) : 0;
    $size   = isset( $_POST['size'] ) ? min( 200, max( 1, (int) $_POST['size'] ) ) : 100;

    if ( strpos( $job, 'sl_ff_imp_' ) !== 0 ) {
        wp_send_json_error( [ 'msg' => 'Job invalide.' ] );
    }
    $units = get_transient( $job );
    if ( $units === false ) {
        wp_send_json_error( [ 'msg' => 'Session expiree, relancez l\'import.' ] );
    }

    $total   = count( $units );
    $created = 0;
    $updated = 0;
    $errors  = [];

    $slice = array_slice( $units, $offset, $size );
    wp_defer_term_counting( true );
    foreach ( $slice as $u ) {
        sl_ff_upsert_unit( $u, $created, $updated, $errors );
    }
    wp_defer_term_counting( false );

    $next = $offset + count( $slice );
    $done = ( $next >= $total );
    if ( $done ) {
        delete_transient( $job );
    }

    wp_send_json_success( [
        'created' => $created,
        'updated' => $updated,
        'errors'  => $errors,
        'next'    => $next,
        'total'   => $total,
        'done'    => $done,
    ] );
}

/* ============================================================
   HELPER : normaliser un en-tete / libelle (casse + accents)
   ============================================================ */
function sl_ff_norm_header( $h ) {
    $h = mb_strtolower( trim( (string) $h ), 'UTF-8' );
    $h = strtr( $h, [
        'á'=>'a','à'=>'a','â'=>'a','ä'=>'a','ã'=>'a',
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
        'ó'=>'o','ò'=>'o','ô'=>'o','ö'=>'o','õ'=>'o',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
        'ç'=>'c','ñ'=>'n',
    ] );
    return $h;
}

/* ============================================================
   EXPORT : tous les repas vers CSV (re-importable)
   Colonnes identiques au modele d'import. La categorie est ecrite
   avec son nom AFFICHE (sl_ff_cat_display) ; l'import accepte les deux.
   ============================================================ */
function sl_ff_export_repas() {
    $posts = get_posts( [
        'post_type'      => 'sl_repas',
        'post_status'    => [ 'publish', 'draft' ],
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ] );

    while ( ob_get_level() > 0 ) { ob_end_clean(); }
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="repas-fastfood-' . date( 'Y-m-d' ) . '.csv"' );
    header( 'Cache-Control: no-cache' );

    $out = fopen( 'php://output', 'w' );
    // BOM UTF-8 pour Excel
    fputs( $out, "\xEF\xBB\xBF" );

    $jours_all  = [ 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche' ];
    $jours_lbl  = [ 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche' ];

    fputcsv( $out, array_merge( [ 'Nom du plat', 'Categorie', 'Agence' ], $jours_lbl ) );

    foreach ( $posts as $p ) {
        $terms  = get_the_terms( $p->ID, 'sl_repas_cat' );
        $cat    = ( $terms && ! is_wp_error( $terms ) ) ? sl_ff_cat_display( $terms[0]->name ) : '';
        $jours  = (array) get_post_meta( $p->ID, '_sl_ff_jours', true );

        // Multi-agences : une ligne par agence du post (export ré-importable)
        $agences_rows = (array) get_post_meta( $p->ID, '_sl_ff_agence' );
        if ( empty( $agences_rows ) ) $agences_rows = [ '' ];

        foreach ( $agences_rows as $agence ) {
            $row = [ $p->post_title, $cat, (string) $agence ];
            foreach ( $jours_all as $j ) {
                $row[] = in_array( $j, $jours, true ) ? '1' : '0';
            }
            fputcsv( $out, $row );
        }
    }
    fclose( $out );
    exit;
}
