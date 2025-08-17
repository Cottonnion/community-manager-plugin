/**
 * Category filter script that matches the author filter functionality
 */
(function($) {
    'use strict';
    
    // Selected categories array
    let selectedCategories = [];
    
    /**
     * Initialize the category filter
     */
    function init() {
        console.log('Category filter initialized');
        
        // Fetch categories initially
        // fetchCategories();
        
        // Make the update function globally available
        window.updateCategoriesFilter = updateCategoriesFilter;
        
        // Toggle dropdown
        $('#mlmmc-category-filter-toggle').on('click', function() {
            $('#mlmmc-category-dropdown').toggle();
            $(this).toggleClass('active');
            $('.chevron-icon', this).toggleClass('flip');
        });
        
        // Close dropdown when clicking outside
        $(document).on('click', function(event) {
            if (!$(event.target).closest('.mlmmc-filter-dropdown').length) {
                $('#mlmmc-category-dropdown').hide();
                $('#mlmmc-category-filter-toggle').removeClass('active');
                $('.chevron-icon', '#mlmmc-category-filter-toggle').removeClass('flip');
            }
        });
        
        // Category search
        $('#mlmmc-category-search').on('input', function() {
            const searchTerm = $(this).val().toLowerCase().trim();
            filterCategoryOptions(searchTerm);
        });
        
        // Clear all button
        $('#mlmmc-clear-categories').on('click', function() {
            clearSelectedCategories();
        });
        
        // Apply filter button
        $('#mlmmc-apply-categories').on('click', function() {
            $('#mlmmc-category-dropdown').hide();
            $('#mlmmc-category-filter-toggle').removeClass('active');
            $('.chevron-icon', '#mlmmc-category-filter-toggle').removeClass('flip');
            
            // Update global category selection for search
            window.selectedCategories = selectedCategories;
            
            // Reset page and perform search
            if (typeof window.currentPage !== 'undefined') {
                window.currentPage = 1;
            }
            
            if (typeof window.performSearch === 'function') {
                window.performSearch();
            }
        });
        
        // Handle checkbox changes
        $(document).on('change', '.mlmmc-category-checkbox', function() {
            const category = $(this).val();

            if (category === 'all') {
                // If "All Categories" is checked, uncheck all other categories
                if ($(this).is(':checked')) {
                    $('.mlmmc-category-checkbox').not('[value="all"]').prop('checked', false);
                    selectedCategories = [];
                }
            } else {
                // If any category is checked, uncheck "All Categories"
                if ($(this).is(':checked')) {
                    $('.mlmmc-category-checkbox[value="all"]').prop('checked', false);

                    if (!selectedCategories.includes(category)) {
                        selectedCategories.push(category);
                    }
                } else {
                    // Remove from selected categories
                    selectedCategories = selectedCategories.filter(c => c !== category);

                    // If no categories selected, check "All Categories"
                    if (selectedCategories.length === 0) {
                        $('.mlmmc-category-checkbox[value="all"]').prop('checked', true);
                    }
                }
            }

            updateSelectedCategoriesUI();
        });
        
        // Remove category tag
        $(document).on('click', '.remove-category', function() {
            const categoryToRemove = $(this).data('category');
            removeCategory(categoryToRemove);
        });
        
        // Initialize with "All Categories" selected
        $('.mlmmc-checkbox-option input[value="all"]').prop('checked', true);
        updateSelectedCategoriesUI();
    }
    
    /**
     * Filter category options based on search term
     */
    function filterCategoryOptions(searchTerm) {
        if (!searchTerm) {
            $('.mlmmc-checkbox-option').show();
            return;
        }
        
        $('.mlmmc-checkbox-option').each(function() {
            const categoryText = $(this).text().toLowerCase();
            if (categoryText.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    }
    
    /**
     * Update the categories list based on filtered categories from AJAX response
     * 
     * @param {Array} filteredCategories Array of categories with counts
     */
    function updateCategoriesFilter(filteredCategories) {
        if (!filteredCategories || !filteredCategories.length) {
            // If no categories match the current filter, show all categories
            $('.mlmmc-checkbox-option').show();
            return;
        }
        
        // Save the currently selected categories for later
        const selectedCategoryNames = selectedCategories.slice();
        
        // Get all category names from the filtered list
        const filteredCategoryNames = filteredCategories.map(category => category.name);
        
        // Show/hide categories based on the filtered list
        $('.mlmmc-checkbox-option').each(function() {
            const categoryName = $(this).find('input').val();
            if (filteredCategoryNames.includes(categoryName)) {
                $(this).show();
                
                // Update the count if provided
                const countSpan = $(this).find('.category-count');
                if (countSpan.length) {
                    const categoryData = filteredCategories.find(c => c.name === categoryName);
                    if (categoryData && categoryData.count !== undefined) {
                        countSpan.text(`(${categoryData.count})`);
                    }
                }
            } else {
                $(this).hide();
            }
        });
        
        // Keep only selected categories that are still available
        selectedCategories = selectedCategoryNames.filter(categoryName => 
            filteredCategoryNames.includes(categoryName)
        );
        
        // Update the selected category tags
        updateSelectedCategoriesUI();
        
        // Update global variable
        window.selectedCategories = selectedCategories;
    }
    
    /**
     * Update the selected categories UI
     */
    function updateSelectedCategoriesUI() {
        const $container = $('.mlmmc-selected-categories');
        
        console.log('MLMMC Debug - Updating selected categories:', selectedCategories);
        
        if (selectedCategories.length === 0) {
            $container.empty().hide();
            $('#mlmmc-category-filter-toggle span:first').text('show by Category');
            return;
        }
        
        let html = '';
        const displayLimit = 3;
        const categoriesToShow = selectedCategories.slice(0, displayLimit);
        
        categoriesToShow.forEach(function(category) {
            html += `
                <div class="mlmmc-selected-category">
                    ${category}
                    <button type="button" class="remove-category" data-category="${category}">×</button>
                </div>
            `;
        });
        
        // Add a +X more indicator if there are more than the display limit
        if (selectedCategories.length > displayLimit) {
            const extraCount = selectedCategories.length - displayLimit;
            const hiddenCategories = selectedCategories.slice(displayLimit).join(', ');
            html += `
                <div class="mlmmc-selected-category mlmmc-more-indicator" data-hidden-authors="${hiddenCategories}">
                    +${extraCount} more
                </div>
            `;
        }
        
        $container.html(html).show();
        $('#mlmmc-category-filter-toggle span:first').text(`${selectedCategories.length} category(s)`);
    }
    
    /**
     * Remove a category from the selection
     */
    function removeCategory(category) {
        selectedCategories = selectedCategories.filter(c => c !== category);
        updateSelectedCategoriesUI();
        
        // Update checkbox in dropdown
        $(`.mlmmc-checkbox-option input[value="${category}"]`).prop('checked', false);
        
        // If no categories selected, check "All Categories"
        if (selectedCategories.length === 0) {
            $('.mlmmc-checkbox-option input[value="all"]').prop('checked', true);
        }
        
        // Update global selection and search
        window.selectedCategories = selectedCategories;
        
        if (typeof window.currentPage !== 'undefined') {
            window.currentPage = 1;
        }
        
        if (typeof window.performSearch === 'function') {
            window.performSearch();
        }
    }
    
    /**
     * Clear all selected categories
     */
    function clearSelectedCategories() {
        selectedCategories = [];
        $('.mlmmc-checkbox-option input').prop('checked', false);
        $('.mlmmc-checkbox-option input[value="all"]').prop('checked', true);
        updateSelectedCategoriesUI();
    }
    
    /**
     * Fetch categories via AJAX
     */
    function fetchCategories() {
        if (typeof mlmmcArticlesData === 'undefined') {
            console.error('mlmmcArticlesData is not defined');
            return;
        }
        
        $.ajax({
            url: mlmmcArticlesData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mlmmc_get_article_categories',
                nonce: mlmmcArticlesData.nonce
            },
            success: function(response) {
                if (response.success && response.data && response.data.categories) {
                    populateCategoryDropdown(response.data.categories);
                } else {
                    console.error('Failed to fetch categories:', response);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
            }
        });
    }
    
    /**
     * Populate category dropdown with fetched data
     */
    function populateCategoryDropdown(categories) {
        const $categoryOptions = $('.mlmmc-category-options');
        
        // Clear existing options except the "All Categories" option
        $categoryOptions.find('.mlmmc-checkbox-option:not(:first)').remove();
        
        // Add categories
        categories.forEach(function(category) {
            $categoryOptions.append(`
                <div class="mlmmc-checkbox-option">
                    <label>
                        <input type="checkbox" class="mlmmc-category-checkbox" value="${category.slug}">
                        ${category.name} (${category.count})
                    </label>
                </div>
            `);
        });
    }
    
    // Initialize when document is ready
    $(document).ready(init);
    
})(jQuery);
