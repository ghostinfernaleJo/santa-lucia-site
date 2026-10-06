<?php
/**
 * Plugin Name: Santa Lucia - Supervision Fidélité
 * Description: Ajoute le rôle Superviseur fidélité pour piloter les rapports quotidiens, leurs validations et les approvisionnements en cartes.
 * Version: 1.0.0
 * Author: Santa Lucia
 * Text Domain: sl-fidelity-supervision
 */

defined( 'ABSPATH' ) || exit;

const SLFD_SUPERVISOR_ROLE = 'sl_superviseur_fidelite';

function slfd_supervision_register_role() {
	$capabilities = array(
		'read'                     => true,
		'slfd_supervise_fidelity' => true,
	);
	$role = get_role( SLFD_SUPERVISOR_ROLE );
	if ( ! $role ) {
		add_role( SLFD_SUPERVISOR_ROLE, __( 'Superviseur fidélité', 'sl-fidelity-supervision' ), $capabilities );
		return;
	}

	foreach ( $capabilities as $capability => $grant ) {
		if ( $grant && ! $role->has_cap( $capability ) ) {
			$role->add_cap( $capability );
		}
	}
}
register_activation_hook( __FILE__, 'slfd_supervision_register_role' );
add_action( 'init', 'slfd_supervision_register_role', 4 );
