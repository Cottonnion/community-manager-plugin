document.addEventListener('DOMContentLoaded', function () {
    const tabBtns = document.querySelectorAll('.threedinst-tab-btn');
    const tabPanes = document.querySelectorAll('.tab-pane');

    // Map data-tab values to actual IDs if they don't match
    const tabIdMap = {
        'members': 'members-tab',
        'progress': 'progress-tab', 
        'settings': 'settings-tab'  // Corrected mapping for settings 
    };

    // Initialize tabs - hide all except the first one
    tabPanes.forEach((pane, index) => {
        pane.style.display = index === 0 ? 'block' : 'none';
    });

    // Handle tab button clicks
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');
            
            // Remove active class from all buttons
            tabBtns.forEach(b => b.classList.remove('active'));
            
            // Add active class to clicked button
            this.classList.add('active');
            
            // Hide all tab panes
            tabPanes.forEach(pane => pane.style.display = 'none');
            
            // Get the actual ID to look for (using mapping if needed)
            const actualId = tabIdMap[targetTab] || (targetTab + '-tab');
            
            // Show the target tab pane
            const targetPane = document.getElementById(actualId);
            if (targetPane) {
                targetPane.style.display = 'block';
            }
        });
    });

    // Handle form submission with group selection
    const inviteForm = document.getElementById('invite-form');
    if (inviteForm) {
        inviteForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const targetGroupId = document.getElementById('target-group').value;
            const userEmail = document.getElementById('user-email').value;
            const firstName = document.getElementById('first-name').value;
            const lastName = document.getElementById('last-name').value;
            
            // You can now use targetGroupId in your AJAX call
            console.log('Inviting to group:', targetGroupId);
            console.log('User details:', { email: userEmail, firstName: firstName, lastName: lastName });
        });
    }

    // Update search functionality to include invited users
    const input = document.getElementById('email-search');
    if (input) {
        const rows = document.querySelectorAll('#members-table tbody tr');
        let debounce;

        // Create no results message element
        const noResultsMsg = document.createElement('div');
        noResultsMsg.className = 'notice info';
        noResultsMsg.style.display = 'none';
        noResultsMsg.innerHTML = '<p>No members or invitations found matching your search.</p>';
        document.querySelector('.members-table').insertAdjacentElement('afterend', noResultsMsg);

        input.addEventListener('input', function () {
            clearTimeout(debounce);
            debounce = setTimeout(() => {
                const searchTerm = input.value.toLowerCase().trim();
                let hasResults = false;
                
                rows.forEach(row => {
                    const username = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                    const displayName = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
                    const status = row.querySelector('td:nth-child(6)').textContent.toLowerCase();
                    
                    const isMatch = username.includes(searchTerm) || 
                                  displayName.includes(searchTerm) || 
                                  status.includes(searchTerm);
                    row.style.display = isMatch ? '' : 'none';
                    if (isMatch) hasResults = true;
                });

                // Show/hide no results message
                noResultsMsg.style.display = !hasResults && searchTerm !== '' ? 'block' : 'none';
            }, 150);
        });
    }
});
