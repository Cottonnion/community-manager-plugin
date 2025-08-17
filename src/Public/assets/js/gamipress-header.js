/**
 * Gamification Header Interface
 * 
 * Handles the display and interaction of gamification elements in the site header
 */
class GamificationHeaderInterface {
    
    /**
     * Constructor
     */
    constructor() {
        this.gamificationData = null;
        this.$header = null;
        this.$headerAside = null;
        this.localStorageKey = 'ghi_header_collapsed';
        this.mobileBreakpoint = 768;
        
        // Default images
        this.defaultImages = {
            credits: 'https://v2mlmmasteryclub.labgenz.com/wp-content/uploads/2025/07/credit-card-1-1.png',
            toggle: 'https://v2mlmmasteryclub.labgenz.com/wp-content/uploads/2025/07/ghi-collapse-header.png'
        };
        
        this.init();
    }
    
    /**
     * Initialize the gamification header interface
     */
    init() {
        if (typeof window.gamificationData === 'undefined') {
            return;
        }
        
        this.gamificationData = window.gamificationData;
        this.$header = jQuery('.site-header-container');
        this.$headerAside = jQuery('.header-aside-inner');
        
        if (this.$header.length === 0) {
            return;
        }
        
        this.render();
        this.bindEvents();
    }
    
    /**
     * Render the gamification interface
     */
    render() {
        const coinsHtml = this.createCoinsElement();
        const rankHtml = this.createRankElement();
        
        if (coinsHtml || rankHtml) {
            this.injectIntoHeader(coinsHtml, rankHtml);
            this.setupToggleButton();
            this.setupMobileToggle();
            this.applyInitialState();
        }
    }
    
    /**
     * Create coins and reward points element
     * 
     * @returns {string} HTML string for coins element
     */
    createCoinsElement() {
        const data = this.gamificationData;

        // Always show the coins/points container, even if values are empty
        let coinsHtml = '<div class="ghi-coins" style="display: flex; align-items: center; gap: 8px;">';

        // Credits section
        const credits = (typeof data.current_coins !== 'undefined' && data.current_coins !== null && data.current_coins !== '') ? data.current_coins : 0;
        coinsHtml += '<img src="' + this.defaultImages.credits + '" alt="Credits" style="width: 24px; height: 24px; margin-left: 15px;">' +
            '<span style="margin: 0 0 0 5px; line-height: 1; font-weight: bold;">Credits: ' + credits + '</span>';
        if (data.coins_img) {
            coinsHtml += '<img src="' + data.coins_img + '" alt="Coins" style="width: 24px; height: 24px;">';
        }

        // Reward Points section
        const points = (typeof data.current_reward_points !== 'undefined' && data.current_reward_points !== null && data.current_reward_points !== '') ? data.current_reward_points : 0;
        coinsHtml += '<h6 style="font-weight: bold; margin: 0; line-height: 1; font-size: 14px;">Points:</h6>' +
            '<span style="margin: 0; line-height: 1;">' + points + '</span>';
        // if (data.reward_points_img) {
        //     coinsHtml += '<img src="' + data.reward_points_img + '" alt="Reward Points" style="width: 24px; height: 24px;">';
        // }

        coinsHtml += '</div>';

        return coinsHtml;
    }
    
    /**
     * Create rank and progress element
     * 
     * @returns {string} HTML string for rank element
     */
    createRankElement() {
        const data = this.gamificationData;
        
        if (!data.current_rank) {
            return '';
        }
        
        const rankImageHtml = this.createRankImage(data);
        const progressData = this.calculateProgressData(data);
        
        return '<div class="ghi-rank">' +
            rankImageHtml +
            '<div class="ghi-rank-info">' +
                '<div class="ghi-rank-details">' +
                    '<span class="ghi-rank-name">' + progressData.percentageText + '</span>' +
                '</div>' +
                '<div class="ghi-progress-bar">' +
                    '<div class="ghi-progress-fill" style="width: ' + progressData.progressWidth + '%;"></div>' +
                '</div>' +
                '<div class="ghi-points-bottom">' + progressData.pointsText + '</div>' +
            '</div>' +
        '</div>';
    }
    
    /**
     * Create rank image HTML
     * 
     * @param {Object} data Gamification data
     * @returns {string} HTML string for rank image
     */
    createRankImage(data) {
        if (data.rank_img) {
            return '<div class="ghi-rank-avatar">' +
                '<img src="' + data.rank_img + '" alt="Rank">' +
            '</div>';
        } else {
            return '<div class="ghi-rank-avatar ghi-rank-letter">' +
                data.current_rank.charAt(0).toUpperCase() +
            '</div>';
        }
    }
    
    /**
     * Calculate progress data for display
     * 
     * @param {Object} data Gamification data
     * @returns {Object} Progress data object
     */
    calculateProgressData(data) {
        let pointsText = '';
        let progressWidth = 0;
        let percentageText = '';
        
        if (data.points_needed > 0) {
            pointsText = data.current_points + ' / ' + data.points_needed;
            progressWidth = data.completion;
            const remainingPercentage = 100 - data.completion;
            percentageText = remainingPercentage.toFixed(0) + '% To The Next Level';
        } else if (data.completion === 100) {
            pointsText = data.current_points + ' (Max Rank)';
            progressWidth = 100;
            percentageText = 'Max Level Achieved';
        } else {
            pointsText = data.current_points + ' points';
            progressWidth = Math.min(100, (data.current_points / 100) * 100);
            const remainingPercentage = 100 - progressWidth;
            percentageText = remainingPercentage.toFixed(0) + '% To Next Level';
        }
        
        return {
            pointsText,
            progressWidth,
            percentageText
        };
    }
    
    /**
     * Inject gamification elements into header
     * 
     * @param {string} coinsHtml HTML for coins element
     * @param {string} rankHtml HTML for rank element
     */
    injectIntoHeader(coinsHtml, rankHtml) {
        const containerHtml = '<div class="ghi-glass-container" style="transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); overflow: hidden; max-height: 200px; opacity: 1;">' +
            '<div class="ghi-content">' + rankHtml + coinsHtml + '</div>' +
            '</div>';
        
        this.$header.after(containerHtml);
    }
    
    /**
     * Setup desktop toggle button
     */
    setupToggleButton() {
        if (this.$headerAside.length > 0) {
            const toggleBtnHtml = '<div class="ghi-toggle-btn" style="position: relative;margin-right: 0px;cursor: pointer;display: flex;align-items: center;justify-content: center;transition: 0.3s;transform: scale(1);" onmouseover="this.style.transform=\'scale(1.1)\';" onmouseout="this.style.transform=\'scale(1)\';" data-ghi-toggle="true">' +
                '<img src="' + this.defaultImages.toggle + '" alt="Toggle" style="width: 20px; height: 20px; transition: transform 0.3s ease;">' +
                '</div>';
            
            this.$headerAside.prepend(toggleBtnHtml);
        }
    }
    
    /**
     * Setup mobile toggle button
     */
    setupMobileToggle() {
        const mobileToggleHtml = '<div class="ghi-toggle-btn-mobile" style="display: none;">' +
            '<img src="' + this.defaultImages.toggle + '" alt="Toggle" style="transition: transform 0.3s ease;">' +
            '</div>';
        
        jQuery('body').append(mobileToggleHtml);
        
        this.updateMobileToggleVisibility();
    }
    
    /**
     * Apply initial collapsed/expanded state
     */
    applyInitialState() {
        const savedState = localStorage.getItem(this.localStorageKey);
        const isCollapsed = savedState === 'true';
        const $container = jQuery('.ghi-glass-container');
        const $desktopIcon = jQuery('.ghi-toggle-btn img');
        const $mobileIcon = jQuery('.ghi-toggle-btn-mobile img');
        
        if (isCollapsed) {
            $container.addClass('ghi-hidden').css({
                'max-height': '0',
                'opacity': '0',
                'margin-top': '0',
                'margin-bottom': '0',
                'display': 'none'
            });
            
            $desktopIcon.css('transform', 'rotate(180deg)');
            $mobileIcon.css('transform', 'rotate(180deg)');
        }
    }
    
    /**
     * Update mobile toggle button visibility based on screen size
     */
    updateMobileToggleVisibility() {
        const $mobileToggle = jQuery('.ghi-toggle-btn-mobile');
        
        if (window.innerWidth <= this.mobileBreakpoint) {
            $mobileToggle.show();
        } else {
            $mobileToggle.hide();
        }
    }
    
    /**
     * Toggle header visibility
     */
    toggleHeaderVisibility() {
        const $container = jQuery('.ghi-glass-container');
        const isHidden = $container.hasClass('ghi-hidden');
        const $desktopIcon = jQuery('.ghi-toggle-btn img');
        const $mobileIcon = jQuery('.ghi-toggle-btn-mobile img');

        if (isHidden) {
            // Show container
            $container.removeClass('ghi-hidden').css({
                'max-height': '200px',
                'opacity': '1',
                'margin-top': '0',
                'margin-bottom': '0',
                'display': 'block'
            });

            $desktopIcon.css('transform', 'rotate(0deg)');
            $mobileIcon.css('transform', 'rotate(0deg)');

            localStorage.setItem(this.localStorageKey, 'false');
        } else {
            // Hide container
            $container.addClass('ghi-hidden').css({
                'max-height': '0',
                'opacity': '0',
                'margin-top': '0',
                'margin-bottom': '0',
                'display': 'none'
            });

            $desktopIcon.css('transform', 'rotate(180deg)');
            $mobileIcon.css('transform', 'rotate(180deg)');

            localStorage.setItem(this.localStorageKey, 'true');
        }
    }
    
    /**
     * Bind event listeners
     */
    bindEvents() {
        const self = this;
        
        // Desktop toggle click
        this.$headerAside.on('click', '.ghi-toggle-btn', function() {
            self.toggleHeaderVisibility();
        });
        
        // Mobile toggle click
        jQuery(document).on('click', '.ghi-toggle-btn-mobile', function() {
            self.toggleHeaderVisibility();
        });
        
        // Window resize event
        jQuery(window).on('resize', function() {
            self.updateMobileToggleVisibility();
        });
    }
}

// Initialize when document is ready
jQuery(document).ready(function() {
    new GamificationHeaderInterface();
});