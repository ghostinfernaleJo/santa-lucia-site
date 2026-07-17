<?php
/**
 * Passerelle de paiement WooCommerce « Mobile Money (MMGate) ».
 *
 * Parcours : le client valide sur son telephone (notification MoMo / USSD).
 * MMGate n'appelle AUCUN webhook chez nous — c'est a nous d'interroger ETATO
 * jusqu'a un statut definitif. La commande n'est encaissee qu'a ETATO 400.
 *
 * @package MMGate_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

class MMGate_Gateway extends WC_Payment_Gateway {

	public function __construct() {
		$this->id                 = 'mmgate';
		$this->method_title       = __( 'Mobile Money (MMGate)', 'mmgate-woocommerce' );
		$this->method_description = __( 'Encaisse MTN MoMo et Orange Money via MMGate. Le client valide le débit sur son téléphone.', 'mmgate-woocommerce' );
		$this->has_fields         = true;
		$this->icon               = '';
		$this->supports           = [ 'products' ];

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title', __( 'Mobile Money (MTN / Orange)', 'mmgate-woocommerce' ) );
		$this->description = $this->get_option( 'description' );
		$this->enabled     = $this->get_option( 'enabled' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
	}

	/**
	 * Identifiants : les constantes (wp-config.php) l'emportent sur les reglages
	 * en base. Un secret dans wp-config n'est pas exporte avec la base de donnees
	 * et ne s'affiche pas dans l'admin — c'est le stockage a privilegier.
	 */
	public function creds() {
		return [
			'cdprt' => defined( 'MMGATE_CDPRT' ) ? MMGATE_CDPRT : $this->get_option( 'cdprt' ),
			'usr'   => defined( 'MMGATE_USR' )   ? MMGATE_USR   : $this->get_option( 'usr' ),
			'pwd'   => defined( 'MMGATE_PWD' )   ? MMGATE_PWD   : $this->get_option( 'pwd' ),
			'token' => defined( 'MMGATE_TOKEN' ) ? MMGATE_TOKEN : $this->get_option( 'token' ),
		];
	}

	public function client() {
		return new MMGate_Client( $this->creds() );
	}

	public function init_form_fields() {
		$this->form_fields = [
			'enabled' => [
				'title'   => __( 'Activer', 'mmgate-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Activer le paiement Mobile Money (MMGate)', 'mmgate-woocommerce' ),
				'default' => 'no',
			],
			'title' => [
				'title'       => __( 'Titre', 'mmgate-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Nom affiché au client au moment du paiement.', 'mmgate-woocommerce' ),
				'default'     => __( 'Mobile Money (MTN / Orange)', 'mmgate-woocommerce' ),
				'desc_tip'    => true,
			],
			'description' => [
				'title'   => __( 'Description', 'mmgate-woocommerce' ),
				'type'    => 'textarea',
				'default' => __( 'Payez avec MTN MoMo ou Orange Money. Vous recevrez une demande de validation sur votre téléphone.', 'mmgate-woocommerce' ),
			],
			'creds_title' => [
				'title'       => __( 'Identifiants partenaire', 'mmgate-woocommerce' ),
				'type'        => 'title',
				'description' => __( 'Fournis par MMGate. <strong>Recommandé :</strong> définissez-les plutôt dans <code>wp-config.php</code> via les constantes <code>MMGATE_CDPRT</code>, <code>MMGATE_USR</code>, <code>MMGATE_PWD</code> et <code>MMGATE_TOKEN</code> — elles prennent le dessus sur les champs ci-dessous et ne partent pas dans les exports de base de données.', 'mmgate-woocommerce' ),
			],
			'cdprt' => [
				'title'       => __( 'Code partenaire (CDPRT)', 'mmgate-woocommerce' ),
				'type'        => 'text',
				'description' => defined( 'MMGATE_CDPRT' ) ? __( '<strong>Défini dans wp-config.php</strong> — ce champ est ignoré.', 'mmgate-woocommerce' ) : '',
			],
			'usr' => [
				'title'       => __( 'Utilisateur API (USR)', 'mmgate-woocommerce' ),
				'type'        => 'text',
				'description' => defined( 'MMGATE_USR' ) ? __( '<strong>Défini dans wp-config.php</strong> — ce champ est ignoré.', 'mmgate-woocommerce' ) : '',
			],
			'pwd' => [
				'title'       => __( 'Mot de passe API (PWD)', 'mmgate-woocommerce' ),
				'type'        => 'password',
				'description' => defined( 'MMGATE_PWD' ) ? __( '<strong>Défini dans wp-config.php</strong> — ce champ est ignoré.', 'mmgate-woocommerce' ) : '',
			],
			'token' => [
				'title'       => __( 'Token partenaire (X-Partner-Token)', 'mmgate-woocommerce' ),
				'type'        => 'password',
				'description' => defined( 'MMGATE_TOKEN' ) ? __( '<strong>Défini dans wp-config.php</strong> — ce champ est ignoré.', 'mmgate-woocommerce' ) : '',
			],
			'advanced_title' => [
				'title' => __( 'Avancé', 'mmgate-woocommerce' ),
				'type'  => 'title',
			],
			'endpoint' => [
				'title'       => __( 'Endpoint d\'encaissement', 'mmgate-woocommerce' ),
				'type'        => 'select',
				'default'     => 'PAIEMENTP',
				'options'     => [
					'PAIEMENTP' => 'PAIEMENTP — débite le numéro du client (encaissement)',
					'DEPOTP'    => 'DEPOTP — crédite le numéro (décaissement)',
				],
				'description' => __( '<strong>Ne changez ceci que si MMGate vous le demande.</strong> La documentation MMGate se contredit sur le sens du flux : l\'onglet PAIEMENTP annonce un envoi <em>vers</em> le client, alors que le guide décrit le numéro EXPO comme « bénéficiaire du débit ». <code>PAIEMENTP</code> est le bon choix pour encaisser — c\'est aussi le seul des deux qui n\'a pas de code d\'erreur « solde partenaire insuffisant ». <strong>Validez toujours par une transaction réelle de faible montant avant la mise en production.</strong>', 'mmgate-woocommerce' ),
			],
			'confirm_duplicate' => [
				'title'       => __( 'Doublons', 'mmgate-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Rejouer automatiquement après un ETAT 600', 'mmgate-woocommerce' ),
				'default'     => 'yes',
				'description' => __( 'MMGate refuse (ETAT 600) une opération identique — même numéro, même montant — dans une fenêtre d\'environ 10 minutes. Sur une boutique, ce cas est <strong>légitime</strong> : deux clients peuvent acheter le même article au même prix à quelques minutes d\'écart. Coché, la commande est rejouée avec l\'en-tête de confirmation. Le garde-fou reste l\'identifiant de commande : une même commande n\'est jamais initiée deux fois.', 'mmgate-woocommerce' ),
			],
			'debug' => [
				'title'       => __( 'Journalisation', 'mmgate-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Consigner les échanges dans les journaux WooCommerce', 'mmgate-woocommerce' ),
				'default'     => 'no',
				'description' => __( 'Source « mmgate ». Les identifiants sont toujours masqués, y compris ici.', 'mmgate-woocommerce' ),
			],
		];
	}

	/** Avertit dans l'admin si la passerelle est active mais inutilisable. */
	public function admin_options() {
		if ( 'yes' === $this->enabled && ! $this->client()->is_configured() ) {
			echo '<div class="notice notice-error inline"><p><strong>'
				. esc_html__( 'Identifiants incomplets : la passerelle est active mais aucun paiement ne peut aboutir.', 'mmgate-woocommerce' )
				. '</strong> ' . esc_html__( 'Les quatre valeurs sont requises — le token seul ne suffit pas, car CDPRT, USR et PWD figurent dans chaque appel.', 'mmgate-woocommerce' )
				. '</p></div>';
		}

		$this->warn_risky_password();
		$this->render_connection_test();
		parent::admin_options();
	}

	/**
	 * L'API MMGate fait transiter le mot de passe dans le CHEMIN de l'URL.
	 * Certains caracteres y sont donc hasardeux :
	 *
	 *  - « # » delimite un fragment : non encode, l'URL est tronquee avant
	 *    l'envoi ; encode en %23, il n'arrive intact que si le routeur MMGate
	 *    decode ses segments. Si ce n'est pas le cas, AUCUN encodage ne marche.
	 *  - « / » et « ? » redecoupent le chemin ou ouvrent la query string : meme
	 *    encodes, beaucoup de serveurs les normalisent en amont de PHP.
	 *  - « % » peut subir un double decodage.
	 *
	 * Symptome typique : ETAT 202 « mot de passe erroné » avec un mot de passe
	 * pourtant exact. Impossible a deviner sans connaitre cette contrainte,
	 * d'ou cet avertissement.
	 */
	private function warn_risky_password() {
		$pwd = $this->creds()['pwd'];
		if ( '' === $pwd ) {
			return;
		}

		$risques = [];
		foreach ( [ '#' => 'dièse', '/' => 'barre oblique', '?' => 'point d\'interrogation', '%' => 'pourcent' ] as $c => $nom ) {
			if ( false !== strpos( $pwd, $c ) ) {
				$risques[] = sprintf( '<code>%s</code> (%s)', esc_html( $c ), esc_html( $nom ) );
			}
		}
		if ( ! $risques ) {
			return;
		}

		echo '<div class="notice notice-warning inline"><p><strong>'
			. esc_html__( 'Votre mot de passe API contient des caractères à risque : ', 'mmgate-woocommerce' )
			. wp_kses_post( implode( ', ', $risques ) ) . '</strong></p><p>'
			. esc_html__( 'L\'API MMGate transmet le mot de passe dans le chemin de l\'URL. Ces caractères y ont une signification particulière et peuvent être altérés avant d\'atteindre MMGate — vous obtiendriez alors « ETAT 202 : mot de passe erroné » alors que votre mot de passe est correct.', 'mmgate-woocommerce' )
			. '</p><p><strong>'
			. esc_html__( 'Recommandation : demandez à MMGate un mot de passe API purement alphanumérique (lettres et chiffres uniquement, 24 caractères ou plus).', 'mmgate-woocommerce' )
			. '</strong> '
			. esc_html__( 'Vous n\'y perdez rien en robustesse, et vous éliminez toute une classe de pannes silencieuses.', 'mmgate-woocommerce' )
			. '</p></div>';
	}

	/**
	 * Test de connexion via SOLDE.
	 *
	 * SOLDE ne deplace pas d'argent : c'est le seul moyen de valider CDPRT / USR
	 * / PWD sans risque. A faire imperativement AVANT le premier paiement reel —
	 * si les identifiants sont faux, autant l'apprendre ici plutot qu'avec la
	 * carte d'un client.
	 */
	private function render_connection_test() {
		$result = '';
		if ( isset( $_GET['mmgate_test'] ) && check_admin_referer( 'mmgate_test' ) && current_user_can( 'manage_woocommerce' ) ) {
			$res = $this->client()->solde();
			if ( is_wp_error( $res ) ) {
				$result = '<div class="notice notice-error inline"><p><strong>' . esc_html__( 'Échec :', 'mmgate-woocommerce' ) . '</strong> '
					. esc_html( $res->get_error_message() ) . '</p></div>';
			} elseif ( isset( $res['ETAT'] ) && 200 === (int) $res['ETAT'] ) {
				$solde = isset( $res['SOLDE'] ) ? $res['SOLDE'] : '?';
				$result = '<div class="notice notice-success inline"><p><strong>' . esc_html__( 'Connexion réussie.', 'mmgate-woocommerce' ) . '</strong> '
					. sprintf(
						/* translators: 1: code partenaire, 2: solde */
						esc_html__( 'Partenaire %1$s — solde : %2$s FCFA.', 'mmgate-woocommerce' ),
						esc_html( isset( $res['CDPRT'] ) ? $res['CDPRT'] : '?' ),
						esc_html( $solde )
					)
					// L'endpoint SOLDE s'authentifie par URL et n'envoie PAS le
					// token : un succes ici ne dit donc rien de sa validite.
					// Le taire laisserait croire que tout est verifie.
					. '</p><p>' . esc_html__( 'CDPRT, USR et PWD sont valides. Attention : ce test n\'utilise pas le token partenaire — seuls les paiements l\'exigent. Un token absent ou périmé se manifestera au premier paiement, pas ici.', 'mmgate-woocommerce' ) . '</p></div>';
			} else {
				$etat = isset( $res['ETAT'] ) ? (int) $res['ETAT'] : 0;
				$diag = self::etat_diagnostic( $etat );
				$result = '<div class="notice notice-error inline"><p><strong>'
					. sprintf( esc_html__( 'Refusé par MMGate (ETAT %d) — %s', 'mmgate-woocommerce' ), $etat, esc_html( $diag['quoi'] ) )
					. '</strong></p><p>' . wp_kses_post( $diag['action'] ) . '</p></div>';
			}
		}

		$url = wp_nonce_url(
			add_query_arg( 'mmgate_test', '1', admin_url( 'admin.php?page=wc-settings&tab=checkout&section=mmgate' ) ),
			'mmgate_test'
		);

		echo $result; // phpcs:ignore WordPress.Security.EscapeOutput -- markup construit ci-dessus
		echo '<p style="margin:14px 0;"><a href="' . esc_url( $url ) . '" class="button">'
			. esc_html__( 'Tester la connexion (consultation du solde)', 'mmgate-woocommerce' ) . '</a>'
			. ' <span style="opacity:.7;font-size:12px;">'
			. esc_html__( 'Vérifie vos identifiants sans déplacer d\'argent. À faire avant le premier paiement réel.', 'mmgate-woocommerce' )
			. '</span></p>';
	}

	public function is_available() {
		return 'yes' === $this->enabled && $this->client()->is_configured();
	}

	/** Champ « numéro à débiter », pré-rempli avec le téléphone de facturation. */
	public function payment_fields() {
		if ( $this->description ) {
			echo wpautop( wp_kses_post( $this->description ) );
		}
		$default = '';
		if ( is_checkout() && WC()->customer ) {
			$default = WC()->customer->get_billing_phone();
		}
		echo '<p class="form-row form-row-wide">
			<label for="mmgate_msisdn">' . esc_html__( 'Numéro Mobile Money à débiter', 'mmgate-woocommerce' ) . ' <abbr class="required">*</abbr></label>
			<input type="tel" id="mmgate_msisdn" name="mmgate_msisdn" class="input-text" autocomplete="tel"
			       value="' . esc_attr( $default ) . '" placeholder="6XX XX XX XX">
			<span style="font-size:12px;opacity:.75;display:block;margin-top:4px;">'
			. esc_html__( 'MTN ou Orange. Vous validerez le débit sur ce téléphone.', 'mmgate-woocommerce' )
			. '</span></p>';
	}

	public function validate_fields() {
		$raw = isset( $_POST['mmgate_msisdn'] ) ? wp_unslash( $_POST['mmgate_msisdn'] ) : '';
		// Le champ peut ne pas avoir ete rempli : on retombe alors sur le
		// telephone de facturation, qui est obligatoire au checkout.
		if ( MMGate_Client::normalize_msisdn( $raw ) === '' && isset( $_POST['billing_phone'] ) ) {
			$raw = wp_unslash( $_POST['billing_phone'] );
		}
		$err = MMGate_Client::msisdn_error( $raw );
		if ( $err !== '' ) {
			wc_add_notice( esc_html( $err ), 'error' );
			return false;
		}
		return true;
	}

	/**
	 * Initie le debit puis renvoie le client vers l'ecran d'attente.
	 * On ne passe JAMAIS la commande en payee ici : seul ETATO 400 le fera.
	 */
	public function process_payment( $order_id ) {
		$order  = wc_get_order( $order_id );
		$client = $this->client();

		$msisdn = MMGate_Client::normalize_msisdn( isset( $_POST['mmgate_msisdn'] ) ? wp_unslash( $_POST['mmgate_msisdn'] ) : '' );
		if ( $msisdn === '' ) {
			$msisdn = MMGate_Client::normalize_msisdn( $order->get_billing_phone() );
		}

		// Idempotence : une commande deja initiee ne relance pas de debit.
		if ( $order->get_meta( '_mmgate_idoper' ) ) {
			return [ 'result' => 'success', 'redirect' => $this->waiting_url( $order ) ];
		}

		$montant = (int) round( (float) $order->get_total() );
		$confirm = 'yes' === $this->get_option( 'confirm_duplicate', 'yes' );
		$method  = 'DEPOTP' === $this->get_option( 'endpoint', 'PAIEMENTP' ) ? 'depot' : 'paiement';

		$res = $client->$method( $msisdn, $montant, false );

		// ETAT 600 : aucune operation creee. Sur une boutique, deux clients qui
		// paient le meme montant a 10 min d'ecart est un cas normal -> on rejoue
		// avec l'en-tete de confirmation. L'idempotence reste assuree par le
		// garde _mmgate_idoper ci-dessus, pas par la fenetre anti-doublon MMGate.
		if ( ! is_wp_error( $res ) && isset( $res['ETAT'] ) && MMGate_Client::ETAT_DOUBLON === (int) $res['ETAT'] ) {
			if ( $confirm ) {
				$order->add_order_note( __( 'MMGate : opération similaire récente (ETAT 600) — rejeu confirmé.', 'mmgate-woocommerce' ) );
				$res = $client->$method( $msisdn, $montant, true );
			} else {
				wc_add_notice( __( 'Un paiement identique vient d\'être enregistré. Patientez quelques minutes avant de réessayer.', 'mmgate-woocommerce' ), 'error' );
				return [ 'result' => 'failure' ];
			}
		}

		if ( is_wp_error( $res ) ) {
			$order->add_order_note( 'MMGate : ' . $res->get_error_message() );
			wc_add_notice( __( 'Le service Mobile Money est momentanément injoignable. Réessayez dans un instant.', 'mmgate-woocommerce' ), 'error' );
			return [ 'result' => 'failure' ];
		}

		$etat   = isset( $res['ETAT'] ) ? (int) $res['ETAT'] : 0;
		$idoper = isset( $res['IDOPER'] ) ? (string) $res['IDOPER'] : '';
		$ok     = in_array( $etat, [ MMGate_Client::ETAT_PAIEMENT_OK, MMGate_Client::ETAT_DEPOT_OK ], true );

		if ( ! $ok || $idoper === '' ) {
			$msg = self::etat_message( $etat );
			$order->add_order_note( sprintf( 'MMGate : échec d\'initiation (ETAT %d — %s).', $etat, $msg ) );
			if ( 'yes' === $this->get_option( 'debug' ) ) {
				$client->log( 'Initiation refusée, ETAT ' . $etat . ' pour la commande ' . $order->get_id() );
			}
			wc_add_notice( $msg, 'error' );
			return [ 'result' => 'failure' ];
		}

		// Persister l'IDOPER AVANT de lancer le suivi : c'est la cle de
		// rapprochement support, et sans elle une transaction reussie serait
		// orpheline si la suite echouait.
		$order->update_meta_data( '_mmgate_idoper', $idoper );
		$order->update_meta_data( '_mmgate_msisdn', $msisdn );
		$order->update_meta_data( '_mmgate_started', time() );
		$order->save();

		$order->update_status( 'pending', sprintf(
			/* translators: %s: identifiant d'operation MMGate */
			__( 'MMGate : débit initié (IDOPER %s). En attente de validation sur le téléphone du client.', 'mmgate-woocommerce' ),
			$idoper
		) );

		MMGate_Poller::schedule( $order->get_id() );

		return [ 'result' => 'success', 'redirect' => $this->waiting_url( $order ) ];
	}

	private function waiting_url( $order ) {
		return add_query_arg( 'mmgate_wait', '1', $this->get_return_url( $order ) );
	}

	/**
	 * Diagnostic ADMIN pour un code ETAT — a ne pas confondre avec
	 * etat_message(), qui s'adresse au CLIENT. Ici on nomme le champ fautif :
	 * un administrateur n'a que faire de « contactez la boutique », il EST la
	 * boutique. Chaque code designe une valeur precise, ce qui evite de tout
	 * ressaisir a l'aveugle.
	 *
	 * @return array{quoi:string,action:string}
	 */
	public static function etat_diagnostic( $etat ) {
		$map = [
			202 => [
				'quoi'   => __( 'mot de passe erroné', 'mmgate-woocommerce' ),
				'action' => __( 'Le code partenaire et l\'utilisateur ont été acceptés : seul <code>PWD</code> est en cause. Vérifiez le mot de passe de l\'<strong>utilisateur API</strong> — ce n\'est pas forcément celui de votre connexion au tableau de bord MMGate. <strong>Si votre mot de passe contient des caractères spéciaux, suspectez-les avant de suspecter la valeur</strong> (voir l\'avertissement ci-dessus).', 'mmgate-woocommerce' ),
			],
			203 => [
				'quoi'   => __( 'utilisateur inactif', 'mmgate-woocommerce' ),
				'action' => __( 'Vos identifiants sont <strong>corrects</strong>, mais MMGate a désactivé cet utilisateur API. Rien à corriger de votre côté : demandez sa réactivation au support MMGate.', 'mmgate-woocommerce' ),
			],
			204 => [
				'quoi'   => __( 'code partenaire erroné', 'mmgate-woocommerce' ),
				'action' => __( 'MMGate ne reconnaît pas <code>CDPRT</code>. Recopiez le code partenaire exact depuis votre espace partenaire — il est sensible à la casse.', 'mmgate-woocommerce' ),
			],
			205 => [
				'quoi'   => __( 'utilisateur non trouvé', 'mmgate-woocommerce' ),
				'action' => __( 'Votre <strong>code partenaire est reconnu</strong> (sinon vous auriez un ETAT 204) et le mot de passe n\'a même pas été examiné : <strong>seul <code>USR</code> est en cause</strong>. MMGate attend l\'<strong>utilisateur API</strong> (appelé <em>utipart</em> dans leur documentation), qui est distinct de votre identifiant de connexion au tableau de bord. Vous le trouverez dans votre espace partenaire, généralement à côté du code partenaire et du token.', 'mmgate-woocommerce' ),
			],
			500 => [
				'quoi'   => __( 'partenaire désactivé', 'mmgate-woocommerce' ),
				'action' => __( 'Vos identifiants sont corrects mais votre compte marchand est désactivé côté MMGate — souvent une validation KYC incomplète. Contactez-les.', 'mmgate-woocommerce' ),
			],
			501 => [
				'quoi'   => __( 'partenaire inexistant', 'mmgate-woocommerce' ),
				'action' => __( 'Aucun compte partenaire ne correspond à ce <code>CDPRT</code>.', 'mmgate-woocommerce' ),
			],
		];
		return isset( $map[ $etat ] ) ? $map[ $etat ] : [
			'quoi'   => __( 'refus non documenté', 'mmgate-woocommerce' ),
			'action' => __( 'Ce code ne figure pas dans la documentation MMGate pour cet appel. Transmettez-le au support MMGate avec votre code partenaire.', 'mmgate-woocommerce' ),
		];
	}

	/** Message CLIENT pour un code ETAT d'initiation (jamais affiché à l'admin). */
	public static function etat_message( $etat ) {
		$map = [
			201 => __( 'L\'envoi a échoué. Vérifiez le numéro et réessayez.', 'mmgate-woocommerce' ),
			202 => __( 'Configuration du paiement incorrecte (authentification). Contactez la boutique.', 'mmgate-woocommerce' ),
			203 => __( 'Compte de paiement inactif. Contactez la boutique.', 'mmgate-woocommerce' ),
			204 => __( 'Configuration du paiement incorrecte (code partenaire). Contactez la boutique.', 'mmgate-woocommerce' ),
			205 => __( 'Compte de paiement introuvable. Contactez la boutique.', 'mmgate-woocommerce' ),
			301 => __( 'L\'opération a échoué. Réessayez.', 'mmgate-woocommerce' ),
			305 => __( 'Plafond de l\'opération dépassé.', 'mmgate-woocommerce' ),
			307 => __( 'Le service de paiement est temporairement indisponible. Contactez la boutique.', 'mmgate-woocommerce' ),
			500 => __( 'Compte marchand désactivé. Contactez la boutique.', 'mmgate-woocommerce' ),
			501 => __( 'Compte marchand inexistant. Contactez la boutique.', 'mmgate-woocommerce' ),
			900 => __( 'Montant invalide.', 'mmgate-woocommerce' ),
		];
		return isset( $map[ $etat ] )
			? $map[ $etat ]
			: __( 'Le paiement n\'a pas pu être initié. Réessayez ou contactez la boutique.', 'mmgate-woocommerce' );
	}
}
