<?php

namespace LABGENZ_CM\Articles;

use LABGENZ_CM\Subscriptions\SubscriptionHandler;


// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get the post type constant from the ArticlesHandler class
$post_type = ArticlesHandler::POST_TYPE;

// Check if the current post type matches the desired post type
if (get_post_type() !== $post_type) {
    return;
}

get_header();
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">

        <?php
        while (have_posts()) :
            the_post();

            // Display the content of the post
            the_content();
        endwhile;
        ?>

    </main><!-- #main -->
</div><!-- #primary -->

<aside id="secondary" class="widget-area">
    <?php
    // Display sidebar if active
    if (is_active_sidebar('mlmmc-article-sidebar')) {
        dynamic_sidebar('mlmmc-article-sidebar');
    }

    // Get random articles
    $daily_article_handler = new DailyArticleHandler();
    $random_articles = $daily_article_handler->get_random_articles('mixed', 9, 10);

    if (!empty($random_articles)) :
    ?>
        <div class="random-articles" style="margin-top: 30px;">
            <h3 style="margin-bottom: 15px;">Other Articles</h3>

            <?php if (!SubscriptionHandler::get_instance()->user_has_resource_access(get_current_user_id(), 'can_view_mlm_articles')) : ?>
                <div class="bb-button-wrapper" style="margin: 20px 0; text-align: center;">
                    <button onclick="location.href='<?php echo esc_url(SubscriptionHandler::get_article_upsell_url()); ?>'" class="bb-button bb-button--primary">
                        Get Access to ALL MLM Articles
                    </button>
                </div>
            <?php endif; ?>

            <ul class="random-articles-list" style="list-style: none; padding: 0; margin: 0;">
                <?php foreach ($random_articles as $article) :
                    $category_name = !empty($article['category']) ? $article['category'] : 'Uncategorized';
                    $rating = $article['avg_rating'];
                ?>
                    <li style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px;">
                        <a href="<?php echo esc_url($article['link']); ?>" style="font-weight: bold; font-size: 16px; text-decoration: none; color: #333;">
                            <?php echo esc_html($article['title']); ?>
                        </a>
                        <p style="margin: 5px 0; font-size: 14px; color: #666;">Category: <?php echo esc_html($category_name); ?></p>

                        <?php if ($article['has_video']) : ?>
                            <span style="display: inline-block; background-color: #28a745; color: white; padding: 3px 8px; font-size: 12px; border-radius: 3px; margin-bottom: 5px;">
                                Has Video
                            </span>
                        <?php endif; ?>

                        <div style="margin-top: 5px; font-size: 14px; color: #444;">
                        <?php if ($rating !== null) : ?>
                            <div style="margin-top: 5px; font-size: 14px; color: #444;">
                                <?php
                                $max_stars = 5;
                                $filled    = floor($rating);
                                $half      = ($rating - $filled >= 0.5) ? 1 : 0;
                                $empty     = $max_stars - $filled - $half;

                                echo '<span style="color: #f5c518;">' . str_repeat('★', $filled) . '</span>';
                                if ($half) echo '<span style="color: #f5c518;">☆</span>';
                                echo str_repeat('☆', $empty);
                                echo ' (' . number_format($rating, 1) . ')';
                                ?>
                            </div>
                        <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</aside>


<?php
get_footer();
