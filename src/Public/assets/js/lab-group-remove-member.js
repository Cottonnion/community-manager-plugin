jQuery(document).ready(function($) {
    const $groupManagement = $('.lab-group-management');
    const groupId = group_remove_member_data.group_id || $groupManagement.data('group-id');
    const ajaxUrl = group_remove_member_data.ajax_url;
    const nonce = group_remove_member_data.nonce;
    
    // Handle member removal
    $('.lab-ajax-remove-member').on('click', function(e) {
        e.preventDefault();
        
        const userId = $(this).data('user-id');
        const userName = $(this).data('user-name');
        
        Swal.fire({
            title: 'Remove Member',
            text: `Are you sure you want to remove ${userName} from this group?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, remove them',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading state
                Swal.fire({
                    title: 'Removing member...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                $.ajax({
                    url: ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'lab_group_remove_member',
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
                            $(`tr[data-user-id="${userId}"]`).fadeOut(300, function() {
                                $(this).remove();
                            });
                        } else {
                            Swal.fire({
                                title: 'Error!',
                                text: response.data || 'Error removing member',
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
    });
});