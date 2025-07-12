<?php
namespace LABGENZ_CM\Groups;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handles member map functionality
 */
class MembersMapHandler {
    private static $instance = null;
    
    /**
     * Get singleton instance
     *
     * @return MembersMapHandler
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
        add_action( 'wp_ajax_get_group_members_location', array( $this, 'ajax_get_group_members_location' ) );
        add_action( 'wp_ajax_nopriv_get_group_members_location', array( $this, 'ajax_get_group_members_location' ) );
    }
    
    /**
     * AJAX handler for getting group members with location data
     */
    public function ajax_get_group_members_location() {
        // Basic validation
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'members-map-nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
            return;
        }
        
        if ( ! isset( $_POST['group_id'] ) || empty( $_POST['group_id'] ) ) {
            wp_send_json_error( array( 'message' => 'Group ID is required' ) );
            return;
        }
        
        $group_id = intval( $_POST['group_id'] );
        
        // Check if group exists
        $group = groups_get_group( $group_id );
        if ( ! $group ) {
            wp_send_json_error( array( 'message' => 'Group not found' ) );
            return;
        }
        
        // Get group members
        $members = $this->get_group_members_with_location( $group_id );
        
        // Add debug info to the response
        $response = array(
            'members' => $members,
            'debug' => array(
                'group_id' => $group_id,
                'group_name' => $group->name,
                'member_count' => count( $members ),
                'timestamp' => current_time( 'mysql' ),
                'user_id_with_location' => wp_get_current_user()->ID // Test with current user
            )
        );
        
        // Test with current user if no members found
        if ( empty( $members ) ) {
            $current_user_id = wp_get_current_user()->ID;
            $test_location = $this->get_member_location_data( $current_user_id );
            $response['debug']['current_user_location_test'] = $test_location;
        }
        
        // Return members data
        wp_send_json_success( $response );
    }
    
    /**
     * Get group members with location data
     *
     * @param int $group_id Group ID
     * @return array Array of members with location data
     */
    private function get_group_members_with_location( $group_id ) {
        $members_with_location = array();
        
        // Get group members
        $group_members = groups_get_group_members( array(
            'group_id' => $group_id,
            'per_page' => 999, // Get all members
            'exclude_admins_mods' => false,
        ) );
        
        if ( empty( $group_members['members'] ) ) {
            return $members_with_location;
        }
        
        // Loop through members
        foreach ( $group_members['members'] as $member ) {
            // Get member's location data (xprofile fields or user meta)
            $location_data = $this->get_member_location_data( $member->ID );
            
            // Skip if no location data
            if ( empty( $location_data ) ) {
                continue;
            }
            
            // Get member's role in the group
            $role = $this->get_member_role_in_group( $member->ID, $group_id );
            
            // Add member to array
            $members_with_location[] = array(
                'id' => $member->ID,
                'name' => bp_core_get_user_displayname( $member->ID ),
                'avatar' => bp_core_fetch_avatar( array(
                    'item_id' => $member->ID,
                    'type' => 'thumb',
                    'width' => 50,
                    'height' => 50,
                    'html' => true,
                ) ),
                'profile_url' => bp_core_get_user_domain( $member->ID ),
                'role' => $role,
                'location' => $location_data['address'],
                'latitude' => $location_data['latitude'],
                'longitude' => $location_data['longitude'],
            );
        }
        
        return $members_with_location;
    }
    
    /**
     * Get member's location data from xprofile fields or user meta
     *
     * @param int $user_id User ID
     * @return array|false Location data or false if not found
     */
    private function get_member_location_data( $user_id ) {
        // Default return array
        $location_data = array(
            'address' => '',
            'latitude' => '',
            'longitude' => '',
        );
        
        // Check meta keys in priority order (most specific first)
        $meta_key_pairs = array(
            array( 'bp_location_latitude', 'bp_location_longitude' ),
            array( 'latitude', 'longitude' ),
            array( 'field_4_latitude', 'field_4_longitude' ),
            array( 'gmw_lat', 'gmw_lng' ),
            array( 'geo_latitude', 'geo_longitude' ),
        );
        
        foreach ( $meta_key_pairs as $pair ) {
            $lat = get_user_meta( $user_id, $pair[0], true );
            $lng = get_user_meta( $user_id, $pair[1], true );
            
            if ( ! empty( $lat ) && ! empty( $lng ) && is_numeric( $lat ) && is_numeric( $lng ) ) {
                $location_data['latitude'] = (float) $lat;
                $location_data['longitude'] = (float) $lng;
                break;
            }
        }
        
        // Check for address in user meta
        $address_meta_keys = array(
            'bp_location',      // BuddyPress Location plugin
            'location',         // Generic location field
            'address',          // Generic address field
            'gmw_address',      // GeoMyWP plugin
            'geo_address',      // Other geo plugins
        );
        
        foreach ( $address_meta_keys as $meta_key ) {
            $address = get_user_meta( $user_id, $meta_key, true );
            
            if ( ! empty( $address ) && is_string( $address ) ) {
                $location_data['address'] = $address;
                break;
            }
        }
        
        // If we have coordinates but no address, set a default address
        if ( ( ! empty( $location_data['latitude'] ) && ! empty( $location_data['longitude'] ) ) && empty( $location_data['address'] ) ) {
            $location_data['address'] = sprintf( 
                'Location at %s, %s', 
                $location_data['latitude'], 
                $location_data['longitude'] 
            );
        }
        
        // Validate coordinates - ensure they are valid latitude/longitude values
        if ( ! empty( $location_data['latitude'] ) && ! empty( $location_data['longitude'] ) ) {
            $lat = (float) $location_data['latitude'];
            $lng = (float) $location_data['longitude'];
            
            // Check if coordinates are within valid ranges
            if ( $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 ) {
                return false;
            }
            
            // Ensure coordinates are not 0,0 (likely invalid)
            if ( $lat == 0 && $lng == 0 ) {
                return false;
            }
            
            // Convert to float to ensure proper format
            $location_data['latitude'] = $lat;
            $location_data['longitude'] = $lng;
        }
        
        // Return false if we don't have sufficient location data
        if ( empty( $location_data['latitude'] ) || empty( $location_data['longitude'] ) ) {
            return false;
        }
        
        return $location_data;
    }
    
    /**
     * Get member's role in a group
     *
     * @param int $user_id User ID
     * @param int $group_id Group ID
     * @return string Role (admin, mod, member)
     */
    private function get_member_role_in_group( $user_id, $group_id ) {
        if ( groups_is_user_admin( $user_id, $group_id ) ) {
            return 'admin';
        } elseif ( groups_is_user_mod( $user_id, $group_id ) ) {
            return 'mod';
        } else {
            return 'member';
        }
    }
}

// Initialize the class
MembersMapHandler::get_instance();
