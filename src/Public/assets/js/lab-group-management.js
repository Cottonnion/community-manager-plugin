// Access localized data from WordPress
const labGroupManagement = lab_group_management_js_data || {};
const groupId = labGroupManagement.group_id || 0;
jQuery(document).ready(function ($) {
        
    // Helper function to display alerts
    function showAlert(message, type) {
        const icon = type === 'error' ? 'error' : 'success';
    
        Swal.fire({
            title: type === 'error' ? 'Error' : 'Success',
            text: message,
            icon: icon,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 5000,
            timerProgressBar: true
        });
    }
    
    // Helper function to validate email
    function isValidEmail(email) {
        // More permissive email validation that accepts most valid email formats
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        console.log(`Validating email: ${email}, result: ${emailPattern.test(email)}`);
        return emailPattern.test(email);
    }

    // Helper function to display the bulk preview
    function showBulkPreview(invitees) {
        const $bulkPreview = $('#lab-bulk-preview');
    
        if (!$bulkPreview.length) return;
    
        // Clear the preview area and make sure there's a content container
        $bulkPreview.empty();
        const $bulkPreviewContent = $('<div id="lab-bulk-preview-content"></div>');
        $bulkPreview.append($bulkPreviewContent);
    
        const groupId = labGroupManagement.group_id;
        const groupName = labGroupManagement.group_name || 'this group';
    
        // Calculate statistics
        const uniqueEmails = new Set(invitees.map(invitee => invitee.email)).size;
        let newUsers = 0;
        let existingUsers = 0;
        let alreadyMembers = 0;
        let alreadyInvited = 0;
        let duplicates = 0;
    
        invitees.forEach(invitee => {
            if (invitee.status === 'New user') {
                newUsers++;
            } else if (invitee.status === 'Existing user - will be invited') {
                existingUsers++;
            } else if (invitee.status === 'Already a member') {
                alreadyMembers++;
            } else if (invitee.status === 'Already invited') {
                alreadyInvited++;
            } else if (invitee.status === 'Duplicate entry') {
                duplicates++;
            }
        });
    
        let previewHtml = `
        <div class="lab-preview-summary">
            <p>Found <strong>${invitees.length}</strong> total entries with <strong>${uniqueEmails}</strong> unique email addresses.</p>
            <ul class="lab-stats-list" style="display: none !important;">
                <li><span class="lab-status-new">${newUsers}</span> new users will be created</li>
                <li><span class="lab-status-invite">${existingUsers}</span> existing users will be invited</li>
                <li><span class="lab-status-member">${alreadyMembers}</span> users are already members</li>
                <li><span class="lab-status-invited">${alreadyInvited}</span> users already invited</li>
                <li><span class="lab-status-duplicate">${duplicates}</span> duplicate entries found</li>
            </ul>
        </div>
        <div class="lab-table-responsive">
            <table class="lab-preview-table">
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Role</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
        invitees.forEach(invitee => {
            // Apply CSS class based on status
            let statusClass = '';
            if (invitee.status === 'New user') {
                statusClass = 'lab-status-new';
            } else if (invitee.status === 'Existing user - will be invited') {
                statusClass = 'lab-status-invite';
            } else if (invitee.status === 'Already a member') {
                statusClass = 'lab-status-member';
            } else if (invitee.status === 'Already invited') {
                statusClass = 'lab-status-invited';
            } else if (invitee.status === 'Duplicate entry') {
                statusClass = 'lab-status-duplicate';
            }
        
            // Format user info from AJAX response
            let userInfoHtml = '';
        
            // Debug the user data received from the server
            console.log('User data for', invitee.email, ':', invitee.userData);
        
            if (invitee.userData) {
                // Show email (always available)
                userInfoHtml += `<div><strong>Email:</strong> ${invitee.email}</div>`;
            
                // Display the status directly from response
                if (invitee.userData.response_status) {
                    userInfoHtml += `<div><strong>Server Status:</strong> ${invitee.userData.response_status}</div>`;
                }
            
                // Display user exists flag
                if (typeof invitee.userData.response_user_exists !== 'undefined') {
                    userInfoHtml += `<div><strong>User Exists:</strong> ${invitee.userData.response_user_exists ? 'Yes' : 'No'}</div>`;
                }
            
                // Display name if available
                if (invitee.userData.display_name) {
                    userInfoHtml += `<div><strong>Name:</strong> ${invitee.userData.display_name}</div>`;
                }
            
                // Display avatar if available
                if (invitee.userData.avatar) {
                    userInfoHtml += `<div class="user-avatar"><img src="${invitee.userData.avatar}" alt="Avatar" width="30" height="30"></div>`;
                }
            
                // Display group name if available
                if (invitee.userData.response_group_name) {
                    userInfoHtml += `<div><strong>Group:</strong> ${invitee.userData.response_group_name}</div>`;
                }
            }
        
            if (!userInfoHtml) {
                userInfoHtml = '-';
            }
        
            previewHtml += `
            <tr>
                <td>${invitee.email}</td>
                <td>${invitee.firstName || '-'}</td>
                <td>${invitee.lastName || '-'}</td>
                <td>${invitee.role}</td>
                <td class="${statusClass}">${invitee.status}</td>
            </tr>
        `;
        });
    
        previewHtml += `
                </tbody>
            </table>
        </div>
    `;
    
        $bulkPreviewContent.html(previewHtml);
    
        // Add "Send Invitations" button after the preview table if there are users to invite
        if (newUsers > 0 || existingUsers > 0) {
            const $sendButton = $('<button>')
                .attr('id', 'lab-send-bulk-invites')
                .addClass('button button-primary')
                .text('Send Invitations')
                .css({
                    'margin-top': '15px',
                    'display': 'block'
                });
            $bulkPreviewContent.append($sendButton);
        }
    
        $bulkPreview.show();
}
    
    // Tab functionality
    $('.threedinst-tab-btn').on('click', function () {
        const targetTab = $(this).data('tab');
        
        // Map tab values to actual IDs if they don't match
        const tabIdMap = {
            'members': 'members-tab',
            'progress': 'progress-tab',
            'settings': 'settings-tab'
        };
        
        // Update active state
        $('.threedinst-tab-btn').removeClass('active');
        $(this).addClass('active');
        
        // Show target tab
        $('.tab-pane').hide();
        const tabId = tabIdMap[targetTab] || (targetTab + '-tab');
        $('#' + tabId).show();
    });

    // Show single invite form
    $('#lab-show-invite-form').on('click', function () {
        $('#lab-bulk-invite-form').hide();
        $('#lab-invite-form').show().css({
            'max-height': '0',
            'opacity': '0'
        }).animate({
            'max-height': '500px',
            'opacity': '1'
        }, 300);
    });
    
    // Show bulk invite form
    $('#lab-bulk-invite').on('click', function () {
        $('#lab-invite-form').hide();
        $('#lab-bulk-invite-form').show().css({
            'max-height': '0',
            'opacity': '0'
        }).animate({
            'max-height': '1000px',
            'opacity': '1'
        }, 300);
    });

    // Process file for bulk invite
    $('#lab-process-bulk-invite').on('click', function () {
        const fileInput = $('#lab-csv-file')[0];
        
        if (!fileInput || fileInput.files.length === 0) {
            showAlert('Please select a file', 'error');
            return;
        }

        const file = fileInput.files[0];
        processFile(file);
    });
    
    // Process all files (CSV and Excel) using SheetJS
    function processFile(file) {
        const reader = new FileReader();
        
        reader.onload = function (e) {
            try {
                // Determine file type and parse accordingly
                let data;
                const fileName = file.name.toLowerCase();
                
                if (fileName.endsWith('.xlsx') || fileName.endsWith('.xls')) {
                    // Excel file
                    const arrayBuffer = e.target.result;
                    data = new Uint8Array(arrayBuffer);
                } else {
                    // CSV/TSV file - convert text to array buffer for SheetJS
                    const text = e.target.result;
                    data = text;
                }
                
                // Use SheetJS to parse both Excel and CSV
                const workbook = XLSX.read(data, { type: fileName.endsWith('.xlsx') || fileName.endsWith('.xls') ? 'array' : 'string' });
                
                // Get the first sheet
                const firstSheetName = workbook.SheetNames[0];
                const worksheet = workbook.Sheets[firstSheetName];
                
                // Convert to JSON
                const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1 });
                
                if (jsonData.length === 0) {
                    showAlert('The file appears to be empty', 'error');
                    return;
                }
                
                const invitees = [];
                let invalidEmails = [];
                
                // Determine if the first row is headers
                const hasHeaders = typeof jsonData[0][0] === 'string' &&
                    jsonData[0][0].toLowerCase().includes('email');
                
                // Start from index 1 to skip header if it exists
                const startIndex = hasHeaders ? 1 : 0;
                
                for (let i = startIndex; i < jsonData.length; i++) {
                    const row = jsonData[i];
                    if (!row || row.length === 0) continue;
                    
                    const email = row[0]?.toString().trim();
                    
                    // Basic email validation
                    if (!email) continue;
                    
                    if (!isValidEmail(email)) {
                        invalidEmails.push(email);
                        continue;
                    }
                    
                    invitees.push({
                        email: email,
                        firstName: row[1]?.toString().trim() || '',
                        lastName: row[2]?.toString().trim() || '',
                        role: row[3]?.toString().trim() || 'Member'
                    });
                }
                
                processInvitees(invitees, invalidEmails, jsonData.length, startIndex);
                
            } catch (error) {
                console.error('Error processing file:', error);
                showAlert('Error processing file. Please check the format.', 'error');
            }
        };
        
        reader.onerror = function () {
            showAlert('Error reading the file', 'error');
        };
        
        // Determine how to read the file based on type
        const fileName = file.name.toLowerCase();
        if (fileName.endsWith('.xlsx') || fileName.endsWith('.xls')) {
            reader.readAsArrayBuffer(file);
        } else {
            reader.readAsText(file);
        }
    }
    
    // Common function to process invitees from any file type
    function processInvitees(invitees, invalidEmails, totalRows, startIndex) {
        // Track emails for duplicate detection but keep all entries
        const emailMap = {};
        const processedInvitees = [];
        
        // Get the group ID for member checks
        const groupId = labGroupManagement.group_id;
        
        // Show loading indicator
        const loadingIndicator = $('<div>').addClass('lab-loading-indicator')
            .html('<span class="spinner is-active"></span> Processing emails...');
        
        $('#lab-bulk-preview').empty().append(loadingIndicator).show();
        
        // Get the nonce from localized data
        const nonce = labGroupManagement.nonce || '';
        
        // Process each invitee and check their status
        let pendingChecks = invitees.length;
        
        if (invitees.length === 0) {
            if (totalRows > startIndex && invalidEmails.length > 0) {
                const invalidMsg = invalidEmails.length > 3
                    ? `${invalidEmails.slice(0, 3).join(', ')}... and ${invalidEmails.length - 3} more`
                    : invalidEmails.join(', ');
                    
                showAlert(`No valid email addresses found in the file. Invalid emails detected: ${invalidMsg}`, 'error');
            } else {
                showAlert('No valid email addresses found in the file', 'error');
            }
            return;
        }
        
        // Process each invitee
        $.each(invitees, function (index, invitee) {
            // Mark duplicates first
            if (emailMap[invitee.email]) {
                processedInvitees.push({
                    ...invitee,
                    status: 'Duplicate entry'
                });
                pendingChecks--;
                
                if (pendingChecks === 0) {
                    showBulkPreview(processedInvitees);
                }
                return true; // Continue to next item
            }
            
            // Mark this email as seen
            emailMap[invitee.email] = true;
            
            // Check user status via AJAX
            $.ajax({
                url: labGroupManagement.ajax_url,
                type: 'POST',
                data: {
                    action: 'lab_group_search_user',
                    email: invitee.email,
                    group_id: groupId,
                    nonce: nonce
                },
                success: function (response) {
                    let status;
                    let userData = {};
                    
                    // Debug the AJAX response
                    console.log('AJAX Response for', invitee.email, ':', response);
                        
                    if (response.success) {
                        const responseData = response.data;
                            
                        // Store the original user data
                        userData = responseData.user_data || {};
                            
                        // Add email directly to user data for display
                        userData.email = invitee.email;
                            
                        // Add direct access to all response data properties
                        Object.keys(responseData).forEach(key => {
                            if (key !== 'user_data') {
                                userData['response_' + key] = responseData[key];
                            }
                        });
                            
                        // Determine status based on the response
                        if (responseData.status === 'already_member') {
                            status = 'Already a member';
                        } else if (responseData.status === 'pending_invitation') {
                            status = 'Already invited';
                        } else if (responseData.status === 'can_invite') {
                            status = 'Existing user - will be invited';
                        } else if (responseData.status === 'user_not_exists') {
                            status = 'New user';
                        } else {
                            status = 'Unknown';
                        }
                        
                        // Add response data for display
                        if (responseData.group_name) {
                            userData.group_name = responseData.group_name;
                        }
                    } else {
                        status = 'Error checking status';
                    }
                    
                    processedInvitees.push({
                        ...invitee,
                        status: status,
                        userData: userData
                    });
                    
                    pendingChecks--;
                    
                    if (pendingChecks === 0) {
                        // Sort to maintain original order
                        processedInvitees.sort((a, b) => invitees.indexOf(a) - invitees.indexOf(b));
                        showBulkPreview(processedInvitees);
                    }
                },
                error: function () {
                    processedInvitees.push({
                        ...invitee,
                        status: 'Error checking status',
                        userData: {}
                    });
                    
                    pendingChecks--;
                    
                    if (pendingChecks === 0) {
                        processedInvitees.sort((a, b) => invitees.indexOf(a) - invitees.indexOf(b));
                        showBulkPreview(processedInvitees);
                    }
                }
            });
        });
    }

    // Send bulk invitations
    $(document).on('click', '#lab-send-bulk-invites', function () {
        // Get group ID from localized data
        const groupId = labGroupManagement.group_id;
        const groupName = labGroupManagement.group_name || 'this group';
        
        // Get all invitable rows from the table
        const $rows = $('#lab-bulk-preview-content .lab-preview-table tbody tr');
        const inviteesData = [];
        
        $rows.each(function () {
            const $row = $(this);
            const status = $row.find('td:nth-child(5)').text().trim();
            
            // Only process new users and existing users who can be invited
            if (status === 'New user' || status === 'Existing user - will be invited') {
                inviteesData.push({
                    email: $row.find('td:nth-child(1)').text().trim(),
                    firstName: $row.find('td:nth-child(2)').text().trim(),
                    lastName: $row.find('td:nth-child(3)').text().trim(),
                    role: $row.find('td:nth-child(4)').text().trim(),
                    status: status,
                    $row: $row // Store reference to the row for updating status
                });
            }
        });
        
        const totalInvites = inviteesData.length;
        let processedCount = 0;
        let successCount = 0;
        let failedCount = 0;
        
        if (totalInvites === 0) {
            showAlert('No invitable users found', 'error');
            return;
        }
        
        // Confirm before sending using SweetAlert
        Swal.fire({
            title: 'Confirm Bulk Invitations',
            html: `You're about to send <strong>${totalInvites}</strong> invitations to <strong>${groupName}</strong>.<br><br>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, send invitations',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#8B4513',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                // Disable button and show progress
                const $button = $(this);
                $button.prop('disabled', true).text(`Sending invitations (0/${totalInvites})...`);
                
                showAlert(`Processing ${totalInvites} invitations to ${groupName}...`, 'success');
            
                // Process invitations one by one
                function processNextInvitation(index) {
                    if (index >= inviteesData.length) {
                        // All invitations processed
                        showAlert(`Processed all invitations: ${successCount} successful, ${failedCount} failed. Reloading page...`, 'success');
                        
                        // Reload the page after a short delay so user can see the message
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                        
                        return;
                    }
                    
                    const invitee = inviteesData[index];
                    const $currentRow = invitee.$row;
                    
                    // Highlight the current row
                    $('.lab-preview-table tbody tr').removeClass('processing');
                    $currentRow.addClass('processing');
                    
                    // Update button text with progress
                    $button.text(`Sending invitations (${processedCount + 1}/${totalInvites})...`);
                    
                    // Send invitation via AJAX
                    $.ajax({
                        url: labGroupManagement.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'lab_group_invite_user',
                            email: invitee.email,
                            first_name: invitee.firstName !== '-' ? invitee.firstName : '',
                            last_name: invitee.lastName !== '-' ? invitee.lastName : '',
                            is_organizer: invitee.role.toLowerCase() === 'organizer' ? 1 : 0,
                            group_id: groupId,
                            nonce: labGroupManagement.nonce
                        },
                        success: function (response) {
                            processedCount++;
                            
                            // Update row status based on response
                            if (response.success) {
                                successCount++;
                                $currentRow.find('td:nth-child(5)').removeClass().addClass('lab-status-invited').text('Invitation sent');
                            } else {
                                failedCount++;
                                $currentRow.find('td:nth-child(5)').removeClass().addClass('lab-status-error').text('Failed: ' + (response.data.message || 'Unknown error'));
                            }
                            
                            // Process next invitation after a short delay
                            setTimeout(() => {
                                processNextInvitation(index + 1);
                            }, 200); // 200ms delay between invitations
                        },
                        error: function () {
                            processedCount++;
                            failedCount++;
                            
                            // Update row status
                            $currentRow.find('td:nth-child(5)').removeClass().addClass('lab-status-error').text('Failed: Server error');
                            
                            // Continue with next invitation after a short delay
                            setTimeout(() => {
                                processNextInvitation(index + 1);
                            }, 200);
                        }
                    });
                }
                
                // Start processing invitations
                processNextInvitation(0);
            }
        });
        
        // Update search functionality to include invited users
        const $searchInput = $('#lab-email-search');
        if ($searchInput.length) {
            const $rows = $('#lab-members-table tbody tr');
            let debounce;

            // Create no results message element
            const $noResultsMsg = $('<div>')
                .addClass('notice info')
                .hide()
                .html('<p>No members or invitations found matching your search.</p>')
                .insertAfter('.lab-members-table');

            $searchInput.on('input', function () {
                clearTimeout(debounce);
                debounce = setTimeout(() => {
                    const searchTerm = $searchInput.val().toLowerCase().trim();
                    let hasResults = false;
                
                    $rows.each(function () {
                        const $row = $(this);
                        const username = $row.find('td:nth-child(2)').text().toLowerCase();
                        const displayName = $row.find('td:nth-child(3)').text().toLowerCase();
                        const status = $row.find('td:nth-child(6)').text().toLowerCase();
                    
                        const isMatch = username.includes(searchTerm) ||
                            displayName.includes(searchTerm) ||
                            status.includes(searchTerm);
                    
                        $row.toggle(isMatch);
                        if (isMatch) hasResults = true;
                    });

                    // Show/hide no results message
                    $noResultsMsg.toggle(!hasResults && searchTerm !== '');
                }, 150);
            });
        }

        // Responsive table enhancements
        const $tableContainer = $('.lab-table-responsive');
        if ($tableContainer.length) {
            // Add indication when table is scrollable
            const indicateScrollability = function () {
                $tableContainer.toggleClass('is-scrollable',
                    $tableContainer[0].scrollWidth > $tableContainer[0].clientWidth);
            };

            // Initialize and listen for window resize
            indicateScrollability();
            $(window).on('resize', indicateScrollability);

            // Show/hide scroll indicators based on scroll position
            $tableContainer.on('scroll', function () {
                const isScrolled = $(this).scrollLeft() > 0;
                $(this).toggleClass('is-scrolled', isScrolled);
            
                const isAtEnd = Math.abs(this.scrollWidth - this.clientWidth - this.scrollLeft) < 2;
                $(this).toggleClass('at-scroll-end', isAtEnd);
            });
        }
    });
});
