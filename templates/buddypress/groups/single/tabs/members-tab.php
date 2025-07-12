<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// Fetch appearance settings at the top
$appearance_settings = get_option( 'labgenz_cm_appearance', array() );
$data                = get_group_management_data();
if ( ! $data['success'] ) {
	echo '<div class="notice error bb-notice bb-notice--error">' . esc_html( $data['error'] ) . '</div>';
	return;
}

if ( ! $data['is_organizer'] ) {
	echo '<div class="notice error bb-notice bb-notice--error">Access restricted. You must be a group organizer to view this page.</div>';
	return;
}
?>

<div id="members-tab" class="tab-pane active default-tab bb-card">
	<div id="alert-container"></div>
	
	<?php
	if ( isset( $message ) ) {
		echo esc_html( $message );
	}
	?>
	
	<h2 class="bb-card__title">Group Members Management</h2>
	
	<div class="invite-section bb-card bb-card--padding">
		<h3 class="bb-card__title">Invite New Member</h3>
		<button id="labgenz-show-invite-popup" type="button" class="bb-button bb-button--primary">Invite Member</button>
		<div id="labgenz-invite-popup" class="labgenz-modal bb-modal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.4); z-index:9999; align-items:center; justify-content:center;">
			<div class="bb-modal__content" style="background:#fff; padding:30px 24px; border-radius:8px; max-width:900px; width:90vw; margin:auto; position:relative; display:flex; gap:32px; flex-wrap:wrap;">
				<button id="labgenz-close-invite-popup" class="bb-modal__close" style="position:absolute; top:10px; right:10px; background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
				<div style="flex:1 1 320px; min-width:300px;">
					<h4 class="bb-card__title">Invited Users</h4>
					<ul id="labgenz-invited-list-popup" class="bb-list" style="max-height:400px; overflow-y:auto; padding-left:0; list-style:none;"></ul>
				</div>
				<div style="flex:1 1 320px; min-width:300px;">
					<form id="labgenz-invite-form" class="invite-form bb-form">
						<div class="form-row bb-form__row">
							<input type="email" name="email" class="bb-input" placeholder="Enter email address" required style="width:100%;margin-bottom:10px;">
							<input type="text" name="first_name" class="bb-input" placeholder="First Name" required style="width:100%;margin-bottom:10px;">
							<input type="text" name="last_name" class="bb-input" placeholder="Last Name" required style="width:100%;margin-bottom:10px;">
							<select name="profile_type" id="labgenz-profile-type-select" class="bb-select" required style="width:100%;margin-bottom:10px;">
								<option value="">Loading profile types...</option>
							</select>
						</div>
						<div class="form-row bb-form__row">
							<button type="submit" class="bb-button bb-button--primary">Send Invitation</button>
						</div>
						<div id="labgenz-invite-message" class="bb-notice" style="margin-top:10px;"></div>
					</form>
				</div>
			</div>
		</div>
	</div>

	<!-- Confirmation Modal -->
	<div id="user-modal" class="modal-overlay bb-modal-overlay">
		<div class="modal bb-modal">
			<div class="modal-header bb-modal__header">
				<h3 id="modal-title" class="bb-modal__title">Confirm User</h3>
			</div>
			<div id="modal-content" class="bb-modal__content">
				<!-- Content will be dynamically inserted -->
			</div>
		</div>
	</div>

	<?php if ( ! empty( $data['members']['members'] ) || ! empty( $data['invited_users'] ) ) : ?>
		<h3 class="bb-card__title">Group Members and Invitations</h3>
		
		<!-- Email Search Input -->
		<input type="text" id="email-search" class="bb-input" placeholder="Search members and invitations" style="margin-bottom:10px; padding:5px; width:100%;">

		<table class="members-table bb-table" id="members-table">
			<thead>
				<tr>
					<th class="bb-table__header">Avatar</th>
					<th class="bb-table__header">Username</th>
					<th class="bb-table__header">Email</th>
					<th class="bb-table__header">Joined Date</th>
					<th class="bb-table__header">Role</th>
					<th class="bb-table__header">Status</th>
					<th class="bb-table__header">Actions</th>
				</tr>
			</thead>
			<tbody>
			<?php
			// First display actual members
			foreach ( $data['members']['members'] as $member ) :
				$is_admin    = groups_is_user_admin( $member->ID, $data['group_id'] );
				$is_mod      = groups_is_user_mod( $member->ID, $data['group_id'] );
				$member_role = $is_admin ? 'Organizer' : ( $is_mod ? 'Moderator' : 'Member' );
				?>
				<tr data-user-id="<?php echo esc_attr( $member->ID ); ?>" class="member-row bb-table__row" style="transition: background-color 0.2s ease; border-bottom: 1px solid #f0f0f0;">
					<td class="bb-table__cell" style="font-size: 16px; padding: 12px 8px; text-align: center; vertical-align: middle;">
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Avatar HTML is already escaped by BuddyPress
						echo bp_core_fetch_avatar(
							array(
								'item_id' => $member->ID,
								'type'    => 'thumb',
								'width'   => 40,
								'height'  => 40,
							)
						);
						?>
					</td>
					<td class="bb-table__cell" style="font-size: 15px; padding: 12px 8px; font-weight: 500; color: #2c3e50; vertical-align: middle;">
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- BuddyPress escapes user link
						echo bp_core_get_userlink( $member->ID );
						?>
					</td>
					<td class="bb-table__cell" style="font-size: 14px; padding: 12px 8px; color: #7f8c8d; vertical-align: middle; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
						<?php echo esc_html( $member->user_email ); ?>
					</td>
					<td class="bb-table__cell" style="font-size: 13px; padding: 12px 8px; color: #95a5a6; vertical-align: middle; white-space: nowrap;">
						<?php echo esc_html( gmdate( 'M j, Y', strtotime( $member->date_modified ) ) ); ?>
					</td>
					<td class="bb-table__cell" style="font-size: 14px; padding: 12px 8px; vertical-align: middle;">
						<strong style="color: #34495e; font-weight: 600;"><?php echo esc_html( $member_role ); ?></strong>
					</td>
					<td class="bb-table__cell" style="font-size: 14px; padding: 12px 8px; vertical-align: middle;">
						<span class="status-badge status-active bb-badge bb-badge--success" style="background: #27ae60; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">Active</span>
					</td>
					<td class="bb-table__cell" style="font-size: 14px; padding: 12px 8px; vertical-align: middle; text-align: center;">
						<?php if ( $member->ID !== $data['current_user_id'] && ! $is_admin ) : ?>
							<a href="javascript:void(0)" 
								class="remove-link ajax-remove-member bb-button bb-button--danger bb-button--small"
								data-user-id="<?php echo esc_attr( $member->ID ); ?>"
								data-user-name="<?php echo esc_attr( bp_core_get_user_displayname( $member->ID ) ); ?>"
								style="color: #e74c3c; text-decoration: none; font-weight: 500; padding: 6px 12px; border-radius: 4px; transition: all 0.2s ease; border: 1px solid #e74c3c;"
								onmouseover="this.style.backgroundColor='#e74c3c'; this.style.color='white';"
								onmouseout="this.style.backgroundColor='transparent'; this.style.color='#e74c3c';">
								Remove
							</a>
						<?php else : ?>
							<?php if ( $member->ID === $data['current_user_id'] ) : ?>
								<em style="color: #3498db; font-style: normal; font-weight: 500;">You</em>
							<?php else : ?>
								<em style="color: #95a5a6; font-style: normal;">Protected</em>
							<?php endif; ?>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>

			<?php
			// Then display invited users
			foreach ( $data['invited_users'] as $user_id => $invitation ) :
				$user = get_user_by( 'id', $user_id );
				if ( ! $user ) {
					continue;
				}
				// Fix: Ensure invitation is an array before accessing its keys
				if ( ! is_array( $invitation ) ) {
					continue;
				}
				$invited_role = $invitation['is_organizer'] ? 'Organizer' : 'Member';
				// Use appearance settings directly, no fallbacks
				$font_size     = isset( $appearance_settings['font_size'] ) ? $appearance_settings['font_size'] . 'px' : '';
				$font_family   = isset( $appearance_settings['font_family'] ) ? $appearance_settings['font_family'] : '';
				$text_color    = isset( $appearance_settings['text_color'] ) ? $appearance_settings['text_color'] : '';
				$warning_color = isset( $appearance_settings['warning_color'] ) ? $appearance_settings['warning_color'] : '';
				$border_radius = isset( $appearance_settings['border_radius'] ) ? $appearance_settings['border_radius'] . 'px' : '';
				$accent_color  = isset( $appearance_settings['accent_color'] ) ? $appearance_settings['accent_color'] : '';
				?>
				<tr data-user-id="<?php echo esc_attr( $user_id ); ?>" class="invited-row bb-table__row">
					<td class="bb-table__cell" style="font-size: <?php echo esc_attr( $font_size ); ?>; padding: 5px; font-family: <?php echo esc_attr( $font_family ); ?>;">
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Avatar HTML is already escaped by BuddyPress
						echo bp_core_fetch_avatar(
							array(
								'item_id' => $user_id,
								'type'    => 'thumb',
								'width'   => 40,
								'height'  => 40,
							)
						);
						?>
					</td>
					<td class="bb-table__cell" style="font-size: <?php echo esc_attr( $font_size ); ?>; padding: 5px; font-family: <?php echo esc_attr( $font_family ); ?>; color: <?php echo esc_attr( $text_color ); ?>;">
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- BuddyPress escapes user link
						echo bp_core_get_userlink( $user_id );
						?>
					</td>
					<td class="bb-table__cell" style="font-size: <?php echo esc_attr( $font_size ); ?>; padding: 5px; font-family: <?php echo esc_attr( $font_family ); ?>; color: <?php echo esc_attr( $text_color ); ?>;">
						<?php echo esc_html( $user->user_email ); ?>
					</td>
					<td class="bb-table__cell" style="font-size: <?php echo esc_attr( $font_size ); ?>; padding: 5px; font-family: <?php echo esc_attr( $font_family ); ?>; color: <?php echo esc_attr( $text_color ); ?>;">
						<?php echo esc_html( gmdate( 'M j, Y', strtotime( $invitation['invited_date'] ) ) ); ?>
					</td>
					<td class="bb-table__cell" style="font-size: <?php echo esc_attr( $font_size ); ?>; padding: 5px; font-family: <?php echo esc_attr( $font_family ); ?>; color: <?php echo esc_attr( $text_color ); ?>;">
						<strong><?php echo esc_html( $invited_role ); ?></strong>
					</td>
					<td class="bb-table__cell" style="font-size: <?php echo esc_attr( $font_size ); ?>; padding: 5px;">
						<span class="status-badge status-pending bb-badge bb-badge--warning" style="background-color: <?php echo esc_attr( $warning_color ); ?>; color: white; padding: 5px; border-radius: <?php echo esc_attr( $border_radius ); ?>;">Pending</span>
					</td>
					<td class="bb-table__cell" style="font-size: <?php echo esc_attr( $font_size ); ?>; padding: 5px;">
						<em>Awaiting Confirmation</em>
						<a href="javascript:void(0)" 
							class="cancel-link ajax-cancel-invitation bb-button bb-button--danger bb-button--small"
							data-user-id="<?php echo esc_attr( $user_id ); ?>"
							data-group-id="<?php echo esc_attr( $data['group_id'] ); ?>"
							style="color: <?php echo esc_attr( $accent_color ); ?>; border-color: <?php echo esc_attr( $accent_color ); ?>;">
							Cancel
						</a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<div class="notice info bb-notice bb-notice--info">
			<p>No members or pending invitations found in this group.</p>
		</div>
	<?php endif; ?>
</div>
