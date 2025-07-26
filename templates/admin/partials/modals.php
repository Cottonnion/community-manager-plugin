<?php
/**
 * Modals partial template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<!-- Details Modal -->
<div id="request-details-modal" class="labgenz-modal" style="display: none;">
	<div class="labgenz-modal-content">
		<div class="labgenz-modal-header">
			<h2><?php _e( 'Request Details', 'labgenz-community-management' ); ?></h2>
			<button type="button" class="labgenz-modal-close">&times;</button>
		</div>
		<div class="labgenz-modal-body">
			<div id="request-details-content"></div>
		</div>
	</div>
</div>

<!-- Action Modal -->
<div id="action-modal" class="labgenz-modal" style="display: none;">
	<div class="labgenz-modal-content">
		<div class="labgenz-modal-header">
			<h2 id="action-modal-title"></h2>
			<button type="button" class="labgenz-modal-close">&times;</button>
		</div>
		<div class="labgenz-modal-body">
			<form id="action-form">
				<input type="hidden" id="action-user-id" name="user_id">
				<input type="hidden" id="action-type" name="action_type">
				
				<div class="form-group">
					<label for="admin-note"><?php _e( 'Admin Note (Optional)', 'labgenz-community-management' ); ?></label>
					<textarea id="admin-note" name="admin_note" rows="4" cols="50" 
							  placeholder="<?php _e( 'Add a note for the user...', 'labgenz-community-management' ); ?>"></textarea>
				</div>
				
				<div class="form-actions">
					<button type="submit" class="button button-primary"><?php _e( 'Confirm', 'labgenz-community-management' ); ?></button>
					<button type="button" class="button labgenz-modal-close"><?php _e( 'Cancel', 'labgenz-community-management' ); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>
