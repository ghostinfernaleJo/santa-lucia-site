<?php
/**
 * Facture / bon de retrait PDF.
 *
 * Le client doit pouvoir se presenter au comptoir avec un document qui porte
 * tout ce que le responsable va lui demander : numero de commande, code de
 * retrait, agence, articles. Jusqu'ici ces informations ne vivaient que dans
 * un email.
 *
 * Reutilise FPDF et la charte du PDF des Bons Plans (bandeau degrade + filigrane)
 * plutot que d'embarquer une seconde bibliotheque.
 *
 * @package SL_Collect
 */

defined( 'ABSPATH' ) || exit;

/** URL de telechargement de la facture d'une commande. */
function slc_facture_url( $order ) {
    return add_query_arg( [
        'slc_facture' => $order->get_id(),
        'key'         => $order->get_order_key(),
    ], home_url( '/' ) );
}

/**
 * Qui peut telecharger cette facture ?
 * La cle de commande suffit (meme principe que les liens WooCommerce envoyes
 * par email : elle est imprevisible et propre a la commande), sinon le client
 * connecte proprietaire, sinon le staff.
 */
function slc_facture_can_view( $order, $key ) {
    if ( $key !== '' && hash_equals( $order->get_order_key(), $key ) ) {
        return true;
    }
    $uid = get_current_user_id();
    if ( $uid && (int) $order->get_customer_id() === $uid ) {
        return true;
    }
    if ( function_exists( 'slc_is_admin_user' ) && slc_is_admin_user() ) {
        return true;
    }
    // Responsable de l'agence de retrait.
    if ( function_exists( 'slc_user_agence_slug' ) ) {
        $mien = slc_user_agence_slug();
        if ( $mien !== '' && $mien === (string) $order->get_meta( '_sl_collect_agence' ) ) {
            return true;
        }
    }
    return false;
}

add_action( 'template_redirect', 'slc_facture_route' );
function slc_facture_route() {
    if ( empty( $_GET['slc_facture'] ) ) {
        return;
    }
    $order_id = absint( $_GET['slc_facture'] );
    $key      = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
    $order    = $order_id ? wc_get_order( $order_id ) : null;

    if ( ! $order ) {
        wp_die( 'Commande introuvable.', 'Facture', [ 'response' => 404 ] );
    }
    if ( ! slc_facture_can_view( $order, $key ) ) {
        wp_die( 'Vous n\'avez pas accès à cette facture.', 'Facture', [ 'response' => 403 ] );
    }

    $fpdf = SL_COLLECT_FPDF;
    if ( ! file_exists( $fpdf ) ) {
        wp_die( 'Le générateur PDF est indisponible.', 'Facture', [ 'response' => 500 ] );
    }

    slc_facture_render( $order, $fpdf );
    exit;
}

function slc_facture_render( $order, $fpdf_path ) {
    require_once $fpdf_path;

    if ( ! class_exists( 'SLC_Facture_PDF' ) ) {
        class SLC_Facture_PDF extends FPDF {
            public $wm_path  = '';
            public $wm_ratio = 0.24;
            function Header() {
                if ( ! $this->wm_path ) {
                    return;
                }
                $w = 170;
                $h = $w * $this->wm_ratio;
                $this->Image( $this->wm_path, ( 210 - $w ) / 2, ( 297 - $h ) / 2, $w, $h, 'JPG' );
            }
        }
    }

    // Un fichier de la pile WordPress emet un BOM UTF-8 : sans ce vidage il
    // precede %PDF et corrompt le fichier pour les lecteurs stricts.
    while ( ob_get_level() > 0 ) {
        @ob_end_clean();
    }

    $txt = function ( $s ) {
        return function_exists( 'sl_bp_pdf_txt' )
            ? sl_bp_pdf_txt( $s )
            : ( @iconv( 'UTF-8', 'windows-1252//TRANSLIT//IGNORE', (string) $s ) ?: (string) $s );
    };

    $code    = (string) $order->get_meta( '_sl_collect_code' );
    $slug    = (string) $order->get_meta( '_sl_collect_agence' );
    $agence  = function_exists( 'slc_agence_contact' ) ? slc_agence_contact( $slug ) : [ 'nom' => $slug, 'adresse' => '', 'tel' => '' ];
    $payee   = $order->is_paid();

    $pdf = new SLC_Facture_PDF();
    $art = function_exists( 'sl_bp_pdf_logo_variants' ) ? sl_bp_pdf_logo_variants() : [];
    if ( ! empty( $art['wm'] ) ) {
        $pdf->wm_path = $art['wm'];
        if ( ! empty( $art['ratio'] ) ) {
            $pdf->wm_ratio = $art['ratio'];
        }
    }
    $pdf->SetAutoPageBreak( true, 18 );
    $pdf->AddPage();

    // ---- Bandeau de marque (reutilise celui des Bons Plans) ----
    // Cle 'header' (et non 'band') : voir sl_bp_pdf_logo_variants().
    if ( ! empty( $art['header'] ) ) {
        $pdf->Image( $art['header'], 0, 0, 210, 26, 'JPG' );
        $pdf->SetY( 30 );
    } else {
        $pdf->SetFillColor( 233, 30, 99 );
        $pdf->Rect( 0, 0, 210, 26, 'F' );
        $pdf->SetY( 30 );
    }

    // ---- Titre ----
    $pdf->SetFont( 'Helvetica', 'B', 17 );
    $pdf->SetTextColor( 29, 84, 160 );
    $pdf->Cell( 0, 8, $txt( 'FACTURE — BON DE RETRAIT' ), 0, 1, 'L' );

    $pdf->SetFont( 'Helvetica', '', 9.5 );
    $pdf->SetTextColor( 90, 90, 90 );
    $pdf->Cell( 0, 5, $txt( 'Commande n° ' . $order->get_order_number()
        . '   •   ' . wc_format_datetime( $order->get_date_created(), 'd/m/Y H:i' )
        . '   •   ' . ( $payee ? 'PAYÉE' : 'EN ATTENTE DE PAIEMENT' ) ), 0, 1, 'L' );
    $pdf->Ln( 3 );

    // ---- LE CODE DE RETRAIT : c'est ce que le comptoir va demander ----
    if ( $code !== '' ) {
        $y = $pdf->GetY();
        $pdf->SetFillColor( 253, 240, 245 );
        $pdf->SetDrawColor( 233, 30, 99 );
        $pdf->SetLineWidth( 0.6 );
        $pdf->Rect( 10, $y, 190, 24, 'DF' );

        $pdf->SetY( $y + 3 );
        $pdf->SetFont( 'Helvetica', '', 9 );
        $pdf->SetTextColor( 150, 60, 95 );
        $pdf->Cell( 0, 5, $txt( 'CODE DE RETRAIT — à présenter au comptoir' ), 0, 1, 'C' );

        $pdf->SetFont( 'Courier', 'B', 22 );
        $pdf->SetTextColor( 233, 30, 99 );
        $pdf->Cell( 0, 11, $txt( $code ), 0, 1, 'C' );
        $pdf->SetY( $y + 26 );
    } else {
        $pdf->SetFillColor( 255, 247, 230 );
        $pdf->SetDrawColor( 219, 154, 4 );
        $pdf->SetLineWidth( 0.4 );
        $y = $pdf->GetY();
        $pdf->Rect( 10, $y, 190, 16, 'DF' );
        $pdf->SetY( $y + 3 );
        $pdf->SetFont( 'Helvetica', 'B', 10 );
        $pdf->SetTextColor( 150, 100, 10 );
        $pdf->Cell( 0, 5, $txt( 'Code de retrait non encore généré' ), 0, 1, 'C' );
        $pdf->SetFont( 'Helvetica', '', 8.5 );
        $pdf->Cell( 0, 4, $txt( 'Il apparaîtra sur cette facture dès le paiement de la commande.' ), 0, 1, 'C' );
        $pdf->SetY( $y + 18 );
    }
    $pdf->Ln( 2 );

    // ---- Agence de retrait / Client, cote a cote ----
    $y0 = $pdf->GetY();
    $pdf->SetFont( 'Helvetica', 'B', 10 );
    $pdf->SetTextColor( 29, 84, 160 );
    $pdf->Cell( 95, 6, $txt( 'RETRAIT À' ), 0, 0, 'L' );
    $pdf->Cell( 95, 6, $txt( 'CLIENT' ), 0, 1, 'L' );

    $pdf->SetFont( 'Helvetica', '', 9.5 );
    $pdf->SetTextColor( 40, 40, 40 );

    $bloc_agence = 'Santa Lucia — ' . $agence['nom'];
    if ( $agence['adresse'] !== '' ) {
        $bloc_agence .= "\n" . $agence['adresse'];
    }
    if ( $agence['tel'] !== '' ) {
        $bloc_agence .= "\n" . 'Tél. ' . $agence['tel'];
    }

    $bloc_client = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
    if ( $order->get_billing_phone() ) {
        $bloc_client .= "\n" . 'Tél. ' . $order->get_billing_phone();
    }
    if ( $order->get_billing_email() ) {
        $bloc_client .= "\n" . $order->get_billing_email();
    }

    $pdf->SetXY( 10, $y0 + 6 );
    $pdf->MultiCell( 92, 4.6, $txt( $bloc_agence ), 0, 'L' );
    $y_ag = $pdf->GetY();
    $pdf->SetXY( 105, $y0 + 6 );
    $pdf->MultiCell( 95, 4.6, $txt( $bloc_client ), 0, 'L' );
    $pdf->SetY( max( $y_ag, $pdf->GetY() ) + 4 );

    // ---- Articles ----
    $pdf->SetFont( 'Helvetica', 'B', 9 );
    $pdf->SetFillColor( 29, 84, 160 );
    $pdf->SetTextColor( 255, 255, 255 );
    $pdf->Cell( 122, 7, $txt( '  Article' ), 0, 0, 'L', true );
    $pdf->Cell( 18, 7, $txt( 'Qté' ), 0, 0, 'C', true );
    $pdf->Cell( 50, 7, $txt( 'Total  ' ), 0, 1, 'R', true );

    $pdf->SetFont( 'Helvetica', '', 9.5 );
    $pdf->SetTextColor( 40, 40, 40 );
    $alt = false;
    foreach ( $order->get_items() as $item ) {
        $pdf->SetFillColor( 247, 247, 249 );
        $nom = $item->get_name();
        if ( strlen( $nom ) > 62 ) {
            $nom = substr( $nom, 0, 59 ) . '...';
        }
        $pdf->Cell( 122, 6.5, $txt( '  ' . $nom ), 0, 0, 'L', $alt );
        $pdf->Cell( 18, 6.5, $txt( (string) $item->get_quantity() ), 0, 0, 'C', $alt );
        $pdf->Cell( 50, 6.5, $txt( wp_strip_all_tags( wc_price( $item->get_total(), [ 'currency' => $order->get_currency() ] ) ) . '  ' ), 0, 1, 'R', $alt );
        $alt = ! $alt;
    }

    $pdf->SetFont( 'Helvetica', 'B', 11 );
    $pdf->SetTextColor( 233, 30, 99 );
    $pdf->Cell( 140, 9, $txt( 'TOTAL  ' ), 'T', 0, 'R' );
    $pdf->Cell( 50, 9, $txt( wp_strip_all_tags( $order->get_formatted_order_total() ) . '  ' ), 'T', 1, 'R' );
    $pdf->Ln( 3 );

    // ---- Ce qu'il faut apporter : la raison d'etre du document ----
    $pdf->SetFont( 'Helvetica', 'B', 10 );
    $pdf->SetTextColor( 29, 84, 160 );
    $pdf->Cell( 0, 6, $txt( 'POUR RETIRER VOTRE COMMANDE' ), 0, 1, 'L' );

    $pdf->SetFont( 'Helvetica', '', 9.5 );
    $pdf->SetTextColor( 40, 40, 40 );
    $consignes = "Présentez-vous à l'agence ci-dessus muni de :\n"
        . "   1.  Cette facture (imprimée ou sur votre téléphone)\n"
        . "   2.  Votre code de retrait\n"
        . "   3.  Une pièce d'identité\n"
        . "   4.  Le téléphone ayant servi à la commande\n\n"
        . "Vous serez prévenu par email dès que votre commande sera prête.\n"
        . "Passé 72 h sans retrait, la commande est annulée automatiquement et les articles remis en vente.";
    $pdf->MultiCell( 190, 4.8, $txt( $consignes ), 0, 'L' );

    // ---- Pied de page ----
    // SetAutoPageBreak(false) AVANT SetY negatif : sinon FPDF ajoute une page vide.
    $pdf->SetAutoPageBreak( false );
    $pdf->SetY( -16 );
    $pdf->SetFont( 'Helvetica', 'I', 8 );
    $pdf->SetTextColor( 130, 130, 130 );
    $pdf->Cell( 0, 4, $txt( 'Complexe Santa Lucia — ' . home_url() ), 0, 1, 'C' );
    $pdf->Cell( 0, 4, $txt( 'Document généré le ' . date_i18n( 'd/m/Y à H:i' ) . ' — ce n\'est pas un justificatif fiscal.' ), 0, 1, 'C' );

    // Varnish met en cache les reponses par URL : sans ces en-tetes, une facture
    // pourrait etre reservie a une autre commande.
    nocache_headers();
    header( 'Content-Type: application/pdf' );
    header( 'Content-Disposition: inline; filename="facture-' . $order->get_order_number() . '.pdf"' );
    $pdf->Output( 'I', 'facture-' . $order->get_order_number() . '.pdf' );
}
