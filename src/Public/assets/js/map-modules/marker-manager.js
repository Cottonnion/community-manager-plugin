/**
 * Marker Manager Module
 * 
 * Handles marker creation, clustering, and management
 */
var MarkerManager = (function($) {
    'use strict';

    // Private variables
    var markers = [];
    var markerClusterGroup = null;
    
    // Public API
    return {
        /**
         * Initialize the marker manager
         * 
         * @param {object} map The Leaflet map object
         * @param {object} options Cluster options
         * @return {object} The marker cluster group
         */
        init: function(map, options) {
            console.log('MarkerManager: Initializing');
            
            // Clear existing markers
            this.clearMarkers();
            
            // Set default options
            var defaultOptions = {
                maxClusterRadius: 40,
                spiderfyOnMaxZoom: true,
                showCoverageOnHover: false,
                zoomToBoundsOnClick: true
            };
            
            // Merge options
            options = $.extend({}, defaultOptions, options);
            
            // Initialize marker cluster group with custom icon function
            markerClusterGroup = L.markerClusterGroup({
                maxClusterRadius: options.maxClusterRadius,
                spiderfyOnMaxZoom: options.spiderfyOnMaxZoom,
                showCoverageOnHover: options.showCoverageOnHover,
                zoomToBoundsOnClick: options.zoomToBoundsOnClick,
                iconCreateFunction: function(cluster) {
                    var childCount = cluster.getChildCount();
                    var clusterClass = 'marker-cluster-small';
                    
                    if (childCount < 10) {
                        clusterClass = 'marker-cluster-small';
                    } else if (childCount < 100) {
                        clusterClass = 'marker-cluster-medium';
                    } else {
                        clusterClass = 'marker-cluster-large';
                    }

                    return new L.DivIcon({
                        html: '<div><span>' + childCount + '</span></div>',
                        className: 'marker-cluster ' + clusterClass,
                        iconSize: new L.Point(40, 40)
                    });
                }
            });
            
            // Add to map
            map.addLayer(markerClusterGroup);
            
            return markerClusterGroup;
        },
        
        /**
         * Clear all markers from the map
         */
        clearMarkers: function() {
            if (markerClusterGroup) {
                markerClusterGroup.clearLayers();
            }
            markers = [];
        },
        
        /**
         * Create a marker for a single member using their avatar
         * 
         * @param {object} member The member data
         * @param {number} lat Latitude
         * @param {number} lng Longitude
         * @return {object} The marker object
         */
        createMemberMarker: function(member, lat, lng) {
            // Extract the avatar URL from the avatar HTML
            var avatarUrl = '';
            
            if (member.avatar) {
                // Extract src from the avatar HTML
                var srcMatch = member.avatar.match(/src="([^"]+)"/);
                if (srcMatch && srcMatch[1]) {
                    avatarUrl = srcMatch[1];
                }
            } else {
                // Default placeholder
                avatarUrl = 'https://www.gravatar.com/avatar/?d=mp&f=y';
            }
            
            // Determine role class based on member role
            var roleClass = '';
            if (member.role) {
                var role = member.role.toLowerCase();
                if (role.includes('admin')) {
                    roleClass = 'role-admin';
                } else if (role.includes('moderator')) {
                    roleClass = 'role-moderator';
                } else {
                    roleClass = 'role-member';
                }
            } else {
                roleClass = 'role-member'; // Default role
            }
            
            var avatarIcon = L.divIcon({
                html: '<div class="avatar-marker ' + roleClass + '"><img src="' + avatarUrl + '" alt="' + member.name + '"></div>',
                className: 'member-avatar-icon',
                iconSize: [40, 40],
                iconAnchor: [20, 20],
                popupAnchor: [0, -20]
            });
            
            // Store original coordinates
            var originalLat = parseFloat(lat);
            var originalLng = parseFloat(lng);
            
            // Define privacy thresholds
            var privacyMaxZoom = 11; // Maximum zoom level before applying offset
            
            // Check if MapUtils and the applyPrivacyOffset function exist
            var adjustedCoords;
            if (typeof MapUtils !== 'undefined' && typeof MapUtils.applyPrivacyOffset === 'function') {
                console.log('MarkerManager: Applying privacy offset to coordinates', originalLat, originalLng);
                adjustedCoords = MapUtils.applyPrivacyOffset(
                    originalLat,
                    originalLng,
                    privacyMaxZoom, // Use privacy threshold as current zoom to start
                    privacyMaxZoom
                );
            } else {
                console.error('MarkerManager: MapUtils or applyPrivacyOffset function not available');
                adjustedCoords = { lat: originalLat, lng: originalLng };
            }
            
            // Create marker with adjusted coordinates - ensure coordinates are valid
            console.log('MarkerManager: Creating marker with coordinates', adjustedCoords);
            
            // Validate coordinates before creating marker
            // Handle both array format [lat, lng] and object format {lat: lat, lng: lng}
            var validLat, validLng;
            
            if (Array.isArray(adjustedCoords)) {
                // If it's an array [lat, lng]
                validLat = adjustedCoords[0];
                validLng = adjustedCoords[1];
                console.log('MarkerManager: Coordinates are in array format:', validLat, validLng);
            } else if (adjustedCoords && typeof adjustedCoords === 'object') {
                // If it's an object {lat: lat, lng: lng}
                validLat = adjustedCoords.lat;
                validLng = adjustedCoords.lng;
                console.log('MarkerManager: Coordinates are in object format:', validLat, validLng);
            } else {
                // Fallback to original coordinates
                validLat = originalLat;
                validLng = originalLng;
                console.log('MarkerManager: Using original coordinates as fallback:', validLat, validLng);
            }
            
            // Final validation
            if (typeof validLat !== 'number' || typeof validLng !== 'number' || isNaN(validLat) || isNaN(validLng)) {
                console.error('MarkerManager: Invalid coordinates after processing', validLat, validLng);
                return null; // Return null if coordinates are invalid
            }
            
            var marker = L.marker([validLat, validLng], {
                icon: avatarIcon,
                title: member.name,
                alt: member.name
            });
            
            // Store original coordinates in marker properties for reference
            marker.originalCoordinates = {
                lat: originalLat,
                lng: originalLng
            };
            
            // Store privacy settings
            marker.privacySettings = {
                maxZoom: privacyMaxZoom
            };
            
            // Create popup content
            var popupContent = this.createMemberPopupContent(member);
            marker.bindPopup(popupContent);
            
            // Add a namespace to our event handler to avoid duplicates
            marker._privacyHandlerAdded = false;
            
            // We'll add the privacy offset handler when the marker is added to the map
            marker.on('add', function(e) {
                var map = e.target._map;
                if (!map || e.target._privacyHandlerAdded) {
                    return; // Don't add handler twice
                }
                
                // Flag that we've added the handler
                e.target._privacyHandlerAdded = true;
                
                // Get privacy settings
                var privacyMaxZoom = e.target.privacySettings.maxZoom;
                
                // Initial adjustment based on current zoom
                var currentZoom = map.getZoom();
                
                // Apply privacy offset based on current zoom
                var adjustedCoords = MapUtils.applyPrivacyOffset(
                    originalLat, 
                    originalLng,
                    currentZoom,
                    privacyMaxZoom
                );
                
                // Handle both array and object formats for coordinates
                if (Array.isArray(adjustedCoords)) {
                    console.log('MarkerManager: Setting LatLng from array:', adjustedCoords);
                    e.target.setLatLng(adjustedCoords);
                } else if (adjustedCoords && typeof adjustedCoords === 'object' && 
                           'lat' in adjustedCoords && 'lng' in adjustedCoords) {
                    console.log('MarkerManager: Setting LatLng from object:', adjustedCoords);
                    e.target.setLatLng([adjustedCoords.lat, adjustedCoords.lng]);
                } else {
                    console.warn('MarkerManager: Invalid adjustedCoords format:', adjustedCoords);
                }
                
                // Create a unique ID for this marker's zoom event handler
                var handlerId = 'privacy_' + originalLat.toFixed(6) + '_' + originalLng.toFixed(6);
                
                // Remove any existing handler with this ID
                map.off('zoomend.' + handlerId);
                
                // Add zoom handler with namespace
                map.on('zoomend.' + handlerId, function() {
                    var newZoom = map.getZoom();
                    
                    // Apply increasing offset as user zooms in beyond privacy threshold
                    var newCoords = MapUtils.applyPrivacyOffset(
                        originalLat,
                        originalLng,
                        newZoom,
                        privacyMaxZoom
                    );
                    
                    // Apply the new coordinates - handle both array and object formats
                    if (Array.isArray(newCoords)) {
                        e.target.setLatLng(newCoords);
                    } else if (newCoords && typeof newCoords === 'object' && 
                              'lat' in newCoords && 'lng' in newCoords) {
                        e.target.setLatLng([newCoords.lat, newCoords.lng]);
                    }
                    
                    // Update privacy notice visibility based on zoom level
                    if (newZoom > privacyMaxZoom) {
                        $('.map-privacy-notice').fadeIn(300);
                    } else {
                        $('.map-privacy-notice').fadeOut(300);
                    }
                });
                
                // Replace the click handler to use offset coordinates
                e.target.off('click'); // Remove any existing click handler
                e.target.on('click', function(clickEvent) {
                    var currentMap = clickEvent.target._map;
                    // Only zoom to the privacy threshold to maintain privacy
                    var targetZoom = privacyMaxZoom; // Use privacy threshold
                    
                    // Calculate offset coordinates for the target zoom level
                    var targetCoords = MapUtils.applyPrivacyOffset(
                        originalLat,
                        originalLng,
                        targetZoom,
                        privacyMaxZoom
                    );
                    
                    // Use the offset coordinates instead of the clicked position
                    // Handle both array and object formats
                    if (Array.isArray(targetCoords)) {
                        currentMap.setView(targetCoords, targetZoom);
                    } else if (targetCoords && typeof targetCoords === 'object' && 
                              'lat' in targetCoords && 'lng' in targetCoords) {
                        currentMap.setView([targetCoords.lat, targetCoords.lng], targetZoom);
                    } else {
                        console.warn('MarkerManager: Invalid target coordinates for setView:', targetCoords);
                        // Fallback to original coordinates
                        currentMap.setView([originalLat, originalLng], targetZoom);
                    }
                });
            });
            
            
            return marker;
        },
        
        /**
         * Create a marker for multiple members at the same location
         * 
         * @param {array} members Array of member data
         * @param {number} lat Latitude
         * @param {number} lng Longitude
         * @return {object} The marker object
         */
        createGroupedMemberMarker: function(members, lat, lng) {
            // Create a visual representation with avatars for the first 3 members
            var avatarsHtml = '';
            var maxAvatars = Math.min(3, members.length);
            
            for (var i = 0; i < maxAvatars; i++) {
                var member = members[i];
                var avatarUrl = '';
                
                // Extract src from the avatar HTML
                if (member.avatar) {
                    var srcMatch = member.avatar.match(/src="([^"]+)"/);
                    if (srcMatch && srcMatch[1]) {
                        avatarUrl = srcMatch[1];
                    }
                } else {
                    // Default placeholder
                    avatarUrl = 'https://www.gravatar.com/avatar/?d=mp&f=y';
                }
                
                // Determine role class based on member role
                var roleClass = '';
                if (member.role) {
                    var role = member.role.toLowerCase();
                    if (role.includes('admin')) {
                        roleClass = 'role-admin';
                    } else if (role.includes('moderator')) {
                        roleClass = 'role-moderator';
                    } else {
                        roleClass = 'role-member';
                    }
                } else {
                    roleClass = 'role-member'; // Default role
                }
                
                avatarsHtml += '<img src="' + avatarUrl + '" alt="' + member.name + '" class="group-avatar-img ' + roleClass + '">';
            }
            
            // Add count if there are more members than shown avatars
            if (members.length > maxAvatars) {
                avatarsHtml += '<span class="avatar-more">+' + (members.length - maxAvatars) + '</span>';
            }
            
            var icon = L.divIcon({
                html: '<div class="multi-member-marker">' + avatarsHtml + '</div>',
                className: 'multi-member-avatar-icon',
                iconSize: [60, 60],
                iconAnchor: [30, 30],
                popupAnchor: [0, -30]
            });
            
            // Store original coordinates
            var originalLat = parseFloat(lat);
            var originalLng = parseFloat(lng);
            
            var marker = L.marker([lat, lng], {
                icon: icon,
                title: members.length + ' members at this location',
                alt: 'Multiple members'
            });
            
            // Store original coordinates in marker properties for reference
            marker.originalCoordinates = {
                lat: originalLat,
                lng: originalLng
            };
            
            // Create popup content for multiple members
            var popupContent = this.createGroupedMemberPopupContent(members);
            marker.bindPopup(popupContent, { maxWidth: 300 });
            
            // Add click handler
            marker.on('click', function(e) {
                // Get the map from the event
                var map = e.target._map;
                
                // Get original coordinates
                var originalLat = e.target.originalCoordinates.lat;
                var originalLng = e.target.originalCoordinates.lng;
                var targetZoom = 15; // Higher zoom level for better detail
                var privacyMaxZoom = 11;
                
                // Calculate privacy-protected coordinates for the target zoom
                var protectedCoords = MapUtils.applyPrivacyOffset(
                    originalLat,
                    originalLng,
                    targetZoom,
                    privacyMaxZoom
                );
                
                // Use the protected coordinates when zooming in
                map.setView([protectedCoords[0], protectedCoords[1]], targetZoom);
            });
            
            // Add a namespace to our event handler to avoid duplicates
            marker._privacyHandlerAdded = false;
            
            // We'll add the privacy offset handler when the marker is added to the map
            marker.on('add', function(e) {
                var map = e.target._map;
                if (!map || e.target._privacyHandlerAdded) {
                    return; // Don't add handler twice
                }
                
                // Flag that we've added the handler
                e.target._privacyHandlerAdded = true;
                
                // Initial adjustment based on current zoom
                var currentZoom = map.getZoom();
                var privacyMaxZoom = 14; // Maximum zoom level before applying offset
                
                // Apply privacy offset based on current zoom
                var adjustedCoords = MapUtils.applyPrivacyOffset(
                    originalLat, 
                    originalLng,
                    currentZoom,
                    privacyMaxZoom
                );
                
                e.target.setLatLng(adjustedCoords);
                
                // Create a unique ID for this marker's zoom event handler
                var handlerId = 'privacy_group_' + originalLat.toFixed(6) + '_' + originalLng.toFixed(6);
                
                // Remove any existing handler with this ID
                map.off('zoomend.' + handlerId);
                
                // Add zoom handler with namespace
                map.on('zoomend.' + handlerId, function() {
                    var newZoom = map.getZoom();
                    var newCoords = MapUtils.applyPrivacyOffset(
                        originalLat,
                        originalLng,
                        newZoom,
                        privacyMaxZoom
                    );
                    
                    e.target.setLatLng(newCoords);
                });
            });
            
            return marker;
        },
        
        /**
         * Create popup content for a single member
         * 
         * @param {object} member The member data
         * @return {string} HTML content for the popup
         */
        createMemberPopupContent: function(member) {
            var content = '<div class="member-popup">';
            
            // Main content section
            content += '<div class="member-popup-content">';
            
            // Name and role only
            content += '<h3>' + member.name + '</h3>';
            if (member.role) {
                // Add role with appropriate color
                var roleColorClass = '';
                var role = member.role.toLowerCase();
                
                if (role.includes('admin')) {
                    roleColorClass = 'color: #ff0000; font-weight: bold;'; // Red for admins
                } else if (role.includes('moderator')) {
                    roleColorClass = 'color: #0000ff; font-weight: bold;'; // Blue for moderators
                } else {
                    roleColorClass = 'color: #006600; font-weight: bold;'; // Dark green for members
                }
                
                content += '<p class="member-role" style="' + roleColorClass + '">' + member.role + '</p>';
            }
            
            // Action buttons
            content += '<div class="member-buttons">';
            
            // Profile link
            if (member.profile_url) {
                content += '<a href="' + member.profile_url + '" class="member-profile-link">Profile</a>';
            }
            
            // Connect button - only show for other members (not the current user)
            if (member.id && MembersMapData.current_user_id && member.id != MembersMapData.current_user_id) {
                // This uses the standard BuddyPress friend request structure
                var connectUrl = member.profile_url; // Friend request parameter
                content += '<a href="' + connectUrl + '" class="member-connect-link">Connect</a>';
            }
            
            content += '</div>'; // Close buttons div
            content += '</div>'; // Close content div
            content += '</div>'; // Close popup div
            
            return content;
        },
        
        /**
         * Create popup content for multiple members at the same location
         * 
         * @param {array} members Array of member data
         * @return {string} HTML content for the popup
         */
        createGroupedMemberPopupContent: function(members) {
            var content = '<div class="grouped-members-popup">';
            content += '<div class="grouped-popup-header">';
            content += '<h3>' + members.length + ' Members at this Location</h3>';
            content += '</div>';
            content += '<div class="members-list">';
            
            members.forEach(function(member) {
                content += '<div class="member-item">';
                
                // Extract avatar src from HTML
                var avatarSrc = '';
                if (member.avatar) {
                    var srcMatch = member.avatar.match(/src="([^"]+)"/);
                    if (srcMatch && srcMatch[1]) {
                        avatarSrc = srcMatch[1];
                    }
                }
                
                if (avatarSrc) {
                    content += '<img src="' + avatarSrc + '" alt="' + member.name + '" class="member-mini-avatar">';
                }
                
                content += '<div class="member-details">';
                content += '<p class="member-name">' + member.name + '</p>';
                
                // Add role with appropriate color if available
                if (member.role) {
                    var roleColorClass = '';
                    var role = member.role.toLowerCase();
                    
                    if (role.includes('admin')) {
                        roleColorClass = 'color: #ff0000; font-size: 10px;'; // Red for admins
                    } else if (role.includes('moderator')) {
                        roleColorClass = 'color: #0000ff; font-size: 10px;'; // Blue for moderators
                    } else {
                        roleColorClass = 'color: #006600; font-size: 10px;'; // Dark green for members
                    }
                    
                    content += '<p class="member-item-role" style="' + roleColorClass + '">' + member.role + '</p>';
                }
                
                // Action buttons for each member - profile and connect only
                content += '<div class="member-item-buttons">';
                
                if (member.profile_url) {
                    content += '<a href="' + member.profile_url + '" class="member-item-profile">Profile</a>';
                }
                
                // Only show connect button for other members (not the current user)
                if (member.id && MembersMapData.current_user_id && member.id != MembersMapData.current_user_id) {
                    var connectUrl = member.profile_url; // Friend request parameter
                    content += '<a href="' + connectUrl + '" class="member-item-connect">Connect</a>';
                }
                
                content += '</div>'; // Close buttons
                content += '</div>'; // Close details
                content += '</div>'; // Close member item
            });
            
            content += '</div></div>'; // Close members list and popup
            
            return content;
        },
        
        /**
         * Add a marker to the map
         * 
         * @param {object} marker The marker to add
         */
        addMarker: function(marker) {
            if (!marker) {
                console.warn('MarkerManager: Attempted to add null/undefined marker');
                return;
            }
            markers.push(marker);
            markerClusterGroup.addLayer(marker);
        },
        
        /**
         * Get the marker cluster group
         * 
         * @return {object} The marker cluster group
         */
        getClusterGroup: function() {
            return markerClusterGroup;
        },
        
        /**
         * Get all markers
         * 
         * @return {array} Array of markers
         */
        getMarkers: function() {
            return markers;
        }
    };
})(jQuery);
