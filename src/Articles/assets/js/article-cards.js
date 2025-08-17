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

		// Initialize event handlers
		initSearchHandlers();
		initLoadMoreButton();

		// Make performSearch function available globally
		window.performSearch = performSearch;
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
		const categories = window.selectedCategories || [];

		// Get authors from window.selectedAuthors (set by author-filter.js)
		const authors = window.selectedAuthors || [];

		// Get ratings from window.selectedRatings (set by rating-filter.js)
		const ratings = window.selectedRatings || [];

		// Get video-only filter state
		const videoOnly = $( '#mlmmc-video-only' ).is( ':checked' );

		// Debug log to confirm the state of filters
		console.log(
			'MLMMC Debug - Performing search with filters:',
			{
				authors: authors,
				categories: categories,
				ratings: ratings,
				page: window.currentPage,
				search: currentSearchTerm
			}
		);

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
					categories: categories,
					authors: authors,
					ratings: ratings,
					vid_only: videoOnly, // Pass video-only filter
					page: window.currentPage,
					posts_per_page: postsPerPage,
					show_excerpt: showExcerpt ? 'true' : 'false',
					show_author: showAuthor ? 'true' : 'false',
					show_date: showDate ? 'true' : 'false',
					show_category: showCategory ? 'true' : 'false',
					show_rating: showRating ? 'true' : 'false',
					excerpt_length: excerptLength,
					cache_buster: new Date().getTime() // Add cache buster timestamp
				},
				success: function (response) {
					// Update authors based on current filters
					if (response.data.filtered_authors && typeof window.updateAuthorsFilter === 'function') {
						window.updateAuthorsFilter( response.data.filtered_authors );
					}

					// Update categories based on current filters
					if (response.data.filtered_categories && typeof window.updateCategoriesFilter === 'function') {
						window.updateCategoriesFilter( response.data.filtered_categories );
					}

					// Update ratings based on current filters
					if (response.data.filtered_ratings && typeof window.updateRatingsFilter === 'function') {
						window.updateRatingsFilter( response.data.filtered_ratings );
					}
					$( '.mlmmc-articles-loading' ).fadeOut( 200 );
					$( '.mlmmc-articles-grid' )
					isLoading = false;

					if (response.success) {

						const $grid     = $( '.mlmmc-articles-grid' );
						const $loadMore = $( '#mlmmc-load-more' );

						// Check if there's already a "no articles found" message and remove it
						$grid.find( '.mlmmc-articles-no-results' ).remove();

						// Clear the grid content before repopulating it
						// if(append == true) {
						// $grid.empty();
						// }

						// Update grid content
						if (response.data.html) {
							if (append) {
								$grid.append( response.data.html );
							} else {
								$grid.html( response.data.html );
							}
						} else {
							$grid.html( '<div class="mlmmc-articles-no-results">No articles found.</div>' );
						}

						// Restore grid opacity
						$grid.css( 'opacity', '1' );

						// Update the articles counter
						if (response.data.found_posts !== undefined) {
							const count = parseInt( response.data.found_posts );
							const text  = count === 1 ? '1 article' : count + ' articles';
							$( '#mlmmc-total-count' ).text( text );
						}

						// Update load more button
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
						// Clear any previous content and show error
						$( '.mlmmc-articles-grid' ).empty().html( '<div class="mlmmc-articles-error">Error loading articles. Please try again.</div>' ).css( 'opacity', '1' );
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

})( jQuery );
