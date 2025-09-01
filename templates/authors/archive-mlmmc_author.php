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
                    
                    // Get article count
                    $article_count = 0;
                    if ($author_name) {
                        $article_query = new WP_Query([
                            'post_type'      => 'mlmmc_artiicle',
                            'posts_per_page' => -1,
                            'post_status'    => 'publish',
                            'fields'         => 'ids',
                            'meta_query'     => [
                                [
                                    'key'     => 'mlmmc_article_author',
                                    'value'   => $author_name,
                                    'compare' => 'LIKE',
                                ]
                            ]
                        ]);
                        $article_count = $article_query->found_posts;
                    }
                ?>
                    <div class="mlmmc-author-card">
                        <div class="mlmmc-author-thumbnail">
                            <a href="<?php the_permalink(); ?>">
                                <?php if (has_post_thumbnail()) {
                                    the_post_thumbnail('medium');
                                } else {
                                    // Default author image
                                    echo '<img src="' . esc_url(LABGENZ_CM_URL . 'src/Public/assets/images/default-author.png') . '" alt="' . esc_attr($author_name) . '">';
                                } ?>
                            </a>
                        </div>
                        <div class="mlmmc-author-content">
                            <h3 class="mlmmc-author-name">
                                <a href="<?php the_permalink(); ?>"><?php echo esc_html($author_name); ?></a>
                            </h3>
                            
                            <?php if (!empty($author_title)) : ?>
                                <p class="mlmmc-author-title"><?php echo esc_html($author_title); ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($bio_excerpt)) : ?>
                                <p class="mlmmc-author-bio"><?php echo esc_html($bio_excerpt); ?></p>
                            <?php endif; ?>
                            
                            <div class="mlmmc-author-meta">
                                <div class="mlmmc-author-articles-count">
                                    <i class="fas fa-file-alt"></i> 
                                    <?php echo esc_html(sprintf(_n('%d Article', '%d Articles', $article_count, 'labgenz-community-management'), $article_count)); ?>
                                </div>
                                <a href="<?php the_permalink(); ?>" class="mlmmc-author-read-more">
                                    <?php esc_html_e('View Profile', 'labgenz-community-management'); ?>
                                </a>
                            </div>
                        </div>
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
