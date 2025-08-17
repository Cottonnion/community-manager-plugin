<?php

namespace LABGENZ_CM\Articles;

use LABGENZ_CM\Articles\ReviewsHandler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles article download functionality using client-side PDF generation.
 *
 * @package Labgenz_Community_Management
 * @subpackage Labgenz_Community_Management/Articles
 */
class SingleArticleHandler {

	/**
	 * Asset handles for JS and CSS
	 */
	public const ASSET_HANDLE_JS  = 'labgenz-cm-article-download';
	public const ASSET_HANDLE_CSS = 'labgenz-cm-article-download-css';

	/**
	 * ReviewsHandler instance
	 *
	 * @var ReviewsHandler
	 */
	private ReviewsHandler $reviews_handler;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->reviews_handler = new ReviewsHandler();
		$this->init_hooks();
	}

	/**
	 * Initialize class hooks.
	 */
	private function init_hooks(): void {
		// Enqueue scripts
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		// Add footer and header to front-end article pages
		add_action( 'the_content', [ $this, 'add_article_footer_content' ] );
		add_action( 'the_content', [ $this, 'add_article_header_content' ] );

		// Add AJAX handler for article data retrieval
		add_action( 'wp_ajax_get_article_data_for_pdf', [ $this, 'get_article_data_for_pdf' ] );
		add_action( 'wp_ajax_nopriv_get_article_data_for_pdf', [ $this, 'get_article_data_for_pdf' ] );
	}

	/**
	 * Enqueue required scripts.
	 */
	public function enqueue_scripts(): void {
		// Only load on article review page or single article pages
		$screen             = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_article_reviews = $screen && strpos( $screen->id, 'article-reviews' ) !== false;
		$is_single_article  = is_singular( ReviewsHandler::POST_TYPE );

		if ( ! $is_article_reviews && ! $is_single_article ) {
			return;
		}

		// Register and enqueue the html2pdf library from CDN
		wp_register_script(
			'html2pdf',
			'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js',
			[],
			'0.10.1',
			true
		);

		// Register and enqueue our custom script
		wp_register_script(
			self::ASSET_HANDLE_JS,
			plugin_dir_url( dirname( __DIR__ ) ) . 'src/Articles/assets/js/article-download.js',
			[ 'jquery', 'html2pdf' ],
			'1.1.3',
			true
		);

		// Register and enqueue our custom CSS
		wp_register_style(
			self::ASSET_HANDLE_CSS,
			plugin_dir_url( dirname( __DIR__ ) ) . 'src/Articles/assets/css/article-download.css',
			[],
			'1.0.3'
		);

		// Localize script with AJAX URL and nonce
		wp_localize_script(
			self::ASSET_HANDLE_JS,
			'labgenzArticleDownload',
			[
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'article_download_nonce' ),
				'dateFormat'      => get_option( 'date_format' ),
				'pdfTitle'        => __( 'Article PDF', 'labgenz-cm' ),
				'downloadingText' => __( 'Generating PDF...', 'labgenz-cm' ),
				'authorLabel'     => __( 'Author', 'labgenz-cm' ),
				'dateLabel'       => __( 'Date', 'labgenz-cm' ),
				'ratingLabel'     => __( 'Rating', 'labgenz-cm' ),
				'reviewsLabel'    => __( 'Reviews', 'labgenz-cm' ),
				'sourceLabel'     => __( 'Source', 'labgenz-cm' ),
			]
		);

		// Enqueue scripts and styles
		wp_enqueue_script( 'html2pdf' );
		wp_enqueue_script( self::ASSET_HANDLE_JS );
		wp_enqueue_style( self::ASSET_HANDLE_CSS );
	}

	/**
	 * Add footer content to content.
	 *
	 * @param string $content The post content
	 * @return string Modified content with footer content
	 */
	public function add_article_footer_content( string $content ): string {
		// Only add the button on single article pages in the frontend
		if ( ! is_singular( ReviewsHandler::POST_TYPE ) ) {
			return $content;
		}

		$post_id = get_the_ID();

		// Load the footer template
		ob_start();
		include LABGENZ_CM_TEMPLATES_DIR . '/articles/mlm-article-footer.php';
		$footer = ob_get_clean();

		// Add footer at the beginning of the content
		return $content . $footer;
	}

	/**
	 * Add footer content to content.
	 *
	 * @param string $content The post content
	 * @return string Modified content with footer content
	 */
	public function add_article_header_content( string $content ): string {
		// Only add the button on single article pages in the frontend
		if ( ! is_singular( ReviewsHandler::POST_TYPE ) ) {
			return $content;
		}

		$post_id = get_the_ID();

		// Load the header template
		ob_start();
		include LABGENZ_CM_TEMPLATES_DIR . '/articles/mlm-article-header.php';
		$header = ob_get_clean();

		// Add header at the beginning of the content
		return $header . $content;
	}

	/**
	 * Handle AJAX request to get article data for PDF generation.
	 */
	public function get_article_data_for_pdf(): void {
		// Verify nonce
		if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( $_REQUEST['nonce'], 'article_download_nonce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Security verification failed.', 'labgenz-cm' ) ] );
			return;
		}

		// Get article ID
		$article_id = isset( $_REQUEST['article_id'] ) ? intval( $_REQUEST['article_id'] ) : 0;
		if ( ! $article_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid article ID.', 'labgenz-cm' ) ] );
			return;
		}

		$article = get_post( $article_id );
		if ( ! $article ) {
			wp_send_json_error( [ 'message' => __( 'Article not found.', 'labgenz-cm' ) ] );
			return;
		}

		// Get article data
		$content = apply_filters( 'the_content', $article->post_content );

		// Get review data
		$avg_rating   = $this->reviews_handler->get_average_rating( $article_id );
		$rating_count = $this->reviews_handler->get_rating_count( $article_id );

		// Format date
		$date = get_the_date( '', $article_id );

		// Get WordPress author
		$author_id   = $article->post_author;
		$author_name = get_the_author_meta( 'display_name', $author_id );

		// Get ACF author using the ArticlesHandler method
		$acf_author       = '';
		$articles_handler = new \LABGENZ_CM\Articles\ArticlesHandler();
		if ( method_exists( $articles_handler, 'get_article_author' ) ) {
			$acf_author = $articles_handler->get_article_author( $article_id );
		}

		if ( method_exists( $articles_handler, 'get_article_category' ) ) {
			$category = $articles_handler->get_article_category( $article_id );
		} else {
			$category = '';
		}

		// Send response
		wp_send_json_success(
			[
				'title'          => get_the_title( $article_id ),
				'content'        => $content,
				'date'           => $date,
				'author'         => $author_name,
				'acf_author'     => $acf_author,
				'category'       => $category,
				'average_rating' => $avg_rating,
				'rating_count'   => $rating_count,
				'permalink'      => get_permalink( $article_id ),
			]
		);
	}
}
