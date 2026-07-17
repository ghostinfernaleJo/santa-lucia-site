<?php
/**
 * Ecran d'attente affiche apres l'initiation du debit.
 *
 * Le client doit valider sur son telephone ; tant que ETATO ne renvoie pas 400,
 * la commande n'est pas payee. Cet ecran interroge le serveur toutes les 3 s.
 * S'il ferme l'onglet, le cron (MMGate_Poller::sweep) prend le relais.
 *
 * @package MMGate_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

class MMGate_Waiting {

	public static function init() {
		add_action( 'woocommerce_thankyou_mmgate', [ __CLASS__, 'render' ], 5 );
	}

	public static function render( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->get_meta( '_mmgate_idoper' ) ) {
			return;
		}
		// Commande deja tranchee : rien a attendre.
		if ( ! $order->has_status( [ 'pending', 'on-hold' ] ) ) {
			return;
		}

		$msisdn = (string) $order->get_meta( '_mmgate_msisdn' );
		?>
		<div id="mmgate-wait" class="mmgate-wait"
		     data-order="<?php echo esc_attr( $order_id ); ?>"
		     data-key="<?php echo esc_attr( $order->get_order_key() ); ?>"
		     data-nonce="<?php echo esc_attr( wp_create_nonce( 'mmgate_poll' ) ); ?>"
		     data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
			<div class="mmgate-wait-spin" aria-hidden="true"></div>
			<h2><?php esc_html_e( 'Validez le paiement sur votre téléphone', 'mmgate-woocommerce' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: %s: numero de telephone */
					esc_html__( 'Une demande de validation a été envoyée au %s. Composez votre code secret Mobile Money pour confirmer.', 'mmgate-woocommerce' ),
					'<strong>' . esc_html( $msisdn ) . '</strong>'
				);
				?>
			</p>
			<p class="mmgate-wait-state" role="status" aria-live="polite">
				<?php esc_html_e( 'En attente de votre validation…', 'mmgate-woocommerce' ); ?>
			</p>
			<p class="mmgate-wait-hint">
				<?php esc_html_e( 'Ne fermez pas cette page. Si vous n\'avez rien reçu, vérifiez que votre téléphone capte, puis composez le menu Mobile Money de votre opérateur.', 'mmgate-woocommerce' ); ?>
			</p>
		</div>

		<style>
		.mmgate-wait{border:1px solid rgba(0,0,0,.1);border-radius:12px;padding:26px;margin:0 0 26px;text-align:center;background:#fff;}
		.mmgate-wait h2{margin:14px 0 8px;font-size:20px;}
		.mmgate-wait-spin{width:38px;height:38px;margin:0 auto;border:3px solid rgba(0,0,0,.12);border-top-color:#E91E63;border-radius:50%;animation:mmgate-spin .9s linear infinite;}
		@keyframes mmgate-spin{to{transform:rotate(360deg);}}
		.mmgate-wait-state{font-weight:600;margin:12px 0 6px;}
		.mmgate-wait-hint{font-size:13px;opacity:.7;margin:0;}
		.mmgate-wait.is-paid .mmgate-wait-spin{border-top-color:#16a34a;animation:none;border-color:#16a34a;}
		.mmgate-wait.is-paid .mmgate-wait-state{color:#16a34a;}
		.mmgate-wait.is-failed .mmgate-wait-spin{border-color:#b32d2e;animation:none;}
		.mmgate-wait.is-failed .mmgate-wait-state{color:#b32d2e;}
		@media (prefers-reduced-motion:reduce){.mmgate-wait-spin{animation:none;}}
		</style>

		<script>
		(function(){
			var box = document.getElementById('mmgate-wait');
			if ( ! box ) return;
			var state = box.querySelector('.mmgate-wait-state');
			var tries = 0;

			function poll(){
				tries++;
				var body = 'action=mmgate_poll'
					+ '&nonce='    + encodeURIComponent(box.dataset.nonce)
					+ '&order_id=' + encodeURIComponent(box.dataset.order)
					+ '&key='      + encodeURIComponent(box.dataset.key);

				fetch(box.dataset.ajax, {
					method: 'POST', credentials: 'same-origin',
					headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: body
				})
				.then(function(r){ return r.json(); })
				.then(function(res){
					if ( ! res || ! res.success ) { return retry(); }
					var d = res.data;
					if ( d.state === 'paid' || d.state === 'done' ) {
						box.classList.add('is-paid');
						state.textContent = <?php echo wp_json_encode( __( 'Paiement confirmé. Redirection…', 'mmgate-woocommerce' ) ); ?>;
						window.location.href = d.redirect || window.location.href.split('?')[0];
						return;
					}
					if ( d.state === 'failed' ) {
						box.classList.add('is-failed');
						state.textContent = ( d.reason ? d.reason + ' — ' : '' )
							+ <?php echo wp_json_encode( __( 'Vous allez pouvoir réessayer, avec ce numéro ou un autre.', 'mmgate-woocommerce' ) ); ?>;
						setTimeout(function(){
							window.location.href = d.retry || window.location.href;
						}, 3200);
						return;
					}
					retry();
				})
				.catch(retry);
			}

			function retry(){
				// Le serveur tranche a 2 min ; on sonde un peu au-dela (~2 min
				// 30 = 50 x 3 s) pour laisser le statut d'echec revenir a l'ecran.
				if ( tries > 50 ) {
					state.textContent = <?php echo wp_json_encode( __( 'Toujours en attente. Vous recevrez un email dès la confirmation.', 'mmgate-woocommerce' ) ); ?>;
					return;
				}
				setTimeout(poll, 3000);
			}

			setTimeout(poll, 2500);
		})();
		</script>
		<?php
	}
}
