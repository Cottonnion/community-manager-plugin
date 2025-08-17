/**
 * Map Utilities Module
 * 
 * Provides utility functions for the members map
 */
var MapUtils = (function($) {
    'use strict';
    
    // Public API
    return {
        /**
         * Apply privacy offset to coordinates based on zoom level
         * 
         * @param {number} lat Original latitude
         * @param {number} lng Original longitude
         * @param {number} zoomLevel Current map zoom level
         * @param {number} maxZoom Maximum zoom level before applying offset
         * @return {object} Object containing adjusted lat/lng coordinates
         */
        applyPrivacyOffset: function(lat, lng, zoomLevel, maxZoom) {
            console.log('MapUtils: applyPrivacyOffset called with', lat, lng, zoomLevel, maxZoom);
            
            // Validate input parameters
            if (typeof lat !== 'number' || typeof lng !== 'number' || 
                isNaN(lat) || isNaN(lng)) {
                console.error('MapUtils: Invalid coordinates in applyPrivacyOffset', lat, lng);
                return [lat, lng]; // Return original coordinates if invalid
            }
            
            // If zoom level is less than or equal to maxZoom, return original coordinates
            if (zoomLevel <= maxZoom) {
                // Return as array to match expected format in marker-manager.js
                return [lat, lng];
            }
            
            // Calculate offset based on zoom level
            // The higher the zoom, the more offset we apply
            var offsetMultiplier = (zoomLevel - maxZoom) * 0.0005;
            
            // Maximum offset: about 100-300 meters at maximum zoom
            var maxOffset = 0.003;
            
            // Cap the offset at the maximum value
            offsetMultiplier = Math.min(offsetMultiplier, maxOffset);
            
            // Generate a random offset within the range
            var latOffset = (Math.random() * 2 - 1) * offsetMultiplier;
            var lngOffset = (Math.random() * 2 - 1) * offsetMultiplier;
            
            // Calculate new coordinates
            var newLat = lat + latOffset;
            var newLng = lng + lngOffset;
            
            console.log('MapUtils: Applied privacy offset', { 
                original: [lat, lng],
                offset: [latOffset, lngOffset],
                result: [newLat, newLng]
            });
            
            // Apply the offset and return as array [lat, lng]
            return [newLat, newLng];
        },
        /**
         * Add custom styles for the map
         */
        addCustomStyles: function() {
            // Check if styles are already added
            if ($('#map-custom-styles').length > 0) {
                return;
            }
            
            // Create custom styles for markers and tooltips
            var customCSS = `
                /* Marker Styles */
                .marker-cluster {
                    background-clip: padding-box;
                    border-radius: 20px;
                }
                
                .marker-cluster div {
                    width: 30px;
                    height: 30px;
                    margin-left: 5px;
                    margin-top: 5px;
                    text-align: center;
                    border-radius: 15px;
                    font-size: 12px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                
                .marker-cluster span {
                    font-weight: bold;
                }
                
                .marker-cluster-small {
                    background-color: rgba(181, 226, 140, 0.6);
                }
                
                .marker-cluster-small div {
                    background-color: rgba(110, 204, 57, 0.6);
                }
                
                .marker-cluster-medium {
                    background-color: rgba(241, 211, 87, 0.6);
                }
                
                .marker-cluster-medium div {
                    background-color: rgba(240, 194, 12, 0.6);
                }
                
                .marker-cluster-large {
                    background-color: rgba(253, 156, 115, 0.6);
                }
                
                .marker-cluster-large div {
                    background-color: rgba(241, 128, 23, 0.6);
                }
                
                /* Custom Markers */
                .member-avatar-icon {
                    background: transparent;
                }
                
                .avatar-marker {
                    width: 40px;
                    height: 40px;
                    border-radius: 50%;
                    overflow: hidden;
                    border: 2px solid #fff;
                    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
                }
                
                .avatar-marker img {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                }
                
                /* Role-based avatar borders */
                .avatar-marker.role-admin {
                    border: 3px solid #e74c3c !important; /* Red for admins */
                    box-shadow: 0 0 8px rgba(255, 0, 0, 0.6);
                }
                
                .avatar-marker.role-moderator {
                    border: 3px solid #3498db !important; /* Blue for moderators */
                    box-shadow: 0 0 8px rgba(0, 0, 255, 0.6);
                }
                
                .avatar-marker.role-member {
                    border: 3px solid #2ecc71 !important; /* Green for members */
                    box-shadow: 0 0 8px rgba(0, 50, 0, 0.6);
                }
                
                .multi-member-avatar-icon {
                    background: transparent;
                }
                
                .multi-member-marker {
                    width: 60px;
                    height: 60px;
                    background-color: rgba(255, 255, 255, 0.8);
                    border: 2px solid #fff;
                    border-radius: 50%;
                    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
                    display: flex;
                    flex-wrap: wrap;
                    align-items: center;
                    justify-content: center;
                    padding: 2px;
                }
                
                .group-avatar-img {
                    width: 28px;
                    height: 28px;
                    border-radius: 50%;
                    margin: 1px;
                    object-fit: cover;
                    border: 1px solid #fff;
                }
                
                /* Role-based avatar borders for grouped markers */
                .group-avatar-img.role-admin {
                    border: 2px solid #e74c3c !important; /* Red for admins */
                    box-shadow: 0 0 5px rgba(255, 0, 0, 0.6);
                }
                
                .group-avatar-img.role-moderator {
                    border: 2px solid #3498db !important; /* Blue for moderators */
                    box-shadow: 0 0 5px rgba(0, 0, 255, 0.6);
                }
                
                .group-avatar-img.role-member {
                    border: 2px solid #2ecc71 !important; /* Green for members */
                    box-shadow: 0 0 5px rgba(0, 255, 0, 0.6);
                }
                
                .avatar-more {
                    position: absolute;
                    right: 3px;
                    bottom: 3px;
                    background-color: #0073aa;
                    color: #fff;
                    border-radius: 50%;
                    width: 18px;
                    height: 18px;
                    font-size: 10px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-weight: bold;
                    border: 1px solid #fff;
                }
                
                .multi-member-icon {
                    background: transparent;
                }
                
                .multi-member-marker {
                    width: 36px;
                    height: 36px;
                    line-height: 36px;
                    background-color: rgba(0, 120, 255, 0.9);
                    border: 2px solid #fff;
                    border-radius: 50%;
                    color: #fff;
                    text-align: center;
                    font-weight: bold;
                    font-size: 12px;
                    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
                }
                
                /* Popup Styles */
                .leaflet-popup-content-wrapper {
                    border-radius: 4px !important;
                    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1) !important;
                    overflow: hidden !important;
                    padding: 0 !important;
                    background: var(--bb-content-background-color, #fff) !important;
                }
                
                .leaflet-popup-content {
                    margin: 0 !important;
                    width: 200px !important;
                }
                
                .leaflet-popup-tip {
                    background: var(--bb-content-background-color, #fff) !important;
                }
                
                .member-popup {
                    padding: 0 !important;
                    text-align: center !important;
                    font-family: var(--bb-font-family-base, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif) !important;
                }
                
                .member-popup-avatar {
                    width: 70px !important;
                    height: 70px !important;
                    border-radius: 50% !important;
                    border: 3px solid var(--bb-content-background-color, #fff) !important;
                    margin: 0 auto !important;
                    margin-top: -35px !important;
                    overflow: hidden !important;
                    position: relative !important;
                    z-index: 2 !important;
                    background: var(--bb-content-background-color, #fff) !important;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1) !important;
                }
                
                .member-popup-avatar img {
                    width: 100% !important;
                    height: 100% !important;
                    object-fit: cover !important;
                }
                
                .member-popup-header {
                    background-color: var(--bb-primary-color, #0073aa) !important;
                    height: 40px !important;
                }
                
                .member-popup-content {
                    padding: 15px !important;
                    padding-top: 5px !important;
                    background: var(--bb-content-background-color, #fff) !important;
                }
                
                .member-popup h3 {
                    margin: 10px 0 5px !important;
                    font-size: 16px !important;
                    color: var(--bb-headings-color, #333) !important;
                    font-weight: 600 !important;
                    line-height: 1.2 !important;
                }
                
                .member-role {
                    color: var(--bb-body-text-color, #666) !important;
                    font-size: 12px !important;
                    margin: 0 0 15px !important;
                }
                
                .member-buttons {
                    display: flex !important;
                    justify-content: center !important;
                    gap: 10px !important;
                    margin-top: 15px !important;
                }
                
                .member-profile-link, .member-connect-link {
                    display: inline-flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    padding: 6px 12px !important;
                    text-decoration: none !important;
                    border-radius: 3px !important;
                    font-size: 12px !important;
                    transition: all 0.2s !important;
                    font-weight: 500 !important;
                    min-width: 70px !important;
                }
                
                .member-profile-link {
                    background-color: var(--bb-primary-color, #0073aa) !important;
                    color: var(--bb-primary-button-text-color, #fff) !important;
                }
                
                .member-connect-link {
                    background-color: var(--bb-success-color, #4CAF50) !important;
                    color: #fff !important;
                }
                
                .member-profile-link:hover {
                    background-color: var(--bb-primary-color-hover, #005177) !important;
                }
                
                .member-connect-link:hover {
                    background-color: var(--bb-success-color-hover, #388E3C) !important;
                }
                
                /* Grouped Members Popup */
                .grouped-members-popup {
                    max-height: 350px !important;
                    overflow-y: auto !important;
                    padding: 0 !important;
                    width: 100% !important;
                    border-radius: 4px !important;
                    font-family: var(--bb-font-family-base, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif) !important;
                }
                
                .grouped-popup-header {
                    background-color: var(--bb-primary-color, #0073aa) !important;
                    color: var(--bb-primary-button-text-color, white) !important;
                    padding: 10px 15px !important;
                    position: sticky !important;
                    top: 0 !important;
                    z-index: 5 !important;
                }
                
                .grouped-members-popup h3 {
                    margin: 0 !important;
                    font-size: 14px !important;
                    text-align: center !important;
                    color: var(--bb-primary-button-text-color, white) !important;
                    font-weight: 500 !important;
                }
                
                .members-list {
                    display: flex !important;
                    flex-direction: column !important;
                    padding: 5px !important;
                    background: var(--bb-content-background-color, white) !important;
                }
                
                .member-item {
                    display: flex !important;
                    align-items: center !important;
                    padding: 8px !important;
                    border-bottom: 1px solid var(--bb-content-border-color, #f0f0f0) !important;
                    transition: background-color 0.2s !important;
                }
                
                .member-item-role {
                    margin: 0 !important;
                    margin-top: -5px !important;
                    margin-bottom: 5px !important;
                    font-size: 10px !important;
                    line-height: 1.2 !important;
                }
                
                .member-item:hover {
                    background-color: var(--bb-content-alternate-background-color, #f9f9f9) !important;
                }
                
                .member-item:last-child {
                    border-bottom: none !important;
                }
                
                .member-mini-avatar {
                    width: 36px !important;
                    height: 36px !important;
                    border-radius: 50% !important;
                    margin-right: 10px !important;
                    border: 2px solid var(--bb-content-border-color, #f0f0f0) !important;
                }
                
                .member-details {
                    flex: 1 !important;
                }
                
                .member-name {
                    margin: 0 0 3px !important;
                    font-weight: 500 !important;
                    font-size: 13px !important;
                    color: var(--bb-headings-color, #333) !important;
                }
                
                .member-item-buttons {
                    display: flex !important;
                    gap: 6px !important;
                    margin-top: 3px !important;
                }
                
                .member-item-profile, .member-item-connect {
                    font-size: 11px !important;
                    text-decoration: none !important;
                    padding: 2px 6px !important;
                    border-radius: 2px !important;
                    transition: all 0.2s !important;
                }
                
                .member-item-profile {
                    background-color: var(--bb-primary-color, #0073aa) !important;
                    color: var(--bb-primary-button-text-color, white) !important;
                }
                
                .member-item-connect {
                    background-color: var(--bb-success-color, #4CAF50) !important;
                    color: white !important;
                }
                
                .member-item-profile:hover {
                    background-color: var(--bb-primary-color-hover, #005177) !important;
                }
                
                .member-item-connect:hover {
                    background-color: var(--bb-success-color-hover, #388E3C) !important;
                }
                
                /* Error and Loading Indicators */
                .map-loading, .map-error, .map-no-data {
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    background-color: rgba(255, 255, 255, 0.9);
                    padding: 10px 15px;
                    border-radius: 4px;
                    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
                    z-index: 1000;
                    text-align: center;
                }
                
                .map-error {
                    color: #d32f2f;
                    border-left: 4px solid #d32f2f;
                }
                
                .map-no-data {
                    color: #0288d1;
                    border-left: 4px solid #0288d1;
                }
            `;
            
            // Add the custom styles to the page
            $('head').append('<style id="map-custom-styles">' + customCSS + '</style>');
            
            console.log('MapUtils: Custom styles added');
        },
        
        /**
         * Set map bounds to fit all markers
         * 
         * @param {object} map The Leaflet map object
         * @param {array} bounds Array of coordinate pairs [[lat, lng], ...]
         */
        fitMapBounds: function(map, bounds) {
            console.log('MapUtils: Setting map bounds:', bounds);
            
            if (!map || !bounds) {
                console.log('MapUtils: Invalid map or bounds');
                return;
            }
            
            if (bounds.length > 0) {
                if (bounds.length === 1) {
                    // Single marker - center on it
                    console.log('MapUtils: Centering map on single marker');
                    map.setView(bounds[0], 12);
                } else {
                    // Multiple markers - fit bounds
                    console.log('MapUtils: Fitting map to multiple markers');
                    map.fitBounds(bounds, {padding: [20, 20]});
                }
            } else {
                console.log('MapUtils: No valid bounds, keeping default view');
            }
        },
        
        /**
         * Check if the Leaflet CSS is loaded
         * 
         * @return {boolean} True if Leaflet CSS is loaded
         */
        isLeafletCssLoaded: function() {
            var leafletCssLoaded = false;
            
            $('link[href*="leaflet"]').each(function() {
                console.log('MapUtils: Found Leaflet CSS:', $(this).attr('href'));
                leafletCssLoaded = true;
            });
            
            if (!leafletCssLoaded) {
                console.warn('MapUtils: Leaflet CSS may not be loaded');
            }
            
            return leafletCssLoaded;
        },
        
        /**
         * Apply privacy offset to coordinates based on zoom level
         * 
         * @param {number} lat Original latitude
         * @param {number} lng Original longitude
         * @param {number} zoom Current zoom level
         * @param {number} maxZoom Maximum zoom level before applying offset
         * @return {array} [offsetLat, offsetLng] - Offset coordinates
         */
        applyPrivacyOffset: function(lat, lng, zoom, maxZoom) {
            // Check for valid inputs
            if (isNaN(lat) || isNaN(lng) || isNaN(zoom) || isNaN(maxZoom)) {
                console.error('MapUtils: Invalid inputs to applyPrivacyOffset', lat, lng, zoom, maxZoom);
                return [lat, lng]; // Return original coordinates if inputs are invalid
            }
            
            // Only apply offset if zoom level is greater than maxZoom
            if (zoom <= maxZoom) {
                return [lat, lng];
            }
            
            console.log('MapUtils: Applying privacy offset for zoom level', zoom);
            
            // Calculate a random offset based on zoom level
            // The higher the zoom, the smaller the area shown, so we need a stronger offset
            // to prevent users from finding the exact location by following the marker
            
            // Base offset in degrees - roughly 100-300 meters depending on latitude
            var baseOffset = 0.003; // Increased from 0.002 for more privacy
            
            // Use a combination of zoom level and member identity to create a consistent offset
            // This ensures the same offset direction is maintained at all zoom levels for the same member
            
            // Create a deterministic seed based on the coordinates
            // This ensures a consistent direction of offset
            var seedValue = (lat * 1000000 + lng * 1000000);
            
            // Create a hash from the seed value
            var hashCode = function(str) {
                var hash = 0;
                for (var i = 0; i < str.length; i++) {
                    hash = ((hash << 5) - hash) + str.charCodeAt(i);
                    hash = hash & hash; // Convert to 32bit integer
                }
                return hash;
            };
            
            var seed = hashCode(seedValue.toString());
            
            // Generate a consistent angle for this location (0-360 degrees)
            var angle = (seed % 360) * (Math.PI / 180);
            
            // Calculate stronger offset based on zoom level - as zoom increases, apply more offset
            var zoomFactor = Math.pow(1.2, zoom - maxZoom);
            var offsetAmount = baseOffset * zoomFactor;
            
            // Calculate offset using trigonometry for consistent direction
            var latOffset = offsetAmount * Math.sin(angle);
            var lngOffset = offsetAmount * Math.cos(angle);
            
            // Apply the offset
            var offsetLat = lat + latOffset;
            var offsetLng = lng + lngOffset;
            
            console.log('MapUtils: Applied offset of', latOffset.toFixed(6), lngOffset.toFixed(6), 
                        'for location', lat.toFixed(6), lng.toFixed(6), 'at zoom', zoom);
            
            return [offsetLat, offsetLng];
        }
    };
})(jQuery);
