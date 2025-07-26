/**
 * Organization Access Admin JavaScript
 * 
 * Handles admin functionality for organization access requests
 */

(function($) {
    'use strict';

    // Initialize when DOM is ready
    $(document).ready(function() {
        new OrganizationAccessAdmin();
    });

    /**
     * Organization Access Admin Handler Class
     */
    function OrganizationAccessAdmin() {
        this.init();
    }

    OrganizationAccessAdmin.prototype = {
        /**
         * Initialize the handler
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event listeners
         */
        bindEvents: function() {
            // View details button
            $(document).on('click', '.view-details', this.handleViewDetails.bind(this));
            
            // User profile link
            $(document).on('click', '.user-profile-link', this.handleUserProfileClick.bind(this));
            
            // User groups link
            $(document).on('click', '.view-user-groups', this.handleUserGroupsClick.bind(this));
            
            // User courses link
            $(document).on('click', '.view-user-courses', this.handleUserCoursesClick.bind(this));
            
            // Approve button
            $(document).on('click', '.approve-request', this.handleApproveRequest.bind(this));
            
            // Reject button
            $(document).on('click', '.reject-request', this.handleRejectRequest.bind(this));
            
            // Action form submission
            $(document).on('submit', '#action-form', this.handleActionSubmit.bind(this));
            
            // Modal close handlers
            $(document).on('click', '.labgenz-modal-close', this.closeModal.bind(this));
            
            // Close modal when clicking outside
            $(document).on('click', '.labgenz-modal', function(e) {
                if (e.target === this) {
                    this.closeModal();
                }
            }.bind(this));
            
            // Bulk actions
            $(document).on('click', '#doaction', this.handleBulkAction.bind(this));
            
            // Select all checkbox
            $(document).on('change', '#cb-select-all-1', this.handleSelectAll.bind(this));
        },

        /**
         * Handle view details
         */
        handleViewDetails: function(e) {
            e.preventDefault();
            var userId = $(e.target).data('user-id');
            this.showRequestDetails(userId);
        },

        /**
         * Show request details modal
         */
        showRequestDetails: function(userId) {
            var $row = $('tr[data-user-id="' + userId + '"]');
            
            // Show loading state
            $('#request-details-content').html('<div class="loading-spinner">Loading request details...</div>');
            $('#request-details-modal').show();
            
            // Fetch complete request details via AJAX
            this.fetchRequestDetails(userId, function(data) {
                var detailsHtml = '<div class="request-details">';
                
                if (data && data.user && data.request) {
                    // User Information
                    detailsHtml += '<div class="detail-section">' +
                        '<h4>User Information</h4>' +
                        '<p><strong>Name:</strong> ' + data.user.display_name + '</p>' +
                        '<p><strong>Email:</strong> ' + data.user.user_email + '</p>' +
                        '<p><strong>Registered on website:</strong> ' + new Date(data.user.user_registered).toLocaleDateString() + '</p>' +
                        '</div>';
                    
                    // User Groups
                    if (data.groups && data.groups.length > 0) {
                        detailsHtml += '<div class="detail-section">' +
                            '<h4>BuddyBoss Groups</h4>';
                        
                        data.groups.forEach(function(group) {
                            detailsHtml += '<div class="group-detail-item">' +
                                '<strong>' + group.name + '</strong>' +
                                '<small> (' + group.member_count + ' members, ' + group.status + ')</small>';
                            
                            if (group.url) {
                                detailsHtml += '<br><a href="' + group.url + '" target="_blank">View Group</a>';
                            }
                            
                            detailsHtml += '</div>';
                        });
                        
                        detailsHtml += '</div>';
                    } else {
                        detailsHtml += '<div class="detail-section">' +
                            '<h4>BuddyBoss Groups</h4>' +
                            '<p class="no-data">User is not a member of any groups</p>' +
                            '</div>';
                    }
                    
                    // User Courses
                    if (data.courses && data.courses.length > 0) {
                        detailsHtml += '<div class="detail-section">' +
                            '<h4>User Courses</h4>';
                        
                        data.courses.forEach(function(course) {
                            detailsHtml += '<div class="course-detail-item">' +
                                '<strong>' + course.title + '</strong>';
                            
                            if (course.progress && course.total > 0) {
                                var percentage = Math.round((course.completed / course.total) * 100);
                                detailsHtml += '<small> (' + percentage + '% complete)</small>';
                            }
                            
                            if (course.url) {
                                detailsHtml += '<br><a href="' + course.url + '" target="_blank">View Course</a>';
                            }
                            
                            detailsHtml += '</div>';
                        });
                        
                        detailsHtml += '</div>';
                    } else {
                        detailsHtml += '<div class="detail-section">' +
                            '<h4>User Courses</h4>' +
                            '<p class="no-data">User is not enrolled in any courses</p>' +
                            '</div>';
                    }
                    
                    // Organization Information
                    detailsHtml += '<div class="detail-section">' +
                        '<h4>Organization Information</h4>' +
                        '<p><strong>Name:</strong> ' + data.request.organization_name + '</p>' +
                        '<p><strong>Type:</strong> ' + data.request.organization_type + '</p>' +
                        '<p><strong>Contact Email:</strong> ' + data.request.contact_email + '</p>';
                    
                    if (data.request.website) {
                        detailsHtml += '<p><strong>Website:</strong> <a href="' + data.request.website + '" target="_blank">' + data.request.website + '</a></p>';
                    }
                    
                    if (data.request.phone) {
                        detailsHtml += '<p><strong>Phone:</strong> ' + data.request.phone + '</p>';
                    }
                    
                    detailsHtml += '</div>';
                    
                    // Request Details
                    detailsHtml += '<div class="detail-section">' +
                        '<h4>Request Details</h4>' +
                        '<p><strong>Status:</strong> <span class="status-badge status-' + data.status + '">' + data.status_label + '</span></p>' +
                        '<p><strong>Request Submitted on:</strong> ' + new Date(data.request.requested_at).toLocaleDateString() + '</p>' +
                        '</div>';
                    
                    // Description and Justification
                    if (data.request.description) {
                        detailsHtml += '<div class="detail-section">' +
                            '<h4>Organization Description</h4>' +
                            '<p>' + data.request.description.replace(/\n/g, '<br>') + '</p>' +
                            '</div>';
                    }
                    
                    if (data.request.justification) {
                        detailsHtml += '<div class="detail-section">' +
                            '<h4>Justification</h4>' +
                            '<p>' + data.request.justification.replace(/\n/g, '<br>') + '</p>' +
                            '</div>';
                    }
                } else {
                    // Fallback to table data if AJAX fails
                    var userName = $row.find('.column-user strong').text();
                    var userEmail = $row.find('.column-user small').text();
                    var orgName = $row.find('.column-organization strong').text();
                    var orgEmail = $row.find('.column-organization small').text();
                    var orgType = $row.find('.column-type').text();
                    var status = $row.find('.column-status .status-badge').text();
                    var date = $row.find('.column-date').text();
                    
                    detailsHtml += '<div class="detail-section">' +
                        '<h4>User Information</h4>' +
                        '<p><strong>Name:</strong> ' + userName + '</p>' +
                        '<p><strong>Email:</strong> ' + userEmail + '</p>' +
                        '</div>' +
                        '<div class="detail-section">' +
                        '<h4>Organization Information</h4>' +
                        '<p><strong>Name:</strong> ' + orgName + '</p>' +
                        '<p><strong>Type:</strong> ' + orgType + '</p>' +
                        '<p><strong>Contact Email:</strong> ' + orgEmail + '</p>' +
                        '</div>' +
                        '<div class="detail-section">' +
                        '<h4>Request Details</h4>' +
                        '<p><strong>Status:</strong> ' + status + '</p>' +
                        '<p><strong>Submitted:</strong> ' + date + '</p>' +
                        '</div>';
                }
                
                detailsHtml += '</div>';
                
                $('#request-details-content').html(detailsHtml);
            });
        },

        /**
         * Fetch complete request details via AJAX
         */
        fetchRequestDetails: function(userId, callback) {
            $.ajax({
                url: labgenz_org_access_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'labgenz_get_request_details',
                    nonce: labgenz_org_access_admin.nonce,
                    user_id: userId
                },
                success: function(response) {
                    if (response.success) {
                        callback(response.data);
                    } else {
                        console.error('Failed to fetch request details:', response.data.message);
                        callback(null);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error fetching request details:', error);
                    callback(null);
                }
            });
        },

        /**
         * Handle approve request
         */
        handleApproveRequest: function(e) {
            e.preventDefault();
            
            var userId = $(e.target).data('user-id');
            var self = this;
            
            Swal.fire({
                title: 'Approve Request',
                text: labgenz_org_access_admin.strings.confirm_approve,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, approve it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    self.showActionModal(userId, 'approve', 'Approve Request');
                }
            });
        },

        /**
         * Handle reject request
         */
        handleRejectRequest: function(e) {
            e.preventDefault();
            
            var userId = $(e.target).data('user-id');
            var self = this;
            
            Swal.fire({
                title: 'Reject Request',
                text: labgenz_org_access_admin.strings.confirm_reject,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, reject it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    self.showActionModal(userId, 'reject', 'Reject Request');
                }
            });
        },

        /**
         * Show action modal
         */
        showActionModal: function(userId, action, title) {
            $('#action-modal-title').text(title);
            $('#action-user-id').val(userId);
            $('#action-type').val(action);
            $('#admin-note').val('');
            $('#action-modal').show();
            $('#admin-note').focus();
        },

        /**
         * Handle action form submission
         */
        handleActionSubmit: function(e) {
            e.preventDefault();
            
            var $form = $(e.target);
            var $submitButton = $form.find('button[type="submit"]');
            var originalText = $submitButton.text();
            
            // Disable submit button and show loading
            $submitButton.prop('disabled', true).text(labgenz_org_access_admin.strings.processing);
            
            var formData = {
                action: 'labgenz_process_org_access_request',
                nonce: labgenz_org_access_admin.nonce,
                user_id: $('#action-user-id').val(),
                action_type: $('#action-type').val(),
                admin_note: $('#admin-note').val()
            };

            $.ajax({
                url: labgenz_org_access_admin.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        this.showMessage('success', response.data.message);
                        this.closeModal();
                        this.updateRowStatus(formData.user_id, response.data.new_status);
                    } else {
                        this.showMessage('error', response.data.message);
                    }
                    $submitButton.prop('disabled', false).text(originalText);
                }.bind(this),
                error: function(xhr, status, error) {
                    this.showMessage('error', labgenz_org_access_admin.strings.error);
                    $submitButton.prop('disabled', false).text(originalText);
                }.bind(this)
            });
        },

        /**
         * Update row status after action
         */
        updateRowStatus: function(userId, newStatus) {
            var $row = $('tr[data-user-id="' + userId + '"]');
            var $statusBadge = $row.find('.status-badge');
            var $actions = $row.find('.column-actions');
            
            // Update status badge
            $statusBadge.removeClass('status-pending status-approved status-rejected');
            $statusBadge.addClass('status-' + newStatus);
            
            var statusText = newStatus === 'approved' ? 'Approved' : 'Rejected';
            $statusBadge.text(statusText);
            
            // Remove action buttons for non-pending requests
            if (newStatus !== 'pending') {
                $actions.find('.approve-request, .reject-request').remove();
            }
            
            // If we're on pending tab, remove the row
            if ($('.nav-tab-active').text().includes('Pending')) {
                $row.fadeOut(300, function() {
                    $(this).remove();
                });
            }
        },

        /**
         * Handle bulk actions
         */
        handleBulkAction: function(e) {
            e.preventDefault();
            
            var action = $('#bulk-action-selector-top').val();
            if (action === '-1') {
                Swal.fire({
                    title: 'No Action Selected',
                    text: 'Please select an action.',
                    icon: 'warning',
                    confirmButtonColor: '#0073aa'
                });
                return;
            }
            
            var selectedIds = [];
            $('input[name="user_ids[]"]:checked').each(function() {
                selectedIds.push($(this).val());
            });
            
            if (selectedIds.length === 0) {
                Swal.fire({
                    title: 'No Requests Selected',
                    text: 'Please select at least one request.',
                    icon: 'warning',
                    confirmButtonColor: '#0073aa'
                });
                return;
            }
            
            var self = this;
            var confirmMessage = action === 'approve' ? 
                'Are you sure you want to approve ' + selectedIds.length + ' request(s)?' :
                'Are you sure you want to reject ' + selectedIds.length + ' request(s)?';
            
            var actionColor = action === 'approve' ? '#28a745' : '#dc3545';
            var actionText = action === 'approve' ? 'Yes, approve them!' : 'Yes, reject them!';
            
            Swal.fire({
                title: 'Bulk Action Confirmation',
                text: confirmMessage,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: actionColor,
                cancelButtonColor: '#6c757d',
                confirmButtonText: actionText,
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    self.processBulkAction(action, selectedIds);
                }
            });
        },

        /**
         * Process bulk action
         */
        processBulkAction: function(action, userIds) {
            var processed = 0;
            var total = userIds.length;
            
            userIds.forEach(function(userId) {
                var formData = {
                    action: 'labgenz_process_org_access_request',
                    nonce: labgenz_org_access_admin.nonce,
                    user_id: userId,
                    action_type: action,
                    admin_note: ''
                };
                
                $.ajax({
                    url: labgenz_org_access_admin.ajax_url,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        processed++;
                        if (response.success) {
                            this.updateRowStatus(userId, response.data.new_status);
                        }
                        
                        if (processed === total) {
                            Swal.fire({
                                title: 'Bulk Action Complete',
                                text: 'Bulk action completed successfully.',
                                icon: 'success',
                                confirmButtonColor: '#28a745'
                            });
                            $('input[name="user_ids[]"]').prop('checked', false);
                            $('#cb-select-all-1').prop('checked', false);
                        }
                    }.bind(this),
                    error: function(xhr, status, error) {
                        processed++;
                        if (processed === total) {
                            Swal.fire({
                                title: 'Bulk Action Error',
                                text: 'Some actions failed. Please refresh and try again.',
                                icon: 'error',
                                confirmButtonColor: '#dc3545'
                            });
                        }
                    }.bind(this)
                });
            }.bind(this));
        },

        /**
         * Handle select all checkbox
         */
        handleSelectAll: function(e) {
            var isChecked = $(e.target).is(':checked');
            $('input[name="user_ids[]"]').prop('checked', isChecked);
        },

        /**
         * Close modal
         */
        closeModal: function() {
            $('.labgenz-modal').hide();
        },

        /**
         * Show admin message using SweetAlert2
         */
        showMessage: function(type, message) {
            var swalConfig = {
                text: message,
                confirmButtonColor: '#0073aa',
                timer: 5000,
                timerProgressBar: true,
                showCloseButton: true
            };
            
            if (type === 'error') {
                swalConfig.title = 'Error';
                swalConfig.icon = 'error';
                swalConfig.confirmButtonColor = '#dc3545';
            } else if (type === 'success') {
                swalConfig.title = 'Success';
                swalConfig.icon = 'success';
                swalConfig.confirmButtonColor = '#28a745';
            } else {
                swalConfig.title = 'Notice';
                swalConfig.icon = 'info';
            }
            
            Swal.fire(swalConfig);
        },

        /**
         * Handle user profile link click
         */
        handleUserProfileClick: function(e) {
            e.preventDefault();
            var userId = $(e.target).data('user-id');
            this.showUserProfileModal(userId);
        },

        /**
         * Handle user groups click
         */
        handleUserGroupsClick: function(e) {
            e.preventDefault();
            var $link = $(e.target);
            var userId = $link.data('user-id');
            var groupsData = $link.data('groups');
            
            if (groupsData && groupsData.length > 0) {
                this.showUserGroupsModal(groupsData);
            } else {
                Swal.fire({
                    title: 'No Groups Found',
                    text: 'This user is not a member of any groups.',
                    icon: 'info',
                    confirmButtonColor: '#007cba'
                });
            }
        },

        /**
         * Handle user courses click
         */
        handleUserCoursesClick: function(e) {
            e.preventDefault();
            var $link = $(e.target);
            var userId = $link.data('user-id');
            var coursesData = $link.data('courses');
            
            if (coursesData && coursesData.length > 0) {
                this.showUserCoursesModal(coursesData);
            } else {
                Swal.fire({
                    title: 'No Courses Found',
                    text: 'This user is not enrolled in any courses.',
                    icon: 'info',
                    confirmButtonColor: '#007cba'
                });
            }
        },

        /**
         * Show user groups modal
         */
        showUserGroupsModal: function(groupsData) {
            var groupsHtml = '';
            
            groupsData.forEach(function(group) {
                var statusClass = group.status === 'public' ? 'group-public' : 'group-private';
                var roleClass = 'role-' + group.user_role;
                var roleLabel = group.user_role.charAt(0).toUpperCase() + group.user_role.slice(1);
                
                groupsHtml += '<div class="group-modal-item ' + statusClass + '">' +
                    '<div class="group-header">' +
                        '<strong>' + group.name + '</strong>' +
                        '<span class="group-status">' + group.status + '</span>' +
                        '<span class="user-role ' + roleClass + '">' + roleLabel + '</span>' +
                    '</div>' +
                    '<div class="group-details">' +
                        '<small>' + group.member_count + ' members</small>';
                
                if (group.url) {
                    groupsHtml += '<a href="' + group.url + '" target="_blank" class="group-link">View Group</a>';
                }
                
                groupsHtml += '</div>' +
                '</div>';
            });
            
            Swal.fire({
                title: 'User Groups (' + groupsData.length + ')',
                html: '<div class="user-groups-modal">' + groupsHtml + '</div>',
                width: '600px',
                showCloseButton: true,
                showConfirmButton: false,
                customClass: {
                    popup: 'user-groups-modal-popup',
                    title: 'user-groups-title',
                    htmlContainer: 'user-groups-content'
                }
            });
        },

        /**
         * Show user courses modal
         */
        showUserCoursesModal: function(coursesData) {
            var coursesHtml = '';
            
            coursesData.forEach(function(course) {
                coursesHtml += '<div class="course-modal-item">' +
                    '<div class="course-header">' +
                        '<strong>' + course.title + '</strong>';
                
                if (course.progress && course.total > 0) {
                    var percentage = Math.round((course.completed / course.total) * 100);
                    coursesHtml += '<div class="course-progress">' +
                        '<div class="progress-bar">' +
                            '<div class="progress-fill" style="width: ' + percentage + '%"></div>' +
                        '</div>' +
                        '<span class="progress-text">' + percentage + '% complete</span>' +
                    '</div>';
                }
                
                coursesHtml += '</div>' +
                    '<div class="course-details">';
                
                if (course.status) {
                    coursesHtml += '<span class="course-status">Status: ' + course.status + '</span>';
                }
                
                if (course.url) {
                    coursesHtml += '<a href="' + course.url + '" target="_blank" class="course-link">View Course</a>';
                }
                
                coursesHtml += '</div>' +
                '</div>';
            });
            
            Swal.fire({
                title: 'User Courses (' + coursesData.length + ')',
                html: '<div class="user-courses-modal">' + coursesHtml + '</div>',
                width: '600px',
                showCloseButton: true,
                showConfirmButton: false,
                customClass: {
                    popup: 'user-courses-modal-popup',
                    title: 'user-courses-title',
                    htmlContainer: 'user-courses-content'
                }
            });
        },

        /**
         * Show user profile modal with links
         */
        showUserProfileModal: function(userId) {
            var self = this;
            
            // Show loading indicator
            Swal.fire({
                title: 'Loading User Profile...',
                text: 'Please wait while we fetch the user profile information.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Fetch user profile links
            $.ajax({
                url: labgenz_org_access_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'labgenz_get_user_profile_links',
                    nonce: labgenz_org_access_admin.nonce,
                    user_id: userId
                },
                success: function(response) {
                    if (response.success) {
                        self.displayUserProfileModal(response.data);
                    } else {
                        console.error('Failed to fetch user profile links:', response.data.message);
                        Swal.fire({
                            title: 'Error Loading Profile',
                            text: 'Failed to load user profile links.',
                            icon: 'error',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error fetching user profile links:', error);
                    Swal.fire({
                        title: 'Connection Error',
                        text: 'An error occurred while loading user profile links.',
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                }
            });
        },

        /**
         * Display user profile modal using SweetAlert2
         */
        displayUserProfileModal: function(userData) {
            var profileLinksHtml = '';
            
            // Build profile links HTML
            if (userData.profile_links && userData.profile_links.length > 0) {
                userData.profile_links.forEach(function(link) {
                    profileLinksHtml += '<div class="profile-link-item">' +
                        '<a href="' + link.url + '" target="' + link.target + '" class="profile-link">' +
                            '<span class="dashicons ' + link.icon + '"></span>' +
                            '<div class="profile-link-content">' +
                                '<strong>' + link.title + '</strong>' +
                                '<p>' + link.description + '</p>' +
                            '</div>' +
                        '</a>' +
                    '</div>';
                });
            } else {
                profileLinksHtml = '<p>No profile links available.</p>';
            }

            // Show SweetAlert2 modal
            Swal.fire({
                title: '<div class="user-profile-header">' +
                    '<img src="' + userData.user.avatar + '" alt="Avatar" class="user-avatar">' +
                    '<div class="user-info">' +
                        '<h3>' + userData.user.display_name + '</h3>' +
                        '<p>' + userData.user.user_email + '</p>' +
                    '</div>' +
                '</div>',
                html: '<div class="user-profile-links">' + profileLinksHtml + '</div>',
                width: '600px',
                showCloseButton: true,
                showConfirmButton: false,
                customClass: {
                    popup: 'user-profile-modal',
                    title: 'user-profile-title',
                    htmlContainer: 'user-profile-content'
                }
            });
        },
    };

})(jQuery);
