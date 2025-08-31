'use strict';

/**
 * PageEnhancer Class
 * Handles URL cleanup and dynamic height adjustments
 */
class PageEnhancer {
    constructor() {
        this.dailyArticleSelector = '.bp-daily-article';
        this.heightPadding = 50;
        this.observer = null;
        
        this.init();
    }
    
    /**
     * Initialize all functionality
     */
    init() {
        this.removeRedirectFilters();
        this.initDailyArticleHeight();
    }
    
    /**
     * Remove e-redirect parameter from all anchor tags
     */
    removeRedirectFilters() {
        jQuery('a').each((index, element) => {
            const $link = jQuery(element);
            const href = $link.attr('href');
            
            if (!href) return;
            
            try {
                const url = new URL(href, window.location.origin);
                url.searchParams.delete('e-redirect');
                $link.attr('href', url.toString());
            } catch (error) {
                console.warn('PageEnhancer: Invalid URL detected:', href, error);
            }
        });
    }
    
    /**
     * Initialize daily article height adjustment
     */
    initDailyArticleHeight() {
        const $dailyArticle = jQuery(this.dailyArticleSelector);
        
        if ($dailyArticle.length === 0) {
            return;
        }
        
        // Initial height adjustment
        this.adjustDailyHeight($dailyArticle);
        
        // Set up mutation observer for dynamic changes
        this.setupHeightObserver($dailyArticle[0]);
    }
    
    /**
     * Calculate and set the height of daily article container
     * @param {jQuery} $container - The daily article container
     */
    adjustDailyHeight($container) {
        let maxHeight = 0;
        
        $container.children().each((index, child) => {
            const height = jQuery(child).outerHeight();
            if (height > maxHeight) {
                maxHeight = height + this.heightPadding;
            }
        });
        
        $container.css('height', `${maxHeight}px`);
    }
    
    /**
     * Set up mutation observer for height changes
     * @param {HTMLElement} element - The element to observe
     */
    setupHeightObserver(element) {
        if (this.observer) {
            this.observer.disconnect();
        }
        
        this.observer = new MutationObserver(() => {
            this.adjustDailyHeight(jQuery(element));
        });
        
        this.observer.observe(element, {
            childList: true,
            subtree: true,
            characterData: true
        });
    }
    
    /**
     * Cleanup method for destroying the instance
     */
    destroy() {
        if (this.observer) {
            this.observer.disconnect();
            this.observer = null;
        }
    }
}

// Initialize when DOM is ready
jQuery(document).ready(() => {
    window.pageEnhancer = new PageEnhancer();
});