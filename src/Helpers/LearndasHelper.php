<?php
declare(strict_types=1);

namespace LABGENZ_CM\Helpers;

class LearndasHelper {

    /**
	 * Synchronize BuddyBoss organizer role with LearnDash group leader role
	 *
	 * @param int $user_id  The user ID to add as a LearnDash group leader
	 * @param int $bb_group_id The BuddyBoss group ID
	 * @return bool Whether the synchronization was successful
	 */
	public function sync_learndash_leadership( $user_id, $bb_group_id ) {
		// Make sure LearnDash is active
		if ( ! function_exists( 'learndash_get_groups_user_ids' ) || ! function_exists( 'learndash_is_group_leader_user' ) ) {
			return false;
		}

		// Get the associated LearnDash group ID(s)
		$ld_group_id = $this->get_associated_ld_group_id( $bb_group_id );
		if ( ! $ld_group_id ) {
			return false;
		}

		// Check if user is already a leader of this group
		if ( learndash_is_group_leader_user( $user_id ) &&
			learndash_is_user_in_group( $user_id, $ld_group_id, true ) ) { // true = as leader
			// User is already a leader, no need to do anything
			return true;
		}

		// Add the user as a group leader
		$success = $this->add_user_as_ld_group_leader( $user_id, $ld_group_id );

		// Logging removed

		return $success;
	}

	/**
	 * Get the associated LearnDash group ID for a BuddyBoss group
	 *
	 * @param int $bb_group_id The BuddyBoss group ID
	 * @return int|false The LearnDash group ID or false if not found
	 */
	public function get_associated_ld_group_id( $bb_group_id ) {
		// First check if there's a direct mapping stored in group meta
		$ld_group_id = groups_get_groupmeta( $bb_group_id, 'learndash_group_id', true );

		if ( ! empty( $ld_group_id ) && is_numeric( $ld_group_id ) ) {
			return intval( $ld_group_id );
		}

		// If no direct mapping, check if we have BuddyBoss/LearnDash integration enabled
		// which typically stores this association somewhere

		// Check for BuddyBoss Platform Pro integration
		if ( function_exists( 'bbp_pro_get_group_sync_settings' ) ) {
			$sync_settings = bbp_pro_get_group_sync_settings( $bb_group_id );
			if ( ! empty( $sync_settings['ldg'] ) ) {
				return intval( $sync_settings['ldg'] );
			}
		}

		// Check for Integration with LearnDash plugin
		// This is a common plugin to connect BuddyBoss with LearnDash
		$ld_group_id = get_post_meta( $bb_group_id, '_sync_group_id', true );
		if ( ! empty( $ld_group_id ) ) {
			return intval( $ld_group_id );
		}

		// Try more generic approach - search for LearnDash groups with matching name
		$bb_group = groups_get_group( $bb_group_id );
		if ( empty( $bb_group->name ) ) {
			return false;
		}

		// Query for LearnDash groups with similar name
		$args = [
			'post_type'      => 'groups', // LearnDash group post type
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'title'          => $bb_group->name,
			'exact'          => true,
		];

		$matching_groups = get_posts( $args );

		if ( empty( $matching_groups ) ) {
			return false;
		}

		// Store this mapping for future use
		groups_update_groupmeta( $bb_group_id, 'learndash_group_id', $matching_groups[0]->ID );

		return $matching_groups[0]->ID;
	}

	/**
	 * Add a user as a leader to a LearnDash group
	 *
	 * @param int $user_id The user ID to add as leader
	 * @param int $ld_group_id The LearnDash group ID
	 * @return bool Whether the operation was successful
	 */
	public function add_user_as_ld_group_leader( $user_id, $ld_group_id ) {
		// Make sure we have the required LearnDash functions
		if ( ! function_exists( 'learndash_set_groups_administrators' ) ) {
			return false;
		}

		// First, ensure the user has the group leader role
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return false;
		}

		// Add the group leader role if they don't have it already
		if ( ! in_array( 'group_leader', (array) $user->roles ) ) {
			$user->add_role( 'group_leader' );
		}

		// Get current group administrators
		$current_admins = learndash_get_groups_administrator_ids( $ld_group_id );

		// Add the new user to the administrators list
		if ( ! in_array( $user_id, $current_admins ) ) {
			$current_admins[] = $user_id;
		}

		// Update the group administrators
		$result = learndash_set_groups_administrators( $ld_group_id, $current_admins );

		// If the above method doesn't work (sometimes it doesn't), try the direct approach
		if ( ! $result ) {
			// Try to use update_post_meta
			$meta_key = 'learndash_group_leaders_' . $ld_group_id;
			$success  = update_user_meta( $user_id, $meta_key, $ld_group_id );

			// Also try updating the group's list of leaders
			$leaders_meta_key = 'learndash_group_leaders';
			$current_leaders  = get_post_meta( $ld_group_id, $leaders_meta_key, true );

			if ( ! is_array( $current_leaders ) ) {
				$current_leaders = [];
			}

			if ( ! in_array( $user_id, $current_leaders ) ) {
				$current_leaders[] = $user_id;
				update_post_meta( $ld_group_id, $leaders_meta_key, $current_leaders );
			}

			// Direct DB approach as last resort
			global $wpdb;
			$table = $wpdb->prefix . 'learndash_group_leaders';

			// Check if the entry already exists
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE group_id = %d AND user_id = %d",
					$ld_group_id,
					$user_id
				)
			);

			if ( ! $exists ) {
				$wpdb->insert(
					$table,
					[
						'group_id' => $ld_group_id,
						'user_id'  => $user_id,
					],
					[ '%d', '%d' ]
				);
			}

			// Clear LearnDash caches
			if ( function_exists( 'learndash_purge_user_group_cache' ) ) {
				learndash_purge_user_group_cache( $user_id );
			}

			return true;
		}

		return $result;
	}
}