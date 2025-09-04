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

    // Display the articles sidebar
    ArticlesHandler::render_articles_sidebar();
    ?>
</aside>
</aside>


<?php
get_footer();
