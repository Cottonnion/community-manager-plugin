<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

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
	$invited_users = groups_get_groupmeta( $group_id, 'labgen_invited', true );
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
		'nonce'            => wp_create_nonce( 'inst3d_group_management_nonce' ),
	);
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
?>

<div class="group-management bb-card" 
	data-group-id="<?php echo esc_attr( $data['group_id'] ); ?>"
	data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
	data-nonce="<?php echo esc_attr( $data['nonce'] ); ?>">
	
	<!-- Tab Navigation -->
	<div class="tab-nav bb-tabs">
		<button class="threedinst-tab-btn bb-tabs__tab bb-tabs__tab--active" data-tab="members">Members Management</button>
		<button class="threedinst-tab-btn bb-tabs__tab" data-tab="progress">Members Progress</button>
		<button class="threedinst-tab-btn bb-tabs__tab" data-tab="settings">Content Settings</button>
	</div>

	<!-- Tab Content -->
	<div class="tab-content bb-tabs__content">
		<?php
		// Include tab templates
		require __DIR__ . '/tabs/members-tab.php';
		require __DIR__ . '/tabs/progress-tab.php';
		require __DIR__ . '/tabs/settings-tab.php';
		?>
	</div>
</div>

<?php get_footer(); ?>
