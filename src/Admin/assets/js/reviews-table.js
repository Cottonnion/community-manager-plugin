/**
 * Article Reviews Table Sorting and Search Functionality
 */
(function($) {
    'use strict';

    // Keep track of sort order
    let sortState = {
        column: null,
        direction: 'asc'
    };

    // Initialize sorting and search functionality
    function initSortingAndSearch() {
        // Only run on the reviews admin page
        if (!$('.mlmmc-reviews-admin').length) {
            return;
        }

        // Add sorting icons and make headers clickable
        addSortingUI();
        
        // Add search input
        addSearchUI();
        
        // Initialize sorting functionality
        initSorting();
        
        // Initialize search functionality
        initSearch();
    }

    // Add sorting UI elements to the table headers
    function addSortingUI() {
        // Add sorting indicators to sortable columns
        const sortableColumns = [
            '.column-article',  // Article title
            '.column-user',     // Username
            '.column-rating'    // Rating
        ];
        
        // Add sort icons and cursor style to sortable column headers
        $('.reviews-table thead th').each(function() {
            if (sortableColumns.some(selector => $(this).is(selector))) {
                $(this).append('<span class="sort-icon">⇕</span>');
                $(this).css('cursor', 'pointer');
                $(this).addClass('sortable');
            }
        });
    }

    // Add search UI above the table
    function addSearchUI() {
        const searchBox = $(
            '<div class="tablenav-pages search-box">' +
                '<label for="reviews-search" class="screen-reader-text">Search Reviews:</label>' +
                '<input type="search" id="reviews-search" placeholder="Search articles, users..." class="reviews-search-input">' +
            '</div>'
        );
        
        // Add search box to the top tablenav area
        $('.mlmmc-reviews-admin .tablenav.top').prepend(searchBox);
    }

    // Initialize column sorting
    function initSorting() {
        $('.reviews-table thead th.sortable').on('click', function() {
            const $header = $(this);
            const columnIndex = $header.index();
            const isRatingColumn = $header.hasClass('column-rating');
            const isUserColumn = $header.hasClass('column-user');
            
            // Update sort state
            if (sortState.column === columnIndex) {
                // Same column clicked, toggle direction
                sortState.direction = sortState.direction === 'asc' ? 'desc' : 'asc';
            } else {
                // New column clicked, set as new sort column with default ascending direction
                sortState.column = columnIndex;
                sortState.direction = 'asc';
            }
            
            // Update sort icons
            updateSortIcons($header);
            
            // Sort the table rows
            sortTableRows(columnIndex, sortState.direction, isRatingColumn, isUserColumn);
        });
    }

    // Update the sort icons to indicate current sort direction
    function updateSortIcons($currentHeader) {
        // Reset all icons first
        $('.reviews-table thead th.sortable .sort-icon').text('⇕');
        
        // Update the current header's icon
        const $icon = $currentHeader.find('.sort-icon');
        if (sortState.direction === 'asc') {
            $icon.text('↑');
        } else {
            $icon.text('↓');
        }
    }

    // Sort table rows based on selected column
    function sortTableRows(columnIndex, direction, isRatingColumn, isUserColumn) {
        const $tbody = $('.reviews-table tbody');
        const rows = $tbody.find('tr').toArray();
        
        // Sort rows
        rows.sort(function(a, b) {
            let aValue, bValue;
            
            if (isRatingColumn) {
                // For rating column, count the active stars
                aValue = $(a).find('.column-rating .star.active').length;
                bValue = $(b).find('.column-rating .star.active').length;
            } else if (isUserColumn) {
                // For user column, get the username text
                aValue = $(a).find('.column-user .user-info strong').text().toLowerCase();
                bValue = $(b).find('.column-user .user-info strong').text().toLowerCase();
            } else {
                // For article column, get the title text
                aValue = $(a).find('td').eq(columnIndex - 1).find('a').text().toLowerCase();
                bValue = $(b).find('td').eq(columnIndex - 1).find('a').text().toLowerCase();
            }
            
            // Apply sorting direction
            if (direction === 'asc') {
                return aValue > bValue ? 1 : -1;
            } else {
                return aValue < bValue ? 1 : -1;
            }
        });
        
        // Reorder the DOM elements
        $tbody.append(rows);
        
        // Zebra striping
        $tbody.find('tr').removeClass('alternate');
        $tbody.find('tr:even').addClass('alternate');
    }

    // Initialize real-time search functionality
    function initSearch() {
        $('#reviews-search').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            
            if (searchTerm === '') {
                // Show all rows if search is cleared
                $('.reviews-table tbody tr').show();
                updateZebraStriping();
                return;
            }
            
            // Filter table rows
            $('.reviews-table tbody tr').each(function() {
                const $row = $(this);
                const articleTitle = $row.find('.column-article a').text().toLowerCase();
                const userName = $row.find('.column-user .user-info strong').text().toLowerCase();
                const userEmail = $row.find('.column-user .user-email').text().toLowerCase();
                
                // Show/hide rows based on search term
                if (articleTitle.includes(searchTerm) || 
                    userName.includes(searchTerm) || 
                    userEmail.includes(searchTerm)) {
                    $row.show();
                } else {
                    $row.hide();
                }
            });
            
            // Update zebra striping for visible rows
            updateZebraStriping();
            
            // Update the count in the tablenav
            updateVisibleCount();
        });
    }

    // Update zebra striping for visible rows
    function updateZebraStriping() {
        $('.reviews-table tbody tr:visible').removeClass('alternate');
        $('.reviews-table tbody tr:visible:even').addClass('alternate');
    }

    // Update the count of visible items
    function updateVisibleCount() {
        const visibleCount = $('.reviews-table tbody tr:visible').length;
        const totalCount = $('.reviews-table tbody tr').length;
        
        $('.displaying-num').text(visibleCount === 1 ? '1 item' : visibleCount + ' items');
        
        // If no visible items, show a "no results" message
        if (visibleCount === 0) {
            if ($('.no-search-results').length === 0) {
                const noResults = $('<div class="no-search-results">' +
                    '<p>' + labgenz_cm_reviews_table_js_data.noResults + '</p>' +
                '</div>');
                
                $('.reviews-table').after(noResults);
            }
        } else {
            $('.no-search-results').remove();
        }
    }

    // Initialize on document ready
    $(document).ready(function() {
        initSortingAndSearch();
    });

})(jQuery);
