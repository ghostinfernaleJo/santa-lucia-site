<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   HELPERS GLOBAUX (utilisés par shortcode + admin)
   ============================================================ */

/**
 * Retourne le slug du jour courant en français (ex: "lundi").
 */
function sl_ff_today_jour() {
    $map = [
        'Sunday'    => 'dimanche',
        'Monday'    => 'lundi',
        'Tuesday'   => 'mardi',
        'Wednesday' => 'mercredi',
        'Thursday'  => 'jeudi',
        'Friday'    => 'vendredi',
        'Saturday'  => 'samedi',
    ];
    return $map[ date( 'l', current_time( 'timestamp' ) ) ] ?? 'lundi';
}

/**
 * Normalise l'affichage d'un nom d'agence (ucwords + minuscule).
 */
function sl_ff_agency_name( $name ) {
    return ucwords( mb_strtolower( trim( $name ), 'UTF-8' ) );
}

/**
 * Normalise un texte pour la recherche/filtre admin : minuscules, sans
 * accents, espaces compactés. Sert aux attributs data-* du planning.
 */
function sl_ff_norm_txt( $s ) {
    $s = mb_strtolower( trim( (string) $s ), 'UTF-8' );
    $tr = @iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $s );
    if ( $tr !== false ) {
        $s = strtolower( $tr );
    }
    return preg_replace( '/\s+/', ' ', $s );
}

/**
 * Retourne les variantes possibles d'une agence pour les anciens repas
 * enregistres avec le nom ("Ahala") au lieu du slug ("ahala").
 */
function sl_ff_agency_meta_values( $agence ) {
    $agence = trim( (string) $agence );
    if ( $agence === '' ) {
        return [];
    }

    $values = [ $agence, sanitize_title( $agence ), sl_ff_agency_name( $agence ) ];
    $term = get_term_by( 'slug', sanitize_title( $agence ), 'sl_agence_promo' );
    if ( ! $term || is_wp_error( $term ) ) {
        $term = get_term_by( 'name', $agence, 'sl_agence_promo' );
    }

    if ( $term && ! is_wp_error( $term ) ) {
        $values[] = $term->slug;
        $values[] = $term->name;
        $values[] = sl_ff_agency_name( $term->name );
    }

    return array_values( array_unique( array_filter( $values, 'strlen' ) ) );
}

function sl_ff_agency_meta_query( $agence ) {
    $values = sl_ff_agency_meta_values( $agence );
    if ( empty( $values ) ) {
        return [ 'key' => '_sl_ff_agence', 'value' => '__missing__' ];
    }

    return [
        'key'     => '_sl_ff_agence',
        'value'   => $values,
        'compare' => 'IN',
    ];
}

function sl_ff_day_meta_query( $jour ) {
    $jour = sanitize_text_field( (string) $jour );
    return [
        'relation' => 'OR',
        [
            'key'     => '_sl_ff_jours',
            'value'   => '"' . $jour . '"',
            'compare' => 'LIKE',
        ],
        [
            'key'     => '_sl_ff_jours',
            'value'   => $jour,
            'compare' => '=',
        ],
        [
            'key'     => '_sl_ff_jours',
            'value'   => $jour,
            'compare' => 'LIKE',
        ],
    ];
}

function sl_ff_normalize_jours( $jours ) {
    $jours_valides = [ 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche' ];
    return array_values( array_intersect( (array) $jours, $jours_valides ) );
}

/**
 * Retourne le planning d'un repas pour une agence precise.
 * Repli sur l'ancien champ global `_sl_ff_jours` pour les donnees existantes.
 */
function sl_ff_get_agence_jours( $post_id, $agence = '' ) {
    $agence = sanitize_title( $agence );
    $by_agence = get_post_meta( $post_id, '_sl_ff_jours_by_agence', true );

    if ( $agence && is_array( $by_agence ) && array_key_exists( $agence, $by_agence ) ) {
        return sl_ff_normalize_jours( $by_agence[ $agence ] );
    }

    if ( $agence && ! in_array( $agence, sl_ff_post_agence_slugs( $post_id ), true ) ) {
        return [];
    }

    return sl_ff_normalize_jours( get_post_meta( $post_id, '_sl_ff_jours', true ) );
}

function sl_ff_set_agence_jours( $post_id, $agence, $jours ) {
    $agence = sanitize_title( $agence );
    $jours  = sl_ff_normalize_jours( $jours );

    if ( $agence === '' ) {
        update_post_meta( $post_id, '_sl_ff_jours', $jours );
        return;
    }

    $by_agence = get_post_meta( $post_id, '_sl_ff_jours_by_agence', true );
    $by_agence = is_array( $by_agence ) ? $by_agence : [];

    // Journal de désactivation : passage de « au moins un jour » à « aucun jour »
    // pour cette agence = le repas cesse d'être proposé. On mémorise la date
    // (le monitoring ne peut pas la deviner autrement — aucune donnée native).
    $avant = isset( $by_agence[ $agence ] ) ? sl_ff_normalize_jours( $by_agence[ $agence ] ) : [];
    if ( ! empty( $avant ) && empty( $jours ) && function_exists( 'sl_ff_log_deactivation' ) ) {
        sl_ff_log_deactivation( $agence, $post_id );
    }

    $by_agence[ $agence ] = $jours;
    update_post_meta( $post_id, '_sl_ff_jours_by_agence', $by_agence );

    $agences = array_values( array_filter( (array) get_post_meta( $post_id, '_sl_ff_agence' ) ) );
    if ( count( array_unique( $agences ) ) <= 1 ) {
        update_post_meta( $post_id, '_sl_ff_jours', $jours );
    }
}

/* ============================================================
   PRIX & PROMOS PAR AGENCE
   Meme modele que les jours : une meta serialisee
   `_sl_ff_promo_by_agence` = [ slug => [prix, promo_pct, prix_promo,
   debut, fin] ]. Repli sur les anciennes metas globales pour les
   donnees existantes et les agences pas encore configurees.
   ============================================================ */

/** Lit le prix/promo d'un repas pour une agence (repli global). */
function sl_ff_get_agence_prix( $post_id, $agence = '' ) {
    $agence = sanitize_title( $agence );
    $by     = get_post_meta( $post_id, '_sl_ff_promo_by_agence', true );

    if ( $agence !== '' && is_array( $by ) && isset( $by[ $agence ] ) && is_array( $by[ $agence ] ) ) {
        $d = $by[ $agence ];
        return [
            'prix'       => (int) ( $d['prix']       ?? 0 ),
            'promo_pct'  => (int) ( $d['promo_pct']  ?? 0 ),
            'prix_promo' => (int) ( $d['prix_promo'] ?? 0 ),
            'debut'      => (string) ( $d['debut']   ?? '' ),
            'fin'        => (string) ( $d['fin']     ?? '' ),
        ];
    }

    // Repli : metas globales (donnees historiques / agence non configuree)
    return [
        'prix'       => (int) get_post_meta( $post_id, '_sl_ff_prix',        true ),
        'promo_pct'  => (int) get_post_meta( $post_id, '_sl_ff_promo_prix',  true ),
        'prix_promo' => (int) get_post_meta( $post_id, '_sl_ff_prix_promo',  true ),
        'debut'      => (string) get_post_meta( $post_id, '_sl_ff_promo_debut', true ),
        'fin'        => (string) get_post_meta( $post_id, '_sl_ff_promo_fin',   true ),
    ];
}

/** Ecrit le prix/promo d'un repas pour une agence. */
function sl_ff_set_agence_prix( $post_id, $agence, $data ) {
    $agence = sanitize_title( $agence );
    $clean  = [
        'prix'       => max( 0, (int) ( $data['prix']       ?? 0 ) ),
        'promo_pct'  => max( 0, (int) ( $data['promo_pct']  ?? 0 ) ),
        'prix_promo' => max( 0, (int) ( $data['prix_promo'] ?? 0 ) ),
        'debut'      => sanitize_text_field( (string) ( $data['debut'] ?? '' ) ),
        'fin'        => sanitize_text_field( (string) ( $data['fin']   ?? '' ) ),
    ];

    if ( $agence === '' ) {
        sl_ff_write_global_prix( $post_id, $clean );
        return;
    }

    $by = get_post_meta( $post_id, '_sl_ff_promo_by_agence', true );
    $by = is_array( $by ) ? $by : [];
    $by[ $agence ] = $clean;
    update_post_meta( $post_id, '_sl_ff_promo_by_agence', $by );

    // Repas mono-agence : refleter aussi dans les metas globales (compat lecture
    // directe : API mobile historique, colonne prix admin, anciens lecteurs).
    $agences = array_values( array_unique( array_filter( (array) get_post_meta( $post_id, '_sl_ff_agence' ) ) ) );
    if ( count( $agences ) <= 1 ) {
        sl_ff_write_global_prix( $post_id, $clean );
    }
}

/** Ecrit un jeu prix/promo dans les anciennes metas globales. */
function sl_ff_write_global_prix( $post_id, $c ) {
    if ( $c['prix'] > 0 ) {
        update_post_meta( $post_id, '_sl_ff_prix', $c['prix'] );
    } else {
        delete_post_meta( $post_id, '_sl_ff_prix' );
    }
    if ( $c['promo_pct'] > 0 || $c['prix_promo'] > 0 ) {
        if ( $c['promo_pct'] > 0 ) {
            update_post_meta( $post_id, '_sl_ff_promo_prix', $c['promo_pct'] );
        } else {
            delete_post_meta( $post_id, '_sl_ff_promo_prix' );
        }
        if ( $c['prix_promo'] > 0 ) {
            update_post_meta( $post_id, '_sl_ff_prix_promo', $c['prix_promo'] );
        } else {
            delete_post_meta( $post_id, '_sl_ff_prix_promo' );
        }
        update_post_meta( $post_id, '_sl_ff_promo_debut', $c['debut'] );
        update_post_meta( $post_id, '_sl_ff_promo_fin',   $c['fin'] );
    } else {
        delete_post_meta( $post_id, '_sl_ff_promo_prix' );
        delete_post_meta( $post_id, '_sl_ff_prix_promo' );
        delete_post_meta( $post_id, '_sl_ff_promo_debut' );
        delete_post_meta( $post_id, '_sl_ff_promo_fin' );
    }
}

function sl_ff_post_agence_slugs( $post_id ) {
    $raw = (array) get_post_meta( $post_id, '_sl_ff_agence' );
    $slugs = [];

    foreach ( $raw as $value ) {
        if ( is_array( $value ) ) {
            $items = $value;
        } else {
            $items = preg_split( '/[\s,;|]+/', (string) $value );
        }

        foreach ( (array) $items as $item ) {
            $slug = sanitize_title( $item );
            if ( $slug !== '' ) {
                $slugs[] = $slug;
            }
        }
    }

    return array_values( array_unique( $slugs ) );
}

function sl_ff_all_agence_slugs() {
    $terms = get_terms( [ 'taxonomy' => 'sl_agence_promo', 'hide_empty' => false, 'fields' => 'slugs' ] );
    if ( is_wp_error( $terms ) || empty( $terms ) ) {
        return [];
    }

    return array_values( array_unique( array_filter( array_map( 'sanitize_title', $terms ) ) ) );
}

function sl_ff_is_repas_available_for_agence( $post_id, $agence, $jour ) {
    return in_array( sanitize_text_field( $jour ), sl_ff_get_agence_jours( $post_id, $agence ), true );
}

function sl_ff_filter_repas_available_for_agence( $repas, $agence, $jour ) {
    return array_values( array_filter( (array) $repas, function ( $r ) use ( $agence, $jour ) {
        return $r && isset( $r->ID ) && sl_ff_is_repas_available_for_agence( $r->ID, $agence, $jour );
    } ) );
}

/**
 * Renomme les catégories pour l'affichage frontend.
 */
function sl_ff_cat_display( $cat ) {
    static $map = [
        // Traditionnels (anciens « Boisson » + terme d'import « plat traditionnel »)
        'boisson'             => 'Plats Traditionnels',
        'boissons'            => 'Plats Traditionnels',
        'plat traditionnel'   => 'Plats Traditionnels',
        'plats traditionnels' => 'Plats Traditionnels',
        // Classiques (ancien « Plat principal » + terme d'import « plat classique »)
        'plat principal'      => 'Plats Classiques',
        'plats principaux'    => 'Plats Classiques',
        'plats principal'     => 'Plats Classiques',
        'plat classique'      => 'Plats Classiques',
        'plats classiques'    => 'Plats Classiques',
        // Complements (le nom affiche passe par esc_html -> accents UTF-8 bruts, pas d'entites)
        'complement'          => 'Compléments',
        'complements'         => 'Compléments',
        'complément'          => 'Compléments',
        'compléments'         => 'Compléments',
        // Desserts / Entrees
        'dessert'             => 'Desserts',
        'desserts'            => 'Desserts',
        'entree'              => 'Entrées',
        'entrée'              => 'Entrées',
        'entrées'             => 'Entrées',
    ];
    return $map[ mb_strtolower( trim( $cat ), 'UTF-8' ) ] ?? $cat;
}

/* ============================================================
   CPT + TAXONOMY
   ============================================================ */

add_action( 'init', 'sl_ff_register_cpt', 5 );
function sl_ff_register_cpt() {
    register_post_type( 'sl_repas', [
        'labels' => [
            'name'          => 'Repas Fast Food',
            'singular_name' => 'Repas',
            'add_new_item'  => 'Ajouter un repas',
            'edit_item'     => 'Modifier le repas',
            'not_found'     => 'Aucun repas trouve',
            'menu_name'     => 'Fast Food',
        ],
        'public'             => false,
        'publicly_queryable' => false,
        'show_ui'            => true,
        'show_in_menu'       => false,
        'show_in_rest'       => true,
        'supports'           => [ 'title', 'thumbnail', 'editor' ],
        'capability_type'    => [ 'sl_repas', 'sl_repas_items' ],
        'map_meta_cap'       => true,
        // Capacite de CREATION dediee (distincte de l'edition) : permet a
        // l'admin d'autoriser/interdire l'ajout de repas au responsable sans
        // toucher a son droit de modifier le planning (edit_sl_repas_items).
        'capabilities'       => [ 'create_posts' => 'create_sl_repas_items' ],
        'has_archive'        => false,
        'rewrite'            => false,
    ] );
}

add_action( 'init', 'sl_ff_register_taxonomies', 5 );
function sl_ff_register_taxonomies() {
    register_taxonomy( 'sl_repas_cat', 'sl_repas', [
        'labels' => [
            'name'          => 'Categories repas',
            'singular_name' => 'Categorie',
            'menu_name'     => 'Categories',
        ],
        'public'            => false,
        'show_ui'           => true,
        'show_in_menu'      => false,
        'show_admin_column' => true,
        'hierarchical'      => true,
        'show_in_rest'      => true,
        'capabilities'      => [
            'manage_terms' => 'manage_sl_repas_terms',
            'edit_terms'   => 'manage_sl_repas_terms',
            'delete_terms' => 'manage_sl_repas_terms',
            'assign_terms' => 'edit_sl_repas_items',
        ],
        'rewrite' => false,
    ] );
}

/* ============================================================
   METABOX
   ============================================================ */

/**
 * Écrit la méta agence SANS casser un post multi-agences.
 * Depuis la « Disponibilité multi-agences », un repas peut porter PLUSIEURS
 * lignes `_sl_ff_agence`. `update_post_meta` écraserait TOUTES les lignes
 * avec la même valeur → on ne remplace que si le post est mono-agence ;
 * sinon on ajoute l'agence si elle manque (jamais de suppression ici).
 */
function sl_ff_set_agence_meta( $post_id, $slug ) {
    $slug = sanitize_title( $slug );
    if ( $slug === '' ) return;
    $rows = get_post_meta( $post_id, '_sl_ff_agence' );
    if ( count( (array) $rows ) > 1 ) {
        if ( ! in_array( $slug, $rows, true ) ) {
            add_post_meta( $post_id, '_sl_ff_agence', $slug );
        }
        return;
    }
    update_post_meta( $post_id, '_sl_ff_agence', $slug );
}

add_action( 'add_meta_boxes', 'sl_ff_meta_boxes' );
function sl_ff_meta_boxes() {
    add_meta_box( 'sl_ff_details', 'Details du repas', 'sl_ff_details_cb', 'sl_repas', 'side', 'high' );
}

function sl_ff_details_cb( $post ) {
    wp_nonce_field( 'sl_ff_save_details', 'sl_ff_nonce' );

    $agence      = get_post_meta( $post->ID, '_sl_ff_agence', true );
    $jours_saved = sl_ff_get_agence_jours( $post->ID, $agence );
    $user_agence = get_user_meta( get_current_user_id(), '_sl_agence_ff', true );

    $jours_list = [
        'lundi'    => 'Lun',
        'mardi'    => 'Mar',
        'mercredi' => 'Mer',
        'jeudi'    => 'Jeu',
        'vendredi' => 'Ven',
        'samedi'   => 'Sam',
        'dimanche' => 'Dim',
    ];
    $today_jour = sl_ff_today_jour();
    ?>

    <!-- Jours de disponibilite -->
    <p style="margin:0 0 6px;"><strong>Disponible les jours :</strong></p>
    <div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:14px;">
    <?php foreach ( $jours_list as $slug => $label ) :
        $checked  = in_array( $slug, $jours_saved, true );
        $is_today = ( $slug === $today_jour );
        $bg       = $checked ? '#e91e8c' : '#f0f0f0';
        $color    = $checked ? '#fff' : '#888';
        $border   = $is_today ? '2px solid #c01870' : '2px solid transparent';
    ?>
    <label style="display:inline-flex;align-items:center;gap:3px;background:<?php echo $bg; ?>;color:<?php echo $color; ?>;padding:4px 9px;border-radius:6px;cursor:pointer;font-size:12px;font-weight:700;border:<?php echo $border; ?>;transition:all .15s;">
        <input type="checkbox" name="sl_ff_jours[]"
               value="<?php echo esc_attr( $slug ); ?>"
               <?php checked( $checked ); ?>
               style="display:none;"
               onchange="this.closest('label').style.background=this.checked?'#e91e8c':'#f0f0f0';this.closest('label').style.color=this.checked?'#fff':'#888';">
        <?php echo esc_html( $label ); ?>
    </label>
    <?php endforeach; ?>
    </div>
    <p style="color:#888;font-size:11px;margin-top:-10px;margin-bottom:12px;">
        <?php
        $today_label = [ 'lundi'=>'Lundi','mardi'=>'Mardi','mercredi'=>'Mercredi',
                         'jeudi'=>'Jeudi','vendredi'=>'Vendredi','samedi'=>'Samedi','dimanche'=>'Dimanche' ];
        echo 'Aujourd\'hui : <strong>' . esc_html( $today_label[ $today_jour ] ?? $today_jour ) . '</strong>';
        ?>
    </p>

    <!-- Agence -->
    <?php if ( $user_agence && ! current_user_can( 'sl_ff_all_agencies' ) ) : ?>
        <input type="hidden" name="sl_ff_agence" value="<?php echo esc_attr( $user_agence ); ?>">
        <p style="margin-bottom:12px;"><strong>Agence :</strong> <?php echo esc_html( sl_ff_agency_name( $user_agence ) ); ?></p>
    <?php else :
        $agences = get_terms( [ 'taxonomy' => 'sl_agence_promo', 'hide_empty' => false ] );
        if ( ! is_wp_error( $agences ) ) : ?>
        <p><label><strong>Agence</strong><br>
        <select name="sl_ff_agence" style="width:100%">
            <option value="">-- Choisir --</option>
            <?php if ( current_user_can( 'manage_options' ) || current_user_can( 'sl_ff_all_agencies' ) ) : ?>
            <option value="__all_agencies">Toutes les agences</option>
            <?php endif; ?>
            <?php foreach ( $agences as $a ) : ?>
            <option value="<?php echo esc_attr( $a->slug ); ?>" <?php selected( $agence, $a->slug ); ?>>
                <?php echo esc_html( sl_ff_agency_name( $a->name ) ); ?>
            </option>
            <?php endforeach; ?>
        </select></label></p>
    <?php endif; endif; ?>

    <?php
    // Post multi-agences (via « Disponibilité multi-agences ») : informer l'éditeur
    $agences_all = (array) get_post_meta( $post->ID, '_sl_ff_agence' );
    if ( count( $agences_all ) > 1 ) : ?>
        <p style="background:#f0f6fc;border-left:3px solid #2271b1;padding:6px 8px;font-size:11px;margin:0 0 12px;">
            <strong>Disponible dans <?php echo count( $agences_all ); ?> agences :</strong>
            <?php echo esc_html( implode( ', ', array_map( 'sl_ff_agency_name', $agences_all ) ) ); ?>.<br>
            Changer l'agence ci-dessus l'<em>ajoute</em> à la liste (gérez les retraits depuis
            « Disponibilité multi-agences »).
        </p>
    <?php endif; ?>

    <hr style="margin:12px 0;border:none;border-top:1px solid #ddd;">
    <p style="color:#e91e8c;font-weight:600;margin:0 0 8px;">&#127991; Promotion (optionnel)</p>

    <?php
    $promo_prix  = get_post_meta( $post->ID, '_sl_ff_promo_prix',  true );
    $promo_debut = get_post_meta( $post->ID, '_sl_ff_promo_debut', true );
    $promo_fin   = get_post_meta( $post->ID, '_sl_ff_promo_fin',   true );
    ?>
    <p><label><strong>Remise affiché (%)</strong><br>
    <input type="number" name="sl_ff_promo_prix" value="<?php echo esc_attr( $promo_prix ); ?>"
           style="width:100%" placeholder="Ex: 20 pour -20%%" min="0" max="100"></label></p>

    <p><label><strong>Debut promo</strong><br>
    <input type="date" name="sl_ff_promo_debut" value="<?php echo esc_attr( $promo_debut ); ?>" style="width:100%"></label></p>

    <p><label><strong>Fin promo</strong><br>
    <input type="date" name="sl_ff_promo_fin" value="<?php echo esc_attr( $promo_fin ); ?>" style="width:100%"></label></p>
    <p style="color:#888;font-size:11px;">Le badge promo s'affiche automatiquement pendant la periode.</p>
    <?php
}

add_action( 'save_post_sl_repas', 'sl_ff_save_details' );
function sl_ff_save_details( $post_id ) {
    static $syncing_all_agencies = false;

    if ( $syncing_all_agencies ) return;
    if ( ! isset( $_POST['sl_ff_nonce'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['sl_ff_nonce'], 'sl_ff_save_details' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $can_manage_all_agencies = current_user_can( 'manage_options' ) || current_user_can( 'sl_ff_all_agencies' );
    $sync_to_all_agencies = false;

    // Jours de disponibilite
    $jours = sl_ff_normalize_jours( $_POST['sl_ff_jours'] ?? [] );

    // Agence
    if ( isset( $_POST['sl_ff_agence'] ) ) {
        $requested_agence = sanitize_text_field( $_POST['sl_ff_agence'] );
        if ( $can_manage_all_agencies ) {
            if ( $requested_agence === '__all_agencies' ) {
                $sync_to_all_agencies = true;
                $agences = get_terms( [ 'taxonomy' => 'sl_agence_promo', 'hide_empty' => false, 'orderby' => 'name' ] );
                if ( ! is_wp_error( $agences ) && ! empty( $agences ) ) {
                    sl_ff_set_agence_meta( $post_id, $agences[0]->slug );
                    sl_ff_set_agence_jours( $post_id, $agences[0]->slug, $jours );
                }
            } else {
                sl_ff_set_agence_meta( $post_id, $requested_agence );
                sl_ff_set_agence_jours( $post_id, $requested_agence, $jours );
            }
        } else {
            $user_agence = get_user_meta( get_current_user_id(), '_sl_agence_ff', true );
            if ( $user_agence ) {
                sl_ff_set_agence_meta( $post_id, $user_agence );
                sl_ff_set_agence_jours( $post_id, $user_agence, $jours );
            }
        }
    } else {
        sl_ff_set_agence_jours( $post_id, get_post_meta( $post_id, '_sl_ff_agence', true ), $jours );
    }

    // Promotions
    $promo_prix  = isset( $_POST['sl_ff_promo_prix'] )  ? intval( $_POST['sl_ff_promo_prix'] )                     : '';
    $promo_debut = isset( $_POST['sl_ff_promo_debut'] ) ? sanitize_text_field( $_POST['sl_ff_promo_debut'] ) : '';
    $promo_fin   = isset( $_POST['sl_ff_promo_fin'] )   ? sanitize_text_field( $_POST['sl_ff_promo_fin'] )   : '';

    if ( $promo_prix > 0 ) {
        update_post_meta( $post_id, '_sl_ff_promo_prix',  $promo_prix );
        update_post_meta( $post_id, '_sl_ff_promo_debut', $promo_debut );
        update_post_meta( $post_id, '_sl_ff_promo_fin',   $promo_fin );
    } else {
        delete_post_meta( $post_id, '_sl_ff_promo_prix' );
        delete_post_meta( $post_id, '_sl_ff_promo_debut' );
        delete_post_meta( $post_id, '_sl_ff_promo_fin' );
    }

    if ( $sync_to_all_agencies ) {
        $syncing_all_agencies = true;
        sl_ff_sync_repas_to_all_agencies( $post_id, $jours, $promo_prix, $promo_debut, $promo_fin );
        $syncing_all_agencies = false;
    }
}

function sl_ff_sync_repas_to_all_agencies( $source_id, $jours, $promo_prix, $promo_debut, $promo_fin ) {
    $source = get_post( $source_id );
    if ( ! $source || $source->post_type !== 'sl_repas' ) return;

    $agences = get_terms( [ 'taxonomy' => 'sl_agence_promo', 'hide_empty' => false, 'orderby' => 'name' ] );
    if ( is_wp_error( $agences ) || empty( $agences ) ) return;

    $source_agence = get_post_meta( $source_id, '_sl_ff_agence', true );
    $term_ids = wp_get_post_terms( $source_id, 'sl_repas_cat', [ 'fields' => 'ids' ] );
    $term_ids = is_wp_error( $term_ids ) ? [] : array_map( 'intval', $term_ids );
    $thumbnail_id = get_post_thumbnail_id( $source_id );

    foreach ( $agences as $agence ) {
        $agence_slug = sanitize_title( $agence->slug );
        if ( $agence_slug === $source_agence ) continue;

        $existing = get_posts( [
            'post_type'      => 'sl_repas',
            'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                [ 'key' => '_sl_ff_agence', 'value' => $agence_slug ],
            ],
        ] );

        $target_id = 0;
        foreach ( $existing as $existing_id ) {
            if ( get_the_title( $existing_id ) === $source->post_title ) {
                $target_id = (int) $existing_id;
                break;
            }
        }

        $post_data = [
            'post_type'    => 'sl_repas',
            'post_title'   => $source->post_title,
            'post_content' => $source->post_content,
            'post_excerpt' => $source->post_excerpt,
            'post_status'  => $source->post_status,
            'post_author'  => $source->post_author,
        ];

        if ( $target_id ) {
            $post_data['ID'] = $target_id;
            wp_update_post( $post_data );
        } else {
            $target_id = wp_insert_post( $post_data );
        }

        if ( ! $target_id || is_wp_error( $target_id ) ) continue;

        update_post_meta( $target_id, '_sl_ff_agence', $agence_slug );
        sl_ff_set_agence_jours( $target_id, $agence_slug, $jours );

        if ( $promo_prix > 0 ) {
            update_post_meta( $target_id, '_sl_ff_promo_prix',  $promo_prix );
            update_post_meta( $target_id, '_sl_ff_promo_debut', $promo_debut );
            update_post_meta( $target_id, '_sl_ff_promo_fin',   $promo_fin );
        } else {
            delete_post_meta( $target_id, '_sl_ff_promo_prix' );
            delete_post_meta( $target_id, '_sl_ff_promo_debut' );
            delete_post_meta( $target_id, '_sl_ff_promo_fin' );
        }

        if ( $term_ids ) {
            wp_set_post_terms( $target_id, $term_ids, 'sl_repas_cat' );
        }

        if ( $thumbnail_id ) {
            set_post_thumbnail( $target_id, $thumbnail_id );
        } else {
            delete_post_thumbnail( $target_id );
        }
    }
}

/**
 * Helper : retourne l'indicateur de promo s'il est actif.
 * Métas : _sl_ff_promo_prix (remise %), _sl_ff_prix (prix actuel FCFA),
 * _sl_ff_prix_promo (prix promo FCFA), _sl_ff_promo_debut/_fin (période).
 * La promo est active si une remise % OU un prix promo est défini, dans la période.
 * Le % affiché est calculé depuis les prix si la remise % n'est pas saisie.
 * @return array { est_promo, pct_reduction, prix, prix_promo }
 */
function sl_ff_get_promo_info( $post_id, $agence = '' ) {
    $today = current_time( 'Y-m-d' );
    $p     = sl_ff_get_agence_prix( $post_id, $agence );
    $promo_pct   = $p['promo_pct'];
    $prix        = $p['prix'];
    $prix_promo  = $p['prix_promo'];
    $promo_debut = $p['debut'];
    $promo_fin   = $p['fin'];

    $est_promo = false;
    if ( $promo_pct > 0 || $prix_promo > 0 ) {
        $debut_ok  = empty( $promo_debut ) || $today >= $promo_debut;
        $fin_ok    = empty( $promo_fin )   || $today <= $promo_fin;
        $est_promo = $debut_ok && $fin_ok;
    }
    if ( $est_promo && $promo_pct <= 0 && $prix > 0 && $prix_promo > 0 && $prix_promo < $prix ) {
        $promo_pct = (int) round( 100 * ( 1 - $prix_promo / $prix ) );
    }
    return [
        'est_promo'     => $est_promo,
        'pct_reduction' => $est_promo ? $promo_pct : 0,
        'prix'          => $prix,
        'prix_promo'    => $est_promo ? $prix_promo : 0,
    ];
}

/** Formate un prix FCFA pour l'affichage. */
function sl_ff_format_prix( $n ) {
    return number_format( (int) $n, 0, ',', ' ' ) . ' FCFA';
}

/* ============================================================
   CACHE DU MENU FRONT (transients versionnés)
   Le HTML du menu par agence est coûteux (requête + boucle) et
   demandé par chaque visiteur via admin-ajax (non cacheable par
   Varnish). On le met en transient, invalidé en masse par bump
   de version à chaque changement de repas/planning/promo/sync.
   ============================================================ */
function sl_ff_menu_cache_ver() {
    return (int) get_option( 'sl_ff_menu_cache_ver', 1 );
}
function sl_ff_bump_menu_cache() {
    static $done = false; // 1 bump par requête suffit
    if ( $done ) return;
    $done = true;
    update_option( 'sl_ff_menu_cache_ver', sl_ff_menu_cache_ver() + 1, false );
}
add_action( 'save_post_sl_repas', 'sl_ff_bump_menu_cache' );
add_action( 'deleted_post', function ( $post_id ) {
    if ( get_post_type( $post_id ) === 'sl_repas' ) sl_ff_bump_menu_cache();
} );

/** Clé de cache du menu : version + agence + date du jour (les promos dépendent de la date). */
function sl_ff_menu_cache_key( $agence ) {
    return 'sl_ff_menu_' . sl_ff_menu_cache_ver() . '_' . md5( $agence . '|' . current_time( 'Y-m-d' ) );
}

// Compatibilite ascendante si sl_ff_get_prix_info() est encore appelée ailleurs
if ( ! function_exists( 'sl_ff_get_prix_info' ) ) {
    function sl_ff_get_prix_info( $post_id ) {
        $info = sl_ff_get_promo_info( $post_id );
        return [
            'prix_affiche'  => 0,
            'prix_original' => 0,
            'est_promo'     => $info['est_promo'],
            'pct_reduction' => $info['pct_reduction'],
        ];
    }
}

/* ============================================================
   COLONNE « AGENCES » dans la liste admin des repas
   Montre d'un coup d'œil où chaque plat est actif (multi-agences).
   ============================================================ */
add_filter( 'manage_sl_repas_posts_columns', function ( $cols ) {
    $new = [];
    foreach ( $cols as $k => $v ) {
        $new[ $k ] = $v;
        if ( $k === 'title' ) $new['sl_ff_agences'] = 'Agences';
    }
    return $new;
} );
add_action( 'manage_sl_repas_posts_custom_column', function ( $col, $post_id ) {
    if ( $col !== 'sl_ff_agences' ) return;
    $slugs = (array) get_post_meta( $post_id, '_sl_ff_agence' );
    $slugs = array_values( array_filter( array_unique( $slugs ) ) );
    if ( empty( $slugs ) ) { echo '<span style="color:#ccc;">—</span>'; return; }
    sort( $slugs );
    $names = [];
    foreach ( $slugs as $s ) {
        $t = get_term_by( 'slug', $s, 'sl_agence_promo' );
        $names[] = ( $t && ! is_wp_error( $t ) ) ? $t->name : $s;
    }
    $n = count( $names );
    if ( $n <= 3 ) {
        echo esc_html( implode( ', ', $names ) );
    } else {
        echo '<span title="' . esc_attr( implode( ', ', $names ) ) . '" style="cursor:help;border-bottom:1px dotted #999;">'
           . esc_html( $names[0] . ' + ' . ( $n - 1 ) . ' agences' ) . '</span>';
    }
}, 10, 2 );

add_action( 'pre_get_posts', 'sl_ff_filter_by_agence' );
function sl_ff_filter_by_agence( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) return;
    if ( $query->get( 'post_type' ) !== 'sl_repas' ) return;
    // Admin WP et Administrateur Fast Food voient tous les repas
    if ( current_user_can( 'manage_options' ) || current_user_can( 'sl_ff_all_agencies' ) ) return;
    $agence = get_user_meta( get_current_user_id(), '_sl_agence_ff', true );
    if ( ! $agence ) {
        // Fail-closed : un responsable sans agence assignee ne voit AUCUN repas
        // (au lieu de tous). Il doit se voir attribuer une agence par un admin.
        $query->set( 'post__in', [ 0 ] );
        return;
    }
    $query->set( 'meta_key',   '_sl_ff_agence' );
    $query->set( 'meta_value', $agence );
}
