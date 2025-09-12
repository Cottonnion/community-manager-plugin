<?php

namespace LABGENZ_CM\Articles\Helpers;

class ArticleMetaHelper {

	/**
	 * Get article counts grouped by a specific meta key (e.g., author, category, or rating).
	 *
	 * @param string $meta_key The meta key to group by.
	 * @param string $post_type The post type to query.
	 * @return array Associative array of meta values and their corresponding article counts.
	 */
	public static function get_article_counts_by_meta( string $meta_key, string $post_type ): array {
		global $wpdb;

		// Direct SQL query to count posts by meta value - much more efficient
		$query = $wpdb->prepare(
			"SELECT pm.meta_value, COUNT(DISTINCT pm.post_id) as count 
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			WHERE pm.meta_key = %s
			AND p.post_type = %s
			AND p.post_status = 'publish'
			AND pm.meta_value != ''
			GROUP BY pm.meta_value",
			$meta_key,
			$post_type
		);

		$results = $wpdb->get_results( $query );
		$counts  = [];

		if ( ! empty( $results ) ) {
			foreach ( $results as $result ) {
				$counts[ $result->meta_value ] = (int) $result->count;
			}
		}

		return $counts;
	}

	/**
	 * Get all article categories.
	 *
	 * @param string $post_type The post type to query.
	 * @return array
	 */
	public static function get_article_categories( string $post_type ): array {
		global $wpdb;

		$categories = [];

		$meta_values = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT meta_value FROM {$wpdb->postmeta} 
                WHERE meta_key = %s AND meta_value != ''",
				'mlmmc_article_category'
			)
		);

		if ( ! empty( $meta_values ) ) {
			$categories = array_unique( $meta_values );
		}

		return $categories;
	}

	/**
	 * Get all article authors.
	 *
	 * @param string $post_type The post type to query.
	 * @return array
	 */
	public static function get_article_authors( string $post_type ): array {
		global $wpdb;

		$authors = [];

		$meta_values = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT meta_value FROM {$wpdb->postmeta} 
                WHERE meta_key = %s AND meta_value != ''",
				'mlmmc_article_author'
			)
		);

		if ( ! empty( $meta_values ) ) {
			$authors = array_unique( $meta_values );
		}

		return $authors;
	}

	/**
	 * Get article categories with their counts.
	 *
	 * @param string $post_type The post type to query.
	 * @return array
	 */
	public static function get_article_categories_with_counts( string $post_type ): array {
		$categories = [];

		// Get all unique category values
		$all_categories = self::get_article_categories( $post_type );

		// Get counts for each category
		$category_counts = self::get_article_counts_by_meta( 'mlmmc_article_category', $post_type );

		// Format the results
		foreach ( $all_categories as $category ) {
			// Create a slug for the category
			$slug = sanitize_title( $category );

			$categories[] = [
				'name'  => $category,
				'slug'  => $slug,
				'count' => isset( $category_counts[ $category ] ) ? (int) $category_counts[ $category ] : 0,
			];
		}

		return $categories;
	}

	/**
	 * Get filtered authors based on category selection
	 *
	 * @param array  $categories Selected categories
	 * @param string $post_type Post type to filter
	 * @return array Filtered authors with counts
	 */
	public static function get_filtered_authors_by_categories( array $categories, string $post_type ): array {
		if ( empty( $categories ) ) {
			return [];
		}

		$filtered_authors = [];

		// Build a query to get all authors who have articles in these categories
		$authors_query_args = [
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids', // Only get post IDs for efficiency
		];

		// Add category filter
		if ( count( $categories ) == 1 ) {
			$authors_query_args['meta_query'] = [
				[
					'key'     => 'mlmmc_article_category',
					'value'   => $categories[0],
					'compare' => '=',
				],
			];
		} else {
			$category_subquery = [ 'relation' => 'OR' ];
			foreach ( $categories as $category ) {
				$category_subquery[] = [
					'key'     => 'mlmmc_article_category',
					'value'   => $category,
					'compare' => '=',
				];
			}
			$authors_query_args['meta_query'] = $category_subquery;
		}

		// Get all post IDs matching the category filter
		$filtered_posts    = new \WP_Query( $authors_query_args );
		$filtered_post_ids = $filtered_posts->posts;

		// Extract authors from these posts
		if ( ! empty( $filtered_post_ids ) ) {
			$author_counts = [];
			foreach ( $filtered_post_ids as $post_id ) {
				$author = get_post_meta( $post_id, 'mlmmc_article_author', true );
				if ( ! empty( $author ) ) {
					if ( ! isset( $author_counts[ $author ] ) ) {
						$author_counts[ $author ] = 0;
					}
					++$author_counts[ $author ];
				}
			}

			// Format for response
			foreach ( $author_counts as $author => $count ) {
				$filtered_authors[] = [
					'name'  => $author,
					'count' => $count,
				];
			}
		}

		return $filtered_authors;
	}

	/**
	 * Get filtered categories based on author selection
	 *
	 * @param array  $authors Selected authors
	 * @param string $post_type Post type to filter
	 * @return array Filtered categories with counts
	 */
	public static function get_filtered_categories_by_authors( array $authors, string $post_type ): array {
		if ( empty( $authors ) ) {
			return [];
		}

		$filtered_categories = [];

		// Build a query to get all categories that have articles by these authors
		$categories_query_args = [
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids', // Only get post IDs for efficiency
		];

		// Add author filter
		if ( count( $authors ) == 1 ) {
			$categories_query_args['meta_query'] = [
				[
					'key'     => 'mlmmc_article_author',
					'value'   => $authors[0],
					'compare' => '=',
				],
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
			$categories_query_args['meta_query'] = $author_subquery;
		}

		// Get all post IDs matching the author filter
		$filtered_posts    = new \WP_Query( $categories_query_args );
		$filtered_post_ids = $filtered_posts->posts;

		// Extract categories from these posts
		if ( ! empty( $filtered_post_ids ) ) {
			$category_counts = [];
			foreach ( $filtered_post_ids as $post_id ) {
				$category = get_post_meta( $post_id, 'mlmmc_article_category', true );
				if ( ! empty( $category ) ) {
					if ( ! isset( $category_counts[ $category ] ) ) {
						$category_counts[ $category ] = 0;
					}
					++$category_counts[ $category ];
				}
			}

			// Format for response
			foreach ( $category_counts as $category => $count ) {
				$filtered_categories[] = [
					'name'  => $category,
					'count' => $count,
				];
			}
		}

		return $filtered_categories;
	}

	/**
	 * Get filtered categories and authors based on rating selection
	 *
	 * @param array  $ratings Selected ratings
	 * @param string $post_type Post type to filter
	 * @return array Associative array with filtered categories and authors
	 *
	 * @internal Currently unused, reserved for future use.
	 */
	public static function get_filtered_items_by_ratings( array $ratings, string $post_type ): array {
		if ( empty( $ratings ) ) {
			return [
				'categories' => [],
				'authors'    => [],
			];
		}

		// Rating query can be complex - we'll use the post IDs approach
		$reviews_handler = class_exists( '\LABGENZ_CM\Articles\ReviewsHandler' ) ? new \LABGENZ_CM\Articles\ReviewsHandler() : null;

		if ( ! $reviews_handler || ! method_exists( $reviews_handler, 'get_average_rating' ) ) {
			return [
				'categories' => [],
				'authors'    => [],
			];
		}

		// Get all published posts of the type
		$posts_query = new \WP_Query(
			[
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);

		$all_post_ids      = $posts_query->posts;
		$filtered_post_ids = [];

		// Filter posts by rating
		foreach ( $all_post_ids as $post_id ) {
			$average_rating = $reviews_handler->get_average_rating( $post_id );

			if ( $average_rating > 0 ) {
				// Check if rating matches any of the selected rating criteria
				foreach ( $ratings as $rating ) {
					if ( $average_rating >= $rating && $average_rating < ( $rating + 1 ) ) {
						$filtered_post_ids[] = $post_id;
						break;
					}
				}
			}
		}

		// Return empty arrays if no posts match the rating criteria
		if ( empty( $filtered_post_ids ) ) {
			return [
				'categories' => [],
				'authors'    => [],
			];
		}

		// Extract categories and authors from the filtered posts
		$category_counts = [];
		$author_counts   = [];

		foreach ( $filtered_post_ids as $post_id ) {
			// Get category
			$category = get_post_meta( $post_id, 'mlmmc_article_category', true );
			if ( ! empty( $category ) ) {
				if ( ! isset( $category_counts[ $category ] ) ) {
					$category_counts[ $category ] = 0;
				}
				++$category_counts[ $category ];
			}

			// Get author
			$author = get_post_meta( $post_id, 'mlmmc_article_author', true );
			if ( ! empty( $author ) ) {
				if ( ! isset( $author_counts[ $author ] ) ) {
					$author_counts[ $author ] = 0;
				}
				++$author_counts[ $author ];
			}
		}

		// Format categories for response
		$filtered_categories = [];
		foreach ( $category_counts as $category => $count ) {
			$filtered_categories[] = [
				'name'  => $category,
				'count' => $count,
			];
		}

		// Format authors for response
		$filtered_authors = [];
		foreach ( $author_counts as $author => $count ) {
			$filtered_authors[] = [
				'name'  => $author,
				'count' => $count,
			];
		}

		return [
			'categories' => $filtered_categories,
			'authors'    => $filtered_authors,
		];
	}

	/**
	 * Get articles with videos only.
	 *
	 * @param array $args Query arguments.
	 * @return array List of articles with videos.
	 */
	public function get_articles_with_videos( array $args = [] ): array {
		// Ensure post type is set
		$args['post_type']   = self::POST_TYPE;
		$args['post_status'] = 'publish';

		// Add meta query to filter articles with a non-empty video link
		$args['meta_query'][] = [
			'key'     => 'mlmmc_video_link',
			'value'   => '',
			'compare' => '!=',
		];

		// Query articles
		$query    = new \WP_Query( $args );
		$articles = [];

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$post_id = get_the_ID();

				// Add article data to the result
				$articles[] = [
					'id'         => $post_id,
					'title'      => get_the_title(),
					'permalink'  => get_permalink(),
					'video_link' => get_post_meta( $post_id, 'mlmmc_video_link', true ),
				];
			}
			wp_reset_postdata();
		}

		return $articles;
	}
}
