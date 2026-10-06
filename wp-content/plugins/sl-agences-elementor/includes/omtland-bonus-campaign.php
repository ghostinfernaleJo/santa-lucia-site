<?php
/**
 * Campagne OMITLAND ODZA : première expérience offerte.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Masque uniquement le rappel de configuration MailChimp non utilisé. */
function sl_omitland_hide_mailchimp_setup_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<script>
	(function () {
		function hideMailchimpSetupNotice() {
			document.querySelectorAll('.notice, .updated, .error').forEach(function (notice) {
				var text = (notice.textContent || '').toLowerCase();
				if (text.indexOf('mailchimp for wordpress') !== -1 && (text.indexOf('api key') !== -1 || text.indexOf('clé api') !== -1)) {
					notice.remove();
				}
			});
		}
		document.addEventListener('DOMContentLoaded', hideMailchimpSetupNotice);
	}());
	</script>
	<?php
}
add_action( 'admin_notices', 'sl_omitland_hide_mailchimp_setup_notice', PHP_INT_MAX );

const SL_OMTLAND_CLAIM_TYPE = 'sl_omtland_claim';
const SL_OMTLAND_PROMO_CODE = 'OMIT237XVZ';
const SL_OMTLAND_START_DATE = '2026-09-19';
const SL_OMTLAND_END_DATE = '2026-10-30';
const SL_OMTLAND_CODE_TYPE = 'sl_omitland_code';

/** Autorise l'équipe CRM existante à ouvrir les fiches privées OMITLAND uniquement. */
function sl_omitland_map_crm_team_capabilities( $caps, $cap, $user_id, $args ) {
	if ( ! is_admin() || ! in_array( $cap, array( 'read_post', 'edit_post' ), true ) || empty( $args[0] ) ) {
		return $caps;
	}
	$post = get_post( (int) $args[0] );
	if ( ! $post || ! in_array( $post->post_type, array( SL_OMTLAND_CLAIM_TYPE, SL_OMTLAND_CODE_TYPE ), true ) ) {
		return $caps;
	}
	if ( user_can( $user_id, 'manage_options' ) || user_can( $user_id, 'edit_slg_requests' ) ) {
		return array( 'exist' );
	}
	return $caps;
}
add_filter( 'map_meta_cap', 'sl_omitland_map_crm_team_capabilities', 20, 4 );

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
		'capability_type'     => array( 'slg_request', 'slg_requests' ),
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
		'capability_type'     => array( 'slg_request', 'slg_requests' ),
		'map_meta_cap'        => true,
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
	sl_omitland_record_analytics_visit( $code );
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

function sl_omitland_record_analytics_visit( $code = '' ) {
	$visitor_cookie = 'sl_omitland_visitor';
	$visitor = isset( $_COOKIE[ $visitor_cookie ] ) ? preg_replace( '/[^a-zA-Z0-9-]/', '', (string) $_COOKIE[ $visitor_cookie ] ) : '';
	if ( ! $visitor ) {
		$visitor = wp_generate_uuid4();
		setcookie( $visitor_cookie, $visitor, time() + ( 90 * DAY_IN_SECONDS ), COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true );
	}
	$today = current_time( 'Y-m-d' );
	$stats = get_option( 'sl_omitland_analytics', array() );
	$stats = is_array( $stats ) ? $stats : array();
	$cutoff = gmdate( 'Y-m-d', current_time( 'timestamp' ) - ( 90 * DAY_IN_SECONDS ) );
	foreach ( array_keys( $stats ) as $day ) {
		if ( $day < $cutoff ) {
			unset( $stats[ $day ] );
		}
	}
	if ( ! isset( $stats[ $today ] ) || ! is_array( $stats[ $today ] ) ) {
		$stats[ $today ] = array( 'visits' => 0, 'visitors' => array(), 'sources' => array(), 'campaigns' => array(), 'codes' => array() );
	}
	$source = isset( $_GET['utm_source'] ) ? sanitize_key( wp_unslash( $_GET['utm_source'] ) ) : 'direct';
	$campaign = isset( $_GET['utm_campaign'] ) ? sanitize_key( wp_unslash( $_GET['utm_campaign'] ) ) : 'non renseignée';
	$stats[ $today ]['visits']++;
	$stats[ $today ]['visitors'][ $visitor ] = 1;
	$stats[ $today ]['sources'][ $source ] = (int) ( $stats[ $today ]['sources'][ $source ] ?? 0 ) + 1;
	$stats[ $today ]['campaigns'][ $campaign ] = (int) ( $stats[ $today ]['campaigns'][ $campaign ] ?? 0 ) + 1;
	if ( $code ) {
		if ( ! isset( $stats[ $today ]['codes'][ $code ] ) ) {
			$stats[ $today ]['codes'][ $code ] = array( 'visits' => 0, 'visitors' => array() );
		}
		$stats[ $today ]['codes'][ $code ]['visits']++;
		$stats[ $today ]['codes'][ $code ]['visitors'][ $visitor ] = 1;
	}
	update_option( 'sl_omitland_analytics', $stats, false );
}

function sl_omitland_analytics_summary() {
	$summary = array( 'visits' => 0, 'visitors' => array(), 'sources' => array(), 'campaigns' => array() );
	foreach ( (array) get_option( 'sl_omitland_analytics', array() ) as $day ) {
		$summary['visits'] += (int) ( $day['visits'] ?? 0 );
		$summary['visitors'] = array_merge( $summary['visitors'], (array) ( $day['visitors'] ?? array() ) );
		foreach ( array( 'sources', 'campaigns' ) as $bucket ) {
			foreach ( (array) ( $day[ $bucket ] ?? array() ) as $label => $count ) {
				$summary[ $bucket ][ $label ] = (int) ( $summary[ $bucket ][ $label ] ?? 0 ) + (int) $count;
			}
		}
	}
	$summary['unique_visitors'] = count( $summary['visitors'] );
	unset( $summary['visitors'] );
	arsort( $summary['sources'] );
	arsort( $summary['campaigns'] );
	return $summary;
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

function sl_omtland_register_campaign_assets() {
	wp_register_style( 'sl-omtland-bonus', SL_AGENCES_URL . 'assets/css/omtland-bonus-v2.css', array(), '1.0.4' );
}
add_action( 'wp_enqueue_scripts', 'sl_omtland_register_campaign_assets', 20 );
add_action( 'elementor/frontend/after_register_styles', 'sl_omtland_register_campaign_assets' );
add_action( 'elementor/editor/after_enqueue_styles', 'sl_omtland_register_campaign_assets' );

function sl_omtland_campaign_assets() {
	if ( is_page( 'bonus-omtland-odza' ) ) {
		wp_enqueue_style( 'sl-omtland-bonus' );
	}
}
add_action( 'wp_enqueue_scripts', 'sl_omtland_campaign_assets', 110 );

function sl_omitland_admin_assets() {
	$screen = get_current_screen();
	if ( ! $screen ) {
		return;
	}
	$is_campaign_screen = in_array( $screen->post_type, array( SL_OMTLAND_CLAIM_TYPE, SL_OMTLAND_CODE_TYPE ), true );
	$is_dashboard = isset( $_GET['page'] ) && 'sl-omitland-dashboard' === sanitize_key( wp_unslash( $_GET['page'] ) );
	if ( ! $is_campaign_screen && ! $is_dashboard ) {
		return;
	}
	wp_enqueue_style( 'sl-omitland-admin', SL_AGENCES_URL . 'assets/css/omitland-admin.css', array(), '1.0.0' );
}
add_action( 'admin_enqueue_scripts', 'sl_omitland_admin_assets' );

function sl_omtland_promotion_is_open() {
	return sl_omitland_promotion_is_open();
}

function sl_omtland_bonus_shortcode() {
	$is_elementor_editor = class_exists( '\\Elementor\\Plugin' ) && \Elementor\Plugin::$instance->editor->is_edit_mode();
	if ( ! $is_elementor_editor ) {
		sl_omitland_track_visit();
	}
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
						<input type="hidden" name="return_url" value="<?php echo esc_url( get_permalink() ); ?>">
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
	if ( isset( $_POST['return_url'] ) ) {
		$url = wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['return_url'] ) ), $url );
	}
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
		'post_status' => array( 'private', 'publish', 'pending', 'draft', 'sl_omitland_archived' ),
		'numberposts' => 1,
		'fields'      => 'ids',
		'meta_key'    => '_sl_omtland_phone_key',
		'meta_value'  => $phone_key,
	) );
	if ( $existing ) {
		$existing_id = (int) $existing[0];
		sl_omitland_log_activity( $existing_id, 'Nouvelle tentative de réclamation bloquée pour ce numéro.' );
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
	sl_omitland_log_activity( $post_id, 'Réclamation créée depuis le formulaire public.' );
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
	$contact_phone = get_post_meta( $post_id, '_sl_omtland_phone', true );
	$phone = sl_omtland_normalize_phone( $contact_phone );
	if ( ! $phone ) {
		return '';
	}
	$name = get_post_meta( $post_id, '_sl_omtland_name', true );
	$email = get_post_meta( $post_id, '_sl_omtland_email', true );
	$code = get_post_meta( $post_id, '_sl_omitland_code', true ) ?: sl_omitland_current_code();
	$reference = get_post_meta( $post_id, '_sl_omtland_reference', true );
	$source = get_post_meta( $post_id, '_sl_omtland_source', true );
	$referrer = get_post_meta( $post_id, '_sl_omitland_referrer', true );

	$message = "Bonjour {$name},\n\n";
	$message .= "*Confirmation de votre bonus OMITLAND*\n\n";
	$message .= "Client : {$name}\n";
	$message .= 'Téléphone : ' . ( $contact_phone ?: 'Non renseigné' ) . "\n";
	$message .= 'E-mail : ' . ( $email ?: 'Non renseigné' ) . "\n";
	$message .= 'Source : ' . ( $source ?: 'Non renseignée' ) . "\n";
	$message .= "Code promotionnel : {$code}\n";
	$message .= "Référence : {$reference}\n";
	$message .= 'Ambassadeur : ' . ( $referrer ?: 'Aucun' ) . "\n\n";
	$message .= "*Pour utiliser vos 50 unités gratuites :*\n";
	$message .= "1. Rendez-vous à OMITLAND ODZA.\n";
	$message .= "2. Présentez ce message WhatsApp et votre code à notre équipe.\n";
	$message .= "3. Après vérification, votre carte de 50 unités vous sera remise.\n\n";
	$message .= "Quand prévoyez-vous de venir jouer ? Répondez directement à ce message.\n\n";
	$message .= "OMITLAND ODZA";
	return 'https://wa.me/' . rawurlencode( $phone ) . '?text=' . rawurlencode( $message );
}

function sl_omtland_claim_meta_boxes() {
	add_meta_box( 'sl-omtland-claim', 'Détails de la réclamation', 'sl_omtland_render_claim_box', SL_OMTLAND_CLAIM_TYPE, 'normal', 'high' );
}
add_action( 'add_meta_boxes_' . SL_OMTLAND_CLAIM_TYPE, 'sl_omtland_claim_meta_boxes' );

function sl_omitland_register_claim_statuses() {
	register_post_status( 'sl_omitland_archived', array(
		'label' => 'Archivée',
		'public' => false,
		'internal' => false,
		'exclude_from_search' => true,
		'show_in_admin_all_list' => true,
		'show_in_admin_status_list' => true,
		'label_count' => _n_noop( 'Archivée <span class="count">(%s)</span>', 'Archivées <span class="count">(%s)</span>', 'sl-agences' ),
	) );
}
add_action( 'init', 'sl_omitland_register_claim_statuses', 20 );

function sl_omitland_log_activity( $post_id, $message ) {
	$activity = get_post_meta( $post_id, '_sl_omitland_activity', true );
	$activity = is_array( $activity ) ? $activity : array();
	$user = wp_get_current_user();
	array_unshift( $activity, array(
		'time' => current_time( 'timestamp' ),
		'user' => $user && $user->exists() ? $user->display_name : 'Formulaire public',
		'message' => sanitize_text_field( $message ),
	) );
	update_post_meta( $post_id, '_sl_omitland_activity', array_slice( $activity, 0, 30 ) );
}

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
	echo '<div class="sl-omitland-detail-table"><table class="widefat striped"><tbody>';
	foreach ( $fields as $label => $value ) {
		echo '<tr><th style="width:160px">' . esc_html( $label ) . '</th><td>' . esc_html( $value ?: '—' ) . '</td></tr>';
	}
	echo '</tbody></table></div>';
	$status = get_post_meta( $post->ID, '_sl_omtland_status', true ) ?: 'pending';
	echo '<div class="sl-omitland-status-field"><label for="sl_omtland_status"><strong>Statut de traitement</strong></label><select id="sl_omtland_status" name="sl_omtland_status">';
	foreach ( array( 'pending' => 'À vérifier', 'verified' => 'Éligible', 'redeemed' => '50 unités remises', 'rejected' => 'Refusée', 'blocked' => 'Bloqué' ) as $key => $label ) {
		echo '<option value="' . esc_attr( $key ) . '" ' . selected( $status, $key, false ) . '>' . esc_html( $label ) . '</option>';
	}
	echo '</select></div>';
	$whatsapp_url = sl_omitland_claim_whatsapp_url( $post->ID );
	if ( $whatsapp_url ) {
		echo '<p class="sl-omitland-whatsapp-action"><a class="button button-primary" href="' . esc_attr( $whatsapp_url ) . '" target="_blank" rel="noopener noreferrer">Ouvrir le message WhatsApp</a><span>Le message est prérempli avec toutes les informations de cette réclamation.</span></p>';
	}
	$archive_url = wp_nonce_url( admin_url( 'admin-post.php?action=sl_omitland_archive_claim&claim_id=' . $post->ID ), 'sl_omitland_archive_claim_' . $post->ID );
	echo '<p class="sl-omitland-record-actions"><a class="button" href="' . esc_url( $archive_url ) . '">Archiver ce contact</a><span>La suppression définitive reste disponible dans la corbeille WordPress.</span></p>';
	$activity = get_post_meta( $post->ID, '_sl_omitland_activity', true );
	if ( is_array( $activity ) && $activity ) {
		echo '<div class="sl-omitland-activity"><h3>Historique</h3><ul>';
		foreach ( $activity as $entry ) {
			$time = isset( $entry['time'] ) ? wp_date( 'd/m/Y H:i', (int) $entry['time'] ) : '';
			echo '<li><strong>' . esc_html( $time ) . '</strong><span>' . esc_html( $entry['message'] ?? '' ) . '</span><small>' . esc_html( $entry['user'] ?? '' ) . '</small></li>';
		}
		echo '</ul></div>';
	}
}

function sl_omtland_save_claim( $post_id ) {
	if ( ! isset( $_POST['sl_omtland_admin_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sl_omtland_admin_nonce'] ) ), 'sl_omtland_save_claim' ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$old_status = get_post_meta( $post_id, '_sl_omtland_status', true ) ?: 'pending';
	$allowed = array( 'pending', 'verified', 'redeemed', 'rejected', 'blocked' );
	$status = isset( $_POST['sl_omtland_status'] ) ? sanitize_key( wp_unslash( $_POST['sl_omtland_status'] ) ) : 'pending';
	if ( in_array( $status, $allowed, true ) ) {
		update_post_meta( $post_id, '_sl_omtland_status', $status );
		if ( $status !== $old_status ) {
			$labels = array( 'pending' => 'À vérifier', 'verified' => 'Éligible', 'redeemed' => '50 unités remises', 'rejected' => 'Refusée', 'blocked' => 'Bloqué' );
			sl_omitland_log_activity( $post_id, 'Statut modifié : ' . ( $labels[ $old_status ] ?? $old_status ) . ' → ' . ( $labels[ $status ] ?? $status ) . '.' );
		}
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
		'sl_code'      => 'Code',
		'sl_reference' => 'Référence',
		'sl_status'    => 'Statut',
		'date'         => 'Demandée le',
	);
}
add_filter( 'manage_' . SL_OMTLAND_CLAIM_TYPE . '_posts_columns', 'sl_omtland_claim_columns' );

function sl_omtland_claim_column( $column, $post_id ) {
	if ( 'sl_phone' === $column ) {
		echo esc_html( get_post_meta( $post_id, '_sl_omtland_phone', true ) );
	} elseif ( 'sl_code' === $column ) {
		echo '<code>' . esc_html( get_post_meta( $post_id, '_sl_omitland_code', true ) ?: '—' ) . '</code>';
	} elseif ( 'sl_reference' === $column ) {
		echo '<strong>' . esc_html( get_post_meta( $post_id, '_sl_omtland_reference', true ) ) . '</strong>';
	} elseif ( 'sl_status' === $column ) {
		$status = get_post_meta( $post_id, '_sl_omtland_status', true ) ?: 'pending';
		$labels = array( 'pending' => 'À vérifier', 'verified' => 'Éligible', 'redeemed' => '50 unités remises', 'rejected' => 'Refusée', 'blocked' => 'Bloqué' );
		$class = 'status-' . sanitize_html_class( $status );
		echo '<span class="sl-omitland-status ' . esc_attr( $class ) . '">' . esc_html( $labels[ $status ] ?? $status ) . '</span>';
	}
}
add_action( 'manage_' . SL_OMTLAND_CLAIM_TYPE . '_posts_custom_column', 'sl_omtland_claim_column', 10, 2 );

function sl_omitland_claim_bulk_actions( $actions ) {
	$actions['sl_omitland_archive'] = 'Archiver';
	$actions['sl_omitland_block'] = 'Bloquer';
	return $actions;
}
add_filter( 'bulk_actions-edit-' . SL_OMTLAND_CLAIM_TYPE, 'sl_omitland_claim_bulk_actions' );

function sl_omitland_handle_claim_bulk_actions( $redirect_url, $action, $post_ids ) {
	if ( ! in_array( $action, array( 'sl_omitland_archive', 'sl_omitland_block' ), true ) || ! sl_omitland_can_manage() ) {
		return $redirect_url;
	}
	$updated = 0;
	foreach ( $post_ids as $post_id ) {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			continue;
		}
		if ( 'sl_omitland_archive' === $action ) {
			wp_update_post( array( 'ID' => $post_id, 'post_status' => 'sl_omitland_archived' ) );
			sl_omitland_log_activity( $post_id, 'Contact archivé.' );
		} else {
			update_post_meta( $post_id, '_sl_omtland_status', 'blocked' );
			sl_omitland_log_activity( $post_id, 'Contact bloqué.' );
		}
		$updated++;
	}
	return add_query_arg( 'sl_omitland_updated', $updated, $redirect_url );
}
add_filter( 'handle_bulk_actions-edit-' . SL_OMTLAND_CLAIM_TYPE, 'sl_omitland_handle_claim_bulk_actions', 10, 3 );

function sl_omitland_archive_claim() {
	$claim_id = isset( $_GET['claim_id'] ) ? absint( $_GET['claim_id'] ) : 0;
	if ( ! $claim_id || ! current_user_can( 'edit_post', $claim_id ) || ! check_admin_referer( 'sl_omitland_archive_claim_' . $claim_id ) ) {
		wp_die( 'Action non autorisée.' );
	}
	wp_update_post( array( 'ID' => $claim_id, 'post_status' => 'sl_omitland_archived' ) );
	sl_omitland_log_activity( $claim_id, 'Contact archivé.' );
	wp_safe_redirect( admin_url( 'edit.php?post_type=' . SL_OMTLAND_CLAIM_TYPE ) );
	exit;
}
add_action( 'admin_post_sl_omitland_archive_claim', 'sl_omitland_archive_claim' );

function sl_omitland_claim_code_filter() {
	global $typenow;
	if ( SL_OMTLAND_CLAIM_TYPE !== $typenow ) {
		return;
	}
	$selected = isset( $_GET['sl_omitland_code'] ) ? sanitize_text_field( wp_unslash( $_GET['sl_omitland_code'] ) ) : '';
	$codes = get_posts( array( 'post_type' => SL_OMTLAND_CODE_TYPE, 'post_status' => 'any', 'numberposts' => 200, 'orderby' => 'title', 'order' => 'ASC' ) );
	echo '<select name="sl_omitland_code"><option value="">Tous les codes</option>';
	foreach ( $codes as $code_post ) {
		echo '<option value="' . esc_attr( $code_post->post_title ) . '" ' . selected( $selected, $code_post->post_title, false ) . '>' . esc_html( $code_post->post_title ) . '</option>';
	}
	echo '</select>';
}
add_action( 'restrict_manage_posts', 'sl_omitland_claim_code_filter' );

function sl_omitland_filter_claims_by_code( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() || SL_OMTLAND_CLAIM_TYPE !== $query->get( 'post_type' ) || empty( $_GET['sl_omitland_code'] ) ) {
		return;
	}
	$query->set( 'meta_key', '_sl_omitland_code' );
	$query->set( 'meta_value', sanitize_text_field( wp_unslash( $_GET['sl_omitland_code'] ) ) );
}
add_action( 'pre_get_posts', 'sl_omitland_filter_claims_by_code' );

function sl_omitland_extend_admin_search( $search, $query ) {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return $search;
	}

	$post_type = $query->get( 'post_type' );
	$term = trim( (string) $query->get( 's' ) );
	if ( ! $term || ! in_array( $post_type, array( SL_OMTLAND_CLAIM_TYPE, SL_OMTLAND_CODE_TYPE ), true ) ) {
		return $search;
	}

	$meta_keys = SL_OMTLAND_CLAIM_TYPE === $post_type
		? array( '_sl_omtland_name', '_sl_omtland_phone', '_sl_omtland_email', '_sl_omtland_source', '_sl_omitland_code', '_sl_omitland_referrer', '_sl_omtland_reference' )
		: array( '_sl_omitland_code_label' );

	global $wpdb;
	$like = '%' . $wpdb->esc_like( $term ) . '%';
	$key_placeholders = implode( ', ', array_fill( 0, count( $meta_keys ), '%s' ) );
	$sql = " AND ( {$wpdb->posts}.post_title LIKE %s OR EXISTS ( SELECT 1 FROM {$wpdb->postmeta} AS sl_omitland_search_meta WHERE sl_omitland_search_meta.post_id = {$wpdb->posts}.ID AND sl_omitland_search_meta.meta_key IN ({$key_placeholders}) AND sl_omitland_search_meta.meta_value LIKE %s ) )";
	$args = array_merge( array( $like ), $meta_keys, array( $like ) );

	return $wpdb->prepare( $sql, $args );
}
add_filter( 'posts_search', 'sl_omitland_extend_admin_search', 20, 2 );

function sl_omitland_admin_search_hint() {
	global $typenow;
	if ( SL_OMTLAND_CLAIM_TYPE === $typenow ) {
		$placeholder = 'Nom, téléphone, e-mail, référence ou code';
	} elseif ( SL_OMTLAND_CODE_TYPE === $typenow ) {
		$placeholder = 'Code ou nom de l’ambassadeur';
	} else {
		return;
	}
	?>
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			var search = document.getElementById('post-search-input');
			if (search) {
				search.placeholder = <?php echo wp_json_encode( $placeholder ); ?>;
			}
		});
	</script>
	<?php
}
add_action( 'admin_footer-edit.php', 'sl_omitland_admin_search_hint' );

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

function sl_omitland_code_meta_boxes() {
	add_meta_box( 'sl-omitland-code-settings', 'Réglages et performance', 'sl_omitland_render_code_box', SL_OMTLAND_CODE_TYPE, 'normal', 'high' );
}
add_action( 'add_meta_boxes_' . SL_OMTLAND_CODE_TYPE, 'sl_omitland_code_meta_boxes' );

function sl_omitland_render_code_box( $post ) {
	wp_nonce_field( 'sl_omitland_save_code', 'sl_omitland_code_admin_nonce' );
	$start = get_post_meta( $post->ID, '_sl_omitland_code_start', true );
	$end = get_post_meta( $post->ID, '_sl_omitland_code_end', true );
	$status = get_post_meta( $post->ID, '_sl_omitland_code_status', true ) ?: 'active';
	$label = get_post_meta( $post->ID, '_sl_omitland_code_label', true );
	$link = add_query_arg( array( 'promo' => $post->post_title, 'ref' => $post->post_title ), home_url( '/bonus-omtland-odza/' ) );
	echo '<div class="sl-omitland-code-editor"><p><label>Nom de l’ambassadeur<input type="text" name="sl_omitland_code_label" value="' . esc_attr( $label ) . '" maxlength="120" class="regular-text"></label></p><p><label>Début<input type="date" name="sl_omitland_code_start" value="' . esc_attr( $start ) . '"></label> <label>Fin<input type="date" name="sl_omitland_code_end" value="' . esc_attr( $end ) . '"></label></p><p><label>État <select name="sl_omitland_code_status"><option value="active" ' . selected( $status, 'active', false ) . '>Actif</option><option value="inactive" ' . selected( $status, 'inactive', false ) . '>Désactivé</option></select></label></p><p><label>Lien partageable<input type="text" readonly value="' . esc_attr( $link ) . '" class="large-text"></label></p><div class="sl-omitland-code-metrics"><span><b>' . esc_html( number_format_i18n( (int) get_post_meta( $post->ID, '_sl_omitland_code_clicks', true ) ) ) . '</b>Clics</span><span><b>' . esc_html( number_format_i18n( (int) get_post_meta( $post->ID, '_sl_omitland_code_claims', true ) ) ) . '</b>Réclamations</span><span><b>' . esc_html( number_format_i18n( (int) get_post_meta( $post->ID, '_sl_omitland_code_uses', true ) ) ) . '</b>Utilisations</span></div></div>';
}

function sl_omitland_save_code( $post_id ) {
	if ( ! isset( $_POST['sl_omitland_code_admin_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sl_omitland_code_admin_nonce'] ) ), 'sl_omitland_save_code' ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$start = isset( $_POST['sl_omitland_code_start'] ) ? sanitize_text_field( wp_unslash( $_POST['sl_omitland_code_start'] ) ) : '';
	$end = isset( $_POST['sl_omitland_code_end'] ) ? sanitize_text_field( wp_unslash( $_POST['sl_omitland_code_end'] ) ) : '';
	if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end ) && $start <= $end ) {
		update_post_meta( $post_id, '_sl_omitland_code_start', $start );
		update_post_meta( $post_id, '_sl_omitland_code_end', $end );
	}
	update_post_meta( $post_id, '_sl_omitland_code_label', isset( $_POST['sl_omitland_code_label'] ) ? sanitize_text_field( wp_unslash( $_POST['sl_omitland_code_label'] ) ) : '' );
	update_post_meta( $post_id, '_sl_omitland_code_status', isset( $_POST['sl_omitland_code_status'] ) && 'inactive' === $_POST['sl_omitland_code_status'] ? 'inactive' : 'active' );
}
add_action( 'save_post_' . SL_OMTLAND_CODE_TYPE, 'sl_omitland_save_code' );

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

function sl_omitland_can_manage() {
	return current_user_can( 'manage_options' ) || current_user_can( 'edit_slg_requests' );
}

function sl_omitland_admin_menu() {
	add_submenu_page( 'edit.php?post_type=' . SL_OMTLAND_CLAIM_TYPE, 'Tableau de bord Omitland', 'Tableau de bord', 'edit_slg_requests', 'sl-omitland-dashboard', 'sl_omitland_render_dashboard' );
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
	if ( ! sl_omitland_can_manage() ) {
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
	$analytics = sl_omitland_analytics_summary();
	$notice = isset( $_GET['sl_omitland_notice'] ) ? sanitize_key( wp_unslash( $_GET['sl_omitland_notice'] ) ) : '';
	$action_url = admin_url( 'admin-post.php' );
	echo '<div class="wrap sl-omitland-dashboard"><div class="sl-omitland-dashboard-heading"><div><p class="sl-omitland-admin-kicker">Campagne et suivi</p><h1>Tableau de bord OMITLAND</h1><p class="sl-omitland-admin-intro">Pilote les codes promotionnels, les réclamations et les visites depuis un seul espace.</p></div><div class="sl-omitland-dashboard-actions"><a class="button" href="' . esc_url( admin_url( 'admin-post.php?action=sl_omitland_export_claims' ) ) . '">Exporter les réclamations</a><a class="button" href="' . esc_url( admin_url( 'admin-post.php?action=sl_omitland_export_codes' ) ) . '">Exporter les codes</a><a class="button" href="' . esc_url( admin_url( 'admin-post.php?action=sl_omitland_export_analytics' ) ) . '">Exporter les statistiques</a><a class="button" href="' . esc_url( home_url( '/bonus-omtland-odza/' ) ) . '" target="_blank" rel="noopener noreferrer">Voir la page publique</a></div></div>';
	if ( 'saved' === $notice ) echo '<div class="notice notice-success is-dismissible"><p>Les réglages Omitland ont été enregistrés.</p></div>';
	if ( 'generated' === $notice ) echo '<div class="notice notice-success is-dismissible"><p>Un nouveau code a été généré.</p></div>';
	echo '<div class="sl-omitland-kpis">';
	foreach ( array( 'Clics' => $clicks, 'Réclamations' => count( $claims ), 'Codes utilisés' => $uses ?: $used, 'Codes actifs' => count( array_filter( $codes, static fn( $p ) => 'active' === ( get_post_meta( $p->ID, '_sl_omitland_code_status', true ) ?: 'active' ) ) ) ) as $label => $value ) {
		echo '<div class="sl-omitland-kpi"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( number_format_i18n( $value ) ) . '</strong></div>';
	}
	echo '</div><h2>Automatisation</h2><form method="post" action="' . esc_url( $action_url ) . '" class="sl-omitland-panel">';
	echo '<input type="hidden" name="action" value="sl_omitland_save_settings">';
	wp_nonce_field( 'sl_omitland_save_settings', 'sl_omitland_settings_nonce' );
	echo '<p><label><input type="checkbox" name="auto_enabled" value="1" ' . checked( $auto_enabled, true, false ) . '> Générer automatiquement de nouveaux codes</label></p>';
	echo '<p><label>Cadence <select name="cadence"><option value="weekly" ' . selected( sl_omitland_get_setting( 'cadence', 'weekly' ), 'weekly', false ) . '>Chaque semaine</option><option value="4days" ' . selected( sl_omitland_get_setting( 'cadence', 'weekly' ), '4days', false ) . '>Tous les 4 jours</option></select></label></p>';
	echo '<p><label>Code actif <input type="text" name="active_code" value="' . esc_attr( sl_omitland_current_code() ) . '" maxlength="24"></label></p>';
	echo '<p><label>Début <input type="date" name="start_date" value="' . esc_attr( sl_omitland_start_date() ) . '"></label> <label>Fin <input type="date" name="end_date" value="' . esc_attr( sl_omitland_end_date() ) . '"></label></p>';
	echo '<p><label><input type="checkbox" name="paused" value="1" ' . checked( $paused, true, false ) . '> Mettre la génération automatique en pause</label></p><p><button class="button button-primary">Enregistrer les réglages</button></p></form>';
	echo '<h2>Statistiques de trafic</h2><div class="sl-omitland-analytics"><div><span>Visites sur 90 jours</span><strong>' . esc_html( number_format_i18n( $analytics['visits'] ) ) . '</strong></div><div><span>Visiteurs uniques</span><strong>' . esc_html( number_format_i18n( $analytics['unique_visitors'] ) ) . '</strong></div><div><span>Conversion</span><strong>' . esc_html( $analytics['visits'] ? number_format_i18n( ( count( $claims ) / $analytics['visits'] ) * 100, 1 ) . ' %' : '—' ) . '</strong></div><div><span>Source principale</span><strong>' . esc_html( $analytics['sources'] ? (string) array_key_first( $analytics['sources'] ) : '—' ) . '</strong></div></div>';
	echo '<h2>Créer un code personnel</h2><form method="post" action="' . esc_url( $action_url ) . '" class="sl-omitland-panel"><input type="hidden" name="action" value="sl_omitland_generate_code">';
	wp_nonce_field( 'sl_omitland_generate_code', 'sl_omitland_code_nonce' );
	echo '<p><label>Code (laisser vide pour générer) <input type="text" name="code" maxlength="24"></label></p><p><label>Nom de l’ambassadeur <input type="text" name="label" maxlength="120"></label></p><p><label>Début <input type="date" name="code_start" value="' . esc_attr( sl_omitland_start_date() ) . '"></label><label>Fin <input type="date" name="code_end" value="' . esc_attr( sl_omitland_end_date() ) . '"></label></p><p><button class="button">Générer le code et le lien</button></p></form>';
	echo '<h2>Codes et performances</h2><div class="sl-omitland-table-wrap"><table class="widefat striped"><thead><tr><th>Code</th><th>Période</th><th>Clics</th><th>Réclamations</th><th>Utilisés</th><th>Lien partageable</th></tr></thead><tbody>';
	foreach ( $codes as $code_post ) {
		$code = $code_post->post_title;
		$link = add_query_arg( array( 'promo' => $code, 'ref' => $code ), home_url( '/bonus-omtland-odza/' ) );
		$claim_url = add_query_arg( array( 'post_type' => SL_OMTLAND_CLAIM_TYPE, 'sl_omitland_code' => $code ), admin_url( 'edit.php' ) );
		$state = get_post_meta( $code_post->ID, '_sl_omitland_code_status', true ) ?: 'active';
		echo '<tr><td><strong>' . esc_html( $code ) . '</strong><br><small>' . esc_html( get_post_meta( $code_post->ID, '_sl_omitland_code_label', true ) ) . '</small><br><span class="sl-omitland-status status-' . esc_attr( sanitize_html_class( $state ) ) . '">' . esc_html( 'active' === $state ? 'Actif' : 'Désactivé' ) . '</span></td><td>' . esc_html( get_post_meta( $code_post->ID, '_sl_omitland_code_start', true ) . ' → ' . get_post_meta( $code_post->ID, '_sl_omitland_code_end', true ) ) . '</td><td>' . esc_html( number_format_i18n( (int) get_post_meta( $code_post->ID, '_sl_omitland_code_clicks', true ) ) ) . '</td><td><a href="' . esc_url( $claim_url ) . '">' . esc_html( number_format_i18n( (int) get_post_meta( $code_post->ID, '_sl_omitland_code_claims', true ) ) ) . '</a></td><td>' . esc_html( number_format_i18n( (int) get_post_meta( $code_post->ID, '_sl_omitland_code_uses', true ) ) ) . '</td><td><input type="text" readonly value="' . esc_attr( $link ) . '" style="width:100%"><a href="' . esc_url( get_edit_post_link( $code_post->ID, '' ) ) . '">Gérer ce code</a></td></tr>';
	}
	if ( ! $codes ) echo '<tr><td colspan="6">Aucun code enregistré.</td></tr>';
	echo '</tbody></table></div></div>';
}

function sl_omitland_save_settings() {
	if ( ! sl_omitland_can_manage() || ! isset( $_POST['sl_omitland_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sl_omitland_settings_nonce'] ) ), 'sl_omitland_save_settings' ) ) {
		wp_die( 'Action non autorisée.' );
	}
	$code = strtoupper( preg_replace( '/[^A-Z0-9]/', '', sanitize_text_field( wp_unslash( $_POST['active_code'] ?? '' ) ) ) );
	if ( ! $code ) $code = sl_omitland_current_code();
	update_option( 'sl_omitland_active_code', $code );
	update_option( 'sl_omitland_start_date', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $_POST['start_date'] ?? '' ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : sl_omitland_start_date() );
	update_option( 'sl_omitland_end_date', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $_POST['end_date'] ?? '' ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : sl_omitland_end_date() );
	update_option( 'sl_omitland_cadence', '4days' === ( $_POST['cadence'] ?? '' ) ? '4days' : 'weekly' );
	update_option( 'sl_omitland_auto_enabled', isset( $_POST['auto_enabled'] ) ? '1' : '0' );
	update_option( 'sl_omitland_paused', isset( $_POST['paused'] ) ? '1' : '0' );
	sl_omitland_ensure_current_code();
	sl_omitland_update_schedule();
	sl_omitland_admin_redirect( 'saved' );
}
add_action( 'admin_post_sl_omitland_save_settings', 'sl_omitland_save_settings' );

function sl_omitland_generate_code_admin() {
	if ( ! sl_omitland_can_manage() || ! isset( $_POST['sl_omitland_code_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sl_omitland_code_nonce'] ) ), 'sl_omitland_generate_code' ) ) {
		wp_die( 'Action non autorisée.' );
	}
	$code = sanitize_text_field( wp_unslash( $_POST['code'] ?? '' ) );
	$code = $code ? strtoupper( preg_replace( '/[^A-Z0-9]/', '', $code ) ) : sl_omitland_generate_code_value();
	$start = isset( $_POST['code_start'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $_POST['code_start'] ) ? sanitize_text_field( wp_unslash( $_POST['code_start'] ) ) : sl_omitland_start_date();
	$end = isset( $_POST['code_end'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $_POST['code_end'] ) ? sanitize_text_field( wp_unslash( $_POST['code_end'] ) ) : sl_omitland_end_date();
	if ( $start <= $end && sl_omitland_create_code( $code, $start, $end, $_POST['label'] ?? '' ) ) {
		sl_omitland_admin_redirect( 'generated' );
	}
	sl_omitland_admin_redirect( 'error' );
}
add_action( 'admin_post_sl_omitland_generate_code', 'sl_omitland_generate_code_admin' );

function sl_omitland_export_claims() {
	if ( ! sl_omitland_can_manage() ) {
		wp_die( 'Accès non autorisé.' );
	}
	$claims = get_posts( array( 'post_type' => SL_OMTLAND_CLAIM_TYPE, 'post_status' => 'any', 'numberposts' => -1, 'orderby' => 'date', 'order' => 'DESC' ) );
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=omitland-reclamations-' . gmdate( 'Y-m-d' ) . '.csv' );
	$output = fopen( 'php://output', 'w' );
	fwrite( $output, "\xEF\xBB\xBF" );
	fputcsv( $output, array( 'Référence', 'Nom', 'Téléphone', 'E-mail', 'Source', 'Code', 'Référent', 'Statut', 'Demandée le' ), ';' );
	$labels = array( 'pending' => 'À vérifier', 'verified' => 'Éligible', 'redeemed' => '50 unités remises', 'rejected' => 'Refusée' );
	foreach ( $claims as $claim ) {
		$status = get_post_meta( $claim->ID, '_sl_omtland_status', true ) ?: 'pending';
		fputcsv( $output, array(
			get_post_meta( $claim->ID, '_sl_omtland_reference', true ),
			get_post_meta( $claim->ID, '_sl_omtland_name', true ),
			get_post_meta( $claim->ID, '_sl_omtland_phone', true ),
			get_post_meta( $claim->ID, '_sl_omtland_email', true ),
			get_post_meta( $claim->ID, '_sl_omtland_source', true ),
			get_post_meta( $claim->ID, '_sl_omitland_code', true ),
			get_post_meta( $claim->ID, '_sl_omitland_referrer', true ),
			$labels[ $status ] ?? $status,
			get_the_date( 'd/m/Y H:i', $claim ),
		), ';' );
	}
	fclose( $output );
	exit;
}
add_action( 'admin_post_sl_omitland_export_claims', 'sl_omitland_export_claims' );

function sl_omitland_export_codes() {
	if ( ! sl_omitland_can_manage() ) {
		wp_die( 'Accès non autorisé.' );
	}
	$codes = get_posts( array( 'post_type' => SL_OMTLAND_CODE_TYPE, 'post_status' => 'any', 'numberposts' => -1, 'orderby' => 'date', 'order' => 'DESC' ) );
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=omitland-codes-' . gmdate( 'Y-m-d' ) . '.csv' );
	$output = fopen( 'php://output', 'w' );
	fwrite( $output, "\xEF\xBB\xBF" );
	fputcsv( $output, array( 'Code', 'Ambassadeur', 'État', 'Début', 'Fin', 'Clics', 'Réclamations', 'Utilisations', 'Lien partageable' ), ';' );
	foreach ( $codes as $code_post ) {
		$code = $code_post->post_title;
		fputcsv( $output, array( $code, get_post_meta( $code_post->ID, '_sl_omitland_code_label', true ), get_post_meta( $code_post->ID, '_sl_omitland_code_status', true ) ?: 'active', get_post_meta( $code_post->ID, '_sl_omitland_code_start', true ), get_post_meta( $code_post->ID, '_sl_omitland_code_end', true ), get_post_meta( $code_post->ID, '_sl_omitland_code_clicks', true ), get_post_meta( $code_post->ID, '_sl_omitland_code_claims', true ), get_post_meta( $code_post->ID, '_sl_omitland_code_uses', true ), add_query_arg( array( 'promo' => $code, 'ref' => $code ), home_url( '/bonus-omtland-odza/' ) ) ), ';' );
	}
	fclose( $output );
	exit;
}
add_action( 'admin_post_sl_omitland_export_codes', 'sl_omitland_export_codes' );

function sl_omitland_export_analytics() {
	if ( ! sl_omitland_can_manage() ) {
		wp_die( 'Accès non autorisé.' );
	}
	$analytics = sl_omitland_analytics_summary();
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=omitland-statistiques-' . gmdate( 'Y-m-d' ) . '.csv' );
	$output = fopen( 'php://output', 'w' );
	fwrite( $output, "\xEF\xBB\xBF" );
	fputcsv( $output, array( 'Indicateur', 'Valeur' ), ';' );
	fputcsv( $output, array( 'Visites (90 jours)', $analytics['visits'] ), ';' );
	fputcsv( $output, array( 'Visiteurs uniques (90 jours)', $analytics['unique_visitors'] ), ';' );
	foreach ( $analytics['sources'] as $source => $count ) {
		fputcsv( $output, array( 'Source : ' . $source, $count ), ';' );
	}
	foreach ( $analytics['campaigns'] as $campaign => $count ) {
		fputcsv( $output, array( 'Campagne : ' . $campaign, $count ), ';' );
	}
	fclose( $output );
	exit;
}
add_action( 'admin_post_sl_omitland_export_analytics', 'sl_omitland_export_analytics' );
