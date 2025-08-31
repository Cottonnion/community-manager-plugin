(function ($) {
	'use strict';

	let selectedRatings = [];

	// Make the update function available globally
	window.updateRatingsFilter = function (filteredRatings) {
		if ( ! filteredRatings || ! Array.isArray( filteredRatings )) {
			console.log( 'MLMMC Debug - No filtered ratings data available' );
			return;
		}

		console.log( 'MLMMC Debug - Updating ratings filter with data:', filteredRatings );

		// Update the rating options with counts from filtered data
		$( '.mlmmc-rating-options .mlmmc-checkbox-option' ).each(
			function () {
				const $this  = $( this );
				const $input = $this.find( 'input' );
				const rating = $input.val();

				// Find the matching rating data
				const ratingData = filteredRatings.find( r => r.rating == rating );

				// Update the count if found, never hide the option
				if (ratingData) {
					// Check if there's already a count span
					let $countSpan = $this.find( '.mlmmc-rating-count' );

					// If not, create one
					if ($countSpan.length === 0) {
						$this.find( 'label' ).append( ' <span class="mlmmc-rating-count">(' + ratingData.count + ')</span>' );
					} else {
						// Otherwise update existing one
						$countSpan.text( '(' + ratingData.count + ')' );
					}

					// Always show all rating options, even if count is 0
					$this.show();
				}
			}
		);
	};

	function initRatingFilter() {
		$( '#mlmmc-rating-filter-toggle' ).on(
			'click',
			function () {
				$( '#mlmmc-rating-dropdown' ).toggle();
				$( this ).toggleClass( 'active' );
				$( '.chevron-icon', this ).toggleClass( 'flip' );
			}
		);

		$( document ).on(
			'click',
			function (event) {
				if ( ! $( event.target ).closest( '#mlmmc-rating-dropdown, #mlmmc-rating-filter-toggle' ).length) {
					$( '#mlmmc-rating-dropdown' ).hide();
					$( '#mlmmc-rating-filter-toggle' ).removeClass( 'active' );
					$( '.chevron-icon', '#mlmmc-rating-filter-toggle' ).removeClass( 'flip' );
				}
			}
		);

		function updateSelectedRatingsUI() {
			// Make sure we're targeting the ratings container, not categories
			const $container = $( '.mlmmc-selected-ratings' );

			if (selectedRatings.length === 0) {
				$container.empty().hide();
				return;
			}

			let html = '';
			selectedRatings.forEach(
				function (rating) {
					html += `
					< div class       = "mlmmc-selected-rating" >
						${rating} Stars
						< button type = "button" class = "remove-rating" data - rating = "${rating}" > × < / button >
					< / div >
					`;
				}
			);

			$container.html( html ).show();

			// Debug log to confirm the contents of selectedRatings during updates
			console.log( 'MLMMC Debug - Updating selected ratings:', selectedRatings );
		}

		$( document ).on(
			'change',
			'.mlmmc-rating-options input',
			function () {
				const rating = $( this ).val();

				if ($( this ).is( ':checked' )) {
					if ( ! selectedRatings.includes( rating )) {
						selectedRatings.push( rating );
					}
				} else {
					selectedRatings = selectedRatings.filter( r => r !== rating );
				}

				// Update the global variable
				window.selectedRatings = selectedRatings;

				updateSelectedRatingsUI();
			}
		);

		$( document ).on(
			'click',
			'.remove-rating',
			function () {
				const ratingToRemove = $( this ).data( 'rating' );
				selectedRatings      = selectedRatings.filter( r => r !== ratingToRemove );

				// Update the global variable
				window.selectedRatings = selectedRatings;

				// Update checkbox in dropdown
				$( `.mlmmc - rating - options input[value = "${ratingToRemove}"]` ).prop( 'checked', false );

				updateSelectedRatingsUI();
			}
		);

		// Ensure AJAX request includes selected ratings
		$( '#mlmmc-apply-ratings' ).on(
			'click',
			function () {
				$( '#mlmmc-rating-dropdown' ).hide();
				$( '#mlmmc-rating-filter-toggle' ).removeClass( 'active' );
				$( '.chevron-icon', '#mlmmc-rating-filter-toggle' ).removeClass( 'flip' );

				window.selectedRatings = selectedRatings;

				if (typeof window.performSearch === 'function') {
					window.performSearch( { ratings: selectedRatings } );
				}
			}
		);

		$( '#mlmmc-clear-ratings' ).on(
			'click',
			function () {
				selectedRatings        = [];
				window.selectedRatings = [];
				$( '.mlmmc-rating-options input' ).prop( 'checked', false );
				updateSelectedRatingsUI();
			}
		);
	}

	$( document ).ready( initRatingFilter );
})( jQuery );
