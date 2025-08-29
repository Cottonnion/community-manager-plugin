/**
 * Universal Tooltip System - tooltip-master.js
 * A global tooltip system that creates tooltips for any element with data-this-mean attribute
 * 
 * Usage: <div data-this-mean="This is a tooltip">Hover me</div>
 */

(function() {
    'use strict';

    // Configuration
    const TOOLTIP_CONFIG = {
        attribute: 'data-this-mean',
        className: 'universal-tooltip',
        showDelay: 300,
        hideDelay: 100,
        offset: 8,
        maxWidth: 250,
        zIndex: 10000,
        animations: true,
        smartPositioning: true
    };

    // Tooltip state
    let activeTooltip = null;
    let showTimeout = null;
    let hideTimeout = null;
    let mouseX = 0;
    let mouseY = 0;

    // Track mouse position for dynamic positioning
    document.addEventListener('mousemove', (e) => {
        mouseX = e.clientX;
        mouseY = e.clientY;
    });

    /**
     * Create tooltip element
     */
    function createTooltip(content) {
        const tooltip = document.createElement('div');
        tooltip.className = TOOLTIP_CONFIG.className;
        tooltip.innerHTML = content;
        tooltip.style.cssText = `
            position: fixed;
            background: rgba(30, 30, 30, 0.95);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            line-height: 1.4;
            max-width: ${TOOLTIP_CONFIG.maxWidth}px;
            word-wrap: break-word;
            z-index: ${TOOLTIP_CONFIG.zIndex};
            pointer-events: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            opacity: 0;
            transform: scale(0.8) translateY(4px);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        `;
        
        // Add arrow
        const arrow = document.createElement('div');
        arrow.className = 'tooltip-arrow';
        arrow.style.cssText = `
            position: absolute;
            width: 0;
            height: 0;
            border: 5px solid transparent;
            border-top-color: rgba(30, 30, 30, 0.95);
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
        `;
        tooltip.appendChild(arrow);
        
        return tooltip;
    }

    /**
     * Position tooltip relative to cursor or element
     */
    function positionTooltip(tooltip, element) {
        const rect = element.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;
        
        let x = mouseX;
        let y = mouseY - tooltipRect.height - TOOLTIP_CONFIG.offset;
        
        // Smart positioning - adjust if tooltip goes off screen
        if (TOOLTIP_CONFIG.smartPositioning) {
            // Horizontal adjustments
            if (x + tooltipRect.width > viewportWidth - 10) {
                x = viewportWidth - tooltipRect.width - 10;
            }
            if (x < 10) {
                x = 10;
            }
            
            // Vertical adjustments - show below if not enough space above
            if (y < 10) {
                y = mouseY + TOOLTIP_CONFIG.offset;
                // Flip arrow
                const arrow = tooltip.querySelector('.tooltip-arrow');
                if (arrow) {
                    arrow.style.cssText = `
                        position: absolute;
                        width: 0;
                        height: 0;
                        border: 5px solid transparent;
                        border-bottom-color: rgba(30, 30, 30, 0.95);
                        top: -10px;
                        left: 50%;
                        transform: translateX(-50%);
                    `;
                }
            }
            
            // If still off screen, position relative to element center
            if (y + tooltipRect.height > viewportHeight - 10) {
                y = rect.top - tooltipRect.height - TOOLTIP_CONFIG.offset;
                x = rect.left + (rect.width / 2) - (tooltipRect.width / 2);
            }
        }
        
        tooltip.style.left = x + 'px';
        tooltip.style.top = y + 'px';
    }

    /**
     * Show tooltip
     */
    function showTooltip(element, content) {
        // Clear any existing timeouts
        clearTimeout(hideTimeout);
        clearTimeout(showTimeout);
        
        showTimeout = setTimeout(() => {
            // Hide any existing tooltip
            hideTooltip();
            
            // Create new tooltip
            activeTooltip = createTooltip(content);
            document.body.appendChild(activeTooltip);
            
            // Position tooltip
            positionTooltip(activeTooltip, element);
            
            // Show with animation
            requestAnimationFrame(() => {
                if (activeTooltip) {
                    activeTooltip.style.opacity = '1';
                    activeTooltip.style.transform = 'scale(1) translateY(0)';
                }
            });
        }, TOOLTIP_CONFIG.showDelay);
    }

    /**
     * Hide tooltip
     */
    function hideTooltip() {
        clearTimeout(showTimeout);
        
        if (activeTooltip) {
            const tooltip = activeTooltip;
            
            if (TOOLTIP_CONFIG.animations) {
                tooltip.style.opacity = '0';
                tooltip.style.transform = 'scale(0.8) translateY(4px)';
                
                hideTimeout = setTimeout(() => {
                    if (tooltip.parentNode) {
                        tooltip.parentNode.removeChild(tooltip);
                    }
                }, 200);
            } else {
                if (tooltip.parentNode) {
                    tooltip.parentNode.removeChild(tooltip);
                }
            }
            
            activeTooltip = null;
        }
    }

    /**
     * Handle mouse enter
     */
    function handleMouseEnter(event) {
        const element = event.currentTarget;
        const content = element.getAttribute(TOOLTIP_CONFIG.attribute);
        
        if (content && content.trim()) {
            // Add active class for styling purposes
            element.classList.add('has-active-tooltip');
            showTooltip(element, content.trim());
        }
    }

    /**
     * Handle mouse leave
     */
    function handleMouseLeave(event) {
        const element = event.currentTarget;
        element.classList.remove('has-active-tooltip');
        
        clearTimeout(showTimeout);
        hideTimeout = setTimeout(hideTooltip, TOOLTIP_CONFIG.hideDelay);
    }

    /**
     * Initialize tooltips for existing elements
     */
    function initializeTooltips() {
        const elements = document.querySelectorAll(`[${TOOLTIP_CONFIG.attribute}]`);
        
        elements.forEach(element => {
            // Remove existing listeners to prevent duplicates
            element.removeEventListener('mouseenter', handleMouseEnter);
            element.removeEventListener('mouseleave', handleMouseLeave);
            
            // Add new listeners
            element.addEventListener('mouseenter', handleMouseEnter);
            element.addEventListener('mouseleave', handleMouseLeave);
            
            // Add hover cursor
            if (!element.style.cursor) {
                element.style.cursor = 'help';
            }
        });
    }

    /**
     * Observer for dynamically added elements
     */
    function setupMutationObserver() {
        const observer = new MutationObserver((mutations) => {
            let shouldReinit = false;
            
            mutations.forEach((mutation) => {
                // Check for added nodes
                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach((node) => {
                        if (node.nodeType === Node.ELEMENT_NODE) {
                            if (node.hasAttribute && node.hasAttribute(TOOLTIP_CONFIG.attribute)) {
                                shouldReinit = true;
                            }
                            // Check children
                            if (node.querySelectorAll) {
                                const children = node.querySelectorAll(`[${TOOLTIP_CONFIG.attribute}]`);
                                if (children.length > 0) {
                                    shouldReinit = true;
                                }
                            }
                        }
                    });
                }
                
                // Check for attribute changes
                if (mutation.type === 'attributes' && 
                    mutation.attributeName === TOOLTIP_CONFIG.attribute) {
                    shouldReinit = true;
                }
            });
            
            if (shouldReinit) {
                // Debounce reinitalization
                clearTimeout(window.tooltipReinitTimeout);
                window.tooltipReinitTimeout = setTimeout(initializeTooltips, 100);
            }
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: [TOOLTIP_CONFIG.attribute]
        });
    }

    /**
     * Handle scroll and resize events
     */
    function handleWindowEvents() {
        let scrollTimeout;
        
        window.addEventListener('scroll', () => {
            if (activeTooltip) {
                activeTooltip.style.opacity = '0.5';
                
                clearTimeout(scrollTimeout);
                scrollTimeout = setTimeout(() => {
                    if (activeTooltip) {
                        activeTooltip.style.opacity = '1';
                    }
                }, 150);
            }
        }, { passive: true });
        
        window.addEventListener('resize', () => {
            hideTooltip();
        });
    }

    /**
     * Add global CSS for enhanced styling
     */
    function addGlobalStyles() {
        const styleId = 'universal-tooltip-styles';
        
        if (!document.getElementById(styleId)) {
            const style = document.createElement('style');
            style.id = styleId;
            style.textContent = `
                .has-active-tooltip {
                    position: relative;
                }
                
                .has-active-tooltip::before {
                    content: '';
                    position: absolute;
                    inset: -2px;
                    border-radius: 4px;
                    background: linear-gradient(45deg, transparent, rgba(59, 130, 246, 0.1), transparent);
                    opacity: 0;
                    transition: opacity 0.2s ease;
                    pointer-events: none;
                    z-index: -1;
                }
                
                .has-active-tooltip:hover::before {
                    opacity: 1;
                }
                
                /* Dark theme support */
                @media (prefers-color-scheme: dark) {
                    .${TOOLTIP_CONFIG.className} {
                        background: rgba(255, 255, 255, 0.95) !important;
                        color: #1f2937 !important;
                        border: 1px solid rgba(0, 0, 0, 0.1) !important;
                    }
                    
                    .${TOOLTIP_CONFIG.className} .tooltip-arrow {
                        border-top-color: rgba(255, 255, 255, 0.95) !important;
                        border-bottom-color: rgba(255, 255, 255, 0.95) !important;
                    }
                }
                
                /* High contrast mode support */
                @media (prefers-contrast: high) {
                    .${TOOLTIP_CONFIG.className} {
                        background: black !important;
                        color: white !important;
                        border: 2px solid white !important;
                    }
                }
                
                /* Reduced motion support */
                @media (prefers-reduced-motion: reduce) {
                    .${TOOLTIP_CONFIG.className} {
                        transition: none !important;
                    }
                }
            `;
            document.head.appendChild(style);
        }
    }

    /**
     * Public API
     */
    window.TooltipMaster = {
        init: initializeTooltips,
        hide: hideTooltip,
        config: TOOLTIP_CONFIG,
        
        // Method to manually show tooltip
        show: function(element, content, options = {}) {
            const mergedOptions = { ...TOOLTIP_CONFIG, ...options };
            showTooltip(element, content);
        },
        
        // Method to update configuration
        configure: function(options) {
            Object.assign(TOOLTIP_CONFIG, options);
        }
    };

    /**
     * Initialize when DOM is ready
     */
    function initialize() {
        addGlobalStyles();
        initializeTooltips();
        setupMutationObserver();
        handleWindowEvents();
        
        console.log('🔧 TooltipMaster initialized - Add data-this-mean="Your tooltip" to any element!');
    }

    // Initialize based on document state
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }

    // Also initialize on window load as fallback
    window.addEventListener('load', initialize);

})();

