/**
 * Category filter handler - to match the author filter functionality
 */
function initCategoryFilter() {
	// Selected categories array
	window.selectedCategories = [];

	// Toggle dropdown
	$( '#mlmmc-category-filter-toggle' ).on(
		'click',
		function () {
			$( '#mlmmc-category-dropdown' ).toggle();
			$( this ).toggleClass( 'active' );
			$( '.chevron-icon', this ).toggleClass( 'flip' );
		}
	);

	// Close dropdown when clicking outside
	$( document ).on(
		'click',
		function (event) {
			if ( ! $( event.target ).closest( '.mlmmc-filter-dropdown' ).length) {
				$( '#mlmmc-category-dropdown' ).hide();
				$( '#mlmmc-category-filter-toggle' ).removeClass( 'active' );
				$( '.chevron-icon' ).removeClass( 'flip' );
			}
		}
	);

	// Category search
	$( '#mlmmc-category-search' ).on(
		'input',
		function () {
			const searchTerm = $( this ).val().toLowerCase().trim();

			$( '.mlmmc-checkbox-option' ).each(
				function () {
					const categoryText = $( this ).text().toLowerCase();
					if (categoryText.includes( searchTerm )) {
						$( this ).show();
					} else {
						$( this ).hide();
					}
				}
			);
		}
	);

	// "All Categories" checkbox handler
	$( '.mlmmc-checkbox-option input[value="all"]' ).on(
		'change',
		function () {
			if ($( this ).is( ':checked' )) {
				$( '.mlmmc-checkbox-option input' ).not( this ).prop( 'checked', false );
			}
		}
	);

	// Regular category checkboxes
	$( '.mlmmc-checkbox-option input' ).not( '[value="all"]' ).on(
		'change',
		function () {
			if ($( this ).is( ':checked' )) {
				// If a regular category is checked, uncheck "All Categories"
				$( '.mlmmc-checkbox-option input[value="all"]' ).prop( 'checked', false );
			}

			// If no regular categories are checked, check "All Categories"
			if ($( '.mlmmc-checkbox-option input:checked' ).not( '[value="all"]' ).length === 0) {
				$( '.mlmmc-checkbox-option input[value="all"]' ).prop( 'checked', true );
			}
		}
	);

	// Clear all categories
	$( '#mlmmc-clear-categories' ).on(
		'click',
		function () {
			$( '.mlmmc-checkbox-option input' ).prop( 'checked', false );
			$( '.mlmmc-checkbox-option input[value="all"]' ).prop( 'checked', true );
		}
	);

	// Apply categories filter
	$( '#mlmmc-apply-categories' ).on(
		'click',
		function () {
			// Close dropdown
			$( '#mlmmc-category-dropdown' ).hide();
			$( '#mlmmc-category-filter-toggle' ).removeClass( 'active' );
			$( '.chevron-icon', $( '#mlmmc-category-filter-toggle' ) ).removeClass( 'flip' );

			// Get selected categories
			selectedCategories = [];

			// If "All Categories" is selected, empty the array
			if ($( '.mlmmc-checkbox-option input[value="all"]' ).is( ':checked' )) {
				selectedCategories = [];
				updateCategoryToggleText();

				// Perform search with all categories
				currentPage = 1;
				performSearch();
				return;
			}

			// Get all checked categories
			$( '.mlmmc-checkbox-option input:checked' ).not( '[value="all"]' ).each(
				function () {
					selectedCategories.push( $( this ).val() );
				}
			);

			// Update toggle button text
			updateCategoryToggleText();

			// Perform search with selected categories
			currentPage = 1;
			performSearch();
		}
	);
}

/**
 * Update the category toggle button text based on selection
 */
function updateCategoryToggleText() {
	if (selectedCategories.length === 0) {
		$( '#mlmmc-category-filter-toggle span:first' ).text( 'Filter by Category' );
	} else {
		$( '#mlmmc-category-filter-toggle span:first' ).text( `${selectedCategories.length} category( s )` );
	}
}

// Update the performSearch function to use selectedCategories
const originalPerformSearch = window.performSearch;
window.performSearch        = function (append) {
	if ( ! append) {
		$( '.mlmmc-articles-loading' ).fadeIn( 200 );
		isLoading = true;

		if ( ! append) {
			$( '.mlmmc-articles-grid' ).css( 'opacity', '0.3' );
		}

		// Get container data attributes
		const $container    = $( '.mlmmc-articles-container' );
		const showExcerpt   = $container.data( 'show-excerpt' );
		const showAuthor    = $container.data( 'show-author' );
		const showDate      = $container.data( 'show-date' );
		const showCategory  = $container.data( 'show-category' );
		const showRating    = $container.data( 'show-rating' );
		const excerptLength = $container.data( 'excerpt-length' );
		const postsPerPage  = $container.data( 'posts-per-page' );

		// Perform AJAX request
		$.ajax(
			{
				url: mlmmcArticlesData.ajaxUrl,
				type: 'POST',
				data: {
					action: mlmmcArticlesData.searchAction,
					nonce: mlmmcArticlesData.nonce,
					search: currentSearchTerm,
					categories: selectedCategories, // Use selectedCategories array
					authors: selectedAuthors,
					page: currentPage,
					posts_per_page: postsPerPage,
					show_excerpt: showExcerpt ? 'true' : 'false',
					show_author: showAuthor ? 'true' : 'false',
					show_date: showDate ? 'true' : 'false',
					show_category: showCategory ? 'true' : 'false',
					show_rating: showRating ? 'true' : 'false',
					excerpt_length: excerptLength
				},
				success: function (response) {
					$( '.mlmmc-articles-loading' ).fadeOut( 200 );
					isLoading = false;

					if (response.success) {
						const $grid = $( '.mlmmc-articles-grid' );

						if (append) {
							$grid.append( response.data.html );
						} else {
							$grid.html( response.data.html ).css( 'opacity', '1' );
						}

						// Update article count
						$( '#mlmmc-total-count' ).text( response.data.found_posts + ' article' + (response.data.found_posts !== 1 ? 's' : '') );

						// Show/hide load more button
						if (response.data.max_pages > 1 && currentPage < response.data.max_pages) {
							const $loadMore = $( '#mlmmc-load-more' );
							if ($loadMore.length) {
								$loadMore.show().data( 'max-pages', response.data.max_pages );
							} else {
								$(
									'<div class="mlmmc-articles-pagination" style="margin-top: 40px; text-align: center;">' +
									'<button id="mlmmc-load-more" class="mlmmc-load-more-button" data-page="' + currentPage + '" data-max-pages="' + response.data.max_pages + '">' +
									'Load More Articles' +
									'</button>' +
									'</div>'
								).appendTo( '.mlmmc-articles-content-wrapper' );
							}
						} else {
							$( '#mlmmc-load-more' ).hide();
						}

						if (response.data.html === '') {
							$grid.html( '<div class="mlmmc-articles-no-results">No articles found.</div>' );
						}
					}
				},
				error: function () {
					$( '.mlmmc-articles-loading' ).fadeOut( 200 );
					isLoading = false;
					$( '.mlmmc-articles-grid' ).css( 'opacity', '1' );
				}
			}
		);
	}
};
