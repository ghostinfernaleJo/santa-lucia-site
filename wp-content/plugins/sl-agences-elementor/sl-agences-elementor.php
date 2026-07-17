<?php
/**
 * Plugin Name: Santa Lucia - Widgets Elementor
 * Plugin URI:  https://lecomplexesantalucia.com
 * Description: Widgets Elementor personnalisés + Système Bons Plans multi-agences (18 responsables).
 * Version:     3.0.3
 * Author:      Santa Lucia
 * Author URI:  https://lecomplexesantalucia.com
 * Text Domain: sl-agences
 * Requires Plugins: elementor
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SL_AGENCES_VERSION', '3.3.9' );
define( 'SL_AGENCES_PATH', plugin_dir_path( __FILE__ ) );
define( 'SL_AGENCES_URL', plugin_dir_url( __FILE__ ) );

/* ============================================================
 *  SYSTÈME BONS PLANS MULTI-AGENCES
 * ============================================================ */
require_once SL_AGENCES_PATH . 'includes/cpt-bon-plan.php';
require_once SL_AGENCES_PATH . 'includes/roles-responsable.php';
require_once SL_AGENCES_PATH . 'includes/admin-responsable.php';
require_once SL_AGENCES_PATH . 'includes/admin-columns.php';
require_once SL_AGENCES_PATH . 'includes/admin-metaboxes.php';
require_once SL_AGENCES_PATH . 'includes/security-tweaks.php';
require_once SL_AGENCES_PATH . 'includes/admin-import.php';
require_once SL_AGENCES_PATH . 'includes/cpt-campagne-woo.php';
require_once SL_AGENCES_PATH . 'includes/admin-import-woo.php';
require_once SL_AGENCES_PATH . 'includes/admin-add-product-woo.php';
require_once SL_AGENCES_PATH . 'includes/ai-providers.php';
require_once SL_AGENCES_PATH . 'includes/admin-settings-ai.php';
require_once SL_AGENCES_PATH . 'includes/admin-magic-import.php';
require_once SL_AGENCES_PATH . 'includes/rest-api-mobile.php';
require_once SL_AGENCES_PATH . 'includes/feedback-module.php';
require_once SL_AGENCES_PATH . 'includes/disable-comments.php';
require_once SL_AGENCES_PATH . 'includes/pdf-bons-plans.php';
require_once SL_AGENCES_PATH . 'includes/bon-plan-cart.php';
require_once SL_AGENCES_PATH . 'includes/supervision-agences.php';

// Force single template for Bons Plans
add_filter( 'template_include', 'sl_bp_single_template' );
function sl_bp_single_template( $template ) {
    if ( is_singular( 'sl_bon_plan' ) ) {
        $custom_template = SL_AGENCES_PATH . 'templates/single-bon-plan.php';
        if ( file_exists( $custom_template ) ) {
            return $custom_template;
        }
    }
    return $template;
}

/* ============================================================
 *  1. Vérifier qu'Elementor est actif
 * ============================================================ */
function sl_agences_check_elementor() {
    if ( ! did_action( 'elementor/loaded' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-warning is-dismissible"><p><strong>Santa Lucia - Widgets Elementor</strong> nécessite le plugin <strong>Elementor</strong> pour fonctionner.</p></div>';
        });
        return false;
    }
    return true;
}

/* ============================================================
 *  2. Charger les assets CSS / JS sur le front
 * ============================================================ */
add_action( 'wp_enqueue_scripts', 'sl_agences_front_assets', 100 );

/* Exclure le JS du slider immersion du combine/minify LiteSpeed.
 * Le hero ne dépend ainsi plus d'un bundle global combiné qui, s'il est
 * périmé/mal régénéré, casse le slider. GSAP n'étant utilisé que dans le
 * handler DOMContentLoaded du slider, il reste disponible à temps. */
add_filter( 'script_loader_tag', 'sl_immersion_no_optimize', 10, 2 );
function sl_immersion_no_optimize( $tag, $handle ) {
    if ( 'sl-immersion-slider' === $handle && false === strpos( $tag, 'data-no-optimize' ) ) {
        $tag = str_replace( ' src=', ' data-no-optimize="1" src=', $tag );
    }
    return $tag;
}

function sl_agences_front_assets() {
    // Styles globaux À Propos (partagés S2-S8)
    wp_enqueue_style(
        'sl-apropos-global',
        SL_AGENCES_URL . 'assets/css/apropos-global.css',
        [],
        SL_AGENCES_VERSION
    );

    // Widget "Nos Agences"
    wp_enqueue_style(
        'sl-agences-widget',
        SL_AGENCES_URL . 'assets/css/agences-elementor.css',
        [],
        SL_AGENCES_VERSION
    );
    wp_enqueue_script(
        'sl-agences-widget',
        SL_AGENCES_URL . 'assets/js/agences-elementor.js',
        [],
        SL_AGENCES_VERSION,
        true
    );

    // Widget "Fiche Agence"
    wp_enqueue_style(
        'sl-fiche-agence',
        SL_AGENCES_URL . 'assets/css/fiche-agence.css',
        [],
        SL_AGENCES_VERSION
    );
    wp_enqueue_script(
        'sl-fiche-agence',
        SL_AGENCES_URL . 'assets/js/fiche-agence.js',
        [],
        SL_AGENCES_VERSION,
        true
    );

    // Widget "Immersion Slider"
    wp_enqueue_style(
        'sl-immersion-slider',
        SL_AGENCES_URL . 'assets/css/immersion-slider.css',
        [],
        SL_AGENCES_VERSION
    );
    wp_enqueue_script(
        'gsap',
        'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js',
        [],
        '3.12.2',
        true
    );
    wp_enqueue_script(
        'sl-immersion-slider',
        SL_AGENCES_URL . 'assets/js/immersion-slider-v2.js',
        ['gsap'],
        SL_AGENCES_VERSION,
        true
    );

    // Widget "Bons Plans"
    wp_enqueue_style(
        'sl-bons-plans',
        SL_AGENCES_URL . 'assets/css/bons-plans-v3f.css',
        [],
        SL_AGENCES_VERSION
    );
    // Fix scroll : 'overflow-x:hidden' sur <html> force overflow-y:auto et casse le scroll du viewport.
    // 'clip' clippe le débordement horizontal SANS créer de conteneur de scroll. !important neutralise aussi la règle 'html,body{overflow-x:hidden}' de bons-plans-v3.css.
    wp_add_inline_style( 'sl-bons-plans', 'html,body{overflow-x:clip!important;overflow-y:visible!important;max-width:100%}' );
    wp_enqueue_script(
        'sl-bons-plans',
        SL_AGENCES_URL . 'assets/js/bons-plans-v3y.js',
        [],
        SL_AGENCES_VERSION,
        true
    );

    // Widget "Hero Espaces"
    wp_enqueue_style(
        'sl-hero-espaces',
        SL_AGENCES_URL . 'assets/css/hero-espaces.css',
        [],
        SL_AGENCES_VERSION
    );

    // Widget "Grille Découverte"
    wp_enqueue_style(
        'sl-grille-decouverte',
        SL_AGENCES_URL . 'assets/css/grille-decouverte.css',
        [],
        SL_AGENCES_VERSION
    );

    // Widget "Section Espace"
    wp_enqueue_style(
        'sl-section-espace',
        SL_AGENCES_URL . 'assets/css/section-espace.css',
        [],
        SL_AGENCES_VERSION
    );
    wp_enqueue_script(
        'sl-section-espace',
        SL_AGENCES_URL . 'assets/js/section-espace.js',
        [],
        SL_AGENCES_VERSION,
        true
    );

    // Hero À Propos
    wp_enqueue_style(
        'sl-hero-apropos',
        SL_AGENCES_URL . 'assets/css/hero-apropos.css',
        [],
        SL_AGENCES_VERSION
    );

    // Widget "Histoire"
    wp_enqueue_style(
        'sl-histoire',
        SL_AGENCES_URL . 'assets/css/histoire.css',
        [],
        SL_AGENCES_VERSION
    );

    // Widget "Services"
    wp_enqueue_style(
        'sl-services',
        SL_AGENCES_URL . 'assets/css/services.css',
        [],
        SL_AGENCES_VERSION
    );

    // Widget "Produits Maison"
    wp_enqueue_style(
        'sl-produits-maison',
        SL_AGENCES_URL . 'assets/css/produits-maison.css',
        [],
        SL_AGENCES_VERSION
    );

    // Widget "Espaces Mosaïque"
    wp_enqueue_style(
        'sl-espaces-mosaic',
        SL_AGENCES_URL . 'assets/css/espaces-mosaic.css',
        [],
        SL_AGENCES_VERSION
    );

    // Widget "Engagements"
    wp_enqueue_style(
        'sl-engagements',
        SL_AGENCES_URL . 'assets/css/engagements.css',
        [],
        SL_AGENCES_VERSION
    );

    // Widget "Implantations"
    wp_enqueue_style(
        'sl-implantations',
        SL_AGENCES_URL . 'assets/css/implantations.css',
        [],
        SL_AGENCES_VERSION
    );

    // Widget "CTA Final"
    wp_enqueue_style(
        'sl-cta-final',
        SL_AGENCES_URL . 'assets/css/cta-final.css',
        [],
        SL_AGENCES_VERSION
    );

    // Page Produits Maison (design v2) + widgets associés
    wp_enqueue_style(
        'sl-produits-maison-page',
        SL_AGENCES_URL . 'assets/css/produits-maison-page.css',
        [],
        SL_AGENCES_VERSION
    );
    wp_enqueue_script(
        'sl-nav-produits',
        SL_AGENCES_URL . 'assets/js/nav-produits.js',
        [],
        SL_AGENCES_VERSION,
        true
    );
}



/* ============================================================
 *  3. Charger le CSS dans l'éditeur Elementor (preview)
 * ============================================================ */
add_action( 'elementor/editor/after_enqueue_styles', 'sl_agences_editor_css' );
function sl_agences_editor_css() {
    wp_enqueue_style(
        'sl-apropos-global',
        SL_AGENCES_URL . 'assets/css/apropos-global.css',
        [],
        SL_AGENCES_VERSION
    );
    wp_enqueue_style(
        'sl-agences-widget',
        SL_AGENCES_URL . 'assets/css/agences-elementor.css',
        [],
        SL_AGENCES_VERSION
    );
    wp_enqueue_style(
        'sl-fiche-agence',
        SL_AGENCES_URL . 'assets/css/fiche-agence.css',
        [],
        SL_AGENCES_VERSION
    );
    wp_enqueue_style(
        'sl-immersion-slider',
        SL_AGENCES_URL . 'assets/css/immersion-slider.css',
        [],
        SL_AGENCES_VERSION
    );
    wp_enqueue_style(
        'sl-bons-plans',
        SL_AGENCES_URL . 'assets/css/bons-plans-v3f.css',
        [],
        SL_AGENCES_VERSION
    );
    wp_enqueue_style(
        'sl-section-espace',
        SL_AGENCES_URL . 'assets/css/section-espace.css',
        [],
        SL_AGENCES_VERSION
    );
    wp_enqueue_style(
        'sl-hero-espaces',
        SL_AGENCES_URL . 'assets/css/hero-espaces.css',
        [],
        SL_AGENCES_VERSION
    );
    wp_enqueue_style(
        'sl-grille-decouverte',
        SL_AGENCES_URL . 'assets/css/grille-decouverte.css',
        [],
        SL_AGENCES_VERSION
    );
    wp_enqueue_style(
        'sl-histoire',
        SL_AGENCES_URL . 'assets/css/histoire.css',
        [],
        SL_AGENCES_VERSION
    );
    wp_enqueue_style(
        'sl-services',
        SL_AGENCES_URL . 'assets/css/services.css',
        [],
        SL_AGENCES_VERSION
    );
    wp_enqueue_style(
        'sl-produits-maison',
        SL_AGENCES_URL . 'assets/css/produits-maison.css',
        [],
        SL_AGENCES_VERSION
    );
    wp_enqueue_style(
        'sl-espaces-mosaic',
        SL_AGENCES_URL . 'assets/css/espaces-mosaic.css',
        [],
        SL_AGENCES_VERSION
    );
    wp_enqueue_style(
        'sl-engagements',
        SL_AGENCES_URL . 'assets/css/engagements.css',
        [],
        SL_AGENCES_VERSION
    );
    wp_enqueue_style(
        'sl-implantations',
        SL_AGENCES_URL . 'assets/css/implantations.css',
        [],
        SL_AGENCES_VERSION
    );
    wp_enqueue_style(
        'sl-cta-final',
        SL_AGENCES_URL . 'assets/css/cta-final.css',
        [],
        SL_AGENCES_VERSION
    );
}

/* ============================================================
 *  4. Charger le JS dans le preview Elementor
 * ============================================================ */
add_action( 'elementor/preview/enqueue_scripts', 'sl_agences_preview_js' );
function sl_agences_preview_js() {
    wp_enqueue_script(
        'sl-agences-widget',
        SL_AGENCES_URL . 'assets/js/agences-elementor.js',
        [],
        SL_AGENCES_VERSION,
        true
    );
    wp_enqueue_script(
        'sl-fiche-agence',
        SL_AGENCES_URL . 'assets/js/fiche-agence.js',
        [],
        SL_AGENCES_VERSION,
        true
    );
    wp_enqueue_script(
        'gsap',
        'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js',
        [],
        '3.12.2',
        true
    );
    wp_enqueue_script(
        'sl-immersion-slider',
        SL_AGENCES_URL . 'assets/js/immersion-slider-v2.js',
        ['gsap'],
        SL_AGENCES_VERSION,
        true
    );
}

/* ============================================================
 *  5. Créer la catégorie Elementor "Santa Lucia"
 * ============================================================ */
add_action( 'elementor/elements/categories_registered', 'sl_agences_register_category' );
function sl_agences_register_category( $elements_manager ) {
    $elements_manager->add_category(
        'santa-lucia',
        [
            'title' => __( '🌺 Santa Lucia', 'sl-agences' ),
            'icon'  => 'fa fa-home',
        ]
    );
}

/* ============================================================
 *  6. Enregistrer les Widgets
 * ============================================================ */
add_action( 'elementor/widgets/register', 'sl_agences_register_widgets' );
function sl_agences_register_widgets( $widgets_manager ) {
    if ( ! sl_agences_check_elementor() ) return;

    // Widget 1 : Nos Agences (liste/grille)
    require_once SL_AGENCES_PATH . 'includes/class-agences-widget.php';
    $widgets_manager->register( new SL_Agences_Widget() );

    // Widget 2 : Fiche Agence (détail, galerie, horaires)
    require_once SL_AGENCES_PATH . 'includes/class-fiche-agence-widget.php';
    $widgets_manager->register( new SL_Fiche_Agence_Widget() );

    // Widget 3 : Immersion Slider (Storytelling)
    require_once SL_AGENCES_PATH . 'includes/class-immersion-slider-widget.php';
    $widgets_manager->register( new SL_Immersion_Slider_Widget() );

    // Widget 4 : Bons Plans (multi-agences, dynamique)
    require_once SL_AGENCES_PATH . 'includes/class-bons-plans-widget.php';
    $widgets_manager->register( new SL_Bons_Plans_Widget() );

    // Widget 4b : Bons Plans — Carrousel (swipe + fleches + autoplay)
    require_once SL_AGENCES_PATH . 'includes/class-bons-plans-carousel-widget.php';
    $widgets_manager->register( new SL_Bons_Plans_Carousel_Widget() );

    // Widget 5 : Gestion des images des produits maison
    require_once SL_AGENCES_PATH . 'includes/class-produits-images-widget.php';
    $widgets_manager->register( new SL_Product_Category_Images_Widget() );
    $widgets_manager->register( new SL_Product_Item_Images_Widget() );

    // Widget 6 : Pages complètes éditables
    require_once SL_AGENCES_PATH . 'includes/class-produits-pages-widgets.php';
    $widgets_manager->register( new SL_Apropos_Complete_Widget() );
    $widgets_manager->register( new SL_Produits_Maison_Complete_Widget() );

    // Widget 7 : Hero Espaces
    require_once SL_AGENCES_PATH . 'includes/class-hero-espaces-widget.php';
    $widgets_manager->register( new SL_Hero_Espaces_Widget() );

    // Widget 8 : Grille Découverte
    require_once SL_AGENCES_PATH . 'includes/class-grille-decouverte-widget.php';
    $widgets_manager->register( new SL_Grille_Decouverte_Widget() );

    // Widget 9 : Section Espace (galerie masonry + en-tête compact)
    require_once SL_AGENCES_PATH . 'includes/class-section-espace-widget.php';
    $widgets_manager->register( new SL_Section_Espace_Widget() );

    // Widget 10 : Hero À Propos
    require_once SL_AGENCES_PATH . 'includes/class-hero-apropos-widget.php';
    $widgets_manager->register( new SL_Hero_Apropos_Widget() );

    // Widget 11 : Histoire
    require_once SL_AGENCES_PATH . 'includes/class-histoire-widget.php';
    $widgets_manager->register( new SL_Histoire_Widget() );

    // Widget 12 : Services
    require_once SL_AGENCES_PATH . 'includes/class-services-widget.php';
    $widgets_manager->register( new SL_Services_Widget() );

    // Widget 13 : Produits Maison
    require_once SL_AGENCES_PATH . 'includes/class-produits-maison-widget.php';
    $widgets_manager->register( new SL_Produits_Maison_Widget() );

    // Widget 14 : Espaces Mosaïque
    require_once SL_AGENCES_PATH . 'includes/class-espaces-mosaic-widget.php';
    $widgets_manager->register( new SL_Espaces_Mosaic_Widget() );

    // Widget 15 : Engagements
    require_once SL_AGENCES_PATH . 'includes/class-engagements-widget.php';
    $widgets_manager->register( new SL_Engagements_Widget() );

    // Widget 16 : Implantations
    require_once SL_AGENCES_PATH . 'includes/class-implantations-widget.php';
    $widgets_manager->register( new SL_Implantations_Widget() );

    // Widget 17 : CTA Final
    require_once SL_AGENCES_PATH . 'includes/class-cta-final-widget.php';
    $widgets_manager->register( new SL_CTA_Final_Widget() );

    // Widget 18 : Hero Produits Maison
    require_once SL_AGENCES_PATH . 'includes/class-hero-produits-widget.php';
    $widgets_manager->register( new SL_Hero_Produits_Widget() );

    // Widget 19 : Nav Catégories Produits
    require_once SL_AGENCES_PATH . 'includes/class-nav-produits-widget.php';
    $widgets_manager->register( new SL_Nav_Produits_Widget() );

    // Widget 20 : Catégorie Produits (une instance par famille)
    require_once SL_AGENCES_PATH . 'includes/class-categorie-produits-widget.php';
    $widgets_manager->register( new SL_Categorie_Produits_Widget() );
}
