<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   IMAGES DES PLATS — une image par NOM de plat, partagee par
   toutes les agences (presentes et futures). Aucune duplication
   de fichier : mapping nom_normalise => attachment_id stocke
   dans une seule option.
   ============================================================ */

if ( ! defined( 'SL_FF_DISH_IMG_OPTION' ) ) {
    define( 'SL_FF_DISH_IMG_OPTION', 'sl_ff_dish_images' );
}

/** Cle normalisee d'un plat (memes regles que l'import : casse + accents). */
function sl_ff_dish_key( $title ) {
    if ( function_exists( 'sl_ff_norm_header' ) ) {
        return sl_ff_norm_header( $title );
    }
    return mb_strtolower( trim( (string) $title ), 'UTF-8' );
}

/** Mapping complet nom_normalise => attachment_id. */
function sl_ff_dish_images_map() {
    $m = get_option( SL_FF_DISH_IMG_OPTION, [] );
    return is_array( $m ) ? $m : [];
}

/** ID d'image associe a un plat (par son titre), 0 si aucun. */
function sl_ff_dish_image_id( $title ) {
    $m = sl_ff_dish_images_map();
    $k = sl_ff_dish_key( $title );
    return isset( $m[ $k ] ) ? (int) $m[ $k ] : 0;
}

/**
 * URL de l'image a afficher pour un repas :
 * 1) image du plat (par nom) ; 2) repli sur l'image a la une du post.
 */
function sl_ff_item_image_url( $post_id, $size = 'large' ) {
    $att = sl_ff_dish_image_id( get_the_title( $post_id ) );
    if ( $att ) {
        $url = wp_get_attachment_image_url( $att, $size );
        if ( $url ) return $url;
    }
    return get_the_post_thumbnail_url( $post_id, $size );
}

/* ── Menu : sous "Fast Food" ── */
add_action( 'admin_menu', 'sl_ff_images_menu', 1000 );
function sl_ff_images_menu() {
    add_submenu_page(
        'sl-fastfood',
        'Images des plats',
        'Images des plats',
        'manage_options',
        'sl-ff-images',
        'sl_ff_images_page'
    );
}

/* ── Page ── */
function sl_ff_images_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Acces refuse.' );
    }
    global $wpdb;

    // Enregistrement
    if ( isset( $_POST['sl_ff_img_save'] ) && check_admin_referer( 'sl_ff_images_nonce' ) ) {
        $titles = isset( $_POST['sl_ff_title'] ) ? (array) $_POST['sl_ff_title'] : [];
        $imgs   = isset( $_POST['sl_ff_img'] )   ? (array) $_POST['sl_ff_img']   : [];
        $map = sl_ff_dish_images_map();
        foreach ( $titles as $i => $t ) {
            $key = sl_ff_dish_key( wp_unslash( $t ) );
            $id  = isset( $imgs[ $i ] ) ? (int) $imgs[ $i ] : 0;
            if ( $id > 0 ) {
                $map[ $key ] = $id;
            } else {
                unset( $map[ $key ] );
            }
        }
        update_option( SL_FF_DISH_IMG_OPTION, $map, false );
        echo '<div class="notice notice-success is-dismissible"><p>Images des plats enregistrees.</p></div>';
    }

    // Liste des plats distincts (+ une categorie representative)
    $rows = $wpdb->get_results( "
        SELECT p.post_title AS title, MAX(t.name) AS cat
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
        LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'sl_repas_cat'
        LEFT JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
        WHERE p.post_type = 'sl_repas' AND p.post_status = 'publish'
        GROUP BY p.post_title
        ORDER BY p.post_title
    " );

    $map     = sl_ff_dish_images_map();
    $nb_done = 0;
    foreach ( $rows as $r ) {
        if ( ! empty( $map[ sl_ff_dish_key( $r->title ) ] ) ) $nb_done++;
    }

    wp_enqueue_media();
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Images des plats</h1>
        <p class="description" style="max-width:760px;">
            Choisissez une image par plat : elle s'applique automatiquement a <strong>toutes les agences</strong>
            (et aux nouvelles a venir). Une seule image stockee par plat, aucune duplication.
        </p>
        <p><strong><?php echo (int) $nb_done; ?></strong> / <?php echo count( $rows ); ?> plats avec image.</p>

        <input type="search" id="sl-ff-img-search" class="regular-text" placeholder="Rechercher un plat…" style="margin:6px 0 12px;">

        <form method="post">
            <?php wp_nonce_field( 'sl_ff_images_nonce' ); ?>
            <table class="widefat striped" id="sl-ff-img-table">
                <thead>
                    <tr>
                        <th style="width:64px;">Image</th>
                        <th>Plat</th>
                        <th style="width:170px;">Categorie</th>
                        <th style="width:190px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php $i = 0; foreach ( $rows as $r ) :
                    $key  = sl_ff_dish_key( $r->title );
                    $id   = isset( $map[ $key ] ) ? (int) $map[ $key ] : 0;
                    $url  = $id ? wp_get_attachment_image_url( $id, 'thumbnail' ) : '';
                    $catd = $r->cat ? ( function_exists( 'sl_ff_cat_display' ) ? sl_ff_cat_display( $r->cat ) : $r->cat ) : '';
                ?>
                    <tr class="sl-ff-img-row" data-name="<?php echo esc_attr( mb_strtolower( $r->title, 'UTF-8' ) ); ?>">
                        <td>
                            <img class="sl-ff-img-prev" src="<?php echo esc_url( $url ); ?>"
                                 style="width:48px;height:48px;object-fit:cover;border-radius:4px;background:#f1f1f1;<?php echo $url ? '' : 'display:none;'; ?>">
                            <span class="sl-ff-img-none" style="<?php echo $url ? 'display:none;' : ''; ?>color:#bbb;">—</span>
                        </td>
                        <td>
                            <strong><?php echo esc_html( $r->title ); ?></strong>
                            <input type="hidden" name="sl_ff_title[<?php echo $i; ?>]" value="<?php echo esc_attr( $r->title ); ?>">
                            <input type="hidden" class="sl-ff-img-id" name="sl_ff_img[<?php echo $i; ?>]" value="<?php echo (int) $id; ?>">
                        </td>
                        <td><?php echo esc_html( $catd ); ?></td>
                        <td>
                            <button type="button" class="button sl-ff-img-pick">Choisir une image…</button>
                            <button type="button" class="button-link sl-ff-img-clear" style="color:#b32d2e;<?php echo $id ? '' : 'display:none;'; ?>">retirer</button>
                        </td>
                    </tr>
                <?php $i++; endforeach; ?>
                </tbody>
            </table>
            <p style="margin-top:16px;">
                <button type="submit" name="sl_ff_img_save" class="button button-primary button-large">Enregistrer les images</button>
            </p>
        </form>
    </div>

    <script>
    jQuery(function($){
        var table = $('#sl-ff-img-table');
        table.on('click', '.sl-ff-img-pick', function(e){
            e.preventDefault();
            var row = $(this).closest('tr');
            var frame = wp.media({
                title: 'Choisir une image pour ce plat',
                library: { type: 'image' },
                multiple: false,
                button: { text: 'Utiliser cette image' }
            });
            frame.on('select', function(){
                var att = frame.state().get('selection').first().toJSON();
                var url = (att.sizes && att.sizes.thumbnail) ? att.sizes.thumbnail.url : att.url;
                row.find('.sl-ff-img-id').val(att.id);
                row.find('.sl-ff-img-prev').attr('src', url).show();
                row.find('.sl-ff-img-none').hide();
                row.find('.sl-ff-img-clear').show();
            });
            frame.open();
        });
        table.on('click', '.sl-ff-img-clear', function(e){
            e.preventDefault();
            var row = $(this).closest('tr');
            row.find('.sl-ff-img-id').val(0);
            row.find('.sl-ff-img-prev').hide().attr('src', '');
            row.find('.sl-ff-img-none').show();
            $(this).hide();
        });
        $('#sl-ff-img-search').on('input', function(){
            var q = $(this).val().toLowerCase();
            $('.sl-ff-img-row').each(function(){
                $(this).toggle( $(this).data('name').indexOf(q) >= 0 );
            });
        });
    });
    </script>
    <?php
}
