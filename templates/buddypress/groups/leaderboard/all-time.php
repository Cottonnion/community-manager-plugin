<?php
/**
 * Template for displaying the all-time group leaderboard
 * 
 * This template displays a leaderboard of group members based on their total GamiPress activity_points
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Include ArticlesHandler to access the sidebar function
use LABGENZ_CM\Articles\ArticlesHandler;

// Get the group ID
$group_id = bp_get_current_group_id();

// Initialize the leaderboard handler
$leaderboard_handler = LABGENZ_CM\Groups\GroupsLeaderboardHandler::get_instance();

// Check if we're changing the point type
if (current_user_can('administrator') && isset($_GET['point_type']) && !empty($_GET['point_type'])) {
    $new_point_type = sanitize_text_field($_GET['point_type']);
    $leaderboard_handler->set_points_type($new_point_type);
}

// Get the leaderboard data
$leaderboard_data = $leaderboard_handler->get_group_leaderboard($group_id, 999);

// Get current user's rank if they're a member of this group
$current_user_id = get_current_user_id();
$user_rank = [];

if (groups_is_user_member($current_user_id, $group_id)) {
    $user_rank = $leaderboard_handler->get_user_rank_in_group($current_user_id, $group_id);
}

// Define the point type label (for display purposes)
$point_type_label = 'Activity Reward Points';
$points_type_name = $leaderboard_handler->get_points_type();

if (function_exists('gamipress_get_points_type')) {
    $points_type = gamipress_get_points_type($points_type_name);
    if (!empty($points_type) && !empty($points_type->post_title)) {
        $point_type_label = $points_type->post_title;
    } else {
        // If no title found, capitalize the points type name
        $point_type_label = ucfirst(str_replace('_', ' ', $points_type_name));
    }
}

// Check if any users have points
$has_points = false;
if (!empty($leaderboard_data)) {
    foreach ($leaderboard_data as $member) {
        if ($member['points'] > 0) {
            $has_points = true;
            break;
        }
    }
}
?>

<div class="bp-group-leaderboard-container">
    <div class="leaderboard-with-sidebar">
        <div class="leaderboard-main-content">
            <div class="leaderboard-tabs">
                <a href="<?php echo esc_url(bp_get_group_permalink() . 'leaderboard/all-time/'); ?>" class="leaderboard-tab active">All-Time Leaderboard</a>
                <a href="<?php echo esc_url(bp_get_group_permalink() . 'leaderboard/weekly/'); ?>" class="leaderboard-tab">Weekly Leaderboard</a>
            </div>
            
            <h2 class="bp-group-leaderboard-title"><?php echo esc_html__('All-Time Group Leaderboard', 'labgenz-cm'); ?></h2>
    
            <?php if (!empty($leaderboard_data) && $has_points): ?>
        <div class="bp-group-leaderboard-list">
            <div class="bp-group-leaderboard-header">
                <div class="bp-leaderboard-rank"><?php echo esc_html__('#', 'labgenz-cm'); ?></div>
                <div class="bp-leaderboard-member"><?php echo esc_html__('Member', 'labgenz-cm'); ?></div>
                <div class="bp-leaderboard-points"><?php echo esc_html($point_type_label); ?></div>
            </div>
            
            <?php foreach ($leaderboard_data as $index => $member): ?>
                <?php $rank = $index + 1; ?>
                <div class="bp-group-leaderboard-item <?php echo ($member['user_id'] == $current_user_id) ? 'current-user' : ''; ?>">
                    <div class="bp-leaderboard-rank">
                        <?php if ($rank <= 3): ?>
                            <span class="bp-leaderboard-rank-top"><?php echo esc_html($rank); ?></span>
                        <?php else: ?>
                            <span class="bp-leaderboard-rank-normal"><?php echo esc_html($rank); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="bp-leaderboard-member">
                        <div class="bp-leaderboard-avatar">
                            <a href="<?php echo esc_url($member['profile_url']); ?>">
                                <img src="<?php echo esc_url($member['avatar']); ?>" alt="<?php echo esc_attr($member['display_name']); ?>" />
                            </a>
                        </div>
                        <div class="bp-leaderboard-name">
                            <a href="<?php echo esc_url($member['profile_url']); ?>">
                                <?php echo esc_html($member['display_name']); ?>
                            </a>
                            <span class="bp-leaderboard-username">@<?php echo esc_html($member['user_login']); ?></span>
                        </div>
                    </div>
                    
                    <div class="bp-leaderboard-points">
                        <?php echo esc_html(number_format($member['points'])); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (!empty($user_rank) && $user_rank['position'] > 10): ?>
            <div class="bp-group-leaderboard-your-position">
                <h3><?php echo esc_html__('Your Position', 'labgenz-cm'); ?></h3>
                
                <div class="bp-group-leaderboard-list">
                    <?php foreach ($user_rank['nearby_members'] as $index => $member): ?>
                        <div class="bp-group-leaderboard-item <?php echo ($member['user_id'] == $current_user_id) ? 'current-user' : ''; ?>">
                            <div class="bp-leaderboard-rank">
                                <span class="bp-leaderboard-rank-normal">
                                    <?php 
                                    $position = $user_rank['position'] - (count($user_rank['nearby_members']) - 1 - $index);
                                    echo esc_html($position); 
                                    ?>
                                </span>
                            </div>
                            
                            <div class="bp-leaderboard-member">
                                <div class="bp-leaderboard-avatar">
                                    <a href="<?php echo esc_url($member['profile_url']); ?>">
                                        <img src="<?php echo esc_url($member['avatar']); ?>" alt="<?php echo esc_attr($member['display_name']); ?>" />
                                    </a>
                                </div>
                                <div class="bp-leaderboard-name">
                                    <a href="<?php echo esc_url($member['profile_url']); ?>">
                                        <?php echo esc_html($member['display_name']); ?>
                                    </a>
                                    <span class="bp-leaderboard-username">@<?php echo esc_html($member['user_login']); ?></span>
                                </div>
                            </div>
                            
                            <div class="bp-leaderboard-points">
                                <?php echo esc_html(number_format($member['points'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <div class="bp-group-leaderboard-empty">
            <p><?php echo esc_html__('No members with points found in this group. Members need to earn ', 'labgenz-cm') . esc_html($point_type_label) . esc_html__(' to appear on the leaderboard.', 'labgenz-cm'); ?></p>
        </div>
    <?php endif; ?>
        </div>
        
 
    </div>
    
    <!-- Add CSS styling -->
    <style>
        .bp-group-leaderboard-container {
            margin: 0 auto;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            color: var(--bb-body-text-color, #4d5c6d);
        }
        
        .bp-group-leaderboard-title {
            margin-bottom: 24px;
            text-align: center;
            font-size: 28px;
            font-weight: 500;
            letter-spacing: -0.5px;
            color: var(--bb-headings-color, #122b46);
        }
        
        .bp-leaderboard-tabs {
            display: flex;
            justify-content: center;
            margin-bottom: 24px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            background-color: #fff;
        }
        
        .bp-leaderboard-tab {
            padding: 12px 24px;
            font-weight: 500;
            color: var(--bb-alternate-text-color, #a3a5a9);
            text-decoration: none;
            transition: all 0.2s ease;
            flex: 1;
            text-align: center;
            border-bottom: 3px solid transparent;
        }
        
        .bp-leaderboard-tab:hover {
            color: var(--bb-primary-color, #385DFF);
            background-color: rgba(var(--bb-primary-color-rgb, 56, 93, 255), 0.02);
        }
        
        .bp-leaderboard-tab.active {
            color: var(--bb-primary-color, #385DFF);
            border-bottom: 3px solid var(--bb-primary-color, #385DFF);
            background-color: rgba(var(--bb-primary-color-rgb, 56, 93, 255), 0.03);
        }
        
        .bp-group-leaderboard-description {
            margin-bottom: 28px;
            text-align: center;
            color: var(--bb-alternate-text-color, #a3a5a9);
            font-size: 15px;
        }
        
        .bp-group-leaderboard-list {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
            margin-bottom: 32px;
            background: #fff;
            border: 1px solid rgba(0, 0, 0, 0.04);
        }
        
        .bp-group-leaderboard-header {
            display: flex;
            background-color: #f8f9fa;
            padding: 16px 20px;
            font-weight: 500;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            font-size: 13px;
            color: var(--bb-alternate-text-color, #a3a5a9);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .bp-group-leaderboard-item {
            display: flex;
            padding: 14px 20px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.03);
            align-items: center;
            transition: all 0.15s ease;
        }
        
        .bp-group-leaderboard-item:last-child {
            border-bottom: none;
        }
        
        .bp-group-leaderboard-item:hover {
            background-color: #f9fafb;
        }
        
        .bp-group-leaderboard-item.current-user {
            background-color: rgba(var(--bb-primary-color-rgb, 56, 93, 255), 0.04);
            border-left: 3px solid var(--bb-primary-color, #385DFF);
        }
        
        .bp-leaderboard-rank {
            width: 87px;
            display: flex;
            align-items: center;
            font-weight: 600;
        }
        
        .bp-leaderboard-rank-top {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ffd86f, #fc9842);
            color: white;
            font-size: 14px;
            box-shadow: 0 2px 4px rgba(252, 152, 66, 0.2);
        }
        
        .bp-leaderboard-rank-normal {
            display: inline-block;
            color: var(--bb-alternate-text-color, #a3a5a9);
            font-size: 15px;
            font-weight: 500;
        }
        
        .bp-leaderboard-member {
            display: flex;
            align-items: center;
            flex: 1;
        }
        
        .bp-leaderboard-avatar {
            margin-right: 14px;
        }
        
        .bp-leaderboard-avatar img {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            object-fit: cover;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }
        
        .bp-leaderboard-name {
            display: flex;
            flex-direction: column;
        }
        
        .bp-leaderboard-name a {
            text-decoration: none;
            color: var(--bb-headings-color, #122b46);
            font-weight: 500;
            font-size: 15px;
            line-height: 1.4;
            transition: color 0.15s ease;
        }
        
        .bp-leaderboard-name a:hover {
            color: var(--bb-primary-color, #385DFF);
        }
        
        .bp-leaderboard-username {
            font-size: 13px;
            color: var(--bb-alternate-text-color, #a3a5a9);
            margin-top: 1px;
        }
        
        .bp-leaderboard-points {
            width: 150px;
            text-align: right;
            font-weight: 600;
            color: var(--bb-alternate-text-color, #a3a5a9);
        }
        
        .bp-group-leaderboard-your-position h3 {
            margin: 8px 0 20px;
            font-size: 17px;
            font-weight: 500;
            color: #444;
            text-align: center;
        }
        
        .bp-group-leaderboard-empty {
            padding: 32px 24px;
            text-align: center;
            background-color: #fafafa;
            border-radius: 12px;
            color: #666;
            line-height: 1.6;
        }
        
        .bp-leaderboard-notice {
            margin-top: 16px;
            font-size: 14px;
            color: #555;
            background-color: #f8f9fa;
            border: 1px solid rgba(0, 0, 0, 0.06);
            border-radius: 8px;
            padding: 12px 16px;
            text-align: center;
            line-height: 1.5;
        }
        
        /* Sidebar layout styling */
        .leaderboard-with-sidebar {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
        }
        
        .leaderboard-main-content {
            flex: 1;
            min-width: 65%;
        }
        
        .leaderboard-sidebar {
            width: 30%;
            min-width: 280px;
        }
        
        /* Leaderboard tabs styling */
        .leaderboard-tabs {
            display: flex;
            margin-bottom: 25px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .leaderboard-tab {
            padding: 12px 24px;
            font-size: 15px;
            font-weight: 500;
            color: var(--bb-alternate-text-color, #a3a5a9);
            text-decoration: none;
            transition: all 0.2s ease;
            border-bottom: 3px solid transparent;
            margin-right: 4px;
        }
        
        .leaderboard-tab:hover {
            color: var(--bb-headings-color, #122b46);
        }
        
        .leaderboard-tab.active {
            color: var(--bb-primary-color, #385DFF);
            border-bottom-color: var(--bb-primary-color, #385DFF);
        }
        
        @media (max-width: 991px) {
            .leaderboard-with-sidebar {
                flex-direction: column;
            }
            
            .leaderboard-sidebar {
                width: 100%;
            }
        }
    </style>
</div><!-- .bp-group-leaderboard-container -->
