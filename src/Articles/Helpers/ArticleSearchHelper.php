<?php

namespace LABGENZ_CM\Articles\Helpers;

use LABGENZ_CM\Articles\ReviewsHandler;
use LABGENZ_CM\Articles\ArticleCardDisplayHandler;
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
		if ( ! empty( $categories ) ) {
			$filtered_authors = ArticleMetaHelper::get_filtered_authors_by_categories( $categories, ArticleCardDisplayHandler::POST_TYPE );

			// If authors are also selected, filter categories by authors
			if ( ! empty( $authors ) ) {
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
}
