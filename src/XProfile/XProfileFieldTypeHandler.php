<?php
namespace LABGENZ_CM\XProfile;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handles registration of custom xprofile field types
 */
class XProfileFieldTypeHandler {
    private static $instance = null;
    
    /**
     * Get singleton instance
     *
     * @return XProfileFieldTypeHandler
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
        // Register custom field types
        add_filter( 'bp_xprofile_get_field_types', array( $this, 'register_field_types' ) );
        
        // Handle field data saving
        add_action( 'xprofile_data_after_save', array( $this, 'save_location_data' ), 10, 1 );
        
        // Handle field data on profile update
        add_action( 'xprofile_updated_profile', array( $this, 'handle_profile_update' ), 10, 5 );
        
        // Initialize location field hooks
        add_action( 'init', array( $this, 'init_location_field_hooks' ), 20 );
    }
    
    /**
     * Initialize location field hooks
     */
    public function init_location_field_hooks() {
        if ( class_exists( '\LABGENZ_CM\XProfile\BP_XProfile_Field_Type_Location' ) ) {
            \LABGENZ_CM\XProfile\BP_XProfile_Field_Type_Location::init_hooks();
        }
    }
    
    /**
     * Register custom field types
     *
     * @param array $field_types Existing field types
     * @return array Modified field types
     */
    public function register_field_types( $field_types ) {
        $field_types['location'] = '\LABGENZ_CM\XProfile\BP_XProfile_Field_Type_Location';
        
        return $field_types;
    }
    
    /**
     * Save location data when xprofile data is saved
     *
     * @param BP_XProfile_ProfileData $data_obj The profile data object
     */
    public function save_location_data( $data_obj ) {
        // Check if this is a location field
        $field = xprofile_get_field( $data_obj->field_id );
        
        if ( ! $field || $field->type !== 'location' ) {
            return;
        }
        
        // Save coordinates if they were submitted
        $latitude_key = "field_{$data_obj->field_id}_latitude";
        $longitude_key = "field_{$data_obj->field_id}_longitude";
        
        if ( isset( $_POST[ $latitude_key ] ) && isset( $_POST[ $longitude_key ] ) ) {
            $latitude = sanitize_text_field( $_POST[ $latitude_key ] );
            $longitude = sanitize_text_field( $_POST[ $longitude_key ] );
            
            if ( ! empty( $latitude ) && ! empty( $longitude ) ) {
                // Save field-specific coordinates
                update_user_meta( $data_obj->user_id, $latitude_key, $latitude );
                update_user_meta( $data_obj->user_id, $longitude_key, $longitude );
                
                // Also save generic coordinates for compatibility
                update_user_meta( $data_obj->user_id, 'latitude', $latitude );
                update_user_meta( $data_obj->user_id, 'longitude', $longitude );
                update_user_meta( $data_obj->user_id, 'location', $data_obj->value );
                
                // Save for GEO my WP compatibility
                update_user_meta( $data_obj->user_id, 'gmw_lat', $latitude );
                update_user_meta( $data_obj->user_id, 'gmw_lng', $longitude );
                update_user_meta( $data_obj->user_id, 'gmw_address', $data_obj->value );
                
                // Save for other location plugins compatibility
                update_user_meta( $data_obj->user_id, 'bp_location_latitude', $latitude );
                update_user_meta( $data_obj->user_id, 'bp_location_longitude', $longitude );
                update_user_meta( $data_obj->user_id, 'bp_location', $data_obj->value );
            }
        }
    }
    
    /**
     * Handle profile updates
     *
     * @param int   $user_id           User ID
     * @param array $posted_field_ids  Array of field IDs that were posted
     * @param bool  $errors            Whether errors occurred
     * @param array $old_values        Array of old field values
     * @param array $new_values        Array of new field values
     */
    public function handle_profile_update( $user_id, $posted_field_ids, $errors, $old_values, $new_values ) {
        // Additional handling if needed
        // This hook is called after all profile fields are updated
    }
    
    /**
     * Get location data for a user
     *
     * @param int $user_id User ID
     * @param int $field_id Field ID (optional)
     * @return array|false Location data or false if not found
     */
    public function get_user_location_data( $user_id, $field_id = null ) {
        $location_data = array();
        
        if ( $field_id ) {
            // Get field-specific location data
            $address = xprofile_get_field_data( $field_id, $user_id );
            $latitude = get_user_meta( $user_id, "field_{$field_id}_latitude", true );
            $longitude = get_user_meta( $user_id, "field_{$field_id}_longitude", true );
            
            if ( $address || ( $latitude && $longitude ) ) {
                $location_data = array(
                    'address' => $address,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                );
            }
        } else {
            // Get generic location data
            $address = get_user_meta( $user_id, 'location', true );
            $latitude = get_user_meta( $user_id, 'latitude', true );
            $longitude = get_user_meta( $user_id, 'longitude', true );
            
            if ( $address || ( $latitude && $longitude ) ) {
                $location_data = array(
                    'address' => $address,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                );
            }
        }
        
        return empty( $location_data ) ? false : $location_data;
    }
    
    /**
     * Get all location fields
     *
     * @return array Array of location field IDs
     */
    public function get_location_fields() {
        $location_fields = array();
        
        // Get all xprofile fields
        $groups = bp_xprofile_get_groups( array(
            'fetch_fields' => true,
        ) );
        
        if ( $groups ) {
            foreach ( $groups as $group ) {
                if ( ! empty( $group->fields ) ) {
                    foreach ( $group->fields as $field ) {
                        if ( $field->type === 'location' ) {
                            $location_fields[] = $field->id;
                        }
                    }
                }
            }
        }
        
        return $location_fields;
    }
}

// Initialize the handler
XProfileFieldTypeHandler::get_instance();
