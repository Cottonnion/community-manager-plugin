// JavaScript for group invitations
jQuery(document).ready(function($) {
    // Show/hide invite popup and fetch profile types
    $('#labgenz-show-invite-popup').on('click', function() {
        var nonce = $('.group-management').data('nonce');
        var $select = $('#labgenz-profile-type-select');
        $select.html('<option value="">Loading profile types...</option>');
        $.post({
            url: $('.group-management').data('ajax-url'),
            data: {
                action: 'labgenz_get_profile_types',
                nonce: nonce
            },
            success: function(response) {
                if (response.success && response.data.profile_types) {
                    $select.empty();
                    $select.append('<option value="">Select Profile Type</option>');
                    response.data.profile_types.forEach(function(type) {
                        $select.append('<option value="' + type + '">' + type.charAt(0).toUpperCase() + type.slice(1) + '</option>');
                    });
                } else {
                    $select.html('<option value="">No profile types found</option>');
                }
            },
            error: function() {
                $select.html('<option value="">Error loading profile types</option>');
            }
        });
        fetchInvitedMembers(); // Also fetch invited users when button is clicked
        $('#labgenz-invite-popup').fadeIn(150);
    });
    $('#labgenz-close-invite-popup').on('click', function() {
        $('#labgenz-invite-popup').fadeOut(150);
    });
    // Close popup on outside click
    $('#labgenz-invite-popup').on('click', function(e) {
        if (e.target === this) {
            $(this).fadeOut(150);
        }
    });

    // Handle invite form submission and fetch invited members after invite
    $('#labgenz-invite-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var data = {
            action: 'labgenz_invite_user',
            group_id: $('.group-management').data('group-id'),
            nonce: $('.group-management').data('nonce'),
            email: $form.find('[name="email"]').val(),
            first_name: $form.find('[name="first_name"]').val(),
            last_name: $form.find('[name="last_name"]').val(),
            profile_type: $form.find('[name="profile_type"]').val()
        };
        $form.find('button[type="submit"]').prop('disabled', true);
        $('#labgenz-invite-message').text('');
        $.post({
            url: $('.group-management').data('ajax-url'),
            data: data,
            success: function(response) {
                if (response.success) {
                    $('#labgenz-invite-message').text('Invitation sent!').css('color', 'green');
                    $form[0].reset();
                    fetchInvitedMembers();
                } else {
                    $('#labgenz-invite-message').text(response.data || 'Error').css('color', 'red');
                }
            },
            complete: function() {
                $form.find('button[type="submit"]').prop('disabled', false);
            }
        });
    });

    // Fetch invited members and update the list
    function fetchInvitedMembers() {
        var groupId = $('.group-management').data('group-id');
        // var nonce = typeof window.lab_group_invite_data !== 'undefined' ? window.lab_group_invite_data.nonce : '';
        var $list = $('#labgenz-invited-list-popup');
        $list.html('<li>Loading...</li>');
        $.post({
            url: typeof window.lab_group_invite_data !== 'undefined' ? window.lab_group_invite_data.ajax_url : $('.group-management').data('ajax-url'),
            data: {
                action: 'labgenz_get_invited_members',
                group_id: groupId,
                nonce: lab_group_invite_data.nonce
            },
            success: function(response) {
                $list.empty();
                if (response.success && response.data.members.length) {
                    response.data.members.forEach(function(member) {
                        $list.append('<li><strong>' + member.display_name + '</strong> (' + member.email + ') - ' + (member.profile_type ? member.profile_type : 'N/A') + '</li>');
                    });
                } else {
                    $list.append('<li>No invited users yet.</li>');
                }
            },
            error: function() {
                $list.html('<li>Error loading invited users</li>');
            }
        });
    }
});
