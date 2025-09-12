/**
 * MLMMC Articles Card Display JS
 *
 * Handles search, filtering, and loading more articles functionality
 */

(function ($) {
	'use strict';

	// Store state variables
	let searchTimeout;
	let isLoading         = false;
	let currentSearchTerm = '';
	let originalContent   = '';

	// Make these variables accessible globally
	window.currentPage        = 1;
	window.selectedAuthors    = [];
	window.selectedCategories = [];
	window.selectedRatings    = [];

	/**
	 * Initialize the functionality
	 */
	function init() {
		// Store original content for reset
		originalContent = $( '.mlmmc-articles-grid' ).html();

		// Check for URL parameters on page load
		checkUrlParameters();

		// Initialize event handlers
		initSearchHandlers();
		initLoadMoreButton();

		// Make performSearch function available globally
		window.performSearch = performSearch;
	}

	/**
	 * Check URL parameters and trigger search if needed
	 */
	function checkUrlParameters() {
		// Get query parameters
		const urlParams = new URLSearchParams( window.location.search );

		// Check for article category parameter
		if (urlParams.has( 'ac' )) {
			let category = urlParams.get( 'ac' );

			// Special cases: keep hyphens for "the-why-" and "the-why"
			if (category !== 'the-why-' && category !== 'the-why') {
				// Replace all hyphens with spaces for other categories
				category = category.replace( /-/g, ' ' );
			}

			console.log( 'MLMMC: Category from URL parameter:', category );

			// Set the category in the global selected categories
			window.selectedCategories = [category];

			// Trigger search with this category
			setTimeout(
				function () {
					performSearch();

					// If category filter exists, update its visual state
					if (typeof window.updateCategoryFilterVisual === 'function') {
						window.updateCategoryFilterVisual( category );
					}

					// Check the corresponding checkbox in the category filter
					$( `.mlmmc - category - checkbox[value = "${category}"]` ).prop( 'checked', true );

					// Update the UI
					if (typeof window.updateSelectedCategoriesUI === 'function') {
						window.updateSelectedCategoriesUI();
					}

					// Log for debugging
					console.log( 'MLMMC: Search triggered with category:', window.selectedCategories );
				},
				100
			);
		}
	}

	/**
	 * Initialize search functionality
	 */
	function initSearchHandlers() {
		// Search input handler with debounce
		$( '#mlmmc-search-input' ).on(
			'input',
			function () {
				clearTimeout( searchTimeout );
				currentSearchTerm = $( this ).val().trim();

				searchTimeout = setTimeout(
					function () {
						window.currentPage = 1;
						performSearch();
					},
					500
				);
			}
		);

		// Search button click handler
		$( '#mlmmc-search-button' ).on(
			'click',
			function () {
				window.currentPage = 1;
				performSearch();
			}
		);

		// Trigger search immediately when "Show only articles with videos" is toggled
		$( '#mlmmc-video-only' ).on(
			'change',
			function () {
				window.currentPage = 1;
				performSearch();
			}
		);
	}

	/**
	 * Initialize load more button
	 */
	function initLoadMoreButton() {
		$( document ).on(
			'click',
			'#mlmmc-load-more',
			function () {
				const maxPages = parseInt( $( this ).data( 'max-pages' ) );
				window.currentPage++;

				if (window.currentPage <= maxPages) {
					performSearch( true ); // True means append results
					$( this ).data( 'page', window.currentPage );
				}

				if (window.currentPage >= maxPages) {
					$( this ).hide();
				}
			}
		);
	}

	/**
	 * Perform search with current filters
	 *
	 * @param {boolean} append Whether to append results or replace them
	 */
	function performSearch(append = false) {
		// Prevent multiple simultaneous searches
		if (isLoading) {
			return;
		}

		isLoading = true;

		// Show loading
		$( '.mlmmc-articles-loading' ).fadeIn( 200 );

		const $grid = $( '.mlmmc-articles-grid' );

		// Clear the grid when starting a new search (not when appending or clicking "Load More")
		// if (!append) {
		// Remove any "no articles found" message and clean the grid
		$grid.empty();
		$grid.css( 'opacity', '0.3' );
		// }

		// Get categories from window.selectedCategories (set by category-filter.js)
		let categories = window.selectedCategories || [];

		// Process categories - replace hyphens with spaces except for special cases
		categories = categories.map(
			function (category) {
				// Special cases: keep hyphens for "the-why-" and "the-why"
				if (category !== 'the-why-' && category !== 'the-why') {
					// Replace all hyphens with spaces for other categories
					return category.replace( /-/g, ' ' );
				}
				return category;
			}
		);

		// Debug log to confirm categories are properly processed
		console.log( 'MLMMC Debug - Categories being sent to AJAX:', categories );

		// Get authors from window.selectedAuthors (set by author-filter.js)
		let authors = window.selectedAuthors || [];

		// Process authors - replace hyphens with spaces
		authors = authors.map(
			function (author) {
				return author.replace( /-/g, ' ' );
			}
		);

		// Get ratings from window.selectedRatings (set by rating-filter.js)
		const ratings = window.selectedRatings || [];

		// Get video-only filter state
		const videoOnly = $( '#mlmmc-video-only' ).is( ':checked' );

		// Always update URL with current category
		updateUrlWithCategory( categories );

		// Debug log to confirm the state of filters
		// console.log(
		// 'MLMMC Debug - Performing search with filters:',
		// {
		// authors: authors,
		// categories: categories,
		// ratings: ratings,
		// page: window.currentPage,
		// search: currentSearchTerm
		// }
		// );		// Get container data attributes
			const $container    = $( '.mlmmc-articles-container' );
			const layout 	    = $container.data( 'layout' ) || 'grid';
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
						categories: categories,
						authors: authors,
						ratings: ratings,
						vid_only: videoOnly, // Pass video-only filter
						layout: layout,
						page: window.currentPage,
						posts_per_page: postsPerPage,
						show_excerpt: showExcerpt ? 'true' : 'false',
						show_author: showAuthor ? 'true' : 'false',
						show_date: showDate ? 'true' : 'false',
						show_category: showCategory ? 'true' : 'false',
						show_rating: showRating ? 'true' : 'false',
						excerpt_length: excerptLength,
						// cache_buster: new Date().getTime() // Add cache buster timestamp
					},
					success: function (response) {
						$( '.mlmmc-articles-loading' ).fadeOut( 200 );
						isLoading = false;

						if (response.success) {
							window.updateCategoriesFilter( response.data.filtered_categories || [] );
							window.updateAuthorsFilter( response.data.filtered_authors || [] );
							window.updateRatingsFilter( response.data.filtered_ratings || [] );

							// Update the articles grid
							// const $grid = $('.mlmmc-articles-grid');
							// $grid.css('opacity', '1');

							// Wrapper and load more button

							const $wrapper = $( '#mlmmc-articles-wrapper' );
							$wrapper.empty();
							const $loadMore = $( '#mlmmc-load-more' );

							// Remove old no-results
							$wrapper.find( '.mlmmc-articles-no-results' ).remove();

							// Update content
							if (response.data.html) {
								if (append) {
									$wrapper.append( response.data.html );
								} else {
									$wrapper.html( response.data.html );
								}
							} else {
								$wrapper.html( '<div class="mlmmc-articles-no-results">No articles found.</div>' );
							}

							// Reset opacity
							$wrapper.css( 'opacity', '1' );

							// Update counter
							if (response.data.found_posts !== undefined) {
								const count = parseInt( response.data.found_posts );
								const text  = count === 1 ? '1 article' : count + ' articles';
								$( '#mlmmc-total-count' ).text( text );
							}

							// Update load more
							if (response.data.max_pages > 1) {
								$loadMore.data( 'max-pages', response.data.max_pages );

								if (window.currentPage < response.data.max_pages) {
									$loadMore.show();
								} else {
									$loadMore.hide();
								}
							} else {
								$loadMore.hide();
							}
						} else {
							$( '#mlmmc-articles-wrapper' )
							.empty()
							.html( '<div class="mlmmc-articles-error">Error loading articles. Please try again.</div>' )
							.css( 'opacity', '1' );
						}
					},
					error: function () {
						$( '.mlmmc-articles-loading' ).fadeOut( 200 );
						// Clear any previous content and show error
						$( '.mlmmc-articles-grid' ).empty().html( '<div class="mlmmc-articles-error">Error loading articles. Please try again.</div>' ).css( 'opacity', '1' );
						isLoading = false;
					}
				}
			);
	}

	// Initialize when document is ready
	$( document ).ready( init );

	/**
	 * Update the URL with the current category filter
	 *
	 * @param {Array} categories The currently selected categories
	 */
	function updateUrlWithCategory(categories) {
		// Get the current URL
		const currentUrl = new URL( window.location.href );

		// If we have exactly one category selected, update the URL
		if (categories && categories.length === 1) {
			const category = categories[0];

			// Special cases: keep as-is for "the-why-" and "the-why"
			if (category === 'the-why-' || category === 'the-why') {
				currentUrl.searchParams.set( 'ac', category );
			} else {
				// Convert spaces back to hyphens for URL for other categories
				const categorySlug = category.replace( / /g, '-' );
				currentUrl.searchParams.set( 'ac', categorySlug );
			}
		} else if (categories && categories.length === 0) {
			// If no categories selected, remove the parameter
			currentUrl.searchParams.delete( 'ac' );
		}

		// Update the URL without reloading the page
		window.history.replaceState( {}, '', currentUrl );
	}

})( jQuery );