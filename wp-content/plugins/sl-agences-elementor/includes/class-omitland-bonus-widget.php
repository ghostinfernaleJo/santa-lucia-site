<?php
/** Widget Elementor pour la campagne Bonus OMITLAND. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Widget_Base;

class SL_Omitland_Bonus_Widget extends Widget_Base {
	public function get_name() { return 'sl_omitland_bonus'; }
	public function get_title() { return __( 'Bonus OMITLAND', 'sl-agences' ); }
	public function get_icon() { return 'eicon-gift'; }
	public function get_categories() { return array( 'santa-lucia' ); }
	public function get_keywords() { return array( 'omitland', 'bonus', 'réalité virtuelle', 'promotion', 'points' ); }
	public function get_style_depends() { return array( 'sl-omtland-bonus' ); }
	public function has_widget_inner_wrapper(): bool { return false; }

	protected function render() {
		echo do_shortcode( '[sl_omtland_bonus]' );
	}
}
