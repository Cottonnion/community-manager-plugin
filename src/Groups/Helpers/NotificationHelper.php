<?php

declare(strict_types=1);

namespace LABGENZ_CM\Groups\Helpers;

/**
 * Extendable notification helper for BuddyPress/BuddyBoss notifications
 * 
 * @package LabgenzCommunityManagement
 */
class NotificationHelper
{
    /**
     * Default component name for notifications
     */
    public const DEFAULT_COMPONENT = 'organization';
    
    /**
     * Notification types registry
     * 
     * @var array<string, array>
     */
    private static array $notification_types = [
        'org_request_approved' => [
            'title' => 'Organization Request Approved',
            'description' => 'Organization request has been approved',
            'component' => self::DEFAULT_COMPONENT,
            'template' => 'Your request to join <strong>%s</strong> has been approved!'
        ],
        'org_request_rejected' => [
            'title' => 'Organization Request Rejected',
            'description' => 'Organization request has been rejected',
            'component' => self::DEFAULT_COMPONENT,
            'template' => 'Your request to join <strong>%s</strong> has been rejected.'
        ],
        'org_invite_received' => [
            'title' => 'Organization Invitation Received',
            'description' => 'You have been invited to join an organization',
            'component' => self::DEFAULT_COMPONENT,
            'template' => 'You have been invited to join <strong>%s</strong>!'
        ],
        'org_member_added' => [
            'title' => 'Organization Member Added',
            'description' => 'A new member has joined the organization',
            'component' => self::DEFAULT_COMPONENT,
            'template' => '<strong>%s</strong> has joined your organization!'
        ],
    ];
    
    /**
     * Register a new notification type
     * 
     * @param string $action_key Unique action identifier
     * @param array $config Notification configuration
     * @return bool
     */
    public static function register_notification_type(string $action_key, array $config): bool
    {
        $default_config = [
            'title' => '',
            'description' => '',
            'component' => self::DEFAULT_COMPONENT,
            'template' => '',
            'link_callback' => null,
            'data_formatter' => null
        ];
        
        self::$notification_types[$action_key] = array_merge($default_config, $config);
        
        // Register with BuddyPress if available
        if (function_exists('bp_notifications_register_notification_type')) {
            bp_notifications_register_notification_type(
                $action_key,
                __($config['title'], 'textdomain'),
                __($config['description'], 'textdomain'),
                $config['component'] ?? self::DEFAULT_COMPONENT
            );
        }
        
        return true;
    }
    
    /**
     * Get registered notification type
     * 
     * @param string $action_key
     * @return array|null
     */
    public static function get_notification_type(string $action_key): ?array
    {
        return self::$notification_types[$action_key] ?? null;
    }
    
    /**
     * Get all registered notification types
     * 
     * @return array<string, array>
     */
    public static function get_all_notification_types(): array
    {
        return self::$notification_types;
    }
    
    /**
     * Send a notification
     * 
     * @param string $action_key Notification type
     * @param int $user_id Recipient user ID
     * @param int $item_id Primary item ID
     * @param int|null $secondary_item_id Secondary item ID (optional)
     * @param array $extra_data Additional data for formatting
     * @return int|false Notification ID on success, false on failure
     */
    public static function send_notification(
        string $action_key, 
        int $user_id, 
        int $item_id, 
        ?int $secondary_item_id = null, 
        array $extra_data = []
    ) {
        // Enhanced debugging
        self::log_debug('=== SENDING NOTIFICATION ===', [
            'action_key' => $action_key,
            'user_id' => $user_id,
            'item_id' => $item_id,
            'secondary_item_id' => $secondary_item_id,
            'extra_data' => $extra_data
        ]);
        
        // Validate notification type
        $notification_type = self::get_notification_type($action_key);
        if (!$notification_type) {
            self::log_debug("Unknown notification type: {$action_key}");
            return false;
        }
        
        // Validate BuddyPress availability
        if (!function_exists('bp_notifications_add_notification')) {
            self::log_debug('BuddyPress notifications not available');
            return false;
        }
        
        // Validate user exists
        if (!get_user_by('id', $user_id)) {
            self::log_debug("User ID {$user_id} does not exist");
            return false;
        }
        
        // Prepare notification data
        $notification_data = [
            'user_id' => $user_id,
            'item_id' => $item_id,
            'secondary_item_id' => $secondary_item_id ?? $item_id,
            'component_name' => $notification_type['component'],
            'component_action' => $action_key,
            'date_notified' => self::get_current_time(),
            'is_new' => 1,
        ];
        
        self::log_debug('Notification data prepared', $notification_data);
        
        // Try BuddyPress API first
        $result = bp_notifications_add_notification($notification_data);
        
        self::log_debug('BP API result', [
            'result' => $result,
            'type' => gettype($result),
            'is_wp_error' => is_wp_error($result)
        ]);
        
        if (is_wp_error($result)) {
            self::log_debug('WP Error: ' . $result->get_error_message());
            return false;
        }
        
        // Fallback to direct database insertion
        if (!$result) {
            self::log_debug('Trying direct database insertion...');
            $result = self::insert_notification_direct($notification_data);
        }
        
        self::log_debug("Final result: " . ($result ? 'SUCCESS' : 'FAILED'));
        return $result;
    }
    
    /**
     * Send multiple notifications at once
     * 
     * @param array $notifications Array of notification data
     * @return array Results array with success/failure status
     */
    public static function send_bulk_notifications(array $notifications): array
    {
        $results = [];
        
        foreach ($notifications as $index => $notification) {
            $result = self::send_notification(
                $notification['action_key'],
                $notification['user_id'],
                $notification['item_id'],
                $notification['secondary_item_id'] ?? null,
                $notification['extra_data'] ?? []
            );
            
            $results[$index] = [
                'success' => (bool) $result,
                'notification_id' => $result,
                'data' => $notification
            ];
        }
        
        return $results;
    }
    
    /**
     * Format notification content
     * 
     * @param string $action
     * @param int $item_id
     * @param int $secondary_item_id
     * @param int $total_items
     * @param string $format
     * @param string $component_action
     * @param string $component_name
     * @return string|array
     */
    public static function format_notification(
        string $action,
        int $item_id,
        int $secondary_item_id,
        int $total_items,
        string $format = 'string',
        string $component_action = '',
        string $component_name = ''
    ) {
        // Check if this is our component
        if (!in_array($component_name, self::get_registered_components())) {
            return $action;
        }
        
        $notification_type = self::get_notification_type($component_action);
        if (!$notification_type) {
            return $action;
        }
        
        // Use custom data formatter if provided
        if (isset($notification_type['data_formatter']) && is_callable($notification_type['data_formatter'])) {
            return call_user_func(
                $notification_type['data_formatter'],
                $item_id,
                $secondary_item_id,
                $total_items,
                $format
            );
        }
        
        // Default formatting
        $organization_name = self::get_organization_name($secondary_item_id);
        $text = sprintf($notification_type['template'], $organization_name);
        
        if ($format === 'array') {
            $link = self::get_notification_link($component_action, $item_id, $secondary_item_id);
            return [
                'text' => $text,
                'link' => $link,
            ];
        }
        
        return $text;
    }
    
    /**
     * Get notification link
     * 
     * @param string $action_key
     * @param int $item_id
     * @param int $secondary_item_id
     * @return string
     */
    public static function get_notification_link(string $action_key, int $item_id, int $secondary_item_id): string
    {
        $notification_type = self::get_notification_type($action_key);
        
        // Use custom link callback if provided
        if (isset($notification_type['link_callback']) && is_callable($notification_type['link_callback'])) {
            return call_user_func($notification_type['link_callback'], $item_id, $secondary_item_id);
        }
        
        // Default link to notifications page
        if (function_exists('bp_get_notifications_slug')) {
            return home_url(trailingslashit(bp_get_notifications_slug()));
        }
        
        return home_url();
    }
    
    /**
     * Clear notifications for user
     * 
     * @param int $user_id
     * @param string|null $action_key Optional: specific action to clear
     * @param string|null $component Optional: specific component
     * @return int Number of notifications cleared
     */
    public static function clear_notifications(int $user_id, ?string $action_key = null, ?string $component = null): int
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'bp_notifications';
        $where_conditions = ['user_id = %d'];
        $where_values = [$user_id];
        
        if ($action_key) {
            $where_conditions[] = 'component_action = %s';
            $where_values[] = $action_key;
        }
        
        if ($component) {
            $where_conditions[] = 'component_name = %s';
            $where_values[] = $component;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        $query = "DELETE FROM {$table_name} WHERE {$where_clause}";
        
        return $wpdb->query($wpdb->prepare($query, ...$where_values)) ?: 0;
    }
    
    /**
     * Get user notifications
     * 
     * @param int $user_id
     * @param array $args Optional query arguments
     * @return array
     */
    public static function get_user_notifications(int $user_id, array $args = []): array
    {
        if (!class_exists('BP_Notifications_Notification')) {
            return [];
        }
        
        $default_args = [
            'user_id' => $user_id,
            'per_page' => 20,
            'page' => 1,
            'is_new' => null
        ];
        
        $query_args = array_merge($default_args, $args);
        
        return BP_Notifications_Notification::get($query_args) ?: [];
    }
    
    /**
     * Register notification types with BuddyPress
     */
    public static function register_all_types(): void
    {
        foreach (self::$notification_types as $action_key => $config) {
            self::register_notification_type($action_key, $config);
        }
    }
    
    /**
     * Get registered components
     * 
     * @return array
     */
    public static function get_registered_components(): array
    {
        return array_unique(array_column(self::$notification_types, 'component'));
    }
    
    /**
     * Direct database insertion fallback
     * 
     * @param array $notification_data
     * @return int|false
     */
    private static function insert_notification_direct(array $notification_data)
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'bp_notifications';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            self::log_debug("Table {$table_name} does not exist");
            return false;
        }
        
        $result = $wpdb->insert(
            $table_name,
            $notification_data,
            ['%d', '%d', '%d', '%s', '%s', '%s', '%d']
        );
        
        if ($result === false) {
            self::log_debug('Database insert failed: ' . $wpdb->last_error);
            return false;
        }
        
        self::log_debug('Direct database insertion successful. Insert ID: ' . $wpdb->insert_id);
        return $wpdb->insert_id;
    }
    
    /**
     * Get current time for notifications
     * 
     * @return string
     */
    private static function get_current_time(): string
    {
        return function_exists('bp_core_current_time') ? bp_core_current_time() : current_time('mysql');
    }
    
    /**
     * Get organization name (override this method for custom logic)
     * 
     * @param int $organization_id
     * @return string
     */
    protected static function get_organization_name(int $organization_id): string
    {
        // Default implementation - override in subclass or via hook
        return apply_filters(
            'labgenz_notification_organization_name', 
            "Test Organization #{$organization_id}", 
            $organization_id
        );
    }
    
    /**
     * Enhanced logging with context
     * 
     * @param string $message
     * @param mixed $context
     */
    private static function log_debug(string $message, $context = null): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_message = "[NotificationHelper] {$message}";
            if ($context !== null) {
                $log_message .= " | Context: " . print_r($context, true);
            }
            error_log($log_message);
        }
    }
    
    /**
     * Quick helper methods for common notification types
     */
    public static function send_approval_notification(int $user_id, int $organization_id): bool
    {
        return (bool) self::send_notification('org_request_approved', $user_id, $user_id, $organization_id);
    }
    
    public static function send_rejection_notification(int $user_id, int $organization_id): bool
    {
        return (bool) self::send_notification('org_request_rejected', $user_id, $user_id, $organization_id);
    }
    
    public static function send_invitation_notification(int $user_id, int $organization_id): bool
    {
        return (bool) self::send_notification('org_invite_received', $user_id, $user_id, $organization_id);
    }
    
    public static function send_member_added_notification(int $admin_user_id, int $organization_id, array $member_data = []): bool
    {
        return (bool) self::send_notification('org_member_added', $admin_user_id, $admin_user_id, $organization_id, $member_data);
    }
}