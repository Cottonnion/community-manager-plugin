<?php
declare(strict_types=1);

namespace LABGENZ_CM\Groups;

/**
 * Class GroupMemberVisibility
 *
 * Manages the visibility of group members in BuddyBoss/BuddyPress groups.
 * Provides filters to exclude hidden members from member lists, admin lists, and member queries.
 *
 * Hidden members are determined by the group meta key: `hidden_member_{user_id}`.
 */
class GroupMemberVisibility {

	/**
	 * Register all filters to control member visibility.
	 *
	 * @return void
	 */
	public function register_filters(): void {
		// Core BuddyPress filters
		add_filter( 'bp_group_has_members', [ $this, 'filter_group_members' ], 10, 2 );
		add_filter( 'bp_groups_member_get_group_member_ids', [ $this, 'filter_member_ids' ], 10, 2 );

		/**
		 * Core BuddyPress filters
		 *
		 * These filters are added to the source code of the buddyboss platform plugin.
		 * They should be added again once the plugin is updated.
		 * - bp_group_list_admins_filter buddyboss-platform/bp-groups/bp-groups-template.php @bp_group_list_admins
		 * - bb_groups_loop_members_filter buddyboss-platform/bp-groups/bp-groups-functions.php @bb_groups_loop_members
		 */
		add_filter( 'bp_group_list_admins_filter', [ $this, 'filter_admins' ], 10, 2 );
		add_filter( 'bb_groups_loop_members_filter', [ $this, 'filter_members' ], 10, 2 );
	}

	/**
	 * Filter BuddyPress members template to remove hidden members.
	 *
	 * @param bool   $has_members      Whether the group has members.
	 * @param object $members_template BuddyPress members template object.
	 * @return bool Unchanged $has_members.
	 */
	public function filter_group_members( bool $has_members, object $members_template ): bool {
		global $members_template;

		if ( empty( $members_template->members ) || ! bp_get_current_group_id() ) {
			return $has_members;
		}

		$group_id = bp_get_current_group_id();

		$members_template->members = array_filter(
			$members_template->members,
			function ( $member ) use ( $group_id ): bool {
				return ! $this->is_member_hidden( $group_id, (int) $member->ID );
			}
		);

		$members_template->member_count       = count( $members_template->members );
		$members_template->total_member_count = $members_template->member_count;

		return $has_members;
	}

	/**
	 * Filter raw group member IDs to exclude hidden members.
	 *
	 * @param int[] $user_ids Array of user IDs.
	 * @param int   $group_id Group ID.
	 * @return int[] Filtered array of user IDs.
	 */
	public function filter_member_ids( array $user_ids, int $group_id ): array {
		return array_values(
			array_filter(
				$user_ids,
				function ( int $user_id ) use ( $group_id ): bool {
					return ! $this->is_member_hidden( $group_id, $user_id );
				}
			)
		);
	}

	/**
	 * Filter group admins for bp_group_list_admins().
	 *
	 * @param array  $admins Array of admin objects.
	 * @param object $group Group object.
	 * @return array Filtered array of admin objects.
	 */
	public function filter_admins( array $admins, object $group ): array {
		$group_id = $group->id ?? bp_get_current_group_id();

		return array_values(
			array_filter(
				$admins,
				function ( $admin ) use ( $group_id ): bool {
					return ! $this->is_member_hidden( $group_id, (int) $admin->user_id );
				}
			)
		);
	}

	/**
	 * Filter members for bb_groups_loop_members().
	 *
	 * @param array $members Array of member objects.
	 * @param int   $group_id Group ID.
	 * @return array Filtered array of member objects.
	 */
	public function filter_members( array $members, int $group_id ): array {
		return array_values(
			array_filter(
				$members,
				function ( $member ) use ( $group_id ): bool {
					return ! $this->is_member_hidden( $group_id, (int) $member->ID );
				}
			)
		);
	}

	/**
	 * Check if a user is hidden in a group.
	 *
	 * @param int $group_id Group ID.
	 * @param int $user_id  User ID.
	 * @return bool True if hidden, false otherwise.
	 */
	public function is_member_hidden( int $group_id, int $user_id ): bool {
		return (bool) groups_get_groupmeta( $group_id, 'hidden_member_' . $user_id, true );
	}
}
