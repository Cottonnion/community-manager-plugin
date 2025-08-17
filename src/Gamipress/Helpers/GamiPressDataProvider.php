<?php
/**
 * GamiPress Data Provider
 *
 * Handles all data retrieval and processing for GamiPress integration
 *
 * @package LABGENZ_CM\Gamipress
 */

namespace LABGENZ_CM\Gamipress\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GamiPressDataProvider {

	/**
	 * GamiPress rank type setting
	 *
	 * @var string
	 */
	private $rank_type;

	/**
	 * GamiPress points type setting
	 *
	 * @var string
	 */
	private $points_type;

	/**
	 * GamiPress coins type setting
	 *
	 * @var string
	 */
	private $coins_type;

	/**
	 * GamiPress reward points type setting
	 *
	 * @var string
	 */
	private $reward_points_type;

	/**
	 * Redeem page ID
	 *
	 * @var int
	 */
	private $redeem_page;

	/**
	 * Available rank meta keys for points requirements
	 *
	 * @var array
	 */
	private $rank_meta_keys;

	/**
	 * Available requirement meta keys for points requirements
	 *
	 * @var array
	 */
	private $requirement_meta_keys;

	/**
	 * User rank meta key prefix
	 *
	 * @var string
	 */
	private $user_rank_meta_prefix;

	/**
	 * Base points for progressive scaling
	 *
	 * @var int
	 */
	private $base_points;

	/**
	 * Default coins image URL
	 *
	 * @var string
	 */
	private $default_coins_image;

	/**
	 * Default accent color
	 *
	 * @var string
	 */
	private $default_accent_color;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->rank_type             = get_option( 'ghi_rank_type' );
		$this->points_type           = get_option( 'ghi_points_type' );
		$this->coins_type            = get_option( 'ghi_coins_type' );
		$this->reward_points_type    = 'reward_points';
		$this->redeem_page           = get_option( 'ghi_redeem_page' );
		$this->user_rank_meta_prefix = '_gamipress_';
		$this->base_points           = 50;
		$this->default_coins_image   = 'https://v2mlmmasteryclub.labgenz.com/wp-content/uploads/2025/07/box-heart-1.png';
		$this->default_accent_color  = '#007cba';

		$this->requirement_meta_keys = [
			'_gamipress_points_required',
			'_gamipress_count',
			'_gamipress_points',
			'_gamipress_achievement_points',
			'_gamipress_points_to_unlock',
		];

		$this->rank_meta_keys = [
			'_gamipress_points_to_unlock',
			'_gamipress_rank_points',
			'_gamipress_points_required',
		];
	}

	/**
	 * Get gamification data for user
	 *
	 * @param int $user_id User ID
	 * @return array Gamification data including ranks, points, coins, and reward points
	 */
	public function get_gamification_data( $user_id ) {
		$current_rank_id       = gamipress_get_user_rank_id( $user_id, $this->rank_type );
		$next_level_id         = gamipress_get_next_user_rank_id( $user_id, $this->rank_type );
		$current_rank          = get_the_title( $current_rank_id );
		$current_points        = gamipress_get_user_points( $user_id, $this->points_type );
		$current_coins         = gamipress_get_user_points( $user_id, $this->coins_type );
		$current_reward_points = gamipress_get_user_points( $user_id, $this->reward_points_type );

		$points_needed = 0;
		$completion    = 0;

		// Use a dynamic points-based system without hardcoded values
		if ( $this->rank_type && $current_rank_id ) {
			// Get all ranks sorted by menu_order (which determines hierarchy)
			$all_ranks = gamipress_get_ranks(
				[
					'post_type' => $this->rank_type,
					'orderby'   => 'menu_order',
					'order'     => 'ASC',
				]
			);

			// Find current rank position and next rank
			$current_rank_position = -1;
			$next_rank             = null;

			foreach ( $all_ranks as $index => $rank ) {
				if ( $rank->ID == $current_rank_id ) {
					$current_rank_position = $index;
					// Get next rank if it exists
					if ( isset( $all_ranks[ $index + 1 ] ) ) {
						$next_rank = $all_ranks[ $index + 1 ];
					}
					break;
				}
			}

			if ( $next_rank ) {
				// Try to get points needed from various sources
				$points_needed = $this->get_dynamic_points_needed( $next_rank->ID, $current_rank_position, $current_points );

				// Check if user should be automatically promoted
				if ( $current_points >= $points_needed ) {
					$promotion_result = $this->attempt_rank_promotion( $user_id, $next_rank->ID, $this->rank_type, $current_points, $points_needed );

					if ( $promotion_result ) {
						// Refresh the rank data after promotion
						$current_rank_id = gamipress_get_user_rank_id( $user_id, $this->rank_type );
						$current_rank    = get_the_title( $current_rank_id );

						// Update next rank target
						$next_level_id = gamipress_get_next_user_rank_id( $user_id, $this->rank_type );

						// Find new position and next rank
						foreach ( $all_ranks as $index => $rank ) {
							if ( $rank->ID == $current_rank_id ) {
								$current_rank_position = $index;
								if ( isset( $all_ranks[ $index + 1 ] ) ) {
									$next_rank     = $all_ranks[ $index + 1 ];
									$points_needed = $this->get_dynamic_points_needed( $next_rank->ID, $current_rank_position, $current_points );
								} else {
									$next_rank = null;
								}
								break;
							}
						}
					}
				}
			} else {
				// User is at maximum rank
				$points_needed = $current_points > 0 ? $current_points : 100;
				$completion    = 100;
			}
		} else {
			$points_needed = 100; // Default progression target
		}

		// Calculate completion percentage
		if ( $completion == 0 && $points_needed > 0 ) {
			$completion = round( $current_points / $points_needed * 100, 0 );
			if ( $completion > 100 ) {
				$completion = 100;
			}
		}

		// Handle case where user has exceeded requirements but hasn't ranked up yet
		// BUT skip this logic if user is at max rank and showing achievement points
		if ( $points_needed > 0 && $current_points >= $points_needed && $completion >= 100 && $points_needed != $current_points ) {
			// Show that they've completed this rank and are ready for the next one
			// Instead of showing 51/50 (which is confusing), show progress toward the rank after next
			if ( $next_rank && isset( $all_ranks[ $current_rank_position + 2 ] ) ) {
				$rank_after_next = $all_ranks[ $current_rank_position + 2 ];

				// Get points needed for the rank after next
				$points_for_rank_after_next = $this->get_dynamic_points_needed( $rank_after_next->ID, $current_rank_position + 1, $current_points );

				$points_needed = $points_for_rank_after_next;
				$completion    = round( $current_points / $points_needed * 100, 0 );
				if ( $completion > 100 ) {
					$completion = 100;
				}
			} else {
				// If no rank after next, show current progress with a note
				// Keep current values but cap completion at 100%
				$completion = 100;
			}
		}

		$redeem_url = $this->redeem_page ? get_permalink( $this->redeem_page ) : 'javascript:void(0)';

		$data = [
			'rank_img'              => get_the_post_thumbnail_url( $current_rank_id ),
			'current_rank'          => $current_rank,
			'current_points'        => $current_points,
			'points_needed'         => $points_needed,
			'completion'            => $completion,
			'redeem_screen'         => $redeem_url,
			'coins_img'             => $this->get_coins_image( $this->coins_type ),
			'current_coins'         => $current_coins,
			'current_reward_points' => $current_reward_points,
			'reward_points_img'     => $this->get_coins_image( $this->reward_points_type ),
			'accent_color'          => $this->default_accent_color,
		];

		return $data;
	}

	/**
	 * Get coins or reward points image
	 *
	 * @param string $points_type Points type slug
	 * @return string Image URL
	 */
	public function get_coins_image( $points_type ) {
		if ( empty( $points_type ) ) {
			return $this->default_coins_image;
		}

		$points_data = gamipress_get_points_type( $points_type );
		$points_img  = ! empty( $points_data ) ? get_the_post_thumbnail_url( $points_data['ID'] ) : '';

		// Return default image if no image found
		if ( empty( $points_img ) ) {
			$points_img = $this->default_coins_image;
		}

		return $points_img;
	}

	/**
	 * Get dynamic points needed for next rank using various GamiPress data sources
	 *
	 * @param int $next_rank_id Next rank ID
	 * @param int $current_rank_position Current rank position in hierarchy
	 * @param int $current_points User's current points
	 * @return int Points needed for next rank
	 */
	public function get_dynamic_points_needed( $next_rank_id, $current_rank_position, $current_points ) {
		$points_needed = 0;

		// Method 1: Try to get points from rank requirements (most accurate)
		$requirements = gamipress_get_rank_requirements( $next_rank_id );

		if ( ! empty( $requirements ) ) {
			foreach ( $requirements as $requirement ) {
				foreach ( $this->requirement_meta_keys as $meta_key ) {
					$meta_value = get_post_meta( $requirement->ID, $meta_key, true );
					if ( ! empty( $meta_value ) && is_numeric( $meta_value ) ) {
						return intval( $meta_value );
					}
				}
			}
		}

		// Method 2: Try to get points from rank meta directly
		foreach ( $this->rank_meta_keys as $meta_key ) {
			$meta_value = get_post_meta( $next_rank_id, $meta_key, true );
			if ( ! empty( $meta_value ) && is_numeric( $meta_value ) ) {
				return intval( $meta_value );
			}
		}

		// Method 3: Use rank priority difference as a multiplier
		$next_rank_priority = get_post_meta( $next_rank_id, '_gamipress_rank_priority', true );
		if ( ! empty( $next_rank_priority ) && is_numeric( $next_rank_priority ) ) {
			// Use priority as a base multiplier (priority * 25 points)
			return intval( $next_rank_priority ) * 25;
		}

		// Method 4: Progressive scaling based on position (double points each level)
		// Special case: if current_rank_position is -1 or 0, this is likely the lowest rank
		if ( $current_rank_position <= 0 ) {
			$points_needed = 0; // Lowest rank requires 0 points
		} else {
			$points_needed = $this->base_points * pow( 2, $current_rank_position );
		}

		// Method 5: If all else fails and points_needed is still too low, use current points + reasonable increment
		if ( $points_needed <= $current_points && $current_rank_position > 0 ) {
			$increment     = max( 50, $current_points * 0.5 ); // 50% more points or minimum 50
			$points_needed = $current_points + $increment;
		}

		return $points_needed;
	}

	/**
	 * Attempt to promote user to next rank automatically
	 *
	 * @param int    $user_id User ID
	 * @param int    $next_rank_id Next rank ID
	 * @param string $rank_type Rank type slug
	 * @param int    $current_points User's current points
	 * @param int    $points_needed Points needed for promotion
	 * @return bool True if promotion successful, false otherwise
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
			$meta_key = $this->user_rank_meta_prefix . $rank_type . '_rank';
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

		// Method 3: WordPress action hook trigger
		// Trigger custom action for rank promotion
		do_action( 'gamipress_auto_rank_promotion', $user_id, $next_rank_id, $rank_type, $current_points, $points_needed );

		// Check if rank was updated
		$new_rank_id = gamipress_get_user_rank_id( $user_id, $rank_type );
		if ( $new_rank_id == $next_rank_id ) {
			return true;
		}

		return false;
	}
}
