/**
 * Article Download PDF functionality - Fixed Version
 *
 * Uses html2pdf.js to generate PDF documents of articles with metadata.
 */
(function($) {
    'use strict';

    /**
     * Generate a PDF from article data
     * 
     * @param {Object} articleData The article data from the AJAX response
     */
    function generatePdf(articleData) {
        // Create container for PDF content
        const pdfContainer = $('<div class="article-pdf-container" style="width: 100% !important"></div>');
        
        // Add title
        pdfContainer.append(`<h1>${articleData.title}</h1>`);
        
        // Process and clean content
        let contentHtml = articleData.content;
        
        // Create temporary div for content processing
        const tempDiv = $('<div class="content-processor"></div>').html(contentHtml);
        
        // 1. Remove empty paragraphs and excessive breaks
        tempDiv.find('p:empty, br:first-child, p > br:first-child, p:first-child:empty').remove();
        
        // 2. Process content to ensure proper display
        tempDiv.find('*').each(function() {
            // Remove any inline styles that might affect spacing
            $(this).css({
                'margin-top': '',
                'margin-bottom': '',
                'padding-top': '',
                'padding-bottom': '',
            });
            
            // Fix heading styles to ensure proper display
            if (this.tagName.match(/^H[1-6]$/i)) {
                $(this).css({
                    'margin-top': '0.8em',
                    'margin-bottom': '0.5em',
                    'page-break-after': 'avoid'
                });
            }
        });
        
        // 3. Clean up any remaining issues
        let cleanedHtml = tempDiv.html()
            // Replace multiple newlines with single newline
            .replace(/\n\s*\n\s*\n/g, '\n\n')
            // Remove any excessive whitespace before paragraphs
            .replace(/^\s+<p/gm, '<p');
        
        // Create article content div with proper spacing and styling
        const contentDiv = $('<div class="article-content"></div>')
            .css({
                'line-height': '1.6',
                'font-size': '14px'
            })
            .html(cleanedHtml);
        
        pdfContainer.append(contentDiv);
        
        // Add horizontal rule before metadata
        pdfContainer.append('<hr>');
        
        // Add metadata at the bottom
        const metaContainer = $('<div class="article-meta"></div>');
        
        // Use ACF author if available, otherwise use default author
        metaContainer.append(`<p><strong>${labgenzArticleDownload.authorLabel || 'Author'}:</strong> ${articleData.acf_author || articleData.author}</p>`);
        metaContainer.append(`<p><strong>${labgenzArticleDownload.dateLabel || 'Date'}:</strong> ${articleData.date}</p>`);
        
        // Add rating information if available
        if (articleData.average_rating > 0) {
            const stars = '★'.repeat(Math.round(articleData.average_rating)) + '☆'.repeat(Math.max(0, 5 - Math.round(articleData.average_rating)));
            // metaContainer.append(`<p><strong>${labgenzArticleDownload.ratingLabel || 'Rating'}:</strong> ${articleData.average_rating}/5 (${stars})</p>`);
            metaContainer.append(`<p><strong>${labgenzArticleDownload.reviewsLabel || 'Reviews'}:</strong> ${articleData.rating_count}</p>`);
        }

        if( articleData.category ) {
            metaContainer.append(`<p><strong>${labgenzArticleDownload.categoryLabel || 'Category'}:</strong> ${articleData.category}</p>`);
        }
        
        pdfContainer.append(metaContainer);
        
        // Add footer with link back to article
        pdfContainer.append('<hr>');
        pdfContainer.append(`<div class="article-footer"><p>${labgenzArticleDownload.sourceLabel || 'Source'}: <a href="${articleData.permalink}">${articleData.permalink}</a></p></div>`);
        
        // Append to document temporarily (hidden)
        const tempContainer = $('<div style="display: none;"></div>').appendTo('body');
        tempContainer.append(pdfContainer);
        
        // PDF options
        const options = {
            margin: [20, 15], // Increased top margin
            filename: articleData.title.replace(/[^a-z0-9]/gi, '_').toLowerCase() + '.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { 
                scale: 2, 
                useCORS: true,
                letterRendering: true,
                scrollY: 0, // Prevent scrolling issues
                // windowWidth: 1200, // Set a consistent width
                // Remove any excessive top whitespace
                onclone: function(doc) {
                    const contentElement = doc.querySelector('.article-pdf-container');
                    if (contentElement) {
                        contentElement.style.paddingTop = '0';
                        contentElement.style.marginTop = '0';
                    }
                    // Remove body margins that cause whitespace
                    doc.body.style.margin = '0';
                    doc.body.style.padding = '0';
                }
            },
            jsPDF: { 
                unit: 'mm', 
                format: 'a4', 
                orientation: 'portrait',
                compress: true
            }
        };
        
        // Generate PDF
        html2pdf().set(options).from(pdfContainer[0]).save().then(() => {
            // Remove temporary container after PDF is generated
            tempContainer.remove();
        });
    }

    /**
     * Initialize PDF download functionality
     */
    function init() {
        // Click event for download buttons
        $(document).on('click', '.download-article-pdf', function(e) {
            e.preventDefault();
            
            const articleId = $(this).data('article-id');
            const $button = $(this);
            const originalText = $button.text();
            
            // Show loading state
            $button.text(labgenzArticleDownload.downloadingText);
            
            // Get article data via AJAX
            $.ajax({
                url: labgenzArticleDownload.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'get_article_data_for_pdf',
                    article_id: articleId,
                    nonce: labgenzArticleDownload.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        // Generate PDF with the article data
                        generatePdf(response.data);
                    } else {
                        alert(response.data?.message || 'Error retrieving article data');
                    }
                },
                error: function() {
                    alert('Network error occurred. Please try again.');
                },
                complete: function() {
                    // Restore button text
                    $button.text(originalText);
                }
            });
        });
    }

    // Initialize when document is ready
    $(document).ready(init);

})(jQuery);