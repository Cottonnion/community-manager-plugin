<?php
/**
 * Requests table partial template
 *
 * @var string $current_tab Current tab
 * @var array $requests Requests data
 * @var OrganizationAccessDataHandler $data_handler Data handler instance
 * @var array $status_constants Status constants
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<form id="organization-requests-form" method="post">
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<?php if ( $current_tab === 'pending' ) : ?>
					<td id="cb" class="manage-column column-cb check-column">
						<input id="cb-select-all-1" type="checkbox">
					</td>
				<?php endif; ?>
				<th scope="col" class="manage-column column-user"><?php _e( 'User', 'labgenz-community-management' ); ?></th>
				<th scope="col" class="manage-column column-organization"><?php _e( 'Organization', 'labgenz-community-management' ); ?></th>
				<th scope="col" class="manage-column column-groups"><?php _e( 'Groups', 'labgenz-community-management' ); ?></th>
				<th scope="col" class="manage-column column-courses"><?php _e( 'Courses', 'labgenz-community-management' ); ?></th>
				<th scope="col" class="manage-column column-status"><?php _e( 'Status', 'labgenz-community-management' ); ?></th>
				<th scope="col" class="manage-column column-date"><?php _e( 'Date', 'labgenz-community-management' ); ?></th>
				<th scope="col" class="manage-column column-actions"><?php _e( 'Actions', 'labgenz-community-management' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $requests as $request ) : ?>
				<?php 
				$user_groups = $data_handler->get_user_groups( $request['user_id'] );
				$user_courses = $data_handler->get_user_courses( $request['user_id'] );
				?>
				<tr data-user-id="<?php echo esc_attr( $request['user_id'] ); ?>">
					<?php if ( $current_tab === 'pending' ) : ?>
						<th scope="row" class="check-column">
							<input type="checkbox" name="user_ids[]" value="<?php echo esc_attr( $request['user_id'] ); ?>">
						</th>
					<?php endif; ?>
					<td class="column-user">
						<strong><a href="#" class="user-profile-link" data-user-id="<?php echo esc_attr( $request['user_id'] ); ?>"><?php echo esc_html( $request['user']->display_name ); ?></a></strong><br>
						<small><?php echo esc_html( $request['user']->user_email ); ?></small>
					</td>
					<td class="column-organization">
						<strong><?php echo esc_html( $request['request_data']['organization_name'] ); ?></strong><br>
						<small><?php echo esc_html( $request['request_data']['contact_email'] ); ?></small>
					</td>
					<td class="column-groups">
						<?php if ( ! empty( $user_groups ) ) : ?>
							<a href="#" class="view-user-groups" data-user-id="<?php echo esc_attr( $request['user_id'] ); ?>" data-groups='<?php echo esc_attr( json_encode( $user_groups ) ); ?>'>
								<?php printf( __( 'User Groups (%d)', 'labgenz-community-management' ), count( $user_groups ) ); ?>
							</a>
						<?php else : ?>
							<span class="no-data"><?php _e( 'No groups', 'labgenz-community-management' ); ?></span>
						<?php endif; ?>
					</td>
					<td class="column-courses">
						<?php if ( ! empty( $user_courses ) ) : ?>
							<a href="#" class="view-user-courses" data-user-id="<?php echo esc_attr( $request['user_id'] ); ?>" data-courses='<?php echo esc_attr( json_encode( $user_courses ) ); ?>'>
								<?php printf( __( 'User Courses (%d)', 'labgenz-community-management' ), count( $user_courses ) ); ?>
							</a>
						<?php else : ?>
							<span class="no-data"><?php _e( 'No courses', 'labgenz-community-management' ); ?></span>
						<?php endif; ?>
					</td>
					<td class="column-status">
						<span class="status-badge status-<?php echo esc_attr( $request['status'] ); ?>">
							<?php echo esc_html( $request['status_label'] ); ?>
						</span>
					</td>
					<td class="column-date">
						<?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $request['request_data']['requested_at'] ) ) ); ?>
					</td>
					<td class="column-actions">
						<button type="button" class="button button-small view-details" 
								data-user-id="<?php echo esc_attr( $request['user_id'] ); ?>">
							<?php _e( 'View Details', 'labgenz-community-management' ); ?>
						</button>
						<?php 
						// Debug: Check if status_constants is available
						if ( ! isset( $status_constants ) ) {
							$status_constants = array(
								'STATUS_PENDING' => 'pending',
								'STATUS_APPROVED' => 'approved', 
								'STATUS_REJECTED' => 'rejected',
								'STATUS_COMPLETED' => 'completed',
							);
						}
						?>
						<?php if ( $request['status'] === $status_constants['STATUS_PENDING'] ) : ?>
							<button type="button" class="button button-primary button-small approve-request" 
									data-user-id="<?php echo esc_attr( $request['user_id'] ); ?>">
								<?php _e( 'Approve', 'labgenz-community-management' ); ?>
							</button>
							<button type="button" class="button button-small reject-request" 
									data-user-id="<?php echo esc_attr( $request['user_id'] ); ?>">
								<?php _e( 'Reject', 'labgenz-community-management' ); ?>
							</button>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</form>
