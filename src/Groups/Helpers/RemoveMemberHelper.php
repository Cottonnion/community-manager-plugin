<?php
namespace LABGENZ_CM\Groups\Helpers;

class RemoveMemberHelper {

	/**
	 * Handles the removal of a member from a group.
	 * @param array $post_data The POST data containing group ID, user ID, and nonce.
	 * @return void
	 */
    public function remove_member($post_data) {
		if ( ! wp_verify_nonce( $post_data['nonce'], 'lab_group_management_nonce' ) ) {
			wp_send_json_error( 'Security check failed' );
		}

		$group_id        = intval( $post_data['group_id'] );
		$user_id         = intval( $post_data['user_id'] );
		$current_user_id = get_current_user_id();

		if ( $user_id === $current_user_id ) {
			wp_send_json_error( 'You cannot remove yourself from the group' );
		}

		if ( groups_remove_member( $user_id, $group_id ) ) {
			wp_send_json_success( 'Member removed successfully' );
		} else {
			wp_send_json_error( 'Failed to remove member' );
		}
    }
}
