/**
 * Map Core Module
 * 
 * Handles the core map initialization and setup
 */
var MapCore = (function($) {
    'use strict';

    // Private variables
    var map = null;
    
    // Public API
    return {
        /**
         * Initialize the map
         * 
         * @param {string} containerId The ID of the container to initialize the map in
         * @param {object} options Map initialization options
         * @return {object|null} The map object or null if initialization failed
         */
        initMap: function(containerId, options) {
            console.log('MapCore: Initializing map');
            
            var container = $('#' + containerId);
            
            // Check if the map container exists
            if (container.length === 0) {
                console.log('MapCore: Map container not found');
                return null;
            }

            // Check if Leaflet is available
            if (typeof L === 'undefined') {
                console.error('MapCore: Leaflet library not loaded');
                container.html('<div class="map-error">Map library not loaded. Please refresh the page.</div>');
                return null;
            }
            
            // Set default options
            var defaultOptions = {
                center: [33.5945, -7.6200], // Default to Casablanca
                zoom: 10,
                minZoom: 3,
                maxZoom: 18, // Limit maximum zoom level for privacy
                tileLayer: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            };
            
            // Merge options
            options = $.extend({}, defaultOptions, options);

            // Ensure the container has proper styling
            container.css({
                'width': '100%',
                'height': '500px',
                'background-color': '#f0f0f0',
                'border': '1px solid #ccc',
                'position': 'relative',
                'display': 'block !important',
                'visibility': 'visible !important',
                'z-index': '1'
            });

            console.log('MapCore: Container dimensions:', container.width(), 'x', container.height());
            console.log('MapCore: Container visible:', container.is(':visible'));
            
            // Initialize the map with the options including maxZoom constraint
            map = L.map(containerId, {
                center: options.center,
                zoom: options.zoom,
                minZoom: options.minZoom,
                maxZoom: options.maxZoom
            });
            
            // Add the tile layer
            L.tileLayer(
                options.tileLayer,
                {
                    attribution: options.attribution,
                    maxZoom: options.maxZoom
                }
            ).addTo(map);
            
            // Add zoom level listener for debugging
            map.on('zoomend', function() {
                console.log('MapCore: Zoom level changed to', map.getZoom());
            });
            
            // Force map to resize after multiple delays to ensure it's properly rendered
            this.forceMapResize(map);
            
            return map;
        },
        
        /**
         * Force map to resize
         * 
         * @param {object} map The map object
         */
        forceMapResize: function(map) {
            if (!map) return;
            
            // Resize immediately
            map.invalidateSize();
            
            // Schedule additional resizes
            [100, 500, 1000].forEach(function(delay) {
                setTimeout(function() {
                    console.log('MapCore: Forcing map resize (' + delay + 'ms)...');
                    map.invalidateSize();
                }, delay);
            });
        },
        
        /**
         * Get the map object
         * 
         * @return {object|null} The map object or null if not initialized
         */
        getMap: function() {
            return map;
        }
    };
})(jQuery);
