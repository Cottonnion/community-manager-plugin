<?php
declare(strict_types=1);

namespace LABGENZ_CM\Groups;

use LABGENZ_CM\Gamipress\Helpers\GamiPressDataProvider;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles group leaderboard functionality
 * Displays leaderboards of group members based on GamiPress activity_points
 */
class GroupsLeaderboardHandler {

    /**
     * Singleton instance
     *
     * @var self|null
     */
    private static $instance = null;

    /**
     * GamiPress Data Provider
     * 
     * @var GamiPressDataProvider
     */
    private $gamipress_data_provider;

    /**
     * The point type to use for leaderboards
     * 
     * @var string
     */
    private $points_type = 'reward_points';  // Changed to use Activity Reward Points

    /**
     * Get the singleton instance of this class
     *
     * @return self
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get the points type used for the leaderboard
     * 
     * @return string The points type
     */
    public function get_points_type() {
        return $this->points_type;
    }
    
    /**
     * Set the points type to use for leaderboard
     * 
     * @param string $points_type The points type to use
     */
    public function set_points_type($points_type) {
        $this->points_type = $points_type;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->gamipress_data_provider = new GamiPressDataProvider();
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // No hooks needed for now, but can be added as necessary
    }

    /**
     * Get leaderboard data for a specific group
     *
     * @param int $group_id The group ID
     * @param int $limit The number of members to include (default: 10)
     * @param int $page The page number for pagination (default: 1)
     * @return array Leaderboard data
     */
    public function get_group_leaderboard($group_id, $limit = 10, $page = 1) {
        // Get group members
        $members = $this->get_group_members($group_id, $limit, $page);
        
        if (empty($members)) {
            return [];
        }

        $leaderboard = [];
        
        // Build leaderboard data
        foreach ($members as $member) {
            $user_id = $member->ID;
            $points = $this->get_user_activity_points($user_id);
            
            $leaderboard[] = [
                'user_id' => $user_id,
                'display_name' => $member->display_name,
                'user_login' => $member->user_login,
                'points' => $points,
                'avatar' => get_avatar_url($user_id, ['size' => 50]),
                'profile_url' => bp_core_get_user_domain($user_id),
            ];
        }

        // Sort by points (highest first)
        usort($leaderboard, function($a, $b) {
            return $b['points'] <=> $a['points'];
        });

        return $leaderboard;
    }

    /**
     * Get all group members including regulars, admins, mods, and any other user types
     *
     * @param int $group_id The group ID
     * @param int $limit The number of members to include
     * @param int $page The page number for pagination
     * @return array Array of user objects
     */
    private function get_group_members($group_id, $limit = 10, $page = 1) {
        $members = [];
        
        if (!function_exists('groups_get_group_members')) {
            return $members;
        }
        
        // Collect all unique user IDs from different member types
        $all_user_ids = [];
        
        // Get regular members
        $group_members_query = groups_get_group_members([
            'group_id' => $group_id,
            'per_page' => 0, // Get all members
        ]);
        
        if (!empty($group_members_query['members'])) {
            foreach ($group_members_query['members'] as $member) {
                if (isset($member->ID)) {
                    $all_user_ids[] = $member->ID;
                }
            }
        }
        
        // Get admins
        $group_admins = groups_get_group_admins($group_id);
        if (!empty($group_admins)) {
            foreach ($group_admins as $admin) {
                if (isset($admin->user_id)) {
                    $all_user_ids[] = $admin->user_id;
                } elseif (isset($admin->ID)) {
                    $all_user_ids[] = $admin->ID;
                }
            }
        }
        
        // Get mods
        $group_mods = groups_get_group_mods($group_id);
        if (!empty($group_mods)) {
            foreach ($group_mods as $mod) {
                if (isset($mod->user_id)) {
                    $all_user_ids[] = $mod->user_id;
                } elseif (isset($mod->ID)) {
                    $all_user_ids[] = $mod->ID;
                }
            }
        }
        
        // Also check for any banned or suspended members if those functions exist
        if (function_exists('groups_get_banned_members')) {
            $banned_members = groups_get_banned_members($group_id);
            if (!empty($banned_members)) {
                foreach ($banned_members as $banned) {
                    if (isset($banned->user_id)) {
                        $all_user_ids[] = $banned->user_id;
                    } elseif (isset($banned->ID)) {
                        $all_user_ids[] = $banned->ID;
                    }
                }
            }
        }
        
        // Check for any pending members if available
        if (function_exists('groups_get_invites')) {
            $invites = groups_get_invites(['item_id' => $group_id]);
            if (!empty($invites)) {
                foreach ($invites as $invite) {
                    if (isset($invite->user_id)) {
                        $all_user_ids[] = $invite->user_id;
                    }
                }
            }
        }
        
        // Check for membership requests if available
        if (function_exists('groups_get_membership_requests')) {
            $requests = groups_get_membership_requests(['item_id' => $group_id]);
            if (!empty($requests)) {
                foreach ($requests as $request) {
                    if (isset($request->user_id)) {
                        $all_user_ids[] = $request->user_id;
                    }
                }
            }
        }
        
        // Remove duplicates and filter out invalid IDs
        $all_user_ids = array_unique(array_filter($all_user_ids, function($id) {
            return is_numeric($id) && $id > 0;
        }));
        
        if (empty($all_user_ids)) {
            return $members;
        }
        
        // Apply pagination to user IDs
        $total_members = count($all_user_ids);
        $offset = ($page - 1) * $limit;
        $paged_user_ids = array_slice($all_user_ids, $offset, $limit);
        
        // Get full user objects for the paged user IDs
        if (!empty($paged_user_ids)) {
            $members = get_users([
                'include' => $paged_user_ids,
                'orderby' => 'include', // Maintain the order from our array
            ]);
        }
        
        return $members;
    }

    /**
     * Get user activity points
     *
     * @param int $user_id The user ID
     * @return int The number of activity points
     */
    private function get_user_activity_points($user_id) {
        // Only get real points from GamiPressDataProvider
        $points = GamiPressDataProvider::get_user_points_balance($user_id, $this->points_type);
        
        // If no points found, check for other common point types
        if ($points === 0) {
            $common_point_types = ['activity_points', 'points', 'credits', 'coins', 'reward_points'];
            
            foreach ($common_point_types as $type) {
                if ($type !== $this->points_type) {
                    $alt_points = GamiPressDataProvider::get_user_points_balance($user_id, $type);
                    if ($alt_points > 0) {
                        return $alt_points;
                    }
                }
            }
            
            // Check if there are any user meta entries for GamiPress points
            global $wpdb;
            $gamipress_points = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT meta_key, meta_value FROM {$wpdb->usermeta} 
                    WHERE user_id = %d AND meta_key LIKE %s AND meta_value > 0",
                    $user_id,
                    '%gamipress%points%'
                )
            );
            
            if (!empty($gamipress_points)) {
                // Found some GamiPress points, use the first one with a value
                return (int) $gamipress_points[0]->meta_value;
            }
            
            // Return 0 if no real points found
            return 0;
        }
        
        return $points;
    }

    /**
     * Get the top performers across all groups
     *
     * @param int $limit The number of users to include
     * @return array Leaderboard data
     */
    public function get_global_leaderboard($limit = 10) {
        global $wpdb;
        
        // Check if GamiPress functions exist
        if (!function_exists('gamipress_get_points_type') || !function_exists('gamipress_get_user_points')) {
            return [];
        }
        
        // Make sure our points type exists
        $points_type = gamipress_get_points_type($this->points_type);
        if (empty($points_type)) {
            return [];
        }
        
        // Get the meta key for this points type
        $meta_key = "_gamipress_{$this->points_type}_points";
        
        // Get users with the highest points
        $user_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT user_id FROM {$wpdb->usermeta} 
                WHERE meta_key = %s 
                ORDER BY CAST(meta_value AS SIGNED) DESC 
                LIMIT %d",
                $meta_key,
                $limit
            )
        );
        
        if (empty($user_ids)) {
            return [];
        }
        
        $leaderboard = [];
        
        foreach ($user_ids as $user_id) {
            $user = get_userdata($user_id);
            if (!$user) {
                continue;
            }
            
            $points = $this->get_user_activity_points($user_id);
            
            $leaderboard[] = [
                'user_id' => $user_id,
                'display_name' => $user->display_name,
                'user_login' => $user->user_login,
                'points' => $points,
                'avatar' => get_avatar_url($user_id, ['size' => 50]),
                'profile_url' => function_exists('bp_core_get_user_domain') ? bp_core_get_user_domain($user_id) : get_author_posts_url($user_id),
            ];
        }
        
        return $leaderboard;
    }

    /**
     * Award test points to a user
     * 
     * @param int $user_id The user ID
     * @param int $points The number of points to award
     * @param string $points_type The type of points to award
     * @return bool True if successful, false otherwise
     */
    public function award_test_points($user_id, $points, $points_type = null) {
        if ($points_type === null) {
            $points_type = $this->points_type;
        }
        
        return GamiPressDataProvider::award_points_with_log(
            $user_id,
            $points,
            $points_type,
            'Test points awarded via leaderboard'
        );
    }
    
    /**
     * Get the rank and position of a specific user within a group
     *
     * @param int $user_id The user ID
     * @param int $group_id The group ID
     * @return array User's rank data including position and nearby members
     */
    public function get_user_rank_in_group($user_id, $group_id) {
        // Get all members' leaderboard data (no limit)
        $all_members_leaderboard = $this->get_group_leaderboard($group_id, 999, 1);
        
        // Find user position
        $position = 0;
        foreach ($all_members_leaderboard as $index => $member) {
            if ($member['user_id'] == $user_id) {
                $position = $index + 1;
                break;
            }
        }
        
        // Get members above and below this user
        $nearby_members = [];
        if ($position > 0) {
            // Get up to 2 members above
            $start = max(0, $position - 3);
            $end = min(count($all_members_leaderboard), $position + 2);
            
            for ($i = $start; $i < $end; $i++) {
                $nearby_members[] = $all_members_leaderboard[$i];
            }
        }
        
        return [
            'position' => $position,
            'nearby_members' => $nearby_members,
            'total_members' => count($all_members_leaderboard),
        ];
    }
}
