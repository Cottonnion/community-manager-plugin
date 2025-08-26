// remove the redirect filter from all purchase buttons
document.addEventListener('DOMContentLoaded', function() {
    // Select all anchor tags with the specific classes
    const buttons = document.querySelectorAll('a');
    
    buttons.forEach(function(button) {
        // Get current href
        let href = button.getAttribute('href');
        
        if (href) {
            // Create URL object to easily manipulate parameters
            const url = new URL(href, window.location.origin);
            
            // Remove the e-redirect parameter
            url.searchParams.delete('e-redirect');
            
            // Update the href with cleaned URL
            button.setAttribute('href', url.toString());
        }
    });
})