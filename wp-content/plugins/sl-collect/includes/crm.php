<?php
/**
 * CRM clients — collecte, fiche, anniversaires, export.
 *
 * Les comptes existent deja (checkout sans invite) : noms, emails, telephones
 * sont dans WooCommerce. Ce module ajoute ce qui manque pour le marketing :
 *  - date d'anniversaire (checkout + Mon compte, FACULTATIVE),
 *  - CONSENTEMENT explicite aux offres (opt-in decoche par defaut, date de
 *    consentement conservee, lien de desinscription dans chaque envoi),
 *  - ecran « Clients » (admins + editeurs) avec export CSV,
 *  - email d'anniversaire automatique aux clients consentants.
 *
 * Metas utilisateur : _slc_birthday (Y-m-d), _slc_marketing_optin ('1'/''),
 * _slc_optin_date, _slc_bday_sent_{annee}.
 *
 * @package SL_Collect
 */

defined( 'ABSPATH' ) || exit;

/* ============================================================
   1. COLLECTE — checkout + Mon compte
   ============================================================ */

add_filter( 'woocommerce_checkout_fields', 'slc_crm_checkout_fields', 30 );
function slc_crm_checkout_fields( $fields ) {
    $uid = get_current_user_id();

    $fields['billing']['slc_birthday'] = [
        'type'     => 'date',
        'label'    => 'Date d\'anniversaire',
        'required' => false,
        'priority' => 130,
        'default'  => $uid ? (string) get_user_meta( $uid, '_slc_birthday', true ) : '',
        'description' => 'Facultatif — pour recevoir une attention le jour J.',
    ];
    $fields['billing']['slc_optin'] = [
        'type'     => 'checkbox',
        'label'    => 'Je souhaite recevoir les offres et promotions de Santa Lucia',
        'required' => false,
        'priority' => 131,
        'default'  => $uid ? ( get_user_meta( $uid, '_slc_marketing_optin', true ) === '1' ? 1 : 0 ) : 0,
    ];
    return $fields;
}

/** Enregistrement au checkout, via l'objet client (CRUD propre). */
add_action( 'woocommerce_checkout_update_customer', 'slc_crm_save_from_checkout', 10, 2 );
function slc_crm_save_from_checkout( $customer, $data ) {
    if ( isset( $_POST['slc_birthday'] ) ) {
        $customer->update_meta_data( '_slc_birthday', slc_crm_sanitize_birthday( wp_unslash( $_POST['slc_birthday'] ) ) );
    }
    $optin_avant = $customer->get_meta( '_slc_marketing_optin' );
    $optin       = ! empty( $_POST['slc_optin'] ) ? '1' : '';
    $customer->update_meta_data( '_slc_marketing_optin', $optin );
    // La date de consentement est une preuve : posee au passage a « oui »,
    // jamais reecrite tant que le consentement ne change pas.
    if ( '1' === $optin && '1' !== $optin_avant ) {
        $customer->update_meta_data( '_slc_optin_date', current_time( 'mysql' ) );
    }
}

/**
 * L'agence du client = la derniere agence de retrait utilisee.
 * Capturee sur l'ORDRE (apres sl_bp_force_order_agency, qui peut corriger le
 * champ soumis) et non sur le POST : c'est la valeur qui fait foi. Le client
 * peut ensuite la changer dans Mon compte.
 */
add_action( 'woocommerce_checkout_order_processed', 'slc_crm_capture_agence', 20, 1 );
function slc_crm_capture_agence( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order || ! $order->get_customer_id() ) {
        return;
    }
    $slug = (string) $order->get_meta( '_sl_collect_agence' );
    if ( $slug !== '' ) {
        update_user_meta( $order->get_customer_id(), '_slc_agence', $slug );
    }
}

/** Y-m-d valide (année plausible), sinon ''. */
function slc_crm_sanitize_birthday( $raw ) {
    $raw = sanitize_text_field( (string) $raw );
    if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m ) ) {
        return '';
    }
    if ( ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
        return '';
    }
    $annee = (int) $m[1];
    $cette_annee = (int) date( 'Y' );
    return ( $annee >= 1900 && $annee <= $cette_annee ) ? $raw : '';
}

/** Mon compte : les memes champs, modifiables a tout moment. */
add_action( 'woocommerce_edit_account_form', 'slc_crm_account_form' );
function slc_crm_account_form() {
    $uid    = get_current_user_id();
    $bday   = (string) get_user_meta( $uid, '_slc_birthday', true );
    $optin  = get_user_meta( $uid, '_slc_marketing_optin', true ) === '1';
    $agence = (string) get_user_meta( $uid, '_slc_agence', true );
    ?>
    <fieldset style="margin-top:18px;">
        <legend>Offres &amp; anniversaire</legend>
        <p class="woocommerce-form-row form-row">
            <label for="slc_agence">Mon agence habituelle</label>
            <select name="slc_agence" id="slc_agence" class="woocommerce-Input">
                <option value="">— Choisir —</option>
                <?php if ( function_exists( 'slc_agences' ) ) : foreach ( slc_agences() as $t ) : ?>
                    <option value="<?php echo esc_attr( $t->slug ); ?>" <?php selected( $agence, $t->slug ); ?>><?php echo esc_html( $t->name ); ?></option>
                <?php endforeach; endif; ?>
            </select>
        </p>
        <p class="woocommerce-form-row form-row">
            <label for="slc_birthday">Date d'anniversaire (facultatif)</label>
            <input type="date" name="slc_birthday" id="slc_birthday" class="woocommerce-Input input-text"
                   value="<?php echo esc_attr( $bday ); ?>">
        </p>
        <p class="woocommerce-form-row form-row">
            <label style="display:flex;gap:8px;align-items:flex-start;">
                <input type="checkbox" name="slc_optin" value="1" <?php checked( $optin ); ?> style="margin-top:4px;">
                <span>Je souhaite recevoir les offres et promotions de Santa Lucia.</span>
            </label>
        </p>
    </fieldset>
    <?php
}

add_action( 'woocommerce_save_account_details', 'slc_crm_account_save' );
function slc_crm_account_save( $uid ) {
    if ( isset( $_POST['slc_birthday'] ) ) {
        update_user_meta( $uid, '_slc_birthday', slc_crm_sanitize_birthday( wp_unslash( $_POST['slc_birthday'] ) ) );
    }
    if ( isset( $_POST['slc_agence'] ) ) {
        $slug = sanitize_title( wp_unslash( $_POST['slc_agence'] ) );
        // N'accepter qu'une agence reelle : cette meta alimente le filtre CRM.
        if ( $slug === '' || get_term_by( 'slug', $slug, 'sl_agence_promo' ) ) {
            update_user_meta( $uid, '_slc_agence', $slug );
        }
    }
    $avant = get_user_meta( $uid, '_slc_marketing_optin', true );
    $optin = ! empty( $_POST['slc_optin'] ) ? '1' : '';
    update_user_meta( $uid, '_slc_marketing_optin', $optin );
    if ( '1' === $optin && '1' !== $avant ) {
        update_user_meta( $uid, '_slc_optin_date', current_time( 'mysql' ) );
    }
}

/* ============================================================
   2. DESINSCRIPTION EN UN CLIC (lien dans chaque envoi)
   ============================================================ */

function slc_crm_unsub_token( $uid ) {
    return substr( hash_hmac( 'sha256', 'slc_unsub' . (int) $uid, wp_salt( 'auth' ) ), 0, 16 );
}

function slc_crm_unsub_url( $uid ) {
    return add_query_arg( [ 'slc_unsub' => (int) $uid, 't' => slc_crm_unsub_token( $uid ) ], home_url( '/' ) );
}

add_action( 'template_redirect', 'slc_crm_unsub_route' );
function slc_crm_unsub_route() {
    if ( empty( $_GET['slc_unsub'] ) ) {
        return;
    }
    nocache_headers(); // ce Varnish cache les 404/pages par URL
    $uid = absint( $_GET['slc_unsub'] );
    $tok = isset( $_GET['t'] ) ? sanitize_text_field( wp_unslash( $_GET['t'] ) ) : '';
    if ( ! $uid || ! hash_equals( slc_crm_unsub_token( $uid ), $tok ) ) {
        wp_die( 'Lien de désinscription invalide.', 'Désinscription', [ 'response' => 403 ] );
    }
    update_user_meta( $uid, '_slc_marketing_optin', '' );
    wp_die(
        '<h1>C\'est fait ✔</h1><p>Vous ne recevrez plus nos offres et promotions. Vous pouvez vous réinscrire à tout moment depuis votre compte sur ' . esc_html( home_url() ) . '.</p>',
        'Désinscription',
        [ 'response' => 200 ]
    );
}

/* ============================================================
   3. ANNIVERSAIRES — via le cron horaire existant (sl_collect_cron)
   ============================================================ */

add_action( 'sl_collect_cron', 'slc_crm_birthday_run', 20 );
function slc_crm_birthday_run() {
    // Une exécution par jour, pas avant 8 h (heure du site) : le cron est
    // horaire, on se cale dessus plutôt que de créer un énième planning.
    $now = current_time( 'timestamp' );
    if ( (int) date( 'G', $now ) < 8 ) {
        return;
    }
    $today = date( 'Y-m-d', $now );
    if ( get_option( 'slc_bday_done' ) === $today ) {
        return;
    }
    update_option( 'slc_bday_done', $today, false );

    $mmjj  = date( 'm-d', $now );
    $annee = date( 'Y', $now );

    $users = get_users( [
        'meta_query' => [
            [ 'key' => '_slc_marketing_optin', 'value' => '1' ],
            [ 'key' => '_slc_birthday', 'value' => '-' . $mmjj, 'compare' => 'LIKE' ],
        ],
        'fields' => [ 'ID', 'user_email', 'display_name' ],
        'number' => 200,
    ] );

    foreach ( $users as $u ) {
        // Garde annuelle : jamais deux envois la meme annee (rejeu de cron,
        // changement de date par le client, etc.).
        if ( get_user_meta( $u->ID, '_slc_bday_sent_' . $annee, true ) ) {
            continue;
        }
        update_user_meta( $u->ID, '_slc_bday_sent_' . $annee, current_time( 'mysql' ) );

        $prenom = get_user_meta( $u->ID, 'first_name', true ) ?: $u->display_name;
        $sujet  = str_replace( '{prenom}', $prenom, get_option( 'slc_bday_subject', 'Joyeux anniversaire {prenom} ! 🎂' ) );
        $corps  = str_replace(
            [ '{prenom}', '{lien}' ],
            [ $prenom, home_url( '/bon-plans/' ) ],
            get_option( 'slc_bday_body', "Toute l'équipe du Complexe Santa Lucia vous souhaite un très joyeux anniversaire !\n\nPour marquer le jour, nos meilleures offres vous attendent :\n{lien}\n\nÀ très vite dans votre agence." )
        );
        $corps .= "\n\n—\nVous recevez ce message car vous avez accepté nos offres.\nSe désinscrire : " . slc_crm_unsub_url( $u->ID );

        wp_mail( $u->user_email, $sujet, $corps );
        if ( function_exists( 'slst_log' ) ) {
            slst_log( 'bday', [ 'product_id' => $u->ID ] );
        }
    }
}

/* ============================================================
   4. ECRAN CLIENTS (admins + editeurs)
   ============================================================ */

add_action( 'admin_menu', 'slc_crm_menu', 1000 ); // le parent sl-collect est a 999
function slc_crm_menu() {
    add_submenu_page(
        'sl-collect',
        'Clients',
        '👥 Clients',
        'edit_others_posts',
        'sl-clients',
        'slc_crm_render_page'
    );
}

/** Requete clients selon les filtres de l'ecran. */
function slc_crm_query( $filtre, $recherche, $paged, $per_page = 30, $agence = '' ) {
    $args = [
        'role__in' => [ 'customer', 'subscriber' ],
        'number'   => $per_page,
        'paged'    => $paged,
        'orderby'  => 'registered',
        'order'    => 'DESC',
        'count_total' => true,
    ];

    if ( 'optin' === $filtre ) {
        $args['meta_query'] = [ [ 'key' => '_slc_marketing_optin', 'value' => '1' ] ];
    } elseif ( 'anniv' === $filtre ) {
        $args['meta_query'] = [ [ 'key' => '_slc_birthday', 'value' => '-' . current_time( 'm' ) . '-', 'compare' => 'LIKE' ] ];
    }

    // Filtre par agence (cumulable avec le filtre principal, relation AND) :
    // c'est lui qui permet « tous les clients de Mokolo » pour une offre ciblee.
    if ( $agence !== '' ) {
        $args['meta_query']   = $args['meta_query'] ?? [];
        $args['meta_query'][] = [ 'key' => '_slc_agence', 'value' => $agence ];
    }

    if ( $recherche !== '' ) {
        $digits = preg_replace( '/\D+/', '', $recherche );
        if ( strlen( $digits ) >= 6 ) {
            // Recherche telephone : sur la meta billing_phone.
            $args['meta_query'] = array_merge( $args['meta_query'] ?? [], [
                [ 'key' => 'billing_phone', 'value' => $digits, 'compare' => 'LIKE' ],
            ] );
        } else {
            $args['search']         = '*' . $recherche . '*';
            $args['search_columns'] = [ 'user_login', 'user_email', 'display_name' ];
        }
    }

    return new WP_User_Query( $args );
}

/** Donnees d'une ligne client (quelques requetes par ligne : page a 30, admin only). */
function slc_crm_row( $uid ) {
    $last   = wc_get_orders( [ 'customer_id' => $uid, 'limit' => 1, 'return' => 'objects' ] );
    $agence = (string) get_user_meta( $uid, '_slc_agence', true );
    // Rattrapage des clients d'avant la meta : on derive de la derniere
    // commande UNE fois et on enregistre — le filtre par agence les voit
    // ensuite, et l'ecran s'auto-repare au fil des consultations.
    if ( $agence === '' && $last ) {
        $agence = (string) $last[0]->get_meta( '_sl_collect_agence' );
        if ( $agence !== '' ) {
            update_user_meta( $uid, '_slc_agence', $agence );
        }
    }
    return [
        'tel'      => get_user_meta( $uid, 'billing_phone', true ),
        'bday'     => (string) get_user_meta( $uid, '_slc_birthday', true ),
        'optin'    => get_user_meta( $uid, '_slc_marketing_optin', true ) === '1',
        'commandes'=> (int) wc_get_customer_order_count( $uid ),
        'total'    => (float) wc_get_customer_total_spent( $uid ),
        'agence'   => $agence,
        'derniere' => $last ? $last[0]->get_date_created()->date_i18n( 'd/m/Y' ) : '',
    ];
}

function slc_crm_render_page() {
    if ( ! current_user_can( 'edit_others_posts' ) && ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Accès refusé.' );
    }

    $filtre    = isset( $_GET['filtre'] ) ? sanitize_key( $_GET['filtre'] ) : '';
    $recherche = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
    $agence    = isset( $_GET['ag'] ) ? sanitize_title( wp_unslash( $_GET['ag'] ) ) : '';
    $paged     = isset( $_GET['pg'] ) ? max( 1, (int) $_GET['pg'] ) : 1;

    $q     = slc_crm_query( $filtre, $recherche, $paged, 30, $agence );
    $total = (int) $q->get_total();
    $pages = max( 1, (int) ceil( $total / 30 ) );

    // Compteurs d'entete (une requete legere chacun, ids seulement)
    $nb_optin = count( get_users( [ 'role__in' => [ 'customer', 'subscriber' ], 'meta_key' => '_slc_marketing_optin', 'meta_value' => '1', 'fields' => 'ID' ] ) );
    $nb_anniv = count( get_users( [ 'role__in' => [ 'customer', 'subscriber' ], 'meta_query' => [ [ 'key' => '_slc_birthday', 'value' => '-' . current_time( 'm' ) . '-', 'compare' => 'LIKE' ] ], 'fields' => 'ID' ] ) );

    $export_url = wp_nonce_url( admin_url( 'admin-post.php?action=slc_crm_export&filtre=' . rawurlencode( $filtre ) . '&ag=' . rawurlencode( $agence ) ), 'slc_crm_export' );
    $base_url   = admin_url( 'admin.php?page=sl-clients' );
    ?>
    <div class="wrap">
        <h1>👥 Clients</h1>
        <p class="description" style="max-width:740px;">
            Données personnelles : réservées à la relation client Santa Lucia. Les offres ne partent
            <strong>qu'aux clients ayant coché le consentement</strong> ; chaque envoi contient un lien de désinscription.
        </p>

        <div style="display:flex;gap:12px;flex-wrap:wrap;margin:14px 0;">
            <div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:12px 18px;"><small>Clients</small><br><b style="font-size:20px;color:#1d54a0;"><?php echo (int) $total; ?></b></div>
            <div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:12px 18px;"><small>Consentement offres</small><br><b style="font-size:20px;color:#1d54a0;"><?php echo (int) $nb_optin; ?></b></div>
            <div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:12px 18px;"><small>Anniversaires ce mois</small><br><b style="font-size:20px;color:#e91e63;"><?php echo (int) $nb_anniv; ?></b></div>
        </div>

        <form method="get" style="margin:12px 0;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
            <input type="hidden" name="page" value="sl-clients">
            <label>Filtre<br>
                <select name="filtre" onchange="this.form.submit()">
                    <option value="" <?php selected( $filtre, '' ); ?>>Tous les clients</option>
                    <option value="optin" <?php selected( $filtre, 'optin' ); ?>>Consentants aux offres</option>
                    <option value="anniv" <?php selected( $filtre, 'anniv' ); ?>>Anniversaire ce mois-ci</option>
                </select>
            </label>
            <label>Agence<br>
                <select name="ag" onchange="this.form.submit()">
                    <option value="">Toutes les agences</option>
                    <?php if ( function_exists( 'slc_agences' ) ) : foreach ( slc_agences() as $t ) : ?>
                        <option value="<?php echo esc_attr( $t->slug ); ?>" <?php selected( $agence, $t->slug ); ?>><?php echo esc_html( $t->name ); ?></option>
                    <?php endforeach; endif; ?>
                </select>
            </label>
            <label>Recherche (nom / email / téléphone)<br>
                <input type="search" name="q" value="<?php echo esc_attr( $recherche ); ?>" style="min-width:240px;">
            </label>
            <button class="button button-primary">Filtrer</button>
            <a class="button" href="<?php echo esc_url( $base_url ); ?>">Réinitialiser</a>
            <a class="button" href="<?php echo esc_url( $export_url ); ?>">⬇ Exporter (CSV)</a>
        </form>

        <?php if ( ! $q->get_results() ) : ?>
            <p><em>Aucun client pour ces critères.</em></p>
        <?php else : ?>
        <table class="widefat striped">
            <thead><tr>
                <th>Client</th><th>Email</th><th>Téléphone</th><th>Anniversaire</th>
                <th>Offres</th><th>Commandes</th><th>Total</th><th>Agence habituelle</th><th>Dernière cmd</th>
            </tr></thead>
            <tbody>
            <?php foreach ( $q->get_results() as $u ) :
                $r = slc_crm_row( $u->ID ); ?>
                <tr>
                    <td><strong><a href="<?php echo esc_url( get_edit_user_link( $u->ID ) ); ?>"><?php echo esc_html( $u->display_name ); ?></a></strong></td>
                    <td><?php echo esc_html( $u->user_email ); ?></td>
                    <td><?php echo $r['tel'] ? '<a href="tel:' . esc_attr( $r['tel'] ) . '">' . esc_html( $r['tel'] ) . '</a>' : '—'; ?></td>
                    <td><?php echo $r['bday'] ? esc_html( mysql2date( 'd/m', $r['bday'] . ' 00:00:00' ) ) : '<span style="color:#8c8f94;">—</span>'; ?></td>
                    <td><?php echo $r['optin'] ? '<span style="color:#1e7b34;font-weight:600;">✔ oui</span>' : '<span style="color:#8c8f94;">non</span>'; ?></td>
                    <td><?php echo (int) $r['commandes']; ?></td>
                    <td><?php echo number_format( $r['total'], 0, ',', ' ' ); ?> F</td>
                    <td><?php echo $r['agence'] ? esc_html( function_exists( 'slc_agence_name' ) ? slc_agence_name( $r['agence'] ) : $r['agence'] ) : '—'; ?></td>
                    <td><?php echo esc_html( $r['derniere'] ?: '—' ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ( $pages > 1 ) : ?>
            <p style="margin-top:12px;">
                Page <?php echo (int) $paged; ?> / <?php echo (int) $pages; ?>
                <?php if ( $paged > 1 ) : ?>
                    <a class="button" href="<?php echo esc_url( add_query_arg( [ 'filtre' => $filtre, 'q' => $recherche, 'ag' => $agence, 'pg' => $paged - 1 ], $base_url ) ); ?>">← Précédent</a>
                <?php endif; ?>
                <?php if ( $paged < $pages ) : ?>
                    <a class="button" href="<?php echo esc_url( add_query_arg( [ 'filtre' => $filtre, 'q' => $recherche, 'ag' => $agence, 'pg' => $paged + 1 ], $base_url ) ); ?>">Suivant →</a>
                <?php endif; ?>
            </p>
        <?php endif; ?>
        <?php endif; ?>

        <h2 style="margin-top:30px;">🎂 Message d'anniversaire automatique</h2>
        <p class="description" style="max-width:740px;">
            Envoyé chaque matin (à partir de 8 h) aux clients consentants dont c'est l'anniversaire.
            Variables : <code>{prenom}</code>, <code>{lien}</code> (page des bons plans). Un seul envoi par client et par an.
        </p>
        <?php
        if ( isset( $_POST['slc_bday_save'] ) && check_admin_referer( 'slc_bday_settings' ) ) {
            update_option( 'slc_bday_subject', sanitize_text_field( wp_unslash( $_POST['slc_bday_subject'] ?? '' ) ) );
            update_option( 'slc_bday_body', sanitize_textarea_field( wp_unslash( $_POST['slc_bday_body'] ?? '' ) ) );
            echo '<div class="notice notice-success inline"><p>Message enregistré.</p></div>';
        }
        ?>
        <form method="post" style="max-width:640px;">
            <?php wp_nonce_field( 'slc_bday_settings' ); ?>
            <p><label><strong>Sujet</strong><br>
                <input type="text" name="slc_bday_subject" class="regular-text" style="width:100%;"
                       value="<?php echo esc_attr( get_option( 'slc_bday_subject', 'Joyeux anniversaire {prenom} ! 🎂' ) ); ?>">
            </label></p>
            <p><label><strong>Message</strong><br>
                <textarea name="slc_bday_body" rows="6" style="width:100%;"><?php
                    echo esc_textarea( get_option( 'slc_bday_body', "Toute l'équipe du Complexe Santa Lucia vous souhaite un très joyeux anniversaire !\n\nPour marquer le jour, nos meilleures offres vous attendent :\n{lien}\n\nÀ très vite dans votre agence." ) );
                ?></textarea>
            </label></p>
            <p><button class="button button-primary" name="slc_bday_save" value="1">Enregistrer le message</button></p>
        </form>
    </div>
    <?php
}

/* ============================================================
   5. EXPORT CSV (nonce + capacite ; BOM pour Excel)
   ============================================================ */

add_action( 'admin_post_slc_crm_export', 'slc_crm_export' );
function slc_crm_export() {
    if ( ! current_user_can( 'edit_others_posts' ) && ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Accès refusé.' );
    }
    check_admin_referer( 'slc_crm_export' );

    $filtre = isset( $_GET['filtre'] ) ? sanitize_key( $_GET['filtre'] ) : '';
    $ag     = isset( $_GET['ag'] ) ? sanitize_title( wp_unslash( $_GET['ag'] ) ) : '';
    $q      = slc_crm_query( $filtre, '', 1, 5000, $ag );

    // Regle deja apprise sur ce site : vider les buffers avant tout download
    // (un fichier de la pile emet un BOM parasite qui corrompt le binaire).
    while ( ob_get_level() > 0 ) {
        @ob_end_clean();
    }
    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="clients-santa-lucia-' . date( 'Ymd' ) . '.csv"' );

    $out = fopen( 'php://output', 'w' );
    fwrite( $out, "\xEF\xBB\xBF" ); // BOM voulu ici : Excel ouvre l'UTF-8 correctement
    fputcsv( $out, [ 'Nom', 'Email', 'Telephone', 'Anniversaire', 'Consentement offres', 'Date consentement', 'Commandes', 'Total FCFA', 'Agence habituelle' ], ';' );

    foreach ( $q->get_results() as $u ) {
        $r = slc_crm_row( $u->ID );
        fputcsv( $out, [
            $u->display_name,
            $u->user_email,
            $r['tel'],
            $r['bday'],
            $r['optin'] ? 'oui' : 'non',
            (string) get_user_meta( $u->ID, '_slc_optin_date', true ),
            $r['commandes'],
            (int) $r['total'],
            $r['agence'] ? ( function_exists( 'slc_agence_name' ) ? slc_agence_name( $r['agence'] ) : $r['agence'] ) : '',
        ], ';' );
    }
    fclose( $out );
    exit;
}
