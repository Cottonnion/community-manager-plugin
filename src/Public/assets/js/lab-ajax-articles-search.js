jQuery(document).ready(function($) {
    let originalContent = $('#articles-ajax-content').html();
    let searchTimeout;
    let currentSearchTerm = '';
    let selectedAuthors = [];
    let authors = [];
    
    // Initialize author filter
    initializeAuthorFilter();
    
    // Search input handler
    $('#articles-ajax-search input').on('input', function() {
        clearTimeout(searchTimeout);
        let searchTerm = $(this).val().trim();
        currentSearchTerm = searchTerm;
        
        searchTimeout = setTimeout(function() {
            if (searchTerm === '' && selectedAuthors.length === 0) {
                $('#articles-ajax-content').html(originalContent);
            } else {
                performSearch(searchTerm, 1, selectedAuthors);
            }
        }, 500);
    });
    
    // Initialize author filter UI
    function initializeAuthorFilter() {
        // Custom dropdown HTML
        const authorFilterHtml = `
            <div id="author-filter-container" style="width:100%;max-width:400px;margin:0 auto;position:relative;">
                <button id="author-filter-toggle" type="button" style="
                    width: 100%;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    padding: 12px 16px;
                    background: #fff;
                    border: 1px solid #bbb;
                    border-radius: 8px;
                    font-size: 15px;
                    color: #222;
                    cursor: pointer;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
                    transition: all 0.2s;
                    justify-content: space-between;
                ">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <i class="bb-icon-filter" style="font-size: 16px; color: #667eea;"></i>
                        <span id="filter-button-text">Filter by author</span>
                    </div>
                    <i class="chevron-icon" style="
                        font-size: 12px;
                        color: #6b7280;
                        transition: transform 0.2s;
                    ">▼</i>
                </button>
                
                <div id="author-dropdown" style="
                    position: absolute;
                    top: 100%;
                    left: 0;
                    right: 0;
                    background: #fff;
                    border: 1px solid #d1d5db;
                    border-radius: 8px;
                    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
                    z-index: 1000;
                    margin-top: 4px;
                    display: none;
                    max-height: 300px;
                    overflow: hidden;
                ">
                    <div style="padding: 12px;">
                        <input type="text" id="author-search" placeholder="Search authors..." style="
                            width: 100%;
                            padding: 8px 12px;
                            border: 1px solid #d1d5db;
                            border-radius: 6px;
                            font-size: 14px;
                            outline: none;
                            transition: border 0.2s;
                        ">
                    </div>
                    
                    <div id="author-options" style="
                        max-height: 200px;
                        overflow-y: auto;
                        border-top: 1px solid #e5e7eb;
                    ">
                        <div style="padding: 20px; text-align: center; color: #6b7280;">
                            Loading authors...
                        </div>
                    </div>
                    
                    <div style="
                        padding: 8px 12px;
                        border-top: 1px solid #e5e7eb;
                        background: #f9fafb;
                        display: flex;
                        gap: 8px;
                    ">
                        <button id="clear-authors" type="button" style="
                            padding: 6px 12px;
                            background: #fff;
                            border: 1px solid #d1d5db;
                            border-radius: 4px;
                            font-size: 12px;
                            color: #6b7280;
                            cursor: pointer;
                            transition: all 0.2s;
                        ">Clear All</button>
                        <button id="apply-filter" type="button" style="
                            padding: 6px 12px;
                            background: #667eea;
                            border: 1px solid #667eea;
                            border-radius: 4px;
                            font-size: 12px;
                            color: #fff;
                            cursor: pointer;
                            transition: all 0.2s;
                        ">Apply Filter</button>
                    </div>
                </div>
                
                <div id="selected-authors" style="
                    margin-top: 8px;
                    display: flex;
                    flex-wrap: wrap;
                    gap: 6px;
                    min-height: 0;
                "></div>
            </div>
        `;
        
        // Render inside #article-author-filtering
        $('#author-filter-sidebar').remove();
        if ($('#article-author-filtering').length) {
            $('#article-author-filtering').css({position:'relative'}).html(`
                <div id="author-filter-sidebar">
                    ${authorFilterHtml}
                </div>
            `);
        }
        
        // Load authors
        loadAuthors();
        // Add styles
        addCustomStyles();
        // Initialize event handlers
        initializeEventHandlers();
    }

    // Initialize event handlers
    function initializeEventHandlers() {
        // Toggle dropdown
        $(document).on('click', '#author-filter-toggle', function(e) {
            e.stopPropagation();
            $('#author-dropdown').toggle();
            $('.chevron-icon').toggleClass('rotated');
        });
        
        // Close dropdown when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#author-filter-container').length) {
                $('#author-dropdown').hide();
                $('.chevron-icon').removeClass('rotated');
            }
        });
        
        // Author search
        $(document).on('input', '#author-search', function() {
            const searchTerm = $(this).val().toLowerCase();
            filterAuthorOptions(searchTerm);
        });
        
        // Author selection
        $(document).on('click', '.author-option', function() {
            const author = $(this).data('author');
            const isSelected = $(this).hasClass('selected');
            
            if (isSelected) {
                // Remove from selection
                selectedAuthors = selectedAuthors.filter(a => a !== author);
                $(this).removeClass('selected');
            } else {
                // Add to selection
                selectedAuthors.push(author);
                $(this).addClass('selected');
            }
            
            updateSelectedAuthors();
        });
        
        // Clear all authors
        $(document).on('click', '#clear-authors', function() {
            selectedAuthors = [];
            $('.author-option').removeClass('selected');
            updateSelectedAuthors();
        });
        
        // Apply filter
        $(document).on('click', '#apply-filter', function() {
            $('#author-dropdown').hide();
            $('.chevron-icon').removeClass('rotated');
            
            if (currentSearchTerm || selectedAuthors.length > 0) {
                performSearch(currentSearchTerm, 1, selectedAuthors);
            } else {
                $('#articles-ajax-content').html(originalContent);
            }
        });
        
        // Remove selected author tag
        $(document).on('click', '.remove-author', function() {
            const author = $(this).data('author');
            selectedAuthors = selectedAuthors.filter(a => a !== author);
            $(`.author-option[data-author="${author}"]`).removeClass('selected');
            updateSelectedAuthors();
            
            // Automatically trigger search with remaining authors
            if (currentSearchTerm || selectedAuthors.length > 0) {
                performSearch(currentSearchTerm, 1, selectedAuthors);
            } else {
                // If no search term and no authors selected, show original content
                $('#articles-ajax-content').html(originalContent);
            }
        });
    }

    // Filter author options based on search
    function filterAuthorOptions(searchTerm) {
        $('.author-option').each(function() {
            const authorName = $(this).text().toLowerCase();
            if (authorName.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    }

    // Update selected authors display
    function updateSelectedAuthors() {
        const selectedContainer = $('#selected-authors');
        
        if (selectedAuthors.length === 0) {
            selectedContainer.empty();
            $('#filter-button-text').text('Filter by author');
            return;
        }
        
        // Update button text
        $('#filter-button-text').text(`${selectedAuthors.length} author${selectedAuthors.length > 1 ? 's' : ''} selected`);
        
        // Show selected author tags
        const tagsHtml = selectedAuthors.map(author => `
            <span class="author-tag" style="
                display: inline-flex;
                align-items: center;
                gap: 4px;
                padding: 4px 8px;
                background: #667eea;
                color: #fff;
                border-radius: 4px;
                font-size: 12px;
                font-weight: 500;
            ">
                ${author}
                <button class="remove-author" data-author="${author}" style="
                    background: none;
                    border: none;
                    color: #fff;
                    cursor: pointer;
                    padding: 0;
                    margin-left: 2px;
                    font-size: 14px;
                    line-height: 1;
                ">&times;</button>
            </span>
        `).join('');
        
        selectedContainer.html(tagsHtml);
    }

    // Render author options
    function renderAuthorOptions() {
        const optionsHtml = authors.map(author => `
            <div class="author-option" data-author="${author}" style="
                padding: 10px 16px;
                cursor: pointer;
                transition: all 0.2s;
                border-left: 3px solid transparent;
                display: flex;
                align-items: center;
                gap: 8px;
            ">
                <div class="checkbox" style="
                    width: 16px;
                    height: 16px;
                    border: 2px solid #d1d5db;
                    border-radius: 3px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    transition: all 0.2s;
                ">
                    <span style="
                        color: #fff;
                        font-size: 10px;
                        font-weight: bold;
                        opacity: 0;
                        transition: opacity 0.2s;
                    ">✓</span>
                </div>
                <span>${author}</span>
            </div>
        `).join('');
        
        $('#author-options').html(optionsHtml);
    }
    
    // Load authors from server
    function loadAuthors() {
        $.ajax({
            url: labgenz_ajax_articles_search_data.ajax_url,
            type: 'POST',
            data: {
                action: 'get_mlmmc_authors',
                nonce: labgenz_ajax_articles_search_data.nonce
            },
            success: function(response) {
                if (response.success && response.data.authors) {
                    authors = response.data.authors;
                    renderAuthorOptions();
                } else {
                    $('#author-options').html('<div style="padding: 20px; text-align: center; color: #ef4444;">No authors found</div>');
                }
            },
            error: function() {
                $('#author-options').html('<div style="padding: 20px; text-align: center; color: #ef4444;">Failed to load authors</div>');
            }
        });
    }
    
    // Add custom styles
    function addCustomStyles() {
        const styles = `
            <style>
                #author-filter-toggle:hover {
                    border-color: #667eea;
                    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
                }
                
                #author-filter-toggle:focus {
                    outline: none;
                    border-color: #667eea;
                    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
                }
                
                .chevron-icon.rotated {
                    transform: rotate(180deg);
                }
                
                #author-search:focus {
                    border-color: #667eea;
                    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
                }
                
                .author-option:hover {
                    background: #f3f4f6;
                    border-left-color: #667eea;
                }
                
                .author-option.selected {
                    background: #eef2ff;
                    border-left-color: #667eea;
                }
                
                .author-option.selected .checkbox {
                    background: #667eea;
                    border-color: #667eea;
                }
                
                .author-option.selected .checkbox span {
                    opacity: 1;
                }
                
                #clear-authors:hover {
                    background: #f3f4f6;
                    border-color: #9ca3af;
                }
                
                #apply-filter:hover {
                    background: #5a67d8;
                    border-color: #5a67d8;
                }
                
                .author-tag .remove-author:hover {
                    background: rgba(255, 255, 255, 0.2);
                    border-radius: 50%;
                }
                
                /* Custom scrollbar for author options */
                #author-options::-webkit-scrollbar {
                    width: 6px;
                }
                
                #author-options::-webkit-scrollbar-track {
                    background: #f1f5f9;
                }
                
                #author-options::-webkit-scrollbar-thumb {
                    background: #cbd5e1;
                    border-radius: 3px;
                }
                
                #author-options::-webkit-scrollbar-thumb:hover {
                    background: #94a3b8;
                }
            </style>
        `;
        $('head').append(styles);
    }
    
    // Enhanced search function with multiple author filter
    function performSearch(searchTerm, page = 1, authors = []) {
        $('#articles-ajax-content').html(`
            <div class="loading" style="
                text-align: center;
                padding: 60px 40px;
                background: #ffffff;
                border-radius: 16px;
                box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
                margin-bottom: 24px;
            ">
                <div style="
                    display: inline-block;
                    width: 48px;
                    height: 48px;
                    border: 3px solid #f3f4f6;
                    border-top: 3px solid #667eea;
                    border-radius: 50%;
                    animation: spin 1s linear infinite;
                    margin-bottom: 20px;
                "></div>
                <div style="
                    color: #374151;
                    font-size: 18px;
                    font-weight: 600;
                    margin-bottom: 8px;
                ">Searching Articles</div>
                <div style="
                    color: #6b7280;
                    font-size: 14px;
                ">Please wait while we find the best results...</div>
            </div>
        `);
        
        $.ajax({
            url: labgenz_ajax_articles_search_data.ajax_url,
            type: 'POST',
            data: {
                action: 'search_mlmmc_articles',
                search_term: searchTerm,
                authors: authors,
                page: page,
                nonce: labgenz_ajax_articles_search_data.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#articles-ajax-content').html(response.data.content);
                } else {
                    $('#articles-ajax-content').html(`
                        <div style="
                            text-align: center;
                            padding: 60px 40px;
                            background: #fef2f2;
                            border: 1px solid #fecaca;
                            border-radius: 16px;
                            color: #dc2626;
                            font-size: 16px;
                            font-weight: 500;
                        ">
                            <div style="font-size: 48px; margin-bottom: 16px;">❌</div>
                            Search failed. Please try again.
                        </div>
                    `);
                }
            },
            error: function() {
                $('#articles-ajax-content').html(`
                    <div style="
                        text-align: center;
                        padding: 60px 40px;
                        background: #fef2f2;
                        border: 1px solid #fecaca;
                        border-radius: 16px;
                        color: #dc2626;
                        font-size: 16px;
                        font-weight: 500;
                    ">
                        <div style="font-size: 48px; margin-bottom: 16px;">🔌</div>
                        Connection failed. Please check your internet connection.
                    </div>
                `);
            }
        });
    }
    
    // Handle pagination clicks
    $(document).on('click', '.search-page-btn', function() {
        let page = $(this).data('page');
        if (currentSearchTerm || selectedAuthors.length > 0) {
            performSearch(currentSearchTerm, page, selectedAuthors);
        }
    });
});