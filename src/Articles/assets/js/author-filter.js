/**
 * Author filter script
 */
(function($) {
    'use strict';
    
    // Selected authors array
    let selectedAuthors = [];
    let availableAuthors = [];
    
    /**
     * Initialize the author filter
     */
    function init() {
        console.log('Author filter initialized');
        
        // Toggle dropdown
        $('#mlmmc-author-filter-toggle').on('click', function() {
            $('#mlmmc-author-dropdown').toggle();
            $(this).toggleClass('active');
            $('.chevron-icon', this).toggleClass('flip');
        });
        
        // Make the update function globally available
        window.updateAuthorsFilter = updateAuthorsFilter;
        
        // Close dropdown when clicking outside
        $(document).on('click', function(event) {
            if (!$(event.target).closest('.mlmmc-filter-dropdown').length) {
                $('#mlmmc-author-dropdown').hide();
                $('#mlmmc-author-filter-toggle').removeClass('active');
                $('.chevron-icon', '#mlmmc-author-filter-toggle').removeClass('flip');
            }
        });
        
        // Author search
        $('#mlmmc-author-search').on('input', function() {
            const searchTerm = $(this).val().toLowerCase().trim();
            filterAuthorOptions(searchTerm);
        });
        
        // Clear all button
        $('#mlmmc-clear-authors').on('click', function() {
            clearSelectedAuthors();
        });
        
        // Apply filter button
        $('#mlmmc-apply-filter').on('click', function() {
            $('#mlmmc-author-dropdown').hide();
            $('#mlmmc-author-filter-toggle').removeClass('active');
            $('.chevron-icon', '#mlmmc-author-filter-toggle').removeClass('flip');
            
            // Update global variable for other scripts
            window.selectedAuthors = selectedAuthors;
            
            // Reset page and perform search
            if (typeof window.currentPage !== 'undefined') {
                window.currentPage = 1;
            }
            
            if (typeof window.performSearch === 'function') {
                window.performSearch();
            }
        });
        
        // Remove author tag
        $(document).on('click', '.remove-author', function() {
            const authorToRemove = $(this).data('author');
            removeAuthor(authorToRemove);
        });
        
        // Load authors
        loadAuthors();
    }
    
    /**
     * Sort authors alphabetically by name
     * 
     * @param {Array} authors List of author objects with name
     * @returns {Array} Sorted list of authors
     */
    function sortAuthorsByName(authors) {
        return authors.sort((a, b) => {
            const nameA = (a.name || a).toLowerCase();
            const nameB = (b.name || b).toLowerCase();
            return nameA.localeCompare(nameB);
        });
    }

    /**
     * Load available authors via AJAX
     */
    function loadAuthors() {
        $.ajax({
            url: mlmmcArticlesData.ajaxUrl,
            type: 'POST',
            data: {
                action: mlmmcArticlesData.authorsAction,
                nonce: mlmmcArticlesData.nonce
            },
            success: function(response) {
                if (response.success && response.data && response.data.authors) {
                    availableAuthors = sortAuthorsByName(response.data.authors);
                    renderAuthorOptions(availableAuthors);
                } else {
                    $('.mlmmc-author-options').html('<div class="mlmmc-no-authors">No authors found.</div>');
                }
            },
            error: function() {
                $('.mlmmc-author-options').html('<div class="mlmmc-error">Failed to load authors. Please try again.</div>');
            }
        });
    }
    
    /**
     * Update the authors list based on filtered authors from AJAX response
     * 
     * @param {Array} filteredAuthors Array of authors with counts
     */
    function updateAuthorsFilter(filteredAuthors) {
        if (!filteredAuthors || !filteredAuthors.length) {
            // If no authors match the current filter, reload all authors
            loadAuthors();
            return;
        }
        
        // Save the currently selected authors for later
        const selectedAuthorNames = selectedAuthors.map(author => author);
        
        // Update available authors
        availableAuthors = filteredAuthors;
        
        // Re-render the author options with the filtered list
        renderAuthorOptions(availableAuthors);
        
        // Keep only selected authors that are still available
        selectedAuthors = selectedAuthorNames.filter(authorName => {
            return filteredAuthors.some(author => author.name === authorName);
        });
        
        // Update the selected author tags
        updateSelectedAuthorsUI();
        
        // Update global variable
        window.selectedAuthors = selectedAuthors;
    }
    
    /**
     * Render author options in the dropdown
     * 
     * @param {Array} authors List of author objects with name and count
     */
    function renderAuthorOptions(authors) {
        if (!authors || authors.length === 0) {
            $('.mlmmc-author-options').html('<div class="mlmmc-no-authors">No authors found.</div>');
            return;
        }
        
        let html = '';
        authors.forEach(function(author) {
            const authorName = author.name || author;
            const authorCount = author.count || 0;
            const isSelected = selectedAuthors.includes(authorName);
            
            // Properly escape the author name for HTML attributes
            const escapedAuthorName = authorName.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
            
            html += `
                <div class="mlmmc-author-option ${isSelected ? 'selected' : ''} mlmmc-checkbox-option" data-author="${escapedAuthorName}">
                    <label>    
                        <input type="checkbox" value="${escapedAuthorName}">
                        ${authorName}
                        <span class="author-count">(${authorCount})</span>
                    </label>        
                </div>
            `;
            
            // Set checked state after HTML is inserted (safer approach)
            if (isSelected) {
                // We'll set this after the HTML is inserted
            }
        });
        
        $('.mlmmc-author-options').html(html);
        
        // Set checked state for selected authors after HTML insertion
        authors.forEach(function(author) {
            const authorName = author.name || author;
            const isSelected = selectedAuthors.includes(authorName);
            if (isSelected) {
                $(`.mlmmc-author-option input[value="${authorName.replace(/"/g, '&quot;').replace(/'/g, '&#39;')}"]`).prop('checked', true);
            }
        });
        
        // Add event listeners to checkboxes
        $('.mlmmc-author-option input').on('change', function() {
            const author = $(this).val();
            // Decode the escaped author name back to original
            const decodedAuthor = author.replace(/&quot;/g, '"').replace(/&#39;/g, "'");
            const $option = $(this).closest('.mlmmc-author-option');
            
            if ($(this).is(':checked')) {
                $option.addClass('selected');
                if (!selectedAuthors.includes(decodedAuthor)) {
                    selectedAuthors.push(decodedAuthor);
                    updateSelectedAuthorsUI();
                }
            } else {
                $option.removeClass('selected');
                selectedAuthors = selectedAuthors.filter(a => a !== decodedAuthor);
                updateSelectedAuthorsUI();
            }
        });
    }
    
    /**
     * Filter author options based on search term
     * 
     * @param {string} searchTerm The search term to filter by
     */
    function filterAuthorOptions(searchTerm) {
        if (!searchTerm) {
            renderAuthorOptions(availableAuthors);
            return;
        }
        
        const filteredAuthors = availableAuthors.filter(author => {
            const authorName = author.name || author;
            return authorName.toLowerCase().includes(searchTerm);
        });
        
        renderAuthorOptions(filteredAuthors);
    }
    
    /**
     * Update the selected authors UI
     */
    function updateSelectedAuthorsUI() {
        const $container = $('.mlmmc-selected-authors');
        
        if (selectedAuthors.length === 0) {
            $container.empty().hide();
            $('#mlmmc-author-filter-toggle span:first').text('show by Author');
            return;
        }
        
        let html = '';
        const displayLimit = 3;
        const authorsToShow = selectedAuthors.slice(0, displayLimit);
        
        authorsToShow.forEach(function(author) {
            html += `
                <div class="mlmmc-selected-author">
                    ${author}
                    <button type="button" class="remove-author" data-author="${author}">×</button>
                </div>
            `;
        });
        
        // Add a +X more indicator if there are more than the display limit
        if (selectedAuthors.length > displayLimit) {
            const extraCount = selectedAuthors.length - displayLimit;
            const hiddenAuthors = selectedAuthors.slice(displayLimit).join(', ');
            html += `
                <div class="mlmmc-selected-author mlmmc-more-indicator" data-hidden-authors="${hiddenAuthors}">
                    +${extraCount} more
                </div>
            `;
        }
        
        $container.html(html).show();
        $('#mlmmc-author-filter-toggle span:first').text(`${selectedAuthors.length} author(s)`);
        
        // Update global variable for other scripts
        window.selectedAuthors = selectedAuthors;
    }
    
    /**
     * Remove an author from the selection
     * 
     * @param {string} author The author to remove
     */
    function removeAuthor(author) {
        selectedAuthors = selectedAuthors.filter(a => a !== author);
        updateSelectedAuthorsUI();
        
        // Update checkbox in dropdown
        $(`.mlmmc-author-option input[value="${author}"]`).prop('checked', false);
        $(`.mlmmc-author-option[data-author="${author}"]`).removeClass('selected');
        
        // Reset page and perform search
        if (typeof window.currentPage !== 'undefined') {
            window.currentPage = 1;
        }
        
        if (typeof window.performSearch === 'function') {
            window.performSearch();
        }
    }
    
    /**
     * Clear all selected authors
     */
    function clearSelectedAuthors() {
        selectedAuthors = [];
        $('.mlmmc-author-option input').prop('checked', false);
        $('.mlmmc-author-option').removeClass('selected');
        updateSelectedAuthorsUI();
    }
    
    // Initialize when document is ready
    $(document).ready(init);
    
})(jQuery);
