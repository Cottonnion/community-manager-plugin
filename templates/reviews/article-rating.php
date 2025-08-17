<?php
/**
 * Template for displaying article ratings
 *
 * @package Labgenz_CM
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get rating data
$average_rating = $this->get_average_rating($post_id);
$rating_count = $this->get_rating_count($post_id);
$user_has_rated = $this->has_user_rated($post_id);
$user_rating = $this->get_user_rating($post_id);

// Generate CSS classes
$container_classes = 'article-rating-container';
if (!empty($args['class'])) {
    $container_classes .= ' ' . esc_attr($args['class']);
}
?>

<div class="<?php echo $container_classes; ?>" data-post-id="<?php echo esc_attr($post_id); ?>">
    <h3 class="article-rating-title"><?php _e('Rate This Article', 'labgenz-cm'); ?></h3>
    
    <?php if (!$user_has_rated) : ?>
    <form class="article-rating-form" data-mode="new">
        <div class="article-rating-stars">
            <?php for ($i = 1; $i <= 5; $i++) : ?>
            <span class="star" data-rating="<?php echo $i; ?>" title="<?php printf(__('%d Star', 'labgenz-cm'), $i); ?>"></span>
            <?php endfor; ?>
        </div>
        
        <button type="submit" class="article-rating-submit" disabled><?php _e('Submit Rating', 'labgenz-cm'); ?></button>
        <div class="article-rating-message" style="display: none;"></div>
    </form>
    
    <div class="article-rated-message hidden">
        <?php _e('Thank you for rating!', 'labgenz-cm'); ?>
    </div>
    <?php else : ?>
    <div class="article-rated-message">
        <?php printf(__('You rated this article %d star(s).', 'labgenz-cm'), $user_rating); ?>
        <a href="#" class="edit-rating-link"><?php _e('Edit my rating', 'labgenz-cm'); ?></a>
    </div>
    
    <form class="article-rating-form edit-form hidden" data-mode="edit">
        <div class="article-rating-stars">
            <?php for ($i = 1; $i <= 5; $i++) : 
                $class = 'star';
                if ($i <= $user_rating) {
                    $class .= ' active';
                }
            ?>
            <span class="<?php echo esc_attr($class); ?>" data-rating="<?php echo $i; ?>" title="<?php printf(__('%d Star', 'labgenz-cm'), $i); ?>"></span>
            <?php endfor; ?>
        </div>
        
        <button type="submit" class="article-rating-submit"><?php _e('Update Rating', 'labgenz-cm'); ?></button>
        <button type="button" class="article-rating-cancel"><?php _e('Cancel', 'labgenz-cm'); ?></button>
        <div class="article-rating-message" style="display: none;"></div>
    </form>
    <?php endif; ?>
    
    <?php if ($average_rating > 0) : ?>
    <div class="article-rating-average">
        <div class="article-rating-average-stars">
            <?php 
            // Display average rating stars
            for ($i = 1; $i <= 5; $i++) {
                $class = 'star';
                if ($i <= floor($average_rating)) {
                    $class .= ' active';
                } elseif ($i == ceil($average_rating) && ($average_rating - floor($average_rating)) >= 0.25) {
                    $class .= ' half-active';
                }
                echo '<span class="' . esc_attr($class) . '"></span>';
            }
            ?>
        </div>
        
        <?php if ($args['show_count']) : ?>
        <span class="article-rating-average-count"><?php 
            printf(
                _n('(%d rating)', '(%d ratings)', $rating_count, 'labgenz-cm'),
                $rating_count
            ); 
        ?></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
