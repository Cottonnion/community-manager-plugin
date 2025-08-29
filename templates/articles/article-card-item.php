<?php
/**
 * Template for rendering a single article card.
 *
 * @package Labgenz_Community_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Variables:
 * @var array $article
 * @var bool  $show_excerpt
 * @var bool  $show_author
 * @var bool  $show_date
 * @var bool  $show_category
 * @var bool  $show_rating
 * @var string $
 */
?>
<div class="mlmmc-article-card">

	<?php if ( ! empty( $article['has_thumbnail'] ) ) : ?>
		<div class="mlmmc-article-card-image">
			<img src="<?php echo esc_url( $article['thumbnail'] ); ?>" alt="<?php echo esc_attr( $article['title'] ); ?>">
		</div>
	<?php endif; ?>

	<div class="mlmmc-article-card-content">

        <?php 
        // Check for has_video in both contexts: 
        // 1. When $article array is available (shortcode render)
        // 2. When $has_video variable is available (AJAX search)
        $show_video_badge = (isset($article['has_video']) && $article['has_video']) || (isset($has_video) && $has_video);
        ?>

		<?php if ( $show_category || $show_video_badge ) : ?>
            <div class="mlmmc-article-category-video" style="display: flex;justify-content: space-between;align-items: center;gap: 10px;">
                <?php if ( $show_category && ! empty( $article['category'] ) ) : ?>
                    <div class="mlmmc-article-category">
                        <span><?php echo esc_html( $article['category'] ); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ( $show_video_badge ) : ?>
                    <div class="mlmmc-article-video-badge">
                        <?php esc_html_e( 'Has Video', 'labgenz-community-management' ); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

		<h3>
			<a href="<?php echo esc_url( $article['permalink'] ); ?>">
				<?php echo esc_html( $article['title'] ); ?>
			</a>
		</h3>

		<?php if ( $show_excerpt && ! empty( $article['excerpt'] ) ) : ?>
			<div class="mlmmc-article-excerpt">
				<p><?php echo esc_html( $article['excerpt'] ); ?></p>
			</div>
		<?php endif; ?>

		<div class="mlmmc-article-meta">
			<?php if ( $show_author ) : ?>
				<div class="mlmmc-article-author">
					<?php if ( ! empty( $article['author_image'] ) ) : ?>
						<div class="mlmmc-article-author-avatar">
							<img src="<?php echo esc_url( $article['author_image'] ); ?>" alt="<?php echo esc_attr( $article['author_name'] ); ?>">
						</div>
					<?php endif; ?>
					<a href="<?php echo esc_url( $article['author_url'] ); ?>" class="mlmmc-article-author-name">
						<?php echo esc_html( $article['author_name'] ); ?>
					</a>
				</div>
			<?php endif; ?>

			<?php if ( $show_date ) : ?>
				<div class="mlmmc-article-date">
					<?php echo esc_html( $article['date'] ); ?>
				</div>
			<?php endif; ?>
		</div>

		
		<div class="mlmmc-article-actions">
			<a href="<?php echo esc_url( $article['permalink'] ); ?>" class="mlmmc-read-more">
				<?php esc_html_e( 'Read Article', 'labgenz-community-management' ); ?> →
			</a>

			<?php if ( $show_rating && ! empty( $article['average_rating'] ) && $article['average_rating'] > 0 ) : ?>
				<div class="mlmmc-article-rating">
					<div class="mlmmc-article-rating-count">
						<?php
						echo sprintf(
							_n( '%d review', '%d reviews', $article['rating_count'], 'labgenz-community-management' ),
							(int) $article['rating_count']
						);
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>

	</div>
</div>
