<?php
if ( ! defined( 'ABSPATH' ) ) exit;

use LABGENZ_CM\Groups\Helpers\GroupMemberVisibilityHelper;

function get_group_management_data() {
	$group_id = bp_get_current_group_id();
	if ( ! $group_id ) {
		return array(
			'error'   => 'No group found.',
			'success' => false,
		);
	}

	$current_user_id = get_current_user_id();
	if ( ! $current_user_id ) {
		return array(
			'error'   => 'Please log in to access this page.',
			'success' => false,
		);
	}

	$is_organizer = groups_is_user_admin( $current_user_id, $group_id );

	// Get all groups where current user is an organizer
	$user_groups      = groups_get_user_groups( $current_user_id );
	$organizer_groups = array();

	if ( ! empty( $user_groups['groups'] ) ) {
		foreach ( $user_groups['groups'] as $user_group_id ) {
			if ( groups_is_user_admin( $current_user_id, $user_group_id ) ) {
				$group_obj          = groups_get_group( $user_group_id );
				$organizer_groups[] = array(
					'id'   => $user_group_id,
					'name' => $group_obj->name,
					'slug' => $group_obj->slug,
				);
			}
		}
	}

	$args    = array(
		'group_id'            => $group_id,
		'per_page'            => 100,
		'page'                => 1,
		'exclude_admins_mods' => false,
		'type'                => 'all',
	);
	$members = groups_get_group_members( $args );

	// Get invited users
	$invited_users = groups_get_groupmeta( $group_id, 'lab_invited', true );
	if ( ! is_array( $invited_users ) ) {
		$invited_users = array();
	}

	return array(
		'success'          => true,
		'group_id'         => $group_id,
		'current_user_id'  => $current_user_id,
		'is_organizer'     => $is_organizer,
		'members'          => $members,
		'invited_users'    => $invited_users,
		'organizer_groups' => $organizer_groups,
		'nonce'            => wp_create_nonce( 'lab_group_management_nonce' ),
	);
}

$data = get_group_management_data();
if ( ! $data['success'] ) {
    echo '<div class="notice error">' . esc_html( $data['error'] ) . '</div>';
    return;
}

if ( ! $data['is_organizer'] && ! current_user_can( 'administrator' ) ) {
    echo '<div class="notice error">Access restricted. You must be a group leader to view this page.</div>';
    return;
}
?>

<div id="lab-members-tab" class="lab-tab-pane active lab-default-tab">
    <div id="lab-alert-container"></div>
    
    <?php if ( isset($message) ) echo $message; ?>
    
    <!-- <h2>Group Members Management</h2> -->

    <div class="lab-invite-section">
        <h3>Invite New Member To <?php echo esc_html( bp_get_current_group_name() ); ?></h3>
        
        <div class="lab-invite-buttons" style="margin-bottom: 15px;">
            <button type="button" id="lab-show-invite-form" class="lab-invite-btn" style="margin-right: 10px;">Search & Invite</button>
            <button type="button" id="lab-bulk-invite" class="lab-invite-btn">Bulk Invite</button>
        </div>
        
                <form id="lab-invite-form" class="lab-invite-form" style="display: none; gap: 10px;">
            <div style="width: 70%; float: left;">
                <div class="lab-form-row" style="margin-bottom: 10px;">
                    <input type="email" id="lab-user-email" placeholder="Enter email address" required pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$" title="Please enter a valid email address">
                </div>
                <div style="display: flex; gap: 10px;">
                    <div class="lab-form-row" style="margin-bottom: 10px; flex: 1;">
                        <input type="text" id="lab-user-firstname" placeholder="First Name">
                        <small style="display: block; margin-top: 3px; color: #666;">Required for new accounts</small>
                    </div>
                    <div class="lab-form-row" style="margin-bottom: 10px; flex: 1;">
                        <input type="text" id="lab-user-lastname" placeholder="Last Name">
                        <small style="display: block; margin-top: 3px; color: #666;">Required for new accounts</small>
                    </div>
                </div>
            </div>
            <div class="lab-form-row" style="width: 30%; float: right; display: flex; align-items: flex-start;">
                <button type="submit" class="lab-invite-btn" style="width: 100%; margin-top: 0;">Search with email</button>
            </div>
            <div style="clear: both;"></div>
        </form>

        <!-- Bulk Invite Form -->
        <div id="lab-bulk-invite-form" class="lab-bulk-invite-form" style="display: none; margin-top: 15px; padding: 15px; background-color: #f9f9f9; border-radius: 5px; border: 1px solid #e5e5e5;">
            <h4 style="margin-top: 0;">Bulk Invite Members</h4>
            
            <div class="lab-bulk-instructions">
                <p><strong>File Format:</strong></p>
                <ol style="margin-left: 20px; line-height: 1.5;">
                    <li>Required columns: Email, First Name, Last Name, Role (Organizer or Member)</li>
                    <li>Upload Excel (.xlsx/.xls) or CSV files</li>
                </ol>
                <button type="button" id="lab-download-template" class="lab-invite-btn" style="margin-top:10px;">Download Template</button>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var downloadBtn = document.getElementById('lab-download-template');
                    if (downloadBtn) {
                        downloadBtn.addEventListener('click', function() {
                            var link = document.createElement('a');
                            link.href = '<?php echo esc_url( LABGENZ_CM_TEMPLATES_URL . '/download-templates/bulk-invite-template.csv' ); ?>';
                            link.download = 'bulk-invite-template.csv';
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                        });
                    }
                });
                </script>
            </div>

            <div class="lab-bulk-upload-section" style="margin-top: 15px;">
                <input type="hidden" name="lab_group_management_nonce" value="<?php echo esc_attr($data['nonce']); ?>" />
                
                <div class="lab-form-row" style="margin-bottom: 10px;">
                    <label for="lab-csv-file">Upload File (Excel, CSV, or tab separated):</label>
                    <input type="file" id="lab-csv-file" name="csv_file" accept=".xlsx, .xls, .csv, .txt, .tsv" required>
                    <small style="display: block; margin-top: 5px; color: #666;">You can upload Excel files (.xlsx, .xls) or CSV/TSV files</small>
                </div>
                
                <div class="lab-form-row" style="margin-top: 15px;">
                    <button type="button" id="lab-process-bulk-invite" class="lab-invite-btn">Process Bulk Invite</button>
                </div>
            </div>

            <div id="lab-bulk-preview" style="margin-top: 20px; display: none;">
                <h4>Preview Invitations</h4>
                <div id="lab-bulk-preview-content"></div>
                <button type="button" id="lab-send-bulk-invites" class="lab-invite-btn" style="margin-top: 15px;">Send Invitations</button>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <!-- <div id="lab-user-modal" class="lab-modal-overlay">
        <div class="lab-modal">
            <div class="lab-modal-header">
                <h3 id="lab-modal-title">Confirm User</h3>
            </div>
            <div id="lab-modal-content">
                 Content will be dynamically inserted
            </div>
        </div>
    </div>  -->

    <?php if ( ! empty( $data['members']['members'] ) || ! empty( $data['invited_users'] ) ) : ?>
        <!-- <h3>Group Members and Invitations</h3> -->
        
        <!-- Email Search Input -->
        <input type="text" id="lab-email-search" placeholder="Search members and invitations" style="margin-bottom:10px; padding:5px; width:100%;">

        <!-- Responsive table wrapper -->
        <div class="lab-table-responsive">
            <table class="lab-members-table" id="lab-members-table">
            <thead>
                <tr>
                    <th>Avatar</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Joined Date</th>
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
                $role = $is_admin ? 'Group Leader' : ( $is_mod ? 'Moderator' : 'Member' );

                // Check if member is hidden in groupmeta
                $hidden_key = 'hidden_member_' . $member->ID;
                $is_hidden = groups_get_groupmeta( $data['group_id'], $hidden_key, true );
                if ( $is_hidden ) {
                    continue;
                }
            ?>
                <tr data-user-id="<?php echo $member->ID; ?>" class="lab-member-row" style="transition: background-color 0.2s ease; border-bottom: 1px solid #f0f0f0;">
                    <td style="font-size: 16px; padding: 12px 8px; text-align: center; vertical-align: middle;">
                        <?php echo bp_core_fetch_avatar( array( 
                            'item_id' => $member->ID, 
                            'type' => 'thumb', 
                            'width' => 40, 
                            'height' => 40 
                        ) ); ?>
                    </td>
                    <td style="font-size: 15px; padding: 12px 8px; font-weight: 500; color: #2c3e50; vertical-align: middle;">
                        <?php
                            $first_name = get_user_meta($member->ID, 'first_name', true);
                            $last_name = get_user_meta($member->ID, 'last_name', true);
                            $full_name = trim($first_name . ' ' . $last_name);
                            echo esc_html($full_name ? $full_name : bp_core_get_user_displayname($member->ID));
                        ?>
                    </td>
                    <td style="font-size: 14px; padding: 12px 8px; color: #7f8c8d; vertical-align: middle; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                        <?php echo esc_html( $member->user_email ); ?>
                    </td>
                    <td style="font-size: 14px; padding: 12px 8px; vertical-align: middle;">
                        <span class="lab-role-badge" style="background: <?php echo $is_admin ? 'var(--bb-primary-button-background-regular)' : ($is_mod ? '#3498db' : '#95a5a6'); ?>; color: white; padding: 3px 10px; border-radius: 15px; font-size: 11px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">
                            <?php echo esc_html( $role ); ?>
                        </span>
                    </td>
                    <td style="font-size: 13px; padding: 12px 8px; color: #95a5a6; vertical-align: middle; white-space: nowrap;">
                        <?php echo date( 'M j, Y', strtotime( $member->date_modified ) ); ?>
                    </td>
                    <td style="font-size: 14px; padding: 12px 8px; vertical-align: middle;">
                        <span class="lab-status-badge lab-status-active" style="background: var(--bb-primary-button-background-regular); color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">Active</span>
                    </td>
                    <td style="font-size: 14px; padding: 12px 8px; vertical-align: middle; text-align: center;">
                        <?php if ( $member->ID !== $data['current_user_id'] && ! $is_admin ): ?>
                            <button type="button" 
                               class="lab-actions-btn lab-member-actions"
                               data-user-id="<?php echo $member->ID; ?>"
                               data-user-name="<?php echo esc_attr( bp_core_get_user_displayname( $member->ID ) ); ?>"
                               data-email="<?php echo esc_attr($member->user_email); ?>"
                               data-role="<?php echo esc_attr($role); ?>"
                               style="background-color: var(--bb-primary-button-background-regular); color: white; border: none; border-radius: 4px; padding: 6px 12px; cursor: pointer; font-weight: 500;"
                               onmouseover="this.style.backgroundColor='var(--bb-primary-button-background-hover)';"
                               onmouseout="this.style.backgroundColor='var(--bb-primary-button-background-regular)';">
                                Actions <i class="bb-icon-f bb-icon-angle-down" style="margin-left: 5px;"></i>
                            </button>
                        <?php else: ?>
                            <?php if ( $member->ID === $data['current_user_id'] ): ?>
                                <em style="color: #8B4513; font-style: normal; font-weight: 500;">You</em>
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
                
                $first_name = get_user_meta($user_id, 'first_name', true);
                $last_name = get_user_meta($user_id, 'last_name', true);
                $full_name = trim($first_name . ' ' . $last_name);
            ?>
                <tr data-user-id="<?php echo $user_id; ?>" class="lab-invited-row" style="transition: background-color 0.2s ease; border-bottom: 1px solid #f0f0f0;">
                    <td style="font-size: 16px; padding: 12px 8px; text-align: center; vertical-align: middle;">
                        <?php echo bp_core_fetch_avatar( array( 
                            'item_id' => $user_id, 
                            'type' => 'thumb', 
                            'width' => 40, 
                            'height' => 40 
                        ) ); ?>
                    </td>
                    <td style="font-size: 15px; padding: 12px 8px; font-weight: 500; color: #2c3e50; vertical-align: middle;">
                        <?php echo esc_html($full_name ? $full_name : $user->display_name); ?>
                    </td>
                    <td style="font-size: 14px; padding: 12px 8px; color: #7f8c8d; vertical-align: middle; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                        <?php echo esc_html( $user->user_email ); ?>
                    </td>
                    <td style="font-size: 14px; padding: 12px 8px; vertical-align: middle;">
                        <span class="lab-role-badge" style="background: var(--bb-warning-color); color: white; padding: 3px 10px; border-radius: 15px; font-size: 11px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">
                            <?php echo isset($invitation['is_organizer']) && $invitation['is_organizer'] ? 'Group Leader' : 'Member'; ?> (Invited)
                        </span>
                    </td>
                    <td style="font-size: 13px; padding: 12px 8px; color: #95a5a6; vertical-align: middle; white-space: nowrap;">
                        <?php echo date( 'M j, Y', strtotime( $invitation['invited_date'] ) ); ?>
                    </td>
                    <td style="font-size: 14px; padding: 12px 8px; vertical-align: middle;">
                        <span class="lab-status-badge lab-status-pending" style="background-color: var(--bb-warning-color); color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">Pending</span>
                    </td>
                    <td style="font-size: 14px; padding: 12px 8px; vertical-align: middle; text-align: center;">
                        <button type="button" 
                           class="lab-actions-btn lab-invitation-actions"
                           data-user-id="<?php echo $user_id; ?>"
                           data-group-id="<?php echo $data['group_id']; ?>"
                           data-email="<?php echo esc_attr($user->user_email); ?>"
                           data-name="<?php echo esc_attr($full_name ? $full_name : $user->display_name); ?>"
                           style="background-color: var(--bb-primary-button-background-regular); color: white; border: none; border-radius: 4px; padding: 6px 12px; cursor: pointer; font-weight: 500;"
                           onmouseover="this.style.backgroundColor='var(--bb-primary-button-background-hover)';"
                           onmouseout="this.style.backgroundColor='var(--bb-primary-button-background-regular)';">
                            Actions <i class="bb-icon-f bb-icon-angle-down" style="margin-left: 5px;"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div> <!-- End responsive wrapper -->
    <?php else: ?>
        <div class="notice info">
            <p style="margin:0px !important; font-weight:600 !important;">No members or pending invitations found in this group.</p>
        </div>
    <?php endif; ?>
</div>