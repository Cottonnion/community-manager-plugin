<?php
declare(strict_types=1);

namespace LABGENZ_CM\Groups;

/**
 * Helper class for managing group member visibility in BuddyBoss/BuddyPress groups.
 *
 * Filters group member lists and member IDs to exclude hidden members based on group meta.
 */
class GroupMemberVisibility {

	/**
	 * Constructor. Initializes filters for group member visibility.
	 */
	public function __construct() {
		$this->init_filters();
	}

	/**
	 * Register filters to modify group member queries and lists.
	 *
	 * @return void
	 */
	private function init_filters(): void {
		add_filter( 'bp_group_has_members', [ $this, 'filter_group_members' ], 10, 2 );
		add_filter( 'bp_groups_member_get_group_member_ids', [ $this, 'filter_member_ids' ], 10, 2 );
	}

	/**
	 * Filter the display of group members to exclude hidden members.
	 *
	 * @param bool   $has_members Whether the group has members.
	 * @param object $members_template BuddyPress members template object.
	 * @return bool Whether the group has members (unchanged).
	 */
	public function filter_group_members( $has_members, $members_template ) {
		global $members_template;

		if ( empty( $members_template->members ) || ! bp_get_current_group_id() ) {
			return $has_members;
		}

		$group_id = bp_get_current_group_id();

		// Remove hidden members from the members list.
		$members_template->members = array_filter(
			$members_template->members,
			function ( $member ) use ( $group_id ) {
				return ! $this->is_member_hidden( $group_id, (int) $member->ID );
			}
		);

		$members_template->member_count       = count( $members_template->members );
		$members_template->total_member_count = $members_template->member_count;

		return $has_members;
	}

	/**
	 * Filter raw group member IDs to exclude hidden ones.
	 *
	 * @param int[] $user_ids Array of user IDs.
	 * @param int   $group_id Group ID.
	 * @return int[] Filtered array of user IDs (excluding hidden members).
	 */
	public function filter_member_ids( array $user_ids, int $group_id ): array {
		return array_filter(
			$user_ids,
			function ( $user_id ) use ( $group_id ) {
				return ! $this->is_member_hidden( $group_id, (int) $user_id );
			}
		);
	}

	/**
	 * Check if a member is hidden in a group.
	 *
	 * @param int $group_id Group ID.
	 * @param int $user_id User ID.
	 * @return bool True if the member is hidden, false otherwise.
	 */
	public function is_member_hidden( int $group_id, int $user_id ): bool {
		return (bool) groups_get_groupmeta( $group_id, 'hidden_member_' . $user_id, true );
	}
}
