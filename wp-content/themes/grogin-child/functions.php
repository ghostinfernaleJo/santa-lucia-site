<?php

/**s
 * functions.php
 * @package WordPress
 * @subpackage Grogin
 * @since Grogin 1.0
 * 
 */

add_action( 'wp_enqueue_scripts', 'grogin_enqueue_styles', 99 );
function grogin_enqueue_styles() {
    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
    wp_style_add_data( 'parent-style', 'rtl', 'replace' );
    wp_enqueue_style( 'child-style', get_stylesheet_directory_uri() . '/style.css', array( 'parent-style' ) );
}

/**
 * Fix : off-canvas drawers (width:100% fixed) causent un scroll horizontal sur mobile.
 * Inline pour contourner le cache Varnish sur les assets CSS.
 */
add_action( 'wp_head', function() {
    echo '<style>html{overflow-x:hidden}</style>';
}, 99 );

/**
 * Keep the custom WhatsApp button intact and shorten only its visible label on product pages.
 */
add_action( 'template_redirect', 'sl_single_product_whatsapp_label_buffer' );
function sl_single_product_whatsapp_label_buffer() {
    if ( function_exists( 'is_product' ) && is_product() && ! is_admin() ) {
        ob_start( 'sl_single_product_whatsapp_label' );
    }
}

function sl_single_product_whatsapp_label( $html ) {
    return str_replace( 'Commander sur WhatsApp', 'Commander', $html );
}

/**
 * Remplace les images app footer du démo klbtheme.com par les copies locales.
 * Approche output buffer = robuste peu importe le format stocké dans le theme mod.
 */
add_action( 'template_redirect', 'sl_footer_app_images_ob_start', 1 );
function sl_footer_app_images_ob_start() {
    if ( is_admin() ) return;
    ob_start( 'sl_footer_app_images_replace' );
}

function sl_footer_app_images_replace( $html ) {
    $replacements = array(
        'https://klbtheme.com/grogin/wp-content/uploads/2023/11/google-play-button-dark.png'
            => 'https://complexesantalucia.com/wp-content/uploads/2026/06/google-play-button-dark.png',
        'https://klbtheme.com/grogin/wp-content/uploads/2023/10/apple-store-button-dark.png'
            => 'https://complexesantalucia.com/wp-content/uploads/2026/06/apple-store-button-dark.png',
    );
    return str_replace(
        array_keys( $replacements ),
        array_values( $replacements ),
        $html
    );
}
