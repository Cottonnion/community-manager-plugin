<?php

declare(strict_types=1);

namespace LABGENZ_CM\Admin;

use LABGENZ_CM\Core\OrganizationAccess;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin interface for managing organization access requests
 *
 * @package    Labgenz_Community_Management
 * @subpackage Labgenz_Community_Management/Admin
 */
class OrganizationAccessAdmin {

	/**
	 * Data handler instance
	 *
	 * @var OrganizationAccessDataHandler
	 */
	private OrganizationAccessDataHandler $data_handler;

	/**
	 * Template renderer instance
	 *
	 * @var OrganizationAccessRenderer
	 */
	private OrganizationAccessRenderer $renderer;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->data_handler = new OrganizationAccessDataHandler();
		$this->renderer     = new OrganizationAccessRenderer( $this->data_handler );
	}

	/**
	 * Initialize admin functionality
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ], 20 );
		add_action( 'wp_ajax_labgenz_process_org_access_request', [ $this, 'handle_admin_action' ] );
		add_action( 'wp_ajax_labgenz_get_request_details', [ $this, 'handle_get_request_details' ] );
		add_action( 'wp_ajax_labgenz_get_user_profile_links', [ $this, 'handle_get_user_profile_links' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
	}

	/**
	 * Add admin menu page
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		// Debug: Log that we're trying to add the menu
		// error_log( 'OrganizationAccessAdmin: Attempting to add admin menu' );

		$hook = add_submenu_page(
			'labgenz-cm',
			__( 'Organization Requests', 'labgenz-community-management' ),
			__( 'Organization Requests', 'labgenz-community-management' ),
			'manage_options',
			'labgenz-organization-requests',
			[ $this, 'render_admin_page' ]
		);
	}

	/**
	 * Enqueue admin scripts
	 *
	 * @param string $hook Current admin page hook
	 * @return void
	 */
	public function enqueue_admin_scripts( string $hook ): void {
		if ( $hook !== 'mlm-mastery-communities_page_labgenz-organization-requests' ) {
			return;
		}

		// Enqueue SweetAlert2
		wp_enqueue_script(
			'sweetalert2',
			'https://cdn.jsdelivr.net/npm/sweetalert2@11',
			[],
			'11.0.0',
			true
		);

		wp_enqueue_script(
			'labgenz-org-access-admin',
			LABGENZ_CM_URL . 'src/Public/assets/js/organization-access-admin.js',
			[ 'jquery', 'sweetalert2' ],
			'1.0.6',
			true
		);

		wp_enqueue_style(
			'labgenz-org-access-admin',
			LABGENZ_CM_URL . 'src/Public/assets/css/organization-access-admin.css',
			[],
			'1.0.5'
		);

		wp_localize_script(
			'labgenz-org-access-admin',
			'labgenz_org_access_admin',
			[
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'labgenz_org_access_admin_nonce' ),
				'strings'  => [
					'confirm_approve' => __( 'Are you sure you want to approve this request?', 'labgenz-community-management' ),
					'confirm_reject'  => __( 'Are you sure you want to reject this request?', 'labgenz-community-management' ),
					'processing'      => __( 'Processing...', 'labgenz-community-management' ),
					'error'           => __( 'An error occurred. Please try again.', 'labgenz-community-management' ),
					'success'         => __( 'Request processed successfully.', 'labgenz-community-management' ),
				],
			]
		);
	}

	/**
	 * Handle admin actions (approve/reject)
	 *
	 * @return void
	 */
	public function handle_admin_action(): void {
		try {
			// Verify nonce
			if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'labgenz_org_access_admin_nonce' ) ) {
				wp_send_json_error( [ 'message' => __( 'Security check failed', 'labgenz-community-management' ) ], 403 );
				return;
			}

			// Check capabilities
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( [ 'message' => __( 'Insufficient permissions', 'labgenz-community-management' ) ], 403 );
				return;
			}

			$user_id    = intval( $_POST['user_id'] ?? 0 );
			$action     = sanitize_text_field( $_POST['action_type'] ?? '' );
			$admin_note = sanitize_textarea_field( $_POST['admin_note'] ?? '' );

			if ( ! $user_id || ! in_array( $action, [ 'approve', 'reject' ], true ) ) {
				wp_send_json_error( [ 'message' => __( 'Invalid request parameters', 'labgenz-community-management' ) ], 400 );
				return;
			}

			$result = $this->data_handler->process_admin_action( $user_id, $action, $admin_note );

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( [ 'message' => $result->get_error_message() ], 400 );
				return;
			}

			$action_label = $action === 'approve' ? __( 'approved', 'labgenz-community-management' ) : __( 'rejected', 'labgenz-community-management' );
			wp_send_json_success(
				[
					'message'    => sprintf( __( 'Request %s successfully.', 'labgenz-community-management' ), $action_label ),
					'new_status' => $action === 'approve' ? OrganizationAccess::STATUS_APPROVED : OrganizationAccess::STATUS_REJECTED,
				]
			);

		} catch ( \Exception $e ) {
			error_log( 'Organization Access Admin Error: ' . $e->getMessage() );
			wp_send_json_error( [ 'message' => __( 'An error occurred while processing the request.', 'labgenz-community-management' ) ], 500 );
		}
	}

	/**
	 * Handle get request details AJAX
	 *
	 * @return void
	 */
	public function handle_get_request_details(): void {
		try {
			// Verify nonce
			if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'labgenz_org_access_admin_nonce' ) ) {
				wp_send_json_error( [ 'message' => __( 'Security check failed', 'labgenz-community-management' ) ], 403 );
				return;
			}

			// Check capabilities
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( [ 'message' => __( 'Insufficient permissions', 'labgenz-community-management' ) ], 403 );
				return;
			}

			$user_id = intval( $_POST['user_id'] ?? 0 );
			if ( ! $user_id ) {
				wp_send_json_error( [ 'message' => __( 'Invalid user ID', 'labgenz-community-management' ) ], 400 );
				return;
			}

			$response_data = $this->data_handler->get_request_details( $user_id );
			if ( ! $response_data ) {
				wp_send_json_error( [ 'message' => __( 'Request not found', 'labgenz-community-management' ) ], 404 );
				return;
			}

			wp_send_json_success( $response_data );

		} catch ( \Exception $e ) {
			error_log( 'Organization Access Get Details Error: ' . $e->getMessage() );
			wp_send_json_error( [ 'message' => __( 'An error occurred while fetching request details.', 'labgenz-community-management' ) ], 500 );
		}
	}

	/**
	 * Handle AJAX request for user profile links
	 *
	 * @return void
	 */
	public function handle_get_user_profile_links(): void {
		try {
			// Verify nonce
			if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'labgenz_org_access_admin_nonce' ) ) {
				wp_send_json_error( [ 'message' => __( 'Security check failed', 'labgenz-community-management' ) ], 403 );
				return;
			}

			// Check capabilities
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( [ 'message' => __( 'Insufficient permissions', 'labgenz-community-management' ) ], 403 );
				return;
			}

			$user_id = intval( $_POST['user_id'] ?? 0 );

			if ( ! $user_id ) {
				wp_send_json_error( [ 'message' => __( 'Invalid user ID', 'labgenz-community-management' ) ], 400 );
				return;
			}

			// Get user info
			$user = get_userdata( $user_id );
			if ( ! $user ) {
				wp_send_json_error( [ 'message' => __( 'User not found', 'labgenz-community-management' ) ], 404 );
				return;
			}

			// Get profile links
			$profile_links = $this->data_handler->get_user_profile_links( $user_id );

			$response_data = [
				'user'          => [
					'id'           => $user->ID,
					'display_name' => $user->display_name,
					'user_email'   => $user->user_email,
					'avatar'       => get_avatar_url( $user_id, [ 'size' => 64 ] ),
				],
				'profile_links' => $profile_links,
			];

			wp_send_json_success( $response_data );

		} catch ( \Exception $e ) {
			error_log( 'Organization Access Get User Profile Links Error: ' . $e->getMessage() );
			wp_send_json_error( [ 'message' => __( 'An error occurred while fetching user profile links.', 'labgenz-community-management' ) ], 500 );
		}
	}

	/**
	 * Render admin page
	 *
	 * @return void
	 */
	public function render_admin_page(): void {
		$current_tab = $_GET['tab'] ?? 'pending';
		$valid_tabs  = [ 'pending', 'approved', 'rejected', 'all' ];

		if ( ! in_array( $current_tab, $valid_tabs, true ) ) {
			$current_tab = 'pending';
		}

		$this->renderer->render_admin_page( $current_tab, $valid_tabs );
	}
}
