<?php

namespace LABGENZ_CM\Articles;

/**
 * Handles AJAX search functionality for MLMMC articles.
 */
class ArticlesHandler {
    private const POSTS_PER_PAGE = 20;
    private const NONCE_ACTION = 'mlmmc_search_nonce';
    private const POST_TYPE = 'mlmmc_artiicle';
    private const TEMPLATE_ID = 42495;

    /**
     * ArticlesHandler constructor.
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize class hooks.
     */
    private function init_hooks(): void {
        add_action('wp_ajax_search_mlmmc_articles', [$this, 'handle_articles_search']);
        add_action('wp_ajax_nopriv_search_mlmmc_articles', '__return_false');
        add_action('wp_ajax_get_mlmmc_categories', [$this, 'handle_get_categories']);
        add_action('wp_ajax_nopriv_get_mlmmc_categories', '__return_false');
        add_action('wp_ajax_get_mlmmc_authors', [$this, 'handle_get_authors']);
        add_action('wp_ajax_nopriv_get_mlmmc_authors', '__return_false');
    }

    /**
     * Handle AJAX search request.
     *
     * @return void
     */
    public function handle_articles_search(): void {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', self::NONCE_ACTION)) {
            wp_die('Security check failed');
        }
        $search_term = sanitize_text_field($_POST['search_term'] ?? '');
        $category = sanitize_text_field($_POST['category'] ?? '');
        // Accept both single and multiple authors (array from JS)
        $authors = [];
        if (isset($_POST['authors'])) {
            if (is_array($_POST['authors'])) {
                $authors = array_map('sanitize_text_field', $_POST['authors']);
            } elseif (is_string($_POST['authors']) && $_POST['authors'] !== '') {
                $authors = [sanitize_text_field($_POST['authors'])];
            }
        } else {
            $author = sanitize_text_field($_POST['author'] ?? '');
            if ($author !== '') {
                $authors = [$author];
            }
        }
        $page = intval($_POST['page'] ?? 1) ?: 1;
        $query = $this->build_search_query($search_term, $category, $authors, $page);
        $content = $this->render_search_results($query, $search_term, $page);
        wp_send_json_success([
            'content' => $content,
            'found_posts' => $query->found_posts,
            'max_pages' => $query->max_num_pages
        ]);
    }

    /**
     * Build the search query.
     *
     * @param string $search_term
     * @param string $category
     * @param array $authors
     * @param int $page
     * @return \WP_Query
     */
    private function build_search_query(string $search_term, string $category, array $authors, int $page): \WP_Query {
        $args = [
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => self::POSTS_PER_PAGE,
            'paged' => $page,
            's' => $search_term,
            'search_columns' => ['post_title']
        ];
        $meta_query = [];
        if (!empty($category)) {
            $meta_query[] = [
                'key' => 'mlmmc_article_category',
                'value' => $category,
                'compare' => '='
            ];
        }
        if (!empty($authors)) {
            $meta_query[] = [
                'key' => 'mlmmc_article_author',
                'value' => $authors,
                'compare' => (count($authors) > 1 ? 'IN' : '=')
            ];
        }
        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
            if (count($meta_query) > 1) {
                $args['meta_query']['relation'] = 'AND';
            }
        }
        if (!empty($search_term)) {
            add_filter('posts_search', [$this, 'search_by_title_only'], 500, 2);
        }
        $query = new \WP_Query($args);
        remove_filter('posts_search', [$this, 'search_by_title_only'], 500);
        return $query;
    }

    /**
     * Handle AJAX request to get available authors.
     *
     * @return void
     */
    public function handle_get_authors(): void {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', self::NONCE_ACTION)) {
            wp_die('Security check failed');
        }
        $authors = $this->get_available_authors();
        wp_send_json_success(['authors' => $authors]);
    }

    /**
     * Get all available authors from the ACF field.
     *
     * @return array
     */
    private function get_available_authors(): array {
        global $wpdb;
        $authors = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT meta_value 
             FROM {$wpdb->postmeta} pm 
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             WHERE pm.meta_key = 'mlmmc_article_author'
             AND pm.meta_value != '' 
             AND p.post_type = %s 
             AND p.post_status = 'publish'
             ORDER BY meta_value ASC",
            self::POST_TYPE
        ));
        return array_filter($authors);
    }

    /**
     * Get authors from ACF field choices or from existing articles.
     *
     * @param int $post_id
     * @return array
     */
    private function get_authors_from_acf_field(int $post_id = 196): array {
        if (function_exists('get_field_object')) {
            $field_object = get_field_object('mlmmc_article_author', $post_id);
            if ($field_object && isset($field_object['choices']) && is_array($field_object['choices'])) {
                return array_values($field_object['choices']);
            }
        }
        return $this->get_available_authors();
    }

    /**
     * Render search results.
     *
     * @param \WP_Query $query
     * @param string $search_term
     * @param int $page
     * @return string
     */
    private function render_search_results(\WP_Query $query, string $search_term, int $page): string {
        ob_start();
        if ($query->have_posts()) {
            $this->render_posts($query);
            $this->render_pagination($query, $page);
        } else {
            $this->render_no_results($search_term);
        }
        wp_reset_postdata();
        return ob_get_clean();
    }

    /**
     * Render posts using Elementor template in 4 columns.
     *
     * @param \WP_Query $query
     * @return void
     */
    private function render_posts(\WP_Query $query): void {
        echo '<div class="search-results-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px;">';
        while ($query->have_posts()) {
            $query->the_post();
            echo '<div class="search-result-item">';
            echo \Elementor\Plugin::instance()->frontend->get_builder_content_for_display(self::TEMPLATE_ID);
            echo '</div>';
        }
        echo '</div>';
    }

    /**
     * Render pagination controls.
     *
     * @param \WP_Query $query
     * @param int $page
     * @return void
     */
    private function render_pagination(\WP_Query $query, int $page): void {
        $total_pages = $query->max_num_pages;
        if ($total_pages <= 1) {
            return;
        }
        echo '<div class="search-pagination">';
        $this->render_previous_button($page);
        $this->render_page_numbers($page, $total_pages);
        $this->render_next_button($page, $total_pages);
        echo '</div>';
    }

    /**
     * Render previous page button.
     *
     * @param int $page
     * @return void
     */
    private function render_previous_button(int $page): void {
        if ($page > 1) {
            printf(
                '<button class="search-page-btn" data-page="%d">Previous</button>',
                $page - 1
            );
        }
    }

    /**
     * Render page number buttons.
     *
     * @param int $page
     * @param int $total_pages
     * @return void
     */
    private function render_page_numbers(int $page, int $total_pages): void {
        $start_page = max(1, $page - 2);
        $end_page = min($total_pages, $page + 2);
        for ($i = $start_page; $i <= $end_page; $i++) {
            $active_class = ($i === $page) ? ' active' : '';
            printf(
                '<button class="search-page-btn%s" data-page="%d">%d</button>',
                $active_class,
                $i,
                $i
            );
        }
    }

    /**
     * Render next page button.
     *
     * @param int $page
     * @param int $total_pages
     * @return void
     */
    private function render_next_button(int $page, int $total_pages): void {
        if ($page < $total_pages) {
            printf(
                '<button class="search-page-btn" data-page="%d">Next</button>',
                $page + 1
            );
        }
    }

    /**
     * Render no results message.
     *
     * @param string $search_term
     * @return void
     */
    private function render_no_results(string $search_term): void {
        printf(
            '<div class="no-results">No articles found for "%s"</div>',
            esc_html($search_term)
        );
    }

    /**
     * Custom function to search only in post titles.
     *
     * @param string $search
     * @param \WP_Query $wp_query
     * @return string
     */
    public function search_by_title_only(string $search, \WP_Query $wp_query): string {
        global $wpdb;
        if (empty($search)) {
            return $search;
        }
        $q = $wp_query->query_vars;
        $n = !empty($q['exact']) ? '' : '%';
        $search = $searchand = '';
        foreach ((array)$q['search_terms'] as $term) {
            $term = esc_sql($wpdb->esc_like($term));
            $search .= "{$searchand}($wpdb->posts.post_title LIKE '{$n}{$term}{$n}')";
            $searchand = ' AND ';
        }
        if (!empty($search)) {
            $search = " AND ({$search}) ";
            if (!is_user_logged_in()) {
                $search .= " AND ($wpdb->posts.post_password = '') ";
            }
        }
        return $search;
    }

    /**
     * Handle AJAX request to get available categories.
     *
     * @return void
     */
    public function handle_get_categories(): void {
        $post_id = intval($_POST['post_id'] ?? 196);
        $categories = $this->get_categories_from_acf_field($post_id);
        wp_send_json_success(['categories' => $categories]);
    }

    /**
     * Get categories from ACF field choices or from existing articles.
     *
     * @param int $post_id
     * @return array
     */
    private function get_categories_from_acf_field(int $post_id): array {
        if (function_exists('get_field_object')) {
            $field_object = get_field_object('mlmmc_article_category', $post_id);
            if ($field_object && isset($field_object['choices']) && is_array($field_object['choices'])) {
                return array_values($field_object['choices']);
            }
        }
        return $this->get_available_categories();
    }

    /**
     * Get all available categories from the ACF field.
     *
     * @return array
     */
    private function get_available_categories(): array {
        global $wpdb;
        $categories = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT meta_value 
             FROM {$wpdb->postmeta} pm 
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             WHERE pm.meta_key = 'mlmmc_article_category' 
             AND pm.meta_value != '' 
             AND p.post_type = %s 
             AND p.post_status = 'publish'
             ORDER BY meta_value ASC",
            self::POST_TYPE
        ));
        return array_filter($categories);
    }
}