<?php
/**
 * Campagne OMITLAND ODZA : première expérience offerte.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SL_OMTLAND_CLAIM_TYPE = 'sl_omtland_claim';
const SL_OMTLAND_PROMO_CODE = 'OMIT237XVZ';
const SL_OMTLAND_START_DATE = '2026-09-19';
const SL_OMTLAND_END_DATE = '2026-10-30';
const SL_OMTLAND_CODE_TYPE = 'sl_omitland_code';

function sl_omtland_register_claim_type() {
	register_post_type( SL_OMTLAND_CLAIM_TYPE, array(
		'labels' => array(
			'name'          => 'Bonus OMITLAND',
			'singular_name' => 'Réclamation OMITLAND',
			'menu_name'     => 'Bonus OMITLAND',
			'all_items'     => 'Réclamations',
			'edit_item'     => 'Traiter la réclamation',
		),
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'menu_icon'           => 'dashicons-games',
		'supports'            => array( 'title' ),
		'capability_type'     => 'post',
		'map_meta_cap'        => true,
		'exclude_from_search' => true,
	) );
}
add_action( 'init', 'sl_omtland_register_claim_type', 5 );

function sl_omitland_register_code_type() {
	register_post_type( SL_OMTLAND_CODE_TYPE, array(
		'labels' => array(
			'name'          => 'Codes Omitland',
			'singular_name' => 'Code Omitland',
			'menu_name'     => 'Codes promotion',
			'all_items'     => 'Tous les codes',
		),
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => 'edit.php?post_type=' . SL_OMTLAND_CLAIM_TYPE,
		'supports'            => array( 'title' ),
		'exclude_from_search' => true,
	) );
}
add_action( 'init', 'sl_omitland_register_code_type', 5 );

function sl_omitland_get_setting( $key, $fallback = '' ) {
	$value = get_option( 'sl_omitland_' . $key, $fallback );
	return '' !== $value && null !== $value ? $value : $fallback;
}

function sl_omitland_current_code() {
	return strtoupper( preg_replace( '/[^A-Z0-9]/', '', (string) sl_omitland_get_setting( 'active_code', SL_OMTLAND_PROMO_CODE ) ) );
}

function sl_omitland_start_date() {
	return (string) sl_omitland_get_setting( 'start_date', SL_OMTLAND_START_DATE );
}

function sl_omitland_end_date() {
	return (string) sl_omitland_get_setting( 'end_date', SL_OMTLAND_END_DATE );
}

function sl_omitland_code_post( $code ) {
	$posts = get_posts( array(
		'post_type'   => SL_OMTLAND_CODE_TYPE,
		'post_status' => 'any',
		'numberposts' => 1,
		'fields'      => 'all',
		'title'       => $code,
	) );
	return $posts ? $posts[0] : null;
}

function sl_omitland_is_code_valid( $code ) {
	$code = strtoupper( preg_replace( '/[^A-Z0-9]/', '', (string) $code ) );
	if ( ! $code ) {
		return false;
	}
	$code_post = sl_omitland_code_post( $code );
	$start = $code_post ? get_post_meta( $code_post->ID, '_sl_omitland_code_start', true ) : sl_omitland_start_date();
	$end = $code_post ? get_post_meta( $code_post->ID, '_sl_omitland_code_end', true ) : sl_omitland_end_date();
	$status = $code_post ? get_post_meta( $code_post->ID, '_sl_omitland_code_status', true ) : 'active';
	$today = current_time( 'Y-m-d' );
	return 'active' === ( $status ?: 'active' ) && $today >= $start && $today <= $end;
}

function sl_omitland_ensure_current_code() {
	$code = sl_omitland_current_code();
	if ( ! $code || sl_omitland_code_post( $code ) ) {
		return;
	}
	$post_id = wp_insert_post( array( 'post_type' => SL_OMTLAND_CODE_TYPE, 'post_status' => 'private', 'post_title' => $code ), true );
	if ( ! is_wp_error( $post_id ) ) {
		update_post_meta( $post_id, '_sl_omitland_code_start', sl_omitland_start_date() );
		update_post_meta( $post_id, '_sl_omitland_code_end', sl_omitland_end_date() );
		update_post_meta( $post_id, '_sl_omitland_code_status', 'active' );
		update_post_meta( $post_id, '_sl_omitland_code_clicks', 0 );
		update_post_meta( $post_id, '_sl_omitland_code_claims', 0 );
		update_post_meta( $post_id, '_sl_omitland_code_uses', 0 );
	}
}
add_action( 'init', 'sl_omitland_ensure_current_code', 35 );

function sl_omitland_promotion_is_open() {
	$today = current_time( 'Y-m-d' );
	return $today >= sl_omitland_start_date() && $today <= sl_omitland_end_date();
}

function sl_omitland_track_visit() {
	$code = isset( $_GET['promo'] ) ? strtoupper( preg_replace( '/[^A-Z0-9]/', '', sanitize_text_field( wp_unslash( $_GET['promo'] ) ) ) ) : '';
	$ref = isset( $_GET['ref'] ) ? strtoupper( preg_replace( '/[^A-Z0-9]/', '', sanitize_text_field( wp_unslash( $_GET['ref'] ) ) ) ) : '';
	if ( ! $code && $ref ) {
		$code = $ref;
	}
	if ( ! $code || ! sl_omitland_is_code_valid( $code ) ) {
		return;
	}
	$cookie_name = 'sl_omitland_click_' . strtolower( $code );
	if ( isset( $_COOKIE[ $cookie_name ] ) ) {
		return;
	}
	$code_post = sl_omitland_code_post( $code );
	if ( $code_post ) {
		$clicks = (int) get_post_meta( $code_post->ID, '_sl_omitland_code_clicks', true ) + 1;
		update_post_meta( $code_post->ID, '_sl_omitland_code_clicks', $clicks );
	}
	setcookie( $cookie_name, '1', time() + MONTH_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true );
}

function sl_omitland_referrer_code() {
	$ref = isset( $_GET['ref'] ) ? sanitize_text_field( wp_unslash( $_GET['ref'] ) ) : '';
	return strtoupper( preg_replace( '/[^A-Z0-9]/', '', $ref ) );
}

function sl_omtland_ensure_campaign_page() {
	$page_id = (int) get_option( 'sl_omtland_campaign_page_id' );

	$page = get_page_by_path( 'bonus-omtland-odza', OBJECT, 'page' );
	if ( ! $page ) {
		$page_id = wp_insert_post( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => '50 unités offertes chez OMITLAND ODZA',
			'post_name'    => 'bonus-omtland-odza',
			'post_content' => '[sl_omtland_bonus]',
		) );
	} else {
		$page_id = wp_update_post( array(
			'ID'           => $page->ID,
			'post_status'  => 'publish',
			'post_title'   => '50 unités offertes chez OMITLAND ODZA',
			'post_content' => '[sl_omtland_bonus]',
		) );
	}

	if ( $page_id && ! is_wp_error( $page_id ) ) {
		update_option( 'sl_omtland_campaign_page_id', (int) $page_id );
	}
}
add_action( 'init', 'sl_omtland_ensure_campaign_page', 30 );

function sl_omtland_campaign_body_class( $classes ) {
	if ( is_page( 'bonus-omtland-odza' ) ) {
		$classes[] = 'sl-omtland-campaign';
	}
	return $classes;
}
add_filter( 'body_class', 'sl_omtland_campaign_body_class' );

function sl_omtland_campaign_assets() {
	if ( ! is_page( 'bonus-omtland-odza' ) ) {
		return;
	}
	wp_enqueue_style( 'sl-omtland-bonus', SL_AGENCES_URL . 'assets/css/omtland-bonus-v2.css', array(), '1.0.2' );
}
add_action( 'wp_enqueue_scripts', 'sl_omtland_campaign_assets', 110 );

function sl_omtland_promotion_is_open() {
	return sl_omitland_promotion_is_open();
}

function sl_omtland_bonus_shortcode() {
	sl_omitland_track_visit();
	$state = isset( $_GET['claim'] ) ? sanitize_key( wp_unslash( $_GET['claim'] ) ) : '';
	$reference = isset( $_GET['ref'] ) ? sanitize_text_field( wp_unslash( $_GET['ref'] ) ) : '';
	$requested_code = isset( $_GET['promo'] ) ? strtoupper( preg_replace( '/[^A-Z0-9]/', '', sanitize_text_field( wp_unslash( $_GET['promo'] ) ) ) ) : '';
	$active_code = $requested_code && sl_omitland_is_code_valid( $requested_code ) ? $requested_code : sl_omitland_current_code();
	$hero = SL_AGENCES_URL . 'assets/images/omtland-bonus-hero.png';

	ob_start();
	?>
	<main class="sl-omtland-page">
		<section class="sl-omtland-hero" style="background-image:url('<?php echo esc_url( $hero ); ?>')">
			<div class="sl-omtland-hero-inner">
				<p class="sl-omtland-eyebrow">OMITLAND ODZA</p>
				<h1>Ta première aventure VR est offerte.</h1>
				<p class="sl-omtland-lead">Reçois <strong>50 unités gratuites</strong>, soit l'accès à une machine ou à un jeu de ton choix.</p>
				<a class="sl-omtland-cta" href="#reclamer">Réclamer mes 50 unités</a>
			</div>
		</section>

		<section class="sl-omtland-offer" aria-label="Détails de l'offre">
			<div class="sl-omtland-inner">
				<div class="sl-omtland-intro">
					<div><p class="sl-omtland-kicker">Bonus de bienvenue</p><h2>Une première expérience, sans rien payer.</h2></div>
					<p>Tu n'as encore jamais joué chez OMITLAND ODZA ? Rejoins notre communauté, remplis le formulaire et présente le code promo à ton arrivée. Après vérification, une carte de <strong>50 unités gratuites</strong> te sera remise.</p>
				</div>
				<div class="sl-omtland-steps">
					<div><span>01</span><h3>Remplis le formulaire</h3><p>Enregistre ton nom et ton numéro de téléphone.</p></div>
					<div><span>02</span><h3>Présente ton code</h3><p>Montre le code <strong><?php echo esc_html( $active_code ); ?></strong> à OMITLAND ODZA.</p></div>
					<div><span>03</span><h3>Profite de ton jeu</h3><p>Reçois ta carte de 50 unités après vérification.</p></div>
				</div>
			</div>
		</section>

		<section class="sl-omtland-value">
			<div class="sl-omtland-inner sl-omtland-value-grid">
				<div><p class="sl-omtland-kicker">Encore plus de jeu</p><h2>Tes unités sont doublées pendant la promotion.</h2><p>Avec le même budget, découvre deux fois plus d'expériences.</p></div>
				<div class="sl-omtland-compare" aria-label="Comparatif de l'offre">
					<div><span>Avant</span><strong>2 500 FCFA</strong><b>50 unités · 1 jeu</b></div>
					<div class="is-current"><span>Maintenant</span><strong>2 500 FCFA</strong><b>100 unités · 2 jeux</b></div>
				</div>
			</div>
		</section>

		<section id="reclamer" class="sl-omtland-claim">
			<div class="sl-omtland-inner sl-omtland-claim-grid">
				<div class="sl-omtland-claim-copy">
					<p class="sl-omtland-kicker"><?php echo esc_html( wp_date( 'd/m/Y', strtotime( sl_omitland_start_date() ) ) . ' - ' . wp_date( 'd/m/Y', strtotime( sl_omitland_end_date() ) ) ); ?></p>
					<h2>Réclame tes 50 unités.</h2>
					<p>Offre réservée aux nouveaux membres de la communauté, valable une seule fois et uniquement chez OMITLAND ODZA.</p>
					<dl><div><dt>Lieu</dt><dd>OMITLAND ODZA</dd></div><div><dt>Code promotionnel</dt><dd><?php echo esc_html( $active_code ); ?></dd></div><div><dt>Bonus</dt><dd>50 unités gratuites</dd></div></dl>
				</div>
				<div class="sl-omtland-form-wrap">
					<?php if ( 'success' === $state ) : ?>
						<div class="sl-omtland-notice is-success" role="status"><strong>Ta demande est enregistrée.</strong><p>Présente le code <b><?php echo esc_html( $active_code ); ?></b> et ta référence <b><?php echo esc_html( $reference ); ?></b> à ton arrivée chez OMITLAND ODZA.</p></div>
					<?php elseif ( 'duplicate' === $state ) : ?>
						<div class="sl-omtland-notice is-error" role="alert"><strong>Ce numéro a déjà été utilisé.</strong><p>Le bonus de bienvenue ne peut être réclamé qu'une seule fois.</p></div>
					<?php elseif ( 'invalid' === $state ) : ?>
						<div class="sl-omtland-notice is-error" role="alert"><strong>La demande n'a pas pu être envoyée.</strong><p>Vérifie les informations et le code promotionnel.</p></div>
					<?php endif; ?>

					<?php if ( sl_omtland_promotion_is_open() && 'success' !== $state ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sl-omtland-form">
						<input type="hidden" name="action" value="sl_omtland_claim">
						<?php wp_nonce_field( 'sl_omtland_claim', 'sl_omtland_nonce' ); ?>
						<div class="sl-omtland-hp" aria-hidden="true"><label>Site web<input name="website" tabindex="-1" autocomplete="off"></label></div>
						<label>Nom complet *<input name="full_name" required autocomplete="name" maxlength="120"></label>
						<label>Numéro WhatsApp *<input name="phone" required type="tel" autocomplete="tel" inputmode="tel" placeholder="Ex. 6 99 00 00 00"></label>
						<label>Adresse e-mail<input name="email" type="email" autocomplete="email"></label>
						<label>Comment as-tu découvert OMITLAND ?<select name="source"><option value="">Choisir</option><option>WhatsApp</option><option>Facebook</option><option>Instagram</option><option>TikTok</option><option>Un proche</option><option>Autre</option></select></label>
						<label>Code promotionnel *<input name="promo_code" required value="<?php echo esc_attr( $active_code ); ?>" autocomplete="off"></label>
						<label class="sl-omtland-check"><input type="checkbox" name="first_visit" value="1" required><span>Je confirme n'avoir jamais bénéficié d'une expérience chez OMITLAND ODZA et j'accepte le traitement de mes informations pour vérifier mon éligibilité.</span></label>
						<button type="submit">Réclamer mes 50 unités</button>
					</form>
					<?php elseif ( ! sl_omtland_promotion_is_open() ) : ?>
						<div class="sl-omtland-notice is-error"><strong>La promotion n'est pas ouverte.</strong><p>Elle est valable du <?php echo esc_html( wp_date( 'd/m/Y', strtotime( sl_omitland_start_date() ) ) ); ?> au <?php echo esc_html( wp_date( 'd/m/Y', strtotime( sl_omitland_end_date() ) ) ); ?>.</p></div>
					<?php endif; ?>
				</div>
			</div>
		</section>
	</main>
	<?php
	return ob_get_clean();
}
add_shortcode( 'sl_omtland_bonus', 'sl_omtland_bonus_shortcode' );

function sl_omtland_normalize_phone( $phone ) {
	$digits = preg_replace( '/\D+/', '', (string) $phone );
	if ( str_starts_with( $digits, '00' ) ) {
		$digits = substr( $digits, 2 );
	}
	if ( 9 === strlen( $digits ) && in_array( $digits[0], array( '6', '2' ), true ) ) {
		$digits = '237' . $digits;
	}
	return strlen( $digits ) >= 11 && strlen( $digits ) <= 15 ? $digits : '';
}

function sl_omtland_claim_redirect( $state, $reference = '' ) {
	$url = home_url( '/bonus-omtland-odza/' );
	$args = array( 'claim' => $state );
	if ( $reference ) {
		$args['ref'] = $reference;
	}
	wp_safe_redirect( add_query_arg( $args, $url ) . '#reclamer' );
	exit;
}

function sl_omtland_handle_claim() {
	if ( ! isset( $_POST['sl_omtland_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sl_omtland_nonce'] ) ), 'sl_omtland_claim' ) ) {
		sl_omtland_claim_redirect( 'invalid' );
	}
	if ( ! empty( $_POST['website'] ) || ! sl_omtland_promotion_is_open() ) {
		sl_omtland_claim_redirect( 'invalid' );
	}

	$name = isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '';
	$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
	$phone_key = sl_omtland_normalize_phone( $phone );
	$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$source = isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : '';
	$code = isset( $_POST['promo_code'] ) ? strtoupper( preg_replace( '/\s+/', '', sanitize_text_field( wp_unslash( $_POST['promo_code'] ) ) ) ) : '';
	$first_visit = isset( $_POST['first_visit'] ) && '1' === $_POST['first_visit'];

	if ( ! $name || ! $phone_key || ! sl_omitland_is_code_valid( $code ) || ! $first_visit || ( $email && ! is_email( $email ) ) ) {
		sl_omtland_claim_redirect( 'invalid' );
	}

	$existing = get_posts( array(
		'post_type'   => SL_OMTLAND_CLAIM_TYPE,
		'post_status' => array( 'private', 'publish', 'pending', 'draft' ),
		'numberposts' => 1,
		'fields'      => 'ids',
		'meta_key'    => '_sl_omtland_phone_key',
		'meta_value'  => $phone_key,
	) );
	if ( $existing ) {
		sl_omtland_claim_redirect( 'duplicate' );
	}

	$post_id = wp_insert_post( array(
		'post_type'   => SL_OMTLAND_CLAIM_TYPE,
		'post_status' => 'private',
		'post_title'  => $name . ' - ' . $phone,
	), true );
	if ( is_wp_error( $post_id ) ) {
		sl_omtland_claim_redirect( 'invalid' );
	}

	$reference = 'OMT-' . str_pad( (string) $post_id, 5, '0', STR_PAD_LEFT );
	update_post_meta( $post_id, '_sl_omtland_name', $name );
	update_post_meta( $post_id, '_sl_omtland_phone', $phone );
	update_post_meta( $post_id, '_sl_omtland_phone_key', $phone_key );
	update_post_meta( $post_id, '_sl_omtland_email', $email );
	update_post_meta( $post_id, '_sl_omtland_source', $source );
	update_post_meta( $post_id, '_sl_omitland_code', $code );
	update_post_meta( $post_id, '_sl_omitland_referrer', sl_omitland_referrer_code() );
	update_post_meta( $post_id, '_sl_omtland_reference', $reference );
	update_post_meta( $post_id, '_sl_omtland_status', 'pending' );
	$code_post = sl_omitland_code_post( $code );
	if ( $code_post ) {
		update_post_meta( $code_post->ID, '_sl_omitland_code_claims', (int) get_post_meta( $code_post->ID, '_sl_omitland_code_claims', true ) + 1 );
	}

	$subject = 'Nouvelle réclamation bonus OMITLAND - ' . $reference;
	$message = "Nom : {$name}\nTéléphone : {$phone}\nE-mail : " . ( $email ?: 'Non renseigné' ) . "\nSource : " . ( $source ?: 'Non renseignée' ) . "\nCode : {$code}\nRéférence : {$reference}";
	wp_mail( get_option( 'admin_email' ), $subject, $message );
	sl_omtland_claim_redirect( 'success', $reference );
}
add_action( 'admin_post_nopriv_sl_omtland_claim', 'sl_omtland_handle_claim' );
add_action( 'admin_post_sl_omtland_claim', 'sl_omtland_handle_claim' );

function sl_omitland_claim_whatsapp_url( $post_id ) {
	$phone = sl_omtland_normalize_phone( get_post_meta( $post_id, '_sl_omtland_phone', true ) );
	if ( ! $phone ) {
		return '';
	}
	$name = get_post_meta( $post_id, '_sl_omtland_name', true );
	$code = get_post_meta( $post_id, '_sl_omitland_code', true ) ?: sl_omitland_current_code();
	$reference = get_post_meta( $post_id, '_sl_omtland_reference', true );
	$message = 'Bonjour *' . $name . '*,\n\n' .
		'Bonne nouvelle : tes *50 points gratuits* sont disponibles chez *OMITLAND ODZA*.\n\n' .
		'*Code promotionnel :* ' . $code . '\n' .
		'*Référence :* ' . $reference . '\n\n' .
		'Pour en profiter, présente-toi à OMITLAND ODZA avec ce message ouvert dans ton WhatsApp et montre ton code à l’équipe. Après vérification, ta carte de 50 points te sera remise.\n\n' .
		'À quelle date prévois-tu de venir jouer ? Réponds directement à ce message pour nous prévenir.\n\n' .
		'*OMITLAND ODZA*';
	return 'https://wa.me/' . rawurlencode( $phone ) . '?text=' . rawurlencode( $message );
}

function sl_omtland_claim_meta_boxes() {
	add_meta_box( 'sl-omtland-claim', 'Détails de la réclamation', 'sl_omtland_render_claim_box', SL_OMTLAND_CLAIM_TYPE, 'normal', 'high' );
}
add_action( 'add_meta_boxes_' . SL_OMTLAND_CLAIM_TYPE, 'sl_omtland_claim_meta_boxes' );

function sl_omtland_render_claim_box( $post ) {
	wp_nonce_field( 'sl_omtland_save_claim', 'sl_omtland_admin_nonce' );
	$fields = array(
		'Nom'       => get_post_meta( $post->ID, '_sl_omtland_name', true ),
		'Téléphone' => get_post_meta( $post->ID, '_sl_omtland_phone', true ),
		'E-mail'    => get_post_meta( $post->ID, '_sl_omtland_email', true ),
		'Source'    => get_post_meta( $post->ID, '_sl_omtland_source', true ),
		'Code'      => get_post_meta( $post->ID, '_sl_omitland_code', true ),
		'Référent'  => get_post_meta( $post->ID, '_sl_omitland_referrer', true ),
		'Référence' => get_post_meta( $post->ID, '_sl_omtland_reference', true ),
	);
	echo '<table class="widefat striped"><tbody>';
	foreach ( $fields as $label => $value ) {
		echo '<tr><th style="width:160px">' . esc_html( $label ) . '</th><td>' . esc_html( $value ?: '—' ) . '</td></tr>';
	}
	echo '</tbody></table>';
	$status = get_post_meta( $post->ID, '_sl_omtland_status', true ) ?: 'pending';
	echo '<p><label for="sl_omtland_status"><strong>Statut</strong></label><br><select id="sl_omtland_status" name="sl_omtland_status">';
	foreach ( array( 'pending' => 'À vérifier', 'verified' => 'Éligible', 'redeemed' => '50 unités remises', 'rejected' => 'Refusée' ) as $key => $label ) {
		echo '<option value="' . esc_attr( $key ) . '" ' . selected( $status, $key, false ) . '>' . esc_html( $label ) . '</option>';
	}
	echo '</select></p>';
	$whatsapp_url = sl_omitland_claim_whatsapp_url( $post->ID );
	if ( $whatsapp_url ) {
		echo '<p><a class="button button-primary" href="' . esc_url( $whatsapp_url ) . '" target="_blank" rel="noopener noreferrer">Ouvrir le message WhatsApp</a></p>';
	}
}

function sl_omtland_save_claim( $post_id ) {
	if ( ! isset( $_POST['sl_omtland_admin_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sl_omtland_admin_nonce'] ) ), 'sl_omtland_save_claim' ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$old_status = get_post_meta( $post_id, '_sl_omtland_status', true ) ?: 'pending';
	$allowed = array( 'pending', 'verified', 'redeemed', 'rejected' );
	$status = isset( $_POST['sl_omtland_status'] ) ? sanitize_key( wp_unslash( $_POST['sl_omtland_status'] ) ) : 'pending';
	if ( in_array( $status, $allowed, true ) ) {
		update_post_meta( $post_id, '_sl_omtland_status', $status );
		if ( 'redeemed' === $status && 'redeemed' !== $old_status ) {
			$code_post = sl_omitland_code_post( get_post_meta( $post_id, '_sl_omitland_code', true ) );
			if ( $code_post ) {
				update_post_meta( $code_post->ID, '_sl_omitland_code_uses', (int) get_post_meta( $code_post->ID, '_sl_omitland_code_uses', true ) + 1 );
			}
		}
	}
}
add_action( 'save_post_' . SL_OMTLAND_CLAIM_TYPE, 'sl_omtland_save_claim' );

function sl_omtland_claim_columns( $columns ) {
	return array(
		'cb'           => $columns['cb'],
		'title'        => 'Participant',
		'sl_phone'     => 'Téléphone',
		'sl_reference' => 'Référence',
		'sl_status'    => 'Statut',
		'date'         => 'Demandée le',
	);
}
add_filter( 'manage_' . SL_OMTLAND_CLAIM_TYPE . '_posts_columns', 'sl_omtland_claim_columns' );

function sl_omtland_claim_column( $column, $post_id ) {
	if ( 'sl_phone' === $column ) {
		echo esc_html( get_post_meta( $post_id, '_sl_omtland_phone', true ) );
	} elseif ( 'sl_reference' === $column ) {
		echo '<strong>' . esc_html( get_post_meta( $post_id, '_sl_omtland_reference', true ) ) . '</strong>';
	} elseif ( 'sl_status' === $column ) {
		$status = get_post_meta( $post_id, '_sl_omtland_status', true ) ?: 'pending';
		$labels = array( 'pending' => 'À vérifier', 'verified' => 'Éligible', 'redeemed' => '50 unités remises', 'rejected' => 'Refusée' );
		echo esc_html( $labels[ $status ] ?? $status );
	}
}
add_action( 'manage_' . SL_OMTLAND_CLAIM_TYPE . '_posts_custom_column', 'sl_omtland_claim_column', 10, 2 );

function sl_omitland_register_cron_schedule( $schedules ) {
	$schedules['sl_omitland_4days'] = array( 'interval' => 4 * DAY_IN_SECONDS, 'display' => 'Tous les 4 jours' );
	return $schedules;
}
add_filter( 'cron_schedules', 'sl_omitland_register_cron_schedule' );

function sl_omitland_update_schedule() {
	while ( $timestamp = wp_next_scheduled( 'sl_omitland_rotate_code' ) ) {
		wp_unschedule_event( $timestamp, 'sl_omitland_rotate_code' );
	}
	if ( '1' === (string) sl_omitland_get_setting( 'auto_enabled', '0' ) && '1' !== (string) sl_omitland_get_setting( 'paused', '0' ) ) {
		$recurrence = '4days' === sl_omitland_get_setting( 'cadence', 'weekly' ) ? 'sl_omitland_4days' : 'weekly';
		wp_schedule_event( time() + MINUTE_IN_SECONDS, $recurrence, 'sl_omitland_rotate_code' );
	}
}

function sl_omitland_generate_code_value() {
	return 'OMIT' . strtoupper( wp_generate_password( 6, false, false ) );
}

function sl_omitland_create_code( $code, $start, $end, $label = '' ) {
	$code = strtoupper( preg_replace( '/[^A-Z0-9]/', '', (string) $code ) );
	if ( ! $code || sl_omitland_code_post( $code ) ) {
		return 0;
	}
	$post_id = wp_insert_post( array( 'post_type' => SL_OMTLAND_CODE_TYPE, 'post_status' => 'private', 'post_title' => $code ), true );
	if ( is_wp_error( $post_id ) ) {
		return 0;
	}
	update_post_meta( $post_id, '_sl_omitland_code_start', $start );
	update_post_meta( $post_id, '_sl_omitland_code_end', $end );
	update_post_meta( $post_id, '_sl_omitland_code_status', 'active' );
	update_post_meta( $post_id, '_sl_omitland_code_label', sanitize_text_field( $label ) );
	update_post_meta( $post_id, '_sl_omitland_code_clicks', 0 );
	update_post_meta( $post_id, '_sl_omitland_code_claims', 0 );
	update_post_meta( $post_id, '_sl_omitland_code_uses', 0 );
	return $post_id;
}

function sl_omitland_rotate_code() {
	if ( '1' !== (string) sl_omitland_get_setting( 'auto_enabled', '0' ) || '1' === (string) sl_omitland_get_setting( 'paused', '0' ) ) {
		return;
	}
	$code = sl_omitland_generate_code_value();
	if ( sl_omitland_create_code( $code, current_time( 'Y-m-d' ), sl_omitland_end_date(), 'Généré automatiquement' ) ) {
		update_option( 'sl_omitland_active_code', $code );
	}
}
add_action( 'sl_omitland_rotate_code', 'sl_omitland_rotate_code' );

function sl_omitland_admin_menu() {
	add_submenu_page( 'edit.php?post_type=' . SL_OMTLAND_CLAIM_TYPE, 'Tableau de bord Omitland', 'Tableau de bord', 'manage_options', 'sl-omitland-dashboard', 'sl_omitland_render_dashboard' );
}
add_action( 'admin_menu', 'sl_omitland_admin_menu', 20 );

function sl_omitland_admin_redirect( $notice = '' ) {
	$url = add_query_arg( array( 'post_type' => SL_OMTLAND_CLAIM_TYPE, 'page' => 'sl-omitland-dashboard' ), admin_url( 'edit.php' ) );
	if ( $notice ) {
		$url = add_query_arg( 'sl_omitland_notice', $notice, $url );
	}
	wp_safe_redirect( $url );
	exit;
}

function sl_omitland_render_dashboard() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Accès non autorisé.' );
	}
	$claims = get_posts( array( 'post_type' => SL_OMTLAND_CLAIM_TYPE, 'post_status' => 'any', 'numberposts' => -1 ) );
	$codes = get_posts( array( 'post_type' => SL_OMTLAND_CODE_TYPE, 'post_status' => 'any', 'numberposts' => 100, 'orderby' => 'date', 'order' => 'DESC' ) );
	$used = 0;
	foreach ( $claims as $claim ) {
		if ( 'redeemed' === get_post_meta( $claim->ID, '_sl_omtland_status', true ) ) {
			$used++;
		}
	}
	$clicks = $claim_count = $uses = 0;
	foreach ( $codes as $code_post ) {
		$clicks += (int) get_post_meta( $code_post->ID, '_sl_omitland_code_clicks', true );
		$claim_count += (int) get_post_meta( $code_post->ID, '_sl_omitland_code_claims', true );
		$uses += (int) get_post_meta( $code_post->ID, '_sl_omitland_code_uses', true );
	}
	$auto_enabled = '1' === (string) sl_omitland_get_setting( 'auto_enabled', '0' );
	$paused = '1' === (string) sl_omitland_get_setting( 'paused', '0' );
	$notice = isset( $_GET['sl_omitland_notice'] ) ? sanitize_key( wp_unslash( $_GET['sl_omitland_notice'] ) ) : '';
	$action_url = admin_url( 'admin-post.php' );
	echo '<div class="wrap"><h1>Tableau de bord Omitland</h1>';
	if ( 'saved' === $notice ) echo '<div class="notice notice-success is-dismissible"><p>Les réglages Omitland ont été enregistrés.</p></div>';
	if ( 'generated' === $notice ) echo '<div class="notice notice-success is-dismissible"><p>Un nouveau code a été généré.</p></div>';
	echo '<div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;max-width:1100px;margin:20px 0">';
	foreach ( array( 'Clics' => $clicks, 'Réclamations' => count( $claims ), 'Codes utilisés' => $uses ?: $used, 'Codes actifs' => count( array_filter( $codes, static fn( $p ) => 'active' === ( get_post_meta( $p->ID, '_sl_omitland_code_status', true ) ?: 'active' ) ) ) ) as $label => $value ) {
		echo '<div style="padding:20px;background:#fff;border:1px solid #dcdcde"><span style="display:block;color:#646970">' . esc_html( $label ) . '</span><strong style="font-size:28px">' . esc_html( number_format_i18n( $value ) ) . '</strong></div>';
	}
	echo '</div><h2>Automatisation</h2><form method="post" action="' . esc_url( $action_url ) . '" style="max-width:700px;padding:20px;background:#fff;border:1px solid #dcdcde">';
	echo '<input type="hidden" name="action" value="sl_omitland_save_settings">';
	wp_nonce_field( 'sl_omitland_save_settings', 'sl_omitland_settings_nonce' );
	echo '<p><label><input type="checkbox" name="auto_enabled" value="1" ' . checked( $auto_enabled, true, false ) . '> Générer automatiquement de nouveaux codes</label></p>';
	echo '<p><label>Cadence <select name="cadence"><option value="weekly" ' . selected( sl_omitland_get_setting( 'cadence', 'weekly' ), 'weekly', false ) . '>Chaque semaine</option><option value="4days" ' . selected( sl_omitland_get_setting( 'cadence', 'weekly' ), '4days', false ) . '>Tous les 4 jours</option></select></label></p>';
	echo '<p><label>Code actif <input type="text" name="active_code" value="' . esc_attr( sl_omitland_current_code() ) . '" maxlength="24"></label></p>';
	echo '<p><label>Début <input type="date" name="start_date" value="' . esc_attr( sl_omitland_start_date() ) . '"></label> <label>Fin <input type="date" name="end_date" value="' . esc_attr( sl_omitland_end_date() ) . '"></label></p>';
	echo '<p><label><input type="checkbox" name="paused" value="1" ' . checked( $paused, true, false ) . '> Mettre la génération automatique en pause</label></p><p><button class="button button-primary">Enregistrer les réglages</button></p></form>';
	echo '<h2>Créer un code personnel</h2><form method="post" action="' . esc_url( $action_url ) . '" style="max-width:700px;padding:20px;background:#fff;border:1px solid #dcdcde"><input type="hidden" name="action" value="sl_omitland_generate_code">';
	wp_nonce_field( 'sl_omitland_generate_code', 'sl_omitland_code_nonce' );
	echo '<p><label>Code (laisser vide pour générer) <input type="text" name="code" maxlength="24"></label></p><p><label>Nom de l’ambassadeur <input type="text" name="label" maxlength="120"></label></p><p><button class="button">Générer le code et le lien</button></p></form>';
	echo '<h2>Codes et performances</h2><table class="widefat striped"><thead><tr><th>Code</th><th>Période</th><th>Clics</th><th>Réclamations</th><th>Utilisés</th><th>Lien</th></tr></thead><tbody>';
	foreach ( $codes as $code_post ) {
		$code = $code_post->post_title;
		$link = add_query_arg( array( 'promo' => $code, 'ref' => $code ), home_url( '/bonus-omtland-odza/' ) );
		echo '<tr><td><strong>' . esc_html( $code ) . '</strong><br><small>' . esc_html( get_post_meta( $code_post->ID, '_sl_omitland_code_label', true ) ) . '</small></td><td>' . esc_html( get_post_meta( $code_post->ID, '_sl_omitland_code_start', true ) . ' → ' . get_post_meta( $code_post->ID, '_sl_omitland_code_end', true ) ) . '</td><td>' . esc_html( number_format_i18n( (int) get_post_meta( $code_post->ID, '_sl_omitland_code_clicks', true ) ) ) . '</td><td>' . esc_html( number_format_i18n( (int) get_post_meta( $code_post->ID, '_sl_omitland_code_claims', true ) ) ) . '</td><td>' . esc_html( number_format_i18n( (int) get_post_meta( $code_post->ID, '_sl_omitland_code_uses', true ) ) ) . '</td><td><input type="text" readonly value="' . esc_attr( $link ) . '" style="width:100%"></td></tr>';
	}
	if ( ! $codes ) echo '<tr><td colspan="6">Aucun code enregistré.</td></tr>';
	echo '</tbody></table></div>';
}

function sl_omitland_save_settings() {
	if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['sl_omitland_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sl_omitland_settings_nonce'] ) ), 'sl_omitland_save_settings' ) ) {
		wp_die( 'Action non autorisée.' );
	}
	$code = strtoupper( preg_replace( '/[^A-Z0-9]/', '', sanitize_text_field( wp_unslash( $_POST['active_code'] ?? '' ) ) ) );
	if ( ! $code ) $code = sl_omitland_current_code();
	update_option( 'sl_omitland_active_code', $code );
	update_option( 'sl_omitland_start_date', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $_POST['start_date'] ?? '' ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : SL_OMTLAND_START_DATE );
	update_option( 'sl_omitland_end_date', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $_POST['end_date'] ?? '' ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : SL_OMTLAND_END_DATE );
	update_option( 'sl_omitland_cadence', '4days' === ( $_POST['cadence'] ?? '' ) ? '4days' : 'weekly' );
	update_option( 'sl_omitland_auto_enabled', isset( $_POST['auto_enabled'] ) ? '1' : '0' );
	update_option( 'sl_omitland_paused', isset( $_POST['paused'] ) ? '1' : '0' );
	sl_omitland_ensure_current_code();
	sl_omitland_update_schedule();
	sl_omitland_admin_redirect( 'saved' );
}
add_action( 'admin_post_sl_omitland_save_settings', 'sl_omitland_save_settings' );

function sl_omitland_generate_code_admin() {
	if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['sl_omitland_code_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sl_omitland_code_nonce'] ) ), 'sl_omitland_generate_code' ) ) {
		wp_die( 'Action non autorisée.' );
	}
	$code = sanitize_text_field( wp_unslash( $_POST['code'] ?? '' ) );
	$code = $code ? strtoupper( preg_replace( '/[^A-Z0-9]/', '', $code ) ) : sl_omitland_generate_code_value();
	if ( sl_omitland_create_code( $code, sl_omitland_start_date(), sl_omitland_end_date(), $_POST['label'] ?? '' ) ) {
		sl_omitland_admin_redirect( 'generated' );
	}
	sl_omitland_admin_redirect( 'error' );
}
add_action( 'admin_post_sl_omitland_generate_code', 'sl_omitland_generate_code_admin' );
