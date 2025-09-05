<?php

namespace LABGENZ_CM\Articles;

use LABGENZ_CM\Admin\DailyArticleAdmin;

/**
 * Handles AJAX search functionality for MLMMC articles.
 */
class ArticlesHandler {
	private const POSTS_PER_PAGE = 20;
	private const NONCE_ACTION   = 'mlmmc_search_nonce';
	public const POST_TYPE       = 'mlmmc_artiicle';
	private const TEMPLATE_ID    = 42495;

	/**
	 * ArticlesHandler constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize class hooks.
	 */
	private function init_hooks(): void {
		// add_action( 'wp_ajax_search_mlmmc_articles', [ $this, 'handle_articles_search' ] );
		add_action( 'wp_ajax_get_mlmmc_categories', [ $this, 'handle_get_categories' ] );
		add_action( 'wp_ajax_get_mlmmc_authors', [ $this, 'handle_get_authors' ] );
		add_action( 'wp_ajax_get_articles_sidebar', [ $this, 'get_articles_sidebar_ajax' ] );
		add_action( 'wp_ajax_nopriv_get_articles_sidebar', [ $this, 'get_articles_sidebar_ajax' ] );

		add_filter(
			'single_template',
			function ( $single_template ) {
				global $post;

				if ( $post->post_type === self::POST_TYPE ) {
					$template_path = LABGENZ_CM_TEMPLATES_DIR . '/single-mlmmc-article.php';
					if ( file_exists( $template_path ) ) {
						return $template_path;
					}
				}
				return $single_template;
			}
		);
		
		// Add sidebar to news feed page
		add_action('wp_footer', [$this, 'add_sidebar_to_news_feed']);
	}
	
	/**
	 * Add the articles sidebar to the news feed page
	 */
	public function add_sidebar_to_news_feed(): void {
		// Only run on the news feed page
		if (!is_page('news-feed')) {
			return;
		}
		
		?>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			// Target the news feed main content area
			const feedContent = document.querySelector('.activity-update-form, .bp-feedback');
			
			if (feedContent) {
				// Create sidebar container
				const sidebarContainer = document.createElement('div');
				sidebarContainer.className = 'news-feed-articles-sidebar';
				sidebarContainer.style.width = '30%';
				sidebarContainer.style.minWidth = '280px';
				
				// Create wrapper for layout
				const wrapper = document.createElement('div');
				wrapper.className = 'news-feed-with-sidebar';
				wrapper.style.display = 'flex';
				wrapper.style.gap = '30px';
				wrapper.style.flexWrap = 'wrap';
				
				// Get the parent node
				const parentNode = feedContent.parentNode;
				
				// Create main content container
				const mainContent = document.createElement('div');
				mainContent.className = 'news-feed-main-content';
				mainContent.style.flex = '1';
				mainContent.style.minWidth = '65%';
				
				// Clone all child nodes to the main content
				Array.from(parentNode.childNodes).forEach(node => {
					mainContent.appendChild(node.cloneNode(true));
				});
				
				// Clear the parent
				while (parentNode.firstChild) {
					parentNode.removeChild(parentNode.firstChild);
				}
				
				// Add sidebar HTML
				const xhr = new XMLHttpRequest();
				xhr.onreadystatechange = function() {
					if (this.readyState === 4 && this.status === 200) {
						const response = JSON.parse(this.responseText);
						sidebarContainer.innerHTML = response.data;
						
						// Add responsiveness
						const style = document.createElement('style');
						style.textContent = `
							@media (max-width: 991px) {
								.news-feed-with-sidebar {
									flex-direction: column;
								}
								.news-feed-articles-sidebar {
									width: 100% !important;
								}
							}
						`;
						document.head.appendChild(style);
						
						// Add the main content and sidebar to the wrapper
						wrapper.appendChild(mainContent);
						wrapper.appendChild(sidebarContainer);
						
						// Add the wrapper to the parent
						parentNode.appendChild(wrapper);
					}
				};
				xhr.open('GET', '<?php echo esc_url(admin_url('admin-ajax.php')); ?>?action=get_articles_sidebar', true);
				xhr.send();
			}
		});
		</script>
		<?php
	}
	
	/**
	 * AJAX handler to get the articles sidebar HTML
	 */
	public function get_articles_sidebar_ajax(): void {
		ob_start();
		self::render_articles_sidebar();
		$sidebar_html = ob_get_clean();
		wp_send_json_success($sidebar_html);
	}

	/**
	 * Handle AJAX search request.
	 *
	 * @return void
	 */
	public function handle_articles_search(): void {
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', self::NONCE_ACTION ) ) {
			wp_die( 'Security check failed' );
		}
		$search_term = sanitize_text_field( $_POST['search_term'] ?? '' );

		// Handle categories - can be single string or array
		$categories = [];
		if ( isset( $_POST['categories'] ) ) {
			if ( is_array( $_POST['categories'] ) ) {
				$categories = array_map( 'sanitize_text_field', $_POST['categories'] );
			} elseif ( is_string( $_POST['categories'] ) && $_POST['categories'] !== '' ) {
				$categories = [ sanitize_text_field( $_POST['categories'] ) ];
			}
		} elseif ( isset( $_POST['category'] ) && $_POST['category'] !== '' ) {
			// Backward compatibility for single category
			$categories = [ sanitize_text_field( $_POST['category'] ) ];
		}

		// Accept both single and multiple authors (array from JS)
		$authors = [];
		if ( isset( $_POST['authors'] ) ) {
			if ( is_array( $_POST['authors'] ) ) {
				$authors = array_map( 'sanitize_text_field', $_POST['authors'] );
			} elseif ( is_string( $_POST['authors'] ) && $_POST['authors'] !== '' ) {
				$authors = [ sanitize_text_field( $_POST['authors'] ) ];
			}
		} else {
			$author = sanitize_text_field( $_POST['author'] ?? '' );
			if ( $author !== '' ) {
				$authors = [ $author ];
			}
		}
		$page    = intval( $_POST['page'] ?? 1 ) ?: 1;
		$query   = $this->build_search_query( $search_term, $categories, $authors, $page );
		$content = $this->render_search_results( $query, $search_term, $page );
		wp_send_json_success(
			[
				'content'     => $content,
				'found_posts' => $query->found_posts,
				'max_pages'   => $query->max_num_pages,
			]
		);
	}

	/**
	 * Build the search query.
	 *
	 * @param string $search_term
	 * @param array  $categories
	 * @param array  $authors
	 * @param int    $page
	 * @return \WP_Query
	 */
	private function build_search_query( string $search_term, array $categories, array $authors, int $page ): \WP_Query {
		$args       = [
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => self::POSTS_PER_PAGE,
			'paged'          => $page,
			's'              => $search_term,
			'search_columns' => [ 'post_title' ],
		];
		$meta_query = [];
		if ( ! empty( $categories ) ) {
			if ( count( $categories ) === 1 ) {
				// Single category
				$meta_query[] = [
					'key'     => 'mlmmc_article_category',
					'value'   => $categories[0],
					'compare' => '=',
				];
			} else {
				// Multiple categories
				$category_query = [ 'relation' => 'OR' ];
				foreach ( $categories as $category ) {
					$category_query[] = [
						'key'     => 'mlmmc_article_category',
						'value'   => $category,
						'compare' => '=',
					];
				}
				$meta_query[] = $category_query;
			}
		}
		if ( ! empty( $authors ) ) {
			$meta_query[] = [
				'key'     => 'mlmmc_article_author',
				'value'   => $authors,
				'compare' => ( count( $authors ) > 1 ? 'IN' : '=' ),
			];
		}
		if ( ! empty( $meta_query ) ) {
			$args['meta_query'] = $meta_query;
			if ( count( $meta_query ) > 1 ) {
				$args['meta_query']['relation'] = 'AND';
			}
		}
		if ( ! empty( $search_term ) ) {
			add_filter( 'posts_search', [ $this, 'search_by_title_only' ], 500, 2 );
		}
		$query = new \WP_Query( $args );
		remove_filter( 'posts_search', [ $this, 'search_by_title_only' ], 500 );
		return $query;
	}

	/**
	 * Handle AJAX request to get available authors.
	 *
	 * @return void
	 */
	public function handle_get_authors(): void {
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', self::NONCE_ACTION ) ) {
			wp_die( 'Security check failed' );
		}
		$authors = $this->get_available_authors();
		wp_send_json_success( [ 'authors' => $authors ] );
	}

	/**
	 * Get author from ACF field for a specific article
	 *
	 * @param int $post_id The post ID
	 * @return string The author name or empty string if not found
	 */
	public function get_article_author( int $post_id ): string {
		if ( function_exists( 'get_field' ) ) {
			$author = get_field( 'mlmmc_article_author', $post_id );
			if ( ! empty( $author ) ) {
				return $author;
			}
		}

		// Fallback to post meta if ACF function is not available
		$author = get_post_meta( $post_id, 'mlmmc_article_author', true );

		return ! empty( $author ) ? $author : '';
	}

	/**
	 * Get all available authors from the ACF field.
	 *
	 * @return array
	 */
	private function get_available_authors(): array {
		global $wpdb;
		$authors = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT meta_value 
             FROM {$wpdb->postmeta} pm 
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             WHERE pm.meta_key = 'mlmmc_article_author'
             AND pm.meta_value != '' 
             AND p.post_type = %s 
             AND p.post_status = 'publish'
             ORDER BY meta_value ASC",
				self::POST_TYPE
			)
		);
		return array_filter( $authors );
	}

	/**
	 * Get authors from ACF field choices or from existing articles.
+    *
	 *
	 * @param int $post_id
	 * @return array
	 */
	private function get_authors_from_acf_field( int $post_id = 196 ): array {
		if ( function_exists( 'get_field_object' ) ) {
			$field_object = get_field_object( 'mlmmc_article_author', $post_id );
			if ( $field_object && isset( $field_object['choices'] ) && is_array( $field_object['choices'] ) ) {
				return array_values( $field_object['choices'] );
			}
		}
		return $this->get_available_authors();
	}

	/**
	 * Render search results.
	 *
	 * @param \WP_Query $query
	 * @param string    $search_term
	 * @param int       $page
	 * @return string
	 */
	private function render_search_results( \WP_Query $query, string $search_term, int $page ): string {
		ob_start();
		if ( $query->have_posts() ) {
			$this->render_posts( $query );
			$this->render_pagination( $query, $page );
		} else {
			$this->render_no_results( $search_term );
		}
		wp_reset_postdata();
		return ob_get_clean();
	}

	/**
	 * Render posts using Elementor template in 4 columns.
	 *
	 * @param \WP_Query $query
	 * @return void
	 */
	private function render_posts( \WP_Query $query ): void {
		echo '<div class="search-results-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px;">';
		while ( $query->have_posts() ) {
			$query->the_post();
			echo '<div class="search-result-item">';
			echo \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( self::TEMPLATE_ID );
			echo '</div>';
		}
		echo '</div>';
	}

	/**
	 * Render pagination controls.
	 *
	 * @param \WP_Query $query
	 * @param int       $page
	 * @return void
	 */
	private function render_pagination( \WP_Query $query, int $page ): void {
		$total_pages = $query->max_num_pages;
		if ( $total_pages <= 1 ) {
			return;
		}
		echo '<div class="search-pagination">';
		$this->render_previous_button( $page );
		$this->render_page_numbers( $page, $total_pages );
		$this->render_next_button( $page, $total_pages );
		echo '</div>';
	}

	/**
	 * Render previous page button.
	 *
	 * @param int $page
	 * @return void
	 */
	private function render_previous_button( int $page ): void {
		if ( $page > 1 ) {
			printf(
				'<button class="search-page-btn" data-page="%d">Previous</button>',
				$page - 1
			);
		}
	}

	/**
	 * Render page number buttons.
	 *
	 * @param int $page
	 * @param int $total_pages
	 * @return void
	 */
	private function render_page_numbers( int $page, int $total_pages ): void {
		$start_page = max( 1, $page - 2 );
		$end_page   = min( $total_pages, $page + 2 );
		for ( $i = $start_page; $i <= $end_page; $i++ ) {
			$active_class = ( $i === $page ) ? ' active' : '';
			printf(
				'<button class="search-page-btn%s" data-page="%d">%d</button>',
				$active_class,
				$i,
				$i
			);
		}
	}

	/**
	 * Render next page button.
	 *
	 * @param int $page
	 * @param int $total_pages
	 * @return void
	 */
	private function render_next_button( int $page, int $total_pages ): void {
		if ( $page < $total_pages ) {
			printf(
				'<button class="search-page-btn" data-page="%d">Next</button>',
				$page + 1
			);
		}
	}

	/**
	 * Render no results message.
	 *
	 * @param string $search_term
	 * @return void
	 */
	private function render_no_results( string $search_term ): void {
		printf(
			'<div class="no-results">No articles found for "%s"</div>',
			esc_html( $search_term )
		);
	}

	/**
	 * Custom function to search only in post titles.
	 *
	 * @param string    $search
	 * @param \WP_Query $wp_query
	 * @return string
	 */
	public function search_by_title_only( string $search, \WP_Query $wp_query ): string {
		global $wpdb;
		if ( empty( $search ) ) {
			return $search;
		}
		$q      = $wp_query->query_vars;
		$n      = ! empty( $q['exact'] ) ? '' : '%';
		$search = $searchand = '';
		foreach ( (array) $q['search_terms'] as $term ) {
			$term      = esc_sql( $wpdb->esc_like( $term ) );
			$search   .= "{$searchand}($wpdb->posts.post_title LIKE '{$n}{$term}{$n}')";
			$searchand = ' AND ';
		}
		if ( ! empty( $search ) ) {
			$search = " AND ({$search}) ";
			if ( ! is_user_logged_in() ) {
				$search .= " AND ($wpdb->posts.post_password = '') ";
			}
		}
		return $search;
	}

	/**
	 * Handle AJAX request to get available categories.
	 *
	 * @return void
	 */
	public function handle_get_categories(): void {
		$post_id    = intval( $_POST['post_id'] ?? 196 );
		$categories = $this->get_categories_from_acf_field( $post_id );
		wp_send_json_success( [ 'categories' => $categories ] );
	}

	/**
	 * Get categories from ACF field choices or from existing articles.
	 *
	 * @param int $post_id
	 * @return array
	 */
	private function get_categories_from_acf_field( int $post_id ): array {
		if ( function_exists( 'get_field_object' ) ) {
			$field_object = get_field_object( 'mlmmc_article_category', $post_id );
			if ( $field_object && isset( $field_object['choices'] ) && is_array( $field_object['choices'] ) ) {
				return array_values( $field_object['choices'] );
			}
		}
		return $this->get_available_categories();
	}

	/**
	 * Get category from ACF field for a specific article
	 *
	 * @param int $post_id The post ID
	 * @return string The category name or empty string if not found
	 */
	public function get_article_category( int $post_id ): string {
		if ( function_exists( 'get_field' ) ) {
			$category = get_field( 'mlmmc_article_category', $post_id );
			if ( ! empty( $category ) ) {
				return $category;
			}
		}

		// Fallback to post meta if ACF function is not available
		$category = get_post_meta( $post_id, 'mlmmc_article_category', true );
		return ! empty( $category ) ? $category : '';
	}

	/**
	 * Get all available categories from the ACF field.
	 *
	 * @return array
	 */
	private function get_available_categories(): array {
		global $wpdb;
		$categories = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT meta_value 
             FROM {$wpdb->postmeta} pm 
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             WHERE pm.meta_key = 'mlmmc_article_category' 
             AND pm.meta_value != '' 
             AND p.post_type = %s 
             AND p.post_status = 'publish'
             ORDER BY meta_value ASC",
				self::POST_TYPE
			)
		);
		return array_filter( $categories );
	}

	/*
	* Get the author's avatar URL for a specific article
	* @param int $post_id The post ID
	* @return string The avatar URL or empty string if not found
	*/

	public function get_article_avatar( int $post_id ): string {
		$article_admin = new DailyArticleAdmin();
		return $article_admin->get_article_author_image_url( $post_id );
	}

	/*
	* Get specific article author bio
	* @param int $post_id The post ID
	* @return string The author bio or empty string if not found
	*/
	public function get_article_author_bio( int $post_id ): string {
		return get_post_meta( $post_id, 'mlmmc_author_bio', true );
	}

	/**
	 * Render the articles sidebar
	 * This function can be used to display the article sidebar on any page
	 * 
	 * @return void
	 */
	public static function render_articles_sidebar(): void {
		// Get random articles
		$daily_article_handler = new DailyArticleHandler();
		$random_articles = $daily_article_handler->get_random_articles('mixed', 9, 10);

		if (!empty($random_articles)) :
		?>
			<div class="random-articles-sidebar">
				<h3 class="sidebar-title">Other Articles</h3>

				<?php 
				// Check for subscription access
				$show_access_button = false;
				if (class_exists('\\LABGENZ_CM\\Subscriptions\\SubscriptionHandler')) {
					$subscription_handler = \LABGENZ_CM\Subscriptions\SubscriptionHandler::get_instance();
					if (!$subscription_handler->user_has_resource_access(get_current_user_id(), 'can_view_mlm_articles')) {
						$show_access_button = true;
					}
				}
				
				if ($show_access_button) : 
				?>
					<div class="bb-button-wrapper">
						<button onclick="location.href='<?php echo esc_url(\LABGENZ_CM\Subscriptions\SubscriptionHandler::get_article_upsell_url()); ?>'" class="bb-button bb-button--primary">
							Get Access to ALL Success Library Articles
						</button>
					</div>
				<?php endif; ?>

				<ul class="random-articles-list"><?php foreach ($random_articles as $article) :
						$category_name = !empty($article['category']) ? $article['category'] : 'Uncategorized';
						$rating = $article['avg_rating'];
					?>
						<li class="random-article-item">
							<a href="<?php echo esc_url($article['link']); ?>" class="article-title">
								<?php echo esc_html($article['title']); ?>
							</a>
							<p class="article-category">Category: <?php echo esc_html($category_name); ?></p>

							<?php if ($article['has_video']) : ?>
								<span class="article-video-badge">
									Has Video
								</span>
							<?php endif; ?>

							<?php if ($rating !== null) : ?>
								<div class="article-rating">
									<?php
									$max_stars = 5;
									$filled    = floor($rating);
									$half      = ($rating - $filled >= 0.5) ? 1 : 0;
									$empty     = $max_stars - $filled - $half;

									echo '<span class="star-filled">' . str_repeat('★', $filled) . '</span>';
									if ($half) echo '<span class="star-filled">☆</span>';
									echo '<span class="star-empty">' . str_repeat('☆', $empty) . '</span>';
									echo ' (' . number_format($rating, 1) . ')';
									?>
								</div>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
			<style>
				.random-articles-sidebar {
					margin-top: 30px;
					padding: 20px;
					background: #fff;
					border-radius: 8px;
					box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
				}
				.sidebar-title {
					margin-bottom: 15px;
					font-size: 18px;
					color: var(--bb-primary-color, #385DFF);
					font-weight: 600;
				}
				.bb-button-wrapper {
					margin: 20px 0;
					text-align: center;
				}
				.random-articles-list {
					list-style: none;
					padding: 0;
					margin: 0;
				}
				.random-article-item {
					margin-bottom: 20px;
					padding: 15px;
					border: 1px solid #eee;
					border-radius: 5px;
					transition: all 0.2s ease;
				}
				.random-article-item:hover {
					border-color: var(--bb-primary-color, #385DFF);
					box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
				}
				.article-title {
					font-weight: bold;
					font-size: 16px;
					text-decoration: none;
					color: var(--bb-headings-color, #122b46);
					display: block;
					margin-bottom: 8px;
					transition: color 0.2s ease;
				}
				.article-title:hover {
					color: var(--bb-primary-color, #385DFF);
				}
				.article-category {
					margin: 5px 0;
					font-size: 14px;
					color: #666;
				}
				.article-video-badge {
					display: inline-block;
					background-color: #28a745;
					color: white;
					padding: 3px 8px;
					font-size: 12px;
					border-radius: 3px;
					margin-bottom: 5px;
				}
				.article-rating {
					margin-top: 5px;
					font-size: 14px;
					color: #444;
				}
				.star-filled {
					color: #f5c518;
				}
				.star-empty {
					color: #ddd;
				}
			</style>
		<?php
		endif;
	}
}
