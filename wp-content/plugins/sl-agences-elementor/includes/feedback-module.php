<?php
/**
 * Module « Avis & Réclamations » Santa Lucia.
 * - CPT sl_feedback (privé), taxonomies type / service / statut + agence (sl_agence_promo).
 * - Formulaire public [sl_feedback_form] (AJAX, anti-spam).
 * - Témoignages publics [sl_avis_positifs] (avis positifs validés).
 * - Administration : colonnes, filtres, métabox de traitement, email au responsable.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SLF_VERSION', '1.0.0' );

/* ============================================================
   1. CPT + TAXONOMIES
   ============================================================ */
add_action( 'init', 'slf_register', 9 );
function slf_register() {

    register_post_type( 'sl_feedback', [
        'labels' => [
            'name'          => 'Avis & Réclamations',
            'singular_name' => 'Avis',
            'menu_name'     => 'Avis & Réclamations',
            'all_items'     => 'Tous les avis',
            'edit_item'     => 'Traiter l\'avis',
            'search_items'  => 'Rechercher',
        ],
        'public'             => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'menu_icon'          => 'dashicons-feedback',
        'menu_position'      => 26,
        'supports'           => [ 'title', 'editor' ],
        'capability_type'    => 'post',
        'map_meta_cap'       => true,
    ] );

    $common = [ 'public' => false, 'show_ui' => true, 'show_admin_column' => true, 'hierarchical' => false ];

    register_taxonomy( 'sl_feedback_type', 'sl_feedback', array_merge( $common, [
        'labels' => [ 'name' => 'Types', 'singular_name' => 'Type', 'menu_name' => 'Types d\'avis' ],
    ] ) );
    register_taxonomy( 'sl_feedback_service', 'sl_feedback', array_merge( $common, [
        'labels' => [ 'name' => 'Services', 'singular_name' => 'Service', 'menu_name' => 'Services' ],
    ] ) );
    register_taxonomy( 'sl_feedback_statut', 'sl_feedback', array_merge( $common, [
        'labels' => [ 'name' => 'Statuts', 'singular_name' => 'Statut', 'menu_name' => 'Statuts' ],
    ] ) );

    // Réutilise la taxonomie d'agences si elle existe.
    if ( taxonomy_exists( 'sl_agence_promo' ) ) {
        register_taxonomy_for_object_type( 'sl_agence_promo', 'sl_feedback' );
    }
}

/* Seed des termes par défaut (une seule fois) */
add_action( 'init', 'slf_seed_terms', 11 );
function slf_seed_terms() {
    if ( get_option( 'slf_seeded_v1' ) ) return;

    $types = [ 'Plainte', 'Mauvais comportement', 'Problème', 'Suggestion', 'Avis positif' ];
    foreach ( $types as $t ) { if ( ! term_exists( $t, 'sl_feedback_type' ) ) wp_insert_term( $t, 'sl_feedback_type' ); }

    $services = [ 'Pâtisserie', 'Caisse', 'Fast Food', 'Boulangerie', 'Boucherie', 'Glace', 'Accueil', 'Sécurité', 'Propreté', 'Livraison', 'Autre' ];
    foreach ( $services as $s ) { if ( ! term_exists( $s, 'sl_feedback_service' ) ) wp_insert_term( $s, 'sl_feedback_service' ); }

    $statuts = [ 'Nouveau', 'En cours', 'Traité', 'Clôturé' ];
    foreach ( $statuts as $st ) { if ( ! term_exists( $st, 'sl_feedback_statut' ) ) wp_insert_term( $st, 'sl_feedback_statut' ); }

    update_option( 'slf_seeded_v1', 1 );
}

function slf_recipient_email() {
    $e = get_option( 'slf_email', 'info@complexesantalucia.com' );
    return is_email( $e ) ? $e : 'info@complexesantalucia.com';
}

/* ============================================================
   2. ASSETS (front + admin)
   ============================================================ */
add_action( 'wp_enqueue_scripts', 'slf_front_assets' );
function slf_front_assets() {
    global $post;
    $need = is_a( $post, 'WP_Post' ) && ( has_shortcode( $post->post_content, 'sl_feedback_form' ) || has_shortcode( $post->post_content, 'sl_avis_positifs' ) );
    if ( ! $need ) return;
    wp_enqueue_style( 'slf-front', SL_AGENCES_URL . 'assets/css/feedback.css', [], SLF_VERSION );
    wp_enqueue_script( 'slf-front', SL_AGENCES_URL . 'assets/js/feedback.js', [], SLF_VERSION, true );
    wp_localize_script( 'slf-front', 'SLF', [
        'ajax'  => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'slf_submit' ),
    ] );
}
add_action( 'admin_enqueue_scripts', 'slf_admin_assets' );
function slf_admin_assets( $hook ) {
    $screen = get_current_screen();
    if ( $screen && $screen->post_type === 'sl_feedback' ) {
        wp_enqueue_style( 'slf-admin', SL_AGENCES_URL . 'assets/css/feedback.css', [], SLF_VERSION );
    }
}

/* ============================================================
   3. FORMULAIRE PUBLIC  [sl_feedback_form]
   ============================================================ */
add_shortcode( 'sl_feedback_form', 'slf_form_shortcode' );
function slf_form_shortcode() {
    $agences  = get_terms( [ 'taxonomy' => 'sl_agence_promo', 'hide_empty' => false, 'orderby' => 'name' ] );
    $services = get_terms( [ 'taxonomy' => 'sl_feedback_service', 'hide_empty' => false, 'orderby' => 'name' ] );
    $types    = get_terms( [ 'taxonomy' => 'sl_feedback_type', 'hide_empty' => false, 'orderby' => 'name' ] );
    $agences  = is_wp_error( $agences ) ? [] : $agences;
    $services = is_wp_error( $services ) ? [] : $services;
    $types    = is_wp_error( $types ) ? [] : $types;

    ob_start(); ?>
    <div class="slf-wrap">
      <form class="slf-form" id="slf-form">
        <div class="slf-row">
          <label class="slf-label">Votre message concerne <span class="slf-req">*</span></label>
          <div class="slf-chips" data-field="type">
            <?php foreach ( $types as $t ) : ?>
              <label class="slf-chip"><input type="radio" name="type" value="<?php echo esc_attr( $t->slug ); ?>" required><span><?php echo esc_html( $t->name ); ?></span></label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="slf-grid2">
          <div class="slf-row">
            <label class="slf-label" for="slf-agence">Agence concernée</label>
            <select id="slf-agence" name="agence">
              <option value="">— Sélectionner —</option>
              <?php foreach ( $agences as $a ) : ?><option value="<?php echo esc_attr( $a->slug ); ?>"><?php echo esc_html( $a->name ); ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="slf-row">
            <label class="slf-label" for="slf-service">Service concerné</label>
            <select id="slf-service" name="service">
              <option value="">— Sélectionner —</option>
              <?php foreach ( $services as $s ) : ?><option value="<?php echo esc_attr( $s->slug ); ?>"><?php echo esc_html( $s->name ); ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="slf-row">
          <label class="slf-label">Votre note</label>
          <div class="slf-stars" id="slf-stars">
            <?php for ( $i = 5; $i >= 1; $i-- ) : ?>
              <input type="radio" id="slf-star<?php echo $i; ?>" name="note" value="<?php echo $i; ?>"><label for="slf-star<?php echo $i; ?>" title="<?php echo $i; ?>/5">&#9733;</label>
            <?php endfor; ?>
          </div>
        </div>

        <div class="slf-row">
          <label class="slf-label" for="slf-message">Votre message <span class="slf-req">*</span></label>
          <textarea id="slf-message" name="message" rows="5" required placeholder="Décrivez votre expérience, votre plainte ou votre suggestion…"></textarea>
        </div>

        <div class="slf-grid2">
          <div class="slf-row">
            <label class="slf-label" for="slf-nom">Nom <span class="slf-req">*</span></label>
            <input type="text" id="slf-nom" name="nom" required>
          </div>
          <div class="slf-row">
            <label class="slf-label" for="slf-tel">Téléphone</label>
            <input type="tel" id="slf-tel" name="tel" placeholder="Ex : 6XX XX XX XX">
          </div>
        </div>
        <div class="slf-row">
          <label class="slf-label" for="slf-email">Email</label>
          <input type="email" id="slf-email" name="email" placeholder="pour être recontacté(e)">
          <p class="slf-hint">Indiquez au moins un moyen de contact (email ou téléphone) si vous souhaitez une réponse.</p>
        </div>

        <label class="slf-consent"><input type="checkbox" name="consent" value="1" required> J'accepte d'être recontacté(e) au sujet de mon message.</label>

        <!-- anti-spam -->
        <input type="text" name="slf_website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;" aria-hidden="true">

        <div class="slf-actions">
          <button type="submit" class="slf-btn">Envoyer mon message</button>
        </div>
        <div class="slf-feedback" id="slf-result" role="status" aria-live="polite"></div>
      </form>
    </div>
    <?php
    return ob_get_clean();
}

/* ============================================================
   4. TRAITEMENT AJAX
   ============================================================ */
add_action( 'wp_ajax_slf_submit', 'slf_handle_submit' );
add_action( 'wp_ajax_nopriv_slf_submit', 'slf_handle_submit' );
function slf_handle_submit() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'slf_submit' ) ) {
        wp_send_json_error( [ 'message' => 'Session expirée, veuillez recharger la page.' ], 400 );
    }
    // Honeypot
    if ( ! empty( $_POST['slf_website'] ) ) {
        wp_send_json_success( [ 'message' => 'Merci !' ] ); // piège : on fait semblant d'accepter
    }
    // Anti-flood
    $ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '0';
    $key = 'slf_rl_' . md5( $ip );
    $n   = (int) get_transient( $key );
    if ( $n >= 5 ) {
        wp_send_json_error( [ 'message' => 'Trop de messages envoyés. Réessayez dans quelques minutes.' ], 429 );
    }

    $type    = sanitize_title( $_POST['type'] ?? '' );
    $agence  = sanitize_title( $_POST['agence'] ?? '' );
    $service = sanitize_title( $_POST['service'] ?? '' );
    $note    = max( 0, min( 5, (int) ( $_POST['note'] ?? 0 ) ) );
    $message = trim( sanitize_textarea_field( $_POST['message'] ?? '' ) );
    $nom     = sanitize_text_field( $_POST['nom'] ?? '' );
    $email   = sanitize_email( $_POST['email'] ?? '' );
    $tel     = sanitize_text_field( $_POST['tel'] ?? '' );
    $consent = ! empty( $_POST['consent'] );

    if ( empty( $type ) )                 wp_send_json_error( [ 'message' => 'Veuillez choisir le type de message.' ], 422 );
    if ( strlen( $message ) < 5 )         wp_send_json_error( [ 'message' => 'Votre message est trop court.' ], 422 );
    if ( empty( $nom ) )                  wp_send_json_error( [ 'message' => 'Veuillez indiquer votre nom.' ], 422 );
    if ( ! $consent )                     wp_send_json_error( [ 'message' => 'Veuillez accepter d\'être recontacté(e).' ], 422 );
    if ( $email && ! is_email( $email ) ) wp_send_json_error( [ 'message' => 'Email invalide.' ], 422 );

    // Libellés lisibles
    $type_term    = get_term_by( 'slug', $type, 'sl_feedback_type' );
    $service_term = $service ? get_term_by( 'slug', $service, 'sl_feedback_service' ) : null;
    $agence_term  = $agence ? get_term_by( 'slug', $agence, 'sl_agence_promo' ) : null;
    $type_label   = $type_term ? $type_term->name : 'Avis';

    $title = sprintf( '[%s] %s%s — %s',
        $type_label,
        $service_term ? $service_term->name : 'Général',
        $agence_term ? ' / ' . $agence_term->name : '',
        $nom
    );

    $post_id = wp_insert_post( [
        'post_type'    => 'sl_feedback',
        'post_status'  => 'private',
        'post_title'   => $title,
        'post_content' => $message,
    ], true );

    if ( is_wp_error( $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Erreur serveur, réessayez plus tard.' ], 500 );
    }

    if ( $type_term )    wp_set_object_terms( $post_id, [ (int) $type_term->term_id ], 'sl_feedback_type' );
    if ( $service_term ) wp_set_object_terms( $post_id, [ (int) $service_term->term_id ], 'sl_feedback_service' );
    if ( $agence_term )  wp_set_object_terms( $post_id, [ (int) $agence_term->term_id ], 'sl_agence_promo' );
    $nouveau = get_term_by( 'name', 'Nouveau', 'sl_feedback_statut' );
    if ( $nouveau )      wp_set_object_terms( $post_id, [ (int) $nouveau->term_id ], 'sl_feedback_statut' );

    update_post_meta( $post_id, '_slf_nom', $nom );
    update_post_meta( $post_id, '_slf_email', $email );
    update_post_meta( $post_id, '_slf_tel', $tel );
    update_post_meta( $post_id, '_slf_note', $note );
    update_post_meta( $post_id, '_slf_agence', $agence );

    set_transient( $key, $n + 1, 10 * MINUTE_IN_SECONDS );

    // Email au responsable
    $to      = slf_recipient_email();
    $subject = sprintf( '[Santa Lucia] %s — %s', $type_label, $service_term ? $service_term->name : 'Général' );
    $lines   = [
        'Type : ' . $type_label,
        'Service : ' . ( $service_term ? $service_term->name : '—' ),
        'Agence : ' . ( $agence_term ? $agence_term->name : '—' ),
        'Note : ' . ( $note ? $note . '/5' : '—' ),
        '',
        'Message :',
        $message,
        '',
        '— Contact —',
        'Nom : ' . $nom,
        'Email : ' . ( $email ?: '—' ),
        'Téléphone : ' . ( $tel ?: '—' ),
        '',
        'Gérer : ' . admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
    ];
    $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];
    if ( $email ) $headers[] = 'Reply-To: ' . $nom . ' <' . $email . '>';
    @wp_mail( $to, $subject, implode( "\n", $lines ), $headers );

    wp_send_json_success( [ 'message' => 'Merci ! Votre message a bien été transmis à notre équipe. Nous vous recontacterons si nécessaire.' ] );
}

/* ============================================================
   5. TÉMOIGNAGES PUBLICS  [sl_avis_positifs]
   ============================================================ */
add_shortcode( 'sl_avis_positifs', 'slf_testimonials_shortcode' );
function slf_testimonials_shortcode( $atts ) {
    $atts = shortcode_atts( [ 'nombre' => 9 ], $atts );
    $q = new WP_Query( [
        'post_type'      => 'sl_feedback',
        'post_status'    => 'publish',
        'posts_per_page' => (int) $atts['nombre'],
        'meta_query'     => [ [ 'key' => '_slf_public', 'value' => '1' ] ],
        'orderby'        => 'date',
        'order'          => 'DESC',
    ] );
    if ( ! $q->have_posts() ) return '';
    ob_start();
    echo '<div class="slf-temoignages">';
    while ( $q->have_posts() ) { $q->the_post();
        $id   = get_the_ID();
        $nom  = get_post_meta( $id, '_slf_nom', true );
        $note = (int) get_post_meta( $id, '_slf_note', true );
        $svc  = get_the_terms( $id, 'sl_feedback_service' );
        echo '<div class="slf-temoignage">';
        if ( $note ) {
            echo '<div class="slf-temoignage-note">';
            for ( $i = 1; $i <= 5; $i++ ) echo $i <= $note ? '&#9733;' : '<span class="slf-star-off">&#9733;</span>';
            echo '</div>';
        }
        echo '<p class="slf-temoignage-msg">' . esc_html( wp_trim_words( get_the_content(), 40 ) ) . '</p>';
        echo '<p class="slf-temoignage-auteur">' . esc_html( $nom );
        if ( ! is_wp_error( $svc ) && $svc ) echo ' <span>· ' . esc_html( $svc[0]->name ) . '</span>';
        echo '</p></div>';
    }
    echo '</div>';
    wp_reset_postdata();
    return ob_get_clean();
}

/* ============================================================
   6. ADMINISTRATION : colonnes, filtres, métabox
   ============================================================ */
add_filter( 'manage_sl_feedback_posts_columns', 'slf_columns' );
function slf_columns( $cols ) {
    $new = [ 'cb' => $cols['cb'], 'title' => 'Avis' ];
    $new['slf_type']    = 'Type';
    $new['slf_service'] = 'Service';
    $new['slf_agence']  = 'Agence';
    $new['slf_note']    = 'Note';
    $new['slf_statut']  = 'Statut';
    $new['slf_contact'] = 'Contact';
    $new['date']        = 'Date';
    return $new;
}
add_action( 'manage_sl_feedback_posts_custom_column', 'slf_column_content', 10, 2 );
function slf_column_content( $col, $post_id ) {
    $term_names = function( $tax ) use ( $post_id ) {
        $t = get_the_terms( $post_id, $tax );
        return ( ! is_wp_error( $t ) && $t ) ? implode( ', ', wp_list_pluck( $t, 'name' ) ) : '—';
    };
    switch ( $col ) {
        case 'slf_type':    echo esc_html( $term_names( 'sl_feedback_type' ) ); break;
        case 'slf_service': echo esc_html( $term_names( 'sl_feedback_service' ) ); break;
        case 'slf_agence':  echo esc_html( $term_names( 'sl_agence_promo' ) ); break;
        case 'slf_note':
            $n = (int) get_post_meta( $post_id, '_slf_note', true );
            echo $n ? str_repeat( '★', $n ) . '<span style="color:#ccc">' . str_repeat( '★', 5 - $n ) . '</span>' : '—';
            break;
        case 'slf_statut':  echo esc_html( $term_names( 'sl_feedback_statut' ) ); break;
        case 'slf_contact':
            $email = get_post_meta( $post_id, '_slf_email', true );
            $tel   = get_post_meta( $post_id, '_slf_tel', true );
            echo $email ? '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a><br>' : '';
            echo $tel ? esc_html( $tel ) : ( $email ? '' : '—' );
            break;
    }
}

/* Filtres déroulants en haut de la liste */
add_action( 'restrict_manage_posts', 'slf_admin_filters' );
function slf_admin_filters( $post_type ) {
    if ( $post_type !== 'sl_feedback' ) return;
    foreach ( [ 'sl_feedback_type' => 'Tous les types', 'sl_feedback_service' => 'Tous les services', 'sl_feedback_statut' => 'Tous les statuts', 'sl_agence_promo' => 'Toutes les agences' ] as $tax => $label ) {
        $terms = get_terms( [ 'taxonomy' => $tax, 'hide_empty' => false ] );
        if ( is_wp_error( $terms ) || ! $terms ) continue;
        $current = isset( $_GET[ $tax ] ) ? $_GET[ $tax ] : '';
        echo '<select name="' . esc_attr( $tax ) . '"><option value="">' . esc_html( $label ) . '</option>';
        foreach ( $terms as $t ) {
            printf( '<option value="%s"%s>%s</option>', esc_attr( $t->slug ), selected( $current, $t->slug, false ), esc_html( $t->name ) );
        }
        echo '</select>';
    }
}

/* Métabox de traitement */
add_action( 'add_meta_boxes', 'slf_metaboxes' );
function slf_metaboxes() {
    add_meta_box( 'slf_contact', 'Coordonnées du client', 'slf_box_contact', 'sl_feedback', 'side', 'high' );
    add_meta_box( 'slf_traitement', 'Traitement', 'slf_box_traitement', 'sl_feedback', 'side', 'default' );
}
function slf_box_contact( $post ) {
    $nom   = get_post_meta( $post->ID, '_slf_nom', true );
    $email = get_post_meta( $post->ID, '_slf_email', true );
    $tel   = get_post_meta( $post->ID, '_slf_tel', true );
    $note  = (int) get_post_meta( $post->ID, '_slf_note', true );
    echo '<p><strong>Nom :</strong> ' . esc_html( $nom ?: '—' ) . '</p>';
    echo '<p><strong>Email :</strong> ' . ( $email ? '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>' : '—' ) . '</p>';
    echo '<p><strong>Téléphone :</strong> ' . esc_html( $tel ?: '—' ) . '</p>';
    echo '<p><strong>Note :</strong> ' . ( $note ? esc_html( $note ) . '/5' : '—' ) . '</p>';
}
function slf_box_traitement( $post ) {
    wp_nonce_field( 'slf_save', 'slf_nonce' );
    $public = get_post_meta( $post->ID, '_slf_public', true );
    $statuts = get_terms( [ 'taxonomy' => 'sl_feedback_statut', 'hide_empty' => false ] );
    $current = wp_get_object_terms( $post->ID, 'sl_feedback_statut', [ 'fields' => 'slugs' ] );
    $cur = ( ! is_wp_error( $current ) && $current ) ? $current[0] : '';
    echo '<p><label for="slf_statut"><strong>Statut</strong></label><br><select name="slf_statut" id="slf_statut" style="width:100%">';
    echo '<option value="">—</option>';
    if ( ! is_wp_error( $statuts ) ) foreach ( $statuts as $st ) {
        printf( '<option value="%s"%s>%s</option>', esc_attr( $st->slug ), selected( $cur, $st->slug, false ), esc_html( $st->name ) );
    }
    echo '</select></p>';
    echo '<p><label><input type="checkbox" name="slf_public" value="1"' . checked( $public, '1', false ) . '> Afficher comme témoignage public</label></p>';
    echo '<p class="description">Cochez pour publier cet avis (positif) sur le site via <code>[sl_avis_positifs]</code>.</p>';
}
add_action( 'save_post_sl_feedback', 'slf_save_box', 10, 2 );
function slf_save_box( $post_id, $post ) {
    if ( ! isset( $_POST['slf_nonce'] ) || ! wp_verify_nonce( $_POST['slf_nonce'], 'slf_save' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    if ( isset( $_POST['slf_statut'] ) ) {
        $slug = sanitize_title( $_POST['slf_statut'] );
        $term = $slug ? get_term_by( 'slug', $slug, 'sl_feedback_statut' ) : null;
        wp_set_object_terms( $post_id, $term ? [ (int) $term->term_id ] : [], 'sl_feedback_statut' );
    }
    $public = ! empty( $_POST['slf_public'] );
    update_post_meta( $post_id, '_slf_public', $public ? '1' : '' );

    // Publier rend l'avis interrogeable publiquement (CPT non public => pas de page单 visible).
    remove_action( 'save_post_sl_feedback', 'slf_save_box', 10 );
    wp_update_post( [ 'ID' => $post_id, 'post_status' => $public ? 'publish' : 'private' ] );
    add_action( 'save_post_sl_feedback', 'slf_save_box', 10, 2 );
}

/* ============================================================
   7. CRÉATION AUTOMATIQUE DE LA PAGE PUBLIQUE
   ============================================================ */
add_action( 'init', 'slf_create_page', 20 );
function slf_create_page() {
    if ( get_option( 'slf_page_id' ) ) return;
    $content = "<!-- wp:paragraph --><p>Votre avis compte. Signalez un problème, déposez une réclamation, faites une suggestion ou partagez une expérience positive sur le Complexe Santa Lucia. Notre équipe relation client vous recontactera si nécessaire.</p><!-- /wp:paragraph -->\n[sl_feedback_form]";
    $page_id = wp_insert_post( [
        'post_title'   => 'Avis & Réclamations',
        'post_name'    => 'avis',
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_content' => $content,
    ] );
    if ( $page_id && ! is_wp_error( $page_id ) ) {
        update_option( 'slf_page_id', $page_id );
    }
}
