<?php
/**
 * Template for displaying articles in a card grid layout
 * 
 * This template displays a collection of articles with search and filtering options.
 * 
 * @package Labgenz_Community_Management
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Available variables:
 * 
 * @var array $articles - Articles data 
 * @var array $categories - Available categories for filtering
 * @var int $posts_per_page - Number of posts per page
 * @var string $layout - Layout type (grid or list)
 * @var int $columns - Number of columns in the grid
 * @var int $excerpt_length - Maximum length of excerpts
 * @var bool $show_excerpt - Whether to show excerpts
 * @var bool $show_author - Whether to show author information
 * @var bool $show_date - Whether to show the publication date
 * @var bool $show_category - Whether to show the category
 * @var bool $show_rating - Whether to show ratings
 * @var bool $show_search - Whether to show search box
 * @var bool $show_filters - Whether to show filtering options
 * @var bool $has_video - Whether article have video
 * @var int $total_articles - Total number of articles
 * @var int $max_pages - Maximum number of pages
 */
?>
<div class="mlmmc-articles-container" 
     data-show-excerpt="<?php echo $show_excerpt ? 'true' : 'false'; ?>"
     data-show-author="<?php echo $show_author ? 'true' : 'false'; ?>"
     data-show-date="<?php echo $show_date ? 'true' : 'false'; ?>"
     data-show-category="<?php echo $show_category ? 'true' : 'false'; ?>"
     data-show-rating="<?php echo $show_rating ? 'true' : 'false'; ?>"
     data-excerpt-length="<?php echo esc_attr($excerpt_length); ?>"
     data-posts-per-page="<?php echo esc_attr($posts_per_page); ?>"
     data-layout="<?php echo esc_attr($layout); ?>"
>

    <?php if ($show_search || $show_filters): ?>
    <div class="mlmmc-articles-search-filter">
        <?php if ($show_search): ?>
        <div class="mlmmc-search-row">
            <div class="mlmmc-search-box">
                <input type="text" id="mlmmc-search-input" placeholder="<?php _e('Search articles by name or content...', 'labgenz-community-management'); ?>">
                <button id="mlmmc-search-button" aria-label="<?php _e('Search', 'labgenz-community-management'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
                    </svg>
                </button>
            </div>

            <!-- Layout Toggle -->
            <div class="mlmmc-layout-toggle">
                <button id="mlmmc-grid-view" class="mlmmc-layout-btn <?php echo $layout === 'grid' ? 'active' : ''; ?>" title="<?php _e('Grid View', 'labgenz-community-management'); ?>">
                    <svg width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M1 2.5A1.5 1.5 0 0 1 2.5 1h3A1.5 1.5 0 0 1 7 2.5v3A1.5 1.5 0 0 1 5.5 7h-3A1.5 1.5 0 0 1 1 5.5v-3zm8 0A1.5 1.5 0 0 1 10.5 1h3A1.5 1.5 0 0 1 15 2.5v3A1.5 1.5 0 0 1 13.5 7h-3A1.5 1.5 0 0 1 9 5.5v-3zm-8 8A1.5 1.5 0 0 1 2.5 9h3A1.5 1.5 0 0 1 7 10.5v3A1.5 1.5 0 0 1 5.5 15h-3A1.5 1.5 0 0 1 1 13.5v-3zm8 0A1.5 1.5 0 0 1 10.5 9h3a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1-1.5 1.5h-3A1.5 1.5 0 0 1 9 13.5v-3z"/>
                    </svg>
                </button>
                <button id="mlmmc-list-view" class="mlmmc-layout-btn <?php echo $layout === 'list' ? 'active' : ''; ?>" title="<?php _e('List View', 'labgenz-community-management'); ?>">
                    <svg width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5z"/>
                    </svg>
                </button>
            </div>
            
            
            <?php if ($show_filters): ?>
                <div class="mlmmc-filter-dropdown">
                    <button id="mlmmc-author-filter-toggle">
                        <span><?php _e('show by Author', 'labgenz-community-management'); ?></span>
                        <span class="chevron-icon">▼</span>
                    </button>
                    <div id="mlmmc-author-dropdown">
                        <div class="mlmmc-dropdown-header">
                            <input type="text" id="mlmmc-author-search" placeholder="<?php _e('Search authors by name...', 'labgenz-community-management'); ?>">
                        </div>
                        <div class="mlmmc-author-options mlmmc-category-options"></div>
                        <div class="mlmmc-dropdown-footer">
                            <button id="mlmmc-clear-authors"><?php _e('Clear All', 'labgenz-community-management'); ?></button>
                            <button id="mlmmc-apply-filter"><?php _e('Apply', 'labgenz-community-management'); ?></button>
                        </div>
                    </div>
                    <!-- <div class="mlmmc-selected-authors"></div> -->
                </div>
                
                <div class="mlmmc-filter-dropdown">
                    <button id="mlmmc-category-filter-toggle">
                        <span><?php _e('show by Category', 'labgenz-community-management'); ?></span>
                        <span class="chevron-icon">▼</span>
                    </button>
                    <div id="mlmmc-category-dropdown">
                        <div class="mlmmc-dropdown-header">
                            <input type="text" id="mlmmc-category-search" placeholder="<?php _e('Search categories by name...', 'labgenz-community-management'); ?>">
                        </div>
                        <div class="mlmmc-category-options">
                            <div class="mlmmc-checkbox-option">
                                <label>
                                    <input type="checkbox" value="all" checked> <?php _e('All Categories', 'labgenz-community-management'); ?>
                                </label>
                            </div>
                            <?php 
                            // Get category counts
                            $category_counts = isset($categories_with_counts) ? $categories_with_counts : [];
                            $category_count_map = [];
                            
                            // Create a lookup map for category counts
                            foreach ($category_counts as $cat_data) {
                                if (isset($cat_data['name']) && isset($cat_data['count'])) {
                                    $category_count_map[$cat_data['name']] = $cat_data['count'];
                                }
                            }
                            
                            foreach ($categories as $category): 
                                $count = isset($category_count_map[$category]) ? $category_count_map[$category] : 0;
                            ?>
                            <div class="mlmmc-checkbox-option">
                                <label>
                                    <input type="checkbox" class="mlmmc-category-checkbox" value="<?php echo esc_attr($category); ?>"> 
                                    <?php echo esc_html($category); ?> 
                                    <span class="mlmmc-category-count">(<?php echo $count; ?>)</span>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mlmmc-dropdown-footer">
                            <button id="mlmmc-clear-categories"><?php _e('Clear All', 'labgenz-community-management'); ?></button>
                            <button id="mlmmc-apply-categories"><?php _e('Apply', 'labgenz-community-management'); ?></button>
                        </div>
                    </div>
                    <!-- <div class="mlmmc-selected-categories"></div> -->
                </div>
                
                <div class="mlmmc-filter-dropdown">
                    <button id="mlmmc-rating-filter-toggle">
                        <span><?php _e('show by Rating', 'labgenz-community-management'); ?></span>
                        <span class="chevron-icon">▼</span>
                    </button>
                    <div id="mlmmc-rating-dropdown">
                        <div class="mlmmc-rating-options">
                            <?php 
                            // Use rating counts if they're available in the template context
                            $display_rating_counts = isset($rating_counts) && is_array($rating_counts) ? 
                                $rating_counts : [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
                            
                            for ($i = 5; $i >= 1; $i--): 
                            ?>
                            <div class="mlmmc-checkbox-option">
                                <label>
                                    <input type="checkbox" value="<?php echo $i; ?>"> 
                                    <?php echo $i; ?> <?php _e('Stars', 'labgenz-community-management'); ?>
                                    <span class="mlmmc-rating-count">(<?php echo isset($display_rating_counts[$i]) ? $display_rating_counts[$i] : 0; ?>)</span>
                                </label>
                            </div>
                            <?php endfor; ?>
                        </div>
                        <div class="mlmmc-dropdown-footer">
                            <button id="mlmmc-clear-ratings"><?php _e('Clear All', 'labgenz-community-management'); ?></button>
                            <button id="mlmmc-apply-ratings"><?php _e('Apply', 'labgenz-community-management'); ?></button>
                        </div>
                    </div>
                </div>
                
                <div class="mlmmc-video-filter-section">
                    <div class="mlmmc-video-filter-wrapper">
                        <label class="mlmmc-video-checkbox-label">
                            <input type="checkbox" id="mlmmc-video-only" value="true">
                            <span class="mlmmc-checkbox-custom"></span>
                            <span class="mlmmc-checkbox-text"><?php _e('Show only articles with videos', 'labgenz-community-management'); ?></span>
                        </label>
                    </div>
                    
                    <div class="mlmmc-filter-info-section">
                        <div class="mlmmc-search-notice">
                            <svg class="mlmmc-info-icon" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                                <path d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533L8.93 6.588zM9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0z"/>
                            </svg>
                            <span><?php _e('You can choose more than 1 author and 1 category to search', 'labgenz-community-management'); ?></span>
                        </div>
                        
                        <div class="mlmmc-articles-counter">
                            <div class="mlmmc-counter-badge">
                                <span id="mlmmc-total-count"><?php echo sprintf(_n('%d article found', '%d articles found', $total_articles, 'labgenz-community-management'), $total_articles); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
            <?php endif; // End if show_filters ?>
        </div>
        
        <div class="mlmmc-selected-authors"></div>
        <div class="mlmmc-selected-categories"></div>
        <div class="mlmmc-selected-ratings"></div>
        <?php endif; // End if show_search ?>
    </div>
    <?php endif; // End if show_search || show_filters ?>
    
    <div class="mlmmc-articles-content-wrapper">
        <div class="mlmmc-articles-loading" style="display: none;">
            <div class="mlmmc-loading-spinner"></div>
            <div class="mlmmc-loading-text"><?php _e('Loading articles...', 'labgenz-community-management'); ?></div>
        </div>
        
        <div id="mlmmc-articles-wrapper" class="mlmmc-articles-<?php echo esc_attr($layout); ?>" <?php if ($layout === 'grid'): ?>style="grid-template-columns: repeat(<?php echo esc_attr($columns); ?>, 1fr);"<?php endif; ?>>
            <?php 
            if (!empty($articles)) {
                foreach ($articles as $article) {
                    include LABGENZ_CM_PATH . 'templates/articles/article-card-item.php';
                }
            } else {
                ?>
                <div class="mlmmc-articles-no-results">
                    <?php _e('No articles found.', 'labgenz-community-management'); ?>
                </div>
                <?php
            }
            ?>
        </div>
        
        <?php if (!empty($articles) && $max_pages > 1): ?>
        <div class="mlmmc-articles-pagination" style="margin-top: 40px; text-align: center;">
            <button id="mlmmc-load-more" class="mlmmc-load-more-button" data-page="1" data-max-pages="<?php echo esc_attr($max_pages); ?>">
                <?php _e('Load More Articles', 'labgenz-community-management'); ?>
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const gridBtn = document.getElementById('mlmmc-grid-view');
    const listBtn = document.getElementById('mlmmc-list-view');
    const articlesWrapper = document.getElementById('mlmmc-articles-wrapper');
    const container = document.querySelector('.mlmmc-articles-container');
    
    if (gridBtn && listBtn && articlesWrapper && container) {
        gridBtn.addEventListener('click', function() {
            switchLayout('grid');
        });
        
        listBtn.addEventListener('click', function() {
            switchLayout('list');
        });
        
        function switchLayout(layout) {
            // Update button states
            gridBtn.classList.toggle('active', layout === 'grid');
            listBtn.classList.toggle('active', layout === 'list');
            
            // Update wrapper classes and styles
            articlesWrapper.className = 'mlmmc-articles-' + layout;
            
            if (layout === 'grid') {
                const columns = <?php echo esc_js($columns); ?>;
                articlesWrapper.style.gridTemplateColumns = 'repeat(' + columns + ', 1fr)';
            } else {
                articlesWrapper.style.gridTemplateColumns = '';
            }

            // ✅ Update container data attribute
            container.setAttribute('data-layout', layout);
        }
    }
});
</script>
