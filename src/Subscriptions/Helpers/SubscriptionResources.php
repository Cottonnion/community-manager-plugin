<?php

declare(strict_types=1);

namespace LABGENZ_CM\Subscriptions\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles subscription resource access
 *
 * @package    Labgenz_Community_Management
 * @subpackage Labgenz_Community_Management/Subscriptions/Helpers
 */
class SubscriptionResources {

    /**
     * Subscription resources configuration
     *
     * @var array
     */
    private static $subscription_resources = [
        'basic'        => [
            'course_categories'     => [ 'basic-courses' ],
            'group_creation'        => false,
            'organization_access'   => false,
            'advanced_features'     => false,
            'support_level'         => 'basic',
            'max_groups'            => 0,
            'max_members_per_group' => 0,
            'can_view_mlm_articles' => false,
            'can_create_articles'   => false,
            'can_edit_articles'     => false,
            'can_filter_articles'   => false,
        ],
        'monthly-basic-subscription' => [
            'course_categories'     => [ 'basic-courses' ],
            'group_creation'        => false,
            'organization_access'   => false,
            'advanced_features'     => false,
            'support_level'         => 'basic',
            'max_groups'            => 0,
            'max_members_per_group' => 0,
            'can_view_mlm_articles' => false,
            'can_create_articles'   => false,
            'can_edit_articles'     => false,
            'can_filter_articles'   => false,
        ],
        'organization' => [
            'course_categories'     => [ 'basic-courses', 'organization-courses', 'advanced-courses' ],
            'group_creation'        => true,
            'organization_access'   => true,
            'advanced_features'     => true,
            'support_level'         => 'premium',
            'max_groups'            => 10,
            'max_members_per_group' => 100,
            'can_view_mlm_articles' => true,
            'can_create_articles'   => true,
            'can_edit_articles'     => true,
            'can_filter_articles'   => true,
        ],
        'monthly-organization-subscription' => [
            'course_categories'     => [ 'basic-courses', 'organization-courses', 'advanced-courses' ],
            'group_creation'        => true,
            'organization_access'   => true,
            'advanced_features'     => true,
            'support_level'         => 'premium',
            'max_groups'            => 10,
            'max_members_per_group' => 100,
            'can_view_mlm_articles' => true,
            'can_create_articles'   => true,
            'can_edit_articles'     => true,
            'can_filter_articles'   => true,
        ],
        // New subscription types
        'apprentice-yearly' => [
            'course_categories'     => [ 'basic-courses', 'advanced-courses' ],
            'group_creation'        => false,
            'organization_access'   => false,
            'advanced_features'     => false,
            'support_level'         => 'standard',
            'max_groups'            => 0,
            'max_members_per_group' => 0,
            'can_view_mlm_articles' => true,
            'can_create_articles'   => true,
            'can_edit_articles'     => true,
            'can_filter_articles'   => true,
            'can_view_pre_release_articles' => true,
        ],
        'apprentice-monthly' => [
            'course_categories'     => [ 'basic-courses', 'advanced-courses' ],
            'group_creation'        => false,
            'organization_access'   => false,
            'advanced_features'     => false,
            'support_level'         => 'standard',
            'max_groups'            => 0,
            'max_members_per_group' => 0,
            'can_view_mlm_articles' => true,
            'can_create_articles'   => true,
            'can_edit_articles'     => true,
            'can_filter_articles'   => true,
            'can_view_pre_release_articles' => false,
        ],
        'team-leader-yearly' => [
            'course_categories'     => [ 'basic-courses', 'advanced-courses', 'team-leader-courses' ],
            'group_creation'        => true,
            'organization_access'   => false,
            'advanced_features'     => true,
            'support_level'         => 'premium',
            'max_groups'            => 5,
            'max_members_per_group' => 50,
            'can_view_mlm_articles' => true,
            'can_create_articles'   => true,
            'can_edit_articles'     => true,
            'can_filter_articles'   => true,
            'can_view_pre_release_articles' => true,
        ],
        'team-leader-monthly' => [
            'course_categories'     => [ 'basic-courses', 'advanced-courses', 'team-leader-courses' ],
            'group_creation'        => true,
            'organization_access'   => false,
            'advanced_features'     => true,
            'support_level'         => 'premium',
            'max_groups'            => 5,
            'max_members_per_group' => 50,
            'can_view_mlm_articles' => true,
            'can_create_articles'   => true,
            'can_edit_articles'     => true,
            'can_filter_articles'   => true,
            'can_view_pre_release_articles' => false,
        ],
        'freedom-builder-yearly' => [
            'course_categories'     => [ 'basic-courses', 'advanced-courses', 'team-leader-courses', 'freedom-builder-courses' ],
            'group_creation'        => true,
            'organization_access'   => true,
            'advanced_features'     => true,
            'support_level'         => 'vip',
            'max_groups'            => 10,
            'max_members_per_group' => 100,
            'can_view_mlm_articles' => true,
            'can_create_articles'   => true,
            'can_edit_articles'     => true,
            'can_filter_articles'   => true,
            'can_view_pre_release_articles' => true,
        ],
        'freedom-builder-monthly' => [
            'course_categories'     => [ 'basic-courses', 'advanced-courses', 'team-leader-courses', 'freedom-builder-courses' ],
            'group_creation'        => true,
            'organization_access'   => true,
            'advanced_features'     => true,
            'support_level'         => 'vip',
            'max_groups'            => 10,
            'max_members_per_group' => 100,
            'can_view_mlm_articles' => true,
            'can_create_articles'   => true,
            'can_edit_articles'     => true,
            'can_filter_articles'   => true,
            'can_view_pre_release_articles' => false,
        ],
    ];

    /**
     * Articles upsell page ID
     */
    private static $articles_upsell_page_id = 44946;

    /**
     * Get allowed resources for subscription type
     *
     * @param string $subscription_type Subscription type
     * @return array
     */
    public static function get_allowed_resources(string $subscription_type): array {
        return self::$subscription_resources[$subscription_type] ?? [];
    }

    /**
     * Get user subscription resources by combining all active subscriptions
     *
     * @param int $user_id User ID
     * @return array Combined resources from all active subscriptions
     */
    public static function get_user_subscription_resources(int $user_id): array {
        if (current_user_can('manage_options')) {
            // Create a combined set of all possible resources for admins
            $all_resources = [];
            foreach (self::$subscription_resources as $type => $resources) {
                foreach ($resources as $key => $value) {
                    if (is_bool($value)) {
                        $all_resources[$key] = true;
                    } elseif (is_numeric($value)) {
                        $all_resources[$key] = max($all_resources[$key] ?? 0, $value);
                    } elseif (is_array($value)) {
                        $all_resources[$key] = array_unique(array_merge($all_resources[$key] ?? [], $value));
                    } else {
                        $all_resources[$key] = $all_resources[$key] ?? $value;
                    }
                }
            }
            return $all_resources;
        }
        
        $active_subscriptions = SubscriptionStorage::get_active_user_subscriptions($user_id);
        
        if (empty($active_subscriptions)) {
            return [];
        }
        
        $all_resources = [];
        
        foreach ($active_subscriptions as $subscription) {
            if (empty($subscription['type'])) {
                continue;
            }
            
            $resources = self::get_allowed_resources($subscription['type']);
            
            // Merge resources from this subscription with previously collected resources
            if (!empty($resources)) {
                foreach ($resources as $key => $value) {
                    // For boolean values, use OR logic (if any subscription has it enabled, it's enabled)
                    if (is_bool($value)) {
                        $all_resources[$key] = ($all_resources[$key] ?? false) || $value;
                    }
                    // For numeric values (limits), use the maximum value
                    elseif (is_numeric($value)) {
                        $all_resources[$key] = max($all_resources[$key] ?? 0, $value);
                    }
                    // For arrays (like course categories), merge them
                    elseif (is_array($value)) {
                        $all_resources[$key] = array_unique(array_merge($all_resources[$key] ?? [], $value));
                    }
                    // For other values, prioritize non-empty values
                    else {
                        $all_resources[$key] = $all_resources[$key] ?? $value;
                    }
                }
            }
        }
        
        return $all_resources;
    }

    /**
     * Check if user has organization subscription
     *
     * @param int $user_id User ID
     * @return bool
     */
    public static function user_has_organization_subscription(int $user_id): bool {
        $subscription_types = SubscriptionStorage::get_user_subscription_types($user_id);
        return in_array('organization', $subscription_types) || 
               in_array('monthly-organization-subscription', $subscription_types);
    }

    /**
     * Check if user can create groups
     *
     * @param int $user_id User ID
     * @return bool
     */
    public static function user_can_create_groups(int $user_id): bool {
        $resources = self::get_user_subscription_resources($user_id);
        return $resources['group_creation'] ?? false;
    }

    /**
     * Get user's allowed course categories
     *
     * @param int $user_id User ID
     * @return array
     */
    public static function get_user_allowed_course_categories(int $user_id): array {
        $resources = self::get_user_subscription_resources($user_id);
        return $resources['course_categories'] ?? [];
    }

    /**
     * Check if user has access to specific resource
     *
     * @param int    $user_id      User ID
     * @param string $resource_key Resource key to check (e.g., 'can_view_mlm_articles', 'group_creation')
     * @return bool
     */
    public static function user_has_resource_access(int $user_id, string $resource_key): bool {
        if (current_user_can('manage_options')) {
            return true; // Admins have access to all resources
        }
        $resources = self::get_user_subscription_resources($user_id);
        return $resources[$resource_key] ?? false;
    }

    /**
     * Get the article upsell URL.
     *
     * @return string The URL of the upsell page.
     */
    public static function get_article_upsell_url(): string {
        return get_permalink(self::$articles_upsell_page_id);
    }
}
