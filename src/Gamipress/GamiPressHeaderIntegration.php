<?php
/**
 * GamiPress Header Integration for Labgenz Community Management
 * 
 * Integrates GamiPress header functionality into the community management plugin
 * Requires: GamiPress plugin
 * 
 * @package LABGENZ_CM\Gamipress
 */

namespace LABGENZ_CM\Gamipress;

if (!defined('ABSPATH')) {
    exit;
}

class GamiPressHeaderIntegration {
    
    private static $instance = null;
    private static $scripts_enqueued = false;
    private $log_file;
    
    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Set up log file
        $plugin_dir = dirname(dirname(__DIR__));
        $this->log_file = $plugin_dir . '/src/logs/gamipress-integration.log';
        
        // Ensure logs directory exists
        $logs_dir = dirname($this->log_file);
        if (!file_exists($logs_dir)) {
            wp_mkdir_p($logs_dir);
        }
        
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // Hook into GamiPress points changes to update ranks
        add_action('gamipress_update_user_points', array($this, 'handle_points_change'), 10, 4);
        add_action('gamipress_award_points_to_user', array($this, 'handle_points_award'), 10, 3);
        add_action('gamipress_deduct_points_to_user', array($this, 'handle_points_deduct'), 10, 3);
        
        // Also hook into rank changes and other GamiPress events
        // add_action('gamipress_update_user_rank', array($this, 'handle_rank_change'), 10, 3);
        // add_action('gamipress_revoke_rank_to_user', array($this, 'handle_rank_change'), 10, 3);
        // add_action('gamipress_user_completed_achievement', array($this, 'handle_achievement_complete'), 10, 3);
    }
    
    /**
     * Register admin settings menu
     */
    public function register_admin_menu() {
        add_options_page(
            'GamiPress Header Settings',
            'GamiPress Header',
            'manage_options',
            'gamipress-header-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('gamipress_header_settings', 'ghi_progress_bar');
        register_setting('gamipress_header_settings', 'ghi_rank_type');
        register_setting('gamipress_header_settings', 'ghi_points_type');
        register_setting('gamipress_header_settings', 'ghi_coins_type');
        register_setting('gamipress_header_settings', 'ghi_redeem_page');
    }
    
    /**
     * Settings page HTML
     */
    public function settings_page() {
        if (!function_exists('gamipress_get_rank_types')) {
            echo '<div class="notice notice-error"><p>GamiPress plugin is required for this functionality.</p></div>';
            return;
        }
        ?>
        <div class="wrap">
            <h1>GamiPress Header Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('gamipress_header_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Show Progress Bar</th>
                        <td>
                            <select name="ghi_progress_bar">
                                <option value="1" <?php selected(get_option('ghi_progress_bar'), '1'); ?>>Enabled</option>
                                <option value="0" <?php selected(get_option('ghi_progress_bar'), '0'); ?>>Disabled</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Rank Type</th>
                        <td>
                            <select name="ghi_rank_type">
                                <option value="">Select Rank Type</option>
                                <?php
                                $rank_types = gamipress_get_rank_types();
                                foreach ($rank_types as $rank) {
                                    $data = get_post($rank['ID']);
                                    $selected = selected(get_option('ghi_rank_type'), $data->post_name, false);
                                    echo '<option value="' . esc_attr($data->post_name) . '" ' . $selected . '>' . esc_html($rank['plural_name']) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Points Type</th>
                        <td>
                            <select name="ghi_points_type">
                                <option value="">Select Points Type</option>
                                <?php
                                $point_types = gamipress_get_points_types();
                                foreach ($point_types as $points) {
                                    $data = get_post($points['ID']);
                                    $selected = selected(get_option('ghi_points_type'), $data->post_name, false);
                                    echo '<option value="' . esc_attr($data->post_name) . '" ' . $selected . '>' . esc_html($points['plural_name']) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Coins Type</th>
                        <td>
                            <select name="ghi_coins_type">
                                <option value="">Select Coins Type</option>
                                <?php
                                foreach ($point_types as $points) {
                                    $data = get_post($points['ID']);
                                    $selected = selected(get_option('ghi_coins_type'), $data->post_name, false);
                                    echo '<option value="' . esc_attr($data->post_name) . '" ' . $selected . '>' . esc_html($points['plural_name']) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Redeem Page</th>
                        <td>
                            <select name="ghi_redeem_page">
                                <option value="">Select Page</option>
                                <?php
                                $pages = get_pages();
                                foreach ($pages as $page) {
                                    $selected = selected(get_option('ghi_redeem_page'), $page->ID, false);
                                    echo '<option value="' . esc_attr($page->ID) . '" ' . $selected . '>' . esc_html($page->post_title) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        // Prevent multiple enqueues
        if (self::$scripts_enqueued) {
            return;
        }
        
        if (!get_option('ghi_progress_bar', 0)) {
            return;
        }
        
        if (!is_user_logged_in()) {
            return;
        }
        
        if (!function_exists('gamipress_get_user_rank_id')) {
            return;
        }
        
        // Mark scripts as enqueued
        self::$scripts_enqueued = true;
        
        $user_id = get_current_user_id();
        $data = $this->get_gamification_data($user_id);
        
        // Inline the JavaScript to avoid creating separate files
        wp_add_inline_script('jquery', $this->get_inline_js($data));
    }
    
    /**
     * Get gamification data for user
     */
    private function get_gamification_data($user_id) {
        $this->log("=== GamiPress Debug: Starting get_gamification_data for user_id: " . $user_id . " ===");
        
        $rank_type = get_option('ghi_rank_type');
        $points_type = get_option('ghi_points_type'); 
        $coins_type = get_option('ghi_coins_type');
        $redeem_page = get_option('ghi_redeem_page');
        
        $this->log("GamiPress Debug: Settings - rank_type=" . $rank_type . ", points_type=" . $points_type . ", coins_type=" . $coins_type);
        
        $current_rank_id = gamipress_get_user_rank_id($user_id, $rank_type);
        $next_level_id = gamipress_get_next_user_rank_id($user_id, $rank_type);
        $current_rank = get_the_title($current_rank_id);
        $current_points = gamipress_get_user_points($user_id, $points_type);
        $current_coins = gamipress_get_user_points($user_id, $coins_type);
        
        $this->log("GamiPress Debug: current_rank_id=" . $current_rank_id);
        $this->log("GamiPress Debug: next_level_id=" . $next_level_id);
        $this->log("GamiPress Debug: current_rank=" . $current_rank);
        $this->log("GamiPress Debug: current_points=" . $current_points);
        $this->log("GamiPress Debug: current_coins=" . $current_coins);
        
        $points_needed = 0;
        $completion = 0;
        
        // Use a dynamic points-based system without hardcoded values
        if ($rank_type && $current_rank_id) {
            $this->log("GamiPress Debug: Using dynamic points-based progression system");
            
            // Get all ranks sorted by menu_order (which determines hierarchy)
            $all_ranks = gamipress_get_ranks(array(
                'post_type' => $rank_type,
                'orderby' => 'menu_order',
                'order' => 'ASC'
            ));
            
            $this->log("GamiPress Debug: All ranks found: " . count($all_ranks));
            foreach ($all_ranks as $index => $rank) {
                $this->log("GamiPress Debug: Rank " . $index . ": ID=" . $rank->ID . ", title=" . $rank->post_title . ", menu_order=" . $rank->menu_order);
            }
            
            // Find current rank position and next rank
            $current_rank_position = -1;
            $next_rank = null;
            
            foreach ($all_ranks as $index => $rank) {
                if ($rank->ID == $current_rank_id) {
                    $current_rank_position = $index;
                    // Get next rank if it exists
                    if (isset($all_ranks[$index + 1])) {
                        $next_rank = $all_ranks[$index + 1];
                    }
                    break;
                }
            }
            
            $this->log("GamiPress Debug: Current rank position: " . $current_rank_position . " out of " . count($all_ranks));
            $this->log("GamiPress Debug: Next rank: " . ($next_rank ? $next_rank->post_title . " (ID: " . $next_rank->ID . ")" : "None - at max rank"));
            
            if ($next_rank) {
                // Try to get points needed from various sources
                $points_needed = $this->get_dynamic_points_needed($next_rank->ID, $current_rank_position, $current_points);
                $this->log("GamiPress Debug: Dynamic points needed for next rank (" . $next_rank->post_title . "): " . $points_needed);
                
                // Check if user should be automatically promoted
                if ($current_points >= $points_needed) {
                    $this->log("GamiPress Debug: User has enough points for promotion - attempting automatic rank up");
                    $promotion_result = $this->attempt_rank_promotion($user_id, $next_rank->ID, $rank_type, $current_points, $points_needed);
                    
                    if ($promotion_result) {
                        $this->log("GamiPress Debug: Rank promotion successful - refreshing data");
                        // Refresh the rank data after promotion
                        $current_rank_id = gamipress_get_user_rank_id($user_id, $rank_type);
                        $current_rank = get_the_title($current_rank_id);
                        
                        // Update next rank target
                        $next_level_id = gamipress_get_next_user_rank_id($user_id, $rank_type);
                        
                        // Find new position and next rank
                        foreach ($all_ranks as $index => $rank) {
                            if ($rank->ID == $current_rank_id) {
                                $current_rank_position = $index;
                                if (isset($all_ranks[$index + 1])) {
                                    $next_rank = $all_ranks[$index + 1];
                                    $points_needed = $this->get_dynamic_points_needed($next_rank->ID, $current_rank_position, $current_points);
                                } else {
                                    $next_rank = null;
                                }
                                break;
                            }
                        }
                        
                        $this->log("GamiPress Debug: After promotion - new rank: " . $current_rank . ", next target: " . ($next_rank ? $next_rank->post_title . " (" . $points_needed . " points)" : "Max rank reached"));
                    }
                }
                
            } else {
                // User is at maximum rank
                $this->log("GamiPress Debug: User is at maximum rank - showing current points as achievement");
                $points_needed = $current_points > 0 ? $current_points : 100;
                $completion = 100;
            }
            
        } else {
            $this->log("GamiPress Debug: No rank_type or current_rank_id available - using default progression");
            $points_needed = 100; // Default progression target
        }
        
        // Calculate completion percentage
        if ($completion == 0 && $points_needed > 0) {
            $completion = round($current_points / $points_needed * 100, 0);
            if ($completion > 100) $completion = 100;
        }
        
        $this->log("GamiPress Debug: Before final checks - points_needed=" . $points_needed . ", completion=" . $completion);
        
        // Handle case where user has exceeded requirements but hasn't ranked up yet
        // BUT skip this logic if user is at max rank and showing achievement points
        if ($points_needed > 0 && $current_points >= $points_needed && $completion >= 100 && $points_needed != $current_points) {
            $this->log("GamiPress Debug: User has exceeded rank requirements but hasn't been promoted yet");
            
            // Show that they've completed this rank and are ready for the next one
            // Instead of showing 51/50 (which is confusing), show progress toward the rank after next
            if ($next_rank && isset($all_ranks[$current_rank_position + 2])) {
                $rank_after_next = $all_ranks[$current_rank_position + 2];
                $this->log("GamiPress Debug: Showing progress toward rank after next: " . $rank_after_next->post_title);
                
                // Get points needed for the rank after next
                $points_for_rank_after_next = $this->get_dynamic_points_needed($rank_after_next->ID, $current_rank_position + 1, $current_points);
                
                $points_needed = $points_for_rank_after_next;
                $completion = round($current_points / $points_needed * 100, 0);
                if ($completion > 100) $completion = 100;
                
                $this->log("GamiPress Debug: Adjusted to show progress toward " . $rank_after_next->post_title . ": " . $current_points . "/" . $points_needed . " (" . $completion . "%)");
                
            } else {
                // If no rank after next, show current progress with a note
                $this->log("GamiPress Debug: No rank after next available - showing ready for promotion");
                // Keep current values but cap completion at 100%
                $completion = 100;
            }
        } else if ($points_needed == $current_points && $completion == 100) {
            // This is the max rank achievement display case - don't modify it
            $this->log("GamiPress Debug: Displaying max rank achievement - keeping points_needed=" . $points_needed);
        }
        
        $this->log("GamiPress Debug: Final - points_needed=" . $points_needed . ", completion=" . $completion);
        
        $redeem_url = $redeem_page ? get_permalink($redeem_page) : 'javascript:void(0)';
        
        $data = array(
            'rank_img' => get_the_post_thumbnail_url($current_rank_id),
            'current_rank' => $current_rank,
            'current_points' => $current_points,
            'points_needed' => $points_needed,
            'completion' => $completion,
            'redeem_screen' => $redeem_url,
            'coins_img' => $this->get_coins_image($coins_type),
            'current_coins' => $current_coins,
            'accent_color' => '#007cba' // Default WP blue, customize as needed
        );
        
        $this->log("=== GamiPress Debug: Final data=" . print_r($data, true) . " ===");
        return $data;
    }
    
    /**
     * Get coins image
     */
    private function get_coins_image($coins_type) {
        if (empty($coins_type)) return '';
        
        $coins = gamipress_get_points_type($coins_type);
        $coins_img = !empty($coins) ? get_the_post_thumbnail_url($coins['ID']) : '';
        
        // You can set a default image URL here
        if (empty($coins_img)) {
            $coins_img = 'https://v2mlmmasteryclub.labgenz.com/wp-content/uploads/2025/07/box-heart-1.png';
        }
        
        return $coins_img;
    }
    
    /**
     * Generate inline JavaScript
     */
    private function get_inline_js($data) {
        $json_data = json_encode($data);
        return "
        jQuery(document).ready(function($) {
            var gamificationData = {$json_data};
            
            // Find header element (adjust selector as needed)
            var \$header = $('.site-header-container');
            var \$headerAside = $('.header-aside-inner');
            
            if (\$header.length === 0) {
                return;
            }
            
            var coinsHtml = '';
            var rankHtml = '';
            
            // Create coins element
            if (gamificationData.current_coins && gamificationData.coins_img) {
                coinsHtml = '<div class=\"ghi-coins\" style=\"display: flex; align-items: center; gap: 8px;\">' +
                    '<img src=\"https://v2mlmmasteryclub.labgenz.com/wp-content/uploads/2025/07/credit-card-1-1.png\" alt=\"Credits\" style=\"width: 24px; height: 24px; margin-left: 15px;\">' +
                    '<span style=\"margin: 0 0 0 5px; line-height: 1; font-weight: bold;\">Credits: ' + gamificationData.current_coins + '</span>' +
                    '<img src=\"' + gamificationData.coins_img + '\" alt=\"Coins\" style=\"width: 24px; height: 24px;\">' +
                    '<h6 style=\"font-weight: bold; margin: 0; line-height: 1;font-size:14px;\"> Points </h6>' +
                    '<span style=\"margin: 0; line-height: 1;\">' + gamificationData.current_points + '</span>' +
                    '</div>';
            }
            
            // Create rank/progress element  
            if (gamificationData.current_rank) {
                var rankImageHtml = '';
                if (gamificationData.rank_img) {
                    rankImageHtml = '<div class=\"ghi-rank-avatar\">' +
                        '<img src=\"' + gamificationData.rank_img + '\" alt=\"Rank\">' +
                    '</div>';
                } else {
                    rankImageHtml = '<div class=\"ghi-rank-avatar ghi-rank-letter\">' +
                        gamificationData.current_rank.charAt(0).toUpperCase() +
                    '</div>';
                }
                
                // Handle points display and percentage calculation
                var pointsText = '';
                var progressWidth = 0;
                var percentageText = '';
                
                if (gamificationData.points_needed > 0) {
                    pointsText = gamificationData.current_points + ' / ' + gamificationData.points_needed;
                    progressWidth = gamificationData.completion;
                    var remainingPercentage = 100 - gamificationData.completion;
                    percentageText = remainingPercentage.toFixed(0) + '% To The Next Level';
                } else if (gamificationData.completion === 100) {
                    // User is at max rank
                    pointsText = gamificationData.current_points + ' (Max Rank)';
                    progressWidth = 100;
                    percentageText = 'Max Level Achieved';
                } else {
                    // Show current points only
                    pointsText = gamificationData.current_points + ' points';
                    progressWidth = Math.min(100, (gamificationData.current_points / 100) * 100); // Fallback progress
                    var remainingPercentage = 100 - progressWidth;
                    percentageText = remainingPercentage.toFixed(0) + '% To Next Level';
                }
                
                rankHtml = '<div class=\"ghi-rank\">' +
                    rankImageHtml +
                    '<div class=\"ghi-rank-info\">' +
                        '<div class=\"ghi-rank-details\">' +
                            '<span class=\"ghi-rank-name\">' + percentageText + '</span>' +
                        '</div>' +
                        '<div class=\"ghi-progress-bar\">' +
                            '<div class=\"ghi-progress-fill\" style=\"width: ' + progressWidth + '%;\"></div>' +
                        '</div>' +
                        '<div class=\"ghi-points-bottom\">' + pointsText + '</div>' +
                    '</div>' +
                '</div>';
            }
            
            // Inject into header
            if (coinsHtml || rankHtml) {
                var containerHtml = '<div class=\"ghi-glass-container\" style=\"transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); overflow: hidden; max-height: 200px; opacity: 1;\">' + 
                    '<div class=\"ghi-content\">' + rankHtml + coinsHtml + '</div>' +
                    '</div>';
                \$header.after(containerHtml);
                
                // Get saved header state from localStorage
                var savedState = localStorage.getItem('ghi_header_collapsed');
                var isCollapsed = savedState === 'true';
                
                // Apply saved state on page load
                var \$container = \$('.ghi-glass-container');
                if (isCollapsed) {
                    \$container.addClass('ghi-hidden').css({
                        'max-height': '0',
                        'opacity': '0',
                        'margin-top': '0',
                        'margin-bottom': '0'
                    });
                }
                
                // Add toggle button to header-aside-inner if it exists
                if (\$headerAside.length > 0) {
                    var toggleBtnHtml = '<div class=\"ghi-toggle-btn\" style=\"position: relative;margin-right: 0px;cursor: pointer;/* width: 32px; *//* height: 32px; */display: flex;align-items: center;justify-content: center;transition: 0.3s;transform: scale(1);\" onmouseover=\"this.style.transform=\'scale(1.1)\';\" onmouseout=\"this.style.transform=\'scale(1)\';\" data-ghi-toggle=\"true\"><img src=\"https://v2mlmmasteryclub.labgenz.com/wp-content/uploads/2025/07/ghi-collapse-header.png\" alt=\"Toggle\" style=\"width: 20px; height: 20px; transition: transform 0.3s ease;\"></div>';
                    \$headerAside.prepend(toggleBtnHtml);
                    
                    // Set initial icon rotation based on saved state
                    var \$icon = \$('.ghi-toggle-btn img');
                    if (isCollapsed) {
                        \$icon.css('transform', 'rotate(180deg)');
                    }
                }
                
                // Add mobile toggle button
                \$('body').append('<div class=\"ghi-toggle-btn-mobile\" style=\"display: none;\"><img src=\"https://v2mlmmasteryclub.labgenz.com/wp-content/uploads/2025/07/ghi-collapse-header.png\" alt=\"Toggle\" style=\"transition: transform 0.3s ease;' + (isCollapsed ? 'transform: rotate(180deg);' : '') + '\"></div>');
                
                // Show mobile toggle button only on mobile
                function updateMobileToggleVisibility() {
                    if (window.innerWidth <= 768) {
                        \$('.ghi-toggle-btn-mobile').show();
                    } else {
                        \$('.ghi-toggle-btn-mobile').hide();
                    }
                }
                
                // Call once on load
                updateMobileToggleVisibility();
                
                // Update on window resize
                \$(window).on('resize', updateMobileToggleVisibility);
                
                // Function to toggle header visibility
                function toggleHeaderVisibility() {
                    var \$container = \$('.ghi-glass-container');
                    var isHidden = \$container.hasClass('ghi-hidden');
                    var \$desktopIcon = \$('.ghi-toggle-btn img');
                    var \$mobileIcon = \$('.ghi-toggle-btn-mobile img');
                    
                    if (isHidden) {
                        // Show the header
                        \$container.removeClass('ghi-hidden').css({
                            'max-height': '200px',
                            'opacity': '1',
                            'margin-top': '0',
                            'margin-bottom': '0'
                        });
                        \$desktopIcon.css('transform', 'rotate(0deg)');
                        \$mobileIcon.css('transform', 'rotate(0deg)');
                        // Save expanded state to localStorage
                        localStorage.setItem('ghi_header_collapsed', 'false');
                    } else {
                        // Hide the header
                        \$container.addClass('ghi-hidden').css({
                            'max-height': '0',
                            'opacity': '0',
                            'margin-top': '0',
                            'margin-bottom': '0'
                        });
                        \$desktopIcon.css('transform', 'rotate(180deg)');
                        \$mobileIcon.css('transform', 'rotate(180deg)');
                        // Save collapsed state to localStorage
                        localStorage.setItem('ghi_header_collapsed', 'true');
                    }
                }
                
                // Add click event handler using jQuery for desktop toggle
                \$headerAside.on('click', '.ghi-toggle-btn', function() {
                    toggleHeaderVisibility();
                });
                
                // Add click event handler for mobile toggle
                \$(document).on('click', '.ghi-toggle-btn-mobile', function() {
                    toggleHeaderVisibility();
                });
            }
        });
        ";
    }

    
    /**
     * Get dynamic points needed for next rank using various GamiPress data sources
     */
    private function get_dynamic_points_needed($next_rank_id, $current_rank_position, $current_points) {
        $this->log("GamiPress Debug: get_dynamic_points_needed called - next_rank_id=" . $next_rank_id . ", current_rank_position=" . $current_rank_position . ", current_points=" . $current_points);
        
        $points_needed = 0;
        
        // Method 1: Try to get points from rank requirements (most accurate)
        $requirements = gamipress_get_rank_requirements($next_rank_id);
        $this->log("GamiPress Debug: Requirements for rank " . $next_rank_id . ": " . print_r($requirements, true));
        
        if (!empty($requirements)) {
            foreach ($requirements as $requirement) {
                // Try different meta keys that might contain points requirements
                $meta_keys_to_try = [
                    '_gamipress_points_required',
                    '_gamipress_count', 
                    '_gamipress_points',
                    '_gamipress_achievement_points',
                    '_gamipress_points_to_unlock'
                ];
                
                foreach ($meta_keys_to_try as $meta_key) {
                    $meta_value = get_post_meta($requirement->ID, $meta_key, true);
                    if (!empty($meta_value) && is_numeric($meta_value)) {
                        $points_needed = intval($meta_value);
                        $this->log("GamiPress Debug: Found points requirement: " . $points_needed . " from meta key: " . $meta_key);
                        return $points_needed;
                    }
                }
            }
        }
        
        // Method 2: Try to get points from rank meta directly
        $rank_meta_keys = [
            '_gamipress_points_to_unlock',
            '_gamipress_rank_points',
            '_gamipress_points_required'
        ];
        
        foreach ($rank_meta_keys as $meta_key) {
            $meta_value = get_post_meta($next_rank_id, $meta_key, true);
            if (!empty($meta_value) && is_numeric($meta_value)) {
                $points_needed = intval($meta_value);
                $this->log("GamiPress Debug: Found points requirement from rank meta: " . $points_needed . " from meta key: " . $meta_key);
                return $points_needed;
            }
        }
        
        // Method 3: Use rank priority difference as a multiplier
        $next_rank_priority = get_post_meta($next_rank_id, '_gamipress_rank_priority', true);
        if (!empty($next_rank_priority) && is_numeric($next_rank_priority)) {
            // Use priority as a base multiplier (priority * 25 points)
            $points_needed = intval($next_rank_priority) * 25;
            $this->log("GamiPress Debug: Using rank priority calculation: priority " . $next_rank_priority . " * 25 = " . $points_needed);
            return $points_needed;
        }
        
        // Method 4: Progressive scaling based on position (double points each level)
        $base_points = 50; // Starting amount
        
        // Special case: if current_rank_position is -1 or 0, this is likely the lowest rank
        if ($current_rank_position <= 0) {
            $points_needed = 0; // Lowest rank requires 0 points
            $this->log("GamiPress Debug: Lowest rank detected (position " . $current_rank_position . ") - setting points_needed = 0");
        } else {
            $points_needed = $base_points * pow(2, $current_rank_position);
            $this->log("GamiPress Debug: Using progressive scaling: " . $base_points . " * 2^" . $current_rank_position . " = " . $points_needed);
        }
        
        // Method 5: If all else fails and points_needed is still too low, use current points + reasonable increment
        if ($points_needed <= $current_points && $current_rank_position > 0) {
            $increment = max(50, $current_points * 0.5); // 50% more points or minimum 50
            $points_needed = $current_points + $increment;
            $this->log("GamiPress Debug: Using current points + increment: " . $current_points . " + " . $increment . " = " . $points_needed);
        }

        $this->log("GamiPress Debug: Final points_needed for rank " . $next_rank_id . ": " . $points_needed);
        return $points_needed;
    }

    /**
     * Attempt to promote user to next rank automatically
     */
    private function attempt_rank_promotion($user_id, $next_rank_id, $rank_type, $current_points, $points_needed) {
        // Method 1: Use GamiPress built-in promotion function
        if (function_exists('gamipress_update_user_rank')) {
            $result = gamipress_update_user_rank($user_id, $next_rank_id, $rank_type);
            
            if ($result) {
                // Trigger GamiPress hooks for rank earned
                if (function_exists('gamipress_trigger_event')) {
                    gamipress_trigger_event(array(
                        'event' => 'gamipress_earn_rank',
                        'user_id' => $user_id,
                        'rank_id' => $next_rank_id,
                        'rank_type' => $rank_type
                    ));
                }
                
                return true;
            }
        }
        
        // Method 2: Direct database update as fallback
        if (function_exists('gamipress_get_user_rank_id')) {
            // Update user rank meta
            $meta_key = '_gamipress_' . $rank_type . '_rank';
            $updated = update_user_meta($user_id, $meta_key, $next_rank_id);
            
            if ($updated !== false) {
                // Clear any relevant caches
                if (function_exists('gamipress_delete_user_earnings_cache')) {
                    gamipress_delete_user_earnings_cache($user_id);
                }
                
                // Log the rank earning in GamiPress earnings table if function exists
                if (function_exists('gamipress_insert_user_earning')) {
                    gamipress_insert_user_earning($user_id, $next_rank_id, 'rank', array(
                        'date' => date('Y-m-d H:i:s'),
                        'reason' => 'Automatic promotion via points threshold'
                    ));
                }
                
                return true;
            }
        }
        
        // Method 3: WordPress action hook trigger
        // Trigger custom action for rank promotion
        do_action('gamipress_auto_rank_promotion', $user_id, $next_rank_id, $rank_type, $current_points, $points_needed);
        
        // Check if rank was updated
        $new_rank_id = gamipress_get_user_rank_id($user_id, $rank_type);
        if ($new_rank_id == $next_rank_id) {
            return true;
        }
        
        return false;
    }

    /**
     * Handle points change events
     */
    public function handle_points_change($user_id, $points, $points_type, $reason) {
        $this->check_and_update_rank($user_id, $points_type);
    }

    /**
     * Handle points award events
     */
    public function handle_points_award($user_id, $points, $points_type) {
        $this->check_and_update_rank($user_id, $points_type);
    }

    /**
     * Handle points deduction events
     */
    public function handle_points_deduct($user_id, $points, $points_type) {
        $this->check_and_update_rank($user_id, $points_type);
    }

    /**
     * Handle rank change events
     */
    public function handle_rank_change($user_id, $rank_id, $rank_type) {
        // Rank change handled - no automatic refresh
    }

    /**
     * Handle achievement completion events
     */
    public function handle_achievement_complete($user_id, $achievement_id, $trigger) {
        // Achievement completion handled - no automatic refresh
    }

    /**
     * Check user's current points and update rank accordingly (promotion or demotion)
     */
    private function check_and_update_rank($user_id, $points_type) {
        // Only process if this is the configured points type
        $configured_points_type = get_option('ghi_points_type');
        if ($points_type !== $configured_points_type) {
            return;
        }

        $rank_type = get_option('ghi_rank_type');
        if (!$rank_type) {
            return;
        }

        $current_points = gamipress_get_user_points($user_id, $points_type);
        $current_rank_id = gamipress_get_user_rank_id($user_id, $rank_type);
        
        // Get all ranks sorted by menu_order
        $all_ranks = gamipress_get_ranks(array(
            'post_type' => $rank_type,
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ));

        $current_rank_position = -1;
        foreach ($all_ranks as $index => $rank) {
            if ($rank->ID == $current_rank_id) {
                $current_rank_position = $index;
                break;
            }
        }

        if ($current_rank_position === -1) {
            return; // Current rank not found
        }

        // Check for promotion
        $this->check_rank_promotion($user_id, $rank_type, $all_ranks, $current_rank_position, $current_points);
    }

    /**
     * Check if user should be promoted to a higher rank
     */
    private function check_rank_promotion($user_id, $rank_type, $all_ranks, $current_rank_position, $current_points) {
        // Check all higher ranks to see if user qualifies
        for ($i = $current_rank_position + 1; $i < count($all_ranks); $i++) {
            $target_rank = $all_ranks[$i];
            $points_needed = $this->get_dynamic_points_needed($target_rank->ID, $i - 1, $current_points);
            
            if ($current_points >= $points_needed) {
                // User qualifies for this rank, promote them
                $this->attempt_rank_promotion($user_id, $target_rank->ID, $rank_type, $current_points, $points_needed);
                // Continue checking higher ranks
            } else {
                // User doesn't qualify for this rank, stop checking
                break;
            }
        }
    }

    /**
     * Custom logging method
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[{$timestamp}] {$message}" . PHP_EOL;
        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Initialize the integration
     */
    public static function init() {
        // if (!class_exists('GamiPress')) {
        //     return; // GamiPress not active
        // }
        
        self::get_instance();
    }
}

// Initialize the integration
// function init_gamipress_header_integration() {
//     // if (!class_exists('GamiPress')) {
//     //     return; // GamiPress not active
//     // }
    
//     GamiPressHeaderIntegration::get_instance();
// }
// add_action('plugins_loaded', 'init_gamipress_header_integration');
