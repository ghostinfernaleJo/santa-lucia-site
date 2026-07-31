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
 * Jeton opaque utilise par le QR code de la facture.
 * Le QR ne contient jamais directement le numero de commande, le telephone ou
 * les articles. Il ne contient qu'une URL de scan qui sera resolue par le site.
 */
function slc_facture_scan_token( $order ) {
    $token = (string) $order->get_meta( '_slc_facture_scan_token' );
    if ( $token !== '' ) {
        return $token;
    }

    try {
        $token = bin2hex( random_bytes( 16 ) );
    } catch ( Exception $e ) {
        $token = wp_generate_password( 32, false, false );
    }

    $order->update_meta_data( '_slc_facture_scan_token', $token );
    $order->save();
    return $token;
}

/** URL mobile ouverte après lecture du QR code. */
function slc_facture_scan_url( $order ) {
    return add_query_arg(
        [
            'slc_scan' => '1',
            'token'    => slc_facture_scan_token( $order ),
        ],
        home_url( '/' )
    );
}

/**
 * Génère temporairement l'image QR via un service spécialisé.
 * Seul le jeton opaque est transmis au service, jamais les données client.
 */
function slc_facture_qr_file( $order ) {
    $url = add_query_arg(
        [
            'size' => '300x300',
            'data' => slc_facture_scan_url( $order ),
        ],
        'https://api.qrserver.com/v1/create-qr-code/'
    );
    $res = wp_remote_get( $url, [ 'timeout' => 8, 'sslverify' => true ] );
    if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
        return '';
    }

    $body = wp_remote_retrieve_body( $res );
    if ( $body === '' ) {
        return '';
    }
    $file = wp_tempnam( 'slc-facture-qr' );
    if ( ! $file || false === file_put_contents( $file, $body ) ) {
        return '';
    }
    return $file;
}

/** Fiche mobile destinée au responsable qui scanne la facture au comptoir. */
add_action( 'template_redirect', 'slc_facture_scan_route', 5 );
function slc_facture_scan_route() {
    if ( empty( $_GET['slc_scan'] ) ) {
        return;
    }

    $token = isset( $_GET['token'] ) ? strtolower( sanitize_text_field( wp_unslash( $_GET['token'] ) ) ) : '';
    if ( ! preg_match( '/^[a-f0-9]{32}$/', $token ) ) {
        nocache_headers();
        wp_die( 'QR code invalide.', 'Scan commande', [ 'response' => 400 ] );
    }

    $orders = wc_get_orders( [
        'limit'      => 1,
        'type'       => 'shop_order',
        'meta_key'   => '_slc_facture_scan_token',
        'meta_value' => $token,
        'return'     => 'objects',
    ] );
    $order = ! empty( $orders ) ? $orders[0] : false;
    if ( ! $order || ! $order->get_meta( '_sl_collect_agence' ) ) {
        nocache_headers();
        wp_die( 'Commande introuvable ou QR expiré.', 'Scan commande', [ 'response' => 404 ] );
    }

    nocache_headers();
    status_header( 200 );
    $items = [];
    foreach ( $order->get_items( 'line_item' ) as $item ) {
        $meta = [];
        foreach ( $item->get_formatted_meta_data( '', true ) as $entry ) {
            $meta[] = wp_strip_all_tags( $entry->display_key . ': ' . $entry->display_value );
        }
        $items[] = [
            'name' => $item->get_name(),
            'qty'  => (int) $item->get_quantity(),
            'meta' => $meta,
            'total' => wp_strip_all_tags( wc_price( $item->get_total(), [ 'currency' => $order->get_currency() ] ) ),
        ];
    }
    $agence = function_exists( 'slc_agence_contact' )
        ? slc_agence_contact( (string) $order->get_meta( '_sl_collect_agence' ) )
        : [ 'nom' => (string) $order->get_meta( '_sl_collect_agence' ), 'adresse' => '', 'tel' => '' ];
    $status = wc_get_order_status_name( $order->get_status() );
    $name   = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
    ?>
    <!doctype html>
    <html <?php language_attributes(); ?>><head>
        <meta charset="<?php bloginfo( 'charset' ); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo esc_html( 'Commande ' . $order->get_order_number() ); ?></title>
        <style>
            body{margin:0;background:#f5f6f8;color:#202938;font:16px/1.45 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
            .wrap{max-width:620px;margin:0 auto;padding:20px 14px 36px}.head{background:#1d54a0;color:#fff;border-radius:14px 14px 0 0;padding:20px}.head h1{margin:0 0 5px;font-size:23px}.head p{margin:0;opacity:.9}.card{background:#fff;border:1px solid #e2e5ea;border-radius:14px;padding:17px;margin-top:12px;box-shadow:0 2px 8px rgba(20,35,60,.05)}.label{color:#687386;font-size:12px;text-transform:uppercase;font-weight:700;letter-spacing:.04em}.value{font-weight:700;margin-top:3px}.state{display:inline-block;background:#fff1f6;color:#d51f65;border-radius:999px;padding:5px 11px;font-weight:700}.item{border-top:1px solid #edf0f4;padding:12px 0}.item:first-child{border-top:0;padding-top:0}.line{display:flex;justify-content:space-between;gap:12px}.item-name{font-weight:700}.qty{color:#d51f65;font-weight:700}.meta{color:#687386;font-size:13px;margin-top:3px}.total{font-weight:800;color:#d51f65}.grand{display:flex;justify-content:space-between;border-top:2px solid #1d54a0;padding-top:12px;margin-top:4px;font-size:19px;font-weight:800}.hint{color:#687386;font-size:13px;margin:0}.ok{color:#16834b;font-weight:700}
        </style>
    </head><body><main class="wrap">
        <header class="head"><h1>Commande n° <?php echo esc_html( $order->get_order_number() ); ?></h1><p>Fiche de contrôle Santa Lucia</p></header>
        <section class="card"><div class="label">Statut</div><div class="value"><span class="state"><?php echo esc_html( $status ); ?></span></div><p class="hint" style="margin-top:10px">Vérifiez que cette commande correspond bien au code de retrait présenté par le client.</p></section>
        <section class="card"><div class="label">Client</div><div class="value"><?php echo esc_html( $name ?: 'Client' ); ?></div><?php if ( $order->get_billing_phone() ) : ?><div><?php echo esc_html( $order->get_billing_phone() ); ?></div><?php endif; ?></section>
        <section class="card"><div class="label">Agence de retrait</div><div class="value"><?php echo esc_html( 'Santa Lucia — ' . $agence['nom'] ); ?></div><?php if ( ! empty( $agence['adresse'] ) ) : ?><div><?php echo esc_html( $agence['adresse'] ); ?></div><?php endif; ?></section>
        <section class="card"><div class="label">Articles commandés</div>
            <?php foreach ( $items as $item ) : ?><div class="item"><div class="line"><div><span class="qty"><?php echo esc_html( $item['qty'] ); ?>×</span> <span class="item-name"><?php echo esc_html( $item['name'] ); ?></span></div><span class="total"><?php echo esc_html( $item['total'] ); ?></span></div><?php foreach ( $item['meta'] as $meta ) : ?><div class="meta"><?php echo esc_html( $meta ); ?></div><?php endforeach; ?></div><?php endforeach; ?>
            <div class="grand"><span>Total</span><span><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span></div>
        </section>
        <section class="card"><p class="hint"><span class="ok">✓ QR vérifié par le site</span><br>Cette fiche est générée à partir de la commande enregistrée en ligne.</p></section>
    </main></body></html>
    <?php
    exit;
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

    // Varnish met en cache les 404 par URL (gotcha connu de ce site) : sans
    // nocache_headers, une URL de facture visitee trop tot resterait « morte »
    // en cache et servirait l'erreur meme une fois la commande valide.
    if ( ! $order ) {
        nocache_headers();
        wp_die( 'Commande introuvable.', 'Facture', [ 'response' => 404 ] );
    }
    if ( ! slc_facture_can_view( $order, $key ) ) {
        nocache_headers();
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

    // ---- QR de contrôle en agence ----
    $qr_file = slc_facture_qr_file( $order );
    if ( $qr_file !== '' ) {
        $qr_y = $pdf->GetY();
        $pdf->SetFont( 'Helvetica', 'B', 9.5 );
        $pdf->SetTextColor( 29, 84, 160 );
        $pdf->Cell( 145, 6, $txt( 'SCAN QR — vérifier la commande en agence' ), 0, 1, 'L' );
        $pdf->SetFont( 'Helvetica', '', 8.5 );
        $pdf->SetTextColor( 90, 90, 90 );
        $pdf->MultiCell( 140, 4.5, $txt( 'Scannez ce code pour afficher les articles, les options, le client et le statut enregistrés sur le site.' ), 0, 'L' );
        $pdf->Image( $qr_file, 165, $qr_y, 30, 30, 'PNG' );
        $pdf->SetY( max( $pdf->GetY(), $qr_y + 30 ) + 2 );
        @unlink( $qr_file );
    }

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
