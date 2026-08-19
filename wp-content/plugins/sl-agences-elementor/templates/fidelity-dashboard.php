<?php
defined( 'ABSPATH' ) || exit;
$screen = get_post_meta( get_queried_object_id(), '_slfd_internal_page', true );
?><!doctype html>
<html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class( 'slfd-page' ); ?>><?php wp_body_open(); ?>
<?php
if ( ! is_user_logged_in() ) {
    echo slfd_render_login(); // phpcs:ignore WordPress.Security.EscapeOutput
} elseif ( ! slfd_can_access() ) {
    echo slfd_render_access_denied(); // phpcs:ignore WordPress.Security.EscapeOutput
} elseif ( 'report' === $screen ) {
    echo slfd_render_report_form(); // phpcs:ignore WordPress.Security.EscapeOutput
} elseif ( 'supply' === $screen && slfd_can_supply() ) {
    echo slfd_render_supply_form(); // phpcs:ignore WordPress.Security.EscapeOutput
} elseif ( 'supply' === $screen ) {
    echo slfd_render_access_denied(); // phpcs:ignore WordPress.Security.EscapeOutput
} else {
    echo slfd_render_dashboard(); // phpcs:ignore WordPress.Security.EscapeOutput
}
wp_footer();
?></body></html>
