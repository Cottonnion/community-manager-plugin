/**
 * Article Rating JavaScript
 */
(function($) {
  'use strict';

  $(document).ready(function() {
    const ratingContainers = $('.article-rating-container');

    ratingContainers.each(function() {
      const container = $(this);
      const stars = container.find('.article-rating-stars .star');
      const submitBtn = container.find('.article-rating-submit');
      const messageDiv = container.find('.article-rating-message');
      const postId = container.data('post-id');
      let selectedRating = 0;

      // Star hover effect
      stars.hover(
        function() {
          const rating = parseInt($(this).data('rating'));
          highlightStars(rating);
        },
        function() {
          // On mouseout, reset stars or keep selected
          highlightStars(selectedRating);
        }
      );

      // Star click
      stars.on('click', function() {
        selectedRating = parseInt($(this).data('rating'));
        highlightStars(selectedRating);
        submitBtn.prop('disabled', false);
      });

      // Submit rating
      container.find('.article-rating-form').on('submit', function(e) {
        e.preventDefault();
        
        if (selectedRating === 0) {
          return;
        }

        submitBtn.prop('disabled', true).text(labgenzArticleReviews.messages.submitting || 'Submitting...');
        
        $.ajax({
          url: labgenzArticleReviews.ajaxUrl,
          type: 'POST',
          data: {
            action: 'mlmmc_submit_article_review',
            nonce: labgenzArticleReviews.nonce,
            post_id: postId,
            rating: selectedRating
          },
          success: function(response) {
            submitBtn.text('Submit Rating');
            
            if (response.success) {
              showMessage(labgenzArticleReviews.messages.success, 'success');
              container.find('.article-rating-form').addClass('hidden');
              
              // Update average rating display
              updateAverageRating(container, response.data.average, response.data.count);
              
              // Show the "already rated" message
              container.find('.article-rated-message').removeClass('hidden');
            } else {
              showMessage(response.data.message, 'error');
              submitBtn.prop('disabled', false);
            }
          },
          error: function() {
            showMessage(labgenzArticleReviews.messages.error, 'error');
            submitBtn.prop('disabled', false).text('Submit Rating');
          }
        });
      });

      function highlightStars(rating) {
        stars.removeClass('active');
        stars.each(function() {
          if (parseInt($(this).data('rating')) <= rating) {
            $(this).addClass('active');
          }
        });
      }

      function showMessage(message, type) {
        messageDiv.removeClass('success error').addClass(type).text(message).show();
        setTimeout(function() {
          messageDiv.fadeOut(500);
        }, 3000);
      }

      function updateAverageRating(container, average, count) {
        const averageStars = container.find('.article-rating-average-stars');
        const countSpan = container.find('.article-rating-average-count');
        const stars = averageStars.find('.star');
        
        stars.removeClass('active');
        stars.each(function(index) {
          if (index < Math.floor(average)) {
            $(this).addClass('active');
          } else if (index === Math.floor(average) && (average % 1) >= 0.5) {
            $(this).addClass('half-active');
          }
        });
        
        countSpan.text('(' + count + ' ' + (count === 1 ? 'rating' : 'ratings') + ')');
      }
    });
  });

})(jQuery);
