// JS for Labgenz Community Management settings page
jQuery(document).ready(function($) {
    // Example: Save settings button handler
    $('#labgenz-cm-save-settings').on('click', function(e) {
        e.preventDefault();
        var data = {
            action: 'labgenz_cm_save_menu_settings',
            menu_page_name: $('#menu_page_name').val(),
            _nonce: labgenz_cm_settings_data.nonce
        };
        $.post(labgenz_cm_settings_data.ajaxurl, data, function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: labgenz_cm_settings_data.saveSettings || 'Settings saved!',
                    showConfirmButton: false,
                    timer: 1500
                });
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: labgenz_cm_settings_data.errorMessage || 'Error',
                    text: (response.data && response.data.message) ? response.data.message : 'An error occurred.'
                });
            }
        });
    });
});
