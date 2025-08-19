<?php
/**
 * Template for displaying enhanced article header with author info and metadata
 *
 * @package Labgenz_CM
 */

namespace LABGENZ_CM\Articles;

use LABGENZ_CM\Admin\DailyArticleAdmin;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$post_id = isset($post_id) ? $post_id : get_the_ID();

// Get the post type to ensure it's an article
$post_type = get_post_type($post_id);
if ($post_type !== ReviewsHandler::POST_TYPE) {
    return;
}

$daily_article_admin = new DailyArticleAdmin();
$author = $daily_article_admin->get_article_author($post_id);
$category = $daily_article_admin->get_article_category($post_id);
$author_avatar = $daily_article_admin->get_article_author_image_url($post_id);

// Get additional article metadata
$publish_date = get_the_date('F j, Y', $post_id);
// $reading_time = $daily_article_admin->get_estimated_reading_time($post_id);
$article_title = get_the_title($post_id);

?>
<header class="article-header">
    <div class="article-header-content">
        
        <!-- Article Title -->
        <h1 class="article-title"><?php echo esc_html($article_title); ?></h1>
        
        <!-- Article Meta Information -->
        <div class="article-meta">
            
            <!-- Author Section -->
            <div class="author-info">
                <?php if ($author_avatar): ?>
                    <img src="<?php echo esc_url($author_avatar); ?>" 
                         alt="<?php echo esc_attr($author); ?>" 
                         class="author-avatar">
                <?php endif; ?>
                
                <div class="author-details">
                    <h2 class="author-name">
                        <span class="by-text">By</span> 
                        <?php echo esc_html($author); ?>
                    </h2>
                    
                    <div class="article-metadata">
                        <span class="publish-date">
                            <time datetime="<?php echo esc_attr(get_the_date('c', $post_id)); ?>">
                                <?php echo esc_html($publish_date); ?>
                            </time>
                        </span>
                        
                        <?php if ($category): ?>
                            <span class="article-category">
                                • <span class="category-label"><?php echo esc_html($category); ?></span>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php include '/reviews/article-average-rating.php'; // Include average rating template ?>
        </div>
    </div>
    <?php do_action('labgenz_cm_after_article_header', $post_id); ?>
<?php
// Show the article average rating directly under the header
if (class_exists('LABGENZ_CM\\Articles\\ReviewsHandler')) {
    $reviews_handler = new \LABGENZ_CM\Articles\ReviewsHandler();
    $average = $reviews_handler->get_average_rating($post_id);

    if ($average === null) {
        return;
    }
    $count = $reviews_handler->get_rating_count($post_id);
    if ($count > 0) : ?>
        <div class="article-average-rating" style="margin: 16px 0 0 0;">
            <span class="average-rating-label">
                <?php echo esc_html(number_format($average, 1)); ?>
                <span class="star" style="color: #f1c40f; font-size: 1.2em;">&#9733;</span>
            </span>
            <span class="average-rating-count" style="color: #888; margin-left: 8px;">
                <?php printf(_n('(%d review)', '(%d reviews)', $count, 'labgenz-cm'), $count); ?>
            </span>
        </div>
    <?php endif;
}
?>
</header>

