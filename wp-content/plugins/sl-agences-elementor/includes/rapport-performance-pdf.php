<?php
/**
 * Rapport de performance des agences — PDF.
 *
 * Reutilise slsv_collect() (supervision-agences.php) pour les donnees et la
 * charte FPDF du PDF Bons Plans (bandeau degrade, filigrane, sl_bp_pdf_txt).
 * Genere un document soigne : couverture + podium, classement note, et une
 * fiche par agence.
 *
 * Score de performance (0-100), pondere et documente sur le rapport :
 *   40 % volume de creations (periode, relatif au meilleur)
 *   25 % fraicheur (jours depuis la derniere publication)
 *   20 % rigueur de stock (% d'offres actives avec stock gere)
 *   15 % proprete du catalogue (penalise les offres expirees encore affichees)
 *
 * @package SL_Agences
 */

defined( 'ABSPATH' ) || exit;

/** Couleurs de marque (RGB). */
function slrp_colors() {
    return [
        'bleu'    => [ 29, 84, 160 ],
        'magenta' => [ 233, 30, 99 ],
        'or'      => [ 201, 162, 39 ],
        'vert'    => [ 30, 123, 52 ],
        'orange'  => [ 179, 120, 10 ],
        'rouge'   => [ 179, 45, 46 ],
        'gris'    => [ 120, 120, 120 ],
        'gris_bg' => [ 244, 245, 248 ],
    ];
}

/**
 * Calcule le score 0-100 de chaque agence a partir des agregats slsv_collect.
 * @return array slug => données + score + rang, triées par score décroissant.
 */
function slrp_scores( $data, $now ) {
    $max_crees = 1;
    foreach ( $data['agences'] as $a ) {
        $max_crees = max( $max_crees, $a['crees'] );
    }

    $rows = [];
    foreach ( $data['agences'] as $slug => $a ) {
        // Volume (40) : relatif au meilleur de la periode.
        $s_volume = 40 * ( $a['crees'] / $max_crees );

        // Fraicheur (25) : 25 si publie il y a <= 3 j, degressif jusqu'a 0 a 30 j.
        if ( $a['derniere_pub'] ) {
            $jours = ( $now - $a['derniere_pub'] ) / DAY_IN_SECONDS;
            $s_frais = $jours <= 3 ? 25 : max( 0, 25 * ( 1 - ( $jours - 3 ) / 27 ) );
        } else {
            $s_frais = 0;
        }

        // Rigueur de stock (20) : part des offres actives avec stock gere.
        $s_stock = $a['actives'] > 0 ? 20 * ( $a['stock_gere'] / $a['actives'] ) : 0;

        // Proprete (15) : penalise les expirees laissees en ligne.
        $total_pub = $a['actives'] + $a['expirees'];
        $s_propre  = $total_pub > 0 ? 15 * ( $a['actives'] / $total_pub ) : ( $a['actives'] ? 15 : 0 );

        $score = (int) round( $s_volume + $s_frais + $s_stock + $s_propre );

        $rows[ $slug ] = $a + [
            'score'   => $score,
            'jours'   => $a['derniere_pub'] ? (int) floor( ( $now - $a['derniere_pub'] ) / DAY_IN_SECONDS ) : null,
            's_vol'   => $s_volume, 's_fra' => $s_frais, 's_sto' => $s_stock, 's_pro' => $s_propre,
        ];
    }

    uasort( $rows, function ( $x, $y ) {
        return $y['score'] <=> $x['score'] ?: $y['crees'] <=> $x['crees'];
    } );

    $rang = 0;
    foreach ( $rows as $slug => &$r ) {
        $r['rang'] = ++$rang;
    }
    return $rows;
}

/** Mention textuelle d'un score. */
function slrp_mention( $score ) {
    if ( $score >= 80 ) return [ 'Excellent', 'vert' ];
    if ( $score >= 60 ) return [ 'Bon', 'bleu' ];
    if ( $score >= 40 ) return [ 'Moyen', 'orange' ];
    if ( $score >= 20 ) return [ 'Faible', 'rouge' ];
    return [ 'Critique', 'rouge' ];
}

/** Classe FPDF avec entete/pied de marque. */
function slrp_pdf_class() {
    if ( class_exists( 'SLRP_PDF' ) ) {
        return;
    }
    class SLRP_PDF extends FPDF {
        public $wm_path = '';
        public $wm_ratio = 0.24;
        public $band = '';
        public $periode_label = '';
        function Header() {
            if ( $this->PageNo() > 1 ) {
                if ( $this->band ) {
                    $this->Image( $this->band, 0, 0, 210, 14, 'JPG' );
                } else {
                    $this->SetFillColor( 29, 84, 160 );
                    $this->Rect( 0, 0, 210, 14, 'F' );
                }
                $this->SetY( 5 );
                $this->SetFont( 'Helvetica', 'B', 8 );
                $this->SetTextColor( 255, 255, 255 );
                $this->Cell( 0, 5, sl_bp_pdf_txt( 'Rapport de performance des agences' ), 0, 0, 'R' );
                $this->SetY( 20 );
            }
            if ( $this->wm_path && $this->PageNo() > 1 ) {
                $w = 150; $h = $w * $this->wm_ratio;
                $this->Image( $this->wm_path, ( 210 - $w ) / 2, ( 297 - $h ) / 2, $w, $h, 'JPG' );
            }
        }
        function Footer() {
            $this->SetY( -12 );
            $this->SetFont( 'Helvetica', 'I', 7.5 );
            $this->SetTextColor( 150, 150, 150 );
            $this->Cell( 0, 5, sl_bp_pdf_txt( 'Complexe Santa Lucia — ' . $this->periode_label . '   •   page ' . $this->PageNo() ), 0, 0, 'C' );
        }
    }
}

add_action( 'admin_post_slrp_rapport', 'slrp_generate' );
function slrp_generate() {
    if ( ! current_user_can( 'edit_others_posts' ) && ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Accès refusé.' );
    }
    check_admin_referer( 'slrp_rapport' );

    if ( ! function_exists( 'slsv_collect' ) ) {
        wp_die( 'Module de supervision indisponible.' );
    }
    $fpdf = SL_AGENCES_PATH . 'lib/fpdf/fpdf.php';
    if ( ! file_exists( $fpdf ) ) {
        wp_die( 'Générateur PDF indisponible.' );
    }
    require_once $fpdf;
    slrp_pdf_class();

    $days = isset( $_GET['periode'] ) ? max( 1, min( 365, (int) $_GET['periode'] ) ) : 30;
    $now  = current_time( 'timestamp' );
    $data = slsv_collect( $days );
    $rows = slrp_scores( $data, $now );
    $C    = slrp_colors();

    $art = function_exists( 'sl_bp_pdf_logo_variants' ) ? sl_bp_pdf_logo_variants() : [];

    $pdf = new SLRP_PDF();
    $pdf->periode_label = 'Période : ' . $days . ' derniers jours — édité le ' . date_i18n( 'd/m/Y' );
    if ( ! empty( $art['wm'] ) ) {
        $pdf->wm_path = $art['wm'];
        $pdf->wm_ratio = $art['ratio'] ?? 0.24;
    }
    if ( ! empty( $art['header'] ) ) {
        $pdf->band = $art['header'];
    }
    $pdf->SetAutoPageBreak( true, 16 );

    /* ============ PAGE 1 : COUVERTURE + PODIUM ============ */
    $pdf->AddPage();
    if ( ! empty( $art['header'] ) ) {
        $pdf->Image( $art['header'], 0, 0, 210, 26, 'JPG' );
    } else {
        $pdf->SetFillColor( ...$C['bleu'] );
        $pdf->Rect( 0, 0, 210, 26, 'F' );
    }
    $pdf->SetY( 40 );
    $pdf->SetFont( 'Helvetica', 'B', 26 );
    $pdf->SetTextColor( ...$C['bleu'] );
    $pdf->Cell( 0, 12, sl_bp_pdf_txt( 'Rapport de performance' ), 0, 1, 'C' );
    $pdf->SetFont( 'Helvetica', '', 14 );
    $pdf->SetTextColor( ...$C['magenta'] );
    $pdf->Cell( 0, 8, sl_bp_pdf_txt( 'Activité des agences — Bons Plans' ), 0, 1, 'C' );
    $pdf->SetFont( 'Helvetica', '', 10 );
    $pdf->SetTextColor( ...$C['gris'] );
    $pdf->Cell( 0, 6, sl_bp_pdf_txt( 'Période : ' . $days . ' derniers jours · édité le ' . date_i18n( 'd/m/Y à H:i' ) ), 0, 1, 'C' );

    // Podium top 3
    $top = array_slice( $rows, 0, 3, true );
    $pdf->Ln( 10 );
    $podium_y = $pdf->GetY() + 6;
    $medals = [ 1 => [ $C['or'], '1er', 46 ], 2 => [ [ 150, 150, 150 ], '2e', 36 ], 3 => [ [ 176, 122, 78 ], '3e', 30 ] ];
    // Ordre visuel : 2 - 1 - 3
    $slots = [ 0 => null, 1 => null, 2 => null ];
    $ordered = array_values( $top );
    $keys    = array_keys( $top );
    if ( isset( $ordered[0] ) ) { $slots[1] = [ $keys[0], $ordered[0] ]; }
    if ( isset( $ordered[1] ) ) { $slots[0] = [ $keys[1], $ordered[1] ]; }
    if ( isset( $ordered[2] ) ) { $slots[2] = [ $keys[2], $ordered[2] ]; }

    $col_w = 56; $gap = 6; $start_x = ( 210 - ( 3 * $col_w + 2 * $gap ) ) / 2;
    foreach ( [ 0, 1, 2 ] as $i ) {
        if ( ! $slots[ $i ] ) continue;
        list( $slug, $r ) = $slots[ $i ];
        $rang = $r['rang'];
        list( $mc, $label, $bh ) = $medals[ $rang ];
        $x = $start_x + $i * ( $col_w + $gap );
        $base_y = $podium_y + 54;
        // socle
        $pdf->SetFillColor( ...$mc );
        $pdf->Rect( $x, $base_y - $bh, $col_w, $bh, 'F' );
        // rang
        $pdf->SetXY( $x, $base_y - $bh + 4 );
        $pdf->SetFont( 'Helvetica', 'B', 20 );
        $pdf->SetTextColor( 255, 255, 255 );
        $pdf->Cell( $col_w, 10, sl_bp_pdf_txt( $label ), 0, 0, 'C' );
        // nom + score au-dessus du socle
        $pdf->SetXY( $x - 2, $base_y - $bh - 16 );
        $pdf->SetFont( 'Helvetica', 'B', 10 );
        $pdf->SetTextColor( ...$C['bleu'] );
        $pdf->MultiCell( $col_w + 4, 5, sl_bp_pdf_txt( slsv_agence_nom( $slug ) ), 0, 'C' );
        $pdf->SetXY( $x, $base_y - $bh - 6 );
        $pdf->SetFont( 'Helvetica', 'B', 15 );
        $pdf->SetTextColor( ...$C['magenta'] );
        $pdf->Cell( $col_w, 6, sl_bp_pdf_txt( $r['score'] . '/100' ), 0, 0, 'C' );
    }

    $pdf->SetY( $podium_y + 66 );
    $pdf->SetFont( 'Helvetica', '', 9 );
    $pdf->SetTextColor( ...$C['gris'] );
    $pdf->MultiCell( 0, 5, sl_bp_pdf_txt(
        "Score /100 = volume de créations (40 %) + fraîcheur des publications (25 %) "
        . "+ rigueur de gestion du stock (20 %) + propreté du catalogue (15 %). "
        . "Le volume est relatif à l'agence la plus active de la période."
    ), 0, 'C' );

    /* ============ PAGE 2 : CLASSEMENT COMPLET ============ */
    $pdf->AddPage();
    $pdf->SetFont( 'Helvetica', 'B', 15 );
    $pdf->SetTextColor( ...$C['bleu'] );
    $pdf->Cell( 0, 10, sl_bp_pdf_txt( 'Classement général' ), 0, 1, 'L' );
    $pdf->Ln( 2 );

    // En-tete du tableau
    $pdf->SetFont( 'Helvetica', 'B', 8.5 );
    $pdf->SetFillColor( ...$C['bleu'] );
    $pdf->SetTextColor( 255, 255, 255 );
    $cols = [ [ 11, 'Rang', 'C' ], [ 46, 'Agence', 'L' ], [ 17, 'Score', 'C' ], [ 17, 'Créés', 'C' ],
              [ 17, '/sem.', 'C' ], [ 20, 'J. actifs', 'C' ], [ 20, 'Actives', 'C' ], [ 24, 'Dern. pub.', 'C' ], [ 18, 'Mention', 'C' ] ];
    foreach ( $cols as $c ) {
        $pdf->Cell( $c[0], 8, sl_bp_pdf_txt( $c[1] ), 0, 0, $c[2], true );
    }
    $pdf->Ln();

    $pdf->SetFont( 'Helvetica', '', 8.5 );
    $alt = false;
    foreach ( $rows as $slug => $r ) {
        list( $mention, $mcol ) = slrp_mention( $r['score'] );
        $pdf->SetFillColor( ...( $alt ? $C['gris_bg'] : [ 255, 255, 255 ] ) );
        $pdf->SetTextColor( 40, 40, 40 );

        $pdf->Cell( 11, 7, sl_bp_pdf_txt( '#' . $r['rang'] ), 0, 0, 'C', true );
        $pdf->SetFont( 'Helvetica', 'B', 8.5 );
        $pdf->Cell( 46, 7, sl_bp_pdf_txt( slsv_agence_nom( $slug ) ), 0, 0, 'L', true );
        $pdf->SetFont( 'Helvetica', 'B', 9 );
        $pdf->SetTextColor( ...$C[ $mcol ] );
        $pdf->Cell( 17, 7, sl_bp_pdf_txt( (string) $r['score'] ), 0, 0, 'C', true );
        $pdf->SetFont( 'Helvetica', '', 8.5 );
        $pdf->SetTextColor( 40, 40, 40 );
        $pdf->Cell( 17, 7, sl_bp_pdf_txt( (string) $r['crees'] ), 0, 0, 'C', true );
        $pdf->Cell( 17, 7, sl_bp_pdf_txt( str_replace( '.', ',', (string) ( $r['par_semaine'] ?? 0 ) ) ), 0, 0, 'C', true );
        $pdf->Cell( 20, 7, sl_bp_pdf_txt( (string) ( $r['jours_actifs'] ?? 0 ) ), 0, 0, 'C', true );
        $pdf->Cell( 20, 7, sl_bp_pdf_txt( (string) $r['actives'] ), 0, 0, 'C', true );
        $dp = null === $r['jours'] ? 'jamais' : ( 0 === $r['jours'] ? 'auj.' : 'il y a ' . $r['jours'] . ' j' );
        $pdf->Cell( 24, 7, sl_bp_pdf_txt( $dp ), 0, 0, 'C', true );
        $pdf->SetTextColor( ...$C[ $mcol ] );
        $pdf->SetFont( 'Helvetica', 'B', 8 );
        $pdf->Cell( 18, 7, sl_bp_pdf_txt( $mention ), 0, 0, 'C', true );
        $pdf->Ln();
        $alt = ! $alt;
    }

    // Synthese chiffree
    $muettes = array_filter( $rows, function ( $r ) { return 0 === $r['crees']; } );
    $pdf->Ln( 6 );
    $pdf->SetFont( 'Helvetica', 'B', 10 );
    $pdf->SetTextColor( ...$C['bleu'] );
    $pdf->Cell( 0, 6, sl_bp_pdf_txt( 'Synthèse' ), 0, 1 );
    $pdf->SetFont( 'Helvetica', '', 9 );
    $pdf->SetTextColor( 50, 50, 50 );
    $tot_crees = array_sum( wp_list_pluck( $rows, 'crees' ) );
    $tot_act   = array_sum( wp_list_pluck( $rows, 'actives' ) );
    $pdf->MultiCell( 0, 5.5, sl_bp_pdf_txt(
        '· ' . count( $rows ) . ' agences suivies, ' . ( count( $rows ) - count( $muettes ) ) . ' actives sur la période.' . "\n"
        . '· ' . $tot_crees . ' bons plans créés, ' . $tot_act . ' actuellement en ligne.' . "\n"
        . '· ' . count( $muettes ) . ' agence(s) sans aucune création : '
        . ( $muettes ? implode( ', ', array_map( function ( $s ) { return slsv_agence_nom( $s ); }, array_keys( $muettes ) ) ) : 'aucune' ) . '.'
    ), 0, 'L' );

    /* ============ PAGES SUIVANTES : FICHE PAR AGENCE ============ */
    $pdf->AddPage();
    $pdf->SetFont( 'Helvetica', 'B', 15 );
    $pdf->SetTextColor( ...$C['bleu'] );
    $pdf->Cell( 0, 10, sl_bp_pdf_txt( 'Détail par agence' ), 0, 1, 'L' );
    $pdf->Ln( 2 );

    $resp_map = function_exists( 'slsv_responsables_par_agence' ) ? slsv_responsables_par_agence() : [];

    foreach ( $rows as $slug => $r ) {
        // Saut de page si la fiche ne tient pas (hauteur ~52 mm).
        if ( $pdf->GetY() > 235 ) {
            $pdf->AddPage();
        }
        $y0 = $pdf->GetY();
        list( $mention, $mcol ) = slrp_mention( $r['score'] );

        // Cadre
        $pdf->SetDrawColor( 220, 222, 228 );
        $pdf->SetLineWidth( 0.3 );
        $pdf->Rect( 10, $y0, 190, 48 );

        // Bandeau rang + nom
        $pdf->SetFillColor( ...$C['bleu'] );
        $pdf->Rect( 10, $y0, 190, 9, 'F' );
        $pdf->SetXY( 12, $y0 + 1.5 );
        $pdf->SetFont( 'Helvetica', 'B', 10 );
        $pdf->SetTextColor( 255, 255, 255 );
        $pdf->Cell( 150, 6, sl_bp_pdf_txt( '#' . $r['rang'] . '  ' . slsv_agence_nom( $slug ) ), 0, 0, 'L' );
        $pdf->SetFont( 'Helvetica', 'B', 11 );
        $pdf->Cell( 36, 6, sl_bp_pdf_txt( $r['score'] . '/100' ), 0, 0, 'R' );

        // Responsables
        $pdf->SetXY( 12, $y0 + 11 );
        $pdf->SetFont( 'Helvetica', '', 8.5 );
        $pdf->SetTextColor( ...$C['gris'] );
        $noms = isset( $resp_map[ $slug ] ) ? implode( ', ', $resp_map[ $slug ] ) : '';
        $pdf->Cell( 0, 5, sl_bp_pdf_txt( $noms ? 'Responsable(s) : ' . $noms : 'Aucun responsable rattaché' ), 0, 1, 'L' );

        // Barres de sous-scores
        $bars = [
            [ 'Créations', $r['s_vol'], 40 ],
            [ 'Fraîcheur', $r['s_fra'], 25 ],
            [ 'Rigueur stock', $r['s_sto'], 20 ],
            [ 'Propreté', $r['s_pro'], 15 ],
        ];
        $by = $y0 + 18;
        foreach ( $bars as $b ) {
            $pdf->SetXY( 12, $by );
            $pdf->SetFont( 'Helvetica', '', 8 );
            $pdf->SetTextColor( 70, 70, 70 );
            $pdf->Cell( 28, 5, sl_bp_pdf_txt( $b[0] ), 0, 0, 'L' );
            // piste
            $pdf->SetFillColor( 232, 233, 238 );
            $pdf->Rect( 40, $by + 1, 90, 3, 'F' );
            // remplissage
            $frac = $b[2] > 0 ? $b[1] / $b[2] : 0;
            $pdf->SetFillColor( ...$C['magenta'] );
            if ( $frac > 0 ) {
                $pdf->Rect( 40, $by + 1, 90 * $frac, 3, 'F' );
            }
            $pdf->SetXY( 132, $by );
            $pdf->SetFont( 'Helvetica', '', 7.5 );
            $pdf->SetTextColor( ...$C['gris'] );
            $pdf->Cell( 20, 5, sl_bp_pdf_txt( round( $b[1] ) . ' / ' . $b[2] ), 0, 0, 'L' );
            $by += 5.4;
        }

        // Colonne chiffres a droite
        $pdf->SetXY( 158, $y0 + 18 );
        $pdf->SetFont( 'Helvetica', '', 8 );
        $pdf->SetTextColor( 60, 60, 60 );
        $stats = [
            'Créés : ' . $r['crees'],
            'En ligne : ' . $r['actives'],
            'Épuisées : ' . $r['epuisees'],
            'Expirées : ' . $r['expirees'],
        ];
        foreach ( $stats as $k => $line ) {
            $pdf->SetXY( 150, $y0 + 18 + $k * 5.4 );
            $pdf->Cell( 48, 5, sl_bp_pdf_txt( $line ), 0, 0, 'L' );
        }

        $pdf->SetY( $y0 + 52 );
    }

    // Sortie
    while ( ob_get_level() > 0 ) {
        @ob_end_clean();
    }
    nocache_headers();
    header( 'Content-Type: application/pdf' );
    header( 'Content-Disposition: inline; filename="rapport-performance-agences-' . date( 'Ymd' ) . '.pdf"' );
    $pdf->Output( 'I', 'rapport-performance-agences.pdf' );
    exit;
}
