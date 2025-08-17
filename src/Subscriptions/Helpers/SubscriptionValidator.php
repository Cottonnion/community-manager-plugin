<?php

declare(strict_types=1);

namespace LABGENZ_CM\Subscriptions\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles subscription validation logic
 *
 * @package    Labgenz_Community_Management
 * @subpackage Labgenz_Community_Management/Subscriptions/Helpers
 */
class SubscriptionValidator {

	/**
	 * Check if two subscriptions are related (from the same family)
	 *
	 * @param  string $subscription1 First subscription type
	 * @param  string $subscription2 Second subscription type
	 * @return bool
	 */
	public static function are_subscriptions_related( string $subscription1, string $subscription2 ): bool {
		// Define subscription families
		$subscription_families = [
			'basic-family'           => [ 'basic', 'monthly-basic-subscription' ],
			'apprentice-family'      => [ 'apprentice-yearly', 'apprentice-monthly' ],
			'team-leader-family'     => [ 'team-leader-yearly', 'team-leader-monthly' ],
			'freedom-builder-family' => [ 'freedom-builder-yearly', 'freedom-builder-monthly' ],
			'articles-family'        => [ 'articles-annual-subscription', 'articles-monthly-subscription' ],
		];

		// Check if both subscriptions belong to the same family
		foreach ( $subscription_families as $family => $types ) {
			if ( in_array( $subscription1, $types ) && in_array( $subscription2, $types ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if new subscription is a downgrade from existing subscription
	 *
	 * @param  string $existing_subscription Current subscription type
	 * @param  string $new_subscription      New subscription type
	 * @return bool
	 */
	public static function is_subscription_downgrade( string $existing_subscription, string $new_subscription ): bool {
		// We consider it a downgrade only if the subscriptions are related and the new one is lower tier
		if ( ! self::are_subscriptions_related( $existing_subscription, $new_subscription ) ) {
			return false;
		}

		// Define subscription hierarchy within families
		$subscription_hierarchy = [
			// Basic family
			'basic'                         => 1,
			'monthly-basic-subscription'    => 1,

			// Apprentice family
			'apprentice-yearly'             => 2,
			'apprentice-monthly'            => 1,

			// Team Leader family
			'team-leader-yearly'            => 2,
			'team-leader-monthly'           => 1,

			// Freedom Builder family
			'freedom-builder-yearly'        => 2,
			'freedom-builder-monthly'       => 1,

			// Articles family (legacy)
			'articles-annual-subscription'  => 2,
			'articles-monthly-subscription' => 1,
		];

		$existing_level = $subscription_hierarchy[ $existing_subscription ] ?? 0;
		$new_level      = $subscription_hierarchy[ $new_subscription ] ?? 0;

		return $new_level < $existing_level;
	}

	/**
	 * Check if new subscription is an upgrade from existing subscription
	 *
	 * @param  string $existing_subscription Current subscription type
	 * @param  string $new_subscription      New subscription type
	 * @return bool
	 */
	public static function is_subscription_upgrade( string $existing_subscription, string $new_subscription ): bool {
		// We consider it an upgrade only if the subscriptions are related
		if ( ! self::are_subscriptions_related( $existing_subscription, $new_subscription ) ) {
			return false;
		}

		// Define subscription hierarchy within families
		$subscription_hierarchy = [
			// Basic family
			'basic'                         => 1,
			'monthly-basic-subscription'    => 1,

			// Apprentice family
			'apprentice-yearly'             => 2,
			'apprentice-monthly'            => 1,

			// Team Leader family
			'team-leader-yearly'            => 2,
			'team-leader-monthly'           => 1,

			// Freedom Builder family
			'freedom-builder-yearly'        => 2,
			'freedom-builder-monthly'       => 1,

			// Articles family (legacy)
			'articles-annual-subscription'  => 2,
			'articles-monthly-subscription' => 1,
		];

		$existing_level = $subscription_hierarchy[ $existing_subscription ] ?? 0;
		$new_level      = $subscription_hierarchy[ $new_subscription ] ?? 0;

		return $new_level > $existing_level;
	}

	/**
	 * Check if it's a subscription from monthly to yearly within the same family
	 *
	 * @param string $existing_subscription Current subscription type
	 * @param string $new_subscription      New subscription type
	 * @return bool
	 */
	public static function is_monthly_to_yearly_upgrade( string $existing_subscription, string $new_subscription ): bool {
		if ( ! self::are_subscriptions_related( $existing_subscription, $new_subscription ) ) {
			return false;
		}

		// Define explicit monthly to yearly upgrade pairs
		$upgrade_pairs = [
			// Basic family
			'monthly-basic-subscription'    => 'basic',

			// Apprentice family
			'apprentice-monthly'            => 'apprentice-yearly',

			// Team Leader family
			'team-leader-monthly'           => 'team-leader-yearly',

			// Freedom Builder family
			'freedom-builder-monthly'       => 'freedom-builder-yearly',

			// Articles family (legacy)
			'articles-monthly-subscription' => 'articles-annual-subscription',
		];

		// Check if this is a defined monthly to yearly upgrade pair
		return isset( $upgrade_pairs[ $existing_subscription ] ) && $upgrade_pairs[ $existing_subscription ] === $new_subscription;
	}

	/**
	 * Get the primary subscription from a list of subscriptions
	 * Primary is determined by hierarchy level and expiration date
	 *
	 * @param array $subscriptions Array of subscriptions
	 * @return array|null The primary subscription or null if none are active
	 */
	public static function get_primary_subscription( array $subscriptions ): ?array {
		if ( empty( $subscriptions ) ) {
			return null;
		}

		$subscription_hierarchy = [
			// Basic family - Tier 1
			'basic'                      => 1,
			'monthly-basic-subscription' => 1,

			// Apprentice family - Tier 2
			'apprentice-yearly'          => 2,
			'apprentice-monthly'         => 2,

			// Team Leader family - Tier 3
			'team-leader-yearly'         => 3,
			'team-leader-monthly'        => 3,

			// Freedom Builder family - Tier 5 (highest)
			'freedom-builder-yearly'     => 5,
			'freedom-builder-monthly'    => 5,
		];

		$active_subscriptions = [];
		foreach ( $subscriptions as $subscription ) {
			if ( isset( $subscription['status'] ) && $subscription['status'] === 'active' &&
				isset( $subscription['expires'] ) && strtotime( $subscription['expires'] ) > time() ) {
				$active_subscriptions[] = $subscription;
			}
		}

		if ( empty( $active_subscriptions ) ) {
			return null;
		}

		// Sort by hierarchy level (highest first) and then by expiration date (furthest in future first)
		usort(
			$active_subscriptions,
			function ( $a, $b ) use ( $subscription_hierarchy ) {
				$a_level = $subscription_hierarchy[ $a['type'] ] ?? 0;
				$b_level = $subscription_hierarchy[ $b['type'] ] ?? 0;

				if ( $a_level !== $b_level ) {
					return $b_level - $a_level; // Highest level first
				}

				return strtotime( $b['expires'] ) - strtotime( $a['expires'] ); // Latest expiry first
			}
		);

		return $active_subscriptions[0] ?? null;
	}
}
