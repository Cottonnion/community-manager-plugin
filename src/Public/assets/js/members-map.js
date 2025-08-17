/**
 * Members Map JavaScript
 *
 * Handles the initialization and display of the members map using Leaflet.js
 * 
 * @deprecated This file is deprecated and will be removed in future versions.
 * It is replaced by the new modular structure in `members-map-new.js`
 * and it's modules.
 */
(function ($) {
	'use strict';

	// Map initialization
	var membersMap = {
		map: null,
		markers: [],
		markerClusterGroup: null,

		/**
		 * Initialize the map
		 */
		init: function () {
			console.log( 'Map init called' );

			// Check if the map container exists
			if ($( '#members-map-container' ).length === 0) {
				console.log( 'Map container not found' );
				return;
			}

			// Check if Leaflet is available
			if (typeof L === 'undefined') {
				console.error( 'Leaflet library not loaded' );
				$( '#members-map-container' ).html( '<div class="map-error">Map library not loaded. Please refresh the page.</div>' );
				return;
			}

			// Check if MembersMapData is available
			if (typeof MembersMapData === 'undefined') {
				console.error( 'MembersMapData not found' );
				$( '#members-map-container' ).html( '<div class="map-error">Map configuration not found.</div>' );
				return;
			}

			console.log( 'Initializing map with data:', MembersMapData );

			// Ensure the container has proper styling
			$( '#members-map-container' ).css(
				{
					'width': '100%',
					'height': '500px',
					'background-color': '#f0f0f0',
					'border': '1px solid #ccc',
					'position': 'relative',
					'display': 'block !important',
					'visibility': 'visible !important',
					'z-index': '1'
				}
			);

			console.log( 'Map container styling applied' );
			console.log( 'Container dimensions:', $( '#members-map-container' ).width(), 'x', $( '#members-map-container' ).height() );
			console.log( 'Container visible:', $( '#members-map-container' ).is( ':visible' ) );

			// Check if Leaflet CSS is loaded
			var leafletCssLoaded = false;
			$( 'link[href*="leaflet"]' ).each(
				function () {
					console.log( 'Found Leaflet CSS:', $( this ).attr( 'href' ) );
					leafletCssLoaded = true;
				}
			);

			if ( ! leafletCssLoaded) {
				console.warn( 'Leaflet CSS may not be loaded' );
			}

			// Add custom CSS for tooltips and markers
			// this.addCustomStyles();

			// Initialize the map
			this.map = L.map( 'members-map-container' ).setView( [33.5945, -7.6200], 10 ); // Default to Casablanca

			// Add the tile layer (OpenStreetMap)
			L.tileLayer(
				'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
				{
					attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
				}
			).addTo( this.map );

			// Initialize marker cluster group
			this.markerClusterGroup = L.markerClusterGroup(
				{
					maxClusterRadius: 40,
					spiderfyOnMaxZoom: true,
					showCoverageOnHover: false,
					zoomToBoundsOnClick: true,
					iconCreateFunction: function (cluster) {
						var childCount   = cluster.getChildCount();
						var clusterClass = 'marker-cluster-small';
						if (childCount < 10) {
							clusterClass = 'marker-cluster-small';
						} else if (childCount < 100) {
							clusterClass = 'marker-cluster-medium';
						} else {
							clusterClass = 'marker-cluster-large';
						}

						return new L.DivIcon(
							{
								html: '<div><span>' + childCount + '</span></div>',
								className: 'marker-cluster ' + clusterClass,
								iconSize: new L.Point( 40, 40 )
							}
						);
					}
				}
			);

			this.map.addLayer( this.markerClusterGroup );

			// Force map to resize after multiple delays to ensure it's properly rendered
			setTimeout(
				function () {
					console.log( 'Forcing map resize (100ms)...' );
					if (this.map) {
						this.map.invalidateSize();
					}
				}.bind( this ),
				100
			);

			setTimeout(
				function () {
					console.log( 'Forcing map resize (500ms)...' );
					if (this.map) {
						this.map.invalidateSize();
					}
				}.bind( this ),
				500
			);

			setTimeout(
				function () {
					console.log( 'Forcing map resize (1000ms)...' );
					if (this.map) {
						this.map.invalidateSize();
					}
				}.bind( this ),
				1000
			);

			console.log( 'Map initialized, loading members data...' );

			// Remove the initialization indicator
			$( '#map-init-indicator' ).remove();

			// Load members data
			this.loadMembersData();
		},

		/**
		 * Load members data via AJAX
		 */
		loadMembersData: function () {
			var self = this;

			// console.log( 'loadMembersData called' );

			// Show loading indicator
			$( '#members-map-container' ).append( '<div class="map-loading">Loading members data...</div>' );

			console.log(
				'Making AJAX request with data:',
				{
					action: 'get_group_members_location',
					group_id: MembersMapData.group_id,
					nonce: MembersMapData.nonce
				}
			);

			// Make AJAX request to get members data
			$.ajax(
				{
					url: MembersMapData.ajaxurl,
					type: 'POST',
					data: {
						action: 'get_group_members_location',
						group_id: MembersMapData.group_id,
						nonce: MembersMapData.nonce
					},
					success: function (response) {
						console.log( 'AJAX Response:', response );

						// Remove loading indicator
						$( '.map-loading' ).remove();

						if (response.success && response.data.members) {
							console.log( 'Members data received:', response.data.members );
							if (response.data.members.length > 0) {
								console.log( 'Calling displayMembers with', response.data.members.length, 'members' );
								self.displayMembers( response.data.members );
							} else {
								console.log( 'No members found' );
								$( '#members-map-container' ).append( '<div class="map-no-data">No members with location data found.</div>' );
							}
						} else {
							console.error( 'Error in response:', response );
							$( '#members-map-container' ).append( '<div class="map-error">Error loading members data.</div>' );
						}
					},
					error: function (xhr, status, error) {
						console.error( 'AJAX Error:', error );
						console.error( 'XHR:', xhr );
						console.error( 'Status:', status );
						$( '.map-loading' ).remove();
						$( '#members-map-container' ).append( '<div class="map-error">Error loading members data: ' + error + '</div>' );
					}
				}
			);
		},

		/**
		 * Display members on the map
		 */
		displayMembers: function (members) {
			var self   = this;
			var bounds = [];

			console.log( 'displayMembers called with:', members );
			console.log( 'Map object:', this.map );

			// Clear existing markers
			this.clearMarkers();

			// Group members by exact coordinates to handle same-location cases
			var locationGroups = {};
			members.forEach(
				function (member) {
					if (member.latitude && member.longitude) {
						var lat         = parseFloat( member.latitude );
						var lng         = parseFloat( member.longitude );
						var locationKey = lat + ',' + lng;

						if ( ! locationGroups[locationKey]) {
							locationGroups[locationKey] = {
								lat: lat,
								lng: lng,
								members: []
							};
						}
						locationGroups[locationKey].members.push( member );
					}
				}
			);

			console.log( 'Location groups:', locationGroups );

			// Create markers for each location group
			Object.keys( locationGroups ).forEach(
				function (locationKey) {
					var group = locationGroups[locationKey];
					var lat   = group.lat;
					var lng   = group.lng;

					console.log( 'Processing location group at:', lat, lng, 'with', group.members.length, 'members' );

					// Add to bounds
					bounds.push( [lat, lng] );

					// Create marker based on number of members at this location
					var marker;
					if (group.members.length === 1) {
						// Single member - create normal marker
						marker = self.createMemberMarker( group.members[0], lat, lng );
					} else {
						// Multiple members - create grouped marker
						marker = self.createGroupedMemberMarker( group.members, lat, lng );
					}

					self.markers.push( marker );
					self.markerClusterGroup.addLayer( marker );

					console.log( 'Marker added successfully for location group' );
				}
			);

			console.log( 'Total bounds:', bounds );

			// Fit map to show all markers
			if (bounds.length > 0) {
				if (bounds.length === 1) {
					// Single marker - center on it
					console.log( 'Centering map on single marker' );
					self.map.setView( bounds[0], 12 );
				} else {
					// Multiple markers - fit bounds
					console.log( 'Fitting map to multiple markers' );
					self.map.fitBounds( bounds, {padding: [20, 20]} );
				}
			} else {
				console.log( 'No valid bounds, keeping default view' );
			}

			// Remove loading/error messages
			$( '.map-loading, .map-error, .map-no-data' ).remove();

			console.log( 'displayMembers completed' );
		},

		/**
		 * Create marker for a member
		 */
		createMemberMarker: function (member, lat, lng) {
			console.log( 'createMemberMarker called for:', member.name, 'at', lat, lng );

			// Choose border color based on role
			var borderColor = '#2ecc71'; // Default green for members
			if (member.role === 'admin') {
				borderColor = '#e74c3c'; // Red for admins
			} else if (member.role === 'mod') {
				borderColor = '#3498db'; // Blue for moderators
			}

			console.log( 'Border color:', borderColor, 'for role:', member.role );

			// Extract avatar URL from avatar HTML
			var avatarUrl = '';
			if (member.avatar) {
				var avatarMatch = member.avatar.match( /src="([^"]+)"/ );
				if (avatarMatch) {
					avatarUrl = avatarMatch[1];
				}
			}

			// Fallback to default avatar if no avatar found
			if ( ! avatarUrl) {
				avatarUrl = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1zbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMjAiIGN5PSIyMCIgcj0iMjAiIGZpbGw9IiNmMGYwZjAiLz4KPGNpcmNsZSBjeD0iMjAiIGN5PSIxNiIgcj0iNiIgZmlsbD0iIzk5OTk5OSIvPgo8cGF0aCBkPSJNMTAgMzBjMC01LjUgNC41LTEwIDEwLTEwczEwIDQuNSAxMCAxMHYySDEwdi0yeiIgZmlsbD0iIzk5OTk5OSIvPgo8L3N2Zz4K';
			}

			console.log( 'Avatar URL:', avatarUrl );

			// Create custom marker icon with avatar
			var markerIcon = L.divIcon(
				{
					className: 'avatar-marker',
					html: '<div class="avatar-marker-container" style="' +
					'width: 40px; height: 40px; border-radius: 50%; border: 3px solid ' + borderColor + '; ' +
					'background: white; box-shadow: 0 2px 8px rgba(0,0,0,0.3); overflow: hidden; position: relative; ' +
					'cursor: pointer; transition: all 0.2s ease;' +
					'" data-member-id="' + member.id + '">' +
					'<img src="' + avatarUrl + '" alt="' + member.name + '" style="' +
					'width: 100%; height: 100%; object-fit: cover; border-radius: 50%;' +
					'">' +
					'<div class="role-indicator" style="' +
					'position: absolute; bottom: -2px; right: -2px; width: 12px; height: 12px; ' +
					'background: ' + borderColor + '; border: 2px solid white; border-radius: 50%; ' +
					'font-size: 8px; color: white; text-align: center; line-height: 8px;' +
					'">' + this.getRoleIcon( member.role ) + '</div>' +
					'</div>',
					iconSize: [40, 40],
					iconAnchor: [20, 20]
				}
			);

			console.log( 'Avatar marker icon created' );

			// Create marker
			var marker = L.marker( [lat, lng], {icon: markerIcon} );

			console.log( 'Marker created' );

			// Create popup content and bind it
			var popupContent = this.createPopupContent( member );
			var popup        = marker.bindPopup(
				popupContent,
				{
					closeButton: false,
					autoClose: false,
					closeOnClick: false,
					className: 'member-popup-hover'
				}
			);

			// Add hover behavior to the popup as well
			var popupTimeout;
			var isHoveringPopup          = false;
			var isHoveringMarkerForPopup = false;

			// Marker hover events for popup
			marker.on(
				'mouseover',
				function (e) {
					// Don't show popup for current user's own avatar
					if (MembersMapData.current_user_id && member.id &&
					parseInt( member.id ) === parseInt( MembersMapData.current_user_id )) {
						console.log( 'Skipping popup for current user:', member.name );
						return;
					}

					isHoveringMarkerForPopup = true;
					clearTimeout( popupTimeout );
					popup.openPopup();
					console.log( 'Marker hover: showing popup for', member.name );
				}
			);

			marker.on(
				'mouseout',
				function (e) {
					isHoveringMarkerForPopup = false;
					if ( ! isHoveringPopup) {
						popupTimeout = setTimeout(
							function () {
								if ( ! isHoveringPopup && ! isHoveringMarkerForPopup) {
									popup.closePopup();
									console.log( 'Marker hover out: popup closed after 1000ms delay' );
								}
							},
							1000
						);
						console.log( 'Marker hover out: starting 2000ms delay for popup' );
					}
				}
			);

			// Popup hover events
			marker.on(
				'popupopen',
				function (e) {
					var popupElement = e.popup.getElement();
					if (popupElement) {
						popupElement.addEventListener(
							'mouseenter',
							function () {
								isHoveringPopup = true;
								clearTimeout( popupTimeout );
								console.log( 'Popup hover: keeping popup open' );
							}
						);

						popupElement.addEventListener(
							'mouseleave',
							function () {
								isHoveringPopup = false;
								popupTimeout    = setTimeout(
									function () {
										if ( ! isHoveringPopup && ! isHoveringMarkerForPopup) {
											popup.closePopup();
											console.log( 'Popup closed after popup leave delay' );
										}
									},
									2000
								);
								console.log( 'Popup leave: starting 2000ms delay' );
							}
						);

						popupElement.addEventListener(
							'click',
							function (e) {
								e.stopPropagation();
								clearTimeout( popupTimeout );
								console.log( 'Popup clicked: preventing close' );
							}
						);
					}
				}
			);

			// Check if device is mobile
			var isMobile = window.innerWidth <= 768 || /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test( navigator.userAgent );

			if (isMobile) {
				// Mobile: Click handler to show popup
				marker.on(
					'click',
					function (e) {
						// Show the same hover content as popup
						var tooltipContent = self.createTooltipContent( member );
						marker.bindPopup(
							tooltipContent,
							{
								maxWidth: 250,
								className: 'member-mobile-popup'
							}
						).openPopup();

						console.log( 'Mobile click: showing popup for', member.name );
					}
				);
			} else {
				// Desktop: Custom fixed hover card system
				var tooltipContent = this.createTooltipContent( member );

				// Custom tooltip behavior with delay
				var tooltipTimeout;
				var isHoveringMarker  = false;
				var isHoveringTooltip = false;
				var hoverCard         = null;

				// Add click handler to show the same hover card
				marker.on(
					'click',
					function (e) {
						// Clear any existing timeout
						clearTimeout( tooltipTimeout );

						// Show fixed hover card
						self.showFixedHoverCard( e.target, member, tooltipContent );

						// Keep it open (don't auto-close on click)
						isHoveringTooltip = true;

						console.log( 'Desktop click: showing fixed hover card for', member.name );
					}
				);

				marker.on(
					'mouseover',
					function (e) {
						isHoveringMarker = true;
						clearTimeout( tooltipTimeout );

						// Show fixed hover card immediately
						self.showFixedHoverCard( e.target, member, tooltipContent );

						// Scale marker
						var markerElement = e.target.getElement();
						if (markerElement) {
							var container = markerElement.querySelector( '.avatar-marker-container' );
							if (container) {
								container.style.transform = 'scale(1.1)';
								container.style.boxShadow = '0 4px 12px rgba(0,0,0,0.4)';
							}
						}

						console.log( 'Desktop hover: showing fixed hover card for', member.name );
					}
				);

				marker.on(
					'mouseout',
					function (e) {
						isHoveringMarker = false;

						// Scale marker back
						var markerElement = e.target.getElement();
						if (markerElement) {
							var container = markerElement.querySelector( '.avatar-marker-container' );
							if (container) {
								container.style.transform = 'scale(1)';
								container.style.boxShadow = '0 2px 8px rgba(0,0,0,0.3)';
							}
						}

						// Only start delay if not hovering tooltip
						if ( ! isHoveringTooltip) {
							clearTimeout( tooltipTimeout );
							tooltipTimeout = setTimeout(
								function () {
									if ( ! isHoveringTooltip && ! isHoveringMarker) {
										self.hideFixedHoverCard();
										console.log( 'Desktop hover out: hover card hidden after 2000ms delay' );
									}
								},
								2000
							);
							console.log( 'Desktop hover out: starting 2000ms delay for', member.name );
						}
					}
				);
			}

			console.log( 'Hover events and tooltip bound to marker' );

			return marker;
		},

		/**
		 * Create marker for multiple members at the same location
		 */
		createGroupedMemberMarker: function (members, lat, lng) {
			var self = this;
			console.log( 'createGroupedMemberMarker called for', members.length, 'members at', lat, lng );

			// Determine the highest role in the group for border color
			var highestRole = 'member';
			members.forEach(
				function (member) {
					if (member.role === 'admin') {
						highestRole = 'admin';
					} else if (member.role === 'mod' && highestRole !== 'admin') {
						highestRole = 'mod';
					}
				}
			);

			var borderColor = '#2ecc71'; // Default green for members
			if (highestRole === 'admin') {
				borderColor = '#e74c3c'; // Red for admins
			} else if (highestRole === 'mod') {
				borderColor = '#3498db'; // Blue for moderators
			}

			console.log( 'Grouped marker border color:', borderColor, 'for highest role:', highestRole );

			// Get avatars for the first 3 members (for display in grouped marker)
			var displayMembers = members.slice( 0, 3 );
			var avatarUrls     = [];

			displayMembers.forEach(
				function (member) {
					var avatarUrl = '';
					if (member.avatar) {
						var avatarMatch = member.avatar.match( /src="([^"]+)"/ );
						if (avatarMatch) {
							avatarUrl = avatarMatch[1];
						}
					}
					if ( ! avatarUrl) {
						avatarUrl = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1zbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMjAiIGN5PSIyMCIgcj0iMjAiIGZpbGw9IiNmMGYwZjAiLz4KPGNpcmNsZSBjeD0iMjAiIGN5PSIxNiIgcj0iNiIgZmlsbD0iIzk5OTk5OSIvPgo8cGF0aCBkPSJNMTAgMzBjMC01LjUgNC41LTEwIDEwLTEwczEwIDQuNSAxMCAxMHYySDEwdi0yeiIgZmlsbD0iIzk5OTk5OSIvPgo8L3N2Zz4K';
					}
					avatarUrls.push( avatarUrl );
				}
			);

			// Create stacked avatar HTML
			var avatarHtml = '';
			if (members.length === 2) {
				// Two members - side by side
				avatarHtml = '<div class="grouped-avatar-container" style="' +
					'width: 50px; height: 40px; position: relative;' +
					'">' +
					'<img src="' + avatarUrls[0] + '" alt="' + members[0].name + '" style="' +
					'width: 30px; height: 30px; border-radius: 50%; border: 2px solid white; ' +
					'position: absolute; left: 0; top: 5px; object-fit: cover; z-index: 2;' +
					'">' +
					'<img src="' + avatarUrls[1] + '" alt="' + members[1].name + '" style="' +
					'width: 30px; height: 30px; border-radius: 50%; border: 2px solid white; ' +
					'position: absolute; right: 0; top: 5px; object-fit: cover; z-index: 1;' +
					'">' +
					'</div>';
			} else {
				// Three or more members - stacked
				avatarHtml = '<div class="grouped-avatar-container" style="' +
					'width: 50px; height: 45px; position: relative;' +
					'">';

				avatarUrls.forEach(
					function (avatarUrl, index) {
						var leftOffset = index * 8;
						var topOffset  = index * 3;
						var zIndex     = avatarUrls.length - index;

						avatarHtml += '<img src="' + avatarUrl + '" alt="' + members[index].name + '" style="' +
						'width: 28px; height: 28px; border-radius: 50%; border: 2px solid white; ' +
						'position: absolute; left: ' + leftOffset + 'px; top: ' + topOffset + 'px; ' +
						'object-fit: cover; z-index: ' + zIndex + '; box-shadow: 0 2px 4px rgba(0,0,0,0.2);' +
						'">';
					}
				);

				// Add count indicator if more than 3 members
				if (members.length > 3) {
					avatarHtml += '<div class="member-count-indicator" style="' +
						'position: absolute; bottom: -2px; right: -2px; width: 20px; height: 20px; ' +
						'background: ' + borderColor + '; border: 2px solid white; border-radius: 50%; ' +
						'font-size: 10px; font-weight: bold; color: white; ' +
						'display: flex; align-items: center; justify-content: center; ' +
						'z-index: 10; box-shadow: 0 2px 4px rgba(0,0,0,0.3);' +
						'">+' + (members.length - 3) + '</div>';
				}

				avatarHtml += '</div>';
			}

			// Create custom grouped marker icon
			var markerIcon = L.divIcon(
				{
					className: 'grouped-avatar-marker',
					html: '<div class="grouped-avatar-marker-container" style="' +
					'min-width: 50px; height: 45px; border-radius: 25px; border: 3px solid ' + borderColor + '; ' +
					'background: white; box-shadow: 0 2px 8px rgba(0,0,0,0.3); overflow: visible; ' +
					'position: relative; cursor: pointer; transition: all 0.2s ease; ' +
					'display: flex; align-items: center; justify-content: center; padding: 5px;' +
					'" data-member-count="' + members.length + '">' +
					avatarHtml +
					'</div>',
					iconSize: [60, 55],
					iconAnchor: [30, 27]
				}
			);

			console.log( 'Grouped avatar marker icon created' );

			// Create marker
			var marker = L.marker( [lat, lng], {icon: markerIcon} );

			console.log( 'Grouped marker created' );

			// Create popup content for grouped members
			var popupContent = this.createGroupedPopupContent( members );
			var popup        = marker.bindPopup(
				popupContent,
				{
					closeButton: false,
					autoClose: false,
					closeOnClick: false,
					className: 'grouped-member-popup-hover',
					maxWidth: 350
				}
			);

			// Add hover behavior similar to single member markers
			var popupTimeout;
			var isHoveringPopup          = false;
			var isHoveringMarkerForPopup = false;

			// Marker hover events for popup
			marker.on(
				'mouseover',
				function (e) {
					isHoveringMarkerForPopup = true;
					clearTimeout( popupTimeout );
					popup.openPopup();
					console.log( 'Grouped marker hover: showing popup for', members.length, 'members' );

					// Scale grouped marker
					var markerElement = e.target.getElement();
					if (markerElement) {
						var container = markerElement.querySelector( '.grouped-avatar-marker-container' );
						if (container) {
							container.style.transform = 'scale(1.1)';
							container.style.boxShadow = '0 4px 12px rgba(0,0,0,0.4)';
						}
					}
				}
			);

			marker.on(
				'mouseout',
				function (e) {
					isHoveringMarkerForPopup = false;

					// Scale grouped marker back
					var markerElement = e.target.getElement();
					if (markerElement) {
						var container = markerElement.querySelector( '.grouped-avatar-marker-container' );
						if (container) {
							container.style.transform = 'scale(1)';
							container.style.boxShadow = '0 2px 8px rgba(0,0,0,0.3)';
						}
					}

					if ( ! isHoveringPopup) {
						popupTimeout = setTimeout(
							function () {
								if ( ! isHoveringPopup && ! isHoveringMarkerForPopup) {
									popup.closePopup();
									console.log( 'Grouped marker hover out: popup closed after 1000ms delay' );
								}
							},
							1000
						);
						console.log( 'Grouped marker hover out: starting 1000ms delay for popup' );
					}
				}
			);

			// Popup hover events
			marker.on(
				'popupopen',
				function (e) {
					var popupElement = e.popup.getElement();
					if (popupElement) {
						popupElement.addEventListener(
							'mouseenter',
							function () {
								isHoveringPopup = true;
								clearTimeout( popupTimeout );
								console.log( 'Grouped popup hover: keeping popup open' );
							}
						);

						popupElement.addEventListener(
							'mouseleave',
							function () {
								isHoveringPopup = false;
								popupTimeout    = setTimeout(
									function () {
										if ( ! isHoveringPopup && ! isHoveringMarkerForPopup) {
											popup.closePopup();
											console.log( 'Grouped popup closed after popup leave delay' );
										}
									},
									2000
								);
								console.log( 'Grouped popup leave: starting 2000ms delay' );
							}
						);

						popupElement.addEventListener(
							'click',
							function (e) {
								e.stopPropagation();
								clearTimeout( popupTimeout );
								console.log( 'Grouped popup clicked: preventing close' );
							}
						);
					}
				}
			);

			// Mobile handling
			var isMobile = window.innerWidth <= 768 || /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test( navigator.userAgent );

			if (isMobile) {
				marker.on(
					'click',
					function (e) {
						var tooltipContent = self.createGroupedTooltipContent( members );
						marker.bindPopup(
							tooltipContent,
							{
								maxWidth: 300,
								className: 'grouped-member-mobile-popup'
							}
						).openPopup();

						console.log( 'Mobile click: showing grouped popup for', members.length, 'members' );
					}
				);
			} else {
				// Desktop: Click to zoom in and separate markers
				marker.on(
					'click',
					function (e) {
						console.log( 'Grouped marker clicked, zooming in to separate markers' );
						self.zoomToSeparateGroupedMarkers( members, lat, lng );
					}
				);
			}

			console.log( 'Grouped hover events bound to marker' );

			return marker;
		},

		/**
		 * Zoom in to separate grouped markers
		 */
		zoomToSeparateGroupedMarkers: function (members, centerLat, centerLng) {
			var self = this;

			// Remove the grouped marker temporarily
			var markersToRemove = [];
			this.markers.forEach(
				function (marker) {
					var markerLatLng = marker.getLatLng();
					if (Math.abs( markerLatLng.lat - centerLat ) < 0.0001 && Math.abs( markerLatLng.lng - centerLng ) < 0.0001) {
						markersToRemove.push( marker );
					}
				}
			);

			markersToRemove.forEach(
				function (marker) {
					self.markerClusterGroup.removeLayer( marker );
					var index = self.markers.indexOf( marker );
					if (index > -1) {
						self.markers.splice( index, 1 );
					}
				}
			);

			// Create individual markers in a small circle pattern
			var radius    = 0.001; // Small radius for separation
			var angleStep = (2 * Math.PI) / members.length;

			members.forEach(
				function (member, index) {
					var angle     = index * angleStep;
					var offsetLat = centerLat + (radius * Math.cos( angle ));
					var offsetLng = centerLng + (radius * Math.sin( angle ));

					// Create individual marker
					var individualMarker = self.createMemberMarker( member, offsetLat, offsetLng );
					self.markers.push( individualMarker );
					self.markerClusterGroup.addLayer( individualMarker );
				}
			);

			// Zoom to show the separated markers
			var bounds = L.latLngBounds();
			members.forEach(
				function (member, index) {
					var angle     = index * angleStep;
					var offsetLat = centerLat + (radius * Math.cos( angle ));
					var offsetLng = centerLng + (radius * Math.sin( angle ));
					bounds.extend( [offsetLat, offsetLng] );
				}
			);

			// Zoom to the bounds with some padding
			self.map.fitBounds(
				bounds,
				{
					padding: [50, 50],
					maxZoom: 16 // Limit zoom to prevent too much zoom
				}
			);

			console.log( 'Separated', members.length, 'members around', centerLat, centerLng );
		},

		/**
		 * Create popup content for member
		 */
		createPopupContent: function (member) {
			// Extract avatar URL from avatar HTML
			var avatarUrl = '';
			if (member.avatar) {
				var avatarMatch = member.avatar.match( /src="([^"]+)"/ );
				if (avatarMatch) {
					avatarUrl = avatarMatch[1];
				}
			}

			// Fallback to default avatar if no avatar found
			if ( ! avatarUrl) {
				avatarUrl = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1zbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMjAiIGN5PSIyMCIgcj0iMjAiIGZpbGw9IiNmMGYwZjAiLz4KPGNpcmNsZSBjeD0iMjAiIGN5PSIxNiIgcj0iNiIgZmlsbD0iIzk5OTk5OSIvPgo8cGF0aCBkPSJNMTAgMzBjMC01LjUgNC41LTEwIDEwLTEwczEwIDQuNSAxMCAxMHYySDEwdi0yeiIgZmlsbD0iIzk5OTk5OSIvPgo8L3N2Zz4K';
			}

			var html = '<div class="member-popup-card">';

			// Avatar and name section
			html += '<div class="member-popup-header">';
			html += '<div class="member-popup-avatar">';
			html += '<img src="' + avatarUrl + '" alt="' + member.name + '" onerror="this.src=\'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1zbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMjAiIGN5PSIyMCIgcj0iMjAiIGZpbGw9IiNmMGYwZjAiLz4KPGNpcmNsZSBjeD0iMjAiIGN5PSIxNiIgcj0iNiIgZmlsbD0iIzk5OTk5OSIvPgo8cGF0aCBkPSJNMTAgMzBjMC01LjUgNC41LTEwIDEwLTEwczEwIDQuNSAxMCAxMHYySDEwdi0yeiIgZmlsbD0iIzk5OTk5OSIvPgo8L3N2Zz4K\'">';
			html += '</div>';
			html += '<div class="member-popup-info">';
			html += '<h4 class="member-popup-name">' + member.name + '</h4>';
			html += '<p class="member-popup-role">' + this.getRoleLabel( member.role ) + '</p>';
			html += '</div>';
			html += '</div>';

			// Action buttons
			html += '<div class="member-popup-actions">';
			html += '<a href="' + member.profile_url + '" target="_blank" class="glass-menu-link">View Profile</a>';
			html += '<button class="glass-menu-link" onclick="window.open(\'' + member.profile_url + '\', \'_blank\')">Connect</button>';
			html += '</div>';

			html += '</div>';

			return html;
		},

		/**
		 * Create popup content for grouped members
		 */
		createGroupedPopupContent: function (members) {
			var html = '<div class="grouped-member-popup-card">';

			// Header with count and location info
			html += '<div class="grouped-member-popup-header">';
			html += '<div class="grouped-member-popup-title">';
			html += '<h3 class="grouped-member-popup-count">' + members.length + ' Members at This Location</h3>';
			html += '</div>';
			html += '</div>';

			// Member list with scroll if needed
			html += '<div class="grouped-member-popup-list">';
			members.forEach(
				function (member, index) {
					// Extract avatar URL from avatar HTML
					var avatarUrl = '';
					if (member.avatar) {
						var avatarMatch = member.avatar.match( /src="([^"]+)"/ );
						if (avatarMatch) {
							avatarUrl = avatarMatch[1];
						}
					}

					// Fallback to default avatar if no avatar found
					if ( ! avatarUrl) {
						avatarUrl = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1zbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMjAiIGN5PSIyMCIgcj0iMjAiIGZpbGw9IiNmMGYwZjAiLz4KPGNpcmNsZSBjeD0iMjAiIGN5PSIxNiIgcj0iNiIgZmlsbD0iIzk5OTk5OSIvPgo8cGF0aCBkPSJNMTAgMzBjMC01LjUgNC41LTEwIDEwLTEwczEwIDQuNSAxMCAxMHYySDEwdi0yeiIgZmlsbD0iIzk5OTk5OSIvPgo8L3N2Zz4K';
					}

					html += '<div class="grouped-member-popup-item" data-member-id="' + member.id + '">';
					html += '<div class="grouped-member-popup-avatar">';
					html += '<img src="' + avatarUrl + '" alt="' + member.name + '" onerror="this.src=\'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1zbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMjAiIGN5PSIyMCIgcj0iMjAiIGZpbGw9IiNmMGYwZjAiLz4KPGNpcmNsZSBjeD0iMjAiIGN5PSIxNiIgcj0iNiIgZmlsbD0iIzk5OTk5OSIvPgo8cGF0aCBkPSJNMTAgMzBjMC01LjUgNC41LTEwIDEwLTEwczEwIDQuNSAxMCAxMHYySDEwdi0yeiIgZmlsbD0iIzk5OTk5OSIvPgo8L3N2Zz4K\'">';
					html += '</div>';
					html += '<div class="grouped-member-popup-info">';
					html += '<h4 class="grouped-member-popup-name">' + member.name + '</h4>';
					html += '<p class="grouped-member-popup-role">' + this.getRoleLabel( member.role ) + '</p>';
					html += '</div>';
					html += '<div class="grouped-member-popup-actions">';
					html += '<a href="' + member.profile_url + '" target="_blank" class="grouped-member-popup-btn">View</a>';
					html += '</div>';
					html += '</div>';
				}
			);
			html += '</div>';

			html += '</div>';

			return html;
		},

		/**
		 * Create tooltip content for member (shown on hover)
		 */
		createTooltipContent: function (member) {
			// Extract avatar URL from avatar HTML
			var avatarUrl = '';
			if (member.avatar) {
				var avatarMatch = member.avatar.match( /src="([^"]+)"/ );
				if (avatarMatch) {
					avatarUrl = avatarMatch[1];
				}
			}

			// Fallback to default avatar if no avatar found
			if ( ! avatarUrl) {
				avatarUrl = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1zbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMjAiIGN5PSIyMCIgcj0iMjAiIGZpbGw9IiNmMGYwZjAiLz4KPGNpcmNsZSBjeD0iMjAiIGN5PSIxNiIgcj0iNiIgZmlsbD0iIzk5OTk5OSIvPgo8cGF0aCBkPSJNMTAgMzBjMC01LjUgNC41LTEwIDEwLTEwczEwIDQuNSAxMCAxMHYySDEwdi0yeiIgZmlsbD0iIzk5OTk5OSIvPgo8L3N2Zz4K';
			}

			// Get role color for modern styling
			var roleColor = this.getRoleColor( member.role );

			var html = '<div class="member-hover-card">';

			// Header with avatar - modern BuddyBoss style
			html += '<div class="member-hover-header">';
			html += '<div class="member-hover-avatar">';
			html += '<img src="' + avatarUrl + '" alt="' + member.name + '" onerror="this.src=\'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1zbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMjAiIGN5PSIyMCIgcj0iMjAiIGZpbGw9IiNmMGYwZjAiLz4KPGNpcmNsZSBjeD0iMjAiIGN5PSIxNiIgcj0iNiIgZmlsbD0iIzk5OTk5OSIvPgo8cGF0aCBkPSJNMTAgMzBjMC01LjUgNC41LTEwIDEwLTEwczEwIDQuNSAxMCAxMHYySDEwdi0yeiIgZmlsbD0iIzk5OTk5OSIvPgo8L3N2Zz4K\'">';
			html += '<div class="member-hover-status"></div>';
			html += '</div>';
			html += '<div class="member-hover-role-badge" style="background-color: ' + roleColor + '">' + this.getRoleIcon( member.role ) + '</div>';
			html += '</div>';

			// Member info
			html += '<div class="member-hover-info">';
			html += '<h4 class="member-hover-name">' + member.name + '</h4>';
			html += '<p class="member-hover-role">' + this.getRoleLabel( member.role ) + '</p>';
			html += '</div>';

			// Action buttons - modern BuddyBoss style
			html += '<div class="member-hover-actions">';
			html += '<a href="' + member.profile_url + '" target="_blank" class="member-hover-btn member-hover-btn-primary">';
			html += '<i class="bb-icon-l bb-icon-user"></i> View Profile';
			html += '</a>';
			html += '<button class="member-hover-btn member-hover-btn-secondary" onclick="window.open(\'' + member.profile_url + '\', \'_blank\')">';
			html += '<i class="bb-icon-l bb-icon-plus"></i> Connect';
			html += '</button>';
			html += '</div>';

			html += '</div>';

			return html;
		},

		/**
		 * Create tooltip content for grouped members (shown on hover)
		 */
		createGroupedTooltipContent: function (members) {
			var html = '<div class="grouped-member-hover-card">';

			// Header with count
			html += '<div class="grouped-member-hover-header">';
			html += '<div class="grouped-member-hover-title">';
			html += '<h3 class="grouped-member-hover-count">' + members.length + ' Members Here</h3>';
			html += '</div>';
			html += '</div>';

			// Show first few members
			var displayCount = Math.min( members.length, 3 );
			html            += '<div class="grouped-member-hover-list">';

			for (var i = 0; i < displayCount; i++) {
				var member = members[i];

				// Extract avatar URL from avatar HTML
				var avatarUrl = '';
				if (member.avatar) {
					var avatarMatch = member.avatar.match( /src="([^"]+)"/ );
					if (avatarMatch) {
						avatarUrl = avatarMatch[1];
					}
				}

				// Fallback to default avatar if no avatar found
				if ( ! avatarUrl) {
					avatarUrl = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1zbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMjAiIGN5PSIyMCIgcj0iMjAiIGZpbGw9IiNmMGYwZjAiLz4KPGNpcmNsZSBjeD0iMjAiIGN5PSIxNiIgcj0iNiIgZmlsbD0iIzk5OTk5OSIvPgo8cGF0aCBkPSJNMTAgMzBjMC01LjUgNC41LTEwIDEwLTEwczEwIDQuNSAxMCAxMHYySDEwdi0yeiIgZmlsbD0iIzk5OTk5OSIvPgo8L3N2Zz4K';
				}

				html += '<div class="grouped-member-hover-item">';
				html += '<div class="grouped-member-hover-avatar">';
				html += '<img src="' + avatarUrl + '" alt="' + member.name + '">';
				html += '</div>';
				html += '<div class="grouped-member-hover-info">';
				html += '<span class="grouped-member-hover-name">' + member.name + '</span>';
				html += '<span class="grouped-member-hover-role">' + this.getRoleLabel( member.role ) + '</span>';
				html += '</div>';
				html += '</div>';
			}

			if (members.length > 3) {
				html += '<div class="grouped-member-hover-more">';
				html += '<span>+ ' + (members.length - 3) + ' more members</span>';
				html += '</div>';
			}

			html += '</div>';

			// Action to view all
			html += '<div class="grouped-member-hover-actions">';
			html += '<button class="grouped-member-hover-btn" onclick="' +
				'var popup = document.querySelector(\'.leaflet-popup-content\'); ' +
				'if (popup) { ' +
				'popup.innerHTML = \'' + this.createGroupedPopupContent( members ).replace( /'/g, "\\'" ) + '\'; ' +
				'popup.closest(\'.leaflet-popup-content-wrapper\').style.maxWidth = \'350px\'; ' +
				'}">' +
				'View All Members' +
				'</button>';
			html += '</div>';

			html += '</div>';

			return html;
		},

		/**
		 * Get role label
		 */
		getRoleLabel: function (role) {
			switch (role) {
				case 'admin':
					return 'Group Admin';
				case 'mod':
					return 'Group Moderator';
				default:
					return 'Group Member';
			}
		},

		/**
		 * Get role icon
		 */
		getRoleIcon: function (role) {
			switch (role) {
				case 'admin':
					return '★'; // Star for admin
				case 'mod':
					return '♦'; // Diamond for moderator
				default:
					return '•'; // Dot for member
			}
		},

		/**
		 * Get role color
		 */
		getRoleColor: function (role) {
			switch (role) {
				case 'admin':
					return '#e74c3c'; // Red for admin
				case 'mod':
					return '#3498db'; // Blue for moderator
				default:
					return '#2ecc71'; // Green for member
			}
		},

		/**
		 * Clear all markers
		 */
		clearMarkers: function () {
			if (this.markerClusterGroup) {
				this.markerClusterGroup.clearLayers();
			}
			this.markers = [];
		},

		/**
		 * Add custom styles for tooltips and markers
		 */
		addCustomStyles: function () {
			// Create style element if it doesn't exist
			if ( ! document.getElementById( 'members-map-styles' )) {
				var style  = document.createElement( 'style' );
				style.id   = 'members-map-styles';
				style.type = 'text/css';
				document.head.appendChild( style );
				console.log( 'Custom styles added' );
			}
		},

		/**
		 * Show fixed hover card
		 */
		showFixedHoverCard: function (marker, member, content) {
			// Remove any existing hover card
			this.hideFixedHoverCard();

			// Get marker position on screen
			var markerElement = marker.getElement();
			if ( ! markerElement) {
				return;
			}

			var markerRect   = markerElement.getBoundingClientRect();
			var mapContainer = document.getElementById( 'members-map-container' );
			var mapRect      = mapContainer.getBoundingClientRect();

			// Calculate relative position within the map container
			var relativeX = markerRect.left - mapRect.left + (markerRect.width / 2);
			var relativeY = markerRect.top - mapRect.top;

			// Create hover card element
			var hoverCard       = document.createElement( 'div' );
			hoverCard.id        = 'fixed-hover-card';
			hoverCard.className = 'fixed-hover-card';
			hoverCard.innerHTML = content;

			// Position the card
			var cardWidth  = 240; // Card width from CSS
			var cardHeight = 320; // Approximate card height
			var padding    = 20;

			// Determine if card should be above or below marker
			var showAbove = (relativeY > cardHeight + padding);
			var showBelow = ! showAbove;

			// Calculate horizontal position (center on marker, but keep within map bounds)
			var leftPosition = relativeX - (cardWidth / 2);
			if (leftPosition < padding) {
				leftPosition = padding;
			} else if (leftPosition + cardWidth > mapRect.width - padding) {
				leftPosition = mapRect.width - cardWidth - padding;
			}

			// Calculate vertical position
			var topPosition;
			if (showAbove) {
				topPosition = relativeY - cardHeight - 15; // 15px gap above marker
			} else {
				topPosition = relativeY + 55; // 55px gap below marker (marker height + gap)
			}

			// Set position and add to map container
			hoverCard.style.position = 'absolute';
			hoverCard.style.left     = leftPosition + 'px';
			hoverCard.style.top      = topPosition + 'px';
			hoverCard.style.zIndex   = '10000';

			// Add hover card to map container
			mapContainer.appendChild( hoverCard );

			// Add event listeners to the hover card
			var self = this;
			var tooltipTimeout;

			hoverCard.addEventListener(
				'mouseenter',
				function () {
					clearTimeout( tooltipTimeout );
					console.log( 'Fixed hover card mouseenter' );
				}
			);

			hoverCard.addEventListener(
				'mouseleave',
				function () {
					tooltipTimeout = setTimeout(
						function () {
							self.hideFixedHoverCard();
							console.log( 'Fixed hover card hidden after mouseleave delay' );
						},
						2000
					);
					console.log( 'Fixed hover card mouseleave: starting 2000ms delay' );
				}
			);

			hoverCard.addEventListener(
				'click',
				function (e) {
					e.stopPropagation();
					clearTimeout( tooltipTimeout );
					console.log( 'Fixed hover card clicked: preventing close' );
				}
			);

			// Add entrance animation
			hoverCard.style.opacity   = '0';
			hoverCard.style.transform = 'translateY(' + (showAbove ? '10px' : '-10px') + ') scale(0.95)';

			// Trigger animation
			setTimeout(
				function () {
					hoverCard.style.transition = 'all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
					hoverCard.style.opacity    = '1';
					hoverCard.style.transform  = 'translateY(0) scale(1)';
				},
				10
			);

			console.log( 'Fixed hover card shown for', member.name );
		},

		/**
		 * Hide fixed hover card
		 */
		hideFixedHoverCard: function () {
			var existingCard = document.getElementById( 'fixed-hover-card' );
			if (existingCard) {
				// Add exit animation
				existingCard.style.transition = 'all 0.2s ease-out';
				existingCard.style.opacity    = '0';
				existingCard.style.transform  = 'translateY(-5px) scale(0.95)';

				// Remove after animation
				setTimeout(
					function () {
						if (existingCard.parentNode) {
							existingCard.parentNode.removeChild( existingCard );
						}
					},
					200
				);

				console.log( 'Fixed hover card hidden' );
			}
		},

	};

	// Initialize when document is ready
	$( document ).ready(
		function () {
			console.log( 'Document ready, initializing map...' );
			console.log( 'MembersMapData available:', typeof MembersMapData !== 'undefined' );
			console.log( 'Leaflet available:', typeof L !== 'undefined' );

			// Wait a bit for Leaflet to load if it's not immediately available
			if (typeof L === 'undefined') {
				console.log( 'Leaflet not ready, waiting...' );
				setTimeout(
					function () {
						console.log( 'Retrying map initialization...' );
						console.log( 'Leaflet available now:', typeof L !== 'undefined' );
						membersMap.init();
					},
					1000
				);
			} else {
				membersMap.init();
			}
		}
	);

})( jQuery );