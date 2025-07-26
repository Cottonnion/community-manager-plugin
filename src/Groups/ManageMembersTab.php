<?php
namespace LABGENZ_CM\Groups;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simple class to add a "Manage Members" tab to BuddyBoss groups with organization type
 */
class ManageMembersTab {
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return ManageMembersTab
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - setup hooks
	 */
	private function __construct() {
		add_action( 'bp_groups_setup_nav', [ $this, 'setup_manage_members_tab' ], 20 );
		add_action( 'bp_groups_setup_nav', [ $this, 'setup_map_members_tab' ], 20 );
		add_action( 'bp_actions', [ $this, 'remove_group_tabs' ], 5 );
	}

	/**
	 * Setup Manage Members tab
	 */
	public function setup_manage_members_tab() {
		// Only proceed if BuddyPress and BuddyBoss are active
		if ( ! function_exists( 'bp_is_active' ) || ! bp_is_active( 'groups' ) ) {
			return;
		}

		// Get the current group
		$group = groups_get_current_group();

		// Return early if not on a group page or not a valid group
		if ( empty( $group ) || ! bp_is_group() ) {
			return;
		}

		// Check if this group has the organization group type
		if ( ! $this->has_organization_group_type( $group->id ) ) {
			return;
		}

		if ( $this->is_user_group_organizer( $group->id, get_current_user_id() ) ) {
					// Add Manage Members tab
			bp_core_new_subnav_item(
				[
					'name'            => __( 'Manage Members', 'buddyboss' ),
					'slug'            => 'manage-members',
					'parent_url'      => bp_get_group_permalink( $group ),
					'parent_slug'     => $group->slug,
					'screen_function' => [ $this, 'display_manage_members_tab' ],
					'position'        => 15,
					'user_has_access' => bp_is_item_admin(),
					'item_css_id'     => 'manage-members',
				]
			);
		}
	}

	/**
	 * Setup Map Members tab
	 */
	public function setup_map_members_tab() {
		// Only proceed if BuddyPress and BuddyBoss are active
		if ( ! function_exists( 'bp_is_active' ) || ! bp_is_active( 'groups' ) ) {
			return;
		}

		// Get the current group
		$group = groups_get_current_group();

		// Return early if not on a group page or not a valid group
		if ( empty( $group ) || ! bp_is_group() ) {
			return;
		}

		// Check if this group has the organization group type
		if ( ! $this->has_organization_group_type( $group->id ) ) {
			return;
		}

		// Add Map Members tab
		bp_core_new_subnav_item(
			[
				'name'            => __( 'Members Map', 'buddyboss' ),
				'slug'            => 'members-map',
				'parent_url'      => bp_get_group_permalink( $group ),
				'parent_slug'     => $group->slug,
				'screen_function' => [ $this, 'display_map_members_tab' ],
				'position'        => 16,
				'user_has_access' => true, // Everyone in the group can see the map
				'item_css_id'     => 'members-map',
			]
		);
	}

	/**
	 * Display the Manage Members tab content
	 */
	public function display_manage_members_tab() {
		// Add title and content to the template
		add_action( 'bp_template_content', [ $this, 'manage_members_tab_content' ] );

		// Load the appropriate template
		bp_core_load_template( apply_filters( 'bp_core_template_plugin', 'groups/single/plugins' ) );
	}

	/**
	 * Manage Members tab content - simple one line template
	 */
	public function manage_members_tab_content() {
		// Include the template file
		$template_file = LABGENZ_CM_TEMPLATES_DIR . '/buddypress/groups/manage-members.php';

		if ( file_exists( $template_file ) ) {
			include $template_file;
		} else {
			echo '<div class="manage-members-container">Manage your organization members here. (Template file not found)</div>';
		}
	}

	/**
	 * Display the Map Members tab content
	 */
	public function display_map_members_tab() {
		// Add title and content to the template
		add_action( 'bp_template_content', [ $this, 'map_members_tab_content' ] );

		// Load the appropriate template
		bp_core_load_template( apply_filters( 'bp_core_template_plugin', 'groups/single/plugins' ) );
	}

	/**
	 * Map Members tab content
	 */
	public function map_members_tab_content() {
		// Include the template file
		$template_file = LABGENZ_CM_TEMPLATES_DIR . '/buddypress/groups/members-map.php';

		if ( file_exists( $template_file ) ) {
			include $template_file;
		} else {
			echo '<div class="members-map-container">
                <div class="members-map-header">
                    <h3>' . __( 'Members Map', 'buddyboss' ) . '</h3>
                    <p>' . __( 'View group members on a map', 'buddyboss' ) . '</p>
                </div>
                <div id="members-map" style="width: 100%; height: 500px;"></div>
                <div class="members-map-footer">
                    <p>' . __( 'Note: Only members who have added their location will appear on the map.', 'buddyboss' ) . '</p>
                </div>
            </div>';
		}
	}

	/**
	 * Check if a group has the organization group type
	 *
	 * @param int $group_id Group ID
	 * @return bool True if has the organization group type
	 */
	private function has_organization_group_type( $group_id ) {
		// Allow overriding via URL for testing purposes - only if user is admin
		if ( current_user_can( 'administrator' ) && isset( $_GET['force_organization_tab'] ) && $_GET['force_organization_tab'] === '1' ) {
			return true;
		}

		// If BP Group Types component is active
		if ( function_exists( 'bp_groups_get_group_type' ) ) {
			$group_type = bp_groups_get_group_type( $group_id );
			if ( $group_type === 'organization' ) {
				return true;
			}
		}

		// Check for group meta - BuddyBoss Platform may store group type in group meta
		$group_type = groups_get_groupmeta( $group_id, 'group_type', true );
		if ( $group_type === 'organization' ) {
			return true;
		}

		// Additional check for custom group_type meta field
		$mlmmc_group_type = groups_get_groupmeta( $group_id, 'mlmmc_group_type', true );
		if ( $mlmmc_group_type === 'organization' ) {
			return true;
		}

		// Check if the group was created by the organization creation handler
		// This is based on the metadata we found in GroupCreationHandler
		$subscription_status = groups_get_groupmeta( $group_id, 'mlmmc_subscription_status', true );
		if ( ! empty( $subscription_status ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Remove unwanted tabs from group navigation
	 */
	public function remove_group_tabs() {
		// Only proceed if BuddyPress and BuddyBoss are active
		if ( ! function_exists( 'bp_is_active' ) || ! bp_is_active( 'groups' ) ) {
			return;
		}

		// Get the current group
		$group = groups_get_current_group();

		// Return early if not on a group page or not a valid group
		if ( empty( $group ) || ! bp_is_group() ) {
			return;
		}

		// Check if this group has the organization group type
		if ( ! $this->has_organization_group_type( $group->id ) ) {
			return;
		}

		bp_core_remove_subnav_item( $group->slug, 'subgroups' );
		bp_core_remove_subnav_item( $group->slug, 'invite' );
		// bp_core_remove_subnav_item( $group->slug, 'admin' );
	}

	/**
	 * Checks if a user is a group organizer (admin)
	 *
	 * @param int $group_id Group ID
	 * @param int $user_id User ID
	 * @return bool True if user is a group organizer
	 */
	private function is_user_group_organizer( $group_id, $user_id ) {
		if ( ! $user_id || ! $group_id ) {
			return false;
		}

		// Check if user is an admin of the group
		return groups_is_user_admin( $user_id, $group_id );
	}
}
