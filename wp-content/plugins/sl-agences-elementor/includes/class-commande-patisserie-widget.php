<?php
/** Widget Elementor + registre des demandes de gâteaux personnalisés. */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', function () {
    $caps = [ 'edit_slg_requests', 'edit_slg_request', 'read_slg_request', 'delete_slg_request', 'publish_slg_requests', 'read_private_slg_requests', 'delete_slg_requests', 'delete_private_slg_requests', 'delete_published_slg_requests', 'delete_others_slg_requests', 'edit_private_slg_requests', 'edit_published_slg_requests' ];
    foreach ( [ 'administrator', 'editor', 'sl_patisserie_manager' ] as $role_name ) {
        $role = get_role( $role_name );
        if ( $role ) foreach ( $caps as $cap ) $role->add_cap( $cap );
    }
    if ( ! get_role( 'sl_patisserie_manager' ) ) {
        add_role( 'sl_patisserie_manager', 'Responsable pâtisserie', array_fill_keys( array_merge( [ 'read' ], $caps ), true ) );
    }
    register_post_type( 'sl_demande_gateau', [
        'labels' => [
            'name' => 'Demandes pâtisserie', 'singular_name' => 'Demande pâtisserie',
            'menu_name' => 'Demandes pâtisserie', 'add_new_item' => 'Ajouter une demande',
            'edit_item' => 'Voir la demande',
        ],
        'public' => false, 'show_ui' => true, 'show_in_menu' => true,
        'menu_icon' => 'dashicons-cake', 'supports' => [ 'title' ],
        'capability_type' => [ 'slg_request', 'slg_requests' ], 'map_meta_cap' => true,
    ] );
} );

/** Création d’une demande depuis Lucie, après confirmation explicite du client. */
function slg_create_cake_request_from_lucie( $data ) {
    $name = sanitize_text_field( $data['nom'] ?? '' );
    $phone = sanitize_text_field( $data['telephone'] ?? '' );
    $type = sanitize_text_field( $data['type'] ?? '' );
    $date = sanitize_text_field( $data['date'] ?? '' );
    $agency = sanitize_text_field( $data['agence'] ?? '' );
    $email = sanitize_email( $data['email'] ?? '' );
    $qty = absint( $data['quantite'] ?? 0 );
    $flavor = sanitize_text_field( $data['saveur'] ?? '' );
    $budget = sanitize_text_field( $data['budget'] ?? '' );
    $message = sanitize_textarea_field( $data['message'] ?? '' );
    if ( $name === '' || $phone === '' || $type === '' || $date === '' || $agency === '' ) return new WP_Error( 'slg_missing', 'Il manque le nom, le téléphone, l’occasion, la date ou l’agence.' );
    $date_obj = DateTime::createFromFormat( '!Y-m-d', $date );
    if ( ! $date_obj || $date_obj->format( 'Y-m-d' ) !== $date || $date < current_time( 'Y-m-d' ) ) return new WP_Error( 'slg_date', 'La date souhaitée doit être une date future valide.' );
    if ( $email !== '' && ! is_email( $email ) ) return new WP_Error( 'slg_email', 'L’adresse e-mail n’est pas valide.' );
    $post_id = wp_insert_post( [ 'post_type' => 'sl_demande_gateau', 'post_status' => 'private', 'post_title' => $name . ' — ' . $type . ' — ' . $date ], true );
    if ( is_wp_error( $post_id ) ) return $post_id;
    foreach ( [ 'type' => $type, 'date' => $date, 'agence' => $agency, 'quantite' => $qty, 'saveur' => $flavor, 'budget' => $budget, 'telephone' => $phone, 'email' => $email, 'message' => $message, 'source' => 'Lucie IA' ] as $key => $value ) update_post_meta( $post_id, '_slg_' . $key, $value );
    wp_mail( get_option( 'admin_email' ), 'Nouvelle demande de gâteau — ' . $type, "Nouvelle demande prise par Lucie IA\n\nClient : {$name}\nTéléphone : {$phone}\nOccasion : {$type}\nDate : {$date}\nAgence : {$agency}\nParts : " . ( $qty ?: 'À préciser' ) . "\nSaveur : " . ( $flavor ?: 'À préciser' ) . "\nBudget : " . ( $budget ?: 'À préciser' ) . "\nE-mail : " . ( $email ?: 'Non fourni' ) . "\n\nDétails :\n" . ( $message ?: '—' ), $email ? [ 'Reply-To: ' . $email ] : [] );
    return $post_id;
}

add_filter( 'manage_sl_demande_gateau_posts_columns', function ( $columns ) {
    return [ 'cb' => $columns['cb'], 'title' => 'Client', 'slg_type' => 'Occasion', 'slg_date' => 'Date souhaitée', 'slg_agence' => 'Agence', 'slg_contact' => 'Contact', 'date' => 'Reçue le' ];
} );
add_action( 'manage_sl_demande_gateau_posts_custom_column', function ( $column, $post_id ) {
    $meta = function ( $key ) use ( $post_id ) { return get_post_meta( $post_id, '_slg_' . $key, true ); };
    if ( 'slg_type' === $column ) echo esc_html( $meta( 'type' ) );
    if ( 'slg_date' === $column ) echo esc_html( $meta( 'date' ) );
    if ( 'slg_agence' === $column ) echo esc_html( $meta( 'agence' ) );
    if ( 'slg_contact' === $column ) echo esc_html( $meta( 'telephone' ) );
}, 10, 2 );

add_action( 'add_meta_boxes', function () {
    add_meta_box( 'slg_details', 'Détails de la demande', function ( $post ) {
        $fields = [ 'type' => 'Occasion', 'date' => 'Date souhaitée', 'agence' => 'Agence', 'quantite' => 'Nombre de parts', 'saveur' => 'Saveur / parfum', 'budget' => 'Budget indicatif', 'telephone' => 'Téléphone', 'email' => 'E-mail', 'message' => 'Détails / décoration' ];
        echo '<table class="form-table"><tbody>';
        foreach ( $fields as $key => $label ) {
            $value = get_post_meta( $post->ID, '_slg_' . $key, true );
            echo '<tr><th>' . esc_html( $label ) . '</th><td>' . ( 'message' === $key ? '<p style="white-space:pre-wrap;margin:0">' . esc_html( $value ) . '</p>' : '<strong>' . esc_html( $value ?: '—' ) . '</strong>' ) . '</td></tr>';
        }
        echo '</tbody></table>';
    }, 'sl_demande_gateau', 'normal', 'high' );
} );

add_action( 'wp_ajax_sl_submit_cake_request', 'sl_submit_cake_request' );
add_action( 'wp_ajax_nopriv_sl_submit_cake_request', 'sl_submit_cake_request' );
function sl_submit_cake_request() {
    if ( ! check_ajax_referer( 'sl_cake_request', 'nonce', false ) ) wp_send_json_error( [ 'message' => 'Session expirée. Rechargez la page puis réessayez.' ], 403 );
    if ( ! empty( $_POST['website'] ) ) wp_send_json_error( [ 'message' => 'Demande invalide.' ], 400 );

    $name = sanitize_text_field( wp_unslash( $_POST['nom'] ?? '' ) );
    $phone = sanitize_text_field( wp_unslash( $_POST['telephone'] ?? '' ) );
    $type = sanitize_text_field( wp_unslash( $_POST['type'] ?? '' ) );
    $date = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );
    $agency = sanitize_text_field( wp_unslash( $_POST['agence'] ?? '' ) );
    $email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
    $qty = absint( $_POST['quantite'] ?? 0 );
    $flavor = sanitize_text_field( wp_unslash( $_POST['saveur'] ?? '' ) );
    $budget = sanitize_text_field( wp_unslash( $_POST['budget'] ?? '' ) );
    $message = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
    if ( $name === '' || $phone === '' || $type === '' || $date === '' || $agency === '' ) wp_send_json_error( [ 'message' => 'Veuillez renseigner les champs obligatoires.' ], 422 );
    $date_obj = DateTime::createFromFormat( '!Y-m-d', $date );
    if ( ! $date_obj || $date_obj->format( 'Y-m-d' ) !== $date || $date < current_time( 'Y-m-d' ) ) wp_send_json_error( [ 'message' => 'Choisissez une date future valide.' ], 422 );
    if ( $email !== '' && ! is_email( $email ) ) wp_send_json_error( [ 'message' => 'L’adresse e-mail n’est pas valide.' ], 422 );

    $post_id = wp_insert_post( [ 'post_type' => 'sl_demande_gateau', 'post_status' => 'private', 'post_title' => $name . ' — ' . $type . ' — ' . $date ], true );
    if ( is_wp_error( $post_id ) ) wp_send_json_error( [ 'message' => 'La demande n’a pas pu être enregistrée.' ], 500 );
    $values = compact( 'type', 'date', 'agency', 'qty', 'flavor', 'budget', 'phone', 'email', 'message' );
    foreach ( $values as $key => $value ) update_post_meta( $post_id, '_slg_' . ( [ 'agency' => 'agence', 'qty' => 'quantite', 'phone' => 'telephone' ][ $key ] ?? $key ), $value );

    $subject = 'Nouvelle demande de gâteau — ' . $type;
    $body = "Nouvelle demande de pâtisserie\n\nClient : {$name}\nTéléphone : {$phone}\nOccasion : {$type}\nDate : {$date}\nAgence : {$agency}\nParts : " . ( $qty ?: 'À préciser' ) . "\nSaveur : " . ( $flavor ?: 'À préciser' ) . "\nBudget : " . ( $budget ?: 'À préciser' ) . "\nE-mail : " . ( $email ?: 'Non fourni' ) . "\n\nDétails :\n" . ( $message ?: '—' );
    wp_mail( get_option( 'admin_email' ), $subject, $body, $email ? [ 'Reply-To: ' . $email ] : [] );
    wp_send_json_success( [ 'message' => 'Votre demande est bien reçue. Notre équipe pâtisserie vous recontactera pour confirmer le modèle, le prix et la disponibilité.' ] );
}

if ( class_exists( '\Elementor\Widget_Base' ) ) {
    class SL_Commande_Patisserie_Widget extends \Elementor\Widget_Base {
        public function get_name() { return 'sl_commande_patisserie'; }
        public function get_title() { return 'Commande gâteaux — Pâtisserie'; }
        public function get_icon() { return 'eicon-form-horizontal'; }
        public function get_categories() { return [ 'santa-lucia' ]; }
        public function get_keywords() { return [ 'gâteau', 'gateau', 'anniversaire', 'mariage', 'pâtisserie', 'commande' ]; }
        protected function register_controls() {
            $this->start_controls_section( 'content', [ 'label' => 'Contenu' ] );
            $this->add_control( 'title', [ 'label' => 'Titre', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Un gâteau pour vos moments précieux' ] );
            $this->add_control( 'subtitle', [ 'label' => 'Sous-titre', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'default' => 'Décrivez votre projet : notre équipe pâtisserie vous recontactera pour confirmer les détails et le prix.' ] );
            $this->end_controls_section();
        }
        protected function render() {
            $s = $this->get_settings_for_display();
            $agencies = function_exists( 'slc_agences' ) ? slc_agences() : get_terms( [ 'taxonomy' => 'sl_agence_promo', 'hide_empty' => false, 'orderby' => 'name' ] );
            if ( is_wp_error( $agencies ) ) $agencies = [];
            static $assets_printed = false;
            if ( ! $assets_printed ) { $assets_printed = true; ?>
                <style>.sl-cake-box{max-width:980px;margin:0 auto;padding:clamp(24px,4vw,48px);border-radius:24px;background:linear-gradient(135deg,#fff8fb,#fff);border:1px solid #f1dbe6;box-shadow:0 12px 35px rgba(29,18,38,.08)}.sl-cake-head{text-align:center;max-width:650px;margin:0 auto 28px}.sl-cake-head h2{margin:0 0 10px;color:#142b4f;font-size:clamp(26px,4vw,42px)}.sl-cake-head p{margin:0;color:#5e6877}.sl-cake-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.sl-cake-field{display:flex;flex-direction:column;gap:7px}.sl-cake-field.full{grid-column:1/-1}.sl-cake-field label{font-weight:600;color:#142b4f;font-size:14px}.sl-cake-field input,.sl-cake-field select,.sl-cake-field textarea{width:100%;box-sizing:border-box;border:1px solid #d8dce4;border-radius:10px;padding:12px 13px;background:#fff;font:inherit;color:#172033}.sl-cake-field textarea{min-height:105px;resize:vertical}.sl-cake-submit{grid-column:1/-1;display:flex;align-items:center;gap:16px;margin-top:4px}.sl-cake-submit button{border:0;border-radius:10px;padding:14px 24px;background:#e91e63;color:#fff;font-weight:700;cursor:pointer}.sl-cake-submit button:disabled{opacity:.6;cursor:wait}.sl-cake-note{font-size:13px;color:#657080}.sl-cake-result{grid-column:1/-1;padding:13px 15px;border-radius:10px;background:#eaf8f0;color:#17633a}.sl-cake-hp{position:absolute;left:-9999px}@media(max-width:650px){.sl-cake-form{grid-template-columns:1fr}.sl-cake-field.full{grid-column:auto}.sl-cake-submit{grid-column:auto;display:block}.sl-cake-submit button{width:100%;margin-bottom:10px}}</style>
            <?php } ?>
            <section class="sl-cake-box" aria-labelledby="sl-cake-title-<?php echo esc_attr( $this->get_id() ); ?>"><div class="sl-cake-head"><h2 id="sl-cake-title-<?php echo esc_attr( $this->get_id() ); ?>"><?php echo esc_html( $s['title'] ); ?></h2><p><?php echo esc_html( $s['subtitle'] ); ?></p></div><form class="sl-cake-form" data-sl-cake-form><input type="hidden" name="action" value="sl_submit_cake_request"><input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'sl_cake_request' ) ); ?>"><input class="sl-cake-hp" type="text" name="website" tabindex="-1" autocomplete="off"><div class="sl-cake-field"><label>Nom complet *</label><input name="nom" required autocomplete="name"></div><div class="sl-cake-field"><label>Téléphone *</label><input name="telephone" required type="tel" autocomplete="tel"></div><div class="sl-cake-field"><label>Occasion *</label><select name="type" required><option value="">Choisir…</option><option>Anniversaire</option><option>Mariage</option><option>Baptême</option><option>Communion</option><option>Événement professionnel</option><option>Autre</option></select></div><div class="sl-cake-field"><label>Date souhaitée *</label><input name="date" required type="date" min="<?php echo esc_attr( date( 'Y-m-d', current_time( 'timestamp' ) + DAY_IN_SECONDS ) ); ?>"></div><div class="sl-cake-field"><label>Agence de retrait *</label><select name="agence" required><option value="">Choisir une agence…</option><?php foreach ( $agencies as $agency ) : ?><option value="<?php echo esc_attr( $agency->name ); ?>"><?php echo esc_html( $agency->name ); ?></option><?php endforeach; ?></select></div><div class="sl-cake-field"><label>Nombre de parts</label><input name="quantite" type="number" min="1" max="500" placeholder="Ex. 20"></div><div class="sl-cake-field"><label>Saveur / parfum souhaité</label><input name="saveur" placeholder="Ex. chocolat, vanille, fruits…"></div><div class="sl-cake-field"><label>Budget indicatif (FCFA)</label><input name="budget" inputmode="numeric" placeholder="Ex. 25 000"></div><div class="sl-cake-field"><label>E-mail</label><input name="email" type="email" autocomplete="email"></div><div class="sl-cake-field full"><label>Décrivez votre gâteau</label><textarea name="message" placeholder="Taille, couleurs, inscription, décoration, photo de référence…"></textarea></div><div class="sl-cake-submit"><button type="submit">Envoyer ma demande</button><span class="sl-cake-note">Réponse de l’équipe pâtisserie après étude de votre projet.</span></div><div class="sl-cake-result" hidden role="status"></div></form></section>
            <script>(function(){var forms=document.querySelectorAll('[data-sl-cake-form]');forms.forEach(function(form){form.addEventListener('submit',function(e){e.preventDefault();var btn=form.querySelector('button'),out=form.querySelector('.sl-cake-result');btn.disabled=true;out.hidden=true;var data=new FormData(form);fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',{method:'POST',body:data,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(json){if(!json.success)throw new Error(json.data&&json.data.message?json.data.message:'Une erreur est survenue.');out.textContent=json.data.message;out.hidden=false;form.reset()}).catch(function(err){out.textContent=err.message;out.style.background='#fff1f1';out.style.color='#9c1c1c';out.hidden=false}).finally(function(){btn.disabled=false})})})})();</script>
            <?php
        }
    }
}
