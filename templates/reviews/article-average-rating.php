<?php
/**
 * Template for displaying the average rating and total review count for an article
 *
 * @package Labgenz_CM
 */

use LABGENZ_CM\Articles\ReviewsHandler;

if (!defined('ABSPATH')) {
    exit;
}

// Accept $post_id as a variable or fallback to get_the_ID()
$post_id = isset($post_id) ? $post_id : get_the_ID();

$reviews_handler = new ReviewsHandler();
$average_rating = $reviews_handler->get_average_rating($post_id);
$rating_count = $reviews_handler->get_rating_count($post_id);

if ($rating_count > 0) : ?>
<div class="article-average-rating-summary">
    <span class="average-rating-value">
        <?php echo number_format($average_rating, 1); ?>
    </span>
    <span class="average-rating-stars">
        <?php
        // Show stars for the average
        for ($i = 1; $i <= 5; $i++) {
            if ($i <= floor($average_rating)) {
                echo '<span class="star active">★</span>';
            } elseif ($i == ceil($average_rating) && ($average_rating - floor($average_rating)) >= 0.25) {
                echo '<span class="star half-active">★</span>';
            } else {
                echo '<span class="star">★</span>';
            }
        }
        ?>
    </span>
    <span class="average-rating-count">
        <?php printf(_n('(%d review)', '(%d reviews)', $rating_count, 'labgenz-cm'), $rating_count); ?>
    </span>
</div>
<?php else : ?>
<div class="article-average-rating-summary no-reviews">
    <?php _e('No reviews yet.', 'labgenz-cm'); ?>
</div>
<?php endif; ?>
