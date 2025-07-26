/**
 * Profile Location Field Enhancement
 * 
 * This script enhances the standard BuddyPress/BuddyBoss location field
 * by adding geocoding capabilities using the OpenStreetMap Nominatim API.
 * 
 * It adds an autocomplete dropdown to the location field and stores 
 * the latitude and longitude as user meta when a location is selected.
 */
(function($) {
    'use strict';

    // Profile Location Field Handler
    var ProfileLocationField = {
        // Configuration
        config: {
            locationFieldId: 'field_4', // The field ID of the location field (change if needed)
            debounceTime: 500, // Delay before making geocoding request
            minChars: 3, // Minimum characters before triggering autocomplete
            maxResults: 5, // Maximum number of results to show
        },

        // DOM elements
        elements: {
            locationField: null,
            autocompleteContainer: null,
            autocompleteList: null,
            loadingIndicator: null,
            latitudeField: null,
            longitudeField: null,
            hiddenFields: null,
        },

        // Data
        data: {
            timer: null,
            selectedLocation: null,
        },

        /**
         * Initialize the location field enhancement
         */
        init: function() {
            this.setupElements();
            
            if (!this.elements.locationField) {
                return; // Exit if location field not found
            }
            
            this.createAutocompleteElements();
            this.createHiddenFields();
            this.bindEvents();
        },

        /**
         * Set up references to DOM elements
         */
        setupElements: function() {
            // Find the location field
            // First try by field name
            this.elements.locationField = $('input[name="field_location"], input#field_location');
            
            // If not found, try finding by field label
            if (this.elements.locationField.length === 0) {
                $('label').each(function() {
                    var label = $(this).text().toLowerCase();
                    if (label.includes('location') || label.includes('where are you')) {
                        var fieldId = $(this).attr('for');
                        ProfileLocationField.elements.locationField = $('#' + fieldId);
                        return false; // Break the loop
                    }
                });
            }
            
            // If still not found, try other common field names
            if (this.elements.locationField.length === 0) {
                this.elements.locationField = $('input[name="field_address"], input#field_address, ' +
                                              'input[name="field_city"], input#field_city');
            }
        },

        /**
         * Create autocomplete UI elements
         */
        createAutocompleteElements: function() {
            // Create container
            this.elements.autocompleteContainer = $('<div class="location-autocomplete-container"></div>');
            
            // Create loading indicator
            this.elements.loadingIndicator = $('<div class="location-loading">Searching...</div>');
            this.elements.loadingIndicator.hide();
            
            // Create results list
            this.elements.autocompleteList = $('<ul class="location-autocomplete-list"></ul>');
            this.elements.autocompleteList.hide();
            
            // Add elements to container
            this.elements.autocompleteContainer.append(this.elements.loadingIndicator);
            this.elements.autocompleteContainer.append(this.elements.autocompleteList);
            
            // Insert container after the location field
            this.elements.locationField.after(this.elements.autocompleteContainer);
            
            // Add some basic styling
            $('<style>')
                .prop('type', 'text/css')
                .html(`
                    .location-autocomplete-container {
                        position: relative;
                        width: 100%;
                    }
                    .location-loading {
                        padding: 8px;
                        background: #f8f8f8;
                        border: 1px solid #ddd;
                        border-top: none;
                    }
                    .location-autocomplete-list {
                        position: absolute;
                        z-index: 1000;
                        width: 100%;
                        max-height: 200px;
                        overflow-y: auto;
                        background: white;
                        border: 1px solid #ddd;
                        border-top: none;
                        margin: 0;
                        padding: 0;
                        list-style: none;
                    }
                    .location-autocomplete-list li {
                        padding: 8px 12px;
                        cursor: pointer;
                        border-bottom: 1px solid #f0f0f0;
                    }
                    .location-autocomplete-list li:hover,
                    .location-autocomplete-list li.active {
                        background-color: #f5f5f5;
                    }
                    .location-autocomplete-list li:last-child {
                        border-bottom: none;
                    }
                    .location-coordinates {
                        margin-top: 10px;
                        font-size: 12px;
                        color: #666;
                    }
                `)
                .appendTo('head');
        },

        /**
         * Create hidden fields for storing latitude and longitude
         */
        createHiddenFields: function() {
            this.elements.hiddenFields = $('<div class="location-hidden-fields" style="display:none;"></div>');
            
            // Create latitude and longitude fields
            this.elements.latitudeField = $('<input type="hidden" name="field_latitude" id="field_latitude">');
            this.elements.longitudeField = $('<input type="hidden" name="field_longitude" id="field_longitude">');
            
            // Add fields to container
            this.elements.hiddenFields.append(this.elements.latitudeField);
            this.elements.hiddenFields.append(this.elements.longitudeField);
            
            // Add container after the location field
            this.elements.locationField.after(this.elements.hiddenFields);
        },

        /**
         * Bind events to elements
         */
        bindEvents: function() {
            var self = this;
            
            // Input event for location field
            this.elements.locationField.on('input', function() {
                var query = $(this).val().trim();
                
                // Clear existing timer
                if (self.data.timer) {
                    clearTimeout(self.data.timer);
                }
                
                // Clear coordinates if the user changes the location text
                self.clearCoordinates();
                
                // Hide autocomplete list if query is empty or too short
                if (query.length < self.config.minChars) {
                    self.elements.autocompleteList.hide();
                    self.elements.loadingIndicator.hide();
                    return;
                }
                
                // Show loading indicator
                self.elements.loadingIndicator.show();
                
                // Set timer for geocoding
                self.data.timer = setTimeout(function() {
                    self.geocodeLocation(query);
                }, self.config.debounceTime);
            });
            
            // Keyboard navigation for autocomplete list
            this.elements.locationField.on('keydown', function(e) {
                var list = self.elements.autocompleteList;
                var active = list.find('li.active');
                
                // If list is not visible, return
                if (!list.is(':visible')) {
                    return;
                }
                
                switch (e.keyCode) {
                    case 40: // Down arrow
                        e.preventDefault();
                        if (active.length) {
                            active.removeClass('active');
                            active.next().addClass('active');
                        } else {
                            list.find('li:first').addClass('active');
                        }
                        break;
                        
                    case 38: // Up arrow
                        e.preventDefault();
                        if (active.length) {
                            active.removeClass('active');
                            active.prev().addClass('active');
                        } else {
                            list.find('li:last').addClass('active');
                        }
                        break;
                        
                    case 13: // Enter
                        if (active.length) {
                            e.preventDefault();
                            active.click();
                        }
                        break;
                        
                    case 27: // Escape
                        e.preventDefault();
                        list.hide();
                        break;
                }
            });
            
            // Hide autocomplete list when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.location-autocomplete-container, ' + self.elements.locationField.selector).length) {
                    self.elements.autocompleteList.hide();
                }
            });
            
            // Listen for form submission
            $('form.standard-form').on('submit', function() {
                // If coordinates are set, save them to user meta
                if (self.elements.latitudeField.val() && self.elements.longitudeField.val()) {
                    // Add AJAX request to save coordinates
                    $.ajax({
                        url: ajaxurl, // WordPress AJAX URL
                        type: 'POST',
                        data: {
                            action: 'save_location_coordinates',
                            latitude: self.elements.latitudeField.val(),
                            longitude: self.elements.longitudeField.val(),
                            location: self.elements.locationField.val(),
                            nonce: ProfileLocationData.nonce // Set from localized data
                        }
                    });
                }
            });
        },

        /**
         * Geocode location using OpenStreetMap Nominatim API
         * 
         * @param {string} query Location query to geocode
         */
        geocodeLocation: function(query) {
            var self = this;
            
            // Build Nominatim API URL
            var apiUrl = 'https://nominatim.openstreetmap.org/search';
            var params = {
                q: query,
                format: 'json',
                addressdetails: 1,
                limit: this.config.maxResults
            };
            
            // Make AJAX request
            $.ajax({
                url: apiUrl,
                data: params,
                dataType: 'json',
                headers: {
                    'Accept-Language': 'en', // Prefer English results
                    'User-Agent': 'BuddyPress Location Field' // Required by Nominatim usage policy
                },
                success: function(data) {
                    self.elements.loadingIndicator.hide();
                    self.displayResults(data);
                },
                error: function() {
                    self.elements.loadingIndicator.hide();
                    console.error('Geocoding failed');
                }
            });
        },

        /**
         * Display geocoding results in autocomplete list
         * 
         * @param {Array} results Geocoding results
         */
        displayResults: function(results) {
            var self = this;
            var list = this.elements.autocompleteList;
            
            // Clear list
            list.empty();
            
            // If no results, hide list and return
            if (!results || results.length === 0) {
                list.hide();
                return;
            }
            
            // Add results to list
            $.each(results, function(index, result) {
                var item = $('<li></li>');
                item.text(result.display_name);
                item.data('location', {
                    name: result.display_name,
                    latitude: result.lat,
                    longitude: result.lon
                });
                
                // Handle click on result
                item.on('click', function() {
                    var location = $(this).data('location');
                    self.selectLocation(location);
                    list.hide();
                });
                
                list.append(item);
            });
            
            // Show list
            list.show();
        },

        /**
         * Select a location from the autocomplete list
         * 
         * @param {Object} location Location object with name, latitude, longitude
         */
        selectLocation: function(location) {
            // Update location field
            this.elements.locationField.val(location.name);
            
            // Update hidden fields
            this.elements.latitudeField.val(location.latitude);
            this.elements.longitudeField.val(location.longitude);
            
            // Save selected location
            this.data.selectedLocation = location;
            
            // Show coordinates for debugging (optional)
            if (this.elements.locationField.closest('.editfield').find('.location-coordinates').length === 0) {
                var coordinates = $('<div class="location-coordinates">Coordinates: ' + 
                                   location.latitude + ', ' + location.longitude + '</div>');
                this.elements.locationField.closest('.editfield').append(coordinates);
            } else {
                this.elements.locationField.closest('.editfield').find('.location-coordinates')
                    .text('Coordinates: ' + location.latitude + ', ' + location.longitude);
            }
        },

        /**
         * Clear coordinates
         */
        clearCoordinates: function() {
            this.elements.latitudeField.val('');
            this.elements.longitudeField.val('');
            this.data.selectedLocation = null;
            
            // Remove coordinates display
            this.elements.locationField.closest('.editfield').find('.location-coordinates').remove();
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        // Check if we're on a profile edit page
        if ($('body.bp-user').length && 
            ($('.profile-edit').length || $('.edit-profile').length || $('form.standard-form').length)) {
            ProfileLocationField.init();
        }
    });

})(jQuery);
