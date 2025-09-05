<?php

namespace LABGENZ_CM\Articles;

/**
 * Handles article reviews functionality.
 */
class ReviewsHandler {
	public const META_KEY_RATING       = 'mlmmc_article_rating';
	public const META_KEY_RATING_COUNT = 'mlmmc_article_rating_count';
	public const META_KEY_USER_RATINGS = 'mlmmc_article_user_ratings';
	public const POST_TYPE             = 'mlmmc_artiicle'; // Note the double 'i' in 'artiicle'
	public const NONCE_ACTION          = 'mlmmc_article_review';
	public const NONCE_NAME            = 'mlmmc_article_review_nonce';

	/**
	 * Asset handles for CSS and JS
	 */
	public const ASSET_HANDLE_CSS = 'labgenz-cm-article-reviews';
	public const ASSET_HANDLE_JS  = 'labgenz-cm-article-reviews';

	/**
	 * ReviewsHandler constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize class hooks.
	 */
	private function init_hooks(): void {
		// Add reviews to article content
		add_filter( 'the_content', [ $this, 'append_reviews_to_content' ], 20 );

		// Add AJAX handlers for submitting and editing reviews
		add_action( 'wp_ajax_mlmmc_submit_article_review', [ $this, 'handle_review_submission' ] );
		add_action( 'wp_ajax_nopriv_mlmmc_submit_article_review', [ $this, 'handle_review_submission' ] );

		add_action( 'wp_ajax_mlmmc_edit_article_review', [ $this, 'handle_review_edit' ] );
	}

	/**
	 * Handle the review submission via AJAX.
	 */
	public function handle_review_submission(): void {
		// Check if ratings are enabled
		$ratings_enabled = get_option( 'mlmmc_ratings_enabled', true );
		if ( ! $ratings_enabled ) {
			wp_send_json_error( [ 'message' => __( 'Ratings have been disabled by the administrator.', 'labgenz-cm' ) ] );
			return;
		}

		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], self::NONCE_ACTION ) ) {
			wp_send_json_error( [ 'message' => __( 'Security verification failed.', 'labgenz-cm' ) ] );
			return;
		}

		// Get and validate post ID
		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		if ( ! $post_id || ! get_post( $post_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid article.', 'labgenz-cm' ) ] );
			return;
		}

		// Get and validate rating
		$rating = isset( $_POST['rating'] ) ? intval( $_POST['rating'] ) : 0;
		if ( $rating < 1 || $rating > 5 ) {
			wp_send_json_error( [ 'message' => __( 'Invalid rating value.', 'labgenz-cm' ) ] );
			return;
		}

		// Get user identifier (user ID for logged in users or IP for guests)
		$user_id         = get_current_user_id();
		$user_identifier = $user_id ? $user_id : $this->get_user_ip();

		// Check if user has already rated this post
		$user_ratings = get_post_meta( $post_id, self::META_KEY_USER_RATINGS, true );
		if ( ! $user_ratings ) {
			$user_ratings = [];
		}

		if ( isset( $user_ratings[ $user_identifier ] ) ) {
			wp_send_json_error(
				[
					'message'  => __( 'You have already rated this article. Use the edit option to change your rating.', 'labgenz-cm' ),
					'can_edit' => true,
				]
			);
			return;
		}

		// Add the new rating
		$user_ratings[ $user_identifier ] = $rating;
		update_post_meta( $post_id, self::META_KEY_USER_RATINGS, $user_ratings );

		// Update the average rating
		$this->update_average_rating( $post_id );

		// Return success
		wp_send_json_success(
			[
				'message'     => __( 'Thank you for your rating!', 'labgenz-cm' ),
				'average'     => $this->get_average_rating( $post_id ),
				'count'       => $this->get_rating_count( $post_id ),
				'user_rating' => $rating,
			]
		);
	}

	/**
	 * Handle editing an existing review via AJAX.
	 */
	public function handle_review_edit(): void {
		// Check if ratings are enabled
		$ratings_enabled = get_option( 'mlmmc_ratings_enabled', true );
		if ( ! $ratings_enabled ) {
			wp_send_json_error( [ 'message' => __( 'Ratings have been disabled by the administrator.', 'labgenz-cm' ) ] );
			return;
		}

		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], self::NONCE_ACTION ) ) {
			wp_send_json_error( [ 'message' => __( 'Security verification failed.', 'labgenz-cm' ) ] );
			return;
		}

		// Get and validate post ID
		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		if ( ! $post_id || ! get_post( $post_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid article.', 'labgenz-cm' ) ] );
			return;
		}

		// Get and validate rating
		$rating = isset( $_POST['rating'] ) ? intval( $_POST['rating'] ) : 0;
		if ( $rating < 1 || $rating > 5 ) {
			wp_send_json_error( [ 'message' => __( 'Invalid rating value.', 'labgenz-cm' ) ] );
			return;
		}

		// Get user identifier (user ID for logged in users or IP for guests)
		$user_id         = get_current_user_id();
		$user_identifier = $user_id ? $user_id : $this->get_user_ip();

		// Check if user has rated this post
		$user_ratings = get_post_meta( $post_id, self::META_KEY_USER_RATINGS, true );
		if ( ! $user_ratings || ! is_array( $user_ratings ) || ! isset( $user_ratings[ $user_identifier ] ) ) {
			wp_send_json_error( [ 'message' => __( 'You have not rated this article yet.', 'labgenz-cm' ) ] );
			return;
		}

		// Update the rating
		$old_rating                       = $user_ratings[ $user_identifier ];
		$user_ratings[ $user_identifier ] = $rating;
		update_post_meta( $post_id, self::META_KEY_USER_RATINGS, $user_ratings );

		// Update the average rating
		$this->update_average_rating( $post_id );

		// Return success
		wp_send_json_success(
			[
				'message'     => __( 'Your rating has been updated!', 'labgenz-cm' ),
				'average'     => $this->get_average_rating( $post_id ),
				'count'       => $this->get_rating_count( $post_id ),
				'user_rating' => $rating,
			]
		);
	}

	/**
	 * Get the user IP address.
	 *
	 * @return string
	 */
	private function get_user_ip(): string {
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		return md5( $ip ); // Hash the IP for privacy
	}

	/**
	 * Update the average rating for a post.
	 *
	 * @param int $post_id
	 */
	private function update_average_rating( int $post_id ): void {
		$user_ratings = get_post_meta( $post_id, self::META_KEY_USER_RATINGS, true );
		if ( ! $user_ratings || ! is_array( $user_ratings ) ) {
			update_post_meta( $post_id, self::META_KEY_RATING, 0 );
			update_post_meta( $post_id, self::META_KEY_RATING_COUNT, 0 );
			return;
		}

		$count   = count( $user_ratings );
		$total   = array_sum( $user_ratings );
		$average = $count > 0 ? round( $total / $count, 1 ) : 0;

		update_post_meta( $post_id, self::META_KEY_RATING, $average );
		update_post_meta( $post_id, self::META_KEY_RATING_COUNT, $count );
	}

	/**
	 * Get the average rating for a post.
	 *
	 * @param int $post_id
	 * @return float|null
	 */
	public function get_average_rating( int $post_id ): ?float {
		$rating_count = $this->get_rating_count( $post_id );
		if ( $rating_count === 0 ) {
			return null;
		}

		$rating = get_post_meta( $post_id, self::META_KEY_RATING, true );
		return $rating ? (float) $rating : 0;
	}
	
	/**
	 * Get the average rating for all articles by an author.
	 *
	 * @param string $author_name The name of the author
	 * @return array An array containing average rating and total articles count
	 */
	public function get_author_average_rating( string $author_name ): array {
		if ( empty( $author_name ) ) {
			return [
				'average'       => 0,
				'article_count' => 0,
				'rated_count'   => 0,
			];
		}
		
		// Query for all published articles by this author
		$args = [
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids', // Only get post IDs for efficiency
			'meta_query'     => [
				[
					'key'     => 'mlmmc_article_author',
					'value'   => $author_name,
					'compare' => 'LIKE',
				]
			]
		];
		
		$articles = new \WP_Query( $args );
		$article_count = $articles->found_posts;
		
		if ( $article_count === 0 ) {
			return [
				'average'       => 0,
				'article_count' => 0,
				'rated_count'   => 0,
			];
		}
		
		// Calculate sum of all ratings
		$total_rating = 0;
		$rated_articles_count = 0;
		
		foreach ( $articles->posts as $article_id ) {
			$article_rating = $this->get_average_rating( $article_id );
			$rating_count = $this->get_rating_count( $article_id );
			
			if ( $article_rating !== null && $rating_count > 0 ) {
				$total_rating += $article_rating;
				$rated_articles_count++;
			}
		}
		
		// Calculate average rating
		$average_rating = $rated_articles_count > 0 ? round( $total_rating / $rated_articles_count, 1 ) : 0;
		
		return [
			'average'       => $average_rating,
			'article_count' => $article_count,
			'rated_count'   => $rated_articles_count,
		];
	}

	/**
	 * Get the number of ratings for a post.
	 *
	 * @param int $post_id
	 * @return int
	 */
	public function get_rating_count( int $post_id ): int {
		$count = get_post_meta( $post_id, self::META_KEY_RATING_COUNT, true );
		return $count ? (int) $count : 0;
	}

	/**
	 * Check if the current user has already rated a post.
	 *
	 * @param int $post_id
	 * @return bool
	 */
	public function has_user_rated( int $post_id ): bool {
		$user_id         = get_current_user_id();
		$user_identifier = $user_id ? $user_id : $this->get_user_ip();

		$user_ratings = get_post_meta( $post_id, self::META_KEY_USER_RATINGS, true );
		if ( ! $user_ratings || ! is_array( $user_ratings ) ) {
			return false;
		}

		return isset( $user_ratings[ $user_identifier ] );
	}

	/**
	 * Get the current user's rating for a post.
	 *
	 * @param int $post_id
	 * @return int|null Rating value or null if user hasn't rated
	 */
	public function get_user_rating( int $post_id ): ?int {
		$user_id         = get_current_user_id();
		$user_identifier = $user_id ? $user_id : $this->get_user_ip();

		$user_ratings = get_post_meta( $post_id, self::META_KEY_USER_RATINGS, true );
		if ( ! $user_ratings || ! is_array( $user_ratings ) || ! isset( $user_ratings[ $user_identifier ] ) ) {
			return null;
		}

		return (int) $user_ratings[ $user_identifier ];
	}

	/**
	 * Delete a specific user's review for a post.
	 *
	 * @param int    $post_id The post ID
	 * @param string $user_identifier The user identifier (user ID or hashed IP)
	 * @return bool True if review was deleted, false otherwise
	 */
	public function delete_user_review( int $post_id, string $user_identifier ): bool {
		$user_ratings = get_post_meta( $post_id, self::META_KEY_USER_RATINGS, true );

		if ( ! $user_ratings || ! is_array( $user_ratings ) || ! isset( $user_ratings[ $user_identifier ] ) ) {
			return false;
		}

		// Remove the rating
		unset( $user_ratings[ $user_identifier ] );

		// Update the user ratings
		update_post_meta( $post_id, self::META_KEY_USER_RATINGS, $user_ratings );

		// Update the average rating
		$this->update_average_rating( $post_id );

		return true;
	}

	/**
	 * Append reviews to article content
	 *
	 * @param string $content The post content
	 * @return string Modified content with reviews
	 */
	public function append_reviews_to_content( string $content ): string {
		// Only add reviews to single article post type
		if ( ! is_singular( self::POST_TYPE ) ) {
			return $content;
		}

		// Check if ratings are enabled
		$ratings_enabled = get_option( 'mlmmc_ratings_enabled', true );
		if ( ! $ratings_enabled ) {
			return $content;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $content;
		}

		// Check if reviews content is already appended to prevent duplication
		if ( strpos( $content, 'article-rating-container' ) !== false ) {
			return $content;
		}

		$reviews_html = $this->render_reviews( $post_id );

		return $content . $reviews_html;
	}

	/**
	 * Render reviews for a post
	 *
	 * @param int   $post_id
	 * @param array $args Additional arguments
	 * @return string
	 */
	public function render_reviews( int $post_id, array $args = [] ): string {
		$args = wp_parse_args(
			$args,
			[
				'show_count' => true,
				'class'      => '',
			]
		);

		// Load template
		ob_start();
		include LABGENZ_CM_TEMPLATES_DIR . '/reviews/article-rating.php';
		return ob_get_clean();
	}
}
