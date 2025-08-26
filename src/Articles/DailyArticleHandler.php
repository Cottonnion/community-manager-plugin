<?php

namespace LABGENZ_CM\Articles;

use LABGENZ_CM\Subscriptions\SubscriptionHandler;
use LABGENZ_CM\Articles\Helpers\ArticleCacheHelper;

/**
 * Handles daily free article functionality.
 */
class DailyArticleHandler {
	private const POST_TYPE     = 'mlmmc_artiicle';
	private const OPTION_KEY    = 'mlmmc_daily_article';
	private const TRANSIENT_KEY = 'mlmmc_daily_article_cache';
	private const TEMPLATE_ID   = 42495; // Unused template ID, can be removed
	public const SHORTCODE      = 'daily_articles';

	/**
	 * Articles handler instance
	 *
	 * @var ArticlesHandler
	 */
	private $articles_handler;

	/**
	 * DailyArticleHandler constructor.
	 */
	public function __construct() {
		$this->articles_handler = new ArticlesHandler();
		$this->init_hooks();
	}

	/**
	 * Initialize class hooks.
	 */
	private function init_hooks(): void {
		add_shortcode( self::SHORTCODE, [ $this, 'render_daily_article_shortcode' ] );
		add_action( 'init', [ $this, 'schedule_daily_update' ] );
		add_action( 'mlmmc_update_daily_article', [ $this, 'update_daily_article' ] );
	}

	/**
	 * Schedule daily article update if not already scheduled.
	 */
	public function schedule_daily_update(): void {
		if ( ! wp_next_scheduled( 'mlmmc_update_daily_article' ) ) {
			// Schedule for midnight every day
			wp_schedule_event(
				strtotime( 'today midnight' ),
				'daily',
				'mlmmc_update_daily_article'
			);
		}
	}

	/**
	 * Update the daily article selection.
	 */
	public function update_daily_article(): void {
		$random_article = $this->get_random_article();
		if ( ! $random_article ) {
			return;
		}

		$article_data = [
			'article_id'    => $random_article->ID,
			'day_start'     => $this->get_current_day_start(),
			'selected_date' => current_time( 'Y-m-d H:i:s' ),
		];

		update_option( self::OPTION_KEY, $article_data );

		// Add this article to the accessible articles for all users with basic subscription
		$this->add_article_to_users_accessible_list( $random_article->ID );
	}

	/**
	 * Add an article to all users' accessible articles lists who have basic subscriptions
	 *
	 * @param int $article_id The article ID to add
	 * @return void
	 */
	private function add_article_to_users_accessible_list( int $article_id ): void {
		// Get all users
		$users = get_users();

		foreach ( $users as $user ) {
			$user_id = $user->ID;

			// Skip admin users
			if ( user_can( $user_id, 'manage_options' ) ) {
				continue;
			}

			// Check if user has a basic subscription
			if ( class_exists( '\LABGENZ_CM\Subscriptions\SubscriptionHandler' ) ) {
				$subscription_types = \LABGENZ_CM\Subscriptions\SubscriptionHandler::get_user_subscription_types( $user_id );
				$has_basic          = false;

				foreach ( $subscription_types as $type ) {
					if ( strpos( $type, 'basic' ) !== false ) {
						$has_basic = true;
						break;
					}
				}

				if ( $has_basic ) {
					// Get current accessible articles
					$accessible_article_ids = get_user_meta( $user_id, '_accessible_article_ids', true );

					if ( ! is_array( $accessible_article_ids ) ) {
						$accessible_article_ids = [];
					}

					// Add the new article if it's not already in the list
					if ( ! in_array( $article_id, $accessible_article_ids ) ) {
						$accessible_article_ids[] = $article_id;
						update_user_meta( $user_id, '_accessible_article_ids', $accessible_article_ids );
					}
				}
			}
		}
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
	 * Get the current day's article data.
	 *
	 * @return array|null
	 */
	private function get_current_daily_article(): ?array {
		$article_data = get_option( self::OPTION_KEY );

		// If no article data exists or it's from a previous day, select a new one
		if ( ! $article_data || $this->is_previous_day( $article_data['day_start'] ) ) {
			$random_article = $this->get_random_article();
			if ( ! $random_article ) {
				return null;
			}

			$article_data = [
				'article_id'    => $random_article->ID,
				'day_start'     => $this->get_current_day_start(),
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
				'day_start'     => $this->get_current_day_start(),
				'selected_date' => current_time( 'Y-m-d H:i:s' ),
			];

			update_option( self::OPTION_KEY, $article_data );
			$article = $random_article;
		}

		return [
			'article'       => $article,
			'day_start'     => $article_data['day_start'],
			'selected_date' => $article_data['selected_date'],
		];
	}

	/**
	 * Check if current user has access to premium articles.
	 *
	 * @return bool True if user has access to premium articles.
	 */
	private function user_has_premium_access(): bool {
		// If user is not logged in, they don't have premium access
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$user_id = get_current_user_id();

		// If user is admin, grant access to everything
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		// Use SubscriptionHandler to check if user has access to premium content
		if ( class_exists( '\LABGENZ_CM\Subscriptions\SubscriptionHandler' ) ) {
			// Check for premium access first
			if ( \LABGENZ_CM\Subscriptions\SubscriptionHandler::user_has_resource_access( $user_id, 'can_view_mlm_articles' ) ) {
				return true;
			}

			// Basic subscription users have access to articles since their subscription start date
			$subscription_types = \LABGENZ_CM\Subscriptions\SubscriptionHandler::get_user_subscription_types( $user_id );
			$has_basic          = false;

			foreach ( $subscription_types as $type ) {
				if ( strpos( $type, 'basic' ) !== false ) {
					$has_basic = true;
					break;
				}
			}

			if ( $has_basic ) {
				$post_id                = get_the_ID();
				$accessible_article_ids = get_user_meta( $user_id, '_accessible_article_ids', true );

				if ( ! empty( $accessible_article_ids ) && is_array( $accessible_article_ids ) ) {
					return in_array( $post_id, $accessible_article_ids );
				}
			}
		}

		return false;
	}




	/**
	 * Get the start date of the current day.
	 *
	 * @return string
	 */
	public function get_current_day_start(): string {
		$current_date = new \DateTime();
		$current_date->setTime( 0, 0, 0 );

		return $current_date->format( 'Y-m-d' );
	}

	/**
	 * Check if the given day start date is from a previous day.
	 *
	 * @param string $day_start Day start date
	 * @return bool
	 */
	private function is_previous_day( string $day_start ): bool {
		$current_day_start = $this->get_current_day_start();
		return $day_start < $current_day_start;
	}

	/**
	 * Render the daily article shortcode.
	 *
	 * @param array $atts
	 * @return string
	 */
	public function render_daily_article_shortcode( array $atts = [] ): string {
		$atts = shortcode_atts(
			[
				'show_title'    => 'true',
				'show_meta'     => 'true',
				'wrapper_class' => 'daily-article-wrapper',
				'num_related'   => 12, // Number of random articles to show (displaying between 9-12)
				'show_upsell'   => 'true', // New attribute to control upsell visibility
			],
			$atts
		);

		$daily_data = $this->get_current_daily_article();

		if ( ! $daily_data ) {
			return $this->render_no_article_message();
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( $atts['wrapper_class'] ); ?> daily-article-container">
			
			<?php if ( $atts['show_title'] === 'true' ) : ?>
				<div class="daily-article-header">
					<h2 class="daily-article-title">🌟 Article of the Day</h2>
				</div>
			<?php endif; ?>
			
			<div class="daily-article-content-wrapper" style="display: flex; flex-wrap: wrap; gap: 30px;">
				<div class="daily-article-content" style="flex: 1; min-width: 60%;">
					<?php
					// Set up global post data for the template
					global $post;
					$original_post = $post;
					$post          = $daily_data['article'];
					setup_postdata( $post );

					// Custom article display
					$featured_image = get_the_post_thumbnail_url( $post, 'large' );
					$category       = $this->articles_handler->get_article_category( $post->ID );

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
					
					<article class="daily-article-custom">
						<?php if ( $featured_image ) : ?>
							<div class="article-featured-image">
								<img src="<?php echo esc_url( $featured_image ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>">
							</div>
						<?php endif; ?>
						
						<div class="article-header">
							<?php if ( ! empty( $category ) ) : ?>
								<div class="article-categories">
									<span class="category-badge"><?php echo esc_html( $category ); ?></span>
								</div>
							<?php endif; ?>
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
				
				<?php
				// Get user-specific accessible articles if basic subscription
				$user_id                = get_current_user_id();
				$accessible_article_ids = [];
				$subscription_types     = [];
				$has_basic              = false;

				if ( is_user_logged_in() && class_exists( '\LABGENZ_CM\Subscriptions\SubscriptionHandler' ) ) {
					$subscription_types = \LABGENZ_CM\Subscriptions\SubscriptionHandler::get_user_subscription_types( $user_id );

					foreach ( $subscription_types as $type ) {
						if ( strpos( $type, 'basic' ) !== false ) {
							$has_basic = true;
							break;
						}
					}

					if ( $has_basic ) {
						$accessible_article_ids = get_user_meta( $user_id, '_accessible_article_ids', true );
					}
				}

				// Show sidebar only for basic users with accessible articles
				if ( is_user_logged_in() && $has_basic && ! empty( $accessible_article_ids ) && is_array( $accessible_article_ids ) ) :
					?>
				<div class="user-accessible-articles-sidebar" style="flex: 0 0 30%; min-width: 300px; background: #f9f9f9; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
					<h3 style="margin-top: 0; color: #2c3e50; font-weight: 600; font-size: 20px; padding-bottom: 10px; border-bottom: 2px solid #e7e7e7;">
						Your Available Articles
					</h3>
					<p style="color: #5a6a7e; font-size: 15px;">
						Articles you have access to since <?php echo date( 'F j, Y', strtotime( get_user_meta( $user_id, '_subscription_start_date', true ) ) ); ?>
					</p>
					
					<div class="accessible-articles-list" style="max-height: 500px; overflow-y: auto; padding-right: 10px;">
						<?php
						// Query accessible articles
						if ( ! empty( $accessible_article_ids ) ) {
							$args = [
								'post_type'      => 'mlmmc_artiicle',
								'post_status'    => 'publish',
								'posts_per_page' => 50,
								'post__in'       => $accessible_article_ids,
								'orderby'        => 'date',
								'order'          => 'DESC',
							];

							$accessible_query = new \WP_Query( $args );

							if ( $accessible_query->have_posts() ) :
								while ( $accessible_query->have_posts() ) :
									$accessible_query->the_post();
									$article_date     = get_the_date( 'F j, Y' );
									$article_thumb    = get_the_post_thumbnail_url( get_the_ID(), 'thumbnail' );
									$category         = $this->articles_handler->get_article_category( get_the_ID() );
									$is_today_article = ( $daily_data['article']->ID === get_the_ID() );
									?>
								<div class="accessible-article-item" style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eaeaea; display: flex; align-items: center; <?php echo $is_today_article ? 'background-color: #f0f7ff; padding: 10px; border-radius: 8px;' : ''; ?>">
									<?php if ( $article_thumb ) : ?>
									<div class="article-thumb" style="flex: 0 0 60px; margin-right: 12px;">
										<img src="<?php echo esc_url( $article_thumb ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 6px;">
									</div>
									<?php endif; ?>
									<div class="article-info" style="flex: 1;">
										<h4 style="margin: 0 0 5px; font-size: 16px; line-height: 1.3;">
											<a href="<?php echo esc_url( get_permalink() ); ?>" style="color: #2c3e50; text-decoration: none;" onmouseover="this.style.color='#3498db'" onmouseout="this.style.color='#2c3e50'">
												<?php echo esc_html( get_the_title() ); ?>
												<?php if ( $is_today_article ) : ?>
												<span style="background: #3498db; color: white; font-size: 11px; padding: 2px 6px; border-radius: 3px; margin-left: 5px; vertical-align: middle;">Today</span>
												<?php endif; ?>
											</a>
										</h4>
										<div style="display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: #7f8c8d;">
											<span><?php echo esc_html( $article_date ); ?></span>
											<?php if ( ! empty( $category ) ) : ?>
											<span style="background: #f5f5f5; padding: 2px 8px; border-radius: 4px; font-size: 12px;"><?php echo esc_html( $category ); ?></span>
											<?php endif; ?>
										</div>
									</div>
								</div>
									<?php
								endwhile;
								wp_reset_postdata();
							else :
								echo '<p>No accessible articles found.</p>';
							endif;
						} else {
							echo '<p>You do not have any accessible articles yet.</p>';
						}
						?>
					</div>
				</div>
				<?php endif; ?>
				<?php if ( $atts['show_upsell'] === 'true' ) : ?>
					<div class="daily-article-upsell" style="flex: 1; min-width: 100%; background: linear-gradient(to right, #f8f9fa, #e9ecef); border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); margin-top: 20px;">
						<div class="upsell-content">
							<?php if ( ! $this->user_has_premium_access() ) : ?>
							<h3 style="color: #2c3e50; margin-top: 0; font-size: 22px; font-weight: 600;">Get Access to Premium Content</h3>
							<button class="upsell-button" style="background-color: #3498db; color: white; border: none; padding: 12px 24px; font-size: 16px; font-weight: 600; border-radius: 6px; cursor: pointer; transition: all 0.3s ease; margin: 15px 0; display: inline-block; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 4px 6px rgba(52, 152, 219, 0.2);" onmouseover="this.style.backgroundColor='#2980b9'" onmouseout="this.style.backgroundColor='#3498db'" onclick="window.location.href='<?php echo esc_url( home_url( '#pricing' ) ); ?>'">
								Get Full Access
							</button>
							<?php else : ?>
							<div style="margin-bottom: 15px; text-align: center;">
								<a href="<?php echo esc_url( home_url( '/mlmmc-articles/' ) ); ?>" class="view-all-articles-button" style="background-color: #3498db; color: white; border: none; padding: 12px 24px; font-size: 16px; font-weight: 600; border-radius: 6px; cursor: pointer; transition: all 0.3s ease; display: inline-block; text-decoration: none; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 4px 6px rgba(52, 152, 219, 0.2);" onmouseover="this.style.backgroundColor='#2980b9'" onmouseout="this.style.backgroundColor='#3498db'">
									View All Articles
								</a>
							</div>
							<?php endif; ?>
							
							<?php
							// Get random articles for display -- not used
							$args = [
								'post_type'      => self::POST_TYPE,
								'post_status'    => 'publish',
								'posts_per_page' => intval( $atts['num_related'] ),
								'orderby'        => 'rand',
								'post__not_in'   => [ $daily_data['article']->ID ], // Exclude current article
							];

							$random_articles = new \WP_Query( $args );

							if ( $random_articles->have_posts() ) :
								?>
								<div class="related-articles" style="margin-top: 25px; border-top: 1px solid #dee2e6; padding-top: 25px;">
									<h4 style="color: #2c3e50; font-size: 22px; margin-bottom: 15px; font-weight: 700; position: relative; display: inline-block;">
										Explore More Articles
										<span style="position: absolute; bottom: -4px; left: 0; width: 40px; height: 3px; background-color: #3498db;"></span>
									</h4>
									<p style="color: #5a6a7e; margin-bottom: 20px; font-size: 16px; line-height: 1.6;">Check out these selected articles from our premium collection:</p>
									<div class="article-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; margin-top: 10px;">
									<?php
									$count        = 0;
									$min_articles = 9; // Minimum number of articles to show
									while ( $random_articles->have_posts() ) :
										$random_articles->the_post();
										++$count;
										// Get author info
										$author_name = get_post_meta( get_the_ID(), 'mlmmc_article_author', true );
										$author_name = $author_name ? $author_name : get_the_author();
										$author_id   = get_post_field( 'post_author', get_the_ID() );

										// Get author image
										$author_image_id = get_post_meta( get_the_ID(), 'mlmmc_author_photo', true );
										$author_image    = $author_image_id ? wp_get_attachment_image_url( $author_image_id, 'thumbnail' ) : '';
										$has_video       = get_post_meta( get_the_ID(), 'mlmmc_video_link', true );
										if ( ! $author_image ) {
											$author_image = get_avatar_url( $author_id, [ 'size' => 40 ] );
										}

										// Get featured image if available
										$thumb_url = get_the_post_thumbnail_url( get_the_ID(), 'thumbnail' );
										$has_thumb = ! empty( $thumb_url );
										?>
										<div class="article-card" style="position: relative;background-color: white;border-radius: 10px;overflow: hidden;transition: 0.3s;box-shadow: rgba(0, 0, 0, 0.08) 0px 3px 8px;display: flex;flex-direction: column;border: 1px solid rgba(0, 0, 0, 0.05);transform: translateY(0px);padding: 10px;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 8px 20px rgba(0,0,0,0.12)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 3px 8px rgba(0,0,0,0.08)'">
											<?php if ( $has_thumb ) : ?>
											<div class="article-card-image" style="height: 150px; overflow: hidden; position: relative;">
												<img src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
											</div>
											<?php endif; ?>
											<div class="article-card-content" >
											<h5 style="margin: 0 0 8px 0; font-size: 16px; line-height: 1.3; font-weight: 600;">
												<a href="<?php echo esc_url( get_permalink() ); ?>" style="color: inherit; text-decoration: none;" onmouseover="this.style.color='#3498db'" onmouseout="this.style.color='inherit'">
													<?php echo esc_html( get_the_title() ); ?>
												</a>
											</h5>
												<div class="article-meta" style="font-size: 13px; color: #7f8c8d; margin-top: auto; display: flex; align-items: center; border-top: 1px solid #f5f5f5; padding-top: 8px; margin-top: 8px;">
													<?php if ( $author_image ) : ?>
													<div class="article-author-avatar" style="margin-right: 10px; flex-shrink: 0;">
														<img src="<?php echo esc_url( $author_image ); ?>" alt="<?php echo esc_attr( $author_name ); ?>" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid #f1f1f1; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
													</div>
													<?php endif; ?>
													<div>
														<span class="article-author-small" style="display: block; font-weight: 600; color: #34495e;">
															<?php echo esc_html( $author_name ); ?>
														</span>
														<span class="article-date-small" style="font-size: 12px; color: #95a5a6;">
															<?php echo get_the_date( 'M j, Y' ); ?>
														</span>
													</div>
												</div>
												<?php
												if ( $has_video ) {
													echo '<div class="video-badge" style="position: absolute;bottom: 10px;left: 10px;background-color: green;color: white;padding: 2px 10px;/* border-radius: 4px; */font-size: 12px;font-weight: 600;box-shadow: 0 2px 4px rgba(0,0,0,0.2);">Has Video</div>';
												}
												?>
											</div>
										</div>
										<?php
										// Display between 9 and 12 articles
										if ( $count >= intval( $atts['num_related'] ) || ( $count >= $min_articles && $count >= 9 ) ) {
											break;
										}
									endwhile;
									?>
									</div>
									
									<div class="view-more" style="margin-top: 20px; text-align: center;">
										<a href="<?php echo esc_url( home_url( '/mlmmc-articles/' ) ); ?>" style="color: #3498db; text-decoration: none; font-weight: 600; font-size: 15px; display: inline-block; padding: 8px 0;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
											View All Articles →
										</a>
									</div>
								</div>
								<?php
								wp_reset_postdata();
							endif;
							?>
						</div>
					</div>
				<?php endif; ?>
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
		return '
		<div class="daily-article-wrapper no-article">
			<div class="daily-article-header">
				<h2 class="daily-article-title">Article of the Day</h2>
			</div>
			<div class="daily-article-content">
				<p>No article available today. Please check back later.</p>
			</div>
		</div>';
	}

	/**
	 * Get daily article data for admin purposes.
	 *
	 * @return array|null
	 */
	public function get_daily_article_admin_data(): ?array {
		$daily_data = $this->get_current_daily_article();

		if ( ! $daily_data ) {
			return null;
		}

		return [
			'article_id'    => $daily_data['article']->ID,
			'article_title' => $daily_data['article']->post_title,
			'day_start'     => $daily_data['day_start'],
			'selected_date' => $daily_data['selected_date'],
			'article_url'   => get_permalink( $daily_data['article']->ID ),
			'next_update'   => wp_next_scheduled( 'mlmmc_update_daily_article' ),
		];
	}

	/**
	 * Manually trigger daily article update (for admin use).
	 */
	public function manual_update_daily_article(): void {
		$this->update_daily_article();
	}

	/**
	 * Clear daily article cache.
	 */
	public function clear_cache(): void {
		ArticleCacheHelper::clear_all();
	}

	/**
	 * Get user-accessible articles based on subscription type
	 *
	 * @param int $user_id User ID
	 * @param int $limit Maximum number of articles to return
	 * @return array Array of WP_Post objects
	 */
	public function get_user_accessible_articles( int $user_id, int $limit = 50 ): array {
		if ( ! is_user_logged_in() || ! $user_id ) {
			return [];
		}

		// Check if user has premium access (can view all articles)
		if ( class_exists( '\LABGENZ_CM\Subscriptions\SubscriptionHandler' ) ) {
			$has_premium_access = \LABGENZ_CM\Subscriptions\SubscriptionHandler::user_has_resource_access( $user_id, 'can_view_mlm_articles' );

			if ( $has_premium_access ) {
				// Get all articles
				$args = [
					'post_type'      => self::POST_TYPE,
					'post_status'    => 'publish',
					'posts_per_page' => $limit,
					'orderby'        => 'date',
					'order'          => 'DESC',
				];

				$query = new \WP_Query( $args );
				return $query->posts;
			}

			// Check for basic subscription
			$subscription_types = \LABGENZ_CM\Subscriptions\SubscriptionHandler::get_user_subscription_types( $user_id );
			$has_basic          = false;

			foreach ( $subscription_types as $type ) {
				if ( strpos( $type, 'basic' ) !== false ) {
					$has_basic = true;
					break;
				}
			}

			if ( $has_basic ) {
				$accessible_article_ids = get_user_meta( $user_id, '_accessible_article_ids', true );

				if ( ! empty( $accessible_article_ids ) && is_array( $accessible_article_ids ) ) {
					$args = [
						'post_type'      => self::POST_TYPE,
						'post_status'    => 'publish',
						'posts_per_page' => $limit,
						'post__in'       => $accessible_article_ids,
						'orderby'        => 'date',
						'order'          => 'DESC',
					];

					$query = new \WP_Query( $args );
					return $query->posts;
				}
			}
		}

		// Only return the current daily article if no other access
		$daily_data = $this->get_current_daily_article();
		return $daily_data ? [ $daily_data['article'] ] : [];
	}

	/**
	 * Get random articles with optional video filter.
	 *
	 * @param bool|string $had_vid Whether to include only articles with videos, or 'mixed' for mixed results.
	 * @param int         $min_articles Minimum number of articles to fetch.
	 * @param int         $max_articles Maximum number of articles to fetch.
	 * @return array List of articles with video information.
	 */
	public function get_random_articles( $had_vid = false, int $min_articles = 1, int $max_articles = 12 ): array {
		$total_articles = rand( $min_articles, $max_articles );
		$articles       = [];
		$reviews_handler = new ReviewsHandler();

		if ( $had_vid === 'mixed' ) {
			$video_count    = (int) ceil( $total_articles * 0.4 );
			$no_video_count = $total_articles - $video_count;

			// Get articles with videos
			if ( $video_count > 0 ) {
				$video_args = [
					'post_type'      => self::POST_TYPE,
					'post_status'    => 'publish',
					'posts_per_page' => $video_count,
					'orderby'        => 'rand',
					'meta_query'     => [
						[
							'key'     => 'mlmmc_video_link',
							'value'   => '',
							'compare' => '!=',
						],
					],
				];

				$video_query = new \WP_Query( $video_args );
				if ( $video_query->have_posts() ) {
					while ( $video_query->have_posts() ) {
						$video_query->the_post();
						$category   = $this->articles_handler->get_article_category( get_the_ID() );
						$articles[] = [
							'id'        => get_the_ID(),
							'title'     => get_the_title(),
							'link'      => get_permalink(),
							'has_video' => true,
							'category'  => $category ? $category : 'Uncategorized',
							'avg_rating'=> $reviews_handler->get_average_rating( get_the_ID() ),
						];
					}
					wp_reset_postdata();
				}
			}

			// Get articles without videos
			if ( $no_video_count > 0 ) {
				$no_video_args = [
					'post_type'      => self::POST_TYPE,
					'post_status'    => 'publish',
					'posts_per_page' => $no_video_count,
					'orderby'        => 'rand',
					'meta_query'     => [
						[
							'key'     => 'mlmmc_video_link',
							'value'   => '',
							'compare' => '=',
						],
					],
				];

				$no_video_query = new \WP_Query( $no_video_args );
				if ( $no_video_query->have_posts() ) {
					while ( $no_video_query->have_posts() ) {
						$no_video_query->the_post();
						$category   = $this->articles_handler->get_article_category( get_the_ID() );
						$articles[] = [
							'id'        => get_the_ID(),
							'title'     => get_the_title(),
							'link'      => get_permalink(),
							'has_video' => false,
							'category'  => $category ? $category : 'Uncategorized',
							'avg_rating'=> $reviews_handler->get_average_rating( get_the_ID() ),
							
						];
					}
					wp_reset_postdata();
				}
			}

			// Shuffle the combined results
			shuffle( $articles );
		} else {
			// Original logic for true/false
			$args = [
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => $total_articles,
				'orderby'        => 'rand',
			];

			if ( $had_vid === true ) {
				$args['meta_query'] = [
					[
						'key'     => 'mlmmc_video_link',
						'value'   => '',
						'compare' => '!=',
					],
				];
			}

			$query = new \WP_Query( $args );

			if ( $query->have_posts() ) {
				while ( $query->have_posts() ) {
					$query->the_post();
					$has_video  = get_post_meta( get_the_ID(), 'mlmmc_video_link', true );
					$category   = $this->articles_handler->get_article_category( get_the_ID() );
					$articles[] = [
						'id'        => get_the_ID(),
						'title'     => get_the_title(),
						'link'      => get_permalink(),
						'has_video' => ! empty( $has_video ),
						'category'  => $category ? $category : 'Uncategorized',
						'avg_rating'=> $reviews_handler->get_average_rating( get_the_ID() ),
					];
				}
				wp_reset_postdata();
			}
		}

		return $articles;
	}
}