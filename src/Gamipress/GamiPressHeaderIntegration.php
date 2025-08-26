<?php
declare(strict_types=1);
/**
 * GamiPress Header Integration for Labgenz Community Management
 *
 * Integrates GamiPress header functionality into the community management plugin
 * Requires: GamiPress plugin
 *
 * @package LABGENZ_CM\Gamipress
 */

namespace LABGENZ_CM\Gamipress;

use LABGENZ_CM\Gamipress\Helpers\GamiPressDataProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GamiPressHeaderIntegration {

	private static $instance         = null;
	private static $scripts_enqueued = false;
	private $data_provider;

	public static function get_instance() {
		if ( self::$instance == null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Initialize data provider
		$this->data_provider = new GamiPressDataProvider();

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_scripts' ] );
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );

		// Hook into GamiPress points changes to update ranks
		add_action( 'gamipress_update_user_points', [ $this, 'handle_points_change' ], 10, 4 );
		add_action( 'gamipress_award_points_to_user', [ $this, 'handle_points_award' ], 10, 3 );
		add_action( 'gamipress_deduct_points_to_user', [ $this, 'handle_points_deduct' ], 10, 3 );
	}

	/**
	 * Register admin settings menu
	 */
	public function register_admin_menu() {
		add_options_page(
			'GamiPress Header Settings',
			'GamiPress Header',
			'manage_options',
			'gamipress-header-settings',
			[ $this, 'settings_page' ]
		);
	}

	/**
	 * Register plugin settings
	 */
	public function register_settings() {
		register_setting( 'gamipress_header_settings', 'ghi_progress_bar' );
		register_setting( 'gamipress_header_settings', 'ghi_rank_type' );
		register_setting( 'gamipress_header_settings', 'ghi_points_type' );
		register_setting( 'gamipress_header_settings', 'ghi_coins_type' );
		register_setting( 'gamipress_header_settings', 'ghi_redeem_page' );
	}

	/**
	 * Settings page HTML
	 */
	public function settings_page() {
		if ( ! function_exists( 'gamipress_get_rank_types' ) ) {
			echo '<div class="notice notice-error"><p>GamiPress plugin is required for this functionality.</p></div>';
			return;
		}
		?>
		<div class="wrap">
			<h1>GamiPress Header Settings</h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'gamipress_header_settings' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row">Show Progress Bar</th>
						<td>
							<select name="ghi_progress_bar">
								<option value="1" <?php selected( get_option( 'ghi_progress_bar' ), '1' ); ?>>Enabled</option>
								<option value="0" <?php selected( get_option( 'ghi_progress_bar' ), '0' ); ?>>Disabled</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">Rank Type</th>
						<td>
							<select name="ghi_rank_type">
								<option value="">Select Rank Type</option>
								<?php
								$rank_types = gamipress_get_rank_types();
								foreach ( $rank_types as $rank ) {
									$data     = get_post( $rank['ID'] );
									$selected = selected( get_option( 'ghi_rank_type' ), $data->post_name, false );
									echo '<option value="' . esc_attr( $data->post_name ) . '" ' . $selected . '>' . esc_html( $rank['plural_name'] ) . '</option>';
								}
								?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">Points Type</th>
						<td>
							<select name="ghi_points_type">
								<option value="">Select Points Type</option>
								<?php
								$point_types = gamipress_get_points_types();
								foreach ( $point_types as $points ) {
									$data     = get_post( $points['ID'] );
									$selected = selected( get_option( 'ghi_points_type' ), $data->post_name, false );
									echo '<option value="' . esc_attr( $data->post_name ) . '" ' . $selected . '>' . esc_html( $points['plural_name'] ) . '</option>';
								}
								?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">Coins Type</th>
						<td>
							<select name="ghi_coins_type">
								<option value="">Select Coins Type</option>
								<?php
								foreach ( $point_types as $points ) {
									$data     = get_post( $points['ID'] );
									$selected = selected( get_option( 'ghi_coins_type' ), $data->post_name, false );
									echo '<option value="' . esc_attr( $data->post_name ) . '" ' . $selected . '>' . esc_html( $points['plural_name'] ) . '</option>';
								}
								?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">Redeem Page</th>
						<td>
							<select name="ghi_redeem_page">
								<option value="">Select Page</option>
								<?php
								$pages = get_pages();
								foreach ( $pages as $page ) {
									$selected = selected( get_option( 'ghi_redeem_page' ), $page->ID, false );
									echo '<option value="' . esc_attr( $page->ID ) . '" ' . $selected . '>' . esc_html( $page->post_title ) . '</option>';
								}
								?>
							</select>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Enqueue frontend scripts
	 */
	public function enqueue_frontend_scripts() {
		// Prevent multiple enqueues
		if ( self::$scripts_enqueued ) {
			return;
		}

		if ( ! get_option( 'ghi_progress_bar', 0 ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		if ( ! function_exists( 'gamipress_get_user_rank_id' ) ) {
			return;
		}

		// Mark scripts as enqueued
		self::$scripts_enqueued = true;

		$user_id = get_current_user_id();
		$data    = $this->data_provider->get_gamification_data( $user_id );

		// Add inline script to expose data globally
		wp_add_inline_script( 'jquery', $this->get_inline_js( $data ) );
	}

	/**
	 * Generate inline JavaScript to expose gamification data globally
	 */
	private function get_inline_js( $data ) {
		$json_data = json_encode( $data );
		return "
		// Expose gamification data to global window object
		window.gamificationData = {$json_data};
		
		// Also create a namespace for better organization
		window.GamiPressHeader = window.GamiPressHeader || {};
		window.GamiPressHeader.data = {$json_data};

		// Trigger events so other scripts know the data is available
		jQuery(document).ready(function($) {
			$(document).trigger('gamipress:dataReady', [window.gamificationData]);
			
			// Also dispatch a native JavaScript event for non-jQuery scripts
			if (typeof CustomEvent !== 'undefined') {
				var event = new CustomEvent('gamipress:dataReady', {
					detail: window.gamificationData
				});
				document.dispatchEvent(event);
			}
		});
		";
	}

	/**
	 * Handle points change events
	 */
	public function handle_points_change( $user_id, $points, $points_type, $reason ) {
		$this->check_and_update_rank( $user_id, $points_type );
	}

	/**
	 * Handle points award events
	 */
	public function handle_points_award( $user_id, $points, $points_type ) {
		$this->check_and_update_rank( $user_id, $points_type );
	}

	/**
	 * Handle points deduction events
	 */
	public function handle_points_deduct( $user_id, $points, $points_type ) {
		$this->check_and_update_rank( $user_id, $points_type );
	}

	/**
	 * Check user's current points and update rank accordingly (promotion or demotion)
	 */
	private function check_and_update_rank( $user_id, $points_type ) {
		// Only process if this is the configured points type
		$configured_points_type = get_option( 'ghi_points_type' );
		if ( $points_type !== $configured_points_type ) {
			return;
		}

		$rank_type = get_option( 'ghi_rank_type' );
		if ( ! $rank_type ) {
			return;
		}

		$current_points  = gamipress_get_user_points( $user_id, $points_type );
		$current_rank_id = gamipress_get_user_rank_id( $user_id, $rank_type );

		// Get all ranks sorted by menu_order
		$all_ranks = gamipress_get_ranks(
			[
				'post_type' => $rank_type,
				'orderby'   => 'menu_order',
				'order'     => 'ASC',
			]
		);

		$current_rank_position = -1;
		foreach ( $all_ranks as $index => $rank ) {
			if ( $rank->ID == $current_rank_id ) {
				$current_rank_position = $index;
				break;
			}
		}

		if ( $current_rank_position === -1 ) {
			return; // Current rank not found
		}

		// Check for promotion
		$this->check_rank_promotion( $user_id, $rank_type, $all_ranks, $current_rank_position, $current_points );
	}

	/**
	 * Check if user should be promoted to a higher rank
	 */
	private function check_rank_promotion( $user_id, $rank_type, $all_ranks, $current_rank_position, $current_points ) {
		// Check all higher ranks to see if user qualifies
		for ( $i = $current_rank_position + 1; $i < count( $all_ranks ); $i++ ) {
			$target_rank   = $all_ranks[ $i ];
			$points_needed = $this->data_provider->get_dynamic_points_needed( $target_rank->ID, $i - 1, $current_points );

			if ( $current_points >= $points_needed ) {
				// User qualifies for this rank, promote them
				$this->attempt_rank_promotion( $user_id, $target_rank->ID, $rank_type, $current_points, $points_needed );
				// Continue checking higher ranks
			} else {
				// User doesn't qualify for this rank, stop checking
				break;
			}
		}
	}

	/**
	 * Attempt to promote user to next rank automatically
	 */
	private function attempt_rank_promotion( $user_id, $next_rank_id, $rank_type, $current_points, $points_needed ) {
		// Method 1: Use GamiPress built-in promotion function
		if ( function_exists( 'gamipress_update_user_rank' ) ) {
			$result = gamipress_update_user_rank( $user_id, $next_rank_id, $rank_type );

			if ( $result ) {
				// Trigger GamiPress hooks for rank earned
				if ( function_exists( 'gamipress_trigger_event' ) ) {
					gamipress_trigger_event(
						[
							'event'     => 'gamipress_earn_rank',
							'user_id'   => $user_id,
							'rank_id'   => $next_rank_id,
							'rank_type' => $rank_type,
						]
					);
				}
				return true;
			}
		}

		// Method 2: Direct database update as fallback
		if ( function_exists( 'gamipress_get_user_rank_id' ) ) {
			// Update user rank meta
			$meta_key = '_gamipress_' . $rank_type . '_rank';
			$updated  = update_user_meta( $user_id, $meta_key, $next_rank_id );

			if ( $updated !== false ) {
				// Clear any relevant caches
				if ( function_exists( 'gamipress_delete_user_earnings_cache' ) ) {
					gamipress_delete_user_earnings_cache( $user_id );
				}

				// Log the rank earning in GamiPress earnings table if function exists
				if ( function_exists( 'gamipress_insert_user_earning' ) ) {
					gamipress_insert_user_earning(
						$user_id,
						$next_rank_id,
						'rank',
						[
							'date'   => date( 'Y-m-d H:i:s' ),
							'reason' => 'Automatic promotion via points threshold',
						]
					);
				}
				return true;
			}
		}

		return false;
	}

	/**
	 * Initialize the integration
	 */
	public static function init() {
		self::get_instance();
	}
}