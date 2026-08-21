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
    if ( preg_match( '/^[a-f0-9]{32}$/', $token ) ) {
        return $token;
    }

    try {
        $token = bin2hex( random_bytes( 16 ) );
    } catch ( Exception $e ) {
        $token = substr( hash( 'sha256', wp_generate_password( 64, true, true ) . microtime( true ) ), 0, 32 );
    }

    $order->update_meta_data( '_slc_facture_scan_token', $token );
    $order->save();
    return $token;
}

/**
 * Identifiant court et lisible du colis associe a la commande.
 *
 * Le code est conserve sur la commande afin que la facture, le ticket de
 * preparation et la fiche ouverte par le QR affichent toujours le meme ID.
 */
function slc_package_code( $order ) {
    $package_code = strtoupper( (string) $order->get_meta( '_slc_package_code' ) );
    if ( preg_match( '/^SLC-[A-Z0-9]{3}-[A-Z0-9]{1,12}-[A-F0-9]{8}$/', $package_code ) ) {
        return $package_code;
    }

    $agency = strtoupper( remove_accents( (string) $order->get_meta( '_sl_collect_agence' ) ) );
    $agency = preg_replace( '/[^A-Z0-9]/', '', $agency );
    $agency = substr( $agency ?: 'AGC', 0, 3 );
    $agency = str_pad( $agency, 3, 'X' );

    $order_number = strtoupper( (string) $order->get_order_number() );
    $order_number = preg_replace( '/[^A-Z0-9]/', '', $order_number );
    $order_number = substr( $order_number ?: (string) $order->get_id(), -12 );

    $suffix       = strtoupper( substr( slc_facture_scan_token( $order ), 0, 8 ) );
    $package_code = 'SLC-' . $agency . '-' . $order_number . '-' . $suffix;

    $order->update_meta_data( '_slc_package_code', $package_code );
    $order->save();

    return $package_code;
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

/** URL de l'image QR ; le service ne reçoit qu'un jeton opaque. */
function slc_facture_qr_url( $order ) {
    return add_query_arg(
        [
            'size' => '300x300',
            'data' => slc_facture_scan_url( $order ),
        ],
        'https://api.qrserver.com/v1/create-qr-code/'
    );
}

/**
 * Génère temporairement l'image QR via un service spécialisé.
 * Seul le jeton opaque est transmis au service, jamais les données client.
 */
function slc_facture_qr_file( $order ) {
    $url = slc_facture_qr_url( $order );
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

    // Le jeton du QR identifie la commande, mais ne donne jamais a lui seul
    // le droit de consulter les donnees du client ou de valider la remise.
    if ( ! is_user_logged_in() ) {
        nocache_headers();
        auth_redirect();
        exit;
    }
    if ( ! function_exists( 'slc_order_is_staff_accessible' ) || ! slc_order_is_staff_accessible( $order ) ) {
        nocache_headers();
        wp_die( 'Accès réservé à l’équipe de l’agence de retrait.', 'Scan commande', [ 'response' => 403 ] );
    }

    if ( 'POST' === strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) && ! empty( $_POST['slc_scan_handover'] ) ) {
        check_admin_referer( 'slc_scan_handover_' . $order->get_id() );
        if ( ! $order->has_status( 'sl-prete' ) ) {
            wp_safe_redirect( add_query_arg( 'slc_scan_notice', 'not_ready', slc_facture_scan_url( $order ) ) );
            exit;
        }
        $user = wp_get_current_user();
        $order->update_meta_data( '_sl_collect_retire_by', $user->ID );
        $order->update_meta_data( '_slc_scan_handover_at', time() );
        $order->update_status( 'completed', 'Drop & Collect — remise validée par scan QR par ' . $user->user_login . '.' );
        wp_safe_redirect( add_query_arg( 'slc_scan_notice', 'done', slc_facture_scan_url( $order ) ) );
        exit;
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
    $status       = wc_get_order_status_name( $order->get_status() );
    $name         = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
    $package_code = slc_package_code( $order );
    ?>
    <!doctype html>
    <html <?php language_attributes(); ?>><head>
        <meta charset="<?php bloginfo( 'charset' ); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo esc_html( 'Colis ' . $package_code ); ?></title>
        <style>
            body{margin:0;background:#f5f6f8;color:#202938;font:16px/1.45 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
            .wrap{max-width:620px;margin:0 auto;padding:20px 14px 36px}.head{background:#1d54a0;color:#fff;border-radius:14px 14px 0 0;padding:20px}.head h1{margin:0 0 5px;font-size:23px}.head p{margin:0;opacity:.9}.card{background:#fff;border:1px solid #e2e5ea;border-radius:14px;padding:17px;margin-top:12px;box-shadow:0 2px 8px rgba(20,35,60,.05)}.package{border:2px solid #1d54a0;background:#f2f7ff}.package-code{display:block;margin-top:6px;color:#d51f65;font:800 21px/1.25 ui-monospace,SFMono-Regular,Consolas,monospace;overflow-wrap:anywhere}.label{color:#687386;font-size:12px;text-transform:uppercase;font-weight:700;letter-spacing:.04em}.value{font-weight:700;margin-top:3px}.state{display:inline-block;background:#fff1f6;color:#d51f65;border-radius:999px;padding:5px 11px;font-weight:700}.item{border-top:1px solid #edf0f4;padding:12px 0}.item:first-child{border-top:0;padding-top:0}.line{display:flex;justify-content:space-between;gap:12px}.item-name{font-weight:700}.qty{color:#d51f65;font-weight:700}.meta{color:#687386;font-size:13px;margin-top:3px}.total{font-weight:800;color:#d51f65}.grand{display:flex;justify-content:space-between;border-top:2px solid #1d54a0;padding-top:12px;margin-top:4px;font-size:19px;font-weight:800}.hint{color:#687386;font-size:13px;margin:0}.ok{color:#16834b;font-weight:700}.notice{padding:12px;border-radius:10px;margin:0 0 12px;background:#e8f7ee;color:#126a3b;font-weight:700}.notice.bad{background:#fff0ef;color:#b42318}.handover{width:100%;border:0;border-radius:11px;background:#16834b;color:#fff;padding:14px;font:inherit;font-weight:800;cursor:pointer}.handover[disabled]{background:#aeb5c0;cursor:not-allowed}
        </style>
    </head><body><main class="wrap">
        <?php $scan_notice = sanitize_key( wp_unslash( $_GET['slc_scan_notice'] ?? '' ) ); ?>
        <?php if ( 'done' === $scan_notice ) : ?><div class="notice">✓ Remise validée. La commande est maintenant terminée.</div><?php elseif ( 'not_ready' === $scan_notice ) : ?><div class="notice bad">Cette commande n'est pas encore prête pour la remise.</div><?php endif; ?>
        <header class="head"><h1>Colis identifié</h1><p>Fiche de contrôle Santa Lucia</p></header>
        <section class="card package"><div class="label">Identifiant du colis</div><code class="package-code"><?php echo esc_html( $package_code ); ?></code><p class="hint" style="margin-top:8px">Commande n° <?php echo esc_html( $order->get_order_number() ); ?> — vérifiez que cet identifiant correspond à celui imprimé sur le paquet.</p></section>
        <section class="card"><div class="label">Statut</div><div class="value"><span class="state"><?php echo esc_html( $status ); ?></span></div><p class="hint" style="margin-top:10px">Vérifiez que cette commande correspond bien au code de retrait présenté par le client.</p></section>
        <section class="card"><div class="label">Client</div><div class="value"><?php echo esc_html( $name ?: 'Client' ); ?></div><?php if ( $order->get_billing_phone() ) : ?><div><?php echo esc_html( $order->get_billing_phone() ); ?></div><?php endif; ?></section>
        <?php if ( $order->get_meta( '_slc_collector_name' ) ) : ?><section class="card"><div class="label">Mandataire autorisé</div><div class="value"><?php echo esc_html( $order->get_meta( '_slc_collector_name' ) ); ?></div><div><?php echo esc_html( $order->get_meta( '_slc_collector_phone' ) ); ?></div><p class="hint" style="margin-top:8px">Vérifiez sa pièce d'identité avant la remise.</p></section><?php endif; ?>
        <section class="card"><div class="label">Agence de retrait</div><div class="value"><?php echo esc_html( 'Santa Lucia — ' . $agence['nom'] ); ?></div><?php if ( ! empty( $agence['adresse'] ) ) : ?><div><?php echo esc_html( $agence['adresse'] ); ?></div><?php endif; ?></section>
        <section class="card"><div class="label">Articles commandés</div>
            <?php foreach ( $items as $item ) : ?><div class="item"><div class="line"><div><span class="qty"><?php echo esc_html( $item['qty'] ); ?>×</span> <span class="item-name"><?php echo esc_html( $item['name'] ); ?></span></div><span class="total"><?php echo esc_html( $item['total'] ); ?></span></div><?php foreach ( $item['meta'] as $meta ) : ?><div class="meta"><?php echo esc_html( $meta ); ?></div><?php endforeach; ?></div><?php endforeach; ?>
            <div class="grand"><span>Total</span><span><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span></div>
        </section>
        <section class="card"><p class="hint"><span class="ok">✓ QR d’identification vérifié par le site</span><br>Ce QR correspond au colis <strong><?php echo esc_html( $package_code ); ?></strong> et à la commande enregistrée en ligne.</p></section>
        <section class="card">
            <?php if ( $order->has_status( 'sl-prete' ) ) : ?>
                <form method="post" onsubmit="return confirm('Confirmer que la commande a bien été remise au client ou à son mandataire autorisé ?');">
                    <?php wp_nonce_field( 'slc_scan_handover_' . $order->get_id() ); ?>
                    <input type="hidden" name="slc_scan_handover" value="1">
                    <button class="handover">Valider la remise de la commande</button>
                </form>
            <?php elseif ( $order->has_status( 'completed' ) ) : ?>
                <button class="handover" disabled>Commande déjà remise</button>
            <?php else : ?>
                <button class="handover" disabled>Remise impossible — commande non prête</button>
            <?php endif; ?>
        </section>
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

    $code         = (string) $order->get_meta( '_sl_collect_code' );
    $slug         = (string) $order->get_meta( '_sl_collect_agence' );
    $agence       = function_exists( 'slc_agence_contact' ) ? slc_agence_contact( $slug ) : [ 'nom' => $slug, 'adresse' => '', 'tel' => '' ];
    $payee        = $order->is_paid();
    $package_code = slc_package_code( $order );

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

    // ---- QR d'identification du colis ----
    $qr_file = slc_facture_qr_file( $order );
    $qr_y    = $pdf->GetY();
    $pdf->SetFillColor( 242, 247, 255 );
    $pdf->SetDrawColor( 29, 84, 160 );
    $pdf->SetLineWidth( 0.4 );
    $pdf->Rect( 10, $qr_y, 190, 38, 'DF' );
    $pdf->SetXY( 15, $qr_y + 4 );
    $pdf->SetFont( 'Helvetica', 'B', 10 );
    $pdf->SetTextColor( 29, 84, 160 );
    $pdf->Cell( 142, 6, $txt( 'QR D’IDENTIFICATION DU COLIS' ), 0, 1, 'L' );
    $pdf->SetX( 15 );
    $pdf->SetFont( 'Helvetica', '', 8.5 );
    $pdf->SetTextColor( 70, 80, 95 );
    $pdf->MultiCell( 142, 4.3, $txt( 'Scannez ce code à l’agence pour retrouver la commande, contrôler son contenu et valider la remise du bon paquet.' ), 0, 'L' );
    $pdf->SetX( 15 );
    $pdf->SetFont( 'Courier', 'B', 12.5 );
    $pdf->SetTextColor( 213, 31, 101 );
    $pdf->Cell( 142, 7, $txt( 'ID COLIS : ' . $package_code ), 0, 1, 'L' );
    if ( $qr_file !== '' ) {
        $pdf->Image( $qr_file, 164, $qr_y + 3, 32, 32, 'PNG' );
        @unlink( $qr_file );
    } else {
        $pdf->SetXY( 164, $qr_y + 10 );
        $pdf->SetFont( 'Helvetica', 'B', 7.5 );
        $pdf->SetTextColor( 150, 60, 70 );
        $pdf->MultiCell( 32, 4, $txt( "QR indisponible\nUtiliser l’ID colis" ), 0, 'C' );
    }
    $pdf->SetY( $qr_y + 41 );

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
    if ( function_exists( 'slc_pickup_slot_label' ) ) {
        $bloc_agence .= "\n" . 'Retrait : ' . slc_pickup_slot_label( $order );
    }

    $bloc_client = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
    if ( $order->get_billing_phone() ) {
        $bloc_client .= "\n" . 'Tél. ' . $order->get_billing_phone();
    }
    if ( $order->get_billing_email() ) {
        $bloc_client .= "\n" . $order->get_billing_email();
    }
    $collector = (string) $order->get_meta( '_slc_collector_name' );
    if ( $collector !== '' ) {
        $bloc_client .= "\n" . 'Mandataire : ' . $collector . ' (' . $order->get_meta( '_slc_collector_phone' ) . ')';
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
