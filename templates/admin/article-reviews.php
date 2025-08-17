<?php
/**
 * Admin template for displaying article reviews.
 *
 * @package Labgenz_CM
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap mlmmc-reviews-admin">
    <h1 class="wp-heading-inline"><?php _e('Article Review Management', 'labgenz-cm'); ?></h1>
    
    <hr class="wp-header-end">
    
    <div class="mlmmc-reviews-settings">
        <h2><?php _e('Ratings Settings', 'labgenz-cm'); ?></h2>
        <?php 
        $ratings_enabled = get_option('mlmmc_ratings_enabled', true);
        // Make sure we have a boolean value
        $ratings_enabled = filter_var($ratings_enabled, FILTER_VALIDATE_BOOLEAN);
        ?>
        <div class="ratings-toggle-container">
            <label class="switch">
                <input type="checkbox" id="ratings-toggle" <?php checked($ratings_enabled); ?>>
                <span class="slider round"></span>
            </label>
            <span class="toggle-label"><?php _e('Enable Reviews', 'labgenz-cm'); ?></span>
            <p class="description"><?php _e('Toggle this setting to enable or disable article ratings throughout the site.', 'labgenz-cm'); ?></p>
            <div class="toggle-status">
                <span class="status"><?php echo $ratings_enabled ? __('Reviews are currently ENABLED', 'labgenz-cm') : __('Reviews are currently DISABLED', 'labgenz-cm'); ?></span>
            </div>
        </div>
    </div>
    
    <div class="mlmmc-reviews-stats">
        <div class="stats-box">
            <h3><?php _e('Total Reviews', 'labgenz-cm'); ?></h3>
            <span class="stat-number"><?php echo count($reviews_data); ?></span>
        </div>
        
        <div class="stats-box">
            <h3><?php _e('Average Rating', 'labgenz-cm'); ?></h3>
            <span class="stat-number">
                <?php 
                $total_rating = 0;
                foreach ($reviews_data as $review) {
                    $total_rating += $review['rating'];
                }
                $average = count($reviews_data) > 0 ? round($total_rating / count($reviews_data), 1) : 0;
                echo $average;
                ?>
            </span>
            <div class="star-rating rating-stars">
                <?php 
                for ($i = 1; $i <= 5; $i++) {
                    echo $i <= $average ? '★' : '☆';
                }
                ?>
            </div>
        </div>
        
        <div class="stats-box">
            <h3><?php _e('Articles Rated', 'labgenz-cm'); ?></h3>
            <span class="stat-number">
                <?php 
                $unique_articles = [];
                foreach ($reviews_data as $review) {
                    $unique_articles[$review['post_id']] = true;
                }
                echo count($unique_articles);
                ?>
            </span>
        </div>
    </div>
    
    <?php if (empty($reviews_data)): ?>
        <div class="mlmmc-empty-state">
            <div class="empty-icon">⭐</div>
            <h2><?php _e('No Reviews Yet', 'labgenz-cm'); ?></h2>
            <p><?php _e('When users start rating your articles, their reviews will appear here.', 'labgenz-cm'); ?></p>
        </div>
    <?php else: ?>
        <form method="post" id="mlmmc-reviews-form">
            <?php wp_nonce_field('mlmmc_reviews_action', 'mlmmc_reviews_nonce'); ?>
            
            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <label for="bulk-action-selector-top" class="screen-reader-text"><?php _e('Select bulk action', 'labgenz-cm'); ?></label>
                    <select name="action" id="bulk-action-selector-top">
                        <option value="-1"><?php _e('Bulk Actions', 'labgenz-cm'); ?></option>
                        <option value="delete"><?php _e('Delete', 'labgenz-cm'); ?></option>
                    </select>
                    <input type="submit" id="doaction" class="button action" value="<?php _e('Apply', 'labgenz-cm'); ?>">
                </div>
                
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php printf(
                            _n('%s item', '%s items', count($reviews_data), 'labgenz-cm'),
                            number_format_i18n(count($reviews_data))
                        ); ?>
                    </span>
                </div>
                <br class="clear">
            </div>
            
            <table class="wp-list-table widefat fixed striped reviews-table">
                <thead>
                    <tr>
                        <td id="cb" class="manage-column column-cb check-column">
                            <input id="cb-select-all-1" type="checkbox">
                        </td>
                        <th class="manage-column column-article"><?php _e('Article', 'labgenz-cm'); ?></th>
                        <th class="manage-column column-user"><?php _e('User', 'labgenz-cm'); ?></th>
                        <th class="manage-column column-rating"><?php _e('Rating', 'labgenz-cm'); ?></th>
                        <th class="manage-column column-actions"><?php _e('Actions', 'labgenz-cm'); ?></th>
                    </tr>
                </thead>
                
                <tbody>
                    <?php foreach ($reviews_data as $review): ?>
                        <tr id="review-<?php echo esc_attr($review['review_id']); ?>">
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="review_ids[]" value="<?php echo esc_attr($review['review_id']); ?>">
                            </th>
                            <td class="column-article">
                                <strong>
                                    <a href="<?php echo esc_url($review['article_url']); ?>" target="_blank" data-article-id="<?php echo esc_attr($review['post_id']); ?>">
                                        <?php echo esc_html($review['article_title']); ?>
                                    </a>
                                </strong>
                                <div class="row-actions">
                                    <span class="edit">
                                        <a href="<?php echo esc_url(admin_url('post.php?post=' . $review['post_id'] . '&action=edit')); ?>">
                                            <?php _e('Edit Article', 'labgenz-cm'); ?>
                                        </a>
                                    </span>
                                </div>
                            </td>
                            <td class="column-user">
                                <div class="user-info">
                                    <?php if ($review['user_type'] === 'registered'): ?>
                                        <span class="dashicons dashicons-admin-users"></span>
                                    <?php else: ?>
                                        <span class="dashicons dashicons-visibility"></span>
                                    <?php endif; ?>
                                    
                                    <div>
                                        <strong><?php echo esc_html($review['user_name']); ?></strong>
                                        <?php if ($review['user_type'] === 'registered'): ?>
                                            <span class="user-email"><?php echo esc_html($review['user_email']); ?></span>
                                        <?php else: ?>
                                            <em class="user-guest"><?php _e('Guest User', 'labgenz-cm'); ?></em>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="column-rating">
                                <div class="star-rating">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="star <?php echo $i <= $review['rating'] ? 'active' : ''; ?>"></span>
                                    <?php endfor; ?>
                                </div>
                            </td>
                            <td class="column-actions">
                                <button type="button" class="button delete-review" 
                                    data-post-id="<?php echo esc_attr($review['post_id']); ?>"
                                    data-user-identifier="<?php echo esc_attr($review['user_identifier']); ?>"
                                    data-nonce="<?php echo esc_attr(wp_create_nonce('mlmmc_reviews_action')); ?>">
                                    <?php _e('Delete', 'labgenz-cm'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                
                <tfoot>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input id="cb-select-all-2" type="checkbox">
                        </td>
                        <th class="manage-column column-article"><?php _e('Article', 'labgenz-cm'); ?></th>
                        <th class="manage-column column-user"><?php _e('User', 'labgenz-cm'); ?></th>
                        <th class="manage-column column-rating"><?php _e('Rating', 'labgenz-cm'); ?></th>
                        <th class="manage-column column-actions"><?php _e('Actions', 'labgenz-cm'); ?></th>
                    </tr>
                </tfoot>
            </table>
            
            <div class="tablenav bottom">
                <div class="alignleft actions bulkactions">
                    <label for="bulk-action-selector-bottom" class="screen-reader-text"><?php _e('Select bulk action', 'labgenz-cm'); ?></label>
                    <select name="action" id="bulk-action-selector-bottom">
                        <option value="-1"><?php _e('Bulk Actions', 'labgenz-cm'); ?></option>
                        <option value="delete"><?php _e('Delete', 'labgenz-cm'); ?></option>
                    </select>
                    <input type="submit" id="doaction2" class="button action" value="<?php _e('Apply', 'labgenz-cm'); ?>">
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>
