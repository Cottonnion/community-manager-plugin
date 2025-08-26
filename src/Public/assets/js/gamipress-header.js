/**
 * Gamification Header Interface
 *
 * Handles the display of gamification elements in the site header
 */
class GamificationHeaderInterface {

	constructor() {
		this.gamificationData = null;
		this.$header          = null;
		this.$headerAside     = null;
		this.localStorageKey  = 'ghi_header_collapsed';
		this.mobileBreakpoint = 768;

		this.defaultImages = {
			credits: 'https://v2mlmmasteryclub.labgenz.com/wp-content/uploads/2025/07/credit-card-1-1.png',
			toggle: 'https://v2mlmmasteryclub.labgenz.com/wp-content/uploads/2025/07/ghi-collapse-header.png'
		};

		this.init();
	}

	init() {
		if (typeof window.gamificationData === 'undefined') return;

		this.gamificationData = window.gamificationData;
		this.$header          = jQuery('.site-header-container');
		this.$headerAside     = jQuery('.header-aside-inner');

		if (this.$header.length === 0) return;

		this.render();
		this.bindEvents();
	}

	render() {
		const coinsHtml = this.createCoinsElement();

		if (coinsHtml) {
			this.injectIntoHeader(coinsHtml);
		}
	}

createCoinsElement() {
    const data    = this.gamificationData;
    const credits = data.current_coins ?? 0;
    const points  = data.current_reward_points ?? 0;
	const shop_url = data.shop_url

    return `
        <div class="ghi-coins-navbar" style="display:flex;align-items:center;gap:10px;margin-left:auto;">
            <a href="${shop_url}?currency=credits" style="display:flex;align-items:center;text-decoration:none;color:inherit;">
                <img src="${this.defaultImages.credits}" style="width:24px;height:24px;margin-right:5px;">
                <span style="font-weight:bold;">Credits: ${credits}</span>
            </a>
            <a href="${shop_url}?currency=points" style="display:flex;align-items:center;text-decoration:none;color:inherit;">
                <span style="font-weight:bold;">Points: ${points}</span>
            </a>
        </div>
    `;
}


	injectIntoHeader(coinsHtml) {
		// Append to the end of primary-navbar on desktop
		if (window.innerWidth > this.mobileBreakpoint) {
			jQuery('#primary-navbar').append(coinsHtml);
		}
	}

	bindEvents() {
		const self = this;

		// Keep original mobile toggle logic untouched
		const $mobileToggle = jQuery('.ghi-toggle-btn-mobile img');
		jQuery(window).on('resize', function() {
			if (window.innerWidth <= self.mobileBreakpoint) {
				$mobileToggle.show();
			} else {
				$mobileToggle.hide();
			}
		});
	}
}

// Initialize
jQuery(document).ready(function() {
	new GamificationHeaderInterface();
});
