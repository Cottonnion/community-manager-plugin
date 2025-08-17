/**
 * Admin Subscriptions JavaScript
 * 
 * Handles the interaction for the admin subscriptions page
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        const adminSubscriptions = {
            init: function() {
                this.bindEvents();
                this.initializeFilters();
            },

            bindEvents: function() {
                // View subscription details
                $('.action-view').on('click', this.viewSubscription);
                
                // Edit subscription
                $('.action-edit').on('click', this.editSubscription);
                
                // Delete subscription
                $('.action-delete').on('click', this.deleteSubscription);
                
                // Close modal
                $('.subscription-modal-close').on('click', this.closeModal);
                
                // Pagination
                $('.admin-subscriptions-pagination button').on('click', this.handlePagination);
                
                // Filters
                $('#status-filter, #date-filter').on('change', this.applyFilters);
                $('#search-subscriptions').on('input', this.debounce(this.searchSubscriptions, 500));
                
                // Form submission
                $('#subscription-form').on('submit', this.handleFormSubmit);
            },

            initializeFilters: function() {
                // Set initial filter values based on URL parameters
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.has('status')) {
                    $('#status-filter').val(urlParams.get('status'));
                }
                if (urlParams.has('search')) {
                    $('#search-subscriptions').val(urlParams.get('search'));
                }
            },
            
            viewSubscription: function(e) {
                e.preventDefault();
                const subscriptionId = $(this).data('id');
                
                // Show loading state
                Swal.fire({
                    title: 'Loading...',
                    text: 'Fetching subscription details',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    willOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Fetch subscription details via AJAX
                $.ajax({
                    url: labgenz_admin_subscriptions.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'get_subscription_details',
                        nonce: labgenz_admin_subscriptions.nonce,
                        subscription_id: subscriptionId
                    },
                    success: function(response) {
                        Swal.close();
                        
                        if (response.success) {
                            // Display subscription details in a modal
                            const subscription = response.data.subscription;
                            
                            Swal.fire({
                                title: 'Subscription Details',
                                html: `
                                    <div class="subscription-detail-view">
                                        <p><strong>User:</strong> ${subscription.user_name}</p>
                                        <p><strong>Email:</strong> ${subscription.user_email}</p>
                                        <p><strong>Plan:</strong> ${subscription.plan_name}</p>
                                        <p><strong>Status:</strong> <span class="subscription-status status-${subscription.status.toLowerCase()}">${subscription.status}</span></p>
                                        <p><strong>Start Date:</strong> ${subscription.start_date}</p>
                                        <p><strong>Expiry Date:</strong> ${subscription.expiry_date}</p>
                                        <p><strong>Amount:</strong> ${subscription.amount}</p>
                                        <p><strong>Payment Method:</strong> ${subscription.payment_method}</p>
                                        <p><strong>Auto Renewal:</strong> ${subscription.auto_renewal ? 'Yes' : 'No'}</p>
                                    </div>
                                `,
                                showCloseButton: true,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.data.message || 'Failed to fetch subscription details'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'An error occurred while fetching subscription details'
                        });
                    }
                });
            },
            
            editSubscription: function(e) {
                e.preventDefault();
                const subscriptionId = $(this).data('id');
                
                // Show loading state
                Swal.fire({
                    title: 'Loading...',
                    text: 'Fetching subscription data',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    willOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Fetch subscription details via AJAX
                $.ajax({
                    url: labgenz_admin_subscriptions.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'get_subscription_details',
                        nonce: labgenz_admin_subscriptions.nonce,
                        subscription_id: subscriptionId
                    },
                    success: function(response) {
                        Swal.close();
                        
                        if (response.success) {
                            const subscription = response.data.subscription;
                            
                            Swal.fire({
                                title: 'Edit Subscription',
                                html: `
                                    <form id="edit-subscription-form">
                                        <input type="hidden" name="subscription_id" value="${subscriptionId}">
                                        
                                        <div class="subscription-form-row">
                                            <label for="status">Status</label>
                                            <select name="status" id="status" required>
                                                <option value="active" ${subscription.status === 'Active' ? 'selected' : ''}>Active</option>
                                                <option value="expired" ${subscription.status === 'Expired' ? 'selected' : ''}>Expired</option>
                                                <option value="pending" ${subscription.status === 'Pending' ? 'selected' : ''}>Pending</option>
                                                <option value="cancelled" ${subscription.status === 'Cancelled' ? 'selected' : ''}>Cancelled</option>
                                            </select>
                                        </div>
                                        
                                        <div class="subscription-form-row">
                                            <label for="expiry_date">Expiry Date</label>
                                            <input type="date" name="expiry_date" id="expiry_date" value="${subscription.expiry_date_raw}" required>
                                        </div>
                                        
                                        <div class="subscription-form-row">
                                            <label for="auto_renewal">Auto Renewal</label>
                                            <select name="auto_renewal" id="auto_renewal">
                                                <option value="1" ${subscription.auto_renewal ? 'selected' : ''}>Yes</option>
                                                <option value="0" ${!subscription.auto_renewal ? 'selected' : ''}>No</option>
                                            </select>
                                        </div>
                                        
                                        <div class="subscription-form-row">
                                            <label for="notes">Admin Notes</label>
                                            <textarea name="notes" id="notes" rows="3">${subscription.notes || ''}</textarea>
                                        </div>
                                    </form>
                                `,
                                showCancelButton: true,
                                confirmButtonText: 'Save Changes',
                                showLoaderOnConfirm: true,
                                preConfirm: () => {
                                    return new Promise((resolve) => {
                                        const formData = $('#edit-subscription-form').serialize();
                                        
                                        $.ajax({
                                            url: labgenz_admin_subscriptions.ajax_url,
                                            type: 'POST',
                                            data: {
                                                action: 'update_subscription',
                                                nonce: labgenz_admin_subscriptions.nonce,
                                                form_data: formData
                                            },
                                            success: function(updateResponse) {
                                                if (updateResponse.success) {
                                                    resolve({
                                                        success: true,
                                                        message: updateResponse.data.message
                                                    });
                                                } else {
                                                    Swal.showValidationMessage(
                                                        updateResponse.data.message || 'Failed to update subscription'
                                                    );
                                                    resolve({
                                                        success: false
                                                    });
                                                }
                                            },
                                            error: function() {
                                                Swal.showValidationMessage(
                                                    'Server error occurred'
                                                );
                                                resolve({
                                                    success: false
                                                });
                                            }
                                        });
                                    });
                                }
                            }).then((result) => {
                                if (result.isConfirmed && result.value.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Success',
                                        text: result.value.message || 'Subscription updated successfully',
                                        confirmButtonText: 'OK'
                                    }).then(() => {
                                        // Reload the page to show updated data
                                        window.location.reload();
                                    });
                                }
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.data.message || 'Failed to fetch subscription details'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'An error occurred while fetching subscription details'
                        });
                    }
                });
            },
            
            deleteSubscription: function(e) {
                e.preventDefault();
                const subscriptionId = $(this).data('id');
                const userName = $(this).data('user');
                
                Swal.fire({
                    title: 'Confirm Deletion',
                    text: `Are you sure you want to delete the subscription for ${userName}? This action cannot be undone.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show loading state
                        Swal.fire({
                            title: 'Deleting...',
                            text: 'Processing your request',
                            allowOutsideClick: false,
                            showConfirmButton: false,
                            willOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        
                        // Send delete request via AJAX
                        $.ajax({
                            url: labgenz_admin_subscriptions.ajax_url,
                            type: 'POST',
                            data: {
                                action: 'delete_subscription',
                                nonce: labgenz_admin_subscriptions.nonce,
                                subscription_id: subscriptionId
                            },
                            success: function(response) {
                                Swal.close();
                                
                                if (response.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Deleted!',
                                        text: response.data.message || 'Subscription has been deleted successfully',
                                        confirmButtonText: 'OK'
                                    }).then(() => {
                                        // Remove the row from the table
                                        $(`tr[data-subscription="${subscriptionId}"]`).fadeOut(400, function() {
                                            $(this).remove();
                                            
                                            // Check if table is empty
                                            if ($('.admin-subscriptions-table tbody tr').length === 0) {
                                                $('.admin-subscriptions-table tbody').html(
                                                    '<tr><td colspan="7" class="subscription-empty-state">No subscriptions found</td></tr>'
                                                );
                                                $('.admin-subscriptions-pagination').hide();
                                            }
                                        });
                                    });
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Error',
                                        text: response.data.message || 'Failed to delete subscription'
                                    });
                                }
                            },
                            error: function() {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: 'An error occurred while deleting the subscription'
                                });
                            }
                        });
                    }
                });
            },
            
            closeModal: function() {
                $('.subscription-modal').hide();
            },
            
            handlePagination: function(e) {
                e.preventDefault();
                const page = $(this).data('page');
                
                // Update URL with the page parameter
                const urlParams = new URLSearchParams(window.location.search);
                urlParams.set('paged', page);
                
                window.location.href = `${window.location.pathname}?${urlParams.toString()}`;
            },
            
            applyFilters: function() {
                const statusFilter = $('#status-filter').val();
                const dateFilter = $('#date-filter').val();
                const searchQuery = $('#search-subscriptions').val();
                
                // Build URL with filter parameters
                const urlParams = new URLSearchParams(window.location.search);
                
                if (statusFilter) {
                    urlParams.set('status', statusFilter);
                } else {
                    urlParams.delete('status');
                }
                
                if (dateFilter) {
                    urlParams.set('date_range', dateFilter);
                } else {
                    urlParams.delete('date_range');
                }
                
                if (searchQuery) {
                    urlParams.set('search', searchQuery);
                } else {
                    urlParams.delete('search');
                }
                
                // Reset to first page when filters change
                urlParams.set('paged', 1);
                
                window.location.href = `${window.location.pathname}?${urlParams.toString()}`;
            },
            
            searchSubscriptions: function() {
                const query = $('#search-subscriptions').val().toLowerCase();

                // Filter table rows based on the search query
                $('.admin-subscriptions-table tbody tr').each(function() {
                    const rowText = $(this).text().toLowerCase();
                    if (rowText.includes(query)) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });

                // Update filtered count
                const visibleRows = $('.admin-subscriptions-table tbody tr:visible').length;
                $('#filtered-subscriptions').text(`Filtered: ${visibleRows}`);
            },
            
            handleFormSubmit: function(e) {
                e.preventDefault();
                const formData = $(this).serialize();
                
                // Show loading state
                Swal.fire({
                    title: 'Saving...',
                    text: 'Processing your request',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    willOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Send form data via AJAX
                $.ajax({
                    url: labgenz_admin_subscriptions.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'save_subscription',
                        nonce: labgenz_admin_subscriptions.nonce,
                        form_data: formData
                    },
                    success: function(response) {
                        Swal.close();
                        
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success',
                                text: response.data.message || 'Subscription saved successfully',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                // Reload the page to show updated data
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.data.message || 'Failed to save subscription'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'An error occurred while saving the subscription'
                        });
                    }
                });
            },
            
            // Utility function to debounce search input
            debounce: function(func, wait) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            }
        };
        
        // Initialize the admin subscriptions functionality
        adminSubscriptions.init();
    });
})(jQuery);
