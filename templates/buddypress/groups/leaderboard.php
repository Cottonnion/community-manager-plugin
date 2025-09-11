<?php
/**
 * Group Leaderboard Router Template
 * 
 * This template handles the leaderboard interface with tab switching functionality.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get the current tab from the URL
$current_tab = 'all-time'; // Default tab

// Check if we're on a specific leaderboard tab
$group_permalink = bp_get_group_permalink();
$current_url = home_url( $_SERVER['REQUEST_URI'] );

// Parse the URL to get the path
$path = wp_parse_url( $current_url, PHP_URL_PATH );

// Check if we're on the weekly tab
if ( strpos( $path, '/leaderboard/weekly' ) !== false ) {
    $current_tab = 'weekly';
}

// Initialize the leaderboard handler
$leaderboard_handler = LABGENZ_CM\Groups\GroupsLeaderboardHandler::get_instance();
$group_id = bp_get_current_group_id();

// Initialize variables for the active tab content
$tab_content = '';
$leaderboard_data = [];
$user_rank = [];
$has_points = false;
$current_user_id = get_current_user_id();
$point_type_label = 'Activity Reward Points';

// Get point type label
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

// Get the appropriate leaderboard data for pre-loading the active tab
if ($current_tab === 'weekly') {
    $leaderboard_data = $leaderboard_handler->get_weekly_group_leaderboard($group_id, 10);
    
    // Get current user's rank if they're a member of this group
    if ($current_user_id && groups_is_user_member($current_user_id, $group_id)) {
        $user_rank = $leaderboard_handler->get_user_weekly_rank_in_group($current_user_id, $group_id);
    }
    
    // Calculate date range for weekly tab
    $end_date = current_time('Y-m-d');
    $start_date = date('Y-m-d', strtotime('-7 days'));
} else {
    $leaderboard_data = $leaderboard_handler->get_group_leaderboard($group_id, 10);
    
    // Get current user's rank if they're a member of this group
    if ($current_user_id && groups_is_user_member($current_user_id, $group_id)) {
        $user_rank = $leaderboard_handler->get_user_rank_in_group($current_user_id, $group_id);
    }
}

// Check if any users have points
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
    <!-- Leaderboard Tabs -->
    <div class="leaderboard-tabs">
        <a href="<?php echo esc_url(bp_get_group_permalink() . 'leaderboard/'); ?>" class="leaderboard-tab <?php echo ($current_tab === 'all-time') ? 'active' : ''; ?>" data-tab="all-time">
            <?php echo esc_html__('All-Time', 'labgenz-cm'); ?>
        </a>
        <a href="<?php echo esc_url(bp_get_group_permalink() . 'leaderboard/weekly/'); ?>" class="leaderboard-tab <?php echo ($current_tab === 'weekly') ? 'active' : ''; ?>" data-tab="weekly">
            <?php echo esc_html__('Weekly', 'labgenz-cm'); ?>
        </a>
    </div>

    <div class="leaderboard-main-content">
        <!-- Tab content will be loaded here via AJAX -->
            <div id="<?php echo esc_attr($current_tab); ?>-leaderboard" class="leaderboard-tab-content active">
                <?php if ($current_tab === 'weekly'): ?>
                    <h2 class="bp-group-leaderboard-title"><?php echo esc_html__('Weekly Group Leaderboard', 'labgenz-cm'); ?></h2>
                    <div class="weekly-date-range">
                        <?php echo esc_html__('Points earned from', 'labgenz-cm'); ?> <strong><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($start_date))); ?></strong> 
                        <?php echo esc_html__('to', 'labgenz-cm'); ?> <strong><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($end_date))); ?></strong>
                    </div>
                <?php else: ?>
                    <h2 class="bp-group-leaderboard-title"><?php echo esc_html__('All-Time Group Leaderboard', 'labgenz-cm'); ?></h2>
                <?php endif; ?>

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
                    <?php if ($current_tab === 'weekly'): ?>
                        <p><?php echo esc_html__('No members with points earned in the past week. Members need to earn ', 'labgenz-cm') . esc_html($point_type_label) . esc_html__(' in the last 7 days to appear on the weekly leaderboard.', 'labgenz-cm'); ?></p>
                    <?php else: ?>
                        <p><?php echo esc_html__('No members with points found in this group. Members need to earn ', 'labgenz-cm') . esc_html($point_type_label) . esc_html__(' to appear on the leaderboard.', 'labgenz-cm'); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            </div>
        </div>
</div>
