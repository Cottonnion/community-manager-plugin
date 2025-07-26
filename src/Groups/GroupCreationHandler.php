<?php
declare(strict_types=1);

namespace LABGENZ_CM\Groups;

use LABGENZ_CM\Subscriptions\SubscriptionHandler;
use LABGENZ_CM\Core\OrganizationAccess;
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
	 * Validate organization access token for the current user
	 *
	 * @param int $user_id User ID
	 * @return bool
	 */
	private function validate_organization_access_token( int $user_id ): bool {
		// Check if user has approved organization access status
		$user_status = get_user_meta( $user_id, OrganizationAccess::STATUS_META_KEY, true );
		
		if ( $user_status === OrganizationAccess::STATUS_APPROVED ) {
			return true;
		}

		// Check if user has valid access token in session or request
		if ( isset( $_POST['access_token'] ) && ! empty( $_POST['access_token'] ) ) {
			$token = sanitize_text_field( $_POST['access_token'] );
			$user = get_user_by( 'id', $user_id );
			
			if ( $user ) {
				$organization_access = new OrganizationAccess();
				$verification_result = $organization_access->verify_access_token( $token, $user->user_email );
				
				if ( ! is_wp_error( $verification_result ) ) {
					return true;
				}
			}
		}

		// Check if user has valid token in user meta (for cases where they accessed via email link)
		$token_data = get_user_meta( $user_id, OrganizationAccess::TOKEN_META_KEY, true );
		if ( $token_data && is_array( $token_data ) ) {
			// Check if token is not expired
			if ( time() <= $token_data['expires_at'] ) {
				return true;
			}
		}

		return false;
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
		try {
			$user_id = get_current_user_id();
			
			// Validate user is logged in
			if ( ! $user_id ) {
				wp_send_json_error([
					'message' => __('You must be logged in to create an organization.', 'labgenz-community-management')
				]);
				return;
			}

			// Validate subscription access
			if ( ! $this->subscription_handler->user_has_organization_subscription( $user_id ) ) {
				wp_send_json_error([
					'message' => __('You need an Organization subscription to create an organization.', 'labgenz-community-management')
				]);
				return;
			}

			// Validate organization access token
			if ( ! $this->validate_organization_access_token( $user_id ) ) {
				wp_send_json_error([
					'message' => __('You need organization access approval to create an organization. Please request access first.', 'labgenz-community-management')
				]);
				return;
			}

			// Clear any existing cart issues first
			if ( function_exists( 'WC' ) && WC()->cart ) {
				self::cleanup_cart_payment_products();
			}

			$post  = $_POST;
			$files = $_FILES;

			$this->validate_post_data( $post );
			$this->validate_nonce( $post['organization_nonce'] );

			$group_id = $this->create_group( $post );
			
			$this->update_group_meta( $group_id, $post );
			$this->update_group_categories( $group_id, $post );

			bp_groups_set_group_type( $group_id, 'organization' );


			$logo_url        = $this->handle_avatar_upload( $group_id, $files, $post );
			$bp_avatar_nonce = wp_create_nonce( 'bp_avatar_cropstore' );

			// Store group_id in user meta for checkout
			$user_id = get_current_user_id();
			if ( $user_id ) {
				update_user_meta( $user_id, 'mlmmc_pending_group_id', $group_id );

				// Clear organization access token since user has successfully created organization
				$this->clear_organization_access_token( $user_id );
			}

			// Set group_id in WooCommerce session
			if ( function_exists( 'WC' ) && WC()->session ) {
				WC()->session->set( 'mlmmc_group_id', $group_id );
				if ( method_exists( WC()->session, 'set_customer_session_cookie' ) ) {
					WC()->session->set_customer_session_cookie( true );
				}
			}

			// Build checkout URL
			$selected_plan = sanitize_text_field( $post['selected_plan'] );
			$product_id    = $this->get_product_id_by_sku( $selected_plan );

			if ( ! $product_id ) {
				throw new \Exception( 'Payment product not found' );
			}

			$checkout_url = add_query_arg(
				[ 'add-to-cart' => $product_id ],
				wc_get_checkout_url()
			);

			$response_message = 'Organization created! Please complete checkout.';
			if ( $logo_url ) {
				$response_message .= ' Avatar uploaded.';
			}

			// Get organization categories information
			$categories_info = [];
			if ( isset( $post['organization_categories'] ) && is_array( $post['organization_categories'] ) ) {
				$categories_info = $this->get_categories_info( $post['organization_categories'] );
			}

			// Get debug information
			$debug_info = groups_get_groupmeta( $group_id, 'mlmmc_debug_info' );

			wp_send_json_success(
				[
					'message'         => $response_message,
					'group_id'        => $group_id,
					'logo_url'        => $logo_url,
					'bp_avatar_nonce' => $bp_avatar_nonce,
					'checkout_url'    => $checkout_url,
					'group_name'      => $post['organization_name'],
					'group_slug'      => $post['organization_slug'] ?? '',
					'categories'      => $categories_info,
					'debug'           => $debug_info,
				]
			);

		} catch ( \Exception $e ) {
			wp_send_json_error(
				[
					'message'        => 'A critical error occurred: ' . $e->getMessage(),
					'exception_type' => get_class( $e ),
				]
			);
		} catch ( \Error $e ) {
			wp_send_json_error(
				[
					'message'    => 'A fatal error occurred: ' . $e->getMessage(),
					'error_type' => get_class( $e ),
					'line'       => $e->getLine(),
					'file'       => $e->getFile(),
				]
			);
		}
	}

	/**
	 * Clean up cart payment products - ensure only 1 payment product with quantity 1
	 */
	private static function cleanup_cart_payment_products(): void {
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
	 * Validates the POST data for required fields and types.
	 */
	private function validate_post_data( array $post ): void {
		if ( ! is_array( $post ) ) {
			throw new \Exception( 'Server error: POST data is not an array.' );
		}

		$required_fields = [ 'organization_nonce', 'organization_name', 'selected_plan' ];
		foreach ( $required_fields as $field ) {
			if ( ! isset( $post[ $field ] ) ) {
				throw new \Exception( sprintf( __( 'Missing required field: %s', 'buddyboss' ), $field ) );
			}
		}

		if ( empty( sanitize_text_field( $post['organization_name'] ) ) ) {
			throw new \Exception( 'Organization name is required' );
		}

		if ( empty( sanitize_text_field( $post['selected_plan'] ) ) ) {
			throw new \Exception( 'Please select a pricing plan' );
		}
	}

	/**
	 * Validates the nonce for security.
	 */
	private function validate_nonce( string $nonce ): void {
		if ( ! wp_verify_nonce( $nonce, 'create_organization' ) ) {
			throw new \Exception( __( 'Nonce verification failed. Please try again.', 'buddyboss' ) );
		}
	}

	/**
	 * Creates a BuddyBoss/BuddyPress group.
	 */
	private function create_group( array $post ): int {
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

		return (int) $group_id;
	}

	/**
	 * Updates group meta.
	 */
	private function update_group_meta( int $group_id, array $post ): void {
		groups_update_groupmeta( $group_id, 'mlmmc_checkout_status', 'false' );
		groups_update_groupmeta( $group_id, 'mlmmc_organization_payment_type', sanitize_text_field( $post['selected_plan'] ) );
		groups_update_groupmeta( $group_id, 'mlmmc_total_seats', 30 );
		groups_update_groupmeta( $group_id, 'mlmmc_used_seats', 0 );
		groups_update_groupmeta( $group_id, 'mlmmc_subscription_status', 'active' );

		// Store organization categories if provided
		if ( isset( $post['organization_categories'] ) && is_array( $post['organization_categories'] ) ) {
			$categories = array_map( 'intval', $post['organization_categories'] );
			groups_update_groupmeta( $group_id, 'mlmmc_organization_categories', $categories );
		}
	}

	/**
	 * Update group categories (taxonomies) and enroll LearnDash group with courses
	 */
	private function update_group_categories( int $group_id, array $post ): void {
		if ( ! isset( $post['organization_categories'] ) || ! is_array( $post['organization_categories'] ) ) {
			return;
		}

		$categories = array_map( 'intval', $post['organization_categories'] );

		// Remove empty or invalid category IDs
		$categories = array_filter(
			$categories,
			function ( $cat_id ) {
				return $cat_id > 0 && term_exists( $cat_id );
			}
		);

		if ( empty( $categories ) ) {
			return;
		}

		// Set group categories using WordPress taxonomy functions
		// Include the organization type (39933) along with the categories
		if ( function_exists( 'wp_set_object_terms' ) ) {
			$all_terms = array_merge( [39933], $categories ); // Add organization type ID
			wp_set_object_terms( $group_id, $all_terms, 'bp_group_type', false );
		}

		// Store as group meta for easier retrieval
		groups_update_groupmeta( $group_id, 'mlmmc_organization_categories', $categories );

		// Get LearnDash group ID associated with this BuddyPress group
		$learndash_group_id = groups_get_groupmeta( $group_id, '_sync_group_id' );

		// Enroll the LearnDash group with courses from these categories
		$enrollment_result = $this->enroll_learndash_group_with_courses( intval($learndash_group_id), $categories );

		// Get all courses from the selected categories for reference
		$courses = $this->get_courses_from_categories( $categories );

		// Store debug information
		$debug_info = [
			'learndash_group_id' => $learndash_group_id,
			'categories' => $categories,
			'courses_found' => $courses,
			'enrollment_result' => $enrollment_result,
			'sync_group_id_exists' => !empty($learndash_group_id),
		];

		// Store debug info in group meta
		groups_update_groupmeta( $group_id, 'mlmmc_debug_info', $debug_info );

		if ( ! empty( $courses ) ) {
			// Store course IDs in group meta for reference
			groups_update_groupmeta( $group_id, 'mlmmc_enrolled_courses', $courses );
		}
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
	 * Get all courses from specified LearnDash course categories
	 *
	 * @param array $category_ids Array of category term IDs
	 * @return array Array of course post IDs
	 */
	private function get_courses_from_categories( array $category_ids ): array {
		if ( empty( $category_ids ) ) {
			return [];
		}

		// Ensure all category IDs are valid integers
		$category_ids = array_map( 'intval', $category_ids );
		$category_ids = array_filter( $category_ids, function( $id ) {
			return $id > 0;
		});

		if ( empty( $category_ids ) ) {
			return [];
		}

		// Query courses with the specified categories
		$courses_query = new \WP_Query([
			'post_type' => 'sfwd-courses', // LearnDash course post type
			'post_status' => 'publish',
			'posts_per_page' => -1, // Get all courses
			'fields' => 'ids', // Only return post IDs for performance
			'tax_query' => [
				[
					'taxonomy' => 'ld_course_category', // LearnDash course category taxonomy
					'field' => 'term_id',
					'terms' => $category_ids,
					'operator' => 'IN'
				]
			],
			'meta_query' => [
				[
					'key' => '_sfwd-courses',
					'compare' => 'EXISTS'
				]
			]
		]);

		$course_ids = $courses_query->posts;

		return $course_ids;
	}

	/**
	 * Get category information including name, description, and courses
	 *
	 * @param array $category_ids Array of category term IDs
	 * @return array Array of category information with name, description, and courses
	 */
	private function get_categories_info( array $category_ids ): array {
		if ( empty( $category_ids ) ) {
			return [];
		}

		// Ensure all category IDs are valid integers
		$category_ids = array_map( 'intval', $category_ids );
		$category_ids = array_filter( $category_ids, function( $id ) {
			return $id > 0;
		});

		if ( empty( $category_ids ) ) {
			return [];
		}

		$categories_info = [];

		foreach ( $category_ids as $category_id ) {
			// Get term information from LearnDash course category taxonomy
			$term = get_term( $category_id, 'ld_course_category' );
			
			if ( $term && ! is_wp_error( $term ) ) {
			// Get courses for this category
			$courses = $this->get_courses_info_for_category( $category_id );				$categories_info[] = [
					'id' => $category_id,
					'name' => $term->name,
					'description' => $term->description,
					'slug' => $term->slug,
					'count' => $term->count,
					'courses' => $courses,
				];
			}
		}

		// Log the category information for debugging
		return $categories_info;
	}

	/**
	 * Get course information for a specific category
	 *
	 * @param int $category_id Category term ID
	 * @return array Array of course information
	 */
	private function get_courses_info_for_category( int $category_id ): array {
		if ( $category_id <= 0 ) {
			return [];
		}

		// Query courses with the specified category
		$courses_query = new \WP_Query([
			'post_type' => 'sfwd-courses', // LearnDash course post type
			'post_status' => 'publish',
			'posts_per_page' => -1, // Get all courses
			'tax_query' => [
				[
					'taxonomy' => 'ld_course_category', // LearnDash course category taxonomy
					'field' => 'term_id',
					'terms' => [ $category_id ],
					'operator' => 'IN'
				]
			],
			'meta_query' => [
				[
					'key' => '_sfwd-courses',
					'compare' => 'EXISTS'
				]
			]
		]);

		$courses_info = [];

		if ( $courses_query->have_posts() ) {
			foreach ( $courses_query->posts as $course ) {
				$courses_info[] = [
					'id' => $course->ID,
					'title' => $course->post_title,
					'slug' => $course->post_name,
					'excerpt' => $course->post_excerpt,
					'status' => $course->post_status,
					'url' => get_permalink( $course->ID ),
				];
			}
		}

		// Reset post data
		wp_reset_postdata();

		return $courses_info;
	}

	/**
	 * Enroll LearnDash group with courses from specified categories
	 *
	 * @param int $learndash_group_id LearnDash group ID
	 * @param array $category_ids Array of category term IDs
	 * @return bool Success status
	 */
	private function enroll_learndash_group_with_courses( int $learndash_group_id, array $category_ids ): bool {
		if ( ! $learndash_group_id || empty( $category_ids ) ) {
			return false;
		}

		// Get courses from the specified categories
		$course_ids = $this->get_courses_from_categories( $category_ids );

		if ( empty( $course_ids ) ) {
			return false;
		}

		// Check if LearnDash functions are available
		$learndash_functions_available = false;
		$update_function = null;
		$get_function = null;

		if ( function_exists( 'learndash_get_group_enrolled_courses' ) && function_exists( 'ld_update_group_access_list' ) ) {
			$learndash_functions_available = true;
			$update_function = 'ld_update_group_access_list';
			$get_function = 'learndash_get_group_enrolled_courses';
		} elseif ( function_exists( 'learndash_set_group_enrolled_courses' ) && function_exists( 'learndash_get_group_courses_list' ) ) {
			$learndash_functions_available = true;
			$update_function = 'learndash_set_group_enrolled_courses';
			$get_function = 'learndash_get_group_courses_list';
		} elseif ( function_exists( 'learndash_update_group_courses' ) ) {
			$learndash_functions_available = true;
			$update_function = 'learndash_update_group_courses';
			$get_function = 'learndash_get_group_courses_list';
		}

		if ( ! $learndash_functions_available ) {
			// Try direct database method as fallback
			return $this->enroll_learndash_group_direct( $learndash_group_id, $course_ids );
		}

		// Get currently enrolled courses to avoid duplicates
		$current_courses = [];
		if ( $get_function ) {
			$current_courses = call_user_func( $get_function, $learndash_group_id );
			$current_courses = is_array( $current_courses ) ? $current_courses : [];
		}

		// Merge new courses with existing ones
		$all_courses = array_unique( array_merge( $current_courses, $course_ids ) );

		// Update the group's course access list
		$update_result = false;
		if ( $update_function === 'ld_update_group_access_list' ) {
			$update_result = ld_update_group_access_list( $learndash_group_id, $all_courses, 'sfwd-courses' );
		} elseif ( $update_function === 'learndash_set_group_enrolled_courses' ) {
			$update_result = learndash_set_group_enrolled_courses( $learndash_group_id, $all_courses );
		} elseif ( $update_function === 'learndash_update_group_courses' ) {
			$update_result = learndash_update_group_courses( $learndash_group_id, $all_courses );
		}

		if ( $update_result ) {
			// Update group meta with enrolled courses for reference
			update_post_meta( $learndash_group_id, 'enrolled_courses', $all_courses );
			update_post_meta( $learndash_group_id, 'enrolled_courses_count', count( $all_courses ) );
			update_post_meta( $learndash_group_id, 'last_course_update', current_time( 'mysql' ) );

			return true;
		} else {
			return false;
		}
	}

	/**
	 * Enroll LearnDash group with courses using direct database method (fallback)
	 *
	 * @param int $learndash_group_id LearnDash group ID
	 * @param array $course_ids Array of course post IDs
	 * @return bool Success status
	 */
	private function enroll_learndash_group_direct( int $learndash_group_id, array $course_ids ): bool {
		if ( ! $learndash_group_id || empty( $course_ids ) ) {
			return false;
		}

		// Get existing courses from post meta
		$existing_courses = get_post_meta( $learndash_group_id, 'learndash_group_enrolled_' . $learndash_group_id, true );
		$existing_courses = is_array( $existing_courses ) ? $existing_courses : [];

		// Merge with new courses
		$all_courses = array_unique( array_merge( $existing_courses, $course_ids ) );

		// Update the group's course list in post meta
		$update_result = update_post_meta( $learndash_group_id, 'learndash_group_enrolled_' . $learndash_group_id, $all_courses );

		// Also try common LearnDash meta keys
		update_post_meta( $learndash_group_id, '_learndash_group_courses', $all_courses );
		update_post_meta( $learndash_group_id, '_ld_group_courses', $all_courses );

		// Store as serialized array (some LearnDash versions use this)
		update_post_meta( $learndash_group_id, 'learndash_group_courses', $all_courses );

		if ( $update_result ) {
			return true;
		}

		return false;
	}

	/**
	 * Handles avatar upload and moves it to the group-avatars directory.
	 */
	private function handle_avatar_upload( int $group_id, array $files, array $post ): string {
		if ( empty( $files['organization_logo']['name'] ) || $files['organization_logo']['error'] !== UPLOAD_ERR_OK ) {
			return '';
		}

		try {
			require_once ABSPATH . 'wp-admin/includes/file.php';

			$uploaded_file    = $files['organization_logo'];
			$upload_dir       = wp_upload_dir();
			$group_avatar_dir = trailingslashit( $upload_dir['basedir'] ) . 'group-avatars/' . $group_id . '/';

			if ( ! file_exists( $group_avatar_dir ) ) {
				wp_mkdir_p( $group_avatar_dir );
			}

			$ext             = pathinfo( $uploaded_file['name'], PATHINFO_EXTENSION );
			$avatar_filename = 'group-avatar-bpfull.' . $ext;
			$avatar_path     = $group_avatar_dir . $avatar_filename;

			$movefile = wp_handle_upload( $uploaded_file, [ 'test_form' => false ] );

			if ( $movefile && empty( $movefile['error'] ) ) {
				if ( ! @copy( $movefile['file'], $avatar_path ) ) {
					throw new \Exception( 'Failed to copy uploaded file to group avatar directory.' );
				}

				@chmod( $avatar_path, 0644 );
				@unlink( $movefile['file'] );

				$avatar_url = trailingslashit( $upload_dir['baseurl'] ) . 'group-avatars/' . $group_id . '/' . $avatar_filename;

				$item_id   = $group_id;
				$item_type = isset( $post['item_type'] ) ? sanitize_text_field( $post['item_type'] ) : null;

				do_action(
					'groups_avatar_uploaded',
					$item_id,
					$item_type,
					[
						'avatar'     => $avatar_url,
						'item_id'    => $item_id,
						'item_type'  => $item_type,
						'object'     => 'group',
						'avatar_dir' => 'group-avatars',
					]
				);

				return $avatar_url;
			} else {
				throw new \Exception( 'Avatar upload failed: ' . $movefile['error'] );
			}
		} catch ( \Exception $e ) {
			// Optionally log error
			return '';
		}
	}

	/**
	 * Gets the group URL if available.
	 */
	private function get_group_url( int $group_id ): string {
		return function_exists( 'bp_get_group_permalink' ) ? bp_get_group_permalink( groups_get_group( [ 'group_id' => $group_id ] ) ) : '';
	}

	/**
	 * Get WooCommerce product ID by SKU
	 */
	private function get_product_id_by_sku( string $sku ): ?int {
		if ( ! function_exists( 'wc_get_product_id_by_sku' ) ) {
			return null;
		}
		return wc_get_product_id_by_sku( $sku );
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
                                    timer: 8000,
                                    timerProgressBar: true
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
	 * Clear organization access token when organization is created
	 */
	private function clear_organization_access_token( int $user_id ): void {
		// Clear the access token and related data
		delete_user_meta( $user_id, '_labgenz_org_access_token' );

		// Update status to completed since they've successfully created organization
		update_user_meta( $user_id, '_labgenz_org_access_status', 'completed' );
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
