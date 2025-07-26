# Helpers for GroupMembersHandler

Each helper class encapsulates the logic for a specific AJAX action or operation:
- RemoveMemberHelper
- InviteUserHelper
- AcceptInvitationHelper
- CancelInvitationHelper
- SearchUserHelper

Each class exposes a `handle($post_data)` method that performs the required logic.
