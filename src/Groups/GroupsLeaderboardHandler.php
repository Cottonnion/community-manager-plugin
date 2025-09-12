<?php
declare(strict_types=1);

namespace LABGENZ_CM\Groups;

use LABGENZ_CM\Gamipress\Helpers\GamiPressDataProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles group leaderboard functionality
 * Displays leaderboards of group members based on GamiPress activity_points
 */
class GroupsLeaderboardHandler {

	/**
	 * Singleton instance
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * GamiPress Data Provider
	 *
	 * @var GamiPressDataProvider
	 */
	private $gamipress_data_provider;

	/**
	 * The point type to use for leaderboards
	 *
	 * @var string
	 */
	private $points_type = 'reward_points';  // Changed to use Activity Reward Points

	/**
	 * Get the singleton instance of this class
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Get the points type used for the leaderboard
	 *
	 * @return string The points type
	 */
	public function get_points_type() {
		return $this->points_type;
	}

	/**
	 * Set the points type to use for leaderboard
	 *
	 * @param string $points_type The points type to use
	 */
	public function set_points_type( $points_type ) {
		$this->points_type = $points_type;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->gamipress_data_provider = new GamiPressDataProvider();
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Add AJAX hooks for leaderboard functionality
		add_action( 'wp_ajax_fetch_leaderboard_data', [ $this, 'fetch_leaderboard_data' ] );
	}

	/**
	 * Fetch leaderboard data via AJAX
	 */
	public function fetch_leaderboard_data() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'mlmmc_leaderboard_nonce' ) ) {
			wp_send_json_error(
				[
					'message' => 'Security check failed',
				]
			);
		}

		// Get parameters
		$group_id = isset( $_POST['group_id'] ) ? intval( $_POST['group_id'] ) : 0;
		$tab_type = isset( $_POST['tab_type'] ) ? sanitize_text_field( $_POST['tab_type'] ) : 'all-time';

		if ( empty( $group_id ) ) {
			wp_send_json_error(
				[
					'message' => 'Missing group ID',
				]
			);
		}

		// Get the appropriate leaderboard data
		if ( $tab_type === 'weekly' ) {
			$leaderboard_data = $this->get_weekly_group_leaderboard( $group_id, 999 );
			$user_rank        = [];

			// Get current user's rank if they're a member of this group
			$current_user_id = get_current_user_id();
			if ( $current_user_id && groups_is_user_member( $current_user_id, $group_id ) ) {
				$user_rank = $this->get_user_weekly_rank_in_group( $current_user_id, $group_id );
			}

			// Calculate date range
			$end_date   = current_time( 'Y-m-d' );
			$start_date = date( 'Y-m-d', strtotime( '-7 days' ) );
		} else {
			$leaderboard_data = $this->get_group_leaderboard( $group_id, 999 );
			$user_rank        = [];

			// Get current user's rank if they're a member of this group
			$current_user_id = get_current_user_id();
			if ( $current_user_id && groups_is_user_member( $current_user_id, $group_id ) ) {
				$user_rank = $this->get_user_rank_in_group( $current_user_id, $group_id );
			}
		}

		// Get point type label
		$point_type_label = 'Activity Reward Points';
		$points_type_name = $this->get_points_type();

		if ( function_exists( 'gamipress_get_points_type' ) ) {
			$points_type = gamipress_get_points_type( $points_type_name );
			if ( ! empty( $points_type ) && ! empty( $points_type->post_title ) ) {
				$point_type_label = $points_type->post_title;
			} else {
				// If no title found, capitalize the points type name
				$point_type_label = ucfirst( str_replace( '_', ' ', $points_type_name ) );
			}
		}

		// Check if any users have points
		$has_points = false;
		if ( ! empty( $leaderboard_data ) ) {
			foreach ( $leaderboard_data as $member ) {
				if ( $member['points'] > 0 ) {
					$has_points = true;
					break;
				}
			}
		}

		// Start output buffering to capture the HTML
		ob_start();

		// If it's the weekly tab, show the date range
		if ( $tab_type === 'weekly' ) {
			echo '<h2 class="bp-group-leaderboard-title">' . esc_html__( 'Weekly Group Leaderboard', 'labgenz-cm' ) . '</h2>';
			echo '<div class="weekly-date-range">';
			echo esc_html__( 'Points earned from', 'labgenz-cm' ) . ' <strong>' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $start_date ) ) ) . '</strong> ';
			echo esc_html__( 'to', 'labgenz-cm' ) . ' <strong>' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $end_date ) ) ) . '</strong>';
			echo '</div>';
		} else {
			echo '<h2 class="bp-group-leaderboard-title">' . esc_html__( 'All-Time Group Leaderboard', 'labgenz-cm' ) . '</h2>';
		}

		if ( ! empty( $leaderboard_data ) && $has_points ) {
			?>
			<div class="bp-group-leaderboard-list">
				<div class="bp-group-leaderboard-header">
					<div class="bp-leaderboard-rank"><?php echo esc_html__( '#', 'labgenz-cm' ); ?></div>
					<div class="bp-leaderboard-member"><?php echo esc_html__( 'Member', 'labgenz-cm' ); ?></div>
					<div class="bp-leaderboard-points"><?php echo esc_html( $point_type_label ); ?></div>
				</div>
				
				<?php foreach ( $leaderboard_data as $index => $member ) : ?>
					<?php $rank = $index + 1; ?>
					<div class="bp-group-leaderboard-item <?php echo ( $member['user_id'] == $current_user_id ) ? 'current-user' : ''; ?>" data-user-id="<?php echo esc_attr( $member['user_id'] ); ?>">
						<div class="bp-leaderboard-rank">
							<?php if ( $rank <= 3 ) : ?>
								<span class="bp-leaderboard-rank-top"><?php echo esc_html( $rank ); ?></span>
							<?php else : ?>
								<span class="bp-leaderboard-rank-normal"><?php echo esc_html( $rank ); ?></span>
							<?php endif; ?>
						</div>
						
						<div class="bp-leaderboard-member">
							<div class="bp-leaderboard-avatar">
								<a href="<?php echo esc_url( $member['profile_url'] ); ?>">
									<img src="<?php echo esc_url( $member['avatar'] ); ?>" alt="<?php echo esc_attr( $member['display_name'] ); ?>" />
								</a>
							</div>
							<div class="bp-leaderboard-name">
								<a href="<?php echo esc_url( $member['profile_url'] ); ?>">
									<?php echo esc_html( $member['display_name'] ); ?>
								</a>
								<span class="bp-leaderboard-username">@<?php echo esc_html( $member['user_login'] ); ?></span>
							</div>
						</div>
						
						<div class="bp-leaderboard-points">
							<?php echo esc_html( number_format( $member['points'] ) ); ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			
			<?php if ( ! empty( $user_rank ) && $user_rank['position'] > 10 ) : ?>
				<div class="bp-group-leaderboard-your-position">
					<h3><?php echo esc_html__( 'Your Position', 'labgenz-cm' ); ?></h3>
					
					<div class="bp-group-leaderboard-list">
						<?php foreach ( $user_rank['nearby_members'] as $index => $member ) : ?>
							<div class="bp-group-leaderboard-item <?php echo ( $member['user_id'] == $current_user_id ) ? 'current-user' : ''; ?>" data-user-id="<?php echo esc_attr( $member['user_id'] ); ?>">
								<div class="bp-leaderboard-rank">
									<span class="bp-leaderboard-rank-normal">
										<?php
										$position = $user_rank['position'] - ( count( $user_rank['nearby_members'] ) - 1 - $index );
										echo esc_html( $position );
										?>
									</span>
								</div>
								
								<div class="bp-leaderboard-member">
									<div class="bp-leaderboard-avatar">
										<a href="<?php echo esc_url( $member['profile_url'] ); ?>">
											<img src="<?php echo esc_url( $member['avatar'] ); ?>" alt="<?php echo esc_attr( $member['display_name'] ); ?>" />
										</a>
									</div>
									<div class="bp-leaderboard-name">
										<a href="<?php echo esc_url( $member['profile_url'] ); ?>">
											<?php echo esc_html( $member['display_name'] ); ?>
										</a>
										<span class="bp-leaderboard-username">@<?php echo esc_html( $member['user_login'] ); ?></span>
									</div>
								</div>
								
								<div class="bp-leaderboard-points">
									<?php echo esc_html( number_format( $member['points'] ) ); ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
			
		<?php } else { ?>
			<div class="bp-group-leaderboard-empty">
				<?php if ( $tab_type === 'weekly' ) : ?>
					<p><?php echo esc_html__( 'No members with points earned in the past week. Members need to earn ', 'labgenz-cm' ) . esc_html( $point_type_label ) . esc_html__( ' in the last 7 days to appear on the weekly leaderboard.', 'labgenz-cm' ); ?></p>
				<?php else : ?>
					<p><?php echo esc_html__( 'No members with points found in this group. Members need to earn ', 'labgenz-cm' ) . esc_html( $point_type_label ) . esc_html__( ' to appear on the leaderboard.', 'labgenz-cm' ); ?></p>
				<?php endif; ?>
			</div>
			<?php
		}

		// Get the captured HTML
		$html = ob_get_clean();

		// Send the HTML as a response
		wp_send_json_success(
			[
				'html' => $html,
			]
		);
	}

	/**
	 * Get leaderboard data for a specific group
	 *
	 * @param int $group_id The group ID
	 * @param int $limit The number of members to include (default: 100)
	 * @param int $page The page number for pagination (default: 1)
	 * @return array Leaderboard data
	 */
	public function get_group_leaderboard( $group_id, $limit = 100, $page = 1 ) {
		// Get group members
		$members = $this->get_group_members( $group_id, $limit, $page );

		if ( empty( $members ) ) {
			return [];
		}

		$leaderboard = [];

		// Build leaderboard data
		foreach ( $members as $member ) {
			$user_id = $member->ID;
			$points  = $this->get_user_activity_points( $user_id );

			$leaderboard[] = [
				'user_id'      => $user_id,
				'display_name' => $member->display_name,
				'user_login'   => $member->user_login,
				'points'       => $points,
				'avatar'       => get_avatar_url( $user_id, [ 'size' => 50 ] ),
				'profile_url'  => bp_core_get_user_domain( $user_id ),
			];
		}

		// Sort by points (highest first)
		usort(
			$leaderboard,
			function ( $a, $b ) {
				return $b['points'] <=> $a['points'];
			}
		);

		return $leaderboard;
	}

	/**
	 * Get all group members including regulars, admins, mods, and any other user types
	 *
	 * @param int $group_id The group ID
	 * @param int $limit The number of members to include
	 * @param int $page The page number for pagination
	 * @return array Array of user objects
	 */
	private function get_group_members( $group_id, $limit = 100, $page = 1 ) {
		$members = [];

		if ( ! function_exists( 'groups_get_group_members' ) ) {
			return $members;
		}

		// Collect all unique user IDs from different member types
		$all_user_ids = [];

		// Get regular members
		$group_members_query = groups_get_group_members(
			[
				'group_id' => $group_id,
				'per_page' => 0, // Get all members
			]
		);

		if ( ! empty( $group_members_query['members'] ) ) {
			foreach ( $group_members_query['members'] as $member ) {
				if ( isset( $member->ID ) ) {
					$all_user_ids[] = $member->ID;
				}
			}
		}

		// Get admins
		$group_admins = groups_get_group_admins( $group_id );
		if ( ! empty( $group_admins ) ) {
			foreach ( $group_admins as $admin ) {
				if ( isset( $admin->user_id ) ) {
					$all_user_ids[] = $admin->user_id;
				} elseif ( isset( $admin->ID ) ) {
					$all_user_ids[] = $admin->ID;
				}
			}
		}

		// Get mods
		$group_mods = groups_get_group_mods( $group_id );
		if ( ! empty( $group_mods ) ) {
			foreach ( $group_mods as $mod ) {
				if ( isset( $mod->user_id ) ) {
					$all_user_ids[] = $mod->user_id;
				} elseif ( isset( $mod->ID ) ) {
					$all_user_ids[] = $mod->ID;
				}
			}
		}

		// Also check for any banned or suspended members if those functions exist
		if ( function_exists( 'groups_get_banned_members' ) ) {
			$banned_members = groups_get_banned_members( $group_id );
			if ( ! empty( $banned_members ) ) {
				foreach ( $banned_members as $banned ) {
					if ( isset( $banned->user_id ) ) {
						$all_user_ids[] = $banned->user_id;
					} elseif ( isset( $banned->ID ) ) {
						$all_user_ids[] = $banned->ID;
					}
				}
			}
		}

		// Check for any pending members if available
		if ( function_exists( 'groups_get_invites' ) ) {
			$invites = groups_get_invites( [ 'item_id' => $group_id ] );
			if ( ! empty( $invites ) ) {
				foreach ( $invites as $invite ) {
					if ( isset( $invite->user_id ) ) {
						$all_user_ids[] = $invite->user_id;
					}
				}
			}
		}

		// Check for membership requests if available
		if ( function_exists( 'groups_get_membership_requests' ) ) {
			$requests = groups_get_membership_requests( [ 'item_id' => $group_id ] );
			if ( ! empty( $requests ) ) {
				foreach ( $requests as $request ) {
					if ( isset( $request->user_id ) ) {
						$all_user_ids[] = $request->user_id;
					}
				}
			}
		}

		// Remove duplicates and filter out invalid IDs
		$all_user_ids = array_unique(
			array_filter(
				$all_user_ids,
				function ( $id ) {
					return is_numeric( $id ) && $id > 0;
				}
			)
		);

		if ( empty( $all_user_ids ) ) {
			return $members;
		}

		// Apply pagination to user IDs
		$total_members  = count( $all_user_ids );
		$offset         = ( $page - 1 ) * $limit;
		$paged_user_ids = array_slice( $all_user_ids, $offset, $limit );

		// Get full user objects for the paged user IDs
		if ( ! empty( $paged_user_ids ) ) {
			$members = get_users(
				[
					'include' => $paged_user_ids,
					'orderby' => 'include', // Maintain the order from our array
				]
			);
		}

		return $members;
	}

	/**
	 * Get user activity points
	 *
	 * @param int $user_id The user ID
	 * @return int The number of activity points
	 */
	private function get_user_activity_points( $user_id ) {
		// Only get real points from GamiPressDataProvider
		$points = GamiPressDataProvider::get_user_points_balance( $user_id, $this->points_type );

		// If no points found, check for other common point types
		if ( $points === 0 ) {
			$common_point_types = [ 'activity_points', 'points', 'credits', 'coins', 'reward_points' ];

			foreach ( $common_point_types as $type ) {
				if ( $type !== $this->points_type ) {
					$alt_points = GamiPressDataProvider::get_user_points_balance( $user_id, $type );
					if ( $alt_points > 0 ) {
						return $alt_points;
					}
				}
			}

			// Check if there are any user meta entries for GamiPress points
			global $wpdb;
			$gamipress_points = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT meta_key, meta_value FROM {$wpdb->usermeta} 
                    WHERE user_id = %d AND meta_key LIKE %s AND meta_value > 0",
					$user_id,
					'%gamipress%points%'
				)
			);

			if ( ! empty( $gamipress_points ) ) {
				// Found some GamiPress points, use the first one with a value
				return (int) $gamipress_points[0]->meta_value;
			}

			// Return 0 if no real points found
			return 0;
		}

		return $points;
	}

	/**
	 * Get the top performers across all groups
	 *
	 * @param int $limit The number of users to include
	 * @return array Leaderboard data
	 */
	public function get_global_leaderboard( $limit = 100 ) {
		global $wpdb;

		// Check if GamiPress functions exist
		if ( ! function_exists( 'gamipress_get_points_type' ) || ! function_exists( 'gamipress_get_user_points' ) ) {
			return [];
		}

		// Make sure our points type exists
		$points_type = gamipress_get_points_type( $this->points_type );
		if ( empty( $points_type ) ) {
			return [];
		}

		// Get the meta key for this points type
		$meta_key = "_gamipress_{$this->points_type}_points";

		// Get users with the highest points
		$user_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} 
                WHERE meta_key = %s 
                ORDER BY CAST(meta_value AS SIGNED) DESC 
                LIMIT %d",
				$meta_key,
				$limit
			)
		);

		if ( empty( $user_ids ) ) {
			return [];
		}

		$leaderboard = [];

		foreach ( $user_ids as $user_id ) {
			$user = get_userdata( $user_id );
			if ( ! $user ) {
				continue;
			}

			$points = $this->get_user_activity_points( $user_id );

			$leaderboard[] = [
				'user_id'      => $user_id,
				'display_name' => $user->display_name,
				'user_login'   => $user->user_login,
				'points'       => $points,
				'avatar'       => get_avatar_url( $user_id, [ 'size' => 50 ] ),
				'profile_url'  => function_exists( 'bp_core_get_user_domain' ) ? bp_core_get_user_domain( $user_id ) : get_author_posts_url( $user_id ),
			];
		}

		return $leaderboard;
	}

	/**
	 * Award test points to a user
	 *
	 * @param int    $user_id The user ID
	 * @param int    $points The number of points to award
	 * @param string $points_type The type of points to award
	 * @return bool True if successful, false otherwise
	 */
	public function award_test_points( $user_id, $points, $points_type = null ) {
		if ( $points_type === null ) {
			$points_type = $this->points_type;
		}

		return GamiPressDataProvider::award_points_with_log(
			$user_id,
			$points,
			$points_type,
			'Test points awarded via leaderboard'
		);
	}

	/**
	 * Get the rank and position of a specific user within a group
	 *
	 * @param int $user_id The user ID
	 * @param int $group_id The group ID
	 * @return array User's rank data including position and nearby members
	 */
	public function get_user_rank_in_group( $user_id, $group_id ) {
		// Get all members' leaderboard data (no limit)
		$all_members_leaderboard = $this->get_group_leaderboard( $group_id, 999, 1 );

		// Find user position
		$position = 0;
		foreach ( $all_members_leaderboard as $index => $member ) {
			if ( $member['user_id'] == $user_id ) {
				$position = $index + 1;
				break;
			}
		}

		// Get members above and below this user
		$nearby_members = [];
		if ( $position > 0 ) {
			// Get up to 2 members above
			$start = max( 0, $position - 3 );
			$end   = min( count( $all_members_leaderboard ), $position + 2 );

			for ( $i = $start; $i < $end; $i++ ) {
				$nearby_members[] = $all_members_leaderboard[ $i ];
			}
		}

		return [
			'position'       => $position,
			'nearby_members' => $nearby_members,
			'total_members'  => count( $all_members_leaderboard ),
		];
	}

	/**
	 * Get weekly leaderboard data for a specific group
	 *
	 * @param int $group_id The group ID
	 * @param int $limit The number of members to include (default: 100)
	 * @param int $page The page number for pagination (default: 1)
	 * @return array Weekly leaderboard data
	 */
	public function get_weekly_group_leaderboard( $group_id, $limit = 100, $page = 1 ) {
		// Get group members
		$members = $this->get_group_members( $group_id, $limit, $page );

		if ( empty( $members ) ) {
			return [];
		}

		$leaderboard = [];

		// Build leaderboard data with weekly points
		foreach ( $members as $member ) {
			$user_id = $member->ID;
			$points  = $this->get_user_weekly_points( $user_id );

			$leaderboard[] = [
				'user_id'      => $user_id,
				'display_name' => $member->display_name,
				'user_login'   => $member->user_login,
				'points'       => $points,
				'avatar'       => get_avatar_url( $user_id, [ 'size' => 50 ] ),
				'profile_url'  => bp_core_get_user_domain( $user_id ),
			];
		}

		// Sort by points (highest first)
		usort(
			$leaderboard,
			function ( $a, $b ) {
				return $b['points'] <=> $a['points'];
			}
		);

		return $leaderboard;
	}

	/**
	 * Get user's weekly activity points (points earned in the last 7 days)
	 *
	 * @param int $user_id The user ID
	 * @return int The number of weekly activity points
	 */
	private function get_user_weekly_points( $user_id ) {
		global $wpdb;

		// Calculate start date (7 days ago)
		$start_date = date( 'Y-m-d H:i:s', strtotime( '-7 days' ) );

		// Query to get sum of points awarded in the last 7 days directly from gamipress_logs table
		$logs_table = $wpdb->prefix . 'gamipress_logs';

		$weekly_points = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(points) 
                FROM {$logs_table}
                WHERE user_id = %d 
                AND type = %s
                AND points_type = %s
                AND date >= %s",
				$user_id,
				'points_award',
				$this->points_type,
				$start_date
			)
		);

		// If no points found in logs, return 0
		if ( empty( $weekly_points ) ) {
			return 0;
		}

		return (int) $weekly_points;
	}

	/**
	 * Get user's rank and position within a group's weekly leaderboard
	 *
	 * @param int $user_id The user ID
	 * @param int $group_id The group ID
	 * @return array User's weekly rank data including position and nearby members
	 */
	public function get_user_weekly_rank_in_group( $user_id, $group_id ) {
		// Get all members' weekly leaderboard data (no limit)
		$all_members_leaderboard = $this->get_weekly_group_leaderboard( $group_id, 999, 1 );

		// Find user position
		$position = 0;
		foreach ( $all_members_leaderboard as $index => $member ) {
			if ( $member['user_id'] == $user_id ) {
				$position = $index + 1;
				break;
			}
		}

		// Get members above and below this user
		$nearby_members = [];
		if ( $position > 0 ) {
			// Get up to 2 members above
			$start = max( 0, $position - 3 );
			$end   = min( count( $all_members_leaderboard ), $position + 2 );

			for ( $i = $start; $i < $end; $i++ ) {
				$nearby_members[] = $all_members_leaderboard[ $i ];
			}
		}

		return [
			'position'       => $position,
			'nearby_members' => $nearby_members,
			'total_members'  => count( $all_members_leaderboard ),
		];
	}
}
