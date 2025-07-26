<?php
namespace LABGENZ_CM\Groups\Helpers;

use LABGENZ_CM\Groups\GroupMembersHandler;

class InviteUserHelper {
    public function invite_user($post_data) {
		if ( ! wp_verify_nonce( $post_data['nonce'], 'lab_group_management_nonce' ) ) {
			wp_send_json_error( 'Security check failed' );
		}

		$group_id = intval( $post_data['group_id'] );
		$email    = sanitize_email( $post_data['email'] );

		$is_organizer_raw = $post_data['is_organizer'] ?? 0;
		$is_organizer     = ( $is_organizer_raw == '1' || $is_organizer_raw === 1 || $is_organizer_raw === true );
		$role             = $is_organizer ? 'organizer' : 'member';

		$user    = get_user_by( 'email', $email );
		$user_id = $user ? $user->ID : null;

        $members_handler = GroupMembersHandler::get_instance();
		// Generate a unique token for this invitation
		$token = wp_generate_password( 20, false );

		if ( ! $user ) {
			// Create new user - get names from POST data
			$first_name = sanitize_text_field( $_POST['first_name'] ?? '' );
			$last_name  = sanitize_text_field( $_POST['last_name'] ?? '' );

			// If no names provided, try to extract from email
			if ( empty( $first_name ) && empty( $last_name ) ) {
				$email_parts = explode( '@', $email );
				$first_name  = ucfirst( $email_parts[0] );
			}

			$username        = $members_handler->generate_unique_username( $email );
			$random_password = wp_generate_password();
			$user_id         = wp_create_user( $username, $random_password, $email );
			if ( is_wp_error( $user_id ) ) {
				wp_send_json_error( $user_id->get_error_message() );
			}
			update_user_meta( $user_id, 'first_name', $first_name );
			update_user_meta( $user_id, 'last_name', $last_name );
			// Store the generated password for later use during invitation acceptance
			update_user_meta( $user_id, '_labgenz_temp_password', $random_password );
			$user = get_user_by( 'id', $user_id );

		}

		// Check if user is already a member or has pending invitation
		if ( groups_is_user_member( $user->ID, $group_id ) ) {
			wp_send_json_error( 'User is already a member of this group' );
		}

		$invited_users = groups_get_groupmeta( $group_id, 'lab_invited', true );
		if ( is_array( $invited_users ) && isset( $invited_users[ $user->ID ] ) ) {
			wp_send_json_error( 'User already has a pending invitation' );
		}

		// Store invitation in group meta with token and role
		if ( ! is_array( $invited_users ) ) {
			$invited_users = [];
		}
		$invited_users[ $user->ID ] = [
			'user_id'      => $user->ID,
			'email'        => $email,
			'role'         => $role,
			'is_organizer' => $is_organizer,
			'invited_date' => current_time( 'mysql' ),
			'status'       => 'pending',
			'token'        => $token,
		];
		groups_update_groupmeta( $group_id, 'lab_invited', $invited_users );

		// Send invitation email
		$members_handler->send_group_invitation_email( $user, $group_id, $is_organizer, $token );

		wp_send_json_success(
			[
				'message' => "Invitation sent successfully to {$user->display_name}",
			]
		);
    }
}
