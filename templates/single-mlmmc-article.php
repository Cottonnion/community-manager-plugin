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
    // Display a sample sidebar
    if (is_active_sidebar('mlmmc-article-sidebar')) {
        dynamic_sidebar('mlmmc-article-sidebar');
    }

    // Use the get_random_articles method to fetch articles
    $daily_article_handler = new DailyArticleHandler();
    $random_articles = $daily_article_handler->get_random_articles('mixed', 9, 10);
    // var_dump($random_articles);
    if (!empty($random_articles)) {

        echo '<div class="random-articles">';
        echo '<h3>Related Articles</h3>';
        if(!SubscriptionHandler::get_instance()->user_has_resource_access(get_current_user_id(), 'can_view_mlm_articles')) {
            echo '<div class="bb-button-wrapper" style="margin: 20px 0; text-align: center;">';
            echo '<button onclick="location.href=\'' . esc_url(SubscriptionHandler::get_article_upsell_url()) . '\'" class="bb-button bb-button--primary">Get Access to ALL MLM Articles</button>';
            echo '</div>';
        }
        echo '<ul class="random-articles-list" style="list-style: none; padding: 0;">';
        foreach ($random_articles as $article) {
            $category_name = !empty($article['category']) ? $article['category'] : 'Uncategorized';

            echo '<li style="margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">';
            echo '<a href="' . esc_url($article['link']) . '" style="font-weight: bold; font-size: 16px; text-decoration: none; color: #333;">' . esc_html($article['title']) . '</a>';
            echo '<p style="margin: 5px 0; font-size: 14px; color: #666;">Category: ' . esc_html($category_name) . '</p>';
            if ($article['has_video']) {
                echo '<span style="display: inline-block; background-color: #28a745; color: white; padding: 3px 8px; font-size: 12px; border-radius: 3px;">Has Video</span>';
            } 
            echo '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }
    ?>
</aside><!-- #secondary -->

<?php
get_footer();
