<?php

namespace LABGENZ_CM\Groups;

use LABGENZ_CM\Groups\Helpers\GroupHelpers;

/**
 * Handles filtering of groups based on user membership and group relationships.
 *
 * Filters the groups directory to only show groups the user is a member of
 * or groups that are parents of the user's current groups.
 *
 * @since 1.0.0
 */
class GroupFiltersHandler {
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		// Modify group query arguments to filter groups based on user membership and relationships
		add_filter( 'bp_ajax_querystring', [ $this, 'modify_groups_query' ], 10, 2 );

		// Also apply to non-AJAX group queries
		add_filter( 'bp_groups_get_groups_args', [ $this, 'modify_groups_get_args' ], 10, 1 );

		// Filter for the initial groups directory page load
		add_filter( 'bp_before_has_groups_parse_args', [ $this, 'filter_groups_directory' ], 10, 1 );

		// Filter the total group count
		add_filter( 'bp_get_total_group_count', [ $this, 'filter_total_group_count' ], 10, 1 );
	}

	/**
	 * Modify the groups query to only show groups the user is a member of
	 * or groups that are parents of the user's current groups.
	 *
	 * @param string $query_string The query string for the BP loop.
	 * @param string $object The type of object being requested.
	 * @return string Modified query string.
	 */
	public function modify_groups_query( string $query_string, string $object ): string {
		// Only apply to group queries
		if ( $object !== 'groups' ) {
			return $query_string;
		}

		// Skip filtering for administrators
		if ( current_user_can( 'administrator' ) ) {
			return $query_string;
		}

		// Parse the query string
		parse_str( $query_string, $args );

		// Get the groups the user can access
		$accessible_group_ids = $this->get_user_accessible_group_ids();

		if ( ! empty( $accessible_group_ids ) ) {
			// If include parameter already exists, intersect with our groups
			if ( isset( $args['include'] ) && ! empty( $args['include'] ) ) {
				$existing_ids = explode( ',', $args['include'] );
				$intersection = array_intersect( $existing_ids, $accessible_group_ids );

				if ( empty( $intersection ) ) {
					// No overlap, include only our accessible groups
					$args['include'] = implode( ',', $accessible_group_ids );
				} else {
					// Use the intersection
					$args['include'] = implode( ',', $intersection );
				}
			} else {
				// No existing include parameter, set to our groups
				$args['include'] = implode( ',', $accessible_group_ids );
			}
		} else {
			// If user has no accessible groups, show none
			$args['include'] = '0';  // This will return no groups
		}

		return http_build_query( $args );
	}

	/**
	 * Modify the groups get arguments for non-AJAX requests.
	 *
	 * @param array $args The arguments for bp_groups_get_groups().
	 * @return array Modified arguments.
	 */
	public function modify_groups_get_args( array $args ): array {
		// Skip filtering for administrators
		if ( current_user_can( 'administrator' ) ) {
			return $args;
		}

		// Get the groups the user can access
		$accessible_group_ids = $this->get_user_accessible_group_ids();

		if ( ! empty( $accessible_group_ids ) ) {
			// If include parameter already exists, intersect with our groups
			if ( isset( $args['include'] ) && ! empty( $args['include'] ) ) {
				$intersection = array_intersect( $args['include'], $accessible_group_ids );

				if ( empty( $intersection ) ) {
					// No overlap, include only our accessible groups
					$args['include'] = $accessible_group_ids;
				} else {
					// Use the intersection
					$args['include'] = $intersection;
				}
			} else {
				// No existing include parameter, set to our groups
				$args['include'] = $accessible_group_ids;
			}
		} else {
			// If user has no accessible groups, show none
			$args['include'] = [ 0 ];  // This will return no groups
		}

		return $args;
	}

	/**
	 * Get the groups that the user can access.
	 * This includes:
	 * - Groups the user is a member of
	 * - Parent groups of user's groups
	 * - Subgroups of user's groups
	 *
	 * @return array Array of group IDs the user can access.
	 */
	private function get_user_accessible_group_ids(): array {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return [];
		}

		// Get the base accessible groups (user groups + parent groups)
		$accessible_group_ids = GroupHelpers::get_user_accessible_group_ids( $user_id );

		// Now also get the subgroups of user's groups
		$user_groups    = groups_get_user_groups( $user_id );
		$user_group_ids = ! empty( $user_groups['groups'] ) ? $user_groups['groups'] : [];

		$subgroup_ids = [];
		foreach ( $user_group_ids as $group_id ) {
			$child_groups = GroupHelpers::get_child_group_ids( $group_id );
			if ( ! empty( $child_groups ) ) {
				$subgroup_ids = array_merge( $subgroup_ids, $child_groups );
			}
		}

		// Combine all accessible groups (user's groups, parent groups, and subgroups)
		$all_accessible_group_ids = array_unique( array_merge( $accessible_group_ids, $subgroup_ids ) );

		/**
		 * Filter the final list of group IDs a user can access.
		 *
		 * @param array $all_accessible_group_ids Array of group IDs.
		 * @param int $user_id User ID.
		 */
		return apply_filters( 'labgenz_cm_user_all_accessible_group_ids', $all_accessible_group_ids, $user_id );
	}

	/**
	 * Filter the groups directory to only show accessible groups.
	 * This is for the initial page load of the groups directory.
	 *
	 * @param array $args Arguments for the groups query.
	 * @return array Modified arguments.
	 */
	public function filter_groups_directory( array $args ): array {
		// Don't filter for administrators
		if ( current_user_can( 'administrator' ) ) {
			return $args;
		}

		// Don't filter on group specific pages (single group)
		if ( bp_is_group() || bp_is_group_create() ) {
			return $args;
		}

		// Get accessible groups
		$accessible_group_ids = $this->get_user_accessible_group_ids();

		if ( empty( $accessible_group_ids ) ) {
			// No accessible groups, show none
			$args['include'] = [ 0 ]; // This will return no groups
		} else {
			// If include already exists, intersect with our accessible groups
			if ( ! empty( $args['include'] ) ) {
				$intersection = array_intersect( (array) $args['include'], $accessible_group_ids );

				if ( empty( $intersection ) ) {
					$args['include'] = $accessible_group_ids;
				} else {
					$args['include'] = $intersection;
				}
			} else {
				$args['include'] = $accessible_group_ids;
			}
		}

		return $args;
	}

	/**
	 * Filter the total group count to only count accessible groups.
	 *
	 * @param int $count The original group count.
	 * @return int Modified group count.
	 */
	public function filter_total_group_count( int $count ): int {
		// Don't filter for administrators
		if ( current_user_can( 'administrator' ) ) {
			return $count;
		}

		$accessible_group_ids = $this->get_user_accessible_group_ids();

		return count( $accessible_group_ids );
	}
}
