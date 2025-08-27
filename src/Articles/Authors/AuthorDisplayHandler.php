<?php

declare(strict_types=1);

namespace LABGENZ_CM\Articles\Authors;

defined('ABSPATH') || exit;

/**
 * Class AuthorDisplayHandler
 *
 * Handles the display logic for single author pages.
 */
class AuthorDisplayHandler {

    public function __construct() {
        // Hook into template redirect to handle single author display
        add_action('template_redirect', array($this, 'maybe_render_single_author'));
    }

    /**
     * Check if we're on a single author page and render custom output.
     */
    public function maybe_render_single_author() {
        if (is_singular(AuthorCPT::POST_TYPE)) {
            $this->render_single_author(get_queried_object_id());
            exit;
        }
    }

    /**
     * Render the single author page.
     *
     * @param int $post_id
     */
public function render_single_author(int $post_id) {
    $author_post = get_post($post_id);

    if (!$author_post) {
        wp_die(
            '<pre>Author not found.' .
            "\nPost ID: " . esc_html($post_id) .
            "\nPost Type Exists? " . (post_type_exists(AuthorCPT::POST_TYPE) ? 'Yes' : 'No') .
            "\nRegistered Post Types: " . esc_html(implode(', ', get_post_types())) .
            "\nPost Status (if any): " . esc_html(get_post_status($post_id)) .
            '</pre>'
        );
    }

    // Declare all data for the template
    $data = [
        'author_name' => get_post_meta($post_id, 'mlmmc_article_author', true) ?: $author_post->post_title,
        'bio'         => get_post_meta($post_id, 'mlmmc_author_bio', true),
        'photo_url'   => get_the_post_thumbnail_url($post_id, 'medium'),
        'post_id'     => $post_id,
        'author_post' => $author_post,
    ];

    // Use theme template if exists
    $template = LABGENZ_CM_TEMPLATES_DIR . '/authors/author-single.php';
    if (file_exists($template)) {
        get_header();
        include $template;
        get_footer();
        return;
    }

    // Default output if no template found
    get_header();
    include __DIR__ . '/templates/author-single.php'; // optional fallback
    get_footer();
}



}
