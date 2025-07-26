/**
 * Location Field JavaScript
 *
 * Handles the custom location field functionality including:
 * - Autocomplete for location search
 * - Geolocation detection
 * - Map preview
 * - Coordinate storage
 */
(function ($) {
	'use strict';

	// Location Field Handler
	var LocationField = {
		// Configuration
		config: {
			debounceTime: 500,
			minChars: 3,
			maxResults: 5,
		},

		// Maps storage
		maps: {},

		/**
		 * Initialize all location fields on the page
		 */
		init: function () {
			$( '.location-field-container' ).each(
				function () {
					LocationField.initField( $( this ) );
				}
			);
		},

		/**
		 * Initialize a single location field
		 */
		initField: function ($container) {
			var $input          = $container.find( '.location-field-input' );
			var $autocomplete   = $container.find( '.location-autocomplete' );
			var $detectBtn      = $container.find( '.location-detect-btn' );
			var $mapPreview     = $container.find( '.location-map-preview' );
			var $latInput       = $container.find( 'input[name$="_latitude"]' );
			var $lngInput       = $container.find( 'input[name$="_longitude"]' );
			var $coordinatesDiv = $container.find( '.location-coordinates' );

			var fieldId = $input.attr( 'id' );
			var timer   = null;

			// Initialize map if coordinates exist
			if ($latInput.val() && $lngInput.val()) {
				LocationField.initMap( $mapPreview, $latInput.val(), $lngInput.val(), $input.val() );
			}

			// Input events
			$input.on(
				'input',
				function () {
					var query = $( this ).val().trim();

					// Clear timer
					if (timer) {
						clearTimeout( timer );
					}

					// Clear coordinates when user types
					LocationField.clearCoordinates( $latInput, $lngInput, $coordinatesDiv, $mapPreview );

					// Hide autocomplete if query too short
					if (query.length < LocationField.config.minChars) {
						$autocomplete.hide();
						return;
					}

					// Set timer for search
					timer = setTimeout(
						function () {
							LocationField.searchLocation( query, $autocomplete, $input, $latInput, $lngInput, $coordinatesDiv, $mapPreview );
						},
						LocationField.config.debounceTime
					);
				}
			);

			// Keyboard navigation
			$input.on(
				'keydown',
				function (e) {
					var $active = $autocomplete.find( '.location-autocomplete-item.active' );

					if ( ! $autocomplete.is( ':visible' )) {
						return;
					}

					switch (e.keyCode) {
						case 40: // Down
							e.preventDefault();
							if ($active.length) {
								$active.removeClass( 'active' ).next().addClass( 'active' );
							} else {
								$autocomplete.find( '.location-autocomplete-item:first' ).addClass( 'active' );
							}
							break;

						case 38: // Up
							e.preventDefault();
							if ($active.length) {
								$active.removeClass( 'active' ).prev().addClass( 'active' );
							} else {
								$autocomplete.find( '.location-autocomplete-item:last' ).addClass( 'active' );
							}
							break;

						case 13: // Enter
							if ($active.length) {
								e.preventDefault();
								$active.click();
							}
							break;

						case 27: // Escape
							e.preventDefault();
							$autocomplete.hide();
							break;
					}
				}
			);

			// Click outside to close
			$( document ).on(
				'click',
				function (e) {
					if ( ! $( e.target ).closest( $container ).length) {
						$autocomplete.hide();
					}
				}
			);

			// Detect location button
			$detectBtn.on(
				'click',
				function () {
					LocationField.detectLocation( $input, $latInput, $lngInput, $coordinatesDiv, $mapPreview, $detectBtn );
				}
			);
		},

		/**
		 * Search for location using geocoding
		 */
		searchLocation: function (query, $autocomplete, $input, $latInput, $lngInput, $coordinatesDiv, $mapPreview) {
			// Show loading
			$autocomplete.html( '<div class="location-autocomplete-item">' + LocationFieldData.strings.searching + '</div>' ).show();

			// Make AJAX request
			$.ajax(
				{
					url: LocationFieldData.ajaxurl,
					type: 'POST',
					data: {
						action: 'geocode_location',
						query: query,
						nonce: LocationFieldData.nonce
					},
					success: function (response) {
						if (response.success && response.data.results.length > 0) {
							LocationField.displayResults( response.data.results, $autocomplete, $input, $latInput, $lngInput, $coordinatesDiv, $mapPreview );
						} else {
							$autocomplete.html( '<div class="location-autocomplete-item">' + LocationFieldData.strings.geocode_failed + '</div>' );
						}
					},
					error: function () {
						$autocomplete.html( '<div class="location-autocomplete-item">' + LocationFieldData.strings.geocode_failed + '</div>' );
					}
				}
			);
		},

		/**
		 * Display search results
		 */
		displayResults: function (results, $autocomplete, $input, $latInput, $lngInput, $coordinatesDiv, $mapPreview) {
			var html = '';

			$.each(
				results,
				function (index, result) {
					html += '<div class="location-autocomplete-item" data-lat="' + result.lat + '" data-lng="' + result.lon + '">' +
						result.display_name + '</div>';
				}
			);

			$autocomplete.html( html ).show();

			// Handle item clicks
			$autocomplete.find( '.location-autocomplete-item' ).on(
				'click',
				function () {
					var $item   = $( this );
					var lat     = $item.data( 'lat' );
					var lng     = $item.data( 'lng' );
					var address = $item.text();

					LocationField.selectLocation( address, lat, lng, $input, $latInput, $lngInput, $coordinatesDiv, $mapPreview );
					$autocomplete.hide();
				}
			);
		},

		/**
		 * Select a location
		 */
		selectLocation: function (address, lat, lng, $input, $latInput, $lngInput, $coordinatesDiv, $mapPreview) {
			// Update input and hidden fields
			$input.val( address );
			$latInput.val( lat );
			$lngInput.val( lng );

			// Update coordinates display
			if ($coordinatesDiv.length) {
				$coordinatesDiv.html( '<small>Coordinates: ' + lat + ', ' + lng + '</small>' ).show();
			} else {
				$input.after( '<div class="location-coordinates"><small>Coordinates: ' + lat + ', ' + lng + '</small></div>' );
			}

			// Initialize or update map
			LocationField.initMap( $mapPreview, lat, lng, address );
			$mapPreview.show();
		},

		/**
		 * Clear coordinates
		 */
		clearCoordinates: function ($latInput, $lngInput, $coordinatesDiv, $mapPreview) {
			$latInput.val( '' );
			$lngInput.val( '' );
			$coordinatesDiv.hide();
			$mapPreview.hide();
		},

		/**
		 * Detect user's current location
		 */
		detectLocation: function ($input, $latInput, $lngInput, $coordinatesDiv, $mapPreview, $detectBtn) {
			if ( ! navigator.geolocation) {
				alert( 'Geolocation is not supported by this browser.' );
				return;
			}

			// Disable button and show loading
			$detectBtn.prop( 'disabled', true ).text( LocationFieldData.strings.detecting );

			navigator.geolocation.getCurrentPosition(
				function (position) {
					var lat = position.coords.latitude;
					var lng = position.coords.longitude;

					// Reverse geocode to get address
					LocationField.reverseGeocode(
						lat,
						lng,
						function (address) {
							LocationField.selectLocation( address, lat, lng, $input, $latInput, $lngInput, $coordinatesDiv, $mapPreview );
							$detectBtn.prop( 'disabled', false ).text( 'Detect My Location' );
						}
					);
				},
				function (error) {
					alert( LocationFieldData.strings.detect_failed );
					$detectBtn.prop( 'disabled', false ).text( 'Detect My Location' );
				}
			);
		},

		/**
		 * Reverse geocode coordinates to address
		 */
		reverseGeocode: function (lat, lng, callback) {
			$.ajax(
				{
					url: LocationFieldData.ajaxurl,
					type: 'POST',
					data: {
						action: 'geocode_location',
						query: lat + ',' + lng,
						nonce: LocationFieldData.nonce
					},
					success: function (response) {
						if (response.success && response.data.results.length > 0) {
							callback( response.data.results[0].display_name );
						} else {
							callback( 'Location at ' + lat + ', ' + lng );
						}
					},
					error: function () {
						callback( 'Location at ' + lat + ', ' + lng );
					}
				}
			);
		},

		/**
		 * Initialize map preview
		 */
		initMap: function ($mapContainer, lat, lng, address) {
			var mapId = $mapContainer.attr( 'id' );

			// Remove existing map if present
			if (LocationField.maps[mapId]) {
				LocationField.maps[mapId].remove();
			}

			// Create new map
			var map = L.map( mapId ).setView( [lat, lng], 13 );

			// Add tile layer
			L.tileLayer(
				'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
				{
					attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
				}
			).addTo( map );

			// Add marker
			L.marker( [lat, lng] ).addTo( map )
				.bindPopup( address )
				.openPopup();

			// Store map reference
			LocationField.maps[mapId] = map;

			// Refresh map size after a short delay
			setTimeout(
				function () {
					map.invalidateSize();
				},
				100
			);
		}
	};

	// Initialize when document is ready
	$( document ).ready(
		function () {
			LocationField.init();
		}
	);

	// Also initialize when new content is loaded (for AJAX pages)
	$( document ).on(
		'DOMNodeInserted',
		function (e) {
			if ($( e.target ).find( '.location-field-container' ).length) {
				LocationField.init();
			}
		}
	);

})( jQuery );
