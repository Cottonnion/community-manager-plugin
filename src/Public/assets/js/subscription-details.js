/**
 * Subscription Details JavaScript
 */
(function ($) {
    'use strict';

    // Initialize Sweetalert2 if available
    const Swal = window.Swal || null;

    /**
     * Initialize the subscription details functionality
     */
    function initSubscriptionDetails() {
        // Add event listener to renewal buttons
        $('.labgenz-subscription-renewal-btn').on('click', handleRenewalClick);
    }

    /**
     * Handle renewal button click
     * 
     * @param {Event} e Click event
     */
    function handleRenewalClick(e) {
        e.preventDefault();
        
        // Show Sweetalert2 notification for upcoming feature
        if (Swal) {
            Swal.fire({
                title: labgenzSubscriptionData.i18n.renewalTitle,
                text: labgenzSubscriptionData.i18n.renewalMessage,
                icon: 'info',
                confirmButtonText: labgenzSubscriptionData.i18n.renewalButton,
                confirmButtonColor: '#1976d2'
            });
        } else {
            // Fallback if Sweetalert2 is not available
            alert(labgenzSubscriptionData.i18n.renewalMessage);
        }
    }

    // Initialize on document ready
    $(document).ready(function () {
        initSubscriptionDetails();
    });

})(jQuery);
