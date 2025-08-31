<?php

declare(strict_types=1);

namespace LABGENZ_CM\Articles\Authors;

defined( 'ABSPATH' ) || exit;

/**
 * Class AuthorDisplayHandler
 *
 * Handles the display logic for single author pages.
 */
class AuthorDisplayHandler {

	public function __construct() {
		// Hook into template redirect to handle single author display
		add_action( 'template_redirect', [ $this, 'maybe_render_single_author' ] );
		add_filter( 'template_include', [ $this, 'register_authors_archive_template' ] );
	}

	/**
	 * Check if we're on a single author page and render custom output.
	 */
	public function maybe_render_single_author() {
		if ( is_singular( AuthorCPT::POST_TYPE ) ) {
			$this->render_single_author( get_queried_object_id() );
			exit;
		}
	}


	/**
	 * Register the custom authors archive template if it exists in the plugin.
	 */
	function register_authors_archive_template( $template ) {
		if ( is_post_type_archive( 'mlmmc_author' ) ) {
			$plugin_template = LABGENZ_CM_TEMPLATES_DIR . '/authors/archive-mlmmc_author.php';
			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}
		return $template;
	}

	/**
	 * Render the single author page.
	 *
	 * @param int $post_id
	 */
	public function render_single_author( int $post_id ) {
		$author_post = get_post( $post_id );

		if ( ! $author_post ) {
			wp_die(
				'<pre>Author not found.' .
				"\nPost ID: " . esc_html( $post_id ) .
				"\nPost Type Exists? " . ( post_type_exists( AuthorCPT::POST_TYPE ) ? 'Yes' : 'No' ) .
				"\nRegistered Post Types: " . esc_html( implode( ', ', get_post_types() ) ) .
				"\nPost Status (if any): " . esc_html( get_post_status( $post_id ) ) .
				'</pre>'
			);
		}

		// Declare all data for the template
		$data = [
			'author_name' => get_post_meta( $post_id, 'mlmmc_article_author', true ) ?: $author_post->post_title,
			'bio'         => get_post_meta( $post_id, 'mlmmc_author_bio', true ),
			'photo_url'   => get_the_post_thumbnail_url( $post_id, 'medium' ),
			'post_id'     => $post_id,
			'author_post' => $author_post,
		];

		// Use theme template if exists
		$template = LABGENZ_CM_TEMPLATES_DIR . '/authors/author-single.php';
		if ( file_exists( $template ) ) {
			get_header();
			include $template;
			get_footer();
			return;
		}

		// Default output if no template found
		get_header();
		echo '<div class="no-template-found"> There been an error getting the author template. Please contact the site administrator. </div>';
		get_footer();
	}

	public function get_author_url( int $post_id ): string {
		$author_id = (int) get_post_meta( $post_id, 'mlmmc_author_id', true );

		if ( ! $author_id || get_post_type( $author_id ) !== AuthorCPT::POST_TYPE ) {
			return ''; // Return empty or fallback if invalid
		}

		return get_permalink( $author_id );
	}
}
