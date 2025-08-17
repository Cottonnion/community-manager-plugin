<?php

namespace LABGENZ_CM\Groups\Helpers;

/**
 * Helper class for group operations.
 *
 * Provides utility methods for working with BuddyPress/BuddyBoss groups.
 *
 * @since 1.0.0
 */
class GroupHelpers {
    /**
     * Get all groups that a user has access to.
     * This includes groups they are members of and parent groups of their groups.
     *
     * @param int $user_id The user ID.
     * @return array Array of group IDs the user can access.
     */
    public static function get_user_accessible_group_ids(int $user_id): array {
        if (!$user_id) {
            return [];
        }
        
        // Get groups the user is a member of
        $user_groups = groups_get_user_groups($user_id);
        $user_group_ids = !empty($user_groups['groups']) ? $user_groups['groups'] : [];
        
        // Get parent groups of user's groups
        $parent_group_ids = [];
        
        foreach ($user_group_ids as $group_id) {
            $parent_id = self::get_parent_group_id($group_id);
            if ($parent_id && !in_array($parent_id, $parent_group_ids)) {
                $parent_group_ids[] = $parent_id;
            }
        }
        
        // Combine user's groups and parent groups
        $accessible_group_ids = array_unique(array_merge($user_group_ids, $parent_group_ids));
        
        /**
         * Filter the group IDs a user can access.
         *
         * @param array $accessible_group_ids Array of group IDs.
         * @param int $user_id User ID.
         */
        return apply_filters('labgenz_cm_user_accessible_group_ids', $accessible_group_ids, $user_id);
    }
    
    /**
     * Get the parent group ID for a given group.
     *
     * @param int $group_id The group ID.
     * @return int|null Parent group ID or null if no parent.
     */
    public static function get_parent_group_id(int $group_id) {
        // Check if BuddyBoss hierarchical groups functionality is available
        if (function_exists('bp_get_parent_group_id')) {
            return bp_get_parent_group_id($group_id);
        }
        
        // Fallback - get parent ID from group meta
        return (int) groups_get_groupmeta($group_id, 'parent_id', true);
    }
    
    /**
     * Get all child groups for a given group.
     *
     * @param int $group_id The parent group ID.
     * @return array Array of child group IDs.
     */
    public static function get_child_group_ids(int $group_id): array {
        global $wpdb, $bp;
        
        // Check if BuddyBoss hierarchical groups functionality is available
        if (function_exists('bp_get_child_group_ids')) {
            return bp_get_child_group_ids($group_id);
        }
        
        // Fallback - query group meta table directly
        $query = $wpdb->prepare(
            "SELECT group_id FROM {$bp->groups->table_name_groupmeta} 
            WHERE meta_key = 'parent_id' AND meta_value = %d",
            $group_id
        );
        
        $child_group_ids = $wpdb->get_col($query);
        
        return array_map('intval', $child_group_ids);
    }
    
    /**
     * Check if a user is a member of a specific group, any of its parent groups,
     * or if the group is a subgroup of any group the user is a member of.
     *
     * @param int $user_id The user ID.
     * @param int $group_id The group ID.
     * @return bool Whether the user has access to the group.
     */
    public static function can_user_access_group(int $user_id, int $group_id): bool {
        if (!$user_id || !$group_id) {
            return false;
        }
        
        // If user is an administrator, they have access to all groups
        if (user_can($user_id, 'administrator')) {
            return true;
        }
        
        // Check if user is a member of this group
        if (groups_is_user_member($user_id, $group_id)) {
            return true;
        }
        
        // Check if user is a member of any parent group
        $parent_id = self::get_parent_group_id($group_id);
        if ($parent_id && groups_is_user_member($user_id, $parent_id)) {
            return true;
        }
        
        // Check if this group is a child of any of the user's groups
        $user_groups = groups_get_user_groups($user_id);
        $user_group_ids = !empty($user_groups['groups']) ? $user_groups['groups'] : [];
        
        foreach ($user_group_ids as $user_group_id) {
            $child_groups = self::get_child_group_ids($user_group_id);
            if (in_array($group_id, $child_groups)) {
                return true;
            }
        }
        
        return false;
    }
}
