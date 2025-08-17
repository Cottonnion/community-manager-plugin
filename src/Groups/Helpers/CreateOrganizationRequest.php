<?php
declare(strict_types=1);

namespace LABGENZ_CM\Groups\Helpers;

use LABGENZ_CM\Subscriptions\SubscriptionHandler;
use LABGENZ_CM\Core\OrganizationAccess;
use LABGENZ_CM\Groups\GroupCreationHandler;

class CreateOrganizationRequest extends GroupCreationHandler
{


    private SubscriptionHandler $subscription_handler;
	private GroupCreationHandler $group_creation_handler;

    public function __construct() {
	    $this->subscription_handler = SubscriptionHandler::get_instance();
		$this->group_creation_handler = new GroupCreationHandler();
    
	}

    public function create_organization_request($post_data){

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
				$this->cleanup_cart_payment_products();
			}

			$post  = $post_data;
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
	 * Clear organization access token when organization is created
	 */
	private function clear_organization_access_token( int $user_id ): void {
		// Clear the access token and related data
		delete_user_meta( $user_id, '_labgenz_org_access_token' );

		// Update status to completed since they've successfully created organization
		update_user_meta( $user_id, '_labgenz_org_access_status', 'completed' );
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
	 * Get WooCommerce product ID by SKU
	 */
	private function get_product_id_by_sku( string $sku ): ?int {
		if ( ! function_exists( 'wc_get_product_id_by_sku' ) ) {
			return null;
		}
		return wc_get_product_id_by_sku( $sku );
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
			$courses = $this->get_courses_info_for_category( $category_id );				
            $categories_info[] = [
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
}