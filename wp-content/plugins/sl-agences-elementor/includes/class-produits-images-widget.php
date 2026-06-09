<?php
if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if ( ! function_exists( 'sl_pm_product_categories' ) ) {
    function sl_pm_product_categories() {
        return [
            'spaghettis' => [
                'label' => 'Spaghettis',
                'control' => 'cat_spaghettis_image',
            ],
            'farines' => [
                'label' => 'Farines',
                'control' => 'cat_farines_image',
            ],
            'chips-apero' => [
                'label' => 'Chips & Apéro',
                'control' => 'cat_chips_apero_image',
            ],
            'glaces' => [
                'label' => 'Glaces',
                'control' => 'cat_glaces_image',
            ],
            'pates-a-tartiner-chocojoy' => [
                'label' => 'Pâtes à tartiner Chocojoy',
                'control' => 'cat_chocojoy_image',
            ],
            'autres' => [
                'label' => 'Autres produits Chocojoy',
                'control' => 'cat_autres_image',
            ],
        ];
    }
}

if ( ! function_exists( 'sl_pm_product_image_controls' ) ) {
    function sl_pm_product_image_controls() {
        return [
            'spaghetti_santa_lucia_250g' => 'Spaghetti Santa Lucia 250g',
            'spaghetti_santa_lucia_500g' => 'Spaghetti Santa Lucia 500g',
            'spaghetti_omit_250g' => 'Spaghetti Omit 250g',
            'spaghetti_omit_500g' => 'Spaghetti Omit 500g',
            'farine_amira_1kg' => 'Farine Amira tout usage 1kg',
            'farine_amira_2kg' => 'Farine Amira tout usage 2kg',
            'farine_la_fleur_blanche' => 'Farine La fleur Blanche',
            'farine_mami_lou_1kg' => 'Farine Mami Lou 1kg',
            'farine_mami_lou_2kg' => 'Farine Mami Lou 2kg',
            'farine_mami_lou_5kg' => 'Farine Mami Lou 5kg',
            'farine_mami_lou_25kg' => 'Farine Mami Lou 25kg',
            'apero_chips_nature' => 'Apéro Chips Nature',
            'apero_chips_sucre' => 'Apéro Chips Sucré',
            'fiesta_chips_banane_sucre' => 'Fiesta Chips Banane Sucré',
            'fiesta_chips_banane_sale' => 'Fiesta Chips Banane Salé',
            'fiesta_chips_pommes_nature' => 'Fiesta Chips Pommes Nature',
            'fiesta_chips_pommes_poulet_braise' => 'Fiesta Chips Pommes Poulet Braisé',
            'fiesta_chips_pommes_poulet_epice' => 'Fiesta Chips Pommes Poulet Epicé',
            'pop_corn_apero_120g' => 'Pop Corn Apéro 120G',
            'glace_la_fiesta_coconut_200ml' => 'Glace La Fiesta Coconut 200ML',
            'glace_la_fiesta_vanille_200ml' => 'Glace La Fiesta Vanille 200ML',
            'glace_la_fiesta_choco_200ml' => 'Glace La Fiesta Choco 200ML',
            'glace_la_fiesta_fraise_200ml' => 'Glace La Fiesta Fraise 200ML',
            'pate_chocojoy_200g' => 'Pâte à tartiner Chocojoy 200G',
            'pate_chocojoy_450g' => 'Pâte à tartiner Chocojoy 450G',
            'pate_chocojoy_800g' => 'Pâte à tartiner Chocojoy 800G',
            'pate_chocojoy_1kg' => 'Pâte à tartiner Chocojoy 1kg',
            'pate_chocojoy_2_8kg' => 'Pâte à tartiner Chocojoy 2.8kg',
            'pate_chocojoy_4_5kg' => 'Pâte à tartiner Chocojoy 4.5kg',
            'pate_chocojoy_10kg' => 'Pâte à tartiner Chocojoy 10kg',
            'sachet_dejeuner_lacte_chocojoy_20g' => 'Sachet Déjeuner Lacté Chocojoy 20G',
            'sachet_dejeuner_lacte_chocojoy_35g' => 'Sachet Déjeuner Lacté Chocojoy 35G',
        ];
    }
}

if ( ! function_exists( 'sl_pm_find_elementor_widget_settings' ) ) {
    function sl_pm_find_elementor_widget_settings( $post_id, $widget_type ) {
        $data = get_post_meta( $post_id, '_elementor_data', true );
        if ( empty( $data ) ) {
            return [];
        }

        $elements = json_decode( $data, true );
        if ( ! is_array( $elements ) ) {
            return [];
        }

        $walk = static function( $items ) use ( &$walk, $widget_type ) {
            foreach ( $items as $item ) {
                if ( isset( $item['widgetType'] ) && $item['widgetType'] === $widget_type ) {
                    return isset( $item['settings'] ) && is_array( $item['settings'] ) ? $item['settings'] : [];
                }
                if ( ! empty( $item['elements'] ) && is_array( $item['elements'] ) ) {
                    $found = $walk( $item['elements'] );
                    if ( ! empty( $found ) ) {
                        return $found;
                    }
                }
            }
            return [];
        };

        return $walk( $elements );
    }
}

if ( ! function_exists( 'sl_pm_elementor_image_url' ) ) {
    function sl_pm_elementor_image_url( $post_id, $widget_type, $control_key, $fallback = '' ) {
        $settings = sl_pm_find_elementor_widget_settings( $post_id, $widget_type );
        if ( empty( $settings[ $control_key ] ) || ! is_array( $settings[ $control_key ] ) ) {
            return $fallback;
        }

        if ( ! empty( $settings[ $control_key ]['id'] ) ) {
            $url = wp_get_attachment_image_url( (int) $settings[ $control_key ]['id'], 'large' );
            if ( $url ) {
                return $url;
            }
        }

        if ( ! empty( $settings[ $control_key ]['url'] ) ) {
            return esc_url_raw( $settings[ $control_key ]['url'] );
        }

        return $fallback;
    }
}

class SL_Product_Category_Images_Widget extends Widget_Base {
    public function get_name() { return 'sl_product_category_images'; }
    public function get_title() { return 'Images catégories produits'; }
    public function get_icon() { return 'eicon-gallery-grid'; }
    public function get_categories() { return [ 'santa-lucia' ]; }

    protected function register_controls() {
        $this->start_controls_section(
            'images_section',
            [
                'label' => 'Images des catégories',
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        foreach ( sl_pm_product_categories() as $category ) {
            $this->add_control(
                $category['control'],
                [
                    'label' => $category['label'],
                    'type'  => Controls_Manager::MEDIA,
                ]
            );
        }

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        echo '<div style="font-family:Arial,sans-serif;padding:18px;border:1px solid #e5e7eb;background:#fff">';
        echo '<strong>Images catégories produits</strong><p style="margin:8px 0 16px;color:#64748b">Modifiez ces images ici. Elles sont utilisées sur la section produits de la page À propos.</p>';
        echo '<div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px">';
        foreach ( sl_pm_product_categories() as $category ) {
            $url = ! empty( $settings[ $category['control'] ]['url'] ) ? $settings[ $category['control'] ]['url'] : '';
            echo '<div style="border:1px solid #e5e7eb;padding:10px;min-height:90px">';
            if ( $url ) {
                echo '<img src="' . esc_url( $url ) . '" alt="" style="width:100%;aspect-ratio:16/10;object-fit:cover;display:block;margin-bottom:8px">';
            }
            echo '<span style="font-size:12px;font-weight:700">' . esc_html( $category['label'] ) . '</span>';
            echo '</div>';
        }
        echo '</div></div>';
    }
}

class SL_Product_Item_Images_Widget extends Widget_Base {
    public function get_name() { return 'sl_product_item_images'; }
    public function get_title() { return 'Images produits maison'; }
    public function get_icon() { return 'eicon-products'; }
    public function get_categories() { return [ 'santa-lucia' ]; }

    protected function register_controls() {
        $this->start_controls_section(
            'images_section',
            [
                'label' => 'Images des produits',
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        foreach ( sl_pm_product_image_controls() as $key => $label ) {
            $this->add_control(
                'product_' . $key,
                [
                    'label' => $label,
                    'type'  => Controls_Manager::MEDIA,
                ]
            );
        }

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        echo '<div style="font-family:Arial,sans-serif;padding:18px;border:1px solid #e5e7eb;background:#fff">';
        echo '<strong>Images produits maison</strong><p style="margin:8px 0 16px;color:#64748b">Modifiez ces images ici. Elles sont utilisées sur la page Produits maison.</p>';
        echo '<div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px">';
        foreach ( sl_pm_product_image_controls() as $key => $label ) {
            $control = 'product_' . $key;
            $url = ! empty( $settings[ $control ]['url'] ) ? $settings[ $control ]['url'] : '';
            echo '<div style="border:1px solid #e5e7eb;padding:10px;min-height:110px">';
            if ( $url ) {
                echo '<img src="' . esc_url( $url ) . '" alt="" style="width:100%;aspect-ratio:4/3;object-fit:cover;display:block;margin-bottom:8px">';
            }
            echo '<span style="font-size:12px;font-weight:700">' . esc_html( $label ) . '</span>';
            echo '</div>';
        }
        echo '</div></div>';
    }
}
