<?php

namespace LABGENZ_CM\Articles\Helpers;

use LABGENZ_CM\Articles\ReviewsHandler;
use LABGENZ_CM\Articles\ArticlesHandler;
use LABGENZ_CM\Articles\ArticleCardDisplayHandler;
use LABGENZ_CM\Articles\Authors\AuthorDisplayHandler;
use WP_Query;

/**
 * Helper class for AJAX search functionality in articles.
 */
class ArticleSearchHelper {

	/**
	 * Validates the AJAX nonce.
	 *
	 * @param string $nonce The nonce to validate.
	 * @return bool Whether the nonce is valid.
	 */
	public static function validate_nonce( $nonce ): bool {
		return isset( $nonce ) && wp_verify_nonce( $nonce, 'mlmmc_articles_ajax_nonce' );
	}

	/**
	 * Gets search parameters from POST data.
	 *
	 * @param array $post_data The POST data.
	 * @return array Sanitized search parameters.
	 */
	public static function get_search_parameters( array $post_data ): array {
		return [
			'search_query'   => isset( $post_data['search'] ) ? sanitize_text_field( $post_data['search'] ) : '',
			'authors'        => isset( $post_data['authors'] ) && is_array( $post_data['authors'] ) ? array_map( 'sanitize_text_field', $post_data['authors'] ) : [],
			'categories'     => isset( $post_data['categories'] ) && is_array( $post_data['categories'] ) ? array_map( 'sanitize_text_field', $post_data['categories'] ) : [],
			'ratings'        => isset( $post_data['ratings'] ) && is_array( $post_data['ratings'] ) ? array_map( 'intval', $post_data['ratings'] ) : [],
			'page'           => isset( $post_data['page'] ) ? intval( $post_data['page'] ) : 1,
			'posts_per_page' => isset( $post_data['posts_per_page'] ) ? intval( $post_data['posts_per_page'] ) : 12,
			'show_excerpt'   => isset( $post_data['show_excerpt'] ) ? ( $post_data['show_excerpt'] === 'true' ) : true,
			'show_author'    => isset( $post_data['show_author'] ) ? ( $post_data['show_author'] === 'true' ) : true,
			'show_date'      => isset( $post_data['show_date'] ) ? ( $post_data['show_date'] === 'true' ) : true,
			'show_category'  => isset( $post_data['show_category'] ) ? ( $post_data['show_category'] === 'true' ) : true,
			'show_rating'    => isset( $post_data['show_rating'] ) ? ( $post_data['show_rating'] === 'true' ) : true,
			'excerpt_length' => isset( $post_data['excerpt_length'] ) ? intval( $post_data['excerpt_length'] ) : 20,
		];
	}

	/**
	 * Builds the WP_Query arguments for article search.
	 *
	 * @param array  $params Search parameters.
	 * @param string $post_type The post type to search.
	 * @return array Query arguments.
	 */
	public static function build_query_args( array $params, string $post_type ): array {
		$args = [
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => $params['posts_per_page'],
			'paged'          => $params['page'],
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		if ( ! empty( $params['search_query'] ) && trim( $params['search_query'] ) !== '' ) {
			$args['post_title_like'] = $params['search_query'];
		}

		$meta_query  = [];
		$has_filters = false;

		// Add author filter
		if ( ! empty( $params['authors'] ) ) {
			$meta_query[] = self::build_meta_filter( 'mlmmc_article_author', $params['authors'] );
			$has_filters  = true;
		}

		// Add category filter
		if ( ! empty( $params['categories'] ) ) {
			$meta_query[] = self::build_meta_filter( 'mlmmc_article_category', $params['categories'] );
			$has_filters  = true;
		}

		// Add rating filter
		if ( ! empty( $params['ratings'] ) && class_exists( '\LABGENZ_CM\Articles\ReviewsHandler' ) ) {
			$rating_subquery = [ 'relation' => 'OR' ];
			foreach ( $params['ratings'] as $rating ) {
				// Ratings are stored as floating-point values, e.g., 4.5
				// For each star rating (1-5), we want to match ratings in that range
				$min = (float) $rating;
				$max = $min + 0.999;

				$rating_subquery[] = [
					'key'     => ReviewsHandler::META_KEY_RATING,
					'value'   => [ $min, $max ],
					'compare' => 'BETWEEN',
					'type'    => 'DECIMAL(10,1)',
				];
			}
			$meta_query[] = $rating_subquery;
			$has_filters  = true;
		}

		if ( $has_filters && count( $meta_query ) > 1 ) {
			$meta_query['relation'] = 'AND';
		}

		if ( $has_filters ) {
			$args['meta_query'] = $meta_query;
		}

		return $args;
	}

/**
	 * Builds a meta query filter for a specific key.
	 *
	 * @param string $key The meta key.
	 * @param array  $values The values to filter by.
	 * @return array The meta query.
	 */
	private static function build_meta_filter( string $key, array $values ): array {
		// Special handling for category filtering
		if ( $key === 'mlmmc_article_category' ) {
			$processed_values = [];
			
			foreach ( $values as $value ) {
				// Handle "The WHY" category specifically - match exact values from JS
				if ( $value === 'the-why-' || $value === 'the-why' || preg_match('/^the[\s\-\_]*why[\s\-\_]*$/i', $value) ) {
					$processed_values[] = 'The "WHY"';
					error_log('MLMMC Debug - Matched "The WHY" from input: "' . $value . '"');
				} else if ( $value !== 'undefined' && $value !== '' ) {
					// For other categories, keep as is but filter out undefined
					$processed_values[] = $value;
					error_log('MLMMC Debug - Other category: "' . $value . '"');
				}
			}
			
			// Replace original values with processed ones
			$values = array_filter($processed_values);
			
			// Debug: Log what we're actually searching for
			error_log('MLMMC Debug - Final search values: ' . print_r($values, true));
			
			// If after filtering we have no values, return empty to avoid errors
			if ( empty($values) ) {
				return [
					'key'     => $key,
					'value'   => '',
					'compare' => '!=',
				];
			}
		}
		
		if ( count( $values ) === 1 ) {
			return [
				'key'     => $key,
				'value'   => $values[0],
				'compare' => '=',
			];
		}

		$subquery = [ 'relation' => 'OR' ];
		foreach ( $values as $value ) {
			$subquery[] = [
				'key'     => $key,
				'value'   => $value,
				'compare' => '=',
			];
		}

		return $subquery;
	}

	/**
	 * Checks if a rating is within a specific range.
	 *
	 * @param float $rating The rating to check.
	 * @param int   $star The star level (1-5).
	 * @return bool Whether the rating is within the range.
	 */
	public static function is_rating_in_range( float $rating, int $star ): bool {
		$min_rating = (float) $star;
		$max_rating = $min_rating + 0.999;
		return $rating >= $min_rating && $rating <= $max_rating;
	}

	/**
	 * Generates star HTML for display.
	 *
	 * @param float $average_rating The average rating.
	 * @return string HTML for displaying stars.
	 */
	public static function generate_stars_html( float $average_rating ): string {
		$stars_html = '';
		if ( $average_rating > 0 ) {
			$full_stars  = floor( $average_rating );
			$half_star   = $average_rating - $full_stars >= 0.5;
			$empty_stars = 5 - $full_stars - ( $half_star ? 1 : 0 );

			for ( $i = 0; $i < $full_stars; $i++ ) {
				$stars_html .= '★';
			}

			if ( $half_star ) {
				$stars_html .= '★';
			}

			for ( $i = 0; $i < $empty_stars; $i++ ) {
				$stars_html .= '☆';
			}
		}
		return $stars_html;
	}

	/**
	 * Deduplicates an array of articles based on their IDs.
	 *
	 * @param array $articles Array of article data.
	 * @return array Deduplicated array of articles.
	 */
	public static function deduplicate_articles( array $articles ): array {
		$unique_articles = [];
		$seen_ids        = [];

		foreach ( $articles as $article ) {
			if ( ! isset( $article['id'] ) || in_array( $article['id'], $seen_ids ) ) {
				continue;
			}

			$seen_ids[]        = $article['id'];
			$unique_articles[] = $article;
		}

		return $unique_articles;
	}

	/**
	 * Get filtered data for AJAX response based on selected filters
	 *
	 * @param array $authors Selected authors
	 * @param array $categories Selected categories
	 * @param array $ratings Selected ratings
	 * @return array Associative array with filtered authors, categories, and ratings
	 */
	public static function get_filtered_data_for_response( array $authors, array $categories, array $ratings ): array {
		$filtered_authors    = [];
		$filtered_categories = [];
		$filtered_ratings    = [];

		// If no ratings but categories are selected
		if ( is_array( $categories ) && count( $categories ) > 0 ) {
			$author_data = ArticleMetaHelper::get_filtered_authors_by_categories( $categories, ArticleCardDisplayHandler::POST_TYPE );
			
			// Convert authors to array with name and count
			if (is_array($author_data)) {
				foreach ($author_data as $author) {
					if (is_array($author) && isset($author['name'])) {
						$filtered_authors[] = [
							'name' => $author['name'],
							'count' => isset($author['count']) ? (int)$author['count'] : 1
						];
					} elseif (is_string($author)) {
						// Legacy support for simple string arrays
						$filtered_authors[] = [
							'name' => $author,
							'count' => 1 // Default count if not provided
						];
					}
				}
			}

			// If authors are also selected, filter categories by authors
			if ( is_array( $authors ) && count( $authors ) > 0 ) {
				$filtered_categories = ArticleMetaHelper::get_filtered_categories_by_authors( $authors, ArticleCardDisplayHandler::POST_TYPE );

				// Keep only categories that match the original category selection
				$filtered_categories = array_filter(
					$filtered_categories,
					function ( $category ) use ( $categories ) {
						return in_array( $category['name'], $categories );
					}
				);

				// Re-index array
				$filtered_categories = array_values( $filtered_categories );
			}
		}
		// If only authors are selected
		elseif ( ! empty( $authors ) ) {
			$filtered_categories = ArticleMetaHelper::get_filtered_categories_by_authors( $authors, ArticleCardDisplayHandler::POST_TYPE );
		}

		// Always filter by authors selection if provided
		if ( is_array( $authors ) && count( $authors ) > 0 ) {
			// When filtering by authors, get ALL authors with their counts
			global $wpdb;
			
			// Query to get counts for ALL authors, not just the selected ones
			$query = $wpdb->prepare(
				"SELECT pm.meta_value as name, COUNT(DISTINCT pm.post_id) as count 
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
				WHERE pm.meta_key = 'mlmmc_article_author'
				AND p.post_type = %s
				AND p.post_status = 'publish'
				GROUP BY pm.meta_value",
				ArticleCardDisplayHandler::POST_TYPE
			);
			
			$results = $wpdb->get_results($query);
			
			// Create author objects with name and count
			$filtered_authors = [];
			if ($results) {
				foreach ($results as $row) {
					$filtered_authors[] = [
						'name' => $row->name,
						'count' => (int)$row->count
					];
				}
			} else {
				// Fallback if query returns no results
				// Just include the selected authors as a minimum
				foreach ($authors as $author) {
					$filtered_authors[] = [
						'name' => $author,
						'count' => 1 // Default count if query fails
					];
				}
			}
		}

		// For ratings, always prepare data for display with counts regardless of selection
		// Run direct SQL query to get accurate counts regardless of other filters
		$article_cards = new ArticleCardDisplayHandler();
		$rating_counts = $article_cards->get_rating_counts(
			$authors,      // Pass current author filters
			$categories,   // Pass current category filters
			$ratings       // Also pass current rating filters to reflect accurate counts
		);

		// Prepare filtered ratings with counts - always show all ratings
		$filtered_ratings = [];
		for ( $i = 5; $i >= 1; $i-- ) {
			$filtered_ratings[] = [
				'rating' => $i,
				'count'  => $rating_counts[ $i ],
			];
		}

		return [
			'filtered_authors'    => $filtered_authors,
			'filtered_categories' => $filtered_categories,
			'filtered_ratings'    => $filtered_ratings,
		];
	}
	/**
	 * Process categories to handle special cases consistently
	 *
	 * @param array $categories Raw categories from request
	 * @return array Processed categories
	 */
	private static function process_categories( array $categories ): array {
		$processed_categories = [];
		
		foreach ( $categories as $category ) {
			// Handle "The WHY" category specifically - match exact values from JS
			if ( $category === 'the-why-' || $category === 'the-why' || preg_match('/^the[\s\-\_]*why[\s\-\_]*$/i', $category) ) {
				$processed_categories[] = 'The "WHY"';
				error_log('MLMMC Debug - Matched "The WHY" from category: "' . $category . '"');
			} else if ( $category !== 'undefined' && $category !== '' ) {
				// Keep other valid categories
				$processed_categories[] = $category;
			}
		}
		
		return array_filter($processed_categories);
	}
	/**
	 * Count articles by rating while respecting other filters - using exact rating matching
	 *
	 * @param array $authors Selected authors (optional)
	 * @param array $categories Selected categories (optional)
	 * @param array $ratings Selected ratings (optional)
	 * @return array Array of rating counts [5 => count, 4 => count, etc.]
	 */
	public static function get_rating_counts( array $authors = [], array $categories = [], array $ratings = [] ): array {
		$cache_key = ArticleCacheHelper::get_ratings_cache_key( $authors, $categories, $ratings );

		return ArticleCacheHelper::get_or_set(
			$cache_key,
			function () use ( $authors, $categories, $ratings ) {
				global $wpdb;
				$rating_counts = [
					5 => 0,
					4 => 0,
					3 => 0,
					2 => 0,
					1 => 0,
				];

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

					$params = [ $rating_meta_key, ReviewsHandler::POST_TYPE ];

					// Add author filter if needed
					if ( ! empty( $authors ) ) {
						$sql   .= " AND p.ID IN (
						SELECT post_id FROM {$wpdb->postmeta} 
						WHERE meta_key = 'mlmmc_article_author' AND meta_value IN (" .
						implode( ',', array_fill( 0, count( $authors ), '%s' ) ) . ')
					)';
						$params = array_merge( $params, $authors );
					}

					// Add category filter if needed
					if ( ! empty( $categories ) ) {
						$sql   .= " AND p.ID IN (
						SELECT post_id FROM {$wpdb->postmeta} 
						WHERE meta_key = 'mlmmc_article_category' AND meta_value IN (" .
						implode( ',', array_fill( 0, count( $categories ), '%s' ) ) . ')
					)';
						$params = array_merge( $params, $categories );
					}

					// Add rating filter if needed - exact matching
					if ( ! empty( $ratings ) ) {
						$placeholders = implode( ',', array_fill( 0, count( $ratings ), '%s' ) );
						$sql         .= " AND pm.meta_value IN ($placeholders)";
						$params       = array_merge( $params, array_map( 'floatval', $ratings ) );
					}

					// Group by to get counts per rating range
					$sql .= ' GROUP BY rating_floor';

					// Prepare and execute the query
					$query   = $wpdb->prepare( $sql, $params );
					$results = $wpdb->get_results( $query );

					// Process results into our standard format
					if ( $results ) {
						foreach ( $results as $row ) {
							$rating_floor = (int) $row->rating_floor;
							if ( $rating_floor >= 1 && $rating_floor <= 5 ) {
								$rating_counts[ $rating_floor ] = (int) $row->count;
							}
						}
					}
				} catch ( \Exception $e ) {
					// Silently fail and return empty counts
				}

				return $rating_counts;
			},
			900
		); // Cache for 15 minutes
	}

public static function handle_ajax_search() {
		// Check nonce
		if ( ! self::validate_nonce( $_POST['nonce'] ) ) {
			wp_send_json_error( [ 'message' => 'Security check failed' ] );
			wp_die();
		}

		// Debug log for debugging category issues
		if ( isset( $_POST['categories'] ) && is_array( $_POST['categories'] ) ) {
			error_log( 'MLMMC Debug - Raw categories in request: ' . print_r( $_POST['categories'], true ) );
		}

		// Get and process search parameters
		$search_params = [
			'search'         => isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '',
			'authors'        => isset( $_POST['authors'] ) && is_array( $_POST['authors'] ) ? array_map(
				function ( $author ) {
					return trim( wp_unslash( $author ) ); },
				$_POST['authors']
			) : [],
			'categories'     => isset( $_POST['categories'] ) && is_array( $_POST['categories'] ) ? 
				self::process_categories( array_map( 'sanitize_text_field', $_POST['categories'] ) ) : [],
			'ratings'        => isset( $_POST['ratings'] ) && is_array( $_POST['ratings'] ) ? array_map( 'intval', $_POST['ratings'] ) : [],
			'vid_only'       => isset( $_POST['vid_only'] ) && $_POST['vid_only'] === 'true' ? true : false,
			'page'           => isset( $_POST['page'] ) ? intval( $_POST['page'] ) : 1,
			'posts_per_page' => isset( $_POST['posts_per_page'] ) ? intval( $_POST['posts_per_page'] ) : 12,
			'show_excerpt'   => isset( $_POST['show_excerpt'] ) ? ( $_POST['show_excerpt'] === 'true' ) : true,
			'layout'         => isset( $_POST['layout'] ) && in_array( $_POST['layout'], [ 'grid', 'list' ] ) ? $_POST['layout'] : 'grid',
			'show_author'    => isset( $_POST['show_author'] ) ? ( $_POST['show_author'] === 'true' ) : true,
			'show_date'      => isset( $_POST['show_date'] ) ? ( $_POST['show_date'] === 'true' ) : true,
			'show_category'  => isset( $_POST['show_category'] ) ? ( $_POST['show_category'] === 'true' ) : true,
			'show_rating'    => isset( $_POST['show_rating'] ) ? ( $_POST['show_rating'] === 'true' ) : true,
			'excerpt_length' => isset( $_POST['excerpt_length'] ) ? intval( $_POST['excerpt_length'] ) : 20,
		];

		// Try to get from cache first
		$cache_key     = self::get_search_cache_key( $search_params );
		$cached_result = false;
		// $cached_result = ArticleCacheHelper::get($cache_key);

		if ( false !== $cached_result ) {
			// Add cache buster for frontend
			$cached_result['cache_buster'] = time();
			wp_send_json_success( $cached_result );
			wp_die();
		}

		// Extract parameters
		$search_query   = $search_params['search'];
		$authors        = $search_params['authors'];
		$categories     = $search_params['categories'];
		$ratings        = $search_params['ratings'];
		$vid_only       = $search_params['vid_only'];
		$page           = $search_params['page'];
		$posts_per_page = $search_params['posts_per_page'];
		$show_excerpt   = $search_params['show_excerpt'];
		$layout         = $search_params['layout'];
		$show_author    = $search_params['show_author'];
		$show_date      = $search_params['show_date'];
		$show_category  = $search_params['show_category'];
		$show_rating    = $search_params['show_rating'];
		$excerpt_length = $search_params['excerpt_length'];

		try {
			// Build query args
			$args = [
				'post_type'      => ReviewsHandler::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => $posts_per_page,
				'paged'          => $page,
				'orderby'        => 'date',
				'order'          => 'DESC',
			];

			$meta_query  = [];
			$has_filters = false;

			// Add search query for title search
			if ( ! empty( $search_query ) && trim( $search_query ) !== '' ) {
				$article_cards = new ArticleCardDisplayHandler();
				add_filter( 'posts_search', [ $article_cards, 'filter_search_by_title_only' ], 10, 2 );
				add_filter( 'posts_where', [ $article_cards, 'filter_search_respect_post_type' ], 10, 2 );
				$args['s'] = $search_query;
			}

			// Add author filter
			if ( ! empty( $authors ) ) {
				if ( count( $authors ) == 1 ) {
					$meta_query[] = [
						'key'     => 'mlmmc_article_author',
						'value'   => $authors[0],
						'compare' => '=',
					];
				} else {
					$author_subquery = [ 'relation' => 'OR' ];
					foreach ( $authors as $author ) {
						$author_subquery[] = [
							'key'     => 'mlmmc_article_author',
							'value'   => $author,
							'compare' => '=',
						];
					}
					$meta_query[] = $author_subquery;
				}
				$has_filters = true;
			}

		if ( ! empty( $search_params['categories'] ) ) {
			$categories = $search_params['categories']; // Already processed
			
			if ( count( $categories ) == 1 ) {
				$meta_query[] = [
					'key'     => 'mlmmc_article_category',
					'value'   => $categories[0],
					'compare' => '=',
				];
				error_log('MLMMC Debug - Single category query: "' . $categories[0] . '"');
			} else {
				$category_subquery = [ 'relation' => 'OR' ];
				foreach ( $categories as $category ) {
					$category_subquery[] = [
						'key'     => 'mlmmc_article_category',
						'value'   => $category,
						'compare' => '=',
					];
				}
				$meta_query[] = $category_subquery;
				error_log('MLMMC Debug - Multiple categories query: ' . print_r($categories, true));
			}
			$has_filters = true;
		}

			// Add rating filter
			if ( ! empty( $ratings ) ) {
				if ( count( $ratings ) == 1 ) {
					$meta_query[] = [
						'key'     => ReviewsHandler::META_KEY_RATING,
						'value'   => (float) $ratings[0],
						'compare' => '=',
						'type'    => 'DECIMAL(10,1)',
					];
				} else {
					$meta_query[] = [
						'key'     => ReviewsHandler::META_KEY_RATING,
						'value'   => array_map( 'floatval', $ratings ),
						'compare' => 'IN',
						'type'    => 'DECIMAL(10,1)',
					];
				}
				$has_filters = true;
			}

			// Add video-only filter
			if ( $vid_only ) {
				$meta_query[] = [
					'key'     => 'mlmmc_video_link',
					'value'   => '',
					'compare' => '!=',
				];
				$has_filters  = true;
			}

			// Set relation and add meta query
			if ( $has_filters && count( $meta_query ) > 1 ) {
				$meta_query['relation'] = 'AND';
			}
			if ( $has_filters ) {
				$args['meta_query'] = $meta_query;
			}

			// Execute query
			$query          = new \WP_Query( $args );
			$total_articles = $query->found_posts;
			$max_pages      = $query->max_num_pages;

			// Remove search filters
			if ( ! empty( $search_query ) && trim( $search_query ) !== '' ) {
				$article_cards = new ArticleCardDisplayHandler();
				remove_filter( 'posts_search', [ $article_cards, 'filter_search_by_title_only' ] );
				remove_filter( 'posts_where', [ $article_cards, 'filter_search_respect_post_type' ] );
			}

			// Process articles
			$articles_handler   = new ArticlesHandler();
			$reviews_handler    = class_exists( '\LABGENZ_CM\Articles\ReviewsHandler' ) ? new ReviewsHandler() : null;
			$articles           = [];
			$processed_post_ids = [];

			if ( $query->have_posts() ) {
				while ( $query->have_posts() ) {
					$query->the_post();
					$post_id = get_the_ID();

					if ( in_array( $post_id, $processed_post_ids ) ) {
						continue;
					}
					$processed_post_ids[] = $post_id;

					// Get metadata
					$author_name = method_exists( $articles_handler, 'get_article_author' ) ?
						$articles_handler->get_article_author( $post_id ) :
						get_post_meta( $post_id, 'mlmmc_article_author', true );

					if ( empty( $author_name ) ) {
						$author_name = get_the_author();
					}

					$category_value = method_exists( $articles_handler, 'get_article_category' ) ?
						$articles_handler->get_article_category( $post_id ) :
						get_post_meta( $post_id, 'mlmmc_article_category', true );

					$average_rating = $reviews_handler && method_exists( $reviews_handler, 'get_average_rating' ) ?
						$reviews_handler->get_average_rating( $post_id ) : 0;

					$rating_count = $reviews_handler && method_exists( $reviews_handler, 'get_rating_count' ) ?
						$reviews_handler->get_rating_count( $post_id ) : 0;

					// Get images
					$thumb_url = get_the_post_thumbnail_url( $post_id, 'medium' );
					$has_thumb = ! empty( $thumb_url );

					$author_image_id = get_post_meta( $post_id, 'mlmmc_author_photo', true );
					$author_image    = $author_image_id ? wp_get_attachment_image_url( $author_image_id, 'thumbnail' ) : '';

					if ( ! $author_image ) {
						$author_id    = get_post_field( 'post_author', $post_id );
						$author_image = get_avatar_url( $author_id, [ 'size' => 40 ] );
					}

					// Get excerpt and video
					$excerpt        = $show_excerpt ? wp_trim_words( get_the_excerpt(), $excerpt_length, '...' ) : '';
					$mlm_video_link = get_post_meta( $post_id, 'mlmmc_video_link', true );
					$has_video      = ! empty( $mlm_video_link );

					$author_display_handler = new AuthorDisplayHandler();
					$author_url             = $author_display_handler->get_author_url( $post_id );

					// Generate stars HTML
					$stars_html = '';
					if ( $show_rating && $average_rating > 0 ) {
						$stars_html = self::generate_stars_html( $average_rating );
					}

					$articles[] = [
						'id'             => $post_id,
						'title'          => get_the_title(),
						'permalink'      => get_permalink(),
						'excerpt'        => $excerpt,
						'thumbnail'      => $thumb_url,
						'has_thumbnail'  => $has_thumb,
						'author_name'    => $author_name,
						'author_image'   => $author_image,
						'category'       => $category_value,
						'date'           => get_the_date( 'M j, Y' ),
						'average_rating' => $average_rating,
						'rating_count'   => $rating_count,
						'stars_html'     => $stars_html,
						'author_url'     => $author_url,
						'has_video'      => $has_video,
					];
				}
				wp_reset_postdata();
			}

			// Deduplicate articles
			$articles = self::deduplicate_articles( $articles );

			// Generate HTML
			ob_start();
			$rendered_article_ids = [];

			if ( is_array( $articles ) && ! empty( $articles ) ) {
				foreach ( $articles as $article ) {
					if ( ! is_array( $article ) || empty( $article['id'] ) ) {
						continue;
					}

					global $post;
					$post = get_post( $article['id'] );
					if ( ! $post ) {
						continue;
					}
					setup_postdata( $post );

					$rendered_article_ids[] = $article['id'];

					// Extract variables for template
					$post_id        = $article['id'] ?? 0;
					$title          = $article['title'] ?? '';
					$permalink      = $article['permalink'] ?? '';
					$excerpt        = $article['excerpt'] ?? '';
					$thumb_url      = $article['thumbnail'] ?? '';
					$has_thumb      = $article['has_thumbnail'] ?? false;
					$author_name    = $article['author_name'] ?? '';
					$author_image   = $article['author_image'] ?? '';
					$category       = $article['category'] ?? '';
					$date           = $article['date'] ?? '';
					$average_rating = $article['average_rating'] ?? 0;
					$rating_count   = $article['rating_count'] ?? 0;
					$author_url     = $article['author_url'] ?? '';
					$stars_html     = $article['stars_html'] ?? '';
					$has_video      = $article['has_video'] ?? false;

					include LABGENZ_CM_PATH . 'templates/articles/article-card-item.php';
				}
			}

			wp_reset_postdata();
			$html = ob_get_clean();

			// Get filtered data
			try {
				$filtered_data = self::get_filtered_data_for_response( $authors, $categories, $ratings );
			} catch ( \Exception $e ) {
				$filtered_data = [
					'filtered_authors'    => [],
					'filtered_categories' => [],
					'filtered_ratings'    => [],
				];
			}

			// Prepare result
			$result = [
				'html'                => $html,
				'found_posts'         => $total_articles,
				'max_pages'           => $max_pages,
				'filtered_authors'    => $filtered_data['filtered_authors'],
				'filtered_categories' => $filtered_data['filtered_categories'],
				'filtered_ratings'    => $filtered_data['filtered_ratings'],
			];

			// Cache result for 10 minutes
			ArticleCacheHelper::set( $cache_key, $result, 600 );

			// Add cache buster and send response
			$result['cache_buster'] = time();
			wp_send_json_success( $result );
			wp_die();

		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getLine() . ' ' . $e->getMessage() ] );
			wp_die();
		} catch ( \Throwable $e ) {
			wp_send_json_error( [ 'message' => $e->getLine() . ' ' . $e->getMessage() ] );
			wp_die();
		}
	}

	/**
	 * Get cache key for search results
	 *
	 * @param array $search_params Search parameters
	 * @return string Cache key
	 */
	private static function get_search_cache_key( $search_params ) {
		// Create a normalized key based on search parameters
		$key_data = [
			'search'         => $search_params['search'] ?? '',
			'authors'        => is_array( $search_params['authors'] ) ? sort( $search_params['authors'] ) : [],
			'categories'     => is_array( $search_params['categories'] ) ? sort( $search_params['categories'] ) : [],
			'ratings'        => is_array( $search_params['ratings'] ) ? sort( $search_params['ratings'] ) : [],
			'vid_only'       => $search_params['vid_only'] ?? false,
			'page'           => $search_params['page'] ?? 1,
			'posts_per_page' => $search_params['posts_per_page'] ?? 12,
			'show_excerpt'   => $search_params['show_excerpt'] ?? true,
			'show_author'    => $search_params['show_author'] ?? true,
			'show_date'      => $search_params['show_date'] ?? true,
			'show_category'  => $search_params['show_category'] ?? true,
			'show_rating'    => $search_params['show_rating'] ?? true,
			'excerpt_length' => $search_params['excerpt_length'] ?? 20,
		];

		return ArticleCacheHelper::get_search_cache_key( $key_data );
	}
}
