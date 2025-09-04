<?php
/**
 * Template for Authors Archive
 *
 * @package Labgenz_Community_Management
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

get_header();

// Get page title
$archive_title = apply_filters('mlmmc_author_archive_title', __('Meet Our Authors', 'labgenz-community-management'));
?>

<div class="mlmmc-authors-archive">
    <div class="container">
        <h1 class="mlmmc-authors-archive-title"><?php echo esc_html($archive_title); ?></h1>
        
        <?php if (have_posts()) : ?>
            <div class="mlmmc-authors-grid">
                <?php while (have_posts()) : the_post(); 
                    $author_id = get_the_ID();
                    $author_name = get_post_meta($author_id, 'mlmmc_article_author', true) ?: get_the_title();
                    $author_title = get_post_meta($author_id, 'product_creator_title', true) ?: '';
                    $author_bio = get_post_meta($author_id, 'mlmmc_author_bio', true);
                    $bio_excerpt = wp_trim_words($author_bio, 20, '...');
                    
                    // Get article count and author rating
                    $article_count = 0;
                    $author_rating = 0;
                    // $rated_count = 0;
                    
                    if ($author_name) {
                        // Use ReviewsHandler to get author stats
                        $reviews_handler = new \LABGENZ_CM\Articles\ReviewsHandler();
                        $author_stats = $reviews_handler->get_author_average_rating($author_name);
                        
                        $article_count = $author_stats['article_count'];
                        $author_rating = $author_stats['average'];
                        // $rated_count = $author_stats['rated_count'];
                    }
                ?>
                    <div class="mlmmc-author-card">
                        <div class="mlmmc-author-header">
                            <div class="mlmmc-author-avatar">
                                <?php if (has_post_thumbnail()) {
                                    // Use 'thumbnail' size for avatar display
                                    $image_id = get_post_thumbnail_id();
                                    $image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
                                    echo '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($author_name) . '">';
                                } else {
                                    // If no image, display initials
                                    $initials = '';
                                    $name_parts = explode(' ', $author_name);
                                    foreach ($name_parts as $part) {
                                        if (!empty($part)) {
                                            $initials .= strtoupper(substr($part, 0, 1));
                                            if (strlen($initials) >= 2) break;
                                        }
                                    }
                                    echo '<div class="mlmmc-author-initials">' . esc_html($initials) . '</div>';
                                } ?>
                            </div>
                            <div class="mlmmc-author-info">
                                <h3><?php echo esc_html($author_name); ?></h3>
                                <?php if (!empty($author_title)) : ?>
                                    <p class="mlmmc-author-role"><?php echo esc_html($author_title); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($bio_excerpt)) : ?>
                            <p class="mlmmc-author-bio"><?php echo esc_html($bio_excerpt); ?></p>
                        <?php endif; ?>
                        
                        <div class="mlmmc-author-stats">
                            <div class="mlmmc-stat">
                                <span class="mlmmc-stat-number"><?php echo esc_html($article_count); ?></span>
                                <span class="mlmmc-stat-label"><?php echo esc_html(_n('Article', 'Articles', $article_count, 'labgenz-community-management')); ?></span>
                            </div>
                            <?php if ($author_rating > 0) : ?>
                            <div class="mlmmc-stat">
                                <span class="mlmmc-stat-number"><?php echo esc_html(number_format($author_rating, 1)); ?></span>
                                <span class="mlmmc-stat-label"><?php esc_html_e('Rating', 'labgenz-community-management'); ?></span>
                            </div>
                            <?php endif; ?>
                        </div> <!-- Closing the stats wrapper -->

                        <button onclick="location.href='<?php the_permalink(); ?>'" class="mlmmc-view-profile-btn">
                            <?php esc_html_e('View Profile', 'labgenz-community-management'); ?>
                        </button>
                    </div>
                <?php endwhile; ?>
            </div>
            
            <div class="mlmmc-authors-pagination">
                <?php the_posts_pagination([
                    'mid_size'  => 2,
                    'prev_text' => __('&laquo; Previous', 'labgenz-community-management'),
                    'next_text' => __('Next &raquo;', 'labgenz-community-management'),
                ]); ?>
            </div>
        <?php else : ?>
            <div class="mlmmc-no-authors">
                <p><?php esc_html_e('No authors found.', 'labgenz-community-management'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php get_footer(); ?>
