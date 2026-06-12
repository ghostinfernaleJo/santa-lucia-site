<?php
/**
 * Désactive complètement les commentaires sur le site.
 * - Aucun nouveau commentaire accepté (anciens + nouveaux contenus).
 * - Aucun formulaire ni affichage de commentaires en front.
 * - L'admin « Commentaires » est CONSERVÉ pour garder l'accès aux commentaires
 *   existants (preuves, ex. signalements). Les commentaires ne sont pas supprimés.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* Fermer les commentaires et pings partout (force, quel que soit le réglage stocké). */
add_filter( 'comments_open', '__return_false', 20 );
add_filter( 'pings_open',    '__return_false', 20 );

/* Ne plus afficher les commentaires existants côté public. */
add_filter( 'comments_array', '__return_empty_array', 20 );

/* Retirer le support des commentaires/trackbacks de tous les types de contenu. */
add_action( 'init', function () {
    foreach ( get_post_types() as $pt ) {
        if ( post_type_supports( $pt, 'comments' ) )   remove_post_type_support( $pt, 'comments' );
        if ( post_type_supports( $pt, 'trackbacks' ) ) remove_post_type_support( $pt, 'trackbacks' );
    }
}, 100 );

/* Réglage par défaut « fermé » pour tout nouveau contenu. */
add_action( 'init', function () {
    if ( get_option( 'default_comment_status' ) !== 'closed' ) update_option( 'default_comment_status', 'closed' );
    if ( get_option( 'default_ping_status' ) !== 'closed' )    update_option( 'default_ping_status', 'closed' );
} );

/* Retirer l'icône « Commentaires » de la barre d'admin (cosmétique). */
add_action( 'wp_before_admin_bar_render', function () {
    global $wp_admin_bar;
    if ( is_object( $wp_admin_bar ) ) $wp_admin_bar->remove_menu( 'comments' );
} );
