<?php
/**
 * Template for displaying enhanced article header with author info and metadata
 *
 * @package Labgenz_CM
 */

namespace LABGENZ_CM\Articles;

use LABGENZ_CM\Admin\DailyArticleAdmin;
use LABGENZ_CM\Articles\ArticlesHandler;
use LABGENZ_CM\Articles\Authors\AuthorDisplayHandler;

if (!defined('ABSPATH')) {
    exit;
}

$post_id   = isset($post_id) ? $post_id : get_the_ID();
$post_type = get_post_type($post_id);

if ($post_type !== ReviewsHandler::POST_TYPE) {
    return;
}

$daily_article_admin = new DailyArticleAdmin();
$article_handler     = new ArticlesHandler();
$author_handler      = new AuthorDisplayHandler();

$author        = $daily_article_admin->get_article_author($post_id);
$category      = $daily_article_admin->get_article_category($post_id);
$author_avatar = $daily_article_admin->get_article_author_image_url($post_id);
$author_url    = $author_handler->get_author_url($post_id);
$publish_date  = get_the_date('F j, Y', $post_id);
$article_title = get_the_title($post_id);


// Ratings
$average = null;
$count   = 0;
if (class_exists('LABGENZ_CM\\Articles\\ReviewsHandler')) {
    $reviews_handler = new \LABGENZ_CM\Articles\ReviewsHandler();
    $average         = $reviews_handler->get_average_rating($post_id);
    $count           = $reviews_handler->get_rating_count($post_id);
}
?>
<header class="article-header">
    <div class="article-header-content">

        <!-- Title -->
        <h1 class="article-title"><?php echo esc_html($article_title); ?></h1>

        <!-- Meta -->
        <div class="article-meta">

            <!-- Author -->
            <div class="author-info">
                <?php if ($author_avatar): ?>
                    <img src="<?php echo esc_url($author_avatar); ?>"
                         alt="<?php echo esc_attr($author); ?>"
                         class="author-avatar">
                <?php endif; ?>

                <div class="author-details">
                    <a href="<?= $author_url; ?>">
                        <h2 class="author-name">
                            <span class="by-text">By</span> <?php echo esc_html($author); ?>
                        </h2>
                    </a>
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
                        <?php if ($average !== null && $count > 0): ?>
                            <span class="article-rating">
                                • <span class="average-rating-stars" data-this-mean="Average Rating of This Article.">
                                    <?php
                                    $full_stars = floor($average);
                                    $half_star  = ($average - $full_stars >= 0.5);
                                    $empty_stars = 5 - $full_stars - ($half_star ? 1 : 0);

                                    // full stars
                                    for ($i = 0; $i < $full_stars; $i++) {
                                        echo '<span class="star" style="color:#f1c40f;font-size:1.1em;">&#9733;</span>';
                                    }

                                    // half star
                                    if ($half_star) {
                                        echo '<span class="star" style="color:#f1c40f;font-size:1.1em;">&#189;</span>'; 
                                        // could replace with SVG half star if you prefer
                                    }

                                    // empty stars
                                    for ($i = 0; $i < $empty_stars; $i++) {
                                        echo '<span class="star" style="color:#ccc;font-size:1.1em;">&#9733;</span>';
                                    }
                                    ?>
                                </span>

                                <?php if ($count >= 10): ?>
                                    <span class="average-rating-count" style="color:#888; margin-left:4px;">
                                        <?php printf(_n('(%d review)', '(%d reviews)', $count, 'labgenz-cm'), $count); ?>
                                    </span>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>

                    </div>
                </div>
            </div>

        </div>
    </div>
    <?php do_action('labgenz_cm_after_article_header', $post_id); ?>
</header>
