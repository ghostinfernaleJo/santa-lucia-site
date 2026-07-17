<?php
/**
 * Suivi des transactions (remplace le webhook que MMGate n'expose pas).
 *
 * MMGate ne rappelle jamais notre serveur : les URL CONFIRPAIE / CONFIRRETRAIT
 * de leur documentation sont appelees par l'operateur VERS MMGate, pas vers
 * nous. C'est donc a nous d'interroger ETATO jusqu'a un statut definitif.
 *
 * Deux chemins complementaires, parce qu'aucun des deux ne suffit seul :
 *  - AJAX depuis l'ecran d'attente : reactif tant que le client regarde la page.
 *  - Cron : rattrape les commandes dont le client a ferme l'onglet. Sans lui,
 *    un paiement valide resterait « en attente » pour toujours.
 *
 * @package MMGate_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

class MMGate_Poller {

	const CRON_ONE   = 'mmgate_check_order';
	const CRON_SWEEP = 'mmgate_sweep';

	/** Delai au-dela duquel une validation non faite est abandonnee. */
	const TIMEOUT = 15 * MINUTE_IN_SECONDS;

	public static function init() {
		add_action( self::CRON_ONE, [ __CLASS__, 'check' ], 10, 1 );
		add_action( self::CRON_SWEEP, [ __CLASS__, 'sweep' ] );

		add_action( 'wp_ajax_mmgate_poll', [ __CLASS__, 'ajax_poll' ] );
		add_action( 'wp_ajax_nopriv_mmgate_poll', [ __CLASS__, 'ajax_poll' ] );

		if ( ! wp_next_scheduled( self::CRON_SWEEP ) ) {
			wp_schedule_event( time() + 120, 'mmgate_two_min', self::CRON_SWEEP );
		}
	}

	/** WP n'a pas d'intervalle plus court que « hourly » par defaut. */
	public static function add_interval( $schedules ) {
		$schedules['mmgate_two_min'] = [
			'interval' => 120,
			'display'  => __( 'Toutes les 2 minutes (MMGate)', 'mmgate-woocommerce' ),
		];
		return $schedules;
	}

	public static function schedule( $order_id ) {
		wp_schedule_single_event( time() + 20, self::CRON_ONE, [ (int) $order_id ] );
	}

	/**
	 * Interroge ETATO pour une commande et applique le resultat.
	 *
	 * @return string pending|paid|failed|done
	 */
	public static function check( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return 'done';
		}

		$idoper = (string) $order->get_meta( '_mmgate_idoper' );
		if ( $idoper === '' ) {
			return 'done';
		}
		// Deja tranchee : ne pas retoucher une commande encaissee ou echouee.
		if ( ! $order->has_status( [ 'pending', 'on-hold' ] ) ) {
			return 'done';
		}

		$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : [];
		if ( empty( $gateways['mmgate'] ) ) {
			return 'pending';
		}
		/** @var MMGate_Gateway $gw */
		$gw  = $gateways['mmgate'];
		$res = $gw->client()->etato( $idoper );

		if ( is_wp_error( $res ) ) {
			// Panne reseau : on ne conclut rien, on repassera.
			self::requeue( $order );
			return 'pending';
		}

		$etato = isset( $res['ETATO'] ) ? (int) $res['ETATO'] : 0;

		switch ( $etato ) {
			case MMGate_Client::ETATO_OK:
				// payment_complete() decremente le stock et declenche les emails.
				$order->add_order_note( sprintf( __( 'MMGate : paiement confirmé (IDOPER %s).', 'mmgate-woocommerce' ), $idoper ) );
				$order->payment_complete( $idoper );
				return 'paid';

			case MMGate_Client::ETATO_ANNULEE:
				$order->update_status( 'failed', sprintf( __( 'MMGate : paiement annulé ou refusé (IDOPER %s).', 'mmgate-woocommerce' ), $idoper ) );
				return 'failed';

			case MMGate_Client::ETATO_INTROUVABLE:
				$order->update_status( 'failed', sprintf( __( 'MMGate : transaction introuvable (IDOPER %s).', 'mmgate-woocommerce' ), $idoper ) );
				return 'failed';

			case MMGate_Client::ETATO_ENCOURS:
			case MMGate_Client::ETATO_ATTENTE:
			default:
				$started = (int) $order->get_meta( '_mmgate_started' );
				if ( $started && ( time() - $started ) > self::TIMEOUT ) {
					$order->update_status( 'failed', __( 'MMGate : délai de validation dépassé, aucune confirmation du client.', 'mmgate-woocommerce' ) );
					return 'failed';
				}
				self::requeue( $order );
				return 'pending';
		}
	}

	private static function requeue( $order ) {
		if ( ! wp_next_scheduled( self::CRON_ONE, [ $order->get_id() ] ) ) {
			wp_schedule_single_event( time() + 30, self::CRON_ONE, [ $order->get_id() ] );
		}
	}

	/**
	 * Filet de securite : rattrape les commandes MMGate encore en attente.
	 * Un evenement cron unique peut se perdre (cron WP declenche par le trafic) ;
	 * ce balayage garantit qu'aucun paiement valide ne reste orphelin.
	 */
	public static function sweep() {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return;
		}
		$orders = wc_get_orders( [
			'limit'          => 25,
			'status'         => [ 'pending', 'on-hold' ],
			'payment_method' => 'mmgate',
			'return'         => 'ids',
			'date_created'   => '>' . ( time() - DAY_IN_SECONDS ),
		] );
		foreach ( $orders as $id ) {
			self::check( $id );
		}
	}

	/** Appele par l'ecran d'attente. Nonce + cle de commande = double controle. */
	public static function ajax_poll() {
		check_ajax_referer( 'mmgate_poll', 'nonce' );

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$key      = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
		$order    = $order_id ? wc_get_order( $order_id ) : null;

		// La cle de commande evite qu'un tiers sonde les commandes d'autrui en
		// incrementant un identifiant.
		if ( ! $order || ! hash_equals( $order->get_order_key(), $key ) ) {
			wp_send_json_error( [ 'message' => __( 'Commande introuvable.', 'mmgate-woocommerce' ) ], 404 );
		}

		$state = self::check( $order_id );
		$order = wc_get_order( $order_id ); // relire : check() a pu changer le statut

		wp_send_json_success( [
			'state'    => $state,
			'status'   => $order->get_status(),
			'redirect' => in_array( $state, [ 'paid', 'done' ], true ) ? $order->get_checkout_order_received_url() : '',
		] );
	}
}
