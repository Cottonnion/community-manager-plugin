<?php
/**
 * Testing Helper Functions for Organization Access
 * 
 * This file contains helper functions for testing the organization access feature.
 */

if (!defined('ABSPATH')) {
    die('Direct access not allowed');
}

/**
 * Clear a user's organization access request data
 * 
 * @param int $user_id User ID to clear data for
 * @return bool Success status
 */
function labgenz_clear_user_org_request($user_id) {
    if (!$user_id) {
        return false;
    }
    
    delete_user_meta($user_id, '_labgenz_org_access_request_data');
    delete_user_meta($user_id, '_labgenz_org_access_status');
    delete_user_meta($user_id, '_labgenz_org_access_token');
    delete_user_meta($user_id, '_labgenz_org_access_token_expires');
    
    return true;
}

/**
 * Clear current user's organization access request data
 * 
 * @return bool Success status
 */
function labgenz_clear_current_user_org_request() {
    if (!is_user_logged_in()) {
        return false;
    }
    
    return labgenz_clear_user_org_request(get_current_user_id());
}

/**
 * Clear all organization access request data for all users
 * 
 * @return int Number of users cleared
 */
function labgenz_clear_all_org_requests() {
    global $wpdb;
    
    $deleted_count = 0;
    
    // Delete all organization access meta
    $meta_keys = array(
        '_labgenz_org_access_request_data',
        '_labgenz_org_access_status',
        '_labgenz_org_access_token',
        '_labgenz_org_access_token_expires'
    );
    
    foreach ($meta_keys as $meta_key) {
        $result = $wpdb->delete(
            $wpdb->usermeta,
            array('meta_key' => $meta_key),
            array('%s')
        );
        
        if ($result !== false) {
            $deleted_count += $result;
        }
    }
    
    return $deleted_count;
}

/**
 * Get all users with organization access requests
 * 
 * @return array Array of user IDs with requests
 */
function labgenz_get_users_with_org_requests() {
    global $wpdb;
    
    $user_ids = $wpdb->get_col(
        "SELECT DISTINCT user_id FROM {$wpdb->usermeta} 
         WHERE meta_key = '_labgenz_org_access_request_data'"
    );
    
    return array_map('intval', $user_ids);
}

// Add admin notice for testing mode
add_action('admin_notices', function() {
    if (current_user_can('manage_options')) {
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>Organization Access Testing Mode:</strong> Multiple requests are allowed. ';
        echo 'To clear test data, add <code>?clear_org_requests=1</code> to any admin URL.</p>';
        echo '</div>';
    }
});

// Handle clearing requests via URL parameter
add_action('admin_init', function() {
    if (isset($_GET['clear_org_requests']) && $_GET['clear_org_requests'] == '1' && current_user_can('manage_options')) {
        $cleared = labgenz_clear_all_org_requests();
        
        add_action('admin_notices', function() use ($cleared) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>Cleared ' . $cleared . ' organization access request records.</p>';
            echo '</div>';
        });
    }
});

// Console logging for debugging
function labgenz_log_org_access($message, $data = null) {
    if (WP_DEBUG) {
        error_log('ORG ACCESS DEBUG: ' . $message . ($data ? ' - ' . print_r($data, true) : ''));
    }
}
?>
