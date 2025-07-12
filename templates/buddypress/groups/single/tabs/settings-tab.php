<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data = get_group_management_data();
if ( ! $data['success'] ) {
	echo '<div class="notice error bb-notice bb-notice--error">' . esc_html( $data['error'] ) . '</div>';
	return;
}

if ( ! $data['is_organizer'] ) {
	echo '<div class="notice error bb-notice bb-notice--error">Access restricted. You must be a group organizer to view this page.</div>';
	return;
}

// Localize script with nonces and AJAX URL
wp_localize_script(
	'inst3d-group-course-manager',
	'inst3dGroupCourseManager',
	array(
		'ajaxurl' => admin_url( 'admin-ajax.php' ),
		'nonces'  => array(
			'getCourses'          => wp_create_nonce( 'get_group_courses_nonce' ),
			'getLearnDashCourses' => wp_create_nonce( 'get_learndash_courses_nonce' ),
			'removeCourse'        => wp_create_nonce( 'remove_group_course_nonce' ),
			'toggleCourseStatus'  => wp_create_nonce( 'toggle_course_status_nonce' ),
		),
	)
);

// Handle AJAX request to toggle course status
add_action( 'wp_ajax_toggle_course_status', 'toggle_course_status' );
function toggle_course_status() {
	// Check nonce
	check_ajax_referer( 'toggle_course_status_nonce', 'nonce' );

	// Get course ID and new status from request
	$course_id  = intval( $_POST['course_id'] );
	$new_status = sanitize_text_field( $_POST['new_status'] );

	// Update course status in the database (pseudo code, replace with actual logic)
	$updated = update_course_status_in_db( $course_id, $new_status );

	if ( $updated ) {
		wp_send_json_success();
	} else {
		wp_send_json_error( 'Failed to update course status.' );
	}
}
?>

<div id="settings-tab" class="tab-pane bb-card">
	<h2 class="bb-card__title">Content Settings</h2>
   
	<div class="inst-3d-course-management-section bb-card bb-card--padding">
		<!-- Group Selector -->
		<div class="inst-3d-group-selector bb-form">
			<div class="inst-3d-form-group bb-form__row">
				<label for="inst3d-manage-group" class="bb-label">Select Group to Manage:</label>
				<select id="inst3d-manage-group" name="manage_group" class="bb-select" required>
					<option value="" selected>Select a group...</option>
					<?php foreach ( $data['organizer_groups'] as $org_group ) : ?>
						<option value="<?php echo esc_attr( $org_group['id'] ); ?>">
							<?php echo esc_html( $org_group['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>

			</div>
		</div>

		<!-- Course Management Section (initially hidden) -->
		<div id="inst3d-course-management" class="bb-card bb-card--padding" style="display: none;">
			<div class="inst3d-section-header">
				<h3 class="bb-card__title">Course Management</h3>
			</div>
			
			<div class="inst3d-course-list">
				<div id="inst3d-loading" class="bb-notice bb-notice--info" style="display: none;">Loading courses...</div>
				<table class="inst3d-courses-table bb-table">
					<thead>
						<tr>
							<th class="bb-table__header">Category Name</th>
							<th class="bb-table__header">Status</th>
							<th class="bb-table__header">Actions</th>
						</tr>
					</thead>
					<tbody id="inst3d-courses-table-body" class="bb-table__body">
						<!-- Category rows will be dynamically populated -->
						<tr class="bb-table__row">
							<td class="bb-table__cell">Category Name</td>
							<td class="bb-table__cell">Assigned</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
