<?php
/**
 * RemoveHandler for AJAX group member removal
 */

namespace LABGENZ_CM\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RemoveHandler {
	public function __construct() {
		add_action( 'wp_ajax_labgenz_remove_group_member', [ $this, 'handle_remove_member' ] );
		add_action( 'wp_ajax_nopriv_labgenz_remove_group_member', [ $this, 'no_permission' ] );
	}

	public function handle_remove_member() {
		// Check nonce
		$nonce = $_POST['_ajax_nonce'] ?? '';
		if ( ! wp_verify_nonce( $nonce, 'labgenz_group_remove_nonce' ) ) {
			wp_send_json_error( __( 'Invalid security token.', 'labgenz-cm' ) );
		}

		$user_id      = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
		$group_id     = isset( $_POST['group_id'] ) ? intval( $_POST['group_id'] ) : 0;
		$current_user = get_current_user_id();

		if ( ! $user_id || ! $group_id ) {
			wp_send_json_error( __( 'Missing user or group ID.', 'labgenz-cm' ) );
		}

		// Only organizers/admins can remove
		if ( ! groups_is_user_admin( $current_user, $group_id ) ) {
			wp_send_json_error( __( 'You do not have permission to remove members.', 'labgenz-cm' ) );
		}

		// Prevent removing self or another admin
		if ( $user_id == $current_user ) {
			wp_send_json_error( __( 'You cannot remove yourself.', 'labgenz-cm' ) );
		}
		if ( groups_is_user_admin( $user_id, $group_id ) ) {
			wp_send_json_error( __( 'You cannot remove another organizer.', 'labgenz-cm' ) );
		}

		// Remove user from group
		$removed = groups_remove_member( $user_id, $group_id );
		if ( $removed ) {
			wp_send_json_success();
		} else {
			wp_send_json_error( __( 'Failed to remove member.', 'labgenz-cm' ) );
		}
	}

	public function no_permission() {
		wp_send_json_error( __( 'You must be logged in.', 'labgenz-cm' ) );
	}
}

// Initialize handler
new RemoveHandler();
