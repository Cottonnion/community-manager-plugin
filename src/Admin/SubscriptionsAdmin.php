<?php
/**
 * Admin Subscriptions Management
 *
 * @package LabgenzCommunityManagement
 */

namespace LABGENZ_CM\Admin;

use LABGENZ_CM\Core\AssetsManager;
use LABGENZ_CM\Database\Database;

/**
 * Admin Subscriptions management class
 */
class SubscriptionsAdmin {

	/**
	 * The assets manager instance.
	 *
	 * @var AssetsManager
	 */
	private $assets_manager;

	/**
	 * The database instance.
	 *
	 * @var Database
	 */
	private $database;

	/**
	 * Constructor.
	 *
	 * @param AssetsManager $assets_manager The assets manager.
	 */
	public function __construct() {
		$this->assets_manager = new AssetsManager();
		$this->database       = Database::get_instance();
		$this->init();
	}

	/**
	 * Initialize the class hooks.
	 */
	public function init() {
		// Add admin menu item.
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );

		// Register admin assets.
		add_action( 'admin_enqueue_scripts', [ $this, 'register_assets' ] );

		// AJAX handlers.
		add_action( 'wp_ajax_get_subscription_details', [ $this, 'ajax_get_subscription_details' ] );
		add_action( 'wp_ajax_update_subscription', [ $this, 'ajax_update_subscription' ] );
		add_action( 'wp_ajax_delete_subscription', [ $this, 'ajax_delete_subscription' ] );
		add_action( 'wp_ajax_save_subscription', [ $this, 'ajax_save_subscription' ] );
	}

	/**
	 * Add admin menu page for subscriptions.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'labgenz-cm',
			'Subscriptions',
			'Subscriptions',
			'manage_options',
			'mlmmc-subscriptions',
			[ $this, 'render_admin_page' ],
			30
		);
	}

	/**
	 * Register admin assets.
	 *
	 * @param string $hook The current admin page.
	 */
	public function register_assets( $hook ) {
		if ( 'mlm-mastery-communities_page_mlmmc-subscriptions' !== $hook ) {
			return;
		}

		// Add SweetAlert2 library.
		wp_enqueue_script(
			'sweetalert2',
			'https://cdn.jsdelivr.net/npm/sweetalert2@11',
			[],
			'11.0.0',
			true
		);

		// Add our admin assets.
		$this->assets_manager->add_admin_asset(
			'labgenz-admin-subscriptions-css',
			[ 'mlm-mastery-communities_page_mlmmc-subscriptions' ],
			'admin-subscriptions.css',
			[],
			[],
			LABGENZ_CM_VERSION
		);

		$this->assets_manager->add_admin_asset(
			'labgenz-admin-subscriptions-js',
			[ 'mlm-mastery-communities_page_mlmmc-subscriptions' ],
			'admin-subscriptions.js',
			[ 'jquery', 'sweetalert2' ],
			[
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'labgenz_admin_subscriptions_nonce' ),
			],
			'1.0.9',
			true
		);

		// Localize the script with necessary data.
		wp_localize_script(
			'labgenz-admin-subscriptions-js',
			'labgenz_admin_subscriptions',
			[
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'labgenz_admin_subscriptions_nonce' ),
			]
		);
	}

	/**
	 * Render the admin page.
	 */
	public function render_admin_page() {
		include_once LABGENZ_CM_TEMPLATES_DIR . '/admin/partials/admin-subscriptions.php';
	}

	/**
	 * Get subscriptions with filtering and pagination.
	 *
	 * @param array $args The query arguments.
	 * @return array The subscriptions array with items and total count.
	 */
	public function get_subscriptions( $args = [] ) {
		return $this->database->get_subscriptions( $args );
	}

	/**
	 * Get subscription plans.
	 *
	 * @return array The subscription plans.
	 */
	public function get_subscription_plans() {
		// This would normally query actual subscription plans from a database or API.
		// For demonstration purposes, we're using a static array.
		return [
			(object) [
				'id'    => 1,
				'name'  => 'Basic Plan',
				'price' => '$9.99/month',
			],
			(object) [
				'id'    => 2,
				'name'  => 'Premium Plan',
				'price' => '$19.99/month',
			],
			(object) [
				'id'    => 3,
				'name'  => 'Pro Plan',
				'price' => '$29.99/month',
			],
			(object) [
				'id'    => 4,
				'name'  => 'Annual Basic',
				'price' => '$99.99/year',
			],
			(object) [
				'id'    => 5,
				'name'  => 'Annual Premium',
				'price' => '$199.99/year',
			],
		];
	}

	/**
	 * AJAX handler to get subscription details.
	 */
	public function ajax_get_subscription_details() {
		// Check nonce for security.
		if ( ! check_ajax_referer( 'labgenz_admin_subscriptions_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'Invalid security token sent.' ] );
		}

		// Check user capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'You do not have permission to perform this action.' ] );
		}

		$subscription_id = isset( $_POST['subscription_id'] ) ? sanitize_text_field( $_POST['subscription_id'] ) : '';

		if ( empty( $subscription_id ) ) {
			wp_send_json_error( [ 'message' => 'Subscription ID is required.' ] );
		}

		// Get subscription details from user meta.
		$user_id = $this->get_user_by_subscription_id( $subscription_id );

		if ( ! $user_id ) {
			wp_send_json_error( [ 'message' => 'Subscription not found.' ] );
		}

		$user = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			wp_send_json_error( [ 'message' => 'User not found.' ] );
		}

		// Get subscription data.
		$user_subscriptions = get_user_meta( $user_id, '_labgenz_subscriptions', true );
		$subscription_data  = null;

		// Find the specific subscription by ID
		if ( is_array( $user_subscriptions ) ) {
			foreach ( $user_subscriptions as $sub ) {
				$sub_id = isset( $sub['id'] ) ? $sub['id'] : md5( $user_id . $sub['type'] . ( $sub['created'] ?? '' ) );
				if ( $sub_id === $subscription_id ) {
					$subscription_data = $sub;
					break;
				}
			}
		}

		if ( ! $subscription_data ) {
			// Fallback to old structure
			$subscription = [
				'id'              => $subscription_id,
				'user_id'         => $user_id,
				'user_name'       => $user->display_name,
				'user_email'      => $user->user_email,
				'plan_id'         => get_user_meta( $user_id, 'subscription_plan_id', true ),
				'plan_name'       => get_user_meta( $user_id, 'subscription_plan_name', true ),
				'status'          => ucfirst( get_user_meta( $user_id, 'subscription_status', true ) ),
				'start_date'      => date_i18n( get_option( 'date_format' ), strtotime( get_user_meta( $user_id, 'subscription_start_date', true ) ) ),
				'expiry_date'     => date_i18n( get_option( 'date_format' ), strtotime( get_user_meta( $user_id, 'subscription_expiry_date', true ) ) ),
				'expiry_date_raw' => date( 'Y-m-d', strtotime( get_user_meta( $user_id, 'subscription_expiry_date', true ) ) ),
				'amount'          => get_user_meta( $user_id, 'subscription_amount', true ),
				'payment_method'  => get_user_meta( $user_id, 'subscription_payment_method', true ),
				'auto_renewal'    => (bool) get_user_meta( $user_id, 'subscription_auto_renewal', true ),
				'notes'           => get_user_meta( $user_id, 'subscription_admin_notes', true ),
			];
		} else {
			// Use new structure
			$subscription = [
				'id'              => $subscription_id,
				'user_id'         => $user_id,
				'user_name'       => $user->display_name,
				'user_email'      => $user->user_email,
				'plan_id'         => $subscription_data['plan_id'] ?? '',
				'plan_name'       => $subscription_data['plan_name'] ?? $subscription_data['type'],
				'status'          => ucfirst( $subscription_data['status'] ),
				'start_date'      => isset( $subscription_data['created'] )
									? date_i18n( get_option( 'date_format' ), strtotime( $subscription_data['created'] ) )
									: '',
				'expiry_date'     => date_i18n( get_option( 'date_format' ), strtotime( $subscription_data['expires'] ) ),
				'expiry_date_raw' => date( 'Y-m-d', strtotime( $subscription_data['expires'] ) ),
				'amount'          => $subscription_data['amount'] ?? '',
				'payment_method'  => $subscription_data['payment_method'] ?? '',
				'auto_renewal'    => isset( $subscription_data['auto_renewal'] ) ? (bool) $subscription_data['auto_renewal'] : false,
				'notes'           => $subscription_data['notes'] ?? '',
			];
		}

		wp_send_json_success( [ 'subscription' => $subscription ] );
	}

	/**
	 * AJAX handler to update subscription.
	 */
	public function ajax_update_subscription() {
		// Check nonce for security.
		if ( ! check_ajax_referer( 'labgenz_admin_subscriptions_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'Invalid security token sent.' ] );
		}

		// Check user capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'You do not have permission to perform this action.' ] );
		}

		$form_data = [];
		parse_str( $_POST['form_data'], $form_data );

		$subscription_id = isset( $form_data['subscription_id'] ) ? sanitize_text_field( $form_data['subscription_id'] ) : '';
		$status          = isset( $form_data['status'] ) ? sanitize_text_field( $form_data['status'] ) : '';
		$expiry_date     = isset( $form_data['expiry_date'] ) ? sanitize_text_field( $form_data['expiry_date'] ) : '';
		$auto_renewal    = isset( $form_data['auto_renewal'] ) ? (int) $form_data['auto_renewal'] : 0;
		$notes           = isset( $form_data['notes'] ) ? sanitize_textarea_field( $form_data['notes'] ) : '';

		if ( empty( $subscription_id ) ) {
			wp_send_json_error( [ 'message' => 'Subscription ID is required.' ] );
		}

		// Get user by subscription ID.
		$user_id = $this->get_user_by_subscription_id( $subscription_id );

		if ( ! $user_id ) {
			wp_send_json_error( [ 'message' => 'Subscription not found.' ] );
		}

		// Get subscription data
		$subscriptions = get_user_meta( $user_id, '_labgenz_subscriptions', true );

		if ( is_array( $subscriptions ) ) {
			// New structure - find and update the specific subscription
			$found = false;
			foreach ( $subscriptions as $key => $subscription ) {
				$sub_id = isset( $subscription['id'] ) ? $subscription['id'] : md5( $user_id . $subscription['type'] . ( $subscription['created'] ?? '' ) );
				if ( $sub_id === $subscription_id ) {
					// Update this subscription
					$subscriptions[ $key ]['status']       = strtolower( $status );
					$subscriptions[ $key ]['expires']      = $expiry_date;
					$subscriptions[ $key ]['auto_renewal'] = $auto_renewal;
					$subscriptions[ $key ]['notes']        = $notes;
					$found                                 = true;
					break;
				}
			}

			if ( $found ) {
				// Save the updated subscriptions array
				update_user_meta( $user_id, '_labgenz_subscriptions', $subscriptions );
			} else {
				// Fallback to old structure
				update_user_meta( $user_id, 'subscription_status', $status );
				update_user_meta( $user_id, 'subscription_expiry_date', $expiry_date );
				update_user_meta( $user_id, 'subscription_auto_renewal', $auto_renewal );
				update_user_meta( $user_id, 'subscription_admin_notes', $notes );
			}
		} else {
			// Old structure
			update_user_meta( $user_id, 'subscription_status', $status );
			update_user_meta( $user_id, 'subscription_expiry_date', $expiry_date );
			update_user_meta( $user_id, 'subscription_auto_renewal', $auto_renewal );
			update_user_meta( $user_id, 'subscription_admin_notes', $notes );
		}

		// Log the subscription update.
		$this->log_subscription_change(
			$user_id,
			'update',
			[
				'status'       => $status,
				'expiry_date'  => $expiry_date,
				'auto_renewal' => $auto_renewal,
			]
		);

		wp_send_json_success( [ 'message' => 'Subscription updated successfully.' ] );
	}

	/**
	 * AJAX handler to delete subscription.
	 */
	public function ajax_delete_subscription() {
		// Check nonce for security.
		if ( ! check_ajax_referer( 'labgenz_admin_subscriptions_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'Invalid security token sent.' ] );
		}

		// Check user capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'You do not have permission to perform this action.' ] );
		}

		$subscription_id = isset( $_POST['subscription_id'] ) ? sanitize_text_field( $_POST['subscription_id'] ) : '';

		if ( empty( $subscription_id ) ) {
			wp_send_json_error( [ 'message' => 'Subscription ID is required.' ] );
		}

		// Get user by subscription ID.
		$user_id = $this->get_user_by_subscription_id( $subscription_id );

		if ( ! $user_id ) {
			wp_send_json_error( [ 'message' => 'Subscription not found.' ] );
		}

		// Log the subscription deletion.
		$this->log_subscription_change(
			$user_id,
			'delete',
			[
				'subscription_id' => $subscription_id,
			]
		);

		// Delete subscription data
		$subscriptions = get_user_meta( $user_id, '_labgenz_subscriptions', true );

		if ( is_array( $subscriptions ) ) {
			// New structure - find and remove the specific subscription
			$found = false;
			foreach ( $subscriptions as $key => $subscription ) {
				$sub_id = isset( $subscription['id'] ) ? $subscription['id'] : md5( $user_id . $subscription['type'] . ( $subscription['created'] ?? '' ) );
				if ( $sub_id === $subscription_id ) {
					// Remove this subscription
					unset( $subscriptions[ $key ] );
					$found = true;
					break;
				}
			}

			if ( $found ) {
				// Reindex the array to prevent holes
				$subscriptions = array_values( $subscriptions );

				// If no subscriptions left, delete the meta entirely
				if ( empty( $subscriptions ) ) {
					delete_user_meta( $user_id, '_labgenz_subscriptions' );
				} else {
					// Save the updated subscriptions array
					update_user_meta( $user_id, '_labgenz_subscriptions', $subscriptions );
				}
			} else {
				// Fallback to old structure
				delete_user_meta( $user_id, 'subscription_id' );
				delete_user_meta( $user_id, 'subscription_plan_id' );
				delete_user_meta( $user_id, 'subscription_plan_name' );
				delete_user_meta( $user_id, 'subscription_status' );
				delete_user_meta( $user_id, 'subscription_start_date' );
				delete_user_meta( $user_id, 'subscription_expiry_date' );
				delete_user_meta( $user_id, 'subscription_amount' );
				delete_user_meta( $user_id, 'subscription_payment_method' );
				delete_user_meta( $user_id, 'subscription_auto_renewal' );
				delete_user_meta( $user_id, 'subscription_admin_notes' );
			}
		} else {
			// Old structure
			delete_user_meta( $user_id, 'subscription_id' );
			delete_user_meta( $user_id, 'subscription_plan_id' );
			delete_user_meta( $user_id, 'subscription_plan_name' );
			delete_user_meta( $user_id, 'subscription_status' );
			delete_user_meta( $user_id, 'subscription_start_date' );
			delete_user_meta( $user_id, 'subscription_expiry_date' );
			delete_user_meta( $user_id, 'subscription_amount' );
			delete_user_meta( $user_id, 'subscription_payment_method' );
			delete_user_meta( $user_id, 'subscription_auto_renewal' );
			delete_user_meta( $user_id, 'subscription_admin_notes' );
		}

		wp_send_json_success( [ 'message' => 'Subscription deleted successfully.' ] );
	}

	/**
	 * AJAX handler to save a new subscription.
	 */
	public function ajax_save_subscription() {
		// Check nonce for security.
		if ( ! check_ajax_referer( 'labgenz_admin_subscriptions_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'Invalid security token sent.' ] );
		}

		// Check user capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'You do not have permission to perform this action.' ] );
		}

		$form_data = [];
		parse_str( $_POST['form_data'], $form_data );

		$user_id        = isset( $form_data['user_id'] ) ? intval( $form_data['user_id'] ) : 0;
		$plan_id        = isset( $form_data['plan_id'] ) ? intval( $form_data['plan_id'] ) : 0;
		$status         = isset( $form_data['status'] ) ? sanitize_text_field( $form_data['status'] ) : '';
		$start_date     = isset( $form_data['start_date'] ) ? sanitize_text_field( $form_data['start_date'] ) : '';
		$expiry_date    = isset( $form_data['expiry_date'] ) ? sanitize_text_field( $form_data['expiry_date'] ) : '';
		$amount         = isset( $form_data['amount'] ) ? sanitize_text_field( $form_data['amount'] ) : '';
		$payment_method = isset( $form_data['payment_method'] ) ? sanitize_text_field( $form_data['payment_method'] ) : '';
		$auto_renewal   = isset( $form_data['auto_renewal'] ) ? (int) $form_data['auto_renewal'] : 0;
		$notes          = isset( $form_data['notes'] ) ? sanitize_textarea_field( $form_data['notes'] ) : '';

		// Validate required fields.
		if ( empty( $user_id ) || empty( $plan_id ) || empty( $status ) || empty( $start_date ) || empty( $expiry_date ) || empty( $amount ) ) {
			wp_send_json_error( [ 'message' => 'All required fields must be filled.' ] );
		}

		// Check if user exists.
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			wp_send_json_error( [ 'message' => 'User not found.' ] );
		}

		// Get plan name from available plans.
		$plans     = $this->get_subscription_plans();
		$plan_name = '';
		foreach ( $plans as $plan ) {
			if ( $plan->id == $plan_id ) {
				$plan_name = $plan->name;
				break;
			}
		}

		if ( empty( $plan_name ) ) {
			wp_send_json_error( [ 'message' => 'Subscription plan not found.' ] );
		}

		// Generate a unique subscription ID.
		$subscription_id = 'sub_' . uniqid();

		// Create new subscription data array
		$new_subscription = [
			'id'             => $subscription_id,
			'type'           => $plan_name,
			'plan_id'        => $plan_id,
			'plan_name'      => $plan_name,
			'status'         => strtolower( $status ),
			'created'        => $start_date,
			'expires'        => $expiry_date,
			'amount'         => $amount,
			'payment_method' => $payment_method,
			'auto_renewal'   => $auto_renewal,
			'notes'          => $notes,
		];

		// Get existing subscriptions
		$existing_subscriptions = get_user_meta( $user_id, '_labgenz_subscriptions', true );

		if ( ! is_array( $existing_subscriptions ) ) {
			$existing_subscriptions = [];
		}

		// Add new subscription to the array
		$existing_subscriptions[] = $new_subscription;

		// Save the updated subscriptions array
		update_user_meta( $user_id, '_labgenz_subscriptions', $existing_subscriptions );

		// For backward compatibility, also save in the old format
		update_user_meta( $user_id, 'subscription_id', $subscription_id );
		update_user_meta( $user_id, 'subscription_plan_id', $plan_id );
		update_user_meta( $user_id, 'subscription_plan_name', $plan_name );
		update_user_meta( $user_id, 'subscription_status', $status );
		update_user_meta( $user_id, 'subscription_start_date', $start_date );
		update_user_meta( $user_id, 'subscription_expiry_date', $expiry_date );
		update_user_meta( $user_id, 'subscription_amount', $amount );
		update_user_meta( $user_id, 'subscription_payment_method', $payment_method );
		update_user_meta( $user_id, 'subscription_auto_renewal', $auto_renewal );
		update_user_meta( $user_id, 'subscription_admin_notes', $notes );

		// Log the subscription creation.
		$this->log_subscription_change(
			$user_id,
			'create',
			[
				'subscription_id' => $subscription_id,
				'plan_id'         => $plan_id,
				'plan_name'       => $plan_name,
				'status'          => $status,
				'start_date'      => $start_date,
				'expiry_date'     => $expiry_date,
				'amount'          => $amount,
			]
		);

		wp_send_json_success( [ 'message' => 'Subscription created successfully.' ] );
	}

	/**
	 * Get user ID by subscription ID.
	 *
	 * @param string $subscription_id The subscription ID.
	 * @return int|false The user ID or false if not found.
	 */
	private function get_user_by_subscription_id( $subscription_id ) {
		return $this->database->get_user_by_subscription_id( $subscription_id );
	}

	/**
	 * Log subscription changes for auditing.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $action The action performed (create, update, delete).
	 * @param array  $data The subscription data.
	 */
	private function log_subscription_change( $user_id, $action, $data ) {
		$user       = get_user_by( 'id', $user_id );
		$admin_user = wp_get_current_user();
		$log_data   = [
			'timestamp'  => current_time( 'mysql' ),
			'user_id'    => $user_id,
			'user_name'  => $user ? $user->display_name : 'Unknown',
			'admin_id'   => $admin_user->ID,
			'admin_name' => $admin_user->display_name,
			'action'     => $action,
			'data'       => $data,
		];

		// Get existing logs or initialize empty array.
		$logs = get_option( 'labgenz_subscription_logs', [] );

		// Add new log entry.
		array_unshift( $logs, $log_data );

		// Keep only the most recent 1000 logs.
		if ( count( $logs ) > 1000 ) {
			$logs = array_slice( $logs, 0, 1000 );
		}

		// Update logs in the database.
		update_option( 'labgenz_subscription_logs', $logs );
	}
}
