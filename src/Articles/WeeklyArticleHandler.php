<?php

namespace LABGENZ_CM\Articles;

/**
 * Handles weekly free article functionality.
 */
class WeeklyArticleHandler {
	private const POST_TYPE     = 'mlmmc_artiicle';
	private const TEMPLATE_ID   = 42495; // Unused template ID, can be removed
	private const OPTION_KEY    = 'mlmmc_weekly_article';
	private const TRANSIENT_KEY = 'mlmmc_weekly_article_cache';
	public const SHORTCODE      = 'weekly_articles';

	/**
	 * WeeklyArticleHandler constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize class hooks.
	 */
	private function init_hooks(): void {
		add_shortcode( self::SHORTCODE, [ $this, 'render_weekly_article_shortcode' ] );
		add_action( 'init', [ $this, 'schedule_weekly_update' ] );
		add_action( 'mlmmc_update_weekly_article', [ $this, 'update_weekly_article' ] );
	}

	/**
	 * Schedule weekly article update if not already scheduled.
	 */
	public function schedule_weekly_update(): void {
		if ( ! wp_next_scheduled( 'mlmmc_update_weekly_article' ) ) {
			// Schedule for every Monday at 12:00 AM
			wp_schedule_event(
				strtotime( 'next Monday midnight' ),
				'weekly',
				'mlmmc_update_weekly_article'
			);
		}
	}

	/**
	 * Update the weekly article selection.
	 */
	public function update_weekly_article(): void {
		$random_article = $this->get_random_article();
		if ( ! $random_article ) {
			return;
		}

		$article_data = [
			'article_id'    => $random_article->ID,
			'week_start'    => $this->get_current_week_start(),
			'selected_date' => current_time( 'Y-m-d H:i:s' ),
		];

		update_option( self::OPTION_KEY, $article_data );
	}

	/**
	 * Get a random published article.
	 *
	 * @return \WP_Post|null
	 */
	private function get_random_article(): ?\WP_Post {
		$args = [
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'orderby'        => 'rand',
			'meta_query'     => [
				[
					'key'     => 'mlmmc_article_category',
					'compare' => 'EXISTS',
				],
			],
		];

		$query = new \WP_Query( $args );

		return $query->have_posts() ? $query->posts[0] : null;
	}

	/**
	 * Get the current week's article data.
	 *
	 * @return array|null
	 */
	private function get_current_weekly_article(): ?array {
		$article_data = get_option( self::OPTION_KEY );

		// If no article data exists or it's from a previous week, select a new one
		if ( ! $article_data || $this->is_previous_week( $article_data['week_start'] ) ) {
			$random_article = $this->get_random_article();
			if ( ! $random_article ) {
				return null;
			}

			$article_data = [
				'article_id'    => $random_article->ID,
				'week_start'    => $this->get_current_week_start(),
				'selected_date' => current_time( 'Y-m-d H:i:s' ),
			];

			update_option( self::OPTION_KEY, $article_data );
		}

		$article = get_post( $article_data['article_id'] );
		if ( ! $article || $article->post_status !== 'publish' ) {
			// Article was deleted or unpublished, select a new one
			$random_article = $this->get_random_article();
			if ( ! $random_article ) {
				return null;
			}

			$article_data = [
				'article_id'    => $random_article->ID,
				'week_start'    => $this->get_current_week_start(),
				'selected_date' => current_time( 'Y-m-d H:i:s' ),
			];

			update_option( self::OPTION_KEY, $article_data );
			$article = $random_article;
		}

		return [
			'article'       => $article,
			'week_start'    => $article_data['week_start'],
			'selected_date' => $article_data['selected_date'],
		];
	}


	/**
	 * Get the start date of the current week (Monday).
	 *
	 * @return string
	 */
	public function get_current_week_start(): string {
		$current_date = new \DateTime();
		$current_date->setTime( 0, 0, 0 );

		// Get Monday of current week
		$day_of_week = $current_date->format( 'N' ); // 1 = Monday, 7 = Sunday
		if ( $day_of_week > 1 ) {
			$current_date->sub( new \DateInterval( 'P' . ( $day_of_week - 1 ) . 'D' ) );
		}

		return $current_date->format( 'Y-m-d' );
	}

	/**
	 * Check if the given week start date is from a previous week.
	 *
	 * @param string $week_start Week start date
	 * @return bool
	 */
	private function is_previous_week( string $week_start ): bool {
		$current_week_start = $this->get_current_week_start();
		return $week_start < $current_week_start;
	}

	/**
	 * Render the weekly article shortcode.
	 *
	 * @param array $atts
	 * @return string
	 */
	public function render_weekly_article_shortcode( array $atts = [] ): string {
		$atts = shortcode_atts(
			[
				'show_title'    => 'true',
				'show_meta'     => 'true',
				'wrapper_class' => 'weekly-article-wrapper',
			],
			$atts
		);

		$weekly_data = $this->get_current_weekly_article();

		if ( ! $weekly_data ) {
			return $this->render_no_article_message();
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( $atts['wrapper_class'] ); ?> weekly-article-container">
			
			<?php if ( $atts['show_title'] === 'true' ) : ?>
				<div class="weekly-article-header">
					<h2 class="weekly-article-title">🌟 Article of the Week</h2>
					
					<?php
					if ( $atts['show_meta'] === 'true' ) :
						$week_start = new \DateTime( $weekly_data['week_start'] );
						$week_end   = clone $week_start;
						$week_end->add( new \DateInterval( 'P6D' ) );
						?>
						<p class="weekly-article-meta">
							Week of <?php echo $week_start->format( 'M j' ); ?> - <?php echo $week_end->format( 'M j, Y' ); ?>
						</p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			
			<div class="weekly-article-content-wrapper">
				<div class="weekly-article-content">
					<?php
					// Set up global post data for the template
					global $post;
					$original_post = $post;
					$post          = $weekly_data['article'];
					setup_postdata( $post );

					// Custom article display
					$featured_image = get_the_post_thumbnail_url( $post, 'large' );
					$category       = get_post_meta( $post->ID, 'mlmmc_article_category' );

					// Get post meta author fields
					$author_name     = get_post_meta( $post->ID, 'mlmmc_article_author', true );
					$author_image_id = get_post_meta( $post->ID, 'mlmmc_author_photo', true );
					$author_image    = $author_image_id ? wp_get_attachment_image_url( $author_image_id, 'medium' ) : '';

					// Fallback to WordPress author if meta fields are empty
					if ( ! $author_name ) {
						$author_id   = $post->post_author;
						$author_name = get_the_author_meta( 'display_name', $author_id );
					}

					if ( ! $author_image ) {
						$author_id    = $post->post_author;
						$author_image = get_avatar_url( $author_id, [ 'size' => 300 ] );
					}

					$publish_date = get_the_date( 'F j, Y' );
					$excerpt      = wp_trim_words( get_the_excerpt(), 40, '...' );
					$permalink    = get_permalink();
					?>
					
					<article class="weekly-article-custom">
						<?php if ( $featured_image ) : ?>
							<div class="article-featured-image">
								<img src="<?php echo esc_url( $featured_image ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>">
							</div>
						<?php endif; ?>
						
						<div class="article-header">
								<!-- <div class="article-categories">
										<span class="category-badge"><?php echo esc_html( $category ); ?></span>
								</div> -->
							<h1 class="article-title"><?php echo get_the_title(); ?></h1>
							
							<div class="article-meta">
								<div class="author-info">
									<?php if ( $author_image ) : ?>
										<img src="<?php echo esc_url( $author_image ); ?>" alt="<?php echo esc_attr( $author_name ); ?>" class="author-image">
									<?php endif; ?>
									<div class="author-details">
										<span class="article-author">By <?php echo esc_html( $author_name ); ?></span>
										<span class="article-date"><?php echo esc_html( $publish_date ); ?></span>
									</div>
								</div>
							</div>
						</div>
						
						<div class="article-excerpt">
							<p><?php echo esc_html( $excerpt ); ?></p>
							<a href="<?php echo esc_url( $permalink ); ?>" class="read-more-link">Read Full Article →</a>
						</div>
					</article>
					
					<?php
					// Restore original post data
					wp_reset_postdata();
					$post = $original_post;
					?>
				</div>
				
				<div class="weekly-article-upsell">
					<div class="upsell-content">
						<h3>📚 Unlock Full Access</h3>
						<p>Gain access to our entire library of <strong>1,500+ MLM articles</strong> with expert insights, strategies, and industry analysis.</p>
						<ul>
							<li>✅ Weekly training modules</li>
							<li>✅ Exclusive member resources</li>
							<li>✅ Downloadable templates & guides</li>
							<li>✅ Community discussions</li>
						</ul>
						<button class="upsell-button" onclick="window.location.href='<?php echo esc_url( home_url( '#pricing' ) ); ?>'">
							Get Full Access Now
						</button>
						<p class="small-text">Join thousands of successful MLM professionals today!</p>
					</div>
				</div>
			</div>
		</div>
		
		<?php
		return ob_get_clean();
	}

	/**
	 * Render message when no article is available.
	 *
	 * @return string
	 */
	private function render_no_article_message(): string {
		return '<div class="weekly-article-wrapper no-article">
                    <div class="weekly-article-header">
                        <h2 class="weekly-article-title">Article of the Week</h2>
                    </div>
                    <div class="weekly-article-content">
                        <p>No article available this week. Please check back later.</p>
                    </div>
                </div>';
	}

	/**
	 * Get weekly article data for admin purposes.
	 *
	 * @return array|null
	 */
	public function get_weekly_article_admin_data(): ?array {
		$weekly_data = $this->get_current_weekly_article();

		if ( ! $weekly_data ) {
			return null;
		}

		return [
			'article_id'    => $weekly_data['article']->ID,
			'article_title' => $weekly_data['article']->post_title,
			'week_start'    => $weekly_data['week_start'],
			'selected_date' => $weekly_data['selected_date'],
			'article_url'   => get_permalink( $weekly_data['article']->ID ),
			'next_update'   => wp_next_scheduled( 'mlmmc_update_weekly_article' ),
		];
	}

	/**
	 * Manually trigger weekly article update (for admin use).
	 */
	public function manual_update_weekly_article(): void {
		$this->update_weekly_article();
	}

	/**
	 * Clear weekly article cache.
	 */
	public function clear_cache(): void {
		delete_option( self::OPTION_KEY );
	}
}