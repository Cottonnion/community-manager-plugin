<?php
namespace LABGENZ_CM\Groups\Helpers;

class SearchUserHelper {

	/**
	 * Searches for a user by email and checks their group membership status.
	 *
	 * @param array $post_data The POST data containing email and group ID.
	 * @return void
	 */
	public function search_user( $post_data ) {
		if ( ! wp_verify_nonce( $post_data['nonce'], 'lab_group_management_nonce' ) ) {
			wp_send_json_error( 'Security check failed' );
		}

		$email    = sanitize_email( $post_data['email'] );
		$group_id = intval( $post_data['group_id'] );

		if ( ! is_email( $email ) ) {
			wp_send_json_error( 'Invalid email address' );
		}

		// Validate group and user permissions
		$current_user_id = get_current_user_id();
		if ( ! $current_user_id ) {
			wp_send_json_error( 'You must be logged in to perform this action' );
		}

		// Check if user is an organizer of the target group
		if ( ! groups_is_user_admin( $current_user_id, $group_id ) ) {
			wp_send_json_error( 'You do not have permission to manage this group' );
		}

		// Verify the group exists
		$group = groups_get_group( $group_id );
		if ( ! $group || ! $group->id ) {
			wp_send_json_error( 'Invalid group specified' );
		}

		$user = get_user_by( 'email', $email );

		if ( $user ) {
			// User exists - check if already a member or invited
			$is_member = groups_is_user_member( $user->ID, $group_id );
			$is_admin  = groups_is_user_admin( $user->ID, $group_id );
			$is_mod    = groups_is_user_mod( $user->ID, $group_id );

			// Check if already invited
			$invited_users = groups_get_groupmeta( $group_id, 'lab_invited', true );
			$is_pending    = is_array( $invited_users ) && isset( $invited_users[ $user->ID ] );

			if ( $is_member ) {
				// Status 1: User is already in the group
				$response = [
					'status'      => 'already_member',
					'user_exists' => true,
					'group_name'  => $group->name,
					'user_data'   => [
						'ID'           => $user->ID,
						'display_name' => $user->display_name,
						'email'        => $user->user_email,
						'avatar'       => get_avatar_url( $user->ID, [ 'size' => 60 ] ),
						'current_role' => $is_admin ? 'Administrator' : ( $is_mod ? 'Moderator' : 'Member' ),
					],
				];
			} elseif ( $is_pending ) {
				// User has pending invitation
				$response = [
					'status'      => 'pending_invitation',
					'user_exists' => true,
					'group_name'  => $group->name,
					'user_data'   => [
						'ID'           => $user->ID,
						'display_name' => $user->display_name,
						'email'        => $user->user_email,
						'avatar'       => get_avatar_url( $user->ID, [ 'size' => 60 ] ),
					],
				];
			} else {
				// Status 2: User exists but not in group - can invite
				$response = [
					'status'      => 'can_invite',
					'user_exists' => true,
					'group_name'  => $group->name,
					'user_data'   => [
						'ID'           => $user->ID,
						'display_name' => $user->display_name,
						'email'        => $user->user_email,
						'avatar'       => get_avatar_url( $user->ID, [ 'size' => 60 ] ),
					],
				];
			}
		} else {
			// Status 3: User doesn't exist - suggest creating and inviting
			$response = [
				'status'      => 'user_not_exists',
				'user_exists' => false,
				'group_name'  => $group->name,
				'user_data'   => [
					'email'        => $email,
					'display_name' => explode( '@', $email )[0],
					'avatar'       => get_avatar_url( 0, [ 'size' => 60 ] ), // Default avatar
				],
			];
		}

		wp_send_json_success( $response );
	}
}
