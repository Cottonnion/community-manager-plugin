<?php
if (!defined('ABSPATH')) exit;

$video_link = get_post_meta($post_id, 'mlmmc_video_link', true);
if (!empty($video_link)) {
    echo '<div class="video-embed" style="margin-bottom: 30px">';
    echo wp_oembed_get($video_link) ?: '<a href="' . esc_url($video_link) . '" target="_blank" rel="noopener">Watch Video</a>';
    echo '</div>';
}