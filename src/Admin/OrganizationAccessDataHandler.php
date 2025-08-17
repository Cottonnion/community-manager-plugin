<?php

declare(strict_types=1);

namespace LABGENZ_CM\Admin;

use LABGENZ_CM\Core\OrganizationAccess;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data handler for organization access requests
 *
 * @package    Labgenz_Community_Management
 * @subpackage Labgenz_Community_Management/Admin
 */
class OrganizationAccessDataHandler {

	/**
	 * Organization access handler
	 *
	 * @var OrganizationAccess
	 */
	private OrganizationAccess $org_access;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->org_access = new OrganizationAccess();
	}

	/**
	 * Get requests by tab
	 *
	 * @param string $tab Tab name
	 * @return array
	 */
	public function get_requests_by_tab( string $tab ): array {
		switch ( $tab ) {
			case 'pending':
				return $this->org_access->get_all_requests( OrganizationAccess::STATUS_PENDING );
			case 'approved':
				return $this->org_access->get_all_requests( OrganizationAccess::STATUS_APPROVED );
			case 'rejected':
				return $this->org_access->get_all_requests( OrganizationAccess::STATUS_REJECTED );
			case 'completed':
				return $this->org_access->get_all_requests( OrganizationAccess::STATUS_COMPLETED );
			case 'all':
			default:
				return $this->org_access->get_all_requests();
		}
	}

	/**
	 * Get tab label
	 *
	 * @param string $tab Tab name
	 * @return string
	 */
	public function get_tab_label( string $tab ): string {
		switch ( $tab ) {
			case 'pending':
				return __( 'Pending', 'labgenz-community-management' );
			case 'approved':
				return __( 'Approved', 'labgenz-community-management' );
			case 'rejected':
				return __( 'Rejected', 'labgenz-community-management' );
			case 'completed':
				return __( 'Completed', 'labgenz-community-management' );
			case 'all':
				return __( 'All', 'labgenz-community-management' );
			default:
				return ucfirst( $tab );
		}
	}

	/**
	 * Get status label
	 *
	 * @param string $status Status
	 * @return string
	 */
	public function get_status_label( string $status ): string {
		switch ( $status ) {
			case OrganizationAccess::STATUS_PENDING:
				return __( 'Pending', 'labgenz-community-management' );
			case OrganizationAccess::STATUS_APPROVED:
				return __( 'Approved', 'labgenz-community-management' );
			case OrganizationAccess::STATUS_REJECTED:
				return __( 'Rejected', 'labgenz-community-management' );
			case OrganizationAccess::STATUS_COMPLETED:
				return __( 'Completed', 'labgenz-community-management' );
			default:
				return __( 'Unknown', 'labgenz-community-management' );
		}
	}

	/**
	 * Get status constants for use in templates
	 *
	 * @return array
	 */
	public function get_status_constants(): array {
		return [
			'STATUS_PENDING'  => OrganizationAccess::STATUS_PENDING,
			'STATUS_APPROVED' => OrganizationAccess::STATUS_APPROVED,
			'STATUS_REJECTED' => OrganizationAccess::STATUS_REJECTED,
			'STATUS_COMPLETED' => OrganizationAccess::STATUS_COMPLETED,
		];
	}

	/**
	 * Get user's BuddyBoss groups
	 *
	 * @param int $user_id User ID
	 * @return array
	 */
	public function get_user_groups( int $user_id ): array {
		$groups = [];

		if ( function_exists( 'groups_get_user_groups' ) ) {
			$user_groups = groups_get_user_groups( $user_id );

			if ( ! empty( $user_groups['groups'] ) ) {
				foreach ( $user_groups['groups'] as $group_id ) {
					$group = groups_get_group( $group_id );
					if ( $group ) {
						// Get user's role in this group (cast to int to match method signature)
						$user_role = $this->get_user_group_role( $user_id, intval( $group_id ) );

						$groups[] = [
							'id'           => $group->id,
							'name'         => $group->name,
							'slug'         => $group->slug,
							'status'       => $group->status,
							'member_count' => $group->total_member_count,
							'user_role'    => $user_role,
							'url'          => function_exists( 'bp_get_group_permalink' ) ? bp_get_group_permalink( $group ) : '',
						];
					}
				}
			}
		}

		return $groups;
	}

	/**
	 * Get user's role in a specific group
	 *
	 * @param int $user_id User ID
	 * @param int $group_id Group ID
	 * @return string User's role in the group
	 */
	private function get_user_group_role( int $user_id, int $group_id ): string {
		// Check if user is group admin (organizer/creator)
		if ( function_exists( 'groups_is_user_admin' ) && groups_is_user_admin( $user_id, $group_id ) ) {
			return 'organizer';
		}

		// Check if user is group moderator
		if ( function_exists( 'groups_is_user_mod' ) && groups_is_user_mod( $user_id, $group_id ) ) {
			return 'moderator';
		}

		// Check if user is a regular member
		if ( function_exists( 'groups_is_user_member' ) && groups_is_user_member( $user_id, $group_id ) ) {
			return 'member';
		}

		// Fallback - if somehow they're in the group but not detected above
		return 'member';
	}

	/**
	 * Get user's courses (LearnDash or other LMS)
	 *
	 * @param int $user_id User ID
	 * @return array
	 */
	public function get_user_courses( int $user_id ): array {
		$courses = [];

		// LearnDash Integration
		if ( function_exists( 'learndash_user_get_enrolled_courses' ) ) {
			$enrolled_courses = learndash_user_get_enrolled_courses( $user_id );

			if ( ! empty( $enrolled_courses ) ) {
				foreach ( $enrolled_courses as $course_id ) {
					$course = get_post( $course_id );
					if ( $course ) {
						$progress = function_exists( 'learndash_user_get_course_progress' ) ?
							learndash_user_get_course_progress( $user_id, $course_id ) : null;

						$courses[] = [
							'id'        => $course->ID,
							'title'     => $course->post_title,
							'status'    => $course->post_status,
							'progress'  => $progress,
							'completed' => $progress ? $progress['completed'] : 0,
							'total'     => $progress ? $progress['total'] : 0,
							'url'       => get_permalink( $course_id ),
						];
					}
				}
			}
		}

		// WooCommerce Subscriptions (if courses are subscription-based)
		if ( function_exists( 'wcs_get_users_subscriptions' ) ) {
			$subscriptions = wcs_get_users_subscriptions( $user_id );

			foreach ( $subscriptions as $subscription ) {
				if ( $subscription->get_status() === 'active' ) {
					foreach ( $subscription->get_items() as $item ) {
						$product = $item->get_product();
						if ( $product && $product->get_type() === 'course' ) {
							$courses[] = [
								'id'     => $product->get_id(),
								'title'  => $product->get_name(),
								'status' => 'active',
								'type'   => 'subscription',
								'url'    => $product->get_permalink(),
							];
						}
					}
				}
			}
		}

		// Generic course post type (if you have a custom course post type)
		$user_courses = get_posts(
			[
				'post_type'      => 'course',
				'meta_query'     => [
					[
						'key'     => 'enrolled_users',
						'value'   => $user_id,
						'compare' => 'LIKE',
					],
				],
				'posts_per_page' => -1,
			]
		);

		foreach ( $user_courses as $course ) {
			$courses[] = [
				'id'     => $course->ID,
				'title'  => $course->post_title,
				'status' => $course->post_status,
				'type'   => 'custom',
				'url'    => get_permalink( $course->ID ),
			];
		}

		return $courses;
	}

	/**
	 * Get user profile links
	 *
	 * @param int $user_id User ID
	 * @return array
	 */
	public function get_user_profile_links( int $user_id ): array {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return [];
		}

		$profile_links = [];

		// WordPress Admin Edit User Page
		$profile_links[] = [
			'title'       => __( 'Edit User (Admin)', 'labgenz-community-management' ),
			'url'         => admin_url( 'user-edit.php?user_id=' . $user_id ),
			'icon'        => 'dashicons-admin-users',
			'description' => __( 'Edit user profile in WordPress admin', 'labgenz-community-management' ),
			'target'      => '_blank',
		];

		// BuddyBoss/BuddyPress Member Profile (if available)
		if ( function_exists( 'bp_core_get_user_domain' ) ) {
			$bp_profile_url = bp_core_get_user_domain( $user_id );
			if ( $bp_profile_url ) {
				$profile_links[] = [
					'title'       => __( 'BuddyBoss Profile', 'labgenz-community-management' ),
					'url'         => $bp_profile_url,
					'icon'        => 'dashicons-buddicons-buddypress-logo',
					'description' => __( 'View public member profile', 'labgenz-community-management' ),
					'target'      => '_blank',
				];
			}
		}

		// WooCommerce My Account Page (if available)
		if ( class_exists( 'WooCommerce' ) ) {
			$wc_account_url = wc_get_account_endpoint_url( 'edit-account' );
			if ( $wc_account_url ) {
				// For admin viewing, we'll link to the customer edit page instead
				$profile_links[] = [
					'title'       => __( 'WooCommerce Customer', 'labgenz-community-management' ),
					'url'         => admin_url( 'admin.php?page=wc-admin&path=/customers&search=' . urlencode( $user->user_email ) ),
					'icon'        => 'dashicons-cart',
					'description' => __( 'View customer details in WooCommerce', 'labgenz-community-management' ),
					'target'      => '_blank',
				];
			}
		}

		// User's frontend profile (if theme supports it)
		$author_url = get_author_posts_url( $user_id );
		if ( $author_url ) {
			$profile_links[] = [
				'title'       => __( 'Author Profile', 'labgenz-community-management' ),
				'url'         => $author_url,
				'icon'        => 'dashicons-admin-users',
				'description' => __( 'View author profile page', 'labgenz-community-management' ),
				'target'      => '_blank',
			];
		}

		// MLMMC Articles (Custom Post Type)
		// $mlmmc_articles_count = $this->get_user_mlmmc_articles_count( $user_id );
		// $profile_links[] = array(
		// 'title' => sprintf( __( 'MLMMC Articles (%d)', 'labgenz-community-management' ), $mlmmc_articles_count ),
		// 'url' => admin_url( 'edit.php?post_type=mlmmc_article&author=' . $user_id ),
		// 'icon' => 'dashicons-media-document',
		// 'description' => sprintf( __( 'View all MLMMC articles by this user (%d articles)', 'labgenz-community-management' ), $mlmmc_articles_count ),
		// 'target' => '_blank',
		// 'count' => $mlmmc_articles_count
		// );

		// User posts/activity
		$profile_links[] = [
			'title'       => __( 'User Posts', 'labgenz-community-management' ),
			'url'         => admin_url( 'edit.php?post_type=post&author=' . $user_id ),
			'icon'        => 'dashicons-admin-post',
			'description' => __( 'View all posts by this user', 'labgenz-community-management' ),
			'target'      => '_blank',
		];

		return $profile_links;
	}

	/**
	 * Get request details for a user
	 *
	 * @param int $user_id User ID
	 * @return array|null
	 */
	public function get_request_details( int $user_id ): ?array {
		// Get request data
		$request_data = $this->org_access->get_user_request_data( $user_id );
		if ( ! $request_data ) {
			return null;
		}

		// Get user info
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return null;
		}

		// Get status
		$status = $this->org_access->get_user_request_status( $user_id );

		// Get user groups and courses
		$groups  = $this->get_user_groups( $user_id );
		$courses = $this->get_user_courses( $user_id );
		// $mlmmc_articles = $this->get_user_mlmmc_articles( $user_id );

		return [
			'user'         => [
				'id'              => $user->ID,
				'display_name'    => $user->display_name,
				'user_email'      => $user->user_email,
				'user_registered' => $user->user_registered,
			],
			'request'      => $request_data,
			'status'       => $status,
			'status_label' => $this->get_status_label( $status ),
			'groups'       => $groups,
			'courses'      => $courses,
			// 'mlmmc_articles' => $mlmmc_articles,
		];
	}

	/**
	 * Process admin action
	 *
	 * @param int    $user_id User ID
	 * @param string $action Action type
	 * @param string $admin_note Admin note
	 * @return mixed
	 */
	public function process_admin_action( int $user_id, string $action, string $admin_note ) {
		return $this->org_access->process_admin_action( $user_id, $action, $admin_note );
	}

	/**
	 * Format groups for display
	 *
	 * @param array $groups User groups
	 * @return string
	 */
	public function format_groups_display( array $groups ): string {
		if ( empty( $groups ) ) {
			return '<span class="no-data">' . __( 'No groups', 'labgenz-community-management' ) . '</span>';
		}

		$output      = '';
		$count       = 0;
		$max_display = 3;

		foreach ( $groups as $group ) {
			if ( $count < $max_display ) {
				$status_class = $group['status'] === 'public' ? 'public' : 'private';
				$output      .= '<div class="group-item ' . esc_attr( $status_class ) . '">';

				if ( ! empty( $group['url'] ) ) {
					$output .= '<a href="' . esc_url( $group['url'] ) . '" target="_blank" title="' . esc_attr( $group['name'] ) . '">';
				}

				$output .= '<span class="group-name">' . esc_html( $group['name'] ) . '</span>';
				$output .= '<small class="group-members">(' . intval( $group['member_count'] ) . ' members)</small>';

				if ( ! empty( $group['url'] ) ) {
					$output .= '</a>';
				}

				$output .= '</div>';
				++$count;
			}
		}

		$remaining = count( $groups ) - $max_display;
		if ( $remaining > 0 ) {
			$output .= '<div class="more-groups">+' . $remaining . ' more</div>';
		}

		return $output;
	}

	/**
	 * Format courses for display
	 *
	 * @param array $courses User courses
	 * @return string
	 */
	public function format_courses_display( array $courses ): string {
		if ( empty( $courses ) ) {
			return '<span class="no-data">' . __( 'No courses', 'labgenz-community-management' ) . '</span>';
		}

		$output      = '';
		$count       = 0;
		$max_display = 3;

		foreach ( $courses as $course ) {
			if ( $count < $max_display ) {
				$output .= '<div class="course-item">';

				if ( ! empty( $course['url'] ) ) {
					$output .= '<a href="' . esc_url( $course['url'] ) . '" target="_blank" title="' . esc_attr( $course['title'] ) . '">';
				}

				$output .= '<span class="course-title">' . esc_html( $course['title'] ) . '</span>';

				// Show progress if available
				if ( isset( $course['progress'] ) && $course['total'] > 0 ) {
					$percentage = round( ( $course['completed'] / $course['total'] ) * 100 );
					$output    .= '<small class="course-progress">(' . $percentage . '% complete)</small>';
				}

				if ( ! empty( $course['url'] ) ) {
					$output .= '</a>';
				}

				$output .= '</div>';
				++$count;
			}
		}

		$remaining = count( $courses ) - $max_display;
		if ( $remaining > 0 ) {
			$output .= '<div class="more-courses">+' . $remaining . ' more</div>';
		}

		return $output;
	}

	/**
	 * Format MLMMC articles for display
	 *
	 * @param array $articles User MLMMC articles
	 * @return string
	 */
	public function format_mlmmc_articles_display( array $articles ): string {
		if ( empty( $articles ) ) {
			return '<span class="no-data">' . __( 'No MLMMC articles', 'labgenz-community-management' ) . '</span>';
		}

		$output      = '';
		$count       = 0;
		$max_display = 3;

		foreach ( $articles as $article ) {
			if ( $count < $max_display ) {
				$status_class = $article['status'] === 'publish' ? 'published' : $article['status'];
				$output      .= '<div class="article-item ' . esc_attr( $status_class ) . '">';

				if ( ! empty( $article['url'] ) ) {
					$output .= '<a href="' . esc_url( $article['url'] ) . '" target="_blank" title="' . esc_attr( $article['title'] ) . '">';
				}

				$output .= '<span class="article-title">' . esc_html( $article['title'] ) . '</span>';
				$output .= '<small class="article-status">(' . esc_html( ucfirst( $article['status'] ) ) . ')</small>';

				if ( ! empty( $article['url'] ) ) {
					$output .= '</a>';
				}

				$output .= '</div>';
				++$count;
			}
		}

		$remaining = count( $articles ) - $max_display;
		if ( $remaining > 0 ) {
			$output .= '<div class="more-articles">+' . $remaining . ' more</div>';
		}

		return $output;
	}

	/**
	 * Get count of user's MLMMC articles
	 *
	 * No direct author identification is attached to the articles,
	 * so this method is not being used for now as it would require
	 * additional logic to associate articles with users.
	 *
	 * @param int $user_id User ID
	 * @return int
	 */
	private function get_user_mlmmc_articles_count( int $user_id ): int {
		$articles = get_posts(
			[
				'post_type'      => 'mlmmc_artiicle',
				'author'         => $user_id,
				'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
				'posts_per_page' => -1,
				'fields'         => 'ids', // Only get IDs - yea, performance optimization
			]
		);

		return count( $articles );
	}

	/**
	 * Get user's MLMMC articles details
	 *
	 * @param int $user_id User ID
	 * @return array
	 */
	public function get_user_mlmmc_articles( int $user_id ): array {
		$articles = get_posts(
			[
				'post_type'      => 'mlmmc_article',
				'author'         => $user_id,
				'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			]
		);

		$articles_data = [];

		foreach ( $articles as $article ) {
			$articles_data[] = [
				'id'       => $article->ID,
				'title'    => $article->post_title,
				'status'   => $article->post_status,
				'date'     => $article->post_date,
				'url'      => get_permalink( $article->ID ),
				'edit_url' => get_edit_post_link( $article->ID ),
				'excerpt'  => wp_trim_words( $article->post_content, 20 ),
			];
		}

		return $articles_data;
	}
}
