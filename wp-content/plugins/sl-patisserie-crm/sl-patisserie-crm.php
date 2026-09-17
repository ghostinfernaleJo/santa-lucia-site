<?php
/**
 * Plugin Name: Santa Lucia - CRM Patisserie
 * Description: Enrichit les demandes de patisserie avec un suivi CRM, les informations completes et un raccourci WhatsApp.
 * Version: 1.3.0
 * Author: Santa Lucia
 * Text Domain: sl-patisserie-crm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SL_Patisserie_CRM {
	private const POST_TYPE = 'sl_demande_gateau';
	private const NONCE_ACTION = 'sl_patisserie_crm_save';
	private const NONCE_NAME = 'sl_patisserie_crm_nonce';

	private const META_STATUS = '_sl_crm_status';
	private const META_CATEGORY = '_sl_crm_category';
	private const META_AMOUNT = '_sl_crm_amount';
	private const META_AGENCY = '_sl_crm_agency';
	private const META_REMINDER = '_sl_crm_reminder';
	private const META_NOTES = '_sl_crm_notes';
	private const META_HISTORY = '_sl_crm_history';

	private static array $statuses = array(
		'nouvelle'        => 'Nouvelle',
		'a_rappeler'      => 'A rappeler',
		'contactee'       => 'Contactee',
		'confirmee'       => 'Confirmee',
		'achetee_agence'  => 'Achetee en agence',
		'annulee'         => 'Annulee',
	);

	private static array $categories = array(
		'nouveau'     => 'Nouveau client',
		'particulier' => 'Particulier',
		'entreprise'  => 'Entreprise',
		'fidele'      => 'Client fidele',
		'vip'         => 'VIP',
	);

	private static function can_manage_crm(): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'edit_slg_requests' );
	}

	public static function init(): void {
		add_filter( 'register_post_type_args', array( __CLASS__, 'add_menu_icon' ), 10, 2 );
		add_action( 'admin_menu', array( __CLASS__, 'adjust_menu' ), 99 );
		add_action( 'admin_menu', array( __CLASS__, 'add_crm_page' ), 100 );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_crm_fields' ), 10, 2 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
		add_filter( 'manage_edit-' . self::POST_TYPE . '_sortable_columns', array( __CLASS__, 'sortable_columns' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'sort_columns' ) );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'filters' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'apply_filters' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
		add_action( 'admin_post_sl_patisserie_whatsapp', array( __CLASS__, 'open_whatsapp' ) );
		add_action( 'admin_post_sl_patisserie_crm_save', array( __CLASS__, 'save_crm_page' ) );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
	}

	public static function add_menu_icon( array $args, string $post_type ): array {
		if ( self::POST_TYPE === $post_type ) {
			$args['menu_icon'] = 'dashicons-buddicons-pm';
			$args['show_in_menu'] = true;
		}

		return $args;
	}

	public static function adjust_menu(): void {
		global $menu;

		foreach ( $menu as &$item ) {
			if ( isset( $item[2] ) && 'edit.php?post_type=' . self::POST_TYPE === $item[2] ) {
				$item[6] = 'dashicons-buddicons-pm';
				break;
			}
		}
	}

	public static function add_crm_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . self::POST_TYPE,
			'Suivi CRM pâtisserie',
			'Suivi CRM',
			'edit_slg_requests',
			'sl-patisserie-crm',
			array( __CLASS__, 'render_crm_page' )
		);
	}

	public static function render_crm_page(): void {
		if ( ! self::can_manage_crm() ) {
			wp_die( 'Vous ne pouvez pas accéder à cette page.' );
		}

		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		echo '<div class="wrap sl-crm-page">';

		if ( $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post || self::POST_TYPE !== $post->post_type ) {
				wp_die( 'Cette demande est introuvable.' );
			}

			echo '<p><a href="' . esc_url( admin_url( 'edit.php?post_type=' . self::POST_TYPE . '&page=sl-patisserie-crm' ) ) . '">&larr; Toutes les demandes</a></p>';
			echo '<h1>Suivi de ' . esc_html( self::get_client_name( $post_id ) ?: get_the_title( $post_id ) ) . '</h1>';
			echo '<div class="sl-crm-page-grid"><div class="sl-crm-page-main">';
			echo '<h2>Informations de la demande</h2>';
			self::render_client_box( $post );
			echo '<h2>Historique</h2>';
			self::render_history_box( $post );
			echo '</div><aside class="sl-crm-page-side"><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo '<input type="hidden" name="action" value="sl_patisserie_crm_save">';
			echo '<input type="hidden" name="post_id" value="' . esc_attr( $post_id ) . '">';
			self::render_crm_box( $post );
			echo '<p><button type="submit" class="button button-primary button-large">Enregistrer le suivi</button></p>';
			echo '</form></aside></div>';
		} else {
			echo '<h1>Suivi CRM pâtisserie</h1>';
			echo '<p>Ouvrez une demande pour renseigner son montant, programmer un rappel et suivre les échanges avec le client.</p>';
			$requests = get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => 'any', 'numberposts' => 100, 'orderby' => 'date', 'order' => 'DESC' ) );
			echo '<div class="sl-crm-table-wrap"><table class="widefat striped"><thead><tr><th>Client</th><th>Téléphone</th><th>Message</th><th>Statut</th><th>Rappel</th><th>Montant</th><th>Action</th></tr></thead><tbody>';
			foreach ( $requests as $request ) {
				$status   = get_post_meta( $request->ID, self::META_STATUS, true ) ?: 'nouvelle';
				$reminder = get_post_meta( $request->ID, self::META_REMINDER, true );
				$amount   = get_post_meta( $request->ID, self::META_AMOUNT, true );
				$url      = add_query_arg( array( 'post_type' => self::POST_TYPE, 'page' => 'sl-patisserie-crm', 'post_id' => $request->ID ), admin_url( 'edit.php' ) );
				echo '<tr>';
				echo '<td><strong>' . esc_html( self::get_client_name( $request->ID ) ?: $request->post_title ) . '</strong></td>';
				echo '<td>' . esc_html( self::get_client_phone( $request->ID ) ?: '—' ) . '</td>';
				echo '<td>' . esc_html( wp_trim_words( self::get_client_message( $request->ID ), 14, '…' ) ?: '—' ) . '</td>';
				echo '<td><span class="sl-crm-badge sl-crm-status-' . esc_attr( $status ) . '">' . esc_html( self::$statuses[ $status ] ?? $status ) . '</span></td>';
				echo '<td>' . ( $reminder ? esc_html( wp_date( 'd/m/Y H:i', strtotime( $reminder ) ) ) : '—' ) . '</td>';
				echo '<td>' . ( '' !== $amount ? esc_html( number_format_i18n( (int) $amount ) . ' FCFA' ) : '—' ) . '</td>';
				echo '<td><a class="button button-primary" href="' . esc_url( $url ) . '">Gérer</a></td>';
				echo '</tr>';
			}
			if ( ! $requests ) {
				echo '<tr><td colspan="7">Aucune demande enregistrée.</td></tr>';
			}
			echo '</tbody></table></div>';
		}

		echo '</div>';
	}

	public static function save_crm_page(): void {
		if ( ! self::can_manage_crm() ) {
			wp_die( 'Vous ne pouvez pas modifier cette demande.' );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || self::POST_TYPE !== get_post_type( $post_id ) ) {
			wp_die( 'Cette demande est introuvable.' );
		}

		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			wp_die( 'La vérification de sécurité a échoué.' );
		}

		self::store_crm_fields( $post_id );
		$url = add_query_arg(
			array( 'post_type' => self::POST_TYPE, 'page' => 'sl-patisserie-crm', 'post_id' => $post_id, 'updated' => 1 ),
			admin_url( 'edit.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	public static function add_meta_boxes(): void {
		add_meta_box(
			'sl-patisserie-client',
			'Informations transmises par le client',
			array( __CLASS__, 'render_client_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'sl-patisserie-crm',
			'Suivi CRM',
			array( __CLASS__, 'render_crm_box' ),
			self::POST_TYPE,
			'side',
			'high'
		);

		add_meta_box(
			'sl-patisserie-history',
			'Historique du suivi',
			array( __CLASS__, 'render_history_box' ),
			self::POST_TYPE,
			'normal',
			'default'
		);
	}

	public static function render_client_box( WP_Post $post ): void {
		$details = self::get_submission_details( $post );

		if ( empty( $details ) ) {
			echo '<p>Aucune information de formulaire n\'est encore rattachee a cette demande.</p>';
			return;
		}

		echo '<div class="sl-crm-details">';
		foreach ( $details as $label => $value ) {
			echo '<div class="sl-crm-detail">';
			echo '<strong>' . esc_html( $label ) . '</strong>';
			echo '<div>' . self::format_value( $value ) . '</div>';
			echo '</div>';
		}
		echo '</div>';
	}

	public static function render_crm_box( WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$status   = get_post_meta( $post->ID, self::META_STATUS, true ) ?: 'nouvelle';
		$category = get_post_meta( $post->ID, self::META_CATEGORY, true ) ?: 'nouveau';
		$amount   = get_post_meta( $post->ID, self::META_AMOUNT, true );
		$agency   = get_post_meta( $post->ID, self::META_AGENCY, true );
		$reminder = get_post_meta( $post->ID, self::META_REMINDER, true );
		$notes    = get_post_meta( $post->ID, self::META_NOTES, true );
		$phone    = self::get_client_phone( $post->ID );
		$wa_url   = self::get_whatsapp_admin_url( $post->ID );
		?>
		<div class="sl-crm-field">
			<label for="sl_crm_status">Statut de la demande</label>
			<select id="sl_crm_status" name="sl_crm_status">
				<?php foreach ( self::$statuses as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="sl-crm-field">
			<label for="sl_crm_category">Categorie du client</label>
			<select id="sl_crm_category" name="sl_crm_category">
				<?php foreach ( self::$categories as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $category, $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="sl-crm-field">
			<label for="sl_crm_amount">Montant paye (FCFA)</label>
			<input type="number" id="sl_crm_amount" name="sl_crm_amount" min="0" step="1" value="<?php echo esc_attr( $amount ); ?>" placeholder="Ex. 25000">
		</div>

		<div class="sl-crm-field">
			<label for="sl_crm_agency">Agence d'achat</label>
			<input type="text" id="sl_crm_agency" name="sl_crm_agency" value="<?php echo esc_attr( $agency ); ?>" placeholder="Nom de l'agence">
		</div>

		<div class="sl-crm-field">
			<label for="sl_crm_reminder">Date et heure de rappel</label>
			<input type="datetime-local" id="sl_crm_reminder" name="sl_crm_reminder" value="<?php echo esc_attr( $reminder ); ?>">
		</div>

		<div class="sl-crm-field">
			<label for="sl_crm_notes">Notes internes</label>
			<textarea id="sl_crm_notes" name="sl_crm_notes" rows="5" placeholder="Compte rendu de l'appel, preferences, prochaine action..."><?php echo esc_textarea( $notes ); ?></textarea>
		</div>

		<?php if ( $phone && $wa_url ) : ?>
			<a class="button button-primary sl-crm-whatsapp" href="<?php echo esc_url( $wa_url ); ?>" target="_blank" rel="noopener noreferrer">
				<span class="dashicons dashicons-whatsapp" aria-hidden="true"></span>
				Confirmer sur WhatsApp
			</a>
			<p class="description">Conversation avec <?php echo esc_html( $phone ); ?> et message pre-rempli.</p>
		<?php else : ?>
			<p class="sl-crm-warning">Aucun numero de telephone exploitable n'a ete trouve.</p>
		<?php endif; ?>
		<?php
	}

	public static function render_history_box( WP_Post $post ): void {
		$history = get_post_meta( $post->ID, self::META_HISTORY, true );
		$history = is_array( $history ) ? array_reverse( $history ) : array();

		if ( empty( $history ) ) {
			echo '<p>Aucune action de suivi enregistree pour le moment.</p>';
			return;
		}

		echo '<ol class="sl-crm-history">';
		foreach ( $history as $event ) {
			$date = isset( $event['date'] ) ? $event['date'] : '';
			$user = isset( $event['user'] ) ? $event['user'] : 'Utilisateur';
			$text = isset( $event['text'] ) ? $event['text'] : '';
			echo '<li><strong>' . esc_html( $text ) . '</strong><span>' . esc_html( $date . ' - ' . $user ) . '</span></li>';
		}
		echo '</ol>';
	}

	public static function save_crm_fields( int $post_id, WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		self::store_crm_fields( $post_id );
	}

	private static function store_crm_fields( int $post_id ): void {
		$old_status = get_post_meta( $post_id, self::META_STATUS, true ) ?: 'nouvelle';
		$old_amount = get_post_meta( $post_id, self::META_AMOUNT, true );
		$old_notes  = get_post_meta( $post_id, self::META_NOTES, true );

		$status = isset( $_POST['sl_crm_status'] ) ? sanitize_key( wp_unslash( $_POST['sl_crm_status'] ) ) : 'nouvelle';
		$status = array_key_exists( $status, self::$statuses ) ? $status : 'nouvelle';

		$category = isset( $_POST['sl_crm_category'] ) ? sanitize_key( wp_unslash( $_POST['sl_crm_category'] ) ) : 'nouveau';
		$category = array_key_exists( $category, self::$categories ) ? $category : 'nouveau';

		$amount = isset( $_POST['sl_crm_amount'] ) ? preg_replace( '/[^0-9]/', '', wp_unslash( $_POST['sl_crm_amount'] ) ) : '';
		$agency = isset( $_POST['sl_crm_agency'] ) ? sanitize_text_field( wp_unslash( $_POST['sl_crm_agency'] ) ) : '';
		$notes  = isset( $_POST['sl_crm_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['sl_crm_notes'] ) ) : '';

		$reminder = isset( $_POST['sl_crm_reminder'] ) ? sanitize_text_field( wp_unslash( $_POST['sl_crm_reminder'] ) ) : '';
		if ( $reminder && ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $reminder ) ) {
			$reminder = '';
		}

		update_post_meta( $post_id, self::META_STATUS, $status );
		update_post_meta( $post_id, self::META_CATEGORY, $category );
		update_post_meta( $post_id, self::META_AMOUNT, $amount );
		update_post_meta( $post_id, self::META_AGENCY, $agency );
		update_post_meta( $post_id, self::META_REMINDER, $reminder );
		update_post_meta( $post_id, self::META_NOTES, $notes );

		if ( $old_status !== $status ) {
			self::add_history( $post_id, 'Statut modifie : ' . self::$statuses[ $status ] );
		}

		if ( (string) $old_amount !== (string) $amount && '' !== $amount ) {
			self::add_history( $post_id, 'Montant enregistre : ' . number_format_i18n( (int) $amount ) . ' FCFA' );
		}

		if ( $notes && $notes !== $old_notes ) {
			self::add_history( $post_id, 'Notes internes mises a jour' );
		}
	}

	public static function columns( array $columns ): array {
		return array(
			'cb'           => $columns['cb'] ?? '<input type="checkbox">',
			'title'        => 'Client',
			'sl_message'   => 'Message',
			'slg_agence'   => 'Agence',
			'sl_status'    => 'Statut',
			'sl_reminder'  => 'Rappel',
			'sl_amount'    => 'Montant',
			'sl_whatsapp'  => 'WhatsApp',
			'sl_manage'    => 'Gérer',
			'date'         => 'Reçue le',
		);
	}

	public static function column_content( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'sl_client':
				echo esc_html( self::get_client_name( $post_id ) ?: 'Non renseigne' );
				break;
			case 'sl_phone':
				echo esc_html( self::get_client_phone( $post_id ) ?: 'Non renseigne' );
				break;
			case 'sl_message':
				$message = self::get_client_message( $post_id );
				echo $message ? '<span title="' . esc_attr( $message ) . '">' . esc_html( wp_trim_words( $message, 18, '...' ) ) . '</span>' : '&mdash;';
				break;
			case 'sl_status':
				$status = get_post_meta( $post_id, self::META_STATUS, true ) ?: 'nouvelle';
				echo '<span class="sl-crm-badge sl-crm-status-' . esc_attr( $status ) . '">' . esc_html( self::$statuses[ $status ] ?? $status ) . '</span>';
				break;
			case 'sl_reminder':
				$reminder = get_post_meta( $post_id, self::META_REMINDER, true );
				if ( ! $reminder ) {
					echo '&mdash;';
					break;
				}
				$is_late = strtotime( $reminder ) < current_time( 'timestamp' );
				echo '<span class="' . ( $is_late ? 'sl-crm-overdue' : '' ) . '">' . esc_html( wp_date( 'd/m/Y H:i', strtotime( $reminder ) ) ) . '</span>';
				break;
			case 'sl_amount':
				$amount = get_post_meta( $post_id, self::META_AMOUNT, true );
				echo '' !== $amount ? esc_html( number_format_i18n( (int) $amount ) . ' FCFA' ) : '&mdash;';
				break;
			case 'sl_whatsapp':
				$url = self::get_whatsapp_admin_url( $post_id );
				if ( $url ) {
					echo '<a class="button button-small sl-crm-wa-small" href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer" aria-label="Contacter le client sur WhatsApp"><span class="dashicons dashicons-whatsapp" aria-hidden="true"></span></a>';
				} else {
					echo '&mdash;';
				}
				break;
			case 'sl_manage':
				$url = add_query_arg( array( 'post_type' => self::POST_TYPE, 'page' => 'sl-patisserie-crm', 'post_id' => $post_id ), admin_url( 'edit.php' ) );
				echo '<a class="button button-primary" href="' . esc_url( $url ) . '">Gérer</a>';
				break;
		}
	}

	public static function sortable_columns( array $columns ): array {
		$columns['sl_status']   = 'sl_status';
		$columns['sl_reminder'] = 'sl_reminder';
		$columns['sl_amount']   = 'sl_amount';
		return $columns;
	}

	public static function sort_columns( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || self::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		$orderby = $query->get( 'orderby' );
		if ( 'sl_status' === $orderby ) {
			$query->set( 'meta_key', self::META_STATUS );
			$query->set( 'orderby', 'meta_value' );
		} elseif ( 'sl_reminder' === $orderby ) {
			$query->set( 'meta_key', self::META_REMINDER );
			$query->set( 'orderby', 'meta_value' );
		} elseif ( 'sl_amount' === $orderby ) {
			$query->set( 'meta_key', self::META_AMOUNT );
			$query->set( 'orderby', 'meta_value_num' );
		}
	}

	public static function filters( string $post_type ): void {
		if ( self::POST_TYPE !== $post_type ) {
			return;
		}

		$current_status   = isset( $_GET['sl_crm_status_filter'] ) ? sanitize_key( wp_unslash( $_GET['sl_crm_status_filter'] ) ) : '';
		$current_category = isset( $_GET['sl_crm_category_filter'] ) ? sanitize_key( wp_unslash( $_GET['sl_crm_category_filter'] ) ) : '';

		echo '<select name="sl_crm_status_filter"><option value="">Tous les statuts</option>';
		foreach ( self::$statuses as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $current_status, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';

		echo '<select name="sl_crm_category_filter"><option value="">Toutes les categories</option>';
		foreach ( self::$categories as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $current_category, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	public static function apply_filters( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || self::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		$meta_query = array();
		if ( ! empty( $_GET['sl_crm_status_filter'] ) ) {
			$status = sanitize_key( wp_unslash( $_GET['sl_crm_status_filter'] ) );
			if ( isset( self::$statuses[ $status ] ) ) {
				if ( 'nouvelle' === $status ) {
					$meta_query[] = array(
						'relation' => 'OR',
						array( 'key' => self::META_STATUS, 'value' => 'nouvelle' ),
						array( 'key' => self::META_STATUS, 'compare' => 'NOT EXISTS' ),
					);
				} else {
					$meta_query[] = array( 'key' => self::META_STATUS, 'value' => $status );
				}
			}
		}

		if ( ! empty( $_GET['sl_crm_category_filter'] ) ) {
			$category = sanitize_key( wp_unslash( $_GET['sl_crm_category_filter'] ) );
			if ( isset( self::$categories[ $category ] ) ) {
				$meta_query[] = array( 'key' => self::META_CATEGORY, 'value' => $category );
			}
		}

		if ( $meta_query ) {
			$query->set( 'meta_query', $meta_query );
		}
	}

	public static function admin_assets( string $hook ): void {
		$screen = get_current_screen();
		$is_crm_page = isset( $_GET['page'] ) && 'sl-patisserie-crm' === sanitize_key( wp_unslash( $_GET['page'] ) );
		if ( ! $screen || ( self::POST_TYPE !== $screen->post_type && ! $is_crm_page ) ) {
			return;
		}

		wp_enqueue_style( 'sl-patisserie-crm', plugin_dir_url( __FILE__ ) . 'assets/admin.css', array(), '1.2.0' );
	}

	public static function open_whatsapp(): void {
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		if ( ! $post_id || self::POST_TYPE !== get_post_type( $post_id ) || ! self::can_manage_crm() ) {
			wp_die( 'Vous ne pouvez pas acceder a cette demande.' );
		}

		check_admin_referer( 'sl_patisserie_whatsapp_' . $post_id );
		$url = self::get_whatsapp_url( $post_id );
		if ( ! $url ) {
			wp_safe_redirect( add_query_arg( array( 'post_type' => self::POST_TYPE, 'page' => 'sl-patisserie-crm', 'post_id' => $post_id, 'sl_crm_notice' => 'phone' ), admin_url( 'edit.php' ) ) );
			exit;
		}

		self::add_history( $post_id, 'Conversation WhatsApp ouverte' );
		wp_redirect( $url );
		exit;
	}

	public static function admin_notices(): void {
		if ( isset( $_GET['sl_crm_notice'] ) && 'phone' === sanitize_key( wp_unslash( $_GET['sl_crm_notice'] ) ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>Le numero de telephone du client est absent ou invalide.</p></div>';
		}
	}

	private static function get_submission_details( WP_Post $post ): array {
		$details = array();
		if ( trim( $post->post_content ) ) {
			$details['Message'] = $post->post_content;
		}
		if ( trim( $post->post_excerpt ) ) {
			$details['Resume'] = $post->post_excerpt;
		}

		$excluded = array(
			'_edit_lock', '_edit_last', '_wp_old_slug',
			self::META_STATUS, self::META_CATEGORY, self::META_AMOUNT, self::META_AGENCY,
			self::META_REMINDER, self::META_NOTES, self::META_HISTORY,
		);

		foreach ( get_post_meta( $post->ID ) as $key => $values ) {
			if ( in_array( $key, $excluded, true ) ) {
				continue;
			}
			$value = count( $values ) === 1 ? maybe_unserialize( $values[0] ) : array_map( 'maybe_unserialize', $values );
			if ( '' === $value || null === $value || array() === $value ) {
				continue;
			}
			$details[ self::humanize_key( $key ) ] = $value;
		}

		return $details;
	}

	private static function format_value( $value ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			$items = array();
			foreach ( (array) $value as $key => $item ) {
				$prefix  = is_string( $key ) ? '<strong>' . esc_html( self::humanize_key( $key ) ) . ' :</strong> ' : '';
				$items[] = '<li>' . $prefix . self::format_value( $item ) . '</li>';
			}
			return '<ul>' . implode( '', $items ) . '</ul>';
		}

		$text = trim( (string) $value );
		if ( is_email( $text ) ) {
			return '<a href="mailto:' . esc_attr( $text ) . '">' . esc_html( $text ) . '</a>';
		}
		if ( filter_var( $text, FILTER_VALIDATE_URL ) ) {
			return '<a href="' . esc_url( $text ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $text ) . '</a>';
		}
		return nl2br( esc_html( $text ) );
	}

	private static function humanize_key( string $key ): string {
		$labels = array(
			'nom' => 'Nom', 'name' => 'Nom', 'prenom' => 'Prenom', 'first_name' => 'Prenom',
			'email' => 'E-mail', 'telephone' => 'Telephone', 'phone' => 'Telephone', 'tel' => 'Telephone',
			'message' => 'Message', 'date' => 'Date souhaitee', 'date_evenement' => 'Date de l\'evenement',
			'gateau' => 'Gateau', 'type_gateau' => 'Type de gateau', 'parfum' => 'Parfum',
			'quantite' => 'Quantite', 'nombre_personnes' => 'Nombre de personnes', 'agence' => 'Agence',
		);

		$normalized = strtolower( ltrim( $key, '_' ) );
		if ( isset( $labels[ $normalized ] ) ) {
			return $labels[ $normalized ];
		}

		return ucfirst( trim( preg_replace( '/[_\-]+/', ' ', ltrim( $key, '_' ) ) ) );
	}

	private static function get_client_name( int $post_id ): string {
		$first = self::find_meta_value( $post_id, array( 'prenom', 'first_name', 'firstname' ) );
		$last  = self::find_meta_value( $post_id, array( 'nom', 'name', 'nom_client', 'client_name', 'fullname', 'full_name' ) );
		$name  = trim( $first . ' ' . $last );

		if ( ! $name ) {
			$title = get_the_title( $post_id );
			$name  = preg_match( '/^Demande\s*[-:#]?\s*\d*$/i', $title ) ? '' : trim( preg_split( '/\s+[—–-]\s+/', $title )[0] ?? $title );
		}

		return $name;
	}

	private static function get_client_phone( int $post_id ): string {
		return self::find_meta_value(
			$post_id,
			array( 'telephone', 'tel', 'phone', 'mobile', 'whatsapp', 'numero', 'numero_telephone', 'client_phone', 'telephone_client' )
		);
	}

	private static function get_client_message( int $post_id ): string {
		$message = self::find_meta_value( $post_id, array( 'message', 'commentaire', 'details', 'description', 'message_client' ) );
		if ( $message ) {
			return $message;
		}
		$post = get_post( $post_id );
		return $post ? trim( wp_strip_all_tags( $post->post_content ) ) : '';
	}

	private static function find_meta_value( int $post_id, array $candidates ): string {
		$all_meta = get_post_meta( $post_id );
		foreach ( $all_meta as $key => $values ) {
			$value = maybe_unserialize( $values[0] ?? '' );
			$found = self::find_value_recursive( $key, $value, $candidates );
			if ( $found ) {
				return $found;
			}
		}
		return '';
	}

	private static function find_value_recursive( string $key, $value, array $candidates ): string {
		$normalized = strtolower( trim( $key, '_' ) );
		foreach ( $candidates as $candidate ) {
			if ( $normalized === $candidate || str_ends_with( $normalized, '_' . $candidate ) ) {
				if ( is_scalar( $value ) && trim( (string) $value ) ) {
					return trim( (string) $value );
				}
			}
		}

		if ( is_array( $value ) || is_object( $value ) ) {
			foreach ( (array) $value as $child_key => $child_value ) {
				$found = self::find_value_recursive( (string) $child_key, $child_value, $candidates );
				if ( $found ) {
					return $found;
				}
			}
		}

		return '';
	}

	private static function normalize_whatsapp_phone( string $phone ): string {
		$digits = preg_replace( '/\D+/', '', $phone );
		if ( str_starts_with( $digits, '00' ) ) {
			$digits = substr( $digits, 2 );
		}
		if ( str_starts_with( $digits, '0' ) && 10 === strlen( $digits ) ) {
			$digits = '237' . substr( $digits, 1 );
		}
		if ( 9 === strlen( $digits ) && in_array( substr( $digits, 0, 1 ), array( '6', '2' ), true ) ) {
			$digits = '237' . $digits;
		}
		return strlen( $digits ) >= 10 ? $digits : '';
	}

	private static function get_whatsapp_message( int $post_id ): string {
		$name       = self::get_client_name( $post_id );
		$occasion   = self::find_meta_value( $post_id, array( 'type', 'occasion', 'type_gateau' ) );
		$date       = self::find_meta_value( $post_id, array( 'date', 'date_evenement', 'date_souhaitee' ) );
		$agency     = self::find_meta_value( $post_id, array( 'agence', 'agency' ) );
		$quantity   = self::find_meta_value( $post_id, array( 'quantite', 'nombre_personnes', 'parts' ) );
		$flavor     = self::find_meta_value( $post_id, array( 'saveur', 'flavor', 'parfum' ) );
		$budget     = self::find_meta_value( $post_id, array( 'budget', 'budget_indicatif' ) );
		$client_msg = self::get_client_message( $post_id );
		$greeting   = $name ? 'Bonjour *' . $name . '*,' : 'Bonjour,';

		$lines = array_filter( array(
			$occasion ? '• *Occasion :* ' . $occasion : '',
			$date ? '• *Date souhaitée :* ' . self::format_whatsapp_date( $date ) : '',
			$agency ? '• *Agence :* ' . $agency : '',
			$quantity ? '• *Nombre de parts :* ' . $quantity : '',
			$flavor ? '• *Saveur / parfum :* ' . $flavor : '',
			$budget ? '• *Budget indicatif :* ' . $budget . ' FCFA' : '',
			$client_msg ? '• *Détails / décoration :* ' . $client_msg : '',
		) );

		return $greeting
			. "\n\nNous avons bien reçu votre demande de pâtisserie auprès du *Complexe Santa Lucia*."
			. "\n\n*RÉCAPITULATIF DE VOTRE COMMANDE*"
			. "\n" . implode( "\n", $lines )
			. "\n\nMerci de nous répondre avec l’un des choix suivants :"
			. "\n*1. Je confirme ma commande*"
			. "\n*2. Je souhaite modifier ma commande*"
			. "\n\nNotre équipe vous contactera pour finaliser le prix et les modalités de retrait."
			. "\n\n*Complexe Santa Lucia*";
	}

	private static function format_whatsapp_date( string $date ): string {
		$timestamp = strtotime( $date );
		return $timestamp ? wp_date( 'd/m/Y', $timestamp ) : $date;
	}

	private static function get_whatsapp_url( int $post_id ): string {
		$phone = self::normalize_whatsapp_phone( self::get_client_phone( $post_id ) );
		if ( ! $phone ) {
			return '';
		}
		return 'https://wa.me/' . rawurlencode( $phone ) . '?text=' . rawurlencode( self::get_whatsapp_message( $post_id ) );
	}

	private static function get_whatsapp_admin_url( int $post_id ): string {
		if ( ! self::get_whatsapp_url( $post_id ) ) {
			return '';
		}
		$url = add_query_arg(
			array( 'action' => 'sl_patisserie_whatsapp', 'post_id' => $post_id ),
			admin_url( 'admin-post.php' )
		);
		return wp_nonce_url( $url, 'sl_patisserie_whatsapp_' . $post_id );
	}

	private static function add_history( int $post_id, string $text ): void {
		$history = get_post_meta( $post_id, self::META_HISTORY, true );
		$history = is_array( $history ) ? $history : array();
		$user    = wp_get_current_user();
		$history[] = array(
			'date' => current_time( 'd/m/Y H:i' ),
			'user' => $user->display_name ?: $user->user_login,
			'text' => sanitize_text_field( $text ),
		);
		update_post_meta( $post_id, self::META_HISTORY, array_slice( $history, -100 ) );
	}
}

SL_Patisserie_CRM::init();
