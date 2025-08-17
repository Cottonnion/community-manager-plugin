<?php
declare(strict_types=1);

namespace LABGENZ_CM\Groups;

use LABGENZ_CM\Subscriptions\SubscriptionHandler;
use LABGENZ_CM\Core\OrganizationAccess;
use LABGENZ_CM\Groups\Helpers\CreateOrganizationRequest;
/**
 * Handles AJAX organization (group) creation for Labgenz Community Management.
 *
 * @package LabgenzCommunityManagement
 */
class GroupCreationHandler {


	/**
	 * @var SubscriptionsHandler
	 */
	private SubscriptionHandler $subscription_handler;

	/**
	 * Registers AJAX actions for organization creation.
	 *
	 * @return void
	 */
	public function __construct() {

		$this->subscription_handler = SubscriptionHandler::get_instance();

		add_action( 'wp_ajax_create_organization_request', [ $this, 'handle_ajax_create_organization_request' ] );

		// Pre-fill form with organization access request data
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_prefill_scripts' ] );
		add_action( 'wp_ajax_get_org_request_data', [ $this, 'get_org_request_data' ] );

		// Clean up cart on frontend hooks
		add_action( 'woocommerce_before_checkout_form', [ $this, 'ensure_single_payment_product' ] );
		add_action( 'woocommerce_before_cart', [ $this, 'ensure_single_payment_product' ] );
		add_action( 'wp_loaded', [ $this, 'ensure_single_payment_product' ], 20 );
		add_action( 'woocommerce_checkout_init', [ $this, 'clear_payment_validation_notices' ] );

		// Prevent adding multiple payment products
		add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'validate_payment_product_add_to_cart' ], 10, 3 );

		// When order is created, move group_id from user meta to order meta
		add_action(
			'woocommerce_thankyou',
			function ( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( ! $order ) {
					return;
				}

				$user_id = $order->get_user_id();
				if ( $user_id ) {
					$group_id = get_user_meta( $user_id, 'mlmmc_pending_group_id', true );
					if ( $group_id ) {
						$order->add_meta_data( 'mlmmc_group_id', $group_id, true );
						$order->save();
						delete_user_meta( $user_id, 'mlmmc_pending_group_id' );
					}
				}
			}
		);

		// When order is completed, set group meta
		add_action(
			'woocommerce_order_status_completed',
			function ( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( ! $order ) {
					return;
				}

				$group_id = $order->get_meta( 'mlmmc_group_id' );
				if ( $group_id ) {
					groups_update_groupmeta( $group_id, 'mlmmc_checkout_status', 'true' );
				}
			}
		);
	}

	/**
	 * Validates adding payment products to cart - prevents duplicates
	 */
	public function validate_payment_product_add_to_cart( $passed, $product_id, $quantity ) {
		if ( ! $passed ) {
			return $passed;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return $passed;
		}

		$sku          = $product->get_sku();
		$payment_skus = [ 'group-payments', 'individual-payments' ];

		if ( ! in_array( $sku, $payment_skus ) ) {
			return $passed;
		}

		// Check if cart already has payment products
		if ( ! WC()->cart->is_empty() ) {
			$payment_products_in_cart = [];

			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				$cart_product = $cart_item['data'];
				if ( $cart_product && method_exists( $cart_product, 'get_sku' ) ) {
					$cart_sku = $cart_product->get_sku();
					if ( in_array( $cart_sku, $payment_skus ) ) {
						$payment_products_in_cart[] = $cart_sku;

						// If trying to add a different payment product
						if ( $cart_sku !== $sku ) {
							wc_add_notice( 'You can only have one payment product in your cart at a time.', 'error' );
							return false;
						}

						// Same product already exists - prevent duplicate
						if ( $cart_sku === $sku ) {
							wc_add_notice( 'This payment product is already in your cart.', 'notice' );
							return false;
						}
					}
				}
			}
		}

		return $passed;
	}

	/**
	 * Handles AJAX request to create an organization (group).
	 *
	 * @return void
	 */
	public function handle_ajax_create_organization_request() {
		try{
			$organization_request_helper = new CreateOrganizationRequest();
			$organization_request_helper->create_organization_request( $_POST );
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
			return;
		} catch ( \Throwable $e ) {
			wp_send_json_error( 'An unexpected error occurred. Please try again later.' . ' Error: ' . $e->getMessage() );
			return;
		} catch ( \Error $e ) {
			wp_send_json_error( 'An unexpected error occurred. Please try again later.' . ' Error: ' . $e->getMessage() );
			return;
		}
	}

	/**
	 * Clean up cart payment products - ensure only 1 payment product with quantity 1
	 */
	protected static function cleanup_cart_payment_products(): void {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}

		$payment_skus  = [ 'group-payments', 'individual-payments' ];
		$cart_contents = WC()->cart->get_cart();
		$payment_items = [];

		// Find all payment items in cart
		foreach ( $cart_contents as $cart_item_key => $cart_item ) {
			$product = $cart_item['data'];
			if ( $product && method_exists( $product, 'get_sku' ) ) {
				$sku = $product->get_sku();
				if ( in_array( $sku, $payment_skus ) ) {
					$payment_items[] = [
						'key'      => $cart_item_key,
						'sku'      => $sku,
						'quantity' => $cart_item['quantity'],
					];
				}
			}
		}

		if ( empty( $payment_items ) ) {
			return;
		}

		// If multiple payment items exist, keep only group-payments
		if ( count( $payment_items ) > 1 ) {
			$keep_item = null;

			// Prefer group-payments over individual-payments
			foreach ( $payment_items as $item ) {
				if ( $item['sku'] === 'group-payments' ) {
					$keep_item = $item;
					break;
				}
			}

			// If no group-payments, keep first individual-payments
			if ( ! $keep_item ) {
				$keep_item = $payment_items[0];
			}

			// Remove all except the one we're keeping
			foreach ( $payment_items as $item ) {
				if ( $item['key'] !== $keep_item['key'] ) {
					WC()->cart->remove_cart_item( $item['key'] );
				}
			}

			// Update quantity to 1 for kept item
			if ( $keep_item['quantity'] > 1 ) {
				WC()->cart->set_quantity( $keep_item['key'], 1 );
			}
		} else {
			// Single payment item - ensure quantity is 1
			$item = $payment_items[0];
			if ( $item['quantity'] > 1 ) {
				WC()->cart->set_quantity( $item['key'], 1 );
			}
		}

		WC()->cart->calculate_totals();
	}

	/**
	 * Public wrapper for cart cleanup
	 */
	public function ensure_single_payment_product(): void {
		self::cleanup_cart_payment_products();
	}

	/**
	 * Creates a BuddyBoss/BuddyPress group.
	 */
	protected function create_group( array $post ): int {
		if ( ! function_exists( 'groups_create_group' ) ) {
			throw new \Exception( 'BuddyPress/BuddyBoss is not active or loaded' );
		}

		$user_id                  = get_current_user_id();
		$organization_name        = sanitize_text_field( $post['organization_name'] );
		$organization_description = isset( $post['organization_description'] ) ? sanitize_textarea_field( $post['organization_description'] ) : '';

		// If name or description is empty, try to get from organization access request
		if ( empty( $organization_name ) || empty( $organization_description ) ) {
			$request_data = get_user_meta( $user_id, '_labgenz_org_access_request_data', true );

			if ( $request_data ) {
				if ( empty( $organization_name ) && ! empty( $request_data['organization_name'] ) ) {
					$organization_name = sanitize_text_field( $request_data['organization_name'] );
				}
				if ( empty( $organization_description ) && ! empty( $request_data['description'] ) ) {
					$organization_description = sanitize_textarea_field( $request_data['description'] );
				}
			}
		}

		$group_id = groups_create_group(
			[
				'name'        => $organization_name,
				'description' => $organization_description,
				'status'      => 'private',
				'creator_id'  => $user_id,
			]
		);

		if ( ! $group_id || is_wp_error( $group_id ) ) {
			$error_msg = is_wp_error( $group_id ) ? $group_id->get_error_message() : 'Failed to create organization';
			throw new \Exception( $error_msg );
		}

		// Auto-join all site administrators to the group and hide them
		$this->auto_join_site_admins( (int) $group_id );

		return (int) $group_id;
	}
	/**
	 * Promote all site administrators to organizers in BuddyBoss groups and group leaders in LearnDash
	 *
	 * @param int $group_id The BuddyBoss group ID to promote admins in
	 * @return void
	 */
	private function promote_site_admins_to_organizers( int $group_id ): void {
		// Get all users with manage_options capability (site admins)
		$admin_users = get_users([
			'capability' => 'manage_options',
			'fields' => 'ID'
		]);

		if ( empty( $admin_users ) ) {
			return;
		}

		// Get the synced LearnDash group ID from BuddyBoss group meta
		$learndash_group_id = groups_get_groupmeta( $group_id, 'sync_group_id', true );

		foreach ( $admin_users as $admin_user_id ) {
			$admin_user_id = (int) $admin_user_id;
			
			// Ensure admin is a member of the BuddyBoss group first
			if ( ! groups_is_user_member( $admin_user_id, $group_id ) ) {
				// Join the admin to the group first
				$join_result = groups_join_group( $group_id, $admin_user_id );
				if ( ! $join_result ) {
					continue; // Skip if join failed
				}
			}

			// Promote to organizer in BuddyBoss group
			$this->promote_to_buddyboss_organizer( $admin_user_id, $group_id );

			// Promote to group leader in LearnDash if group is synced
			if ( ! empty( $learndash_group_id ) ) {
				$this->promote_to_learndash_group_leader( $admin_user_id, $learndash_group_id );
			}

			// Mark this member as hidden from group displays (optional)
			$this->mark_member_as_hidden( $admin_user_id, $group_id );
		}
	}

	/**
	 * Promote user to organizer in BuddyBoss group
	 *
	 * @param int $user_id The user ID to promote
	 * @param int $group_id The BuddyBoss group ID
	 * @return bool Success status
	 */
	private function promote_to_buddyboss_organizer( int $user_id, int $group_id ): bool {
		global $wpdb, $bp;

		// Update the user's role to 'admin' (organizer) in BuddyBoss groups table
		$updated = $wpdb->update(
			$bp->groups->table_name_members,
			[
				'is_admin' => 1,
				'is_mod' => 0, // Clear mod status when promoting to admin
				'date_modified' => bp_core_current_time()
			],
			[
				'user_id' => $user_id,
				'group_id' => $group_id
			],
			['%d', '%d', '%s'],
			['%d', '%d']
		);

		// Also update via meta key method for redundancy
		bp_update_user_meta( $user_id, "bp_group_{$group_id}_role", 'admin' );

		// Clear any cached group data
		wp_cache_delete( $group_id, 'bp_groups_members' );
		wp_cache_delete( "bp_groups_memberships_{$user_id}", 'bp' );

		return $updated !== false;
	}

	/**
	 * Promote user to group leader in LearnDash group
	 *
	 * @param int $user_id The user ID to promote
	 * @param int $learndash_group_id The LearnDash group ID
	 * @return bool Success status
	 */
	private function promote_to_learndash_group_leader( int $user_id, int $learndash_group_id ): bool {
		// Method 1: Using LearnDash function
		if ( function_exists( 'ld_update_group_access' ) ) {
			$group_leaders = learndash_get_groups_group_leaders( $learndash_group_id );
			if ( ! in_array( $user_id, $group_leaders ) ) {
				$group_leaders[] = $user_id;
				
				// Update group leaders via LearnDash method
				update_post_meta( $learndash_group_id, 'learndash_group_leaders', $group_leaders );
				
				// Also ensure user has group leader capabilities
				ld_update_group_access( $user_id, $learndash_group_id, false );
			}
		}

		// Method 2: Direct meta key update (redundancy)
		$current_leaders = get_post_meta( $learndash_group_id, 'learndash_group_leaders', true );
		if ( ! is_array( $current_leaders ) ) {
			$current_leaders = [];
		}

		if ( ! in_array( $user_id, $current_leaders ) ) {
			$current_leaders[] = $user_id;
			update_post_meta( $learndash_group_id, 'learndash_group_leaders', $current_leaders );
		}

		// Update user meta to reflect group leadership
		$user_groups = get_user_meta( $user_id, 'learndash_group_leaders', true );
		if ( ! is_array( $user_groups ) ) {
			$user_groups = [];
		}

		if ( ! in_array( $learndash_group_id, $user_groups ) ) {
			$user_groups[] = $learndash_group_id;
			update_user_meta( $user_id, 'learndash_group_leaders', $user_groups );
		}

		// Clear LearnDash caches
		if ( function_exists( 'learndash_cache_clear' ) ) {
			learndash_cache_clear();
		}

		return true;
	}

	/**
	 * Updated auto-join function that also promotes admins
	 *
	 * @param int $group_id The group ID to join admins to
	 * @return void
	 */
	private function auto_join_site_admins( int $group_id ): void {
		// Get all users with manage_options capability (site admins)
		$admin_users = get_users([
			'capability' => 'manage_options',
			'fields' => 'ID'
		]);

		if ( empty( $admin_users ) ) {
			return;
		}

		foreach ( $admin_users as $admin_user_id ) {
			$admin_user_id = (int) $admin_user_id;
			
			// Check if admin is already a member
			$is_member = groups_is_user_member( $admin_user_id, $group_id );
			
			if ( ! $is_member ) {
				// Join the admin to the group
				$join_result = groups_join_group( $group_id, $admin_user_id );
				if ( ! $join_result ) {
					continue; // Skip if join failed
				}
			}

			// Promote to organizer (this will handle both BuddyBoss and LearnDash)
			$this->promote_to_buddyboss_organizer( $admin_user_id, $group_id );

			// Handle LearnDash group leader promotion if synced
			$learndash_group_id = groups_get_groupmeta( $group_id, 'sync_group_id', true );
			if ( ! empty( $learndash_group_id ) ) {
				$this->promote_to_learndash_group_leader( $admin_user_id, $learndash_group_id );
			}

			// Mark as hidden from group displays
			$this->mark_member_as_hidden( $admin_user_id, $group_id );
		}
	}

	/**
	 * Batch promote all admins across all synced groups
	 *
	 * @return array Results summary
	 */
	public function batch_promote_admins_all_groups(): array {
		global $wpdb, $bp;

		$results = [
			'processed_groups' => 0,
			'promoted_users' => 0,
			'errors' => []
		];

		// Get all BuddyBoss groups that have LearnDash sync
		$synced_groups = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT group_id, meta_value as learndash_group_id 
				FROM {$bp->groups->table_name_groupmeta} 
				WHERE meta_key = %s 
				AND meta_value != ''",
				'sync_group_id'
			)
		);

		if ( empty( $synced_groups ) ) {
			$results['errors'][] = 'No synced groups found';
			return $results;
		}

		foreach ( $synced_groups as $group_data ) {
			try {
				$this->promote_site_admins_to_organizers( (int) $group_data->group_id );
				$results['processed_groups']++;
			} catch ( Exception $e ) {
				$results['errors'][] = "Error processing group {$group_data->group_id}: " . $e->getMessage();
			}
		}

		// Count total promoted users
		$admin_users = get_users([
			'capability' => 'manage_options',
			'fields' => 'ID'
		]);
		$results['promoted_users'] = count( $admin_users ) * $results['processed_groups'];

		return $results;
	}

	/**
	 * Mark a group member as hidden from displays
	 *
	 * @param int $user_id The user ID to hide
	 * @param int $group_id The group ID
	 * @return void
	 */
	private function mark_member_as_hidden( int $user_id, int $group_id ): void {
		// Store hidden status in group member meta
		groups_update_groupmeta( $group_id, "hidden_member_{$user_id}", true );
		
		// Also store in user meta for easier querying
		update_user_meta( $user_id, "hidden_in_group_{$group_id}", true );
	}

	/**
	 * Get or create LearnDash group associated with BuddyPress group
	 *
	 * @param int $bp_group_id BuddyPress group ID
	 * @return int|false LearnDash group ID or false on failure
	 */
	private function get_or_create_learndash_group( int $bp_group_id ) {
		if ( ! $bp_group_id ) {
			return false;
		}

		// Check if LearnDash group already exists for this BP group
		$learndash_group_id = groups_get_groupmeta( $bp_group_id, 'learndash_group_id' );

		if ( $learndash_group_id && get_post( $learndash_group_id ) && get_post_type( $learndash_group_id ) === 'groups' ) {
			return intval( $learndash_group_id );
		}

		// Get BuddyPress group details
		$bp_group = groups_get_group( $bp_group_id );
		if ( ! $bp_group ) {
			return false;
		}

		// Create new LearnDash group
		$learndash_group_data = [
			'post_title' => $bp_group->name,
			'post_content' => $bp_group->description,
			'post_status' => 'publish',
			'post_type' => 'groups', // LearnDash group post type
			'post_author' => get_current_user_id(),
			'meta_input' => [
				'_bp_group_id' => $bp_group_id,
				'_groups_group_enabled' => 'yes',
				'_groups_group_price_type' => 'closed',
			]
		];

		$learndash_group_id = wp_insert_post( $learndash_group_data );

		if ( $learndash_group_id && ! is_wp_error( $learndash_group_id ) ) {
			// Store the association in both directions
			groups_update_groupmeta( $bp_group_id, 'learndash_group_id', $learndash_group_id );
			update_post_meta( $learndash_group_id, '_bp_group_id', $bp_group_id );

			return $learndash_group_id;
		}

		return false;
	}


	/**
	 * Gets the group URL if available.
	 */
	private function get_group_url( int $group_id ): string {
		return function_exists( 'bp_get_group_permalink' ) ? bp_get_group_permalink( groups_get_group( [ 'group_id' => $group_id ] ) ) : '';
	}


	/**
	 * Enqueue scripts for form pre-filling
	 */
	public function enqueue_prefill_scripts(): void {
		// Only enqueue on group creation pages
		if ( strpos( $_SERVER['REQUEST_URI'] ?? '', '/groups/create/step/group-details/' ) === false ) {
			return;
		}

		wp_enqueue_script( 'sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], '11.0.0', true );

		wp_add_inline_script(
			'sweetalert2',
			"
            jQuery(document).ready(function($) {
                // Get organization request data
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'get_org_request_data',
                        nonce: '" . wp_create_nonce( 'get_org_request_data' ) . "'
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            const data = response.data;
                            let prefilledFields = [];
                            
                            // Pre-fill organization name
                            if (data.organization_name) {
                                $('input[name=\"organization_name\"]').val(data.organization_name);
                                prefilledFields.push('Organization Name');
                            }
                            
                            // Pre-fill description
                            if (data.description) {
                                $('textarea[name=\"organization_description\"]').val(data.description);
                                prefilledFields.push('Description');
                            }
                            
                            // Show notification if fields were pre-filled
                            if (prefilledFields.length > 0) {
                                Swal.fire({
                                    title: 'Form Pre-filled',
                                    text: 'The following fields have been automatically filled from your organization access request: ' + prefilledFields.join(', ') + '. You can edit them if needed.',
                                    icon: 'info',
                                    confirmButtonText: 'Got it!',
                                    // timer: 10000,
                                    // timerProgressBar: true
                                });
                            }
                        }
                    }
                });
            });
        "
		);
	}

	/**
	 * AJAX handler to get organization request data
	 */
	public function get_org_request_data(): void {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'get_org_request_data' ) ) {
			wp_send_json_error( 'Security check failed' );
			return;
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'User not logged in' );
			return;
		}

		$user_id = get_current_user_id();

		// Get organization access request data
		$request_data = get_user_meta( $user_id, '_labgenz_org_access_request_data', true );

		if ( ! $request_data ) {
			wp_send_json_success( null );
			return;
		}

		// Return only the fields we need for pre-filling
		$prefill_data = [
			'organization_name' => $request_data['organization_name'] ?? '',
			'description'       => $request_data['description'] ?? '',
		];

		wp_send_json_success( $prefill_data );
	}


	/**
	 * Clear payment validation notices on checkout if cart is valid
	 */
	public function clear_payment_validation_notices() {
		if ( ! WC()->cart->is_empty() ) {
			$payment_skus  = [ 'group-payments', 'individual-payments' ];
			$payment_count = 0;

			foreach ( WC()->cart->get_cart() as $cart_item ) {
				$cart_product = $cart_item['data'];
				if ( $cart_product && method_exists( $cart_product, 'get_sku' ) ) {
					$cart_sku = $cart_product->get_sku();
					if ( in_array( $cart_sku, $payment_skus ) ) {
						++$payment_count;
					}
				}
			}

			// If only one payment product, clear any validation errors
			if ( $payment_count <= 1 ) {
				$notices = wc_get_notices( 'error' );
				if ( $notices ) {
					foreach ( $notices as $key => $notice ) {
						if ( strpos( $notice['notice'], 'You can only have one payment product' ) !== false ) {
							wc_clear_notices();
						}
					}
				}
			}
		}
	}
}
