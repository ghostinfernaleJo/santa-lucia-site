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

	add_filter( 'woocommerce_payment_gateways', function ( $gateways ) {
		$gateways[] = 'MMGate_Gateway';
		return $gateways;
	} );

	// Mobile Money en tête de liste au checkout : paiement immédiat, on le
	// propose avant "confirmer par téléphone" (qui reste un repli manuel).
	add_filter( 'woocommerce_available_payment_gateways', function ( $gateways ) {
		if ( isset( $gateways['mmgate'] ) ) {
			$gateways = [ 'mmgate' => $gateways['mmgate'] ] + $gateways;
		}
		return $gateways;
	}, 20 );

	load_plugin_textdomain( 'mmgate-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
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
