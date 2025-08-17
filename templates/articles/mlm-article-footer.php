<?php
/**
 * Template for displaying article download button
 *
 * @package Labgenz_CM
 */

namespace LABGENZ_CM\Articles;

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