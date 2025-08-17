<?php
namespace LABGENZ_CM\Groups;

use LABGENZ_CM\Groups\GroupMemberVisibility;
use LABGENZ_CM\Database\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles member map functionality with privacy protection
 */
class MembersMapHandler {
	private static $instance = null;

	// Privacy settings - configurable offset range in meters
	private const MIN_OFFSET_METERS = 500;  // Minimum offset distance
	private const MAX_OFFSET_METERS = 1000;  // Maximum offset distance

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
		add_action( 'wp_ajax_get_group_members_location', [ $this, 'ajax_get_group_members_location' ] );
		// add_action( 'wp_ajax_nopriv_get_group_members_location', [ $this, 'ajax_get_group_members_location' ] );
	}

	/**
	 * AJAX handler for getting group members with location data
	 */
	public function ajax_get_group_members_location() {
		// Basic validation
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'members-map-nonce' ) ) {
			wp_send_json_error( [ 'message' => 'Invalid nonce' ] );
			return;
		}

		if ( ! isset( $_POST['group_id'] ) || empty( $_POST['group_id'] ) ) {
			wp_send_json_error( [ 'message' => 'Group ID is required' ] );
			return;
		}

		$group_id = intval( $_POST['group_id'] );

		// Check if group exists
		$group = groups_get_group( $group_id );
		if ( ! $group ) {
			wp_send_json_error( [ 'message' => 'Group not found' ] );
			return;
		}

		// Get group members with privacy-protected locations
		$members = $this->get_group_members_with_location( $group_id );

		// Add debug info to the response
		$response = [
			'members' => $members,
		];

		// Test with current user if no members found
		if ( empty( $members ) ) {
			$current_user_id		= wp_get_current_user()->ID;
			$test_location			= $this->get_member_location_data( $current_user_id );
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
		try {
			$members_with_location = [];

		// Get group members
		$group_members = groups_get_group_members(
			[
				'group_id'            => $group_id,
				'per_page'            => 999, // Get all members
				'exclude_admins_mods' => false,
			]
		);

		if ( empty( $group_members['members'] ) ) {
			return $members_with_location;
		}

		// Loop through members
		foreach ( $group_members['members'] as $member ) {
			// Check user visibility
			$database = Database::get_instance();
			$field_id = 4;
			$visibility = $database->get_user_field_visibility($field_id, $member->ID);

			if ($visibility === 'hidden') {
				continue; // Skip hidden members
			}

			// Get member's location data (xprofile fields or user meta)
			$location_data = $this->get_member_location_data($member->ID);

			// Skip if no location data
			if (empty($location_data)) {
				continue;
			}

			// Apply privacy offset unless visibility is exact_location
			$privacy_coords = ($visibility === 'exact_location')
				? [
					'latitude' => $location_data['latitude'],
					'longitude' => $location_data['longitude'],
				]
				: $this->apply_privacy_offset(
					$location_data['latitude'],
					$location_data['longitude'],
					$member->ID
				);

			// Get member's role in the group
			$role = $this->get_member_role_in_group( $member->ID, $group_id );

			// Add member to array with privacy-protected coordinates
			$members_with_location[] = [
				'id'          => $member->ID,
				'name'        => bp_core_get_user_displayname( $member->ID ),
				'avatar'      => bp_core_fetch_avatar(
					[
						'item_id' => $member->ID,
						'type'    => 'thumb',
						'width'   => 50,
						'height'  => 50,
						'html'    => true,
					]
				),
				'profile_url' => bp_core_get_user_domain( $member->ID ),
				'role'        => $role,
				'latitude'    => $privacy_coords['latitude'],
				'longitude'   => $privacy_coords['longitude'],
			];
		}

		return $members_with_location;
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => 'Error retrieving members: ' . $e->getMessage() ] );
		} catch ( \Error $err ) {
			wp_send_json_error( [ 'message' => 'Error retrieving members: ' . $err->getMessage() ] );
		}
	}

	/**
	 * Apply privacy offset to coordinates
	 * 
	 * @param float $lat Original latitude
	 * @param float $lng Original longitude  
	 * @param int $user_id User ID for consistent offset
	 * @return array Offset coordinates
	 */
	private function apply_privacy_offset( $lat, $lng, $user_id ) {
		// Use user ID as seed for consistent but random offset per user
		mt_srand( $user_id );
		
		// Generate random distance within our range
		$distance_meters = mt_rand( self::MIN_OFFSET_METERS, self::MAX_OFFSET_METERS );
		
		// Generate random bearing (0-360 degrees)
		$bearing_degrees = mt_rand( 0, 360 );
		
		// Convert bearing to radians
		$bearing_rad = deg2rad( $bearing_degrees );
		
		// Earth's radius in meters
		$earth_radius = 6371000;
		
		// Convert original coordinates to radians
		$lat_rad = deg2rad( $lat );
		$lng_rad = deg2rad( $lng );
		
		// Calculate new coordinates using haversine formula
		$angular_distance = $distance_meters / $earth_radius;
		
		$new_lat_rad = asin(
			sin( $lat_rad ) * cos( $angular_distance ) +
			cos( $lat_rad ) * sin( $angular_distance ) * cos( $bearing_rad )
		);
		
		$new_lng_rad = $lng_rad + atan2(
			sin( $bearing_rad ) * sin( $angular_distance ) * cos( $lat_rad ),
			cos( $angular_distance ) - sin( $lat_rad ) * sin( $new_lat_rad )
		);
		
		// Convert back to degrees
		$new_lat = rad2deg( $new_lat_rad );
		$new_lng = rad2deg( $new_lng_rad );
		
		// Restore random seed
		mt_srand();
		
		return [
			'latitude'  => round( $new_lat, 6 ),
			'longitude' => round( $new_lng, 6 )
		];
	}

	/**
	 * Get member's location data from xprofile fields or user meta
	 *
	 * @param int $user_id User ID
	 * @return array|false Location data or false if not found
	 */
	private function get_member_location_data( $user_id ) {
		// Default return array
		$location_data = [
			'address'   => '',
			'latitude'  => '',
			'longitude' => '',
		];

		// Check meta keys in priority order (most specific first)
		$meta_key_pairs = [
			[ 'bp_location_latitude', 'bp_location_longitude' ],
			[ 'latitude', 'longitude' ],
			[ 'field_4_latitude', 'field_4_longitude' ],
			[ 'gmw_lat', 'gmw_lng' ],
			[ 'geo_latitude', 'geo_longitude' ],
		];

		foreach ( $meta_key_pairs as $pair ) {

			// Skips users who are hidden in the group - most likely site admins with manage_options capability
			$group_visibility = new GroupMemberVisibility();
			if( $group_visibility->is_member_hidden( bp_get_current_group_id(), $user_id ) ) {
				continue; // Skip hidden members
			}
			
			$lat = get_user_meta( $user_id, $pair[0], true );
			$lng = get_user_meta( $user_id, $pair[1], true );

			if ( ! empty( $lat ) && ! empty( $lng ) && is_numeric( $lat ) && is_numeric( $lng ) ) {
				$location_data['latitude']  = (float) $lat;
				$location_data['longitude'] = (float) $lng;
				break;
			}
		}

		// Check for address in user meta
		$address_meta_keys = [
			'bp_location',      // BuddyPress Location plugin
			'location',         // Generic location field
			'address',          // Generic address field
			'gmw_address',      // GeoMyWP plugin
			'geo_address',      // Other geo plugins
		];

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
			$location_data['latitude']  = $lat;
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