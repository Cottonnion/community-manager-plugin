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
?>
<div class="mlmmc-authors-archive">
    <h1><?php esc_html_e('Authors', 'labgenz-community-management'); ?></h1>
    <?php if (have_posts()) : ?>
        <div class="mlmmc-authors-list">
            <?php while (have_posts()) : the_post(); ?>
                <div class="mlmmc-author-card">
                    <a href="<?php the_permalink(); ?>">
                        <?php if (has_post_thumbnail()) {
                            the_post_thumbnail('thumbnail');
                        } ?>
                        <h2><?php the_title(); ?></h2>
                    </a>
                    <div class="mlmmc-author-excerpt">
                        <?php the_excerpt(); ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        <div class="mlmmc-authors-pagination">
            <?php the_posts_pagination(); ?>
        </div>
    <?php else : ?>
        <p><?php esc_html_e('No authors found.', 'labgenz-community-management'); ?></p>
    <?php endif; ?>
</div>
<?php get_footer(); ?>
