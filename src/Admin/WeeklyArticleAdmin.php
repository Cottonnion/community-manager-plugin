<?php

namespace LABGENZ_CM\Admin;

use LABGENZ_CM\Articles\WeeklyArticleHandler;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin page for managing weekly articles
 */
class WeeklyArticleAdmin {
    
    /**
     * Initialize admin hooks
     */
    public function init(): void {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_mlmmc_set_weekly_article', [$this, 'ajax_set_weekly_article']);
        add_action('wp_ajax_mlmmc_clear_weekly_cache', [$this, 'ajax_clear_cache']);
        add_action('wp_ajax_mlmmc_search_articles', [$this, 'ajax_search_articles']);
    }
    
    /**
     * Add admin menu page
     */
    public function add_admin_menu(): void {
        add_submenu_page(
            'edit.php?post_type=mlmmc_artiicle',
            'Weekly Article Management',
            'Weekly Article',
            'manage_options',
            'weekly-article-management',
            [$this, 'render_admin_page']
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook): void {
        if ($hook !== 'mlmmc_artiicle_page_weekly-article-management') {
            return;
        }
        
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page(): void {
        // Handle form submissions
        $this->handle_form_submissions();
        // Get current weekly article data
        $weekly_handler = new WeeklyArticleHandler();
        $current_weekly_data = $weekly_handler->get_weekly_article_admin_data();
        // Handle search
        $search_query = isset($_GET['mlmmc_article_search']) ? sanitize_text_field($_GET['mlmmc_article_search']) : '';
        $articles = $this->get_all_articles($search_query);
        ?>
        <div class="wrap">
            <h1 style="margin-bottom: 30px;">Weekly Article Management</h1>
            <?php settings_errors(); ?>
            <div class="card" style="max-width: 900px; margin: 0 auto 30px auto; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                <h2 style="margin-top:0;">Current Weekly Article</h2>
                <?php if ($current_weekly_data): ?>
                    <div class="current-weekly-article" style="background: #eaf6ff; border: 1px solid #b3d8ff; border-radius: 6px; padding: 18px; margin: 10px 0;">
                        <h3 style="margin-top:0; color:#0073aa;"><?php echo esc_html($current_weekly_data['article_title']); ?></h3>
                        <div style="display:flex; flex-wrap:wrap; gap:30px;">
                            <div><strong>Article ID:</strong> <?php echo esc_html($current_weekly_data['article_id']); ?></div>
                            <div><strong>Week Start:</strong> <?php echo esc_html($current_weekly_data['week_start']); ?></div>
                            <div><strong>Article Selected Date:</strong> <?php echo esc_html($current_weekly_data['selected_date']); ?></div>
                            <?php if ($current_weekly_data['next_update']): ?>
                                <div><strong>Next Auto Update:</strong> <?php echo esc_html(date('Y-m-d H:i:s', $current_weekly_data['next_update'])); ?></div>
                            <?php endif; ?>
                        </div>
                        <p style="margin-top:15px;"><a href="<?php echo esc_url($current_weekly_data['article_url']); ?>" target="_blank" class="button button-primary">View Article</a></p>
                    </div>
                <?php else: ?>
                    <p>No current weekly article selected.</p>
                <?php endif; ?>
            </div>
            <div class="card" style="max-width: 900px; margin: 0 auto 30px auto; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                <h2 style="margin-top:0;">Actions</h2>
                <form method="post" action="" style="display: inline-block; margin-right: 10px;">
                    <?php wp_nonce_field('mlmmc_clear_cache', 'mlmmc_cache_nonce'); ?>
                    <input type="hidden" name="action" value="clear_cache">
                    <?php submit_button('Clear Weekly Article Cache', 'secondary', 'submit', false); ?>
                </form>
                <form method="post" action="" style="display: inline-block;">
                    <?php wp_nonce_field('mlmmc_force_update', 'mlmmc_update_nonce'); ?>
                    <input type="hidden" name="action" value="force_update">
                    <?php submit_button('Force Weekly Update', 'secondary', 'submit', false); ?>
                </form>
            </div>
            <div class="card" style="max-width: 1200px; margin: 0 auto 30px auto; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                <h2 style="margin-top:0;">All MLM Articles</h2>
                <form id="mlmmc-article-search-form" method="get" action="" style="margin-bottom: 15px;">
                    <input type="hidden" name="page" value="weekly-article-management" />
                    <input type="text" name="mlmmc_article_search" id="mlmmc_article_search" value="<?php echo esc_attr($search_query); ?>" placeholder="Search articles by title..." style="width: 350px; padding:8px; font-size:16px;" />
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
                        <?php foreach ($articles as $article): 
                            $author_name = get_post_meta($article->ID, 'mlmmc_article_author', true);
                            if (!$author_name) {
                                $author_name = get_the_author_meta('display_name', $article->post_author);
                            }
                            // Get category from postmeta instead of taxonomy
                            $category_names = get_post_meta($article->ID, 'mlmmc_article_category', true);
                            if (!$category_names) {
                                $category_names = 'None';
                            }
                            $is_current = $current_weekly_data && $current_weekly_data['article_id'] == $article->ID;
                        ?>
                            <tr <?php echo $is_current ? 'class="current-weekly"' : ''; ?> >
                                <td><?php echo esc_html($article->ID); ?></td>
                                <td><?php echo esc_html($article->post_title); ?></td>
                                <td><?php echo esc_html($author_name); ?></td>
                                <td><?php echo esc_html($category_names); ?></td>
                                <td><?php echo esc_html(ucfirst($article->post_status)); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(get_permalink($article->ID)); ?>" target="_blank" class="button" style="padding:2px 10px;">View</a>
                                    <?php if (!$is_current): ?>
                                        | <a href="#" class="set-weekly-link" data-article-id="<?php echo esc_attr($article->ID); ?>" style="color:#0073aa;">Set Weekly</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
        <style>
        .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 24px;
            margin: 30px 0;
        }
        .current-weekly-article {
            background: #eaf6ff;
            border: 1px solid #b3d8ff;
            border-radius: 6px;
            padding: 18px;
            margin: 10px 0;
        }
        .current-weekly {
            background: #f0fff0 !important;
        }
        .form-table th {
            width: 180px;
        }
        .set-weekly-link {
            cursor: pointer;
            color: #0073aa;
            font-weight: 500;
        }
        .set-weekly-link:hover {
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
            $('.set-weekly-link').on('click', function(e) {
                e.preventDefault();
                var articleId = $(this).data('article-id');
                $.post(ajaxurl, {
                    action: 'mlmmc_set_weekly_article',
                    article_id: articleId,
                    nonce: '<?php echo wp_create_nonce("mlmmc_set_weekly_article"); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('Weekly article updated successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                });
            });
            // AJAX search
            $('#mlmmc-article-search-form').on('submit', function(e) {
                e.preventDefault();
                var searchVal = $('#mlmmc_article_search').val();
                $('#mlmmc-articles-tbody').html('<tr><td colspan="6">Searching...</td></tr>');
                $.post(ajaxurl, {
                    action: 'mlmmc_search_articles',
                    search: searchVal,
                    nonce: '<?php echo wp_create_nonce("mlmmc_search_articles"); ?>'
                }, function(response) {
                    if (response.success) {
                        $('#mlmmc-articles-tbody').html(response.data.html);
                    } else {
                        $('#mlmmc-articles-tbody').html('<tr><td colspan="6">No articles found.</td></tr>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Handle form submissions
     */
    private function handle_form_submissions(): void {
        // Set weekly article
        if (isset($_POST['action']) && $_POST['action'] === 'set_weekly_article' && isset($_POST['mlmmc_nonce'])) {
            if (!wp_verify_nonce($_POST['mlmmc_nonce'], 'mlmmc_set_weekly_article')) {
                add_settings_error('mlmmc_weekly_article', 'nonce_error', 'Security check failed.', 'error');
                return;
            }
            if (!current_user_can('manage_options')) {
                add_settings_error('mlmmc_weekly_article', 'capability_error', 'Insufficient permissions.', 'error');
                return;
            }
            $article_id = intval($_POST['article_id']);
            if ($article_id) {
                $this->set_weekly_article($article_id);
                // Clear WP Rocket cache
                if ( function_exists( 'rocket_clean_domain' ) ) {
                    rocket_clean_domain();
                }
                add_settings_error('mlmmc_weekly_article', 'success', 'Weekly article updated successfully.', 'updated');
            } else {
                add_settings_error('mlmmc_weekly_article', 'error', 'Please select an article.', 'error');
            }
        }
        // Clear cache
        if (isset($_POST['action']) && $_POST['action'] === 'clear_cache' && isset($_POST['mlmmc_cache_nonce'])) {
            if (!wp_verify_nonce($_POST['mlmmc_cache_nonce'], 'mlmmc_clear_cache')) {
                add_settings_error('mlmmc_weekly_article', 'nonce_error', 'Security check failed.', 'error');
                return;
            }
            if (!current_user_can('manage_options')) {
                add_settings_error('mlmmc_weekly_article', 'capability_error', 'Insufficient permissions.', 'error');
                return;
            }
            $weekly_handler = new WeeklyArticleHandler();
            $weekly_handler->clear_cache();
            // Clear WP Rocket cache
            if ( function_exists( 'rocket_clean_domain' ) ) {
                rocket_clean_domain();
            }
            add_settings_error('mlmmc_weekly_article', 'success', 'Cache cleared successfully.', 'updated');
        }
        // Force update
        if (isset($_POST['action']) && $_POST['action'] === 'force_update' && isset($_POST['mlmmc_update_nonce'])) {
            if (!wp_verify_nonce($_POST['mlmmc_update_nonce'], 'mlmmc_force_update')) {
                add_settings_error('mlmmc_weekly_article', 'nonce_error', 'Security check failed.', 'error');
                return;
            }
            if (!current_user_can('manage_options')) {
                add_settings_error('mlmmc_weekly_article', 'capability_error', 'Insufficient permissions.', 'error');
                return;
            }
            $weekly_handler = new WeeklyArticleHandler();
            $weekly_handler->manual_update_weekly_article();
            // Clear WP Rocket cache
            if ( function_exists( 'rocket_clean_domain' ) ) {
                rocket_clean_domain();
            }
            add_settings_error('mlmmc_weekly_article', 'success', 'Weekly article updated successfully.', 'updated');
        }
    }
    
    /**
     * Set weekly article via AJAX
     */
    public function ajax_set_weekly_article(): void {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'mlmmc_set_weekly_article')) {
            wp_send_json_error('Security check failed.');
        }
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
        }
        $article_id = intval($_POST['article_id']);
        if (!$article_id) {
            wp_send_json_error('Invalid article ID.');
        }
        $this->set_weekly_article($article_id);
        // Clear WP Rocket cache
        if ( function_exists( 'rocket_clean_domain' ) ) {
            rocket_clean_domain();
        }
        wp_send_json_success('Weekly article updated successfully.');
    }
    
    /**
     * Clear cache via AJAX
     */
    public function ajax_clear_cache(): void {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mlmmc_clear_cache')) {
            wp_send_json_error('Security check failed.');
        }
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
        }
        $weekly_handler = new WeeklyArticleHandler();
        $weekly_handler->clear_cache();
        // Clear WP Rocket cache
        if ( function_exists( 'rocket_clean_domain' ) ) {
            rocket_clean_domain();
        }
        wp_send_json_success('Cache cleared successfully.');
    }
    
    /**
     * Search articles via AJAX
     */
    public function ajax_search_articles(): void {
        check_ajax_referer('mlmmc_search_articles', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
        }
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $articles = $this->get_all_articles($search);
        $weekly_handler = new WeeklyArticleHandler();
        $current_weekly_data = $weekly_handler->get_weekly_article_admin_data();
        ob_start();
        if ($articles) {
            foreach ($articles as $article) {
                $author_name = get_post_meta($article->ID, 'mlmmc_article_author', true);
                if (!$author_name) {
                    $author_name = get_the_author_meta('display_name', $article->post_author);
                }
                $category_names = get_post_meta($article->ID, 'mlmmc_article_category', true);
                if (!$category_names) {
                    $category_names = 'None';
                }
                $is_current = $current_weekly_data && $current_weekly_data['article_id'] == $article->ID;
                ?>
                <tr <?php echo $is_current ? 'class="current-weekly"' : ''; ?> >
                    <td><?php echo esc_html($article->ID); ?></td>
                    <td><?php echo esc_html($article->post_title); ?></td>
                    <td><?php echo esc_html($author_name); ?></td>
                    <td><?php echo esc_html($category_names); ?></td>
                    <td><?php echo esc_html(ucfirst($article->post_status)); ?></td>
                    <td>
                        <a href="<?php echo esc_url(get_permalink($article->ID)); ?>" target="_blank" class="button" style="padding:2px 10px;">View</a>
                        <?php if (!$is_current): ?>
                            | <a href="#" class="set-weekly-link" data-article-id="<?php echo esc_attr($article->ID); ?>" style="color:#0073aa;">Set Weekly</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php
            }
        } else {
            echo '<tr><td colspan="6">No articles found.</td></tr>';
        }
        $html = ob_get_clean();
        wp_send_json_success(['html' => $html]);
    }
    
    /**
     * Set the weekly article
     */
    private function set_weekly_article(int $article_id): void {
        $article = get_post($article_id);
        if (!$article || $article->post_type !== 'mlmmc_artiicle' || $article->post_status !== 'publish') {
            return;
        }
        $article_data = [
            'article_id' => $article_id,
            'week_start' => (new WeeklyArticleHandler())->get_current_week_start(),
            'selected_date' => current_time('Y-m-d H:i:s')
        ];
        update_option('mlmmc_weekly_article', $article_data);
        // Clear WP Rocket cache to ensure the new weekly article is reflected and the old one is removed
        if ( function_exists( 'rocket_clean_domain' ) ) {
            rocket_clean_domain();
        }
    }
    
    /**
     * Get all MLM articles
     */
    private function get_all_articles($search_query = ''): array {
        $args = [
            'post_type' => 'mlmmc_artiicle',
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
        if ( $search_query ) {
            $args['s'] = $search_query;
        }
        $query = new \WP_Query($args);
        return $query->posts;
    }
}