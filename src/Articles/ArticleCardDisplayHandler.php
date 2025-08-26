<?php

namespace LABGENZ_CM\Articles;

use LABGENZ_CM\Articles\Helpers\ArticleMetaHelper;
use LABGENZ_CM\Articles\Helpers\ArticleSearchHelper;
use LABGENZ_CM\Articles\Helpers\ArticleCacheHelper;

/**
 * Handles the displays in a card-based layout.
 */
class ArticleCardDisplayHandler {
	public const POST_TYPE = 'mlmmc_artiicle';
	public const SHORTCODE  = 'mlmmc_articles';

	/**
	 * Asset handles
	 */
	public const ASSET_HANDLE_CSS = 'mlmmc-articles-cards';
	public const ASSET_HANDLE_JS  = 'mlmmc-articles-cards-js';

	/**
	 * AJAX actions
	 */
	public const AJAX_ACTION_SEARCH     = 'mlmmc_articles_search';
	public const AJAX_ACTION_AUTHORS    = 'mlmmc_get_article_authors';
	public const AJAX_ACTION_CATEGORIES = 'mlmmc_get_article_categories';
	public const AJAX_ACTION_CLEAR_CACHES = 'mlmmc_clear_article_caches';

	/**
	 * ArticleCardDisplayHandler constructor.
	 */
	public function __construct() {
		$articles_handler = new ArticlesHandler();
		$this->init_hooks();

		ArticleCacheHelper::init_hooks();
		$article_cache_helper = new ArticleCacheHelper();
	}

	/**
	 * Initialize class hooks.
	 */
	private function init_hooks(): void {
		add_shortcode( self::SHORTCODE, [ $this, 'render_articles_shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );

		// AJAX handlers
		add_action( 'wp_ajax_' . self::AJAX_ACTION_SEARCH, [ $this, 'ajax_search_articles' ] );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION_SEARCH, [ $this, 'ajax_search_articles' ] );

		add_action( 'wp_ajax_' . self::AJAX_ACTION_AUTHORS, [ $this, 'ajax_get_article_authors' ] );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION_AUTHORS, [ $this, 'ajax_get_article_authors' ] );

		add_action( 'wp_ajax_' . self::AJAX_ACTION_CATEGORIES, [ $this, 'ajax_get_article_categories' ] );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION_CATEGORIES, [ $this, 'ajax_get_article_categories' ] );

        add_action( 'wp_ajax_' . self::AJAX_ACTION_CLEAR_CACHES, [ 'LABGENZ_CM\\Articles\\Helpers\\ArticleCacheHelper', 'clear_article_caches' ] );


		add_action( 'labgenz_cm_after_article_header', [ $this, 'render_article_video' ], 20, 1 );
	}

	/**
	 * Register assets.
	 */
	public function register_assets(): void {
		// Register force visible CSS (for debugging)
		// wp_register_style(
		// self::ASSET_HANDLE_CSS . '-force-visible',
		// LABGENZ_CM_URL . 'src/Articles/assets/css/force-visible.css',
		// [],
		// '1.0.0'
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
			[ self::ASSET_HANDLE_CSS . '-improved-dropdown' ],
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
			[ self::ASSET_HANDLE_CSS . '-filter-common' ],
			'1.1.0'
		);

		// Register category filter JS
		wp_register_script(
			self::ASSET_HANDLE_JS . '-category',
			LABGENZ_CM_URL . 'src/Articles/assets/js/category-filter-new.js',
			[ 'jquery' ],
			'1.0.8',
			true
		);

				// Register author filter JS
		wp_register_script(
			self::ASSET_HANDLE_JS . '-author',
			LABGENZ_CM_URL . 'src/Articles/assets/js/author-filter.js',
			[ 'jquery' ],
			'1.0.8',
			true
		);

		// Register rating filter JS
		wp_register_script(
			self::ASSET_HANDLE_JS . '-rating',
			LABGENZ_CM_URL . 'src/Articles/assets/js/rating-filter.js',
			[ 'jquery' ],
			'1.0.7',
			true
		);

		// Register JS
		wp_register_script(
			self::ASSET_HANDLE_JS,
			LABGENZ_CM_URL . 'src/Articles/assets/js/article-cards.js',
			[ 'jquery', self::ASSET_HANDLE_JS . '-category', self::ASSET_HANDLE_JS . '-author' ],
			'1.2.5',
			true
		);
	}

/**
 * Render the articles shortcode with optimized database queries.
 *
 * @param array $atts Shortcode attributes
 * @return string
 */
public function render_articles_shortcode( array $atts = [] ): string {
	// Enqueue assets
	wp_enqueue_style( self::ASSET_HANDLE_CSS . '-improved-dropdown' );
	wp_enqueue_style( self::ASSET_HANDLE_CSS );
	wp_enqueue_script( self::ASSET_HANDLE_JS . '-author' );
	wp_enqueue_script( self::ASSET_HANDLE_JS . '-category' );
	wp_enqueue_script( self::ASSET_HANDLE_JS );
	wp_enqueue_script( self::ASSET_HANDLE_JS . '-rating' );

	// Get cached categories with counts for localization
	$categories_with_counts = $this->get_article_counts_by_meta( 'mlmmc_article_category' );

	// Localize script
	wp_localize_script(
		self::ASSET_HANDLE_JS,
		'mlmmcArticlesData',
		[
			'ajaxUrl'              => admin_url( 'admin-ajax.php' ),
			'searchAction'         => self::AJAX_ACTION_SEARCH,
			'authorsAction'        => self::AJAX_ACTION_AUTHORS,
			'categoriesAction'     => self::AJAX_ACTION_CATEGORIES,
			'nonce'                => wp_create_nonce( 'mlmmc_articles_ajax_nonce' ),
			'categoriesWithCounts' => $categories_with_counts,
			'i18n'                 => [
				'selectCategories' => __( 'Select categories', 'labgenz-community-management' ),
				'clearAll'         => __( 'Clear All', 'labgenz-community-management' ),
				'apply'            => __( 'Apply', 'labgenz-community-management' ),
				'noResults'        => __( 'No results found', 'labgenz-community-management' ),
			],
		]
	);

	$atts = shortcode_atts(
		[
			'posts_per_page' => 20,
			'category'       => '',
			'author'         => '',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'columns'        => 3,
			'show_excerpt'   => 'true',
			'excerpt_length' => 20,
			'show_author'    => 'true',
			'show_date'      => 'true',
			'show_category'  => 'true',
			'show_rating'    => 'true',
			'show_search'    => 'true',
			'show_filters'   => 'true',
		],
		$atts
	);

	// Sanitize attributes
	$posts_per_page = intval( $atts['posts_per_page'] );
	$columns        = intval( $atts['columns'] );
	$excerpt_length = intval( $atts['excerpt_length'] );
	$show_excerpt   = $atts['show_excerpt'] === 'true';
	$show_author    = $atts['show_author'] === 'true';
	$show_date      = $atts['show_date'] === 'true';
	$show_category  = $atts['show_category'] === 'true';
	$show_rating    = $atts['show_rating'] === 'true';
	$show_search    = $atts['show_search'] === 'true';
	$show_filters   = $atts['show_filters'] === 'true';

	// Build query args
	$args = [
		'post_type'      => self::POST_TYPE,
		'post_status'    => 'publish',
		'posts_per_page' => $posts_per_page,
		'orderby'        => $atts['orderby'],
		'order'          => $atts['order'],
	];

	// Add meta queries for category/author filters
	if ( ! empty( $atts['category'] ) ) {
		$args['meta_query'][] = [
			'key'     => 'mlmmc_article_category',
			'value'   => $atts['category'],
			'compare' => '=',
		];
	}

	if ( ! empty( $atts['author'] ) ) {
		$args['meta_query'][] = [
			'key'     => 'mlmmc_article_author',
			'value'   => $atts['author'],
			'compare' => '=',
		];
	}

	if ( isset( $args['meta_query'] ) && count( $args['meta_query'] ) > 1 ) {
		$args['meta_query']['relation'] = 'AND';
	}

	// Execute main query
	$query = new \WP_Query( $args );
	$total_articles = $query->found_posts;
	$max_pages = $query->max_num_pages;

	// Get all post IDs for bulk meta loading
	$post_ids = [];
	if ( $query->have_posts() ) {
		while ( $query->have_posts() ) {
			$query->the_post();
			$post_ids[] = get_the_ID();
		}
		wp_reset_postdata();
		$query->rewind_posts();
	}

	// OPTIMIZATION: Bulk load all meta data in single queries
	$all_meta = [];
	$author_images = [];
	$ratings = [];
	$rating_counts = [];

	if ( ! empty( $post_ids ) ) {
		// Load all postmeta in one query
		global $wpdb;
		$post_ids_str = implode( ',', array_map( 'intval', $post_ids ) );
		$meta_results = $wpdb->get_results( $wpdb->prepare(
			"SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} 
			 WHERE post_id IN ($post_ids_str) 
			 AND meta_key IN ('mlmmc_article_author', 'mlmmc_article_category', 'mlmmc_author_photo', 'mlmmc_video_link', %s)",
			ReviewsHandler::META_KEY_RATING
		) );

		// Organize meta by post ID
		foreach ( $meta_results as $meta ) {
			$all_meta[ $meta->post_id ][ $meta->meta_key ] = $meta->meta_value;
		}

		// Initialize handlers once
		$articles_handler = new ArticlesHandler();
		$reviews_handler = class_exists( '\LABGENZ_CM\Articles\ReviewsHandler' ) ? new ReviewsHandler() : null;

		// If we have reviews handler, get ratings in bulk
		if ( $reviews_handler ) {
			foreach ( $post_ids as $post_id ) {
				$ratings[ $post_id ] = method_exists( $reviews_handler, 'get_average_rating' ) ?
					$reviews_handler->get_average_rating( $post_id ) : 0;
				$rating_counts[ $post_id ] = method_exists( $reviews_handler, 'get_rating_count' ) ?
					$reviews_handler->get_rating_count( $post_id ) : 0;
			}
		}
	}

	// Get cached filter data
	$categories = $this->get_article_categories();
	$categories_with_counts = $this->get_article_categories_with_counts();
	$rating_counts_filter = $this->get_rating_counts();

	// Process articles with pre-loaded data
	$articles = [];
	if ( $query->have_posts() ) {
		while ( $query->have_posts() ) {
			$query->the_post();
			$post_id = get_the_ID();

			// Get metadata from bulk-loaded data
			$post_meta = $all_meta[ $post_id ] ?? [];
			
			$author_name = $post_meta['mlmmc_article_author'] ?? '';
			if ( empty( $author_name ) ) {
				$author_name = get_the_author();
			}

			$category = $post_meta['mlmmc_article_category'] ?? '';
			$average_rating = $ratings[ $post_id ] ?? 0;
			$rating_count = $rating_counts[ $post_id ] ?? 0;

			// Get images
			$thumb_url = get_the_post_thumbnail_url( $post_id, 'medium' );
			$has_thumb = ! empty( $thumb_url );

			$author_image_id = $post_meta['mlmmc_author_photo'] ?? '';
			$author_image = $author_image_id ? wp_get_attachment_image_url( $author_image_id, 'thumbnail' ) : '';

			if ( ! $author_image ) {
				$author_id = get_post_field( 'post_author', $post_id );
				$author_image = get_avatar_url( $author_id, [ 'size' => 40 ] );
			}

			// Get excerpt and video status
			$excerpt = $show_excerpt ? wp_trim_words( get_the_excerpt(), $excerpt_length, '...' ) : '';
			$mlm_video_link = $post_meta['mlmmc_video_link'] ?? '';
			$has_video = ! empty( $mlm_video_link );

			$articles[] = [
				'id'             => $post_id,
				'title'          => get_the_title(),
				'permalink'      => get_permalink(),
				'excerpt'        => $excerpt,
				'thumbnail'      => $thumb_url,
				'author_name'    => $author_name,
				'author_image'   => $author_image,
				'category'       => $category,
				'date'           => get_the_date( 'M j, Y' ),
				'average_rating' => $average_rating,
				'rating_count'   => $rating_count,
				'has_video'      => $has_video,
			];
		}
		wp_reset_postdata();
	}

	// Include template
	ob_start();
	$template_path = LABGENZ_CM_PATH . 'templates/articles/article-cards.php';
	include $template_path;
	return ob_get_clean();
}

    /**
     * Get all article categories with caching.
     *
     * @return array
     */
    public function get_article_categories(): array {
        $cache_key = ArticleCacheHelper::get_categories_cache_key(self::POST_TYPE);
        
        return ArticleCacheHelper::get_or_set($cache_key, function() {
            return ArticleMetaHelper::get_article_categories(self::POST_TYPE);
        });
    }

    /**
     * Get all article authors with caching.
     *
     * @return array
     */
    public function get_article_authors(): array {
        $cache_key = ArticleCacheHelper::get_authors_cache_key(self::POST_TYPE);
        
        return ArticleCacheHelper::get_or_set($cache_key, function() {
            return ArticleMetaHelper::get_article_authors(self::POST_TYPE);
        });
    }

    /**
     * Get article counts grouped by meta key with caching.
     *
     * @param string $meta_key The meta key to group by.
     * @return array
     */
    public function get_article_counts_by_meta($meta_key) {
        $cache_key = ArticleCacheHelper::get_counts_cache_key($meta_key, self::POST_TYPE);
        
        return ArticleCacheHelper::get_or_set($cache_key, function() use ($meta_key) {
            return ArticleMetaHelper::get_article_counts_by_meta($meta_key, self::POST_TYPE);
        });
    }

	/**
	 * AJAX handler for searching articles with caching.
	 */
	public function ajax_search_articles(): void {
		// Get search parameters
		$search_params = [
			'search_term' => sanitize_text_field($_POST['search_term'] ?? ''),
			'authors' => $_POST['authors'] ?? [],
			'categories' => $_POST['categories'] ?? [],
			'ratings' => $_POST['ratings'] ?? [],
			'page' => intval($_POST['page'] ?? 1),
			'posts_per_page' => intval($_POST['posts_per_page'] ?? 20)
		];
		
		// Try to get from cache first
		$cache_key = ArticleCacheHelper::get_search_cache_key($search_params);
		$cached_result = ArticleCacheHelper::get($cache_key);
		
		if (false !== $cached_result) {
			wp_send_json_success($cached_result);
			wp_die();
		}
		
		// If not cached, perform search and cache result
		ob_start();
		ArticleSearchHelper::handle_ajax_search(self::POST_TYPE);
		$output = ob_get_clean();
		
		// Cache the result for 15 minutes (search results change frequently)
		ArticleCacheHelper::set($cache_key, $output, 900);
		
		echo $output;
		wp_die();
	}

	/**
	 * Additional filter to ensure search respects our post type
	 *
	 * @param string   $where    The WHERE clause of the query.
	 * @param WP_Query $wp_query The WP_Query instance.
	 * @return string Modified WHERE clause.
	 */
	public function filter_search_respect_post_type( $where, $wp_query ) {
		// Only modify if this is our specific search
		if ( ! isset( $wp_query->query_vars['s'] ) || empty( $wp_query->query_vars['s'] ) ) {
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
	public function filter_search_by_title_only( $search, $wp_query ) {
		if ( empty( $search ) || ! isset( $wp_query->query_vars['s'] ) ) {
			return $search; // Return the original if not a search or no search term
		}

		global $wpdb;

		// Get the search term and escape it for SQL LIKE
		$search_term = $wpdb->esc_like( $wp_query->query_vars['s'] );
		$search_term = '%' . $search_term . '%'; // Add wildcards for LIKE query

		// Create a direct title-only search using post_title column
		$search = $wpdb->prepare(
			" AND ({$wpdb->posts}.post_title LIKE %s) ",
			$search_term
		);

		// Remove any hooks that might interfere with our custom search
		remove_all_filters( 'posts_search' );

		return $search;
	}

	/**
	 * AJAX handler for getting article authors
	 */
	public function ajax_get_article_authors(): void {
		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'mlmmc_articles_ajax_nonce' ) ) {
			wp_send_json_error( [ 'message' => 'Security check failed' ] );
			wp_die();
		}

		// Get authors
		$authors       = $this->get_article_authors();
		$author_counts = $this->get_article_counts_by_meta( 'mlmmc_article_author' );

		// Debug: Count articles directly using WP_Query for comparison
		foreach ( $authors as $author ) {
			$query_args    = ArticleSearchHelper::build_query_args( [ 'authors' => [ $author ] ], self::POST_TYPE );
			$query         = new \WP_Query( $query_args );
			$wpquery_count = $query->found_posts;
		}

		$authors_with_counts = array_map(
			function ( $author ) use ( $author_counts ) {
				return [
					'name'  => $author,
					'count' => $author_counts[ $author ] ?? 0,
				];
			},
			$authors
		);

		// Return authors
		wp_send_json_success(
			[
				'authors' => $authors_with_counts,
			]
		);

		wp_die();
	}

	/**
	 * AJAX handler for getting article categories
	 */
	public function ajax_get_article_categories(): void {
		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'mlmmc_articles_ajax_nonce' ) ) {
			wp_send_json_error( [ 'message' => 'Security check failed' ] );
			wp_die();
		}

		// Get categories with counts
		$categories_with_counts = $this->get_article_categories_with_counts();

		// Return categories
		wp_send_json_success(
			[
				'categories' => $categories_with_counts,
			]
		);

		wp_die();
	}

	/**
	 * Get article categories with counts (cached).
	 *
	 * @return array
	 */
	private function get_article_categories_with_counts(): array {
		$cache_key = ArticleCacheHelper::get_counts_cache_key('categories_with_counts', self::POST_TYPE);
		
		return ArticleCacheHelper::get_or_set($cache_key, function() {
			return ArticleMetaHelper::get_article_categories_with_counts(self::POST_TYPE);
		});
	}

	/**
	 * Get rating counts with caching.
	 *
	 * @param array $authors
	 * @param array $categories 
	 * @param array $ratings
	 * @return array
	 */
	public function get_rating_counts(array $authors = [], array $categories = [], array $ratings = []): array {
		$cache_key = ArticleCacheHelper::get_ratings_cache_key($authors, $categories, $ratings);
		
		return ArticleCacheHelper::get_or_set($cache_key, function() use ($authors, $categories, $ratings) {
			return ArticleSearchHelper::get_rating_counts($authors, $categories, $ratings);
		}, 1800); // 30 minutes for search-specific data
	}

	/**
	 * Get filtered data for AJAX response based on selected filters
	 *
	 * @param array $authors Selected authors
	 * @param array $categories Selected categories
	 * @param array $ratings Selected ratings
	 * @return array Associative array with filtered authors, categories, and ratings
	 */
	private function get_filtered_data_for_response( array $authors, array $categories, array $ratings ): array {
		return ArticleSearchHelper::get_filtered_data_for_response( $authors, $categories, $ratings, self::POST_TYPE );
	}


	/**
	 * Render the article video.
	 *
	 * @param int $post_id The ID of the post to render the video for.
	 */
	public function render_article_video( $post_id ) {
		$template_path = LABGENZ_CM_TEMPLATES_DIR . '/articles/article-video.php';

		if ( file_exists( $template_path ) ) {
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
	public function get_articles_with_videos( array $args = [] ): array {
		return ArticleMetaHelper::get_articles_with_videos( $args );
	}
}