/**
 * Leaderboard Tab Switching
 * Handles the real-time tab switching for BuddyPress group leaderboards
 */

(function($) {
    'use strict';

    // Define variables
    const ajaxUrl = labgenz_leaderboard.ajax_url;
    const nonce = labgenz_leaderboard.nonce;
    const groupId = labgenz_leaderboard.group_id;
    const currentUserId = labgenz_leaderboard.current_user_id;

    // Document ready handler
    $(document).ready(function() {
        initLeaderboardTabs();
        highlightCurrentUser();
    });

    /**
     * Initialize leaderboard tabs
     */
    function initLeaderboardTabs() {
        // Set up click handlers for tabs
        $('.leaderboard-tab').on('click', function(e) {
            e.preventDefault();
            
            // Get the tab type (all-time or weekly)
            const tabType = $(this).data('tab');
            
            // Skip if the tab is already active
            if ($(this).hasClass('active')) {
                return;
            }
            
            // Update active tab
            $('.leaderboard-tab').removeClass('active');
            $(this).addClass('active');
            
            // Update URL without page reload
            const newUrl = $(this).attr('href');
            history.pushState({tab: tabType}, '', newUrl);
            
            // Hide all tab content
            $('.leaderboard-tab-content').removeClass('active');
            
            // If the content is already loaded, just show it
            if ($('#' + tabType + '-leaderboard').length) {
                $('#' + tabType + '-leaderboard').addClass('active');
                return;
            }
            
            // Show loading state
            const leaderboardContainer = $('.leaderboard-main-content');
            leaderboardContainer.append('<div class="leaderboard-loading">' + labgenz_i18n.loading_message + '</div>');
            
            // Fetch the tab content via AJAX
            fetchLeaderboardData(tabType);
        });
        
        // Handle browser back/forward buttons
        $(window).on('popstate', function(e) {
            if (e.originalEvent.state && e.originalEvent.state.tab) {
                const tabType = e.originalEvent.state.tab;
                $('.leaderboard-tab[data-tab="' + tabType + '"]').trigger('click');
            }
        });
    }

    /**
     * Fetch leaderboard data from the server
     *
     * @param {string} tabType - The type of leaderboard (all-time or weekly)
     */
    function fetchLeaderboardData(tabType) {
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'fetch_leaderboard_data',
                nonce: nonce,
                group_id: groupId,
                tab_type: tabType
            },
            success: function(response) {
                // Remove loading indicator
                $('.leaderboard-loading').remove();
                
                if (response.success) {
                    // Add the content to the page
                    const leaderboardContainer = $('.leaderboard-main-content');
                    
                    // Create a new container for this tab content
                    const tabContent = $('<div id="' + tabType + '-leaderboard" class="leaderboard-tab-content active"></div>');
                    tabContent.html(response.data.html);
                    
                    // Append it to the container
                    leaderboardContainer.append(tabContent);
                    
                    // Highlight current user
                    highlightCurrentUser();
                } else {
                    // Handle error
                    console.error('Error fetching leaderboard data:', response.data.message);
                    showErrorMessage(labgenz_i18n.error_message);
                }
            },
            error: function(xhr, status, error) {
                // Remove loading indicator
                $('.leaderboard-loading').remove();
                
                // Handle AJAX error
                console.error('AJAX error:', error);
                showErrorMessage(labgenz_i18n.error_message);
            }
        });
    }
    
    /**
     * Display an error message to the user
     *
     * @param {string} message - The error message to display
     */
    function showErrorMessage(message) {
        const errorHtml = '<div class="leaderboard-error">' + message + '</div>';
        $('.leaderboard-main-content').append(errorHtml);
        
        // Remove the error after 4 seconds
        setTimeout(function() {
            $('.leaderboard-error').fadeOut(300, function() {
                $(this).remove();
            });
        }, 4000);
    }
    
    /**
     * Highlight the current user in the leaderboard
     */
    function highlightCurrentUser() {
        if (currentUserId) {
            $('.bp-group-leaderboard-item[data-user-id="' + currentUserId + '"]').addClass('current-user');
        }
    }

})(jQuery);
