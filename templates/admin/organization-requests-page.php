<?php
/**
 * Organization Access Requests Admin Page Template
 *
 * @var string $current_tab Current tab
 * @var array $valid_tabs Valid tabs
 * @var array $requests Requests data
 * @var OrganizationAccessDataHandler $data_handler Data handler instance
 * @var array $status_constants Status constants
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1><?php _e( 'Organization Access Requests', 'labgenz-community-management' ); ?></h1>
	
	<?php include LABGENZ_CM_PATH . 'templates/admin/partials/navigation-tabs.php'; ?>

	<div class="tablenav top">
		<div class="alignleft actions bulkactions">
			<?php if ( $current_tab === 'pending' ) : ?>
				<label for="bulk-action-selector-top" class="screen-reader-text"><?php _e( 'Select bulk action', 'labgenz-community-management' ); ?></label>
				<select name="action" id="bulk-action-selector-top">
					<option value="-1"><?php _e( 'Bulk Actions', 'labgenz-community-management' ); ?></option>
					<option value="approve"><?php _e( 'Approve', 'labgenz-community-management' ); ?></option>
					<option value="reject"><?php _e( 'Reject', 'labgenz-community-management' ); ?></option>
				</select>
				<input type="submit" id="doaction" class="button action" value="<?php _e( 'Apply', 'labgenz-community-management' ); ?>">
			<?php endif; ?>
		</div>
	</div>

	<?php if ( empty( $requests ) ) : ?>
		<div class="notice notice-info">
			<p><?php _e( 'No organization access requests found.', 'labgenz-community-management' ); ?></p>
		</div>
	<?php else : ?>
		<?php include LABGENZ_CM_PATH . 'templates/admin/partials/requests-table.php'; ?>
	<?php endif; ?>
</div>

<?php 
// Include modals
include LABGENZ_CM_PATH . 'templates/admin/partials/modals.php';
?>
