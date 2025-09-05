<?php

declare(strict_types=1);

namespace LABGENZ_CM\Subscriptions\Shortcodes;

use LABGENZ_CM\Subscriptions\SubscriptionHandler;
use LABGENZ_CM\Subscriptions\Helpers\SubscriptionStorage;
use LABGENZ_CM\Subscriptions\Helpers\SubscriptionResources;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the subscription details display shortcode
 *
 * @package    Labgenz_Community_Management
 * @subpackage Labgenz_Community_Management/Subscriptions/Shortcodes
 */
class SubscriptionDetailsShortcode {

	/**
	 * Shortcode tag
	 */
	const SHORTCODE = 'labgenz_subscription_details';

	/**
	 * Asset handles
	 */
	const ASSET_HANDLE_CSS = 'labgenz-subscription-details-css';
	const ASSET_HANDLE_JS  = 'labgenz-subscription-details-js';

	/**
	 * Initialize the shortcode
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks(): void {
		add_shortcode( self::SHORTCODE, [ $this, 'render_shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
	}

	/**
	 * Register scripts and styles
	 */
	public function register_assets(): void {
		// Register CSS
		wp_register_style(
			self::ASSET_HANDLE_CSS,
			LABGENZ_CM_URL . 'src/Public/assets/css/subscription-details.css',
			[],
			'1.0.0'
		);

		// Register JS
		wp_register_script(
			self::ASSET_HANDLE_JS,
			LABGENZ_CM_URL . 'src/Public/assets/js/subscription-details.js',
			[ 'jquery', 'sweetalert2' ],
			'1.0.0',
			true
		);

		// Localize script
		wp_localize_script(
			self::ASSET_HANDLE_JS,
			'labgenzSubscriptionData',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'labgenz_subscription_details_nonce' ),
				'i18n'    => [
					'renewalTitle'   => __( 'Subscription Renewal', 'labgenz-community-management' ),
					'renewalMessage' => __( 'This feature will be implemented soon.' ),
					'renewalButton'  => __( 'Ok, Got it!', 'labgenz-community-management' ),
				],
			]
		);
	}

	/**
	 * Render the shortcode
	 *
	 * @param array $atts Shortcode attributes
	 * @return string
	 */
	public function render_shortcode( array $atts = [] ): string {
		// Enqueue assets
		wp_enqueue_style( self::ASSET_HANDLE_CSS );
		wp_enqueue_script( self::ASSET_HANDLE_JS );

		// Get current user
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return '<div class="labgenz-subscription-notice labgenz-subscription-notice--error">' .
					__( 'You must be logged in to view subscription details.', 'labgenz-community-management' ) .
					'</div>';
		}

		// Get all user subscriptions using the SubscriptionStorage helper
		// Filter out any deleted subscriptions
		$all_subscriptions  = SubscriptionStorage::get_user_subscriptions( $user_id );
		$user_subscriptions = [];

		foreach ( $all_subscriptions as $subscription ) {
			if ( ! isset( $subscription['status'] ) || $subscription['status'] !== 'deleted' ) {
				$user_subscriptions[] = $subscription;
			}
		}

		if ( current_user_can( 'administrator' ) ) {
			return '<div class="labgenz-subscription-notice labgenz-subscription-notice--warning">' .
				__( "You're an admin, you can view all subscriptions in <a href='https://v2mlmmasteryclub.labgenz.com/wp-admin/admin.php?page=mlmmc-subscriptions'>Subscriptions</a>.", 'labgenz-community-management' ) .
				'</div>';
		}

		if ( ! is_array( $user_subscriptions ) || empty( $user_subscriptions ) ) {
			return '<div class="labgenz-subscription-notice labgenz-subscription-notice--warning">' .
					__( 'You do not have any active subscriptions.', 'labgenz-community-management' ) .
					'</div>';
		}

		// Get combined subscription resources from all subscriptions
		$subscription_resources = SubscriptionHandler::get_user_subscription_resources( $user_id );

		// Define helper functions for the template
		$get_formatted_subscription_name = function ( $subscription_type ) {
			return $this->get_formatted_subscription_name( $subscription_type );
		};

		$get_formatted_benefits = function ( $resources ) {
			return $this->get_formatted_benefits( $resources );
		};

		// Start output buffer
		ob_start();

		// Include template
		$template_path = LABGENZ_CM_TEMPLATES_DIR . '/subscriptions/subscription-details.php';

		if ( file_exists( $template_path ) ) {
			// Make variables accessible in template
			include $template_path;
		} else {
			// Fallback to inline template if file doesn't exist
			$this->render_inline_template(
				$user_subscriptions,
				$subscription_resources
			);
		}

		// Return the output
		return ob_get_clean();
	}

	/**
	 * Render inline template if the template file doesn't exist
	 *
	 * @param array $user_subscriptions    All user subscriptions
	 * @param array $subscription_resources Subscription resources
	 */
	private function render_inline_template(
		array $user_subscriptions,
		array $subscription_resources
	): void {
		// Process each subscription
		$active_subscriptions  = [];
		$expired_subscriptions = [];

		foreach ( $user_subscriptions as $subscription ) {
			// Skip non-active subscriptions
			if ( ! isset( $subscription['status'] ) || $subscription['status'] !== 'active' ) {
				continue;
			}

			if ( ! isset( $subscription['expires'] ) ) {
				continue;
			}

			$days_until_expiry = SubscriptionStorage::calculate_remaining_days( $subscription );
			$is_expired        = $days_until_expiry <= 0;

			if ( $is_expired ) {
				$expired_subscriptions[] = [
					'type'              => $subscription['type'],
					'name'              => $this->get_formatted_subscription_name( $subscription['type'] ),
					'expires'           => $subscription['expires'],
					'created'           => $subscription['created'] ?? null,
					'days_until_expiry' => $days_until_expiry,
				];
			} else {
				$active_subscriptions[] = [
					'type'              => $subscription['type'],
					'name'              => $this->get_formatted_subscription_name( $subscription['type'] ),
					'status'            => $subscription['status'],
					'expires'           => $subscription['expires'],
					'created'           => $subscription['created'] ?? null,
					'days_until_expiry' => $days_until_expiry,
					'needs_renewal'     => $days_until_expiry <= 15,
				];
			}
		}
		?>
		<div class="labgenz-subscription-details">
			<h2 class="labgenz-subscription-details__title"><?php esc_html_e( 'Your Subscription Details', 'labgenz-community-management' ); ?></h2>
			
			<?php
			// Show warning for subscriptions that need renewal
			foreach ( $active_subscriptions as $sub ) {
				if ( $sub['needs_renewal'] ) :
					?>
				<div class="labgenz-subscription-notice labgenz-subscription-notice--warning">
					<?php
					printf(
						esc_html__( 'Your %1$s subscription will expire in %2$d days. Please renew to maintain access to your benefits.', 'labgenz-community-management' ),
						'<strong>' . esc_html( $sub['name'] ) . '</strong>',
						$sub['days_until_expiry']
					);
					?>
					<button class="labgenz-subscription-renewal-btn" data-subscription-type="<?php echo esc_attr( $sub['type'] ); ?>"><?php esc_html_e( 'Renew Now', 'labgenz-community-management' ); ?></button>
				</div>
					<?php
				endif;
			}

			// Show message for expired subscriptions
			if ( ! empty( $expired_subscriptions ) ) :
				?>
				<div class="labgenz-subscription-notice labgenz-subscription-notice--error">
					<?php
					if ( count( $expired_subscriptions ) === 1 ) {
						printf(
							esc_html__( 'Your %s subscription has expired. Please renew to regain access to your benefits.', 'labgenz-community-management' ),
							'<strong>' . esc_html( $expired_subscriptions[0]['name'] ) . '</strong>'
						);
					} else {
						esc_html_e( 'Some of your subscriptions have expired. Please renew to regain access to your benefits.', 'labgenz-community-management' );
					}
					?>
					<button class="labgenz-subscription-renewal-btn"><?php esc_html_e( 'Renew Now', 'labgenz-community-management' ); ?></button>
				</div>
			<?php endif; ?>

			<!-- Active Subscriptions -->
			<?php if ( ! empty( $active_subscriptions ) ) : ?>
				<?php if ( count( $active_subscriptions ) > 1 ) : ?>
					<!-- Multiple Subscriptions Table -->
					<div class="labgenz-subscription-table-container">
						<table class="labgenz-subscription-table labgenz-multiple-subscriptions-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Subscription Plan', 'labgenz-community-management' ); ?></th>
									<th><?php esc_html_e( 'Status', 'labgenz-community-management' ); ?></th>
									<th><?php esc_html_e( 'Start Date', 'labgenz-community-management' ); ?></th>
									<th><?php esc_html_e( 'Expiry Date', 'labgenz-community-management' ); ?></th>
									<th><?php esc_html_e( 'Days Remaining', 'labgenz-community-management' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'labgenz-community-management' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $active_subscriptions as $subscription ) : ?>
									<tr>
										<td><?php echo esc_html( $subscription['name'] ); ?></td>
										<td>
											<span class="labgenz-subscription-status labgenz-subscription-status--<?php echo esc_attr( strtolower( $subscription['status'] ) ); ?>">
												<?php echo esc_html( ucfirst( $subscription['status'] ) ); ?>
											</span>
										</td>
										<td>
											<?php
											if ( ! empty( $subscription['created'] ) ) {
												echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $subscription['created'] ) ) );
											} else {
												echo esc_html__( 'N/A', 'labgenz-community-management' );
											}
											?>
										</td>
										<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $subscription['expires'] ) ) ); ?></td>
										<td><?php echo esc_html( $subscription['days_until_expiry'] ); ?></td>
										<td>
											<?php if ( $subscription['needs_renewal'] ) : ?>
												<button class="labgenz-subscription-renewal-btn labgenz-btn labgenz-btn--small" 
														data-subscription-type="<?php echo esc_attr( $subscription['type'] ); ?>">
													<?php esc_html_e( 'Renew', 'labgenz-community-management' ); ?>
												</button>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php else : ?>
					<!-- Single Subscription Table -->
					<?php $subscription = $active_subscriptions[0]; ?>
					<div class="labgenz-subscription-table-container">
						<table class="labgenz-subscription-table">
							<tbody>
								<tr>
									<th><?php esc_html_e( 'Subscription Plan', 'labgenz-community-management' ); ?></th>
									<td><?php echo esc_html( $subscription['name'] ); ?></td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Status', 'labgenz-community-management' ); ?></th>
									<td>
										<span class="labgenz-subscription-status labgenz-subscription-status--<?php echo esc_attr( strtolower( $subscription['status'] ) ); ?>">
											<?php echo esc_html( ucfirst( $subscription['status'] ) ); ?>
										</span>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Start Date', 'labgenz-community-management' ); ?></th>
									<td>
										<?php
										if ( ! empty( $subscription['created'] ) ) {
											echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $subscription['created'] ) ) );
										} else {
											echo esc_html__( 'N/A', 'labgenz-community-management' );
										}
										?>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Expiry Date', 'labgenz-community-management' ); ?></th>
									<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $subscription['expires'] ) ) ); ?></td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Days Remaining', 'labgenz-community-management' ); ?></th>
									<td><?php echo esc_html( $subscription['days_until_expiry'] ); ?></td>
								</tr>
							</tbody>
						</table>
						
						<?php if ( $subscription['needs_renewal'] ) : ?>
							<div class="labgenz-subscription-actions">
								<button class="labgenz-subscription-renewal-btn labgenz-btn labgenz-btn--primary" 
										data-subscription-type="<?php echo esc_attr( $subscription['type'] ); ?>">
									<?php esc_html_e( 'Renew Subscription', 'labgenz-community-management' ); ?>
								</button>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<!-- Subscription Benefits Section -->
			<h3 class="labgenz-subscription-details__subtitle"><?php esc_html_e( 'Your Subscription Benefits', 'labgenz-community-management' ); ?></h3>
			
			<div class="labgenz-subscription-benefits">
				<ul class="labgenz-subscription-benefits-list">
					<?php
					$benefits = $this->get_formatted_benefits( $subscription_resources );
					foreach ( $benefits as $benefit ) :
						?>
						<li class="labgenz-subscription-benefit <?php echo $benefit['enabled'] ? 'is-enabled' : 'is-disabled'; ?>">
							<span class="labgenz-subscription-benefit-icon"><?php echo $benefit['enabled'] ? '✓' : '×'; ?></span>
							<span class="labgenz-subscription-benefit-text"><?php echo esc_html( $benefit['label'] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<!-- Expired Subscriptions Section -->
			<?php if ( ! empty( $expired_subscriptions ) ) : ?>
				<div class="labgenz-expired-subscriptions">
					<h3 class="labgenz-subscription-details__subtitle"><?php esc_html_e( 'Expired Subscriptions', 'labgenz-community-management' ); ?></h3>
					
					<div class="labgenz-subscription-table-container">
						<table class="labgenz-subscription-table labgenz-expired-subscriptions-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Subscription Plan', 'labgenz-community-management' ); ?></th>
									<th><?php esc_html_e( 'Start Date', 'labgenz-community-management' ); ?></th>
									<th><?php esc_html_e( 'Expired On', 'labgenz-community-management' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'labgenz-community-management' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $expired_subscriptions as $expired ) : ?>
									<tr>
										<td><?php echo esc_html( $expired['name'] ); ?></td>
										<td>
											<?php
											if ( ! empty( $expired['created'] ) ) {
												echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $expired['created'] ) ) );
											} else {
												echo esc_html__( 'N/A', 'labgenz-community-management' );
											}
											?>
										</td>
										<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $expired['expires'] ) ) ); ?></td>
										<td>
											<button class="labgenz-subscription-renewal-btn labgenz-btn labgenz-btn--small"
													data-subscription-type="<?php echo esc_attr( $expired['type'] ); ?>">
												<?php esc_html_e( 'Renew', 'labgenz-community-management' ); ?>
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			<?php endif; ?>

			<style>
				/* Styling for multiple subscriptions table */
				.labgenz-multiple-subscriptions-table {
					width: 100%;
					border-collapse: collapse;
					margin-bottom: 20px;
				}
				
				.labgenz-multiple-subscriptions-table th,
				.labgenz-multiple-subscriptions-table td {
					padding: 10px;
					text-align: left;
					border-bottom: 1px solid #ddd;
				}
				
				.labgenz-multiple-subscriptions-table th {
					background-color: #f5f5f5;
					font-weight: bold;
				}
				
				.labgenz-multiple-subscriptions-table tr:hover {
					background-color: #f9f9f9;
				}
				
				.labgenz-btn--small {
					padding: 5px 10px;
					font-size: 12px;
				}
			</style>
		</div>
		<?php
	}

	/**
	 * Get formatted subscription name
	 *
	 * @param string $subscription_type Subscription type
	 * @return string
	 */
	private function get_formatted_subscription_name( string $subscription_type ): string {
		$subscription_names = [
			'basic'                             => __( 'Discovery Annual Subscription', 'labgenz-community-management' ),
			'monthly-basic-subscription'        => __( 'Discovery Monthly Subscription', 'labgenz-community-management' ),
			'organization'                      => __( 'Organization Subscription', 'labgenz-community-management' ),
			'monthly-organization-subscription' => __( 'Organization Monthly Subscription', 'labgenz-community-management' ),
			'articles-annual-subscription'      => __( 'Articles Annual Subscription', 'labgenz-community-management' ),
			'articles-monthly-subscription'     => __( 'Articles Monthly Subscription', 'labgenz-community-management' ),
		];

		return $subscription_names[ $subscription_type ] ?? ucfirst( str_replace( '-', ' ', $subscription_type ) );
	}

	/**
	 * Get formatted subscription benefits
	 *
	 * @param array $resources Subscription resources
	 * @return array
	 */
	private function get_formatted_benefits( array $resources ): array {
		$benefits = [];

		// Course categories
		if ( isset( $resources['course_categories'] ) ) {
			$course_levels = [
				'basic-courses'        => __( 'Discovery Courses Access', 'labgenz-community-management' ),
				'organization-courses' => __( 'Organization Courses Access', 'labgenz-community-management' ),
				'advanced-courses'     => __( 'Advanced Courses Access', 'labgenz-community-management' ),
			];

			foreach ( $course_levels as $level => $label ) {
				$benefits[] = [
					'label'   => $label,
					'enabled' => in_array( $level, $resources['course_categories'], true ),
				];
			}
		}

		// Group creation
		$benefits[] = [
			'label'   => __( 'Group Creation', 'labgenz-community-management' ),
			'enabled' => $resources['group_creation'] ?? false,
		];

		// Organization access
		$benefits[] = [
			'label'   => __( 'Organization Access', 'labgenz-community-management' ),
			'enabled' => $resources['organization_access'] ?? false,
		];

		// Advanced features
		$benefits[] = [
			'label'   => __( 'Advanced Features', 'labgenz-community-management' ),
			'enabled' => $resources['advanced_features'] ?? false,
		];

		// Success Library Articles access
		$benefits[] = [
			'label'   => __( 'Success Library Articles Access', 'labgenz-community-management' ),
			'enabled' => $resources['can_view_mlm_articles'] ?? false,
		];

		// Success Library Articles creation
		if ( isset( $resources['can_create_articles'] ) ) {
			$benefits[] = [
				'label'   => __( 'Success Library Articles Creation', 'labgenz-community-management' ),
				'enabled' => $resources['can_create_articles'],
			];
		}

		// Success Library Articles editing
		if ( isset( $resources['can_edit_articles'] ) ) {
			$benefits[] = [
				'label'   => __( 'Success Library Articles Editing', 'labgenz-community-management' ),
				'enabled' => $resources['can_edit_articles'],
			];
		}

		// Success Library Articles filtering
		if ( isset( $resources['can_filter_articles'] ) ) {
			$benefits[] = [
				'label'   => __( 'Success Library Articles Filtering', 'labgenz-community-management' ),
				'enabled' => $resources['can_filter_articles'],
			];
		}

		// Pre-release articles
		if ( isset( $resources['can_view_pre_release_articles'] ) ) {
			$benefits[] = [
				'label'   => __( 'Pre-Release Articles Access', 'labgenz-community-management' ),
				'enabled' => $resources['can_view_pre_release_articles'],
			];
		}

		return $benefits;
	}
}
