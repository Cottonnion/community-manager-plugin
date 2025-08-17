jQuery(document).ready(function($) {
    // Access localized data from WordPress
    const labGroupInvite = lab_group_invite_data || {};
    const ajaxUrl = labGroupInvite.ajax_url;
    const nonce = labGroupInvite.nonce;
    let currentUserData = null;
    
    // Function to show alerts using SweetAlert2
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
    
    // Handle invite form submission
    $('#lab-invite-form').on('submit', function(e) {
        e.preventDefault();
        
        const userEmail = $('#lab-user-email').val().trim();
        
        if (!userEmail) {
            showAlert('Please enter an email address', 'error');
            return;
        }
        
        // Show loading state
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.text();
        submitBtn.prop('disabled', true).text('Searching...');
        
        // First search for the user to check their status
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'lab_group_search_user',
                email: userEmail,
                group_id: labGroupInvite.group_id,
                nonce: nonce
            },
            success: function(response) {
                submitBtn.prop('disabled', false).text(originalText);
                
                if (response.success) {
                    const data = response.data;
                    let title, text, showConfirm = false;
                    
                    switch(data.status) {
                        case 'already_member':
                            title = 'Already a Member';
                            text = `${data.user_data.display_name} is already a member of ${data.group_name} as ${data.user_data.current_role}.`;
                            break;
                            
                        case 'pending_invitation':
                            title = 'Invitation Pending';
                            text = `${data.user_data.display_name} already has a pending invitation to ${data.group_name}.`;
                            break;
                            
                        case 'can_invite':
                            title = 'Invite Existing User';
                            text = `Do you want to invite ${data.user_data.display_name} to join ${data.group_name}?`;
                            showConfirm = true;
                            break;
                            
                        case 'user_not_exists':
                            title = 'Create New User';
                            text = `User with email ${userEmail} doesn't exist. Do you want to create an account and invite them to ${data.group_name}?`;
                            showConfirm = true;
                            break;
                            
                        default:
                            title = 'Error';
                            text = 'Unknown user status.';
                    }
                    
                    if (showConfirm) {
                        Swal.fire({
                            title: title,
                            text: text,
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: 'var(--bb-primary-button-background-regular)',
                            cancelButtonColor: '#d33',
                            confirmButtonText: 'Yes, invite them',
                            cancelButtonText: 'Cancel',
                            input: 'checkbox',
                            inputValue: 0,
                            inputPlaceholder: 'Invite as organizer',
                            preConfirm: (isOrganizer) => {
                                return isOrganizer;
                            }
                        }).then((result) => {
                            if (result.isConfirmed) {
                                const isOrganizer = result.value;
                                
                                // Validate first name and last name if user doesn't exist
                                if (data.status === 'user_not_exists') {
                                    const firstName = $('#lab-user-firstname').val().trim();
                                    const lastName = $('#lab-user-lastname').val().trim();
                                    
                                    if (!firstName || !lastName) {
                                        Swal.fire({
                                            title: 'Missing Information',
                                            text: 'First name and last name are required to create a new user account.',
                                            icon: 'warning'
                                        });
                                        return;
                                    }
                                }
                                
                                // Send the invitation
                                sendInviteRequest(userEmail, isOrganizer);
                            }
                        });
                    } else {
                        Swal.fire({
                            title: title,
                            text: text,
                            icon: data.status === 'already_member' ? 'info' : 'warning'
                        });
                    }
                } else {
                    showAlert(response.data || 'Error checking user status', 'error');
                }
            },
            error: function() {
                submitBtn.prop('disabled', false).text(originalText);
                showAlert('Error connecting to server. Please try again.', 'error');
            }
        });
    });

    // Send the invitation after confirmation
    function sendInviteRequest(email, isOrganizer) {
        // Show loading state
        Swal.fire({
            title: 'Sending invitation...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: {
                action: 'lab_group_search_user',
                email: email,
                group_id: labGroupInvite.group_id,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    currentUserData = response.data;
                    showUserConfirmation(response.data);
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: response.data || 'Error searching for user',
                        icon: 'error'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    title: 'Error!',
                    text: 'Network error occurred. Please try again.',
                    icon: 'error'
                });
            }
        });
    }

    // We can now remove the old searchUser and showUserConfirmation functions since
    // they are integrated into the form submission handler

    // Send the actual invitation
    function sendInviteRequest(email, isOrganizer) {
        // Show loading state
        Swal.fire({
            title: 'Sending invitation...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Get first and last name values from the form
        const firstName = $('#lab-user-firstname').val().trim();
        const lastName = $('#lab-user-lastname').val().trim();
        
        const postData = {
            action: 'lab_group_invite_user',
            group_id: labGroupInvite.group_id,
            nonce: nonce,
            email: email,
            first_name: firstName,
            last_name: lastName,
            is_organizer: isOrganizer ? 1 : 0
        };
        
        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: postData,
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: response.data.message || 'Invitation sent successfully',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    
                    // Clear form fields
                    $('#lab-user-email').val('');
                    $('#lab-user-firstname').val('');
                    $('#lab-user-lastname').val('');
                    
                    // Refresh the page to show updated member list
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: response.data.message || 'Error sending invitation',
                        icon: 'error'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    title: 'Error!',
                    text: 'Network error occurred',
                    icon: 'error'
                });
            }
        });
    }

    // Handle cancel invitation
    $('.lab-ajax-cancel-invitation').on('click', function(e) {
        e.preventDefault();
        
        const userId = $(this).data('user-id');
        const groupId = $(this).data('group-id');
        
        Swal.fire({
            title: 'Cancel Invitation',
            text: 'Are you sure you want to cancel this invitation?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, cancel it',
            cancelButtonText: 'No, keep it'
        }).then((result) => {
            if (result.isConfirmed) {
                cancelInvitation(userId, groupId);
            }
        });
    });

    function cancelInvitation(userId, groupId) {
        // Show loading state
        Swal.fire({
            title: 'Cancelling invitation...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: {
                action: 'lab_group_cancel_invitation',
                group_id: groupId,
                user_id: userId,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: response.data,
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    // Remove the row from the table
                    $(`tr[data-user-id="${userId}"]`).fadeOut(300, function() {
                        $(this).remove();
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: response.data || 'Error cancelling invitation',
                        icon: 'error'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    title: 'Error!',
                    text: 'Network error occurred',
                    icon: 'error'
                });
            }
        });
    }
});
