<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$data = get_group_management_data();
if ( ! $data['success'] ) {
    echo '<div class="notice error">' . esc_html( $data['error'] ) . '</div>';
    return;
}

if ( ! $data['is_organizer'] ) {
    echo '<div class="notice error">Access restricted. You must be a group organizer to view this page.</div>';
    return;
}
?>

<div id="members-tab" class="tab-pane active default-tab">
    <div id="alert-container"></div>
    
    <?php if ( isset($message) ) echo $message; ?>
    
    <h2>Group Members Management</h2>
    
    <div class="invite-section">
        <h3>Invite New Member</h3>
        <form id="invite-form" class="invite-form">
            <?php if ( count( $data['organizer_groups'] ) > 1 ) : ?>
                <div class="form-row">
                    <label for="target-group">Select Group:</label>
                    <select id="target-group" name="target_group" required>
                        <?php foreach ( $data['organizer_groups'] as $org_group ) : ?>
                            <option value="<?php echo esc_attr( $org_group['id'] ); ?>" 
                                    <?php selected( $org_group['id'], $data['group_id'] ); ?>>
                                <?php echo esc_html( $org_group['name'] ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else : ?>
                <input type="hidden" id="target-group" value="<?php echo esc_attr( $data['group_id'] ); ?>">
            <?php endif; ?>
            
            <div class="form-row">
                <input type="email" id="user-email" placeholder="Enter email address" required pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$" title="Please enter a valid email address">
                <input type="text" id="first-name" placeholder="First Name" required>
                <input type="text" id="last-name" placeholder="Last Name" required>
            </div>
            <div class="form-row">
                <button type="submit" class="invite-btn">Search & Invite Member</button>
            </div>
        </form>
    </div>

    <!-- Confirmation Modal -->
    <div id="user-modal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h3 id="modal-title">Confirm User</h3>
            </div>
            <div id="modal-content">
                <!-- Content will be dynamically inserted -->
            </div>
        </div>
    </div>

    <?php if ( ! empty( $data['members']['members'] ) || ! empty( $data['invited_users'] ) ) : ?>
        <h3>Group Members and Invitations</h3>
        
        <!-- Email Search Input -->
        <input type="text" id="email-search" placeholder="Search members and invitations" style="margin-bottom:10px; padding:5px; width:100%;">

        <table class="members-table" id="members-table">
            <thead>
                <tr>
                    <th>Avatar</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Joined Date</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php 
            // First display actual member
            foreach ( $data['members']['members'] as $member ) : 
                $is_admin = groups_is_user_admin( $member->ID, $data['group_id'] );
                $is_mod = groups_is_user_mod( $member->ID, $data['group_id'] );
                $role = $is_admin ? 'Organizer' : ( $is_mod ? 'Moderator' : 'Member' );
            ?>
                <tr data-user-id="<?php echo $member->ID; ?>" class="member-row" style="transition: background-color 0.2s ease; border-bottom: 1px solid #f0f0f0;">
                    <td style="font-size: 16px; padding: 12px 8px; text-align: center; vertical-align: middle;">
                        <?php echo bp_core_fetch_avatar( array( 
                            'item_id' => $member->ID, 
                            'type' => 'thumb', 
                            'width' => 40, 
                            'height' => 40 
                        ) ); ?>
                    </td>
                    <td style="font-size: 15px; padding: 12px 8px; font-weight: 500; color: #2c3e50; vertical-align: middle;">
                        <?php echo bp_core_get_userlink( $member->ID ); ?>
                    </td>
                    <td style="font-size: 14px; padding: 12px 8px; color: #7f8c8d; vertical-align: middle; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                        <?php echo esc_html( $member->user_email ); ?>
                    </td>
                    <td style="font-size: 13px; padding: 12px 8px; color: #95a5a6; vertical-align: middle; white-space: nowrap;">
                        <?php echo date( 'M j, Y', strtotime( $member->date_modified ) ); ?>
                    </td>
                    <td style="font-size: 14px; padding: 12px 8px; vertical-align: middle;">
                        <strong style="color: #34495e; font-weight: 600;"><?php echo $role; ?></strong>
                    </td>
                    <td style="font-size: 14px; padding: 12px 8px; vertical-align: middle;">
                        <span class="status-badge status-active" style="background: #27ae60; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">Active</span>
                    </td>
                    <td style="font-size: 14px; padding: 12px 8px; vertical-align: middle; text-align: center;">
                        <?php if ( $member->ID !== $data['current_user_id'] && ! $is_admin ): ?>
                            <a href="javascript:void(0)" 
                               class="remove-link ajax-remove-member"
                               data-user-id="<?php echo $member->ID; ?>"
                               data-user-name="<?php echo esc_attr( bp_core_get_user_displayname( $member->ID ) ); ?>"
                               style="color: #e74c3c; text-decoration: none; font-weight: 500; padding: 6px 12px; border-radius: 4px; transition: all 0.2s ease; border: 1px solid #e74c3c;"
                               onmouseover="this.style.backgroundColor='#e74c3c'; this.style.color='white';"
                               onmouseout="this.style.backgroundColor='transparent'; this.style.color='#e74c3c';">
                                Remove
                            </a>
                        <?php else: ?>
                            <?php if ( $member->ID === $data['current_user_id'] ): ?>
                                <em style="color: #3498db; font-style: normal; font-weight: 500;">You</em>
                            <?php else: ?>
                                <em style="color: #95a5a6; font-style: normal;">Protected</em>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php 
            // Then display invited users
            foreach ( $data['invited_users'] as $user_id => $invitation ) : 
                $user = get_user_by('id', $user_id);
                if (!$user) continue;
                
                $role = $invitation['is_organizer'] ? 'Organizer' : 'Member';
            ?>
                <tr data-user-id="<?php echo $user_id; ?>" class="invited-row">
                    <td style="font-size: 0.9em; padding: 5px;">
                        <?php echo bp_core_fetch_avatar( array( 
                            'item_id' => $user_id, 
                            'type' => 'thumb', 
                            'width' => 40, 
                            'height' => 40 
                        ) ); ?>
                    </td>
                    <td style="font-size: 0.9em; padding: 5px;">
                        <?php echo bp_core_get_userlink( $user_id ); ?>
                    </td>
                    <td style="font-size: 0.9em; padding: 5px;">
                        <?php echo esc_html( $user->user_email ); ?>
                    </td>
                    <td style="font-size: 0.9em; padding: 5px;">
                        <?php echo date( 'M j, Y', strtotime( $invitation['invited_date'] ) ); ?>
                    </td>
                    <td style="font-size: 0.9em; padding: 5px;">
                        <strong><?php echo $role; ?></strong>
                    </td>
                    <td style="font-size: 0.9em; padding: 5px;">
                        <span class="status-badge status-pending" style="background-color: #f0ad4e; color: white; padding: 5px; border-radius: 3px;">Pending</span>
                    </td>
                    <td style="font-size: 0.9em; padding: 5px;">
                        <em>Awaiting Confirmation</em>
                        <a href="javascript:void(0)" 
                           class="cancel-link ajax-cancel-invitation"
                           data-user-id="<?php echo $user_id; ?>"
                           data-group-id="<?php echo $data['group_id']; ?>">
                            Cancel
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="notice info">
            <p>No members or pending invitations found in this group.</p>
        </div>
    <?php endif; ?>
</div>