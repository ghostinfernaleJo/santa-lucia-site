<?php
/**
 * Rapport de performance Fast Food — PDF.
 *
 * Meme principe que le rapport Bons Plans : reutilise FPDF et la charte
 * (bandeau degrade, filigrane, sl_bp_pdf_txt) fournis par le plugin
 * sl-agences-elementor. Couverture + podium + classement note + fiches.
 *
 * Score /100 : 50 % couverture menu (repas au menu, relatif au meilleur)
 *            + 30 % fraicheur (jours depuis la derniere activite)
 *            + 20 % completude (% de repas au menu ayant un prix).
 *
 * @package SL_FastFood
 */

defined( 'ABSPATH' ) || exit;

/** Chemin FPDF fourni par sl-agences-elementor. */
function sl_ff_rp_fpdf_path() {
    return WP_PLUGIN_DIR . '/sl-agences-elementor/lib/fpdf/fpdf.php';
}

function sl_ff_rp_colors() {
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

function sl_ff_rp_scores( $agences, $now ) {
    $max_prop = 1;
    foreach ( $agences as $a ) {
        $max_prop = max( $max_prop, $a['proposes'] );
    }
    $rows = [];
    foreach ( $agences as $slug => $a ) {
        $s_couv  = 50 * ( $a['proposes'] / $max_prop );
        if ( $a['derniere_pub'] ) {
            $j = ( $now - $a['derniere_pub'] ) / DAY_IN_SECONDS;
            $s_frais = $j <= 14 ? 30 : max( 0, 30 * ( 1 - ( $j - 14 ) / 46 ) );
        } else {
            $s_frais = 0;
        }
        $s_prix = $a['proposes'] > 0 ? 20 * ( $a['avec_prix'] / $a['proposes'] ) : 0;
        $score  = (int) round( $s_couv + $s_frais + $s_prix );

        $rows[ $slug ] = $a + [
            'score' => $score,
            'jours' => $a['derniere_pub'] ? (int) floor( ( $now - $a['derniere_pub'] ) / DAY_IN_SECONDS ) : null,
            's_couv' => $s_couv, 's_fra' => $s_frais, 's_prix' => $s_prix,
        ];
    }
    uasort( $rows, function ( $x, $y ) {
        return $y['score'] <=> $x['score'] ?: $y['proposes'] <=> $x['proposes'];
    } );
    $rang = 0;
    foreach ( $rows as &$r ) {
        $r['rang'] = ++$rang;
    }
    return $rows;
}

function sl_ff_rp_mention( $score ) {
    if ( $score >= 80 ) return [ 'Excellent', 'vert' ];
    if ( $score >= 60 ) return [ 'Bon', 'bleu' ];
    if ( $score >= 40 ) return [ 'Moyen', 'orange' ];
    if ( $score >= 20 ) return [ 'Faible', 'rouge' ];
    return [ 'Inactif', 'rouge' ];
}

add_action( 'admin_post_slffrp_rapport', 'sl_ff_rp_generate' );
function sl_ff_rp_generate() {
    if ( ! current_user_can( 'edit_others_posts' ) && ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Accès refusé.' );
    }
    check_admin_referer( 'slffrp_rapport' );

    if ( ! function_exists( 'sl_ff_supervision_collect' ) || ! function_exists( 'sl_bp_pdf_txt' ) ) {
        wp_die( 'Modules requis indisponibles (supervision Fast Food ou charte PDF Bons Plans).' );
    }
    $fpdf = sl_ff_rp_fpdf_path();
    if ( ! file_exists( $fpdf ) ) {
        wp_die( 'Générateur PDF indisponible.' );
    }
    require_once $fpdf;

    if ( ! class_exists( 'SLFFRP_PDF' ) ) {
        class SLFFRP_PDF extends FPDF {
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
                    $this->Cell( 0, 5, sl_bp_pdf_txt( 'Rapport Fast Food — usage par agence' ), 0, 0, 'R' );
                    $this->SetY( 20 );
                    if ( $this->wm_path ) {
                        $w = 150; $h = $w * $this->wm_ratio;
                        $this->Image( $this->wm_path, ( 210 - $w ) / 2, ( 297 - $h ) / 2, $w, $h, 'JPG' );
                    }
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

    $days    = isset( $_GET['periode'] ) ? max( 1, min( 365, (int) $_GET['periode'] ) ) : 30;
    $now     = current_time( 'timestamp' );
    $agences = sl_ff_supervision_collect( $days );
    $rows    = sl_ff_rp_scores( $agences, $now );
    $deact   = get_option( 'slff_deact_log', [] );
    $deact   = is_array( $deact ) ? $deact : [];
    $resp    = function_exists( 'sl_ff_sup_responsables' ) ? sl_ff_sup_responsables() : [];
    $C       = sl_ff_rp_colors();

    $art = function_exists( 'sl_bp_pdf_logo_variants' ) ? sl_bp_pdf_logo_variants() : [];

    $pdf = new SLFFRP_PDF();
    $pdf->periode_label = 'Période : ' . $days . ' j — édité le ' . date_i18n( 'd/m/Y' );
    if ( ! empty( $art['wm'] ) ) {
        $pdf->wm_path = $art['wm'];
        $pdf->wm_ratio = $art['ratio'] ?? 0.24;
    }
    if ( ! empty( $art['header'] ) ) {
        $pdf->band = $art['header'];
    }
    $pdf->SetAutoPageBreak( true, 16 );

    /* ---- Couverture + podium ---- */
    $pdf->AddPage();
    if ( ! empty( $art['header'] ) ) {
        $pdf->Image( $art['header'], 0, 0, 210, 26, 'JPG' );
    } else {
        $pdf->SetFillColor( ...$C['bleu'] );
        $pdf->Rect( 0, 0, 210, 26, 'F' );
    }
    $pdf->SetY( 40 );
    $pdf->SetFont( 'Helvetica', 'B', 25 );
    $pdf->SetTextColor( ...$C['bleu'] );
    $pdf->Cell( 0, 12, sl_bp_pdf_txt( 'Rapport Fast Food' ), 0, 1, 'C' );
    $pdf->SetFont( 'Helvetica', '', 14 );
    $pdf->SetTextColor( ...$C['magenta'] );
    $pdf->Cell( 0, 8, sl_bp_pdf_txt( 'Usage du menu du jour par agence' ), 0, 1, 'C' );
    $pdf->SetFont( 'Helvetica', '', 10 );
    $pdf->SetTextColor( ...$C['gris'] );
    $pdf->Cell( 0, 6, sl_bp_pdf_txt( 'Période : ' . $days . ' derniers jours · édité le ' . date_i18n( 'd/m/Y à H:i' ) ), 0, 1, 'C' );

    $top = array_slice( $rows, 0, 3, true );
    $ordered = array_values( $top ); $keys = array_keys( $top );
    $slots = [ 0 => null, 1 => null, 2 => null ];
    if ( isset( $ordered[0] ) ) { $slots[1] = [ $keys[0], $ordered[0] ]; }
    if ( isset( $ordered[1] ) ) { $slots[0] = [ $keys[1], $ordered[1] ]; }
    if ( isset( $ordered[2] ) ) { $slots[2] = [ $keys[2], $ordered[2] ]; }
    $medals = [ 1 => [ $C['or'], '1er', 46 ], 2 => [ [ 150, 150, 150 ], '2e', 36 ], 3 => [ [ 176, 122, 78 ], '3e', 30 ] ];
    $col_w = 56; $gap = 6; $sx = ( 210 - ( 3 * $col_w + 2 * $gap ) ) / 2; $py = $pdf->GetY() + 12;
    foreach ( [ 0, 1, 2 ] as $i ) {
        if ( ! $slots[ $i ] ) { continue; }
        list( $slug, $r ) = $slots[ $i ];
        list( $mc, $label, $bh ) = $medals[ $r['rang'] ];
        $x = $sx + $i * ( $col_w + $gap );
        $base_y = $py + 54;
        $pdf->SetFillColor( ...$mc );
        $pdf->Rect( $x, $base_y - $bh, $col_w, $bh, 'F' );
        $pdf->SetXY( $x, $base_y - $bh + 4 );
        $pdf->SetFont( 'Helvetica', 'B', 20 );
        $pdf->SetTextColor( 255, 255, 255 );
        $pdf->Cell( $col_w, 10, sl_bp_pdf_txt( $label ), 0, 0, 'C' );
        $pdf->SetXY( $x - 2, $base_y - $bh - 16 );
        $pdf->SetFont( 'Helvetica', 'B', 10 );
        $pdf->SetTextColor( ...$C['bleu'] );
        $pdf->MultiCell( $col_w + 4, 5, sl_bp_pdf_txt( sl_ff_sup_agence_nom( $slug ) ), 0, 'C' );
        $pdf->SetXY( $x, $base_y - $bh - 6 );
        $pdf->SetFont( 'Helvetica', 'B', 15 );
        $pdf->SetTextColor( ...$C['magenta'] );
        $pdf->Cell( $col_w, 6, sl_bp_pdf_txt( $r['score'] . '/100' ), 0, 0, 'C' );
    }
    $pdf->SetY( $py + 66 );
    $pdf->SetFont( 'Helvetica', '', 9 );
    $pdf->SetTextColor( ...$C['gris'] );
    $pdf->MultiCell( 0, 5, sl_bp_pdf_txt(
        "Score /100 = couverture du menu (50 %) + fraîcheur de l'activité (30 %) + complétude des prix (20 %). "
        . "La couverture est relative à l'agence proposant le plus de repas."
    ), 0, 'C' );

    /* ---- Classement ---- */
    $pdf->AddPage();
    $pdf->SetFont( 'Helvetica', 'B', 15 );
    $pdf->SetTextColor( ...$C['bleu'] );
    $pdf->Cell( 0, 10, sl_bp_pdf_txt( 'Classement des agences' ), 0, 1, 'L' );
    $pdf->Ln( 2 );

    $pdf->SetFont( 'Helvetica', 'B', 8.5 );
    $pdf->SetFillColor( ...$C['bleu'] );
    $pdf->SetTextColor( 255, 255, 255 );
    $cols = [ [ 11, 'Rang', 'C' ], [ 48, 'Agence', 'L' ], [ 18, 'Score', 'C' ], [ 22, 'Au menu', 'C' ],
              [ 22, 'Avec prix', 'C' ], [ 20, 'Promo', 'C' ], [ 28, 'Dern. activité', 'C' ], [ 20, 'Mention', 'C' ] ];
    foreach ( $cols as $c ) {
        $pdf->Cell( $c[0], 8, sl_bp_pdf_txt( $c[1] ), 0, 0, $c[2], true );
    }
    $pdf->Ln();

    $pdf->SetFont( 'Helvetica', '', 8.5 );
    $alt = false;
    foreach ( $rows as $slug => $r ) {
        list( $mention, $mcol ) = sl_ff_rp_mention( $r['score'] );
        $pdf->SetFillColor( ...( $alt ? $C['gris_bg'] : [ 255, 255, 255 ] ) );
        $pdf->SetTextColor( 40, 40, 40 );
        $pdf->Cell( 11, 7, sl_bp_pdf_txt( '#' . $r['rang'] ), 0, 0, 'C', true );
        $pdf->SetFont( 'Helvetica', 'B', 8.5 );
        $pdf->Cell( 48, 7, sl_bp_pdf_txt( sl_ff_sup_agence_nom( $slug ) ), 0, 0, 'L', true );
        $pdf->SetFont( 'Helvetica', 'B', 9 );
        $pdf->SetTextColor( ...$C[ $mcol ] );
        $pdf->Cell( 18, 7, sl_bp_pdf_txt( (string) $r['score'] ), 0, 0, 'C', true );
        $pdf->SetFont( 'Helvetica', '', 8.5 );
        $pdf->SetTextColor( 40, 40, 40 );
        $pdf->Cell( 22, 7, sl_bp_pdf_txt( $r['proposes'] . ' / ' . $r['total'] ), 0, 0, 'C', true );
        $pdf->Cell( 22, 7, sl_bp_pdf_txt( (string) $r['avec_prix'] ), 0, 0, 'C', true );
        $pdf->Cell( 20, 7, sl_bp_pdf_txt( (string) $r['en_promo'] ), 0, 0, 'C', true );
        $dp = null === $r['jours'] ? 'jamais' : ( 0 === $r['jours'] ? 'auj.' : 'il y a ' . $r['jours'] . ' j' );
        $pdf->Cell( 28, 7, sl_bp_pdf_txt( $dp ), 0, 0, 'C', true );
        $pdf->SetTextColor( ...$C[ $mcol ] );
        $pdf->SetFont( 'Helvetica', 'B', 8 );
        $pdf->Cell( 20, 7, sl_bp_pdf_txt( $mention ), 0, 0, 'C', true );
        $pdf->Ln();
        $alt = ! $alt;
    }

    $inactives = array_filter( $rows, function ( $r ) { return 0 === $r['proposes']; } );
    $pdf->Ln( 6 );
    $pdf->SetFont( 'Helvetica', 'B', 10 );
    $pdf->SetTextColor( ...$C['bleu'] );
    $pdf->Cell( 0, 6, sl_bp_pdf_txt( 'Synthèse' ), 0, 1 );
    $pdf->SetFont( 'Helvetica', '', 9 );
    $pdf->SetTextColor( 50, 50, 50 );
    $pdf->MultiCell( 0, 5.5, sl_bp_pdf_txt(
        '· ' . count( $rows ) . ' agences suivies, ' . ( count( $rows ) - count( $inactives ) ) . ' proposent un menu.' . "\n"
        . '· ' . count( $inactives ) . ' agence(s) sans aucun repas au menu : '
        . ( $inactives ? implode( ', ', array_map( 'sl_ff_sup_agence_nom', array_keys( $inactives ) ) ) : 'aucune' ) . '.'
    ), 0, 'L' );

    /* ---- Fiches par agence ---- */
    $pdf->AddPage();
    $pdf->SetFont( 'Helvetica', 'B', 15 );
    $pdf->SetTextColor( ...$C['bleu'] );
    $pdf->Cell( 0, 10, sl_bp_pdf_txt( 'Détail par agence' ), 0, 1, 'L' );
    $pdf->Ln( 2 );

    foreach ( $rows as $slug => $r ) {
        if ( $pdf->GetY() > 232 ) {
            $pdf->AddPage();
        }
        $y0 = $pdf->GetY();
        $pdf->SetDrawColor( 220, 222, 228 );
        $pdf->SetLineWidth( 0.3 );
        $pdf->Rect( 10, $y0, 190, 46 );
        $pdf->SetFillColor( ...$C['bleu'] );
        $pdf->Rect( 10, $y0, 190, 9, 'F' );
        $pdf->SetXY( 12, $y0 + 1.5 );
        $pdf->SetFont( 'Helvetica', 'B', 10 );
        $pdf->SetTextColor( 255, 255, 255 );
        $pdf->Cell( 150, 6, sl_bp_pdf_txt( '#' . $r['rang'] . '  ' . sl_ff_sup_agence_nom( $slug ) ), 0, 0, 'L' );
        $pdf->SetFont( 'Helvetica', 'B', 11 );
        $pdf->Cell( 36, 6, sl_bp_pdf_txt( $r['score'] . '/100' ), 0, 0, 'R' );

        $pdf->SetXY( 12, $y0 + 11 );
        $pdf->SetFont( 'Helvetica', '', 8.5 );
        $pdf->SetTextColor( ...$C['gris'] );
        $noms = isset( $resp[ $slug ] ) ? implode( ', ', $resp[ $slug ] ) : '';
        $pdf->Cell( 0, 5, sl_bp_pdf_txt( $noms ? 'Responsable(s) : ' . $noms : 'Aucun responsable Fast Food rattaché' ), 0, 1, 'L' );

        $bars = [
            [ 'Couverture menu', $r['s_couv'], 50 ],
            [ 'Fraîcheur', $r['s_fra'], 30 ],
            [ 'Prix renseignés', $r['s_prix'], 20 ],
        ];
        $by = $y0 + 18;
        foreach ( $bars as $b ) {
            $pdf->SetXY( 12, $by );
            $pdf->SetFont( 'Helvetica', '', 8 );
            $pdf->SetTextColor( 70, 70, 70 );
            $pdf->Cell( 30, 5, sl_bp_pdf_txt( $b[0] ), 0, 0, 'L' );
            $pdf->SetFillColor( 232, 233, 238 );
            $pdf->Rect( 42, $by + 1, 88, 3, 'F' );
            $frac = $b[2] > 0 ? $b[1] / $b[2] : 0;
            if ( $frac > 0 ) {
                $pdf->SetFillColor( ...$C['magenta'] );
                $pdf->Rect( 42, $by + 1, 88 * $frac, 3, 'F' );
            }
            $pdf->SetXY( 132, $by );
            $pdf->SetFont( 'Helvetica', '', 7.5 );
            $pdf->SetTextColor( ...$C['gris'] );
            $pdf->Cell( 20, 5, sl_bp_pdf_txt( round( $b[1] ) . ' / ' . $b[2] ), 0, 0, 'L' );
            $by += 5.6;
        }

        // Chiffres a droite + derniere desactivation
        $dl = isset( $deact[ $slug ] ) ? $deact[ $slug ] : null;
        $deact_txt = ( $dl && ! empty( $dl['ts'] ) )
            ? 'il y a ' . (int) floor( ( $now - $dl['ts'] ) / DAY_IN_SECONDS ) . ' j (' . (int) ( $dl['count'] ?? 0 ) . ')'
            : '—';
        $stats = [
            'Au menu : ' . $r['proposes'] . ' / ' . $r['total'],
            'En promo : ' . $r['en_promo'],
            'Ajouts (période) : ' . $r['ajouts'],
            'Dern. désactiv. : ' . $deact_txt,
        ];
        foreach ( $stats as $k => $line ) {
            $pdf->SetXY( 150, $y0 + 18 + $k * 5.6 );
            $pdf->SetFont( 'Helvetica', '', 8 );
            $pdf->SetTextColor( 60, 60, 60 );
            $pdf->Cell( 48, 5, sl_bp_pdf_txt( $line ), 0, 0, 'L' );
        }

        $pdf->SetY( $y0 + 50 );
    }

    while ( ob_get_level() > 0 ) {
        @ob_end_clean();
    }
    nocache_headers();
    header( 'Content-Type: application/pdf' );
    header( 'Content-Disposition: inline; filename="rapport-fastfood-' . date( 'Ymd' ) . '.pdf"' );
    $pdf->Output( 'I', 'rapport-fastfood.pdf' );
    exit;
}
