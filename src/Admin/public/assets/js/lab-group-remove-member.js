// group-remove.js
// Handles AJAX member removal for group management

document.addEventListener('DOMContentLoaded', function () {
    const membersTable = document.getElementById('members-table');
    if (!membersTable) return;

    membersTable.addEventListener('click', function (e) {
        const target = e.target;
        if (target.classList.contains('ajax-remove-member')) {
            e.preventDefault();
            const userId = target.getAttribute('data-user-id');
            const userName = target.getAttribute('data-user-name');
            if (!userId) return;

            if (!confirm(`Are you sure you want to remove ${userName || 'this user'} from the group?`)) {
                return;
            }

            target.textContent = 'Removing...';
            target.disabled = true;

            fetch(labgenz_group_remove_data.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'labgenz_remove_group_member',
                    user_id: userId,
                    group_id: labgenz_group_remove_data.group_id,
                    _ajax_nonce: labgenz_group_remove_data.nonce
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the row from the table
                    const row = target.closest('tr');
                    if (row) row.remove();
                } else {
                    alert(data.data || 'Failed to remove member.');
                    target.textContent = 'Remove';
                    target.disabled = false;
                }
            })
            .catch(() => {
                alert('AJAX error. Please try again.');
                target.textContent = 'Remove';
                target.disabled = false;
            });
        }
    });
});
