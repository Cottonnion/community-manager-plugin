/**
 * Article Rating JavaScript for Frontend
 *
 * @version 1.1.0
 */
(function ($) {
	'use strict';

	$( document ).ready(
		function () {
			const ratingContainers = $( '.article-rating-container' );

			ratingContainers.each(
				function () {
					const container    = $( this );
					const submitBtn    = container.find( '.article-rating-submit' );
					const messageDiv   = container.find( '.article-rating-message' );
					const postId       = container.data( 'post-id' );
					const editLink     = container.find( '.edit-rating-link' );
					const ratedMessage = container.find( '.article-rated-message' );
					const editForm     = container.find( '.edit-form' );
					const cancelBtn    = container.find( '.article-rating-cancel' );

					let selectedRating = 0;

					// Initialize handlers for both new and edit forms
					initializeForm( container.find( '.article-rating-form[data-mode="new"]' ), 'new' );
					initializeForm( container.find( '.article-rating-form[data-mode="edit"]' ), 'edit' );

					// Edit link click
					editLink.on(
						'click',
						function (e) {
							e.preventDefault();
							ratedMessage.addClass( 'hidden' );
							editForm.removeClass( 'hidden' );
						}
					);

					// Cancel button click
					cancelBtn.on(
						'click',
						function () {
							editForm.addClass( 'hidden' );
							ratedMessage.removeClass( 'hidden' );
						}
					);

					// Initialize a rating form
					function initializeForm(form, mode) {
						if ( ! form.length) {
							return;
						}

						const stars             = form.find( '.article-rating-stars .star' );
						const submitBtn         = form.find( '.article-rating-submit' );
						let localSelectedRating = parseInt( stars.filter( '.active' ).last().data( 'rating' ) ) || 0;

						// Star hover effect
						stars.hover(
							function () {
								const rating = parseInt( $( this ).data( 'rating' ) );
								highlightStars( stars, rating );
							},
							function () {
								// On mouseout, reset stars or keep selected
								highlightStars( stars, localSelectedRating );
							}
						);

							// Star click
							stars.on(
								'click',
								function () {
									localSelectedRating = parseInt( $( this ).data( 'rating' ) );
									highlightStars( stars, localSelectedRating );
									submitBtn.prop( 'disabled', false );
								}
							);

							// Submit rating
							form.on(
								'submit',
								function (e) {
									e.preventDefault();

									if (localSelectedRating === 0) {
										return;
									}

									// Get localized data from wp_localize_script
									const localizationData = window.mlmmc_article_reviews_data || {};
									const submittingText   = localizationData.messages &&
									localizationData.messages.submitting ?
									localizationData.messages.submitting : 'Submitting...';

									submitBtn.prop( 'disabled', true ).text( submittingText );

									// Determine which action to use based on form mode
									const action = mode === 'edit' ? 'mlmmc_edit_article_review' : 'mlmmc_submit_article_review';

									$.ajax(
										{
											url: localizationData.ajaxUrl || ajaxurl,
											type: 'POST',
											data: {
												action: action,
												nonce: localizationData.nonce || '',
												post_id: postId,
												rating: localSelectedRating
											},
											success: function (response) {
												submitBtn.text( mode === 'edit' ? 'Update Rating' : 'Submit Rating' );

												if (response.success) {
													const successMessage = mode === 'edit' ?
													(localizationData.messages && localizationData.messages.update_success ?
													localizationData.messages.update_success : 'Your rating has been updated!') :
														(localizationData.messages && localizationData.messages.success ?
														localizationData.messages.success : 'Thank you for your rating!');

													showMessage( form.find( '.article-rating-message' ), successMessage, 'success' );

													// Update average rating display
													updateAverageRating( container, response.data.average, response.data.count );

													if (mode === 'edit') {
															// Update the user's rating text
															ratedMessage.text( 'You rated this article ' + localSelectedRating + ' star(s).' )
															.append( ' <a href="#" class="edit-rating-link">Edit my rating</a>' );

															// Hide the edit form and show the message
															setTimeout(
																function () {
																	editForm.addClass( 'hidden' );
																	ratedMessage.removeClass( 'hidden' );
																},
																1500
															);
													} else {
														// For new ratings
														form.addClass( 'hidden' );
														// Update and show the "already rated" message
														ratedMessage.text( 'You rated this article ' + localSelectedRating + ' star(s).' )
														.append( ' <a href="#" class="edit-rating-link">Edit my rating</a>' )
														.removeClass( 'hidden' );
													}

													// Re-bind the edit link since we replaced it
													container.find( '.edit-rating-link' ).on(
														'click',
														function (e) {
															e.preventDefault();
															ratedMessage.addClass( 'hidden' );
															editForm.removeClass( 'hidden' );
														}
													);
												} else {
													const errorMsg = response.data && response.data.message ?
													response.data.message :
													'Error ' + (mode === 'edit' ? 'updating' : 'submitting') + ' rating. Please try again.';

													showMessage( form.find( '.article-rating-message' ), errorMsg, 'error' );
													submitBtn.prop( 'disabled', false );
												}
											},
											error: function () {
												const errorMessage = localizationData.messages &&
												localizationData.messages.error ?
												localizationData.messages.error :
												'Error ' + (mode === 'edit' ? 'updating' : 'submitting') + ' rating. Please try again.';

												showMessage( form.find( '.article-rating-message' ), errorMessage, 'error' );
												submitBtn.prop( 'disabled', false ).text( mode === 'edit' ? 'Update Rating' : 'Submit Rating' );
											}
										}
									);
								}
							);
					}

					function highlightStars(starsCollection, rating) {
						starsCollection.removeClass( 'active' );
						starsCollection.each(
							function () {
								if (parseInt( $( this ).data( 'rating' ) ) <= rating) {
										$( this ).addClass( 'active' );
								}
							}
						);
					}

					function showMessage(messageElement, message, type) {
						messageElement.removeClass( 'success error' ).addClass( type ).text( message ).show();
						setTimeout(
							function () {
								messageElement.fadeOut( 500 );
							},
							3000
						);
					}

					function updateAverageRating(container, average, count) {
						const averageStars = container.find( '.article-rating-average-stars' );
						const countSpan    = container.find( '.article-rating-average-count' );
						const stars        = averageStars.find( '.star' );

						// Reset all stars
						stars.removeClass( 'active half-active' );

						// Apply the correct classes based on the average rating
						stars.each(
							function (index) {
								const starRating = index + 1;

								if (starRating <= Math.floor( average )) {
									// Full star
									$( this ).addClass( 'active' );
								} else if (starRating === Math.ceil( average ) && average % 1 >= 0.25) {
									// Half star (or more)
									$( this ).addClass( 'half-active' );
								}
							}
						);

						countSpan.text( '(' + count + ' ' + (count === 1 ? 'rating' : 'ratings') + ')' );
					}
				}
			);
		}
	);

})( jQuery );
