<?php
namespace LABGENZ_CM\Groups\Helpers;

use LABGENZ_CM\Groups\GroupMembersHandler;

class AcceptInvitationHelper {

	/**
	 * Accepts a group invitation for the current user.
	 *
	 * @param array $post_data The POST data containing group ID and nonce.
	 * @return void
	 */
	public function accept_invitation( $post_data ) {
		if ( ! wp_verify_nonce( $post_data['nonce'], 'lab_group_management_nonce' ) ) {
			wp_send_json_error( 'Security check failed' );
		}

		$group_id = intval( $post_data['group_id'] );
		$user_id  = get_current_user_id();

		if ( ! $user_id ) {
			wp_send_json_error( 'You must be logged in to accept invitations' );
		}

		// Get invited users
		$invited_users = groups_get_groupmeta( $group_id, 'lab_invited', true );

		if ( ! is_array( $invited_users ) || ! isset( $invited_users[ $user_id ] ) ) {
			wp_send_json_error( 'No pending invitation found' );
		}

		$invitation = $invited_users[ $user_id ];

		// Store role information before joining
		$is_organizer = isset( $invitation['is_organizer'] ) && $invitation['is_organizer'];
		$role         = isset( $invitation['role'] ) ? $invitation['role'] : ( $is_organizer ? 'organizer' : 'member' );

		// Add user to group
		$join_result = groups_join_group( $group_id, $user_id );

		if ( ! $join_result ) {
			wp_send_json_error( 'Failed to join group' );
		}

		// Give WordPress a moment to process the group join
		sleep( 1 );

		// If user was invited as organizer, promote them
		if ( $is_organizer ) {
			$promote_result = groups_promote_member( $user_id, $group_id, 'admin' );

			// If promotion failed, try direct database update as fallback
			if ( ! $promote_result ) {
				global $wpdb, $bp;

				// Try direct database update as fallback
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$bp->groups->table_name_members} 
                    SET is_admin = 1, is_mod = 0 
                    WHERE user_id = %d AND group_id = %d",
						$user_id,
						$group_id
					)
				);

				// Clear group cache to ensure changes take effect
				groups_clear_group_object_cache( $group_id );
			}

			// Sync with LearnDash - Add user as leader to associated LearnDash group
			$members_handler = GroupMembersHandler::get_instance();
			$members_handler->sync_learndash_leadership( $user_id, $group_id );
		}

		// Remove from invited users
		unset( $invited_users[ $user_id ] );
		groups_update_groupmeta( $group_id, 'lab_invited', $invited_users );

		// Get WooCommerce account edit URL
		$edit_account_url = wc_get_endpoint_url( 'edit-account', '', wc_get_page_permalink( 'myaccount' ) );

		wp_send_json_success(
			[
				'message'             => 'Successfully joined the group',
				'redirect_url'        => $edit_account_url,
				'show_password_alert' => true,
			]
		);
	}
}
