jQuery(document).ready(function($) {
    // Tab switching functionality
    function switchTab(targetStep) {
        $('.step-tab').removeClass('active').filter('[data-step="' + targetStep + '"]').addClass('active');
        $('.tab-content').removeClass('active').filter('#' + targetStep + '-content').addClass('active');
    }

    $('.step-tab').on('click', function() {
        switchTab($(this).data('step'));
    });
    $('.next-step').on('click', function() {
        switchTab($(this).data('next'));
    });
    $('.prev-step').on('click', function() {
        switchTab($(this).data('prev'));
    });

    // Logo upload preview
    $('#organization-logo').on('change', function(e) {
        var file = e.target.files[0];
        if (file) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#logo-preview').html('<img src="' + e.target.result + '" alt="Logo" style="width: 100%; height: 100%; object-fit: cover; border-radius: 6px;">');
            };
            reader.readAsDataURL(file);
        }
    });

    // Pricing plan selection
    $('.select-plan-btn').on('click', function() {
        var plan = $(this).data('plan');
        $('.pricing-card').removeClass('selected');
        $(this).closest('.pricing-card').addClass('selected');
        $('#selected-plan').val(plan);
        $('#create-organization-btn').prop('disabled', false);
    });

    // AJAX Form submission
    $('#create-organization-form').on('submit', function(e) {
        e.preventDefault();
        $('.form-error, .success-message, .error-message').remove();
        var orgName = $('#organization-name').val().trim();
        var selectedPlan = $('#selected-plan').val();
        var hasErrors = false;
        if (!orgName) {
            showFieldError('organization-name', 'Please enter your organization name.');
            switchTab('details');
            hasErrors = true;
        }
        if (!selectedPlan) {
            showMessage('Please select a pricing plan.', 'error');
            switchTab('pricing');
            hasErrors = true;
        }
        if (hasErrors) return;
        $('#loading-overlay').addClass('show');
        var formData = new FormData(this);
        formData.append('action', 'create_organization_request');
        $.ajax({
            url: typeof lab_group_create_form_data !== 'undefined' ? lab_group_create_form_data.ajax_url : '',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(data) {
                $('#loading-overlay').removeClass('show');
                if (data.success) {
                    showMessage(data.data.message, 'success');
                    setTimeout(function() {
                        window.location.href = data.data.checkout_url;
                    }, 1500);
                } else {
                    showMessage(data.data || 'An error occurred. Please try again.', 'error');
                }
            },
            error: function(xhr, status, error) {
                $('#loading-overlay').removeClass('show');
                showMessage('An unexpected error occurred. Please try again.', 'error');
            }
        });
    });

    function showFieldError(fieldId, message) {
        var $field = $('#' + fieldId);
        var $errorEl = $('<div class="form-error"></div>').text(message);
        $field.parent().append($errorEl);
    }
    function showMessage(message, type) {
        var $messageEl = $('<div></div>').addClass(type === 'success' ? 'success-message' : 'error-message').text(message);
        var $form = $('#create-organization-form');
        $form.prepend($messageEl);
        $form[0].scrollIntoView({ behavior: 'smooth' });
    }
});