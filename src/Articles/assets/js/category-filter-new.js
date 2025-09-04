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
        // Fetch categories initially
        fetchCategories();
        
        // Make the update function globally available
        window.updateCategoriesFilter = updateCategoriesFilter;
        
        // Sort existing categories in the DOM
        sortExistingCategoriesInDOM();
        
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
            $('.mlmmc-checkbox-option').show();
            return;
        }
        
        // Sort the categories alphabetically by name
        // filteredCategories = sortCategoriesByName(filteredCategories);
        
        // Save the currently selected categories for later
        const selectedCategoryNames = selectedCategories.slice();
        
        // Get all category slugs from the filtered list (create slugs if needed)
        const filteredCategorySlugs = filteredCategories.map(category => {
            return category.slug || category.name.toLowerCase().replace(/[\s\.\'\"\&\+\-\_\(\)]+/g, '-').replace(/\-+/g, '-');
        });
        
        // Show/hide categories based on the filtered list
        $('.mlmmc-checkbox-option').each(function() {
            const categoryValue = $(this).find('input').val();
            
            // Skip the "All Categories" option
            if (categoryValue === 'all') {
                $(this).show();
                return;
            }
            
            if (filteredCategorySlugs.includes(categoryValue)) {
                $(this).show();
                
                // Update the count if provided
                const categoryData = filteredCategories.find(c => {
                    const slug = c.slug || c.name.toLowerCase().replace(/[\s\.\'\"\&\+\-\_\(\)]+/g, '-').replace(/\-+/g, '-');
                    return slug === categoryValue;
                });
                
                if (categoryData && categoryData.count !== undefined) {
                    // Update both the label text and count
                    const $label = $(this).find('label');
                    const $input = $(this).find('input');
                    
                    // Reconstruct the label with updated count
                    $label.html(`
                        <input type="checkbox" class="mlmmc-category-checkbox" value="${categoryValue}" ${$input.is(':checked') ? 'checked' : ''}>
                        <span class="mlmmc-category-name">${categoryData.name}</span> 
                        <span class="mlmmc-category-count">(${categoryData.count})</span>
                    `);
                }
            } else {
                $(this).hide();
            }
        });
        
        // Keep only selected categories that are still available
        selectedCategories = selectedCategoryNames.filter(categoryName => 
            filteredCategorySlugs.includes(categoryName)
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
        
        if (selectedCategories.length === 0) {
            $container.empty().hide();
            $('#mlmmc-category-filter-toggle span:first').text('show by Category');
            return;
        }
        
        let html = '';
        const displayLimit = 3;
        const categoriesToShow = selectedCategories.slice(0, displayLimit);
        
        // Get a mapping of slugs to display names
        const slugToNameMap = {};
        $('.mlmmc-checkbox-option').each(function() {
            const $checkbox = $(this).find('input');
            const value = $checkbox.val();
            if (value !== 'all') {
                // Get the category name from the span or fallback to parsing the text
                const $nameSpan = $(this).find('.mlmmc-category-name');
                let labelText;
                if ($nameSpan.length) {
                    labelText = $nameSpan.text().trim();
                } else {
                    // Fallback: parse the text and remove count
                    labelText = $(this).text().trim().replace(/\(\d+\)$/, '').trim();
                }
                slugToNameMap[value] = labelText;
            }
        });
        
        categoriesToShow.forEach(function(categorySlug) {
            // Use the display name if available, otherwise use the slug
            const displayName = slugToNameMap[categorySlug] || categorySlug;
            
            html += `
                <div class="mlmmc-selected-category">
                    ${displayName}
                    <button type="button" class="remove-category" data-category="${categorySlug}">×</button>
                </div>
            `;
        });
        
        // Add a +X more indicator if there are more than the display limit
        if (selectedCategories.length > displayLimit) {
            const extraCount = selectedCategories.length - displayLimit;
            
            // Get display names for hidden categories
            const hiddenCategoriesDisplay = selectedCategories.slice(displayLimit).map(slug => 
                slugToNameMap[slug] || slug
            ).join(', ');
            
            html += `
                <div class="mlmmc-selected-category mlmmc-more-indicator" data-hidden-categories="${hiddenCategoriesDisplay}">
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
     * Update the visual state of the category filter based on a selected category slug
     * 
     * @param {string} categorySlug The selected category slug
     */
    function updateCategoryFilterVisual(categorySlug) {
        // Uncheck "All Categories"
        $('.mlmmc-category-checkbox[value="all"]').prop('checked', false);
        
        // Check the matching category
        $(`.mlmmc-category-checkbox[value="${categorySlug}"]`).prop('checked', true);
        
        // Add to selected categories if not already there
        if (!selectedCategories.includes(categorySlug)) {
            selectedCategories.push(categorySlug);
        }
        
        // Update UI
        updateSelectedCategoriesUI();
    }
    
    // Make functions available globally
    window.updateCategoryFilterVisual = updateCategoryFilterVisual;
    window.updateSelectedCategoriesUI = updateSelectedCategoriesUI;
    
    /**
     * Sort categories alphabetically by name
     * 
     * @param {Array} categories List of category objects with name
     * @returns {Array} Sorted list of categories
     */
    function sortCategoriesByName(categories) {
        return categories.sort((a, b) => {
            const nameA = (a.name || a).toLowerCase();
            const nameB = (b.name || b).toLowerCase();
            return nameA.localeCompare(nameB);
        });
    }
    
    /**
     * Sort existing categories in the DOM
     */
    function sortExistingCategoriesInDOM() {
        const $categoryOptions = $('.mlmmc-category-options');
        const $allCategoriesOption = $categoryOptions.find('.mlmmc-checkbox-option:first');
        const $categoryItems = $categoryOptions.find('.mlmmc-checkbox-option:not(:first)').detach().toArray();
        
        // Sort the detached category items
        $categoryItems.sort(function(a, b) {
            const textA = $(a).text().trim().toLowerCase();
            const textB = $(b).text().trim().toLowerCase();
            return textA.localeCompare(textB);
        });
        
        // Reattach the "All Categories" option first
        $categoryOptions.append($allCategoriesOption);
        
        // Reattach the sorted category items
        $.each($categoryItems, function(index, item) {
            $categoryOptions.append(item);
        });
    }

    /**
     * Fetch categories via AJAX
     */
    function fetchCategories() {
        if (typeof mlmmcArticlesData === 'undefined') {
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
                    const sortedCategories = sortCategoriesByName(response.data.categories);
                    populateCategoryDropdown(sortedCategories);
                } else {
                    // Handle error or empty response
                }
            },
            error: function(xhr, status, error) {
                // Handle AJAX error
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
        
        // Add categories with separate spans for name and count
        categories.forEach(function(category) {
            // Create a slug from the category name if one doesn't exist
            const categorySlug = category.slug || category.name.toLowerCase().replace(/[\s\.\'\"\&\+\-\_\(\)]+/g, '-').replace(/\-+/g, '-');
            
            $categoryOptions.append(`
                <div class="mlmmc-checkbox-option">
                    <label>
                        <input type="checkbox" class="mlmmc-category-checkbox" value="${categorySlug}">
                        <span class="mlmmc-category-name">${category.name}</span> 
                        <span class="mlmmc-category-count">(${category.count})</span>
                    </label>
                </div>
            `);
        });
    }
    
    // Initialize when document is ready
    $(document).ready(init);
    
})(jQuery);