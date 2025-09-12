<?php

declare(strict_types=1);

namespace LABGENZ_CM\Articles\Authors;

defined( 'ABSPATH' ) || exit;

/**
 * Class AuthorDisplayHandler
 *
 * Handles the display logic for single author pages and archives.
 * Also provides a shortcode for displaying authors in various layouts.
 */
class AuthorDisplayHandler {

	/**
	 * Cache group name
	 */
	public const CACHE_GROUP = 'mlmmc_authors';

	/**
	 * Cache expiration time in seconds (12 hours)
	 */
	public const CACHE_EXPIRATION = 43200;

	/**
	 * AuthorDisplayHandler constructor.
	 */
	public function __construct() {
		// Hook into template redirect to handle single author display
		add_action( 'template_redirect', [ $this, 'maybe_render_single_author' ] );
		add_filter( 'template_include', [ $this, 'register_authors_archive_template' ] );

		// Register shortcode for displaying authors
		add_shortcode( 'mlmmc_authors', [ $this, 'authors_shortcode' ] );

		// Clear cache when author is updated
		add_action( 'save_post_' . AuthorCPT::POST_TYPE, [ $this, 'clear_authors_cache' ] );
		add_action( 'deleted_post', [ $this, 'clear_cache_on_author_deletion' ] );
	}

	/**
	 * Clear authors cache when an author is updated
	 *
	 * @param int $post_id The post ID
	 */
	public function clear_authors_cache( int $post_id ): void {
		if ( get_post_type( $post_id ) === AuthorCPT::POST_TYPE ) {
			wp_cache_delete( 'all_authors', self::CACHE_GROUP );
			wp_cache_delete( 'author_' . $post_id, self::CACHE_GROUP );
		}
	}

	/**
	 * Clear cache when an author is deleted
	 *
	 * @param int $post_id The post ID
	 */
	public function clear_cache_on_author_deletion( int $post_id ): void {
		if ( get_post_type( $post_id ) === AuthorCPT::POST_TYPE ) {
			$this->clear_authors_cache( $post_id );
		}
	}

	/**
	 * Get all published authors with caching
	 *
	 * @param array $args Query arguments
	 * @return array Array of author post objects
	 */
	public function get_authors( array $args = [] ): array {
		// Default query args
		$default_args = [
			'post_type'      => AuthorCPT::POST_TYPE,
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		];

		$query_args = wp_parse_args( $args, $default_args );

		// Generate a cache key based on the query args
		$cache_key = 'authors_' . md5( serialize( $query_args ) );

		// Try to get cached data
		$authors = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false === $authors ) {
			// Cache miss, fetch authors
			$query   = new \WP_Query( $query_args );
			$authors = $query->posts;

			// Cache the results
			wp_cache_set( $cache_key, $authors, self::CACHE_GROUP, self::CACHE_EXPIRATION );
		}

		return $authors;
	}

	/**
	 * Get author metadata with caching
	 *
	 * @param int $author_id The author post ID
	 * @return array Author metadata
	 */
	public function get_author_data( int $author_id ): array {
		$cache_key = 'author_' . $author_id;

		// Try to get cached data
		$author_data = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false === $author_data ) {
			$author_post = get_post( $author_id );

			if ( ! $author_post ) {
				return [];
			}

			// Get author meta
			$author_data = [
				'id'            => $author_id,
				'name'          => get_post_meta( $author_id, 'mlmmc_article_author', true ) ?: $author_post->post_title,
				'title'         => get_post_meta( $author_id, 'product_creator_title', true ) ?: 'Writer',
				'bio'           => get_post_meta( $author_id, 'mlmmc_author_bio', true ),
				'photo_url'     => get_the_post_thumbnail_url( $author_id, 'medium' ),
				'email'         => get_post_meta( $author_id, 'product_creator_email', true ),
				'website'       => get_post_meta( $author_id, 'product_creator_website', true ),
				'permalink'     => get_permalink( $author_id ),
				'article_count' => $this->get_author_article_count( $author_id ),
			];

			// Cache the results
			wp_cache_set( $cache_key, $author_data, self::CACHE_GROUP, self::CACHE_EXPIRATION );
		}

		return $author_data;
	}

	/**
	 * Get count of published articles by an author
	 *
	 * @param int $author_id The author post ID
	 * @return int Article count
	 */
	public function get_author_article_count( int $author_id ): int {
		$cache_key = 'author_' . $author_id . '_article_count';

		// Try to get cached count
		$count = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false === $count ) {
			$author_name = get_post_meta( $author_id, 'mlmmc_article_author', true ) ?: get_the_title( $author_id );

			$query = new \WP_Query(
				[
					'post_type'      => 'mlmmc_artiicle',
					'posts_per_page' => -1,
					'post_status'    => 'publish',
					'fields'         => 'ids',
					'meta_query'     => [
						[
							'key'     => 'mlmmc_article_author',
							'value'   => $author_name,
							'compare' => 'LIKE',
						],
					],
				]
			);

			$count = $query->found_posts;

			// Cache the count
			wp_cache_set( $cache_key, $count, self::CACHE_GROUP, self::CACHE_EXPIRATION );
		}

		return $count;
	}

	/**
	 * Authors shortcode
	 *
	 * @param array $atts Shortcode attributes
	 * @return string HTML output
	 */
	public function authors_shortcode( $atts ): string {
		$atts = shortcode_atts(
			[
				'layout'     => 'grid', // grid, list, compact
				'columns'    => 3,      // 1-6 columns for grid layout
				'limit'      => 12,     // number of authors to display
				'orderby'    => 'title',// title, date, article_count
				'order'      => 'ASC',  // ASC, DESC
				'exclude'    => '',     // comma-separated IDs to exclude
				'include'    => '',     // comma-separated IDs to include
				'show_bio'   => 'yes',  // yes, no
				'show_count'   // yes, no
					=> 'yes',
				'bio_length' => 55,    // number of characters for bio excerpt
				'class'      => '',     // additional CSS classes
				'pagination' => 'no',   // yes, no
			],
			$atts,
			'mlmmc_authors'
		);

		// Sanitize attributes
		$layout       = in_array( $atts['layout'], [ 'grid', 'list', 'compact' ] ) ? $atts['layout'] : 'grid';
		$columns      = min( 6, max( 1, (int) $atts['columns'] ) );
		$limit        = (int) $atts['limit'];
		$orderby      = in_array( $atts['orderby'], [ 'title', 'date', 'article_count' ] ) ? $atts['orderby'] : 'title';
		$order        = in_array( strtoupper( $atts['order'] ), [ 'ASC', 'DESC' ] ) ? strtoupper( $atts['order'] ) : 'ASC';
		$show_bio     = $atts['show_bio'] === 'yes';
		$show_count   = $atts['show_count'] === 'yes';
		$bio_length   = (int) $atts['bio_length'];
		$custom_class = sanitize_html_class( $atts['class'] );
		$pagination   = $atts['pagination'] === 'yes';

		// Handle includes/excludes
		$exclude_ids = [];
		if ( ! empty( $atts['exclude'] ) ) {
			$exclude_ids = array_map( 'intval', explode( ',', $atts['exclude'] ) );
		}

		$include_ids = [];
		if ( ! empty( $atts['include'] ) ) {
			$include_ids = array_map( 'intval', explode( ',', $atts['include'] ) );
		}

		// Set up query args
		$query_args = [
			'post_type'      => AuthorCPT::POST_TYPE,
			'posts_per_page' => $pagination ? $limit : -1,
			'post_status'    => 'publish',
			'order'          => $order,
		];

		// Handle pagination
		if ( $pagination ) {
			$paged               = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
			$query_args['paged'] = $paged;
		}

		// Handle ordering
		if ( $orderby === 'article_count' ) {
			// Custom ordering will be applied after getting the posts
			$query_args['orderby'] = 'title';
		} else {
			$query_args['orderby'] = $orderby;
		}

		// Handle includes/excludes
		if ( ! empty( $include_ids ) ) {
			$query_args['post__in'] = $include_ids;
		} elseif ( ! empty( $exclude_ids ) ) {
			$query_args['post__not_in'] = $exclude_ids;
		}

		// Get authors
		$authors = $this->get_authors( $query_args );

		// Custom ordering by article count if needed
		if ( $orderby === 'article_count' ) {
			$authors_with_count = [];
			foreach ( $authors as $author ) {
				$count                = $this->get_author_article_count( $author->ID );
				$authors_with_count[] = [
					'author' => $author,
					'count'  => $count,
				];
			}

			// Sort by article count
			usort(
				$authors_with_count,
				function ( $a, $b ) use ( $order ) {
					if ( $order === 'ASC' ) {
						return $a['count'] - $b['count'];
					} else {
						return $b['count'] - $a['count'];
					}
				}
			);

			// Extract just the authors
			$authors = array_map(
				function ( $item ) {
					return $item['author'];
				},
				$authors_with_count
			);
		}

		// Limit results if not using pagination
		if ( ! $pagination && $limit > 0 && count( $authors ) > $limit ) {
			$authors = array_slice( $authors, 0, $limit );
		}

		// Start output buffer
		ob_start();

		// CSS classes
		$container_classes = [
			'mlmmc-authors-archive',
			'mlmmc-authors-' . $layout,
			'mlmmc-authors-columns-' . $columns,
		];

		if ( $custom_class ) {
			$container_classes[] = $custom_class;
		}

		echo '<div class="' . esc_attr( implode( ' ', $container_classes ) ) . '">';

		if ( ! empty( $authors ) ) {
			// Grid container
			$layout_class = 'mlmmc-authors-' . $layout;
			echo '<div class="' . esc_attr( $layout_class ) . '">';

			foreach ( $authors as $author_post ) {
				$author_data = $this->get_author_data( $author_post->ID );

				// Create excerpt from bio if needed
				$bio_excerpt = '';
				if ( $show_bio && ! empty( $author_data['bio'] ) ) {
					$bio_excerpt = wp_trim_words( $author_data['bio'], $bio_length / 5, '...' );
				}

				echo '<div class="mlmmc-author-card">';

				// Author thumbnail
				if ( ! empty( $author_data['photo_url'] ) ) {
					echo '<div class="mlmmc-author-thumbnail">';
					echo '<a href="' . esc_url( $author_data['permalink'] ) . '">';
					echo '<img src="' . esc_url( $author_data['photo_url'] ) . '" alt="' . esc_attr( $author_data['name'] ) . '">';
					echo '</a>';
					echo '</div>';
				} else {
					echo '<div class="mlmmc-author-thumbnail">';
					echo '<a href="' . esc_url( $author_data['permalink'] ) . '">';
					echo '<img src="' . esc_url( LABGENZ_CM_URL . 'src/Public/assets/images/default-author.png' ) . '" alt="' . esc_attr( $author_data['name'] ) . '">';
					echo '</a>';
					echo '</div>';
				}

				// Author content
				echo '<div class="mlmmc-author-content">';
				echo '<h3 class="mlmmc-author-name"><a href="' . esc_url( $author_data['permalink'] ) . '">' . esc_html( $author_data['name'] ) . '</a></h3>';

				if ( ! empty( $author_data['title'] ) ) {
					echo '<p class="mlmmc-author-title">' . esc_html( $author_data['title'] ) . '</p>';
				}

				if ( $show_bio && ! empty( $bio_excerpt ) ) {
					echo '<p class="mlmmc-author-bio">' . esc_html( $bio_excerpt ) . '</p>';
				}

				// Meta section with article count and read more
				echo '<div class="mlmmc-author-meta">';

				if ( $show_count ) {
					echo '<div class="mlmmc-author-articles-count">';
					echo '<i class="fas fa-file-alt"></i> ';
					echo esc_html( sprintf( _n( '%d Article', '%d Articles', $author_data['article_count'], 'labgenz-cm' ), $author_data['article_count'] ) );
					echo '</div>';
				}

				echo '<a href="' . esc_url( $author_data['permalink'] ) . '" class="mlmmc-author-read-more">' . esc_html__( 'View Profile', 'labgenz-cm' ) . '</a>';
				echo '</div>'; // .mlmmc-author-meta

				echo '</div>'; // .mlmmc-author-content
				echo '</div>'; // .mlmmc-author-card
			}

			echo '</div>'; // .$layout_class

			// Pagination
			if ( $pagination ) {
				echo '<div class="mlmmc-authors-pagination">';
				$big = 999999999;
				echo paginate_links(
					[
						'base'    => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
						'format'  => '?paged=%#%',
						'current' => max( 1, get_query_var( 'paged' ) ),
						'total'   => ceil(
							count(
								$this->get_authors(
									[
										'post_type'      => AuthorCPT::POST_TYPE,
										'posts_per_page' => -1,
										'post_status'    => 'publish',
										'fields'         => 'ids',
									]
								)
							) / $limit
						),
					]
				);
				echo '</div>';
			}
		} else {
			echo '<p class="mlmmc-no-authors">' . esc_html__( 'No authors found.', 'labgenz-cm' ) . '</p>';
		}

		echo '</div>'; // .mlmmc-authors-archive

		return ob_get_clean();
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
	 *
	 * @param string $template Template path
	 * @return string Modified template path
	 */
	public function register_authors_archive_template( $template ) {
		if ( is_post_type_archive( 'mlmmc_author' ) ) {
			$plugin_template = LABGENZ_CM_TEMPLATES_DIR . '/authors/archive-mlmmc_author.php';
			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}
		return $template;
	}

	/**
	 * Get the URL for an author by post ID
	 *
	 * @param int $post_id Post ID with author metadata
	 * @return string Author URL
	 */
	public function get_author_url( int $post_id ): string {
		$author_id = (int) get_post_meta( $post_id, 'mlmmc_author_id', true );

		if ( ! $author_id || get_post_type( $author_id ) !== AuthorCPT::POST_TYPE ) {
			return ''; // Return empty or fallback if invalid
		}

		return get_permalink( $author_id );
	}
}
