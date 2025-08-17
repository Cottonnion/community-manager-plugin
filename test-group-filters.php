<?php
/**
 * Test the GroupFiltersHandler implementation
 *
 * This file is for testing purposes only and can be deleted after verification.
 */

// Add a test function to verify the filtering logic
add_action('wp_footer', 'test_group_filters_handler');

function test_group_filters_handler() {
    // Only run on BuddyBoss groups pages
    if (!function_exists('bp_is_groups_component') || !bp_is_groups_component()) {
        return;
    }
    
    // Get the current user's ID
    $user_id = get_current_user_id();
    if (!$user_id) {
        return;
    }
    
    // Get groups the user is a member of
    $user_groups = groups_get_user_groups($user_id);
    $user_group_ids = !empty($user_groups['groups']) ? $user_groups['groups'] : [];
    
    // Output test information
    echo '<div style="background: #f8f8f8; border: 1px solid #ddd; padding: 15px; margin: 20px; border-radius: 5px;">';
    echo '<h4>Group Filtering Test Information</h4>';
    
    echo '<p><strong>User ID:</strong> ' . $user_id . '</p>';
    
    echo '<p><strong>User\'s Groups:</strong> ';
    if (!empty($user_group_ids)) {
        echo implode(', ', $user_group_ids);
    } else {
        echo 'None';
    }
    echo '</p>';
    
    // Get parent groups
    $parent_group_ids = [];
    foreach ($user_group_ids as $group_id) {
        $parent_id = null;
        
        // Check if BuddyBoss hierarchical groups functionality is available
        if (function_exists('bp_get_parent_group_id')) {
            $parent_id = bp_get_parent_group_id($group_id);
        } else {
            // Fallback - get parent ID from group meta
            $parent_id = (int) groups_get_groupmeta($group_id, 'parent_id', true);
        }
        
        if ($parent_id && !in_array($parent_id, $parent_group_ids)) {
            $parent_group_ids[] = $parent_id;
        }
    }
    
    echo '<p><strong>Parent Groups:</strong> ';
    if (!empty($parent_group_ids)) {
        echo implode(', ', $parent_group_ids);
    } else {
        echo 'None';
    }
    echo '</p>';
    
    // All accessible groups
    $accessible_group_ids = array_unique(array_merge($user_group_ids, $parent_group_ids));
    
    echo '<p><strong>All Accessible Groups:</strong> ';
    if (!empty($accessible_group_ids)) {
        echo implode(', ', $accessible_group_ids);
    } else {
        echo 'None';
    }
    echo '</p>';
    
    echo '</div>';
}
