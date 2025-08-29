<?php

declare(strict_types=1);

namespace LABGENZ_CM\Core;

use LABGENZ_CM\Groups\Helpers\NotificationHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles organization access requests
 *
 * This class manages the complete flow of organization access requests:
 * - Processing form submissions
 * - Generating and validating tokens
 * - Sending notification emails
 * - Managing request status
 *
 * @package    Labgenz_Community_Management
 * @subpackage Labgenz_Community_Management/Core
 */
class OrganizationAccess {

	/**
	 * User meta key for storing request data
	 */
	const REQUEST_DATA_META_KEY = '_labgenz_org_access_request_data';

	/**
	 * User meta key for storing verification token
	 */
	const TOKEN_META_KEY = '_labgenz_org_access_token';

	/**
	 * User meta key for storing request status
	 */
	const STATUS_META_KEY = '_labgenz_org_access_status';

	/**
	 * User meta key for storing rejection timestamp
	 */
	const REJECTED_AT_META_KEY = '_labgenz_org_access_rejected_at';
	
	/**
	 * Request status constants
	 */
	const STATUS_PENDING   = 'pending';
	const STATUS_APPROVED  = 'approved';
	const STATUS_REJECTED  = 'rejected';
	const STATUS_COMPLETED = 'completed';

	/**
	 * Token expiration time (7 days in seconds)
	 */
	const TOKEN_EXPIRATION = 7 * 24 * 60 * 60;

	/**
	 * Initialize the organization access handler
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', [ $this, 'handle_create_group_access' ] );
		add_action( 'wp_ajax_labgenz_submit_org_access_request', [ $this, 'handle_form_submission' ] );
		
		// Initialize notification system
		$this->init_notification_system();
	}

	/**
	 * Initialize the notification system with custom types
	 *
	 * @return void
	 */
	private function init_notification_system(): void {
		// Register custom notification types for organization access
		NotificationHelper::register_notification_type('org_access_approved', [
			'title' => 'Organization Access Approved',
			'description' => 'Organization access request has been approved',
			'component' => 'labgenz_community',
			'template' => 'Your organization access request for "<strong>%s</strong>" has been approved! Check your email for instructions.',
			'link_callback' => [ $this, 'get_approval_notification_link' ],
			'data_formatter' => [ $this, 'format_approval_notification' ]
		]);

		NotificationHelper::register_notification_type('org_access_rejected', [
			'title' => 'Organization Access Rejected',
			'description' => 'Organization access request has been rejected',
			'component' => 'labgenz_community',
			'template' => 'Your organization access request for "<strong>%s</strong>" has been rejected. Check your email for details.',
			'link_callback' => [ $this, 'get_rejection_notification_link' ],
			'data_formatter' => [ $this, 'format_rejection_notification' ]
		]);

		// Register the component with BuddyPress
		add_filter('bp_notifications_get_registered_components', function ($components) {
			if (!is_array($components)) $components = [];
			$components['labgenz_community'] = 'labgenz_community';
			return $components;
		});

		// Register the notification formatter
		add_filter('bp_notifications_get_notifications_for_user', 
			[ 'LABGENZ_CM\Groups\Helpers\NotificationHelper', 'format_notification' ], 10, 7);

		// Initialize all notification types
		NotificationHelper::register_all_types();
	}

	/**
	 * Custom approval notification formatter
	 *
	 * @param int $item_id
	 * @param int $secondary_item_id
	 * @param int $total_items
	 * @param string $format
	 * @return string|array
	 */
	public function format_approval_notification(int $item_id, int $secondary_item_id, int $total_items, string $format) {
		$user_id = $item_id;
		$request_data = get_user_meta($user_id, self::REQUEST_DATA_META_KEY, true);
		$organization_name = $request_data['organization_name'] ?? 'your organization';

		if ($total_items > 1) {
			$text = sprintf(
				__('You have %d approved organization access requests', 'labgenz-community-management'),
				$total_items
			);
		} else {
			$text = sprintf(
				__('Your organization access request for "%s" has been approved! Check your email for instructions.', 'labgenz-community-management'),
				$organization_name
			);
		}

		if ($format === 'array') {
			return [
				'text' => $text,
				'link' => $this->get_approval_notification_link($item_id, $secondary_item_id),
			];
		}

		return $text;
	}

	/**
	 * Custom rejection notification formatter
	 *
	 * @param int $item_id
	 * @param int $secondary_item_id
	 * @param int $total_items
	 * @param string $format
	 * @return string|array
	 */
	public function format_rejection_notification(int $item_id, int $secondary_item_id, int $total_items, string $format) {
		$user_id = $item_id;
		$request_data = get_user_meta($user_id, self::REQUEST_DATA_META_KEY, true);
		$organization_name = $request_data['organization_name'] ?? 'your organization';

		if ($total_items > 1) {
			$text = sprintf(
				__('You have %d organization access request updates', 'labgenz-community-management'),
				$total_items
			);
		} else {
			$text = sprintf(
				__('Your organization access request for "%s" has been rejected.', 'labgenz-community-management'),
				$organization_name
			);
		}

		if ($format === 'array') {
			return [
				'text' => $text,
				'link' => $this->get_rejection_notification_link($item_id, $secondary_item_id),
			];
		}

		return $text;
	}

	/**
	 * Get approval notification link
	 *
	 * @param int $item_id
	 * @param int $secondary_item_id
	 * @return string
	 */
	public function get_approval_notification_link(int $item_id, int $secondary_item_id): string {
		$user_id = $item_id;
		$user = get_userdata($user_id);
		
		if (!$user) {
			return home_url();
		}

		$token_data = get_user_meta($user_id, self::TOKEN_META_KEY, true);
		
		if ($token_data && isset($token_data['token'])) {
			return add_query_arg([
				'token' => $token_data['token'],
				'user_email' => urlencode($user->user_email),
			], home_url('/groups/create/step/group-details/'));
		}

		return home_url('/groups/create/step/group-details/');
	}

	/**
	 * Get rejection notification link
	 *
	 * @param int $item_id
	 * @param int $secondary_item_id
	 * @return string
	 */
	public function get_rejection_notification_link(int $item_id, int $secondary_item_id): string {
		// Default to notifications page or contact page
		if (function_exists('bp_get_notifications_slug')) {
			return home_url(trailingslashit(bp_get_notifications_slug()));
		}
		return home_url('/contact/');
	}

	/**
	 * Handle organization access form submission
	 *
	 * @return void
	 */
	public function handle_form_submission(): void {
		try {
			// Verify nonce
			if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'labgenz_org_access_nonce' ) ) {
				wp_send_json_error( [ 'message' => __( 'Security check failed', 'labgenz-community-management' ) ], 403 );
				return;
			}

			// Check if user is logged in
			if ( ! is_user_logged_in() ) {
				wp_send_json_error( [ 'message' => __( 'You must be logged in to request organization access', 'labgenz-community-management' ) ], 401 );
				return;
			}

			$user_id = get_current_user_id();

			// Check if user already has a pending request
			$current_status = get_user_meta( $user_id, self::STATUS_META_KEY, true );
			if ( $current_status === self::STATUS_PENDING ) {
				wp_send_json_error( [ 'message' => __( 'You already have a pending organization access request', 'labgenz-community-management' ) ], 400 );
				return;
			}

			// Block new request if rejected within last 7 days
			if ( $current_status === self::STATUS_REJECTED ) {
				$rejected_at = get_user_meta( $user_id, self::REJECTED_AT_META_KEY, true );
				if ( $rejected_at ) {
					$rejected_timestamp = strtotime( $rejected_at );
					$now = time();
					$wait_seconds = 7 * 24 * 60 * 60;
					if ( ( $now - $rejected_timestamp ) < $wait_seconds ) {
						$remaining = $wait_seconds - ( $now - $rejected_timestamp );
						$days = ceil( $remaining / ( 24 * 60 * 60 ) );
						wp_send_json_error( [
							'message' => sprintf(
								__( 'You must wait %d more day(s) before submitting another organization access request.', 'labgenz-community-management' ),
								$days
							)
						], 400 );
						return;
					}
				}
			}

			// Sanitize form data
			$form_data = $this->sanitize_form_data( $_POST );

			// Validate required fields
			$validation_result = $this->validate_form_data( $form_data );
			if ( is_wp_error( $validation_result ) ) {
				wp_send_json_error( [ 'message' => $validation_result->get_error_message() ], 400 );
				return;
			}

			// Save request data
			$request_data = [
				'organization_name' => $form_data['organization_name'],
				'organization_type' => $form_data['organization_type'],
				'description'       => $form_data['description'],
				'website'           => $form_data['website'],
				'contact_email'     => $form_data['contact_email'],
				'phone'             => $form_data['phone'],
				'justification'     => $form_data['justification'],
				'requested_at'      => current_time( 'mysql' ),
				'user_id'           => $user_id,
			];

			// Store request data and status
			update_user_meta( $user_id, self::REQUEST_DATA_META_KEY, $request_data );
			update_user_meta( $user_id, self::STATUS_META_KEY, self::STATUS_PENDING );

			// Send notification email to admin
			$this->send_admin_notification( $user_id, $request_data );

			wp_send_json_success(
				[
					'message' => __( 'Your organization access request has been submitted successfully. You will receive an email notification once it is reviewed.', 'labgenz-community-management' ),
				]
			);

		} catch ( \Exception $e ) {
			error_log( 'Organization Access Error: ' . $e->getMessage() );
			wp_send_json_error( [ 'message' => __( 'An error occurred while processing your request. Please try again.', 'labgenz-community-management' ) ], 500 );
		}
	}

	/**
	 * Handle non-logged user requests
	 *
	 * @return void
	 */
	public function handle_non_logged_user_request(): void {
		wp_send_json_error( [ 'message' => __( 'You must be logged in to ORGANIZER', 'labgenz-community-management' ) ], 401 );
	}

	/**
	 * Sanitize form data
	 *
	 * @param array $data Raw form data
	 * @return array Sanitized form data
	 */
	private function sanitize_form_data( array $data ): array {
		return [
			'organization_name' => sanitize_text_field( $data['organization_name'] ?? '' ),
			'organization_type' => sanitize_text_field( $data['organization_type'] ?? '' ),
			'description'       => sanitize_textarea_field( $data['description'] ?? '' ),
			'website'           => esc_url_raw( $data['website'] ?? '' ),
			'contact_email'     => sanitize_email( $data['contact_email'] ?? '' ),
			'phone'             => sanitize_text_field( $data['phone'] ?? '' ),
			'justification'     => sanitize_textarea_field( $data['justification'] ?? '' ),
		];
	}

	/**
	 * Validate form data
	 *
	 * @param array $data Sanitized form data
	 * @return true|\WP_Error
	 */
	private function validate_form_data( array $data ) {
		$required_fields = [
			'organization_name' => __( 'Organization name is required', 'labgenz-community-management' ),
			'organization_type' => __( 'Organization type is required', 'labgenz-community-management' ),
			'description'       => __( 'Description is required', 'labgenz-community-management' ),
			'contact_email'     => __( 'Contact email is required', 'labgenz-community-management' ),
			'justification'     => __( 'Justification is required', 'labgenz-community-management' ),
		];

		foreach ( $required_fields as $field => $message ) {
			if ( empty( $data[ $field ] ) ) {
				return new \WP_Error( 'missing_field', $message );
			}
		}

		// Validate email format
		if ( ! is_email( $data['contact_email'] ) ) {
			return new \WP_Error( 'invalid_email', __( 'Please enter a valid email address', 'labgenz-community-management' ) );
		}

		// Validate website URL if provided
		if ( ! empty( $data['website'] ) && ! filter_var( $data['website'], FILTER_VALIDATE_URL ) ) {
			return new \WP_Error( 'invalid_url', __( 'Please enter a valid website URL', 'labgenz-community-management' ) );
		}

		return true;
	}

	/**
	 * Send admin notification email
	 *
	 * @param int   $user_id User ID
	 * @param array $request_data Request data
	 * @return void
	 */
	private function send_admin_notification( int $user_id, array $request_data ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$admin_email = get_option( 'admin_email' );
		$subject     = sprintf(
			__( '[%s] New Organization Access Request', 'labgenz-community-management' ),
			get_bloginfo( 'name' )
		);

		$admin_url = admin_url( 'admin.php?page=labgenz-organization-requests' );

		$message = sprintf(
			__( "A new organization access request has been submitted.\n\nUser: %1\$s (%2\$s)\nOrganization: %3\$s\nType: %4\$s\nEmail: %5\$s\n\nDescription:\n%6\$s\n\nJustification:\n%7\$s\n\nReview and manage this request: %8\$s", 'labgenz-community-management' ),
			$user->display_name,
			$user->user_email,
			$request_data['organization_name'],
			$request_data['organization_type'],
			$request_data['contact_email'],
			$request_data['description'],
			$request_data['justification'],
			$admin_url
		);

		wp_mail( $admin_email, $subject, $message );
	}

	/**
	 * Process admin approval/rejection
	 *
	 * @param int    $user_id User ID
	 * @param string $action Action (approve/reject)
	 * @param string $admin_note Optional admin note
	 * @return bool|\WP_Error
	 */
	public function process_admin_action( int $user_id, string $action, string $admin_note = '' ) {
		$request_data = get_user_meta( $user_id, self::REQUEST_DATA_META_KEY, true );
		if ( ! $request_data ) {
			return new \WP_Error( 'no_request', __( 'No request found for this user', 'labgenz-community-management' ) );
		}

		$current_status = get_user_meta( $user_id, self::STATUS_META_KEY, true );
		if ( $current_status !== self::STATUS_PENDING ) {
			return new \WP_Error( 'invalid_status', __( 'Request is not in pending status', 'labgenz-community-management' ) );
		}

		if ( $action === 'approve' ) {
			return $this->approve_request( $user_id, $request_data, $admin_note );
		} elseif ( $action === 'reject' ) {
			return $this->reject_request( $user_id, $request_data, $admin_note );
		}

		return new \WP_Error( 'invalid_action', __( 'Invalid action', 'labgenz-community-management' ) );
	}

	/**
	 * Approve organization access request
	 *
	 * @param int    $user_id User ID
	 * @param array  $request_data Request data
	 * @param string $admin_note Admin note
	 * @return bool
	 */
	private function approve_request( int $user_id, array $request_data, string $admin_note ): bool {
		// Generate access token
		$token = $this->generate_access_token( $user_id );

		// Update status
		update_user_meta( $user_id, self::STATUS_META_KEY, self::STATUS_APPROVED );
		update_user_meta(
			$user_id,
			self::TOKEN_META_KEY,
			[
				'token'      => $token,
				'expires_at' => time() + self::TOKEN_EXPIRATION,
				'created_at' => time(),
			]
		);

		// Send approval email
		$this->send_approval_email( $user_id, $request_data, $token, $admin_note );

		// Send BuddyPress notification using NotificationHelper
		NotificationHelper::send_notification(
			'org_access_approved',
			$user_id,
			$user_id,
			$user_id, // Use user_id as secondary for this case
			[
				'organization_name' => $request_data['organization_name'],
				'admin_note' => $admin_note,
				'token' => $token
			]
		);

		return true;
	}

	/**
	 * Reject organization access request
	 *
	 * @param int    $user_id User ID
	 * @param array  $request_data Request data
	 * @param string $admin_note Admin note
	 * @return bool
	 */
	private function reject_request( int $user_id, array $request_data, string $admin_note ): bool {
		// Update status
		update_user_meta( $user_id, self::STATUS_META_KEY, self::STATUS_REJECTED );
		update_user_meta( $user_id, self::REJECTED_AT_META_KEY, current_time( 'mysql' ) );

		// Send rejection email
		$this->send_rejection_email( $user_id, $request_data, $admin_note );

		// Send BuddyPress notification using NotificationHelper
		NotificationHelper::send_notification(
			'org_access_rejected',
			$user_id,
			$user_id,
			$user_id, // Use user_id as secondary for this case
			[
				'organization_name' => $request_data['organization_name'],
				'admin_note' => $admin_note
			]
		);

		return true;
	}

	/**
	 * Generate access token
	 *
	 * @param int $user_id User ID
	 * @return string
	 */
	private function generate_access_token( int $user_id ): string {
		$user = get_userdata( $user_id );
		$data = $user_id . $user->user_email . time() . wp_generate_password( 32, false );
		return hash( 'sha256', $data );
	}

	/**
	 * Send approval email
	 *
	 * @param int    $user_id User ID
	 * @param array  $request_data Request data
	 * @param string $token Access token
	 * @param string $admin_note Admin note
	 * @return void
	 */
	private function send_approval_email( int $user_id, array $request_data, string $token, string $admin_note ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$create_group_url = add_query_arg(
			[
				'token'      => $token,
				'user_email' => urlencode( $user->user_email ),
			],
			home_url( '/groups/create/step/group-details/' )
		);

		$subject = sprintf(
			__( '[%s] Organization Access Request Approved', 'labgenz-community-management' ),
			get_bloginfo( 'name' )
		);

		$message = sprintf(
			__( "Hello %1\$s,\n\nGreat news! Your organization access request for \"%2\$s\" has been approved.\n\nYou can now create your organization group by clicking the link below:\n%3\$s\n\nThis link will expire in 7 days.\n\n%4\$s\n\nBest regards,\n%5\$s Team", 'labgenz-community-management' ),
			$user->display_name,
			$request_data['organization_name'],
			$create_group_url,
			$admin_note ? 'Admin Note: ' . $admin_note : '',
			get_bloginfo( 'name' )
		);

		// Send email
		wp_mail( $user->user_email, $subject, $message );
	}

	/**
	 * Send rejection email
	 *
	 * @param int    $user_id User ID
	 * @param array  $request_data Request data
	 * @param string $admin_note Admin note
	 * @return void
	 */
	private function send_rejection_email( int $user_id, array $request_data, string $admin_note ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$subject = sprintf(
			__( '[%s] Organization Access Request Update', 'labgenz-community-management' ),
			get_bloginfo( 'name' )
		);

		$message = sprintf(
			__( "Hello %1\$s,\n\nThank you for your organization access request for \"%2\$s\".\n\nAfter careful review, we are unable to approve your request at this time.\n\n%3\$s\n\nIf you have any questions or would like to discuss this further, please don't hesitate to contact us.\n\nBest regards,\n%4\$s Team", 'labgenz-community-management' ),
			$user->display_name,
			$request_data['organization_name'],
			$admin_note ? 'Reason: ' . $admin_note : '',
			get_bloginfo( 'name' )
		);

		wp_mail( $user->user_email, $subject, $message );
	}

	/**
	 * Verify access token for create-group page
	 *
	 * @param string $token Token to verify
	 * @param string $user_email User email
	 * @return bool|\WP_Error
	 */
	public function verify_access_token( string $token, string $user_email ) {
		$user = get_user_by( 'email', $user_email );
		if ( ! $user ) {
			return new \WP_Error( 'invalid_user', __( 'Invalid user', 'labgenz-community-management' ) );
		}

		$token_data = get_user_meta( $user->ID, self::TOKEN_META_KEY, true );
		if ( ! $token_data ) {
			return new \WP_Error( 'no_token', __( 'No access token found', 'labgenz-community-management' ) );
		}

		// Check if token matches
		if ( ! hash_equals( $token_data['token'], $token ) ) {
			return new \WP_Error( 'invalid_token', __( 'Invalid access token', 'labgenz-community-management' ) );
		}

		// Check if token is expired
		if ( time() > $token_data['expires_at'] ) {
			return new \WP_Error( 'expired_token', __( 'Access token has expired', 'labgenz-community-management' ) );
		}

		// Check if user status is approved
		$status = get_user_meta( $user->ID, self::STATUS_META_KEY, true );
		if ( $status !== self::STATUS_APPROVED ) {
			return new \WP_Error( 'not_approved', __( 'Access not approved', 'labgenz-community-management' ) );
		}

		return true;
	}

	/**
	 * Handle create-group page access verification
	 *
	 * @return void
	 */
	public function handle_create_group_access(): void {
		// Check if we're on the group creation page
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';
		if ( strpos( $request_uri, '/groups/create/step/group-details/' ) === false ) {
			return;
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_die(
				__( 'You must be logged in to create an organization.', 'labgenz-community-management' ),
				__( 'Access Denied', 'labgenz-community-management' ),
				[ 'response' => 401 ]
			);
		}

		$current_user_id = get_current_user_id();

		// Check if token verification is required
		if ( isset( $_GET['token'] ) && isset( $_GET['user_email'] ) ) {
			// Token-based access
			$token      = sanitize_text_field( $_GET['token'] );
			$user_email = sanitize_email( $_GET['user_email'] );

			$verification_result = $this->verify_access_token( $token, $user_email );

			if ( is_wp_error( $verification_result ) ) {
				wp_die(
					esc_html( $verification_result->get_error_message() ),
					__( 'Access Denied', 'labgenz-community-management' ),
					[ 'response' => 403 ]
				);
			}

			// Verify that the token belongs to the current user
			$token_user = get_user_by( 'email', $user_email );
			if ( ! $token_user || $token_user->ID !== $current_user_id ) {
				wp_die(
					__( 'Invalid access token for current user.', 'labgenz-community-management' ),
					__( 'Access Denied', 'labgenz-community-management' ),
					[ 'response' => 403 ]
				);
			}

			// Token is valid, set verification flag
			add_action(
				'wp_head',
				function () {
					echo '<meta name="labgenz-org-access-verified" content="true">';
				}
			);

		} else {
			// No token provided - check if user has approved status
			$user_status = get_user_meta( $current_user_id, self::STATUS_META_KEY, true );

			if ( $user_status !== self::STATUS_APPROVED ) {
				// User doesn't have approved access and no valid token
				$message = __( 'You need organization access approval to create an organization. Please request access first.', 'labgenz-community-management' );

				// Check if they have a pending request
				if ( $user_status === self::STATUS_PENDING ) {
					$message = __( 'Your organization access request is pending approval. Please wait for admin approval.', 'labgenz-community-management' );
				} elseif ( $user_status === self::STATUS_REJECTED ) {
					$message = __( 'Your organization access request was rejected. Please contact an administrator.', 'labgenz-community-management' );
				}

				wp_die(
					esc_html( $message ),
					__( 'Organization Access Required', 'labgenz-community-management' ),
					[ 'response' => 403 ]
				);
			}
		}
	}

	/**
	 * Get all organization access requests
	 *
	 * @param string $status Filter by status (optional)
	 * @return array
	 */
	public function get_all_requests( string $status = '' ): array {
		$meta_query = [
			[
				'key'     => self::REQUEST_DATA_META_KEY,
				'compare' => 'EXISTS',
			],
		];

		if ( ! empty( $status ) ) {
			$meta_query[] = [
				'key'     => self::STATUS_META_KEY,
				'value'   => $status,
				'compare' => '=',
			];
		}

		$users = get_users(
			[
				'meta_query' => $meta_query,
				'orderby'    => 'registered',
				'order'      => 'DESC',
			]
		);

		$requests = [];
		foreach ( $users as $user ) {
			$request_data = get_user_meta( $user->ID, self::REQUEST_DATA_META_KEY, true );
			$status       = get_user_meta( $user->ID, self::STATUS_META_KEY, true );

			if ( $request_data ) {
				$requests[] = [
					'user_id'      => $user->ID,
					'user'         => $user,
					'request_data' => $request_data,
					'status'       => $status,
					'status_label' => $this->get_status_label( $status ),
				];
			}
		}

		return $requests;
	}

	/**
	 * Get status label
	 *
	 * @param string $status Status
	 * @return string
	 */
	private function get_status_label( string $status ): string {
		switch ( $status ) {
			case self::STATUS_PENDING:
				return __( 'Pending', 'labgenz-community-management' );
			case self::STATUS_APPROVED:
				return __( 'Approved', 'labgenz-community-management' );
			case self::STATUS_REJECTED:
				return __( 'Rejected', 'labgenz-community-management' );
			case self::STATUS_COMPLETED:
				return __( 'Completed', 'labgenz-community-management' );
			default:
				return __( 'Unknown', 'labgenz-community-management' );
		}
	}

	/**
	 * Get user's current request status
	 *
	 * @param int $user_id User ID
	 * @return string|null
	 */
	public function get_user_request_status( int $user_id ): ?string {
		return get_user_meta( $user_id, self::STATUS_META_KEY, true ) ?: null;
	}

	/**
	 * Get user's request data
	 *
	 * @param int $user_id User ID
	 * @return array|null
	 */
	public function get_user_request_data( int $user_id ): ?array {
		$data = get_user_meta( $user_id, self::REQUEST_DATA_META_KEY, true );
		return $data ?: null;
	}
}
