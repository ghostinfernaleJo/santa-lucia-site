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

/** Référencement de la page Pâtisserie, sans dépendre du contenu Elementor. */
function slg_is_patisserie_page() {
    return ! is_admin() && is_page( 'patisserie' );
}

function slg_patisserie_seo_title() {
    return 'Gâteaux personnalisés | Pâtisserie Santa Lucia';
}

function slg_patisserie_seo_description() {
    return 'Commandez un gâteau personnalisé pour un anniversaire, un mariage ou un baptême chez Santa Lucia. Retrait dans l’agence de votre choix.';
}

function slg_patisserie_seo_image() {
    return 'https://complexesantalucia.com/wp-content/uploads/2024/06/arriere-plan-patisserie-complexe-santa-lucia.webp';
}

/** Les questions restent visibles sur la page et servent aussi aux données structurées. */
function slg_patisserie_faq_items() {
    return [
        [
            'question' => 'Pour quels événements puis-je commander un gâteau ?',
            'answer'   => 'Les demandes peuvent concerner notamment un anniversaire, un mariage, un baptême, une communion, un événement professionnel ou toute autre célébration.',
        ],
        [
            'question' => 'Quelles informations dois-je indiquer dans ma demande ?',
            'answer'   => 'Indiquez au minimum votre nom, votre téléphone, l’occasion, la date souhaitée et l’agence de retrait. Le nombre de parts, la saveur, le budget et les idées de décoration aideront aussi notre équipe à vous proposer une réponse adaptée.',
        ],
        [
            'question' => 'Comment le modèle et le prix sont-ils confirmés ?',
            'answer'   => 'Après étude de votre demande, l’équipe pâtisserie vous recontacte pour confirmer le modèle, le prix et la disponibilité avant la préparation.',
        ],
        [
            'question' => 'Où retirer ma commande de gâteau ?',
            'answer'   => 'Choisissez l’agence souhaitée dans le formulaire. L’équipe confirme ensuite avec vous le lieu de retrait de votre commande.',
        ],
    ];
}

function slg_filter_patisserie_document_title( $title ) {
    return slg_is_patisserie_page() ? slg_patisserie_seo_title() : $title;
}
add_filter( 'pre_get_document_title', 'slg_filter_patisserie_document_title', 999 );
add_filter( 'rank_math/frontend/title', 'slg_filter_patisserie_document_title', 999 );

function slg_filter_patisserie_description( $description ) {
    return slg_is_patisserie_page() ? slg_patisserie_seo_description() : $description;
}
add_filter( 'rank_math/frontend/description', 'slg_filter_patisserie_description', 999 );

function slg_filter_patisserie_social_image( $image ) {
    return slg_is_patisserie_page() ? slg_patisserie_seo_image() : $image;
}
add_filter( 'rank_math/opengraph/facebook/image', 'slg_filter_patisserie_social_image', 999 );
add_filter( 'rank_math/opengraph/twitter/image', 'slg_filter_patisserie_social_image', 999 );

/** Replie les balises indispensables si Rank Math est désactivé ultérieurement. */
function slg_patisserie_fallback_meta() {
    if ( ! slg_is_patisserie_page() || defined( 'RANK_MATH_VERSION' ) ) return;
    $title       = slg_patisserie_seo_title();
    $description = slg_patisserie_seo_description();
    $image       = slg_patisserie_seo_image();
    printf( "\n<meta name=\"description\" content=\"%s\">\n<meta property=\"og:title\" content=\"%s\">\n<meta property=\"og:description\" content=\"%s\">\n<meta property=\"og:image\" content=\"%s\">\n<meta name=\"twitter:card\" content=\"summary_large_image\">\n", esc_attr( $description ), esc_attr( $title ), esc_attr( $description ), esc_url( $image ) );
}
add_action( 'wp_head', 'slg_patisserie_fallback_meta', 1 );

/** Remplace le schéma Article générique par les données utiles à une demande de gâteau. */
function slg_patisserie_schema( $data, $jsonld ) {
    if ( ! slg_is_patisserie_page() ) return $data;

    foreach ( $data as $key => $schema ) {
        $types = isset( $schema['@type'] ) ? (array) $schema['@type'] : [];
        if ( in_array( 'Article', $types, true ) || in_array( 'BreadcrumbList', $types, true ) ) unset( $data[ $key ] );
    }

    $page_url    = get_permalink();
    $site_url    = home_url( '/' );
    $title       = slg_patisserie_seo_title();
    $description = slg_patisserie_seo_description();
    $image       = slg_patisserie_seo_image();
    if ( ! $page_url ) return $data;

    if ( isset( $data['WebPage'] ) && is_array( $data['WebPage'] ) ) {
        $data['WebPage']['name']        = $title;
        $data['WebPage']['description'] = $description;
    }

    $data['PatisserieSantaLucia'] = [
        '@type'              => 'Bakery',
        '@id'                => $page_url . '#bakery',
        'name'               => 'Pâtisserie Santa Lucia',
        'url'                => $page_url,
        'image'              => $image,
        'description'        => $description,
        'parentOrganization' => [ '@id' => $site_url . '#organization' ],
        'areaServed'         => [ '@type' => 'Country', 'name' => 'Cameroun' ],
        'makesOffer'         => [ '@type' => 'Offer', 'itemOffered' => [ '@id' => $page_url . '#cake-order-service' ] ],
    ];
    $data['CommandeGateauxSantaLucia'] = [
        '@type'       => 'Service',
        '@id'         => $page_url . '#cake-order-service',
        'name'        => 'Commande de gâteaux personnalisés',
        'description' => 'Demande de gâteaux personnalisés pour un anniversaire, un mariage, un baptême ou un événement professionnel, avec retrait en agence.',
        'provider'    => [ '@id' => $page_url . '#bakery' ],
        'areaServed'  => [ '@type' => 'Country', 'name' => 'Cameroun' ],
        'url'         => $page_url,
    ];

    $questions = [];
    foreach ( slg_patisserie_faq_items() as $item ) {
        $questions[] = [
            '@type'          => 'Question',
            'name'           => $item['question'],
            'acceptedAnswer' => [ '@type' => 'Answer', 'text' => $item['answer'] ],
        ];
    }
    $data['PatisserieFAQ'] = [ '@type' => 'FAQPage', '@id' => $page_url . '#faq', 'mainEntity' => $questions ];
    $data['PatisserieBreadcrumbs'] = [
        '@type'           => 'BreadcrumbList',
        '@id'             => $page_url . '#breadcrumb',
        'itemListElement' => [
            [ '@type' => 'ListItem', 'position' => 1, 'name' => 'Accueil', 'item' => $site_url ],
            [ '@type' => 'ListItem', 'position' => 2, 'name' => 'Pâtisserie', 'item' => $page_url ],
        ],
    ];
    return $data;
}
add_filter( 'rank_math/json_ld', 'slg_patisserie_schema', 999, 2 );

/** Affiche automatiquement le formulaire sur la page publique Pâtisserie. */
function slg_append_cake_form_to_patisserie( $content ) {
    $elementor_data = (string) get_post_meta( get_queried_object_id(), '_elementor_data', true );
    if ( is_admin() || ! is_page( 'patisserie' ) || false !== strpos( $content, 'data-sl-cake-form' ) || false !== strpos( $elementor_data, 'sl_commande_patisserie' ) || false !== strpos( $content, 'data-sl-cake-auto' ) ) return $content;
    $agencies = function_exists( 'slc_agences' ) ? slc_agences() : get_terms( [ 'taxonomy' => 'sl_agence_promo', 'hide_empty' => false, 'orderby' => 'name' ] );
    if ( is_wp_error( $agencies ) ) $agencies = [];
    ob_start(); ?>
    <style>
        /* Masque uniquement le bandeau héro de la page Pâtisserie : le bandeau promotionnel global reste affiché. */
        .elementor-element-49f18f4b{display:none!important}
        .sl-cake-auto{max-width:1180px;margin:clamp(32px,4vw,56px) auto clamp(42px,7vw,84px);overflow:hidden;border:1px solid #edf0f4;border-radius:28px;background:#fff;box-shadow:0 18px 54px rgba(20,43,79,.13)}
        .sl-cake-layout{display:grid;grid-template-columns:minmax(330px,.82fr) minmax(0,1.18fr)}
        .sl-cake-visual{position:relative;min-height:720px;overflow:hidden;background:#142b4f;color:#fff}
        .sl-cake-visual>img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
        .sl-cake-visual:after{position:absolute;inset:0;content:"";background:linear-gradient(180deg,rgba(11,23,43,.04) 18%,rgba(11,23,43,.84) 100%)}
        .sl-cake-visual-content{position:relative;z-index:1;display:flex;height:100%;min-height:720px;flex-direction:column;justify-content:space-between;padding:clamp(24px,3vw,38px)}
        .sl-cake-kicker{display:inline-flex;align-items:center;gap:8px;font-size:11px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}
        .sl-cake-visual .sl-cake-kicker{width:max-content;padding:9px 12px;border:1px solid rgba(255,255,255,.34);border-radius:999px;background:rgba(20,43,79,.24);backdrop-filter:blur(6px)}
        .sl-cake-promise{max-width:390px;padding:24px;border:1px solid rgba(255,255,255,.38);border-radius:18px;background:rgba(17,37,63,.47);box-shadow:0 10px 30px rgba(0,0,0,.16);backdrop-filter:blur(12px)}
        .sl-cake-promise p{margin:0 0 20px;font-size:clamp(18px,2vw,24px);font-weight:700;line-height:1.35}
        .sl-cake-promise strong,.sl-cake-promise span{display:block}.sl-cake-promise strong{margin-bottom:4px;font-size:15px}.sl-cake-promise span{font-size:13px;opacity:.86}
        .sl-cake-panel{padding:clamp(30px,5vw,64px);background:linear-gradient(140deg,#fff 0%,#fffafd 100%)}
        .sl-cake-head{max-width:620px;margin:0 0 30px}.sl-cake-head .sl-cake-kicker{margin-bottom:12px;color:#e91e63}.sl-cake-head h1{margin:0 0 10px;color:#142b4f;font-size:clamp(29px,3.4vw,44px);letter-spacing:-.03em;line-height:1.08}.sl-cake-head p{margin:0;color:#667085;line-height:1.6}
        .sl-cake-auto form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:17px}.sl-cake-auto label{display:flex;flex-direction:column;gap:8px;color:#142b4f;font-size:13px;font-weight:800}
        .sl-cake-auto input:not([type=hidden]),.sl-cake-auto select,.sl-cake-auto textarea{box-sizing:border-box;width:100%;min-height:48px;padding:12px 14px;border:1px solid #dce1e8;border-radius:10px;background:#fff;color:#172033;font:inherit;transition:border-color .18s,box-shadow .18s}
        .sl-cake-auto input:focus,.sl-cake-auto select:focus,.sl-cake-auto textarea:focus{outline:0;border-color:#e91e63;box-shadow:0 0 0 3px rgba(233,30,99,.12)}.sl-cake-auto textarea{min-height:118px;resize:vertical}.sl-cake-auto .full,.sl-cake-auto .result{grid-column:1/-1}
        .sl-cake-auto button{border:0;border-radius:10px;padding:15px 26px;background:#e91e63;color:#fff;font-size:15px;font-weight:800;cursor:pointer;box-shadow:0 10px 18px rgba(233,30,99,.2);transition:transform .18s,box-shadow .18s}.sl-cake-auto button:hover{transform:translateY(-1px);box-shadow:0 13px 23px rgba(233,30,99,.28)}.sl-cake-auto button:disabled{opacity:.6;cursor:wait;transform:none}.sl-cake-auto .result{padding:14px 16px;border-radius:10px;background:#eaf8f0;color:#17633a}.sl-cake-auto .hp{position:absolute;left:-9999px}
        .sl-cake-seo{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(300px,.85fr);gap:clamp(28px,5vw,62px);padding:clamp(30px,5vw,60px);border-top:1px solid #edf0f4;background:linear-gradient(145deg,#fff 0%,#fff8fb 100%)}.sl-cake-seo h2{margin:0 0 15px;color:#142b4f;font-size:clamp(23px,2.4vw,32px);letter-spacing:-.02em}.sl-cake-seo p{margin:0 0 13px;color:#586274;line-height:1.72}.sl-cake-seo p:last-child{margin-bottom:0}.sl-cake-seo a{color:#d81b60;font-weight:700;text-decoration:underline;text-underline-offset:3px}.sl-cake-faq{align-self:start;padding:24px;border:1px solid #f0dce7;border-radius:18px;background:#fff}.sl-cake-faq h2{font-size:22px}.sl-cake-faq details{border-top:1px solid #edf0f4;padding:13px 0}.sl-cake-faq details:last-child{padding-bottom:0}.sl-cake-faq summary{cursor:pointer;color:#142b4f;font-weight:800;line-height:1.4}.sl-cake-faq details p{padding:9px 20px 0 0;font-size:14px;line-height:1.6}
        @media(max-width:900px){.sl-cake-auto{margin:38px 18px}.sl-cake-layout,.sl-cake-seo{grid-template-columns:1fr}.sl-cake-visual,.sl-cake-visual-content{min-height:330px}.sl-cake-visual-content{padding:26px}.sl-cake-promise{max-width:520px}.sl-cake-panel{padding:34px 28px}}
        @media(max-width:650px){.sl-cake-auto{margin:30px 14px;border-radius:20px}.sl-cake-visual,.sl-cake-visual-content{min-height:280px}.sl-cake-visual-content{box-sizing:border-box;padding:20px 20px 86px}.sl-cake-promise{padding:17px;border-radius:14px}.sl-cake-promise p{margin:0;font-size:17px}.sl-cake-promise strong,.sl-cake-promise span{display:none}.sl-cake-panel,.sl-cake-seo{padding:30px 20px}.sl-cake-auto form{grid-template-columns:1fr}.sl-cake-auto .full,.sl-cake-auto .result{grid-column:auto}.sl-cake-auto button{width:100%}.sl-cake-faq{padding:20px}}
    </style>
    <section class="sl-cake-auto" aria-labelledby="sl-cake-auto-title">
        <div class="sl-cake-layout">
            <aside class="sl-cake-visual" aria-label="Créations de la pâtisserie Santa Lucia">
                <img src="https://complexesantalucia.com/wp-content/uploads/2024/06/arriere-plan-patisserie-complexe-santa-lucia.webp" alt="Assortiment de pâtisseries Santa Lucia" loading="lazy" decoding="async">
                <div class="sl-cake-visual-content"><span class="sl-cake-kicker">Pâtisserie Santa Lucia</span><div class="sl-cake-promise"><p>« Pour chaque célébration, un gâteau imaginé avec vous. »</p><strong>Anniversaire, mariage, baptême…</strong><span>Retrait dans l’agence de votre choix.</span></div></div>
            </aside>
            <div class="sl-cake-panel">
                <div class="sl-cake-head">
                    <span class="sl-cake-kicker">Demande sur mesure</span>
                    <h1 id="sl-cake-auto-title">Gâteaux personnalisés et pâtisserie Santa Lucia</h1>
                    <p>Partagez les grandes lignes de votre projet. Notre équipe pâtisserie vous recontactera pour valider le modèle, le prix et la disponibilité.</p>
                </div>
                <form data-sl-cake-auto>
                    <input type="hidden" name="action" value="sl_submit_cake_request">
                    <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'sl_cake_request' ) ); ?>">
                    <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off">
                    <label>Nom complet *<input name="nom" required autocomplete="name"></label>
                    <label>Téléphone *<input name="telephone" required type="tel" autocomplete="tel"></label>
                    <label>Occasion *<select name="type" required><option value="">Choisir…</option><option>Anniversaire</option><option>Mariage</option><option>Baptême</option><option>Communion</option><option>Événement professionnel</option><option>Autre</option></select></label>
                    <label>Date souhaitée *<input name="date" required type="date" min="<?php echo esc_attr( date( 'Y-m-d', current_time( 'timestamp' ) + DAY_IN_SECONDS ) ); ?>"></label>
                    <label>Agence de retrait *<select name="agence" required><option value="">Choisir une agence…</option><?php foreach ( $agencies as $agency ) : ?><option value="<?php echo esc_attr( $agency->name ); ?>"><?php echo esc_html( $agency->name ); ?></option><?php endforeach; ?></select></label>
                    <label>Nombre de parts<input name="quantite" type="number" min="1" max="500" placeholder="Ex. 20"></label>
                    <label>Saveur / parfum<input name="saveur" placeholder="Ex. chocolat, vanille…"></label>
                    <label>Budget indicatif (FCFA)<input name="budget" inputmode="numeric" placeholder="Ex. 25 000"></label>
                    <label>E-mail<input name="email" type="email" autocomplete="email"></label>
                    <label class="full">Détails du gâteau<textarea name="message" placeholder="Couleurs, inscription, décoration, photo de référence…"></textarea></label>
                    <div class="full"><button type="submit">Envoyer ma demande</button></div>
                    <div class="result" hidden role="status"></div>
                </form>
            </div>
        </div>
        <div class="sl-cake-seo">
            <section class="sl-cake-guide" aria-labelledby="sl-cake-guide-title">
                <span class="sl-cake-kicker">Pâtisserie sur mesure</span>
                <h2 id="sl-cake-guide-title">Un gâteau sur mesure pour votre événement</h2>
                <p>Anniversaire, mariage, baptême, communion ou événement professionnel : la Pâtisserie Santa Lucia vous aide à préparer un gâteau adapté à votre célébration. Indiquez l’occasion, la date souhaitée, le nombre de parts, la saveur, le budget et vos idées de décoration.</p>
                <p>Après réception de votre demande, notre équipe vérifie la disponibilité et vous contacte pour confirmer le modèle, le prix et l’agence de retrait. Vous pouvez choisir l’agence qui vous convient parmi <a href="<?php echo esc_url( home_url( '/nos-agences/' ) ); ?>">nos agences Santa Lucia</a>.</p>
            </section>
            <section class="sl-cake-faq" id="sl-cake-faq" aria-labelledby="sl-cake-faq-title">
                <h2 id="sl-cake-faq-title">Questions fréquentes</h2>
                <?php foreach ( slg_patisserie_faq_items() as $item ) : ?>
                    <details>
                        <summary><?php echo esc_html( $item['question'] ); ?></summary>
                        <p><?php echo esc_html( $item['answer'] ); ?></p>
                    </details>
                <?php endforeach; ?>
            </section>
        </div>
    </section>
    <script>(function(){document.querySelectorAll('[data-sl-cake-auto]').forEach(function(f){f.addEventListener('submit',function(e){e.preventDefault();var b=f.querySelector('button'),o=f.querySelector('.result');b.disabled=true;var d=new FormData(f);fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',{method:'POST',body:d,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(j){if(!j.success)throw new Error(j.data&&j.data.message?j.data.message:'Une erreur est survenue.');o.textContent=j.data.message;o.hidden=false;f.reset()}).catch(function(e){o.textContent=e.message;o.style.background='#fff1f1';o.style.color='#9c1c1c';o.hidden=false}).finally(function(){b.disabled=false})})})})();</script>
    <?php return $content . ob_get_clean();
}
add_filter( 'the_content', 'slg_append_cake_form_to_patisserie', 30 );
add_filter( 'elementor/frontend/the_content', 'slg_append_cake_form_to_patisserie', 30 );

add_action( 'elementor/widgets/register', function ( $widgets_manager ) {
    if ( ! class_exists( '\Elementor\Widget_Base' ) || class_exists( 'SL_Commande_Patisserie_Widget' ) ) return;
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
    $widgets_manager->register( new SL_Commande_Patisserie_Widget() );
} );
