<?php

namespace LABGENZ_CM\Articles;

use LABGENZ_CM\Articles\Helpers\ArticleMetaHelper;
use LABGENZ_CM\Articles\Helpers\ArticleSearchHelper;

/**
 * Handles the displays in a card-based layout.
 */
class ArticleCardDisplayHandler {
    private const POST_TYPE = 'mlmmc_artiicle';
    public const SHORTCODE = 'mlmmc_articles';
    
    /**
     * Asset handles
     */
    public const ASSET_HANDLE_CSS = 'mlmmc-articles-cards';
    public const ASSET_HANDLE_JS = 'mlmmc-articles-cards-js';
    
    /**
     * AJAX actions
     */
    public const AJAX_ACTION_SEARCH = 'mlmmc_articles_search';
    public const AJAX_ACTION_AUTHORS = 'mlmmc_get_article_authors';
    public const AJAX_ACTION_CATEGORIES = 'mlmmc_get_article_categories';
    
    /**
     * ArticleCardDisplayHandler constructor.
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize class hooks.
     */
    private function init_hooks(): void {
        add_shortcode(self::SHORTCODE, [$this, 'render_articles_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        
        // AJAX handlers
        add_action('wp_ajax_' . self::AJAX_ACTION_SEARCH, [$this, 'ajax_search_articles']);
        add_action('wp_ajax_nopriv_' . self::AJAX_ACTION_SEARCH, [$this, 'ajax_search_articles']);
        
        add_action('wp_ajax_' . self::AJAX_ACTION_AUTHORS, [$this, 'ajax_get_article_authors']);
        add_action('wp_ajax_nopriv_' . self::AJAX_ACTION_AUTHORS, [$this, 'ajax_get_article_authors']);
        
        add_action('wp_ajax_' . self::AJAX_ACTION_CATEGORIES, [$this, 'ajax_get_article_categories']);
        add_action('wp_ajax_nopriv_' . self::AJAX_ACTION_CATEGORIES, [$this, 'ajax_get_article_categories']);
        
        add_action('labgenz_cm_after_article_header', [$this, 'render_article_video'], 20, 1);
    }
    
    /**
     * Register assets.
     */
    public function register_assets(): void {
        // Register force visible CSS (for debugging)
        // wp_register_style(
        //     self::ASSET_HANDLE_CSS . '-force-visible',
        //     LABGENZ_CM_URL . 'src/Articles/assets/css/force-visible.css',
        //     [],
        //     '1.0.0'
        // );
        
        // Register improved dropdown CSS
        wp_register_style(
            self::ASSET_HANDLE_CSS . '-improved-dropdown',
            LABGENZ_CM_URL . 'src/Articles/assets/css/improved-dropdown.css',
            [],
            '1.0.0'
        );
    
        
        // Register category filter CSS
        wp_register_style(
            self::ASSET_HANDLE_CSS . '-category',
            LABGENZ_CM_URL . 'src/Articles/assets/css/category-filter.css',
            [self::ASSET_HANDLE_CSS . '-improved-dropdown'],
            '1.0.3'
        );
        
        // Register CSS
        wp_register_style(
            self::ASSET_HANDLE_CSS . '-filter-common',
            LABGENZ_CM_URL . 'src/Articles/assets/css/filter-common.css',
            [],
            '1.0.3'
        );
        
        // Register CSS
        wp_register_style(
            self::ASSET_HANDLE_CSS,
            LABGENZ_CM_URL . 'src/Articles/assets/css/article-cards.css',
            [self::ASSET_HANDLE_CSS . '-category', self::ASSET_HANDLE_CSS . '-filter-common'],
            '1.0.7'
        );
        
        // Register category filter JS
        wp_register_script(
            self::ASSET_HANDLE_JS . '-category',
            LABGENZ_CM_URL . 'src/Articles/assets/js/category-filter-new.js',
            ['jquery'],
            '1.0.8',
            true
        );
        
                // Register author filter JS
        wp_register_script(
            self::ASSET_HANDLE_JS . '-author',
            LABGENZ_CM_URL . 'src/Articles/assets/js/author-filter.js',
            ['jquery'],
            '1.0.8',
            true
        );
        
        // Register rating filter JS
        wp_register_script(
            self::ASSET_HANDLE_JS . '-rating',
            LABGENZ_CM_URL . 'src/Articles/assets/js/rating-filter.js',
            ['jquery'],
            '1.0.6',
            true
        );
        
        // Register JS
        wp_register_script(
            self::ASSET_HANDLE_JS,
            LABGENZ_CM_URL . 'src/Articles/assets/js/article-cards.js',
            ['jquery', self::ASSET_HANDLE_JS . '-category', self::ASSET_HANDLE_JS . '-author'],
            '1.2.5',
            true
        );
    }
    
    /**
     * Render the articles shortcode.
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function render_articles_shortcode(array $atts = []): string {
        // Enqueue styles and scripts
        wp_enqueue_style(self::ASSET_HANDLE_CSS . '-improved-dropdown');
        wp_enqueue_style(self::ASSET_HANDLE_CSS . '-category');
        wp_enqueue_style(self::ASSET_HANDLE_CSS);
        
        // Enqueue category filter, author filter, and main JS
        wp_enqueue_script(self::ASSET_HANDLE_JS . '-author');
        wp_enqueue_script(self::ASSET_HANDLE_JS . '-category');
        wp_enqueue_script(self::ASSET_HANDLE_JS);
        wp_enqueue_script(self::ASSET_HANDLE_JS . '-rating');
        
        // Pass categories with counts to the localized script
        $categories_with_counts = $this->get_article_counts_by_meta('mlmmc_article_category');

        // Localize script with AJAX URL and action names
        wp_localize_script(
            self::ASSET_HANDLE_JS,
            'mlmmcArticlesData',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'searchAction' => self::AJAX_ACTION_SEARCH,
                'authorsAction' => self::AJAX_ACTION_AUTHORS,
                'categoriesAction' => self::AJAX_ACTION_CATEGORIES,
                'nonce' => wp_create_nonce('mlmmc_articles_ajax_nonce'),
                'categoriesWithCounts' => $categories_with_counts,
                'i18n' => [
                    'selectCategories' => __('Select categories', 'labgenz-community-management'),
                    'clearAll' => __('Clear All', 'labgenz-community-management'),
                    'apply' => __('Apply', 'labgenz-community-management'),
                    'noResults' => __('No results found', 'labgenz-community-management')
                ]
            ]
        );
        
        $atts = shortcode_atts(
            [
                'posts_per_page' => 20,
                'category' => '',
                'author' => '',
                'orderby' => 'date',
                'order' => 'DESC',
                'columns' => 3,
                'show_excerpt' => 'true',
                'excerpt_length' => 20,
                'show_author' => 'true',
                'show_date' => 'true',
                'show_category' => 'true',
                'show_rating' => 'true',
                'show_search' => 'true',
                'show_filters' => 'true',
            ],
            $atts
        );
        
        // Sanitize attribute values
        $posts_per_page = intval($atts['posts_per_page']);
        $columns = intval($atts['columns']);
        $excerpt_length = intval($atts['excerpt_length']);
        $show_excerpt = $atts['show_excerpt'] === 'true';
        $show_author = $atts['show_author'] === 'true';
        $show_date = $atts['show_date'] === 'true';
        $show_category = $atts['show_category'] === 'true';
        $show_rating = $atts['show_rating'] === 'true';
        $show_search = $atts['show_search'] === 'true';
        $show_filters = $atts['show_filters'] === 'true';
        
        // Build query args
        $args = [
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => $posts_per_page,
            'orderby' => $atts['orderby'],
            'order' => $atts['order'],
        ];
        
        // Add meta query if category is specified
        if (!empty($atts['category'])) {
            $args['meta_query'][] = [
                'key' => 'mlmmc_article_category',
                'value' => $atts['category'],
                'compare' => '=',
            ];
        }
        
        // Add meta query if author is specified
        if (!empty($atts['author'])) {
            $args['meta_query'][] = [
                'key' => 'mlmmc_article_author',
                'value' => $atts['author'],
                'compare' => '=',
            ];
        }
        
        // If we have multiple meta queries, set relation
        if (isset($args['meta_query']) && count($args['meta_query']) > 1) {
            $args['meta_query']['relation'] = 'AND';
        }
        
        // Get articles
        $query = new \WP_Query($args);
        $total_articles = $query->found_posts;
        $max_pages = $query->max_num_pages;
        
        // Get categories for the filter
        $categories = $this->get_article_categories();
        
        // Get categories with counts for display
        $categories_with_counts = $this->get_article_categories_with_counts();
        
        // Get initial rating counts for display
        $rating_counts = $this->get_rating_counts();
        
        // Initialize ArticlesHandler and ReviewsHandler for getting metadata
        $articles_handler = new ArticlesHandler();
        $reviews_handler = class_exists('\LABGENZ_CM\Articles\ReviewsHandler') ? new ReviewsHandler() : null;
        
        // Pass articles to template
        $articles = [];
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                // Get metadata
                $author_name = method_exists($articles_handler, 'get_article_author') ? 
                    $articles_handler->get_article_author($post_id) : 
                    get_post_meta($post_id, 'mlmmc_article_author', true);
                
                if (empty($author_name)) {
                    $author_name = get_the_author();
                }
                
                $category = method_exists($articles_handler, 'get_article_category') ? 
                    $articles_handler->get_article_category($post_id) : 
                    get_post_meta($post_id, 'mlmmc_article_category', true);
                
                $average_rating = $reviews_handler && method_exists($reviews_handler, 'get_average_rating') ? 
                    $reviews_handler->get_average_rating($post_id) : 0;
                    
                $rating_count = $reviews_handler && method_exists($reviews_handler, 'get_rating_count') ? 
                    $reviews_handler->get_rating_count($post_id) : 0;
                
                // Get featured image
                $thumb_url = get_the_post_thumbnail_url($post_id, 'medium');
                $has_thumb = !empty($thumb_url);
                
                // Get author image
                $author_image_id = get_post_meta($post_id, 'mlmmc_author_photo', true);
                $author_image = $author_image_id ? wp_get_attachment_image_url($author_image_id, 'thumbnail') : '';
                
                if (!$author_image) {
                    $author_id = get_post_field('post_author', $post_id);
                    $author_image = get_avatar_url($author_id, ['size' => 40]);
                }
                
                // Get excerpt
                $excerpt = $show_excerpt ? wp_trim_words(get_the_excerpt(), $excerpt_length, '...') : '';
                
                $mlm_video_link = get_post_meta($post_id, 'mlmmc_video_link', true);
                $has_video = !empty($mlm_video_link);

                // Add to article array with null checks
                $articles[] = [
                    'id' => $post_id,
                    'title' => get_the_title(),
                    'permalink' => get_permalink(),
                    'excerpt' => $excerpt,
                    'thumbnail' => $thumb_url,
                    'author_name' => $author_name,
                    'author_image' => $author_image,
                    'category' => $category,
                    'date' => get_the_date('M j, Y'),
                    'average_rating' => $average_rating,
                    'rating_count' => $rating_count,
                    'has_video' => $has_video,
                    ];
            }
            wp_reset_postdata();
        }
        
        // Include template
        ob_start();
        
        $template_path = LABGENZ_CM_PATH . 'templates/articles/article-cards.php';
        
        // Pass data to template
        include $template_path;
        
        // Return the output
        return ob_get_clean();
    }
    
    /**
     * Get all article categories.
     *
     * @return array
     */
    public function get_article_categories(): array {
        return ArticleMetaHelper::get_article_categories(self::POST_TYPE);
    }

    /**
     * Get all article authors.
     *
     * @return array
     */
    public function get_article_authors(): array {
        return ArticleMetaHelper::get_article_authors(self::POST_TYPE);
    }
    
    /**
     * Get article counts grouped by a specific meta key (e.g., author, category, or rating).
     *
     * @param string $meta_key The meta key to group by.
     * @return array Associative array of meta values and their corresponding article counts.
     */
    public function get_article_counts_by_meta($meta_key) {
        return ArticleMetaHelper::get_article_counts_by_meta($meta_key, self::POST_TYPE);
    }
    
    /**
     * AJAX handler for searching articles - FIXED VERSION
     */
    public function ajax_search_articles(): void {
        // Check nonce
        if (!ArticleSearchHelper::validate_nonce($_POST['nonce'])) {
            wp_send_json_error(['message' => 'Security check failed']);
            wp_die();
        }
        
        // Get search parameters
        $search_query = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $authors = isset($_POST['authors']) && is_array($_POST['authors']) ? array_map('sanitize_text_field', $_POST['authors']) : [];
        $categories = isset($_POST['categories']) && is_array($_POST['categories']) ? array_map('sanitize_text_field', $_POST['categories']) : [];
        $ratings = isset($_POST['ratings']) && is_array($_POST['ratings']) ? array_map('intval', $_POST['ratings']) : [];
        $vid_only = isset($_POST['vid_only']) && $_POST['vid_only'] === 'true' ? true : false;
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $posts_per_page = isset($_POST['posts_per_page']) ? intval($_POST['posts_per_page']) : 12;

        // Parse shortcode attributes
        $show_excerpt = isset($_POST['show_excerpt']) ? ($_POST['show_excerpt'] === 'true') : true;
        $show_author = isset($_POST['show_author']) ? ($_POST['show_author'] === 'true') : true;
        $show_date = isset($_POST['show_date']) ? ($_POST['show_date'] === 'true') : true;
        $show_category = isset($_POST['show_category']) ? ($_POST['show_category'] === 'true') : true;
        $show_rating = isset($_POST['show_rating']) ? ($_POST['show_rating'] === 'true') : true;
        $excerpt_length = isset($_POST['excerpt_length']) ? intval($_POST['excerpt_length']) : 20;
        
        // Build query args
        $args = [
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => $posts_per_page,
            'paged' => $page,
            'orderby' => 'date',
            'order' => 'DESC',
        ];
        try{

        // FIXED: Build meta query properly for multiple filter types
        $meta_query = [];
        $has_filters = false;
        
        // Add search query only if not empty - use direct SQL for title search
        if (!empty($search_query) && trim($search_query) !== '') {
            // Add a filter to customize the search SQL query
            add_filter('posts_search', [$this, 'filter_search_by_title_only'], 10, 2);
            add_filter('posts_where', [$this, 'filter_search_respect_post_type'], 10, 2);
            
            // Set the standard WordPress search parameter
            $args['s'] = $search_query;
        }
        
        // Add author filter if specified - use individual queries for each author
        if (!empty($authors)) {
            if (count($authors) == 1) {
                // Single author - use simple comparison
                $meta_query[] = [
                    'key' => 'mlmmc_article_author',
                    'value' => $authors[0],
                    'compare' => '=',
                ];
            } else {
                // Multiple authors - create OR subquery
                $author_subquery = ['relation' => 'OR'];
                foreach ($authors as $author) {
                    $author_subquery[] = [
                        'key' => 'mlmmc_article_author',
                        'value' => $author,
                        'compare' => '=',
                    ];
                }
                $meta_query[] = $author_subquery;
            }
            $has_filters = true;
        }
        
        // Add category filter if specified - use individual queries for each category
        if (!empty($categories)) {
            if (count($categories) == 1) {
                // Single category - use simple comparison
                $meta_query[] = [
                    'key' => 'mlmmc_article_category',
                    'value' => $categories[0],
                    'compare' => '=',
                ];
            } else {
                // Multiple categories - create OR subquery
                $category_subquery = ['relation' => 'OR'];
                foreach ($categories as $category) {
                    $category_subquery[] = [
                        'key' => 'mlmmc_article_category',
                        'value' => $category,
                        'compare' => '=',
                    ];
                }
                $meta_query[] = $category_subquery;
            }
            $has_filters = true;
        }
        
        // Add rating filter if specified - EXACT MATCHING VERSION
        if (!empty($ratings)) {
            
            try {
                // Create a rating query with exact matching
                if (count($ratings) == 1) {
                    // Single rating - use exact equality
                    $meta_query[] = [
                        'key' => ReviewsHandler::META_KEY_RATING,
                        'value' => (float)$ratings[0],
                        'compare' => '=',
                        'type' => 'DECIMAL(10,1)',
                    ];
                } else {
                    // Multiple ratings - use IN operator
                    $meta_query[] = [
                        'key' => ReviewsHandler::META_KEY_RATING,
                        'value' => array_map('floatval', $ratings),
                        'compare' => 'IN',
                        'type' => 'DECIMAL(10,1)',
                    ];
                }
                
                $has_filters = true;
            } catch (\Exception $e) {
            }
        }

        // Add video-only filter if specified
        if ($vid_only) {
            $meta_query[] = [
                'key' => 'mlmmc_video_link',
                'value' => '',
                'compare' => '!=',
            ];
            $has_filters = true;
        }

        // Set relation to AND if we have multiple filters
        if ($has_filters && count($meta_query) > 1) {
            $meta_query['relation'] = 'AND';
        }

        // Add meta query to args if we have any filters
        if ($has_filters) {
            $args['meta_query'] = $meta_query;
        }
        
        // Prepare debug data for JSON response
        $debug_data = [
            'search_query' => $search_query,
            'received_authors' => $authors,
            'received_categories' => $categories,
            'received_ratings' => $ratings,
            'meta_query' => $meta_query,
            'wp_query_args' => $args,
            'raw_post_data' => $_POST
        ];
        
        // Get articles
        $query = new \WP_Query($args);
        $total_articles = $query->found_posts;
        $max_pages = $query->max_num_pages;
        
        // Remove the title-only search filter after query is complete
        if (!empty($search_query) && trim($search_query) !== '') {
            remove_filter('posts_search', [$this, 'filter_search_by_title_only']);
            remove_filter('posts_where', [$this, 'filter_search_respect_post_type']);
        }
        
        // Verify the rating values of returned articles when rating filter is applied
        if (!empty($ratings) && $query->have_posts()) {
            $rating_verification = [];
            $reviews_handler = class_exists('\LABGENZ_CM\Articles\ReviewsHandler') ? new ReviewsHandler() : null;
            
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $article_rating = null;
                
                // Get the rating directly from meta to verify
                if ($reviews_handler) {
                    $article_rating = $reviews_handler->get_average_rating($post_id);
                } else {
                    $article_rating = get_post_meta($post_id, ReviewsHandler::META_KEY_RATING, true);
                }
                
                $rating_verification[] = [
                    'post_id' => $post_id,
                    'title' => get_the_title(),
                    'rating' => $article_rating,
                    'rating_meta_exists' => metadata_exists('post', $post_id, ReviewsHandler::META_KEY_RATING),
                    'matches_filter' => false // Will be updated below
                ];
            }
            
            // Reset query
            wp_reset_postdata();
            $query->rewind_posts();
            
            // Check which ratings match our filter criteria - EXACT MATCHING
            foreach ($rating_verification as &$verification) {
                $rating = (float)$verification['rating'];
                if ($rating <= 0) {
                    $verification['matches_filter'] = false;
                    continue;
                }
                
                // Check if the rating exactly matches any of the filter ratings
                $verification['matches_filter'] = in_array($rating, array_map('floatval', $ratings));
            }
        }
        
        // DEBUG: Collect found posts and their meta values for JSON response
        $found_posts_debug = [];
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $stored_category = get_post_meta($post_id, 'mlmmc_article_category', true);
                $stored_author = get_post_meta($post_id, 'mlmmc_article_author', true);
                $found_posts_debug[] = [
                    'id' => $post_id,
                    'title' => get_the_title(),
                    'category' => $stored_category,
                    'author' => $stored_author
                ];
            }
            wp_reset_postdata();
            $query->rewind_posts();
        }
        
        // Initialize ArticlesHandler and ReviewsHandler for getting metadata
        $articles_handler = new ArticlesHandler();
        $reviews_handler = class_exists('\LABGENZ_CM\Articles\ReviewsHandler') ? new ReviewsHandler() : null;
        
        // Prepare articles data
        $articles = [];
        $processed_post_ids = []; // Track post IDs to prevent duplicates
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                // Skip if we've already processed this post
                if (in_array($post_id, $processed_post_ids)) {
                    continue;
                }
                
                // Add this post ID to the processed list
                $processed_post_ids[] = $post_id;
                
                // Get metadata
                $author_name = method_exists($articles_handler, 'get_article_author') ? 
                    $articles_handler->get_article_author($post_id) : 
                    get_post_meta($post_id, 'mlmmc_article_author', true);
                
                if (empty($author_name)) {
                    $author_name = get_the_author();
                }
                
                $category_value = method_exists($articles_handler, 'get_article_category') ? 
                    $articles_handler->get_article_category($post_id) : 
                    get_post_meta($post_id, 'mlmmc_article_category', true);
                
                $average_rating = $reviews_handler && method_exists($reviews_handler, 'get_average_rating') ? 
                    $reviews_handler->get_average_rating($post_id) : 0;
                    
                $rating_count = $reviews_handler && method_exists($reviews_handler, 'get_rating_count') ? 
                    $reviews_handler->get_rating_count($post_id) : 0;
                
                // Get featured image
                $thumb_url = get_the_post_thumbnail_url($post_id, 'medium');
                $has_thumb = !empty($thumb_url);
                
                // Get author image
                $author_image_id = get_post_meta($post_id, 'mlmmc_author_photo', true);
                $author_image = $author_image_id ? wp_get_attachment_image_url($author_image_id, 'thumbnail') : '';
                
                if (!$author_image) {
                    $author_id = get_post_field('post_author', $post_id);
                    $author_image = get_avatar_url($author_id, ['size' => 40]);
                }
                
                // Get excerpt
                $excerpt = $show_excerpt ? wp_trim_words(get_the_excerpt(), $excerpt_length, '...') : '';
                
                // Check if article has a video
                $mlm_video_link = get_post_meta($post_id, 'mlmmc_video_link', true);
                $has_video = !empty($mlm_video_link);
                
                // Build rating stars HTML
                $stars_html = '';
                if ($show_rating && $average_rating > 0) {
                    $stars_html = ArticleSearchHelper::generate_stars_html($average_rating);
                }
                
                // Add to articles array
                $articles[] = [
                    'id' => $post_id,
                    'title' => get_the_title(),
                    'permalink' => get_permalink(),
                    'excerpt' => $excerpt,
                    'thumbnail' => $thumb_url,
                    'has_thumbnail' => $has_thumb, // Add has_thumbnail property to fix warnings
                    'author_name' => $author_name,
                    'author_image' => $author_image,
                    'category' => $category_value,
                    'date' => get_the_date('M j, Y'),
                    'average_rating' => $average_rating,
                    'rating_count' => $rating_count,
                    'stars_html' => $stars_html,
                    'has_video' => $has_video, // Add has_video property
                ];
            }
            wp_reset_postdata();
        }
        
        // Deduplicate articles array before rendering
        $articles = ArticleSearchHelper::deduplicate_articles($articles);
        
        ob_start();

        // Create a set of article IDs to track duplicates
        $rendered_article_ids = [];
        
        // Load article card template for each article - FIXED VERSION
        if (is_array($articles) && !empty($articles)) {
            foreach ($articles as $article) {
                // Validate article data
                if (!is_array($article) || empty($article['id'])) {
                    continue;
                }
                
                // Set up global post object for template compatibility
                global $post;
                $post = get_post($article['id']);
                if (!$post) {
                    continue;
                }
                setup_postdata($post);
                
                // Add this article ID to the rendered list
                $rendered_article_ids[] = $article['id'];
                
                // Set up template variables for the current article with null checks
                $post_id = $article['id'] ?? 0;
                $title = $article['title'] ?? '';
                $permalink = $article['permalink'] ?? '';
                $excerpt = $article['excerpt'] ?? '';
                $thumb_url = $article['thumbnail'] ?? '';
                $has_thumb = $article['has_thumbnail'] ?? false;
                $author_name = $article['author_name'] ?? '';
                $author_image = $article['author_image'] ?? '';
                $category = $article['category'] ?? '';
                $date = $article['date'] ?? '';
                $average_rating = $article['average_rating'] ?? 0;
                $rating_count = $article['rating_count'] ?? 0;
                $stars_html = $article['stars_html'] ?? '';
                $has_video = $article['has_video'] ?? false;
                
                // Include the template with these variables available
                include LABGENZ_CM_PATH . 'templates/articles/article-card-item.php';
            }
        }
        
        // Reset post data
        wp_reset_postdata();
        
        $html = ob_get_clean();
        
        // Get filtered data based on current selections
        try {
            $filtered_data = $this->get_filtered_data_for_response($authors, $categories, $ratings);
        } catch (\Exception $e) {
            $filtered_data = [
                'filtered_authors' => [],
                'filtered_categories' => [],
                'filtered_ratings' => []
            ];
        }
        
        // Add a cache-busting parameter for AJAX responses
        $cache_buster = time();
        
        // Add debug information about duplicates - with null checks
        $duplicate_info = [
            'duplicates_removed' => is_array($processed_post_ids) ? count($processed_post_ids) - count(array_unique($processed_post_ids)) : 0,
            'html_duplicates_removed' => (isset($rendered_article_ids) && is_array($rendered_article_ids)) ? 
                count($articles) - count($rendered_article_ids) : 0
        ];
        
        // Return results with the total number of articles found in the query
        wp_send_json_success([
            'html' => $html,
            'found_posts' => $total_articles, // Use total number of articles found in query
            'max_pages' => $max_pages,
            'filtered_authors' => $filtered_data['filtered_authors'],
            'filtered_categories' => $filtered_data['filtered_categories'],
            'filtered_ratings' => $filtered_data['filtered_ratings'],
            'cache_buster' => $cache_buster, // Add cache buster
            'duplicate_info' => $duplicate_info // Add duplicate tracking info
        ]);
        
        wp_die();
        } catch (\Exception $e) {
            wp_send_json_error(['message' => 'An error occurred while processing your request.' . $e->getMessage()]);
            wp_die();
    } catch (\Throwable $e) {
            wp_send_json_error(['message' => 'An error occurred while processing your request. ' . $e->getMessage()]);
            wp_die();
        } 
    }
    
    /**
     * Additional filter to ensure search respects our post type
     * 
     * @param string   $where    The WHERE clause of the query.
     * @param WP_Query $wp_query The WP_Query instance.
     * @return string Modified WHERE clause.
     */
    public function filter_search_respect_post_type($where, $wp_query) {
        // Only modify if this is our specific search
        if (!isset($wp_query->query_vars['s']) || empty($wp_query->query_vars['s'])) {
            return $where;
        }
        
        return $where;
    }
    
    /**
     * Filter the search query to search in post titles only using a direct SQL query
     * 
     * @param string   $search   Search SQL for WHERE clause.
     * @param WP_Query $wp_query The WP_Query instance.
     * @return string Modified search SQL.
     */
    public function filter_search_by_title_only($search, $wp_query) {
        if (empty($search) || !isset($wp_query->query_vars['s'])) {
            return $search; // Return the original if not a search or no search term
        }
        
        global $wpdb;
        
        // Get the search term and escape it for SQL LIKE
        $search_term = $wpdb->esc_like($wp_query->query_vars['s']);
        $search_term = '%' . $search_term . '%'; // Add wildcards for LIKE query
        
        // Create a direct title-only search using post_title column
        $search = $wpdb->prepare(
            " AND ({$wpdb->posts}.post_title LIKE %s) ",
            $search_term
        );
        
        // Remove any hooks that might interfere with our custom search
        remove_all_filters('posts_search');
        
        return $search;
    }
    
    /**
     * AJAX handler for getting article authors
     */
    public function ajax_get_article_authors(): void {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mlmmc_articles_ajax_nonce')) {
            wp_send_json_error(['message' => 'Security check failed']);
            wp_die();
        }
        
        // Get authors
        $authors = $this->get_article_authors();
        $author_counts = $this->get_article_counts_by_meta('mlmmc_article_author');
        
        // Debug: Count articles directly using WP_Query for comparison
        foreach ($authors as $author) {
            $query_args = ArticleSearchHelper::build_query_args(['authors' => [$author]], self::POST_TYPE);
            $query = new \WP_Query($query_args);
            $wpquery_count = $query->found_posts;
        }

        $authors_with_counts = array_map(function($author) use ($author_counts) {
            return [
                'name' => $author,
                'count' => $author_counts[$author] ?? 0
            ];
        }, $authors);

        // Return authors
        wp_send_json_success([
            'authors' => $authors_with_counts,
        ]);
        
        wp_die();
    }
    
    /**
     * AJAX handler for getting article categories
     */
    public function ajax_get_article_categories(): void {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mlmmc_articles_ajax_nonce')) {
            wp_send_json_error(['message' => 'Security check failed']);
            wp_die();
        }
        
        // Get categories with counts
        $categories_with_counts = $this->get_article_categories_with_counts();
        
        // Return categories
        wp_send_json_success([
            'categories' => $categories_with_counts,
        ]);
        
        wp_die();
    }
    
    /**
     * Get article categories with their counts.
     *
     * @return array
     */
    private function get_article_categories_with_counts(): array {
        return ArticleMetaHelper::get_article_categories_with_counts(self::POST_TYPE);
    }
    
    /**
     * Count articles by rating while respecting other filters - using exact rating matching
     * 
     * @param array $authors Selected authors (optional)
     * @param array $categories Selected categories (optional)
     * @param array $ratings Selected ratings (optional)
     * @return array Array of rating counts [5 => count, 4 => count, etc.]
     */
    public function get_rating_counts(array $authors = [], array $categories = [], array $ratings = []): array {
        global $wpdb;
        $rating_counts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        
        try {
            // Get the meta key for ratings from ReviewsHandler
            $rating_meta_key = ReviewsHandler::META_KEY_RATING;
            
            // Base query - for counting ratings
            $sql = "
                SELECT FLOOR(pm.meta_value) as rating_floor, COUNT(DISTINCT p.ID) as count
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s AND pm.meta_value > 0
                WHERE p.post_type = %s AND p.post_status = 'publish'
            ";
            
            $params = [$rating_meta_key, self::POST_TYPE];
            
            // Add author filter if needed
            if (!empty($authors)) {
                $sql .= " AND p.ID IN (
                    SELECT post_id FROM {$wpdb->postmeta} 
                    WHERE meta_key = 'mlmmc_article_author' AND meta_value IN (" . 
                    implode(',', array_fill(0, count($authors), '%s')) . ")
                )";
                $params = array_merge($params, $authors);
            }
            
            // Add category filter if needed
            if (!empty($categories)) {
                $sql .= " AND p.ID IN (
                    SELECT post_id FROM {$wpdb->postmeta} 
                    WHERE meta_key = 'mlmmc_article_category' AND meta_value IN (" . 
                    implode(',', array_fill(0, count($categories), '%s')) . ")
                )";
                $params = array_merge($params, $categories);
            }
            
            // Add rating filter if needed - exact matching
            if (!empty($ratings)) {
                $placeholders = implode(',', array_fill(0, count($ratings), '%s'));
                $sql .= " AND pm.meta_value IN ($placeholders)";
                $params = array_merge($params, array_map('floatval', $ratings));
            }
            
            // Group by to get counts per rating range
            $sql .= " GROUP BY rating_floor";
            
            // Prepare and execute the query
            $query = $wpdb->prepare($sql, $params);
            
            $results = $wpdb->get_results($query);
            
            // Process results into our standard format
            if ($results) {
                foreach ($results as $row) {
                    $rating_floor = (int)$row->rating_floor;
                    if ($rating_floor >= 1 && $rating_floor <= 5) {
                        $rating_counts[$rating_floor] = (int)$row->count;
                    }
                }
            }
        } catch (\Exception $e) {
        }
        
        return $rating_counts;
    }
    
    /**
     * Get filtered data for AJAX response based on selected filters
     *
     * @param array $authors Selected authors
     * @param array $categories Selected categories
     * @param array $ratings Selected ratings
     * @return array Associative array with filtered authors, categories, and ratings
     */
    private function get_filtered_data_for_response(array $authors, array $categories, array $ratings): array {
        $filtered_authors = [];
        $filtered_categories = [];
        $filtered_ratings = [];
        
        // If no ratings but categories are selected
        if (!empty($categories)) {
            $filtered_authors = ArticleMetaHelper::get_filtered_authors_by_categories($categories, self::POST_TYPE);
            
            // If authors are also selected, filter categories by authors
            if (!empty($authors)) {
                $filtered_categories = ArticleMetaHelper::get_filtered_categories_by_authors($authors, self::POST_TYPE);
                
                // Keep only categories that match the original category selection
                $filtered_categories = array_filter($filtered_categories, function($category) use ($categories) {
                    return in_array($category['name'], $categories);
                });
                
                // Re-index array
                $filtered_categories = array_values($filtered_categories);
            }
        }
        // If only authors are selected
        else if (!empty($authors)) {
            $filtered_categories = ArticleMetaHelper::get_filtered_categories_by_authors($authors, self::POST_TYPE);
        }
        
        // For ratings, always prepare data for display with counts regardless of selection
        // Run direct SQL query to get accurate counts regardless of other filters
        $rating_counts = $this->get_rating_counts(
            $authors,      // Pass current author filters
            $categories,   // Pass current category filters
            $ratings       // Also pass current rating filters to reflect accurate counts
        );
        
        // Prepare filtered ratings with counts - always show all ratings
        $filtered_ratings = [];
        for ($i = 5; $i >= 1; $i--) {
            $filtered_ratings[] = [
                'rating' => $i,
                'count' => $rating_counts[$i]
            ];
        }
        
        return [
            'filtered_authors' => $filtered_authors,
            'filtered_categories' => $filtered_categories,
            'filtered_ratings' => $filtered_ratings
        ];
    }


    /**
     * Render the article video.
     *
     * @param int $post_id The ID of the post to render the video for.
     */
    public function render_article_video($post_id) {
        $template_path =  LABGENZ_CM_TEMPLATES_DIR . '/articles/article-video.php';

        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<p class="no-video">Error Loading Video - please refresh the page.</p>';
        }
    }

    /**
     * Get articles with videos only.
     *
     * @param array $args Query arguments.
     * @return array List of articles with videos.
     */
    public function get_articles_with_videos(array $args = []): array {
        // Ensure post type is set
        $args['post_type'] = self::POST_TYPE;
        $args['post_status'] = 'publish';

        // Add meta query to filter articles with a non-empty video link
        $args['meta_query'][] = [
            'key' => 'mlmmc_video_link',
            'value' => '',
            'compare' => '!=',
        ];

        // Query articles
        $query = new \WP_Query($args);
        $articles = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                // Add article data to the result
                $articles[] = [
                    'id' => $post_id,
                    'title' => get_the_title(),
                    'permalink' => get_permalink(),
                    'video_link' => get_post_meta($post_id, 'mlmmc_video_link', true),
                ];
            }
            wp_reset_postdata();
        }

        return $articles;
    }
}
