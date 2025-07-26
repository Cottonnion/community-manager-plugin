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

			// Enqueue our custom JS
			wp_enqueue_script(
				'profile-location-field-js',
				LABGENZ_CM_URL . 'public/assets/js/profile-location-field.js',
				[ 'jquery' ],
				'1.0.2',
				true
			);

			// Pass data to script
			wp_localize_script(
				'profile-location-field-js',
				'ProfileLocationData',
				[
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'save_location_coordinates_nonce' ),
				]
			);
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
}

// Initialize the class
ProfileLocationHandler::get_instance();
