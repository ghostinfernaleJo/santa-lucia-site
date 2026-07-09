<?php
/**
 * Plugin Name: Santa Lucia Fast Food
 * Description: Menu du jour par agence avec planning hebdomadaire, import CSV/Excel, promotions et partage.
 * Version:     1.8.9
 * Author:      Santa Lucia
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SL_FF_VERSION', '1.8.9' );
define( 'SL_FF_PATH',    plugin_dir_path( __FILE__ ) );
define( 'SL_FF_URL',     plugin_dir_url(  __FILE__ ) );

require_once SL_FF_PATH . 'includes/cpt-repas.php';
require_once SL_FF_PATH . 'includes/roles-fastfood.php';
require_once SL_FF_PATH . 'includes/ajax-fastfood.php';
require_once SL_FF_PATH . 'includes/admin-fastfood.php';
require_once SL_FF_PATH . 'includes/import-fastfood.php';
require_once SL_FF_PATH . 'includes/sync-agences-ff.php';
require_once SL_FF_PATH . 'includes/admin-images-ff.php';
require_once SL_FF_PATH . 'includes/promos-fastfood.php';
require_once SL_FF_PATH . 'includes/shortcode-fastfood.php';
require_once SL_FF_PATH . 'includes/profile-field-ff.php';

add_action( 'wp_enqueue_scripts', 'sl_ff_front_assets' );
function sl_ff_front_assets() {
    $needs_assets = is_singular();
    if ( $needs_assets ) {
        $post = get_post();
        $content = $post ? (string) $post->post_content : '';
        $elementor_data = $post ? (string) get_post_meta( $post->ID, '_elementor_data', true ) : '';
        $needs_assets = has_shortcode( $content, 'sl_fastfood_menu' )
            || has_shortcode( $content, 'sl_fastfood_browser' )
            || has_shortcode( $content, 'elementor-template' )
            || false !== strpos( $content, 'sl_fiche_agence' )
            || false !== strpos( $elementor_data, 'sl_fiche_agence' );
    }

    if ( ! $needs_assets && function_exists( 'is_elementor_preview' ) && is_elementor_preview() ) {
        $needs_assets = true;
    }

    if ( ! $needs_assets ) {
        return;
    }

    $css_ver = @filemtime( SL_FF_PATH . 'assets/css/fastfood-front.css' ) ?: SL_FF_VERSION;
    $js_ver  = @filemtime( SL_FF_PATH . 'assets/js/fastfood-front.js'  ) ?: SL_FF_VERSION;
    wp_enqueue_style(  'sl-ff-front', SL_FF_URL . 'assets/css/fastfood-front.css', [], $css_ver );
    wp_enqueue_script( 'sl-ff-front', SL_FF_URL . 'assets/js/fastfood-front.js', [], $js_ver, true );
    wp_localize_script( 'sl-ff-front', 'slFFAjax', [
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'sl_ff_get_menu' ),
    ] );
}
