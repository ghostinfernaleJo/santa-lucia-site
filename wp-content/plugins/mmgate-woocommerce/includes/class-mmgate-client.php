<?php
/**
 * Client HTTP de l'API MMGate.
 *
 * Toute l'API MMGate est en GET, et les identifiants (CDPRT/USR/PWD) voyagent
 * dans le CHEMIN de l'URL. C'est leur conception, on fait avec — mais ca impose
 * une regle absolue : ne JAMAIS journaliser une URL complete. Toutes les traces
 * de cette classe passent par self::redact().
 *
 * @package MMGate_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

class MMGate_Client {

	const BASE = 'https://www.mmgate.org/mmgweb';

	/** Codes ETATO renvoyes par le suivi de transaction. */
	const ETATO_OK        = 400; // terminee avec succes
	const ETATO_ENCOURS   = 401; // en cours
	const ETATO_ATTENTE   = 402; // en attente de validation du client
	const ETATO_ANNULEE   = 403; // annulee / echouee
	const ETATO_INTROUVABLE = 404;

	/** Codes ETAT d'initiation. */
	const ETAT_PAIEMENT_OK = 200; // PAIEMENTP insere
	const ETAT_DEPOT_OK    = 300; // DEPOTP insere
	const ETAT_DOUBLON     = 600; // operation similaire recente

	/** @var array{cdprt:string,usr:string,pwd:string,token:string} */
	private $creds;

	public function __construct( array $creds ) {
		$this->creds = wp_parse_args( $creds, [ 'cdprt' => '', 'usr' => '', 'pwd' => '', 'token' => '' ] );
	}

	/** Les 4 identifiants sont-ils presents ? (le token seul ne suffit pas) */
	public function is_configured() {
		foreach ( [ 'cdprt', 'usr', 'pwd', 'token' ] as $k ) {
			if ( $this->creds[ $k ] === '' ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Normalise un numero au format attendu par MMGate.
	 * Les exemples de la doc sont a 9 chiffres sans indicatif (688740365) alors
	 * que le checkout collecte souvent « +237 6XX… » : on retire l'indicatif.
	 *
	 * @return string 9 chiffres, ou '' si le numero est inexploitable.
	 */
	public static function normalize_msisdn( $raw ) {
		$d = preg_replace( '/\D+/', '', (string) $raw );
		if ( $d === '' ) {
			return '';
		}
		// Indicatif Cameroun, avec ou sans 00 devant.
		if ( strpos( $d, '00237' ) === 0 ) {
			$d = substr( $d, 5 );
		} elseif ( strpos( $d, '237' ) === 0 && strlen( $d ) > 9 ) {
			$d = substr( $d, 3 );
		}
		// Le Cameroun n'a pas de « 0 » de preface, mais beaucoup l'ajoutent par
		// habitude (reflexe francais). On l'accepte plutot que de renvoyer un
		// refus incomprehensible sur un numero par ailleurs correct.
		if ( strlen( $d ) === 10 && '0' === $d[0] ) {
			$d = substr( $d, 1 );
		}
		// Mobile Money = mobiles uniquement, prefixe 6 au Cameroun. Un fixe a
		// 9 chiffres (2XX…) passerait le test de longueur mais echouerait cote
		// operateur avec un message obscur : mieux vaut refuser ici, ou le
		// message d'erreur explique quoi corriger.
		return ( strlen( $d ) === 9 && '6' === $d[0] ) ? $d : '';
	}

	/**
	 * Explique POURQUOI un numero est refuse, pour que l'utilisateur puisse
	 * corriger. Un simple « numero invalide » l'oblige a deviner : il ne voit
	 * pas ce que le serveur a lu de sa saisie.
	 *
	 * @return string Message pret a afficher, ou '' si le numero est valide.
	 */
	public static function msisdn_error( $raw ) {
		if ( self::normalize_msisdn( $raw ) !== '' ) {
			return '';
		}
		$d = preg_replace( '/\D+/', '', (string) $raw );
		if ( $d === '' ) {
			return __( 'Indiquez le numéro Mobile Money à débiter (9 chiffres, par exemple 6XX XX XX XX).', 'mmgate-woocommerce' );
		}
		return sprintf(
			/* translators: 1: nombre de chiffres lus, 2: chiffres lus */
			__( 'Numéro Mobile Money invalide : nous avons lu %1$d chiffres (« %2$s »). Un numéro camerounais en compte 9 et commence par 6 — l\'indicatif +237 est facultatif.', 'mmgate-woocommerce' ),
			strlen( $d ),
			$d
		);
	}

	/**
	 * Encaissement : debite le numero du client.
	 *
	 * @param string $msisdn         Numero a debiter (EXPO).
	 * @param int    $montant        Entier FCFA.
	 * @param bool   $confirm_doublon Rejouer malgre un ETAT 600.
	 * @return array|WP_Error Corps JSON decode.
	 */
	public function paiement( $msisdn, $montant, $confirm_doublon = false ) {
		return $this->call_token_endpoint( 'PAIEMENTP', $msisdn, $montant, $confirm_doublon );
	}

	/**
	 * Decaissement : credite un numero. Consomme le solde partenaire (ETAT 307
	 * si insuffisant). Non utilise pour encaisser une commande.
	 */
	public function depot( $msisdn, $montant, $confirm_doublon = false ) {
		return $this->call_token_endpoint( 'DEPOTP', $msisdn, $montant, $confirm_doublon );
	}

	/** PAIEMENTP et DEPOTP : token en en-tete, jamais dans l'URL. */
	private function call_token_endpoint( $resource, $msisdn, $montant, $confirm_doublon ) {
		$msisdn  = self::normalize_msisdn( $msisdn );
		$montant = (int) $montant;

		if ( $msisdn === '' ) {
			return new WP_Error( 'mmgate_msisdn', __( 'Numéro de téléphone invalide.', 'mmgate-woocommerce' ) );
		}
		if ( $montant <= 0 ) {
			return new WP_Error( 'mmgate_montant', __( 'Montant invalide.', 'mmgate-woocommerce' ) );
		}
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'mmgate_config', __( 'Identifiants MMGate incomplets.', 'mmgate-woocommerce' ) );
		}

		$url = sprintf(
			'%s/%s/%s/%d/%s/%s/%s',
			self::BASE,
			$resource,
			rawurlencode( $msisdn ),
			$montant,
			rawurlencode( $this->creds['cdprt'] ),
			rawurlencode( $this->creds['usr'] ),
			rawurlencode( $this->creds['pwd'] )
		);

		$headers = [ 'X-Partner-Token' => $this->creds['token'] ];
		if ( $confirm_doublon ) {
			$headers['X-MMGate-Confirm-Duplicate'] = '1';
		}

		return $this->request( $url, $headers );
	}

	/**
	 * Suivi d'une transaction. Pas de token : auth par URL.
	 *
	 * @return array|WP_Error {IDOPER, ETATO}
	 */
	public function etato( $idoper ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'mmgate_config', __( 'Identifiants MMGate incomplets.', 'mmgate-woocommerce' ) );
		}
		$url = sprintf(
			'%s/ETATO/%s/%s/%s/%s',
			self::BASE,
			rawurlencode( $idoper ),
			rawurlencode( $this->creds['cdprt'] ),
			rawurlencode( $this->creds['usr'] ),
			rawurlencode( $this->creds['pwd'] )
		);
		return $this->request( $url );
	}

	/** Solde partenaire — pratique pour valider les identifiants sans bouger d'argent. */
	public function solde() {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'mmgate_config', __( 'Identifiants MMGate incomplets.', 'mmgate-woocommerce' ) );
		}
		$url = sprintf(
			'%s/SOLDE/%s/%s/%s',
			self::BASE,
			rawurlencode( $this->creds['cdprt'] ),
			rawurlencode( $this->creds['usr'] ),
			rawurlencode( $this->creds['pwd'] )
		);
		return $this->request( $url );
	}

	/**
	 * SMS via la file MMGate. 5 FCFA debites du solde partenaire (ETAT 309 si
	 * insuffisant). Le corps doit etre en ASCII : certains telephones forcent un
	 * encodage 7 bits et massacrent les accents.
	 */
	public function sms( $msisdn, $message ) {
		$msisdn = self::normalize_msisdn( $msisdn );
		if ( $msisdn === '' ) {
			return new WP_Error( 'mmgate_msisdn', __( 'Numéro SMS invalide.', 'mmgate-woocommerce' ) );
		}
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'mmgate_config', __( 'Identifiants MMGate incomplets.', 'mmgate-woocommerce' ) );
		}
		$url = sprintf(
			'%s/SMS/%s/%s/%s/%s/%s',
			self::BASE,
			rawurlencode( $msisdn ),
			self::encode_sms( $message ),
			rawurlencode( $this->creds['cdprt'] ),
			rawurlencode( $this->creds['usr'] ),
			rawurlencode( $this->creds['pwd'] )
		);
		return $this->request( $url );
	}

	/** Corps SMS : « b64. » + Base64 URL-safe, sans « = » final. */
	public static function encode_sms( $message ) {
		$ascii = self::to_ascii( $message );
		return 'b64.' . rtrim( strtr( base64_encode( $ascii ), '+/', '-_' ), '=' );
	}

	/** Retire les accents et tout ce qui sort de l'ASCII imprimable. */
	public static function to_ascii( $s ) {
		$s = remove_accents( (string) $s );
		return preg_replace( '/[^\x20-\x7E\r\n]/', '', $s );
	}

	/** Requete GET + decodage JSON, avec normalisation des codes en entiers. */
	private function request( $url, array $headers = [] ) {
		$res = wp_remote_get( $url, [
			'timeout'   => 30,
			'headers'   => $headers,
			'sslverify' => true,
		] );

		if ( is_wp_error( $res ) ) {
			$this->log( 'Échec réseau sur ' . self::redact( $url ) . ' : ' . $res->get_error_message() );
			return $res;
		}

		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = wp_remote_retrieve_body( $res );

		// Le serveur peut emettre un BOM UTF-8 : json_decode le refuse.
		$body = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $body );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			$this->log( 'Réponse illisible (HTTP ' . $code . ') sur ' . self::redact( $url ) );
			return new WP_Error( 'mmgate_json', __( 'Réponse illisible du serveur MMGate.', 'mmgate-woocommerce' ) );
		}

		// « Normaliser ETAT en nombre, certains clients peuvent recevoir une chaine. »
		foreach ( [ 'ETAT', 'ETATO' ] as $k ) {
			if ( isset( $data[ $k ] ) ) {
				$data[ $k ] = (int) $data[ $k ];
			}
		}

		return $data;
	}

	/**
	 * Masque les identifiants d'une URL avant journalisation.
	 * La doc MMGate est explicite : « Ne jamais logger les URLs completes
	 * contenant USR/PWD ». On ne garde que la ressource.
	 */
	public static function redact( $url ) {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$seg  = array_values( array_filter( explode( '/', $path ) ) );
		$res  = '';
		foreach ( $seg as $s ) {
			if ( in_array( $s, [ 'PAIEMENTP', 'DEPOTP', 'ETATO', 'SOLDE', 'SMS' ], true ) ) {
				$res = $s;
				break;
			}
		}
		return $res !== '' ? self::BASE . '/' . $res . '/[masqué]' : '[URL masquée]';
	}

	/** Journal WooCommerce, source « mmgate ». Jamais d'identifiant dedans. */
	public function log( $message ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->info( $message, [ 'source' => 'mmgate' ] );
		}
	}
}
