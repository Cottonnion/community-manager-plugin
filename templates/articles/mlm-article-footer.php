<?php
/**
 * Template for displaying article download button
 *
 * @package Labgenz_CM
 */

namespace LABGENZ_CM\Articles;

use LABGENZ_CM\Admin\DailyArticleAdmin;
use LABGENZ_CM\Articles\ArticlesHandler;

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

$article_handler     = new ArticlesHandler();
$daily_article_admin = new DailyArticleAdmin();

$author_bio = $article_handler->get_article_author_bio($post_id);
$author = $daily_article_admin->get_article_author($post_id);
$category = $daily_article_admin->get_article_category($post_id);
$author_avatar = $daily_article_admin->get_article_author_image_url($post_id);
?>

            <div class="article-download-pdf-container" style="display: flex; gap: 10px; align-items: center;">
                <button class="download-article-pdf button" data-article-id="<?php echo esc_attr($post_id); ?>">
                    <span class="download-icon dashicons dashicons-pdf"></span>
                    <?php esc_html_e('Download Article as PDF', 'labgenz-cm'); ?>
                </button>
                <button class="go-back-article button" type="button" onclick="window.history.back();">
                    <span class="dashicons dashicons-arrow-left-alt"></span>
                    <?php esc_html_e('Go Back', 'labgenz-cm'); ?>
                </button>
            </div>
            <div class="article-meta" style="margin-top: 20px;">
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
                <?php if ($author_bio): ?>
                    <div class="author-bio" style="
                        border: 2px dashed #16697a;
                        padding: 12px;
                        margin-top: 8px;
                        background-color: #f9f9f9;
                        font-style: italic;
                        font-size: 0.95em;
                        position: relative;
                        margin-top: 20px;
                    ">
                        <span style="
                            position: absolute;
                            top: -10px;
                            left: 10px;
                            background: #f9f9f9;
                            padding: 0 6px;
                            font-weight: bold;
                            color: #16697a;
                            font-size: 0.85em;
                        ">Author Bio</span>
                        <?php echo wp_kses_post(wpautop($author_bio)); ?>
                    </div>
                <?php endif; ?>
                </div>