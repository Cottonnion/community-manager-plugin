<?php

namespace LABGENZ_CM\Admin;

use LABGENZ_CM\Articles\DailyArticleHandler;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin page for managing daily articles
 */
class DailyArticleAdmin
{
    /**
     * Constants
     */
    private const ADMIN_PAGE_HOOK = 'mlmmc_artiicle_page_daily-article-management';
    private const POST_TYPE = 'mlmmc_artiicle';
    private const OPTION_NAME = 'mlmmc_daily_article';
    
    /**
     * Nonce actions
     */
    private const NONCE_SET_daily = 'mlmmc_set_daily_article';
    private const NONCE_CLEAR_CACHE = 'mlmmc_clear_cache';
    private const NONCE_FORCE_UPDATE = 'mlmmc_force_update';
    private const NONCE_SEARCH = 'mlmmc_search_articles';

    /**
     * Initialize admin hooks
     */
    public function init(): void
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_mlmmc_set_daily_article', [$this, 'ajax_set_daily_article']);
        add_action('wp_ajax_mlmmc_clear_daily_cache', [$this, 'ajax_clear_cache']);
        add_action('wp_ajax_mlmmc_search_articles', [$this, 'ajax_search_articles']);
    }

    /**
     * Add admin menu page
     */
    public function add_admin_menu(): void
    {
        add_submenu_page(
            'labgenz-cm',
            'Daily Article Management',
            'Daily Article',
            'manage_options',
            'daily-article-management',
            [$this, 'render_admin_page']
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook): void
    {
        if ($hook !== self::ADMIN_PAGE_HOOK) {
            return;
        }

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
    }

    /**
     * Render admin page
     */
    public function render_admin_page(): void
    {
        $this->handle_form_submissions();

        $daily_handler = new DailyArticleHandler();
        $current_daily_data = $daily_handler->get_daily_article_admin_data();

        $search_query = isset($_GET['mlmmc_article_search']) ? sanitize_text_field($_GET['mlmmc_article_search']) : '';
        $articles = $this->get_all_articles($search_query);

        $this->render_page_header();
        $this->render_current_daily_section($current_daily_data);
        $this->render_actions_section();
        $this->render_articles_section($articles, $search_query, $current_daily_data);
        $this->render_styles_and_scripts();
    }

    /**
     * Render page header
     */
    private function render_page_header(): void
    {
        ?>
        <div class="wrap">
            <h1 style="margin-bottom: 30px;">daily Article Management</h1>
            <?php settings_errors(); ?>
        <?php
    }

    /**
     * Render current daily article section
     */
    private function render_current_daily_section($current_daily_data): void
    {
        ?>
        <div class="card" style="max-width: 900px; margin: 0 auto 30px auto; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
            <h2 style="margin-top:0;">Current Daily Article</h2>
            <?php if ($current_daily_data): ?>
                <div class="current-daily-article">
                    <h3 style="margin-top:0; color:#0073aa;"><?php echo esc_html($current_daily_data['article_title']); ?></h3>
                    <div style="display:flex; flex-wrap:wrap; gap:30px;">
                        <div><strong>Article ID:</strong> <?php echo esc_html($current_daily_data['article_id']); ?></div>
                        <!-- <div><strong>day Start:</strong> <?php echo esc_html($current_daily_data['day_start']); ?></div> -->
                        <div><strong>Article Selected Date:</strong> <?php echo esc_html($current_daily_data['selected_date']); ?></div>
                        <?php if ($current_daily_data['next_update']): ?>
                            <div><strong>Next Auto Update:</strong> <?php echo esc_html(date('Y-m-d H:i:s', $current_daily_data['next_update'])); ?></div>
                        <?php endif; ?>
                    </div>
                    <p style="margin-top:15px;">
                        <a href="<?php echo esc_url($current_daily_data['article_url']); ?>" target="_blank" class="button button-primary">View Article</a>
                    </p>
                </div>
            <?php else: ?>
                <p>No current daily article selected.</p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render actions section
     */
    private function render_actions_section(): void
    {
        ?>
        <div class="card" style="max-width: 900px; margin: 0 auto 30px auto; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
            <h2 style="margin-top:0;">Actions</h2>
            <form method="post" action="" style="display: inline-block; margin-right: 10px;">
                <?php wp_nonce_field('mlmmc_clear_cache', 'mlmmc_cache_nonce'); ?>
                <input type="hidden" name="action" value="clear_cache">
                <?php submit_button('Clear daily Article Cache', 'secondary', 'submit', false); ?>
            </form>
            <form method="post" action="" style="display: inline-block;">
                <?php wp_nonce_field('mlmmc_force_update', 'mlmmc_update_nonce'); ?>
                <input type="hidden" name="action" value="force_update">
                <?php submit_button('Force daily Update', 'secondary', 'submit', false); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render articles section
     */
    private function render_articles_section($articles, $search_query, $current_daily_data): void
    {
        ?>
        <div class="card" style="max-width: 1200px; margin: 0 auto 30px auto; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
            <h2 style="margin-top:0;">All MLM Articles</h2>
            <form id="mlmmc-article-search-form" method="get" action="" style="margin-bottom: 15px;">
                <input type="hidden" name="page" value="daily-article-management" />
                <input type="text" name="mlmmc_article_search" id="mlmmc_article_search" 
                       value="<?php echo esc_attr($search_query); ?>" 
                       placeholder="Search articles by title..." 
                       style="width: 350px; padding:8px; font-size:16px;" />
                <button type="submit" class="button" style="margin-left:10px;">Search</button>
            </form>
            <div style="overflow-x:auto;">
                <table id="mlmmc-articles-table" class="widefat fixed striped" style="min-width:1100px; font-size:15px;">
                    <thead>
                        <tr style="background:#f7f7f7;">
                            <th width="5%">ID</th>
                            <th width="40%">Title</th>
                            <th width="20%">Author</th>
                            <th width="15%">Category</th>
                            <th width="10%">Status</th>
                            <th width="10%">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="mlmmc-articles-tbody">
                        <?php $this->render_article_rows($articles, $current_daily_data); ?>
                    </tbody>
                </table>
            </div>
        </div>
        </div>
        <?php
    }

    /**
     * Render article table rows
     */
    private function render_article_rows($articles, $current_daily_data): void
    {
        foreach ($articles as $article) {
            $author_name = $this->get_article_author($article);
            $category_names = $this->get_article_category($article);
            $is_current = $current_daily_data && $current_daily_data['article_id'] == $article->ID;
            ?>
            <tr <?php echo $is_current ? 'class="current-daily"' : ''; ?>>
                <td><?php echo esc_html($article->ID); ?></td>
                <td><?php echo esc_html($article->post_title); ?></td>
                <td><?php echo esc_html($author_name); ?></td>
                <td><?php echo esc_html($category_names); ?></td>
                <td><?php echo esc_html(ucfirst($article->post_status)); ?></td>
                <td>
                    <a href="<?php echo esc_url(get_permalink($article->ID)); ?>" target="_blank" class="button" style="padding:2px 10px;">View</a>
                    <?php if (!$is_current): ?>
                        | <a href="#" class="set-daily-link" data-article-id="<?php echo esc_attr($article->ID); ?>" style="color:#0073aa;">Set daily</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php
        }
    }

    /**
     * Get article author name
     */
    public function get_article_author($article): string
    {

        if(is_object($article)) {
            $article_id = $article->ID;
        } else {
            $article_id = $article;
        }

        $author_name = get_post_meta($article_id, 'mlmmc_article_author', true);
        if (!$author_name) {
            $author_name = get_the_author_meta('display_name', $article->post_author);
        }
        return $author_name ?: 'Unknown';
    }

    /**
     * Get article category
     */
    public function get_article_category($article): string
    {
        if (is_object($article)) {
            $article_id = $article->ID;
        } else {
            $article_id = $article;
        }
        $category_names = get_post_meta($article_id, 'mlmmc_article_category', true);
        return $category_names ?: 'None';
    }


    public function get_article_author_image_url($article): string
    {
        if (is_object($article)) {
            $article_id = $article->ID;
        } else {
            $article_id = $article;
        }

        // Retrieve the author photo ID
        $author_image_id = get_post_meta($article_id, 'mlmmc_author_photo', true);

        // Get the image URL
        $author_image_url = wp_get_attachment_image_url($author_image_id, 'thumbnail');

        return $author_image_url ?: '';
    }


    /**
     * Render styles and scripts
     */
    private function render_styles_and_scripts(): void
    {
        ?>
        <style>
        .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 24px;
            margin: 30px 0;
        }
        .current-daily-article {
            background: #eaf6ff;
            border: 1px solid #b3d8ff;
            border-radius: 6px;
            padding: 18px;
            margin: 10px 0;
        }
        .current-daily {
            background: #f0fff0 !important;
        }
        .form-table th {
            width: 180px;
        }
        .set-daily-link {
            cursor: pointer;
            color: #0073aa;
            font-weight: 500;
        }
        .set-daily-link:hover {
            color: #005a87;
            text-decoration: underline;
        }
        @media (max-width: 900px) {
            .card { max-width: 100% !important; }
            table.widefat { font-size:14px; }
        }
        </style>
        <script>
            jQuery(document).ready(function($) {
                $('.set-daily-link').on('click', function(e) {
                    e.preventDefault();
                    var articleId = $(this).data('article-id');
                    
                    // Show confirmation dialog before setting daily article
                    Swal.fire({
                        title: 'Set daily Article?',
                        text: 'Are you sure you want to set this article as the daily article?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, set it!',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Show loading state
                            Swal.fire({
                                title: 'Updating...',
                                text: 'Setting daily article...',
                                icon: 'info',
                                allowOutsideClick: false,
                                allowEscapeKey: false,
                                showConfirmButton: false,
                                willOpen: () => {
                                    Swal.showLoading();
                                }
                            });
                            
                            $.post(ajaxurl, {
                                action: 'mlmmc_set_daily_article',
                                article_id: articleId,
                                nonce: '<?php echo wp_create_nonce(self::NONCE_SET_daily); ?>'
                            }, function(response) {
                                if (response.success) {
                                    Swal.fire({
                                        title: 'Success!',
                                        text: 'daily article updated successfully!',
                                        icon: 'success',
                                        confirmButtonText: 'OK'
                                    }).then(() => {
                                        location.reload();
                                    });
                                } else {
                                    Swal.fire({
                                        title: 'Error!',
                                        text: 'Error: ' + response.data,
                                        icon: 'error',
                                        confirmButtonText: 'OK'
                                    });
                                }
                            }).fail(function() {
                                Swal.fire({
                                    title: 'Error!',
                                    text: 'An unexpected error occurred. Please try again.',
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                            });
                        }
                    });
                });

                $('#mlmmc-article-search-form').on('submit', function(e) {
                    e.preventDefault();
                    var searchVal = $('#mlmmc_article_search').val();
                    
                    // Show searching state
                    $('#mlmmc-articles-tbody').html('<tr><td colspan="6">Searching...</td></tr>');
                    
                    $.post(ajaxurl, {
                        action: 'mlmmc_search_articles',
                        search: searchVal,
                        nonce: '<?php echo wp_create_nonce(self::NONCE_SEARCH); ?>'
                    }, function(response) {
                        if (response.success) {
                            $('#mlmmc-articles-tbody').html(response.data.html);
                        } else {
                            $('#mlmmc-articles-tbody').html('<tr><td colspan="6">No articles found.</td></tr>');
                            
                            // Show error message with SweetAlert
                            Swal.fire({
                                title: 'No Results',
                                text: 'No articles found matching your search criteria.',
                                icon: 'info',
                                confirmButtonText: 'OK'
                            });
                        }
                    }).fail(function() {
                        $('#mlmmc-articles-tbody').html('<tr><td colspan="6">Search failed. Please try again.</td></tr>');
                        
                        // Show error message with SweetAlert
                        Swal.fire({
                            title: 'Search Error',
                            text: 'An error occurred while searching. Please try again.',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * Handle form submissions
     */
    private function handle_form_submissions(): void
    {
        if (!isset($_POST['action'])) {
            return;
        }

        switch ($_POST['action']) {
            case 'set_daily_article':
                $this->handle_set_daily_article();
                break;
            case 'clear_cache':
                $this->handle_clear_cache();
                break;
            case 'force_update':
                $this->handle_force_update();
                break;
        }
    }

    /**
     * Handle set daily article form submission
     */
    private function handle_set_daily_article(): void
    {
        if (!$this->verify_nonce_and_capability('mlmmc_nonce', self::NONCE_SET_daily)) {
            return;
        }

        $article_id = intval($_POST['article_id']);
        if ($article_id) {
            $this->set_daily_article($article_id);
            $this->clear_wp_rocket_cache();
            add_settings_error('mlmmc_daily_article', 'success', 'daily article updated successfully.', 'updated');
        } else {
            add_settings_error('mlmmc_daily_article', 'error', 'Please select an article.', 'error');
        }
    }

    /**
     * Handle clear cache form submission
     */
    private function handle_clear_cache(): void
    {
        if (!$this->verify_nonce_and_capability('mlmmc_cache_nonce', self::NONCE_CLEAR_CACHE)) {
            return;
        }

        $daily_handler = new DailyArticleHandler();
        $daily_handler->clear_cache();
        $this->clear_wp_rocket_cache();
        add_settings_error('mlmmc_daily_article', 'success', 'Cache cleared successfully.', 'updated');
    }

    /**
     * Handle force update form submission
     */
    private function handle_force_update(): void
    {
        if (!$this->verify_nonce_and_capability('mlmmc_update_nonce', self::NONCE_FORCE_UPDATE)) {
            return;
        }

        $daily_handler = new DailyArticleHandler();
        $daily_handler->manual_update_daily_article();
        $this->clear_wp_rocket_cache();
        add_settings_error('mlmmc_daily_article', 'success', 'daily article updated successfully.', 'updated');
    }

    /**
     * Verify nonce and user capability
     */
    private function verify_nonce_and_capability(string $nonce_field, string $nonce_action): bool
    {
        if (!isset($_POST[$nonce_field]) || !wp_verify_nonce($_POST[$nonce_field], $nonce_action)) {
            add_settings_error('mlmmc_daily_article', 'nonce_error', 'Security check failed.', 'error');
            return false;
        }

        if (!current_user_can('manage_options')) {
            add_settings_error('mlmmc_daily_article', 'capability_error', 'Insufficient permissions.', 'error');
            return false;
        }

        return true;
    }

    /**
     * Clear WP Rocket cache if available
     */
    private function clear_wp_rocket_cache(): void
    {
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
    }

    /**
     * Set daily article via AJAX
     */
    public function ajax_set_daily_article(): void
    {
        if (!wp_verify_nonce($_POST['nonce'], self::NONCE_SET_daily)) {
            wp_send_json_error('Security check failed.');
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
        }

        $article_id = intval($_POST['article_id']);
        if (!$article_id) {
            wp_send_json_error('Invalid article ID.');
        }

        $this->set_daily_article($article_id);
        $this->clear_wp_rocket_cache();
        wp_send_json_success('daily article updated successfully.');
    }

    /**
     * Clear cache via AJAX
     */
    public function ajax_clear_cache(): void
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], self::NONCE_CLEAR_CACHE)) {
            wp_send_json_error('Security check failed.');
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
        }

        $daily_handler = new DailyArticleHandler();
        $daily_handler->clear_cache();
        $this->clear_wp_rocket_cache();
        wp_send_json_success('Cache cleared successfully.');
    }

    /**
     * Search articles via AJAX
     */
    public function ajax_search_articles(): void
    {
        check_ajax_referer(self::NONCE_SEARCH, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
        }

        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $articles = $this->get_all_articles($search);

        $daily_handler = new DailyArticleHandler();
        $current_daily_data = $daily_handler->get_daily_article_admin_data();

        ob_start();
        if ($articles) {
            $this->render_article_rows($articles, $current_daily_data);
        } else {
            echo '<tr><td colspan="6">No articles found.</td></tr>';
        }
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }

    /**
     * Set the daily article
     */
    private function set_daily_article(int $article_id): void
    {
        $article = get_post($article_id);
        if (!$article || $article->post_type !== self::POST_TYPE || $article->post_status !== 'publish') {
            return;
        }

        $daily_handler = new DailyArticleHandler();
        $article_data = [
            'article_id' => $article_id,
            'day_start' => $daily_handler->get_current_day_start(),
            'selected_date' => current_time('Y-m-d H:i:s')
        ];

        update_option(self::OPTION_NAME, $article_data);
        $this->clear_wp_rocket_cache();
    }

    /**
     * Get all MLM articles
     */
    private function get_all_articles(string $search_query = ''): array
    {
        $args = [
            'post_type' => self::POST_TYPE,
            'post_status' => ['publish', 'draft'],
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'meta_query' => [
                [
                    'key' => 'mlmmc_article_category',
                    'compare' => 'EXISTS'
                ]
            ]
        ];

        if ($search_query) {
            $args['s'] = $search_query;
        }

        $query = new \WP_Query($args);
        return $query->posts;
    }
}