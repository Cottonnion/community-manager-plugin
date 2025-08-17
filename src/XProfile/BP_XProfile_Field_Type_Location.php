<?php
namespace LABGENZ_CM\XProfile;

use LABGENZ_CM\Database\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom Location Field Type for BuddyPress xProfile
 *
 * This field type provides location input with geocoding and map integration
 */
class BP_XProfile_Field_Type_Location extends \BP_XProfile_Field_Type {

	/**
	 * Constructor for the location field type
	 */
	public function __construct() {
		parent::__construct();

		$this->category = _x( 'Single Fields', 'xprofile field type category', 'buddypress' );
		$this->name     = _x( 'Location with Map', 'xprofile field type', 'buddypress' );

		$this->accepts_null_value = true;
		$this->supports_options   = false;

		$this->set_format( '/^.+$/', 'replace' );

		/**
		 * Fires inside __construct() method for BP_XProfile_Field_Type_Location class.
		 */
		do_action( 'bp_xprofile_field_type_location', $this );
	}

	/**
	 * Initialize hooks (called from the handler)
	 */
	public static function init_hooks() {
		// Add custom actions
		add_action( 'wp_ajax_geocode_location', [ __CLASS__, 'ajax_geocode_location' ] );
		add_action( 'wp_ajax_nopriv_geocode_location', [ __CLASS__, 'ajax_geocode_location' ] );

		// Add location-specific visibility levels using the proper filter
		add_filter( 'bp_xprofile_get_visibility_levels', [ __CLASS__, 'add_location_visibility_levels' ] );

		// Filter to control which visibility options show for which field types
		add_filter( 'bp_xprofile_field_get_visibility_level_options', [ __CLASS__, 'filter_field_visibility_options' ], 10, 2 );

		// Handle hiding fields based on custom visibility levels
		add_filter( 'bp_xprofile_get_hidden_field_types_for_user', [ __CLASS__, 'handle_location_visibility' ], 10, 3 );

		// Clear offset when location is updated
		add_action( 'xprofile_updated_profile', [ __CLASS__, 'handle_profile_update' ], 10, 5 );

		// Set the default visibility level based on user's saved preference
		add_filter( 'bp_get_the_profile_field_visibility_level', [ __CLASS__, 'set_default_visibility_level' ], 10, 5 );
	}

	/**
	 * Filter to set the default visibility level based on user's saved preference
	 *
	 * @param string $level    The visibility level to apply.
	 * @param int    $field_id ID of the field being rendered (optional).
	 * @param int    $user_id  ID of the user field is being rendered for (optional).
	 * @param object $field    The BP_XProfile_Field object (optional).
	 * @param bool   $on_form  Whether this is being rendered on a form or not (optional).
	 * @return string          The visibility level.
	 */
	public static function set_default_visibility_level( $level ) {
		// Get all arguments
		$args = func_get_args();

		// Check if we have all the expected parameters
		if ( count( $args ) < 3 ) {
			error_log( 'set_default_visibility_level called with insufficient parameters: ' . count( $args ) );

			// Try to get the field ID and user ID from the context
			$field_id = 0;
			$user_id  = 0;

			// Try to get field ID from the current context
			if ( function_exists( 'bp_get_the_profile_field_id' ) ) {
				$field_id = bp_get_the_profile_field_id();
			}

			// Try to get user ID from the current context
			if ( function_exists( 'bp_displayed_user_id' ) ) {
				$user_id = bp_displayed_user_id();
			}

			if ( empty( $user_id ) ) {
				$user_id = get_current_user_id();
			}

			// If we couldn't determine field_id or user_id, return the original level
			if ( empty( $field_id ) || empty( $user_id ) ) {
				return $level;
			}

			// Get the field object
			$field = null;
			if ( function_exists( 'xprofile_get_field' ) ) {
				$field = xprofile_get_field( $field_id );
			}

			// If we couldn't get the field object, return the original level
			if ( ! $field ) {
				return $level;
			}
		} else {
			// We have all parameters
			$field_id = isset( $args[1] ) ? $args[1] : 0;
			$user_id  = isset( $args[2] ) ? $args[2] : 0;
			$field    = isset( $args[3] ) ? $args[3] : null;
		}

		// If we have a field object, check its type
		if ( $field && isset( $field->type ) && $field->type !== 'location' ) {
			return $level;
		}

		// Only apply to field_id = 4 for now
		if ( $field_id != 4 ) {
			return $level;
		}

		// Try to get the saved visibility from database
		$db = \LABGENZ_CM\Database\Database::get_instance();
		if ( ! is_object( $db ) ) {
			error_log( 'Database class not found' );
			return $level;
		}

		$saved_visibility = $db->get_user_field_visibility( $field_id, $user_id );

		// Debug
		error_log( "set_default_visibility_level called for field {$field_id}, user {$user_id}: saved={$saved_visibility}, original={$level}" );

		// If found, use it as the default
		if ( ! empty( $saved_visibility ) ) {
			return $saved_visibility;
		}

		return $level;
	}

	/**
	 * CORRECTED: Add custom visibility levels for location fields using proper filter
	 */
	public static function add_location_visibility_levels( $levels ) {
		// Check if we're in a context where we can determine the current field
		// This could be called from various places, so we need to be flexible

		// Try to get field ID from global BP context
		$field_id = 0;

		// Check if we're in the loop and can get the current field
		if ( function_exists( 'bp_get_the_profile_field_id' ) ) {
			$field_id = bp_get_the_profile_field_id();
		}

		// Fallback: check admin context
		if ( ! $field_id && isset( $_GET['field_id'] ) ) {
			$field_id = (int) $_GET['field_id'];
		}

		// Get the field object to check its type
		$field = xprofile_get_field( $field_id );

		// Only add custom visibility levels for location field types
		if ( $field && $field->type === 'location' ) {
			// Clear existing visibility levels if we only want our custom ones
			$custom_levels = [];

			// Add "Exact Location" visibility level
			$custom_levels['exact_location'] = [
				'id'    => 'exact_location',
				'label' => __( 'Exact Location', 'buddypress' ),
			];

			// Add "Privacy Offset" visibility level
			$custom_levels['privacy_offset'] = [
				'id'    => 'privacy_offset',
				'label' => __( 'Privacy Offset', 'buddypress' ),
			];

			// Add "Hidden" visibility level
			$custom_levels['hidden'] = [
				'id'    => 'hidden',
				'label' => __( 'Hidden', 'buddypress' ),
			];

			// Replace all standard visibility levels with our custom ones
			return $custom_levels;
		}

		return $levels;
	}

	/**
	 * Filter which visibility options are shown for which field types
	 */
	public static function filter_field_visibility_options( $options, $field ) {
		// If this is a location field, only show our custom visibility options
		if ( $field && $field->type === 'location' ) {
			// Only keep our custom options
			$filtered_options = [];

			// Check if our custom options exist in the available options
			foreach ( $options as $option ) {
				if ( $option->id === 'exact_location' || $option->id === 'privacy_offset' || $option->id === 'hidden' ) {
					// Add custom class to the option
					$option->class      = 'visibility-option-' . $option->id;
					$filtered_options[] = $option;
				}
			}

			// If we have our custom options, return only them
			if ( ! empty( $filtered_options ) ) {
				return $filtered_options;
			}
		} elseif ( $field && $field->type !== 'location' ) {
			// For non-location fields, filter out our custom visibility options
			$filtered_options = [];

			foreach ( $options as $option ) {
				if ( $option->id !== 'exact_location' && $option->id !== 'privacy_offset' && $option->id !== 'hidden' ) {
					$filtered_options[] = $option;
				}
			}

			return $filtered_options;
		}

		return $options;
	}

	/**
	 * CORRECTED: Handle location visibility logic using the proper hook
	 * This function should return an array of visibility levels to hide for the given user
	 */
	public static function handle_location_visibility( $hidden_levels, $user_id, $field_id ) {
		// We don't actually want to hide fields, just modify the data
		// This hook is for completely hiding fields, not for modifying display
		return $hidden_levels;
	}

	/**
	 * CORRECTED: Filter location field display data to apply privacy offset
	 */
	public static function filter_location_display( $value, $type, $field_id ) {
		// Check if this is a location field
		$field = xprofile_get_field( $field_id );
		if ( ! $field || $field->type !== 'location' ) {
			return $value;
		}

		$user_id         = bp_displayed_user_id();
		$current_user_id = get_current_user_id();

		// If viewing own profile, show exact location
		if ( $current_user_id === $user_id ) {
			return $value;
		}

		// Get field visibility setting
		// $field_visibility = bp_xprofile_get_meta( $field_id, 'field', 'default_visibility' );

		// Apply privacy modifications if needed
		if ( $field_visibility === 'privacy_offset' ) {
			// Generalize the address for privacy
			return self::generalize_address( $value );
		}

		return $value;
	}

	/**
	 * CORRECTED: Filter location field data to apply privacy offset for coordinates
	 */
	public static function filter_location_data( $value, $field_id, $user_id ) {
		// Check if this is a location field
		$field = xprofile_get_field( $field_id );
		if ( ! $field || $field->type !== 'location' ) {
			return $value;
		}

		$current_user_id = get_current_user_id();

		// If viewing own profile, show exact location
		if ( $current_user_id === $user_id ) {
			return $value;
		}

		// Get field visibility setting
		// $field_visibility = bp_xprofile_get_meta( $field_id, 'field', 'default_visibility' );

		// Apply privacy modifications if needed
		if ( $field_visibility === 'privacy_offset' ) {
			// The address is already filtered by filter_location_display
			// Here we could modify coordinates if they're being accessed directly
			return self::generalize_address( $value );
		}

		return $value;
	}

	/**
	 * Handle profile updates to clear privacy offsets
	 */
	public static function handle_profile_update( $user_id, $posted_fields, $errors, $old_values, $new_values ) {
		foreach ( $posted_fields as $field_id => $value ) {
			$field = xprofile_get_field( $field_id );
			if ( $field && $field->type === 'location' ) {
				self::clear_privacy_offset( $field_id, $user_id );
			}
		}
	}

	/**
	 * Output the edit field HTML for this field type
	 */
	public function edit_field_html( array $raw_properties = [] ) {
		// Get the field value
		$field_value = bp_get_the_profile_field_edit_value();

		// Get stored coordinates
		$field_id = bp_get_the_profile_field_id();
		$user_id  = bp_displayed_user_id();

		if ( empty( $user_id ) ) {
			$user_id = bp_loggedin_user_id();
		}

		$latitude  = get_user_meta( $user_id, "field_{$field_id}_latitude", true );
		$longitude = get_user_meta( $user_id, "field_{$field_id}_longitude", true );

		// Generate unique IDs for this field
		$field_name    = bp_get_the_profile_field_input_name();
		$field_id_attr = sanitize_title( $field_name );

		$html_properties = [
			'type'        => 'text',
			'name'        => $field_name,
			'id'          => $field_id_attr,
			'value'       => $field_value,
			'placeholder' => __( 'Enter your location (e.g., New York, NY)', 'buddypress' ),
			'class'       => 'location-field-input',
		];

		// Merge with any passed properties
		$html_properties = array_merge( $html_properties, $raw_properties );

		// Build attribute string
		$attributes = '';
		foreach ( $html_properties as $attr => $value ) {
			if ( $value !== '' && $value !== false ) {
				$attributes .= sprintf( ' %s="%s"', esc_attr( $attr ), esc_attr( $value ) );
			}
		}
		?>
		
		<div class="location-field-container">
			<legend id="<?php echo esc_attr( $field_id_attr ); ?>-legend">
				<?php bp_the_profile_field_name(); ?>
				<?php bp_the_profile_field_required_label(); ?>
			</legend>
			
			<?php do_action( bp_get_the_profile_field_errors_action() ); ?>
			
			<div class="location-input-wrapper">
				<input <?php echo $attributes; ?> 
						aria-labelledby="<?php echo esc_attr( $field_id_attr ); ?>-legend" 
						aria-describedby="<?php echo esc_attr( $field_id_attr ); ?>-description" />
				
				<div class="location-autocomplete" id="<?php echo esc_attr( $field_id_attr ); ?>-autocomplete"></div>
				
				<button type="button" class="location-detect-btn" id="<?php echo esc_attr( $field_id_attr ); ?>-detect">
					<?php _e( 'Detect My Location', 'buddypress' ); ?>
				</button>

					<?php
					// Show the label of the selected visibility option for this field (field_id = 4)
					if ( $field_id == 4 ) {
						?>
						<p class="field-visibility-settings-toggle field-visibility-settings-header" id="field-visibility-settings-toggle-<?php echo esc_attr( $field_id ); ?>">
							<span class="current-visibility-level">
								<?php
								// Get the selected visibility value for this user/field
								$selected_visibility = bp_get_the_profile_field_visibility_level();

								// Map value to label
								$visibility_labels = [
									'exact_location' => __( 'Exact Location', 'buddypress' ),
									'privacy_offset' => __( 'Privacy Offset', 'buddypress' ),
									'hidden'         => __( 'Hidden', 'buddypress' ),
								];

								// Display the label for the selected visibility level
								echo isset( $visibility_labels[ $selected_visibility ] ) ? esc_html( $visibility_labels[ $selected_visibility ] ) : esc_html__( 'Default', 'buddypress' );
								?>
							</span>
							<button class="visibility-toggle-link button" type="button" aria-expanded="false">Change</button>
						</p>
						<?php
					}
					?>
			</div>
			
			<!-- Hidden fields for coordinates -->
			<input type="hidden" name="<?php echo esc_attr( $field_name ); ?>_latitude" 
					id="<?php echo esc_attr( $field_id_attr ); ?>_latitude" 
					value="<?php echo esc_attr( $latitude ); ?>" />
			<input type="hidden" name="<?php echo esc_attr( $field_name ); ?>_longitude" 
					id="<?php echo esc_attr( $field_id_attr ); ?>_longitude" 
					value="<?php echo esc_attr( $longitude ); ?>" />
			
			<?php
			// Get the current field visibility setting
			$field_visibility = bp_xprofile_get_meta( $field_id, 'field', 'default_visibility' );
			$show_map         = true; // Set to false to hide map for all visibility options
			?>
			
			<!-- Map preview - Only show based on visibility setting -->
			<?php if ( $show_map ) : ?>
				<div class="location-map-preview" id="<?php echo esc_attr( $field_id_attr ); ?>-map" 
					style="height: 200px; margin-top: 10px; display: <?php echo ( $latitude && $longitude ) ? 'block' : 'none'; ?>;"></div>
			<?php endif; ?>
			
			<!-- Coordinates display -->
			<?php if ( $latitude && $longitude ) : ?>
				<div class="location-coordinates" id="<?php echo esc_attr( $field_id_attr ); ?>-coordinates">
					<small><?php printf( __( 'Coordinates: %1$s, %2$s', 'buddypress' ), $latitude, $longitude ); ?></small>
				</div>
			<?php endif; ?>
			
			<?php if ( bp_get_the_profile_field_description() ) : ?>
				<p class="description" id="<?php echo esc_attr( $field_id_attr ); ?>-description">
					<?php bp_the_profile_field_description(); ?>
				</p>
			<?php endif; ?>
		</div>
		
		<?php
	}

	/**
	 * Output the admin form HTML for this field type
	 */
	public function admin_field_html( array $raw_properties = [] ) {
		$html_properties = [
			'type'        => 'text',
			'name'        => bp_get_the_profile_field_input_name(),
			'id'          => bp_get_the_profile_field_input_name(),
			'value'       => bp_get_the_profile_field_edit_value(),
			'placeholder' => __( 'Enter location...', 'buddypress' ),
		];

		// Fetch the default visibility value from the database for field_id = 4
		$field_id = 4;
		$user_id  = get_current_user_id();
		if ( empty( $user_id ) ) {
			$user_id = bp_loggedin_user_id();
		}

		$default_visibility = null;
		if ( $user_id ) {
			global $wpdb;
			$table_name         = $wpdb->prefix . 'bb_xprofile_visibility';
			$default_visibility = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT value FROM $table_name WHERE field_id = %d AND user_id = %d",
					$field_id,
					$user_id
				)
			);
		}

		// Use the fetched value as the default selected option
		if ( $default_visibility ) {
			$html_properties['value'] = $default_visibility;
		}

		$html_properties = array_merge( $html_properties, $raw_properties );

		// Build attribute string
		$attributes = '';
		foreach ( $html_properties as $attr => $value ) {
			if ( $value !== '' && $value !== false ) {
				$attributes .= sprintf( ' %s="%s"', esc_attr( $attr ), esc_attr( $value ) );
			}
		}
		?>
		
		<legend id="<?php bp_the_profile_field_input_name(); ?>-1">
			<?php bp_the_profile_field_name(); ?>
			<?php bp_the_profile_field_required_label(); ?>
		</legend>
		
		<?php do_action( bp_get_the_profile_field_errors_action() ); ?>
		
		<input <?php echo $attributes; ?> 
				aria-labelledby="<?php bp_the_profile_field_input_name(); ?>-1" 
				aria-describedby="<?php bp_the_profile_field_input_name(); ?>-3" />
		
		<?php if ( bp_get_the_profile_field_description() ) : ?>
			<p class="description" id="<?php bp_the_profile_field_input_name(); ?>-3">
				<?php bp_get_the_profile_field_description(); ?>
			</p>
		<?php endif; ?>
		
		<?php
	}

	/**
	 * Modify submitted values before validation
	 */
	public static function pre_validate_filter( $field_value, $field_id = null ) {
		// Store coordinates when the field is saved
		if ( ! empty( $_POST[ "field_{$field_id}_latitude" ] ) && ! empty( $_POST[ "field_{$field_id}_longitude" ] ) ) {
			$user_id = bp_displayed_user_id();

			if ( empty( $user_id ) ) {
				$user_id = bp_loggedin_user_id();
			}

			if ( $user_id ) {
				update_user_meta( $user_id, "field_{$field_id}_latitude", sanitize_text_field( $_POST[ "field_{$field_id}_latitude" ] ) );
				update_user_meta( $user_id, "field_{$field_id}_longitude", sanitize_text_field( $_POST[ "field_{$field_id}_longitude" ] ) );

				// Also store in common format for compatibility
				update_user_meta( $user_id, 'latitude', sanitize_text_field( $_POST[ "field_{$field_id}_latitude" ] ) );
				update_user_meta( $user_id, 'longitude', sanitize_text_field( $_POST[ "field_{$field_id}_longitude" ] ) );
			}
		}

		return parent::pre_validate_filter( $field_value, $field_id );
	}


	/**
	 * AJAX handler for geocoding
	 */
	public static function ajax_geocode_location() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'location_field_nonce' ) ) {
			wp_send_json_error( [ 'message' => 'Invalid nonce' ] );
			return;
		}

		$query = isset( $_POST['query'] ) ? sanitize_text_field( $_POST['query'] ) : '';

		if ( empty( $query ) ) {
			wp_send_json_error( [ 'message' => 'No query provided' ] );
			return;
		}

		// Use Nominatim API for geocoding
		$url    = 'https://nominatim.openstreetmap.org/search';
		$params = [
			'q'              => $query,
			'format'         => 'json',
			'addressdetails' => 1,
			'limit'          => 5,
		];

		$response = wp_remote_get(
			add_query_arg( $params, $url ),
			[
				'timeout' => 10,
				'headers' => [
					'User-Agent' => 'BuddyPress Location Field',
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( [ 'message' => 'Geocoding service unavailable' ] );
			return;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data ) ) {
			wp_send_json_error( [ 'message' => 'No results found' ] );
			return;
		}

		$results = [];
		foreach ( $data as $item ) {
			$results[] = [
				'display_name' => $item['display_name'],
				'lat'          => $item['lat'],
				'lon'          => $item['lon'],
			];
		}

		wp_send_json_success( [ 'results' => $results ] );
	}

	/**
	 * CORRECTED: Get field value including coordinates with privacy handling
	 */
	public function get_field_value( $user_id, $field_id ) {
		$value           = xprofile_get_field_data( $field_id, $user_id );
		$current_user_id = get_current_user_id();

		// Get original coordinates
		$latitude  = get_user_meta( $user_id, "field_{$field_id}_latitude", true );
		$longitude = get_user_meta( $user_id, "field_{$field_id}_longitude", true );

		// Check if privacy offset should be applied
		$field_visibility = bp_xprofile_get_meta( $field_id, 'field', 'default_visibility' );

		// Apply privacy offset if not viewing own profile and privacy offset is set
		if ( $current_user_id !== $user_id && $field_visibility === 'privacy_offset' && $latitude && $longitude ) {
			$offset_coords = $this->apply_privacy_offset( $latitude, $longitude, $user_id, $field_id );
			$latitude      = $offset_coords['latitude'];
			$longitude     = $offset_coords['longitude'];

			// Also modify the address to be more general for privacy
			$value = $this->generalize_address( $value );
		}

		return [
			'address'   => $value,
			'latitude'  => $latitude,
			'longitude' => $longitude,
		];
	}

	/**
	 * Apply privacy offset to coordinates
	 */
	private function apply_privacy_offset( $latitude, $longitude, $user_id, $field_id ) {
		// Check if we already have a stored offset for this user/field
		$offset_lat = get_user_meta( $user_id, "field_{$field_id}_offset_latitude", true );
		$offset_lon = get_user_meta( $user_id, "field_{$field_id}_offset_longitude", true );

		if ( empty( $offset_lat ) || empty( $offset_lon ) ) {
			// Generate random offset (0.5-2km radius)
			$offset_distance = ( rand( 500, 2000 ) / 1000 ); // 0.5 to 2 km
			$offset_angle    = rand( 0, 360 ) * ( M_PI / 180 ); // Random angle in radians

			// Convert to lat/lon offset (approximate)
			$lat_offset = ( $offset_distance / 111.32 ) * cos( $offset_angle ); // 1 degree lat ≈ 111.32 km
			$lon_offset = ( $offset_distance / ( 111.32 * cos( deg2rad( $latitude ) ) ) ) * sin( $offset_angle );

			$offset_lat = $latitude + $lat_offset;
			$offset_lon = $longitude + $lon_offset;

			// Store the offset for consistency
			update_user_meta( $user_id, "field_{$field_id}_offset_latitude", $offset_lat );
			update_user_meta( $user_id, "field_{$field_id}_offset_longitude", $offset_lon );
		}

		return [
			'latitude'  => $offset_lat,
			'longitude' => $offset_lon,
		];
	}

	/**
	 * Generalize address for privacy
	 */
	private static function generalize_address( $address ) {
		if ( empty( $address ) ) {
			return $address;
		}

		// Remove specific street numbers and apartment details
		$address = preg_replace( '/\b\d+[a-zA-Z]?\b/', '', $address ); // Remove numbers
		$address = preg_replace( '/\b(apt|apartment|suite|unit|#)\s*\w+/i', '', $address ); // Remove apt numbers
		$address = preg_replace( '/\s+/', ' ', $address ); // Clean up extra spaces
		$address = trim( $address, ', ' ); // Clean up commas and spaces

		return $address;
	}

	/**
	 * Clear offset when location is updated
	 */
	public static function clear_privacy_offset( $field_id, $user_id ) {
		delete_user_meta( $user_id, "field_{$field_id}_offset_latitude" );
		delete_user_meta( $user_id, "field_{$field_id}_offset_longitude" );
	}
}