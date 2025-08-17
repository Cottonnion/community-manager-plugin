<?php
namespace LABGENZ_CM\Groups\Helpers;

class CancelInvitationHelper {

	/**
	 * Cancels a group invitation for a user.
	 *
	 * @param array $post_data The POST data containing group ID, user ID, and nonce.
	 * @return void
	 */
	public function cancel_invitation( $post_data ) {
		if ( ! wp_verify_nonce( $post_data['nonce'], 'lab_group_management_nonce' ) ) {
			wp_send_json_error( 'Security check failed' );
		}

		$group_id        = intval( $post_data['group_id'] );
		$user_id         = intval( $post_data['user_id'] );
		$current_user_id = get_current_user_id();

		// Check if current user is group admin
		if ( ! groups_is_user_admin( $current_user_id, $group_id ) ) {
			wp_send_json_error( 'You do not have permission to cancel invitations' );
		}

		// Get invited users
		$invited_users = groups_get_groupmeta( $group_id, 'lab_invited', true );

		if ( ! is_array( $invited_users ) || ! isset( $invited_users[ $user_id ] ) ) {
			wp_send_json_error( 'Invitation not found' );
		}

		// Remove the invitation
		unset( $invited_users[ $user_id ] );
		groups_update_groupmeta( $group_id, 'lab_invited', $invited_users );

		wp_send_json_success( 'Invitation cancelled successfully' );
	}
}
