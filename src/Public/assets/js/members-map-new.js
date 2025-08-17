/**
 * Members Map JavaScript
 *
 * Handles the initialization and display of the members map using Leaflet.js
 * This is the main file that coordinates all map functionality
 */
(function($) {
    'use strict';

    // Main map controller
    var MembersMap = {
        // Map properties
        map: null,
        containerId: 'members-map-container',
        
        /**
         * Initialize the members map
         */
        init: function() {
            console.log('MembersMap: Initialization started');
            
            // Check if the map container exists
            if ($('#' + this.containerId).length === 0) {
                console.log('MembersMap: Map container not found');
                return;
            }
            
            // Check if MembersMapData is available
            if (typeof MembersMapData === 'undefined') {
                console.error('MembersMap: MembersMapData not found');
                $('#' + this.containerId).html('<div class="map-error">Map configuration not found.</div>');
                return;
            }
            
            console.log('MembersMap: Initializing with data:', MembersMapData);
            
            // Debug module loading
            console.log('MembersMap: Checking module availability - MapCore:', typeof MapCore, 'MapUtils:', typeof MapUtils, 'MarkerManager:', typeof MarkerManager, 'DataHandler:', typeof DataHandler);
            
            // Add custom styles
            MapUtils.addCustomStyles();
            
            // Check if Leaflet CSS is loaded
            MapUtils.isLeafletCssLoaded();
            
            // Initialize the map
            this.map = MapCore.initMap(this.containerId, {
                center: [33.5945, -7.6200], // Default to Casablanca
                zoom: 10
            });
            
            if (!this.map) {
                console.error('MembersMap: Failed to initialize map');
                return;
            }
            
            // Initialize marker manager
            MarkerManager.init(this.map);
            
            // Remove the initialization indicator
            $('#map-init-indicator').remove();
            
            // Add privacy notice to the map
            this.addPrivacyNotice();
            
            // Load members data
            this.loadAndDisplayMembers();
        },
        
        /**
         * Add a privacy notice to the map
         */
        addPrivacyNotice: function() {
            // Create privacy notice HTML
            var privacyNoticeHtml = '<div class="map-privacy-notice" style="display:none;">' +
                '<div class="notice-content">' +
                '<p><strong>Privacy Notice:</strong> Member locations are showing with approximate offsets for privacy protection. Exact locations are not displayed when zoomed in beyond street level.</p>' +
                '</div>' +
                '<button class="notice-close" title="Dismiss">×</button>' +
                '</div>';
            
            // Add notice to the map container
            $('#' + this.containerId).append(privacyNoticeHtml);
            
            // Add custom CSS for the notice
            var noticeCSS = `
                .map-privacy-notice {
                    position: absolute;
                    bottom: 10px;
                    left: 10px;
                    background-color: rgba(255, 255, 255, 0.9);
                    padding: 8px 12px;
                    border-radius: 4px;
                    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
                    z-index: 1000;
                    max-width: 300px;
                    font-size: 12px;
                    display: flex;
                    align-items: center;
                    border-left: 4px solid #3498db;
                }
                
                .notice-content {
                    flex: 1;
                }
                
                .notice-content p {
                    margin: 0;
                    color: #333;
                }
                
                .notice-close {
                    background: none;
                    border: none;
                    color: #999;
                    font-size: 16px;
                    cursor: pointer;
                    padding: 0 0 0 8px;
                    margin: 0;
                }
                
                .notice-close:hover {
                    color: #333;
                }
            `;
            
            $('head').append('<style id="map-privacy-notice-styles">' + noticeCSS + '</style>');
            
            // Add dismiss functionality
            $('.notice-close').on('click', function() {
                $('.map-privacy-notice').fadeOut(300, function() {
                    $(this).remove();
                });
            });
        },
        
        /**
         * Load and display members on the map
         */
        loadAndDisplayMembers: function() {
            var self = this;
            
            // Load members data
            DataHandler.loadMembersData({
                containerId: this.containerId,
                ajaxUrl: MembersMapData.ajaxurl,
                groupId: MembersMapData.group_id,
                nonce: MembersMapData.nonce
            }, function(error, members) {
                if (error) {
                    console.error('MembersMap: Error loading members data:', error);
                    return;
                }
                
                if (!members || members.length === 0) {
                    console.log('MembersMap: No members data available');
                    return;
                }
                
                self.displayMembers(members);
            });
        },
        
        /**
         * Display members on the map
         * 
         * @param {array} members Array of member data
         */
        displayMembers: function(members) {
            var self = this;
            var bounds = [];
            
            console.log('MembersMap: Displaying', members.length, 'members on the map');
            
            // Clear existing markers
            MarkerManager.clearMarkers();
            
            // Debug: Check if we have valid location data
            var validLocations = 0;
            members.forEach(function(member) {
                if (member.latitude && member.longitude) {
                    var lat = parseFloat(member.latitude);
                    var lng = parseFloat(member.longitude);
                    if (!isNaN(lat) && !isNaN(lng)) {
                        validLocations++;
                    }
                }
            });
            console.log('MembersMap: Found', validLocations, 'members with valid location data');
            
            // Group members by location
            var locationGroups = DataHandler.groupMembersByLocation(members);
            console.log('MembersMap: Created', Object.keys(locationGroups).length, 'location groups');
            
            // Create markers for each location group
            Object.keys(locationGroups).forEach(function(locationKey) {
                var group = locationGroups[locationKey];
                var lat = group.lat;
                var lng = group.lng;
                
                // Verify coordinates are valid
                if (isNaN(lat) || isNaN(lng)) {
                    console.error('MembersMap: Invalid coordinates for location', locationKey);
                    return; // Skip this location
                }
                
                console.log('MembersMap: Processing location', locationKey, 'with', group.members.length, 'members');
                
                // Add to bounds
                bounds.push([lat, lng]);
                
                // Create marker based on number of members at this location
                var marker;
                if (group.members.length === 1) {
                    // Single member - create normal marker
                    marker = MarkerManager.createMemberMarker(group.members[0], lat, lng);
                } else {
                    // Multiple members - create grouped marker
                    marker = MarkerManager.createGroupedMemberMarker(group.members, lat, lng);
                }
                
                // Add marker to the map if it exists
                if (marker) {
                    MarkerManager.addMarker(marker);
                } else {
                    console.warn('MembersMap: No valid marker created for location', locationKey);
                }
                
                console.log('MembersMap: Marker added for location', locationKey);
            });
            
            console.log('MembersMap: Total markers added:', MarkerManager.getMarkers().length);
            
            // Fit map to show all markers
            if (bounds.length > 0) {
                MapUtils.fitMapBounds(this.map, bounds);
            } else {
                console.warn('MembersMap: No valid bounds to fit map to');
            }
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        // Initialize the map when the document is ready
        MembersMap.init();
    });
    
})(jQuery);
