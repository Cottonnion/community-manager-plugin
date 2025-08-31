<?php

namespace LABGENZ_CM\Admin;

use LABGENZ_CM\Articles\ReviewsHandler;

/**
 * Admin page for managing article reviews.
 */
class ReviewsAdmin {
	/**
	 * Menu slug for the reviews admin page.
	 */
	private const MENU_SLUG = 'article-reviews';

	/**
	 * ReviewsAdmin constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks(): void {
		// Add admin menu
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );

		// Add AJAX handlers for admin actions
		add_action( 'wp_ajax_mlmmc_delete_article_review', [ $this, 'handle_delete_review' ] );
		add_action( 'wp_ajax_mlmmc_toggle_ratings', [ $this, 'handle_toggle_ratings' ] );

		// Add admin notices
		// add_action('admin_notices', [$this, 'display_admin_notices']);

		// Note: Script enqueuing is now handled by AssetsManager
	}

	/**
	 * Add admin menu item.
	 */
	public function add_admin_menu(): void {
		add_submenu_page(
			'labgenz-cm', // Parent slug for Labgenz CM main menu
			__( 'Article Reviews', 'labgenz-cm' ),
			__( 'Article Reviews', 'labgenz-cm' ),
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'render_admin_page' ]
		);
	}

	/**
	 * Render the admin page.
	 */
	public function render_admin_page(): void {
		// Process actions
		$this->process_actions();

		// Prepare reviews data
		$reviews_data = $this->get_reviews_data();

		// Include template
		include LABGENZ_CM_TEMPLATES_DIR . '/admin/article-reviews.php';
	}

	/**
	 * Process admin actions.
	 */
	private function process_actions(): void {
		// Check if we have a valid nonce
		if (
			isset( $_POST['mlmmc_reviews_nonce'] ) &&
			wp_verify_nonce( $_POST['mlmmc_reviews_nonce'], 'mlmmc_reviews_action' )
		) {
			// Process bulk actions
			if ( isset( $_POST['action'] ) && $_POST['action'] !== '-1' ) {
				$action     = sanitize_text_field( $_POST['action'] );
				$review_ids = isset( $_POST['review_ids'] ) ? array_map( 'intval', (array) $_POST['review_ids'] ) : [];

				if ( ! empty( $review_ids ) ) {
					switch ( $action ) {
						case 'delete':
							$this->delete_reviews( $review_ids );
							break;
					}
				}
			}
		}
	}

	/**
	 * Delete selected reviews.
	 *
	 * @param array $review_ids Array of post IDs and user identifiers.
	 */
	private function delete_reviews( array $review_ids ): void {
		$deleted         = 0;
		$reviews_handler = new ReviewsHandler();

		foreach ( $review_ids as $review_id ) {
			$parts = explode( '|', $review_id );
			if ( count( $parts ) !== 2 ) {
				continue;
			}

			$post_id         = intval( $parts[0] );
			$user_identifier = sanitize_text_field( $parts[1] );

			if ( $reviews_handler->delete_user_review( $post_id, $user_identifier ) ) {
				++$deleted;
			}
		}

		if ( $deleted > 0 ) {
			// Add success notice
			update_option(
				'mlmmc_reviews_admin_notice',
				[
					'type'    => 'success',
					'message' => sprintf(
						_n(
							'%d review deleted successfully.',
							'%d reviews deleted successfully.',
							$deleted,
							'labgenz-cm'
						),
						$deleted
					),
				]
			);
		}
	}

	/**
	 * Handle review deletion via AJAX.
	 */
	public function handle_delete_review(): void {
		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to perform this action.', 'labgenz-cm' ) ] );
			return;
		}

		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'mlmmc_reviews_action' ) ) {
			wp_send_json_error( [ 'message' => __( 'Security verification failed.', 'labgenz-cm' ) ] );
			return;
		}

		// Get review data
		$post_id         = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$user_identifier = isset( $_POST['user_identifier'] ) ? sanitize_text_field( $_POST['user_identifier'] ) : '';

		if ( ! $post_id || empty( $user_identifier ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid review data.', 'labgenz-cm' ) ] );
			return;
		}

		$reviews_handler = new ReviewsHandler();
		$article_title   = get_the_title( $post_id );
		$result          = $reviews_handler->delete_user_review( $post_id, $user_identifier );

		if ( $result ) {
			wp_send_json_success(
				[
					'message' => sprintf(
						__( 'Review for "%s" deleted successfully.', 'labgenz-cm' ),
						$article_title
					),
				]
			);
		} else {
			wp_send_json_error( [ 'message' => __( 'Failed to delete review.', 'labgenz-cm' ) ] );
		}
	}

	/**
	 * Handle toggling ratings via AJAX.
	 */
	public function handle_toggle_ratings(): void {
		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to perform this action.', 'labgenz-cm' ) ] );
			return;
		}

		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'mlmmc_reviews_action' ) ) {
			wp_send_json_error( [ 'message' => __( 'Security verification failed.', 'labgenz-cm' ) ] );
			return;
		}

		// Get toggle state
		$enabled = isset( $_POST['enabled'] ) ? filter_var( $_POST['enabled'], FILTER_VALIDATE_BOOLEAN ) : false;

		// Update option
		update_option( 'mlmmc_ratings_enabled', $enabled );

		wp_send_json_success(
			[
				'message' => $enabled
					? __( 'Ratings have been enabled.', 'labgenz-cm' )
					: __( 'Ratings have been disabled.', 'labgenz-cm' ),
				'enabled' => $enabled,
			]
		);
	}

	/**
	 * Display admin notices.
	 */
	public function display_admin_notices(): void {
		$current_screen = get_current_screen();
		if ( ! $current_screen || $current_screen->id !== 'labgenz-cm_page_' . self::MENU_SLUG ) {
			return;
		}

		$notice = get_option( 'mlmmc_reviews_admin_notice' );
		if ( ! $notice ) {
			return;
		}

		$class   = 'notice notice-' . esc_attr( $notice['type'] );
		$message = esc_html( $notice['message'] );

		printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );

		// Remove the notice after displaying
		delete_option( 'mlmmc_reviews_admin_notice' );
	}

	/**
	 * Get all reviews data for the admin table.
	 *
	 * @return array
	 */
	private function get_reviews_data(): array {
		$reviews_data    = [];
		$reviews_handler = new ReviewsHandler();

		// Get all articles
		$args = [
			'post_type'      => ReviewsHandler::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		];

		$articles = get_posts( $args );

		foreach ( $articles as $article ) {
			$user_ratings = get_post_meta( $article->ID, ReviewsHandler::META_KEY_USER_RATINGS, true );
			if ( ! $user_ratings || ! is_array( $user_ratings ) ) {
				continue;
			}

			foreach ( $user_ratings as $user_identifier => $rating ) {
				$user_info = $this->get_user_info( $user_identifier );

				$reviews_data[] = [
					'post_id'         => $article->ID,
					'article_title'   => $article->post_title,
					'article_url'     => get_permalink( $article->ID ),
					'user_identifier' => $user_identifier,
					'user_name'       => $user_info['name'],
					'user_email'      => $user_info['email'],
					'user_type'       => $user_info['type'],
					'rating'          => $rating,
					'review_id'       => $article->ID . '|' . $user_identifier,
				];
			}
		}

		// Sort reviews by article title and then by rating (descending)
		usort(
			$reviews_data,
			function ( $a, $b ) {
				if ( $a['article_title'] === $b['article_title'] ) {
					return $b['rating'] <=> $a['rating']; // Sort by rating descending
				}
				return $a['article_title'] <=> $b['article_title']; // Sort by article title ascending
			}
		);

		return $reviews_data;
	}

	/**
	 * Get user information from user identifier.
	 *
	 * @param string $user_identifier
	 * @return array
	 */
	private function get_user_info( string $user_identifier ): array {
		// Check if this is a user ID
		if ( is_numeric( $user_identifier ) ) {
			$user = get_user_by( 'id', $user_identifier );
			if ( $user ) {
				return [
					'name'  => $user->display_name,
					'email' => $user->user_email,
					'type'  => 'registered',
				];
			}
		}

		// If not a user ID or user not found, it's a hashed IP
		return [
			'name'  => __( 'Anonymous User', 'labgenz-cm' ),
			'email' => __( 'Guest', 'labgenz-cm' ),
			'type'  => 'guest',
		];
	}
}
