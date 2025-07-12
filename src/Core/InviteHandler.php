<?php
/**
 * InviteHandler class for managing group invitations in Labgenz Community Management plugin.
 *
 * @package LABGENZ_CM\Core
 * @author  Yahya
 * @since   1.0.0
 */

namespace LABGENZ_CM\Core;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Class InviteHandler
 *
 * Handles user invitations, sending welcome emails, and retrieving invited members for BuddyPress groups.
 *
 * @author Yahya
 * @since 1.0.0
 */
class InviteHandler {
	/**
	 * AjaxHandler instance for registering and handling AJAX actions.
	 *
	 * @var AjaxHandler
	 */
	private $ajax_handler;

	/**
	 * InviteHandler constructor.
	 *
	 * Registers AJAX actions using AjaxHandler.
	 *
	 * @author Yahya
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->ajax_handler = new AjaxHandler();
		$this->ajax_handler->register_ajax_actions(
			array(
				'labgenz_invite_user'         => array( $this, 'handle_invite_user' ),
				'labgenz_get_invited_members' => array( $this, 'get_invited_members' ),
				'labgenz_get_profile_types'   => array( $this, 'get_profile_types' ),
			)
		);
	}

	/**
	 * Handles inviting a user to a group via AJAX.
	 *
	 * @param array|null $request Optional request data (unused, handled by AjaxHandler).
	 * @return void
	 *
	 * @author Yahya
	 * @since 1.0.0
	 */
	public function handle_invite_user() {
		$this->ajax_handler->handle_request(
			function ( $data ) {
				$current_user_id = get_current_user_id();
				if ( ! $current_user_id ) {
					return new \WP_Error( 'not_logged_in', 'User not logged in' );
				}
				$email        = sanitize_email( $data['email'] ?? '' );
				$first_name   = sanitize_text_field( $data['first_name'] ?? '' );
				$last_name    = sanitize_text_field( $data['last_name'] ?? '' );
				$profile_type = sanitize_text_field( $data['profile_type'] ?? '' );
				$group_id     = intval( $data['group_id'] ?? 0 );

				// Check if current user is group admin
				if ( ! groups_is_user_admin( $current_user_id, $group_id ) ) {
					return new \WP_Error( 'not_organizer', 'You are not an organizer of this group.' );
				}

				$user_id     = email_exists( $email );
				$is_new_user = false;

				// Check if user is already a group member
				if ( $user_id && groups_is_user_member( $user_id, $group_id ) ) {
					return new \WP_Error( 'already_member', 'User is already a member of this group.' );
				}

				// Create new user if not exists
				if ( ! $user_id ) {
					$base_username = strtolower( sanitize_user( $first_name . '.' . $last_name ) );
					$username      = $base_username;
					$i             = 1;
					while ( username_exists( $username ) ) {
						$username = $base_username . $i;
						$i++;
					}
					$password = wp_generate_password();
					$user_id  = wp_create_user( $username, $password, $email );
					if ( is_wp_error( $user_id ) ) {
						return $user_id;
					}
					$is_new_user = true;
					$this->send_welcome_email( $email, $username, $password );
				}

				// Update user meta for new users
				if ( $is_new_user ) {
					update_user_meta( $user_id, 'first_name', $first_name );
					update_user_meta( $user_id, 'last_name', $last_name );
				}

				// Set profile type and invited by meta
				update_user_meta( $user_id, 'labgenz_profile_type_' . $group_id, $profile_type );
				bp_set_member_type( $user_id, $profile_type );
				update_user_meta( $user_id, 'labgenz_invited_by_group_' . $group_id, $current_user_id );

				// Add to group invited list
				$invited = groups_get_groupmeta( $group_id, 'labgenz_invited', true );
				if ( ! is_array( $invited ) ) {
					$invited = array();
				}
				if ( ! in_array( $user_id, $invited, true ) ) {
					$invited[] = $user_id;
					groups_update_groupmeta( $group_id, 'labgenz_invited', $invited );
				}

				// Add user to group if not already a member
				if ( ! groups_is_user_member( $user_id, $group_id ) ) {
					groups_join_group( $group_id, $user_id );
				}

				return array( 'message' => 'User invited successfully' );
			},
			'inst3d_group_management_nonce'
		);
	}

	/**
	 * Sends a welcome email to a newly created user.
	 *
	 * @param string $email    User email address.
	 * @param string $username Username.
	 * @param string $password Password.
	 * @return void
	 *
	 * @author Yahya
	 * @since 1.0.0
	 */
	private function send_welcome_email( $email, $username, $password ) {
		$subject = 'Welcome to Labgenz Community';
		$message = sprintf(
			"Welcome! Your account has been created.\n\nUsername: %s\nPassword: %s\n\nPlease login and change your password.",
			$username,
			$password
		);
		wp_mail( $email, $subject, $message );
	}

	/**
	 * Retrieves invited members for a group via AJAX.
	 *
	 * @param array|null $request Optional request data (unused, handled by AjaxHandler).
	 * @return void
	 *
	 * @author Yahya
	 * @since 1.0.0
	 */
	public function get_invited_members() {
		$this->ajax_handler->handle_request(
			function ( $data ) {
				$current_user_id = get_current_user_id();
				if ( ! $current_user_id ) {
					return new \WP_Error( 'not_logged_in', 'User not logged in' );
				}
				$group_id = intval( $data['group_id'] ?? 0 );
				if ( ! groups_is_user_admin( $current_user_id, $group_id ) ) {
					return new \WP_Error( 'not_organizer', 'You are not an organizer of this group.' );
				}
				$invited = groups_get_groupmeta( $group_id, 'labgenz_invited', true );
				if ( ! is_array( $invited ) ) {
					$invited = array();
				}
				$result = array();
				foreach ( $invited as $user_id ) {
					$user = get_userdata( $user_id );
					if ( $user ) {
						$profile_type = get_user_meta( $user_id, 'labgenz_profile_type_' . $group_id, true );
						$result[]     = array(
							'user_id'      => $user_id,
							'display_name' => $user->display_name,
							'email'        => $user->user_email,
							'profile_type' => $profile_type ? $profile_type : '',
						);
					}
				}
				return array( 'members' => $result );
			},
			'lab_group_management_nonce'
		);
	}

	/**
	 * Retrieves available profile types for members via AJAX.
	 *
	 * @param array|null $request Optional request data (unused, handled by AjaxHandler).
	 * @return void
	 *
	 * @author Yahya
	 * @since 1.0.0
	 */
	public function get_profile_types() {
		$this->ajax_handler->handle_request(
			function () {
				if ( function_exists( 'bp_get_member_types' ) ) {
					$types         = bp_get_member_types( array(), 'objects' );
					$profile_types = array();
					foreach ( $types as $type => $obj ) {
						$profile_types[] = $type;
					}
				} else {
					$profile_types = array( 'member', 'moderator', 'organizer' );
				}
				return array( 'profile_types' => array_values( $profile_types ) );
			},
			'inst3d_group_management_nonce'
		);
	}
}

// Instantiate the InviteHandler class
new InviteHandler();
