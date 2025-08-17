/**
 * Profile Location Visibility Handler
 * 
 * This script helps ensure location field visibility settings are properly saved
 */
(function($) {
    'use strict';

    // Log function that only runs when debug is enabled
    function logDebug(message) {
        if (ProfileLocationVisibility.debug) {
            console.log('[Location Visibility]', message);
        }
    }

    /**
     * Initialize location visibility handling
     */
    function initLocationVisibility() {
        logDebug('Initializing location visibility handler');
        
        // Find profile edit form
        const $form = $('#profile-edit-form');
        if (!$form.length) {
            logDebug('Profile edit form not found');
            return;
        }
        
        // Track visibility inputs to ensure they're properly submitted
        const visibilityFields = [];
        
        // Find location fields by looking for latitude inputs
        $form.find('input[name$="_latitude"]').each(function() {
            const fieldName = $(this).attr('name');
            const fieldId = fieldName.replace('_latitude', '');
            const visibilityKey = fieldId + '_visibility';
            
            logDebug(`Found location field: ${fieldId}`);
            
            // Look for existing visibility field
            let $visibilityField = $('input[name="' + visibilityKey + '"]');
            
            if ($visibilityField.length) {
                logDebug(`Found existing visibility field with value: ${$visibilityField.val()}`);
                visibilityFields.push(visibilityKey);
            } else {
                // If visibility field doesn't exist, look for field visibility in the DOM
                // This will be in the visibility settings toggle
                const $visibilitySelect = $form.find(`select[name="${visibilityKey}"]`);
                
                if ($visibilitySelect.length) {
                    logDebug(`Found visibility select with value: ${$visibilitySelect.val()}`);
                    visibilityFields.push(visibilityKey);
                } else {
                    // If no visibility field or select found, create a hidden input
                    logDebug(`Creating missing visibility field for: ${fieldId}`);
                    
                    // Get the field container
                    const $fieldContainer = $('input[name="' + fieldId + '"]').closest('.editfield');
                    
                    // Create a hidden input for the visibility
                    $fieldContainer.append(
                        $('<input>', {
                            type: 'hidden',
                            name: visibilityKey,
                            value: ProfileLocationVisibility.defaultVisibility || 'exact_location'
                        })
                    );
                    
                    visibilityFields.push(visibilityKey);
                    logDebug(`Created visibility field with default: ${ProfileLocationVisibility.defaultVisibility}`);
                }
            }
        });
        
        // If we found visibility fields, add a form submit handler to ensure they're submitted
        if (visibilityFields.length > 0) {
            logDebug(`Monitoring ${visibilityFields.length} visibility fields`);
            
            $form.on('submit', function() {
                logDebug('Form submit detected');
                
                // Check that all visibility fields are present
                for (const field of visibilityFields) {
                    const $field = $(`input[name="${field}"], select[name="${field}"]`);
                    
                    if ($field.length) {
                        logDebug(`Field ${field} present with value: ${$field.val()}`);
                    } else {
                        logDebug(`Field ${field} MISSING - this might cause visibility settings to be lost!`);
                    }
                }
                
                // Log form data for debugging
                const formData = new FormData(this);
                for (const pair of formData.entries()) {
                    if (pair[0].includes('visibility')) {
                        logDebug(`${pair[0]}: ${pair[1]}`);
                    }
                }
                
                return true; // Allow form submission to continue
            });
        }
    }

    // Initialize when document is ready
    $(document).ready(function() {
        initLocationVisibility();
    });

})(jQuery);
