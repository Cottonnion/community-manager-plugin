<?php
namespace LABGENZ_CM\Groups\Helpers;

use LABGENZ_CM\Groups\GroupMembersHandler;

class ResendInvitationHelper {

	/**
	 * Resends a group invitation to a user.
	 *
	 * @param array $post_data The POST data containing group ID, user ID, email, and nonce.
	 * @return void
	 */
	public function resend_invitation( $post_data ) {
		if ( ! wp_verify_nonce( $post_data['nonce'], 'lab_group_management_nonce' ) ) {
			wp_send_json_error( 'Security check failed' );
		}

		$group_id        = intval( $post_data['group_id'] );
		$user_id         = intval( $post_data['user_id'] );
		$email           = sanitize_email( $post_data['email'] );
		$current_user_id = get_current_user_id();

		// Check if current user is group admin
		if ( ! groups_is_user_admin( $current_user_id, $group_id ) ) {
			wp_send_json_error( 'You do not have permission to resend invitations' );
		}

		// Get invited users
		$invited_users = groups_get_groupmeta( $group_id, 'lab_invited', true );

		if ( ! is_array( $invited_users ) || ! isset( $invited_users[ $user_id ] ) ) {
			wp_send_json_error( 'Invitation not found' );
		}

		// Get user data
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			wp_send_json_error( 'User not found' );
		}

		// Get invitation data
		$invitation   = $invited_users[ $user_id ];
		$is_organizer = isset( $invitation['is_organizer'] ) && $invitation['is_organizer'];
		$token        = isset( $invitation['token'] ) ? $invitation['token'] : wp_generate_password( 20, false );

		// Update token if needed
		if ( ! isset( $invitation['token'] ) ) {
			$invited_users[ $user_id ]['token'] = $token;
			groups_update_groupmeta( $group_id, 'lab_invited', $invited_users );
		}

		// Send the invitation email again
		$members_handler = GroupMembersHandler::get_instance();
		$members_handler->send_group_invitation_email( $user, $group_id, $is_organizer, $token, 'reminder' );

		wp_send_json_success( 'Invitation resent successfully' );
	}
}
