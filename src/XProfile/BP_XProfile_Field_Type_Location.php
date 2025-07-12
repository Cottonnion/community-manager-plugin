<?php
namespace LABGENZ_CM\XProfile;

if ( ! defined( 'ABSPATH' ) ) exit;

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
        $this->supports_options = false;
        
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
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_geocode_location', array( __CLASS__, 'ajax_geocode_location' ) );
        add_action( 'wp_ajax_nopriv_geocode_location', array( __CLASS__, 'ajax_geocode_location' ) );
    }
    
    /**
     * Output the edit field HTML for this field type
     */
    public function edit_field_html( array $raw_properties = array() ) {
        // Get the field value
        $field_value = bp_get_the_profile_field_edit_value();
        
        // Get stored coordinates
        $field_id = bp_get_the_profile_field_id();
        $user_id = bp_displayed_user_id();
        
        if ( empty( $user_id ) ) {
            $user_id = bp_loggedin_user_id();
        }
        
        $latitude = get_user_meta( $user_id, "field_{$field_id}_latitude", true );
        $longitude = get_user_meta( $user_id, "field_{$field_id}_longitude", true );
        
        // Generate unique IDs for this field
        $field_name = bp_get_the_profile_field_input_name();
        $field_id_attr = sanitize_title( $field_name );
        
        $html_properties = array(
            'type'        => 'text',
            'name'        => $field_name,
            'id'          => $field_id_attr,
            'value'       => $field_value,
            'placeholder' => __( 'Enter your location (e.g., New York, NY)', 'buddypress' ),
            'class'       => 'location-field-input',
        );
        
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
            </div>
            
            <!-- Hidden fields for coordinates -->
            <input type="hidden" name="<?php echo esc_attr( $field_name ); ?>_latitude" 
                   id="<?php echo esc_attr( $field_id_attr ); ?>_latitude" 
                   value="<?php echo esc_attr( $latitude ); ?>" />
            <input type="hidden" name="<?php echo esc_attr( $field_name ); ?>_longitude" 
                   id="<?php echo esc_attr( $field_id_attr ); ?>_longitude" 
                   value="<?php echo esc_attr( $longitude ); ?>" />
            
            <!-- Map preview -->
            <div class="location-map-preview" id="<?php echo esc_attr( $field_id_attr ); ?>-map" 
                 style="height: 200px; margin-top: 10px; display: <?php echo ( $latitude && $longitude ) ? 'block' : 'none'; ?>;"></div>
            
            <!-- Coordinates display -->
            <?php if ( $latitude && $longitude ) : ?>
                <div class="location-coordinates" id="<?php echo esc_attr( $field_id_attr ); ?>-coordinates">
                    <small><?php printf( __( 'Coordinates: %s, %s', 'buddypress' ), $latitude, $longitude ); ?></small>
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
    public function admin_field_html( array $raw_properties = array() ) {
        $html_properties = array(
            'type' => 'text',
            'name' => bp_get_the_profile_field_input_name(),
            'id'   => bp_get_the_profile_field_input_name(),
            'value' => bp_get_the_profile_field_edit_value(),
            'placeholder' => __( 'Enter location...', 'buddypress' ),
        );
        
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
                <?php bp_the_profile_field_description(); ?>
            </p>
        <?php endif; ?>
        
        <?php
    }
    
    /**
     * Modify submitted values before validation
     */
    public static function pre_validate_filter( $field_value, $field_id = null ) {
        // Store coordinates when the field is saved
        if ( ! empty( $_POST["field_{$field_id}_latitude"] ) && ! empty( $_POST["field_{$field_id}_longitude"] ) ) {
            $user_id = bp_displayed_user_id();
            
            if ( empty( $user_id ) ) {
                $user_id = bp_loggedin_user_id();
            }
            
            if ( $user_id ) {
                update_user_meta( $user_id, "field_{$field_id}_latitude", sanitize_text_field( $_POST["field_{$field_id}_latitude"] ) );
                update_user_meta( $user_id, "field_{$field_id}_longitude", sanitize_text_field( $_POST["field_{$field_id}_longitude"] ) );
                
                // Also store in common format for compatibility
                update_user_meta( $user_id, 'latitude', sanitize_text_field( $_POST["field_{$field_id}_latitude"] ) );
                update_user_meta( $user_id, 'longitude', sanitize_text_field( $_POST["field_{$field_id}_longitude"] ) );
            }
        }
        
        return parent::pre_validate_filter( $field_value, $field_id );
    }
    
    /**
     * Enqueue scripts and styles for this field type
     */
    public static function enqueue_scripts() {
        // Only enqueue on profile edit pages
        if ( ! function_exists( 'bp_is_user_profile_edit' ) || 
             ( ! bp_is_user_profile_edit() && ! bp_is_register_page() ) ) {
            return;
        }
        
        // Enqueue Leaflet for map display
        wp_enqueue_style( 
            'leaflet-css', 
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
            array(),
            '1.9.4'
        );
        
        wp_enqueue_script( 
            'leaflet-js', 
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', 
            array(), 
            '1.9.4', 
            true 
        );
        
        // Enqueue our custom script
        wp_enqueue_script( 
            'location-field-js', 
            LABGENZ_CM_URL . 'public/assets/js/location-field.js', 
            array( 'jquery', 'leaflet-js' ), 
            LABGENZ_CM_VERSION, 
            true 
        );
        
        // Localize script with AJAX data
        wp_localize_script( 
            'location-field-js', 
            'LocationFieldData', 
            array(
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'location_field_nonce' ),
                'strings' => array(
                    'detecting' => __( 'Detecting...', 'buddypress' ),
                    'detect_failed' => __( 'Could not detect your location. Please enter manually.', 'buddypress' ),
                    'geocode_failed' => __( 'Could not find location. Please try a different search.', 'buddypress' ),
                    'searching' => __( 'Searching...', 'buddypress' ),
                ),
            )
        );
        
        // Add custom CSS
        wp_add_inline_style( 'leaflet-css', self::get_field_css() );
    }
    
    /**
     * AJAX handler for geocoding
     */
    public static function ajax_geocode_location() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'location_field_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
            return;
        }
        
        $query = isset( $_POST['query'] ) ? sanitize_text_field( $_POST['query'] ) : '';
        
        if ( empty( $query ) ) {
            wp_send_json_error( array( 'message' => 'No query provided' ) );
            return;
        }
        
        // Use Nominatim API for geocoding
        $url = 'https://nominatim.openstreetmap.org/search';
        $params = array(
            'q' => $query,
            'format' => 'json',
            'addressdetails' => 1,
            'limit' => 5,
        );
        
        $response = wp_remote_get( add_query_arg( $params, $url ), array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'BuddyPress Location Field',
            ),
        ) );
        
        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => 'Geocoding service unavailable' ) );
            return;
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( empty( $data ) ) {
            wp_send_json_error( array( 'message' => 'No results found' ) );
            return;
        }
        
        $results = array();
        foreach ( $data as $item ) {
            $results[] = array(
                'display_name' => $item['display_name'],
                'lat' => $item['lat'],
                'lon' => $item['lon'],
            );
        }
        
        wp_send_json_success( array( 'results' => $results ) );
    }
    
    /**
     * Get custom CSS for the field
     */
    private static function get_field_css() {
        return '
            .location-field-container {
                margin-bottom: 20px;
            }
            
            .location-input-wrapper {
                position: relative;
            }
            
            .location-field-input {
                width: 100%;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            
            .location-autocomplete {
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: white;
                border: 1px solid #ddd;
                border-top: none;
                max-height: 200px;
                overflow-y: auto;
                z-index: 1000;
                display: none;
            }
            
            .location-autocomplete-item {
                padding: 10px;
                cursor: pointer;
                border-bottom: 1px solid #eee;
            }
            
            .location-autocomplete-item:hover,
            .location-autocomplete-item.active {
                background-color: #f5f5f5;
            }
            
            .location-detect-btn {
                margin-top: 10px;
                padding: 8px 16px;
                background: #0073aa;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
            }
            
            .location-detect-btn:hover {
                background: #005a87;
            }
            
            .location-detect-btn:disabled {
                background: #ccc;
                cursor: not-allowed;
            }
            
            .location-map-preview {
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            
            .location-coordinates {
                margin-top: 5px;
                color: #666;
            }
        ';
    }
    
    /**
     * Get field value including coordinates
     */
    public function get_field_value( $user_id, $field_id ) {
        $value = xprofile_get_field_data( $field_id, $user_id );
        
        // Also return coordinates if available
        $latitude = get_user_meta( $user_id, "field_{$field_id}_latitude", true );
        $longitude = get_user_meta( $user_id, "field_{$field_id}_longitude", true );
        
        return array(
            'address' => $value,
            'latitude' => $latitude,
            'longitude' => $longitude,
        );
    }
}
