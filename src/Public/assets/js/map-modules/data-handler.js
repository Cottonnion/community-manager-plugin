/**
 * Data Handler Module
 *
 * Handles data loading and processing for the members map
 */
var DataHandler = (function ($) {
	'use strict';

	// Public API
	return {
		/**
		 * Load members data via AJAX
		 *
		 * @param {object} options Configuration options
		 * @param {function} callback Callback function to execute with the loaded data
		 */
		loadMembersData: function (options, callback) {
			console.log( 'DataHandler: Loading members data' );

			// Show loading indicator
			$( '#' + options.containerId ).append( '<div class="map-loading">Loading members data...</div>' );

			console.log(
				'DataHandler: Making AJAX request with data:',
				{
					action: 'get_group_members_location',
					group_id: options.groupId,
					nonce: options.nonce
				}
			);

			// Make AJAX request to get members data
			$.ajax(
				{
					url: options.ajaxUrl,
					type: 'POST',
					data: {
						action: 'get_group_members_location',
						group_id: options.groupId,
						nonce: options.nonce
					},
					success: function (response) {
						console.log( 'DataHandler: AJAX Response:', response );

						// Remove loading indicator
						$( '.map-loading' ).remove();

						if (response.success && response.data.members) {
							console.log( 'DataHandler: Members data received:', response.data.members );
							if (response.data.members.length > 0) {
								console.log( 'DataHandler: Calling callback with', response.data.members.length, 'members' );
								callback( null, response.data.members );
							} else {
								console.log( 'DataHandler: No members found' );
								$( '#' + options.containerId ).append( '<div class="map-no-data">No members with location data found. You can add your location from your profile page.</div>' );
								callback( null, [] );
							}
						} else {
							console.error( 'DataHandler: Error in response:', response );
							$( '#' + options.containerId ).append( '<div class="map-error">Error loading members data.</div>' );
							callback( new Error( 'Invalid response data' ), null );
						}
					},
					error: function (xhr, status, error) {
						console.error( 'DataHandler: AJAX Error:', error );
						console.error( 'DataHandler: XHR:', xhr );
						console.error( 'DataHandler: Status:', status );

						$( '.map-loading' ).remove();
						$( '#' + options.containerId ).append( '<div class="map-error">Error loading members data: ' + error + '</div>' );
						callback( error, null );
					}
				}
			);
		},

		/**
		 * Process members data to group by location
		 *
		 * @param {array} members Array of member data
		 * @return {object} Object with location groups
		 */
		groupMembersByLocation: function (members) {
			console.log( 'DataHandler: Grouping members by location' );

			var locationGroups = {};
			var skippedMembers = 0;

			members.forEach(
				function (member) {
					if (member.latitude && member.longitude) {
						var lat = parseFloat( member.latitude );
						var lng = parseFloat( member.longitude );

						// Validate coordinates
						if (isNaN( lat ) || isNaN( lng )) {
							console.warn( 'DataHandler: Invalid coordinates for member', member.name );
							skippedMembers++;
							return; // Skip this member
						}

						// Round to 6 decimal places for grouping (approximately 10cm precision)
						// This ensures that members with very slightly different coordinates
						// are still grouped together
						var roundedLat  = lat.toFixed( 6 );
						var roundedLng  = lng.toFixed( 6 );
						var locationKey = roundedLat + ',' + roundedLng;

						if ( ! locationGroups[locationKey]) {
							locationGroups[locationKey] = {
								lat: lat,
								lng: lng,
								members: []
							};
						}

						locationGroups[locationKey].members.push( member );
					} else {
						console.warn( 'DataHandler: Missing coordinates for member', member.name );
						skippedMembers++;
					}
				}
			);

			console.log( 'DataHandler: Created', Object.keys( locationGroups ).length, 'location groups' );
			console.log( 'DataHandler: Skipped', skippedMembers, 'members with missing or invalid coordinates' );

			return locationGroups;
		}
	};
})( jQuery );
