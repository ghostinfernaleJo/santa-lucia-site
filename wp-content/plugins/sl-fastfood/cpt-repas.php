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

    $query = [
        'relation' => 'OR',
        [
            'key'     => '_sl_ff_agence',
            'value'   => $values,
            'compare' => 'IN',
        ],
    ];

    foreach ( $values as $value ) {
        $query[] = [
            'key'     => '_sl_ff_agence',
            'value'   => '"' . $value . '"',
            'compare' => 'LIKE',
        ];
    }

    return $query;
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

/**
 * Renomme les catégories pour l'affichage frontend.
 */
function sl_ff_cat_display( $cat ) {
    static $map = [
        'boisson'          => 'Plats Traditionnels',
        'boissons'         => 'Plats Traditionnels',
        'plat principal'   => 'Plats Classiques',
        'plats principaux' => 'Plats Classiques',
        'plats principal'  => 'Plats Classiques',
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

add_action( 'add_meta_boxes', 'sl_ff_meta_boxes' );
function sl_ff_meta_boxes() {
    remove_meta_box( 'postimagediv', 'sl_repas', 'side' );
    add_meta_box( 'sl_ff_image', 'Image du repas', 'sl_ff_image_cb', 'sl_repas', 'side', 'high' );
    add_meta_box( 'sl_ff_details', 'Details du repas', 'sl_ff_details_cb', 'sl_repas', 'side', 'high' );
}

function sl_ff_image_cb( $post ) {
    $thumb_id  = get_post_thumbnail_id( $post->ID );
    $thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : '';
    $can_upload = current_user_can( 'upload_files' );
    ?>
    <div class="sl-ff-image-box">
        <input type="hidden" id="sl_ff_thumbnail_id" name="sl_ff_thumbnail_id" value="<?php echo esc_attr( $thumb_id ); ?>">
        <div id="sl_ff_image_preview" class="sl-ff-image-preview<?php echo $thumb_url ? ' has-image' : ''; ?>">
            <?php if ( $thumb_url ) : ?>
                <img src="<?php echo esc_url( $thumb_url ); ?>" alt="">
            <?php else : ?>
                <span class="dashicons dashicons-format-image"></span>
                <strong>Aucune image</strong>
            <?php endif; ?>
        </div>
        <?php if ( $can_upload ) : ?>
            <button type="button" class="button button-primary button-large sl-ff-image-select">
                <?php echo $thumb_url ? 'Remplacer l&#39;image' : 'Ajouter une image'; ?>
            </button>
            <button type="button" class="button sl-ff-image-remove" <?php disabled( ! $thumb_url ); ?>>
                Supprimer
            </button>
            <p class="description">Cette image s'affiche dans le menu Fast Food du site.</p>
        <?php else : ?>
            <p class="description">Votre compte ne permet pas d'envoyer des images.</p>
        <?php endif; ?>
    </div>
    <?php
}

function sl_ff_details_cb( $post ) {
    wp_nonce_field( 'sl_ff_save_details', 'sl_ff_nonce' );

    $agence      = get_post_meta( $post->ID, '_sl_ff_agence', true );
    $jours_saved = (array) get_post_meta( $post->ID, '_sl_ff_jours', true );
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
    <?php if ( $user_agence ) : ?>
        <input type="hidden" name="sl_ff_agence" value="<?php echo esc_attr( $user_agence ); ?>">
        <p style="margin-bottom:12px;"><strong>Agence :</strong> <?php echo esc_html( sl_ff_agency_name( $user_agence ) ); ?></p>
    <?php else :
        $agences = get_terms( [ 'taxonomy' => 'sl_agence_promo', 'hide_empty' => false ] );
        if ( ! is_wp_error( $agences ) ) : ?>
        <p><label><strong>Agence</strong><br>
        <select name="sl_ff_agence" style="width:100%">
            <option value="">-- Choisir --</option>
            <?php foreach ( $agences as $a ) : ?>
            <option value="<?php echo esc_attr( $a->slug ); ?>" <?php selected( $agence, $a->slug ); ?>>
                <?php echo esc_html( sl_ff_agency_name( $a->name ) ); ?>
            </option>
            <?php endforeach; ?>
        </select></label></p>
    <?php endif; endif; ?>

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
    if ( ! isset( $_POST['sl_ff_nonce'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['sl_ff_nonce'], 'sl_ff_save_details' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    // Jours de disponibilite
    $jours_valides = [ 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche' ];
    $jours = array_values( array_intersect(
        (array) ( $_POST['sl_ff_jours'] ?? [] ),
        $jours_valides
    ) );
    update_post_meta( $post_id, '_sl_ff_jours', $jours );

    // Agence
    if ( isset( $_POST['sl_ff_agence'] ) ) {
        $requested_agence = sanitize_text_field( $_POST['sl_ff_agence'] );
        if ( current_user_can( 'manage_options' ) || current_user_can( 'sl_ff_all_agencies' ) ) {
            update_post_meta( $post_id, '_sl_ff_agence', sanitize_title( $requested_agence ) );
        } else {
            $user_agence = get_user_meta( get_current_user_id(), '_sl_agence_ff', true );
            if ( $user_agence ) {
                update_post_meta( $post_id, '_sl_ff_agence', sanitize_title( $user_agence ) );
            }
        }
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

    // Image du repas.
    if ( current_user_can( 'upload_files' ) && array_key_exists( 'sl_ff_thumbnail_id', $_POST ) ) {
        $thumb_id = absint( $_POST['sl_ff_thumbnail_id'] );
        if ( $thumb_id > 0 ) {
            set_post_thumbnail( $post_id, $thumb_id );
        } else {
            delete_post_thumbnail( $post_id );
        }
    }
}

/**
 * Helper : retourne l'indicateur de promo (badge %) s'il est actif.
 * @return array { est_promo, pct_reduction }
 */
function sl_ff_get_promo_info( $post_id ) {
    $today      = current_time( 'Y-m-d' );
    $promo_pct  = (int) get_post_meta( $post_id, '_sl_ff_promo_prix',  true );
    $promo_debut = get_post_meta( $post_id, '_sl_ff_promo_debut', true );
    $promo_fin   = get_post_meta( $post_id, '_sl_ff_promo_fin',   true );

    $est_promo = false;
    if ( $promo_pct > 0 ) {
        $debut_ok  = empty( $promo_debut ) || $today >= $promo_debut;
        $fin_ok    = empty( $promo_fin )   || $today <= $promo_fin;
        $est_promo = $debut_ok && $fin_ok;
    }
    return [
        'est_promo'     => $est_promo,
        'pct_reduction' => $est_promo ? $promo_pct : 0,
    ];
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

add_action( 'pre_get_posts', 'sl_ff_filter_by_agence' );
function sl_ff_filter_by_agence( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) return;
    if ( $query->get( 'post_type' ) !== 'sl_repas' ) return;
    // Admin WP et Administrateur Fast Food voient tous les repas
    if ( current_user_can( 'manage_options' ) || current_user_can( 'sl_ff_all_agencies' ) ) return;
    $agence = get_user_meta( get_current_user_id(), '_sl_agence_ff', true );
    if ( ! $agence ) return;
    $query->set( 'meta_key',   '_sl_ff_agence' );
    $query->set( 'meta_value', $agence );
}

add_filter( 'manage_sl_repas_posts_columns', 'sl_ff_repas_columns' );
function sl_ff_repas_columns( $columns ) {
    $new = [];
    foreach ( $columns as $key => $label ) {
        if ( $key === 'title' ) {
            $new['sl_ff_image'] = 'Image';
        }
        $new[ $key ] = $label;
        if ( $key === 'title' ) {
            $new['sl_ff_agence_col'] = 'Agence';
            $new['sl_ff_jours_col']  = 'Jours';
        }
    }
    return $new;
}

add_action( 'manage_sl_repas_posts_custom_column', 'sl_ff_repas_column_content', 10, 2 );
function sl_ff_repas_column_content( $column, $post_id ) {
    if ( $column === 'sl_ff_image' ) {
        $thumb = get_the_post_thumbnail_url( $post_id, 'thumbnail' );
        if ( $thumb ) {
            echo '<img src="' . esc_url( $thumb ) . '" alt="" style="width:54px;height:54px;object-fit:cover;border-radius:8px;border:1px solid #ddd;">';
        } else {
            echo '<span style="display:inline-flex;width:54px;height:54px;align-items:center;justify-content:center;border-radius:8px;background:#f3f4f6;color:#9ca3af;border:1px solid #e5e7eb;">-</span>';
        }
        return;
    }

    if ( $column === 'sl_ff_agence_col' ) {
        $agence = get_post_meta( $post_id, '_sl_ff_agence', true );
        echo $agence ? esc_html( sl_ff_agency_name( is_array( $agence ) ? implode( ', ', $agence ) : $agence ) ) : '-';
        return;
    }

    if ( $column === 'sl_ff_jours_col' ) {
        $jours = (array) get_post_meta( $post_id, '_sl_ff_jours', true );
        echo $jours ? esc_html( implode( ', ', $jours ) ) : '-';
    }
}
