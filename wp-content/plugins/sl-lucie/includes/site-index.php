<?php
/**
 * Index public du site pour Lucie.
 *
 * L'index couvre les pages, articles, produits et types de contenus publics,
 * y compris le texte stocke par Elementor et les liens de navigation. Il ne
 * lit volontairement pas les metadonnees arbitraires afin de ne jamais
 * exposer une donnee privee via le chatbot.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SL_LUCIE_INDEX_DB_VERSION', '1.0.0' );

function sl_lucie_index_table() {
    global $wpdb;
    return $wpdb->prefix . 'sl_lucie_index';
}

/** Cree ou met a niveau la table sans ralentir les requetes suivantes. */
function sl_lucie_index_maybe_install() {
    if ( get_option( 'sl_lucie_index_db_version' ) === SL_LUCIE_INDEX_DB_VERSION ) return;

    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table   = sl_lucie_index_table();
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        object_id bigint(20) unsigned NOT NULL DEFAULT 0,
        source varchar(64) NOT NULL DEFAULT '',
        title text NOT NULL,
        content longtext NOT NULL,
        url text NOT NULL,
        image text NOT NULL,
        updated datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY source_object (source, object_id),
        KEY object_id (object_id),
        KEY source (source)
    ) {$charset};";
    dbDelta( $sql );

    update_option( 'sl_lucie_index_db_version', SL_LUCIE_INDEX_DB_VERSION, false );
    update_option( 'sl_lucie_index_dirty', '1', false );
    if ( ! wp_next_scheduled( 'sl_lucie_rebuild_site_index' ) ) {
        wp_schedule_single_event( time() + 20, 'sl_lucie_rebuild_site_index' );
    }
}
add_action( 'init', 'sl_lucie_index_maybe_install', 90 );
add_action( 'sl_lucie_rebuild_site_index', 'sl_lucie_rebuild_site_index' );

/** Types de contenus dont la consultation est publique. */
function sl_lucie_index_public_post_types() {
    $types = get_post_types( [ 'public' => true ], 'names' );
    unset( $types['attachment'], $types['sl_lucie_lead'] );
    return array_values( $types );
}

function sl_lucie_index_should_include( $post ) {
    if ( ! $post instanceof WP_Post || $post->post_status !== 'publish' ) return false;
    if ( $post->post_type === 'nav_menu_item' ) {
        $linked_id = (int) get_post_meta( $post->ID, '_menu_item_object_id', true );
        if ( $linked_id ) {
            $linked = get_post( $linked_id );
            if ( $linked && ( $linked->post_password !== '' || get_post_meta( $linked_id, '_slfd_internal_page', true ) !== '' ) ) return false;
        }
        return true;
    }
    if ( $post->post_password !== '' ) return false;
    // Les ecrans fidelite sont des pages techniques publiees uniquement pour
    // disposer d'une URL ; leur contenu et leur lien restent internes.
    if ( get_post_meta( $post->ID, '_slfd_internal_page', true ) !== '' ) return false;
    $allowed = in_array( $post->post_type, sl_lucie_index_public_post_types(), true );
    return (bool) apply_filters( 'sl_lucie_index_post_allowed', $allowed, $post );
}

/** Transforme une URL interne relative en URL partageable et refuse les pseudo-URLs. */
function sl_lucie_index_clean_url( $url ) {
    $url = trim( html_entity_decode( (string) $url, ENT_QUOTES, 'UTF-8' ) );
    if ( $url === '' || str_starts_with( $url, '#' ) || preg_match( '~^(javascript|data):~i', $url ) ) return '';
    if ( str_starts_with( $url, '/' ) ) $url = home_url( $url );
    if ( ! preg_match( '~^(https?://|mailto:|tel:)~i', $url ) ) return '';
    return esc_url_raw( $url, [ 'http', 'https', 'mailto', 'tel' ] );
}

/** Extrait les liens exacts et, si possible, leur libelle. */
function sl_lucie_index_extract_links( $html ) {
    $html  = (string) $html;
    $links = [];
    if ( preg_match_all( '~<a\b[^>]*href\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)</a>~isu', $html, $matches, PREG_SET_ORDER ) ) {
        foreach ( $matches as $match ) {
            $url = sl_lucie_index_clean_url( $match[2] );
            if ( $url === '' ) continue;
            $label = trim( wp_strip_all_tags( html_entity_decode( $match[3], ENT_QUOTES, 'UTF-8' ) ) );
            $links[ $url ] = $label !== '' ? $label : $url;
        }
    }
    if ( preg_match_all( '~https?://[^\s<"\']+~iu', $html, $matches ) ) {
        foreach ( $matches[0] as $raw ) {
            $url = sl_lucie_index_clean_url( rtrim( $raw, '.,;:!?)\]' ) );
            if ( $url !== '' && ! isset( $links[ $url ] ) ) $links[ $url ] = $url;
        }
    }
    return array_slice( $links, 0, 100, true );
}

/** Recupere le texte utile et les URLs imbriques dans la structure Elementor. */
function sl_lucie_index_flatten_elementor( $value, &$texts, &$links, $depth = 0 ) {
    if ( $depth > 20 ) return;
    if ( is_array( $value ) ) {
        foreach ( $value as $key => $child ) {
            if ( is_string( $child ) && ( $key === 'url' || str_ends_with( (string) $key, '_url' ) ) ) {
                $url = sl_lucie_index_clean_url( $child );
                if ( $url !== '' ) $links[ $url ] = $url;
            }
            sl_lucie_index_flatten_elementor( $child, $texts, $links, $depth + 1 );
        }
        return;
    }
    if ( ! is_string( $value ) ) return;

    foreach ( sl_lucie_index_extract_links( $value ) as $url => $label ) $links[ $url ] = $label;
    $text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( html_entity_decode( $value, ENT_QUOTES, 'UTF-8' ) ) ) );
    if ( $text === '' || mb_strlen( $text ) < 2 || preg_match( '/^(#?[a-f0-9]{3,8}|-?\d+(\.\d+)?(%|px|em|rem)?)$/i', $text ) ) return;
    $texts[] = $text;
}

/** Construit uniquement des donnees deja publiques pour un contenu. */
function sl_lucie_index_document( WP_Post $post ) {
    $title = trim( wp_strip_all_tags( get_the_title( $post ) ) );
    $url   = '';
    $image = '';
    $parts = [];
    $links = [];

    if ( $post->post_type === 'nav_menu_item' ) {
        $url = sl_lucie_index_clean_url( get_post_meta( $post->ID, '_menu_item_url', true ) );
        $linked_id = (int) get_post_meta( $post->ID, '_menu_item_object_id', true );
        if ( $url === '' && $linked_id ) $url = (string) get_permalink( $linked_id );
        if ( $url !== '' ) $links[ $url ] = $title !== '' ? $title : $url;
        $parts[] = 'Lien de navigation';
    } else {
        $url = (string) get_permalink( $post );
        $image = (string) get_the_post_thumbnail_url( $post, 'medium_large' );
        $parts[] = (string) $post->post_excerpt;
        $parts[] = (string) $post->post_content;
        foreach ( sl_lucie_index_extract_links( $post->post_content ) as $href => $label ) $links[ $href ] = $label;

        $raw_elementor = get_post_meta( $post->ID, '_elementor_data', true );
        if ( is_string( $raw_elementor ) && $raw_elementor !== '' ) {
            $tree = json_decode( $raw_elementor, true );
            if ( is_array( $tree ) ) sl_lucie_index_flatten_elementor( $tree, $parts, $links );
        }

        $taxonomies = get_object_taxonomies( $post->post_type, 'objects' );
        foreach ( $taxonomies as $taxonomy ) {
            if ( empty( $taxonomy->public ) ) continue;
            $names = wp_get_post_terms( $post->ID, $taxonomy->name, [ 'fields' => 'names' ] );
            if ( ! is_wp_error( $names ) && $names ) $parts[] = $taxonomy->labels->singular_name . ' : ' . implode( ', ', $names );
        }

        if ( $post->post_type === 'product' && function_exists( 'wc_get_product' ) ) {
            $product = wc_get_product( $post->ID );
            if ( $product ) {
                $parts[] = 'Prix : ' . html_entity_decode( wp_strip_all_tags( wc_price( $product->get_price() ) ), ENT_QUOTES, 'UTF-8' );
                $parts[] = 'Disponibilite : ' . ( $product->is_in_stock() ? 'en stock' : 'rupture de stock' );
                if ( $product->get_sku() !== '' ) $parts[] = 'Reference : ' . $product->get_sku();
                $product_image = wp_get_attachment_image_url( $product->get_image_id(), 'medium_large' );
                if ( $product_image ) $image = $product_image;
            }
        }
    }

    $clean = [];
    foreach ( $parts as $part ) {
        $text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( strip_shortcodes( (string) $part ) ) ) );
        if ( $text !== '' ) $clean[] = $text;
    }
    if ( $links ) {
        $formatted = [];
        foreach ( $links as $href => $label ) $formatted[] = trim( $label ) . ' : ' . $href;
        $clean[] = "Liens publics :\n" . implode( "\n", $formatted );
    }

    return [
        'object_id' => (int) $post->ID,
        'source'    => sanitize_key( $post->post_type ),
        'title'     => $title !== '' ? $title : '(Sans titre)',
        'content'   => mb_substr( implode( "\n", array_unique( $clean ) ), 0, 120000 ),
        'url'       => $url,
        'image'     => $image,
        'updated'   => current_time( 'mysql' ),
    ];
}

function sl_lucie_index_post( $post_id ) {
    $post = get_post( $post_id );
    if ( ! sl_lucie_index_should_include( $post ) ) {
        sl_lucie_index_delete_post( $post_id );
        return false;
    }
    $doc = sl_lucie_index_document( $post );
    if ( $doc['url'] === '' && $post->post_type !== 'nav_menu_item' ) return false;

    global $wpdb;
    return false !== $wpdb->replace(
        sl_lucie_index_table(),
        $doc,
        [ '%d', '%s', '%s', '%s', '%s', '%s', '%s' ]
    );
}

function sl_lucie_index_delete_post( $post_id ) {
    global $wpdb;
    $wpdb->delete( sl_lucie_index_table(), [ 'object_id' => (int) $post_id ], [ '%d' ] );
}

/** Reconstruction complete, executee en tache differee ou au premier besoin. */
function sl_lucie_rebuild_site_index() {
    sl_lucie_index_maybe_install();
    global $wpdb;
    $table = sl_lucie_index_table();
    $wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

    $types = array_merge( sl_lucie_index_public_post_types(), [ 'nav_menu_item' ] );
    $ids = get_posts( [
        'post_type'              => array_values( array_unique( $types ) ),
        'post_status'            => 'publish',
        'posts_per_page'         => -1,
        'fields'                 => 'ids',
        'orderby'                => 'ID',
        'order'                  => 'ASC',
        'no_found_rows'          => true,
        'suppress_filters'       => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ] );
    foreach ( $ids as $id ) sl_lucie_index_post( $id );
    update_option( 'sl_lucie_index_dirty', '0', false );
    update_option( 'sl_lucie_index_last_rebuild', current_time( 'mysql' ), false );
    return count( $ids );
}

/** Synchronisation incrementale apres une modification editoriale. */
add_action( 'save_post', function ( $post_id, $post ) {
    if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;
    if ( $post instanceof WP_Post ) sl_lucie_index_post( $post_id );
}, 50, 2 );
add_action( 'before_delete_post', 'sl_lucie_index_delete_post' );
add_action( 'set_object_terms', function ( $object_id ) {
    sl_lucie_index_post( $object_id );
}, 50, 1 );
function sl_lucie_index_meta_changed( $meta_id, $object_id, $meta_key ) {
    $watched = [
        '_elementor_data', '_slfd_internal_page', '_thumbnail_id',
        '_menu_item_url', '_menu_item_object_id', '_menu_item_type',
        '_price', '_regular_price', '_sale_price', '_stock', '_stock_status',
    ];
    if ( in_array( $meta_key, $watched, true ) ) sl_lucie_index_post( $object_id );
}
add_action( 'added_post_meta', 'sl_lucie_index_meta_changed', 50, 3 );
add_action( 'updated_post_meta', 'sl_lucie_index_meta_changed', 50, 3 );
add_action( 'deleted_post_meta', 'sl_lucie_index_meta_changed', 50, 3 );

function sl_lucie_index_normalize( $text ) {
    return mb_strtolower( remove_accents( wp_strip_all_tags( (string) $text ) ) );
}

/** Recherche ponderee dans l'index complet. */
function sl_lucie_search_site_index( $query, $limit = 8 ) {
    $query = trim( sanitize_text_field( (string) $query ) );
    if ( $query === '' ) return [ 'resultats' => [], 'note' => 'Requete vide.' ];
    sl_lucie_index_maybe_install();

    global $wpdb;
    $table = sl_lucie_index_table();
    $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    if ( $count === 0 || get_option( 'sl_lucie_index_dirty', '0' ) === '1' ) {
        sl_lucie_rebuild_site_index();
        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    $normalized = sl_lucie_index_normalize( $query );
    $tokens = preg_split( '/[^\p{L}\p{N}]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY );
    $tokens = array_values( array_unique( array_filter( $tokens, fn( $token ) => mb_strlen( $token ) >= 2 ) ) );
    $terms  = array_slice( array_values( array_unique( array_merge( [ $query ], $tokens ) ) ), 0, 10 );

    $where = [];
    $args  = [];
    foreach ( $terms as $term ) {
        $like = '%' . $wpdb->esc_like( $term ) . '%';
        $where[] = '(title LIKE %s OR content LIKE %s OR url LIKE %s)';
        array_push( $args, $like, $like, $like );
    }
    if ( ! $where ) return [ 'resultats' => [] ];

    $sql = "SELECT object_id, source, title, content, url, image, updated FROM {$table} WHERE " . implode( ' OR ', $where ) . ' LIMIT 120';
    $rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    $scored = [];
    foreach ( (array) $rows as $row ) {
        $title   = sl_lucie_index_normalize( $row['title'] );
        $content = sl_lucie_index_normalize( $row['content'] );
        $url     = sl_lucie_index_normalize( $row['url'] );
        $score   = 0;
        if ( str_contains( $title, $normalized ) ) $score += 160;
        if ( str_contains( $content, $normalized ) ) $score += 65;
        if ( str_contains( $url, $normalized ) ) $score += 35;
        foreach ( $tokens as $token ) {
            if ( str_contains( $title, $token ) ) $score += 38;
            if ( str_contains( $content, $token ) ) $score += 9;
            if ( str_contains( $url, $token ) ) $score += 6;
        }
        if ( $row['source'] === 'page' ) $score += 8;
        $row['score'] = $score;
        $scored[] = $row;
    }
    usort( $scored, fn( $a, $b ) => $b['score'] <=> $a['score'] );

    $results = [];
    foreach ( array_slice( $scored, 0, max( 1, min( 12, (int) $limit ) ) ) as $row ) {
        $plain = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $row['content'] ) ) );
        $results[] = [
            'titre'   => $row['title'],
            'type'    => $row['source'],
            'extrait' => mb_substr( $plain, 0, 900 ),
            'url'     => $row['url'],
            'image'   => $row['image'],
        ];
    }
    return [
        'resultats'       => $results,
        'documents_index' => $count,
        'mis_a_jour'      => get_option( 'sl_lucie_index_last_rebuild', '' ),
    ];
}
