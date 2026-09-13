<?php
/**
 * Plugin Name:       MMGate for WooCommerce
 * Plugin URI:        https://github.com/ghostinfernaleJo/mmgate-woocommerce
 * Description:       Encaissez MTN Mobile Money et Orange Money via MMGate. Une seule intégration pour les deux opérateurs : le client valide le débit sur son téléphone, la commande est encaissée automatiquement dès confirmation.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Complexe Santa Lucia
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mmgate-woocommerce
 * Domain Path:       /languages
 * WC requires at least: 6.0
 * WC tested up to:   9.9
 *
 * @package MMGate_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'MMGATE_WC_VERSION', '1.0.0' );
define( 'MMGATE_WC_PATH', plugin_dir_path( __FILE__ ) );
define( 'MMGATE_WC_FILE', __FILE__ );

/**
 * Compatibilite HPOS (stockage des commandes en tables dediees). A declarer
 * avant l'init de WooCommerce, sinon la boutique affiche le plugin comme
 * incompatible et desactive les tables dediees.
 */
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

add_action( 'plugins_loaded', 'mmgate_wc_boot', 20 );
function mmgate_wc_boot() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p><strong>MMGate for WooCommerce</strong> nécessite WooCommerce actif.</p></div>';
		} );
		return;
	}

	require_once MMGATE_WC_PATH . 'includes/class-mmgate-client.php';
	require_once MMGATE_WC_PATH . 'includes/class-mmgate-gateway.php';
	require_once MMGATE_WC_PATH . 'includes/class-mmgate-poller.php';
	require_once MMGATE_WC_PATH . 'includes/class-mmgate-waiting.php';

	// Enregistre ICI et pas au chargement du fichier : la classe n'existe que si
	// WooCommerce est actif. Enregistre plus haut, un site sans WooCommerce
	// evaluerait cron_schedules (quasi chaque requete) sur une classe absente
	// -> fatal « Class not found » sur tout le site, admin compris.
	add_filter( 'cron_schedules', [ 'MMGate_Poller', 'add_interval' ] );

	MMGate_Poller::init();
	MMGate_Waiting::init();

	// Le numéro à débiter est demandé dans une confirmation dédiée au moment
	// de valider le checkout. Les assets vivent dans ce plugin afin que ce
	// parcours reste fonctionnel indépendamment du thème ou des autres plugins.
	add_action( 'wp_enqueue_scripts', 'mmgate_wc_enqueue_checkout_assets', 20 );

	add_filter( 'woocommerce_payment_gateways', function ( $gateways ) {
		$gateways[] = 'MMGate_Gateway';
		return $gateways;
	} );

	// Mobile Money en tête de liste au checkout : paiement immédiat, on le
	// propose avant "confirmer par téléphone" (qui reste un repli manuel).
	add_filter( 'woocommerce_available_payment_gateways', function ( $gateways ) {
		if ( mmgate_wc_is_store_paused() ) {
			return [];
		}
		if ( isset( $gateways['mmgate'] ) ) {
			$gateways = [ 'mmgate' => $gateways['mmgate'] ] + $gateways;
		}
		return $gateways;
	}, 20 );

	add_filter( 'woocommerce_is_purchasable', 'mmgate_wc_pause_purchasable', 20, 2 );
	add_filter( 'woocommerce_add_to_cart_validation', 'mmgate_wc_pause_add_to_cart', 20, 5 );
	add_action( 'woocommerce_check_cart_items', 'mmgate_wc_block_paused_cart' );
	add_action( 'woocommerce_checkout_process', 'mmgate_wc_block_paused_checkout' );
	add_action( 'woocommerce_before_cart', 'mmgate_wc_pause_notice' );
	add_action( 'woocommerce_before_checkout_form', 'mmgate_wc_pause_notice' );
	add_action( 'woocommerce_cart_calculate_fees', 'mmgate_wc_add_payment_fee', 20 );

	add_action( 'init', function () {
		load_plugin_textdomain( 'mmgate-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	} );
}

/** Interrupteur global de mise en pause des commandes et paiements. */
function mmgate_wc_is_store_paused() {
	$settings = (array) get_option( 'woocommerce_mmgate_settings', [] );
	return ! empty( $settings['store_paused'] ) && 'yes' === $settings['store_paused'];
}

/** Accès au contrôle rapide réservé aux administrateurs du site. */
function mmgate_wc_can_toggle_store() {
	return current_user_can( 'manage_options' );
}

/** Bascule l'état depuis le bouton de la barre d'administration. */
add_action( 'admin_init', function () {
	if ( ! isset( $_GET['mmgate_store_toggle'] ) || ! mmgate_wc_can_toggle_store() ) {
		return;
	}

	if ( ! wp_verify_nonce( $_GET['_mmgate_nonce'] ?? '', 'mmgate_store_toggle' ) ) {
		return;
	}

	$settings                  = (array) get_option( 'woocommerce_mmgate_settings', [] );
	$settings['store_paused'] = mmgate_wc_is_store_paused() ? 'no' : 'yes';
	update_option( 'woocommerce_mmgate_settings', $settings );

	wp_safe_redirect( remove_query_arg( [ 'mmgate_store_toggle', '_mmgate_nonce' ], wp_get_referer() ?: admin_url() ) );
	exit;
} );

function mmgate_wc_store_toggle_url() {
	return wp_nonce_url( add_query_arg( 'mmgate_store_toggle', '1' ), 'mmgate_store_toggle', '_mmgate_nonce' );
}

/** Raccourci ON/OFF dans la barre d'administration, sur toutes les pages. */
add_action( 'admin_bar_menu', function ( $bar ) {
	if ( ! mmgate_wc_can_toggle_store() ) {
		return;
	}

	$paused = mmgate_wc_is_store_paused();
	$bar->add_node( [
		'id'    => 'mmgate-store-toggle',
		'title' => 'Paiements : ' . ( $paused ? '🔴 OFF' : '🟢 ON' ),
		'href'  => esc_url( mmgate_wc_store_toggle_url() ),
		'meta'  => [ 'title' => $paused ? 'Cliquer pour réactiver les paiements' : 'Cliquer pour suspendre les paiements' ],
	] );
}, 101 );

function mmgate_wc_pause_message() {
	return __( 'Les commandes et paiements sont temporairement suspendus. Merci de revenir un peu plus tard.', 'mmgate-woocommerce' );
}

function mmgate_wc_pause_purchasable( $purchasable, $product ) {
	return mmgate_wc_is_store_paused() ? false : $purchasable;
}

function mmgate_wc_pause_add_to_cart( $passed ) {
	if ( mmgate_wc_is_store_paused() ) {
		wc_add_notice( mmgate_wc_pause_message(), 'error' );
		return false;
	}
	return $passed;
}

function mmgate_wc_block_paused_cart() {
	if ( mmgate_wc_is_store_paused() && function_exists( 'WC' ) && WC()->cart && ! WC()->cart->is_empty() ) {
		wc_add_notice( mmgate_wc_pause_message(), 'error' );
	}
}

function mmgate_wc_block_paused_checkout() {
	if ( mmgate_wc_is_store_paused() ) {
		wc_add_notice( mmgate_wc_pause_message(), 'error' );
	}
}

function mmgate_wc_pause_notice() {
	if ( mmgate_wc_is_store_paused() ) {
		wc_print_notice( mmgate_wc_pause_message(), 'notice' );
	}
}

/** Pourcentage des frais Mobile Money, réglable dans les paramètres de la passerelle. */
function mmgate_wc_payment_fee_percent() {
	$settings = (array) get_option( 'woocommerce_mmgate_settings', [] );
	$value = isset( $settings['payment_fee_percent'] ) ? str_replace( ',', '.', (string) $settings['payment_fee_percent'] ) : '2';
	return min( 100, max( 0, (float) $value ) );
}

/** Ajoute les frais comme ligne WooCommerce : ils apparaissent dans la commande et la facture. */
function mmgate_wc_add_payment_fee( $cart ) {
	if ( is_admin() && ! wp_doing_ajax() ) return;
	if ( mmgate_wc_is_store_paused() ) return;
	if ( ! function_exists( 'WC' ) || ! WC()->session || 'mmgate' !== WC()->session->get( 'chosen_payment_method' ) ) return;
	$percent = mmgate_wc_payment_fee_percent();
	if ( $percent <= 0 || ! $cart || ! method_exists( $cart, 'get_cart_contents_total' ) ) return;
	$base = (float) $cart->get_cart_contents_total();
	$fee  = round( $base * $percent / 100, 2 );
	if ( $fee <= 0 ) return;
	$label = sprintf( __( 'Frais de paiement Mobile Money (%s%%)', 'mmgate-woocommerce' ), rtrim( rtrim( number_format( $percent, 2, '.', '' ), '0' ), '.' ) );
	$cart->add_fee( $label, $fee, false );
}

/** Charge l'interface de confirmation Mobile Money sur le checkout uniquement. */
function mmgate_wc_enqueue_checkout_assets() {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) ) {
		return;
	}

	$js_file  = MMGATE_WC_PATH . 'assets/mmgate-checkout.js';
	$css_file = MMGATE_WC_PATH . 'assets/mmgate-checkout.css';

	wp_enqueue_style(
		'mmgate-checkout',
		plugins_url( 'assets/mmgate-checkout.css', MMGATE_WC_FILE ),
		[],
		file_exists( $css_file ) ? (string) filemtime( $css_file ) : MMGATE_WC_VERSION
	);
	wp_enqueue_script(
		'mmgate-checkout',
		plugins_url( 'assets/mmgate-checkout.js', MMGATE_WC_FILE ),
		[],
		file_exists( $js_file ) ? (string) filemtime( $js_file ) : MMGATE_WC_VERSION,
		true
	);
}

/** Lien « Réglages » depuis la liste des extensions. */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
	$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=mmgate' );
	array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . __( 'Réglages', 'mmgate-woocommerce' ) . '</a>' );
	return $links;
} );

register_deactivation_hook( __FILE__, function () {
	wp_unschedule_hook( 'mmgate_sweep' );
	// Les verifications unitaires encore en file (une par commande en attente)
	// ne doivent pas survivre au plugin : leurs callbacks n'existeraient plus.
	wp_unschedule_hook( 'mmgate_check_order' );
} );

/**
 * Envoie un SMS via la file MMGate (5 FCFA débités du solde partenaire).
 *
 * Exposé volontairement en fonction publique : le même compte partenaire sert au
 * paiement et au SMS, autant que les autres extensions du site en profitent.
 * Le corps est translittéré en ASCII (compatibilité GSM 7 bits).
 *
 * @param string $msisdn  Numéro destinataire.
 * @param string $message Texte du SMS.
 * @return array|WP_Error Réponse MMGate (ETAT 300 = accepté).
 */
function mmgate_send_sms( $msisdn, $message ) {
	if ( ! class_exists( 'MMGate_Gateway' ) || ! function_exists( 'WC' ) ) {
		return new WP_Error( 'mmgate_unavailable', 'MMGate indisponible.' );
	}
	$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : [];
	if ( empty( $gateways['mmgate'] ) ) {
		return new WP_Error( 'mmgate_unavailable', 'Passerelle MMGate introuvable.' );
	}
	return $gateways['mmgate']->client()->sms( $msisdn, $message );
}
