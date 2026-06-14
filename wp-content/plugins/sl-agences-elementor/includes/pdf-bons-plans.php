<?php
/**
 * PDF des Bons Plans par agence — Santa Lucia
 * Endpoint public : /?sl_bp_pdf=1[&agence=slug1,slug2]
 * Mêmes règles d'affichage que le widget (offres actives, stock épuisé masqué).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'template_redirect', 'sl_bp_pdf_maybe_render', 1 );
function sl_bp_pdf_maybe_render() {
    if ( ! isset( $_GET['sl_bp_pdf'] ) ) {
        return;
    }

    // Anti-abus léger : 10 PDF / 5 min / IP
    $ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
    $key = 'slbp_pdf_' . md5( $ip );
    $n   = (int) get_transient( $key );
    if ( $n >= 10 ) {
        status_header( 429 );
        exit( 'Trop de demandes. Merci de réessayer dans quelques minutes.' );
    }
    set_transient( $key, $n + 1, 5 * MINUTE_IN_SECONDS );

    $agences_param = isset( $_GET['agence'] ) ? sanitize_text_field( wp_unslash( $_GET['agence'] ) ) : '';
    $slugs = array_values( array_filter( array_map( 'sanitize_title', explode( ',', $agences_param ) ) ) );

    sl_bp_pdf_render( $slugs );
    exit;
}

/** Texte UTF-8 → Windows-1252 (jeu de caractères des polices core FPDF). */
function sl_bp_pdf_txt( $s ) {
    $out = @iconv( 'UTF-8', 'windows-1252//TRANSLIT//IGNORE', (string) $s );
    return $out !== false ? $out : preg_replace( '/[^\x20-\x7E]/', '?', (string) $s );
}

/** Prix formaté FCFA. */
function sl_bp_pdf_prix( $v ) {
    return number_format( (float) $v, 0, ',', ' ' ) . ' FCFA';
}

/**
 * Chemin local d'une miniature utilisable par FPDF (jpg/png).
 * Les webp (Image Optimizer) sont convertis en JPEG temporaire via GD.
 * Retourne '' si pas d'image exploitable.
 */
function sl_bp_pdf_image_path( $post_id ) {
    $att_id = get_post_thumbnail_id( $post_id );
    if ( ! $att_id ) return '';

    $path = '';
    $info = image_get_intermediate_size( $att_id, 'thumbnail' );
    if ( $info && ! empty( $info['file'] ) ) {
        $full = get_attached_file( $att_id );
        if ( $full ) $path = path_join( dirname( $full ), $info['file'] );
    }
    if ( ! $path || ! file_exists( $path ) ) {
        $path = get_attached_file( $att_id );
    }
    if ( ! $path || ! file_exists( $path ) ) return '';

    $ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
    if ( in_array( $ext, [ 'jpg', 'jpeg', 'png' ], true ) ) {
        return $path;
    }

    // webp (ou autre) → JPEG temporaire via GD
    if ( $ext === 'webp' && function_exists( 'imagecreatefromwebp' ) ) {
        $img = @imagecreatefromwebp( $path );
        if ( $img ) {
            $tmp = wp_tempnam( 'slbp-pdf-' . $att_id . '.jpg' );
            if ( $tmp && @imagejpeg( $img, $tmp, 82 ) ) {
                imagedestroy( $img );
                return $tmp; // l'appelant le sait temporaire via préfixe slbp-pdf
            }
            imagedestroy( $img );
        }
    }
    return '';
}

/**
 * Dérivés JPEG du logo pour le PDF (FPDF ne gère ni l'alpha PNG ni l'opacité) :
 *  - header    : logo aplati sur fond blanc
 *  - watermark : logo fondu à ~9% sur fond blanc (filigrane)
 * Générés via GD une seule fois et mis en cache dans uploads/slbp-pdf/.
 * Retourne [ 'header' => path|'', 'wm' => path|'', 'ratio' => h/w ].
 */
function sl_bp_pdf_logo_variants() {
    $out = [ 'header' => '', 'wm' => '', 'ratio' => 0.24 ];

    $up = wp_get_upload_dir();
    $candidates = [];
    $logo_id = (int) get_theme_mod( 'custom_logo' );
    if ( $logo_id ) {
        $f = get_attached_file( $logo_id );
        if ( $f ) $candidates[] = $f;
    }
    $candidates[] = path_join( $up['basedir'], '2024/06/logo-santa-1.png' );

    $src = '';
    foreach ( $candidates as $c ) {
        if ( $c && file_exists( $c ) ) { $src = $c; break; }
    }
    if ( ! $src || ! function_exists( 'imagecreatetruecolor' ) ) return $out;

    $dir = path_join( $up['basedir'], 'slbp-pdf' );
    if ( ! wp_mkdir_p( $dir ) ) return $out;
    // En-tête : BANDEAU COMPLET généré en dégradé bleu Santa Lucia → magenta, avec le
    // logo (alpha) composé directement dessus → transparence parfaite (FPDF ne gère
    // ni l'alpha ni les dégradés, on fabrique donc l'image finale via GD).
    $h_path = $dir . '/band-header.jpg';
    $w_path = $dir . '/logo-watermark.jpg';

    $fresh = file_exists( $h_path ) && file_exists( $w_path )
          && filemtime( $h_path ) >= filemtime( $src );

    if ( ! $fresh ) {
        $ext = strtolower( pathinfo( $src, PATHINFO_EXTENSION ) );
        $img = false;
        if ( $ext === 'png' )                                    $img = @imagecreatefrompng( $src );
        elseif ( in_array( $ext, [ 'jpg', 'jpeg' ], true ) )     $img = @imagecreatefromjpeg( $src );
        elseif ( $ext === 'webp' && function_exists( 'imagecreatefromwebp' ) ) $img = @imagecreatefromwebp( $src );
        if ( ! $img ) return $out;

        $w = imagesx( $img );
        $h = imagesy( $img );

        // 1) Bandeau 210x26mm à 10px/mm : dégradé horizontal bleu (29,84,160) → magenta (233,30,99)
        $bw = 2100; $bh = 260;
        $band = imagecreatetruecolor( $bw, $bh );
        $blue = [ 29, 84, 160 ];
        $mag  = [ 233, 30, 99 ];
        for ( $x = 0; $x < $bw; $x++ ) {
            $t = $x / ( $bw - 1 );
            $col = imagecolorallocate( $band,
                (int) round( $blue[0] + ( $mag[0] - $blue[0] ) * $t ),
                (int) round( $blue[1] + ( $mag[1] - $blue[1] ) * $t ),
                (int) round( $blue[2] + ( $mag[2] - $blue[2] ) * $t )
            );
            imageline( $band, $x, 0, $x, $bh, $col );
        }
        // Logo composé sur le dégradé (46mm de large à gauche, centré verticalement)
        $lw = 460;
        $lh = (int) round( $lw * $h / max( 1, $w ) );
        if ( $lh > 200 ) { $lh = 200; $lw = (int) round( $lh * $w / max( 1, $h ) ); }
        imagecopyresampled( $band, $img, 100, (int) ( ( $bh - $lh ) / 2 ), 0, 0, $lw, $lh, $w, $h );
        @imagejpeg( $band, $h_path, 90 );
        imagedestroy( $band );

        // 2) Filigrane : aplati sur blanc puis fondu à 9% vers le blanc
        $flat  = imagecreatetruecolor( $w, $h );
        $white = imagecolorallocate( $flat, 255, 255, 255 );
        imagefill( $flat, 0, 0, $white );
        imagecopy( $flat, $img, 0, 0, 0, 0, $w, $h );
        $wm = imagecreatetruecolor( $w, $h );
        $white2 = imagecolorallocate( $wm, 255, 255, 255 );
        imagefill( $wm, 0, 0, $white2 );
        imagecopymerge( $wm, $flat, 0, 0, 0, 0, $w, $h, 9 );
        @imagejpeg( $wm, $w_path, 88 );

        imagedestroy( $img );
        imagedestroy( $flat );
        imagedestroy( $wm );
    }

    if ( file_exists( $h_path ) ) $out['header'] = $h_path;
    if ( file_exists( $w_path ) ) $out['wm'] = $w_path;
    $size = @getimagesize( $src );
    if ( $size && $size[0] > 0 ) $out['ratio'] = $size[1] / $size[0];
    return $out;
}

function sl_bp_pdf_render( $slugs ) {
    require_once SL_AGENCES_PATH . 'lib/fpdf/fpdf.php';

    // FPDF appelle Header() à chaque AddPage : on y dessine le filigrane
    // AVANT le contenu de la page (le contenu opaque passe par-dessus).
    if ( ! class_exists( 'SL_BP_PDF' ) ) {
        class SL_BP_PDF extends FPDF {
            public $wm_path  = '';
            public $wm_ratio = 0.24;
            function Header() {
                if ( ! $this->wm_path ) return;
                $w = 170;
                $h = $w * $this->wm_ratio;
                $this->Image( $this->wm_path, ( 210 - $w ) / 2, ( 297 - $h ) / 2, $w, $h, 'JPG' );
            }
        }
    }

    // Vider TOUS les buffers de sortie : un fichier de la pile (plugin/thème) émet un
    // BOM UTF-8 qui sinon précède %PDF et corrompt le fichier pour les lecteurs stricts.
    while ( ob_get_level() > 0 ) {
        @ob_end_clean();
    }

    $today = current_time( 'Y-m-d' );

    $args = [
        'post_type'      => 'sl_bon_plan',
        'post_status'    => 'publish',
        'posts_per_page' => 200,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_query'     => [
            'relation' => 'OR',
            [ 'key' => '_sl_bp_date_fin', 'value' => $today, 'compare' => '>=', 'type' => 'DATE' ],
            [ 'key' => '_sl_bp_date_fin', 'value' => '', 'compare' => '=' ],
            [ 'key' => '_sl_bp_date_fin', 'compare' => 'NOT EXISTS' ],
        ],
    ];
    if ( ! empty( $slugs ) ) {
        $args['tax_query'] = [ [
            'taxonomy' => 'sl_agence_promo',
            'field'    => 'slug',
            'terms'    => $slugs,
        ] ];
    }
    $posts = get_posts( $args );

    // Libellé agences pour l'en-tête
    $agence_label = 'Toutes les agences';
    if ( ! empty( $slugs ) ) {
        $names = [];
        foreach ( $slugs as $slug ) {
            $t = get_term_by( 'slug', $slug, 'sl_agence_promo' );
            if ( $t && ! is_wp_error( $t ) ) $names[] = $t->name;
        }
        if ( $names ) $agence_label = implode( ', ', $names );
    }

    $magenta = [ 233, 30, 99 ];
    $dark    = [ 33, 37, 41 ];
    $muted   = [ 120, 124, 130 ];

    $logo = sl_bp_pdf_logo_variants();

    $pdf = new SL_BP_PDF( 'P', 'mm', 'A4' );
    $pdf->wm_path  = $logo['wm'];
    $pdf->wm_ratio = $logo['ratio'];
    $pdf->SetAutoPageBreak( true, 18 );
    $pdf->SetTitle( sl_bp_pdf_txt( 'Bons Plans Santa Lucia — ' . $agence_label ) );
    $pdf->SetAuthor( 'Complexe Santa Lucia' );
    $pdf->AddPage();

    // ── En-tête bandeau : image dégradé bleu→magenta avec logo intégré
    // (le Rect magenta sert de secours si GD/le logo manquent)
    $pdf->SetFillColor( $magenta[0], $magenta[1], $magenta[2] );
    $pdf->Rect( 0, 0, 210, 26, 'F' );

    $tx = 10; // x du texte si pas de bandeau-image
    if ( $logo['header'] ) {
        try {
            $pdf->Image( $logo['header'], 0, 0, 210, 26, 'JPG' );
            $tx = 62; // après le logo (46mm à x=10) + marge
        } catch ( \Throwable $e ) { /* bandeau de secours conservé */ }
    }

    $pdf->SetTextColor( 255, 255, 255 );
    $pdf->SetFont( 'Helvetica', 'B', 15 );
    $pdf->SetXY( $tx, 6 );
    $pdf->Cell( 0, 8, sl_bp_pdf_txt( 'COMPLEXE SANTA LUCIA — BONS PLANS' ), 0, 1 );
    $pdf->SetFont( 'Helvetica', '', 10 );
    $pdf->SetX( $tx );
    $pdf->Cell( 0, 6, sl_bp_pdf_txt( 'Agence(s) : ' . $agence_label . '   •   Édité le ' . date_i18n( 'd/m/Y' ) ), 0, 1 );
    $pdf->SetY( 32 );

    if ( empty( $posts ) ) {
        $pdf->SetTextColor( $dark[0], $dark[1], $dark[2] );
        $pdf->SetFont( 'Helvetica', '', 12 );
        $pdf->Cell( 0, 10, sl_bp_pdf_txt( 'Aucun bon plan actif pour cette sélection actuellement.' ), 0, 1 );
    }

    $tmp_files = [];
    $row_h     = 30;

    foreach ( $posts as $p ) {
        // Mêmes règles que le widget : stock épuisé → masqué
        $stock_actif = get_post_meta( $p->ID, '_sl_bp_stock_actif', true );
        $stock_qty   = get_post_meta( $p->ID, '_sl_bp_stock_qty', true );
        if ( $stock_actif === '1' && $stock_qty !== '' && (int) $stock_qty <= 0 ) {
            continue;
        }

        $prix_av  = (float) get_post_meta( $p->ID, '_sl_bp_prix_avant', true );
        $prix_ap  = (float) get_post_meta( $p->ID, '_sl_bp_prix_apres', true );
        $reduc    = (int) get_post_meta( $p->ID, '_sl_bp_reduction_pct', true );
        $date_fin = get_post_meta( $p->ID, '_sl_bp_date_fin', true );

        $c_terms  = wp_get_object_terms( $p->ID, 'sl_categorie_promo' );
        $cat_name = ( ! is_wp_error( $c_terms ) && $c_terms ) ? $c_terms[0]->name : '';
        $a_terms  = wp_get_object_terms( $p->ID, 'sl_agence_promo' );
        $ag_name  = ( ! is_wp_error( $a_terms ) && $a_terms ) ? $a_terms[0]->name : '';

        // Saut de page si la ligne ne tient pas
        if ( $pdf->GetY() + $row_h > 279 ) {
            $pdf->AddPage();
            $pdf->SetY( 14 );
        }
        $y0 = $pdf->GetY();

        // Cadre de ligne
        $pdf->SetDrawColor( 225, 225, 228 );
        $pdf->Rect( 10, $y0, 190, $row_h - 3 );

        // Image (26x26 mm), avec cadre discret
        $img = sl_bp_pdf_image_path( $p->ID );
        if ( $img ) {
            if ( strpos( basename( $img ), 'slbp-pdf-' ) === 0 ) $tmp_files[] = $img;
            $type = strtolower( pathinfo( $img, PATHINFO_EXTENSION ) ) === 'png' ? 'PNG' : 'JPG';
            // FPDF peut échouer sur une image corrompue : ne pas faire tomber tout le PDF
            try {
                $pdf->Image( $img, 11.5, $y0 + 1.5, 24, 24, $type );
            } catch ( \Throwable $e ) { /* image ignorée */ }
        }

        // Titre
        $pdf->SetTextColor( $dark[0], $dark[1], $dark[2] );
        $pdf->SetFont( 'Helvetica', 'B', 11 );
        $pdf->SetXY( 39, $y0 + 2.5 );
        $pdf->Cell( 100, 6, sl_bp_pdf_txt( mb_strimwidth( $p->post_title, 0, 58, '…', 'UTF-8' ) ), 0, 0 );

        // Catégorie + agence
        $pdf->SetFont( 'Helvetica', '', 8.5 );
        $pdf->SetTextColor( $muted[0], $muted[1], $muted[2] );
        $pdf->SetXY( 39, $y0 + 9 );
        $meta_line = trim( $ag_name . ( $cat_name ? '  •  ' . $cat_name : '' ), ' •' );
        $pdf->Cell( 100, 5, sl_bp_pdf_txt( $meta_line ), 0, 0 );

        // Validité + stock
        $pdf->SetXY( 39, $y0 + 15 );
        $valid = $date_fin ? 'Valable jusqu\'au ' . date_i18n( 'd/m/Y', strtotime( $date_fin ) ) : '';
        if ( $stock_actif === '1' ) {
            $valid .= ( $valid ? '  —  ' : '' ) . 'Dans la limite des stocks disponibles';
        }
        $pdf->Cell( 100, 5, sl_bp_pdf_txt( $valid ), 0, 0 );

        // Bloc prix à droite
        $pdf->SetFont( 'Helvetica', 'B', 13 );
        $pdf->SetTextColor( $magenta[0], $magenta[1], $magenta[2] );
        $pdf->SetXY( 142, $y0 + 4 );
        $pdf->Cell( 56, 7, sl_bp_pdf_txt( $prix_ap > 0 ? sl_bp_pdf_prix( $prix_ap ) : '' ), 0, 0, 'R' );

        if ( $prix_av > 0 ) {
            $pdf->SetFont( 'Helvetica', '', 9 );
            $pdf->SetTextColor( $muted[0], $muted[1], $muted[2] );
            $pdf->SetXY( 142, $y0 + 12 );
            $txt_av = sl_bp_pdf_txt( sl_bp_pdf_prix( $prix_av ) );
            $pdf->Cell( 56, 5, $txt_av, 0, 0, 'R' );
            // barré manuel
            $w = $pdf->GetStringWidth( $txt_av );
            $pdf->SetDrawColor( $muted[0], $muted[1], $muted[2] );
            $pdf->Line( 198 - $w, $y0 + 14.5, 198, $y0 + 14.5 );
        }
        if ( $reduc > 0 ) {
            $pdf->SetFont( 'Helvetica', 'B', 9 );
            $pdf->SetTextColor( 255, 255, 255 );
            $pdf->SetFillColor( $magenta[0], $magenta[1], $magenta[2] );
            $pdf->SetXY( 183, $y0 + 18.5 );
            $pdf->Cell( 15, 5.5, sl_bp_pdf_txt( '-' . $reduc . '%' ), 0, 0, 'C', true );
        }

        $pdf->SetY( $y0 + $row_h );
    }

    // ── Pied de page simple sur la dernière page
    // (désactiver le saut auto : SetY(-16) place le curseur au-delà du seuil de
    // rupture et créerait sinon une page vide rien que pour le pied de page)
    $pdf->SetAutoPageBreak( false );
    $pdf->SetY( -16 );
    $pdf->SetFont( 'Helvetica', '', 8 );
    $pdf->SetTextColor( $muted[0], $muted[1], $muted[2] );
    $pdf->Cell( 0, 5, sl_bp_pdf_txt( 'complexesantalucia.com — Offres valables dans les agences indiquées, sous réserve d\'erreurs ou de ruptures.' ), 0, 0, 'C' );

    // Nettoyage des JPEG temporaires (conversions webp)
    foreach ( $tmp_files as $tf ) { @unlink( $tf ); }

    $fname = 'bons-plans-santa-lucia'
           . ( $slugs ? '-' . implode( '-', array_slice( $slugs, 0, 3 ) ) : '' )
           . '-' . date( 'Y-m-d' ) . '.pdf';

    nocache_headers();
    $pdf->Output( 'D', $fname );
}
