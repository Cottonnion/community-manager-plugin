<?php
namespace LABGENZ_CM\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the profile location field and coordinates
 */
class ProfileLocationHandler {
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return ProfileLocationHandler
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - setup hooks
	 */
	private function __construct() {
		// Enqueue scripts for profile edit page
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		// Handle AJAX request to save coordinates
		add_action( 'wp_ajax_save_location_coordinates', [ $this, 'ajax_save_location_coordinates' ] );
		
		// Add a hook to save visibility level when profile is updated
		// Save per-user field visibility when profile is updated
		add_action( 'xprofile_updated_profile', [ $this, 'save_field_visibility' ], 20, 5 );
		
		// Add a hook for individual field updates
		add_action( 'xprofile_profile_field_data_updated', [ $this, 'save_individual_field_visibility' ], 20, 3 );

		// Add custom field to the xprofile field list
		// add_action( 'bp_custom_profile_edit_fields_pre_visibility', array( $this, 'add_coordinates_fields' ) );
	}

	/**
	 * Enqueue scripts for profile edit page
	 */
	public function enqueue_scripts() {
		// Only enqueue on profile edit page
		if ( ! function_exists( 'bp_is_user_profile_edit' ) ) {
			return;
		}

		if ( bp_is_user_profile_edit() ||
			( function_exists( 'bp_is_register_page' ) && bp_is_register_page() ) ) {
			

			// Enqueue our custom JS for handling visibility settings
			wp_enqueue_script(
				'profile-location-visibility-js',
				LABGENZ_CM_URL . 'src/Public/assets/js/profile-location-visibility.js',
				[ 'jquery' ],
				LABGENZ_CM_VERSION,
				true
			);

			// Pass data to script
			wp_localize_script(
				'profile-location-visibility-js',
				'ProfileLocationVisibility',
				[
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'location_visibility_nonce' ),
					'defaultVisibility' => 'exact_location',
					// 'debug' => true
				]
			);
			
			// Commented out legacy code
			// wp_enqueue_script(
			// 	'profile-location-field-js',
			// 	LABGENZ_CM_URL . 'src/Public/assets/js/profile-location-field.js',
			// 	[ 'jquery' ],
			// 	'1.0.3s',
			// 	true
			// );

			// Pass data to script
			// wp_localize_script(
			// 	'profile-location-field-js',
			// 	'ProfileLocationData',
			// 	[
			// 		'ajaxurl' => admin_url( 'admin-ajax.php' ),
			// 		'nonce'   => wp_create_nonce( 'save_location_coordinates_nonce' ),
			// 	]
			// );
		}
	}

	/**
	 * AJAX handler for saving location coordinates
	 */
	public function ajax_save_location_coordinates() {
		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'save_location_coordinates_nonce' ) ) {
			wp_send_json_error( [ 'message' => 'Invalid nonce' ] );
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => 'User not logged in' ] );
		}

		// Get current user ID
		$user_id = get_current_user_id();

		// Get coordinates
		$latitude  = isset( $_POST['latitude'] ) ? sanitize_text_field( $_POST['latitude'] ) : '';
		$longitude = isset( $_POST['longitude'] ) ? sanitize_text_field( $_POST['longitude'] ) : '';
		$location  = isset( $_POST['location'] ) ? sanitize_text_field( $_POST['location'] ) : '';

		// Validate coordinates
		if ( empty( $latitude ) || empty( $longitude ) ) {
			wp_send_json_error( [ 'message' => 'Invalid coordinates' ] );
		}

		// Save coordinates as user meta
		update_user_meta( $user_id, 'latitude', $latitude );
		update_user_meta( $user_id, 'longitude', $longitude );
		update_user_meta( $user_id, 'geocoded_location', $location );

		// Also save using common format for compatibility with other plugins
		update_user_meta( $user_id, 'bp_location_latitude', $latitude );
		update_user_meta( $user_id, 'bp_location_longitude', $longitude );

		// Also save for GEO my WP compatibility
		update_user_meta( $user_id, 'gmw_lat', $latitude );
		update_user_meta( $user_id, 'gmw_lng', $longitude );

		wp_send_json_success( [ 'message' => 'Coordinates saved successfully' ] );
	}

	/**
	 * Add hidden coordinates fields to profile edit form
	 * Note: Not currently used as we're adding the fields via JavaScript
	 */
	public function add_coordinates_fields() {
		$user_id   = bp_displayed_user_id();
		$latitude  = get_user_meta( $user_id, 'latitude', true );
		$longitude = get_user_meta( $user_id, 'longitude', true );

		?>
		<div class="editfield field_coordinates">
			<fieldset>
				<legend>Coordinates</legend>
				<div class="field-visibility-settings-notoggle">
					<input type="hidden" name="field_latitude" id="field_latitude" value="<?php echo esc_attr( $latitude ); ?>">
					<input type="hidden" name="field_longitude" id="field_longitude" value="<?php echo esc_attr( $longitude ); ?>">
					<?php if ( ! empty( $latitude ) && ! empty( $longitude ) ) : ?>
						<div class="location-coordinates">
							Coordinates: <?php echo esc_html( $latitude ); ?>, <?php echo esc_html( $longitude ); ?>
						</div>
					<?php endif; ?>
				</div>
			</fieldset>
		</div>
		<?php
	}
	
	/**
	 * Save field visibility settings when the entire profile is updated
	 *
	 * @param int   $user_id        The user ID
	 * @param array $posted_field_ids Field IDs that were updated
	 * @param bool  $errors         Whether there were errors
	 * @param array $old_values     Previous field values
	 * @param array $new_values     New field values
	 */
	public function save_field_visibility( $user_id, $posted_field_ids, $errors, $old_values, $new_values ) {
		// Ensure required BuddyPress functions exist
		$missing = [];
		foreach ( [ 'xprofile_set_field_visibility_level', 'xprofile_get_field', 'bp_get_the_profile_field_visibility_level' ] as $fn ) {
			if ( ! function_exists( $fn ) ) {
				$missing[] = $fn;
			}
		}
		if ( ! empty( $missing ) ) {
			wp_die(
				sprintf(
					'Fatal: missing functions: %s',
					implode( ', ', $missing )
				)
			);
		}
		
		// Check if there were errors
		if ( $errors ) {
			return;
		}
		
		// Make sure we have field IDs
		if ( empty( $posted_field_ids ) || !is_array( $posted_field_ids ) ) {
			return;
		}
		
		// Process each field
		foreach ( $posted_field_ids as $field_id ) {
			// Check if this is a location field
			$field = xprofile_get_field( $field_id );
			if ( !$field || !is_object( $field ) || !isset( $field->type ) || $field->type !== 'location' ) {
				continue;
			}
			
			// Check if visibility is set for this field
			$visibility_key = "field_{$field_id}_visibility";
			if ( isset( $_POST[$visibility_key] ) ) {
				$visibility = sanitize_text_field( $_POST[$visibility_key] );
				
				// IMPORTANT: Save the visibility setting ONLY for this specific user
				xprofile_set_field_visibility_level( $field_id, $user_id, $visibility );
				
				// Ensure custom visibility is allowed on the field itself
				bp_xprofile_update_meta( $field_id, 'field', 'allow_custom_visibility', 'allowed' );
				
				// Log successful save for debugging
				error_log( "User-specific location visibility for user {$user_id}, field {$field_id} saved as {$visibility}" );
			}
			
			// Add direct database update for field_id = 4
			if ( $field_id == 4 ) {
				// Use Database class
				$db = \LABGENZ_CM\Database\Database::get_instance();
				if ( !is_object($db) ) {
					wp_die('Database class not found');
					return;
				}
				
				$saved = $db->save_user_field_visibility( $field_id, $user_id, $visibility );
				if ( !$saved ) {
					error_log( "Error saving visibility to database for user {$user_id}, field {$field_id}" );
				}
			}
		}
	}
	
	/**
	 * Save field visibility when an individual field is updated
	 *
	 * @param int   $field_id The field ID
	 * @param mixed $value    The field value
	 * @param int   $user_id  The user ID
	 */
	public function save_individual_field_visibility( $field_id, $value, $user_id = null ) {
		// Ensure required BuddyPress functions exist
		$missing = [];
		foreach ( [ 'xprofile_set_field_visibility_level', 'xprofile_get_field' ] as $fn ) {
			if ( ! function_exists( $fn ) ) {
				$missing[] = $fn;
			}
		}
		if ( ! empty( $missing ) ) {
			wp_die(
				sprintf(
					'Fatal: missing functions: %s',
					implode( ', ', $missing )
				)
			);
		}
		
		// Get user ID if not provided
		if ( empty( $user_id ) ) {
			$user_id = get_current_user_id();
			if ( empty( $user_id ) ) {
				return;
			}
		}
		
		// Check if this is a location field
		$field = xprofile_get_field( $field_id );
		if ( !$field || !is_object( $field ) || !isset( $field->type ) || $field->type !== 'location' ) {
			return;
		}
		
		// Check if visibility is set for this field
		$visibility_key = "field_{$field_id}_visibility";
		if ( isset( $_POST[$visibility_key] ) ) {
			$visibility = sanitize_text_field( $_POST[$visibility_key] );
			
			// Save visibility level ONLY for this user
			xprofile_set_field_visibility_level( $field_id, $user_id, $visibility );
			
			// Make sure the field allows custom visibility
			bp_xprofile_update_meta( $field_id, 'field', 'allow_custom_visibility', 'allowed' );
			
			// Log successful save for debugging
			error_log( "Individual user visibility for user {$user_id}, field {$field_id} saved as {$visibility}" );
		}
		
		// Add direct database update for field_id = 4
		if ( $field_id == 4 ) {
			// Use Database class
			$db = \LABGENZ_CM\Database\Database::get_instance();
			if ( !is_object($db) ) {
				wp_die('Database class not found');
				return;
			}
			
			$saved = $db->save_user_field_visibility( $field_id, $user_id, $visibility );
			if ( !$saved ) {
				error_log( "Error saving individual visibility to database for user {$user_id}, field {$field_id}" );
			}
		}
	}
	
	/**
	 * Debug current visibility settings for this user's profile
	 */
	private function debug_current_visibility_settings() {
		if (!function_exists('xprofile_get_field')) {
			return;
		}
		
		$user_id = bp_displayed_user_id();
		if (empty($user_id)) {
			$user_id = get_current_user_id();
		}
		
		if (empty($user_id)) {
			return;
		}
		
		// Log the user we're debugging
		error_log("Debugging visibility for user ID: {$user_id}");
		
		// Find location fields
		if (function_exists('bp_xprofile_get_fields_by_field_type')) {
			$fields = bp_xprofile_get_fields_by_field_type('location');
		} else {
			// Try a different approach if the helper function isn't available
			global $wpdb, $bp;
			if (isset($bp->profile->table_name_fields)) {
				$table_name = $bp->profile->table_name_fields;
				$sql = $wpdb->prepare("SELECT * FROM {$table_name} WHERE type = %s", 'location');
				$fields = $wpdb->get_results($sql);
			} else {
				error_log("Could not locate BuddyPress profile fields table");
				return;
			}
		}
		
		if (empty($fields)) {
			error_log("No location fields found");
			return;
		}
		
		foreach ($fields as $field) {
			$field_id = $field->id;
			
			// Get field's default visibility from meta
			$field_default = bp_xprofile_get_meta($field_id, 'field', 'default_visibility');
			
			// Check if custom visibility is allowed
			$allow_custom = bp_xprofile_get_meta($field_id, 'field', 'allow_custom_visibility');
			
			// Check if we can get user visibility
			$user_visibility = "unknown";
			// Use buddyboss function if available
			// if (function_exists('bp_get_profile_field_visibility_level')) {
				$user_visibility = bp_get_the_profile_field_visibility_level($field_id, $user_id);
			// }
			
			error_log("Field #{$field_id} visibility: user={$user_visibility}, default={$field_default}, allow_custom={$allow_custom}");
		}
	}
}

// Initialize the class
ProfileLocationHandler::get_instance();
