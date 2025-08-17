<?php

namespace LABGENZ_CM\Subscriptions\Helpers;

use LABGENZ_CM\Subscriptions\SubscriptionHandler;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Helper class for subscription hierarchy and type checking
 */
class SubscriptionTypeHelper {
    
    /**
     * Subscription hierarchy from lowest to highest
     * 
     * @var array
     */
    private static $subscription_hierarchy = [
        'basic' => 10,
        'monthly-basic-subscription' => 10,
        'apprentice-monthly' => 20,
        'apprentice-yearly' => 21,
        'team-leader-monthly' => 30,
        'team-leader-yearly' => 31,
        'freedom-builder-monthly' => 40,
        'freedom-builder-yearly' => 41
    ];
    
    /**
     * Check if a user has only a basic subscription
     *
     * @param int $user_id The user ID to check
     * @return bool True if the user has only a basic subscription, false otherwise
     */
    public static function user_has_only_basic_subscription(int $user_id): bool {
        $subscription_types = SubscriptionHandler::get_user_subscription_types($user_id);
        
        if (empty($subscription_types)) {
            return false;
        }
        
        foreach ($subscription_types as $type) {
            if (strpos($type, 'basic') === false) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Check if a user has multiple subscription types
     *
     * @param int $user_id The user ID to check
     * @return bool True if the user has multiple subscription types, false otherwise
     */
    public static function user_has_multiple_subscriptions(int $user_id): bool {
        $subscription_types = SubscriptionHandler::get_user_subscription_types($user_id);
        return count($subscription_types) > 1;
    }
    
    /**
     * Get the highest hierarchy subscription for a user
     *
     * @param int $user_id The user ID to check
     * @return string|null The highest subscription type or null if none found
     */
    public static function get_highest_subscription_type(int $user_id): ?string {
        $subscription_types = SubscriptionHandler::get_user_subscription_types($user_id);
        
        if (empty($subscription_types)) {
            return null;
        }
        
        $highest_value = 0;
        $highest_type = null;
        
        foreach ($subscription_types as $type) {
            $value = self::$subscription_hierarchy[$type] ?? 0;
            
            if ($value > $highest_value) {
                $highest_value = $value;
                $highest_type = $type;
            }
        }
        
        return $highest_type;
    }
    
    /**
     * Get a friendly name for a subscription type
     *
     * @param string $subscription_type The subscription type
     * @return string The friendly name
     */
    public static function get_friendly_name(string $subscription_type): string {
        // Remove monthly/yearly prefix
        $base_name = str_replace(['monthly-', 'yearly-'], '', $subscription_type);
        
        // Replace dashes with spaces and capitalize words
        return ucwords(str_replace('-', ' ', $base_name));
    }
    
    /**
     * Get the subscription level (basic, apprentice, team leader, freedom builder)
     *
     * @param string $subscription_type The subscription type
     * @return string The subscription level
     */
    public static function get_subscription_level(string $subscription_type): string {
        if (strpos($subscription_type, 'basic') !== false) {
            return 'basic';
        } elseif (strpos($subscription_type, 'apprentice') !== false) {
            return 'apprentice';
        } elseif (strpos($subscription_type, 'team-leader') !== false) {
            return 'team-leader';
        } elseif (strpos($subscription_type, 'freedom-builder') !== false) {
            return 'freedom-builder';
        }
        
        return 'unknown';
    }
}
