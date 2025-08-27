<?php
defined('ABSPATH') || exit;

/**
 * Single Author Template
 *
 * Data passed from render_single_author() in $data
 *
 * @var array $data
 */

$author_name = $data['author_name'] ?? '';
$bio         = $data['bio'] ?? '';
$photo_url   = $data['photo_url'] ?? '';
$post_id     = $data['post_id'] ?? 0;
$author_post = $data['author_post'] ?? null;

// Get additional author articles for display
$author_article_ids = [];

if ($author_name) {
    $articles_query = new WP_Query([
        'post_type'      => 'mlmmc_artiicle',
        'posts_per_page' => -1, // get all posts
        'post_status'    => 'publish',
        'fields'         => 'ids', // return only IDs
        'meta_query'     => [
            [
                'key'     => 'mlmmc_article_author',
                'value'   => $author_name,
                'compare' => 'LIKE',
            ]
        ]
    ]);

    $author_article_ids = $articles_query->posts; // array of post IDs
}

$last_3_articles = [];
if ($author_name) {
    $last_articles_query = new WP_Query([
        'post_type'      => 'mlmmc_artiicle',
        'posts_per_page' => 3,
        'post_status'    => 'publish',
        'fields'         => 'all', // get full posts
        'meta_query'     => [
            [
                'key'     => 'mlmmc_article_author',
                'value'   => $author_name,
                'compare' => 'LIKE',
            ]
        ],
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);

    if ($last_articles_query->have_posts()) {
        while ($last_articles_query->have_posts()) {
            $last_articles_query->the_post();
            $last_3_articles[] = [
                'title' => get_the_title(),
                'url'   => get_permalink(),
                'date'  => get_the_date('M j, Y'),
                'excerpt' => get_the_excerpt(),
            ];
        }
        wp_reset_postdata();
    }
}

// Additional data fields you might want to add to your plugin later
$author_title = $data['author_title'] ?? 'Writer & Content Creator';
$location = $data['location'] ?? '';
$writing_since = $data['writing_since'] ?? '';
$email = $data['email'] ?? '';
$website_url = $data['website_url'] ?? '';
$twitter_url = $data['twitter_url'] ?? '';
$linkedin_url = $data['linkedin_url'] ?? '';
$articles_published = count($author_article_ids);
$total_articles = wp_count_posts()->publish ?? 0;
?>


<div class="mlmmc-author-profile">
    <!-- Hero Section -->
    <section class="mlmmc-hero-section">
        <div class="mlmmc-hero-container">
            <div class="mlmmc-hero-content">
                <?php if ($photo_url) : ?>
                    <img src="<?php echo esc_url($photo_url); ?>" alt="<?php echo esc_attr($author_name); ?>" class="mlmmc-avatar">
                <?php endif; ?>
                
                <div class="mlmmc-hero-info">
                    <h1 class="mlmmc-author-name"><?php echo esc_html($author_name); ?></h1>
                    <?php if ($author_title) : ?>
                        <p class="mlmmc-author-title"><?php echo esc_html($author_title); ?></p>
                    <?php endif; ?>
                    
                    <div class="mlmmc-badges">
                        <span class="mlmmc-badge">
                            <span class="mlmmc-icon article"></span>
                            <?php echo $articles_published; ?> Published Articles
                        </span>
                        <?php if ($author_post && $author_post->post_date) : 
                            $writing_since_year = date('Y', strtotime($author_post->post_date));
                        ?>
                            <span class="mlmmc-badge">
                                <span class="mlmmc-icon users"></span>
                                Writing since <?php echo esc_html($writing_since_year); ?>
                            </span>
                        <?php endif; ?>
                        <span class="mlmmc-badge">
                            <span class="mlmmc-icon book"></span>
                            Content Creator
                        </span>
                    </div>
                    
                    <div class="mlmmc-meta">
                        <?php if ($location) : ?>
                            <span class="mlmmc-meta-item">
                                <span class="mlmmc-icon map-pin"></span>
                                <?php echo esc_html($location); ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($writing_since) : ?>
                            <span class="mlmmc-meta-item">
                                <span class="mlmmc-icon calendar"></span>
                                Writing since <?php echo esc_html($writing_since); ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($email) : ?>
                            <span class="mlmmc-meta-item">
                                <span class="mlmmc-icon mail"></span>
                                <?php echo esc_html($email); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Content Section -->
    <section class="mlmmc-content-section">
        <div class="mlmmc-content-container">
            
            <!-- Main Content -->
            <div class="mlmmc-main-content">
                
                <!-- Bio & About -->
                <div class="mlmmc-card">
                    <div class="mlmmc-card-content">
                        <h2 class="mlmmc-card-title">About the Author</h2>
                        <?php if ($bio) : ?>
                            <div class="mlmmc-bio">
                                <?php echo wpautop(esc_html($bio)); ?>
                            </div>
                        <?php else: ?>
                            <div class="mlmmc-bio">
                                <p>Professional writer and content creator with a passion for sharing knowledge and engaging stories. 
                                <?php echo esc_html($author_name); ?> brings expertise and creativity to every piece, 
                                connecting with readers through thoughtful and well-researched content.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Articles -->
                <?php if (!empty($author_articles)) : ?>
                <div class="mlmmc-card">
                    <div class="mlmmc-card-content">
                        <h2 class="mlmmc-card-title">Recent Articles</h2>
                        <div class="mlmmc-articles-list">
                            <?php foreach (array_slice($author_articles, 0, 4) as $article) : ?>
                                <div class="mlmmc-article-item">
                                    <div class="mlmmc-article-info">
                                        <h3><a href="<?php echo esc_url($article['url']); ?>"><?php echo esc_html($article['title']); ?></a></h3>
                                        <p class="mlmmc-article-date"><?php echo esc_html($article['date']); ?></p>
                                        <?php if (!empty($article['excerpt'])) : ?>
                                            <p class="mlmmc-article-excerpt"><?php echo esc_html(wp_trim_words($article['excerpt'], 20)); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($author_articles) > 4) : ?>
                            <div class="mlmmc-view-more">
                                <p><strong>And <?php echo count($author_articles) - 4; ?> more articles...</strong></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($last_3_articles)) : ?>
<div class="mlmmc-card">
    <div class="mlmmc-card-content">
        <h2 class="mlmmc-card-title">Last 3 Published Articles</h2>
        <div class="mlmmc-articles-list">
            <?php foreach ($last_3_articles as $article) : ?>
                <div class="mlmmc-article-item">
                    <h3><a href="<?php echo esc_url($article['url']); ?>"><?php echo esc_html($article['title']); ?></a></h3>
                    <p class="mlmmc-article-date"><?php echo esc_html($article['date']); ?></p>
                    <?php if (!empty($article['excerpt'])) : ?>
                        <p class="mlmmc-article-excerpt"><?php echo esc_html(wp_trim_words($article['excerpt'], 20)); ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="mlmmc-sidebar">
                
                <!-- Social Links -->
                <div class="mlmmc-card">
                    <div class="mlmmc-card-content">
                        <h3 class="mlmmc-card-title">Connect</h3>
                        <div class="mlmmc-connect-buttons">
                            <?php if ($website_url) : ?>
                                <a href="<?php echo esc_url($website_url); ?>" class="mlmmc-connect-btn" target="_blank" rel="noopener">
                                    <span class="mlmmc-icon external-link"></span>
                                    Official Website
                                </a>
                            <?php endif; ?>
                            <?php if ($twitter_url) : ?>
                                <a href="<?php echo esc_url($twitter_url); ?>" class="mlmmc-connect-btn" target="_blank" rel="noopener">
                                    <span class="mlmmc-icon external-link"></span>
                                    Twitter
                                </a>
                            <?php endif; ?>
                            <?php if ($linkedin_url) : ?>
                                <a href="<?php echo esc_url($linkedin_url); ?>" class="mlmmc-connect-btn" target="_blank" rel="noopener">
                                    <span class="mlmmc-icon external-link"></span>
                                    LinkedIn
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Awards -->
                <?php if ($email) : ?>
                <div class="mlmmc-card">
                    <div class="mlmmc-card-content">
                        <h3 class="mlmmc-card-title">Contact</h3>
                        <div class="mlmmc-connect-buttons">
                            <a href="mailto:<?php echo esc_attr($email); ?>" class="mlmmc-connect-btn">
                                <span class="mlmmc-icon mail"></span>
                                <?php echo esc_html($email); ?>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Stats -->
                <?php if ($books_published || $books_sold || $languages) : ?>
                <div class="mlmmc-card">
                    <div class="mlmmc-card-content">
                        <h3 class="mlmmc-card-title">By the Numbers</h3>
                        <div class="mlmmc-stats">
                            <?php if ($books_published) : ?>
                                <div class="mlmmc-stat-item">
                                    <span class="mlmmc-stat-number"><?php echo esc_html($books_published); ?></span>
                                    <div class="mlmmc-stat-label">Published Novels</div>
                                </div>
                            <?php endif; ?>
                            <?php if ($books_sold) : ?>
                                <div class="mlmmc-stat-item">
                                    <span class="mlmmc-stat-number"><?php echo esc_html($books_sold); ?></span>
                                    <div class="mlmmc-stat-label">Books Sold</div>
                                </div>
                            <?php endif; ?>
                            <?php if ($languages) : ?>
                                <div class="mlmmc-stat-item">
                                    <span class="mlmmc-stat-number"><?php echo esc_html($languages); ?></span>
                                    <div class="mlmmc-stat-label">Languages</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
            </div>
        </div>
    </section>
</div>