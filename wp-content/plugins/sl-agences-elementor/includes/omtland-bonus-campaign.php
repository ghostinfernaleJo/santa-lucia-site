<?php
/**
 * Campagne OMTLAND ODZA : première expérience offerte.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SL_OMTLAND_CLAIM_TYPE = 'sl_omtland_claim';
const SL_OMTLAND_PROMO_CODE = 'OMIT237XVZ';
const SL_OMTLAND_START_DATE = '2026-09-19';
const SL_OMTLAND_END_DATE = '2026-10-30';

function sl_omtland_register_claim_type() {
	register_post_type( SL_OMTLAND_CLAIM_TYPE, array(
		'labels' => array(
			'name'          => 'Bonus OMTLAND',
			'singular_name' => 'Réclamation OMTLAND',
			'menu_name'     => 'Bonus OMTLAND',
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

function sl_omtland_ensure_campaign_page() {
	$page_id = (int) get_option( 'sl_omtland_campaign_page_id' );
	if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
		return;
	}

	$page = get_page_by_path( 'bonus-omtland-odza' );
	if ( ! $page ) {
		$page_id = wp_insert_post( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => '50 unités offertes chez OMTLAND ODZA',
			'post_name'    => 'bonus-omtland-odza',
			'post_content' => '[sl_omtland_bonus]',
		) );
	} else {
		$page_id = $page->ID;
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
	wp_enqueue_style( 'sl-omtland-bonus', SL_AGENCES_URL . 'assets/css/omtland-bonus.css', array(), '1.0.0' );
}
add_action( 'wp_enqueue_scripts', 'sl_omtland_campaign_assets', 110 );

function sl_omtland_promotion_is_open() {
	$today = current_time( 'Y-m-d' );
	return $today >= SL_OMTLAND_START_DATE && $today <= SL_OMTLAND_END_DATE;
}

function sl_omtland_bonus_shortcode() {
	$state = isset( $_GET['claim'] ) ? sanitize_key( wp_unslash( $_GET['claim'] ) ) : '';
	$reference = isset( $_GET['ref'] ) ? sanitize_text_field( wp_unslash( $_GET['ref'] ) ) : '';
	$hero = SL_AGENCES_URL . 'assets/images/omtland-bonus-hero.png';

	ob_start();
	?>
	<main class="sl-omtland-page">
		<section class="sl-omtland-hero" style="background-image:url('<?php echo esc_url( $hero ); ?>')">
			<div class="sl-omtland-hero-inner">
				<p class="sl-omtland-eyebrow">OMTLAND ODZA</p>
				<h1>Ta première aventure VR est offerte.</h1>
				<p class="sl-omtland-lead">Reçois <strong>50 unités gratuites</strong>, soit l'accès à une machine ou à un jeu de ton choix.</p>
				<a class="sl-omtland-cta" href="#reclamer">Réclamer mes 50 unités</a>
			</div>
		</section>

		<section class="sl-omtland-offer" aria-label="Détails de l'offre">
			<div class="sl-omtland-inner">
				<div class="sl-omtland-intro">
					<div><p class="sl-omtland-kicker">Bonus de bienvenue</p><h2>Une première expérience, sans rien payer.</h2></div>
					<p>Tu n'as encore jamais joué chez OMTLAND ODZA ? Rejoins notre communauté, remplis le formulaire et présente le code promo à ton arrivée. Après vérification, une carte de <strong>50 unités gratuites</strong> te sera remise.</p>
				</div>
				<div class="sl-omtland-steps">
					<div><span>01</span><h3>Remplis le formulaire</h3><p>Enregistre ton nom et ton numéro de téléphone.</p></div>
					<div><span>02</span><h3>Présente ton code</h3><p>Montre le code <strong><?php echo esc_html( SL_OMTLAND_PROMO_CODE ); ?></strong> à OMTLAND ODZA.</p></div>
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
					<p class="sl-omtland-kicker">19 septembre - 30 octobre 2026</p>
					<h2>Réclame tes 50 unités.</h2>
					<p>Offre réservée aux nouveaux membres de la communauté, valable une seule fois et uniquement chez OMTLAND ODZA.</p>
					<dl><div><dt>Lieu</dt><dd>OMTLAND ODZA</dd></div><div><dt>Code promotionnel</dt><dd><?php echo esc_html( SL_OMTLAND_PROMO_CODE ); ?></dd></div><div><dt>Bonus</dt><dd>50 unités gratuites</dd></div></dl>
				</div>
				<div class="sl-omtland-form-wrap">
					<?php if ( 'success' === $state ) : ?>
						<div class="sl-omtland-notice is-success" role="status"><strong>Ta demande est enregistrée.</strong><p>Présente le code <b><?php echo esc_html( SL_OMTLAND_PROMO_CODE ); ?></b> et ta référence <b><?php echo esc_html( $reference ); ?></b> à ton arrivée chez OMTLAND ODZA.</p></div>
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
						<label>Comment as-tu découvert OMTLAND ?<select name="source"><option value="">Choisir</option><option>WhatsApp</option><option>Facebook</option><option>Instagram</option><option>TikTok</option><option>Un proche</option><option>Autre</option></select></label>
						<label>Code promotionnel *<input name="promo_code" required value="<?php echo esc_attr( SL_OMTLAND_PROMO_CODE ); ?>" autocomplete="off"></label>
						<label class="sl-omtland-check"><input type="checkbox" name="first_visit" value="1" required><span>Je confirme n'avoir jamais bénéficié d'une expérience chez OMTLAND ODZA et j'accepte le traitement de mes informations pour vérifier mon éligibilité.</span></label>
						<button type="submit">Réclamer mes 50 unités</button>
					</form>
					<?php elseif ( ! sl_omtland_promotion_is_open() ) : ?>
						<div class="sl-omtland-notice is-error"><strong>La promotion n'est pas ouverte.</strong><p>Elle est valable du 19 septembre au 30 octobre 2026.</p></div>
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

	if ( ! $name || ! $phone_key || SL_OMTLAND_PROMO_CODE !== $code || ! $first_visit || ( $email && ! is_email( $email ) ) ) {
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
	update_post_meta( $post_id, '_sl_omtland_reference', $reference );
	update_post_meta( $post_id, '_sl_omtland_status', 'pending' );

	$subject = 'Nouvelle réclamation bonus OMTLAND - ' . $reference;
	$message = "Nom : {$name}\nTéléphone : {$phone}\nE-mail : " . ( $email ?: 'Non renseigné' ) . "\nSource : " . ( $source ?: 'Non renseignée' ) . "\nRéférence : {$reference}";
	wp_mail( get_option( 'admin_email' ), $subject, $message );
	sl_omtland_claim_redirect( 'success', $reference );
}
add_action( 'admin_post_nopriv_sl_omtland_claim', 'sl_omtland_handle_claim' );
add_action( 'admin_post_sl_omtland_claim', 'sl_omtland_handle_claim' );

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
}

function sl_omtland_save_claim( $post_id ) {
	if ( ! isset( $_POST['sl_omtland_admin_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sl_omtland_admin_nonce'] ) ), 'sl_omtland_save_claim' ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$allowed = array( 'pending', 'verified', 'redeemed', 'rejected' );
	$status = isset( $_POST['sl_omtland_status'] ) ? sanitize_key( wp_unslash( $_POST['sl_omtland_status'] ) ) : 'pending';
	if ( in_array( $status, $allowed, true ) ) {
		update_post_meta( $post_id, '_sl_omtland_status', $status );
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
