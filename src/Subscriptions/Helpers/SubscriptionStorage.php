<?php

declare(strict_types=1);

namespace LABGENZ_CM\Subscriptions\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles subscription storage and retrieval
 *
 * @package    Labgenz_Community_Management
 * @subpackage Labgenz_Community_Management/Subscriptions/Helpers
 */
class SubscriptionStorage {

	/**
	 * Meta key for storing subscriptions
	 */
	const SUBSCRIPTIONS_META = '_labgenz_subscriptions';

	/**
	 * Get all user subscriptions
	 *
	 * @param int $user_id User ID
	 * @return array Array of user subscriptions
	 */
	public static function get_user_subscriptions( int $user_id ): array {
		$subscriptions = get_user_meta( $user_id, self::SUBSCRIPTIONS_META, true );
		return is_array( $subscriptions ) ? $subscriptions : [];
	}

	/**
	 * Get the highest user subscription based on a defined priority order.
	 *
	 * @param int $user_id User ID
	 * @param array|null $priority_order Optional. Array of subscription types in descending priority.
	 * @return array|null The highest priority active subscription, or null if none found.
	 */
	public static function get_highest_user_subscription( int $user_id, ?array $priority_order = null ): ?array {
		$active_subscriptions = self::get_active_user_subscriptions( $user_id );
		if ( empty( $active_subscriptions ) ) {
			return null;
		}

		// If no priority order is given, use the first active subscription (fallback)
		if ( empty( $priority_order ) ) {
			return $active_subscriptions[0];
		}

		// Build a map of type => subscription
		$subs_by_type = [];
		foreach ( $active_subscriptions as $sub ) {
			if ( isset( $sub['type'] ) ) {
				$subs_by_type[ $sub['type'] ] = $sub;
			}
		}

		// Find the highest priority subscription present
		foreach ( $priority_order as $type ) {
			if ( isset( $subs_by_type[ $type ] ) ) {
				return $subs_by_type[ $type ];
			}
		}

		// If none match the priority order, return the first active subscription
		return $active_subscriptions[0];
	}

	/**
	 * Get the highest user subscription name (beautified).
	 *
	 * @param int $user_id User ID
	 * @param array|null $priority_order Optional. Array of subscription types in descending priority.
	 * @return string|null Subscription name, or null if none.
	 */
	public static function get_highest_user_subscription_name( int $user_id, ?array $priority_order = null ): ?string {
		$sub = self::get_highest_user_subscription( $user_id, $priority_order );

		if ( empty( $sub ) || empty( $sub['type'] ) ) {
			return null;
		}

		return self::beautify_subscription_type( $sub['type'] );
	}


	/**
	 * Beautify subscription type into a readable name.
	 *
	 * @param string $type
	 * @return string
	 */
	public static function beautify_subscription_type( string $type ): string {
		$map = [
			'basic'                         => 'Discovery Member',
			'monthly-basic-subscription'    => 'Discovery Member',
			'yearly-basic-subscription'     => 'Discovery Member',

			'apprentice-monthly'            => 'Apprentice',
			'apprentice-yearly'             => 'Apprentice',

			'team-leader-monthly'           => 'Team Leader',
			'team-leader-yearly'            => 'Team Leader',

			'freedom-builder-monthly'       => 'Freedom Builder',
			'freedom-builder-yearly'        => 'Freedom Builder',

			'articles-monthly-subscription' => 'Articles',
			'articles-annual-subscription'  => 'Articles',
		];

		return $map[ $type ] ?? ucwords( str_replace( '-', ' ', $type ) );
	}

	/**
	 * Get active user subscriptions
	 *
	 * @param int $user_id User ID
	 * @return array Array of active user subscriptions
	 */
	public static function get_active_user_subscriptions( int $user_id ): array {
		$subscriptions        = self::get_user_subscriptions( $user_id );
		$active_subscriptions = [];

		foreach ( $subscriptions as $subscription ) {
			if ( isset( $subscription['status'] ) && $subscription['status'] === 'active' &&
				isset( $subscription['expires'] ) && strtotime( $subscription['expires'] ) > time() ) {
				$active_subscriptions[] = $subscription;
			}
		}

		return $active_subscriptions;
	}

	/**
	 * Check if user has active subscription
	 *
	 * @param int $user_id User ID
	 * @return bool
	 */
	public static function user_has_active_subscription( int $user_id ): bool {
		$active_subscriptions = self::get_active_user_subscriptions( $user_id );
		return ! empty( $active_subscriptions );
	}

	/**
	 * Get all user subscription types
	 *
	 * @param int $user_id User ID
	 * @return array Array of subscription type strings
	 */
	public static function get_user_subscription_types( int $user_id ): array {
		$active_subscriptions = self::get_active_user_subscriptions( $user_id );
		$types                = [];

		foreach ( $active_subscriptions as $subscription ) {
			if ( isset( $subscription['type'] ) ) {
				$types[] = $subscription['type'];
			}
		}

		return $types;
	}

	/**
	 * Save a new subscription for a user
	 *
	 * @param int   $user_id      User ID
	 * @param array $subscription Subscription data
	 * @return bool Success status
	 */
	public static function save_subscription( int $user_id, array $subscription ): bool {
		if ( empty( $user_id ) || empty( $subscription ) || empty( $subscription['type'] ) ) {
			return false;
		}

		// Get existing subscriptions
		$subscriptions = self::get_user_subscriptions( $user_id );

		// Add an ID to the subscription if not present
		if ( ! isset( $subscription['id'] ) ) {
			$subscription['id'] = 'sub_' . uniqid();
		}

		// Add created timestamp if not present
		if ( ! isset( $subscription['created'] ) ) {
			$subscription['created'] = current_time( 'mysql' );
		}

		// Add updated timestamp
		$subscription['updated'] = current_time( 'mysql' );

		// Add the new subscription
		$subscriptions[] = $subscription;

		// Save to database
		return (bool) update_user_meta( $user_id, self::SUBSCRIPTIONS_META, $subscriptions );
	}

	/**
	 * Update an existing subscription
	 *
	 * @param int    $user_id        User ID
	 * @param string $subscription_id Subscription ID to update
	 * @param array  $new_data       New subscription data
	 * @return bool Success status
	 */
	public static function update_subscription( int $user_id, string $subscription_id, array $new_data ): bool {
		if ( empty( $user_id ) || empty( $subscription_id ) ) {
			return false;
		}

		$subscriptions = self::get_user_subscriptions( $user_id );
		$updated       = false;

		foreach ( $subscriptions as $key => $subscription ) {
			$sub_id = isset( $subscription['id'] ) ? $subscription['id'] : null;
			if ( $sub_id === $subscription_id ) {
				// Update the subscription with new data
				$subscriptions[ $key ]            = array_merge( $subscription, $new_data );
				$subscriptions[ $key ]['updated'] = current_time( 'mysql' );
				$updated                          = true;
				break;
			}
		}

		if ( $updated ) {
			return update_user_meta( $user_id, self::SUBSCRIPTIONS_META, $subscriptions );
		}

		return false;
	}

	/**
	 * Get a specific subscription by ID
	 *
	 * @param int    $user_id        User ID
	 * @param string $subscription_id Subscription ID
	 * @return array|null Subscription data or null if not found
	 */
	public static function get_subscription_by_id( int $user_id, string $subscription_id ): ?array {
		$subscriptions = self::get_user_subscriptions( $user_id );

		foreach ( $subscriptions as $subscription ) {
			$sub_id = isset( $subscription['id'] ) ? $subscription['id'] : null;
			if ( $sub_id === $subscription_id ) {
				return $subscription;
			}
		}

		return null;
	}

	/**
	 * Get subscription by type
	 *
	 * @param int    $user_id           User ID
	 * @param string $subscription_type Subscription type
	 * @param bool   $active_only       Whether to only return active subscriptions
	 * @return array|null Subscription data or null if not found
	 */
	public static function get_subscription_by_type( int $user_id, string $subscription_type, bool $active_only = false ): ?array {
		$subscriptions = $active_only
			? self::get_active_user_subscriptions( $user_id )
			: self::get_user_subscriptions( $user_id );

		foreach ( $subscriptions as $subscription ) {
			if ( isset( $subscription['type'] ) && $subscription['type'] === $subscription_type ) {
				return $subscription;
			}
		}

		return null;
	}

	/**
	 * Delete a subscription
	 *
	 * Soft-delete: mark the subscription as deleted instead of removing it
	 * so we preserve audit trail. Frontend only shows status === 'active'.
	 *
	 * @param int    $user_id        User ID
	 * @param string $subscription_id Subscription ID to delete
	 * @return bool Success status
	 */
	public static function delete_subscription( int $user_id, string $subscription_id ): bool {
		$subscriptions = self::get_user_subscriptions( $user_id );
		$updated       = false;

		foreach ( $subscriptions as $key => $subscription ) {
			$sub_id = isset( $subscription['id'] ) ? $subscription['id'] : null;
			if ( $sub_id === $subscription_id ) {
				// Soft delete: flag as deleted and timestamp it
				$subscriptions[ $key ]['status']     = 'deleted';
				$subscriptions[ $key ]['deleted_at'] = current_time( 'mysql' );
				$subscriptions[ $key ]['updated']    = current_time( 'mysql' );
				$updated                             = true;
				break;
			}
		}

		if ( $updated ) {
			return update_user_meta( $user_id, self::SUBSCRIPTIONS_META, $subscriptions );
		}

		return false;
	}

	/**
	 * Update subscription status to inactive
	 *
	 * @param int    $user_id        User ID
	 * @param string $subscription_id Subscription ID
	 * @return bool Success status
	 */
	public static function deactivate_subscription( int $user_id, string $subscription_id ): bool {
		return self::update_subscription(
			$user_id,
			$subscription_id,
			[
				'status' => 'inactive',
			]
		);
	}

	/**
	 * Calculate remaining days for a subscription
	 *
	 * @param array $subscription Subscription data
	 * @return int Number of days remaining (0 if expired)
	 */
	public static function calculate_remaining_days( array $subscription ): int {
		// Treat missing fields as non-active / expired
		if ( ! isset( $subscription['expires'] ) || ! isset( $subscription['status'] ) || $subscription['status'] !== 'active' ) {
			return 0;
		}

		$expiry_timestamp = strtotime( $subscription['expires'] );
		$now              = time();

		if ( $expiry_timestamp <= $now ) {
			return 0;
		}

		// Cast to int because ceil() returns float in PHP 8+
		return (int) ceil( ( $expiry_timestamp - $now ) / DAY_IN_SECONDS );
	}

	/**
	 * Add remaining days from one subscription to another
	 *
	 * When used for monthly -> yearly upgrades, this will transfer the remaining
	 * days from the monthly subscription to the new yearly subscription and then
	 * mark the monthly subscription as deleted to avoid duplicates.
	 *
	 * @param int    $user_id                User ID
	 * @param string $source_subscription_id Source subscription ID (e.g., monthly)
	 * @param string $target_subscription_id Target subscription ID (e.g., yearly)
	 * @return bool Success status
	 */
	public static function add_remaining_days( int $user_id, string $source_subscription_id, string $target_subscription_id ): bool {
		$source_subscription = self::get_subscription_by_id( $user_id, $source_subscription_id );
		$target_subscription = self::get_subscription_by_id( $user_id, $target_subscription_id );

		if ( ! $source_subscription || ! $target_subscription ) {
			return false;
		}

		// Calculate remaining days in source subscription
		$remaining_days = self::calculate_remaining_days( $source_subscription );
		if ( $remaining_days <= 0 ) {
			// Source has no transferable time: just soft-delete it to prevent overlap
			self::delete_subscription( $user_id, $source_subscription_id );
			return true;
		}

		// Add days to target subscription expiry
		$target_expiry_ts = strtotime( $target_subscription['expires'] );
		$new_expiry       = date( 'Y-m-d H:i:s', strtotime( "+{$remaining_days} days", $target_expiry_ts ) );

		// Update target subscription with new expiry
		$update_result = self::update_subscription(
			$user_id,
			$target_subscription_id,
			[
				'expires' => $new_expiry,
			]
		);

		// Soft-delete source subscription after successful transfer
		if ( $update_result ) {
			self::delete_subscription( $user_id, $source_subscription_id );
			return true;
		}

		return false;
	}
}
