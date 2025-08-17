/**
 * Enhanced Location Field Visibility Fix
 *
 * This script ensures consistent map visibility behavior and provides
 * debugging for visibility options selection.
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        console.log('[Location Visibility] Script initialized');
        
        // Function to handle visibility radio button changes
        function handleVisibilityChange() {
            // Get all visibility radio buttons for field_id 4
            var $visibilityOptions = $('input[name="field_4_visibility"]');
            
            if ($visibilityOptions.length) {
                console.log('[Location Visibility] Found visibility options:', $visibilityOptions.length);
                
                // Get currently selected option
                var $selectedOption = $visibilityOptions.filter(':checked');
                console.log('[Location Visibility] Currently selected:', $selectedOption.val());
                
                // Get the map container
                var $mapContainer = $('.location-map-preview');
                
                // Get coordinates inputs
                var $latInput = $('input[name$="_latitude"]');
                var $lngInput = $('input[name$="_longitude"]');
                
                // Show map if coordinates exist, regardless of visibility option
                if ($latInput.val() && $lngInput.val()) {
                    console.log('[Location Visibility] Showing map - coordinates found');
                    $mapContainer.show();
                }
                
                // Listen for visibility option changes
                $visibilityOptions.on('change', function() {
                    console.log('[Location Visibility] Option changed to:', $(this).val());
                    
                    // Always show map if coordinates exist
                    if ($latInput.val() && $lngInput.val()) {
                        $mapContainer.show();
                    }
                });
            } else {
                console.log('[Location Visibility] No visibility options found yet, will check again on DOM changes');
            }
        }
        
        // Initialize on page load
        handleVisibilityChange();
        
        // Also initialize when new content is loaded (for AJAX pages)
        $(document).on('DOMNodeInserted', function(e) {
            // Check if inserted element or its children contain visibility options
            if ($(e.target).find('input[name="field_4_visibility"]').length || 
                $(e.target).is('input[name="field_4_visibility"]')) {
                console.log('[Location Visibility] New DOM content with visibility options detected');
                setTimeout(handleVisibilityChange, 100); // Small delay to ensure elements are fully rendered
            }
        });
    });

})(jQuery);