<?php
namespace LABGENZ_CM\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database helper class for direct database operations
 */
class Database {
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return Database
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		// Initialize any database setup
	}

	/**
	 * Get user's field visibility setting from the database
	 *
	 * @param int $field_id The profile field ID
	 * @param int $user_id  The user ID
	 * @return string|null The visibility setting or null if not found
	 */
	public function get_user_field_visibility( $field_id, $user_id ) {
		// Safety check
		if ( empty( $field_id ) || empty( $user_id ) ) {
			error_log( "Database::get_user_field_visibility - Invalid parameters: field_id={$field_id}, user_id={$user_id}" );
			return null;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'bb_xprofile_visibility';

		// Query the database directly
		$visibility = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT value FROM $table_name WHERE field_id = %d AND user_id = %d",
				$field_id,
				$user_id
			)
		);

		// Log the result for debugging
		error_log( "Database::get_user_field_visibility - Result for field_id={$field_id}, user_id={$user_id}: {$visibility}" );

		return $visibility;
	}

	/**
	 * Save user's field visibility setting to the database
	 *
	 * @param int    $field_id   The profile field ID
	 * @param int    $user_id    The user ID
	 * @param string $visibility The visibility setting
	 * @return bool Whether the operation was successful
	 */
	public function save_user_field_visibility( $field_id, $user_id, $visibility ) {
		// Safety check
		if ( empty( $field_id ) || empty( $user_id ) || empty( $visibility ) ) {
			error_log( "Database::save_user_field_visibility - Invalid parameters: field_id={$field_id}, user_id={$user_id}, visibility={$visibility}" );
			return false;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'bb_xprofile_visibility';

		// Prepare data for insertion or update
		$data = [
			'field_id'     => $field_id,
			'user_id'      => $user_id,
			'value'        => $visibility,
			'last_updated' => current_time( 'mysql' ),
		];

		// Check if an entry already exists for this user and field
		$existing_entry = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM $table_name WHERE field_id = %d AND user_id = %d",
				$field_id,
				$user_id
			)
		);

		$result = false;
		if ( $existing_entry ) {
			// Update the existing entry
			$result = $wpdb->update(
				$table_name,
				$data,
				[ 'id' => $existing_entry->id ]
			);
		} else {
			// Insert a new entry
			$result = $wpdb->insert( $table_name, $data );
		}

		// Log the result for debugging
		error_log(
			"Database::save_user_field_visibility - Operation result for field_id={$field_id}, user_id={$user_id}, visibility={$visibility}: " .
			( $result ? 'Success' : 'Failed - ' . $wpdb->last_error )
		);

		return (bool) $result;
	}

	/**
	 * Get user ID by subscription ID.
	 *
	 * @param string $subscription_id The subscription ID.
	 * @return int|false The user ID or false if not found.
	 */
	public function get_user_by_subscription_id( $subscription_id ) {
		global $wpdb;

		// First try with the new subscriptions structure
		$query = $wpdb->prepare(
			"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s",
			'_labgenz_subscriptions'
		);

		$results = $wpdb->get_results( $query );

		foreach ( $results as $result ) {
			$user_id       = $result->user_id;
			$subscriptions = get_user_meta( $user_id, '_labgenz_subscriptions', true );

			if ( is_array( $subscriptions ) ) {
				foreach ( $subscriptions as $subscription ) {
					$sub_id = isset( $subscription['id'] ) ? $subscription['id'] : md5( $user_id . $subscription['type'] . ( $subscription['created'] ?? '' ) );
					if ( $sub_id === $subscription_id ) {
						return $user_id;
					}
				}
			}
		}

		// Fallback to the old structure
		$query = $wpdb->prepare(
			"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'subscription_id' AND meta_value = %s LIMIT 1",
			$subscription_id
		);

		return $wpdb->get_var( $query );
	}

	/**
	 * Get subscriptions with filtering and pagination.
	 *
	 * @param array $args The query arguments.
	 * @return array The subscriptions array with items and total count.
	 */
	public function get_subscriptions( $args = [] ) {
		global $wpdb;

		$defaults = [
			'paged'      => 1,
			'per_page'   => 10,
			'status'     => '',
			'date_range' => '',
			'search'     => '',
		];

		$args = wp_parse_args( $args, $defaults );

		// Sanitize arguments.
		$paged      = absint( $args['paged'] );
		$per_page   = absint( $args['per_page'] );
		$status     = sanitize_text_field( $args['status'] );
		$date_range = sanitize_text_field( $args['date_range'] );
		$search     = sanitize_text_field( $args['search'] );

		// Calculate offset.
		$offset = ( $paged - 1 ) * $per_page;

		// Start building query.
		$query = "
            SELECT u.ID as user_id, u.display_name as user_name, u.user_email
            FROM {$wpdb->users} u
            JOIN {$wpdb->usermeta} um ON u.ID = um.user_id 
            WHERE um.meta_key = '_labgenz_subscriptions'
        ";

		// Add search filter
		if ( ! empty( $search ) ) {
			$search_term = '%' . $wpdb->esc_like( $search ) . '%';
			$query      .= $wpdb->prepare(
				' AND (u.display_name LIKE %s OR u.user_email LIKE %s)',
				$search_term,
				$search_term
			);
		}

		// Count total items for pagination.
		$count_query = "SELECT COUNT(*) FROM ($query) as temp";
		$total       = $wpdb->get_var( $count_query );

		// Add sorting and pagination.
		$query .= ' ORDER BY u.display_name ASC';
		$query .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $per_page, $offset );

		// Get the results.
		$users = $wpdb->get_results( $query );

		// Format the results.
		$subscriptions = [];
		foreach ( $users as $user ) {
			$user_subscriptions = get_user_meta( $user->user_id, '_labgenz_subscriptions', true );

			if ( ! is_array( $user_subscriptions ) ) {
				continue;
			}

			foreach ( $user_subscriptions as $subscription ) {
				// Apply status filter
				if ( ! empty( $status ) && $subscription['status'] !== strtolower( $status ) ) {
					continue;
				}

				// Apply date range filter
				if ( ! empty( $date_range ) ) {
					$subscription_start = isset( $subscription['created'] )
						? strtotime( $subscription['created'] )
						: 0;

					$now   = current_time( 'timestamp' );
					$today = date( 'Y-m-d', $now );

					switch ( $date_range ) {
						case 'today':
							if ( date( 'Y-m-d', $subscription_start ) !== $today ) {
								continue 2; // Skip this subscription
							}
							break;
						case 'this_week':
							$week_start = strtotime( 'monday this week', $now );
							$week_end   = strtotime( 'sunday this week', $now );
							if ( $subscription_start < $week_start || $subscription_start > $week_end ) {
								continue 2; // Skip this subscription
							}
							break;
						case 'this_month':
							$month_start = strtotime( date( 'Y-m-01', $now ) );
							$month_end   = strtotime( date( 'Y-m-t', $now ) );
							if ( $subscription_start < $month_start || $subscription_start > $month_end ) {
								continue 2; // Skip this subscription
							}
							break;
						case 'last_month':
							$last_month_start = strtotime( 'first day of last month', $now );
							$last_month_end   = strtotime( 'last day of last month', $now );
							if ( $subscription_start < $last_month_start || $subscription_start > $last_month_end ) {
								continue 2; // Skip this subscription
							}
							break;
						case 'this_year':
							$year_start = strtotime( date( 'Y-01-01', $now ) );
							$year_end   = strtotime( date( 'Y-12-31', $now ) );
							if ( $subscription_start < $year_start || $subscription_start > $year_end ) {
								continue 2; // Skip this subscription
							}
							break;
					}
				}

				$subscription_obj                 = new \stdClass();
				$subscription_obj->id             = isset( $subscription['id'] ) ? $subscription['id'] : md5( $user->user_id . $subscription['type'] . ( $subscription['created'] ?? '' ) );
				$subscription_obj->user_id        = $user->user_id;
				$subscription_obj->user_name      = $user->user_name;
				$subscription_obj->user_email     = $user->user_email;
				$subscription_obj->plan_id        = isset( $subscription['plan_id'] ) ? $subscription['plan_id'] : '';
				$subscription_obj->plan_name      = isset( $subscription['plan_name'] ) ? $subscription['plan_name'] : $subscription['type'];
				$subscription_obj->status         = ucfirst( $subscription['status'] );
				$subscription_obj->start_date     = isset( $subscription['created'] )
					? date_i18n( get_option( 'date_format' ), strtotime( $subscription['created'] ) )
					: '';
				$subscription_obj->expiry_date    = date_i18n( get_option( 'date_format' ), strtotime( $subscription['expires'] ) );
				$subscription_obj->amount         = isset( $subscription['amount'] ) ? $subscription['amount'] : '';
				$subscription_obj->payment_method = isset( $subscription['payment_method'] ) ? $subscription['payment_method'] : '';
				$subscription_obj->auto_renewal   = isset( $subscription['auto_renewal'] ) ? (bool) $subscription['auto_renewal'] : false;

				$subscriptions[] = $subscription_obj;
			}
		}

		// Manually handle pagination after filtering subscriptions
		$total_after_filtering = count( $subscriptions );
		$start                 = ( $paged - 1 ) * $per_page;
		$subscriptions         = array_slice( $subscriptions, $start, $per_page );

		return [
			'items' => $subscriptions,
			'total' => $total_after_filtering,
		];
	}
}

// Initialize the class
Database::get_instance();
