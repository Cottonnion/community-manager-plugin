/**
 * Article Reviews JavaScript for Admin
 */
(function($) {
  'use strict';

  // Initialize admin reviews page
  function initAdminReviewsPage() {
    // Only run on the reviews admin page
    if (!$('.mlmmc-reviews-admin').length) {
      return;
    }

    // Initialize delete review functionality
    initDeleteReview();
    
    // Initialize bulk actions
    initBulkActions();
    
    // Initialize ratings toggle
    initRatingsToggle();
  }

  // Initialize toggle for enabling/disabling ratings
  function initRatingsToggle() {
    const toggleCheckbox = $('#ratings-toggle');
    
    if (!toggleCheckbox.length) return;
    
    toggleCheckbox.on('change', function() {
      const isEnabled = $(this).is(':checked');
      
      // Update status text immediately for better UX
      updateToggleStatus(isEnabled);
      
      // Send AJAX request to update setting
      $.ajax({
        url: labgenz_cm_reviews_admin_js_data.ajaxUrl,
        type: 'POST',
        data: {
          action: 'mlmmc_toggle_ratings',
          nonce: labgenz_cm_reviews_admin_js_data.nonce,
          enabled: isEnabled ? 1 : 0
        },
        success: function(response) {
          if (response.success) {
            Swal.fire({
              title: 'Success!',
              text: response.data.message,
              icon: 'success',
              confirmButtonText: 'OK'
            });
            
            // Double check that the toggle state reflects the server state
            if (toggleCheckbox.is(':checked') !== response.data.enabled) {
              toggleCheckbox.prop('checked', response.data.enabled);
              updateToggleStatus(response.data.enabled);
            }
          } else {
            // Revert toggle if there was an error
            toggleCheckbox.prop('checked', !isEnabled);
            updateToggleStatus(!isEnabled);
            
            Swal.fire({
              title: 'Error',
              text: response.data.message || 'An error occurred while updating settings.',
              icon: 'error',
              confirmButtonText: 'OK'
            });
          }
        },
        error: function() {
          // Revert toggle if there was an error
          toggleCheckbox.prop('checked', !isEnabled);
          updateToggleStatus(!isEnabled);
          
          Swal.fire({
            title: 'Error',
            text: 'An error occurred while updating settings.',
            icon: 'error',
            confirmButtonText: 'OK'
          });
        }
      });
    });
  }
  
  // Update the toggle status text
  function updateToggleStatus(isEnabled) {
    const statusSpan = $('.toggle-status .status');
    if (statusSpan.length) {
      if (isEnabled) {
        statusSpan.text('Ratings are currently ENABLED');
      } else {
        statusSpan.text('Ratings are currently DISABLED');
      }
    }
  }

  // Handle individual review deletion
  function initDeleteReview() {
    $('.mlmmc-reviews-admin').on('click', '.delete-review', function(e) {
      e.preventDefault();
      
      const button = $(this);
      const postId = button.data('post-id');
      const userIdentifier = button.data('user-identifier');
      const nonce = button.data('nonce');
      const row = button.closest('tr');
      
      // Confirm deletion with SweetAlert2
      Swal.fire({
        title: 'Delete Review?',
        text: 'Are you sure you want to delete this review? This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
      }).then((result) => {
        if (result.isConfirmed) {
          // Send AJAX request to delete the review
          $.ajax({
            url: labgenz_cm_reviews_admin_js_data.ajaxUrl,
            type: 'POST',
            data: {
              action: 'mlmmc_delete_article_review',
              post_id: postId,
              user_identifier: userIdentifier,
              nonce: nonce
            },
            beforeSend: function() {
              // Disable the button and show loading state
              button.prop('disabled', true).text('Deleting...');
            },
            success: function(response) {
              if (response.success) {
                // Show success message
                Swal.fire({
                  title: 'Success!',
                  text: response.data.message,
                  icon: 'success',
                  timer: 2000,
                  showConfirmButton: false
                });
                
                // Remove the row with a fade effect
                row.fadeOut(400, function() {
                  $(this).remove();
                  
                  // Update counts on the page
                  updateReviewCounts();
                });
              } else {
                // Show error message
                Swal.fire({
                  title: 'Error',
                  text: response.data.message,
                  icon: 'error'
                });
                
                // Reset button state
                button.prop('disabled', false).text('Delete');
              }
            },
            error: function() {
              // Show generic error message
              Swal.fire({
                title: 'Error',
                text: 'An error occurred. Please try again.',
                icon: 'error'
              });
              
              // Reset button state
              button.prop('disabled', false).text('Delete');
            }
          });
        }
      });
    });
  }

  // Handle bulk actions
  function initBulkActions() {
    $('#mlmmc-reviews-form').on('submit', function(e) {
      const action = $('#bulk-action-selector-top').val() || $('#bulk-action-selector-bottom').val();
      const selectedCount = $('input[name="review_ids[]"]:checked').length;
      
      if (action === 'delete' && selectedCount > 0) {
        e.preventDefault();
        
        Swal.fire({
          title: 'Delete Multiple Reviews?',
          text: `Are you sure you want to delete ${selectedCount} reviews? This action cannot be undone.`,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#d33',
          cancelButtonColor: '#3085d6',
          confirmButtonText: 'Yes, delete them!',
          cancelButtonText: 'Cancel'
        }).then((result) => {
          if (result.isConfirmed) {
            // Submit the form
            $(this).off('submit').submit();
          }
        });
      }
    });

    // Handle "select all" checkboxes
    $('#cb-select-all-1, #cb-select-all-2').on('change', function() {
      const isChecked = $(this).prop('checked');
      $('input[name="review_ids[]"]').prop('checked', isChecked);
    });
  }

  // Update statistics counts after deletion
  function updateReviewCounts() {
    // Update total reviews count
    const totalReviews = $('.mlmmc-reviews-admin tbody tr').length;
    $('.mlmmc-reviews-stats .stats-box:nth-child(1) .stat-number').text(totalReviews);
    
    // Update displaying-num text
    const displayingText = totalReviews === 1 
      ? '1 item' 
      : `${totalReviews} items`;
    $('.displaying-num').text(displayingText);
    
    // If no reviews left, show empty state
    if (totalReviews === 0) {
      $('.mlmmc-reviews-admin table, .tablenav').hide();
      
      const emptyState = $('<div class="mlmmc-empty-state">' +
        '<div class="empty-icon">⭐</div>' +
        '<h2>No Reviews Yet</h2>' +
        '<p>When users start rating your articles, their reviews will appear here.</p>' +
        '</div>');
      
      $('.mlmmc-reviews-admin form').after(emptyState);
    }
    
    // Update average rating
    let totalRating = 0;
    let reviewCount = 0;
    $('.mlmmc-reviews-admin tbody tr').each(function() {
      // Count active stars in each row
      const activeStars = $(this).find('.column-rating .star.active').length;
      if (activeStars > 0) {
        totalRating += activeStars;
        reviewCount++;
      }
    });
    
    const averageRating = reviewCount > 0 ? (totalRating / reviewCount).toFixed(1) : 0;
    $('.mlmmc-reviews-stats .stats-box:nth-child(2) .stat-number').text(averageRating);
    
    // Update star display
    const stars = $('.mlmmc-reviews-stats .stats-box:nth-child(2) .star-rating');
    stars.empty();
    
    for (let i = 1; i <= 5; i++) {
      const star = $('<span>').text(i <= averageRating ? '★' : '☆');
      stars.append(star);
    }
    
    // Update articles count
    const uniqueArticles = {};
    $('.mlmmc-reviews-admin tbody tr').each(function() {
      const articleId = $(this).find('.column-article a').data('article-id');
      if (articleId) {
        uniqueArticles[articleId] = true;
      }
    });
    
    const articlesCount = Object.keys(uniqueArticles).length;
    $('.mlmmc-reviews-stats .stats-box:nth-child(3) .stat-number').text(articlesCount);
  }

  // Initialize on document ready
  $(document).ready(function() {
    initAdminReviewsPage();
  });

})(jQuery);